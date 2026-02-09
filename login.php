<?php
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/security.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Token de sécurité invalide.";
    }

    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = "Veuillez saisir vos identifiants.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors[] = "Identifiants incorrects.";
        } elseif ($user['status'] !== 'active') {
            $errors[] = "Votre compte est actuellement : " . $user['status'] . ".";
        } else {
            ensureSession();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['full_name'] = $user['full_name'];
            header('Location: /dashboard.php');
            exit;
        }
    }
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<section class="container section">
    <div class="form-card">
        <div class="section-title">
            <span>Connexion</span>
            <h2>Accéder à votre espace</h2>
        </div>
        <?php if (!empty($errors)): ?>
            <div class="card" style="margin-bottom: 18px;">
                <p style="color: #fca5a5;"><?= implode('<br>', $errors); ?></p>
            </div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
            <div class="form-group">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" placeholder="contact@email.com" required>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input id="password" name="password" type="password" required>
            </div>
            <button class="button primary" type="submit">Se connecter</button>
            <p style="margin-top: 14px; color: var(--muted);">Besoin d'un compte ? <a href="<?= $baseUrl; ?>/register.php">Créer un compte</a></p>
        </form>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
