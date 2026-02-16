<?php
$dashboardSection = $dashboardSection ?? 'overview';
$organizerMenu = [
    'overview' => ['label' => 'Vue generale', 'path' => '/dashboard'],
    'create_event' => ['label' => 'Creer un evenement', 'path' => '/create-event'],
    'guests' => ['label' => 'Invites', 'path' => '/guests'],
    'checkin_scan' => ['label' => 'Scan entree', 'path' => '/scan-checkin'],
    'communications' => ['label' => 'Communication', 'path' => '/communications'],
    'reports' => ['label' => 'Rapports', 'path' => '/reports'],
    'settings' => ['label' => 'Parametres', 'path' => '/settings'],
];
?>
<aside class="sidebar" data-dashboard-sidebar>
    <div class="sidebar-header">
        <h3>Tableau de bord</h3>
        <button
            type="button"
            class="sidebar-toggle"
            data-sidebar-toggle
            aria-expanded="false"
            aria-controls="organizer-sidebar-nav"
        >
            Menu
        </button>
    </div>
    <nav id="organizer-sidebar-nav" class="sidebar-nav" aria-label="Navigation organisateur">
        <?php foreach ($organizerMenu as $menuKey => $menuItem): ?>
            <a
                class="sidebar-link <?= $dashboardSection === $menuKey ? 'active' : ''; ?>"
                href="<?= $baseUrl; ?><?= $menuItem['path']; ?>"
            >
                <?= htmlspecialchars($menuItem['label'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
