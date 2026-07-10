<?php
declare(strict_types=1);

/**
 * Sends via PHP's built-in mail() (works out of the box on most shared hosting
 * for the account's own domain — no SMTP credentials needed). Best-effort: failures
 * are logged, never thrown, so a flaky mail relay can't break checkout/contact flows.
 *
 * @param string|null $html Optional HTML body. When given, sends a multipart/alternative
 *                           message (plain text + HTML) built by hand — no PHPMailer/Composer
 *                           dependency, same reasoning as the rest of this codebase.
 */
function send_mail(string $to, string $subject, string $text, ?string $html = null): bool
{
    if (!env_bool('MAIL_ENABLED', true)) {
        error_log("[mailer] MAIL_ENABLED=false — E-Mail an $to nur geloggt.\nBetreff: $subject\n$text");
        return false;
    }

    $from = env('MAIL_FROM', 'no-reply@example.com');
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    if ($html === null) {
        $headers = "From: $from\r\n" .
            "Content-Type: text/plain; charset=UTF-8\r\n" .
            'X-Mailer: PHP/' . phpversion();
        $ok = @mail($to, $encodedSubject, $text, $headers);
    } else {
        $boundary = 'b_' . bin2hex(random_bytes(12));
        $headers = "From: $from\r\n" .
            "MIME-Version: 1.0\r\n" .
            "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n" .
            'X-Mailer: PHP/' . phpversion();

        $body = "--$boundary\r\n" .
            "Content-Type: text/plain; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: 8bit\r\n\r\n" .
            $text . "\r\n\r\n" .
            "--$boundary\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: 8bit\r\n\r\n" .
            $html . "\r\n\r\n" .
            "--$boundary--";

        $ok = @mail($to, $encodedSubject, $body, $headers);
    }

    if (!$ok) {
        error_log("[mailer] Versand an $to fehlgeschlagen.\nBetreff: $subject\n$text");
    }
    return $ok;
}
