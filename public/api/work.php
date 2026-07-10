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

$id = require_query('id');
$stmt = get_db()->prepare('SELECT * FROM works WHERE id = :id');
$stmt->execute(['id' => $id]);
$row = $stmt->fetch();

if (!$row) {
    json_error('Werk nicht gefunden.', 404);
}

json_response(serialize_work($row));
