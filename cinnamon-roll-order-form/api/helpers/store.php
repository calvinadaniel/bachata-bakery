<?php
/**
 * api/helpers/store.php
 *
 * Store open/closed state, pause (vacation) settings, weekly sell-out
 * reservations, and checkout rate limiting. Used by status.php, menu.php,
 * checkout.php, and the admin dashboard.
 *
 * Public API:
 *   computeStoreStatus(DateTimeImmutable $now, array $cap, ?string $pausedUntil, ?string $message): array
 *   storeStatus(PDO $pdo, DateTimeImmutable $now): array
 *   getSetting(PDO $pdo, string $key): ?string
 *   setSetting(PDO $pdo, string $key, ?string $value): void
 *   reserveCheckout(PDO $pdo, string $windowId, string $ref, array $order): int
 *   markCheckoutPaid(PDO $pdo, int $orderId, string $squareOrderId, string $paymentId): void
 *   failCheckout(PDO $pdo, int $orderId, string $windowId, int $items, ?string $squareOrderId = null): void
 *   rateLimitVerdict(int $recentAttempts, int $recentDeclines, int $maxAttempts, int $maxDeclines): ?string
 *   checkRateLimit(PDO $pdo, string $ipHash): ?string
 *   recordAttempt(PDO $pdo, string $ipHash, string $outcome): void
 */

declare(strict_types=1);

require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/cap.php';

/** Thrown by reserveCheckout() when the window can't take the order. */
final class CheckoutRejected extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly int $httpCode)
    {
        parent::__construct($message);
    }
}

// ---------------------------------------------------------------
// Status
// ---------------------------------------------------------------

/** First moment at or after $from when the weekly window is open. */
function nextOpenMoment(DateTimeImmutable $from): DateTimeImmutable
{
    $d = $from;
    for ($i = 0; $i < 8 && !isOpenAt($d); $i++) {
        $d = $d->setTime(0, 0)->modify('+1 day');
    }
    return $d;
}

/**
 * Pure status calculation. $cap is an order_caps row (see cap.php defaults).
 * Precedence: vacation → time gate → force closed → sold out.
 */
function computeStoreStatus(DateTimeImmutable $now, array $cap, ?string $pausedUntil, ?string $message): array
{
    $wednesday = orderWeekWednesday($now);
    $closesAt  = $wednesday->setTime(23, 59, 59);
    $itemsLeft = max(0, (int) $cap['rolls_max'] - (int) $cap['rolls_sold']);
    $ordersLeft = max(0, (int) $cap['orders_max'] - (int) $cap['orders_placed']);

    $pauseEnd = null;
    if ($pausedUntil !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $pausedUntil)) {
        $pauseEnd = DateTimeImmutable::createFromFormat('!Y-m-d', $pausedUntil, $now->getTimezone());
        if ($pauseEnd <= $now) {
            $pauseEnd = null;
        }
    }

    $reason = match (true) {
        $pauseEnd !== null                  => 'vacation',
        !isOpenAt($now)                     => 'time_gate',
        (int) $cap['force_closed'] === 1    => 'force_closed',
        $itemsLeft === 0 || $ordersLeft === 0 => 'sold_out',
        default                             => null,
    };

    $reopensAt = match ($reason) {
        'vacation'  => nextOpenMoment($pauseEnd),
        'time_gate' => nextOpenMoment($now->setTime(0, 0)->modify('+1 day')),
        default     => nextOpenMoment($closesAt->modify('+1 second')),
    };

    return [
        'open'             => $reason === null,
        'closed_reason'    => $reason,
        'window_id'        => $wednesday->format('Y-m-d'),
        'closes_at'        => $closesAt->format(DATE_RFC3339),
        'reopens_at'       => $reopensAt->format(DATE_RFC3339),
        'items_remaining'  => $itemsLeft,
        'orders_remaining' => $ordersLeft,
        'message'          => $reason === 'vacation' ? $message : null,
    ];
}

function storeStatus(PDO $pdo, DateTimeImmutable $now): array
{
    $windowId = orderWeekWednesday($now)->format('Y-m-d');
    return computeStoreStatus(
        $now,
        getCapRow($pdo, $windowId),
        getSetting($pdo, 'paused_until'),
        getSetting($pdo, 'pause_message')
    );
}

// ---------------------------------------------------------------
// Settings
// ---------------------------------------------------------------

function getSetting(PDO $pdo, string $key): ?string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM store_settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return ($value === false || $value === '') ? null : (string) $value;
}

function setSetting(PDO $pdo, string $key, ?string $value): void
{
    $pdo->prepare(
        'INSERT INTO store_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    )->execute([$key, $value]);
}

// ---------------------------------------------------------------
// Sell-out reservations
// ---------------------------------------------------------------

/**
 * Locks the window's cap row, checks limits, inserts a 'pending' order and
 * pre-increments the counters in one short transaction (no network I/O while
 * the lock is held). Returns the orders.id. Throws CheckoutRejected.
 */
function reserveCheckout(PDO $pdo, string $windowId, string $ref, array $order): int
{
    ensureCapRowExists($pdo, $windowId);
    $items = array_sum(array_column($order['lines'], 'qty'));

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT rolls_sold, orders_placed, rolls_max, orders_max, force_closed
               FROM order_caps WHERE window_id = ? FOR UPDATE'
        );
        $stmt->execute([$windowId]);
        $cap = $stmt->fetch();

        if ((int) $cap['force_closed'] === 1) {
            throw new CheckoutRejected('form_closed', 'Orders are closed for this week.', 403);
        }
        if ((int) $cap['orders_placed'] >= (int) $cap['orders_max']) {
            throw new CheckoutRejected('sold_out', 'We’re sold out for this week — thank you! Orders reopen Sunday.', 409);
        }
        $left = (int) $cap['rolls_max'] - (int) $cap['rolls_sold'];
        if ($items > $left) {
            throw new CheckoutRejected(
                'sold_out',
                $left > 0
                    ? "Only {$left} item" . ($left === 1 ? '' : 's') . ' left this week. Please reduce your order.'
                    : 'We’re sold out for this week — thank you! Orders reopen Sunday.',
                409
            );
        }

        $c = $order['contact'];
        $p = $order['pickup'];
        $summary = implode(', ', array_map(static fn($l) => "{$l['qty']}× {$l['name']}", $order['lines']));

        $pdo->prepare(
            'INSERT INTO orders
               (order_ref, window_id, customer_name, customer_email, customer_phone,
                quantity, product_variant, items_json, pickup_date, pickup_location, pickup_time,
                payment_status, amount_cents)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', ?)'
        )->execute([
            $ref,
            $windowId,
            "{$c['first']} {$c['last']}",
            $c['email'],
            $c['phone'],
            $items,
            mb_substr($summary, 0, 255),
            json_encode($order['lines'], JSON_UNESCAPED_UNICODE),
            $p['date'],
            $p['label'],
            $p['time'] . ':00',
            $order['total_cents'],
        ]);
        $orderId = (int) $pdo->lastInsertId();

        $pdo->prepare(
            'UPDATE order_caps SET rolls_sold = rolls_sold + ?, orders_placed = orders_placed + 1 WHERE window_id = ?'
        )->execute([$items, $windowId]);

        $pdo->commit();
        return $orderId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function markCheckoutPaid(PDO $pdo, int $orderId, string $squareOrderId, string $paymentId): void
{
    try {
        $pdo->prepare(
            "UPDATE orders SET payment_status = 'paid', square_order_id = ?, square_payment_id = ? WHERE id = ?"
        )->execute([$squareOrderId, $paymentId, $orderId]);
    } catch (Throwable $e) {
        // The charge succeeded; the payment webhook reconciles the row.
        error_log("[bachata-bakery] CRITICAL: payment {$paymentId} succeeded but order #{$orderId} update failed: " . $e->getMessage());
    }
}

/** Marks the order failed and releases its reserved items/order slot. */
function failCheckout(PDO $pdo, int $orderId, string $windowId, int $items, ?string $squareOrderId = null): void
{
    try {
        $pdo->beginTransaction();
        $pdo->prepare(
            "UPDATE orders SET payment_status = 'failed', square_order_id = COALESCE(?, square_order_id) WHERE id = ?"
        )->execute([$squareOrderId, $orderId]);
        $pdo->prepare(
            'UPDATE order_caps
                SET rolls_sold = GREATEST(0, rolls_sold - ?), orders_placed = GREATEST(0, orders_placed - 1)
              WHERE window_id = ?'
        )->execute([$items, $windowId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("[bachata-bakery] CRITICAL: releasing caps for order #{$orderId} failed: " . $e->getMessage());
    }
}

// ---------------------------------------------------------------
// Rate limiting
// ---------------------------------------------------------------

function rateLimitVerdict(int $recentAttempts, int $recentDeclines, int $maxAttempts, int $maxDeclines): ?string
{
    if ($recentDeclines >= $maxDeclines) {
        return 'Too many declined payments. Please wait an hour or contact info@bachatabakery.com.';
    }
    if ($recentAttempts >= $maxAttempts) {
        return 'Too many attempts. Please wait 15 minutes and try again.';
    }
    return null;
}

function clientIpHash(): string
{
    load_env();
    return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|' . ($_ENV['SQUARE_LOCATION_ID'] ?? ''));
}

function checkRateLimit(PDO $pdo, string $ipHash): ?string
{
    load_env();
    $stmt = $pdo->prepare(
        "SELECT
            SUM(outcome = 'attempt' AND created_at >= NOW() - INTERVAL 15 MINUTE),
            SUM(outcome = 'declined' AND created_at >= NOW() - INTERVAL 60 MINUTE)
           FROM checkout_attempts
          WHERE ip_hash = ? AND created_at >= NOW() - INTERVAL 60 MINUTE"
    );
    $stmt->execute([$ipHash]);
    [$attempts, $declines] = $stmt->fetch(PDO::FETCH_NUM);

    return rateLimitVerdict(
        (int) $attempts,
        (int) $declines,
        (int) ($_ENV['RATE_LIMIT_ATTEMPTS'] ?? 8),
        (int) ($_ENV['RATE_LIMIT_DECLINES'] ?? 3)
    );
}

function recordAttempt(PDO $pdo, string $ipHash, string $outcome): void
{
    $pdo->prepare('INSERT INTO checkout_attempts (ip_hash, outcome) VALUES (?, ?)')->execute([$ipHash, $outcome]);

    if (random_int(1, 100) === 1) {
        $pdo->exec('DELETE FROM checkout_attempts WHERE created_at < NOW() - INTERVAL 2 DAY');
    }
}
