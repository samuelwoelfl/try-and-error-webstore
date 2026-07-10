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

$orderNumber = require_query('number');
$pdo = get_db();

$stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = :n');
$stmt->execute(['n' => $orderNumber]);
$order = $stmt->fetch();

if (!$order) {
    json_error('Bestellung nicht gefunden.', 404);
}

$itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :id');
$itemsStmt->execute(['id' => $order['id']]);

json_response(serialize_order($order, $itemsStmt->fetchAll()));
