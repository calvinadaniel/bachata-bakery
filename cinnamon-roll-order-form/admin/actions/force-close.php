<?php
/**
 * admin/actions/force-close.php
 *
 * POST — Toggle force_closed for a window.
 * If no cap row exists yet, creates one before toggling.
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

$pdo = db();
ensureCapRowExists($pdo, $windowId);

$pdo->prepare('UPDATE order_caps SET force_closed = 1 - force_closed WHERE window_id = ?')
    ->execute([$windowId]);

backToDashboard($windowId, 'force_close_updated');
