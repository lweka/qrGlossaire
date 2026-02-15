<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('admin');
require_once __DIR__ . '/../app/config/constants.php';
require_once __DIR__ . '/../app/helpers/mailer.php';

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

$message = null;
$messageType = 'success';
$generatedLink = null;

if (!empty($_GET['action']) && $_GET['action'] === 'generate-link' && !empty($_GET['id'])) {
    $id = (int) $_GET['id'];

    $userStmt = $pdo->prepare('SELECT id, full_name, email, payment_confirmed FROM users WHERE id = :id LIMIT 1');
    $userStmt->execute(['id' => $id]);
    $targetUser = $userStmt->fetch();

    if (!$targetUser) {
        $messageType = 'error';
        $message = 'Utilisateur introuvable.';
    } elseif ((int) ($targetUser['payment_confirmed'] ?? 0) !== 1) {
        $messageType = 'warning';
        $message = 'Paiement non confirme. Confirmez le paiement avant envoi du lien.';
    } else {
        $token = generateSecureToken(20);
        $expiry = (new DateTime('+' . TOKEN_EXPIRY_DAYS . ' days'))->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare('UPDATE users SET unique_link_token = :token, token_expiry = :expiry WHERE id = :id');
        $stmt->execute([
            'token' => $token,
            'expiry' => $expiry,
            'id' => $id,
        ]);

        $generatedLink = $baseUrl . '/activate?token=' . $token;
        $activationAbsoluteLink = buildAbsoluteUrl($generatedLink);

        $mailError = null;
        $mailSent = sendActivationLinkEmail(
            (string) ($targetUser['email'] ?? ''),
            (string) ($targetUser['full_name'] ?? ''),
            $activationAbsoluteLink,
            $mailError
        );

        if ($mailSent) {
            $safeEmail = htmlspecialchars((string) $targetUser['email'], ENT_QUOTES, 'UTF-8');
            $messageType = 'success';
            $message = 'Lien d activation envoye automatiquement a ' . $safeEmail . ' (valide ' . TOKEN_EXPIRY_DAYS . ' jours).';
            $generatedLink = null;
        } else {
            $messageType = 'warning';
            $message = 'Lien genere mais email non envoye automatiquement.';
            if (defined('APP_DEBUG') && APP_DEBUG && $mailError) {
                $message .= ' Detail: ' . $mailError;
            } else {
                $message .= ' Copiez le lien ci-dessous et envoyez-le manuellement.';
            }
        }
    }
}

$pendingUsers = $pdo->query('SELECT id, full_name, email, payment_confirmed FROM users WHERE status = \'pending\' ORDER BY created_at DESC')->fetchAll();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<section class="container section">
    <div class="section-title">
        <span>Admin</span>
        <h2>Validation des organisateurs</h2>
    </div>
    <div style="margin: 0 0 18px;">
        <a class="button ghost" href="<?= $baseUrl; ?>/admin/dashboard">Retour au dashboard</a>
    </div>
    <?php if ($message): ?>
        <div class="card" style="margin-bottom: 18px;">
            <?php
            $messageColor = '#a7f3d0';
            if ($messageType === 'warning') {
                $messageColor = '#fcd34d';
            } elseif ($messageType === 'error') {
                $messageColor = '#fca5a5';
            }
            ?>
            <p style="color: <?= $messageColor; ?>;"><?= $message; ?></p>
            <?php if ($generatedLink): ?>
                <p style="margin-top: 8px;">Lien d activation : <a href="<?= $generatedLink; ?>"><?= $generatedLink; ?></a></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="card">
        <p>Confirmez d abord le paiement (et les credits) dans <a href="<?= $baseUrl; ?>/admin/users">Utilisateurs</a>, puis envoyez automatiquement le lien d activation (valide 7 jours).</p>
        <?php if (empty($pendingUsers)): ?>
            <p style="margin-top: 12px;">Aucun organisateur en attente.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Paiement</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingUsers as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= ((int) ($user['payment_confirmed'] ?? 0) === 1) ? 'Confirme' : 'En attente'; ?></td>
                            <td>
                                <?php if ((int) ($user['payment_confirmed'] ?? 0) === 1): ?>
                                    <a class="button primary" href="<?= $baseUrl; ?>/admin/validation?action=generate-link&id=<?= $user['id']; ?>">Envoyer lien</a>
                                <?php else: ?>
                                    <span style="color: var(--text-mid);">Confirmez d abord le paiement</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
