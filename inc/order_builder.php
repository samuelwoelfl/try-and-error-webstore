<?php
declare(strict_types=1);

/**
 * Recomputes line items & total straight from the DB — the client-submitted cart is
 * only ever treated as a list of {workId, qty} references, never as a source of price/status truth.
 *
 * @return array{lineItems: array, totalCents: int}|array{error: string}
 */
function build_line_items(PDO $pdo, mixed $cartItems): array
{
    if (!is_array($cartItems) || count($cartItems) === 0) {
        return ['error' => 'Warenkorb ist leer.'];
    }

    $stmt = $pdo->prepare('SELECT * FROM works WHERE id = :id');
    $lineItems = [];
    $totalCents = 0;

    foreach ($cartItems as $entry) {
        $workId = (int) ($entry['workId'] ?? 0);
        $qty = (int) ($entry['qty'] ?? 0);
        if ($workId <= 0 || $qty < 1) {
            return ['error' => 'Ungültiger Warenkorb-Eintrag.'];
        }
        $stmt->execute(['id' => $workId]);
        $w = $stmt->fetch();
        if (!$w) {
            return ['error' => "Werk $workId existiert nicht mehr."];
        }
        if ($w['status'] === 'verkauft') {
            return ['error' => "„{$w['title']}\" ist bereits verkauft."];
        }
        $maxQty = $w['kind'] === 'unique' ? 1 : 99;
        if ($qty > $maxQty) {
            return ['error' => "„{$w['title']}\" ist nur bis zu {$maxQty}× verfügbar."];
        }

        $lineItems[] = [
            'workId' => (int) $w['id'],
            'title' => $w['title'],
            'metaLine' => meta_line_for($w),
            'unitPriceCents' => (int) $w['price_cents'],
            'qty' => $qty,
            'kind' => $w['kind'],
        ];
        $totalCents += (int) $w['price_cents'] * $qty;
    }

    if ($totalCents <= 0) {
        return ['error' => 'Bestellwert muss größer als 0 sein.'];
    }

    return ['lineItems' => $lineItems, 'totalCents' => $totalCents];
}

/**
 * @return array{customer: array}|array{error: string}
 */
function validate_customer(mixed $customer): array
{
    $required = ['firstName', 'lastName', 'email', 'street', 'zip', 'city'];
    $c = is_array($customer) ? $customer : [];
    foreach ($required as $key) {
        if (!isset($c[$key]) || trim((string) $c[$key]) === '') {
            return ['error' => 'Bitte alle Kontakt- und Lieferdaten ausfüllen.'];
        }
    }
    if (!filter_var($c['email'], FILTER_VALIDATE_EMAIL)) {
        return ['error' => 'Bitte eine gültige E-Mail-Adresse angeben.'];
    }
    return ['customer' => $c];
}

function create_pending_order(PDO $pdo, array $lineItems, int $totalCents, array $customer, string $paymentMethod): array
{
    $orderNumber = gen_order_number();

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            INSERT INTO orders (order_number, status, payment_method, first_name, last_name, email, street, zip, city, total_cents, currency)
            VALUES (:order_number, 'pending', :payment_method, :first_name, :last_name, :email, :street, :zip, :city, :total_cents, 'eur')
        ")->execute([
            'order_number' => $orderNumber,
            'payment_method' => $paymentMethod,
            'first_name' => $customer['firstName'],
            'last_name' => $customer['lastName'],
            'email' => $customer['email'],
            'street' => $customer['street'],
            'zip' => $customer['zip'],
            'city' => $customer['city'],
            'total_cents' => $totalCents,
        ]);
        $orderId = (int) $pdo->lastInsertId();

        $insertItem = $pdo->prepare("
            INSERT INTO order_items (order_id, work_id, title, meta_line, unit_price_cents, qty, kind)
            VALUES (:order_id, :work_id, :title, :meta_line, :unit_price_cents, :qty, :kind)
        ");
        foreach ($lineItems as $li) {
            $insertItem->execute([
                'order_id' => $orderId,
                'work_id' => $li['workId'],
                'title' => $li['title'],
                'meta_line' => $li['metaLine'],
                'unit_price_cents' => $li['unitPriceCents'],
                'qty' => $li['qty'],
                'kind' => $li['kind'],
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return ['orderId' => $orderId, 'orderNumber' => $orderNumber];
}
