<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('admin');

$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$newUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$totalEvents = (int) $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$totalRevenue = (int) $pdo->query("SELECT SUM(IF(payment_confirmed = 1, 1, 0)) FROM users")->fetchColumn();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<section class="container section">
    <div class="section-title">
        <span>Admin</span>
        <h2>Tableau de bord administrateur</h2>
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
            <h3>Événements créés</h3>
            <p><strong><?= $totalEvents; ?></strong> événements</p>
        </div>
        <div class="card">
            <h3>Revenus</h3>
            <p><strong><?= $totalRevenue; ?></strong> paiements confirmés</p>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
