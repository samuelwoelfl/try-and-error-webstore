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
require_once $__bootstrapDir . '/inc/stripe_client.php';
require_once $__bootstrapDir . '/inc/finalize_order.php';

// Fallback sync for local/dev environments without a webhook forwarder — the client
// calls this right after stripe.confirmPayment() resolves so the order doesn't stay
// stuck on "pending" waiting for a webhook that may not reach this host yet. In
// production the webhook is the source of truth; this is a redundant, idempotent check.

require_method('POST');
if (!stripe_enabled()) {
    json_error('Kartenzahlung ist derzeit nicht verfügbar.', 503);
}

$paymentIntentId = require_query('id');
$pdo = get_db();

$stmt = $pdo->prepare('SELECT * FROM orders WHERE stripe_payment_intent_id = :pi');
$stmt->execute(['pi' => $paymentIntentId]);
$order = $stmt->fetch();
if (!$order) {
    json_error('Bestellung nicht gefunden.', 404);
}

try {
    $intent = stripe_retrieve_payment_intent($paymentIntentId);
    if (($intent['status'] ?? null) === 'succeeded') {
        finalize_order_paid($pdo, (int) $order['id']);
        json_response(['ok' => true, 'orderNumber' => $order['order_number'], 'status' => 'paid']);
    }
    json_response(['ok' => true, 'orderNumber' => $order['order_number'], 'status' => $order['status']]);
} catch (Throwable $e) {
    error_log('[checkout/stripe-sync] ' . $e->getMessage());
    json_error('Status konnte nicht geprüft werden.', 500);
}
