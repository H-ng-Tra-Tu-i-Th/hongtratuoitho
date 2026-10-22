<?php

/** Checkout drafts and request outcomes stay in the current authenticated session. */
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}
function hcq_context(string $group = ''): string
{
    return $group !== '' ? 'group:' . $group : 'ordinary';
}
function hcq_fields(): array
{
    return ['customer_name' => 100, 'phone' => 50, 'address' => 255, 'payment_method' => 16, 'invoice_required' => 1, 'invoice_company_name' => 200, 'invoice_tax_id' => 50, 'invoice_company_address' => 255, 'invoice_company_representative' => 120, 'invoice_company_phone' => 50, 'invoice_email' => 190, 'order_note' => 1000];
}
function hcq_capture(string $context, array $input): array
{
    $values = [];
    $errors = [];
    foreach (hcq_fields() as $key => $max) {
        $raw = $input[$key] ?? '';
        if (!is_string($raw) && !is_int($raw)) {
            $errors[$key] = 'incomplete_data';
            $raw = '';
        }
        $value = trim((string)$raw);
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        if ($length > $max) {
            $errors[$key] = 'incomplete_data';
            $value = function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
        }
        $values[$key] = $value;
    }
    foreach (['customer_name', 'phone', 'address'] as $key) if ($values[$key] === '') $errors[$key] = 'incomplete_data';
    if (!in_array($values['payment_method'], ['transfer', 'cash', 'split'], true)) $errors['payment_method'] = 'incomplete_data';
    if (!in_array($values['invoice_required'], ['0', '1'], true)) $errors['invoice_required'] = 'incomplete_data';
    if ($values['invoice_required'] === '1') {
        foreach (['invoice_company_name', 'invoice_tax_id', 'invoice_company_address', 'invoice_company_phone', 'invoice_email'] as $key) if ($values[$key] === '') $errors[$key] = 'invoice_fields_required';
        if ($values['invoice_email'] !== '' && !filter_var($values['invoice_email'], FILTER_VALIDATE_EMAIL)) $errors['invoice_email'] = 'invalid_email';
    }
    $drafts = $_SESSION['checkout_drafts'] ?? [];
    if (!is_array($drafts)) $drafts = [];
    unset($drafts[$context]);
    $drafts[$context] = ['values' => $values, 'errors' => $errors, 'saved_at' => time(), 'member_id' => (int)($_SESSION['member_id'] ?? 0)];
    $_SESSION['checkout_drafts'] = array_slice($drafts, -20, null, true);
    return ['values' => $values, 'errors' => $errors];
}
function hcq_draft(string $context): array
{
    $draft = $_SESSION['checkout_drafts'][$context] ?? [];
    if (!is_array($draft) || ($draft['saved_at'] ?? 0) < time() - 7200 || (int)($draft['member_id'] ?? -1) !== (int)($_SESSION['member_id'] ?? 0)) return ['values' => [], 'errors' => []];
    return ['values' => is_array($draft['values'] ?? null) ? $draft['values'] : [], 'errors' => is_array($draft['errors'] ?? null) ? $draft['errors'] : []];
}
function hcq_back(string $group = ''): string
{
    return base_url('checkout.php' . ($group !== '' ? '?group=' . rawurlencode($group) : ''));
}
function hcq_fail(string $context, string $group, string $message): never
{
    $_SESSION['checkout_errors'][$context] = $message;
    header('Location: ' . hcq_back($group), true, 303);
    exit;
}
function hcq_request(string $context, string $signature): string
{
    $requests = $_SESSION['checkout_requests'] ?? [];
    if (!is_array($requests)) $requests = [];
    // An unresolved submission always takes precedence, including after quote changes or long absences.
    if ($unknown = hcq_unknown_token($context)) return $unknown;
    foreach ($requests as $token => $r) if (is_array($r) && ($r['context'] ?? '') === $context && ($r['signature'] ?? '') === $signature && (int)($r['member_id'] ?? -1) === (int)($_SESSION['member_id'] ?? 0) && ($r['created_at'] ?? 0) > time() - 7200 && ($r['state'] ?? '') === 'open') return (string)$token;
    $token = bin2hex(random_bytes(24));
    $requests[$token] = ['context' => $context, 'signature' => $signature, 'member_id' => (int)($_SESSION['member_id'] ?? 0), 'created_at' => time(), 'state' => 'open'];
    $_SESSION['checkout_requests'] = hcq_trim_requests($requests);
    return $token;
}
function hcq_unknown_token(string $context): ?string
{
    foreach ($_SESSION['checkout_requests'] ?? [] as $token => $r) if (is_array($r) && ($r['context'] ?? '') === $context && (int)($r['member_id'] ?? -1) === (int)($_SESSION['member_id'] ?? 0) && ($r['state'] ?? '') === 'unknown') return (string)$token;
    return null;
}
function hcq_trim_requests(array $requests): array
{
    $unknown = [];
    $open = [];
    $saved = [];
    foreach ($requests as $token => $request) {
        if (!is_array($request)) continue;
        switch ($request['state'] ?? '') {
            case 'unknown':
                $unknown[] = $token;
                break;
            case 'open':
                $open[] = $token;
                break;
            case 'saved':
                $saved[] = $token;
                break;
        }
    }
    // Ordinary history is bounded independently. Unresolved references cannot be evicted by it.
    $keep = array_fill_keys(array_merge($unknown, array_slice($open, -20), array_slice($saved, -20)), true);
    return array_intersect_key($requests, $keep);
}
function hcq_lookup(mixed $token, string $context): ?array
{
    if (!is_string($token) || !preg_match('/^[a-f0-9]{48}$/D', $token)) return null;
    $r = $_SESSION['checkout_requests'][$token] ?? null;
    if (is_array($r) && ($r['state'] ?? '') === 'open' && ($r['created_at'] ?? 0) <= time() - 7200) return null;
    return is_array($r) && ($r['context'] ?? '') === $context && (int)($r['member_id'] ?? -1) === (int)($_SESSION['member_id'] ?? 0) ? $r : null;
}
function hcq_saved(string $token, string $number, string $state = 'saved'): void
{
    if (isset($_SESSION['checkout_requests'][$token])) {
        $request = $_SESSION['checkout_requests'][$token];
        unset($_SESSION['checkout_requests'][$token]);
        $request['state'] = $state;
        $request['order_no'] = $number;
        $_SESSION['checkout_requests'][$token] = $request;
        $_SESSION['checkout_requests'] = hcq_trim_requests($_SESSION['checkout_requests']);
    }
}
function hcq_outcome(mixed $token, string $context): ?array
{
    $request = hcq_lookup($token, $context);
    return $request && in_array($request['state'] ?? '', ['saved', 'unknown'], true) ? $request : null;
}
function hcq_existing_order(string $token, string $context): ?array
{
    $request = hcq_lookup($token, $context);
    if (!$request) return null;
    $order = hyg_one('SELECT * FROM orders WHERE checkout_request_hash=?', [hash('sha256', $token)]);
    if (!$order) return null;
    if ((int)$request['member_id'] === 0 && empty($order['member_id']) && empty($order['group_room_id']) && $context === 'ordinary') htp_remember_customer_order($order['order_no']);
    if (!htp_customer_allowed($order)) htp_fail('forbidden');
    return $order;
}
function hcq_field_error(array $errors, string $name): string
{
    return isset($errors[$name]) ? '<small class="checkout-field-error" id="error-' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">' . e(t($errors[$name])) . '</small>' : '';
}
function hcq_field_attrs(array $errors, string $name): string
{
    return isset($errors[$name]) ? ' aria-invalid="true" aria-describedby="error-' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"' : '';
}

/**
 * Tìm đơn hàng đang ở trạng thái chờ thanh toán (PENDING_PAYMENT) của một người dùng.
 * Áp dụng cho cả Member (đã đăng nhập) và Khách vãng lai (Guest nhận diện qua Session hoặc Số điện thoại).
 */
function customer_find_pending_payment_order(?int $memberId = null, ?string $phone = null, array $sessionOrders = [], bool $forUpdate = false): ?array
{
    // Tự động quét và hủy các đơn quá thời gian giữ thanh toán chưa thanh toán.
    require_once __DIR__ . '/order_workflow.php';
    if (function_exists('htow_cancel_expired_orders')) {
        try {
            htow_cancel_expired_orders(20);
        } catch (Throwable $ignored) {}
    }

    $pdo = db();
    $userClauses = [];
    $userParams = [];

    // 1. Nhận diện qua Member ID nếu đã đăng nhập
    if ($memberId !== null && $memberId > 0) {
        $userClauses[] = 'o.member_id = ?';
        $userParams[] = $memberId;

        if ($forUpdate && $pdo->inTransaction()) {
            $stLock = $pdo->prepare('SELECT id FROM members WHERE id = ? FOR UPDATE');
            $stLock->execute([$memberId]);
        }
    }

    // 2. Nhận diện qua danh sách đơn hàng đã lưu trong Session hiện tại
    $validSessionOrders = array_values(array_filter(
        $sessionOrders,
        fn($no) => is_string($no) && preg_match('/^[A-Za-z0-9_-]{8,80}$/D', $no)
    ));
    if (!empty($validSessionOrders)) {
        $placeholders = implode(',', array_fill(0, count($validSessionOrders), '?'));
        $userClauses[] = "o.order_no IN ($placeholders)";
        foreach ($validSessionOrders as $no) {
            $userParams[] = $no;
        }
    }

    // 3. Nhận diện qua Số điện thoại (chuẩn hóa các định dạng +84 / 0...)
    $cleanPhone = preg_replace('/\D+/', '', (string) $phone);
    if (strlen($cleanPhone) >= 8) {
        $variants = array_values(array_unique(array_filter([
            trim((string) $phone),
            $cleanPhone,
            str_starts_with($cleanPhone, '0') ? '+84' . substr($cleanPhone, 1) : '',
            str_starts_with($cleanPhone, '84') ? '0' . substr($cleanPhone, 2) : '',
            str_starts_with($cleanPhone, '0') ? '84' . substr($cleanPhone, 1) : '',
        ])));
        if (!empty($variants)) {
            $phonePlaceholders = implode(',', array_fill(0, count($variants), '?'));
            $userClauses[] = "o.phone IN ($phonePlaceholders)";
            foreach ($variants as $p) {
                $userParams[] = $p;
            }
        }
    }

    if (empty($userClauses)) {
        return null;
    }

    $whereUser = '(' . implode(' OR ', $userClauses) . ')';

    // Lazy expiration: hủy ngay các đơn quá thời gian giữ của user / số điện thoại này.
    try {
        $cancelSql = "UPDATE orders o
                      LEFT JOIN transfer_order_receipts r ON r.order_id = o.id
                      SET o.status = 'cancelled', o.status_version = o.status_version + 1
                      WHERE $whereUser
                        AND o.status = 'new'
                        AND o.payment_method = 'transfer'
                        AND (
                            (o.payment_expires_at IS NOT NULL AND o.payment_expires_at <= UNIX_TIMESTAMP())
                            OR (o.payment_expires_at IS NULL AND o.created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL " . (int) PAYMENT_HOLD_SECONDS . " SECOND))
                        )
                        AND COALESCE(r.received_amount, 0) <= 0
                        AND COALESCE(r.status, 'pending') NOT IN ('confirmed', 'overpaid')";
        $pdo->prepare($cancelSql)->execute($userParams);
    } catch (Throwable $ignored) {
    }

    $sql = "SELECT o.id, o.order_no, o.total_amount, o.status, o.payment_method, o.phone, o.customer_name, o.created_at, o.payment_expires_at,
                   r.status AS receipt_status, r.received_amount, r.refunded_amount
            FROM orders o
            LEFT JOIN transfer_order_receipts r ON r.order_id = o.id
            WHERE $whereUser
              AND o.status NOT IN ('done', 'cancelled')
              AND o.payment_method = 'transfer'
              AND o.total_amount > 0
              AND (
                  (o.payment_expires_at IS NOT NULL AND o.payment_expires_at > UNIX_TIMESTAMP())
                  OR (o.payment_expires_at IS NULL AND o.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL " . (int) PAYMENT_HOLD_SECONDS . " SECOND))
              )
              AND (
                  r.order_id IS NULL
                  OR r.status IN ('pending', 'partial', 'requested')
                  OR (COALESCE(r.received_amount, 0) - COALESCE(r.refunded_amount, 0)) < o.total_amount
              )
            ORDER BY o.id DESC
            LIMIT 1";

    if ($forUpdate && $pdo->inTransaction()) {
        $sql .= " FOR UPDATE";
    }

    $st = $pdo->prepare($sql);
    $st->execute($userParams);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Ràng buộc bất biến: Mỗi người dùng (user) chỉ được phép có tối đa DUY NHẤT 1 đơn hàng ở trạng thái PENDING_PAYMENT.
 * Nếu đang có 1 đơn pending chưa hoàn tất hoặc chưa hủy, ném ExistingPendingOrderException.
 *
 * @throws \Lib\Exceptions\ExistingPendingOrderException
 */
function customer_assert_no_pending_order(?int $memberId = null, ?string $phone = null, array $sessionOrders = [], bool $forUpdate = false): void
{
    $pending = customer_find_pending_payment_order($memberId, $phone, $sessionOrders, $forUpdate);
    if ($pending !== null) {
        throw new \Lib\Exceptions\ExistingPendingOrderException(
            (string) $pending['order_no'],
            (float) ($pending['total_amount'] ?? 0),
            (string) ($pending['created_at'] ?? '')
        );
    }
}
