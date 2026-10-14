<?php
/**
 * api/checkout.php
 *
 * POST — Native preorder checkout (no database).
 *
 * Flow:
 *   1. Time gate (Sun–Wed)
 *   2. Validate body; recompute total from helpers/catalog.php
 *   3. Square CreateOrder (line items + PICKUP fulfillment)
 *   4. Square CreatePayment against that order
 *
 * Error codes returned to client:
 *   form_closed | invalid_input | card_declined | server_error
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

try {
    $now = new DateTimeImmutable('now', _bakeryTz());

    if (!isOpenAt($now)) {
        fail(403, 'form_closed', 'Orders are closed right now — the window reopens Sunday.');
    }

    $body = json_decode((string) file_get_contents('php://input'), true, 16, JSON_THROW_ON_ERROR);

    $result = validateCheckout($body, $now);
    if ($result['errors'] !== []) {
        fail(422, 'invalid_input', implode(' ', $result['errors']));
    }
    $order = $result['order'];

    $locationId = squareLocationId();
    $ref        = makeReferenceId($now);

    // Square order
    $orderRes = squareRequest('orders', buildOrderBody($order, $locationId, $ref));
    $sqOrder  = $orderRes['data']['order'] ?? null;

    if ($orderRes['error'] !== null || $orderRes['status'] !== 200 || $sqOrder === null) {
        logSquareFailure('CreateOrder', $ref, $orderRes);
        fail(500, 'server_error', 'Something went wrong on our end. You have not been charged.');
    }

    $squareTotal = (int) ($sqOrder['total_money']['amount'] ?? -1);
    if ($squareTotal !== $order['total_cents']) {
        error_log("[bachata-bakery] checkout {$ref}: Square total {$squareTotal} ≠ computed {$order['total_cents']}");
        fail(500, 'server_error', 'Something went wrong on our end. You have not been charged.');
    }

    // Payment
    $payRes  = squareRequest('payments', buildPaymentBody($order, $locationId, $ref, $sqOrder['id']));
    $payment = $payRes['data']['payment'] ?? null;

    if ($payRes['error'] !== null) {
        logSquareFailure('CreatePayment', $ref, $payRes);
        fail(500, 'server_error', 'Payment service unavailable. You have not been charged — please try again.');
    }

    if ($payRes['status'] !== 200 || ($payment['status'] ?? '') !== 'COMPLETED') {
        logSquareFailure('CreatePayment', $ref, $payRes);
        $code = $payRes['data']['errors'][0]['code'] ?? '';
        fail(402, 'card_declined', squareDeclineMessage($code));
    }

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
    fail(500, 'server_error', 'Something went wrong on our end. You have not been charged.');
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
