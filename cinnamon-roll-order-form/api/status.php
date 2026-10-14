<?php
/**
 * api/status.php
 *
 * GET — Current preorder state. Polled by the homepage strip and the order
 * page gate (assets/js/preorder-window.js).
 *
 * Response shape:
 * {
 *   "open":             bool,
 *   "closed_reason":    null|"time_gate"|"vacation"|"sold_out"|"force_closed",
 *   "next_open":        ISO 8601 — alias of reopens_at,
 *   "reopens_at":       ISO 8601 — next moment orders open,
 *   "closes_at":        ISO 8601 — Wed 23:59:59 of the active order week,
 *   "window_id":        string — Wednesday Y-m-d closing the order week,
 *   "items_remaining":  int,
 *   "orders_remaining": int,
 *   "force_closed":     bool,
 *   "message":          string|null — owner's pause message,
 *   "timezone":         string
 * }
 *
 * On server error returns HTTP 500 with { "open": false, "error": "server_error" }
 * so the frontend falls back to its client-side time gate.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['open' => false, 'error' => 'method_not_allowed']);
    exit;
}

require_once __DIR__ . '/helpers/time_gate.php';
require_once __DIR__ . '/helpers/store.php';

try {
    $s = storeStatus(db(), new DateTimeImmutable('now', _bakeryTz()));

    echo json_encode([
        'open'             => $s['open'],
        'closed_reason'    => $s['closed_reason'],
        'next_open'        => $s['reopens_at'],
        'reopens_at'       => $s['reopens_at'],
        'closes_at'        => $s['closes_at'],
        'window_id'        => $s['window_id'],
        'items_remaining'  => $s['items_remaining'],
        'orders_remaining' => $s['orders_remaining'],
        'force_closed'     => $s['closed_reason'] === 'force_closed',
        'message'          => $s['message'],
        'timezone'         => $_ENV['BAKERY_TIMEZONE'] ?? 'America/New_York',
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('[bachata-bakery] status.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['open' => false, 'error' => 'server_error']);
}
