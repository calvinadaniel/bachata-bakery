<?php
/**
 * api/menu.php
 *
 * GET — Weekly preorder menu, prices, and pickup options for the native
 * order form. Public data only.
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

try {
    $now = new DateTimeImmutable('now', _bakeryTz());
    echo json_encode(menuPayload($now), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[bachata-bakery] menu.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}
