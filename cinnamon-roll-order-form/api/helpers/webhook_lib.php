<?php
/**
 * api/helpers/webhook_lib.php
 *
 * Square sends payment.created / payment.updated; the payment's status
 * decides whether an order is confirmed or released.
 */

declare(strict_types=1);

/** Returns 'completed', 'failed', or null (ignore). */
function routeSquareEvent(array $event): ?string
{
    if (!in_array($event['type'] ?? '', ['payment.created', 'payment.updated'], true)) {
        return null;
    }

    return match ($event['data']['object']['payment']['status'] ?? '') {
        'COMPLETED'          => 'completed',
        'FAILED', 'CANCELED' => 'failed',
        default              => null,
    };
}
