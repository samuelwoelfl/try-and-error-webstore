<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../inc/bootstrap.php';
require_once __DIR__ . '/../../../inc/order_builder.php';
require_once __DIR__ . '/../../../inc/paypal_client.php';

require_method('POST');
if (!paypal_enabled()) {
    json_error('PayPal ist derzeit nicht verfügbar.', 503);
}

$body = json_input();
$li = build_line_items(get_db(), $body['cart'] ?? null);
if (isset($li['error'])) {
    json_error($li['error']);
}
$cust = validate_customer($body['customer'] ?? null);
if (isset($cust['error'])) {
    json_error($cust['error']);
}

$pdo = get_db();
try {
    $created = create_pending_order($pdo, $li['lineItems'], $li['totalCents'], $cust['customer'], 'paypal');
    $ppOrder = paypal_create_order($li['totalCents'], 'EUR', $created['orderNumber']);
    $pdo->prepare('UPDATE orders SET paypal_order_id = :pid WHERE id = :id')
        ->execute(['pid' => $ppOrder['id'], 'id' => $created['orderId']]);

    json_response(['paypalOrderId' => $ppOrder['id'], 'orderNumber' => $created['orderNumber']]);
} catch (Throwable $e) {
    error_log('[checkout/paypal-create] ' . $e->getMessage());
    json_error('PayPal-Bestellung konnte nicht erstellt werden.', 500);
}
