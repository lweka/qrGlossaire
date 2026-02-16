<?php
require_once __DIR__ . '/app/config/constants.php';
require_once __DIR__ . '/app/helpers/seo.php';

if (!headers_sent()) {
    header('Content-Type: text/plain; charset=UTF-8');
}

$allowPath = seoRoutePath('/');
$disallowRoutes = seoNoindexRoutesForRobots();
$seen = [];

echo "User-agent: *\n";
echo 'Allow: ' . $allowPath . "\n";

foreach ($disallowRoutes as $route) {
    $rawRoute = (string) $route;
    $path = seoRoutePath($rawRoute);
    if ($rawRoute !== '/' && substr(trim($rawRoute), -1) === '/' && substr($path, -1) !== '/') {
        $path .= '/';
    }
    if (isset($seen[$path])) {
        continue;
    }
    $seen[$path] = true;
    echo 'Disallow: ' . $path . "\n";
}

echo 'Sitemap: ' . seoAppAbsoluteUrl('/sitemap.xml') . "\n";
