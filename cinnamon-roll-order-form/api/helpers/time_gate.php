<?php
/**
 * api/helpers/time_gate.php
 *
 * Weekly order-window helpers. Orders are accepted Sunday 12:00 AM through
 * Wednesday 11:59:59 PM in the bakery timezone (BAKERY_TIMEZONE from .env,
 * default America/New_York). Capacity still rolls on a Friday-keyed window_id.
 *
 * Public API:
 *   isFormOpen(): bool
 *   getClosesAt(): DateTimeImmutable      — Wed 23:59:59 of the active order week
 *   getNextOpenTime(): DateTimeImmutable  — next Sunday 00:00:00
 *   getCurrentWindowId(): string          — Friday date (Y-m-d) of the active window
 *
 * Requires db.php to have been loaded first (ensures .env is loaded).
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function _bakeryTz(): DateTimeZone
{
    static $tz = null;
    if ($tz === null) {
        load_env();
        $tz = new DateTimeZone($_ENV['BAKERY_TIMEZONE'] ?? 'America/New_York');
    }
    return $tz;
}

/**
 * Is the order form open right now?
 * Open: Sunday (N=7) through Wednesday (N=3), inclusive, full calendar days.
 * Closed: Thursday–Saturday.
 */
function isFormOpen(): bool
{
    $now = new DateTimeImmutable('now', _bakeryTz());
    $dow = (int) $now->format('N'); // 1=Mon … 7=Sun

    return $dow === 7 || $dow <= 3;
}

/**
 * Wednesday 11:59:59 PM of the current (or most recent) order week.
 * Used by status.php so the homepage can say when the window closes.
 */
function getClosesAt(): DateTimeImmutable
{
    $now = new DateTimeImmutable('now', _bakeryTz());
    $dow = (int) $now->format('N'); // 1=Mon … 7=Sun

    // Days from today to this week's Wednesday
    $daysToWed = match (true) {
        $dow === 7 => 3,          // Sun → Wed
        $dow <= 3  => 3 - $dow,   // Mon–Wed
        default    => 3 - $dow,   // Thu–Sat → previous Wed (negative)
    };

    return $now->modify(($daysToWed >= 0 ? '+' : '') . $daysToWed . ' days')
               ->setTime(23, 59, 59);
}

/**
 * Next Sunday 00:00:00 when the order window reopens.
 * When currently open (Sun–Wed), returns the following Sunday.
 * When closed (Thu–Sat), returns the upcoming Sunday.
 */
function getNextOpenTime(): DateTimeImmutable
{
    $now = new DateTimeImmutable('now', _bakeryTz());
    $dow = (int) $now->format('N'); // 1=Mon … 7=Sun

    if ($dow === 7) {
        // Already Sunday — next open is next week
        return $now->modify('+7 days')->setTime(0, 0, 0);
    }

    // Mon–Sat: upcoming Sunday
    $daysUntilSunday = 7 - $dow;
    return $now->modify("+{$daysUntilSunday} days")->setTime(0, 0, 0);
}

/**
 * Returns the DATE string (Y-m-d) of the Friday that keys the current
 * weekly capacity window. Used as window_id in the DB.
 *
 * - Fri: today.
 * - Sat: yesterday.
 * - Sun: two days ago.
 * - Mon–Thu: the upcoming Friday (orders count toward that weekend's window).
 */
function getCurrentWindowId(): string
{
    $now = new DateTimeImmutable('now', _bakeryTz());
    $dow = (int) $now->format('N');

    return match (true) {
        $dow === 5 => $now->format('Y-m-d'),
        $dow === 6 => $now->modify('-1 day')->format('Y-m-d'),
        $dow === 7 => $now->modify('-2 days')->format('Y-m-d'),
        default    => $now->modify('+' . (5 - $dow) . ' days')->format('Y-m-d'),
    };
}
