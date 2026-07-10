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
$pdo = get_db();
$id = require_query('id');
$method = $_SERVER['REQUEST_METHOD'];

$stmt = $pdo->prepare('SELECT * FROM works WHERE id = :id');
$stmt->execute(['id' => $id]);
$existing = $stmt->fetch();
if (!$existing) {
    json_error('Werk nicht gefunden.', 404);
}

if ($method === 'PUT') {
    $body = json_input();
    $rec = work_record_from_request($body);
    if (empty($body['imageUrl'])) {
        $rec['image_url'] = $existing['image_url'];
    }
    $rec['id'] = $id;
    $pdo->prepare("
        UPDATE works SET title=:title, technique=:technique, width_cm=:width_cm, height_cm=:height_cm,
            year=:year, price_cents=:price_cents, kind=:kind, edition_label=:edition_label, status=:status,
            description=:description, image_url=:image_url
        WHERE id=:id
    ")->execute($rec);

    $stmt->execute(['id' => $id]);
    json_response(serialize_work($stmt->fetch()));
}

if ($method === 'DELETE') {
    try {
        $pdo->prepare('DELETE FROM works WHERE id = :id')->execute(['id' => $id]);
        json_response(['ok' => true]);
    } catch (PDOException $e) {
        if ((int) $e->errorInfo[1] === 1451) { // MySQL FK constraint violation
            json_error('Werk ist Teil bestehender Bestellungen und kann nicht gelöscht werden — stattdessen auf „Verkauft" setzen.', 409);
        }
        throw $e;
    }
}

json_error('Method not allowed', 405);
