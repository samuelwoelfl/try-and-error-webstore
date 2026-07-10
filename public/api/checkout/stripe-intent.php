<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../inc/bootstrap.php';
require_once __DIR__ . '/../../../inc/order_builder.php';
require_once __DIR__ . '/../../../inc/stripe_client.php';

require_method('POST');

if (!stripe_enabled()) {
    json_error('Kartenzahlung ist derzeit nicht verfügbar.', 503);
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
    $created = create_pending_order($pdo, $li['lineItems'], $li['totalCents'], $cust['customer'], 'stripe');
    $intent = stripe_create_payment_intent(
        $li['totalCents'],
        'eur',
        ['orderId' => (string) $created['orderId'], 'orderNumber' => $created['orderNumber']],
        $cust['customer']['email']
    );
    $pdo->prepare('UPDATE orders SET stripe_payment_intent_id = :pi WHERE id = :id')
        ->execute(['pi' => $intent['id'], 'id' => $created['orderId']]);

    json_response(['clientSecret' => $intent['client_secret'], 'orderNumber' => $created['orderNumber']]);
} catch (Throwable $e) {
    error_log('[checkout/stripe-intent] ' . $e->getMessage());
    json_error('Zahlung konnte nicht vorbereitet werden.', 500);
}
