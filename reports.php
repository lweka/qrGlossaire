<?php
require_once __DIR__ . '/includes/auth-check.php';
require_once __DIR__ . '/app/helpers/credits.php';
requireRole('organizer');
ensureCreditSystemSchema($pdo);

$dashboardSection = 'reports';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$summary = getUserCreditSummary($pdo, $userId);

$statsStmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_guests,
        SUM(CASE WHEN g.rsvp_status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_guests,
        SUM(CASE WHEN g.rsvp_status = 'declined' THEN 1 ELSE 0 END) AS declined_guests,
        SUM(CASE WHEN g.rsvp_status = 'pending' THEN 1 ELSE 0 END) AS pending_guests
     FROM guests g
     INNER JOIN events e ON e.id = g.event_id
     WHERE e.user_id = :user_id"
);
$statsStmt->execute(['user_id' => $userId]);
$guestStats = $statsStmt->fetch() ?: [];

$eventStatsStmt = $pdo->prepare(
    "SELECT e.title,
            COUNT(g.id) AS invite_count,
            SUM(CASE WHEN g.rsvp_status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_count
     FROM events e
     LEFT JOIN guests g ON g.event_id = e.id
     WHERE e.user_id = :user_id
     GROUP BY e.id, e.title
     ORDER BY e.event_date DESC, e.id DESC"
);
$eventStatsStmt->execute(['user_id' => $userId]);
$eventStats = $eventStatsStmt->fetchAll();

$totalGuests = (int) ($guestStats['total_guests'] ?? 0);
$confirmedGuests = (int) ($guestStats['confirmed_guests'] ?? 0);
$pendingGuests = (int) ($guestStats['pending_guests'] ?? 0);
$declinedGuests = (int) ($guestStats['declined_guests'] ?? 0);
$confirmationRate = $totalGuests > 0 ? round(($confirmedGuests / $totalGuests) * 100, 2) : 0;
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="dashboard">
    <?php include __DIR__ . '/includes/organizer-sidebar.php'; ?>
    <main class="dashboard-content">
        <div class="section-title">
            <span>Rapports</span>
            <h2>Performance de vos campagnes d invitation</h2>
        </div>
        <div style="margin: 0 0 18px;">
            <a class="button ghost" href="<?= $baseUrl; ?>/dashboard">Retour au dashboard</a>
        </div>

        <div class="card-grid" style="margin-bottom: 22px;">
            <div class="card">
                <h3>Invitations achetees</h3>
                <p><strong><?= $summary['invitation_total']; ?></strong></p>
            </div>
            <div class="card">
                <h3>Invitations utilisees</h3>
                <p><strong><?= $summary['invitation_used']; ?></strong></p>
            </div>
            <div class="card">
                <h3>Invitations restantes</h3>
                <p><strong><?= $summary['invitation_remaining']; ?></strong></p>
            </div>
            <div class="card">
                <h3>Taux de confirmation RSVP</h3>
                <p><strong><?= $confirmationRate; ?>%</strong></p>
            </div>
        </div>

        <div class="card" style="margin-bottom: 22px;">
            <h3 style="margin-bottom: 10px;">Synthese RSVP</h3>
            <p><strong>Total invites:</strong> <?= $totalGuests; ?></p>
            <p><strong>Confirmes:</strong> <?= $confirmedGuests; ?></p>
            <p><strong>En attente:</strong> <?= $pendingGuests; ?></p>
            <p><strong>Declines:</strong> <?= $declinedGuests; ?></p>
        </div>

        <div class="card">
            <h3 style="margin-bottom: 12px;">Detail par evenement</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Evenement</th>
                        <th>Invites</th>
                        <th>Confirmes</th>
                        <th>Taux confirmation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($eventStats)): ?>
                        <tr>
                            <td colspan="4">Aucun evenement enregistre.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($eventStats as $event): ?>
                            <?php
                            $eventInvites = (int) ($event['invite_count'] ?? 0);
                            $eventConfirmed = (int) ($event['confirmed_count'] ?? 0);
                            $eventRate = $eventInvites > 0 ? round(($eventConfirmed / $eventInvites) * 100, 2) : 0;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($event['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= $eventInvites; ?></td>
                                <td><?= $eventConfirmed; ?></td>
                                <td><?= $eventRate; ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
