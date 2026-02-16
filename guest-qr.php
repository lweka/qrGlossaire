<?php
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/config/constants.php';
require_once __DIR__ . '/app/helpers/mailer.php';

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$code = strtoupper(trim((string) ($_GET['code'] ?? '')));
$code = preg_replace('/[^A-Z0-9\-]/', '', $code ?? '');
$forceDownload = (string) ($_GET['download'] ?? '0') === '1';

if ($code === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Code invité invalide.';
    exit;
}

$stmt = $pdo->prepare('SELECT id, guest_code FROM guests WHERE guest_code = :guest_code LIMIT 1');
$stmt->execute(['guest_code' => $code]);
$guest = $stmt->fetch();

if (!$guest) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Invite introuvable.';
    exit;
}

$checkinPath = $baseUrl . '/guest-checkin?code=' . rawurlencode((string) ($guest['guest_code'] ?? '')) . '&scan=1';
$checkinLink = buildAbsoluteUrl($checkinPath);
$qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?format=png&size=480x480&data=' . rawurlencode($checkinLink);

$pngData = false;
if (function_exists('curl_init')) {
    $curl = curl_init($qrApiUrl);
    if ($curl !== false) {
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_errno($curl);
        curl_close($curl);

        if ($curlError === 0 && is_string($response) && $response !== '' && $httpCode >= 200 && $httpCode < 300) {
            $pngData = $response;
        }
    }
}

if ($pngData === false && ini_get('allow_url_fopen')) {
    $streamContent = @file_get_contents($qrApiUrl);
    if (is_string($streamContent) && $streamContent !== '') {
        $pngData = $streamContent;
    }
}

if (!is_string($pngData) || $pngData === '') {
    http_response_code(502);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Impossible de générer le QR code pour le moment.';
    exit;
}

$fileName = 'inviteqr_' . preg_replace('/[^A-Z0-9\-]/', '', (string) ($guest['guest_code'] ?? $code)) . '.png';
header('Content-Type: image/png');
header('Content-Length: ' . strlen($pngData));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: ' . ($forceDownload ? 'attachment' : 'inline') . '; filename="' . $fileName . '"');
echo $pngData;
exit;

