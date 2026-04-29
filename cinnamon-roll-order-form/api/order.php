<?php
/**
 * api/order.php
 *
 * POST — Submit a cinnamon roll order.
 *
 * Flow:
 *   1. Validate input
 *   2. Server-side time gate check
 *   3. BEGIN TRANSACTION
 *      a. SELECT order_caps FOR UPDATE  ← serialises concurrent submissions
 *      b. Dual cap check (rolls + orders)
 *      c. INSERT order (payment_status = 'pending')
 *      d. Pre-increment order_caps counters
 *   4. COMMIT  ← lock released; slot is reserved
 *   5. Charge Square
 *   6a. Charge OK  → UPDATE order to 'paid'  → return success
 *   6b. Charge fail → UPDATE order to 'failed' + compensate counters → return error
 *
 * The short DB transaction (step 3) means the FOR UPDATE lock is held only
 * for local reads/writes — never during the Square HTTP call.
 *
 * Error codes returned to client:
 *   form_closed | sold_out_rolls | sold_out_orders | card_declined | invalid_input | server_error
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// ---------------------------------------------------------------
// Constants
// ---------------------------------------------------------------
const ROLL_PRICE_CENTS  = 600;   // $6.00 — must match form.js ROLL_PRICE_CENTS
const MAX_QTY_PER_ORDER = 12;
const ALLOWED_VARIANTS  = ['Classic Glazed', 'Cream Cheese Frosted', 'Brown Butter Pecan'];
const SQUARE_API_SANDBOX    = 'https://connect.squareupsandbox.com/v2';
const SQUARE_API_PRODUCTION = 'https://connect.squareup.com/v2';
const SQUARE_API_VERSION    = '2024-01-18';

// ---------------------------------------------------------------
// Gate: POST + JSON only
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bail(405, 'method_not_allowed', 'Method not allowed.');
}
if (!str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    bail(415, 'invalid_input', 'Content-Type must be application/json.');
}

require_once __DIR__ . '/helpers/db.php';
require_once __DIR__ . '/helpers/time_gate.php';
require_once __DIR__ . '/helpers/cap.php';

// ---------------------------------------------------------------
// Main
// ---------------------------------------------------------------
try {
    // 1. Parse body
    $body = json_decode(
        file_get_contents('php://input'),
        associative: true,
        flags: JSON_THROW_ON_ERROR
    );

    // 2. Validate input — all checks before any DB or network work
    $errors = validateInput($body);
    if ($errors) {
        bail(422, 'invalid_input', implode(' ', $errors));
    }

    // Normalise validated fields
    $nonce       = trim($body['nonce']);
    $name        = trim($body['name']);
    $email       = strtolower(trim($body['email']));
    $phone       = trim($body['phone'] ?? '');
    $quantity    = (int) $body['quantity'];
    $variant     = trim($body['variant']);
    $pickupDate  = trim($body['pickup_date']);
    $notes       = trim($body['notes'] ?? '');
    $amountCents = (int) $body['amount_cents'];

    // 3. Server-side time gate (re-checked here even if frontend already checked)
    if (!isFormOpen()) {
        bail(403, 'form_closed', 'Orders are not open right now. Check back Friday!');
    }

    $windowId = getCurrentWindowId();

    // 3b. Pickup date must be the Saturday or Sunday of THIS window.
    //     validateInput() already confirmed it is a Sat/Sun — here we confirm
    //     it is specifically this window's Sat/Sun, not a different weekend.
    $windowFriday  = new DateTimeImmutable($windowId);
    $allowedDates  = [
        $windowFriday->modify('+1 day')->format('Y-m-d'),  // Saturday
        $windowFriday->modify('+2 days')->format('Y-m-d'), // Sunday
    ];
    if (!in_array($pickupDate, $allowedDates, true)) {
        bail(422, 'invalid_input', 'Pickup date must be within the current order window.');
    }

    $pdo = db();

    // 4. Ensure a lockable cap row exists for this window
    ensureCapRowExists($pdo, $windowId);

    // 5. Generate order identifiers before entering the transaction
    $orderRef       = generateOrderRef($pdo);
    $idempotencyKey = uniqid('bb_', true);

    // 6. Atomic cap check + tentative order insert
    $orderId = reserveOrderSlot($pdo, $windowId, $orderRef, $quantity, [
        'name'        => $name,
        'email'       => $email,
        'phone'       => $phone,
        'variant'     => $variant,
        'pickup_date' => $pickupDate,
        'notes'       => $notes,
        'amount_cents'=> $amountCents,
    ]);

    // 7. Charge Square — outside any DB transaction so the lock isn't held
    $chargeResult = chargeSquare($nonce, $amountCents, $orderRef, $idempotencyKey);

    if ($chargeResult['success']) {
        // 8a. Payment succeeded — mark order paid
        finaliseOrder($pdo, $orderId, 'paid', $chargeResult['payment_id']);

        // 8b. Send confirmation emails — failure is logged but never blocks the response
        dispatchOrderEmails($pdo, $windowId, [
            'order_ref'   => $orderRef,
            'window_id'   => $windowId,
            'name'        => $name,
            'email'       => $email,
            'phone'       => $phone,
            'quantity'    => $quantity,
            'variant'     => $variant,
            'pickup_date' => $pickupDate,
            'notes'       => $notes,
            'amount_cents'=> $amountCents,
        ]);

        echo json_encode([
            'success'   => true,
            'order_ref' => $orderRef,
            'message'   => 'Order placed! Check your email for confirmation.',
        ]);

    } else {
        // 8b. Payment failed — mark order failed, compensate counters
        failOrder($pdo, $orderId, $windowId, $quantity);

        bail(402, 'card_declined', $chargeResult['message']);
    }

} catch (JsonException) {
    bail(400, 'invalid_input', 'Invalid JSON body.');
} catch (Throwable $e) {
    error_log('[bachata-bakery] order.php unhandled: ' . $e->getMessage());
    bail(500, 'server_error', 'Something went wrong. Please try again in a moment.');
}

// ---------------------------------------------------------------
// reserveOrderSlot()
//
// Single transaction:
//   - Locks the cap row (FOR UPDATE)
//   - Validates both caps against the requested quantity
//   - Inserts the order as 'pending'
//   - Pre-increments order_caps counters
//
// Returns the new order's auto-increment id on success.
// Calls bail() and exits on cap failure — never returns on error.
// ---------------------------------------------------------------
function reserveOrderSlot(
    PDO    $pdo,
    string $windowId,
    string $orderRef,
    int    $quantity,
    array  $fields
): int {
    $pdo->beginTransaction();

    try {
        // Lock this window's cap row — concurrent requests queue here
        $capStmt = $pdo->prepare(
            'SELECT rolls_sold, orders_placed, rolls_max, orders_max, force_closed
               FROM order_caps
              WHERE window_id = ?
                FOR UPDATE'
        );
        $capStmt->execute([$windowId]);
        $cap = $capStmt->fetch();

        // Guard: row should always exist (ensureCapRowExists ran before us)
        if ($cap === false) {
            $pdo->rollBack();
            error_log('[bachata-bakery] order_caps row missing after ensureCapRowExists');
            bail(500, 'server_error', 'Something went wrong. Please try again.');
        }

        // Force-closed check
        if ((int) $cap['force_closed'] === 1) {
            $pdo->rollBack();
            bail(403, 'form_closed', 'Orders are closed for this weekend.');
        }

        // Orders cap check
        if ((int) $cap['orders_placed'] + 1 > (int) $cap['orders_max']) {
            $pdo->rollBack();
            bail(409, 'sold_out_orders', 'All order slots are filled for this weekend!');
        }

        // Rolls cap check (handles partial-fill edge case: 94 rolls + order of 7 = rejected)
        if ((int) $cap['rolls_sold'] + $quantity > (int) $cap['rolls_max']) {
            $pdo->rollBack();
            bail(409, 'sold_out_rolls', 'Not enough rolls remaining. Try a smaller quantity.');
        }

        // Caps clear — insert order as 'pending'
        $pdo->prepare(
            'INSERT INTO orders
               (order_ref, window_id, customer_name, customer_email, customer_phone,
                quantity, product_variant, pickup_date, special_notes,
                payment_status, amount_cents)
             VALUES
               (:ref, :win, :name, :email, :phone,
                :qty, :variant, :pickup, :notes,
                \'pending\', :cents)'
        )->execute([
            ':ref'     => $orderRef,
            ':win'     => $windowId,
            ':name'    => $fields['name'],
            ':email'   => $fields['email'],
            ':phone'   => $fields['phone'],
            ':qty'     => $quantity,
            ':variant' => $fields['variant'],
            ':pickup'  => $fields['pickup_date'],
            ':notes'   => $fields['notes'],
            ':cents'   => $fields['amount_cents'],
        ]);

        $orderId = (int) $pdo->lastInsertId();

        // Pre-increment counters — after this commit, the slot is reserved.
        // Other requests will see the incremented totals and be rejected if at cap.
        $pdo->prepare(
            'UPDATE order_caps
                SET rolls_sold    = rolls_sold    + :qty,
                    orders_placed = orders_placed + 1
              WHERE window_id = :win'
        )->execute([':qty' => $quantity, ':win' => $windowId]);

        $pdo->commit();

        return $orderId;

    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ---------------------------------------------------------------
// finaliseOrder()
// Called after a successful Square charge.
// ---------------------------------------------------------------
function finaliseOrder(PDO $pdo, int $orderId, string $status, string $paymentId): void
{
    try {
        $pdo->beginTransaction();
        $pdo->prepare(
            'UPDATE orders SET payment_status = :status, square_payment_id = :pid WHERE id = :id'
        )->execute([':status' => $status, ':pid' => $paymentId, ':id' => $orderId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        // Payment succeeded but DB update failed.
        // The Square webhook (Phase 6) will reconcile this via payment.completed.
        error_log(
            "[bachata-bakery] CRITICAL: payment {$paymentId} succeeded but " .
            "order #{$orderId} status update failed — webhook should reconcile. " .
            $e->getMessage()
        );
    }
}

// ---------------------------------------------------------------
// failOrder()
// Called after a failed Square charge.
// Marks the order 'failed' and compensates the pre-incremented counters.
// ---------------------------------------------------------------
function failOrder(PDO $pdo, int $orderId, string $windowId, int $quantity): void
{
    try {
        $pdo->beginTransaction();

        $pdo->prepare(
            'UPDATE orders SET payment_status = \'failed\' WHERE id = ?'
        )->execute([$orderId]);

        // Decrement counters back — the slot is now available again
        $pdo->prepare(
            'UPDATE order_caps
                SET rolls_sold    = GREATEST(0, rolls_sold    - :qty),
                    orders_placed = GREATEST(0, orders_placed - 1)
              WHERE window_id = :win'
        )->execute([':qty' => $quantity, ':win' => $windowId]);

        $pdo->commit();

    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log(
            "[bachata-bakery] CRITICAL: cap compensation failed for order #{$orderId}. " .
            "Manual counter correction may be needed. " . $e->getMessage()
        );
    }
}

// ---------------------------------------------------------------
// chargeSquare()
// POST to Square Payments API. Returns ['success' => bool, ...].
// ---------------------------------------------------------------
function chargeSquare(
    string $nonce,
    int    $amountCents,
    string $orderRef,
    string $idempotencyKey
): array {
    load_env();

    $accessToken = $_ENV['SQUARE_ACCESS_TOKEN'] ?? '';
    $locationId  = $_ENV['SQUARE_LOCATION_ID']  ?? '';
    $appEnv      = $_ENV['APP_ENV']             ?? 'sandbox';

    if ($accessToken === '' || $locationId === '') {
        error_log('[bachata-bakery] Square credentials not configured in .env');
        return ['success' => false, 'message' => 'Payment service not configured.'];
    }

    $apiBase = ($appEnv === 'production') ? SQUARE_API_PRODUCTION : SQUARE_API_SANDBOX;

    $payload = json_encode([
        'idempotency_key' => $idempotencyKey,
        'source_id'       => $nonce,
        'amount_money'    => ['amount' => $amountCents, 'currency' => 'USD'],
        'location_id'     => $locationId,
        'note'            => "Bachata Bakery – {$orderRef}",
        'reference_id'    => $orderRef,
    ]);

    $ch = curl_init("{$apiBase}/payments");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Square-Version: ' . SQUARE_API_VERSION,
            "Authorization: Bearer {$accessToken}",
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr !== '') {
        error_log("[bachata-bakery] Square cURL error for {$orderRef}: {$curlErr}");
        return ['success' => false, 'message' => 'Payment service unavailable. Please try again.'];
    }

    $data = json_decode($response, true) ?? [];

    if ($httpCode === 200 && isset($data['payment']['id'])) {
        return ['success' => true, 'payment_id' => $data['payment']['id']];
    }

    // Translate Square error codes into user-friendly messages
    $code   = $data['errors'][0]['code']   ?? '';
    $detail = $data['errors'][0]['detail'] ?? '';

    error_log("[bachata-bakery] Square charge failed for {$orderRef} (HTTP {$httpCode}): {$code} — {$detail}");

    $message = match ($code) {
        'CARD_DECLINED',
        'CARD_DECLINED_CALL_ISSUER',
        'CARD_DECLINED_VERIFICATION_REQUIRED'
                         => 'Your card was declined. Please try a different card.',
        'INVALID_CARD'   => 'Card information is invalid. Please re-enter your card.',
        'EXPIRED_CARD'   => 'Your card has expired.',
        'CVV_FAILURE'    => 'Incorrect CVV. Please check and try again.',
        'ADDRESS_VERIFICATION_FAILURE'
                         => 'Billing ZIP code did not match. Please check and try again.',
        'INSUFFICIENT_FUNDS'
                         => 'Insufficient funds. Please try a different card.',
        default          => 'Card declined. Please try a different card or contact your bank.',
    };

    return ['success' => false, 'message' => $message];
}

// ---------------------------------------------------------------
// dispatchOrderEmails()
// Non-fatal wrapper — logs failures so they never bubble up to the customer.
// ---------------------------------------------------------------
function dispatchOrderEmails(PDO $pdo, string $windowId, array $order): void
{
    try {
        require_once __DIR__ . '/helpers/mailer.php';
        require_once __DIR__ . '/helpers/cap.php';
        $cap = getCapRow($pdo, $windowId);
        sendCustomerConfirmation($order);
        sendOwnerAlert($order, $cap);
    } catch (Throwable $e) {
        error_log(
            '[bachata-bakery] Email dispatch failed for ' .
            ($order['order_ref'] ?? '?') . ': ' . $e->getMessage()
        );
    }
}

// ---------------------------------------------------------------
// generateOrderRef()
// Format: BB-YYYYMMDD-NNNN (sequence resets each calendar day)
// ---------------------------------------------------------------
function generateOrderRef(PDO $pdo): string
{
    $date = (new DateTimeImmutable('now', new DateTimeZone('America/New_York')))->format('Ymd');

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE order_ref LIKE ?");
    $stmt->execute(["BB-{$date}-%"]);
    $seq = (int) $stmt->fetchColumn() + 1;

    return sprintf('BB-%s-%04d', $date, $seq);
}

// ---------------------------------------------------------------
// validateInput()
// Returns array of error strings (empty = valid).
// ---------------------------------------------------------------
function validateInput(mixed $body): array
{
    if (!is_array($body)) {
        return ['Request body must be a JSON object.'];
    }

    $errors = [];

    // nonce
    if (empty($body['nonce']) || !is_string($body['nonce'])) {
        $errors[] = 'Payment token is missing.';
    }

    // name
    $name = trim($body['name'] ?? '');
    if ($name === '' || strlen($name) > 120) {
        $errors[] = 'Full name is required (max 120 characters).';
    }

    // email
    $email = trim($body['email'] ?? '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 180) {
        $errors[] = 'A valid email address is required.';
    }

    // phone (optional)
    if (isset($body['phone']) && strlen(trim((string) $body['phone'])) > 20) {
        $errors[] = 'Phone number is too long (max 20 characters).';
    }

    // quantity
    $qty = $body['quantity'] ?? null;
    if (!is_int($qty) || $qty < 1 || $qty > MAX_QTY_PER_ORDER) {
        $errors[] = 'Quantity must be a whole number between 1 and ' . MAX_QTY_PER_ORDER . '.';
    }

    // variant
    $variant = trim($body['variant'] ?? '');
    if (!in_array($variant, ALLOWED_VARIANTS, true)) {
        $errors[] = 'Invalid variety selected.';
    }

    // pickup_date
    $pickup = trim($body['pickup_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $pickup)) {
        $errors[] = 'A valid pickup date is required.';
    } else {
        [$y, $m, $d] = array_map('intval', explode('-', $pickup));
        if (!checkdate($m, $d, $y)) {
            $errors[] = 'Pickup date is not a real calendar date.';
        } else {
            // Must be Saturday (6) or Sunday (0) in the current window
            $pickupDt = new DateTimeImmutable($pickup);
            $dow      = (int) $pickupDt->format('w');
            if ($dow !== 6 && $dow !== 0) {
                $errors[] = 'Pickup date must be a Saturday or Sunday.';
            }
        }
    }

    // notes (optional)
    if (isset($body['notes']) && strlen(trim((string) $body['notes'])) > 500) {
        $errors[] = 'Special requests must be 500 characters or fewer.';
    }

    // amount_cents — server recomputes the expected amount to prevent tampering
    $safeQty       = (is_int($qty) && $qty >= 1 && $qty <= MAX_QTY_PER_ORDER) ? $qty : 0;
    $expectedCents = $safeQty * ROLL_PRICE_CENTS;
    $submitted     = $body['amount_cents'] ?? null;

    if (!is_int($submitted) || $submitted !== $expectedCents) {
        $errors[] = 'Order amount does not match. Please refresh and try again.';
    }

    return $errors;
}

// ---------------------------------------------------------------
// bail() — emit a JSON error response and exit
// ---------------------------------------------------------------
function bail(int $httpCode, string $errorCode, string $message): never
{
    http_response_code($httpCode);
    echo json_encode(['success' => false, 'error_code' => $errorCode, 'message' => $message]);
    exit;
}
