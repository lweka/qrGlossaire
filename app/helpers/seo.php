<?php
require_once __DIR__ . '/../config/constants.php';

function seoRequestScheme(): string
{
    $forwardedProto = trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwardedProto !== '') {
        $firstProto = strtolower(trim(explode(',', $forwardedProto)[0]));
        if ($firstProto === 'https' || $firstProto === 'http') {
            return $firstProto;
        }
    }

    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    if ($https !== '' && $https !== 'off' && $https !== '0') {
        return 'https';
    }

    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return 'https';
    }

    return 'http';
}

function seoRequestHost(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));
    if ($host === '') {
        return 'localhost';
    }

    $cleanHost = preg_replace('/[^a-zA-Z0-9\.\-\:\[\]]/', '', $host);
    return is_string($cleanHost) && $cleanHost !== '' ? $cleanHost : 'localhost';
}

function seoBasePath(): string
{
    return defined('BASE_URL') ? rtrim((string) BASE_URL, '/') : '';
}

function seoNormalizePath(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '/';
    }

    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    $normalized = preg_replace('#/+#', '/', $path);
    if (!is_string($normalized) || $normalized === '') {
        return '/';
    }

    if ($normalized !== '/' && substr($normalized, -1) === '/') {
        $normalized = rtrim($normalized, '/');
    }

    return $normalized === '' ? '/' : $normalized;
}

function seoRoutePath(string $relativePath): string
{
    $basePath = seoBasePath();
    $normalized = seoNormalizePath($relativePath);
    if ($normalized === '/') {
        return $basePath === '' ? '/' : $basePath . '/';
    }

    return ($basePath === '' ? '' : $basePath) . $normalized;
}

function seoAbsoluteUrl(string $path = '/'): string
{
    return seoRequestScheme() . '://' . seoRequestHost() . seoNormalizePath($path);
}

function seoAppAbsoluteUrl(string $relativePath = '/'): string
{
    return seoAbsoluteUrl(seoRoutePath($relativePath));
}

function seoCurrentPath(): string
{
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string) parse_url($requestUri, PHP_URL_PATH);
    return seoNormalizePath($path);
}

function seoCurrentCanonicalUrl(): string
{
    return seoAbsoluteUrl(seoCurrentPath());
}

function seoScriptKey(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptName = trim($scriptName, '/');
    if ($scriptName === '') {
        return '';
    }

    $segments = explode('/', $scriptName);
    $file = strtolower((string) end($segments));
    if ($file === '') {
        return '';
    }

    if (count($segments) > 1 && strtolower((string) $segments[count($segments) - 2]) === 'admin') {
        return 'admin/' . $file;
    }

    return $file;
}

function seoIndexableRoutes(): array
{
    return [
        [
            'path' => '/',
            'file' => 'index.php',
            'changefreq' => 'weekly',
            'priority' => '1.0',
        ],
        [
            'path' => '/invitation',
            'file' => 'invitation.php',
            'changefreq' => 'weekly',
            'priority' => '0.8',
        ],
    ];
}

function seoNoindexScriptList(): array
{
    return [
        'login.php',
        'register.php',
        'activate.php',
        'logout.php',
        'dashboard.php',
        'create-event.php',
        'guests.php',
        'scan-checkin.php',
        'communications.php',
        'reports.php',
        'settings.php',
        'guest-register.php',
        'guest-invitation.php',
        'guest-checkin.php',
        'guest-qr.php',
        'admin/login.php',
        'admin/dashboard.php',
        'admin/users.php',
        'admin/validation.php',
        'admin/logs.php',
        'admin/settings.php',
        'admin/index.php',
    ];
}

function seoDefaultMeta(): array
{
    $appName = defined('APP_NAME') ? (string) APP_NAME : 'InviteQR';
    $defaultTitle = $appName . ' | Invitations numériques avec QR Code';
    $defaultDescription = 'Plateforme pour créer, partager et valider des invitations numériques avec QR Code.';
    $defaultImage = seoAppAbsoluteUrl('/assets/images/Logo12.png');
    $scriptKey = seoScriptKey();
    $isNoindex = in_array($scriptKey, seoNoindexScriptList(), true) || strpos($scriptKey, 'admin/') === 0;

    $meta = [
        'title' => $defaultTitle,
        'description' => $defaultDescription,
        'keywords' => 'invitation numérique, qr code, invitation événement, gestion invités, check-in qr',
        'robots' => $isNoindex ? 'noindex,nofollow,noarchive' : 'index,follow,max-image-preview:large',
        'canonical' => seoCurrentCanonicalUrl(),
        'og_type' => 'website',
        'og_image' => $defaultImage,
        'og_image_alt' => $appName . ' - Logo',
        'twitter_card' => 'summary_large_image',
        'site_name' => $appName,
        'json_ld' => [],
    ];

    switch ($scriptKey) {
        case 'index.php':
            $meta['title'] = $appName . ' | Invitations QR Code pour événements';
            $meta['description'] = 'Créez des invitations numériques élégantes, suivez les RSVPs et validez les invités avec QR Code.';
            $meta['canonical'] = seoAppAbsoluteUrl('/');
            break;
        case 'invitation.php':
            $meta['title'] = "Modèles d'invitations numériques | " . $appName;
            $meta['description'] = "Découvrez des modèles d'invitations numériques modernes adaptés à tous vos événements.";
            $meta['og_type'] = 'article';
            $meta['canonical'] = seoAppAbsoluteUrl('/invitation');
            break;
        case 'login.php':
            $meta['title'] = 'Connexion organisateur | ' . $appName;
            $meta['description'] = 'Connectez-vous à votre espace organisateur pour gérer vos événements et invités.';
            break;
        case 'register.php':
            $meta['title'] = 'Création de compte organisateur | ' . $appName;
            $meta['description'] = 'Ouvrez votre compte organisateur et lancez vos invitations numériques QR Code.';
            break;
        case 'guest-register.php':
            $meta['title'] = 'Inscription invité | ' . $appName;
            $meta['description'] = "Formulaire d'inscription invité pour recevoir une invitation QR Code.";
            break;
        case 'guest-invitation.php':
            $meta['title'] = 'Invitation personnelle | ' . $appName;
            $meta['description'] = 'Invitation personnelle et confirmation RSVP.';
            break;
    }

    $organizationLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $appName,
        'url' => seoAppAbsoluteUrl('/'),
        'logo' => $defaultImage,
    ];

    if ($scriptKey === 'index.php') {
        $websiteLd = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $appName,
            'url' => seoAppAbsoluteUrl('/'),
            'inLanguage' => 'fr',
        ];

        $softwareLd = [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $appName,
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'url' => seoAppAbsoluteUrl('/'),
            'description' => $meta['description'],
        ];

        $meta['json_ld'] = [$organizationLd, $websiteLd, $softwareLd];
    } elseif ($scriptKey === 'invitation.php') {
        $pageLd = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $meta['title'],
            'url' => $meta['canonical'],
            'description' => $meta['description'],
            'inLanguage' => 'fr',
        ];
        $meta['json_ld'] = [$organizationLd, $pageLd];
    } else {
        if (!$isNoindex) {
            $meta['json_ld'] = [$organizationLd];
        }
    }

    return $meta;
}

function seoResolveMeta(array $overrides = []): array
{
    $meta = seoDefaultMeta();

    $stringFields = [
        'title',
        'description',
        'keywords',
        'robots',
        'canonical',
        'og_type',
        'og_image',
        'og_image_alt',
        'twitter_card',
        'site_name',
    ];

    foreach ($stringFields as $field) {
        if (!array_key_exists($field, $overrides)) {
            continue;
        }
        $value = trim((string) $overrides[$field]);
        if ($value === '') {
            continue;
        }
        $meta[$field] = $value;
    }

    if (array_key_exists('json_ld', $overrides)) {
        $jsonLdOverride = $overrides['json_ld'];
        if (is_array($jsonLdOverride)) {
            $meta['json_ld'] = $jsonLdOverride;
        } elseif (is_string($jsonLdOverride) && trim($jsonLdOverride) !== '') {
            $decoded = json_decode($jsonLdOverride, true);
            if (is_array($decoded)) {
                $meta['json_ld'] = [$decoded];
            }
        }
    }

    if (strpos($meta['canonical'], 'http://') !== 0 && strpos($meta['canonical'], 'https://') !== 0) {
        $meta['canonical'] = seoAbsoluteUrl($meta['canonical']);
    }

    if (strpos($meta['og_image'], 'http://') !== 0 && strpos($meta['og_image'], 'https://') !== 0) {
        $meta['og_image'] = seoAbsoluteUrl($meta['og_image']);
    }

    return $meta;
}

function seoNoindexRoutesForRobots(): array
{
    return [
        '/admin/',
        '/dashboard',
        '/create-event',
        '/guests',
        '/scan-checkin',
        '/communications',
        '/reports',
        '/settings',
        '/login',
        '/register',
        '/activate',
        '/logout',
        '/guest-register',
        '/guest-invitation',
        '/guest-checkin',
        '/guest-qr',
    ];
}

