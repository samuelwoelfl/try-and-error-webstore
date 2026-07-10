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
require_once $__bootstrapDir . '/inc/mailer.php';

require_method('POST');
$body = json_input();

$name = trim((string) ($body['name'] ?? ''));
$email = trim((string) ($body['email'] ?? ''));
$subject = trim((string) ($body['subject'] ?? ''));
$message = trim((string) ($body['message'] ?? ''));

if ($name === '' || $email === '' || $message === '') {
    json_error('Name, E-Mail und Nachricht sind erforderlich.', 400);
}

$to = env('CONTACT_EMAIL', env('MAIL_FROM'));
if ($to) {
    send_mail($to, 'Kontaktformular: ' . ($subject !== '' ? $subject : 'Neue Nachricht'), "Von: $name <$email>\n\n$message");
} else {
    error_log("[contact] Neue Nachricht von $name <$email>: $subject\n$message");
}

json_response(['ok' => true]);
