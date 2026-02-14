<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('admin');
require_once __DIR__ . '/../app/config/constants.php';

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

$message = null;
$generatedLink = null;

if (!empty($_GET['action']) && $_GET['action'] === 'generate-link' && !empty($_GET['id'])) {
    $id = (int) $_GET['id'];
    $token = generateSecureToken(20);
    $expiry = (new DateTime('+' . TOKEN_EXPIRY_DAYS . ' days'))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare("UPDATE users SET unique_link_token = :token, token_expiry = :expiry WHERE id = :id");
    $stmt->execute([
        'token' => $token,
        'expiry' => $expiry,
        'id' => $id,
    ]);
    $generatedLink = $baseUrl . '/activate?token=' . $token;
    $message = "Lien généré (valide " . TOKEN_EXPIRY_DAYS . " jours).";
}

$pendingUsers = $pdo->query("SELECT id, full_name, email FROM users WHERE status = 'pending' ORDER BY created_at DESC")->fetchAll();
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
            <p style="color: #a7f3d0;"><?= $message; ?></p>
            <?php if ($generatedLink): ?>
                <p style="margin-top: 8px;">Lien d'activation : <a href="<?= $generatedLink; ?>"><?= $generatedLink; ?></a></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="card">
        <p>Vérifiez les informations fournies et générez un lien unique d'activation valable 7 jours.</p>
        <?php if (empty($pendingUsers)): ?>
            <p style="margin-top: 12px;">Aucun organisateur en attente.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingUsers as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <a class="button primary" href="<?= $baseUrl; ?>/admin/validation?action=generate-link&id=<?= $user['id']; ?>">Générer lien</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
