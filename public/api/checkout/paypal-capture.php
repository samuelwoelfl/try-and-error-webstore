<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../inc/bootstrap.php';
require_once __DIR__ . '/../../../inc/paypal_client.php';
require_once __DIR__ . '/../../../inc/finalize_order.php';

require_method('POST');
if (!paypal_enabled()) {
    json_error('PayPal ist derzeit nicht verfügbar.', 503);
}

$paypalOrderId = require_query('id');
$pdo = get_db();

$stmt = $pdo->prepare('SELECT * FROM orders WHERE paypal_order_id = :pid');
$stmt->execute(['pid' => $paypalOrderId]);
$order = $stmt->fetch();
if (!$order) {
    json_error('Bestellung nicht gefunden.', 404);
}

try {
    $capture = paypal_capture_order($paypalOrderId);
    if (($capture['status'] ?? null) === 'COMPLETED') {
        finalize_order_paid($pdo, (int) $order['id']);
        json_response(['ok' => true, 'orderNumber' => $order['order_number'], 'status' => 'paid']);
    }
    json_error('Zahlung nicht abgeschlossen (Status: ' . ($capture['status'] ?? 'unbekannt') . ').', 402);
} catch (Throwable $e) {
    error_log('[checkout/paypal-capture] ' . $e->getMessage());
    json_error('PayPal-Zahlung konnte nicht bestätigt werden.', 500);
}
