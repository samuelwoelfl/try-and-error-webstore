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
require_method('PATCH');

$id = require_query('id');
$pdo = get_db();

$stmt = $pdo->prepare('SELECT * FROM works WHERE id = :id');
$stmt->execute(['id' => $id]);
$existing = $stmt->fetch();
if (!$existing) {
    json_error('Werk nicht gefunden.', 404);
}

$next = $existing['is_hidden'] ? 0 : 1;
$pdo->prepare('UPDATE works SET is_hidden = :is_hidden WHERE id = :id')->execute(['is_hidden' => $next, 'id' => $id]);

$stmt->execute(['id' => $id]);
json_response(serialize_work($stmt->fetch()));
