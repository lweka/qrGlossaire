<?php
require_once __DIR__ . '/includes/auth-check.php';
require_once __DIR__ . '/app/helpers/credits.php';
requireRole('organizer');
ensureCreditSystemSchema($pdo);

$dashboardSection = 'guests';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$message = null;
$messageType = 'success';

$eventsStmt = $pdo->prepare('SELECT id, title, event_date FROM events WHERE user_id = :user_id ORDER BY event_date DESC, id DESC');
$eventsStmt->execute(['user_id' => $userId]);
$events = $eventsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add-guest') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token de securite invalide.';
        $messageType = 'error';
    } else {
        $eventId = (int) ($_POST['event_id'] ?? 0);
        $fullName = sanitizeInput($_POST['full_name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');

        if ($eventId <= 0 || $fullName === '') {
            $message = 'Renseignez au minimum l evenement et le nom de l invite.';
            $messageType = 'error';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Adresse email invite invalide.';
            $messageType = 'error';
        } else {
            $ownedEventStmt = $pdo->prepare('SELECT id FROM events WHERE id = :event_id AND user_id = :user_id LIMIT 1');
            $ownedEventStmt->execute([
                'event_id' => $eventId,
                'user_id' => $userId,
            ]);
            $ownedEvent = $ownedEventStmt->fetch();

            if (!$ownedEvent) {
                $message = 'Evenement introuvable.';
                $messageType = 'error';
            } else {
                $summary = getUserCreditSummary($pdo, $userId);
                if ($summary['invitation_remaining'] <= 0) {
                    $message = 'Credits invitations epuises. Demandez une augmentation avant d ajouter un invite.';
                    $messageType = 'error';
                } else {
                    $guestCode = 'INV-' . strtoupper(substr(generateSecureToken(8), 0, 10));

                    $insertStmt = $pdo->prepare(
                        'INSERT INTO guests (event_id, guest_code, full_name, email, phone)
                         VALUES (:event_id, :guest_code, :full_name, :email, :phone)'
                    );
                    $insertStmt->execute([
                        'event_id' => $eventId,
                        'guest_code' => $guestCode,
                        'full_name' => $fullName,
                        'email' => $email !== '' ? $email : null,
                        'phone' => $phone !== '' ? $phone : null,
                    ]);

                    $message = 'Invite ajoute avec succes. 1 credit invitation consomme.';
                    $messageType = 'success';
                }
            }
        }
    }

    $eventsStmt->execute(['user_id' => $userId]);
    $events = $eventsStmt->fetchAll();
}

$summary = getUserCreditSummary($pdo, $userId);

$guestsStmt = $pdo->prepare(
    'SELECT g.id, g.full_name, g.email, g.phone, g.rsvp_status, g.guest_code, e.title AS event_title
     FROM guests g
     INNER JOIN events e ON e.id = g.event_id
     WHERE e.user_id = :user_id
     ORDER BY g.id DESC
     LIMIT 100'
);
$guestsStmt->execute(['user_id' => $userId]);
$guests = $guestsStmt->fetchAll();
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="dashboard">
    <?php include __DIR__ . '/includes/organizer-sidebar.php'; ?>
    <main class="dashboard-content">
        <div class="section-title">
            <span>Invites</span>
            <h2>Gestion des invites</h2>
        </div>
        <div style="margin: 0 0 18px;">
            <a class="button ghost" href="<?= $baseUrl; ?>/dashboard">Retour au dashboard</a>
        </div>

        <div class="card" style="margin-bottom: 18px;">
            <p><strong>Credits invitations restants:</strong> <?= $summary['invitation_remaining']; ?> / <?= $summary['invitation_total']; ?></p>
            <p style="margin-top: 6px; color: var(--text-mid);">Chaque invite ajoute consomme 1 credit invitation.</p>
        </div>

        <?php if ($message): ?>
            <div class="card" style="margin-bottom: 18px;">
                <?php $color = $messageType === 'error' ? '#dc2626' : '#166534'; ?>
                <p style="color: <?= $color; ?>;"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-bottom: 22px;">
            <h3 style="margin-bottom: 12px;">Ajouter un invite</h3>
            <?php if ($summary['invitation_remaining'] <= 0): ?>
                <p style="color: #92400e; margin-bottom: 10px;">
                    Credits invitations epuises. Demandez une augmentation avant d ajouter de nouveaux invites.
                </p>
                <a class="button primary" href="<?= $baseUrl; ?>/dashboard">Demander une augmentation</a>
            <?php elseif (empty($events)): ?>
                <p style="color: var(--text-mid);">Vous devez d abord creer un evenement avant d ajouter des invites.</p>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
                    <input type="hidden" name="action" value="add-guest">
                    <div class="form-group">
                        <label for="event_id">Evenement</label>
                        <select id="event_id" name="event_id" required>
                            <option value="">Selectionner</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?= (int) $event['id']; ?>">
                                    <?= htmlspecialchars((string) ($event['title'] ?? 'Evenement'), ENT_QUOTES, 'UTF-8'); ?>
                                    - <?= htmlspecialchars((string) ($event['event_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="full_name">Nom invite</label>
                        <input id="full_name" name="full_name" type="text" placeholder="Ex: Jean Mavoungou" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email invite</label>
                        <input id="email" name="email" type="email" placeholder="invite@email.com">
                    </div>
                    <div class="form-group">
                        <label for="phone">Telephone invite</label>
                        <input id="phone" name="phone" type="text" placeholder="+242 06 000 0000">
                    </div>
                    <button class="button primary" type="submit">Ajouter l invite</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 style="margin-bottom: 12px;">Invites enregistres</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Telephone</th>
                        <th>Evenement</th>
                        <th>Statut RSVP</th>
                        <th>Code</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($guests)): ?>
                        <tr>
                            <td colspan="6">Aucun invite enregistre pour le moment.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($guests as $guest): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($guest['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars((string) ($guest['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars((string) ($guest['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars((string) ($guest['event_title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars((string) ($guest['rsvp_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars((string) ($guest['guest_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
