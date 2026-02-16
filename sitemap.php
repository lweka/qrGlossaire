<?php
require_once __DIR__ . '/app/config/constants.php';
require_once __DIR__ . '/app/helpers/seo.php';

if (!headers_sent()) {
    header('Content-Type: application/xml; charset=UTF-8');
}

$routes = seoIndexableRoutes();

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

foreach ($routes as $route) {
    $path = (string) ($route['path'] ?? '/');
    $file = trim((string) ($route['file'] ?? ''));
    $changeFreq = trim((string) ($route['changefreq'] ?? 'weekly'));
    $priority = trim((string) ($route['priority'] ?? '0.5'));

    $loc = seoAppAbsoluteUrl($path);
    $filePath = $file !== '' ? __DIR__ . '/' . $file : '';
    $lastModDate = gmdate('Y-m-d');

    if ($filePath !== '' && is_file($filePath)) {
        $timestamp = filemtime($filePath);
        if (is_int($timestamp) && $timestamp > 0) {
            $lastModDate = gmdate('Y-m-d', $timestamp);
        }
    }

    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>\n";
    echo '    <lastmod>' . htmlspecialchars($lastModDate, ENT_XML1, 'UTF-8') . "</lastmod>\n";
    echo '    <changefreq>' . htmlspecialchars($changeFreq, ENT_XML1, 'UTF-8') . "</changefreq>\n";
    echo '    <priority>' . htmlspecialchars($priority, ENT_XML1, 'UTF-8') . "</priority>\n";
    echo "  </url>\n";
}

echo "</urlset>\n";
