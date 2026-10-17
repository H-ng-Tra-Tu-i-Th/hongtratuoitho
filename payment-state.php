<?php
require __DIR__ . '/lib/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['ok' => false, 'message' => htp('forbidden')]);
    exit;
}
try {
    $order = htp_customer_order($_GET['order'] ?? '');
    $receipt = htp_receipt((int) $order['id']);
    if (!$receipt)
        htp_fail('legacy');
    $signature = htp_customer_version($order, $receipt);
    $html = htp_render_customer($order);
    $status = $receipt['financial_state'] ?? $receipt['status'];
    $isPaid = in_array($status, ['confirmed', 'not_required', 'overpaid'], true) || ((float) ($receipt['amount'] ?? 0) > 0 && (float) ($receipt['received_amount'] ?? 0) >= (float) ($receipt['amount'] ?? 0));
    echo json_encode(['ok' => true, 'signature' => $signature, 'html' => $html, 'status' => $status, 'financial_state' => $status, 'paid' => $isPaid, 'received_amount' => (float) ($receipt['received_amount'] ?? 0), 'balance_due' => (float) ($receipt['balance_due'] ?? 0), 'message' => htp_ux('updated')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code($e instanceof TransferPaymentError && $e->getMessage() === 'forbidden' ? 403 : 409);
    echo json_encode(['ok' => false, 'message' => htp_error($e)], JSON_UNESCAPED_UNICODE);
}
