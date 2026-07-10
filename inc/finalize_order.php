<?php
declare(strict_types=1);

// Same-directory requires (not path-resolution-sensitive like inc/bootstrap.php itself) —
// finalize_order_paid() depends on both directly, regardless of what its callers already loaded.
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/email_templates.php';

/**
 * Called only after a payment provider has confirmed the charge (Stripe webhook,
 * PayPal capture). Marks the order paid and takes unique (one-of-a-kind) works off
 * the market so they can't be sold twice. Edition works have no fixed stock ledger here.
 */
function finalize_order_paid(PDO $pdo, int $orderId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
    $stmt->execute(['id' => $orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }
    if ($order['status'] === 'paid') {
        return $order; // idempotent — webhooks/captures can fire more than once
    }

    $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :id');

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE orders SET status = 'paid', paid_at = NOW() WHERE id = :id")->execute(['id' => $orderId]);
        $itemsStmt->execute(['id' => $orderId]);
        $items = $itemsStmt->fetchAll();
        $markSold = $pdo->prepare("UPDATE works SET status = 'verkauft' WHERE id = :id");
        foreach ($items as $item) {
            if ($item['kind'] === 'unique') {
                $markSold->execute(['id' => $item['work_id']]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $stmt->execute(['id' => $orderId]);
    $updated = $stmt->fetch();

    $emailItems = array_map(static fn (array $i) => [
        'title' => $i['title'],
        'metaLine' => $i['meta_line'],
        'unitPriceCents' => (int) $i['unit_price_cents'],
        'qty' => (int) $i['qty'],
    ], $items);
    $email = build_order_confirmation_email([
        'orderNumber' => $updated['order_number'],
        'firstName' => $updated['first_name'],
        'lastName' => $updated['last_name'],
        'email' => $updated['email'],
        'street' => $updated['street'],
        'zip' => $updated['zip'],
        'city' => $updated['city'],
        'totalCents' => (int) $updated['total_cents'],
        'paymentMethod' => $updated['payment_method'],
        'status' => $updated['status'],
    ], $emailItems);
    send_mail($updated['email'], $email['subject'], $email['text'], $email['html']);

    return $updated;
}
