<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';

$row = get_db()->query('SELECT * FROM settings WHERE id = 1')->fetch();
json_response(serialize_settings($row));
