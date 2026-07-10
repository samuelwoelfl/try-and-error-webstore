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
require_once $__bootstrapDir . '/inc/mailer.php';
require_once $__bootstrapDir . '/inc/email_templates.php';

// No live payment processor — the order is created "pending" and the customer
// receives the bank details by email; the admin reconciles payment manually and
// marks the work "Verkauft" via the status pill once the transfer arrives.

require_method('POST');
$body = json_input();
$li = build_line_items(get_db(), $body['cart'] ?? null);
if (isset($li['error'])) {
    json_error($li['error']);
}
$cust = validate_customer($body['customer'] ?? null);
if (isset($cust['error'])) {
    json_error($cust['error']);
}

try {
    $created = create_pending_order(get_db(), $li['lineItems'], $li['totalCents'], $cust['customer'], 'invoice');

    $email = build_order_confirmation_email(
        [
            'orderNumber' => $created['orderNumber'],
            'firstName' => $cust['customer']['firstName'],
            'lastName' => $cust['customer']['lastName'],
            'email' => $cust['customer']['email'],
            'street' => $cust['customer']['street'],
            'zip' => $cust['customer']['zip'],
            'city' => $cust['customer']['city'],
            'totalCents' => $li['totalCents'],
            'paymentMethod' => 'invoice',
            'status' => 'pending',
        ],
        $li['lineItems'],
        [
            'bankHolder' => env('BANK_HOLDER', ''),
            'bankIban' => env('BANK_IBAN', ''),
            'bankBic' => env('BANK_BIC', ''),
        ]
    );
    send_mail($cust['customer']['email'], $email['subject'], $email['text'], $email['html']);

    json_response(['orderNumber' => $created['orderNumber'], 'orderId' => $created['orderId']]);
} catch (Throwable $e) {
    error_log('[checkout/invoice] ' . $e->getMessage());
    json_error('Bestellung konnte nicht angelegt werden.', 500);
}
