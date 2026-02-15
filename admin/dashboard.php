<?php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../app/helpers/credits.php';
requireRole('admin');
ensureCreditSystemSchema($pdo);

$totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$newUsers = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn();
$totalEvents = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
$totalPaidUsers = (int) $pdo->query('SELECT SUM(IF(payment_confirmed = 1, 1, 0)) FROM users')->fetchColumn();
$pendingCreditRequests = (int) $pdo->query("SELECT COUNT(*) FROM credit_requests WHERE status = 'pending'")->fetchColumn();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<section class="container section">
    <div class="section-title">
        <span>Admin</span>
        <h2>Tableau de bord administrateur</h2>
    </div>
    <div class="card" style="margin-bottom: 20px;">
        <h3 style="margin-bottom: 12px;">Acces rapide supervision</h3>
        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
            <a class="button ghost" href="<?= $baseUrl; ?>/admin/validation">Valider comptes</a>
            <a class="button ghost" href="<?= $baseUrl; ?>/admin/users">Utilisateurs</a>
            <a class="button ghost" href="<?= $baseUrl; ?>/admin/logs">Logs</a>
            <a class="button ghost" href="<?= $baseUrl; ?>/admin/settings">Parametres</a>
        </div>
    </div>
    <div class="card-grid">
        <div class="card">
            <h3>Utilisateurs</h3>
            <p><strong><?= $totalUsers; ?></strong> comptes</p>
        </div>
        <div class="card">
            <h3>Nouveaux inscrits</h3>
            <p><strong><?= $newUsers; ?></strong> sur 7 jours</p>
        </div>
        <div class="card">
            <h3>Evenements crees</h3>
            <p><strong><?= $totalEvents; ?></strong> evenements</p>
        </div>
        <div class="card">
            <h3>Paiements confirmes</h3>
            <p><strong><?= $totalPaidUsers; ?></strong> comptes payes</p>
        </div>
        <div class="card">
            <h3>Demandes credits</h3>
            <p><strong><?= $pendingCreditRequests; ?></strong> en attente</p>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
