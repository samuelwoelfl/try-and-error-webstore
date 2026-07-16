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
require_method('DELETE');

$id = require_query('id');
$pdo = get_db();

// order_items has ON DELETE CASCADE on order_id, so its rows go with it automatically.
$stmt = $pdo->prepare('DELETE FROM orders WHERE id = :id');
$stmt->execute(['id' => $id]);
if ($stmt->rowCount() === 0) {
    json_error('Bestellung nicht gefunden.', 404);
}

json_response(['ok' => true]);
