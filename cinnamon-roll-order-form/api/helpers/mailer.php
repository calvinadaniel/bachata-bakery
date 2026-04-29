<?php
/**
 * api/helpers/mailer.php
 *
 * PHPMailer wrapper for Bachata Bakery transactional emails.
 *
 * Installation (one-time, Hostinger):
 *   1. Download PHPMailer from https://github.com/PHPMailer/PHPMailer/releases
 *   2. Unzip and upload the src/ folder to:
 *      vendor/phpmailer/phpmailer/src/
 *
 * Public API:
 *   sendCustomerConfirmation(array $order): void
 *   sendOwnerAlert(array $order, array $cap): void
 *
 * Both functions throw on mailer failure — callers should catch and log.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php'; // ensures load_env() is available

// PHPMailer core files
$_pmBase = dirname(__DIR__, 2) . '/vendor/phpmailer/phpmailer/src/';
require_once $_pmBase . 'Exception.php';
require_once $_pmBase . 'PHPMailer.php';
require_once $_pmBase . 'SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

// ---------------------------------------------------------------
// Public functions
// ---------------------------------------------------------------

/**
 * Send the order confirmation email to the customer.
 *
 * @param array $order {order_ref, name, email, quantity, variant,
 *                      pickup_date, notes, amount_cents}
 * @throws MailException|\RuntimeException
 */
function sendCustomerConfirmation(array $order): void
{
    $mail = buildMailer();
    $mail->addAddress($order['email'], $order['name']);
    $mail->Subject = "Your Bachata Bakery order is confirmed — {$order['order_ref']}";
    $mail->Body    = renderTemplate(
        dirname(__DIR__, 2) . '/emails/confirmation.html',
        confirmationTokens($order)
    );
    $mail->AltBody = confirmationPlainText($order);
    $mail->send();
}

/**
 * Send a new-order notification to the bakery owner.
 *
 * @param array $order {order_ref, window_id, name, email, phone,
 *                      quantity, variant, pickup_date, notes, amount_cents}
 * @param array $cap   {rolls_sold, orders_placed, rolls_max, orders_max}
 * @throws MailException|\RuntimeException
 */
function sendOwnerAlert(array $order, array $cap): void
{
    load_env();

    $ownerEmail = $_ENV['OWNER_EMAIL'] ?? '';
    if ($ownerEmail === '') {
        error_log('[bachata-bakery] OWNER_EMAIL not set — skipping owner alert');
        return;
    }

    $revenue = weekendRevenue(db(), $order['window_id']);

    $mail = buildMailer();
    $mail->addAddress($ownerEmail, 'Bachata Bakery');
    $mail->Subject = "New order — {$order['order_ref']} · {$order['quantity']} roll(s)";
    $mail->Body    = renderTemplate(
        dirname(__DIR__, 2) . '/emails/owner-alert.html',
        ownerAlertTokens($order, $cap, $revenue)
    );
    $mail->AltBody = ownerAlertPlainText($order, $cap, $revenue);
    $mail->send();
}

// ---------------------------------------------------------------
// Private helpers
// ---------------------------------------------------------------

/** Configure and return a ready-to-use PHPMailer instance. */
function buildMailer(): PHPMailer
{
    load_env();

    $mail             = new PHPMailer(exceptions: true);
    $mail->isSMTP();
    $mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.hostinger.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['SMTP_USER'] ?? '';
    $mail->Password   = $_ENV['SMTP_PASS'] ?? '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL — port 465
    $mail->Port       = (int) ($_ENV['SMTP_PORT'] ?? 465);
    $mail->CharSet    = PHPMailer::CHARSET_UTF8;
    $mail->isHTML(true);

    $fromAddr = $_ENV['SMTP_USER'] ?? '';
    $mail->setFrom($fromAddr, 'Bachata Bakery');
    $mail->addReplyTo($fromAddr, 'Bachata Bakery');

    return $mail;
}

/**
 * Load an HTML template and replace {{TOKEN}} placeholders with values.
 * Token values are NOT escaped here — callers must escape user content
 * before passing it in (see confirmationTokens / ownerAlertTokens).
 */
function renderTemplate(string $path, array $tokens): string
{
    if (!is_file($path)) {
        throw new RuntimeException("Email template not found: {$path}");
    }

    $html = (string) file_get_contents($path);

    foreach ($tokens as $key => $value) {
        $html = str_replace("{{$key}}", (string) $value, $html);
    }

    return $html;
}

/** Query total paid revenue for the current window. */
function weekendRevenue(PDO $pdo, string $windowId): int
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount_cents), 0)
           FROM orders
          WHERE window_id = ? AND payment_status = 'paid'"
    );
    $stmt->execute([$windowId]);
    return (int) $stmt->fetchColumn();
}

// ---------------------------------------------------------------
// Token builders — user-supplied strings are escaped with esc()
// ---------------------------------------------------------------

function confirmationTokens(array $o): array
{
    $notesBlock = ($o['notes'] ?? '') !== ''
        ? '<tr>
             <td style="padding:8px 0;color:#7A5C44;font-size:13px;font-weight:600;
                        text-transform:uppercase;letter-spacing:0.06em;">
               Special requests
             </td>
             <td style="padding:8px 0;color:#3B1A08;font-size:15px;text-align:right;">
               ' . esc($o['notes']) . '
             </td>
           </tr>'
        : '';

    return [
        'ORDER_REF'    => esc($o['order_ref']),
        'CUSTOMER_NAME'=> esc($o['name']),
        'QUANTITY'     => (string) (int) $o['quantity'],
        'VARIANT'      => esc($o['variant']),
        'PICKUP_DATE'  => fmtDate($o['pickup_date']),
        'AMOUNT'       => fmtMoney($o['amount_cents']),
        'NOTES_BLOCK'  => $notesBlock,
        'YEAR'         => date('Y'),
    ];
}

function ownerAlertTokens(array $o, array $cap, int $revenueCents): array
{
    $notesBlock = ($o['notes'] ?? '') !== ''
        ? '<p style="margin:0;"><strong>Notes:</strong> ' . esc($o['notes']) . '</p>'
        : '';

    $phone = ($o['phone'] ?? '') !== '' ? esc($o['phone']) : '<em style="color:#7A5C44;">not provided</em>';

    return [
        'ORDER_REF'      => esc($o['order_ref']),
        'CUSTOMER_NAME'  => esc($o['name']),
        'CUSTOMER_EMAIL' => esc($o['email']),
        'CUSTOMER_PHONE' => $phone,
        'QUANTITY'       => (string) (int) $o['quantity'],
        'VARIANT'        => esc($o['variant']),
        'PICKUP_DATE'    => fmtDate($o['pickup_date']),
        'AMOUNT'         => fmtMoney($o['amount_cents']),
        'NOTES_BLOCK'    => $notesBlock,
        'ROLLS_SOLD'     => (string) (int) $cap['rolls_sold'],
        'ROLLS_MAX'      => (string) (int) $cap['rolls_max'],
        'ORDERS_PLACED'  => (string) (int) $cap['orders_placed'],
        'ORDERS_MAX'     => (string) (int) $cap['orders_max'],
        'WEEKEND_REVENUE'=> fmtMoney($revenueCents),
        'YEAR'           => date('Y'),
    ];
}

// ---------------------------------------------------------------
// Plain-text fallbacks
// ---------------------------------------------------------------

function confirmationPlainText(array $o): string
{
    $notes = ($o['notes'] ?? '') !== '' ? "\nSpecial requests: {$o['notes']}" : '';
    return <<<TEXT
    Order Confirmed — Bachata Bakery
    ================================

    Hi {$o['name']},

    Your order is confirmed. Here's your reference:

      {$o['order_ref']}

    Order details
    -------------
    Variety:    {$o['variant']}
    Quantity:   {$o['quantity']} roll(s)
    Total:      {$o['amount_cents']}
    Pickup:     {$o['pickup_date']}{$notes}

    Questions? Email orders@bachatababakery.com

    Bachata Bakery — Handcrafted with sabor
    TEXT;
}

function ownerAlertPlainText(array $o, array $cap, int $revenueCents): string
{
    $notes = ($o['notes'] ?? '') !== '' ? "\nNotes: {$o['notes']}" : '';
    return <<<TEXT
    New Order — {$o['order_ref']}
    ==============================

    Customer:  {$o['name']}
    Email:     {$o['email']}
    Phone:     {$o['phone']}

    Variety:   {$o['variant']}
    Quantity:  {$o['quantity']} roll(s)
    Total:     {$o['amount_cents']}{$notes}
    Pickup:    {$o['pickup_date']}

    Weekend totals
    --------------
    Rolls:    {$cap['rolls_sold']} / {$cap['rolls_max']}
    Orders:   {$cap['orders_placed']} / {$cap['orders_max']}
    Revenue:  {$revenueCents}
    TEXT;
}

// ---------------------------------------------------------------
// Micro-utilities
// ---------------------------------------------------------------

function esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fmtDate(string $ymd): string
{
    return (new DateTimeImmutable($ymd))->format('l, F j, Y');
}

function fmtMoney(int $cents): string
{
    return '$' . number_format($cents / 100, 2);
}
