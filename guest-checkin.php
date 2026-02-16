<?php
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/security.php';
require_once __DIR__ . '/app/config/constants.php';

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$code = strtoupper(trim((string) ($_GET['code'] ?? '')));
$code = preg_replace('/[^A-Z0-9\-]/', '', $code ?? '');
$scanMode = (string) ($_GET['scan'] ?? '') === '1';

$message = null;
$messageType = 'success';

function findGuestForCheckin(PDO $pdo, string $code): ?array
{
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
    $guest = findGuestForCheckin($pdo, $code);
}

if ($scanMode && $guest) {
    if (($guest['rsvp_status'] ?? 'pending') !== 'confirmed') {
        $message = 'Acces refuse: cet invite n est pas confirme.';
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

        $guest = findGuestForCheckin($pdo, $code);
        $updatedCount = (int) ($guest['check_in_count'] ?? 0);
        if ($previousCount <= 0) {
            $message = 'Entree validee avec succes.';
            $messageType = 'success';
        } else {
            $message = 'QR deja scanne auparavant. Nombre total de scans: ' . $updatedCount . '.';
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
            <h2>Validation d entree</h2>
        </div>

        <?php if (!$guest): ?>
            <div class="card" style="margin-bottom: 18px;">
                <p style="color: #dc2626;">Code invite invalide.</p>
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
                <p><strong>Evenement:</strong> <?= htmlspecialchars((string) ($guest['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Date:</strong> <?= htmlspecialchars((string) ($guest['event_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Lieu:</strong> <?= htmlspecialchars((string) ($guest['location'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Statut RSVP:</strong> <?= htmlspecialchars((string) ($guest['rsvp_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Scans:</strong> <?= (int) ($guest['check_in_count'] ?? 0); ?></p>
                <p><strong>Premiere entree:</strong> <?= htmlspecialchars((string) ($guest['check_in_time'] ?? 'Non enregistree'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <?php if (($guest['rsvp_status'] ?? 'pending') === 'confirmed' && !$scanMode): ?>
                <div class="card">
                    <p style="margin-bottom: 12px;">Pret pour validation d entree.</p>
                    <a class="button primary" href="<?= $baseUrl; ?>/guest-checkin?code=<?= rawurlencode((string) $guest['guest_code']); ?>&scan=1">Valider maintenant</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
