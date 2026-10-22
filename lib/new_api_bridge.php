<?php
/**
 * Cầu nối tạm sang hệ mới (backend-api Laravel).
 * Báo sự kiện đơn hàng qua webhook có ký HMAC; Laravel tự đọc lại đơn từ DB dùng chung
 * và bắn socket (Reverb) cho partner-web. Không bao giờ throw: lỗi chỉ ghi log,
 * không được làm hỏng đặt đơn / phân đơn của hệ cũ.
 * Xoá file này (và các chỗ gọi hq_bridge_notify) khi bỏ hệ cũ.
 */
function hq_bridge_notify(string $event, int $orderId, array $extra = []): void {
    if (!defined('NEW_API_WEBHOOK_URL') || NEW_API_WEBHOOK_URL === '' || !defined('NEW_API_WEBHOOK_SECRET') || NEW_API_WEBHOOK_SECRET === '') return;
    if (!function_exists('curl_init')) { error_log('[bridge] curl unavailable'); return; }
    try {
        $body = json_encode(['event' => $event, 'order_id' => $orderId] + $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ts = (string) time();
        $sig = hash_hmac('sha256', $ts . '.' . $body, NEW_API_WEBHOOK_SECRET);
        $ch = curl_init(NEW_API_WEBHOOK_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Legacy-Timestamp: ' . $ts,
                'X-Legacy-Signature: ' . $sig,
            ],
        ]);
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($res === false || $code < 200 || $code >= 300) {
            error_log('[bridge] ' . $event . ' #' . $orderId . ' failed: http ' . $code . ($err !== '' ? ' ' . $err : ''));
        }
    } catch (Throwable $e) {
        error_log('[bridge] ' . $event . ' #' . $orderId . ' error: ' . $e->getMessage());
    }
}
