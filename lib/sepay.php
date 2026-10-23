<?php
/**
 * SePay VietQR Payment Integration Service.
 * Handles VietQR dynamic generation, SePay Webhook processing,
 * account matching, concurrency-safe idempotent database updates,
 * and post-payment kitchen/store notification triggering.
 */
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

class SepayPaymentError extends RuntimeException
{
}

function sepay_fail(string $message): never
{
    throw new SepayPaymentError($message);
}

/**
 * Get SePay API Key configured in constants or database settings.
 */
function sepay_api_key(): string
{
    if (defined('SEPAY_API_KEY') && is_string(SEPAY_API_KEY) && SEPAY_API_KEY !== '') {
        return trim(SEPAY_API_KEY);
    }
    return function_exists('setting') ? setting('sepay_api_key', '') : '';
}

/**
 * Get SePay Webhook Token / Secret for authentication.
 */
function sepay_webhook_token(): string
{
    if (defined('SEPAY_WEBHOOK_TOKEN') && is_string(SEPAY_WEBHOOK_TOKEN) && SEPAY_WEBHOOK_TOKEN !== '') {
        return trim(SEPAY_WEBHOOK_TOKEN);
    }
    return function_exists('setting') ? setting('sepay_webhook_token', '') : '';
}

/**
 * Normalize and strip Vietnamese diacritics / accents.
 */
function sepay_strip_accents(string $str): string
{
    $chars = [
        'a' => '/[àáạảãâầấậẩẫăằắặẳẵ]/u',
        'e' => '/[èéẹẻẽêềếệểễ]/u',
        'i' => '/[ìíịỉĩ]/u',
        'o' => '/[òóọỏõôồốộổỗơờớợởỡ]/u',
        'u' => '/[ùúụủũưừứựửữ]/u',
        'y' => '/[ỳýỵỷỹ]/u',
        'd' => '/[đ]/u',
        'A' => '/[ÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴ]/u',
        'E' => '/[ÈÉẸẺẼÊỀẾỆỂỄ]/u',
        'I' => '/[ÌÍỊỈĨ]/u',
        'O' => '/[ÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠ]/u',
        'U' => '/[ÙÚỤỦŨƯỪỨỰỬỮ]/u',
        'Y' => '/[ỲÝỴỶỸ]/u',
        'D' => '/[Đ]/u',
    ];
    foreach ($chars as $replacement => $pattern) {
        $str = preg_replace($pattern, $replacement, $str);
    }
    return $str;
}

/**
 * Map Vietnamese bank names, BINs, or common aliases to official SePay / VietQR bank codes.
 */
function sepay_bank_code(string $bankName): string
{
    $raw = strtoupper(trim(sepay_strip_accents($bankName)));
    $compact = preg_replace('/[^A-Z0-9]/', '', $raw);
    // 1. Exact match first (highest priority)
    $exactMap = [
        'VIETCOMBANK' => 'VCB',
        'VCB' => 'VCB',
        '970436' => 'VCB',
        'MBBANK' => 'MB',
        'MB' => 'MB',
        '970422' => 'MB',
        'TECHCOMBANK' => 'TCB',
        'TCB' => 'TCB',
        '970407' => 'TCB',
        'VIETINBANK' => 'ICB',
        'VIETIN' => 'ICB',
        'CTG' => 'ICB',
        'ICB' => 'ICB',
        '970415' => 'ICB',
        'BIDV' => 'BIDV',
        '970418' => 'BIDV',
        'ACB' => 'ACB',
        '970416' => 'ACB',
        'TPBANK' => 'TPB',
        'TPB' => 'TPB',
        '970423' => 'TPB',
        'VPBANK' => 'VPB',
        'VPB' => 'VPB',
        '970432' => 'VPB',
        'SACOMBANK' => 'STB',
        'STB' => 'STB',
        '970403' => 'STB',
        'HDBANK' => 'HDB',
        'HDB' => 'HDB',
        '970437' => 'HDB',
        'VIB' => 'VIB',
        '970441' => 'VIB',
        'MSB' => 'MSB',
        '970426' => 'MSB',
        'OCB' => 'OCB',
        '970448' => 'OCB',
        'SHB' => 'SHB',
        '970443' => 'SHB',
        'SEABANK' => 'SEAB',
        'SEAB' => 'SEAB',
        '970440' => 'SEAB',
        'LPBANK' => 'LPB',
        'LPB' => 'LPB',
        '970449' => 'LPB',
        'EXIMBANK' => 'EIB',
        'EIB' => 'EIB',
        '970431' => 'EIB',
        'NAMABANK' => 'NAB',
        'NAM A' => 'NAB',
        'NAB' => 'NAB',
        '970428' => 'NAB',
        'SHINHAN' => 'SHBVN',
        'SHINHANBANK' => 'SHBVN',
        'SHBVN' => 'SHBVN',
        '970424' => 'SHBVN',
        'TIMO' => 'TIMO',
        '963388' => 'TIMO',
    ];
    if (isset($exactMap[$raw])) {
        return $exactMap[$raw];
    }
    if (isset($exactMap[$compact])) {
        return $exactMap[$compact];
    }

    // 2. Substring matching for phrases and descriptive names
    $aliasMap = [
        'TECHCOM' => 'TCB',
        'VIETCOM' => 'VCB',
        'VIETIN' => 'ICB',
        'SACOM' => 'STB',
        'TIEN PHONG' => 'TPB',
        'QUAN DOI' => 'MB',
        'KY THUONG' => 'TCB',
        'A CHAU' => 'ACB',
        'CONG THUONG' => 'ICB',
        'DAU TU VA PHAT TRIEN' => 'BIDV',
        'THINH VUONG' => 'VPB',
        'SAI GON THUONG TIN' => 'STB',
        'HANG HAI' => 'MSB',
        'MARITIME' => 'MSB',
        'PHUONG DONG' => 'OCB',
        'SAI GON HA NOI' => 'SHB',
        'LIEN VIET' => 'LPB',
        'BUU DIEN LIEN VIET' => 'LPB',
        'XUAT NHAP KHAU' => 'EIB',
        'NAM A' => 'NAB',
    ];
    foreach ($aliasMap as $alias => $code) {
        if (str_contains($raw, $alias)) {
            return $code;
        }
    }


    // Default fallback: strip non-alphanumeric
    $clean = preg_replace('/[^A-Z0-9]/', '', $raw);
    return $clean !== '' ? $clean : 'VCB';
}

/**
 * Generate SePay dynamic VietQR image URL.
 */
function sepay_qr_url(string $accountNumber, string $bankName, float|int $amount, string $description, string $template = 'compact'): string
{
    $bankCode = sepay_bank_code($bankName);
    $cleanAcc = preg_replace('/[^A-Za-z0-9]/', '', $accountNumber);
    $cleanDes = trim(sepay_strip_accents($description));
    $cleanDes = preg_replace('/[^A-Za-z0-9 _-]/', '', $cleanDes);
    $amt = max(0, (int) round($amount));

    return 'https://qr.sepay.vn/img?acc=' . rawurlencode($cleanAcc)
        . '&bank=' . rawurlencode($bankCode)
        . ($amt > 0 ? '&amount=' . $amt : '')
        . '&des=' . rawurlencode($cleanDes)
        . '&template=' . rawurlencode($template);
}

/**
 * Standard description syntax for individual order.
 */
function sepay_order_description(string $orderNo, bool $isGroup = false): string
{
    $prefix = $isGroup ? 'HYGG ' : 'HYG ';
    return $prefix . strtoupper(trim($orderNo));
}

/**
 * Standard description syntax for group member split bill.
 */
function sepay_member_description(int $memberId): string
{
    return 'HYGM ' . $memberId;
}

/**
 * Parse transfer description / memo received from SePay Webhook.
 * CRITICAL: Checks HYGM (Split Bill Member) BEFORE HYG/HYGG (Order),
 * and handles optional spacing and diacritics removal.
 */
function sepay_parse_description(string $content, ?string $code = null): ?array
{
    $combined = strtoupper(sepay_strip_accents($content . ' ' . (string) $code));

    // Priority 1: Group Member Split Bill (HYGM<MEMBER_ID>)
    if (preg_match('/HYGM\s*(\d+)/i', $combined, $matches)) {
        $memberId = (int) $matches[1];
        if ($memberId > 0) {
            return ['type' => 'member', 'id' => $memberId];
        }
    }

    // Priority 1b: Alternative legacy format HYG-<ROOM>-<MEMBER_ID>
    if (preg_match('/HYG-[A-Z0-9]+-(\d+)/i', $combined, $matches)) {
        $memberId = (int) $matches[1];
        if ($memberId > 0) {
            return ['type' => 'member', 'id' => $memberId];
        }
    }

    // Priority 2: Individual Order (HYG <ORDER_NO>) / Group Full Payment (HYGG <ORDER_NO>)
    // Matches: HYG 20261016204124-700F69, HYGG 20261016204124-700F69
    if (preg_match('/HYGG?[-_\s]*([A-Z0-9]{14}[-_ ]?[A-Z0-9]{4,8})/i', $combined, $matches)) {
        $orderNo = trim($matches[1]);
        if ($orderNo !== '') {
            return ['type' => 'order', 'order_no' => $orderNo];
        }
    } elseif (preg_match('/HYGG?[-_\s]*([A-Z0-9_-]+)/i', $combined, $matches)) {
        $orderNo = trim($matches[1]);
        if ($orderNo !== '') {
            return ['type' => 'order', 'order_no' => $orderNo];
        }
    }

    return null;
}

/**
 * Verify Webhook Authorization header from SePay.
 */
function sepay_verify_webhook_auth(?string $authHeader): bool
{
    $configuredToken = sepay_webhook_token();
    $configuredApiKey = sepay_api_key();

    // If neither is configured in production, log notice and fail closed
    if ($configuredToken === '' && $configuredApiKey === '') {
        // In local development or first setup before key is added, check if running on loopback
        if (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
            return true;
        }
        return false;
    }

    if (!$authHeader || !is_string($authHeader)) {
        return false;
    }

    $header = trim($authHeader);
    // SePay sends: "Apikey <TOKEN>" or "Bearer <TOKEN>"
    if (preg_match('/^(?:Apikey|Bearer)\s+(.+)$/i', $header, $m)) {
        $token = trim($m[1]);
    } else {
        $token = $header;
    }

    if ($configuredToken !== '' && hash_equals($configuredToken, $token)) {
        return true;
    }
    if ($configuredApiKey !== '' && hash_equals($configuredApiKey, $token)) {
        return true;
    }

    return false;
}

/**
 * Normalize account number for robust comparison.
 */
function sepay_normalize_account(string $account): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $account));
}

function sepay_normalize_bank(string $bank): string
{
    return sepay_bank_code($bank);
}

/**
 * Check whether the received bank account and gateway match an allowed account.
 */
function sepay_match_account(string $receivedAccount, string $receivedBank, array $validAccounts): bool
{
    $rec = sepay_normalize_account($receivedAccount);
    $bank = sepay_normalize_bank($receivedBank);
    if ($rec === '' || $bank === '')
        return false;

    foreach ($validAccounts as $acc) {
        $num = sepay_normalize_account((string) ($acc['account_number'] ?? ''));
        $configuredBank = sepay_normalize_bank((string) ($acc['bank_name'] ?? ''));
        if ($num !== '' && $configuredBank !== '' && hash_equals($num, $rec) && hash_equals($configuredBank, $bank)) {
            return true;
        }
    }
    return false;
}

/**
 * Process SePay payment for an individual order or host full payment.
 * Ensures Database Concurrency with row locks (SELECT ... FOR UPDATE),
 * Idempotency, and triggers kitchen/store push notification upon successful confirmation.
 */
function sepay_process_order_payment(array $payload, string $orderNo): array
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        sepay_fail('Transaction already active');
    }

    $sepayId = (int) ($payload['id'] ?? 0);
    $delta = htp_cents($payload['transferAmount'] ?? 0);
    $bankAccount = (string) ($payload['accountNumber'] ?? '');
    $referenceCode = (string) ($payload['referenceCode'] ?? '');
    $gateway = trim((string) ($payload['gateway'] ?? ''));
    $bankBrand = trim((string) ($payload['bank_brand_name'] ?? ''));
    if ($gateway === '') {
        $gateway = $bankBrand;
    } elseif ($bankBrand !== '' && sepay_normalize_bank($gateway) !== sepay_normalize_bank($bankBrand)) {
        sepay_fail('SePay bank identity fields do not match.');
    }
    $content = (string) ($payload['content'] ?? '');
    $subAccount = (string) ($payload['subAccount'] ?? '');

    if ($delta <= 0) {
        sepay_fail('Invalid transfer amount');
    }

    $eventKey = 'sepay:' . $sepayId;
    $refKey = hash('sha256', json_encode(['sepay', $sepayId, $bankAccount, $referenceCode], JSON_UNESCAPED_UNICODE));

    // Check if this SePay transaction was already processed
    $chk = $pdo->prepare('SELECT order_id FROM transfer_payment_events WHERE event_key = ? OR reference_key = ?');
    $chk->execute([$eventKey, $refKey]);
    $existingOrderId = $chk->fetchColumn();
    if ($existingOrderId) {
        return [
            'status' => 'already_processed',
            'order_id' => (int) $existingOrderId,
            'message' => 'Transaction was already confirmed previously.'
        ];
    }

    $pdo->beginTransaction();
    try {
        // Step 1: Direct match
        $order = hyg_one('SELECT * FROM orders WHERE order_no = ? FOR UPDATE', [$orderNo]);
        if (!$order) {
            // Step 2: Fallback matching with stripped hyphens/spaces (bank transfers often strip punctuation)
            $cleanNo = preg_replace('/[^A-Za-z0-9]/', '', $orderNo);
            if ($cleanNo !== '') {
                $order = hyg_one("SELECT * FROM orders WHERE REPLACE(REPLACE(order_no, '-', ''), ' ', '') = ? ORDER BY id DESC LIMIT 1 FOR UPDATE", [$cleanNo]);
            }
        }
        if (!$order) {
            sepay_fail('Order not found: ' . $orderNo);
        }
        $orderId = (int) $order['id'];
        $orderNo = (string) $order['order_no']; // Use canonical order_no from database

        $receipt = hyg_one('SELECT * FROM transfer_order_receipts WHERE order_id = ? FOR UPDATE', [$orderId]);
        if (!$receipt) {
            // Initialize if missing
            $pdo->prepare("INSERT INTO transfer_order_receipts(order_id,status,amount,created_at) VALUES(?, 'pending', ?, UTC_TIMESTAMP())")
                ->execute([$orderId, $order['total_amount']]);
            $receipt = hyg_one('SELECT * FROM transfer_order_receipts WHERE order_id = ? FOR UPDATE', [$orderId]);
        }

        // Validate account recipient: strictly the order's branch account (or HQ fallback if branch has no account).
        // Never allow other branches' accounts!
        $orderForBank = $order;
        $orderForBank['payment_method'] = 'transfer';
        $validAccounts = htp_order_accounts($orderForBank, false, true);
        if (!$validAccounts || !sepay_match_account($bankAccount, $gateway, $validAccounts)) {
            sepay_fail('Received bank account ' . $bankAccount . ' does not match allowed branch/HQ account for order ' . $orderNo);
        }

        $due = htp_cents($receipt['amount']);
        $currentReceived = htp_cents($receipt['received_amount']);
        $currentRefunded = htp_cents($receipt['refunded_amount']);

        $newReceived = $currentReceived + $delta;
        $net = $newReceived - $currentRefunded;

        $newVersion = (int) $receipt['version'] + 1;

        // Snapshot matching account details
        $matchedAccount = null;
        foreach ($validAccounts as $va) {
            if (
                sepay_normalize_account((string) $va['account_number']) === sepay_normalize_account($bankAccount)
                && sepay_normalize_bank((string) ($va['bank_name'] ?? '')) === sepay_normalize_bank($gateway)
            ) {
                $matchedAccount = $va;
                break;
            }
        }
        if (!$matchedAccount) {
            $matchedAccount = [
                'source' => 'sepay',
                'id' => $sepayId,
                'recipient' => (string) ($order['dispatch_store_name'] ?? 'Huiyi Tea'),
                'bank_name' => $gateway,
                'account_number' => $bankAccount,
                'account_name' => '',
                'payment_method' => 'transfer'
            ];
        }

        $recipientJson = json_encode(htp_account_snapshot($matchedAccount), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        // Check if order is already cancelled or has expired past the configured hold period.
        $nowTs = time();
        $isExpired = false;
        if (!empty($order['payment_expires_at'])) {
            $isExpired = (int) $order['payment_expires_at'] <= $nowTs;
        } elseif (!empty($order['created_at'])) {
            try {
                $createdUtc = new DateTimeImmutable($order['created_at'], new DateTimeZone('UTC'));
                $isExpired = ($createdUtc->getTimestamp() + PAYMENT_HOLD_SECONDS) <= $nowTs;
            } catch (Throwable) {
                $isExpired = true;
            }
        }

        // Handle payment for already-cancelled or expired orders
        if ($order['status'] === 'cancelled' || ($order['status'] === 'new' && $isExpired)) {
            if ($order['status'] === 'new') {
                $curVer = (int) ($order['status_version'] ?? 1);
                $newVer = $curVer + 1;
                $pdo->prepare('UPDATE orders SET status = "cancelled", status_version = ? WHERE id = ?')
                    ->execute([$newVer, $orderId]);
                $pdo->prepare('INSERT INTO order_status_events(order_id, from_status, to_status, from_version, to_version, admin_id, reason, created_at) VALUES(?, "new", "cancelled", ?, ?, 0, ?, UTC_TIMESTAMP())')
                    ->execute([$orderId, $curVer, $newVer, 'Tự động hủy đơn do khách chuyển khoản sau khi đã quá hạn thời gian thanh toán']);
                $order['status'] = 'cancelled';
                $order['status_version'] = $newVer;
            }

            $pdo->prepare("UPDATE transfer_order_receipts SET status = 'refund_pending', version = ?, received_amount = ?, report_state = 'refund_pending', recipient_json = COALESCE(recipient_json, ?), confirmed_at = UTC_TIMESTAMP(), confirmed_by_admin_id = 0 WHERE order_id = ?")
                ->execute([$newVersion, htp_decimal($newReceived), $recipientJson, $orderId]);

            $before = $receipt;
            $after = hyg_one('SELECT * FROM transfer_order_receipts WHERE order_id = ?', [$orderId]);
            $safe = fn($r) => array_intersect_key($r, array_flip(['status', 'version', 'amount', 'received_amount', 'refunded_amount', 'report_state', 'recipient_json', 'confirmed_at']));

            $pdo->prepare('INSERT INTO transfer_payment_events(order_id, event_key, event_type, amount_delta, refund_delta, account_code, recipient_json, transaction_reference, reference_key, reason, admin_id, before_json, after_json, created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())')
                ->execute([
                    $orderId,
                    $eventKey,
                    'payment_after_cancel',
                    htp_decimal($delta),
                    '0.00',
                    $matchedAccount['source'] . ':' . ($matchedAccount['id'] ?? 0),
                    $recipientJson,
                    $referenceCode !== '' ? $referenceCode : ('sepay:' . $sepayId),
                    $refKey,
                    'Khách chuyển khoản sau khi đơn đã hủy hoặc quá hạn thời gian thanh toán. Chờ xử lý hoàn tiền / soát xét thủ công. Ghi chú: ' . mb_substr($content, 0, 500),
                    0,
                    json_encode($safe($before), JSON_UNESCAPED_UNICODE),
                    json_encode($safe($after), JSON_UNESCAPED_UNICODE)
                ]);

            $pdo->commit();

            return [
                'status' => 'payment_after_cancel',
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'received' => htp_decimal($delta),
                'receipt_status' => 'refund_pending',
                'message' => 'Payment received for cancelled or expired order. Flagged for refund/manual review.'
            ];
        }

        $newStatus = ($due === 0 && $net === 0) ? 'not_required' : (($net >= $due && $due > 0) ? 'confirmed' : 'partial');

        $fullyPaid = in_array($newStatus, ['confirmed', 'not_required'], true);
        $pdo->prepare("UPDATE transfer_order_receipts SET status = ?, version = ?, received_amount = ?, report_state = IF(? = 1, 'confirmed', report_state), recipient_json = COALESCE(recipient_json, ?), confirmed_at = IF(? = 1, UTC_TIMESTAMP(), confirmed_at), confirmed_by_admin_id = IF(? = 1, 0, confirmed_by_admin_id) WHERE order_id = ?")
            ->execute([$newStatus, $newVersion, htp_decimal($newReceived), $fullyPaid ? 1 : 0, $recipientJson, $fullyPaid ? 1 : 0, $fullyPaid ? 1 : 0, $orderId]);

        // Insert audit event in transfer_payment_events
        $before = $receipt;
        $after = hyg_one('SELECT * FROM transfer_order_receipts WHERE order_id = ?', [$orderId]);
        $safe = fn($r) => array_intersect_key($r, array_flip(['status', 'version', 'amount', 'received_amount', 'refunded_amount', 'report_state', 'recipient_json', 'confirmed_at']));

        $pdo->prepare('INSERT INTO transfer_payment_events(order_id, event_key, event_type, amount_delta, refund_delta, account_code, recipient_json, transaction_reference, reference_key, reason, admin_id, before_json, after_json, created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())')
            ->execute([
                $orderId,
                $eventKey,
                'receive',
                htp_decimal($delta),
                '0.00',
                $matchedAccount['source'] . ':' . ($matchedAccount['id'] ?? 0),
                $recipientJson,
                $referenceCode !== '' ? $referenceCode : ('sepay:' . $sepayId),
                $refKey,
                'Xác nhận tự động qua SePay (VietQR). Ghi chú: ' . mb_substr($content, 0, 500),
                0,
                json_encode($safe($before), JSON_UNESCAPED_UNICODE),
                json_encode($safe($after), JSON_UNESCAPED_UNICODE)
            ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    // Notify staff only after the full payment is confirmed.
    if (in_array($newStatus, ['confirmed', 'not_required'], true)) {
        try {
            send_new_order_push_notifications($orderId, true);
        } catch (Throwable $e) {
            error_log('[sepay] Push notification failed for order ' . $orderId . ': ' . $e->getMessage());
        }
        try {
            $mailDetail = '';
            send_order_notification_by_id($orderId, $mailDetail);
        } catch (Throwable $e) {
            error_log('[sepay] Mail notification failed for order ' . $orderId . ': ' . $e->getMessage());
        }
    }

    return [
        'status' => 'confirmed',
        'order_id' => $orderId,
        'order_no' => $orderNo,
        'received' => htp_decimal($delta),
        'receipt_status' => $newStatus,
        'message' => 'Order payment confirmed successfully via SePay.'
    ];
}

/**
 * Process SePay payment for a Group Member Split Bill.
 * Acquires row lock on group_rooms first (parent table) to eliminate race conditions
 * when multiple group members transfer concurrently, then updates group_members.
 */
function sepay_process_member_payment(array $payload, int $memberId): array
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        sepay_fail('Transaction already active');
    }

    $sepayId = (int) ($payload['id'] ?? 0);
    $delta = htp_cents($payload['transferAmount'] ?? 0);
    $bankAccount = (string) ($payload['accountNumber'] ?? '');
    $referenceCode = (string) ($payload['referenceCode'] ?? '');
    $gateway = trim((string) ($payload['gateway'] ?? ''));
    $bankBrand = trim((string) ($payload['bank_brand_name'] ?? ''));
    if ($gateway === '') {
        $gateway = $bankBrand;
    } elseif ($bankBrand !== '' && sepay_normalize_bank($gateway) !== sepay_normalize_bank($bankBrand)) {
        sepay_fail('SePay bank identity fields do not match.');
    }
    $content = (string) ($payload['content'] ?? '');
    $subAccount = trim((string) ($payload['subAccount'] ?? ''));

    if ($delta <= 0) {
        sepay_fail('Invalid transfer amount');
    }

    $seedKey = 'sepay:' . $sepayId;

    // Check if event with this seed_key was already processed
    $chk = $pdo->prepare('SELECT id, room_id FROM group_payment_events WHERE seed_key = ?');
    $chk->execute([$seedKey]);
    $existing = $chk->fetch();
    if ($existing) {
        return [
            'status' => 'already_processed',
            'room_id' => (int) $existing['room_id'],
            'member_id' => $memberId,
            'message' => 'Member payment was already processed previously.'
        ];
    }

    $orderToNotify = null;
    $pdo->beginTransaction();
    try {
        // Step 1: Find member's room_id
        $stMem = $pdo->prepare('SELECT * FROM group_members WHERE id = ?');
        $stMem->execute([$memberId]);
        $memberRow = $stMem->fetch();
        if (!$memberRow) {
            sepay_fail('Group member not found: ' . $memberId);
        }
        $roomId = (int) $memberRow['room_id'];

        // Step 2: Acquire parent lock on group_rooms to serialize concurrent member webhooks!
        $room = hyg_one('SELECT * FROM group_rooms WHERE id = ? FOR UPDATE', [$roomId]);
        if (!$room) {
            sepay_fail('Group room not found: ' . $roomId);
        }

        $groupAccount = hyg_one('SELECT bank_name,account_number,account_name FROM transfer_group_accounts WHERE room_id=?', [$roomId]);
        if (!$groupAccount || !sepay_match_account($bankAccount, $gateway, [$groupAccount])) {
            sepay_fail('Received bank account does not match the group payment account.');
        }

        // Step 3: Lock group_members row
        $member = hyg_one('SELECT * FROM group_members WHERE id = ? AND room_id = ? FOR UPDATE', [$memberId, $roomId]);
        if (!$member) {
            sepay_fail('Group member not found in room: ' . $memberId);
        }

        // Check if member already paid
        $currentPaid = htp_cents($member['payment_received_amount'] ?? 0);
        $currentRequested = htp_cents($member['payment_requested_amount'] ?? 0);
        if ($member['payment_status'] === 'paid' && $currentPaid >= $delta && $delta > 0) {
            $pdo->commit();
            return [
                'status' => 'already_paid',
                'room_id' => $roomId,
                'member_id' => $memberId,
                'message' => 'Member has already paid.'
            ];
        }

        // Update member payment record
        $newReceived = $currentPaid + $delta;
        $confirmedAmount = $currentRequested > 0 ? $currentRequested : $delta;

        $pdo->prepare("UPDATE group_members SET payment_status = 'paid', payment_completed_at = COALESCE(payment_completed_at, UTC_TIMESTAMP()), payment_confirmed_at = UTC_TIMESTAMP(), payment_confirmed_by_member_id = 0, payment_confirmed_amount = ?, payment_received_amount = ?, payment_invalidated_at = NULL, payment_invalidation_reason = '' WHERE id = ?")
            ->execute([htp_decimal($confirmedAmount), htp_decimal($newReceived), $memberId]);

        $afterMember = hyg_one('SELECT * FROM group_members WHERE id = ?', [$memberId]);

        // Insert audit event in group_payment_events with seed_key
        hyg_payment_event($room, $member, 0, 'confirmed', htp_decimal($delta), 'SePay VietQR split bill auto confirmation.' . ($subAccount !== '' ? ' SubAccount: ' . mb_substr($subAccount, 0, 80) . '.' : '') . ' ' . mb_substr($content, 0, 100), $member, $afterMember, $seedKey);

        // Increment room revision
        $pdo->prepare('UPDATE group_rooms SET revision = revision + 1, updated_at = UTC_TIMESTAMP() WHERE id = ?')
            ->execute([$roomId]);

        // Check if all payers are now confirmed
        $allMembers = hyg_all('SELECT * FROM group_members WHERE room_id = ? FOR UPDATE', [$roomId]);
        $cart = hyg_cart($room);
        $coupon = promotion_group_record($room);
        $summary = hyg_payment_summary($room, $cart, $coupon);

        if (hyg_payments_confirmed($summary) && !empty($room['order_id'])) {
            // If the group order was already created, update receipt and trigger kitchen notification
            $orderRow = hyg_one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [(int) $room['order_id']]);
            if ($orderRow) {
                $pdo->prepare("UPDATE transfer_order_receipts SET status = 'confirmed', received_amount = amount, confirmed_at = UTC_TIMESTAMP() WHERE order_id = ?")
                    ->execute([(int) $orderRow['id']]);
                $orderToNotify = (int) $orderRow['id'];
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    if ($orderToNotify) {
        try {
            send_new_order_push_notifications($orderToNotify, true);
            $mailDetail = '';
            send_order_notification_by_id($orderToNotify, $mailDetail);
        } catch (Throwable $e) {
            error_log('[sepay] Notification error for group order ' . $orderToNotify . ': ' . $e->getMessage());
        }
    }

    return [
        'status' => 'confirmed',
        'room_id' => $roomId,
        'member_id' => $memberId,
        'member_name' => $member['nickname'] ?? '',
        'received' => htp_decimal($delta),
        'all_confirmed' => !empty($summary) && hyg_payments_confirmed($summary),
        'message' => 'Member payment confirmed successfully via SePay.'
    ];
}
