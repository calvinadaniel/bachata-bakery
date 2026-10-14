<?php
/**
 * api/helpers/checkout_lib.php
 *
 * Validation and Square request-body builders for checkout.php.
 * Pure functions: no .env, database, or network access.
 *
 * Public API:
 *   validateCheckout(mixed $body, DateTimeImmutable $now): array{errors: string[], order: ?array}
 *   makeReferenceId(DateTimeImmutable $now): string
 *   buildOrderBody(array $order, string $locationId, string $ref): array
 *   buildPaymentBody(array $order, string $locationId, string $ref, string $squareOrderId): array
 */

declare(strict_types=1);

require_once __DIR__ . '/catalog.php';

const UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

/**
 * Returns ['errors' => [], 'order' => [...]] on success, or
 * ['errors' => ['…', …], 'order' => null] when anything is invalid.
 *
 * order = [
 *   'token', 'key',
 *   'contact' => ['first', 'last', 'email', 'phone' (E.164)],
 *   'pickup'  => ['id', 'label', 'date', 'date_label', 'time', 'pickup_at'],
 *   'lines'   => [['id', 'name', 'qty', 'unit_cents', 'line_cents'], …],
 *   'total_cents',
 * ]
 */
function validateCheckout(mixed $body, DateTimeImmutable $now): array
{
    if (!is_array($body)) {
        return ['errors' => ['Request body must be a JSON object.'], 'order' => null];
    }

    $errors = [];

    $token = $body['token'] ?? null;
    if (!is_string($token) || $token === '' || strlen($token) > 512) {
        $errors[] = 'Payment token is missing.';
    }

    $key = $body['idempotency_key'] ?? null;
    if (!is_string($key) || preg_match(UUID_V4_PATTERN, $key) !== 1) {
        $errors[] = 'Invalid request key. Please refresh and try again.';
    }

    [$contact, $contactErrors] = validateContact($body['contact'] ?? null);
    [$pickup, $pickupErrors]   = validatePickup($body['pickup'] ?? null, $now);
    [$lines, $itemErrors]      = validateItems($body['items'] ?? null);
    array_push($errors, ...$contactErrors, ...$pickupErrors, ...$itemErrors);

    $total = array_sum(array_column($lines, 'line_cents'));
    if ($itemErrors === [] && ($body['amount_cents'] ?? null) !== $total) {
        $errors[] = 'Order total does not match. Please refresh and try again.';
    }

    if ($errors !== []) {
        return ['errors' => $errors, 'order' => null];
    }

    return [
        'errors' => [],
        'order'  => [
            'token'       => $token,
            'key'         => strtolower($key),
            'contact'     => $contact,
            'pickup'      => $pickup,
            'lines'       => $lines,
            'total_cents' => $total,
        ],
    ];
}

function validateContact(mixed $c): array
{
    if (!is_array($c)) {
        return [null, ['Contact information is required.']];
    }

    $errors = [];
    $first  = trim((string) ($c['first'] ?? ''));
    $last   = trim((string) ($c['last'] ?? ''));
    $email  = strtolower(trim((string) ($c['email'] ?? '')));
    $digits = preg_replace('/\D+/', '', (string) ($c['phone'] ?? ''));

    if ($first === '' || mb_strlen($first) > 60) {
        $errors[] = 'First name is required (max 60 characters).';
    }
    if ($last === '' || mb_strlen($last) > 60) {
        $errors[] = 'Last name is required (max 60 characters).';
    }
    if ($email === '' || strlen($email) > 180 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }

    if (strlen($digits) === 11 && $digits[0] === '1') {
        $digits = substr($digits, 1);
    }
    if (strlen($digits) !== 10) {
        $errors[] = 'Please enter a 10-digit US phone number.';
    }

    return [
        ['first' => $first, 'last' => $last, 'email' => $email, 'phone' => '+1' . $digits],
        $errors,
    ];
}

function validatePickup(mixed $p, DateTimeImmutable $now): array
{
    if (!is_array($p)) {
        return [null, ['Please choose a pickup location and time.']];
    }

    $options = [];
    foreach (pickupOptions($now) as $opt) {
        $options[$opt['id']] = $opt;
    }

    $opt = $options[$p['location'] ?? ''] ?? null;
    if ($opt === null) {
        return [null, ['Please choose a pickup location.']];
    }

    $time = $p['time'] ?? '';
    if (!in_array($time, $opt['slots'], true)) {
        return [null, ['Please choose a pickup time within ' . $opt['label'] . ' pickup hours.']];
    }

    $pickupAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i', "{$opt['date']} {$time}", $now->getTimezone());

    return [
        [
            'id'         => $opt['id'],
            'label'      => $opt['label'],
            'date'       => $opt['date'],
            'date_label' => $opt['date_label'],
            'time'       => $time,
            'time_label' => $pickupAt->format('g:i A'),
            'pickup_at'  => $pickupAt->format(DATE_RFC3339),
        ],
        [],
    ];
}

function validateItems(mixed $items): array
{
    $catalog = catalogById();

    if (!is_array($items) || $items === [] || count($items) > count($catalog)) {
        return [[], ['Please add at least one item to your order.']];
    }

    $lines = [];
    foreach ($items as $entry) {
        $id  = is_array($entry) ? ($entry['id'] ?? null) : null;
        $qty = is_array($entry) ? ($entry['qty'] ?? null) : null;

        if (!is_string($id) || !isset($catalog[$id])) {
            return [[], ['One of the items in your order is no longer available. Please refresh.']];
        }
        if (isset($lines[$id])) {
            return [[], ['Each item can only appear once in an order.']];
        }
        if (!is_int($qty) || $qty < 1 || $qty > MAX_QTY_PER_ITEM) {
            return [[], ['Quantities must be whole numbers from 1 to ' . MAX_QTY_PER_ITEM . '.']];
        }

        $unit = $catalog[$id]['price_cents'];
        $lines[$id] = [
            'id'         => $id,
            'name'       => $catalog[$id]['name'],
            'qty'        => $qty,
            'unit_cents' => $unit,
            'line_cents' => $unit * $qty,
        ];
    }

    return [array_values($lines), []];
}

/** BB-YYYYMMDD-XXXX — short, human-readable, fits Square's 40-char reference_id. */
function makeReferenceId(DateTimeImmutable $now): string
{
    return 'BB-' . $now->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

function buildOrderBody(array $order, string $locationId, string $ref): array
{
    $c = $order['contact'];
    $p = $order['pickup'];

    $lineItems = array_map(
        static fn(array $line) => [
            'name'             => $line['name'],
            'quantity'         => (string) $line['qty'],
            'base_price_money' => ['amount' => $line['unit_cents'], 'currency' => 'USD'],
        ],
        $order['lines']
    );

    return [
        'idempotency_key' => $order['key'] . '-order',
        'order'           => [
            'location_id'  => $locationId,
            'reference_id' => $ref,
            'line_items'   => $lineItems,
            'fulfillments' => [[
                'type'           => 'PICKUP',
                'state'          => 'PROPOSED',
                'pickup_details' => [
                    'recipient'     => [
                        'display_name'  => "{$c['first']} {$c['last']}",
                        'email_address' => $c['email'],
                        'phone_number'  => $c['phone'],
                    ],
                    'schedule_type' => 'SCHEDULED',
                    'pickup_at'     => $p['pickup_at'],
                    'note'          => "Pickup at {$p['label']}",
                ],
            ]],
        ],
    ];
}

function buildPaymentBody(array $order, string $locationId, string $ref, string $squareOrderId): array
{
    $c = $order['contact'];
    $p = $order['pickup'];

    $note = sprintf(
        'Pickup: %s, %s %s — %s %s, %s',
        $p['label'],
        $p['date_label'],
        $p['time_label'],
        $c['first'],
        $c['last'],
        $c['phone']
    );

    return [
        'idempotency_key'     => $order['key'] . '-pay',
        'source_id'           => $order['token'],
        'amount_money'        => ['amount' => $order['total_cents'], 'currency' => 'USD'],
        'order_id'            => $squareOrderId,
        'location_id'         => $locationId,
        'reference_id'        => $ref,
        'buyer_email_address' => $c['email'],
        'note'                => mb_substr($note, 0, 500),
        'autocomplete'        => true,
    ];
}
