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

$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
$stmt->execute(['id' => $id]);
$existing = $stmt->fetch();
if (!$existing) {
    json_error('Bestellung nicht gefunden.', 404);
}

// Independent of the payment status — this just tracks whether the shop owner has
// shipped/handled the order, so it can be toggled either way regardless of paid/pending.
$next = $existing['fulfilled_at'] === null ? date('Y-m-d H:i:s') : null;
$pdo->prepare('UPDATE orders SET fulfilled_at = :fulfilled_at WHERE id = :id')->execute(['fulfilled_at' => $next, 'id' => $id]);

$itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :id');
$itemsStmt->execute(['id' => $id]);
$stmt->execute(['id' => $id]);
json_response(serialize_order($stmt->fetch(), $itemsStmt->fetchAll()));
