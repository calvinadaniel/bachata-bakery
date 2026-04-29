<?php
/**
 * admin/actions/export-csv.php
 *
 * GET — Stream all orders for the active window as a CSV download.
 * Includes paid, pending, and failed rows for full auditability.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/window.php';
require_once dirname(__DIR__, 2) . '/api/helpers/db.php';

$pdo      = db();
$windowId = activeWindowId($pdo);

$stmt = $pdo->prepare(
    'SELECT order_ref, window_id, customer_name, customer_email, customer_phone,
            product_variant, quantity, amount_cents, pickup_date,
            payment_status, square_payment_id, special_notes, created_at
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
    'Variety', 'Qty', 'Amount ($)', 'Pickup Date',
    'Status', 'Square Payment ID', 'Notes', 'Created At',
]);

while ($row = $stmt->fetch()) {
    fputcsv($out, [
        $row['order_ref'],
        $row['window_id'],
        $row['customer_name'],
        $row['customer_email'],
        $row['customer_phone'] ?? '',
        $row['product_variant'] ?? '',
        $row['quantity'],
        number_format($row['amount_cents'] / 100, 2),
        $row['pickup_date'] ?? '',
        $row['payment_status'],
        $row['square_payment_id'] ?? '',
        $row['special_notes'] ?? '',
        $row['created_at'],
    ]);
}

fclose($out);
exit;
