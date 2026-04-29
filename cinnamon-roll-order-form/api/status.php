<?php
/**
 * api/status.php
 *
 * GET — Returns the current form state. Polled by the frontend every 60 s.
 *
 * Response shape:
 * {
 *   "open":             bool,
 *   "next_open":        ISO 8601 string (next Friday midnight, bakery timezone),
 *   "rolls_remaining":  int,
 *   "orders_remaining": int,
 *   "force_closed":     bool
 * }
 *
 * Always returns JSON. On server error returns HTTP 500 with
 * { "open": false, "error": "server_error" } so the frontend degrades safely.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// Only GET allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['open' => false, 'error' => 'method_not_allowed']);
    exit;
}

require_once __DIR__ . '/helpers/db.php';
require_once __DIR__ . '/helpers/time_gate.php';
require_once __DIR__ . '/helpers/cap.php';

try {
    $formOpen  = isFormOpen();
    $nextOpen  = getNextOpenTime()->format(DateTimeInterface::ATOM);
    $windowId  = getCurrentWindowId();

    if (!$formOpen) {
        // Outside the Friday–Sunday window — no active cap row to query.
        echo json_encode([
            'open'             => false,
            'next_open'        => $nextOpen,
            'rolls_remaining'  => 0,
            'orders_remaining' => 0,
            'force_closed'     => false,
            'closed_reason'    => 'time_gate',
        ]);
        exit;
    }

    $cap      = getCapRow(db(), $windowId);
    $soldOut  = isSoldOut($cap);

    $closedReason = null;
    if ($soldOut) {
        $closedReason = (bool) $cap['force_closed'] ? 'force_closed' : 'sold_out';
    }

    echo json_encode([
        'open'             => !$soldOut,
        'next_open'        => $nextOpen,
        'rolls_remaining'  => rollsRemaining($cap),
        'orders_remaining' => ordersRemaining($cap),
        'force_closed'     => (bool) $cap['force_closed'],
        'closed_reason'    => $closedReason,
    ]);

} catch (Throwable $e) {
    error_log('[bachata-bakery] status.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['open' => false, 'error' => 'server_error']);
}
