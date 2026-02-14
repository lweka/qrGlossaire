<?php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/security.php';
require_once __DIR__ . '/../app/config/constants.php';

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$errors = [];

ensureSession();
if (!empty($_SESSION['user_id']) && (string) ($_SESSION['user_type'] ?? '') === 'admin') {
    header('Location: ' . $baseUrl . '/admin/dashboard');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de securite invalide.';
    }

    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Veuillez saisir vos identifiants administrateur.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email AND user_type = 'admin' LIMIT 1");
        $stmt->execute(['email' => $email]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            $errors[] = 'Identifiants administrateur incorrects.';
        } elseif (($admin['status'] ?? '') !== 'active') {
            $errors[] = "Le compte administrateur est actuellement : " . ($admin['status'] ?? 'inconnu') . '.';
        } else {
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['user_type'] = $admin['user_type'];
            $_SESSION['full_name'] = $admin['full_name'];
            header('Location: ' . $baseUrl . '/admin/dashboard');
            exit;
        }
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<section class="container section">
    <div class="form-card">
        <div class="section-title">
            <span>Administration</span>
            <h2>Connexion administrateur</h2>
        </div>
        <?php if (!empty($errors)): ?>
            <div class="card" style="margin-bottom: 18px;">
                <p style="color: #fca5a5;"><?= implode('<br>', $errors); ?></p>
            </div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
            <div class="form-group">
                <label for="email">Email admin</label>
                <input id="email" name="email" type="email" placeholder="admin@email.com" required>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input id="password" name="password" type="password" required>
            </div>
            <button class="button primary" type="submit">Se connecter (admin)</button>
            <p style="margin-top: 14px; color: var(--muted);">Espace client : <a href="<?= $baseUrl; ?>/login">connexion organisateur</a></p>
        </form>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
