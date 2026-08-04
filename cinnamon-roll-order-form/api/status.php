<?php
/**
 * api/status.php
 *
 * GET — Returns the current form state. Polled by the order form every 60 s
 * and by the marketing homepage on load.
 *
 * Response shape:
 * {
 *   "open":             bool,
 *   "next_open":        ISO 8601 — next Sunday 00:00 (bakery timezone),
 *   "closes_at":        ISO 8601 — Wed 23:59:59 of the active order week,
 *   "reopens_at":       ISO 8601 — alias of next_open (homepage countdown),
 *   "window_id":        string — Friday Y-m-d capacity window key,
 *   "rolls_remaining":  int,
 *   "orders_remaining": int,
 *   "force_closed":     bool,
 *   "closed_reason":    "time_gate"|"sold_out"|"force_closed"|null,
 *   "timezone":         string
 * }
 *
 * Always returns JSON. On server error returns HTTP 500 with
 * { "open": false, "error": "server_error" } so the frontend degrades safely.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Cache-Control: no-store');

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
    $closesAt  = getClosesAt()->format(DateTimeInterface::ATOM);
    $windowId  = getCurrentWindowId();
    $tzName    = ($_ENV['BAKERY_TIMEZONE'] ?? 'America/New_York');

    if (!$formOpen) {
        echo json_encode([
            'open'             => false,
            'next_open'        => $nextOpen,
            'closes_at'        => $closesAt,
            'reopens_at'       => $nextOpen,
            'window_id'        => $windowId,
            'rolls_remaining'  => 0,
            'orders_remaining' => 0,
            'force_closed'     => false,
            'closed_reason'    => 'time_gate',
            'timezone'         => $tzName,
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
        'closes_at'        => $closesAt,
        'reopens_at'       => $nextOpen,
        'window_id'        => $windowId,
        'rolls_remaining'  => rollsRemaining($cap),
        'orders_remaining' => ordersRemaining($cap),
        'force_closed'     => (bool) $cap['force_closed'],
        'closed_reason'    => $closedReason,
        'timezone'         => $tzName,
    ]);

} catch (Throwable $e) {
    error_log('[bachata-bakery] status.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['open' => false, 'error' => 'server_error']);
}
