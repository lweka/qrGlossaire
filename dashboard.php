<?php
require_once __DIR__ . '/includes/auth-check.php';
requireRole('organizer');

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM guests g JOIN events e ON g.event_id = e.id WHERE e.user_id = :user_id");
$stmt->execute(['user_id' => $userId]);
$totalInvites = (int) ($stmt->fetch()['total'] ?? 0);

$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM guests g JOIN events e ON g.event_id = e.id WHERE e.user_id = :user_id AND g.rsvp_status = 'confirmed'");
$stmt->execute(['user_id' => $userId]);
$confirmed = (int) ($stmt->fetch()['total'] ?? 0);

$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM events WHERE user_id = :user_id");
$stmt->execute(['user_id' => $userId]);
$eventCount = (int) ($stmt->fetch()['total'] ?? 0);

$recentStmt = $pdo->prepare("SELECT g.full_name, g.email, g.rsvp_status FROM guests g JOIN events e ON g.event_id = e.id WHERE e.user_id = :user_id ORDER BY g.id DESC LIMIT 5");
$recentStmt->execute(['user_id' => $userId]);
$recentGuests = $recentStmt->fetchAll();
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="dashboard">
    <aside class="sidebar">
        <h3>Tableau de bord</h3>
        <a class="active" href="<?= $baseUrl; ?>/dashboard">Vue générale</a>
        <a href="<?= $baseUrl; ?>/create-event">Créer un événement</a>
        <a href="#">Invités</a>
        <a href="#">Communication</a>
        <a href="#">Rapports</a>
        <a href="#">Paramètres</a>
    </aside>
    <main class="dashboard-content">
        <div class="section-title">
            <span>Vue générale</span>
            <h2>Bienvenue, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Organisateur', ENT_QUOTES, 'UTF-8'); ?></h2>
        </div>
        <div class="card-grid">
            <div class="card">
                <h3>Invitations envoyées</h3>
                <p><strong><?= $totalInvites; ?></strong> invitations envoyées</p>
            </div>
            <div class="card">
                <h3>Taux de confirmation</h3>
                <p><strong><?= $totalInvites > 0 ? round(($confirmed / $totalInvites) * 100) : 0; ?>%</strong> confirmations reçues</p>
            </div>
            <div class="card">
                <h3>Invitations ouvertes</h3>
                <p><strong><?= $eventCount; ?></strong> événements actifs</p>
            </div>
            <div class="card">
                <h3>Présences confirmées</h3>
                <p><strong><?= $confirmed; ?></strong> invités attendus</p>
            </div>
        </div>

        <section class="section" style="padding: 40px 0 0;">
            <div class="section-title">
                <span>Suivi invités</span>
                <h2>Derniers invités</h2>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentGuests)): ?>
                        <tr>
                            <td colspan="4">Aucun invité pour le moment.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentGuests as $guest): ?>
                            <tr>
                                <td><?= htmlspecialchars($guest['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars($guest['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars($guest['rsvp_status'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><a class="button ghost" href="#">Relancer</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
