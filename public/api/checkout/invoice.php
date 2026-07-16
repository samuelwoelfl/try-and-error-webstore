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

$pdo = get_db();
try {
    $created = create_pending_order($pdo, $li['lineItems'], $li['totalCents'], $cust['customer'], 'invoice');
} catch (Throwable $e) {
    error_log('[checkout/invoice] ' . $e->getMessage());
    json_error('Bestellung konnte nicht angelegt werden.', 500);
}

// The order already exists in the DB at this point, so a mail hiccup below must
// never turn into an error response — the customer would think checkout failed
// and could re-order, while the original order sits there unnoticed.
try {
    $orderForEmail = [
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
    ];

    $settings = $pdo->query('SELECT order_sender_name, order_sender_email, order_notification_email FROM settings WHERE id = 1')->fetch();
    $senderFrom = build_mail_from($settings['order_sender_name'] ?? null, $settings['order_sender_email'] ?? null);

    $email = build_order_confirmation_email(
        $orderForEmail,
        $li['lineItems'],
        [
            'bankHolder' => env('BANK_HOLDER', ''),
            'bankIban' => env('BANK_IBAN', ''),
            'bankBic' => env('BANK_BIC', ''),
        ]
    );
    send_mail($cust['customer']['email'], $email['subject'], $email['text'], $email['html'], $senderFrom);

    $notifyTo = $settings['order_notification_email'] ?: env('ADMIN_EMAIL');
    if ($notifyTo) {
        $notification = build_order_notification_email($orderForEmail, $li['lineItems']);
        send_mail($notifyTo, $notification['subject'], $notification['text'], $notification['html'], $senderFrom);
    }
} catch (Throwable $e) {
    error_log("[checkout/invoice] Mailversand für Bestellung {$created['orderNumber']} fehlgeschlagen: " . $e->getMessage());
}

json_response(['orderNumber' => $created['orderNumber'], 'orderId' => $created['orderId']]);
