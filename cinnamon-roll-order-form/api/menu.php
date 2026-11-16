<?php
/**
 * api/menu.php
 *
 * GET — Weekly preorder menu, prices, pickup options, and store status
 * (open / closed reason / items remaining) for the native order form.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

require_once __DIR__ . '/helpers/time_gate.php';
require_once __DIR__ . '/helpers/catalog.php';
require_once __DIR__ . '/helpers/store.php';

try {
    $now    = new DateTimeImmutable('now', _bakeryTz());
    $status = storeStatus(db(), $now);

    echo json_encode(array_merge(menuPayload($now), [
        'open'            => $status['open'],
        'closed_reason'   => $status['closed_reason'],
        'message'         => $status['message'],
        'reopens_at'      => $status['reopens_at'],
        'items_remaining' => $status['items_remaining'],
    ]), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[bachata-bakery] menu.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}
