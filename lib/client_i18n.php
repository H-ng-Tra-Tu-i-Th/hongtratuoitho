<?php
/**
 * Client Multilingual (i18n) Service.
 * Provides unified, synchronized translations for storefront client interface (vi, en, zh).
 */

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

function client_i18n_dictionary(): array {
    return [
        'vi' => [
            'pending_order_exists_title' => 'Bạn đang có đơn hàng chờ thanh toán!',
            'pending_order_exists_desc' => 'Đơn hàng #{order_no} ({amount}) đang chờ bạn hoàn tất thanh toán. Mỗi tài khoản chỉ được phép có tối đa 1 đơn chờ thanh toán. Vui lòng thanh toán hoặc hủy đơn cũ trước khi tạo đơn mới.',
            'pending_order_toast_title' => 'Đang có đơn hàng chờ thanh toán!',
            'pending_order_toast_desc' => 'Đang tự động kéo lên xem chi tiết đơn hàng #{order_no}...',
            'pending_order_pay_now' => 'Đi tới trang thanh toán',
            'pending_order_blocked_submit' => 'Không thể đặt thêm đơn! Bạn đang có đơn hàng #{order_no} chờ thanh toán.',
            'pending_order_error_exception' => 'Tài khoản đang có đơn hàng #{order_no} chờ thanh toán. Vui lòng hoàn tất hoặc hủy đơn này trước khi tạo đơn mới.',
            'sepay_qr_expired_badge' => 'Thời gian giữ đơn: Đã hết hạn (00:00)',
            'sepay_qr_expired_title' => 'Mã QR đã hết hạn',
            'sepay_qr_expired_sub' => 'Vui lòng không chuyển tiền vào mã này',
            'sepay_qr_expired_notice' => 'Đơn hàng đã hết thời gian giữ chỗ thanh toán (15 phút). Hệ thống sẽ tự động hủy đơn để bạn có thể đặt lại đồ uống mới bất cứ lúc nào.',
            'cancel_order_btn' => 'Hủy đơn này để đặt lại',
            'cancel_order_reorder_btn' => 'Hủy đơn & Đặt lại món',
            'cancel_order_only_btn' => 'Hủy đơn hàng',
            'cancel_order_confirm' => 'Bạn có chắc chắn muốn hủy đơn hàng này không?',
            'cancel_order_reorder_success' => 'Đã hủy đơn hàng hết hạn và khôi phục các món vào giỏ hàng. Bạn có thể kiểm tra và tiến hành đặt đơn mới ngay!',
            'cancel_order_success' => 'Đã hủy đơn hàng thành công. Bạn có thể đặt đơn mới bất cứ lúc nào.',
            'sepay_already_paid_hint' => 'Nếu bạn đã chuyển khoản thành công trước đó, vui lòng liên hệ hotline chi nhánh bên dưới để được hỗ trợ kiểm tra và đối soát ngay.',
            'payment_support_need_help' => 'Cần hỗ trợ về đơn hàng? Hotline chi nhánh:',
            'order_cancelled_title' => 'Đơn hàng đã được hủy',
            'order_cancelled_desc' => 'Đơn hàng này đã được hủy do quá hạn thanh toán. Bạn có thể tiến hành đặt đơn mới ngay bây giờ.',
            'order_status_cancelled' => 'Đã hủy',
        ],
        'en' => [
            'pending_order_exists_title' => 'You have a pending order awaiting payment!',
            'pending_order_exists_desc' => 'Order #{order_no} ({amount}) is waiting for payment. Each account is allowed only 1 pending order at a time. Please complete payment or cancel the previous order before creating a new one.',
            'pending_order_toast_title' => 'Pending order awaiting payment!',
            'pending_order_toast_desc' => 'Scrolling up to view order #{order_no}...',
            'pending_order_pay_now' => 'Go to payment page',
            'pending_order_blocked_submit' => 'Cannot create new order! You have pending order #{order_no}.',
            'pending_order_error_exception' => 'Your account has a pending order #{order_no}. Please complete or cancel it before creating a new one.',
            'sepay_qr_expired_badge' => 'Hold time: Expired (00:00)',
            'sepay_qr_expired_title' => 'QR Code Expired',
            'sepay_qr_expired_sub' => 'Please do not transfer to this QR',
            'sepay_qr_expired_notice' => 'Order hold time (15 mins) has elapsed. The system will automatically cancel this order so you can place a new order anytime.',
            'cancel_order_btn' => 'Cancel order to reorder',
            'cancel_order_reorder_btn' => 'Cancel & Reorder',
            'cancel_order_only_btn' => 'Cancel Order',
            'cancel_order_confirm' => 'Are you sure you want to cancel this order?',
            'cancel_order_reorder_success' => 'Expired order cancelled and items restored to cart. You can now place a new order!',
            'cancel_order_success' => 'Order cancelled successfully. You can now place a new order anytime.',
            'sepay_already_paid_hint' => 'If you already completed the transfer, please call the branch hotline below for immediate assistance.',
            'payment_support_need_help' => 'Need help with your order? Branch hotline:',
            'order_cancelled_title' => 'Order has been cancelled',
            'order_cancelled_desc' => 'This order was cancelled due to payment expiration. You can place a new order right now.',
            'order_status_cancelled' => 'Cancelled',
        ],
        'zh' => [
            'pending_order_exists_title' => '您有一筆待付款的訂單！',
            'pending_order_exists_desc' => '訂單 #{order_no}（{amount}）正在等待您完成付款。每位用戶最多僅允許 1 筆待付款訂單。請先完成付款或取消舊訂單後再建立新訂單。',
            'pending_order_toast_title' => '您有待付款訂單！',
            'pending_order_toast_desc' => '正在自動向上滾動查看訂單 #{order_no} 詳情...',
            'pending_order_pay_now' => '前往付款頁面',
            'pending_order_blocked_submit' => '無法建立新訂單！您目前有待付款訂單 #{order_no}。',
            'pending_order_error_exception' => '您的帳號已有待付款訂單 #{order_no}。請先完成或取消該訂單後再下新訂單。',
            'sepay_qr_expired_badge' => '保留時間：已逾期 (00:00)',
            'sepay_qr_expired_title' => '付款碼已過期',
            'sepay_qr_expired_sub' => '請勿轉帳至此二維碼',
            'sepay_qr_expired_notice' => '訂單保留時間（15分鐘）已過。系統將自動取消此訂單，以便您隨時重新下單。',
            'cancel_order_btn' => '取消此訂單以重新下單',
            'cancel_order_reorder_btn' => '取消並重新下單',
            'cancel_order_only_btn' => '取消訂單',
            'cancel_order_confirm' => '您確定要取消此訂單嗎？',
            'cancel_order_reorder_success' => '已取消過期訂單並將商品恢復至購物車，您現在可以立即重新下單！',
            'cancel_order_success' => '已成功取消訂單，您可以隨時建立新訂單。',
            'sepay_already_paid_hint' => '若您先前已完成轉帳，請聯繫下方門市專線以立即協助核對款項。',
            'payment_support_need_help' => '需要訂單協助？門市專線：',
            'order_cancelled_title' => '訂單已取消',
            'order_cancelled_desc' => '此訂單已取消，您可以隨時建立新訂單。',
            'order_status_cancelled' => '已取消',
        ],
    ];
}

/**
 * Translate a client-facing string with placeholder replacement.
 */
function client_t(string $key, array $vars = []): string {
    $lang = function_exists('current_lang') ? current_lang() : 'vi';
    $dict = client_i18n_dictionary();
    $text = $dict[$lang][$key] ?? $dict['vi'][$key] ?? $key;

    foreach ($vars as $name => $value) {
        $text = str_replace('{' . $name . '}', (string)$value, $text);
    }
    return $text;
}

/**
 * Output JavaScript i18n object tag for client frontend scripts.
 */
function client_render_js_i18n(): string {
    $lang = function_exists('current_lang') ? current_lang() : 'vi';
    $dict = client_i18n_dictionary();
    $payload = [
        'lang' => $lang,
        'client' => $dict[$lang] ?? $dict['vi'],
    ];
    return '<script>window.__CLIENT_I18N__ = ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . ';</script>' . "\n";
}
