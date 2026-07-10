<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';

$id = require_query('id');
$stmt = get_db()->prepare('SELECT * FROM works WHERE id = :id');
$stmt->execute(['id' => $id]);
$row = $stmt->fetch();

if (!$row) {
    json_error('Werk nicht gefunden.', 404);
}

json_response(serialize_work($row));
