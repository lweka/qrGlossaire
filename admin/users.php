<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('admin');

$message = null;

if (!empty($_GET['action']) && !empty($_GET['id'])) {
    $action = sanitizeInput($_GET['action']);
    $id = (int) $_GET['id'];

    if ($action === 'suspend') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $message = "Utilisateur suspendu.";
    }

    if ($action === 'activate') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = :id AND payment_confirmed = 1");
        $stmt->execute(['id' => $id]);
        $message = "Compte activé si paiement confirmé.";
    }

    if ($action === 'confirm-payment') {
        $stmt = $pdo->prepare("UPDATE users SET payment_confirmed = 1, payment_date = CURDATE() WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $message = "Paiement confirmé.";
    }
}

$users = $pdo->query("SELECT id, full_name, email, status, payment_confirmed FROM users ORDER BY created_at DESC")->fetchAll();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<section class="container section">
    <div class="section-title">
        <span>Admin</span>
        <h2>Gestion des utilisateurs</h2>
    </div>
    <div style="margin: 0 0 18px;">
        <a class="button ghost" href="<?= $baseUrl; ?>/admin/dashboard">Retour au dashboard</a>
    </div>
    <?php if ($message): ?>
        <div class="card" style="margin-bottom: 18px;">
            <p style="color: #a7f3d0;"><?= $message; ?></p>
        </div>
    <?php endif; ?>
    <table class="table">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Email</th>
                <th>Statut</th>
                <th>Paiement</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="5">Aucun utilisateur pour le moment.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($user['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= $user['payment_confirmed'] ? 'Oui' : 'Non'; ?></td>
                        <td>
                            <a class="button ghost" href="<?= $baseUrl; ?>/admin/users?action=confirm-payment&id=<?= $user['id']; ?>">Paiement</a>
                            <a class="button ghost" href="<?= $baseUrl; ?>/admin/users?action=activate&id=<?= $user['id']; ?>">Activer</a>
                            <a class="button ghost" href="<?= $baseUrl; ?>/admin/users?action=suspend&id=<?= $user['id']; ?>">Suspendre</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
