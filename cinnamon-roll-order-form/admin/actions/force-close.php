<?php
/**
 * admin/actions/force-close.php
 *
 * POST — Toggle force_closed for the active window.
 * If no cap row exists yet, creates one before toggling.
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

$pdo      = db();
$windowId = activeWindowId($pdo);

ensureCapRowExists($pdo, $windowId);

$stmt = $pdo->prepare('SELECT force_closed FROM order_caps WHERE window_id = ?');
$stmt->execute([$windowId]);
$current = (int) ($stmt->fetchColumn() ?? 0);

$pdo->prepare('UPDATE order_caps SET force_closed = ? WHERE window_id = ?')
    ->execute([$current === 1 ? 0 : 1, $windowId]);

header('Location: /admin/dashboard.php?msg=force_close_updated');
exit;
