<?php
/**
 * admin/login.php
 *
 * Single-owner login. Password is stored as ADMIN_PASS in .env.
 * After three failed attempts, the form locks for 15 minutes (session-based).
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// Already authenticated — go straight to dashboard
if (!empty($_SESSION['bb_admin_authed'])) {
    header('Location: dashboard.php');
    exit;
}

require_once dirname(__DIR__) . '/api/helpers/db.php';
require_once dirname(__DIR__) . '/api/helpers/store.php';
load_env();

// Lockout is per IP and stored in the DB, so clearing cookies doesn't reset it.
const LOGIN_MAX_FAILS     = 5;
const LOGIN_LOCK_MINUTES  = 15;
const ADMIN_PASS_MIN_PROD = 16;

$error  = '';
$pdo    = db();
$ipHash = clientIpHash();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM checkout_attempts
      WHERE ip_hash = ? AND outcome = 'admin_fail' AND created_at >= NOW() - INTERVAL " . LOGIN_LOCK_MINUTES . ' MINUTE'
);
$stmt->execute([$ipHash]);
$fails     = (int) $stmt->fetchColumn();
$lockedOut = $fails >= LOGIN_MAX_FAILS;

$adminPass = $_ENV['ADMIN_PASS'] ?? '';
$weakPass  = ($_ENV['APP_ENV'] ?? 'sandbox') === 'production'
          && (strlen($adminPass) < ADMIN_PASS_MIN_PROD || $adminPass === 'changeme');

if ($weakPass) {
    $error = 'Admin login is disabled: set a strong ADMIN_PASS (16+ characters) in .env.';
} elseif ($lockedOut) {
    $error = 'Too many failed attempts. Try again in ' . LOGIN_LOCK_MINUTES . ' minutes.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$lockedOut && !$weakPass) {
    $submitted = $_POST['password'] ?? '';

    $valid = $adminPass !== ''
          && is_string($submitted)
          && hash_equals($adminPass, $submitted);

    if ($valid) {
        session_regenerate_id(true);
        $_SESSION['bb_admin_authed'] = true;
        $_SESSION['csrf_token']      = bin2hex(random_bytes(32));
        header('Location: dashboard.php');
        exit;
    }

    recordAttempt($pdo, $ipHash, 'admin_fail');
    $remaining = LOGIN_MAX_FAILS - $fails - 1;

    $error = $remaining > 0
        ? "Incorrect password. {$remaining} attempt(s) remaining."
        : 'Too many failed attempts. Try again in ' . LOGIN_LOCK_MINUTES . ' minutes.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — Bachata Bakery</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'DM Sans', system-ui, sans-serif;
      background: #EDE4D6;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(59,26,8,0.12);
      overflow: hidden;
      width: 100%;
      max-width: 380px;
    }
    .card-header {
      background: #3B1A08;
      padding: 28px 32px;
      text-align: center;
    }
    .card-header h1 {
      font-family: Georgia, serif;
      font-size: 22px;
      color: #F5EFE6;
      margin-bottom: 4px;
    }
    .card-header p { font-size: 12px; color: #F4A228; font-style: italic; }
    .card-body { padding: 32px; }
    .label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: #3B1A08;
      margin-bottom: 8px;
    }
    .input {
      display: block;
      width: 100%;
      padding: 11px 14px;
      font-family: inherit;
      font-size: 15px;
      color: #3B1A08;
      background: #fff;
      border: 1.5px solid #D9CEBF;
      border-radius: 8px;
      outline: none;
      transition: border-color 0.15s;
    }
    .input:focus { border-color: #1A9E8F; box-shadow: 0 0 0 3px rgba(26,158,143,0.15); }
    .btn {
      display: block;
      width: 100%;
      margin-top: 20px;
      padding: 13px;
      background: #1A9E8F;
      color: #fff;
      font-family: inherit;
      font-size: 15px;
      font-weight: 600;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: background 0.15s;
    }
    .btn:hover { background: #157E73; }
    .error {
      margin-bottom: 20px;
      padding: 11px 14px;
      background: #FDF0EF;
      border: 1.5px solid rgba(229,37,33,0.25);
      border-radius: 8px;
      font-size: 13px;
      color: #E52521;
      font-weight: 500;
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="card-header">
      <h1>Bachata Bakery</h1>
      <p>Owner Dashboard</p>
    </div>
    <div class="card-body">
      <?php if ($error !== ''): ?>
        <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>
      <form method="POST" autocomplete="off">
        <label class="label" for="password">Password</label>
        <input class="input" type="password" id="password" name="password"
               autofocus autocomplete="current-password" required>
        <button class="btn" type="submit">Sign in</button>
      </form>
    </div>
  </div>
</body>
</html>
