<?php
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/security.php';
require_once __DIR__ . '/app/config/constants.php';
require_once __DIR__ . '/app/helpers/credits.php';
require_once __DIR__ . '/app/helpers/mailer.php';
require_once __DIR__ . '/app/helpers/guest_registration.php';

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$guestRegistrationSchemaReady = ensureGuestRegistrationSchema($pdo);
$token = normalizeGuestRegistrationToken((string) ($_GET['token'] ?? ($_POST['token'] ?? '')));

$event = null;
$message = null;
$messageType = 'success';
$createdGuestCode = strtoupper(trim((string) ($_GET['created'] ?? '')));
$createdGuestCode = preg_replace('/[^A-Z0-9\-]/', '', $createdGuestCode ?? '');
if (!is_string($createdGuestCode)) {
    $createdGuestCode = '';
}
$createdInvitationPath = '';
$createdInvitationAbsolute = '';
$creditControlEnabled = false;
$invitationRemaining = null;

$formFullName = '';
$formEmail = '';
$formPhone = '';

if ($createdGuestCode !== '') {
    $createdInvitationPath = $baseUrl . '/guest-invitation?code=' . rawurlencode($createdGuestCode);
    $createdInvitationAbsolute = buildAbsoluteUrl($createdInvitationPath);
}

$refreshEventAndCredits = static function () use ($pdo, $token, $guestRegistrationSchemaReady, &$event, &$creditControlEnabled, &$invitationRemaining): void {
    $event = null;
    $creditControlEnabled = false;
    $invitationRemaining = null;

    if (!$guestRegistrationSchemaReady || $token === '') {
        return;
    }

    $event = findEventByGuestRegistrationToken($pdo, $token);
    if (!$event) {
        return;
    }

    $summary = getUserCreditSummary($pdo, (int) ($event['user_id'] ?? 0));
    $creditControlEnabled = !empty($summary['credit_controls_enabled']);
    if ($creditControlEnabled) {
        $invitationRemaining = (int) ($summary['invitation_remaining'] ?? 0);
    }
};

$refreshEventAndCredits();

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($requestMethod === 'POST') {
    $formFullName = sanitizeInput($_POST['full_name'] ?? '');
    $formEmail = sanitizeInput($_POST['email'] ?? '');
    $formPhone = sanitizeInput($_POST['phone'] ?? '');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token de sécurité invalide.';
        $messageType = 'error';
    } elseif (!$guestRegistrationSchemaReady) {
        $message = "Le service d'inscription est temporairement indisponible.";
        $messageType = 'error';
    } elseif (!$event) {
        $message = "Lien d'inscription invalide ou introuvable.";
        $messageType = 'error';
    } else {
        $creation = createGuestThroughRegistrationLink(
            $pdo,
            (int) ($event['id'] ?? 0),
            (int) ($event['user_id'] ?? 0),
            $formFullName,
            $formEmail,
            $formPhone
        );

        if (!empty($creation['ok'])) {
            $createdGuestCode = (string) ($creation['guest_code'] ?? '');
            $redirectPath = $baseUrl . '/guest-register?token=' . rawurlencode($token) . '&created=' . rawurlencode($createdGuestCode);
            header('Location: ' . $redirectPath);
            exit;
        } else {
            $message = (string) ($creation['message'] ?? "Impossible de créer l'invitation.");
            $messageType = stripos($message, 'Limite atteinte') !== false ? 'warning' : 'error';
        }
    }

    $refreshEventAndCredits();
}

$eventIsOpen = $event
    && (int) ($event['is_active'] ?? 0) === 1
    && (int) ($event['public_registration_enabled'] ?? 0) === 1;
$creditsAvailable = !$creditControlEnabled || (int) ($invitationRemaining ?? 0) > 0;

if ($requestMethod !== 'POST' && $createdGuestCode !== '' && $message === null) {
    $message = 'Inscription enregistrée avec succès.';
    if ($creditControlEnabled) {
        $message .= ' Crédits invitations restants pour ce lien: ' . max(0, (int) ($invitationRemaining ?? 0)) . '.';
    }
    $messageType = 'success';
}

$pageHeadExtra = <<<'HTML'
<style>
    .public-register-wrap {
        max-width: 920px;
        margin: 0 auto;
    }

    .public-register-event {
        margin-bottom: 18px;
    }

    .public-register-event h3 {
        margin-bottom: 8px;
    }

    .public-register-meta {
        margin: 6px 0 0 0;
        color: var(--text-mid);
    }

    .public-register-ref {
        margin-top: 10px;
        padding: 10px;
        border-radius: 12px;
        border: 1px dashed rgba(148, 163, 184, 0.45);
        background: #f8fafc;
        color: var(--text-dark);
        font-size: 0.9rem;
        word-break: break-all;
    }

    .public-register-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 12px;
    }

    .public-register-actions .button {
        min-width: 200px;
    }

    @media (max-width: 768px) {
        .public-register-actions {
            display: grid;
        }

        .public-register-actions .button {
            width: 100%;
            min-width: 0;
        }
    }
</style>
HTML;
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<section class="container section">
    <div class="form-card public-register-wrap">
        <div class="section-title">
            <span>Inscription invité</span>
            <h2>Recevoir mon invitation QR</h2>
        </div>

        <?php if ($message): ?>
            <div class="card" style="margin-bottom: 18px;">
                <?php
                $messageColor = '#166534';
                if ($messageType === 'error') {
                    $messageColor = '#dc2626';
                } elseif ($messageType === 'warning') {
                    $messageColor = '#92400e';
                }
                ?>
                <p style="color: <?= $messageColor; ?>;"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$guestRegistrationSchemaReady): ?>
            <div class="card">
                <p style="color: #dc2626;">Service d'inscription indisponible pour le moment.</p>
            </div>
        <?php elseif (!$event): ?>
            <div class="card">
                <p style="color: #dc2626;">Ce lien n'est pas valide ou l'événement n'existe plus.</p>
            </div>
        <?php else: ?>
            <div class="card public-register-event">
                <h3><?= htmlspecialchars((string) ($event['title'] ?? 'Événement'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <p class="public-register-meta"><strong>Date:</strong> <?= htmlspecialchars((string) ($event['event_date'] ?? 'À définir'), ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="public-register-meta"><strong>Lieu:</strong> <?= htmlspecialchars((string) ($event['location'] ?? 'À définir'), ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="public-register-meta"><strong>Organisateur:</strong> <?= htmlspecialchars((string) ($event['organizer_name'] ?? 'Organisateur'), ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if ($creditControlEnabled): ?>
                    <p class="public-register-meta">
                        <strong>Places restantes via crédits:</strong>
                        <?= (int) ($invitationRemaining ?? 0); ?>
                    </p>
                <?php endif; ?>
            </div>

            <?php if ($createdGuestCode !== ''): ?>
                <div class="card" style="margin-bottom: 18px;">
                    <h3 style="margin-bottom: 10px;">Votre inscription est confirmée</h3>
                    <p class="public-register-meta" style="margin-top: 0;">Conservez ce numero de reference:</p>
                    <p class="public-register-ref"><strong><?= htmlspecialchars($createdGuestCode, ENT_QUOTES, 'UTF-8'); ?></strong></p>
                    <p class="public-register-meta">Lien personnel d'invitation:</p>
                    <p class="public-register-ref"><?= htmlspecialchars($createdInvitationAbsolute, ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="public-register-actions">
                        <a class="button primary" href="<?= htmlspecialchars($createdInvitationPath, ENT_QUOTES, 'UTF-8'); ?>">Ouvrir mon invitation</a>
                        <a class="button ghost" href="<?= htmlspecialchars($createdInvitationPath, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Ouvrir dans un nouvel onglet</a>
                    </div>
                    <p class="public-register-meta" style="margin-top: 12px;">
                        Le QR code d'accès sera actif après confirmation de présence sur votre invitation.
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!$eventIsOpen): ?>
                <div class="card">
                    <p style="color: #92400e;">Les inscriptions sont fermées pour cet événement.</p>
                </div>
            <?php elseif (!$creditsAvailable): ?>
                <div class="card">
                    <p style="color: #92400e;">
                        Vous ne pouvez plus créer d'invitation avec ce lien: le quota de crédits invitations est atteint.
                    </p>
                </div>
            <?php elseif ($createdGuestCode !== ''): ?>
                <div class="card">
                    <p style="color: var(--text-mid);">
                        Votre inscription est déjà enregistrée. Ouvrez votre invitation pour confirmer votre présence.
                    </p>
                </div>
            <?php else: ?>
                <div class="card">
                    <h3 style="margin-bottom: 10px;">Remplissez votre formulaire</h3>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="form-group">
                            <label for="full_name">Nom complet</label>
                            <input id="full_name" name="full_name" type="text" required placeholder="Ex: Jean Mavoungou" value="<?= htmlspecialchars($formFullName, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="email">Email (optionnel)</label>
                            <input id="email" name="email" type="email" placeholder="invite@email.com" value="<?= htmlspecialchars($formEmail, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="phone">Telephone (optionnel)</label>
                            <input id="phone" name="phone" type="text" placeholder="+242 06 000 0000" value="<?= htmlspecialchars($formPhone, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <button class="button primary" type="submit">Créer mon invitation</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>

