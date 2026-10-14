<?php
/**
 * admin/actions/export-csv.php
 *
 * GET ?window=YYYY-MM-DD — Stream all orders for a window as a CSV download.
 * Includes paid, pending, and failed rows for full auditability.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/window.php';
require_once dirname(__DIR__, 2) . '/api/helpers/db.php';

$pdo      = db();
$windowId = requestedWindowId($_GET['window'] ?? null);

$stmt = $pdo->prepare(
    'SELECT order_ref, window_id, customer_name, customer_email, customer_phone,
            product_variant, quantity, amount_cents, pickup_date, pickup_location, pickup_time,
            payment_status, square_order_id, square_payment_id, special_notes, created_at
       FROM orders
      WHERE window_id = ?
      ORDER BY created_at ASC'
);
$stmt->execute([$windowId]);

$filename = 'bachata-bakery-orders-' . $windowId . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store');
header('Pragma: no-cache');

// UTF-8 BOM so Excel opens it correctly without needing the import wizard
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'wb');

fputcsv($out, [
    'Order Ref', 'Window', 'Name', 'Email', 'Phone',
    'Items', 'Item Count', 'Amount ($)', 'Pickup Date', 'Pickup Location', 'Pickup Time',
    'Status', 'Square Order ID', 'Square Payment ID', 'Notes', 'Created At',
]);

/** Prevent spreadsheet formula injection from customer-entered text. */
function csvCell(?string $value): string
{
    $value ??= '';
    return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
}

while ($row = $stmt->fetch()) {
    fputcsv($out, [
        $row['order_ref'],
        $row['window_id'],
        csvCell($row['customer_name']),
        csvCell($row['customer_email']),
        csvCell($row['customer_phone']),
        csvCell($row['product_variant']),
        $row['quantity'],
        number_format($row['amount_cents'] / 100, 2),
        $row['pickup_date'] ?? '',
        $row['pickup_location'] ?? '',
        $row['pickup_time'] !== null ? date('g:i A', strtotime($row['pickup_time'])) : '',
        $row['payment_status'],
        $row['square_order_id'] ?? '',
        $row['square_payment_id'] ?? '',
        csvCell($row['special_notes']),
        $row['created_at'],
    ]);
}

fclose($out);
exit;
