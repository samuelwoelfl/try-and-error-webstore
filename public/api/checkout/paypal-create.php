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
require_once $__bootstrapDir . '/inc/order_builder.php';
require_once $__bootstrapDir . '/inc/paypal_client.php';

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
