<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../inc/bootstrap.php';

require_admin();
require_method('PATCH');

$id = require_query('id');
$pdo = get_db();

$stmt = $pdo->prepare('SELECT * FROM works WHERE id = :id');
$stmt->execute(['id' => $id]);
$existing = $stmt->fetch();
if (!$existing) {
    json_error('Werk nicht gefunden.', 404);
}

$next = $existing['status'] === 'verkauft' ? 'verfuegbar' : 'verkauft';
$pdo->prepare('UPDATE works SET status = :status WHERE id = :id')->execute(['status' => $next, 'id' => $id]);

$stmt->execute(['id' => $id]);
json_response(serialize_work($stmt->fetch()));
