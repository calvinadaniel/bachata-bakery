<?php
/**
 * admin/actions/cap-override.php
 *
 * POST — Update rolls_max and/or orders_max for the active window.
 * Values are clamped: rolls 1–500, orders 1–200.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/window.php';
require_once dirname(__DIR__, 2) . '/api/helpers/db.php';
require_once dirname(__DIR__, 2) . '/api/helpers/cap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/dashboard.php');
    exit;
}

verifyCsrf();

$rollsMax  = max(1, min(500, (int) ($_POST['rolls_max']  ?? 100)));
$ordersMax = max(1, min(200, (int) ($_POST['orders_max'] ?? 50)));

$pdo      = db();
$windowId = activeWindowId($pdo);

ensureCapRowExists($pdo, $windowId);

$pdo->prepare(
    'UPDATE order_caps SET rolls_max = ?, orders_max = ? WHERE window_id = ?'
)->execute([$rollsMax, $ordersMax, $windowId]);

header('Location: /admin/dashboard.php?msg=caps_updated');
exit;
