<?php
/**
 * api/config.php
 *
 * GET — Returns public Square credentials for the frontend SDK.
 * Only APP_ID, LOCATION_ID, and APP_ENV are exposed.
 * SQUARE_ACCESS_TOKEN and SQUARE_WEBHOOK_SIG_KEY never leave the server.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

require_once __DIR__ . '/helpers/db.php';

try {
    load_env();

    $appId      = $_ENV['SQUARE_APP_ID']      ?? '';
    $locationId = $_ENV['SQUARE_LOCATION_ID'] ?? '';
    $appEnv     = $_ENV['APP_ENV']            ?? 'sandbox';

    if ($appId === '' || $locationId === '') {
        throw new RuntimeException('Square credentials not configured.');
    }

    echo json_encode([
        'square_app_id'      => $appId,
        'square_location_id' => $locationId,
        'app_env'            => $appEnv,
    ]);

} catch (Throwable $e) {
    error_log('[bachata-bakery] config.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}
