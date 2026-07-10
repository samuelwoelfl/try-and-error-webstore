<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';

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
