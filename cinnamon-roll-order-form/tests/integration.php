<?php
/**
 * tests/integration.php — end-to-end checkout scenarios against a running
 * local server, the local database, the Square sandbox, and hCaptcha test keys.
 *
 *   php -S localhost:8000 -t .            (repo root, in another terminal)
 *   php cinnamon-roll-order-form/tests/integration.php --wipe-local-db [base-url]
 *
 * WIPES orders, order_caps, store_settings and checkout_attempts. Refuses to
 * run unless APP_ENV=sandbox and DB_HOST is 127.0.0.1 or localhost.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || !in_array('--wipe-local-db', $argv, true)) {
    fwrite(STDERR, "CLI only. Pass --wipe-local-db to confirm the local DB may be wiped.\n");
    exit(2);
}

require_once __DIR__ . '/../api/helpers/db.php';
require_once __DIR__ . '/../api/helpers/catalog.php';

load_env();
if (($_ENV['APP_ENV'] ?? '') !== 'sandbox' || !in_array($_ENV['DB_HOST'] ?? '', ['127.0.0.1', 'localhost'], true)) {
    fwrite(STDERR, "Refusing: needs APP_ENV=sandbox and a local DB_HOST.\n");
    exit(2);
}

$base = 'http://localhost:8000/cinnamon-roll-order-form/api';
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, 'http')) {
        $base = rtrim($arg, '/');
    }
}

const CAPTCHA_OK = '10000000-aaaa-bbbb-cccc-000000000001';

$pdo  = db();
$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? '  ok   ' : '  FAIL ') . $label . ($ok || $detail === '' ? '' : " — {$detail}") . "\n";
}

function http(string $method, string $url, ?string $body = null, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 60,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw  = (string) curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, json_decode($raw, true) ?? ['raw' => $raw]];
}

function uuid4(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

/** @param array<string,int> $items */
function checkout(array $items, string $token = 'cnon:card-nonce-ok', ?string $captcha = CAPTCHA_OK, ?int $amount = null): array
{
    global $base;
    $catalog = catalogById();
    $total   = 0;
    $lines   = [];
    foreach ($items as $id => $qty) {
        $total  += $catalog[$id]['price_cents'] * $qty;
        $lines[] = ['id' => $id, 'qty' => $qty];
    }
    $body = [
        'token'           => $token,
        'idempotency_key' => uuid4(),
        'contact'         => ['first' => 'Test', 'last' => 'Integration', 'email' => 'test@example.com', 'phone' => '(302) 555-0142'],
        'pickup'          => ['location' => 'westchester', 'time' => '10:30'],
        'items'           => $lines,
        'amount_cents'    => $amount ?? $total,
    ];
    if ($captcha !== null) {
        $body['captcha_token'] = $captcha;
    }
    return http('POST', "{$base}/checkout.php", json_encode($body), ['Content-Type: application/json']);
}

function caps(string $windowId): array
{
    global $pdo;
    $s = $pdo->prepare('SELECT rolls_sold, orders_placed, rolls_max, orders_max, force_closed FROM order_caps WHERE window_id = ?');
    $s->execute([$windowId]);
    return array_map('intval', $s->fetch() ?: ['rolls_sold' => 0, 'orders_placed' => 0, 'rolls_max' => 100, 'orders_max' => 50, 'force_closed' => 0]);
}

function resetAttempts(): void
{
    global $pdo;
    $pdo->exec('DELETE FROM checkout_attempts');
}

foreach (['orders', 'order_caps', 'store_settings', 'checkout_attempts'] as $t) {
    $pdo->exec("DELETE FROM {$t}");
}

[$code, $status] = http('GET', "{$base}/status.php");
if (!($status['open'] ?? false)) {
    fwrite(STDERR, "The order window is closed right now ({$status['closed_reason']}); run during Sun–Wed.\n");
    exit(1);
}
$win = $status['window_id'];
echo "Window {$win}\n";

echo "\nSuccessful checkout\n";
[$code, $res] = checkout(['roll-clasico' => 2, 'tl-guava' => 1]);
check('200 + success', $code === 200 && ($res['success'] ?? false) === true, json_encode($res));
$c = caps($win);
check('3 items + 1 order reserved', $c['rolls_sold'] === 3 && $c['orders_placed'] === 1, json_encode($c));
$row = $pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 1")->fetch();
check('order row paid with Square ids', $row['payment_status'] === 'paid' && $row['square_order_id'] && $row['square_payment_id'], json_encode($row));
check('order row has items + pickup', $row['quantity'] == 3 && $row['pickup_location'] !== '' && str_contains($row['items_json'], 'roll-clasico'));
$paidRef = $row['order_ref'];
$paidPaymentId = $row['square_payment_id'];

echo "\nDeclined card releases the reservation\n";
resetAttempts();
[$code, $res] = checkout(['roll-dulce' => 4], 'cnon:card-nonce-declined');
check('402 card_declined', $code === 402 && ($res['error_code'] ?? '') === 'card_declined', json_encode($res));
$c = caps($win);
check('caps unchanged (3 / 1)', $c['rolls_sold'] === 3 && $c['orders_placed'] === 1, json_encode($c));
$row = $pdo->query("SELECT payment_status FROM orders ORDER BY id DESC LIMIT 1")->fetch();
check('declined order row marked failed', $row['payment_status'] === 'failed');

echo "\nTampered amount\n";
resetAttempts();
[$code, $res] = checkout(['roll-cafe' => 1], amount: 100);
check('422 invalid_input', $code === 422 && ($res['error_code'] ?? '') === 'invalid_input', json_encode($res));

echo "\nhCaptcha\n";
resetAttempts();
[$code, $res] = checkout(['roll-cafe' => 1], captcha: null);
check('missing token → 422 captcha_failed', $code === 422 && ($res['error_code'] ?? '') === 'captcha_failed', json_encode($res));
[$code, $res] = checkout(['roll-cafe' => 1], captcha: 'not-a-real-token');
check('bad token → 422 captcha_failed', $code === 422 && ($res['error_code'] ?? '') === 'captcha_failed', json_encode($res));

echo "\nWeekly sell-out limit\n";
resetAttempts();
$pdo->prepare('UPDATE order_caps SET rolls_max = 4 WHERE window_id = ?')->execute([$win]);
[$code, $res] = checkout(['roll-clasico' => 2]);
check('over the limit → 409 "Only 1 item left"', $code === 409 && str_contains($res['message'] ?? '', 'Only 1 item'), json_encode($res));
[$code, $res] = checkout(['roll-clasico' => 1]);
check('last item sells', $code === 200, json_encode($res));
[, $status] = http('GET', "{$base}/status.php");
check('status → sold_out', ($status['open'] ?? true) === false && $status['closed_reason'] === 'sold_out', json_encode($status));
[, $menu] = http('GET', "{$base}/menu.php");
check('menu → closed, 0 remaining', ($menu['open'] ?? true) === false && $menu['items_remaining'] === 0);
[$code, $res] = checkout(['roll-clasico' => 1]);
check('next checkout → 409 sold_out', $code === 409 && ($res['error_code'] ?? '') === 'sold_out', json_encode($res));
$pdo->prepare('UPDATE order_caps SET rolls_max = 100 WHERE window_id = ?')->execute([$win]);

echo "\nForce closed\n";
resetAttempts();
$pdo->prepare('UPDATE order_caps SET force_closed = 1 WHERE window_id = ?')->execute([$win]);
[$code, $res] = checkout(['roll-clasico' => 1]);
check('403 form_closed', $code === 403 && ($res['error_code'] ?? '') === 'form_closed', json_encode($res));
$pdo->prepare('UPDATE order_caps SET force_closed = 0 WHERE window_id = ?')->execute([$win]);

echo "\nVacation pause\n";
resetAttempts();
$until = (new DateTimeImmutable('now', new DateTimeZone($_ENV['BAKERY_TIMEZONE'] ?? 'America/New_York')))->modify('+10 days')->format('Y-m-d');
$pdo->prepare("INSERT INTO store_settings (setting_key, setting_value) VALUES ('paused_until', ?), ('pause_message', 'Back soon!')")->execute([$until]);
[, $status] = http('GET', "{$base}/status.php");
check('status → vacation with message', $status['closed_reason'] === 'vacation' && $status['message'] === 'Back soon!', json_encode($status));
check('reopens_at after the pause', substr($status['reopens_at'], 0, 10) >= $until, $status['reopens_at']);
[$code, $res] = checkout(['roll-clasico' => 1]);
check('403 with the pause message', $code === 403 && ($res['message'] ?? '') === 'Back soon!', json_encode($res));
$pdo->exec('DELETE FROM store_settings');

echo "\nRate limiting\n";
resetAttempts();
$max = (int) ($_ENV['RATE_LIMIT_ATTEMPTS'] ?? 8);
for ($i = 0; $i < $max; $i++) {
    checkout(['roll-cafe' => 1], captcha: null);
}
[$code, $res] = checkout(['roll-cafe' => 1]);
check("attempt #" . ($max + 1) . " → 429", $code === 429 && ($res['error_code'] ?? '') === 'rate_limited', json_encode($res));
resetAttempts();
for ($i = 0; $i < (int) ($_ENV['RATE_LIMIT_DECLINES'] ?? 3); $i++) {
    checkout(['roll-cafe' => 1], 'cnon:card-nonce-declined');
}
[$code, $res] = checkout(['roll-cafe' => 1]);
check('after repeated declines → 429', $code === 429 && str_contains($res['message'] ?? '', 'declined'), json_encode($res));
resetAttempts();

echo "\nWebhook reconciliation\n";
function webhook(array $event): int
{
    global $base;
    $body = json_encode($event);
    $sig  = base64_encode(hash_hmac('sha256', $_ENV['WEBHOOK_URL'] . $body, $_ENV['SQUARE_WEBHOOK_SIG_KEY'], true));
    [$code] = http('POST', "{$base}/webhook/square.php", $body, ['Content-Type: application/json', "x-square-hmacsha256-signature: {$sig}"]);
    return $code;
}
function paymentEvent(string $type, string $status, string $ref, string $paymentId): array
{
    return ['type' => $type, 'data' => ['object' => ['payment' => ['id' => $paymentId, 'status' => $status, 'reference_id' => $ref]]]];
}

[$code] = http('POST', "{$base}/webhook/square.php", '{}', ['Content-Type: application/json', 'x-square-hmacsha256-signature: bogus']);
check('bad signature → 403', $code === 403);

$before = caps($win);
$pdo->prepare(
    "INSERT INTO orders (order_ref, window_id, customer_name, customer_email, customer_phone, quantity, product_variant,
                         items_json, pickup_date, pickup_location, pickup_time, payment_status, amount_cents)
     VALUES ('BB-TEST-PEND', ?, 'Hook Test', 'h@example.com', '+13025550142', 2, '2× El Clásico', '[]', CURDATE(), 'x', '10:00:00', 'pending', 1600)"
)->execute([$win]);
$pdo->prepare('UPDATE order_caps SET rolls_sold = rolls_sold + 2, orders_placed = orders_placed + 1 WHERE window_id = ?')->execute([$win]);

check('payment.updated FAILED → 200', webhook(paymentEvent('payment.updated', 'FAILED', 'BB-TEST-PEND', 'pay-test-1')) === 200);
$st = $pdo->query("SELECT payment_status FROM orders WHERE order_ref = 'BB-TEST-PEND'")->fetchColumn();
$c  = caps($win);
check('pending order → failed, items released', $st === 'failed' && $c['rolls_sold'] === $before['rolls_sold'], "{$st} " . json_encode($c));

check('payment.updated COMPLETED → 200', webhook(paymentEvent('payment.updated', 'COMPLETED', 'BB-TEST-PEND', 'pay-test-1')) === 200);
$st = $pdo->query("SELECT payment_status FROM orders WHERE order_ref = 'BB-TEST-PEND'")->fetchColumn();
$c  = caps($win);
check('failed order → paid, items re-reserved', $st === 'paid' && $c['rolls_sold'] === $before['rolls_sold'] + 2, "{$st} " . json_encode($c));

check('repeat COMPLETED is a no-op', webhook(paymentEvent('payment.updated', 'COMPLETED', $paidRef, $paidPaymentId)) === 200 && caps($win) === $c);
check('payment.created APPROVED ignored', webhook(paymentEvent('payment.created', 'APPROVED', 'BB-TEST-PEND', 'pay-test-1')) === 200 && caps($win) === $c);

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
