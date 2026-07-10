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

require_method('POST');
$body = json_input();
$email = trim((string) ($body['email'] ?? ''));
$password = (string) ($body['password'] ?? '');

if ($email === '' || $password === '') {
    json_error('E-Mail und Passwort erforderlich.', 400);
}

$stmt = get_db()->prepare('SELECT * FROM admin_users WHERE email = :email');
$stmt->execute(['email' => strtolower($email)]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($password, $admin['password_hash'])) {
    json_error('E-Mail oder Passwort ist falsch.', 401);
}

session_regenerate_id(true);
$_SESSION['admin_id'] = (int) $admin['id'];
$_SESSION['admin_email'] = $admin['email'];

json_response(['ok' => true, 'email' => $admin['email']]);
