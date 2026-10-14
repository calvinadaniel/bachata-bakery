<?php
/**
 * api/helpers/catalog.php
 *
 * Weekly preorder menu and pickup rules for the native order form.
 * This file is the only source of prices — checkout.php recomputes every
 * total from it. Pure functions: no .env, database, or network access.
 *
 * Public API:
 *   MAX_QTY_PER_ITEM
 *   catalogItems(): array
 *   catalogById(): array
 *   pickupLocations(): array
 *   pickupOptions(DateTimeImmutable $now): array
 *   isOpenAt(DateTimeImmutable $now): bool
 *   menuPayload(DateTimeImmutable $now): array
 */

declare(strict_types=1);

const MAX_QTY_PER_ITEM = 10;

function catalogItems(): array
{
    return [
        ['id' => 'cookie-preset',     'category' => 'Preset Sugar Cookies', 'name' => 'Sugar Cookie',               'price_cents' => 700, 'description' => 'Preset cookie design, heat sealed for freshness. Designs change periodically.'],
        ['id' => 'cookie-gift',       'category' => 'Preset Sugar Cookies', 'name' => 'Sugar Cookie with Gift Tag', 'price_cents' => 800, 'description' => 'Same preset cookie with a personalized gift tag and premium packaging.'],
        ['id' => 'roll-clasico',      'category' => 'Cinnamon Rolls',       'name' => 'El Clásico',                 'price_cents' => 800, 'description' => 'A classic cinnamon roll with cream cheese icing.'],
        ['id' => 'roll-dominicana',   'category' => 'Cinnamon Rolls',       'name' => 'La Dominicana',              'price_cents' => 800, 'description' => 'Guava-filled cinnamon roll topped with guava cream cheese icing.'],
        ['id' => 'roll-cafe',         'category' => 'Cinnamon Rolls',       'name' => 'Café con Leche',             'price_cents' => 800, 'description' => 'Espresso-infused cinnamon roll topped with creamy coffee cream cheese frosting.'],
        ['id' => 'roll-dulce',        'category' => 'Cinnamon Rolls',       'name' => 'Dulce',                      'price_cents' => 800, 'description' => 'Dulce de leche cinnamon roll finished with dulce icing and a touch of sea salt.'],
        ['id' => 'roll-choco-banana', 'category' => 'Cinnamon Rolls',       'name' => 'Choco Banana',               'price_cents' => 800, 'description' => 'Banana-filled cinnamon roll with chocolate drizzle and chocolate cream cheese icing.'],
        ['id' => 'tl-classic',        'category' => 'Tres Leches Cakes',    'name' => 'Classic Tres Leches',        'price_cents' => 800, 'description' => 'Soaked in three milks, topped with whipped cream, a dusting of cinnamon, and a cherry.'],
        ['id' => 'tl-guava',          'category' => 'Tres Leches Cakes',    'name' => 'Guava Tres Leches',          'price_cents' => 800, 'description' => 'Guava-infused tres leches with whipped cream and a drizzle of guava sauce.'],
        ['id' => 'tl-coffee',         'category' => 'Tres Leches Cakes',    'name' => 'Coffee Tres Leches',         'price_cents' => 800, 'description' => 'Espresso-soaked tres leches with whipped cream and a dusting of cinnamon espresso.'],
        ['id' => 'tl-passion',        'category' => 'Tres Leches Cakes',    'name' => 'Passion Fruit Tres Leches',  'price_cents' => 800, 'description' => 'Passion fruit–soaked tres leches with whipped cream and a bright passion fruit drizzle.'],
    ];
}

function catalogById(): array
{
    $byId = [];
    foreach (catalogItems() as $item) {
        $byId[$item['id']] = $item;
    }
    return $byId;
}

/**
 * weekday: ISO-8601 day number (6 = Saturday, 7 = Sunday).
 * slots: 24h "HH:MM" start times inside the location's pickup hours.
 */
function pickupLocations(): array
{
    return [
        'middletown' => [
            'id'      => 'middletown',
            'label'   => 'Middletown, DE',
            'weekday' => 7,
            'slots'   => ['13:00', '13:30', '14:00', '14:30', '15:00', '15:30'],
        ],
        'westchester' => [
            'id'      => 'westchester',
            'label'   => 'Artisan Exchange, West Chester, PA',
            'weekday' => 6,
            'slots'   => ['10:00', '10:30', '11:00', '11:30', '12:00', '12:30'],
        ],
    ];
}

/** Orders are accepted Sunday 00:00 through Wednesday 23:59:59. */
function isOpenAt(DateTimeImmutable $now): bool
{
    $dow = (int) $now->format('N');
    return $dow === 7 || $dow <= 3;
}

/**
 * Wednesday that closes the order week $now belongs to. Thursday–Saturday
 * belong to the next week, since the next order a customer can place is then.
 */
function orderWeekWednesday(DateTimeImmutable $now): DateTimeImmutable
{
    $dow  = (int) $now->format('N');
    $days = match (true) {
        $dow === 7 => 3,
        $dow <= 3  => 3 - $dow,
        default    => 10 - $dow,
    };
    return $now->setTime(0, 0)->modify("+{$days} days");
}

/** Pickup date is the Saturday/Sunday right after the order week closes. */
function pickupOptions(DateTimeImmutable $now): array
{
    $wednesday = orderWeekWednesday($now);
    $options   = [];

    foreach (pickupLocations() as $loc) {
        $date = $wednesday->modify('+' . ($loc['weekday'] - 3) . ' days');
        $options[] = [
            'id'         => $loc['id'],
            'label'      => $loc['label'],
            'date'       => $date->format('Y-m-d'),
            'date_label' => $date->format('l, M j'),
            'slots'      => $loc['slots'],
        ];
    }

    return $options;
}

function menuPayload(DateTimeImmutable $now): array
{
    return [
        'open'             => isOpenAt($now),
        'max_qty_per_item' => MAX_QTY_PER_ITEM,
        'items'            => catalogItems(),
        'pickup'           => pickupOptions($now),
    ];
}
