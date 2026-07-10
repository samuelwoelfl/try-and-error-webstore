<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/../../inc/mailer.php';

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
