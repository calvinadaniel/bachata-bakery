<?php
/**
 * api/helpers/cap.php
 *
 * Cap-check helpers for the order_caps table.
 * These are READ-ONLY helpers for status checks.
 * The atomic cap increment lives in api/order.php inside a transaction.
 *
 * Public API:
 *   getCapRow(PDO, string): array        — fetch or synthesize the cap row for a window
 *   ensureCapRowExists(PDO, string): void — INSERT IGNORE a default row (call before locking)
 *   isSoldOut(array): bool
 *   rollsRemaining(array): int
 *   ordersRemaining(array): int
 *
 * Requires db.php to have been loaded first.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Fetch the order_caps row for $windowId.
 * If no row exists yet (no orders have been placed this weekend),
 * returns an array of defaults — does NOT write to the DB.
 */
function getCapRow(PDO $pdo, string $windowId): array
{
    $stmt = $pdo->prepare(
        'SELECT window_id, rolls_sold, orders_placed, rolls_max, orders_max, force_closed
           FROM order_caps
          WHERE window_id = ?'
    );
    $stmt->execute([$windowId]);
    $row = $stmt->fetch();

    if ($row === false) {
        return [
            'window_id'     => $windowId,
            'rolls_sold'    => 0,
            'orders_placed' => 0,
            'rolls_max'     => 100,
            'orders_max'    => 50,
            'force_closed'  => 0,
        ];
    }

    return $row;
}

/**
 * INSERT IGNORE a default cap row for $windowId.
 * Call this at the start of order.php (before the FOR UPDATE lock)
 * so the row exists and can be locked with SELECT … FOR UPDATE.
 */
function ensureCapRowExists(PDO $pdo, string $windowId): void
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO order_caps (window_id, rolls_sold, orders_placed, rolls_max, orders_max, force_closed)
         VALUES (?, 0, 0, 100, 50, 0)'
    );
    $stmt->execute([$windowId]);
}

/**
 * Returns true if either cap is exhausted or the owner forced it closed.
 */
function isSoldOut(array $cap): bool
{
    if ((int) $cap['force_closed'] === 1) {
        return true;
    }

    if ((int) $cap['rolls_sold'] >= (int) $cap['rolls_max']) {
        return true;
    }

    if ((int) $cap['orders_placed'] >= (int) $cap['orders_max']) {
        return true;
    }

    return false;
}

/**
 * How many rolls can still be ordered this window?
 * Returns 0 rather than a negative number if somehow oversold.
 */
function rollsRemaining(array $cap): int
{
    return max(0, (int) $cap['rolls_max'] - (int) $cap['rolls_sold']);
}

/**
 * How many order slots are still open this window?
 */
function ordersRemaining(array $cap): int
{
    return max(0, (int) $cap['orders_max'] - (int) $cap['orders_placed']);
}
