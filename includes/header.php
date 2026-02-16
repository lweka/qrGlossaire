<?php
require_once __DIR__ . '/../app/config/constants.php';
require_once __DIR__ . '/../app/helpers/seo.php';

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

$seoOverrides = [];
if (isset($pageTitle) && is_string($pageTitle)) {
    $seoOverrides['title'] = $pageTitle;
}
if (isset($pageDescription) && is_string($pageDescription)) {
    $seoOverrides['description'] = $pageDescription;
}
if (isset($pageKeywords) && is_string($pageKeywords)) {
    $seoOverrides['keywords'] = $pageKeywords;
}
if (isset($pageRobots) && is_string($pageRobots)) {
    $seoOverrides['robots'] = $pageRobots;
}
if (isset($pageCanonical) && is_string($pageCanonical)) {
    $seoOverrides['canonical'] = $pageCanonical;
}
if (isset($pageOgType) && is_string($pageOgType)) {
    $seoOverrides['og_type'] = $pageOgType;
}
if (isset($pageOgImage) && is_string($pageOgImage)) {
    $seoOverrides['og_image'] = $pageOgImage;
}
if (isset($pageOgImageAlt) && is_string($pageOgImageAlt)) {
    $seoOverrides['og_image_alt'] = $pageOgImageAlt;
}
if (isset($pageTwitterCard) && is_string($pageTwitterCard)) {
    $seoOverrides['twitter_card'] = $pageTwitterCard;
}
if (isset($pageSiteName) && is_string($pageSiteName)) {
    $seoOverrides['site_name'] = $pageSiteName;
}
if (isset($pageJsonLd) && (is_array($pageJsonLd) || is_string($pageJsonLd))) {
    $seoOverrides['json_ld'] = $pageJsonLd;
}

$seoMeta = seoResolveMeta($seoOverrides);
if (!headers_sent() && strpos((string) ($seoMeta['robots'] ?? ''), 'noindex') !== false) {
    header('X-Robots-Tag: ' . (string) $seoMeta['robots']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string) ($seoMeta['title'] ?? 'InviteQR'), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?= htmlspecialchars((string) ($seoMeta['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <?php if (!empty($seoMeta['keywords'])): ?>
        <meta name="keywords" content="<?= htmlspecialchars((string) $seoMeta['keywords'], ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <meta name="robots" content="<?= htmlspecialchars((string) ($seoMeta['robots'] ?? 'index,follow'), ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="googlebot" content="<?= htmlspecialchars((string) ($seoMeta['robots'] ?? 'index,follow'), ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="theme-color" content="#4a6fa5">
    <link rel="canonical" href="<?= htmlspecialchars((string) ($seoMeta['canonical'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

    <meta property="og:locale" content="fr_FR">
    <meta property="og:type" content="<?= htmlspecialchars((string) ($seoMeta['og_type'] ?? 'website'), ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?= htmlspecialchars((string) ($seoMeta['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?= htmlspecialchars((string) ($seoMeta['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?= htmlspecialchars((string) ($seoMeta['canonical'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?= htmlspecialchars((string) ($seoMeta['og_image'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image:alt" content="<?= htmlspecialchars((string) ($seoMeta['og_image_alt'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:site_name" content="<?= htmlspecialchars((string) ($seoMeta['site_name'] ?? 'InviteQR'), ENT_QUOTES, 'UTF-8'); ?>">

    <meta name="twitter:card" content="<?= htmlspecialchars((string) ($seoMeta['twitter_card'] ?? 'summary_large_image'), ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:title" content="<?= htmlspecialchars((string) ($seoMeta['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars((string) ($seoMeta['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars((string) ($seoMeta['og_image'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= $baseUrl; ?>/assets/images/Logo12.png">
    <link rel="stylesheet" href="<?= $baseUrl; ?>/assets/css/main.css?v=<?= htmlspecialchars($mainCssVersion, ENT_QUOTES, 'UTF-8'); ?>">

    <?php if (!empty($seoMeta['json_ld']) && is_array($seoMeta['json_ld'])): ?>
        <?php foreach ($seoMeta['json_ld'] as $jsonLdBlock): ?>
            <?php if (is_array($jsonLdBlock) && !empty($jsonLdBlock)): ?>
                <script type="application/ld+json"><?= json_encode($jsonLdBlock, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (isset($pageHeadExtra) && is_string($pageHeadExtra) && $pageHeadExtra !== ''): ?>
        <?= $pageHeadExtra; ?>
    <?php endif; ?>
</head>
<body>
