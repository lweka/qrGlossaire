<?php
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/security.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Token de sécurité invalide.";
    }

    $fullName = sanitizeInput($_POST['full_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $eventType = sanitizeInput($_POST['event_type'] ?? '');
    $eventDate = sanitizeInput($_POST['event_date'] ?? '');
    $password = $_POST['password'] ?? '';
    $recaptchaToken = sanitizeInput($_POST['recaptcha_token'] ?? '');

    if ($fullName === '' || $email === '' || $phone === '' || $eventType === '' || $eventDate === '' || $password === '') {
        $errors[] = "Veuillez remplir tous les champs requis.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Adresse email invalide.";
    } else {
        $emailDomain = strtolower(substr(strrchr($email, '@') ?: '', 1));
        if ($emailDomain !== 'gmail.com') {
            $errors[] = "Seules les adresses Gmail sont acceptées.";
        }
    }

    if (strlen($password) < 8) {
        $errors[] = "Le mot de passe doit contenir au moins 8 caractères.";
    }

    if ($recaptchaToken === '') {
        $errors[] = "Veuillez valider le reCAPTCHA.";
    }

    if (empty($errors)) {
        $url = 'https://recaptchaenterprise.googleapis.com/v1/projects/' . RECAPTCHA_PROJECT_ID . '/assessments?key=' . RECAPTCHA_API_KEY;
        $payload = json_encode([
            'event' => [
                'token' => $recaptchaToken,
                'expectedAction' => RECAPTCHA_ACTION_REGISTER,
                'siteKey' => RECAPTCHA_SITE_KEY,
            ],
        ], JSON_UNESCAPED_SLASHES);

        $response = null;
        $errorDetail = null;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);
            if ($curlError) {
                $errorDetail = $curlError;
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $payload,
                    'timeout' => 10,
                ],
            ]);
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                $errorDetail = 'file_get_contents a échoué.';
            }
        }

        if (!$response) {
            $errors[] = APP_DEBUG && $errorDetail
                ? "Erreur reCAPTCHA. Détail: " . $errorDetail
                : "Erreur reCAPTCHA. Veuillez réessayer.";
        } else {
            $result = json_decode($response, true);
            $tokenProps = $result['tokenProperties'] ?? [];
            $riskScore = $result['riskAnalysis']['score'] ?? 0;
            $action = $tokenProps['action'] ?? '';
            $valid = $tokenProps['valid'] ?? false;
            $apiError = $result['error']['message'] ?? null;

            if ($apiError) {
                $errors[] = APP_DEBUG
                    ? "Erreur reCAPTCHA. Détail: " . $apiError
                    : "Erreur reCAPTCHA. Veuillez réessayer.";
            }

            if (!$valid || $action !== RECAPTCHA_ACTION_REGISTER) {
                $errors[] = "Vérification reCAPTCHA invalide.";
            } elseif ($riskScore < RECAPTCHA_MIN_SCORE) {
                $errors[] = "Activité suspecte détectée. Veuillez réessayer.";
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = "Un compte existe déjà avec cet email.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, full_name, phone) VALUES (:email, :password_hash, :full_name, :phone)");
            $stmt->execute([
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'full_name' => $fullName,
                'phone' => $phone,
            ]);
            $success = true;
        }
    }
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<section class="container section">
    <div class="form-card">
        <div class="section-title">
            <span>Inscription</span>
            <h2>Créer un compte organisateur</h2>
        </div>
        <?php if (!empty($errors)): ?>
            <div class="card" style="margin-bottom: 18px;">
                <p style="color: #fca5a5;"><?= implode('<br>', $errors); ?></p>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="card" style="margin-bottom: 18px;">
                <p style="color: #a7f3d0;">Demande reçue. Votre compte est en attente de validation admin.</p>
            </div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
            <div class="form-group">
                <label for="full_name">Nom complet</label>
                <input id="full_name" name="full_name" type="text" placeholder="Ex : Marie Dubois" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" placeholder="contact@email.com" required>
            </div>
            <div class="form-group">
                <label for="phone">Téléphone</label>
                <input id="phone" name="phone" type="tel" placeholder="+242 06 000 0000" required>
            </div>
            <div class="form-group">
                <label for="event_type">Type d'événement</label>
                <select id="event_type" name="event_type" required>
                    <option value="">Sélectionner</option>
                    <option value="wedding">Mariage</option>
                    <option value="birthday">Anniversaire</option>
                    <option value="corporate">Corporate</option>
                    <option value="other">Autre</option>
                </select>
            </div>
            <div class="form-group" id="activity_group" style="display: none;">
                <label for="activity">Votre activité</label>
                <input id="activity" name="activity" type="text" placeholder="Ex : Organisation de soirées, coaching, etc.">
            </div>
            <div class="form-group">
                <label for="event_date">Date événement</label>
                <input id="event_date" name="event_date" type="date" required>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input id="password" name="password" type="password" minlength="8" placeholder="Min. 8 caractères" required>
            </div>
                        <input type="hidden" name="recaptcha_token" id="recaptcha_token">
            <button class="button primary" type="submit">Soumettre ma demande</button>
            <p style="margin-top: 14px; color: var(--muted);">Statut attendu : pending (validation admin).</p>
        </form>
    </div>
</section>
<script src="https://www.google.com/recaptcha/enterprise.js?render=<?= RECAPTCHA_SITE_KEY; ?>"></script>
<script>
    const form = document.querySelector('form');
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const tokenField = document.getElementById('recaptcha_token');
        const token = await grecaptcha.enterprise.execute('<?= RECAPTCHA_SITE_KEY; ?>', {action: '<?= RECAPTCHA_ACTION_REGISTER; ?>'});
        tokenField.value = token;
        form.submit();
    });
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
