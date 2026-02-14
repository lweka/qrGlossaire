<?php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/security.php';
require_once __DIR__ . '/../app/config/constants.php';

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$errors = [];
$adminTableReady = true;
$hasAdminAccount = false;

ensureSession();
if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . $baseUrl . '/admin/dashboard');
    exit;
}

try {
    $countStmt = $pdo->query('SELECT COUNT(*) FROM admins');
    $hasAdminAccount = ((int) $countStmt->fetchColumn()) > 0;
} catch (PDOException $exception) {
    $adminTableReady = false;
    $errors[] = APP_DEBUG
        ? 'Table admins introuvable. Creez-la d abord. Detail: ' . $exception->getMessage()
        : 'La configuration administrateur est incomplete.';
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

    if ($adminTableReady && empty($errors)) {
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, (string) ($admin['password_hash'] ?? ''))) {
            $errors[] = 'Identifiants administrateur incorrects.';
        } elseif (($admin['status'] ?? 'active') !== 'active') {
            $errors[] = "Le compte administrateur est actuellement : " . ($admin['status'] ?? 'inconnu') . '.';
        } else {
            unset($_SESSION['user_id'], $_SESSION['user_type'], $_SESSION['full_name']);
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_email'] = (string) ($admin['email'] ?? '');
            $_SESSION['admin_name'] = (string) ($admin['full_name'] ?? 'Administrateur');

            $updateLastLogin = $pdo->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = :id');
            $updateLastLogin->execute(['id' => (int) $admin['id']]);

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
            <h2>Connexion administrateur principal</h2>
        </div>
        <?php if (!empty($errors)): ?>
            <div class="card" style="margin-bottom: 18px;">
                <p style="color: #fca5a5;"><?= implode('<br>', $errors); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($adminTableReady && !$hasAdminAccount): ?>
            <div class="card" style="margin-bottom: 18px;">
                <p style="color: #0f172a; margin-bottom: 8px;">Aucun administrateur principal n existe encore.</p>
                <p style="color: var(--text-mid); margin-bottom: 8px;">Creez le premier admin avec :</p>
                <code style="display:block; word-break: break-all;">php scripts/create_admin.php --email=\"votre@email.com\" --password=\"MotDePasseFort123!\" --name=\"Admin Principal\"</code>
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
