<?php
declare(strict_types=1);

/**
 * Minimal PayPal Orders v2 REST client over cURL — no SDK dependency, same reasoning
 * as the Stripe client (plain FTP deploy to shared hosting).
 */

function paypal_enabled(): bool
{
    return env('PAYPAL_CLIENT_ID', '') !== '' && env('PAYPAL_CLIENT_SECRET', '') !== '';
}

function paypal_client_id(): string
{
    return env('PAYPAL_CLIENT_ID', '');
}

function paypal_base_url(): string
{
    return strtolower(env('PAYPAL_ENV', 'sandbox')) === 'live'
        ? 'https://api-m.paypal.com'
        : 'https://api-m.sandbox.paypal.com';
}

function paypal_curl_json(string $method, string $url, array $headers, ?array $jsonBody = null): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CUSTOMREQUEST => $method,
    ];
    if ($jsonBody !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($jsonBody);
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("PayPal-Request fehlgeschlagen: $err");
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode((string) $body, true) ?? [];
    if ($status >= 400) {
        $message = $data['message'] ?? "PayPal-Fehler ($status)";
        throw new RuntimeException($message);
    }
    return $data;
}

function paypal_get_access_token(): string
{
    $clientId = env('PAYPAL_CLIENT_ID', '');
    $clientSecret = env('PAYPAL_CLIENT_SECRET', '');
    $auth = base64_encode("$clientId:$clientSecret");

    $ch = curl_init(paypal_base_url() . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Basic $auth",
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $status >= 400) {
        throw new RuntimeException('PayPal OAuth fehlgeschlagen.');
    }
    $data = json_decode((string) $body, true);
    return $data['access_token'] ?? throw new RuntimeException('PayPal OAuth: kein Access-Token erhalten.');
}

function paypal_create_order(int $amountCents, string $currency, string $orderNumber): array
{
    $token = paypal_get_access_token();
    return paypal_curl_json('POST', paypal_base_url() . '/v2/checkout/orders', [
        "Authorization: Bearer $token",
        'Content-Type: application/json',
    ], [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => $orderNumber,
            'amount' => [
                'currency_code' => $currency,
                'value' => number_format($amountCents / 100, 2, '.', ''),
            ],
        ]],
    ]);
}

function paypal_capture_order(string $paypalOrderId): array
{
    $token = paypal_get_access_token();
    return paypal_curl_json('POST', paypal_base_url() . "/v2/checkout/orders/$paypalOrderId/capture", [
        "Authorization: Bearer $token",
        'Content-Type: application/json',
    ]);
}
