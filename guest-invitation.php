<?php
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/security.php';
require_once __DIR__ . '/app/config/constants.php';
require_once __DIR__ . '/app/helpers/mailer.php';

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$code = strtoupper(trim((string) ($_GET['code'] ?? '')));
$code = preg_replace('/[^A-Z0-9\-]/', '', $code ?? '');

$message = null;
$messageType = 'success';

function findGuestByCode(PDO $pdo, string $code): ?array
{
    $stmt = $pdo->prepare(
        'SELECT
            g.id,
            g.full_name,
            g.email,
            g.phone,
            g.rsvp_status,
            g.guest_code,
            g.check_in_count,
            g.check_in_time,
            e.id AS event_id,
            e.title,
            e.event_date,
            e.location,
            e.invitation_design
         FROM guests g
         INNER JOIN events e ON e.id = g.event_id
         WHERE g.guest_code = :guest_code
         LIMIT 1'
    );
    $stmt->execute(['guest_code' => $code]);
    $guest = $stmt->fetch();
    return $guest ?: null;
}

$guest = null;
if ($code !== '') {
    $guest = findGuestByCode($pdo, $code);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token de securite invalide.';
        $messageType = 'error';
    } elseif (!$guest) {
        $message = 'Invitation introuvable.';
        $messageType = 'error';
    } else {
        $action = sanitizeInput($_POST['action'] ?? '');
        if ($action === 'rsvp') {
            $status = sanitizeInput($_POST['status'] ?? '');
            if (!in_array($status, ['confirmed', 'declined'], true)) {
                $message = 'Reponse invalide.';
                $messageType = 'error';
            } else {
                $updateStmt = $pdo->prepare('UPDATE guests SET rsvp_status = :rsvp_status WHERE id = :id');
                $updateStmt->execute([
                    'rsvp_status' => $status,
                    'id' => (int) $guest['id'],
                ]);

                $guest = findGuestByCode($pdo, $code);
                if ($status === 'confirmed') {
                    $message = 'Merci. Votre presence est confirmee.';
                    $messageType = 'success';
                } else {
                    $message = 'Votre absence a bien ete enregistree.';
                    $messageType = 'warning';
                }
            }
        }
    }
}

$eventMessage = '';
if ($guest) {
    $design = json_decode((string) ($guest['invitation_design'] ?? ''), true);
    if (is_array($design)) {
        $eventMessage = trim((string) ($design['message'] ?? ''));
    }
}

$checkinLink = '';
$qrImageUrl = '';
if ($guest) {
    $checkinPath = $baseUrl . '/guest-checkin?code=' . rawurlencode((string) $guest['guest_code']) . '&scan=1';
    $checkinLink = buildAbsoluteUrl($checkinPath);
    $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode($checkinLink);
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<section class="container section">
    <div class="form-card" style="max-width: 760px;">
        <div class="section-title">
            <span>Invitation</span>
            <h2>Votre invitation personnelle</h2>
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

        <?php if (!$guest): ?>
            <div class="card">
                <p style="color: #dc2626;">Invitation invalide ou non reconnue.</p>
            </div>
        <?php else: ?>
            <div class="card" style="margin-bottom: 18px;">
                <h3 style="margin-bottom: 10px;"><?= htmlspecialchars((string) ($guest['title'] ?? 'Evenement'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><strong>Invite:</strong> <?= htmlspecialchars((string) ($guest['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Date:</strong> <?= htmlspecialchars((string) ($guest['event_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Lieu:</strong> <?= htmlspecialchars((string) ($guest['location'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if ($eventMessage !== ''): ?>
                    <p><strong>Message:</strong> <?= htmlspecialchars($eventMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <p><strong>Code invite:</strong> <?= htmlspecialchars((string) ($guest['guest_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Statut RSVP:</strong> <?= htmlspecialchars((string) ($guest['rsvp_status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <div class="card" style="margin-bottom: 18px;">
                <h3 style="margin-bottom: 12px;">Confirmer votre reponse</h3>
                <form method="post" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
                    <input type="hidden" name="action" value="rsvp">
                    <input type="hidden" name="status" value="confirmed">
                    <button class="button primary" type="submit">Je confirme ma presence</button>
                </form>
                <form method="post" style="margin-top: 10px;">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
                    <input type="hidden" name="action" value="rsvp">
                    <input type="hidden" name="status" value="declined">
                    <button class="button ghost" type="submit">Je ne pourrai pas venir</button>
                </form>
            </div>

            <?php if (($guest['rsvp_status'] ?? 'pending') === 'confirmed'): ?>
                <div class="card">
                    <h3 style="margin-bottom: 12px;">Votre QR code d acces</h3>
                    <p style="margin-bottom: 14px;">Presentez ce QR code a l entree pour valider votre arrivee.</p>
                    <div class="qr-box">
                        <img src="<?= htmlspecialchars($qrImageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="QR code personnel">
                        <a class="button ghost" href="<?= htmlspecialchars($checkinLink, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Lien de validation</a>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
