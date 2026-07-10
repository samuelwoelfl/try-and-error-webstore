<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/../../inc/stripe_client.php';
require_once __DIR__ . '/../../inc/paypal_client.php';

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
