<?php
/**
 * api/helpers/time_gate.php
 *
 * Time-gate logic for the Friday–Sunday order window.
 * All times are evaluated in the BAKERY_TIMEZONE from .env.
 *
 * Public API:
 *   isFormOpen(): bool
 *   getNextOpenTime(): DateTimeImmutable   — next Friday midnight
 *   getCurrentWindowId(): string           — Friday date (Y-m-d) of the active window
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
 * Open window: Friday 12:00:00 AM through Sunday 11:59:59 PM (bakery timezone).
 */
function isFormOpen(): bool
{
    $now = new DateTimeImmutable('now', _bakeryTz());
    $dow = (int) $now->format('N'); // ISO: 1=Mon … 5=Fri, 6=Sat, 7=Sun
    return $dow >= 5;
}

/**
 * Returns the DateTimeImmutable for the next Friday at 00:00:00.
 *
 * - If currently closed (Mon–Thu): this coming Friday.
 * - If currently open  (Fri–Sun):  next week's Friday (for the countdown
 *   shown when all slots sell out mid-weekend).
 */
function getNextOpenTime(): DateTimeImmutable
{
    $now = new DateTimeImmutable('now', _bakeryTz());
    $dow = (int) $now->format('N');

    if ($dow < 5) {
        // Mon(1)–Thu(4): days until this Friday
        $daysUntil = 5 - $dow;
    } else {
        // Fri(5)–Sun(7): days until NEXT Friday
        // Fri→7, Sat→6, Sun→5
        $daysUntil = 12 - $dow;
    }

    return $now->modify("+{$daysUntil} days")->setTime(0, 0, 0);
}

/**
 * Returns the DATE string (Y-m-d) of the Friday that opened the current
 * or upcoming window. Used as window_id in the DB.
 *
 * - Fri: today.
 * - Sat: yesterday.
 * - Sun: two days ago.
 * - Mon–Thu: the upcoming Friday (for reference — no active window yet).
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
