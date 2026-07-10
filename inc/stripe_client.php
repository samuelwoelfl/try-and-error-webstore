<?php
declare(strict_types=1);

/**
 * Minimal Stripe REST client over cURL — no Composer/SDK dependency so this deploys
 * by plain FTP upload to shared hosting. Covers exactly what checkout needs:
 * create/retrieve a PaymentIntent and verify a webhook signature.
 */

function stripe_enabled(): bool
{
    return str_starts_with((string) env('STRIPE_SECRET_KEY', ''), 'sk_');
}

function stripe_publishable_key(): string
{
    return env('STRIPE_PUBLISHABLE_KEY', '');
}

/**
 * @throws RuntimeException on transport or API error
 */
function stripe_request(string $method, string $path, array $params = []): array
{
    $secretKey = env('STRIPE_SECRET_KEY', '');
    $url = 'https://api.stripe.com/v1' . $path;

    $ch = curl_init();
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secretKey],
        CURLOPT_TIMEOUT => 20,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_URL] = $url;
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
    } else {
        $opts[CURLOPT_URL] = $params ? $url . '?' . http_build_query($params) : $url;
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("Stripe-Request fehlgeschlagen: $err");
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode((string) $body, true) ?? [];
    if ($status >= 400) {
        $message = $data['error']['message'] ?? "Stripe-Fehler ($status)";
        throw new RuntimeException($message);
    }
    return $data;
}

function stripe_create_payment_intent(int $amountCents, string $currency, array $metadata, ?string $receiptEmail = null): array
{
    $params = [
        'amount' => $amountCents,
        'currency' => $currency,
        'automatic_payment_methods' => ['enabled' => 'true'],
        'metadata' => $metadata,
    ];
    if ($receiptEmail) {
        $params['receipt_email'] = $receiptEmail;
    }
    return stripe_request('POST', '/payment_intents', $params);
}

function stripe_retrieve_payment_intent(string $id): array
{
    return stripe_request('GET', '/payment_intents/' . urlencode($id));
}

/**
 * Verifies Stripe's `Stripe-Signature` header per Stripe's documented algorithm:
 * https://stripe.com/docs/webhooks/signatures
 */
function stripe_verify_webhook_signature(string $payload, string $sigHeader, string $secret): bool
{
    $parts = [];
    foreach (explode(',', $sigHeader) as $chunk) {
        [$k, $v] = array_pad(explode('=', trim($chunk), 2), 2, null);
        if ($k === 't') {
            $parts['t'] = $v;
        } elseif ($k === 'v1') {
            $parts['v1'][] = $v;
        }
    }
    if (empty($parts['t']) || empty($parts['v1'])) {
        return false;
    }

    $signedPayload = $parts['t'] . '.' . $payload;
    $expected = hash_hmac('sha256', $signedPayload, $secret);

    foreach ($parts['v1'] as $candidate) {
        if (hash_equals($expected, (string) $candidate)) {
            return true;
        }
    }
    return false;
}
