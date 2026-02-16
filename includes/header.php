<?php
require_once __DIR__ . '/../app/config/constants.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (function_exists('ini_set')) {
    ini_set('default_charset', 'UTF-8');
}
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$mainCssPath = __DIR__ . '/../assets/css/main.css';
$mainCssVersion = is_file($mainCssPath) ? (string) filemtime($mainCssPath) : (string) time();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitations Numeriques</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= $baseUrl; ?>/assets/images/Logo12.png">
    <link rel="stylesheet" href="<?= $baseUrl; ?>/assets/css/main.css?v=<?= htmlspecialchars($mainCssVersion, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if (isset($pageHeadExtra) && is_string($pageHeadExtra) && $pageHeadExtra !== ''): ?>
        <?= $pageHeadExtra; ?>
    <?php endif; ?>
</head>
<body>
