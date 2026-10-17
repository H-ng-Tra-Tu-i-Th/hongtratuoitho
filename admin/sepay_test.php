<?php
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';
require_admin();

$msg = '';
$error = '';
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $action = (string) ($_POST['action'] ?? '');

  try {
    if ($action === 'simulate_webhook') {
      $payload = [
        'id' => (int) ($_POST['sim_id'] ?: time()),
        'gateway' => trim((string) ($_POST['sim_gateway'] ?: 'MBBank')),
        'transactionDate' => date('Y-m-d H:i:s'),
        'accountNumber' => trim((string) ($_POST['sim_account'] ?: '')),
        'code' => trim((string) ($_POST['sim_code'] ?: '')),
        'content' => trim((string) ($_POST['sim_content'] ?: '')),
        'transferType' => 'in',
        'transferAmount' => (float) ($_POST['sim_amount'] ?: 0),
        'accumulated' => 10000000,
        'referenceCode' => 'SIM' . date('YmdHis') . rand(100, 999),
      ];

      $parsed = sepay_parse_description($payload['content'], $payload['code']);
      if (!$parsed) {
        throw new RuntimeException('Cú pháp chuyển khoản không khớp với HYGM (thành viên) hoặc HYG (đơn hàng).');
      }

      if ($parsed['type'] === 'member') {
        $testResult = sepay_process_member_payment($payload, (int) $parsed['id']);
      } else {
        $testResult = sepay_process_order_payment($payload, (string) $parsed['order_no']);
      }

      $msg = 'Mô phỏng Webhook SePay thành công!';
    } elseif ($action === 'save_sepay_settings') {
      $token = trim((string) ($_POST['sepay_webhook_token'] ?? ''));
      $apiKey = trim((string) ($_POST['sepay_api_key'] ?? ''));

      set_setting('sepay_webhook_token', $token);
      set_setting('sepay_api_key', $apiKey);

      $msg = 'Đã lưu cấu hình SePay thành công!';
    }
  } catch (Throwable $e) {
    $error = $e->getMessage();
  }
}

// Fetch recent unpaid orders for quick selection
$recentOrders = hyg_all("SELECT o.id, o.order_no, o.total_amount, o.customer_name, r.status AS receipt_status, r.received_amount 
    FROM orders o 
    LEFT JOIN transfer_order_receipts r ON r.order_id = o.id 
    WHERE o.payment_method = 'transfer' 
    ORDER BY o.id DESC LIMIT 10");

// Fetch recent locked group members for quick selection
$recentMembers = hyg_all("SELECT m.id, m.nickname, m.payment_status, m.payment_received_amount, r.title AS room_title, r.public_id 
    FROM group_members m 
    JOIN group_rooms r ON r.id = m.room_id 
    WHERE r.status = 'locked' AND m.payment_method = 'bank_transfer' 
    ORDER BY m.id DESC LIMIT 10");

// Active store / HQ bank accounts
$activeAccounts = array_merge(
  hyg_all('SELECT bank_name, account_number, account_name, "Trụ sở" AS location FROM bank_accounts WHERE active = 1'),
  hyg_all('SELECT a.bank_name, a.account_number, a.account_name, s.name AS location FROM transfer_store_accounts a JOIN dispatch_stores s ON s.id = a.store_id WHERE a.enabled = 1')
);

// Recent SePay transactions
$recentEvents = hyg_all("SELECT * FROM transfer_payment_events WHERE event_key LIKE 'sepay:%' ORDER BY id DESC LIMIT 10");

admin_header('Kiểm thử & Cấu hình SePay VietQR');
?>

<div class="wrap">
  <h1>Kiểm thử & Cấu hình Thanh toán SePay (VietQR)</h1>

  <?php if ($msg): ?>
    <div class="alert success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($error): ?>
    <div class="alert danger"><?= e($error) ?></div><?php endif; ?>

  <?php if ($testResult): ?>
    <section class="panel">
      <h2>Kết quả xử lý Webhook SePay</h2>
      <pre
        style="background:#0f172a;color:#38bdf8;padding:16px;border-radius:8px;overflow-x:auto;"><?= e(json_encode($testResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
    </section>
  <?php endif; ?>

  <div class="ui-grid ui-grid-2-1">
    <section class="panel">
      <h2>Mô phỏng Webhook SePay (Webhook Simulator)</h2>
      <p class="muted">Công cụ này giả lập Webhook của SePay gửi tới hệ thống khi có tiền về tài khoản ngân hàng, hỗ trợ
        cả <strong>Đơn cá nhân (HYG)</strong> và <strong>Đơn nhóm Split Bill (HYGM)</strong>.</p>

      <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="simulate_webhook">
        <input type="hidden" name="sim_id" value="<?= time() ?>">

        <label>
          Tài khoản ngân hàng nhận tiền
          <select name="sim_account" required>
            <option value="">-- Chọn tài khoản ngân hàng chi nhánh / trụ sở --</option>
            <?php foreach ($activeAccounts as $acc): ?>
              <option value="<?= e($acc['account_number']) ?>">
                [<?= e($acc['location']) ?>] <?= e($acc['bank_name']) ?> - <?= e($acc['account_number']) ?>
                (<?= e($acc['account_name']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>
          Ngân hàng phát sinh giao dịch
          <input name="sim_gateway" value="MBBank" required>
        </label>

        <label>
          Số tiền chuyển (VND)
          <input name="sim_amount" type="number" step="1000" min="1000" required placeholder="50000">
        </label>

        <label class="full">
          Nội dung chuyển khoản (Content)
          <input name="sim_content" id="sim_content" required placeholder="HYG 261016A1B2 hoặc HYGM 428">
          <small class="muted">Cú pháp: <code>HYG &lt;MÃ_ĐƠN&gt;</code> cho đơn hàng |
            <code>HYGM &lt;ID_THÀNH_VIÊN&gt;</code> cho thành viên chia tiền</small>
        </label>

        <div class="full" style="display:flex;gap:10px;margin-top:10px;">
          <button type="submit" class="btn primary">Gửi giả lập Webhook SePay</button>
        </div>
      </form>

      <hr style="margin:24px 0;">

      <h3>Gợi ý nhanh từ dữ liệu thật</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div>
          <strong>Đơn hàng chuyển khoản gần đây:</strong>
          <ul style="font-size:0.875rem;padding-left:18px;">
            <?php foreach ($recentOrders as $ro): ?>
              <li>
                <a href="javascript:void(0)"
                  onclick="fillOrder('<?= e($ro['order_no']) ?>', <?= (int) $ro['total_amount'] ?>)">
                  <?= e($ro['order_no']) ?> - <?= e(money($ro['total_amount'])) ?>đ (<?= e($ro['receipt_status']) ?>)
                </a>
              </li>
            <?php endforeach; ?>
            <?php if (!$recentOrders): ?>
              <li class="muted">Chưa có đơn chuyển khoản nào</li><?php endif; ?>
          </ul>
        </div>

        <div>
          <strong>Thành viên nhóm Split Bill gần đây:</strong>
          <ul style="font-size:0.875rem;padding-left:18px;">
            <?php foreach ($recentMembers as $rm): ?>
              <li>
                <a href="javascript:void(0)" onclick="fillMember(<?= (int) $rm['id'] ?>)">
                  GM#<?= (int) $rm['id'] ?> (<?= e($rm['nickname']) ?> - <?= e($rm['room_title']) ?>)
                  [<?= e($rm['payment_status']) ?>]
                </a>
              </li>
            <?php endforeach; ?>
            <?php if (!$recentMembers): ?>
              <li class="muted">Chưa có nhóm nào đang khóa</li><?php endif; ?>
          </ul>
        </div>
      </div>
    </section>

    <section class="panel">
      <h2>Thông tin tích hợp SePay</h2>
      <div
        style="background:#f8fafc;padding:12px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:16px;font-size:0.875rem;">
        <div><strong>Webhook URL:</strong></div>
        <code style="word-break:break-all;color:#0284c7;"><?= e(base_url('api/sepay_webhook.php')) ?></code>
        <div style="margin-top:8px;"><strong>Phương thức:</strong> <code>POST</code></div>
        <div><strong>Kiểu dữ liệu:</strong> <code>application/json</code></div>
        <div><strong>Quy tắc cú pháp:</strong></div>
        <ul style="padding-left:16px;margin:4px 0;">
          <li>Đơn hàng: <code>HYG &lt;MÃ_ĐƠN&gt;</code></li>
          <li>Chia tiền nhóm: <code>HYGM &lt;ID&gt;</code></li>
        </ul>
      </div>

      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_sepay_settings">

        <label>
          SePay API Key (Lấy từ sepay.vn)
          <input name="sepay_api_key" value="<?= e(sepay_api_key()) ?>" placeholder="Ví dụ: SP-API-KEY-xxxx">
        </label>

        <label>
          SePay Webhook Token (Tùy chọn)
          <input name="sepay_webhook_token" value="<?= e(sepay_webhook_token()) ?>"
            placeholder="Bearer token kiểm tra webhook">
        </label>

        <button type="submit" class="btn">Lưu cấu hình SePay</button>
      </form>
    </section>
  </div>

  <section class="panel">
    <h2>Lịch sử giao dịch SePay xác nhận tự động</h2>
    <?php if (!$recentEvents): ?>
      <p class="muted">Chưa có giao dịch SePay nào được ghi nhận.</p>
    <?php else: ?>
      <table class="data-table">
        <thead>
          <tr>
            <th>Thời gian</th>
            <th>Mã đơn</th>
            <th>Số tiền</th>
            <th>Mã tham chiếu</th>
            <th>Ghi chú</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentEvents as $ev): ?>
            <tr>
              <td><?= e($ev['created_at']) ?></td>
              <td>#<?= (int) $ev['order_id'] ?></td>
              <td><strong style="color:#16a34a;">+<?= e(money($ev['amount_delta'])) ?>đ</strong></td>
              <td><code><?= e($ev['transaction_reference']) ?></code></td>
              <td><?= e($ev['reason']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>

<script>
  function fillOrder(orderNo, amount) {
    document.getElementById('sim_content').value = 'HYG ' + orderNo;
    document.querySelector('[name="sim_amount"]').value = amount;
  }
  function fillMember(memberId) {
    document.getElementById('sim_content').value = 'HYGM ' + memberId;
  }
</script>

<?php admin_footer(); ?>