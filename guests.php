<?php
require_once __DIR__ . '/includes/auth-check.php';
require_once __DIR__ . '/app/helpers/credits.php';
require_once __DIR__ . '/app/helpers/mailer.php';
require_once __DIR__ . '/app/helpers/messaging.php';
require_once __DIR__ . '/app/helpers/guest_registration.php';
requireRole('organizer');
$creditSchemaReady = ensureCreditSystemSchema($pdo);
$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

$dashboardSection = 'guests';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$message = null;
$messageType = 'success';
$manualSharePreview = null;
$summary = getUserCreditSummary($pdo, $userId);
$creditControlEnabled = !empty($summary['credit_controls_enabled']);
$guestCustomAnswersEnabled = creditColumnExists($pdo, 'guests', 'custom_answers');

$messagingStatus = getMessagingStatusSummary();
$smsChannelReady = !empty($messagingStatus['sms']['ready']);
$whatsAppChannelReady = !empty($messagingStatus['whatsapp']['ready']);
$smsProviderLabel = (string) ($messagingStatus['sms']['provider_label'] ?? 'SMS');
$whatsAppProviderLabel = (string) ($messagingStatus['whatsapp']['provider_label'] ?? 'WhatsApp');

$eventsStmt = $pdo->prepare('SELECT id, title, event_date FROM events WHERE user_id = :user_id ORDER BY event_date DESC, id DESC');
$eventsStmt->execute(['user_id' => $userId]);
$events = $eventsStmt->fetchAll();
$guestRegistrationSchemaReady = ensureGuestRegistrationSchema($pdo);
$buildEventRegistrationLinks = static function (array $eventRows) use ($pdo, $userId, $baseUrl): array {
    $links = [];
    foreach ($eventRows as $eventRow) {
        $eventId = (int) ($eventRow['id'] ?? 0);
        if ($eventId <= 0) {
            continue;
        }

        $token = getOrCreateGuestRegistrationToken($pdo, $eventId, $userId);
        if ($token === '') {
            continue;
        }

        $path = $baseUrl . '/guest-register?token=' . rawurlencode($token);
        $links[$eventId] = [
            'path' => $path,
            'absolute' => buildAbsoluteUrl($path),
        ];
    }

    return $links;
};
$eventRegistrationLinks = $guestRegistrationSchemaReady ? $buildEventRegistrationLinks($events) : [];

function parseGuestCustomAnswers(?string $rawJson): array
{
    if (!is_string($rawJson) || trim($rawJson) === '') {
        return [];
    }

    $decoded = json_decode($rawJson, true);
    return is_array($decoded) ? $decoded : [];
}

function guestSeatFromCustomAnswers(?string $rawJson): array
{
    $data = parseGuestCustomAnswers($rawJson);
    $tableName = trim((string) ($data['table_name'] ?? ''));
    $tableNumber = trim((string) ($data['table_number'] ?? ''));

    return [
        'table_name' => $tableName,
        'table_number' => $tableNumber,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token de sécurité invalide.';
        $messageType = 'error';
    } else {
        $action = sanitizeInput($_POST['action'] ?? '');

        if ($action === 'add-guest') {
            $message = "Ajout manuel des invités désactivé. Utilisez le lien d'inscription autonome.";
            $messageType = 'warning';
        } elseif ($action === 'assign-table') {
            $guestId = (int) ($_POST['guest_id'] ?? 0);
            $tableName = sanitizeInput($_POST['table_name'] ?? '');
            $tableNumber = sanitizeInput($_POST['table_number'] ?? '');

            if ($guestId <= 0) {
                $message = 'Invite introuvable.';
                $messageType = 'error';
            } elseif (!$guestCustomAnswersEnabled) {
                $message = 'Affectation de table indisponible: colonne custom_answers absente.';
                $messageType = 'warning';
            } else {
                $guestSeatStmt = $pdo->prepare(
                    'SELECT g.id, g.custom_answers
                     FROM guests g
                     INNER JOIN events e ON e.id = g.event_id
                     WHERE g.id = :guest_id AND e.user_id = :user_id
                     LIMIT 1'
                );
                $guestSeatStmt->execute([
                    'guest_id' => $guestId,
                    'user_id' => $userId,
                ]);
                $guestSeat = $guestSeatStmt->fetch();

                if (!$guestSeat) {
                    $message = 'Invite introuvable pour cet utilisateur.';
                    $messageType = 'error';
                } else {
                    $existingMeta = parseGuestCustomAnswers((string) ($guestSeat['custom_answers'] ?? ''));
                    $existingMeta['table_name'] = $tableName;
                    $existingMeta['table_number'] = $tableNumber;
                    $payload = json_encode($existingMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                    $updateSeatStmt = $pdo->prepare('UPDATE guests SET custom_answers = :custom_answers WHERE id = :id');
                    $updateSeatStmt->execute([
                        'custom_answers' => $payload,
                        'id' => $guestId,
                    ]);

                    $message = "Table de l'invité mise à jour.";
                    $messageType = 'success';
                }
            }
        } elseif ($action === 'send-guest-message') {
            $guestId = (int) ($_POST['guest_id'] ?? 0);
            $channel = sanitizeInput($_POST['channel'] ?? 'email');
            $validChannels = ['email', 'sms', 'whatsapp', 'manual'];

            if ($guestId <= 0 || !in_array($channel, $validChannels, true)) {
                $message = "Paramètres d'envoi invalides.";
                $messageType = 'error';
            } else {
                $guestStmt = $pdo->prepare(
                    'SELECT g.id, g.full_name, g.email, g.phone, g.guest_code, e.title, e.event_date, e.location
                     FROM guests g
                     INNER JOIN events e ON e.id = g.event_id
                     WHERE g.id = :guest_id AND e.user_id = :user_id
                     LIMIT 1'
                );
                $guestStmt->execute([
                    'guest_id' => $guestId,
                    'user_id' => $userId,
                ]);
                $guest = $guestStmt->fetch();

                if (!$guest) {
                    $message = 'Invite introuvable pour cet utilisateur.';
                    $messageType = 'error';
                } else {
                    $guestInvitationPath = $baseUrl . '/guest-invitation?code=' . rawurlencode((string) $guest['guest_code']);
                    $guestInvitationLink = buildAbsoluteUrl($guestInvitationPath);
                    $dispatchError = null;
                    $providerMessageId = null;
                    $sent = false;

                    if ($channel === 'email') {
                        $guestEmail = trim((string) ($guest['email'] ?? ''));
                        if (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
                            $message = 'Adresse email invalide pour cet invité.';
                            $messageType = 'error';
                        } else {
                            $sent = sendGuestInvitationEmail(
                                $guestEmail,
                                (string) ($guest['full_name'] ?? ''),
                                (string) ($guest['title'] ?? ''),
                                $guestInvitationLink,
                                (string) ($guest['event_date'] ?? ''),
                                (string) ($guest['location'] ?? ''),
                                'Merci de confirmer votre présence via ce lien.',
                                $dispatchError
                            );
                        }
                    } elseif ($channel === 'sms') {
                        $guestPhone = trim((string) ($guest['phone'] ?? ''));
                        if ($guestPhone === '') {
                            $message = 'Numéro téléphone manquant pour cet invité.';
                            $messageType = 'error';
                        } else {
                            $sent = sendGuestSmsInvitation(
                                $guestPhone,
                                (string) ($guest['full_name'] ?? ''),
                                (string) ($guest['title'] ?? ''),
                                $guestInvitationLink,
                                (string) ($guest['event_date'] ?? ''),
                                (string) ($guest['location'] ?? ''),
                                'Merci de confirmer votre présence via ce lien.',
                                $dispatchError,
                                $providerMessageId
                            );
                        }
                    } elseif ($channel === 'whatsapp') {
                        $guestPhone = trim((string) ($guest['phone'] ?? ''));
                        if ($guestPhone === '') {
                            $message = 'Numéro téléphone manquant pour cet invité.';
                            $messageType = 'error';
                        } else {
                            $sent = sendGuestWhatsAppInvitation(
                                $guestPhone,
                                (string) ($guest['full_name'] ?? ''),
                                (string) ($guest['title'] ?? ''),
                                $guestInvitationLink,
                                (string) ($guest['event_date'] ?? ''),
                                (string) ($guest['location'] ?? ''),
                                'Merci de confirmer votre présence via ce lien.',
                                $dispatchError,
                                $providerMessageId
                            );
                        }
                    } else {
                        $manualSharePreview = buildGuestManualShareText(
                            (string) ($guest['full_name'] ?? ''),
                            (string) ($guest['title'] ?? ''),
                            $guestInvitationLink,
                            (string) ($guest['event_date'] ?? ''),
                            (string) ($guest['location'] ?? ''),
                            'Merci de confirmer votre présence via ce lien.'
                        );
                        $sent = true;
                    }

                    if ($sent) {
                        $message = $channel === 'manual'
                            ? 'Message manuel généré. Copiez et partagez le texte ci-dessous.'
                            : strtoupper($channel) . ' envoyé avec succès.';
                        if ($providerMessageId) {
                            $message .= ' Ref: ' . $providerMessageId;
                        }
                        $messageType = 'success';
                    } elseif ($messageType !== 'error') {
                        $message = 'Échec envoi ' . strtoupper($channel) . '.';
                        if ($dispatchError) {
                            $message .= ' Detail: ' . $dispatchError;
                        }
                        $messageType = 'error';
                    }
                }
            }
        }
    }

    $eventsStmt->execute(['user_id' => $userId]);
    $events = $eventsStmt->fetchAll();
    $eventRegistrationLinks = $guestRegistrationSchemaReady ? $buildEventRegistrationLinks($events) : [];
}

$summary = getUserCreditSummary($pdo, $userId);
$creditControlEnabled = !empty($summary['credit_controls_enabled']);

$customAnswersSelect = $guestCustomAnswersEnabled ? 'g.custom_answers' : 'NULL AS custom_answers';
$guestsStmt = $pdo->prepare(
    'SELECT g.id, g.event_id, g.full_name, g.email, g.phone, g.rsvp_status, g.guest_code, g.check_in_count, ' . $customAnswersSelect . ', e.title AS event_title
     FROM guests g
     INNER JOIN events e ON e.id = g.event_id
     WHERE e.user_id = :user_id
     ORDER BY g.id DESC
     LIMIT 100'
);
$guestsStmt->execute(['user_id' => $userId]);
$guests = $guestsStmt->fetchAll();

$pageHeadExtra = <<<'HTML'
<style>
    .guests-register-card {
        max-width: 1080px;
        margin: 0 auto;
    }

    .guests-register-card > h3 {
        font-size: 1.1rem;
        margin-bottom: 10px !important;
    }

    .event-link-list {
        display: grid;
        gap: 10px;
        margin-top: 10px;
    }

    .event-link-card {
        border: 1px solid rgba(148, 163, 184, 0.35);
        border-radius: 12px;
        background: #f8fafc;
        padding: 12px;
        display: grid;
        gap: 10px;
    }

    .event-link-card h4 {
        margin: 0;
        font-size: 0.95rem;
        color: var(--text-dark);
    }

    .event-link-meta {
        margin: 5px 0 0 0;
        color: var(--text-mid);
        font-size: 0.83rem;
    }

    .event-link-url {
        margin: 8px 0 0 0;
        padding: 8px 10px;
        border: 1px dashed rgba(148, 163, 184, 0.45);
        border-radius: 10px;
        background: #ffffff;
        color: #1e293b;
        font-size: 0.79rem;
        line-height: 1.35;
        word-break: break-all;
    }

    .event-link-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .guest-list-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 10px;
    }

    .guest-item-card {
        border: 1px solid rgba(148, 163, 184, 0.35);
        border-radius: 12px;
        background: #ffffff;
        padding: 10px;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
    }

    .guest-item-top {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        align-items: flex-start;
    }

    .guest-item-name {
        margin: 0;
        color: var(--text-dark);
        font-size: 0.94rem;
        line-height: 1.25;
        word-break: break-word;
    }

    .guest-item-code {
        margin: 3px 0 0 0;
        color: var(--text-light);
        font-size: 0.76rem;
        word-break: break-word;
    }

    .guest-status-chip {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 4px 8px;
        font-size: 0.66rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .guest-status-chip.pending {
        color: #92400e;
        background: #fef3c7;
        border: 1px solid #fcd34d;
    }

    .guest-status-chip.confirmed {
        color: #166534;
        background: #dcfce7;
        border: 1px solid #86efac;
    }

    .guest-status-chip.declined {
        color: #991b1b;
        background: #fee2e2;
        border: 1px solid #fca5a5;
    }

    .guest-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px 10px;
        margin-top: 8px;
    }

    .guest-info-grid p {
        margin: 0;
        color: var(--text-mid);
        line-height: 1.35;
        font-size: 0.83rem;
        word-break: break-word;
    }

    .guest-info-grid strong {
        color: var(--text-dark);
    }

    .guest-section {
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px dashed rgba(148, 163, 184, 0.45);
    }

    .guests-link-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .guests-seat-label {
        margin: 0 0 6px 0;
        font-weight: 600;
        color: var(--text-dark);
        font-size: 0.82rem;
    }

    .guests-seat-form {
        display: grid;
        gap: 5px;
        min-width: 0;
    }

    .guests-actions-form {
        display: flex;
        gap: 6px;
        align-items: center;
        flex-wrap: wrap;
    }

    .guests-actions-form select {
        min-width: 150px;
        flex: 1;
    }

    .guest-item-card .button {
        padding: 7px 11px;
        font-size: 0.78rem;
    }

    .guest-item-card input,
    .guest-item-card select {
        padding: 8px 10px;
        font-size: 0.82rem;
        border-radius: 10px;
    }

    .guest-advanced {
        margin-top: 6px;
    }

    .guest-advanced summary {
        cursor: pointer;
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--text-mid);
        list-style: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        user-select: none;
    }

    .guest-advanced summary::-webkit-details-marker {
        display: none;
    }

    .guest-advanced summary::before {
        content: "+";
        font-weight: 700;
        color: var(--brand-blue);
    }

    .guest-advanced[open] summary::before {
        content: "-";
    }

    .guest-advanced-content {
        margin-top: 8px;
        display: grid;
        gap: 8px;
    }

    @media (max-width: 768px) {
        .guest-list-grid {
            grid-template-columns: 1fr;
        }

        .guest-item-card {
            padding: 9px;
        }

        .guest-item-top {
            flex-direction: column;
            align-items: flex-start;
        }

        .guest-info-grid {
            grid-template-columns: 1fr;
        }

        .guests-link-actions,
        .event-link-actions,
        .guests-actions-form {
            display: grid;
            gap: 8px;
        }

        .event-link-actions .button,
        .guests-link-actions .button,
        .guests-actions-form .button,
        .guests-actions-form select,
        .guests-seat-form .button,
        .guest-item-card input,
        .guest-item-card select {
            width: 100%;
        }

        .guests-seat-form {
            min-width: 0;
        }
    }
</style>
HTML;
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="dashboard">
    <?php include __DIR__ . '/includes/organizer-sidebar.php'; ?>
    <main class="dashboard-content">
        <div class="section-title">
            <span>Invités</span>
            <h2>Gestion des invités</h2>
        </div>
        <div style="margin: 0 0 18px; display: flex; gap: 10px; flex-wrap: wrap;">
            <a class="button ghost" href="<?= $baseUrl; ?>/dashboard">Retour au dashboard</a>
            <a class="button primary" href="<?= $baseUrl; ?>/scan-checkin">Scanner à l'entrée</a>
        </div>

        <div class="card" style="margin-bottom: 18px;">
            <?php if ($creditControlEnabled): ?>
                <p><strong>Crédits invitations restants:</strong> <?= $summary['invitation_remaining']; ?> / <?= $summary['invitation_total']; ?></p>
                <p style="margin-top: 6px; color: var(--text-mid);">Chaque inscription invité via lien consomme 1 crédit invitation.</p>
            <?php else: ?>
                <p><strong>Crédits invitations:</strong> mode libre temporaire (module crédits non initialisé).</p>
            <?php endif; ?>
            <?php if (!$creditSchemaReady): ?>
                <p style="margin-top: 6px; color: #92400e;">Le module crédits est en initialisation sur ce serveur.</p>
            <?php endif; ?>
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
                <p style="color: <?= $color; ?>;"><?= htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($manualSharePreview !== null): ?>
            <div class="card" style="margin-bottom: 18px;">
                <h3 style="margin-bottom: 10px;">Message manuel</h3>
                <textarea id="manual_share_preview" rows="6" readonly style="width: 100%;"><?= htmlspecialchars($manualSharePreview, ENT_QUOTES, 'UTF-8'); ?></textarea>
                <button type="button" class="button ghost" style="margin-top: 10px;" data-copy-manual-preview="#manual_share_preview">Copier le message</button>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-bottom: 22px;">
            <h3 style="margin-bottom: 10px;">Lien d'inscription autonome</h3>
            <p style="margin: 0; color: var(--text-mid);">
                Partagez ce lien pour que chaque invité remplisse son propre formulaire. Le système crée automatiquement
                le code référence et stoppe la création quand les crédits invitations sont épuisés.
            </p>

            <?php if (!$guestRegistrationSchemaReady): ?>
                <p style="margin-top: 10px; color: #92400e;">
                    Le module de lien public n'est pas encore disponible sur ce serveur.
                </p>
            <?php elseif (empty($events)): ?>
                <p style="margin-top: 10px; color: var(--text-mid);">
                    Créez d'abord un événement pour obtenir un lien à partager.
                </p>
            <?php else: ?>
                <div class="event-link-list">
                    <?php foreach ($events as $event): ?>
                        <?php
                        $eventId = (int) ($event['id'] ?? 0);
                        $registrationLink = $eventRegistrationLinks[$eventId]['absolute'] ?? '';
                        $registrationPath = $eventRegistrationLinks[$eventId]['path'] ?? '';
                        ?>
                        <article class="event-link-card">
                            <div>
                                <h4><?= htmlspecialchars((string) ($event['title'] ?? 'Événement'), ENT_QUOTES, 'UTF-8'); ?></h4>
                                <p class="event-link-meta">
                                    Date:
                                    <?= htmlspecialchars((string) ($event['event_date'] ?? 'Non definie'), ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                                <?php if ($registrationLink !== ''): ?>
                                    <p class="event-link-url"><?= htmlspecialchars($registrationLink, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php else: ?>
                                    <p class="event-link-meta" style="margin-top: 8px; color: #92400e;">
                                        Impossible de générer le lien public pour cet événement.
                                    </p>
                                <?php endif; ?>
                            </div>

                            <?php if ($registrationLink !== '' && $registrationPath !== ''): ?>
                                <div class="event-link-actions">
                                    <a class="button ghost" href="<?= htmlspecialchars($registrationPath, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Ouvrir formulaire</a>
                                    <button
                                        class="button primary"
                                        type="button"
                                        data-copy-link="<?= htmlspecialchars($registrationLink, ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                        Copier lien formulaire
                                    </button>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card guests-register-card">
            <h3 style="margin-bottom: 12px;">Invités enregistrés</h3>
            <div class="guest-list-grid">
                <?php if (empty($guests)): ?>
                    <div class="guest-item-card">
                        <p style="margin: 0; color: var(--text-mid);">Aucun invité enregistré pour le moment.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($guests as $guest): ?>
                        <?php
                        $guestInvitationPath = $baseUrl . '/guest-invitation?code=' . rawurlencode((string) $guest['guest_code']);
                        $guestInvitationAbsolute = buildAbsoluteUrl($guestInvitationPath);
                        $seat = guestSeatFromCustomAnswers((string) ($guest['custom_answers'] ?? ''));
                        $seatLabel = trim($seat['table_name']) !== '' ? $seat['table_name'] : 'Non affectee';
                        if (trim($seat['table_number']) !== '') {
                            $seatLabel .= ' (#' . $seat['table_number'] . ')';
                        }
                        $statusRaw = strtolower(trim((string) ($guest['rsvp_status'] ?? 'pending')));
                        $statusClass = in_array($statusRaw, ['pending', 'confirmed', 'declined'], true) ? $statusRaw : 'pending';
                        ?>
                        <article class="guest-item-card">
                            <div class="guest-item-top">
                                <div>
                                    <h4 class="guest-item-name"><?= htmlspecialchars((string) ($guest['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h4>
                                    <p class="guest-item-code">Code: <?= htmlspecialchars((string) ($guest['guest_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <span class="guest-status-chip <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?= htmlspecialchars((string) ($guest['rsvp_status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </div>

                            <div class="guest-info-grid">
                                <p><strong>Email:</strong> <?= htmlspecialchars((string) ($guest['email'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></p>
                                <p><strong>Téléphone:</strong> <?= htmlspecialchars((string) ($guest['phone'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></p>
                                <p><strong>Événement:</strong> <?= htmlspecialchars((string) ($guest['event_title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                                <p><strong>Check-in:</strong> <?= (int) ($guest['check_in_count'] ?? 0); ?></p>
                                <p><strong>Table:</strong> <?= htmlspecialchars($seatLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>

                            <div class="guest-section">
                                <div class="guests-link-actions">
                                    <a class="button ghost" href="<?= $guestInvitationPath; ?>" target="_blank" rel="noopener">Ouvrir invitation</a>
                                    <button
                                        class="button ghost"
                                        type="button"
                                        data-copy-link="<?= htmlspecialchars($guestInvitationAbsolute, ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                        Copier lien
                                    </button>
                                </div>
                            </div>

                            <div class="guest-section">
                                <details class="guest-advanced">
                                    <summary>Actions avancees</summary>
                                    <div class="guest-advanced-content">
                                        <?php if ($guestCustomAnswersEnabled): ?>
                                            <div>
                                                <p class="guests-seat-label">Affectation de table</p>
                                                <form method="post" class="guests-seat-form">
                                                    <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
                                                    <input type="hidden" name="action" value="assign-table">
                                                    <input type="hidden" name="guest_id" value="<?= (int) $guest['id']; ?>">
                                                    <input type="text" name="table_name" placeholder="Nom table" value="<?= htmlspecialchars((string) ($seat['table_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="text" name="table_number" placeholder="Numero" value="<?= htmlspecialchars((string) ($seat['table_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <button class="button ghost" type="submit">Enregistrer table</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>

                                        <form method="post" class="guests-actions-form">
                                            <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
                                            <input type="hidden" name="action" value="send-guest-message">
                                            <input type="hidden" name="guest_id" value="<?= (int) $guest['id']; ?>">
                                            <select name="channel" required>
                                                <option value="email">Email</option>
                                                <?php if ($smsChannelReady): ?>
                                                    <option value="sms">SMS - <?= htmlspecialchars($smsProviderLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                                                <?php endif; ?>
                                                <?php if ($whatsAppChannelReady): ?>
                                                    <option value="whatsapp">WhatsApp - <?= htmlspecialchars($whatsAppProviderLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                                                <?php endif; ?>
                                                <option value="manual">Manuel</option>
                                            </select>
                                            <button class="button primary" type="submit">Envoyer</button>
                                        </form>
                                    </div>
                                </details>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const copyButtons = document.querySelectorAll("[data-copy-link]");
    copyButtons.forEach(function (button) {
        button.addEventListener("click", async function () {
            const link = button.getAttribute("data-copy-link") || "";
            if (!link) {
                return;
            }

            try {
                await navigator.clipboard.writeText(link);
                const originalLabel = button.textContent;
                button.textContent = "Copie";
                setTimeout(function () {
                    button.textContent = originalLabel;
                }, 1200);
            } catch (error) {
                window.prompt("Copiez ce lien:", link);
            }
        });
    });

    const manualCopyButton = document.querySelector("[data-copy-manual-preview]");
    if (manualCopyButton) {
        manualCopyButton.addEventListener("click", async function () {
            const selector = manualCopyButton.getAttribute("data-copy-manual-preview");
            const source = selector ? document.querySelector(selector) : null;
            const text = source ? source.value : "";
            if (!text) {
                return;
            }
            try {
                await navigator.clipboard.writeText(text);
                const original = manualCopyButton.textContent;
                manualCopyButton.textContent = "Copie";
                setTimeout(function () {
                    manualCopyButton.textContent = original;
                }, 1200);
            } catch (error) {
                window.prompt("Copiez ce message:", text);
            }
        });
    }
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>

