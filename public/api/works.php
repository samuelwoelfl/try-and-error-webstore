<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';

$rows = get_db()->query('SELECT * FROM works ORDER BY sort_order ASC, id ASC')->fetchAll();
json_response(array_map('serialize_work', $rows));
