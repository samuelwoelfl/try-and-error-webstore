<?php
declare(strict_types=1);
$__bootstrapDir = __DIR__;
while (!is_file($__bootstrapDir . '/inc/bootstrap.php')) {
    $__parent = dirname($__bootstrapDir);
    if ($__parent === $__bootstrapDir) {
        http_response_code(500);
        exit('inc/bootstrap.php not found — check that the inc/ folder was uploaded.');
    }
    $__bootstrapDir = $__parent;
}
require_once $__bootstrapDir . '/inc/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

$base = site_base_url();

$staticPaths = [
    '' => '1.0',
    'kontakt.html' => '0.6',
    'impressum.html' => '0.3',
    'datenschutz.html' => '0.3',
];

$works = get_db()->query('SELECT id FROM works WHERE is_hidden = 0 ORDER BY sort_order ASC, id ASC')->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($staticPaths as $path => $priority) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($base . '/' . $path, ENT_XML1) . "</loc>\n";
    echo "    <priority>{$priority}</priority>\n";
    echo "  </url>\n";
}

foreach ($works as $work) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($base . '/werk.html?id=' . $work['id'], ENT_XML1) . "</loc>\n";
    echo "    <priority>0.8</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>' . "\n";
