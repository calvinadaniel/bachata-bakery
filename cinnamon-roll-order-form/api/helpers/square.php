<?php
/**
 * api/helpers/square.php
 *
 * Minimal Square REST client for the native order form.
 *
 * Public API:
 *   squareLocationId(): string
 *   squareRequest(string $path, array $body): array{status: int, data: array, error: ?string}
 *   squareDeclineMessage(string $code): string
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const SQUARE_REST_SANDBOX    = 'https://connect.squareupsandbox.com/v2';
const SQUARE_REST_PRODUCTION = 'https://connect.squareup.com/v2';
const SQUARE_REST_VERSION    = '2024-01-18';

function squareLocationId(): string
{
    load_env();
    return $_ENV['SQUARE_LOCATION_ID'] ?? '';
}

/**
 * POST $body as JSON to {base}/{path}. `error` is set only for transport
 * failures (DNS, TLS, timeout, missing credentials); HTTP errors from Square
 * come back with their status and decoded body.
 */
function squareRequest(string $path, array $body): array
{
    load_env();

    $token = $_ENV['SQUARE_ACCESS_TOKEN'] ?? '';
    if ($token === '' || squareLocationId() === '') {
        return ['status' => 0, 'data' => [], 'error' => 'Square credentials not configured in .env'];
    }

    $base = ($_ENV['APP_ENV'] ?? 'sandbox') === 'production' ? SQUARE_REST_PRODUCTION : SQUARE_REST_SANDBOX;

    $ch = curl_init($base . '/' . ltrim($path, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Square-Version: ' . SQUARE_REST_VERSION,
            "Authorization: Bearer {$token}",
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 15,
    ]);

    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($err !== '') {
        return ['status' => 0, 'data' => [], 'error' => $err];
    }

    return ['status' => $status, 'data' => json_decode((string) $raw, true) ?? [], 'error' => null];
}

function squareDeclineMessage(string $code): string
{
    return match ($code) {
        'CARD_DECLINED',
        'CARD_DECLINED_CALL_ISSUER',
        'CARD_DECLINED_VERIFICATION_REQUIRED',
        'GENERIC_DECLINE'                => 'Your card was declined. Please try a different card.',
        'INVALID_CARD', 'INVALID_CARD_DATA',
        'INVALID_ACCOUNT'                => 'Card information is invalid. Please re-enter your card.',
        'EXPIRED_CARD', 'INVALID_EXPIRATION'
                                         => 'Your card has expired or the expiration date is wrong.',
        'CVV_FAILURE'                    => 'Incorrect CVV. Please check and try again.',
        'ADDRESS_VERIFICATION_FAILURE',
        'INVALID_POSTAL_CODE'            => 'Billing ZIP code did not match. Please check and try again.',
        'INSUFFICIENT_FUNDS'             => 'Insufficient funds. Please try a different card.',
        'PAYMENT_LIMIT_EXCEEDED'         => 'This payment exceeds your card limit. Please try a different card.',
        default                          => 'Payment was not approved. Please try a different payment method or contact your bank.',
    };
}
