<?php
require_once __DIR__ . '/includes/auth-check.php';
require_once __DIR__ . '/app/helpers/credits.php';
require_once __DIR__ . '/app/helpers/mailer.php';
require_once __DIR__ . '/app/helpers/messaging.php';
requireRole('organizer');
$creditSchemaReady = ensureCreditSystemSchema($pdo);
$communicationLogModuleEnabled = isCommunicationLogModuleEnabled($pdo);
$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

$dashboardSection = 'communications';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$message = null;
$messageType = 'success';
$manualDispatches = [];

$messagingStatus = getMessagingStatusSummary();
$smsChannelReady = !empty($messagingStatus['sms']['ready']);
$whatsAppChannelReady = !empty($messagingStatus['whatsapp']['ready']);
$smsProviderLabel = (string) ($messagingStatus['sms']['provider_label'] ?? 'SMS');
$whatsAppProviderLabel = (string) ($messagingStatus['whatsapp']['provider_label'] ?? 'WhatsApp');
$smsChannelError = (string) ($messagingStatus['sms']['error'] ?? '');
$whatsAppChannelError = (string) ($messagingStatus['whatsapp']['error'] ?? '');

$eventsStmt = $pdo->prepare('SELECT id, title FROM events WHERE user_id = :user_id ORDER BY event_date DESC, id DESC');
$eventsStmt->execute(['user_id' => $userId]);
$events = $eventsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send-communication') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token de sécurité invalide.';
        $messageType = 'error';
    } elseif (!$communicationLogModuleEnabled) {
        $message = 'Module de journalisation des communications non initialisé. Réessayez après migration.';
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
            $eventStmt = $pdo->prepare('SELECT id, title, event_date, location FROM events WHERE id = :event_id AND user_id = :user_id LIMIT 1');
            $eventStmt->execute([
                'event_id' => $eventId,
                'user_id' => $userId,
            ]);
            $event = $eventStmt->fetch();

            if (!$event) {
                $message = 'Événement introuvable.';
                $messageType = 'error';
            } else {
                $recipientSql = 'SELECT id, full_name, email, phone, guest_code, rsvp_status FROM guests WHERE event_id = :event_id';
                $recipientParams = ['event_id' => $eventId];
                if ($recipientScope !== 'all') {
                    $recipientSql .= ' AND rsvp_status = :status';
                    $recipientParams['status'] = $recipientScope;
                }
                $recipientSql .= ' ORDER BY id DESC';

                $recipientStmt = $pdo->prepare($recipientSql);
                $recipientStmt->execute($recipientParams);
                $recipients = $recipientStmt->fetchAll();
                $recipientCount = count($recipients);

                if ($recipientCount <= 0) {
                    $message = 'Aucun destinataire pour ce filtre.';
                    $messageType = 'error';
                } else {
                    $sentCount = 0;
                    $failedCount = 0;
                    $firstError = null;

                    foreach ($recipients as $recipient) {
                        $recipientName = (string) ($recipient['full_name'] ?? '');
                        $invitationPath = $baseUrl . '/guest-invitation?code=' . rawurlencode((string) ($recipient['guest_code'] ?? ''));
                        $absoluteInvitationLink = buildAbsoluteUrl($invitationPath);
                        $dispatchError = null;
                        $providerMessageId = null;

                        if ($channel === 'email') {
                            $recipientEmail = trim((string) ($recipient['email'] ?? ''));
                            if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                                $failedCount++;
                                if ($firstError === null) {
                                    $firstError = 'Adresse email invalide pour ' . ($recipientName !== '' ? $recipientName : 'un invité') . '.';
                                }
                                continue;
                            }

                            $isSent = sendGuestInvitationEmail(
                                $recipientEmail,
                                $recipientName,
                                (string) ($event['title'] ?? 'Événement'),
                                $absoluteInvitationLink,
                                (string) ($event['event_date'] ?? ''),
                                (string) ($event['location'] ?? ''),
                                $messageText,
                                $dispatchError
                            );
                        } elseif ($channel === 'sms') {
                            $recipientPhone = trim((string) ($recipient['phone'] ?? ''));
                            if ($recipientPhone === '') {
                                $failedCount++;
                                if ($firstError === null) {
                                    $firstError = 'Numéro téléphone manquant pour ' . ($recipientName !== '' ? $recipientName : 'un invité') . '.';
                                }
                                continue;
                            }

                            $isSent = sendGuestSmsInvitation(
                                $recipientPhone,
                                $recipientName,
                                (string) ($event['title'] ?? 'Événement'),
                                $absoluteInvitationLink,
                                (string) ($event['event_date'] ?? ''),
                                (string) ($event['location'] ?? ''),
                                $messageText,
                                $dispatchError,
                                $providerMessageId
                            );
                        } elseif ($channel === 'whatsapp') {
                            $recipientPhone = trim((string) ($recipient['phone'] ?? ''));
                            if ($recipientPhone === '') {
                                $failedCount++;
                                if ($firstError === null) {
                                    $firstError = 'Numéro téléphone manquant pour ' . ($recipientName !== '' ? $recipientName : 'un invité') . '.';
                                }
                                continue;
                            }

                            $isSent = sendGuestWhatsAppInvitation(
                                $recipientPhone,
                                $recipientName,
                                (string) ($event['title'] ?? 'Événement'),
                                $absoluteInvitationLink,
                                (string) ($event['event_date'] ?? ''),
                                (string) ($event['location'] ?? ''),
                                $messageText,
                                $dispatchError,
                                $providerMessageId
                            );
                        } else {
                            $manualText = buildGuestManualShareText(
                                $recipientName,
                                (string) ($event['title'] ?? 'Événement'),
                                $absoluteInvitationLink,
                                (string) ($event['event_date'] ?? ''),
                                (string) ($event['location'] ?? ''),
                                $messageText
                            );
                            $manualDispatches[] = [
                                'name' => $recipientName,
                                'email' => (string) ($recipient['email'] ?? ''),
                                'phone' => (string) ($recipient['phone'] ?? ''),
                                'link' => $absoluteInvitationLink,
                                'text' => $manualText,
                            ];
                            $isSent = true;
                        }

                        if ($isSent) {
                            $sentCount++;
                        } else {
                            $failedCount++;
                            if ($firstError === null && $dispatchError) {
                                $firstError = $dispatchError;
                            }
                        }
                    }

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
                    } catch (Throwable $throwable) {
                    }

                    if ($channel === 'manual') {
                        $message = 'Messages manuels générés: ' . $sentCount . ' sur ' . $recipientCount . ' invité(s).';
                        $messageType = 'success';
                    } else {
                        if ($sentCount > 0) {
                            $message = strtoupper($channel) . ' envoyé(s): ' . $sentCount . '. Échecs: ' . $failedCount . '.';
                            $messageType = $failedCount > 0 ? 'warning' : 'success';
                            if ($failedCount > 0 && $firstError) {
                                $message .= ' Detail: ' . $firstError;
                            }
                        } else {
                            $message = 'Aucun envoi ' . strtoupper($channel) . ' réussi.';
                            if ($firstError) {
                                $message .= ' Detail: ' . $firstError;
                            }
                            $messageType = 'error';
                        }
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
                <p style="color: #92400e;">Journal des communications non disponible tant que la migration n'est pas appliquée.</p>
            </div>
        <?php endif; ?>

        <?php if (!$smsChannelReady || !$whatsAppChannelReady): ?>
            <div class="card" style="margin-bottom: 18px;">
                <p style="color: #92400e; margin-bottom: 6px;">
                    Configuration messaging incomplète pour certains canaux.
                </p>
                <p style="color: var(--text-mid); margin: 0;">
                    Cela ne bloque pas la connexion ni l'envoi Email/Manuel.
                </p>
                <?php if (!$smsChannelReady && $smsChannelError !== ''): ?>
                    <p style="color: var(--text-mid); margin: 6px 0 0 0;">SMS (<?= htmlspecialchars($smsProviderLabel, ENT_QUOTES, 'UTF-8'); ?>): <?= htmlspecialchars($smsChannelError, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <?php if (!$whatsAppChannelReady && $whatsAppChannelError !== ''): ?>
                    <p style="color: var(--text-mid); margin: 6px 0 0 0;">WhatsApp (<?= htmlspecialchars($whatsAppProviderLabel, ENT_QUOTES, 'UTF-8'); ?>): <?= htmlspecialchars($whatsAppChannelError, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($manualDispatches)): ?>
            <div class="card" style="margin-bottom: 22px;">
                <h3 style="margin-bottom: 12px;">Partage manuel généré</h3>
                <p style="color: var(--text-mid); margin-bottom: 12px;">Copiez le message et partagez-le par le canal de votre choix.</p>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Invite</th>
                            <th>Contact</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($manualDispatches as $dispatch): ?>
                            <?php
                            $contact = trim((string) ($dispatch['phone'] ?? '')) !== ''
                                ? (string) $dispatch['phone']
                                : (string) ($dispatch['email'] ?? '');
                            ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($dispatch['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars($contact !== '' ? $contact : 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <a class="button ghost" href="<?= htmlspecialchars((string) ($dispatch['link'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Ouvrir lien</a>
                                    <button
                                        type="button"
                                        class="button ghost"
                                        data-copy-manual="<?= htmlspecialchars((string) ($dispatch['text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                        Copier message
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-bottom: 22px;">
            <h3 style="margin-bottom: 12px;">Nouvelle communication</h3>
            <?php if (!$communicationLogModuleEnabled): ?>
                <p style="color: var(--text-mid);">Module indisponible temporairement.</p>
            <?php elseif (empty($events)): ?>
                <p style="color: var(--text-mid);">Créez d'abord un événement puis ajoutez des invités avant d'envoyer une communication.</p>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
                    <input type="hidden" name="action" value="send-communication">
                    <div class="form-group">
                        <label for="event_id">Événement cible</label>
                        <select id="event_id" name="event_id" required>
                            <option value="">Sélectionner</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?= (int) $event['id']; ?>"><?= htmlspecialchars((string) ($event['title'] ?? 'Événement'), ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="channel">Canal</label>
                        <select id="channel" name="channel" required>
                            <option value="email">Email (envoi actif)</option>
                            <option value="sms">SMS - <?= htmlspecialchars($smsProviderLabel, ENT_QUOTES, 'UTF-8'); ?><?= $smsChannelReady ? ' (envoi actif)' : ' (configuration requise)'; ?></option>
                            <option value="whatsapp">WhatsApp - <?= htmlspecialchars($whatsAppProviderLabel, ENT_QUOTES, 'UTF-8'); ?><?= $whatsAppChannelReady ? ' (envoi actif)' : ' (configuration requise)'; ?></option>
                            <option value="manual">Manuel (copie de lien/message)</option>
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
                        <textarea id="message_text" name="message_text" rows="4" placeholder="Ex: Merci de confirmer votre présence avant la date limite." required></textarea>
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
                        <th>Événement</th>
                        <th>Canal</th>
                        <th>Filtre</th>
                        <th>Destinataires</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6">Aucune communication enregistrée.</td>
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
<script>
document.addEventListener("DOMContentLoaded", function () {
    const manualCopyButtons = document.querySelectorAll("[data-copy-manual]");
    manualCopyButtons.forEach(function (button) {
        button.addEventListener("click", async function () {
            const text = button.getAttribute("data-copy-manual") || "";
            if (!text) {
                return;
            }
            try {
                await navigator.clipboard.writeText(text);
                const original = button.textContent;
                button.textContent = "Copie";
                setTimeout(function () {
                    button.textContent = original;
                }, 1200);
            } catch (error) {
                window.prompt("Copiez ce message:", text);
            }
        });
    });
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>

