<?php
/**
 * admin/actions/pause.php
 *
 * POST — Vacation / pause switch (applies to every window).
 *   action=pause  paused_until (Y-m-d, today .. +1 year), pause_message (≤ 280 chars, optional)
 *   action=resume clears both
 * Orders stay closed until paused_until; the weekly window then resumes normally.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/window.php';
require_once dirname(__DIR__, 2) . '/api/helpers/db.php';
require_once dirname(__DIR__, 2) . '/api/helpers/store.php';

$windowId = requestedWindowId($_POST['window_id'] ?? null);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    backToDashboard($windowId, '');
}

verifyCsrf();

$pdo = db();

if (($_POST['action'] ?? '') === 'resume') {
    setSetting($pdo, 'paused_until', null);
    setSetting($pdo, 'pause_message', null);
    backToDashboard($windowId, 'resumed');
}

$today = new DateTimeImmutable('today', _bakeryTz());
$until = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($_POST['paused_until'] ?? ''), _bakeryTz());

if ($until === false || $until->format('Y-m-d') !== ($_POST['paused_until'] ?? '')
    || $until <= $today || $until > $today->modify('+1 year')) {
    backToDashboard($windowId, 'pause_invalid');
}

$message = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['pause_message'] ?? '')) ?? '');

setSetting($pdo, 'paused_until', $until->format('Y-m-d'));
setSetting($pdo, 'pause_message', $message === '' ? null : mb_substr($message, 0, 280));

backToDashboard($windowId, 'paused');
