<?php
/**
 * api/notify.php
 *
 * POST — Waitlist / "notify me when orders open" opt-in.
 *
 * Accepts JSON or form-urlencoded:
 *   email (required), phone (optional), source (optional)
 *
 * Currently logs the signup and returns success. Wire to email list /
 * Supabase / ESP when ready — see TODO below.
 *
 * Response: { "ok": true } or { "ok": false, "error": "..." }
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$raw  = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$email  = trim((string) ($data['email'] ?? ''));
$phone  = trim((string) ($data['phone'] ?? ''));
$source = trim((string) ($data['source'] ?? 'homepage'));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'invalid_email']);
    exit;
}

if ($phone !== '' && !preg_match('/^[+\d()\s.-]{7,20}$/', $phone)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'invalid_phone']);
    exit;
}

// TODO: Persist to DB / ESP. For now, append to a local log (Hostinger-safe).
$logDir = dirname(__DIR__) . '/storage';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0750, true);
}
$line = sprintf(
    "%s\temail=%s\tphone=%s\tsource=%s\tip=%s\n",
    (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
    $email,
    $phone !== '' ? $phone : '-',
    preg_replace('/[^\w.-]/', '', $source) ?: 'homepage',
    $_SERVER['REMOTE_ADDR'] ?? '-'
);
@file_put_contents($logDir . '/notify-waitlist.log', $line, FILE_APPEND | LOCK_EX);

error_log('[bachata-bakery] notify signup: ' . $email);

echo json_encode(['ok' => true]);
