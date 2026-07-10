<?php
declare(strict_types=1);

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

    $lines = implode("\n", array_map(
        static fn (array $i) => "{$i['qty']}× {$i['title']} — " . fmt_euro((int) $i['unit_price_cents'] * (int) $i['qty']),
        $items
    ));
    send_mail(
        $updated['email'],
        "Bestellbestätigung {$updated['order_number']}",
        "Hallo {$updated['first_name']},\n\nvielen Dank für deine Bestellung!\n\n$lines\n\nGesamt: " . fmt_euro((int) $updated['total_cents']) . "\nBestellnummer: {$updated['order_number']}\n\nWir melden uns mit dem Versand."
    );

    return $updated;
}
