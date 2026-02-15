<?php
require_once __DIR__ . '/includes/auth-check.php';
requireRole('organizer');

$dashboardSection = 'settings';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$message = null;
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token de securite invalide.';
        $messageType = 'error';
    } else {
        $action = sanitizeInput($_POST['action'] ?? '');
        if ($action === 'update-profile') {
            $fullName = sanitizeInput($_POST['full_name'] ?? '');
            $phone = sanitizeInput($_POST['phone'] ?? '');

            if ($fullName === '') {
                $message = 'Le nom complet est obligatoire.';
                $messageType = 'error';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET full_name = :full_name, phone = :phone WHERE id = :id');
                $stmt->execute([
                    'full_name' => $fullName,
                    'phone' => $phone !== '' ? $phone : null,
                    'id' => $userId,
                ]);

                $_SESSION['full_name'] = $fullName;
                $message = 'Profil mis a jour.';
                $messageType = 'success';
            }
        } elseif ($action === 'update-password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                $message = 'Tous les champs mot de passe sont obligatoires.';
                $messageType = 'error';
            } elseif (strlen($newPassword) < 8) {
                $message = 'Le nouveau mot de passe doit contenir au moins 8 caracteres.';
                $messageType = 'error';
            } elseif ($newPassword !== $confirmPassword) {
                $message = 'La confirmation du mot de passe ne correspond pas.';
                $messageType = 'error';
            } else {
                $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $userId]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($currentPassword, (string) ($user['password_hash'] ?? ''))) {
                    $message = 'Mot de passe actuel incorrect.';
                    $messageType = 'error';
                } else {
                    $updateStmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
                    $updateStmt->execute([
                        'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                        'id' => $userId,
                    ]);
                    $message = 'Mot de passe mis a jour.';
                    $messageType = 'success';
                }
            }
        }
    }
}

$profileStmt = $pdo->prepare('SELECT full_name, email, phone FROM users WHERE id = :id LIMIT 1');
$profileStmt->execute(['id' => $userId]);
$profile = $profileStmt->fetch() ?: ['full_name' => '', 'email' => '', 'phone' => ''];
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="dashboard">
    <?php include __DIR__ . '/includes/organizer-sidebar.php'; ?>
    <main class="dashboard-content">
        <div class="section-title">
            <span>Parametres</span>
            <h2>Configuration du compte</h2>
        </div>
        <div style="margin: 0 0 18px;">
            <a class="button ghost" href="<?= $baseUrl; ?>/dashboard">Retour au dashboard</a>
        </div>

        <?php if ($message): ?>
            <div class="card" style="margin-bottom: 18px;">
                <?php $color = $messageType === 'error' ? '#dc2626' : '#166534'; ?>
                <p style="color: <?= $color; ?>;"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-bottom: 22px;">
            <h3 style="margin-bottom: 12px;">Profil</h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
                <input type="hidden" name="action" value="update-profile">
                <div class="form-group">
                    <label for="full_name">Nom complet</label>
                    <input id="full_name" name="full_name" type="text" value="<?= htmlspecialchars((string) ($profile['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="<?= htmlspecialchars((string) ($profile['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" disabled>
                </div>
                <div class="form-group">
                    <label for="phone">Telephone</label>
                    <input id="phone" name="phone" type="text" value="<?= htmlspecialchars((string) ($profile['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <button class="button primary" type="submit">Enregistrer le profil</button>
            </form>
        </div>

        <div class="card">
            <h3 style="margin-bottom: 12px;">Securite</h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
                <input type="hidden" name="action" value="update-password">
                <div class="form-group">
                    <label for="current_password">Mot de passe actuel</label>
                    <input id="current_password" name="current_password" type="password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">Nouveau mot de passe</label>
                    <input id="new_password" name="new_password" type="password" minlength="8" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmer le nouveau mot de passe</label>
                    <input id="confirm_password" name="confirm_password" type="password" minlength="8" required>
                </div>
                <button class="button primary" type="submit">Mettre a jour le mot de passe</button>
            </form>
        </div>
    </main>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
