<?php
/**
 * api/checkout.php
 *
 * POST — Native preorder checkout.
 *
 * Flow:
 *   1. Rate limit (per hashed IP) and hCaptcha
 *   2. Store status: vacation / time gate / force closed / sold out
 *   3. Validate body; recompute total from helpers/catalog.php
 *   4. Reserve items + order slot (short DB transaction, row lock)
 *   5. Square CreateOrder (line items + PICKUP fulfillment)
 *   6. Square CreatePayment against that order
 *   7. Paid → mark order paid. Any failure → mark failed, release items.
 *
 * Error codes returned to client:
 *   rate_limited | captcha_failed | form_closed | sold_out |
 *   invalid_input | card_declined | server_error
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'method_not_allowed', 'Method not allowed.');
}
if (!str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    fail(415, 'invalid_input', 'Content-Type must be application/json.');
}

require_once __DIR__ . '/helpers/time_gate.php';
require_once __DIR__ . '/helpers/checkout_lib.php';
require_once __DIR__ . '/helpers/square.php';
require_once __DIR__ . '/helpers/store.php';
require_once __DIR__ . '/helpers/captcha.php';

const NOT_CHARGED = 'Something went wrong on our end. You have not been charged.';

try {
    $now    = new DateTimeImmutable('now', _bakeryTz());
    $pdo    = db();
    $ipHash = clientIpHash();

    // 1. Card-testing protection
    if (($limited = checkRateLimit($pdo, $ipHash)) !== null) {
        fail(429, 'rate_limited', $limited);
    }
    recordAttempt($pdo, $ipHash, 'attempt');

    $body = json_decode((string) file_get_contents('php://input'), true, 16, JSON_THROW_ON_ERROR);

    if (($captchaError = verifyCaptcha(is_array($body) ? ($body['captcha_token'] ?? null) : null)) !== null) {
        fail(422, 'captcha_failed', $captchaError);
    }

    // 2. Store open?
    $status = storeStatus($pdo, $now);
    if (!$status['open']) {
        [$code, $http] = $status['closed_reason'] === 'sold_out' ? ['sold_out', 409] : ['form_closed', 403];
        fail($http, $code, closedMessage($status));
    }

    // 3. Validate
    $result = validateCheckout($body, $now);
    if ($result['errors'] !== []) {
        fail(422, 'invalid_input', implode(' ', $result['errors']));
    }
    $order    = $result['order'];
    $items    = array_sum(array_column($order['lines'], 'qty'));
    $windowId = $status['window_id'];
    $ref      = makeReferenceId($now);

    // 4. Reserve
    try {
        $orderId = reserveCheckout($pdo, $windowId, $ref, $order);
    } catch (CheckoutRejected $e) {
        fail($e->httpCode, $e->errorCode, $e->getMessage());
    }

    $locationId = squareLocationId();

    // 5. Square order
    $orderRes = squareRequest('orders', buildOrderBody($order, $locationId, $ref));
    $sqOrder  = $orderRes['data']['order'] ?? null;

    if ($orderRes['error'] !== null || $orderRes['status'] !== 200 || $sqOrder === null) {
        logSquareFailure('CreateOrder', $ref, $orderRes);
        failCheckout($pdo, $orderId, $windowId, $items);
        fail(500, 'server_error', NOT_CHARGED);
    }

    if ((int) ($sqOrder['total_money']['amount'] ?? -1) !== $order['total_cents']) {
        error_log("[bachata-bakery] checkout {$ref}: Square total ≠ computed {$order['total_cents']}");
        failCheckout($pdo, $orderId, $windowId, $items, $sqOrder['id']);
        fail(500, 'server_error', NOT_CHARGED);
    }

    // 6. Payment
    $payRes  = squareRequest('payments', buildPaymentBody($order, $locationId, $ref, $sqOrder['id']));
    $payment = $payRes['data']['payment'] ?? null;

    if ($payRes['error'] !== null) {
        // Transport failure: the charge may or may not have happened. Leave the
        // order pending so the payment webhook can settle it either way.
        logSquareFailure('CreatePayment', $ref, $payRes);
        fail(500, 'server_error', 'We couldn’t confirm your payment. Please check your email for a Square receipt before trying again.');
    }

    if ($payRes['status'] !== 200 || ($payment['status'] ?? '') !== 'COMPLETED') {
        logSquareFailure('CreatePayment', $ref, $payRes);
        failCheckout($pdo, $orderId, $windowId, $items, $sqOrder['id']);
        recordAttempt($pdo, $ipHash, 'declined');
        fail(402, 'card_declined', squareDeclineMessage($payRes['data']['errors'][0]['code'] ?? ''));
    }

    // 7. Paid
    markCheckoutPaid($pdo, $orderId, $sqOrder['id'], $payment['id']);
    recordAttempt($pdo, $ipHash, 'paid');

    echo json_encode([
        'success'      => true,
        'reference_id' => $ref,
        'order_id'     => $sqOrder['id'],
        'receipt_url'  => $payment['receipt_url'] ?? null,
        'total_cents'  => $order['total_cents'],
        'pickup'       => [
            'label'      => $order['pickup']['label'],
            'date_label' => $order['pickup']['date_label'],
            'time_label' => $order['pickup']['time_label'],
        ],
        'items'        => array_map(
            static fn(array $l) => ['name' => $l['name'], 'qty' => $l['qty'], 'line_cents' => $l['line_cents']],
            $order['lines']
        ),
    ], JSON_UNESCAPED_UNICODE);

} catch (JsonException) {
    fail(400, 'invalid_input', 'Invalid JSON body.');
} catch (Throwable $e) {
    error_log('[bachata-bakery] checkout.php unhandled: ' . $e->getMessage());
    fail(500, 'server_error', NOT_CHARGED);
}

function closedMessage(array $status): string
{
    return match ($status['closed_reason']) {
        'vacation'  => $status['message'] ?: 'Orders are paused right now. Please check back soon!',
        'sold_out'  => 'We’re sold out for this week — thank you! Orders reopen Sunday.',
        default     => 'Orders are closed right now — the window reopens Sunday.',
    };
}

function logSquareFailure(string $call, string $ref, array $res): void
{
    $first = $res['data']['errors'][0] ?? [];
    error_log(sprintf(
        '[bachata-bakery] checkout %s %s failed (HTTP %d): %s %s — %s',
        $ref,
        $call,
        $res['status'],
        $res['error'] ?? '',
        $first['code'] ?? '',
        $first['detail'] ?? ''
    ));
}

function fail(int $httpCode, string $errorCode, string $message): never
{
    http_response_code($httpCode);
    echo json_encode(['success' => false, 'error_code' => $errorCode, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
