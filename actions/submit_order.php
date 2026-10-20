<?php
require __DIR__ . '/../lib/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
require_once __DIR__ . '/../lib/promotion_views.php';
require_once __DIR__ . '/../lib/customer_checkout.php';
header('Cache-Control: private, no-store, max-age=0');
function hcq_restore_existing(string $token, string $context, string $group): void
{
    $request = hcq_lookup($token, $context);
    if (!$request)
        return;
    $saved = hcq_existing_order($token, $context);
    if (!$saved)
        return;
    if ($context === 'ordinary' && $request['state'] !== 'saved') {
        $cart = $_SESSION['cart'] ?? [];
        if ($cart && hash_equals($request['signature'], promotion_quote_signature($cart))) {
            $_SESSION['cart'] = [];
            clear_coupon();
            unset($_SESSION['promotion_mode']);
        }
    }
    hcq_saved($token, $saved['order_no']);
    unset($_SESSION['checkout_drafts'][$context], $_SESSION['checkout_errors'][$context]);
    $_SESSION['last_order_no'] = $saved['order_no'];
    header('Location: ' . ($group !== '' ? hyg_room_url($group) : base_url('payment.php?order=' . rawurlencode($saved['order_no']))), true, 303);
    exit;
}
try {
    $groupId = !empty($_POST['group_id']) ? hyg_code($_POST['group_id']) : '';
} catch (Throwable $e) {
    hyg_error_page($e);
}
$checkoutContext = hcq_context($groupId);
$csrf = $_POST['csrf'] ?? null;
$expectedCsrf = $_SESSION['_csrf'] ?? null;
if (!is_string($csrf) || !is_string($expectedCsrf) || $expectedCsrf === '' || !hash_equals($expectedCsrf, $csrf))
    hcq_fail($checkoutContext, $groupId, t('csrf_expired'));
$postedToken = $_POST['checkout_token'] ?? null;
$checkoutRequest = hcq_lookup($postedToken, $checkoutContext);
if ($postedToken !== null && !$checkoutRequest)
    hcq_fail($checkoutContext, $groupId, htp('changed'));
if ($checkoutRequest)
    try {
        hcq_restore_existing($postedToken, $checkoutContext, $groupId);
    } catch (Throwable $e) {
        hcq_fail($checkoutContext, $groupId, htp_ux('unknown'));
    }
if (hcq_outcome($postedToken, $checkoutContext)) {
    try {
        $saved = htp_customer_order($checkoutRequest['order_no']);
        hcq_saved($postedToken, $saved['order_no']);
        header('Location: ' . base_url('payment.php?order=' . rawurlencode($saved['order_no'])), true, 303);
        exit;
    } catch (Throwable $e) {
        hcq_fail($checkoutContext, $groupId, htp_ux('unknown'));
    }
}
$groupCheckout = null;
$cart = $_SESSION['cart'] ?? [];
if (!$groupId && !$cart)
    redirect(base_url('index.php'));

$orderMember = hym_current_member();
$orderMemberId = $orderMember ? (int) $orderMember['id'] : null;

$addressMode = trim((string) ($_POST['address_mode'] ?? 'new'));
if ($addressMode === 'saved') {
    if (!$orderMember || trim((string) ($orderMember['delivery_address'] ?? '')) === '') {
        hcq_fail($checkoutContext, $groupId, sepay_t('addr_err_saved_empty'));
    }
    // Bỏ qua delivery_address trong POST, tự đọc members.delivery_address theo session
    $_POST['address'] = trim((string) $orderMember['delivery_address']);
} else {
    $addressMode = 'new';
    $rawAddress = trim((string) ($_POST['address'] ?? $_POST['delivery_address'] ?? ''));
    $cleanAddress = preg_replace('/\s+/', ' ', $rawAddress);
    $_POST['address'] = mb_substr($cleanAddress, 0, 255, 'UTF-8');
}

$draft = hcq_capture($checkoutContext, $_POST);
$values = $draft['values'];
if ($groupId === '' && $values['payment_method'] === 'split') {
    $_SESSION['checkout_drafts'][$checkoutContext]['errors']['payment_method'] = 'incomplete_data';
    $draft['errors']['payment_method'] = 'incomplete_data';
}
if ($draft['errors'])
    hcq_fail($checkoutContext, $groupId, htp_ux('draft_error'));

// Server-side validation: Kiểm tra ward_code có thuộc province_code không từ file JSON cục bộ
function hcq_location_index(string $path): ?array
{
    static $index = null;
    if ($index !== null) {
        return $index;
    }
    if (!is_file($path)) {
        error_log("Thiếu file địa chỉ: $path");
        return null;
    }
    try {
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        error_log('JSON địa chỉ lỗi: ' . $e->getMessage());
        return null;
    }
    $built = [];
    foreach ($data as $p) {
        $pc = (string) ($p['code'] ?? '');
        $built[$pc] = ['name' => (string) ($p['name'] ?? ''), 'wards' => []];
        foreach (($p['wards'] ?? []) as $w) {
            $built[$pc]['wards'][(string) $w['code']] = (string) $w['name'];
        }
    }
    return $index = $built;
}

// Chỉ bắt buộc chọn Tỉnh / Phường đối với địa chỉ mới (mode=new)
if ($addressMode !== 'saved') {
    $provinceCode = trim((string) ($_POST['province_code'] ?? ''));
    $wardCode     = trim((string) ($_POST['ward_code'] ?? ''));
    $provinceName = trim((string) ($_POST['province_name'] ?? ''));
    $wardName     = trim((string) ($_POST['ward_name'] ?? ''));

    // 1) Bắt buộc phải chọn đủ Tỉnh và Phường
    if ($provinceCode === '' || $wardCode === '') {
        hcq_fail($checkoutContext, $groupId, sepay_t('addr_err_prov_ward_req'));
    }

    // 2) Lỗi hệ thống tách khỏi lỗi dữ liệu của khách
    $index = hcq_location_index(__DIR__ . '/../assets/data/vietnam-provinces.json');
    if ($index === null) {
        hcq_fail($checkoutContext, $groupId, sepay_t('addr_err_system_address'));
    }

    // 3) Kiểm tra ward thuộc province
    $province = $index[$provinceCode] ?? null;
    if ($province === null || !isset($province['wards'][$wardCode])) {
        hcq_fail($checkoutContext, $groupId, sepay_t('addr_err_ward_mismatch'));
    }

    // 4) Lưu tên chuẩn từ JSON, không tin tên do client gửi
    $provinceName = $province['name'];
    $wardName     = $province['wards'][$wardCode];
}

$name = $values['customer_name'];
$phone = $values['phone'];
$address = preg_replace('/\s+/', ' ', trim((string) $values['address']));
if ($address === '') {
    hcq_fail($checkoutContext, $groupId, sepay_t('addr_err_empty'));
}
$pay = $values['payment_method'];
$invoice = (int) $values['invoice_required'];
$orderNote = $values['order_note'];
$companyName = $values['invoice_company_name'];
$taxId = $values['invoice_tax_id'];
$companyAddress = $values['invoice_company_address'];
$companyRepresentative = $values['invoice_company_representative'];
$companyPhone = $values['invoice_company_phone'];
$invoiceEmail = $values['invoice_email'];
if (!$invoice)
    $companyName = $taxId = $companyAddress = $companyRepresentative = $companyPhone = $invoiceEmail = '';

// A locked group may already have earned eligibility before the activity is disabled.
$walletToken = customer_game_wallet_token(true);
$orderMember = hym_current_member();
$orderMemberId = $orderMember ? (int) $orderMember['id'] : null;

$sessionOrders = $_SESSION['customer_payment_orders'] ?? [];
if (!is_array($sessionOrders)) $sessionOrders = [];
if (!empty($_SESSION['last_order_no'])) $sessionOrders[] = $_SESSION['last_order_no'];

// Kiểm tra trước: Mỗi người dùng (user) chỉ được phép có tối đa DUY NHẤT 1 đơn hàng chờ thanh toán (PENDING_PAYMENT)
// Áp dụng cho cả Member đã đăng nhập và Khách vãng lai qua SĐT/Session
try {
    customer_assert_no_pending_order($orderMemberId, $phone, $sessionOrders, false);
} catch (\Lib\Exceptions\ExistingPendingOrderException $e) {
    hcq_fail($checkoutContext, $groupId, $e->getMessage());
}

if ($groupId === '') {
    $given = $_POST['promotion_signature'] ?? null;
    $choice = promotion_cart_choice($cart);
    if (!$choice['valid'] || !is_string($given) || !hash_equals(promotion_quote_signature($cart), $given)) {
        $_SESSION['coupon_error'] = pt('selection_changed');
        header('Location: ' . base_url('cart.php'), true, 303);
        exit;
    }
    if ($choice['mode'] === 'reward' && (float) cart_coupon_summary($cart)['discount'] <= 0) {
        $_SESSION['coupon_error'] = pt('reward_no_discount');
        header('Location: ' . base_url('cart.php'), true, 303);
        exit;
    }
}
if ($groupId !== '') {
    try {
        hyg_csrf();
        hyg_schema();
    } catch (Throwable $e) {
        hyg_error_page($e);
    }
}
$requestSignature = $groupId !== '' ? (is_string($_POST['group_signature'] ?? null) ? $_POST['group_signature'] : '') : promotion_quote_signature($cart);
$checkoutToken = hcq_unknown_token($checkoutContext) ?? ($checkoutRequest ? $postedToken : hcq_request($checkoutContext, $requestSignature));
$commitStarted = false;
$no = '';
try {
    hcq_restore_existing($checkoutToken, $checkoutContext, $groupId);
} catch (Throwable $e) {
    hcq_fail($checkoutContext, $groupId, htp_ux('unknown'));
}
// Old rendered forms may omit the token; their fallback must obey the same uncertain-outcome guard.
if ($retry = hcq_outcome($checkoutToken, $checkoutContext)) {
    try {
        $saved = htp_customer_order($retry['order_no']);
        hcq_saved($checkoutToken, $saved['order_no']);
        header('Location: ' . base_url('payment.php?order=' . rawurlencode($saved['order_no'])), true, 303);
        exit;
    } catch (Throwable $e) {
        hcq_fail($checkoutContext, $groupId, htp_ux('unknown'));
    }
}
if ($checkoutRequest && !hash_equals($checkoutRequest['signature'], $requestSignature))
    hcq_fail($checkoutContext, $groupId, htp('changed'));
$no = order_no();
hcq_saved($checkoutToken, $no, 'unknown');
// Persist the reservation before SQL. A process crash must leave an unknown result, never an open retry.
if (session_status() !== PHP_SESSION_ACTIVE || !session_write_close())
    hcq_fail($checkoutContext, $groupId, htp_ux('unknown'));
if (!@session_start())
    hcq_fail($checkoutContext, $groupId, htp_ux('unknown'));
$reopenedRequest = hcq_lookup($checkoutToken, $checkoutContext);
if (!$reopenedRequest || ($reopenedRequest['state'] ?? '') !== 'unknown' || ($reopenedRequest['order_no'] ?? '') !== $no || (int) ($_SESSION['member_id'] ?? 0) !== (int) ($orderMemberId ?? 0))
    hcq_fail($checkoutContext, $groupId, htp_ux('unknown'));
$pdo = db();
try {
    $pdo->beginTransaction();
    // Bắt buộc: Ràng buộc duy nhất 1 đơn pending - Kiểm tra có Lock cho cả Member và Khách vãng lai
    customer_assert_no_pending_order($orderMemberId, $phone, $sessionOrders, true);
    if ($groupId !== '') {
        $groupCheckout = hyg_checkout($groupId, true);
        if ($groupCheckout['already']) {
            $existing = hyg_one('SELECT order_no FROM orders WHERE id=?', [$groupCheckout['room']['order_id']]);
            $pdo->commit();
            if ($existing)
                hcq_saved($checkoutToken, $existing['order_no']);
            header('Location: ' . hyg_room_url($groupId), true, 303);
            exit;
        }
        // Payment reports/approvals change the revision; removing a member changes the signed quote.
        if (!is_string($_POST['group_signature'] ?? null) || !hash_equals((string) $groupCheckout['room']['lock_signature'], $_POST['group_signature']))
            hyg_fail('checkout_change');
        $cart = $groupCheckout['cart'];
        $coupon = $groupCheckout['coupon'];
        $choice = promotion_room_choice($groupCheckout['room'], $cart);
        $groupPayment = hyg_payment_summary($groupCheckout['room'], $cart, $coupon);
        $expectedPay = htp_group_order_method($groupPayment);
        if ($pay !== $expectedPay)
            hyg_fail('checkout_change');
    } else {
        $choice = promotion_cart_choice($cart);
        promotion_validate_choice($choice['mode'], $choice['code']);
        $coupon = cart_coupon_summary($cart);
        if ($choice['mode'] === 'reward' && (float) $coupon['discount'] <= 0)
            throw new RuntimeException('coupon_no_discount');
    }
    $subtotal = cart_subtotal($cart);
    $discount = (float) $coupon['discount'];
    $total = max(0, $subtotal - $discount);
    $couponCode = $coupon['valid'] ? $coupon['code'] : '';
    $eligibleDrinkQty = 0;
    foreach ($cart as $r) {
        if (!empty($r['product_id']) && empty($r['standalone_topping_id']))
            $eligibleDrinkQty += (int) $r['qty'];
    }
    $expectedGameChances = $choice['mode'] === 'draw' && $couponCode === '' && $discount <= 0 ? game_saved_chance_count($eligibleDrinkQty, (int) $choice['expected_chances']) : 0;
    $st = $pdo->prepare('INSERT INTO orders(order_no,customer_name,phone,address,payment_method,invoice_required,invoice_company_name,invoice_tax_id,invoice_company_address,invoice_company_representative,invoice_company_phone,invoice_email,order_note,coupon_code,subtotal_amount,discount_amount,total_amount,game_wallet_token,group_room_id,group_title,member_id,promotion_mode,game_chances_expected,checkout_request_hash) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $st->execute([$no, $name, $phone, $address, $pay, $invoice ? 1 : 0, $companyName ?: null, $taxId ?: null, $companyAddress ?: null, $companyRepresentative ?: null, $companyPhone ?: null, $invoiceEmail ?: null, $orderNote ?: null, $couponCode ?: null, $subtotal, $discount, $total, $choice['mode'] === 'draw' ? ($walletToken ?: null) : null, $groupCheckout['room']['id'] ?? null, $groupCheckout['room']['title'] ?? null, $orderMemberId, $choice['mode'], $expectedGameChances, hash('sha256', $checkoutToken)]);
    $oid = (int) $pdo->lastInsertId();
    $st2 = $pdo->prepare('INSERT INTO order_items(order_id,product_id,standalone_topping_id,product_name,cup_size,base_price,ice_choice,sugar_choice,toppings_text,unit_price,discount_amount,qty,line_total,group_member_name,group_member_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($cart as $key => $r) {
        $tops = implode('、', array_map(fn($top) => ($top['display_name'] ?? $top['name']) . '(+' . currency() . money($top['price']) . ')', $r['toppings'] ?? []));
        $lineDiscount = (float) ($coupon['line_discounts'][(string) $key] ?? 0);
        $lineOriginal = (float) $r['unit_price'] * (int) $r['qty'];
        $productId = !empty($r['product_id']) ? (int) $r['product_id'] : null;
        $standaloneId = !empty($r['standalone_topping_id']) ? (int) $r['standalone_topping_id'] : null;
        $st2->execute([$oid, $productId, $standaloneId, $r['name'], $r['cup_size'] ?? null, $r['base_price'], $r['ice'] ?? '', $r['sugar'] ?? '', $tops, $r['unit_price'], $lineDiscount, $r['qty'], max(0, $lineOriginal - $lineDiscount), $r['group_member_name'] ?? null, $r['group_member_id'] ?? null]);
    }
    if (($coupon['source'] ?? '') === 'game' && $couponCode !== '') {
        if (!mark_customer_coupon_used($couponCode, $oid))
            throw new RuntimeException('Coupon already used.');
    }
    if ($groupCheckout)
        hyg_finish($groupCheckout, $oid);
    $selectedStoreId = !empty($_POST['selected_store_id']) ? (int) $_POST['selected_store_id'] : null;
    if ($selectedStoreId > 0) {
        $stStore = $pdo->prepare('SELECT id, name, address, map_url FROM dispatch_stores WHERE id = ? AND enabled = 1');
        $stStore->execute([$selectedStoreId]);
        $storeRow = $stStore->fetch();
        if ($storeRow) {
            $upd = $pdo->prepare('UPDATE orders SET dispatch_store_id = ?, dispatch_store_name = ?, dispatch_store_address = ?, dispatch_store_map_url = ?, dispatch_assigned_at = UTC_TIMESTAMP(), dispatch_revision = 1 WHERE id = ?');
            $upd->execute([(int) $storeRow['id'], (string) $storeRow['name'], (string) $storeRow['address'], (string) $storeRow['map_url'], $oid]);
            $hist = $pdo->prepare('INSERT INTO dispatch_history(order_id, revision, store_id, name, address, map_url, admin_id, created_at) VALUES(?, 1, ?, ?, ?, ?, 0, UTC_TIMESTAMP())');
            $hist->execute([$oid, (int) $storeRow['id'], (string) $storeRow['name'], (string) $storeRow['address'], (string) $storeRow['map_url']]);
        }
    }
    htp_initialize_order($oid);
    // This server-generated reference also authorizes recovery if COMMIT loses its reply.
    if ($orderMemberId === null)
        htp_remember_customer_order($no);

    // Cập nhật địa chỉ mặc định vào hồ sơ thành viên nếu có yêu cầu
    $saveToProfile = !empty($_POST['save_to_profile']) && in_array((string) $_POST['save_to_profile'], ['1', 'true', 'on'], true);
    if ($orderMemberId !== null && $addressMode === 'new' && $saveToProfile && $address !== '') {
        $stUpdateMember = $pdo->prepare('UPDATE members SET delivery_address = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?');
        $stUpdateMember->execute([$address, $orderMemberId]);
    }

    $commitStarted = true;
    $pdo->commit();
} catch (Throwable $e) {
    if ($e instanceof \Lib\Exceptions\ExistingPendingOrderException) {
        try {
            if ($pdo->inTransaction())
                $pdo->rollBack();
        } catch (Throwable $ignored) {
        }
        hcq_saved($checkoutToken, '', 'open');
        hcq_fail($checkoutContext, $groupId, $e->getMessage());
    }
    if ($commitStarted && $no !== '') {
        if ($orderMemberId === null)
            htp_remember_customer_order($no);
        hcq_saved($checkoutToken, $no, 'unknown');
        try {
            if ($pdo->inTransaction())
                $pdo->rollBack();
        } catch (Throwable $ignored) {
        }
        hcq_fail($checkoutContext, $groupId, htp_ux('unknown'));
    }
    try {
        if ($pdo->inTransaction() && !$pdo->rollBack())
            throw new RuntimeException('rollback_failed');
        hcq_restore_existing($checkoutToken, $checkoutContext, $groupId);
    } catch (Throwable $ignored) {
        hcq_fail($checkoutContext, $groupId, htp_ux('unknown'));
    }
    hcq_saved($checkoutToken, '', 'open');
    if ($groupId !== '')
        hyg_error_page($e);
    hcq_fail($checkoutContext, $groupId, t('order_failed'));
}

// The order is committed. A notification failure must never turn it into a failed checkout.
hcq_saved($checkoutToken, $no);
unset($_SESSION['checkout_drafts'][$checkoutContext], $_SESSION['checkout_errors'][$checkoutContext]);
if ($groupId === '') {
    $_SESSION['cart'] = [];
    clear_coupon();
    unset($_SESSION['promotion_mode']);
}
$_SESSION['last_order_no'] = $no;
$_SESSION['last_order_expected_game_chances'] = $expectedGameChances;
$_SESSION['last_order_eligible_drinks'] = $eligibleDrinkQty;
$_SESSION['last_order_promotion'] = $choice;
$shouldNotifyNow = ($pay !== 'transfer' || $total <= 0);
if ($shouldNotifyNow) {
    try {
        $pushResult = send_new_order_push_notifications($oid, false);
    } catch (Throwable $e) {
        error_log('[checkout] Notification enqueue unavailable for committed order ' . $oid);
    }
}
$redirectUrl = ($groupId !== '')
    ? hyg_room_url($groupId)
    : ($pay === 'transfer' ? base_url('payment.php?order=' . rawurlencode($no)) : base_url('success.php'));
header('Location: ' . $redirectUrl, true, 303);
if (session_status() === PHP_SESSION_ACTIVE)
    session_write_close();
ignore_user_abort(true);
if (function_exists('fastcgi_finish_request'))
    fastcgi_finish_request();
// Locate the committed order after releasing the session; maps failure never fails checkout.
try {
    hds_recommend_order($oid);
} catch (Throwable $e) {
    error_log('[dispatch] Recommendation unavailable for order ' . $oid);
}
if ($shouldNotifyNow) {
    try {
        hqp_process_queue(20, 9, $oid);
    } catch (Throwable $e) {
        hqp_log('post_order_push_error', $e);
    }
    try {
        $mailDetail = '';
        send_order_notification_by_id($oid, $mailDetail);
    } catch (Throwable $e) {
        hqp_log('post_order_mail_error', $e);
    }
}
exit;
