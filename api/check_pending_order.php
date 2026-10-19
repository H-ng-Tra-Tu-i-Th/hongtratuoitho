<?php
/**
 * Realtime API: Kiểm tra đơn hàng đang chờ thanh toán (PENDING_PAYMENT)
 * Hỗ trợ kiểm tra tức thì khi khách hàng nhập số điện thoại mà không cần phải F5 lại trang.
 */
require __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');

try {
    $phone = trim((string) ($_REQUEST['phone'] ?? ''));
    $checkoutMember = hym_current_member();
    $memberId = $checkoutMember ? (int) $checkoutMember['id'] : null;

    $sessionOrders = $_SESSION['customer_payment_orders'] ?? [];
    if (!is_array($sessionOrders)) {
        $sessionOrders = [];
    }
    if (!empty($_SESSION['last_order_no'])) {
        $sessionOrders[] = $_SESSION['last_order_no'];
    }

    $pending = customer_find_pending_payment_order($memberId, $phone, $sessionOrders);

    if ($pending) {
        $amountFormatted = currency() . money($pending['total_amount']);
        $title = function_exists('client_t') ? client_t('pending_order_exists_title') : 'Bạn đang có đơn hàng chờ thanh toán!';
        $desc = function_exists('client_t')
            ? client_t('pending_order_exists_desc', [
                'order_no' => $pending['order_no'],
                'amount' => $amountFormatted,
            ])
            : sprintf(
                'Đơn hàng #%s (%s) đang chờ bạn hoàn tất thanh toán. Vui lòng thanh toán hoặc hủy đơn cũ trước khi tạo đơn mới.',
                $pending['order_no'],
                $amountFormatted
            );

        echo json_encode([
            'ok' => true,
            'has_pending' => true,
            'order_no' => (string) $pending['order_no'],
            'amount' => (float) $pending['total_amount'],
            'formatted_amount' => $amountFormatted,
            'payment_url' => base_url('payment.php?order=' . rawurlencode($pending['order_no'])),
            'title' => $title,
            'desc' => $desc,
            'toast_title' => function_exists('client_t') ? client_t('pending_order_toast_title') : 'Đang có đơn hàng chờ thanh toán!',
            'toast_desc' => function_exists('client_t') ? client_t('pending_order_toast_desc', ['order_no' => $pending['order_no']]) : 'Đang kéo lên xem chi tiết đơn hàng...',
            'pay_now_text' => function_exists('client_t') ? client_t('pending_order_pay_now') : 'Đi tới trang thanh toán',
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'ok' => true,
            'has_pending' => false,
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
