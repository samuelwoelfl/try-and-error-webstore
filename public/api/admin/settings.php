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

require_admin();
$pdo = get_db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $row = $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch();
    json_response(serialize_settings($row));
}

if ($method === 'PUT') {
    $current = $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch();
    $b = json_input();

    $pdo->prepare("
        UPDATE settings SET hero_title=:hero_title, hero_sub=:hero_sub, about_title=:about_title,
            about_text=:about_text, logo_url=:logo_url
        WHERE id = 1
    ")->execute([
        'hero_title' => $b['heroTitle'] ?? $current['hero_title'],
        'hero_sub' => $b['heroSub'] ?? $current['hero_sub'],
        'about_title' => $b['aboutTitle'] ?? $current['about_title'],
        'about_text' => $b['aboutText'] ?? $current['about_text'],
        'logo_url' => array_key_exists('logoUrl', $b) ? ($b['logoUrl'] ?: null) : $current['logo_url'],
    ]);

    $row = $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch();
    json_response(serialize_settings($row));
}

json_error('Method not allowed', 405);
