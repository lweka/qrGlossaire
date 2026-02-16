<?php
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/security.php';
require_once __DIR__ . '/app/config/constants.php';
require_once __DIR__ . '/app/helpers/mailer.php';
require_once __DIR__ . '/app/helpers/credits.php';

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$code = strtoupper(trim((string) ($_GET['code'] ?? '')));
$code = preg_replace('/[^A-Z0-9\-]/', '', $code ?? '');
$guestCustomAnswersEnabled = creditColumnExists($pdo, 'guests', 'custom_answers');

$message = null;
$messageType = 'success';

function findGuestByCode(PDO $pdo, string $code, bool $withCustomAnswers): ?array
{
    $customAnswersSelect = $withCustomAnswers ? ', g.custom_answers' : ', NULL AS custom_answers';
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
            e.event_type,
            e.event_date,
            e.location,
            e.invitation_design
            ' . $customAnswersSelect . '
         FROM guests g
         INNER JOIN events e ON e.id = g.event_id
         WHERE g.guest_code = :guest_code
         LIMIT 1'
    );
    $stmt->execute(['guest_code' => $code]);
    $guest = $stmt->fetch();
    return $guest ?: null;
}

function guestSeatMetaFromJson(?string $rawJson): array
{
    if (!is_string($rawJson) || trim($rawJson) === '') {
        return ['table_name' => '', 'table_number' => ''];
    }

    $decoded = json_decode($rawJson, true);
    if (!is_array($decoded)) {
        return ['table_name' => '', 'table_number' => ''];
    }

    return [
        'table_name' => trim((string) ($decoded['table_name'] ?? '')),
        'table_number' => trim((string) ($decoded['table_number'] ?? '')),
    ];
}

function normalizeDisplayText(string $text): string
{
    $value = trim($text);
    if ($value === '') {
        return '';
    }

    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    if (
        function_exists('mb_check_encoding')
        && function_exists('mb_convert_encoding')
        && !mb_check_encoding($value, 'UTF-8')
    ) {
        $converted = @mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
        if (is_string($converted) && $converted !== '') {
            $value = $converted;
        }
    }

    return $value;
}

function normalizeStoredAssetPath(string $path): string
{
    $value = trim(str_replace('\\', '/', $path));
    if ($value === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $value) === 1) {
        return $value;
    }

    $value = ltrim($value, '/');
    if (strpos($value, '..') !== false) {
        return '';
    }
    if (strpos($value, 'assets/images/') !== 0) {
        return '';
    }

    return $value;
}

function buildPublicAssetUrl(string $path, string $baseUrl): string
{
    $safePath = normalizeStoredAssetPath($path);
    if ($safePath === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $safePath) === 1) {
        return $safePath;
    }

    $segments = array_filter(explode('/', $safePath), static fn ($segment): bool => $segment !== '');
    $encodedSegments = array_map(static fn ($segment): string => rawurlencode($segment), $segments);

    return $baseUrl . '/' . implode('/', $encodedSegments);
}

function resolveFallbackInvitationVisual(string $eventType): string
{
    $directory = __DIR__ . '/assets/images/modele_invitations';
    if (!is_dir($directory)) {
        return '';
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];
    $entries = scandir($directory);
    if ($entries === false) {
        return '';
    }

    $eventType = strtolower(trim($eventType));
    $keywordsByType = [
        'wedding' => ['mariage', 'wedding', 'marry'],
        'birthday' => ['anniv', 'anniversaire', 'birthday'],
        'corporate' => ['confe', 'conference', 'corporate', 'business'],
        'other' => ['invite', 'modele', 'model'],
    ];

    $candidateFiles = [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $fullPath = $directory . DIRECTORY_SEPARATOR . $entry;
        if (!is_file($fullPath)) {
            continue;
        }
        $ext = strtolower((string) pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            continue;
        }
        $candidateFiles[] = $entry;
    }

    if (empty($candidateFiles)) {
        return '';
    }

    natcasesort($candidateFiles);
    $candidateFiles = array_values($candidateFiles);

    $keywords = $keywordsByType[$eventType] ?? $keywordsByType['other'];
    foreach ($candidateFiles as $file) {
        $name = strtolower((string) pathinfo($file, PATHINFO_FILENAME));
        foreach ($keywords as $keyword) {
            if (strpos($name, $keyword) !== false) {
                return 'assets/images/modele_invitations/' . $file;
            }
        }
    }

    return 'assets/images/modele_invitations/' . $candidateFiles[0];
}

$guest = null;
if ($code !== '') {
    $guest = findGuestByCode($pdo, $code, $guestCustomAnswersEnabled);
}

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($requestMethod === 'POST') {
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
            } elseif ((string) ($guest['rsvp_status'] ?? 'pending') !== 'pending') {
                $message = 'Votre reponse a deja ete enregistree.';
                $messageType = 'warning';
            } else {
                $updateStmt = $pdo->prepare('UPDATE guests SET rsvp_status = :rsvp_status WHERE id = :id');
                $updateStmt->execute([
                    'rsvp_status' => $status,
                    'id' => (int) $guest['id'],
                ]);

                $guest = findGuestByCode($pdo, $code, $guestCustomAnswersEnabled);
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
$coverImagePath = '';
$coverImageAlt = 'Visuel invitation';
$seatMeta = ['table_name' => '', 'table_number' => ''];

if ($guest) {
    $design = json_decode((string) ($guest['invitation_design'] ?? ''), true);
    if (is_array($design)) {
        $eventMessage = normalizeDisplayText((string) ($design['message'] ?? ''));
        $coverImagePath = normalizeStoredAssetPath((string) ($design['cover_image'] ?? ''));
        $coverImageAltRaw = normalizeDisplayText((string) ($design['cover_alt'] ?? ''));
        if ($coverImageAltRaw !== '') {
            $coverImageAlt = $coverImageAltRaw;
        }
    }

    if ($coverImagePath === '') {
        $coverImagePath = resolveFallbackInvitationVisual((string) ($guest['event_type'] ?? 'other'));
    }

    $seatMeta = guestSeatMetaFromJson((string) ($guest['custom_answers'] ?? ''));
}

$coverImageUrl = $coverImagePath !== '' ? buildPublicAssetUrl($coverImagePath, $baseUrl) : '';

$checkinLink = '';
$qrImageUrl = '';
if ($guest) {
    $checkinPath = $baseUrl . '/guest-checkin?code=' . rawurlencode((string) $guest['guest_code']) . '&scan=1';
    $checkinLink = buildAbsoluteUrl($checkinPath);
    $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode($checkinLink);
}

$qrDownloadPath = '';
if ($guest) {
    $qrDownloadPath = $baseUrl . '/guest-qr?code=' . rawurlencode((string) $guest['guest_code']) . '&download=1';
}

$displayTitle = normalizeDisplayText((string) ($guest['title'] ?? 'Evenement'));
$displayGuestName = normalizeDisplayText((string) ($guest['full_name'] ?? ''));
$displayDate = normalizeDisplayText((string) ($guest['event_date'] ?? ''));
$displayLocation = normalizeDisplayText((string) ($guest['location'] ?? ''));
$displayCode = normalizeDisplayText((string) ($guest['guest_code'] ?? ''));
$displayRsvp = normalizeDisplayText((string) ($guest['rsvp_status'] ?? 'pending'));
$displayTableName = normalizeDisplayText((string) ($seatMeta['table_name'] ?? ''));
$displayTableNumber = normalizeDisplayText((string) ($seatMeta['table_number'] ?? ''));

$pageHeadExtra = <<<'HTML'
<style>
    .guest-invite-visual {
        position: relative;
        overflow: hidden;
        border-radius: 20px;
        margin-bottom: 18px;
        background: #0b1220;
    }
    .guest-invite-visual img {
        width: 100%;
        min-height: 260px;
        max-height: 420px;
        object-fit: cover;
        display: block;
    }
    .guest-invite-visual-overlay {
        position: absolute;
        inset: auto 0 0 0;
        padding: 18px 20px;
        background: linear-gradient(180deg, rgba(2, 6, 23, 0) 0%, rgba(2, 6, 23, 0.88) 65%);
        color: #ffffff;
    }
    .guest-invite-visual-overlay h3 {
        margin: 0 0 6px 0;
        color: #ffffff;
    }
    .guest-invite-visual-overlay p {
        margin: 0;
        color: rgba(255, 255, 255, 0.9);
    }
</style>
HTML;
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<section class="container section">
    <div class="form-card" style="max-width: 860px;">
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
            <?php if ($coverImageUrl !== ''): ?>
                <div class="guest-invite-visual">
                    <img src="<?= htmlspecialchars($coverImageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($coverImageAlt, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="guest-invite-visual-overlay">
                        <h3><?= htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?= htmlspecialchars($displayDate, ENT_QUOTES, 'UTF-8'); ?><?= $displayLocation !== '' ? ' - ' . htmlspecialchars($displayLocation, ENT_QUOTES, 'UTF-8') : ''; ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card" style="margin-bottom: 18px;">
                <h3 style="margin-bottom: 10px;"><?= htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><strong>Invite:</strong> <?= htmlspecialchars($displayGuestName, ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Date:</strong> <?= htmlspecialchars($displayDate, ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Lieu:</strong> <?= htmlspecialchars($displayLocation, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if ($displayTableName !== '' || $displayTableNumber !== ''): ?>
                    <?php
                    $tableLabel = $displayTableName !== '' ? $displayTableName : 'Table';
                    if ($displayTableNumber !== '') {
                        $tableLabel .= ' (#' . $displayTableNumber . ')';
                    }
                    ?>
                    <p><strong>Table:</strong> <?= htmlspecialchars($tableLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <?php if ($eventMessage !== ''): ?>
                    <p><strong>Message:</strong> <?= htmlspecialchars($eventMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <p><strong>Code invite:</strong> <?= htmlspecialchars($displayCode, ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Statut RSVP:</strong> <?= htmlspecialchars($displayRsvp, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <?php if (($guest['rsvp_status'] ?? 'pending') === 'pending'): ?>
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
            <?php elseif (($guest['rsvp_status'] ?? 'pending') === 'confirmed'): ?>
                <div class="card" style="margin-bottom: 18px;">
                    <p style="color: #166534; margin: 0;">
                        Votre presence est deja confirmee. Votre QR code d acces est actif ci-dessous.
                    </p>
                </div>
            <?php else: ?>
                <div class="card" style="margin-bottom: 18px;">
                    <p style="color: #92400e; margin: 0;">
                        Votre reponse est deja enregistree. Si vous devez modifier ce statut, contactez l organisateur.
                    </p>
                </div>
            <?php endif; ?>

            <?php if (($guest['rsvp_status'] ?? 'pending') === 'confirmed'): ?>
                <div class="card">
                    <h3 style="margin-bottom: 12px;">Votre QR code d acces</h3>
                    <p style="margin-bottom: 14px;">Presentez ce QR code a l entree pour valider votre arrivee.</p>
                    <div class="qr-box">
                        <img src="<?= htmlspecialchars($qrImageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="QR code personnel">
                        <a class="button primary" href="<?= htmlspecialchars($qrDownloadPath, ENT_QUOTES, 'UTF-8'); ?>">Telecharger mon QR</a>
                        <a class="button ghost" href="<?= htmlspecialchars($checkinLink, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Lien de validation</a>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
