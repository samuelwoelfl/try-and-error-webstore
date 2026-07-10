<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../inc/bootstrap.php';

if (!empty($_SESSION['admin_id'])) {
    json_response(['authed' => true, 'email' => $_SESSION['admin_email']]);
}
json_response(['authed' => false]);
