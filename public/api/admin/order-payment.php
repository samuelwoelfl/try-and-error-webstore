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

require_admin();
require_method('PATCH');

$id = require_query('id');
$pdo = get_db();

$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
$stmt->execute(['id' => $id]);
$order = $stmt->fetch();
if (!$order) {
    json_error('Bestellung nicht gefunden.', 404);
}

// Stripe orders are confirmed exclusively by the webhook/payment confirmation — this
// checkbox is only for payment methods the admin reconciles by hand (Rechnung, and
// legacy PayPal orders), so it never fights with the automatic flow.
if ($order['payment_method'] === 'stripe') {
    json_error('Der Zahlungsstatus von Stripe-Bestellungen wird automatisch verwaltet.', 400);
}
if (!in_array($order['status'], ['pending', 'paid'], true)) {
    json_error('Zahlungsstatus kann bei stornierten oder fehlgeschlagenen Bestellungen nicht geändert werden.', 400);
}

$itemsStmt = $pdo->prepare("SELECT work_id FROM order_items WHERE order_id = :id AND kind = 'unique'");
$nowPaid = $order['status'] !== 'paid';

$pdo->beginTransaction();
try {
    if ($nowPaid) {
        $pdo->prepare("UPDATE orders SET status = 'paid', paid_at = NOW() WHERE id = :id")->execute(['id' => $id]);
    } else {
        $pdo->prepare("UPDATE orders SET status = 'pending', paid_at = NULL WHERE id = :id")->execute(['id' => $id]);
    }

    // Mirrors finalize_order_paid()/the storno endpoint — a unique work is only ever
    // "verkauft" while exactly one order actually holds it as paid.
    $itemsStmt->execute(['id' => $id]);
    $workStatus = $nowPaid ? 'verkauft' : 'verfuegbar';
    $updateWork = $pdo->prepare('UPDATE works SET status = :status WHERE id = :id');
    foreach ($itemsStmt->fetchAll() as $item) {
        $updateWork->execute(['status' => $workStatus, 'id' => $item['work_id']]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

$itemsStmt2 = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :id');
$itemsStmt2->execute(['id' => $id]);
$stmt->execute(['id' => $id]);
json_response(serialize_order($stmt->fetch(), $itemsStmt2->fetchAll()));
