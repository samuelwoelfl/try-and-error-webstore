<?php
declare(strict_types=1);

/**
 * Sends via PHP's built-in mail() (works out of the box on most shared hosting
 * for the account's own domain — no SMTP credentials needed). Best-effort: failures
 * are logged, never thrown, so a flaky mail relay can't break checkout/contact flows.
 */
function send_mail(string $to, string $subject, string $text): bool
{
    if (!env_bool('MAIL_ENABLED', true)) {
        error_log("[mailer] MAIL_ENABLED=false — E-Mail an $to nur geloggt.\nBetreff: $subject\n$text");
        return false;
    }

    $from = env('MAIL_FROM', 'no-reply@example.com');
    $headers = "From: $from\r\n" .
        "Content-Type: text/plain; charset=UTF-8\r\n" .
        'X-Mailer: PHP/' . phpversion();

    $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $text, $headers);
    if (!$ok) {
        error_log("[mailer] Versand an $to fehlgeschlagen.\nBetreff: $subject\n$text");
    }
    return $ok;
}
