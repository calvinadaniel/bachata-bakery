<?php
/**
 * admin/window.php
 *
 * Which order window the admin is looking at. A window is identified by the
 * Wednesday that closes the Sun–Wed order week (see orderWeekWednesday()).
 *
 *   defaultAdminWindow(DateTimeImmutable $today): string
 *       Thu–Sun → the window whose pickups are happening now (last Wednesday).
 *       Mon–Wed → the window currently taking orders.
 *   adminWindowOptions(PDO $pdo, DateTimeImmutable $today): string[]  newest first
 *   requestedWindowId(mixed $value): string
 *       A valid window id (a Wednesday, Y-m-d) from GET/POST, else the default.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/helpers/time_gate.php';
require_once dirname(__DIR__) . '/api/helpers/catalog.php';

function defaultAdminWindow(DateTimeImmutable $today): string
{
    $dow = (int) $today->format('N');
    $wednesday = $dow >= 4
        ? $today->setTime(0, 0)->modify('-' . ($dow - 3) . ' days')
        : orderWeekWednesday($today);
    return $wednesday->format('Y-m-d');
}

/** @return string[] */
function adminWindowOptions(PDO $pdo, DateTimeImmutable $today): array
{
    $ids = $pdo->query(
        'SELECT window_id FROM order_caps UNION SELECT DISTINCT window_id FROM orders'
    )->fetchAll(PDO::FETCH_COLUMN);
    $ids[] = defaultAdminWindow($today);
    $ids[] = orderWeekWednesday($today)->format('Y-m-d');

    $ids = array_values(array_unique(array_filter(array_map('strval', $ids), 'isWindowId')));
    rsort($ids);
    return $ids;
}

function isWindowId(string $value): bool
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return false;
    }
    return (new DateTimeImmutable($value))->format('N') === '3';
}

function requestedWindowId(mixed $value): string
{
    return is_string($value) && isWindowId($value)
        ? $value
        : defaultAdminWindow(new DateTimeImmutable('now', _bakeryTz()));
}
