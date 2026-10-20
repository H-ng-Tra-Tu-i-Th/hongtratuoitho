<?php
require __DIR__ . '/../lib/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');
if (!admin_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}
$after = max(0, (int) ($_GET['after'] ?? 0));
$watch = max(0, (int) ($_GET['watch'] ?? 0));
try {
    $readyCond = "(o.payment_method != 'transfer' OR o.total_amount <= 0 OR r.status IN ('confirmed', 'overpaid') OR r.received_amount >= r.amount)";
    $st = db()->prepare("SELECT o.id,o.order_no,o.customer_name,o.total_amount,o.created_at,o.dispatch_store_id,ds.name AS dispatch_store_name,ds.address AS dispatch_store_address,ds.phone AS dispatch_store_phone,ds.map_url AS dispatch_store_map_url,ds.name AS store_name,ds.address AS store_address,ds.phone AS store_phone FROM orders o LEFT JOIN transfer_order_receipts r ON r.order_id=o.id LEFT JOIN dispatch_stores ds ON ds.id=o.dispatch_store_id WHERE (o.id>? OR (o.payment_method='transfer' AND r.confirmed_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 SECOND))) AND o.status='new' AND $readyCond ORDER BY o.id ASC");
    $st->execute([$after]);
    $orders = $st->fetchAll();
    $latest = latest_order_id();
    $watchStatus = null;
    if ($watch > 0) {
        $ws = db()->prepare('SELECT status FROM orders WHERE id=?');
        $ws->execute([$watch]);
        $v = $ws->fetchColumn();
        $watchStatus = $v === false ? 'missing' : (string) $v;
    }
    $pending = db()->query("SELECT o.id,o.order_no,o.customer_name,o.total_amount,o.created_at,o.dispatch_store_id,ds.name AS dispatch_store_name,ds.address AS dispatch_store_address,ds.phone AS dispatch_store_phone,ds.map_url AS dispatch_store_map_url,ds.name AS store_name,ds.address AS store_address,ds.phone AS store_phone FROM orders o LEFT JOIN transfer_order_receipts r ON r.order_id=o.id LEFT JOIN dispatch_stores ds ON ds.id=o.dispatch_store_id WHERE o.status='new' AND $readyCond ORDER BY o.id ASC")->fetchAll();
    $newCount = (int) db()->query("SELECT COUNT(*) FROM orders o LEFT JOIN transfer_order_receipts r ON r.order_id=o.id WHERE o.status='new' AND $readyCond")->fetchColumn();
    echo json_encode(['ok' => true, 'latest' => $latest, 'new_count' => $newCount, 'orders' => $orders, 'pending_orders' => $pending, 'watch_status' => $watchStatus], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
