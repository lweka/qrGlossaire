<?php
require_once __DIR__ . '/includes/auth-check.php';
requireRole('organizer');
require_once __DIR__ . '/app/helpers/credits.php';

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$code = strtoupper(trim((string) ($_GET['code'] ?? '')));
$code = preg_replace('/[^A-Z0-9\-]/', '', $code ?? '');
$scanMode = (string) ($_GET['scan'] ?? '') === '1';
$guestCustomAnswersEnabled = creditColumnExists($pdo, 'guests', 'custom_answers');

$message = null;
$messageType = 'success';

function findGuestForCheckin(PDO $pdo, int $organizerId, string $code, bool $withCustomAnswers): ?array
{
    $customAnswersSelect = $withCustomAnswers ? ', g.custom_answers' : ', NULL AS custom_answers';
    $stmt = $pdo->prepare(
        'SELECT
            g.id,
            g.full_name,
            g.rsvp_status,
            g.guest_code,
            g.check_in_count,
            g.check_in_time,
            e.title,
            e.event_date,
            e.location
            ' . $customAnswersSelect . '
         FROM guests g
         INNER JOIN events e ON e.id = g.event_id
         WHERE g.guest_code = :guest_code
           AND e.user_id = :user_id
         LIMIT 1'
    );
    $stmt->execute([
        'guest_code' => $code,
        'user_id' => $organizerId,
    ]);
    $guest = $stmt->fetch();
    return $guest ?: null;
}

function guestCodeBelongsToAnotherAccount(PDO $pdo, int $organizerId, string $code): bool
{
    if ($organizerId <= 0 || $code === '') {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT 1
         FROM guests g
         INNER JOIN events e ON e.id = g.event_id
         WHERE g.guest_code = :guest_code
           AND e.user_id <> :user_id
         LIMIT 1'
    );
    $stmt->execute([
        'guest_code' => $code,
        'user_id' => $organizerId,
    ]);

    return (bool) $stmt->fetchColumn();
}

function guestSeatForCheckin(?string $rawJson): array
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

$guest = null;
if ($code !== '') {
    $guest = findGuestForCheckin($pdo, $userId, $code, $guestCustomAnswersEnabled);

    if (!$guest && guestCodeBelongsToAnotherAccount($pdo, $userId, $code)) {
        $message = "Le QR code n'est pas associé à ce compte.";
        $messageType = 'error';
    }
}

if ($scanMode && $guest) {
    if (($guest['rsvp_status'] ?? 'pending') !== 'confirmed') {
        $message = "Accès refusé: cet invité n'est pas confirmé.";
        $messageType = 'error';
    } else {
        $previousCount = (int) ($guest['check_in_count'] ?? 0);
        $updateStmt = $pdo->prepare(
            'UPDATE guests
             SET check_in_count = check_in_count + 1,
                 check_in_time = IFNULL(check_in_time, NOW())
             WHERE id = :id'
        );
        $updateStmt->execute(['id' => (int) $guest['id']]);

        $guest = findGuestForCheckin($pdo, $userId, $code, $guestCustomAnswersEnabled);
        $updatedCount = (int) ($guest['check_in_count'] ?? 0);
        if ($previousCount <= 0) {
            $message = "Entrée validée avec succès.";
            $messageType = 'success';
        } else {
            $message = 'QR déjà scanné auparavant. Nombre total de scans: ' . $updatedCount . '.';
            $messageType = 'warning';
        }
    }
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<section class="container section">
    <div class="form-card" style="max-width: 760px;">
        <div class="section-title">
            <span>Check-in</span>
            <h2>Validation d'entrée</h2>
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
            <?php if (!$message): ?>
                <div class="card" style="margin-bottom: 18px;">
                    <p style="color: #dc2626;">Code invité invalide.</p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="card" style="margin-bottom: 18px;">
                <p><strong>Invité:</strong> <?= htmlspecialchars((string) ($guest['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Événement:</strong> <?= htmlspecialchars((string) ($guest['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Date:</strong> <?= htmlspecialchars((string) ($guest['event_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Lieu:</strong> <?= htmlspecialchars((string) ($guest['location'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <?php
                $seat = guestSeatForCheckin((string) ($guest['custom_answers'] ?? ''));
                $seatName = trim((string) ($seat['table_name'] ?? ''));
                $seatNumber = trim((string) ($seat['table_number'] ?? ''));
                ?>
                <?php if ($seatName !== '' || $seatNumber !== ''): ?>
                    <?php
                    $seatLabel = $seatName !== '' ? $seatName : 'Table';
                    if ($seatNumber !== '') {
                        $seatLabel .= ' (#' . $seatNumber . ')';
                    }
                    ?>
                    <p><strong>Table:</strong> <?= htmlspecialchars($seatLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <p><strong>Statut RSVP:</strong> <?= htmlspecialchars((string) ($guest['rsvp_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Scans:</strong> <?= (int) ($guest['check_in_count'] ?? 0); ?></p>
                <p><strong>Première entrée:</strong> <?= htmlspecialchars((string) ($guest['check_in_time'] ?? 'Non enregistrée'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <?php if (($guest['rsvp_status'] ?? 'pending') === 'confirmed' && !$scanMode): ?>
                <div class="card" style="margin-bottom: 18px;">
                    <p style="margin-bottom: 12px;">Prêt pour validation d'entrée.</p>
                    <a class="button primary" href="<?= $baseUrl; ?>/guest-checkin?code=<?= rawurlencode((string) $guest['guest_code']); ?>&scan=1">Valider maintenant</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($scanMode): ?>
            <div class="card">
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a class="button primary" href="<?= $baseUrl; ?>/scan-checkin">Scanner un autre code</a>
                    <a class="button ghost" href="<?= $baseUrl; ?>/guests">Retour aux invités</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
