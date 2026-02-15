<?php
require_once __DIR__ . '/includes/auth-check.php';
require_once __DIR__ . '/app/helpers/credits.php';
requireRole('organizer');
$creditSchemaReady = ensureCreditSystemSchema($pdo);
$communicationLogModuleEnabled = isCommunicationLogModuleEnabled($pdo);

$dashboardSection = 'communications';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$message = null;
$messageType = 'success';

$eventsStmt = $pdo->prepare('SELECT id, title FROM events WHERE user_id = :user_id ORDER BY event_date DESC, id DESC');
$eventsStmt->execute(['user_id' => $userId]);
$events = $eventsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send-communication') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token de securite invalide.';
        $messageType = 'error';
    } elseif (!$communicationLogModuleEnabled) {
        $message = 'Module de journalisation des communications non initialise. Reessayez apres migration.';
        $messageType = 'warning';
    } else {
        $eventId = (int) ($_POST['event_id'] ?? 0);
        $channel = sanitizeInput($_POST['channel'] ?? 'email');
        $recipientScope = sanitizeInput($_POST['recipient_scope'] ?? 'all');
        $messageText = sanitizeInput($_POST['message_text'] ?? '');

        $validChannels = ['email', 'sms', 'whatsapp', 'manual'];
        $validScopes = ['all', 'pending', 'confirmed', 'declined'];

        if ($eventId <= 0 || !in_array($channel, $validChannels, true) || !in_array($recipientScope, $validScopes, true) || $messageText === '') {
            $message = 'Renseignez correctement les informations de communication.';
            $messageType = 'error';
        } else {
            $eventStmt = $pdo->prepare('SELECT id FROM events WHERE id = :event_id AND user_id = :user_id LIMIT 1');
            $eventStmt->execute([
                'event_id' => $eventId,
                'user_id' => $userId,
            ]);
            $event = $eventStmt->fetch();

            if (!$event) {
                $message = 'Evenement introuvable.';
                $messageType = 'error';
            } else {
                $countSql = 'SELECT COUNT(*) FROM guests WHERE event_id = :event_id';
                $countParams = ['event_id' => $eventId];
                if ($recipientScope !== 'all') {
                    $countSql .= ' AND rsvp_status = :status';
                    $countParams['status'] = $recipientScope;
                }

                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute($countParams);
                $recipientCount = (int) $countStmt->fetchColumn();

                if ($recipientCount <= 0) {
                    $message = 'Aucun destinataire pour ce filtre.';
                    $messageType = 'error';
                } else {
                    try {
                        $insertLogStmt = $pdo->prepare(
                            'INSERT INTO communication_logs (user_id, event_id, channel, recipient_scope, message_text, recipient_count)
                             VALUES (:user_id, :event_id, :channel, :recipient_scope, :message_text, :recipient_count)'
                        );
                        $insertLogStmt->execute([
                            'user_id' => $userId,
                            'event_id' => $eventId,
                            'channel' => $channel,
                            'recipient_scope' => $recipientScope,
                            'message_text' => $messageText,
                            'recipient_count' => $recipientCount,
                        ]);

                        $message = 'Communication enregistree pour ' . $recipientCount . ' destinataire(s).';
                        $messageType = 'success';
                    } catch (Throwable $throwable) {
                        $message = 'Impossible d enregistrer la communication: ' . $throwable->getMessage();
                        $messageType = 'error';
                    }
                }
            }
        }
    }
}

$logs = [];
if ($communicationLogModuleEnabled) {
    try {
        $logsStmt = $pdo->prepare(
            'SELECT cl.*, e.title AS event_title
             FROM communication_logs cl
             LEFT JOIN events e ON e.id = cl.event_id
             WHERE cl.user_id = :user_id
             ORDER BY cl.id DESC
             LIMIT 20'
        );
        $logsStmt->execute(['user_id' => $userId]);
        $logs = $logsStmt->fetchAll();
    } catch (Throwable $throwable) {
        $logs = [];
    }
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="dashboard">
    <?php include __DIR__ . '/includes/organizer-sidebar.php'; ?>
    <main class="dashboard-content">
        <div class="section-title">
            <span>Communication</span>
            <h2>Campagnes et relances</h2>
        </div>
        <div style="margin: 0 0 18px;">
            <a class="button ghost" href="<?= $baseUrl; ?>/dashboard">Retour au dashboard</a>
        </div>

        <?php if ($message): ?>
            <div class="card" style="margin-bottom: 18px;">
                <?php
                $color = '#166534';
                if ($messageType === 'error') {
                    $color = '#dc2626';
                } elseif ($messageType === 'warning') {
                    $color = '#92400e';
                }
                ?>
                <p style="color: <?= $color; ?>;"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$creditSchemaReady || !$communicationLogModuleEnabled): ?>
            <div class="card" style="margin-bottom: 18px;">
                <p style="color: #92400e;">Journal des communications non disponible tant que la migration n est pas appliquee.</p>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-bottom: 22px;">
            <h3 style="margin-bottom: 12px;">Nouvelle communication</h3>
            <?php if (!$communicationLogModuleEnabled): ?>
                <p style="color: var(--text-mid);">Module indisponible temporairement.</p>
            <?php elseif (empty($events)): ?>
                <p style="color: var(--text-mid);">Creez d abord un evenement puis ajoutez des invites avant d envoyer une communication.</p>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
                    <input type="hidden" name="action" value="send-communication">
                    <div class="form-group">
                        <label for="event_id">Evenement cible</label>
                        <select id="event_id" name="event_id" required>
                            <option value="">Selectionner</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?= (int) $event['id']; ?>"><?= htmlspecialchars((string) ($event['title'] ?? 'Evenement'), ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="channel">Canal</label>
                        <select id="channel" name="channel" required>
                            <option value="email">Email</option>
                            <option value="sms">SMS</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="manual">Manuel</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="recipient_scope">Destinataires</label>
                        <select id="recipient_scope" name="recipient_scope" required>
                            <option value="all">Tous</option>
                            <option value="pending">RSVP en attente</option>
                            <option value="confirmed">RSVP confirmes</option>
                            <option value="declined">RSVP declines</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="message_text">Message</label>
                        <textarea id="message_text" name="message_text" rows="4" placeholder="Rappel: merci de confirmer votre presence..." required></textarea>
                    </div>
                    <button class="button primary" type="submit">Valider la communication</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 style="margin-bottom: 12px;">Historique des communications</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Evenement</th>
                        <th>Canal</th>
                        <th>Filtre</th>
                        <th>Destinataires</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6">Aucune communication enregistree.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($log['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars((string) ($log['event_title'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars((string) ($log['channel'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars((string) ($log['recipient_scope'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= (int) ($log['recipient_count'] ?? 0); ?></td>
                                <td><?= htmlspecialchars((string) ($log['message_text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
