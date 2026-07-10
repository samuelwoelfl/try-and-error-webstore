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
require_once $__bootstrapDir . '/inc/stripe_client.php';
require_once $__bootstrapDir . '/inc/paypal_client.php';

json_response([
    'stripe' => ['enabled' => stripe_enabled(), 'publishableKey' => stripe_publishable_key()],
    'paypal' => ['enabled' => paypal_enabled(), 'clientId' => paypal_client_id()],
    'invoice' => [
        'enabled' => true,
        'bankHolder' => env('BANK_HOLDER', ''),
        'bankIban' => env('BANK_IBAN', ''),
        'bankBic' => env('BANK_BIC', ''),
    ],
]);
