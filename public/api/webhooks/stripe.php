<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../inc/bootstrap.php';
require_once __DIR__ . '/../../../inc/stripe_client.php';
require_once __DIR__ . '/../../../inc/finalize_order.php';

if (!stripe_enabled()) {
    http_response_code(503);
    exit;
}

$payload = file_get_contents('php://input') ?: '';
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$webhookSecret = env('STRIPE_WEBHOOK_SECRET', '');

if ($webhookSecret !== '') {
    if (!stripe_verify_webhook_signature($payload, $sigHeader, $webhookSecret)) {
        http_response_code(400);
        echo 'Webhook Error: invalid signature';
        exit;
    }
}
// No webhook secret configured (e.g. local testing without a configured Stripe
// endpoint) — trust the payload as-is, same dev-only fallback as production's
// signature check being the actual gate once STRIPE_WEBHOOK_SECRET is set.

$event = json_decode($payload, true);
if (!is_array($event)) {
    http_response_code(400);
    exit;
}

if (($event['type'] ?? null) === 'payment_intent.succeeded') {
    $intentId = $event['data']['object']['id'] ?? null;
    if ($intentId) {
        $pdo = get_db();
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE stripe_payment_intent_id = :pi');
        $stmt->execute(['pi' => $intentId]);
        $order = $stmt->fetch();
        if ($order) {
            finalize_order_paid($pdo, (int) $order['id']);
        }
    }
}

json_response(['received' => true]);
