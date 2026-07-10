<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';
load_env(__DIR__ . '/../.env');

date_default_timezone_set('Europe/Berlin');
mb_internal_encoding('UTF-8');

// Every endpoint here returns JSON — a PHP warning printed inline would corrupt the
// response body, so errors always go to the log, never to output.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? null) === '443')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

// Some shared-hosting php.ini defaults ship without a usable session.save_path —
// fall back to the system temp dir explicitly so sessions always work.
if (ini_get('session.save_path') === '') {
    session_save_path(sys_get_temp_dir());
}

$sessionName = 'atelier_sid';
// Discard a malformed/stale session cookie up front instead of letting session_start()
// warn on it — can happen after switching session ID formats or a corrupted client cookie.
if (isset($_COOKIE[$sessionName]) && !preg_match('/^[a-zA-Z0-9,-]{22,250}$/', $_COOKIE[$sessionName])) {
    unset($_COOKIE[$sessionName]);
}

session_name($sessionName);
session_set_cookie_params([
    'lifetime' => 60 * 60 * 12,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => $isHttps,
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/json_helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/money.php';
require_once __DIR__ . '/serialize.php';
require_once __DIR__ . '/auth.php';
