<?php
require_once __DIR__ . '/includes/auth-check.php';
require_once __DIR__ . '/app/helpers/credits.php';
requireRole('organizer');
ensureCreditSystemSchema($pdo);

$userId = (int) ($_SESSION['user_id'] ?? 0);
$dashboardSection = 'overview';
$message = null;
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request-credit') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token de securite invalide.';
        $messageType = 'error';
    } else {
        $requestedInvitationCredits = max(0, (int) ($_POST['requested_invitation_credits'] ?? 0));
        $requestedEventCredits = max(0, (int) ($_POST['requested_event_credits'] ?? 0));
        $requestNote = sanitizeInput($_POST['request_note'] ?? '');

        $requestResult = createCreditIncreaseRequest(
            $pdo,
            $userId,
            $requestedInvitationCredits,
            $requestedEventCredits,
            $requestNote
        );

        $message = $requestResult['message'] ?? 'Action terminee.';
        $messageType = !empty($requestResult['ok']) ? 'success' : 'error';
    }
}

$summary = getUserCreditSummary($pdo, $userId);
$pendingRequest = getPendingCreditRequestForUser($pdo, $userId);
$latestProcessedRequest = getLatestProcessedCreditRequestForUser($pdo, $userId);

$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS total
     FROM guests g
     INNER JOIN events e ON g.event_id = e.id
     WHERE e.user_id = :user_id"
);
$stmt->execute(['user_id' => $userId]);
$totalInvites = (int) ($stmt->fetch()['total'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS total
     FROM guests g
     INNER JOIN events e ON g.event_id = e.id
     WHERE e.user_id = :user_id AND g.rsvp_status = 'confirmed'"
);
$stmt->execute(['user_id' => $userId]);
$confirmed = (int) ($stmt->fetch()['total'] ?? 0);

$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM events WHERE user_id = :user_id");
$stmt->execute(['user_id' => $userId]);
$eventCount = (int) ($stmt->fetch()['total'] ?? 0);

$recentStmt = $pdo->prepare(
    "SELECT g.full_name, g.email, g.rsvp_status
     FROM guests g
     INNER JOIN events e ON g.event_id = e.id
     WHERE e.user_id = :user_id
     ORDER BY g.id DESC
     LIMIT 5"
);
$recentStmt->execute(['user_id' => $userId]);
$recentGuests = $recentStmt->fetchAll();
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="dashboard">
    <?php include __DIR__ . '/includes/organizer-sidebar.php'; ?>
    <main class="dashboard-content">
        <div class="section-title">
            <span>Vue generale</span>
            <h2>Bienvenue, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Organisateur', ENT_QUOTES, 'UTF-8'); ?></h2>
        </div>

        <?php if ($message): ?>
            <div class="card" style="margin-bottom: 18px;">
                <?php $messageColor = $messageType === 'error' ? '#dc2626' : '#166534'; ?>
                <p style="color: <?= $messageColor; ?>;"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endif; ?>

        <div class="card-grid">
            <div class="card">
                <h3>Credits invitations</h3>
                <p><strong><?= $summary['invitation_remaining']; ?></strong> restants / <?= $summary['invitation_total']; ?> achetes</p>
            </div>
            <div class="card">
                <h3>Credits creation evenement</h3>
                <p><strong><?= $summary['event_remaining']; ?></strong> restants / <?= $summary['event_total']; ?> achetes</p>
            </div>
            <div class="card">
                <h3>Taux de confirmation</h3>
                <p><strong><?= $totalInvites > 0 ? round(($confirmed / $totalInvites) * 100) : 0; ?>%</strong> confirmations recues</p>
            </div>
            <div class="card">
                <h3>Evenements crees</h3>
                <p><strong><?= $eventCount; ?></strong> evenement(s)</p>
            </div>
        </div>

        <section class="section" style="padding: 32px 0 0;">
            <div class="card" style="margin-bottom: 18px;">
                <h3 style="margin-bottom: 10px;">Augmentation de credits</h3>
                <p style="margin-bottom: 14px; color: var(--text-mid);">
                    Prix en vigueur: <strong>$<?= number_format(invitationUnitPriceUsd(), 2); ?></strong> par invitation.
                    Vous pouvez aussi demander des credits de creation d evenement.
                </p>

                <?php if ($pendingRequest): ?>
                    <p style="color: #92400e;">
                        Demande en attente: +<?= (int) $pendingRequest['requested_invitation_credits']; ?> invitations,
                        +<?= (int) $pendingRequest['requested_event_credits']; ?> credit(s) evenement,
                        montant attendu $<?= number_format((float) ($pendingRequest['amount_usd'] ?? 0), 2); ?>.
                    </p>
                <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
                        <input type="hidden" name="action" value="request-credit">
                        <div class="form-group">
                            <label for="requested_invitation_credits">Invitations supplementaires</label>
                            <input id="requested_invitation_credits" name="requested_invitation_credits" type="number" min="0" step="1" value="50" required>
                        </div>
                        <div class="form-group">
                            <label for="requested_event_credits">Credits creation evenement supplementaires</label>
                            <input id="requested_event_credits" name="requested_event_credits" type="number" min="0" step="1" value="0" required>
                        </div>
                        <div class="form-group">
                            <label for="request_note">Message pour l administration (optionnel)</label>
                            <textarea id="request_note" name="request_note" rows="3" placeholder="Ex: J ai besoin de 50 invitations pour un nouveau lot."></textarea>
                        </div>
                        <button class="button primary" type="submit">Envoyer la demande</button>
                    </form>
                <?php endif; ?>

                <?php if ($latestProcessedRequest): ?>
                    <?php
                    $status = (string) ($latestProcessedRequest['status'] ?? '');
                    $statusColor = $status === 'approved' ? '#166534' : '#b91c1c';
                    $statusLabel = $status === 'approved' ? 'approuvee' : 'rejetee';
                    ?>
                    <p style="margin-top: 14px; color: <?= $statusColor; ?>;">
                        Derniere demande <?= $statusLabel; ?>:
                        +<?= (int) $latestProcessedRequest['requested_invitation_credits']; ?> invitations /
                        +<?= (int) $latestProcessedRequest['requested_event_credits']; ?> credit(s) evenement.
                        <?= !empty($latestProcessedRequest['admin_note']) ? 'Note admin: ' . htmlspecialchars((string) $latestProcessedRequest['admin_note'], ENT_QUOTES, 'UTF-8') : ''; ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="section-title">
                <span>Suivi invites</span>
                <h2>Derniers invites</h2>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentGuests)): ?>
                        <tr>
                            <td colspan="3">Aucun invite pour le moment.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentGuests as $guest): ?>
                            <tr>
                                <td><?= htmlspecialchars($guest['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars($guest['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars($guest['rsvp_status'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
