<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../inc/bootstrap.php';
require_once __DIR__ . '/../../../inc/upload.php';

require_admin();
require_method('POST');

json_response(['url' => handle_image_upload('image')]);
