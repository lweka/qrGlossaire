<?php
$dashboardSection = $dashboardSection ?? 'overview';
$organizerMenu = [
    'overview' => ['label' => 'Vue generale', 'path' => '/dashboard'],
    'create_event' => ['label' => 'Creer un evenement', 'path' => '/create-event'],
    'guests' => ['label' => 'Invites', 'path' => '/guests'],
    'communications' => ['label' => 'Communication', 'path' => '/communications'],
    'reports' => ['label' => 'Rapports', 'path' => '/reports'],
    'settings' => ['label' => 'Parametres', 'path' => '/settings'],
];
?>
<aside class="sidebar">
    <h3>Tableau de bord</h3>
    <?php foreach ($organizerMenu as $menuKey => $menuItem): ?>
        <a
            class="<?= $dashboardSection === $menuKey ? 'active' : ''; ?>"
            href="<?= $baseUrl; ?><?= $menuItem['path']; ?>"
        >
            <?= htmlspecialchars($menuItem['label'], ENT_QUOTES, 'UTF-8'); ?>
        </a>
    <?php endforeach; ?>
</aside>
