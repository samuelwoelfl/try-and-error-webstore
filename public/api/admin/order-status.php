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

// Single-purpose "Stornieren" action (not a generic status setter, so the admin UI
// can never accidentally move an order into 'paid' without a real payment).
if ($order['status'] !== 'cancelled') {
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = :id")->execute(['id' => $id]);

        // A storno on a paid order frees up the unique (one-of-a-kind) works it held —
        // editions have no per-order stock ledger, so only kind='unique' items matter here.
        if ($order['status'] === 'paid') {
            $itemsStmt = $pdo->prepare("SELECT work_id FROM order_items WHERE order_id = :id AND kind = 'unique'");
            $itemsStmt->execute(['id' => $id]);
            $revert = $pdo->prepare("UPDATE works SET status = 'verfuegbar' WHERE id = :id");
            foreach ($itemsStmt->fetchAll() as $item) {
                $revert->execute(['id' => $item['work_id']]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

$itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :id');
$itemsStmt->execute(['id' => $id]);
$stmt->execute(['id' => $id]);
json_response(serialize_order($stmt->fetch(), $itemsStmt->fetchAll()));
