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
echo "\n{$passes} passed, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
