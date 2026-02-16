<?php
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/security.php';
require_once __DIR__ . '/app/config/constants.php';
require_once __DIR__ . '/app/helpers/credits.php';

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$code = strtoupper(trim((string) ($_GET['code'] ?? '')));
$code = preg_replace('/[^A-Z0-9\-]/', '', $code ?? '');
$scanMode = (string) ($_GET['scan'] ?? '') === '1';
$guestCustomAnswersEnabled = creditColumnExists($pdo, 'guests', 'custom_answers');

$message = null;
$messageType = 'success';

function findGuestForCheckin(PDO $pdo, string $code, bool $withCustomAnswers): ?array
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
         LIMIT 1'
    );
    $stmt->execute(['guest_code' => $code]);
    $guest = $stmt->fetch();
    return $guest ?: null;
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
    $guest = findGuestForCheckin($pdo, $code, $guestCustomAnswersEnabled);
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

        $guest = findGuestForCheckin($pdo, $code, $guestCustomAnswersEnabled);
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

        <?php if (!$guest): ?>
            <div class="card" style="margin-bottom: 18px;">
                <p style="color: #dc2626;">Code invité invalide.</p>
            </div>
        <?php else: ?>
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

            <div class="card" style="margin-bottom: 18px;">
                <p><strong>Invite:</strong> <?= htmlspecialchars((string) ($guest['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
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
                <div class="card">
                    <p style="margin-bottom: 12px;">Prêt pour validation d'entrée.</p>
                    <a class="button primary" href="<?= $baseUrl; ?>/guest-checkin?code=<?= rawurlencode((string) $guest['guest_code']); ?>&scan=1">Valider maintenant</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>

