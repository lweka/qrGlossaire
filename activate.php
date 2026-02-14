<?php
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/security.php';
require_once __DIR__ . '/app/config/constants.php';

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

$token = sanitizeInput($_GET['token'] ?? '');
$message = 'Lien invalide.';
$success = false;

if ($token !== '') {
    $stmt = $pdo->prepare("SELECT id, payment_confirmed, token_expiry FROM users WHERE unique_link_token = :token LIMIT 1");
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch();

    if ($user) {
        $expiry = new DateTime($user['token_expiry']);
        if ($expiry < new DateTime()) {
            $message = "Lien expiré. Veuillez contacter l'administrateur.";
        } elseif ((int) $user['payment_confirmed'] !== 1) {
            $message = "Paiement non confirmé. Veuillez contacter l'administrateur.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET status = 'active', unique_link_token = NULL, token_expiry = NULL WHERE id = :id");
            $stmt->execute(['id' => $user['id']]);
            $message = "Votre compte est activé. Vous pouvez vous connecter.";
            $success = true;
        }
    }
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<section class="container section">
    <div class="form-card">
        <div class="section-title">
            <span>Activation</span>
            <h2>Activation du compte</h2>
        </div>
        <p style="color: <?= $success ? '#a7f3d0' : '#fca5a5'; ?>;"><?= $message; ?></p>
        <div style="margin-top: 18px;">
            <a class="button primary" href="<?= $baseUrl; ?>/login">Se connecter</a>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
