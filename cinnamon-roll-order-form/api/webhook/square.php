<?php
/**
 * api/webhook/square.php
 *
 * POST — Square webhook receiver.
 *
 * Security: validates x-square-hmacsha256-signature before touching the DB.
 * Idempotent: safe to call multiple times for the same event (Square retries on non-2xx).
 *
 * Events handled:
 *   payment.completed — confirm / reconcile a paid order
 *   payment.failed    — reconcile a payment that failed after order.php ran
 *
 * Reconciliation scenarios (see order.php for the normal flow):
 *
 *   payment.completed + order is 'pending'
 *     → order.php crashed after the Square charge but before finaliseOrder().
 *       Counters were already pre-incremented. Just flip status to 'paid'.
 *
 *   payment.completed + order is 'paid'
 *     → Already handled by order.php. No-op (idempotent).
 *
 *   payment.completed + order is 'failed'
 *     → failOrder() ran (decremented counters) but Square actually succeeded.
 *       Flip status to 'paid' and re-increment counters. Log as UNUSUAL.
 *
 *   payment.failed + order is 'pending'
 *     → order.php crashed before failOrder() could decrement counters.
 *       Flip status to 'failed' and decrement counters.
 *
 *   payment.failed + order is 'failed'
 *     → Already handled. No-op.
 *
 *   payment.failed + order is 'paid'
 *     → Genuine conflict. Log as CRITICAL. Do not modify — needs manual review.
 */

declare(strict_types=1);

// IMPORTANT: read the raw body first — the stream is consumed once.
// We need the raw bytes for HMAC verification before json_decode touches it.
$rawBody = (string) file_get_contents('php://input');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

require_once dirname(__DIR__) . '/helpers/db.php';

// ---------------------------------------------------------------
// Custom exception for signature failures
// ---------------------------------------------------------------
class SignatureException extends RuntimeException {}

// ---------------------------------------------------------------
// Main
// ---------------------------------------------------------------
try {
    load_env();

    // 1. Validate signature — reject anything that doesn't pass
    validateSignature($rawBody);

    // 2. Parse event payload
    $event = json_decode($rawBody, associative: true, flags: JSON_THROW_ON_ERROR);
    $type  = $event['type'] ?? '';

    // 3. Route to handler — unrecognised types are silently accepted (200)
    match ($type) {
        'payment.completed' => handlePaymentCompleted($event),
        'payment.failed'    => handlePaymentFailed($event),
        default             => null,
    };

    http_response_code(200);
    echo json_encode(['ok' => true]);

} catch (SignatureException $e) {
    error_log('[bachata-bakery] Webhook rejected — invalid signature: ' . $e->getMessage());
    http_response_code(403);
    echo json_encode(['error' => 'invalid_signature']);

} catch (JsonException $e) {
    error_log('[bachata-bakery] Webhook rejected — bad JSON: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'invalid_payload']);

} catch (Throwable $e) {
    // Return 500 so Square retries — only for genuine transient failures (DB down, etc.)
    error_log('[bachata-bakery] Webhook handler error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}

// ---------------------------------------------------------------
// validateSignature()
//
// Square computes: base64( HMAC-SHA256( sigKey, notificationUrl + rawBody ) )
// and sends the result in the x-square-hmacsha256-signature header.
//
// The notification URL must match exactly what is registered in the
// Square Developer Dashboard — set WEBHOOK_URL in .env to be explicit.
// ---------------------------------------------------------------
function validateSignature(string $rawBody): void
{
    $sigHeader = $_SERVER['HTTP_X_SQUARE_HMACSHA256_SIGNATURE'] ?? '';
    if ($sigHeader === '') {
        throw new SignatureException('Missing x-square-hmacsha256-signature header');
    }

    $sigKey = $_ENV['SQUARE_WEBHOOK_SIG_KEY'] ?? '';
    if ($sigKey === '') {
        throw new RuntimeException('SQUARE_WEBHOOK_SIG_KEY not set in .env');
    }

    $notificationUrl = resolveNotificationUrl();
    $expected        = base64_encode(
        hash_hmac('sha256', $notificationUrl . $rawBody, $sigKey, binary: true)
    );

    // hash_equals is timing-safe — prevents timing side-channel attacks
    if (!hash_equals($expected, $sigHeader)) {
        throw new SignatureException(
            "Signature mismatch for URL: {$notificationUrl}"
        );
    }
}

/**
 * Returns the webhook's notification URL as Square registered it.
 * WEBHOOK_URL in .env is the canonical source; $_SERVER fallback is for local dev.
 */
function resolveNotificationUrl(): string
{
    if (!empty($_ENV['WEBHOOK_URL'])) {
        return $_ENV['WEBHOOK_URL'];
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $path   = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '/api/webhook/square.php';

    return "{$scheme}://{$host}{$path}";
}

// ---------------------------------------------------------------
// handlePaymentCompleted()
// ---------------------------------------------------------------
function handlePaymentCompleted(array $event): void
{
    $payment   = $event['data']['object']['payment'] ?? [];
    $paymentId = $payment['id'] ?? '';
    $orderRef  = $payment['reference_id'] ?? '';

    if ($paymentId === '') {
        error_log('[bachata-bakery] payment.completed missing payment.id — skipping');
        return;
    }

    $pdo   = db();
    $order = findOrder($pdo, $orderRef, $paymentId);

    if ($order === null) {
        // Could be a payment not associated with this system (e.g. manual Square charge).
        // Log and move on — no throw, so we return 200 and Square won't retry.
        error_log(
            "[bachata-bakery] payment.completed: no order found " .
            "(ref={$orderRef}, payment_id={$paymentId}) — not from this system?"
        );
        return;
    }

    $status = $order['payment_status'];

    if ($status === 'paid') {
        // Already finalised by order.php — idempotent no-op
        return;
    }

    if ($status === 'pending') {
        // order.php crashed between Square charge and finaliseOrder().
        // Counters were pre-incremented — only the status update is missing.
        $pdo->prepare(
            "UPDATE orders SET payment_status = 'paid', square_payment_id = ? WHERE id = ?"
        )->execute([$paymentId, $order['id']]);

        error_log(
            "[bachata-bakery] Webhook reconciled pending→paid: {$order['order_ref']}"
        );
        return;
    }

    if ($status === 'failed') {
        // Unusual: failOrder() decremented counters but the payment actually completed.
        // Restore the order and re-increment counters inside a transaction.
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "UPDATE orders SET payment_status = 'paid', square_payment_id = ? WHERE id = ?"
            )->execute([$paymentId, $order['id']]);

            $pdo->prepare(
                'UPDATE order_caps
                    SET rolls_sold    = rolls_sold    + :qty,
                        orders_placed = orders_placed + 1
                  WHERE window_id = :win'
            )->execute([':qty' => (int) $order['quantity'], ':win' => $order['window_id']]);

            $pdo->commit();

            error_log(
                "[bachata-bakery] UNUSUAL — payment.completed for 'failed' order " .
                "{$order['order_ref']}: status corrected to 'paid', counters re-incremented"
            );
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

// ---------------------------------------------------------------
// handlePaymentFailed()
// ---------------------------------------------------------------
function handlePaymentFailed(array $event): void
{
    $payment   = $event['data']['object']['payment'] ?? [];
    $paymentId = $payment['id'] ?? '';
    $orderRef  = $payment['reference_id'] ?? '';

    if ($paymentId === '' && $orderRef === '') {
        error_log('[bachata-bakery] payment.failed missing both payment.id and reference_id — skipping');
        return;
    }

    $pdo   = db();
    $order = findOrder($pdo, $orderRef, $paymentId);

    if ($order === null) {
        error_log(
            "[bachata-bakery] payment.failed: no order found " .
            "(ref={$orderRef}, payment_id={$paymentId})"
        );
        return;
    }

    $status = $order['payment_status'];

    if ($status === 'failed') {
        // Already handled by failOrder() in order.php — idempotent no-op
        return;
    }

    if ($status === 'paid') {
        // Genuine conflict — order was marked paid but Square reports failure.
        // Do not modify the record. Flag for manual review.
        error_log(
            "[bachata-bakery] CRITICAL — payment.failed for already-paid order " .
            "{$order['order_ref']} (payment_id={$paymentId}). " .
            "Manual review required — no automatic action taken."
        );
        return;
    }

    if ($status === 'pending') {
        // order.php crashed before failOrder() could decrement counters.
        // Mark failed and compensate.
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "UPDATE orders SET payment_status = 'failed', square_payment_id = ? WHERE id = ?"
            )->execute([$paymentId, $order['id']]);

            // GREATEST(0, ...) prevents counters going negative if called twice
            $pdo->prepare(
                'UPDATE order_caps
                    SET rolls_sold    = GREATEST(0, rolls_sold    - :qty),
                        orders_placed = GREATEST(0, orders_placed - 1)
                  WHERE window_id = :win'
            )->execute([':qty' => (int) $order['quantity'], ':win' => $order['window_id']]);

            $pdo->commit();

            error_log(
                "[bachata-bakery] Webhook reconciled pending→failed: " .
                "{$order['order_ref']} — counters decremented"
            );
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

// ---------------------------------------------------------------
// findOrder()
// Look up by order_ref first (our value), fall back to square_payment_id.
// Returns the order row array or null.
// ---------------------------------------------------------------
function findOrder(PDO $pdo, string $orderRef, string $paymentId): ?array
{
    if ($orderRef !== '') {
        $stmt = $pdo->prepare(
            'SELECT id, order_ref, window_id, quantity, payment_status, square_payment_id
               FROM orders WHERE order_ref = ?'
        );
        $stmt->execute([$orderRef]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return $row;
        }
    }

    if ($paymentId !== '') {
        $stmt = $pdo->prepare(
            'SELECT id, order_ref, window_id, quantity, payment_status, square_payment_id
               FROM orders WHERE square_payment_id = ?'
        );
        $stmt->execute([$paymentId]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return $row;
        }
    }

    return null;
}
