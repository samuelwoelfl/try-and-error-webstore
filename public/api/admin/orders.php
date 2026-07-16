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
require_method('GET');
$pdo = get_db();

$orders = $pdo->query('SELECT * FROM orders ORDER BY created_at DESC')->fetchAll();
$itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :id');

$result = [];
foreach ($orders as $order) {
    $itemsStmt->execute(['id' => $order['id']]);
    $result[] = serialize_order($order, $itemsStmt->fetchAll());
}

json_response($result);
