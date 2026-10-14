<?php
/**
 * tests/run.php — CLI assertions for the native preorder helpers.
 *
 * Usage: php cinnamon-roll-order-form/tests/run.php
 * No network or database access.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/helpers/catalog.php';

$failures = 0;
$passes   = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures, $passes;
    if ($ok) {
        $passes++;
        echo "  ok   {$label}\n";
    } else {
        $failures++;
        echo "  FAIL {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

function at(string $local): DateTimeImmutable
{
    return new DateTimeImmutable($local, new DateTimeZone('America/New_York'));
}

function pickupById(array $options): array
{
    $out = [];
    foreach ($options as $opt) {
        $out[$opt['id']] = $opt;
    }
    return $out;
}

// ---------------------------------------------------------------
echo "catalog\n";
// ---------------------------------------------------------------
$items = catalogItems();
check('has 11 items', count($items) === 11, (string) count($items));
check(
    'prices are $7 or $8',
    array_diff(array_unique(array_column($items, 'price_cents')), [700, 800]) === []
);
check('ids are unique', count(catalogById()) === 11);
check('max qty is 10', MAX_QTY_PER_ITEM === 10);

// ---------------------------------------------------------------
echo "pickup dates\n";
// ---------------------------------------------------------------
$wed = pickupById(pickupOptions(at('2026-10-14 12:00')));
check('Wed → West Chester Sat 10/17', $wed['westchester']['date'] === '2026-10-17', $wed['westchester']['date']);
check('Wed → Middletown Sun 10/18', $wed['middletown']['date'] === '2026-10-18', $wed['middletown']['date']);
check('date label', $wed['westchester']['date_label'] === 'Saturday, Oct 17', $wed['westchester']['date_label']);
check('West Chester slots', $wed['westchester']['slots'] === ['10:00', '10:30', '11:00', '11:30', '12:00', '12:30']);
check('Middletown slots', $wed['middletown']['slots'] === ['13:00', '13:30', '14:00', '14:30', '15:00', '15:30']);

$sun = pickupById(pickupOptions(at('2026-10-11 00:05')));
check('Sun → Sat 10/17', $sun['westchester']['date'] === '2026-10-17', $sun['westchester']['date']);
check('Sun → Sun 10/18', $sun['middletown']['date'] === '2026-10-18', $sun['middletown']['date']);

$thu = pickupById(pickupOptions(at('2026-10-15 09:00')));
check('Thu → next window Sat 10/24', $thu['westchester']['date'] === '2026-10-24', $thu['westchester']['date']);
check('Thu → next window Sun 10/25', $thu['middletown']['date'] === '2026-10-25', $thu['middletown']['date']);

check('open Wed 23:59', isOpenAt(at('2026-10-14 23:59:59')) === true);
check('closed Thu 00:00', isOpenAt(at('2026-10-15 00:00:00')) === false);
check('closed Sat', isOpenAt(at('2026-10-17 12:00')) === false);
check('open Sun 00:00', isOpenAt(at('2026-10-18 00:00:00')) === true);

$menu = menuPayload(at('2026-10-14 12:00'));
check('menu payload keys', array_keys($menu) === ['open', 'max_qty_per_item', 'items', 'pickup']);

// ---------------------------------------------------------------
echo "checkout validation\n";
// ---------------------------------------------------------------
require_once dirname(__DIR__) . '/api/helpers/checkout_lib.php';

$now = at('2026-10-14 12:00');

function validBody(array $override = []): array
{
    return array_replace([
        'token'           => 'cnon:card-nonce-ok',
        'idempotency_key' => '3f2b8c1e-4a5d-4e6f-9a7b-1c2d3e4f5a6b',
        'contact'         => ['first' => 'Ana', 'last' => 'Diaz', 'email' => 'Ana@Example.com', 'phone' => '(302) 555-0100'],
        'pickup'          => ['location' => 'westchester', 'time' => '10:30'],
        'items'           => [['id' => 'roll-clasico', 'qty' => 2], ['id' => 'tl-guava', 'qty' => 1]],
        'amount_cents'    => 2400,
    ], $override);
}

function errorsFor(array $override, DateTimeImmutable $now): array
{
    return validateCheckout(validBody($override), $now)['errors'];
}

$ok = validateCheckout(validBody(), $now);
check('valid body has no errors', $ok['errors'] === [], implode(' | ', $ok['errors']));
check('total is 2400', ($ok['order']['total_cents'] ?? null) === 2400);
check('email lowercased', ($ok['order']['contact']['email'] ?? '') === 'ana@example.com');
check('two lines', count($ok['order']['lines'] ?? []) === 2);
check('line name from catalog', ($ok['order']['lines'][0]['name'] ?? '') === 'El Clásico');
check(
    'pickup_at is RFC 3339 in bakery tz',
    ($ok['order']['pickup']['pickup_at'] ?? '') === '2026-10-17T10:30:00-04:00',
    $ok['order']['pickup']['pickup_at'] ?? ''
);

check('non-array body rejected', validateCheckout('nope', $now)['errors'] !== []);
check('wrong amount rejected', errorsFor(['amount_cents' => 1600], $now) !== []);
check('string amount rejected', errorsFor(['amount_cents' => '2400'], $now) !== []);
check('missing token rejected', errorsFor(['token' => ''], $now) !== []);
check('bad idempotency key rejected', errorsFor(['idempotency_key' => 'abc'], $now) !== []);
check('unknown item rejected', errorsFor(['items' => [['id' => 'roll-fake', 'qty' => 1]], 'amount_cents' => 800], $now) !== []);
check('qty 0 rejected', errorsFor(['items' => [['id' => 'roll-clasico', 'qty' => 0]], 'amount_cents' => 0], $now) !== []);
check('qty 11 rejected', errorsFor(['items' => [['id' => 'roll-clasico', 'qty' => 11]], 'amount_cents' => 8800], $now) !== []);
check('string qty rejected', errorsFor(['items' => [['id' => 'roll-clasico', 'qty' => '2']], 'amount_cents' => 1600], $now) !== []);
check('empty items rejected', errorsFor(['items' => [], 'amount_cents' => 0], $now) !== []);
check(
    'duplicate item rejected',
    errorsFor(['items' => [['id' => 'roll-clasico', 'qty' => 1], ['id' => 'roll-clasico', 'qty' => 1]], 'amount_cents' => 1600], $now) !== []
);
check('unknown location rejected', errorsFor(['pickup' => ['location' => 'dover', 'time' => '10:30']], $now) !== []);
check('slot outside hours rejected', errorsFor(['pickup' => ['location' => 'middletown', 'time' => '10:30']], $now) !== []);
check('bad email rejected', errorsFor(['contact' => ['first' => 'A', 'last' => 'D', 'email' => 'nope', 'phone' => '3025550100']], $now) !== []);
check('bad phone rejected', errorsFor(['contact' => ['first' => 'A', 'last' => 'D', 'email' => 'a@b.co', 'phone' => 'call me']], $now) !== []);
check('missing first name rejected', errorsFor(['contact' => ['first' => ' ', 'last' => 'D', 'email' => 'a@b.co', 'phone' => '3025550100']], $now) !== []);

// ---------------------------------------------------------------
echo "square request bodies\n";
// ---------------------------------------------------------------
$order = $ok['order'];
$ref   = makeReferenceId($now);
check('reference id format', preg_match('/^BB-20261014-[0-9A-F]{4}$/', $ref) === 1, $ref);

$orderBody = buildOrderBody($order, 'LOC123', $ref);
$o = $orderBody['order'];
check('order idempotency key', $orderBody['idempotency_key'] === $order['key'] . '-order');
check('order location', $o['location_id'] === 'LOC123');
check('order reference', $o['reference_id'] === $ref);
check('line quantity is a string', $o['line_items'][0]['quantity'] === '2');
check('line price from catalog', $o['line_items'][0]['base_price_money'] === ['amount' => 800, 'currency' => 'USD']);
check('pickup fulfillment', $o['fulfillments'][0]['type'] === 'PICKUP');
check('pickup_at on fulfillment', $o['fulfillments'][0]['pickup_details']['pickup_at'] === '2026-10-17T10:30:00-04:00');
check('recipient email', $o['fulfillments'][0]['pickup_details']['recipient']['email_address'] === 'ana@example.com');

$payBody = buildPaymentBody($order, 'LOC123', $ref, 'SQ_ORDER_1');
check('payment idempotency key', $payBody['idempotency_key'] === $order['key'] . '-pay');
check('payment source', $payBody['source_id'] === 'cnon:card-nonce-ok');
check('payment amount', $payBody['amount_money'] === ['amount' => 2400, 'currency' => 'USD']);
check('payment order id', $payBody['order_id'] === 'SQ_ORDER_1');
check('payment note ≤ 500', strlen($payBody['note']) <= 500 && str_contains($payBody['note'], 'West Chester'));

// ---------------------------------------------------------------
echo "\n{$passes} passed, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
