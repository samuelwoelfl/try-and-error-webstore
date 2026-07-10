<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../inc/bootstrap.php';

require_admin();
$pdo = get_db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $pdo->query('SELECT * FROM works ORDER BY sort_order ASC, id ASC')->fetchAll();
    json_response(array_map('serialize_work', $rows));
}

if ($method === 'POST') {
    $rec = work_record_from_request(json_input());
    $maxSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), -1) FROM works')->fetchColumn();
    $rec['sort_order'] = $maxSort + 1;

    $pdo->prepare("
        INSERT INTO works (title, technique, width_cm, height_cm, year, price_cents, kind, edition_label, status, description, image_url, sort_order)
        VALUES (:title, :technique, :width_cm, :height_cm, :year, :price_cents, :kind, :edition_label, :status, :description, :image_url, :sort_order)
    ")->execute($rec);
    $id = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare('SELECT * FROM works WHERE id = :id');
    $stmt->execute(['id' => $id]);
    json_response(serialize_work($stmt->fetch()), 201);
}

json_error('Method not allowed', 405);
