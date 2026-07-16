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
    $orderForEmail = [
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
    ];

    // The order is already committed as paid at this point — a mail hiccup (bad
    // settings row, mail() rejecting the sender domain, etc.) must never bubble up
    // as an exception here. If it did, Stripe/PayPal would see a failed webhook and
    // retry; finalize_order_paid() would then hit the idempotent early-return above
    // on that retry and never even attempt to send mail again, silently losing both
    // emails for good.
    try {
        $settings = $pdo->query('SELECT order_sender_name, order_sender_email, order_notification_email FROM settings WHERE id = 1')->fetch();
        $senderFrom = build_mail_from($settings['order_sender_name'] ?? null, $settings['order_sender_email'] ?? null);

        $email = build_order_confirmation_email($orderForEmail, $emailItems);
        send_mail($updated['email'], $email['subject'], $email['text'], $email['html'], $senderFrom);

        $notifyTo = $settings['order_notification_email'] ?: env('ADMIN_EMAIL');
        if ($notifyTo) {
            $notification = build_order_notification_email($orderForEmail, $emailItems);
            send_mail($notifyTo, $notification['subject'], $notification['text'], $notification['html'], $senderFrom);
        }
    } catch (Throwable $e) {
        error_log("[finalize_order] Mailversand für Bestellung {$updated['order_number']} fehlgeschlagen: " . $e->getMessage());
    }

    return $updated;
}
