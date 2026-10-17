<?php

/** Shared customer/staff payment UI. Every value is escaped; receipt status is not order status. */
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
  http_response_code(404);
  exit;
}
function htp(string $key, array $vars = []): string
{
  $copy = [
    'vi' => [
      'headquarters' => 'Tài khoản cửa hàng / trụ sở',
      'store_account' => 'Thông tin chuyển khoản của chi nhánh',
      'store_help' => 'Chỉ hiển thị sau khi có đơn và nhân viên chỉ định chi nhánh. Chi nhánh đề xuất không tự trở thành nơi nhận tiền.',
      'enabled' => 'Sử dụng tài khoản chi nhánh',
      'remove_qr' => 'Xóa ảnh QR đã lưu',
      'qr_help' => 'JPG / PNG / WEBP, tối đa 2MB, kích thước tối đa 4096×4096.',
      'host_collect' => 'Trưởng nhóm thu tiền hộ',
      'host_help' => 'Thành viên trả cho trưởng nhóm → trưởng nhóm xác nhận tiền đã thu → sau khi gửi đơn, trưởng nhóm trả toàn bộ tiền đơn cho cửa hàng. Tài khoản được cố định khi tạo nhóm.',
      'host_bank' => 'Ngân hàng của trưởng nhóm',
      'host_number' => 'Số tài khoản của trưởng nhóm',
      'host_name' => 'Tên chủ tài khoản của trưởng nhóm',
      'host_qr' => 'QR nhận tiền của trưởng nhóm',
      'pay_host' => 'Chuyển tiền vào tài khoản trưởng nhóm bên dưới, rồi báo đã trả và chờ trưởng nhóm xác nhận.',
      'cash_host' => 'Giao tiền mặt cho trưởng nhóm, rồi báo đã trả và chờ xác nhận.',
      'host_contribution' => 'Đây là khoản tự đặt và trả hộ của bạn. Xác nhận đã chuẩn bị khoản này; chưa phải xác nhận cửa hàng đã nhận tiền.',
      'contribution_confirm' => 'Đã chuẩn bị phần của tôi và phần trả hộ',
      'contribution_prompt' => 'Bạn đã chuẩn bị phần của mình và phần trả hộ? Cửa hàng chưa được đánh dấu đã nhận tiền.',
      'collected_hint' => 'Thanh toán thành viên là tiền trưởng nhóm đã thu hoặc chuẩn bị, không phải tiền cửa hàng đã nhận.',
      'collection_before_order' => 'Trưởng nhóm thu tiền trước khi gửi đơn; sau khi có đơn sẽ có thông tin chi nhánh nhận tiền.',
      'merchant_title' => 'Thanh toán cho cửa hàng',
      'merchant_total' => 'Tổng tiền cần trả cửa hàng',
      'merchant_host' => 'Trưởng nhóm thanh toán toàn bộ số tiền dưới đây, không chỉ phần tự đặt.',
      'pending' => 'Chưa xác nhận cửa hàng đã nhận tiền',
      'requested' => 'Đã báo thanh toán · chờ cửa hàng xác nhận',
      'confirmed' => 'Cửa hàng đã xác nhận nhận tiền',
      'report' => 'Tôi đã thanh toán cho cửa hàng',
      'report_prompt' => 'Bạn đã chuyển đúng số tiền tới đúng tài khoản (hoặc đã giao tiền mặt)? Thao tác này chỉ báo thanh toán, không tự xác nhận tiền đã đến.',
      'confirmed_hint' => 'Đơn đã gửi không đồng nghĩa tiền đã vào tài khoản. Chỉ nhân viên xác nhận sau khi kiểm tra thực tế.',
      'refresh' => 'Cập nhật thông tin thanh toán',
      'payment_link' => 'Xem / cập nhật thanh toán',
      'reference' => 'Nội dung chuyển khoản: {number}',
      'cash_help' => 'Giao tiền mặt theo hướng dẫn của cửa hàng; chỉ báo sau khi đã giao tiền.',
      'missing' => 'Cửa hàng sẽ cung cấp thông tin chuyển khoản khi xác nhận đơn. Vui lòng đợi thông tin trước khi chuyển tiền.',
      'recipient_frozen' => 'Giữ nguyên nơi nhận tiền đã báo, ngay cả khi tài khoản hoặc chi nhánh giao hàng thay đổi. Không chuyển lần nữa.',
      'staff_confirm' => 'Xác nhận cửa hàng đã nhận tiền',
      'staff_prompt' => 'Bạn đã kiểm tra ngân hàng / tiền mặt thực nhận đủ số tiền? Xác nhận này độc lập với trạng thái đơn.',
      'staff_saved' => 'Đã xác nhận nhận tiền.',
      'report_saved' => 'Đã báo thanh toán, đang chờ cửa hàng kiểm tra.',
      'confirmed_by' => 'Nhân viên xác nhận',
      'reported_at' => 'Thời gian báo',
      'confirmed_at' => 'Thời gian xác nhận',
      'legacy' => 'Đơn cũ / nhóm trả trực tiếp cửa hàng: liên hệ cửa hàng, không yêu cầu trả lại tổng tiền.',
      'invalid_account' => 'Thông tin tài khoản không hợp lệ.',
      'account_required' => 'Điền ngân hàng, số tài khoản và tên chủ tài khoản.',
      'invalid_image' => 'Chỉ chấp nhận ảnh QR JPG, PNG hoặc WEBP hợp lệ.',
      'image_size' => 'Ảnh QR phải nhỏ hơn hoặc bằng 2MB.',
      'changed' => 'Thông tin thanh toán đã thay đổi. Hãy tải lại để đối chiếu. Nếu đã trả, không trả lần nữa; liên hệ cửa hàng để đối soát tài khoản nhận tiền ban đầu.',
      'forbidden' => 'Bạn không có quyền xem hoặc thay đổi thanh toán này.',
      'not_found' => 'Không tìm thấy thanh toán.',
      'cancelled' => 'Đơn đã hủy. Không chuyển thêm tiền; liên hệ cửa hàng để đối soát / hoàn tiền.',
      'storage' => 'Chưa thể lưu thanh toán. Vui lòng thử lại.',
      'recipient' => 'Nơi nhận tiền',
    ],
    'en' => [
      'headquarters' => 'Store / headquarters account',
      'store_account' => 'Branch transfer details',
      'store_help' => 'Shown after an order exists and staff assign the branch. A recommendation never selects the payment recipient.',
      'enabled' => 'Use this branch account',
      'remove_qr' => 'Remove saved QR',
      'qr_help' => 'JPG / PNG / WEBP, up to 2MB and 4096×4096 pixels.',
      'host_collect' => 'Host collects member payments',
      'host_help' => 'Members pay the host → the host confirms collection → after submitting the order, the host pays the entire order to the store. The account is fixed when the group is created.',
      'host_bank' => 'Host bank',
      'host_number' => 'Host account number',
      'host_name' => 'Host account holder',
      'host_qr' => 'Host payment QR',
      'pay_host' => 'Transfer to the host account below, then report payment and wait for the host to confirm.',
      'cash_host' => 'Hand cash to the host, then report payment and wait for confirmation.',
      'host_contribution' => 'This is your own contribution and advances. Confirm funds are ready; this does not confirm store receipt.',
      'contribution_confirm' => 'My contribution and advances are ready',
      'contribution_prompt' => 'Have you prepared your own contribution and advances? The store is not marked as paid.',
      'collected_hint' => 'Member confirmations record funds collected or prepared by the host, not funds received by the store.',
      'collection_before_order' => 'The host collects before submitting. Branch payment details are available after the order exists.',
      'merchant_title' => 'Payment to the store',
      'merchant_total' => 'Total payable to the store',
      'merchant_host' => 'The host pays the entire amount below, not just their own contribution.',
      'pending' => 'Store receipt not yet confirmed',
      'requested' => 'Payment reported · awaiting store confirmation',
      'confirmed' => 'Store receipt confirmed',
      'report' => 'I have paid the store',
      'report_prompt' => 'Have you paid the exact amount to this recipient (or handed over cash)? Reporting does not automatically confirm receipt.',
      'confirmed_hint' => 'Order submitted does not mean money received. Staff confirm only after checking the actual transfer or cash.',
      'refresh' => 'Refresh payment details',
      'payment_link' => 'View / update payment',
      'reference' => 'Transfer reference: {number}',
      'cash_help' => 'Hand over cash as instructed by the store. Report only after handing it over.',
      'missing' => 'The store will provide transfer details when confirming the order. Wait for those details before transferring.',
      'recipient_frozen' => 'The reported recipient remains unchanged when accounts or delivery branches change. Do not pay again.',
      'staff_confirm' => 'Confirm the store received payment',
      'staff_prompt' => 'Have you verified the full amount was actually received? This confirmation is independent from order status.',
      'staff_saved' => 'Store receipt confirmed.',
      'report_saved' => 'Payment reported; awaiting store verification.',
      'confirmed_by' => 'Confirmed by staff',
      'reported_at' => 'Reported at',
      'confirmed_at' => 'Confirmed at',
      'legacy' => 'Legacy / direct-store group order: contact the store. Do not pay the total again.',
      'invalid_account' => 'Invalid account details.',
      'account_required' => 'Enter the bank, account number, and account holder.',
      'invalid_image' => 'Upload a valid JPG, PNG or WEBP QR image only.',
      'image_size' => 'The QR image must be no larger than 2MB.',
      'changed' => 'Payment details changed. Refresh to verify. If you already paid, do not pay again; contact the store to reconcile the original recipient.',
      'forbidden' => 'You cannot access or change this payment.',
      'not_found' => 'Payment not found.',
      'cancelled' => 'Order cancelled. Do not pay more; contact the store for reconciliation or a refund.',
      'storage' => 'Unable to save payment. Try again.',
      'recipient' => 'Payment recipient',
    ],
    'zh' => [
      'headquarters' => '店家／總部收款帳戶',
      'store_account' => '門市轉帳資訊',
      'store_help' => '訂單成立且由店員確認指派門市後才提供；建議門市不會自動成為收款對象。',
      'enabled' => '啟用門市收款帳戶',
      'remove_qr' => '移除已保存 QR',
      'qr_help' => 'JPG／PNG／WEBP，最大 2MB，尺寸最大 4096×4096。',
      'host_collect' => '團主代收團員付款',
      'host_help' => '團員付款給團主 → 團主確認代收 → 送出訂單後團主再支付全團款給店家。帳戶在開團時固定；團員各付請使用分開結帳，一起結帳表示由團主代付整團。',
      'host_bank' => '團主收款銀行',
      'host_number' => '團主收款帳號',
      'host_name' => '團主收款戶名',
      'host_qr' => '團主收款 QR',
      'pay_host' => '請轉帳至下方團主帳戶，再回報付款，等待團主確認代收。',
      'cash_host' => '請將現金交給團主，再回報付款並等待團主確認。',
      'host_contribution' => '這是團主自購與墊付的出資額。請確認已備妥；此操作不代表店家已收款。',
      'contribution_confirm' => '已備妥自己的出資與墊付款',
      'contribution_prompt' => '已備妥自己的出資與墊付款嗎？此操作不會將店家款項標為已收款。',
      'collected_hint' => '團員付款確認只表示團主已代收或備妥款項，不代表店家已收到全團款。',
      'collection_before_order' => '團主先代收，再送出訂單；訂單成立後才提供已確認門市的收款資訊。',
      'merchant_title' => '支付店家款項',
      'merchant_total' => '店家應收全團／訂單總額',
      'merchant_host' => '團主需支付下方全團總額，不是只支付自己的出資額。',
      'pending' => '店家尚未確認收款',
      'requested' => '已回報付款・待店家核款',
      'confirmed' => '店家已確認收款',
      'report' => '我已支付店家款項',
      'report_prompt' => '已將正確金額支付給此收款對象（或交付現金）嗎？此操作只回報付款，不會自動確認入帳。',
      'confirmed_hint' => '訂單已送出不代表款項已入帳；只有店員核對實際轉帳／現金後才確認收款。',
      'refresh' => '更新付款資訊',
      'payment_link' => '查看／更新付款',
      'reference' => '轉帳備註：{number}',
      'cash_help' => '請依店家指示交付現金；實際交付後再回報。',
      'missing' => '店家會在確認訂單時另行提供轉帳資訊，請取得資訊後再轉帳。',
      'recipient_frozen' => '保留已回報的收款對象，不受帳戶或配送門市變更影響；請勿重複付款。',
      'staff_confirm' => '確認店家已收到款項',
      'staff_prompt' => '已核對銀行／現金實際收到完整金額嗎？收款確認與訂單狀態分開記錄。',
      'staff_saved' => '已確認店家收款。',
      'report_saved' => '已回報付款，等待店家核對。',
      'confirmed_by' => '確認店員',
      'reported_at' => '回報時間',
      'confirmed_at' => '核款時間',
      'legacy' => '舊版／直接付店家的團購：請聯絡店家核對，不會要求再支付全團總額。',
      'invalid_account' => '收款資訊格式不正確。',
      'account_required' => '請填寫銀行、帳號與戶名。',
      'invalid_image' => '僅接受有效的 JPG、PNG 或 WEBP 收款 QR 圖片。',
      'image_size' => '收款 QR 圖片不得超過 2MB。',
      'changed' => '付款資訊已改變，請重新載入後確認。若已付款，請勿重複付款，請聯繫店家核對原收款帳戶。',
      'forbidden' => '您沒有查看或變更此付款的權限。',
      'not_found' => '找不到付款資料。',
      'cancelled' => '訂單已取消，請勿再付款；已付款請聯繫店家對帳或退款。',
      'storage' => '暫時無法保存付款資料，請稍後再試。',
      'recipient' => '收款對象',
    ],
  ];
  $extra = [
    'vi' => ['order_first' => 'Gửi đơn trước. Sau đó dùng trang thanh toán của đơn để xác nhận nơi nhận tiền và mã đơn rồi mới chuyển khoản. Nếu chưa có tài khoản, cửa hàng sẽ cung cấp khi xác nhận đơn.', 'not_required' => 'Không cần thanh toán (tổng tiền bằng 0)', 'funds_ready' => 'Đã chuẩn bị tiền của trưởng nhóm', 'collected' => 'Trưởng nhóm đã xác nhận thu tiền', 'contribution' => 'Phần tự đặt / trả hộ của trưởng nhóm'],
    'en' => ['order_first' => 'Submit the order first. Then verify its recipient and order reference on the payment page before transferring. If no account is configured, the store will provide details when confirming.', 'not_required' => 'No payment required (zero total)', 'funds_ready' => 'Host funds prepared', 'collected' => 'Host collection confirmed', 'contribution' => 'Host contribution / advances'],
    'zh' => ['order_first' => '請先送出訂單，取得訂單編號並在訂單付款頁確認收款對象後再轉帳；若尚未設定帳戶，店家會在確認訂單時另行提供資訊。', 'not_required' => '無需付款（訂單金額為零）', 'funds_ready' => '團主出資已備妥', 'collected' => '團主已確認代收', 'contribution' => '團主自購／墊付出資'],
  ];
  foreach ($extra as $lang => $values)
    $copy[$lang] = array_merge($copy[$lang], $values);
  $text = $copy[current_lang()][$key] ?? $copy['vi'][$key] ?? $key;
  foreach ($vars as $k => $v)
    $text = str_replace('{' . $k . '}', (string) $v, $text);
  return $text;
}
function htp_ux(string $key): string
{
  $copy = [
    'vi' => ['copy' => 'Sao chép', 'copied' => 'Đã sao chép.', 'copy_manual' => 'Không thể sao chép tự động. Chọn và sao chép nội dung bên dưới.', 'qr_open' => 'Phóng to QR', 'qr_save' => 'Lưu ảnh QR', 'qr_close' => 'Đóng', 'qr_verify' => 'Kiểm tra ngân hàng, người nhận, số tiền và mã đơn trong ứng dụng ngân hàng trước khi xác nhận.', 'live' => 'Thông tin thanh toán được cập nhật khi bạn quay lại trang.', 'offline' => 'Chưa cập nhật được. Không chuyển thêm tiền; dùng nút cập nhật hoặc liên hệ cửa hàng.', 'changed' => 'Thông tin hoặc trạng thái thanh toán đã thay đổi. Kiểm tra lại bên dưới. Nếu đã trả, không trả lần nữa.', 'session_lost' => 'Phiên truy cập đã hết hoặc bạn không phải chủ đơn. Đăng nhập tài khoản đặt đơn hoặc quay lại cùng trình duyệt đã đặt đơn. Không chuyển tiền lần nữa.', 'login' => 'Đăng nhập và quay lại đơn', 'contact' => 'Gọi chi nhánh đã nhận đơn', 'same_session' => 'Các đơn từ phiên này', 'guest_note' => 'Giữ trang này và quay lại cùng trình duyệt. Đăng nhập/đăng xuất sẽ xóa quyền xem đơn khách vãng lai của phiên này.', 'draft_error' => 'Kiểm tra các trường được đánh dấu. Thông tin bạn đã nhập được giữ lại trong phiên này.', 'submitting' => 'Đang gửi đơn…', 'unknown' => 'Chưa xác định được kết quả gửi đơn. Không gửi lại hoặc thanh toán lần nữa. Dùng liên kết dưới đây để kiểm tra đơn ban đầu hoặc liên hệ cửa hàng.', 'check_order' => 'Kiểm tra đơn ban đầu', 'payment_method' => 'Phương thức thanh toán', 'reconcile' => 'Số tiền đã được ghi nhận hoặc cần đối soát. Không tự chuyển thêm tiền; liên hệ cửa hàng để xác nhận chênh lệch.', 'not_required_hint' => 'Đơn này không cần thanh toán.', 'updated' => 'Đã cập nhật thông tin thanh toán.', 'report_again' => 'Bạn đã báo thanh toán cho đơn này. Không cần báo hoặc trả lần nữa.'],
    'en' => ['copy' => 'Copy', 'copied' => 'Copied.', 'copy_manual' => 'Automatic copy is unavailable. Select and copy the text below.', 'qr_open' => 'Enlarge QR', 'qr_save' => 'Save QR image', 'qr_close' => 'Close', 'qr_verify' => 'Verify the bank, recipient, amount and order reference in your banking app before confirming.', 'live' => 'Payment details refresh when you return to this page.', 'offline' => 'Unable to refresh. Do not pay again; use refresh or contact the store.', 'changed' => 'Payment details or status changed. Check below. If you already paid, do not pay again.', 'session_lost' => 'Access expired or this order belongs to another customer. Sign in to the ordering account or return in the browser used to order. Do not pay again.', 'login' => 'Sign in and return to the order', 'contact' => 'Call the assigned branch', 'same_session' => 'Orders from this session', 'guest_note' => 'Keep this page and return in the same browser. Signing in or out clears access to this session’s guest orders.', 'draft_error' => 'Check the marked fields. Your entries are retained in this session.', 'submitting' => 'Submitting order…', 'unknown' => 'The submission result is uncertain. Do not submit or pay again. Use the link below to check the original order or contact the store.', 'check_order' => 'Check the original order', 'payment_method' => 'Payment method', 'reconcile' => 'Funds were recorded or need reconciliation. Do not transfer more on your own; contact the store to verify the difference.', 'not_required_hint' => 'No payment is needed for this order.', 'updated' => 'Payment details refreshed.', 'report_again' => 'Payment was already reported for this order. You do not need to report or pay again.'],
    'zh' => ['copy' => '複製', 'copied' => '已複製。', 'copy_manual' => '無法自動複製，請選取並複製下方文字。', 'qr_open' => '放大 QR', 'qr_save' => '儲存 QR 圖片', 'qr_close' => '關閉', 'qr_verify' => '銀行 App 確認前，請核對銀行、收款人、金額與訂單備註。', 'live' => '返回此頁時會自動更新付款資訊。', 'offline' => '暫時無法更新，請勿再次付款；請按更新或聯繫店家。', 'changed' => '付款資訊或狀態已變更，請核對下方內容；若已付款，請勿重複付款。', 'session_lost' => '存取已逾時或此訂單屬於其他客戶。請登入下單帳號，或返回原下單瀏覽器；請勿再次付款。', 'login' => '登入並返回訂單', 'contact' => '聯繫已指派門市', 'same_session' => '本次瀏覽器的訂單', 'guest_note' => '請保留此頁並使用原瀏覽器返回；登入或登出會清除本次訪客訂單的存取權限。', 'draft_error' => '請檢查標示的欄位，已填資料保留在本次瀏覽器中。', 'submitting' => '正在送出訂單…', 'unknown' => '暫時無法確定送單結果，請勿再次送單或付款。請使用下方連結查詢原訂單，或聯繫店家核對。', 'check_order' => '查詢原訂單', 'payment_method' => '付款方式', 'reconcile' => '已有收款紀錄或款項待核對，請勿自行補轉；請聯繫店家確認差額。', 'not_required_hint' => '此訂單無需付款。', 'updated' => '付款資訊已更新。', 'report_again' => '此訂單已回報付款，不必再次回報或付款。'],
  ];
  return $copy[current_lang()][$key] ?? $copy['vi'][$key] ?? $key;
}
function htp_copy(string $value, string $label, ?string $display = null): string
{
  return '<span class="payment-copy"><b class="payment-copy-value">' . e($display ?? $value) . '</b><button class="btn small" type="button" data-payment-copy="' . e($value) . '" aria-label="' . e(htp_ux('copy') . ' ' . $label) . '">' . e(htp_ux('copy')) . '</button></span>';
}
function htp_contact(?array $order = null): string
{
  if (!$order || empty($order['dispatch_store_id']))
    return '';
  $store = hyg_one('SELECT phone FROM dispatch_stores WHERE id=?', [(int) $order['dispatch_store_id']]);
  $phone = trim((string) ($store['phone'] ?? ''));
  $dial = preg_replace('/[^0-9+]/', '', $phone);
  if ($phone === '' || !preg_match('/^\+?[0-9]{6,20}$/D', $dial))
    return '';
  return '<a class="btn" href="tel:' . e($dial) . '">' . e(htp_ux('contact')) . ' · ' . e($phone) . '</a>';
}
function htp_render_session_orders(): string
{
  $numbers = $_SESSION['customer_payment_orders'] ?? [];
  if (!is_array($numbers) || !$numbers)
    return '';
  $links = [];
  foreach (array_reverse(array_slice($numbers, -10)) as $number) {
    if (!is_string($number))
      continue;
    try {
      $order = htp_customer_order($number);
      $links[] = '<a class="btn small" href="' . e(base_url('payment.php?order=' . rawurlencode($number))) . '">' . e($number) . '</a>';
    } catch (Throwable $e) {
    }
  }
  if (!$links)
    return '';
  return '<nav aria-label="' . e(htp_ux('same_session')) . '"><strong>' . e(htp_ux('same_session')) . '</strong><div class="payment-session-orders">' . implode('', $links) . '</div><p class="muted">' . e(htp_ux('guest_note')) . '</p></nav>';
}
function htp_render_account(array $a): string
{
  ob_start(); ?>
  <article class="transfer-account-card payment-account-card">
    <div class="transfer-account-main">
      <?php if (!empty($a['recipient'])): ?><small><?= e(htp('recipient')) ?>：<?= e($a['recipient']) ?></small><?php endif; ?><strong><?= e($a['bank_name'] ?? '') ?></strong>
      <dl>
        <dt><?= e(t('account_number')) ?></dt>
        <dd><?= htp_copy((string) ($a['account_number'] ?? ''), t('account_number')) ?></dd>
        <dt><?= e(t('account_name')) ?></dt>
        <dd><?= e($a['account_name'] ?? '') ?></dd>
      </dl>
    </div><?php if (!empty($a['qr_url'])): ?>
      <div class="payment-qr"><a href="<?= e($a['qr_url']) ?>" target="_blank" rel="noopener noreferrer" data-payment-qr
          data-qr-title="<?= e(t('transfer_qr_alt')) ?>"><img loading="lazy" src="<?= e($a['qr_url']) ?>"
            alt="<?= e(t('transfer_qr_alt')) ?>" width="180" height="180"><span><?= e(htp_ux('qr_open')) ?></span></a><a
          class="btn small" href="<?= e($a['qr_url']) ?>" download="payment-qr.png"><?= e(htp_ux('qr_save')) ?></a></div>
    <?php endif; ?>
  </article><?php return ob_get_clean();
}
function htp_render_group_fields(): string
{
  ob_start(); ?>
  <details class="hyg-card">
    <summary><?= e(htp('host_collect')) ?></summary>
    <p class="hyg-muted"><?= e(htp('host_help')) ?></p><label class="hyg-check"><input type="checkbox"
        name="collect_by_host"
        value="1"><span><?= e(htp('host_collect')) ?></span></label><label><?= e(htp('host_bank')) ?><input
        name="host_bank_name" maxlength="120" autocomplete="off"></label><label><?= e(htp('host_number')) ?><input
        name="host_account_number" maxlength="80" autocomplete="off"></label><label><?= e(htp('host_name')) ?><input
        name="host_account_name" maxlength="160" autocomplete="off"></label><label><?= e(htp('host_qr')) ?><input
        type="file" name="host_qr"
        accept="image/jpeg,image/png,image/webp"><small><?= e(htp('qr_help')) ?></small></label>
  </details><?php return ob_get_clean();
}
function htp_render_store_fields(array $store): string
{
  ob_start(); ?>
  <fieldset>
    <legend><?= e(htp('store_account')) ?></legend>
    <p class="hds-help"><?= e(htp('store_help')) ?></p><label class="hds-check"><input type="checkbox"
        name="transfer_enabled" value="1" <?= !empty($store['transfer_enabled']) ? 'checked' : '' ?>><span><?= e(htp('enabled')) ?></span></label>
    <div class="hds-field-grid"><label><?= e(t('bank_name')) ?><input name="transfer_bank_name"
          value="<?= e($store['transfer_bank_name'] ?? '') ?>"
          maxlength="120"></label><label><?= e(t('account_number')) ?><input name="transfer_account_number"
          value="<?= e($store['transfer_account_number'] ?? '') ?>"
          maxlength="80"></label><label><?= e(t('account_name')) ?><input name="transfer_account_name"
          value="<?= e($store['transfer_account_name'] ?? '') ?>"
          maxlength="160"></label><label><?= e(t('qr_image')) ?><input type="file" name="transfer_qr"
          accept="image/jpeg,image/png,image/webp"><small><?= e(htp('qr_help')) ?></small></label></div>
    <?php if (!empty($store['transfer_has_qr'])): ?><img
        src="<?= e(base_url('payment-qr.php?type=store&store=' . (int) $store['id'])) ?>"
        alt="<?= e(t('transfer_qr_alt')) ?>" width="160" height="160" style="max-width:100%;height:auto"><label
        class="hds-check"><input type="checkbox" name="remove_transfer_qr"
          value="1"><span><?= e(htp('remove_qr')) ?></span></label><?php endif; ?>
  </fieldset><?php return ob_get_clean();
}
function htp_customer_version(array $order, array $r, ?array $accounts = null): string
{
  $accounts ??= (empty($r['recipient_json']) || ($r['report_state'] ?? '') === 'rejected') ? htp_order_accounts($order) : [];
  $quotes = [];
  foreach ($accounts as $a)
    $quotes[] = htp_quote($order, $r, $a);
  return hash('sha256', json_encode([(int) $r['version'], $order['status'], $r['status'], $r['financial_state'] ?? '', $r['received_amount'] ?? 0, $r['refunded_amount'] ?? 0, $r['report_state'] ?? '', (int) ($order['dispatch_revision'] ?? 0), $quotes], JSON_THROW_ON_ERROR));
}
function htp_payment_label(array $r): string
{
  return function_exists('pwf') ? pwf($r['financial_state'] ?? ($r['status'] === 'pending' ? 'unpaid' : $r['status'])) : htp($r['status']);
}
function htp_render_customer(array $order): string
{
  if (!htp_customer_allowed($order))
    return '';
  $r = htp_receipt((int) $order['id']);
  if (!$r)
    return '';
  $summary = hyg_order_payment_summary($order);
  $host = ($summary['collection_mode'] ?? 'store') === 'host';
  $cancelled = $order['status'] === 'cancelled';
  $legacy = !empty($r['ledger_only']);
  $rejected = ($r['report_state'] ?? '') === 'rejected';
  $accounts = $r['recipient_json'] && !$rejected ? [] : htp_order_accounts($order);
  $version = htp_customer_version($order, $r, $accounts);
  $canReport = !$cancelled && !$legacy && $r['status'] === 'pending' && (float) ($r['received_amount'] ?? 0) <= 0;
  $financial = $r['financial_state'] ?? $r['status'];
  $isConfirmed = in_array($financial, ['confirmed', 'not_required'], true) || ((float) ($r['received_amount'] ?? 0) >= (float) $r['amount'] && (float) $r['amount'] > 0);
  $paymentUrl = base_url('payment.php?order=' . rawurlencode($order['order_no']));

  // Resolve bank account details for SePay VietQR
  $selectedAcc = null;
  if (!empty($r['recipient_json']) && !$rejected) {
    $selectedAcc = json_decode($r['recipient_json'], true);
  }
  if (!$selectedAcc && $accounts) {
    $selectedAcc = $accounts[0];
  }
  if (!$selectedAcc) {
    $selectedAcc = hyg_one('SELECT bank_name, account_number, account_name FROM bank_accounts WHERE active = 1 ORDER BY sort_order, id LIMIT 1');
  }
  $bankName = $selectedAcc['bank_name'] ?? '';
  $accNum = $selectedAcc['account_number'] ?? '';
  $accName = $selectedAcc['account_name'] ?? '';
  $des = sepay_order_description($order['order_no']);
  $vietQrUrl = ($accNum && $bankName) ? sepay_qr_url($accNum, $bankName, (float) $r['amount'], $des, 'compact') : '';

  ob_start(); ?>
  <link rel="stylesheet" href="<?= e(base_url('assets/css/sepay-payment.css?v=1.0.0')) ?>">
  <section class="transfer-payment-panel sepay-payment-container" id="merchant-payment" data-sepay-panel
    data-is-confirmed="<?= $isConfirmed ? '1' : '0' ?>" data-order-no="<?= e($order['order_no']) ?>" data-payment-panel
    data-payment-version="<?= e($version) ?>"
    data-payment-state-url="<?= e(base_url('payment-state.php?order=' . rawurlencode($order['order_no']))) ?>"
    data-payment-changed="<?= e(htp_ux('changed')) ?>" data-payment-offline="<?= e(htp_ux('offline')) ?>"
    data-payment-lost="<?= e(htp_ux('session_lost')) ?>"
    data-payment-login="<?= e(base_url('member/index.php?return_order=' . rawurlencode($order['order_no']))) ?>"
    data-payment-login-label="<?= e(htp_ux('login')) ?>" data-payment-copied="<?= e(htp_ux('copied')) ?>"
    data-payment-copy-manual="<?= e(htp_ux('copy_manual')) ?>" data-payment-close="<?= e(htp_ux('qr_close')) ?>">

    <?php if ($isConfirmed): ?>
      <div class="sepay-success-card">
        <div class="sepay-success-icon" aria-hidden="true">✓</div>
        <h3 class="sepay-success-title"><?= e(sepay_t('sepay_success_title')) ?></h3>
        <p class="sepay-success-desc"><?= e(sepay_t('sepay_success_desc_order')) ?></p>
        <div class="sepay-success-details">
          <div><span><?= e(sepay_t('sepay_order_number')) ?></span> <strong><?= e($order['order_no']) ?></strong></div>
          <div><span><?= e(sepay_t('sepay_status')) ?></span> <b class="badge-success"><?= e(htp_payment_label($r)) ?></b></div>
          <div><span><?= e(sepay_t('sepay_received')) ?></span> <strong><?= e(currency()) ?><?= money($r['received_amount']) ?></strong></div>
        </div>
      </div>
    <?php elseif ($order['payment_method'] === 'transfer' && $vietQrUrl): ?>
      <article class="sepay-payment-card">
        <div class="sepay-header">
          <h3 class="sepay-title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="2" y="5" width="20" height="14" rx="2" />
              <line x1="2" y1="10" x2="22" y2="10" />
            </svg>
            <?= e(sepay_t('sepay_card_title')) ?>
          </h3>
          <span class="sepay-timer-badge">
            <span class="sepay-pulse-dot"></span>
            <?= e(sepay_t('sepay_timer_label')) ?> <span data-sepay-countdown>15:00</span>
          </span>
        </div>

        <div class="sepay-grid">
          <div class="sepay-qr-box">
            <img src="<?= e($vietQrUrl) ?>" alt="VietQR" class="sepay-qr-img" data-sepay-qr-modal
              data-sepay-qr-src="<?= e($vietQrUrl) ?>" title="VietQR">
            <div class="sepay-qr-actions">
              <button type="button" class="btn small" data-sepay-qr-modal data-sepay-qr-src="<?= e($vietQrUrl) ?>"><?= e(sepay_t('sepay_qr_zoom')) ?></button>
              <a class="btn small" href="<?= e($vietQrUrl) ?>&download=true"
                download="VietQR-<?= e($order['order_no']) ?>.png"><?= e(sepay_t('sepay_qr_download')) ?></a>
            </div>
          </div>

          <div class="sepay-info-list">
            <div class="sepay-info-item">
              <span class="sepay-info-label"><?= e(sepay_t('sepay_bank')) ?></span>
              <div class="sepay-info-val-wrap">
                <span class="sepay-info-val"><?= e($bankName) ?> (<?= e(sepay_bank_code($bankName)) ?>)</span>
              </div>
            </div>

            <div class="sepay-info-item">
              <span class="sepay-info-label"><?= e(sepay_t('sepay_account_number')) ?></span>
              <div class="sepay-info-val-wrap">
                <span class="sepay-info-val" data-sepay-copy="<?= e($accNum) ?>"><?= e($accNum) ?></span>
              </div>
            </div>

            <div class="sepay-info-item">
              <span class="sepay-info-label"><?= e(sepay_t('sepay_account_name')) ?></span>
              <div class="sepay-info-val-wrap">
                <span class="sepay-info-val"><?= e($accName) ?></span>
              </div>
            </div>

            <div class="sepay-info-item">
              <span class="sepay-info-label"><?= e(sepay_t('sepay_amount')) ?></span>
              <div class="sepay-info-val-wrap">
                <span class="sepay-info-val amount"><?= e(currency()) ?><?= money($r['amount']) ?></span>
              </div>
            </div>

            <div class="sepay-info-item">
              <span class="sepay-info-label"><?= e(sepay_t('sepay_transfer_syntax')) ?></span>
              <div class="sepay-info-val-wrap">
                <span class="sepay-info-val syntax" data-sepay-copy="<?= e($des) ?>"><?= e($des) ?></span>
              </div>
            </div>
          </div>
        </div>

        <div class="sepay-live-status">
          <div class="sepay-spinner"></div>
          <span data-sepay-status-note><?= e(sepay_t('sepay_waiting_status')) ?></span>
        </div>
      </article>
    <?php endif; ?>

    <?php if (!$isConfirmed && !$vietQrUrl): ?>
      <div class="hyg-person-total">
        <strong><?= e(htp('merchant_total')) ?></strong><?= htp_copy((string) (int) round((float) $r['amount']), t('total'), money($r['amount'])) ?>
        <span><?= e(currency()) ?></span></div>
      <p><?= e(htp_ux('payment_method')) ?>：<?= e(payment_label($order['payment_method'])) ?></p><?php if ($host): ?>
        <p><?= e(htp('merchant_host')) ?></p>
        <p class="muted"><?= e(htp('collected_hint')) ?></p><?php endif; ?>
      <p role="status"><strong><?= e($cancelled ? htp('cancelled') : htp_payment_label($r)) ?></strong></p>
      <?php if ($legacy): ?>
        <p class="alert info"><?= e(htp('legacy')) ?></p><?php elseif ($financial === 'not_required'): ?>
        <p><?= e(htp_ux('not_required_hint')) ?></p><?php else: ?>
        <p class="muted"><?= e(htp('confirmed_hint')) ?></p><?php endif; ?>
    <?php endif; ?>

    <?php if (!$isConfirmed && ((float) ($r['received_amount'] ?? 0) > 0 || ($r['report_state'] ?? '') === 'rejected')): ?>
      <div class="payment-financial-summary">
        <?php foreach (['received_amount', 'refunded_amount', 'balance_due', 'refund_due'] as $key): ?>
          <div><span><?= e(pwf($key)) ?></span><b><?= e(currency()) ?><?= money($r[$key] ?? 0) ?></b></div>
        <?php endforeach; ?>
      </div>
      <p class="alert info"><?= e(htp_ux('reconcile')) ?></p>
    <?php endif; ?>

    <?php if ($canReport && $order['payment_method'] === 'transfer' && !$isConfirmed): ?>
      <details class="sepay-manual-report">
        <summary><?= e(sepay_t('sepay_help_toggle')) ?></summary>
        <p class="muted"><?= e(sepay_t('sepay_help_desc')) ?></p>
        <?php foreach ($accounts as $a): ?>
          <form method="post" action="<?= e($paymentUrl) ?>" data-payment-report
            data-confirm="<?= e(htp('report_prompt')) ?>"><?= csrf_field() ?><input type="hidden" name="action"
              value="report"><input type="hidden" name="account_code" value="<?= e($a['code']) ?>"><input type="hidden"
              name="payment_quote" value="<?= e(htp_quote($order, $r, $a)) ?>"><button class="btn secondary"
              type="submit"><?= e(htp('report')) ?></button></form><?php endforeach; ?>
      </details>
    <?php endif; ?>

    <?php if (!$vietQrUrl): ?>
    <div class="payment-actions"><a class="btn" href="<?= e($paymentUrl) ?>"
        data-payment-refresh><?= e(htp('refresh')) ?></a><?= htp_contact($order) ?></div>
    <?php endif; ?>
  </section>
  <?= sepay_render_js_i18n() ?>
  <script src="<?= e(base_url('assets/js/sepay-payment.js?v=1.0.0')) ?>" defer></script>
  <?php return ob_get_clean();
}

function htp_render_staff(array $order): string
{
  $r = htp_receipt((int) $order['id']);
  if (!$r) {
    $r = htp_financial_summary($order, null);
    $r += ['recipient_json' => null, 'reported_at' => null, 'confirmed_at' => null, 'confirmed_by_admin_id' => null];
    $r['version'] = 1;
  }
  $accounts = htp_staff_accounts($order);
  $events = htp_events((int) $order['id']);
  $cancelled = $order['status'] === 'cancelled';
  ob_start(); ?>
  <section class="panel ma-panel" id="merchant-payment">
    <h2><?= e(htp('merchant_title')) ?></h2>
    <p><strong><?= e(htp_payment_label($r)) ?></strong> · <?= e(currency()) ?><?= money($r['amount']) ?></p>
    <p class="muted"><?= e(htp('confirmed_hint')) ?></p>
    <div class="payment-financial-summary">
      <?php foreach (['received_amount', 'refunded_amount', 'net_received', 'balance_due', 'overpaid_amount', 'refund_due'] as $key):
        $value = $key === 'overpaid_amount' ? ($r['overpaid'] ?? 0) : ($r[$key] ?? 0); ?>
        <div><span><?= e(pwf($key)) ?></span><b><?= e(currency()) ?><?= money($value) ?></b></div><?php endforeach; ?>
    </div>
    <?php if ($r['recipient_json']):
      $a = json_decode($r['recipient_json'], true);
      if (is_array($a)): ?>
        <p class="payment-history-value"><?= e(htp('recipient')) ?>：<?= e($a['recipient'] ?? '') ?> ·
          <?= e($a['bank_name'] ?? '') ?> · <?= e($a['account_number'] ?? '') ?> · <?= e($a['account_name'] ?? '') ?></p>
      <?php endif;
    endif; ?>
    <p><?= e(htp('reported_at')) ?>：<?= e($r['reported_at'] ?? '—') ?></p>
    <p><?= e(htp('confirmed_at')) ?>：<?= e($r['confirmed_at'] ?? '—') ?> ·
      <?= e(htp('confirmed_by')) ?>：<?= e($r['confirmed_by_admin_id'] ?? '—') ?></p>
    <div class="payment-staff-actions">
      <?php foreach (['payment_receive', 'payment_correct', 'payment_reject', 'payment_refund'] as $action):
        if ($action === 'payment_reject' && ($r['report_state'] ?? '') !== 'requested' && $r['status'] !== 'requested')
          continue;
        if ($action === 'payment_refund' && (float) ($r['net_received'] ?? 0) <= 0)
          continue; ?>
        <details>
          <summary><?= e(pwf($action)) ?></summary>
          <form method="post" class="payment-staff-form" onsubmit="return confirm(this.dataset.confirm)"
            data-confirm="<?= e($action === 'payment_receive' ? htp('staff_prompt') : pwf($action)) ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="<?= e($action) ?>"><input type="hidden"
              name="payment_version" value="<?= (int) $r['version'] ?>">
            <?php if ($action !== 'payment_reject'): ?><label><?= e(pwf('account')) ?><select name="account_code" required>
                  <option value="" disabled selected><?= e(pwf('account')) ?></option><?php foreach ($accounts as $a): ?>
                    <option value="<?= e($a['code']) ?>">
                      <?= e(($a['source'] ?? '') === 'cash' ? pwf('cash_account') : (($a['code'] === 'snapshot' ? pwf('snapshot_account') . ' · ' : '') . ($a['recipient'] ?? '') . ' · ' . ($a['bank_name'] ?? '') . ' · ' . ($a['account_number'] ?? ''))) ?>
                    </option><?php endforeach; ?>
                </select></label><?php endif; ?>
            <?php if ($action !== 'payment_reject'): ?><label><?= e(pwf('amount')) ?><input name="amount" type="number"
                  step="1" <?= ($action === 'payment_correct' ? '' : 'min="1"') ?>
                  value="<?= e($action === 'payment_receive' ? (string) (int) max(0, $r['balance_due'] ?? $r['amount']) : '') ?>"
                  required></label><?php endif; ?>
            <?php if ($action === 'payment_receive' || $action === 'payment_refund'): ?><label><?= e(pwf('transaction_reference')) ?><input
                  name="transaction_reference" maxlength="160"
                  autocomplete="off"><small><?= e($action === 'payment_receive' ? pwf('reference_required') : pwf('refund_manual_only')) ?></small></label><?php endif; ?>
            <?php if ($action === 'payment_correct'): ?><label><?= e(pwf('payment_correct')) ?><select
                  name="correction_target">
                  <option value="received"><?= e(pwf('received_amount')) ?></option>
                  <option value="refunded"><?= e(pwf('refunded_amount')) ?></option>
                </select></label>
              <p class="muted"><?= e(pwf('correction_help')) ?></p><?php elseif ($action === 'payment_reject'): ?>
              <p class="muted"><?= e(pwf('reject_help')) ?></p><?php endif; ?>
            <label><?= e(pwf('reason')) ?><textarea name="reason" maxlength="1000" rows="2"
                required></textarea></label><button class="btn primary ma-btn" type="submit"><?= e(pwf($action)) ?></button>
          </form>
        </details><?php endforeach; ?>
    </div>
    <details class="payment-history">
      <summary><?= e(pwf('history')) ?> (<?= count($events) ?>)</summary><?php foreach ($events as $event): ?>
        <article><strong><?= e(pwf($event['event_type'])) ?></strong><span><?= e($event['created_at']) ?> ·
            <?= e(pwf('staff')) ?>     <?= e($event['admin_id'] ?? '—') ?></span>
          <p><?= e(pwf('received_amount')) ?>     <?= e(currency()) ?>    <?= money($event['amount_delta'] ?? 0) ?> ·
            <?= e(pwf('refunded_amount')) ?>     <?= e(currency()) ?>    <?= money($event['refund_delta'] ?? 0) ?></p>
          <?php if (!empty($event['transaction_reference'])): ?>
            <p class="payment-history-value"><?= e(pwf('transaction_reference')) ?>：<?= e($event['transaction_reference']) ?>
            </p><?php endif; ?>
          <p class="payment-history-value"><?= e($event['reason'] ?? '') ?></p>
          <?php foreach (['before_json' => 'before', 'after_json' => 'after'] as $json => $label):
            $state = json_decode((string) ($event[$json] ?? ''), true);
            if (!is_array($state))
              continue; ?>
            <p>
              <?= e(pwf($label)) ?>：<?php foreach (['received_amount', 'refunded_amount'] as $key):
                  if (isset($state[$key])): ?><?= e(pwf($key)) ?>
                  <?= e(currency()) ?>          <?= money($state[$key]) ?>
                <?php endif;
                endforeach; ?>
            </p><?php endforeach; ?>
        </article><?php endforeach; ?>
    </details>
  </section>
  <?php return ob_get_clean();
}
