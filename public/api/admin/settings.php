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
    // logo_url is intentionally not writable here — the storefront wordmark logo
    // is a branding decision, not day-to-day content, so it stays fixed.
    $current = $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch();
    $b = json_input();

    $pdo->prepare("
        UPDATE settings SET
            hero_title=:hero_title, hero_sub=:hero_sub, hero_eyebrow=:hero_eyebrow,
            hero_image_a_url=:hero_image_a_url, hero_image_b_url=:hero_image_b_url,
            works_eyebrow=:works_eyebrow, works_title=:works_title, works_count_label=:works_count_label,
            about_title=:about_title, about_text=:about_text, about_eyebrow=:about_eyebrow,
            about_image_url=:about_image_url,
            order_notification_email=:order_notification_email,
            order_sender_name=:order_sender_name, order_sender_email=:order_sender_email
        WHERE id = 1
    ")->execute([
        'hero_title' => $b['heroTitle'] ?? $current['hero_title'],
        'hero_sub' => $b['heroSub'] ?? $current['hero_sub'],
        'hero_eyebrow' => $b['heroEyebrow'] ?? $current['hero_eyebrow'],
        'hero_image_a_url' => array_key_exists('heroImageAUrl', $b) ? ($b['heroImageAUrl'] ?: null) : $current['hero_image_a_url'],
        'hero_image_b_url' => array_key_exists('heroImageBUrl', $b) ? ($b['heroImageBUrl'] ?: null) : $current['hero_image_b_url'],
        'works_eyebrow' => $b['worksEyebrow'] ?? $current['works_eyebrow'],
        'works_title' => $b['worksTitle'] ?? $current['works_title'],
        'works_count_label' => $b['worksCountLabel'] ?? $current['works_count_label'],
        'about_title' => $b['aboutTitle'] ?? $current['about_title'],
        'about_text' => $b['aboutText'] ?? $current['about_text'],
        'about_eyebrow' => $b['aboutEyebrow'] ?? $current['about_eyebrow'],
        'about_image_url' => array_key_exists('aboutImageUrl', $b) ? ($b['aboutImageUrl'] ?: null) : $current['about_image_url'],
        'order_notification_email' => array_key_exists('orderNotificationEmail', $b) ? (trim((string) $b['orderNotificationEmail']) ?: null) : $current['order_notification_email'],
        'order_sender_name' => array_key_exists('orderSenderName', $b) ? (trim((string) $b['orderSenderName']) ?: null) : $current['order_sender_name'],
        'order_sender_email' => array_key_exists('orderSenderEmail', $b) ? (trim((string) $b['orderSenderEmail']) ?: null) : $current['order_sender_email'],
    ]);

    $row = $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch();
    json_response(serialize_settings($row));
}

json_error('Method not allowed', 405);
