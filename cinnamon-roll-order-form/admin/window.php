<?php
/**
 * admin/window.php
 *
 * Shared helper for admin action files.
 * Defines activeWindowId() — returns the window_id the admin should
 * operate on: the most recent cap row, or getCurrentWindowId() if none exist.
 *
 * Include this file instead of requiring another action file.
 */

declare(strict_types=1);

function activeWindowId(PDO $pdo): string
{
    require_once dirname(__DIR__) . '/api/helpers/time_gate.php';

    $stmt = $pdo->query('SELECT window_id FROM order_caps ORDER BY window_id DESC LIMIT 1');
    $row  = $stmt->fetchColumn();

    return $row !== false ? (string) $row : getCurrentWindowId();
}
