<?php
/**
 * setup/preflight.php
 *
 * One-time server health check for Bachata Bakery.
 * Upload to production, visit it ONCE in a browser, then DELETE it.
 *
 * DO NOT leave this file on the server — it leaks configuration details.
 *
 * Visit: https://yourdomain.com/setup/preflight.php
 */

declare(strict_types=1);

// ─── Bootstrap ───────────────────────────────────────────────────────────────

// Load .env parser from the API layer
$rootDir = dirname(__DIR__);
require_once $rootDir . '/api/helpers/db.php';

@load_env();   // suppress warnings — we'll report missing vars below

// ─── Check helpers ────────────────────────────────────────────────────────────

$results = [];   // ['label' => string, 'ok' => bool, 'detail' => string]

function check(string $label, bool $ok, string $detail = ''): void
{
    global $results;
    $results[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
}

// ─── 1. PHP version ───────────────────────────────────────────────────────────

$phpVer = PHP_VERSION;
check(
    'PHP version ≥ 8.0',
    version_compare($phpVer, '8.0.0', '>='),
    "Running PHP {$phpVer}"
);

// ─── 2. Required extensions ───────────────────────────────────────────────────

$required = ['pdo', 'pdo_mysql', 'curl', 'openssl', 'mbstring', 'json'];
foreach ($required as $ext) {
    check(
        "Extension: {$ext}",
        extension_loaded($ext),
        extension_loaded($ext) ? 'loaded' : 'MISSING — install via php.ini or contact host'
    );
}

// ─── 3. .env file exists and is readable ──────────────────────────────────────

$envPath = $rootDir . '/.env';
$envExists = file_exists($envPath) && is_readable($envPath);
check('.env file', $envExists, $envExists ? $envPath : 'File not found or not readable');

// ─── 4. Required .env variables ───────────────────────────────────────────────

$requiredVars = [
    'APP_ENV'               => ['sandbox', 'production'],
    'SQUARE_APP_ID'         => null,
    'SQUARE_LOCATION_ID'    => null,
    'SQUARE_ACCESS_TOKEN'   => null,
    'SQUARE_WEBHOOK_SIG_KEY'=> null,
    'WEBHOOK_URL'           => null,
    'DB_HOST'               => null,
    'DB_NAME'               => null,
    'DB_USER'               => null,
    'DB_PASS'               => null,
    'SMTP_HOST'             => null,
    'SMTP_USER'             => null,
    'SMTP_PASS'             => null,
    'SMTP_PORT'             => null,
    'OWNER_EMAIL'           => null,
    'BAKERY_TIMEZONE'       => null,
    'ADMIN_PASS'            => null,
];

foreach ($requiredVars as $key => $allowedValues) {
    $val = $_ENV[$key] ?? '';
    $set = $val !== '';

    if ($set && $allowedValues !== null) {
        $valid = in_array($val, $allowedValues, true);
        check(
            ".env: {$key}",
            $valid,
            $valid ? "= \"{$val}\"" : "= \"{$val}\" — must be one of: " . implode(', ', $allowedValues)
        );
    } else {
        $detail = $set ? '(set)' : 'NOT SET';
        check(".env: {$key}", $set, $detail);
    }
}

// Extra: warn if APP_ENV is still 'sandbox' on a production-looking domain
$appEnv = $_ENV['APP_ENV'] ?? '';
$host   = $_SERVER['HTTP_HOST'] ?? '';
if ($appEnv === 'sandbox' && !str_contains($host, 'localhost') && !str_contains($host, '127.0.0')) {
    check(
        'APP_ENV production check',
        false,
        "APP_ENV=sandbox but host is \"{$host}\" — change to 'production' before going live"
    );
} elseif ($appEnv === 'production') {
    check('APP_ENV production check', true, 'APP_ENV=production — Square live mode active');
}

// ─── 5. Database connectivity ────────────────────────────────────────────────

try {
    $pdo = db();
    check('DB connection', true, 'Connected to ' . ($_ENV['DB_NAME'] ?? '?') . '@' . ($_ENV['DB_HOST'] ?? '?'));
} catch (Throwable $e) {
    $pdo = null;
    check('DB connection', false, 'PDO error: ' . $e->getMessage());
}

// ─── 6. Required tables exist ────────────────────────────────────────────────

if ($pdo !== null) {
    foreach (['orders', 'order_caps'] as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
            $exists = $stmt->rowCount() > 0;
            check("DB table: {$table}", $exists, $exists ? 'exists' : 'MISSING — run schema.sql');
        } catch (Throwable $e) {
            check("DB table: {$table}", false, $e->getMessage());
        }
    }
} else {
    check('DB table: orders', false, 'Skipped — no DB connection');
    check('DB table: order_caps', false, 'Skipped — no DB connection');
}

// ─── 7. PHPMailer source files ───────────────────────────────────────────────

$mailerFiles = [
    'vendor/phpmailer/phpmailer/src/PHPMailer.php',
    'vendor/phpmailer/phpmailer/src/SMTP.php',
    'vendor/phpmailer/phpmailer/src/Exception.php',
];
foreach ($mailerFiles as $rel) {
    $abs    = $rootDir . '/' . $rel;
    $exists = file_exists($abs);
    check("PHPMailer: {$rel}", $exists, $exists ? 'found' : 'MISSING — upload the phpmailer/src/ directory');
}

// ─── 8. Email template files ─────────────────────────────────────────────────

$emailFiles = ['emails/confirmation.html', 'emails/owner-alert.html'];
foreach ($emailFiles as $rel) {
    $abs    = $rootDir . '/' . $rel;
    $exists = file_exists($abs);
    check("Email template: {$rel}", $exists, $exists ? 'found' : 'MISSING');
}

// ─── 9. setup/ directory protection (this file shouldn't be web-accessible after go-live) ──

check(
    'REMINDER: delete setup/preflight.php',
    false,   // always warn — this is a reminder, not a real pass/fail
    'Delete this file before going live. It exposes server configuration.'
);

// ─── Render ───────────────────────────────────────────────────────────────────

$allPassed = true;
foreach ($results as $r) {
    // The delete-reminder is intentionally false; don't let it fail the overall pass
    if ($r['label'] !== 'REMINDER: delete setup/preflight.php' && !$r['ok']) {
        $allPassed = false;
        break;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Preflight Check — Bachata Bakery</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'DM Sans', system-ui, sans-serif;
      background: #F5EFE6;
      padding: 40px 20px;
      color: #3B1A08;
    }
    .container { max-width: 760px; margin: 0 auto; }
    h1 {
      font-family: Georgia, serif;
      font-size: 26px;
      margin-bottom: 6px;
    }
    .subtitle { font-size: 13px; color: #7B5C3E; margin-bottom: 32px; }
    .banner {
      padding: 16px 20px;
      border-radius: 10px;
      font-weight: 700;
      font-size: 15px;
      margin-bottom: 28px;
    }
    .banner.pass { background: #D4F0EC; color: #0D6B62; border: 1.5px solid #1A9E8F; }
    .banner.fail { background: #FDF0EF; color: #C02020; border: 1.5px solid #E52521; }
    table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(59,26,8,0.08); }
    th {
      text-align: left;
      padding: 12px 16px;
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      background: #3B1A08;
      color: #F5EFE6;
    }
    td { padding: 11px 16px; font-size: 13px; border-bottom: 1px solid #EDE4D6; vertical-align: top; }
    tr:last-child td { border-bottom: none; }
    .status { font-weight: 700; white-space: nowrap; }
    .ok   { color: #1A9E8F; }
    .fail { color: #E52521; }
    .warn { color: #E07B00; }
    .detail { color: #5C4030; font-size: 12px; font-family: monospace; }
    .footer { margin-top: 24px; font-size: 12px; color: #7B5C3E; text-align: center; }
  </style>
</head>
<body>
  <div class="container">
    <h1>Bachata Bakery — Preflight Check</h1>
    <p class="subtitle">Run once after uploading to production, then delete this file.</p>

    <div class="banner <?= $allPassed ? 'pass' : 'fail' ?>">
      <?= $allPassed
          ? '✓ All checks passed — server is ready. Delete this file now.'
          : '✗ One or more checks failed — fix the issues above before going live.' ?>
    </div>

    <table>
      <thead>
        <tr>
          <th>Check</th>
          <th>Status</th>
          <th>Detail</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($results as $r): ?>
          <?php
            $isReminder = $r['label'] === 'REMINDER: delete setup/preflight.php';
            $statusClass = $isReminder ? 'warn' : ($r['ok'] ? 'ok' : 'fail');
            $statusText  = $isReminder ? '⚠ ACTION REQUIRED' : ($r['ok'] ? '✓ PASS' : '✗ FAIL');
          ?>
          <tr>
            <td><?= htmlspecialchars($r['label'], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="status <?= $statusClass ?>"><?= $statusText ?></td>
            <td class="detail"><?= htmlspecialchars($r['detail'], ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <p class="footer">Delete <code>setup/preflight.php</code> immediately after reviewing these results.</p>
  </div>
</body>
</html>
