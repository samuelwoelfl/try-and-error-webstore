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
require_once $__bootstrapDir . '/inc/upload.php';

require_admin();
require_method('POST');

// This file always lives at <webroot>/api/admin/upload.php, so two levels up is the
// web root regardless of where inc/ was deployed (sibling of or nested inside it).
json_response(['url' => handle_image_upload('image', dirname(__DIR__, 2))]);
