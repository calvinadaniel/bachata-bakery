<?php
/**
 * admin/actions/cap-override.php
 *
 * POST — Update the weekly sell-out limits for a window.
 * rolls_max is the max total items (any product); orders_max the max orders.
 * Values are clamped: items 1–500, orders 1–200.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/window.php';
require_once dirname(__DIR__, 2) . '/api/helpers/db.php';
require_once dirname(__DIR__, 2) . '/api/helpers/cap.php';

$windowId = requestedWindowId($_POST['window_id'] ?? null);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    backToDashboard($windowId, '');
}

verifyCsrf();

$rollsMax  = max(1, min(500, (int) ($_POST['rolls_max']  ?? 100)));
$ordersMax = max(1, min(200, (int) ($_POST['orders_max'] ?? 50)));

$pdo = db();
ensureCapRowExists($pdo, $windowId);

$pdo->prepare(
    'UPDATE order_caps SET rolls_max = ?, orders_max = ? WHERE window_id = ?'
)->execute([$rollsMax, $ordersMax, $windowId]);

backToDashboard($windowId, 'caps_updated');
