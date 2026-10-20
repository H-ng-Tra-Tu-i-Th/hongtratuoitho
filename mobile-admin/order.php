<?php
require __DIR__ . '/_common.php';
ma_require_admin();
require_once __DIR__ . '/../lib/group_payment_views.php';
require_once __DIR__ . '/../lib/dispatch_views.php';
require_once __DIR__ . '/../lib/order_workflow.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) redirect(ma_url('index.php'));
hds_view_require_staff(ma_url('login.php?return_order=' . $id));
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  try {
    $action = $_POST['action'] ?? null;
    if (!is_string($action)) throw new InvalidArgumentException('invalid_action');
    if (in_array($action, ['dispatch_refresh', 'dispatch_assign', 'dispatch_adopt', 'dispatch_clear'], true)) {
      $_SESSION['dispatch_order_flash'] = ['id' => $id, 'key' => hds_view_order_action($id, $action, $_POST, (int) $_SESSION['admin_id'])];
    } elseif ($action === 'confirm_merchant_payment') {
      htp_confirm($id, $_POST['payment_version'] ?? null, (int) $_SESSION['admin_id']);
      $_SESSION['transfer_staff_flash'] = $id;
    } elseif (in_array($action, ['payment_receive', 'payment_correct', 'payment_reject', 'payment_refund'], true)) {
      htp_staff_action($id, $action, $_POST, (int) $_SESSION['admin_id']);
      $_SESSION['payment_action_flash'] = $id;
    } elseif ($action === 'status') {
      htow_change($id, $_POST['status'] ?? null, $_POST['status_version'] ?? null, $_POST['status_reason'] ?? null, (int) $_SESSION['admin_id']);
      $_SESSION['order_status_flash'] = $id;
    } elseif ($action === 'resend_email') {
      $detail = '';
      if (send_order_notification_by_id($id, $detail)) {
        $_SESSION['order_email_flash'] = 'sent';
      } else {
        $_SESSION['order_email_error'] = $detail ?: 'unknown';
      }
    } else {
      throw new InvalidArgumentException('invalid_action');
    }
    redirect(ma_url('order.php?id=' . $id));
  } catch (Throwable $exception) {
    $error = htow_error($exception);
  }
}

$dispatchFlash = $_SESSION['dispatch_order_flash'] ?? null;
unset($_SESSION['dispatch_order_flash']);
if (is_array($dispatchFlash) && (int) ($dispatchFlash['id'] ?? 0) === $id && in_array($dispatchFlash['key'] ?? '', ['dispatch_saved', 'dispatch_refreshed'], true)) {
  $message = dt($dispatchFlash['key']);
}
if (($_SESSION['transfer_staff_flash'] ?? 0) === $id) {
  $message = htp('staff_saved');
  unset($_SESSION['transfer_staff_flash']);
}
if (($_SESSION['payment_action_flash'] ?? 0) === $id) {
  $message = pwf('action_saved');
  unset($_SESSION['payment_action_flash']);
}
if (($_SESSION['order_status_flash'] ?? 0) === $id) {
  $message = pwf('status_saved');
  unset($_SESSION['order_status_flash']);
}
if (isset($_SESSION['order_email_flash'])) {
  $message = t('order_email_resent');
  unset($_SESSION['order_email_flash']);
}
if (isset($_SESSION['order_email_error'])) {
  $error = t('order_email_resend_failed', ['detail' => $_SESSION['order_email_error']]);
  unset($_SESSION['order_email_error']);
}

$st = db()->prepare('SELECT * FROM orders WHERE id=?');
$st->execute([$id]);
$o = $st->fetch();
if (!$o) {
  http_response_code(404);
  exit('Order not found');
}

$st = db()->prepare('SELECT * FROM order_items WHERE order_id=? ORDER BY id ASC');
$st->execute([$id]);
$items = $st->fetchAll();

// Chi nhánh trực tiếp từ dispatch_stores
$dispatchStore = null;
if (!empty($o['dispatch_store_id'])) {
  $dispatchStore = hyg_one('SELECT * FROM dispatch_stores WHERE id = ?', [(int)$o['dispatch_store_id']]);
}

$targets = htow_targets($o['status']);
$ver = (int)$o['status_version'];

ma_header('#' . $o['order_no']);
?>

<link rel="stylesheet" href="<?= e(base_url('assets/css/admin-order.css?v=' . filemtime(__DIR__ . '/../assets/css/admin-order.css'))) ?>">

<div class="ma-order-wrap">

  <a class="ma-back" href="<?= e(ma_url('index.php')) ?>">← <?= e(ma_t('back')) ?></a>

  <?php if ($message): ?>
    <div class="hds-notice" role="status"><?= e($message) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="hds-notice danger" role="alert"><?= e($error) ?></div>
  <?php endif; ?>

  <!-- Hero Summary Card -->
  <div class="ma-order-hero">
    <div class="ma-hero-top">
      <span class="order-status-pill <?= e($o['status']) ?>">
        <?= e(ma_status_label($o['status'])) ?>
      </span>
      <div class="ma-hero-total-box">
        <span class="ma-hero-total-label"><?= e(ma_t('total')) ?></span>
        <strong class="ma-hero-total-val"><?= e(currency()) ?><?= money($o['total_amount']) ?></strong>
      </div>
    </div>

    <div class="ma-hero-no-row">
      <h1 class="ma-hero-order-no">#<?= e($o['order_no']) ?></h1>
      <button type="button" class="btn-copy-icon" title="<?= e(adm_t('copy_order_no_title')) ?>" onclick="copyToClipboard(this, '<?= e($o['order_no']) ?>')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
      </button>
    </div>

    <div class="ma-order-hero-meta">
      <div class="ma-meta-item">
        <span class="ma-meta-icon">⏰</span>
        <span><?= e($o['created_at']) ?></span>
      </div>
      <div class="ma-meta-item">
        <span class="ma-meta-icon">💳</span>
        <?php if ($o['payment_method'] === 'transfer'): ?>
          <strong class="badge-payment-transfer"><?= e(adm_t('badge_transfer_success')) ?></strong>
        <?php elseif ($o['payment_method'] === 'cod' || $o['payment_method'] === 'cash'): ?>
          <strong class="badge-payment-cash"><?= e(adm_t('badge_cash_delivery')) ?></strong>
        <?php else: ?>
          <strong><?= e(payment_label($o['payment_method'])) ?></strong>
        <?php endif; ?>
      </div>
      <?php if (!empty($dispatchStore['name'])): ?>
        <div class="ma-meta-item">
          <span class="ma-meta-icon">🏬</span>
          <span><?= e($dispatchStore['name']) ?></span>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Quick Actions Bar for Order Status -->
  <div class="ma-quick-actions-bar">
    <div class="ma-quick-actions-title"><?= e(adm_t('quick_actions_label')) ?></div>
    <div class="ma-action-btn-row">
      <?php if ($o['status'] === 'new'): ?>
        <form method="post" style="flex:1;" onsubmit="return confirm('<?= e(adm_t('confirm_accept_msg')) ?>');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="status_version" value="<?= $ver ?>">
          <input type="hidden" name="status" value="accepted">
          <input type="hidden" name="status_reason" value="<?= e(adm_t('reason_staff_accept')) ?>">
          <button type="submit" class="ma-btn-quick accept"><?= e(adm_t('btn_accept_short')) ?></button>
        </form>

        <form method="post" style="flex:1;" onsubmit="return promptCancelOrderMobile(this);">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="status_version" value="<?= $ver ?>">
          <input type="hidden" name="status" value="cancelled">
          <input type="hidden" name="status_reason" value="">
          <button type="submit" class="ma-btn-quick cancel"><?= e(adm_t('btn_cancel_short')) ?></button>
        </form>

      <?php elseif ($o['status'] === 'accepted'): ?>
        <form method="post" style="flex:1;" onsubmit="return confirm('<?= e(adm_t('confirm_complete_msg')) ?>');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="status_version" value="<?= $ver ?>">
          <input type="hidden" name="status" value="done">
          <input type="hidden" name="status_reason" value="<?= e(adm_t('reason_staff_complete')) ?>">
          <button type="submit" class="ma-btn-quick complete"><?= e(adm_t('btn_complete_short')) ?></button>
        </form>

        <form method="post" style="flex:1;" onsubmit="return promptCancelOrderMobile(this);">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="status_version" value="<?= $ver ?>">
          <input type="hidden" name="status" value="cancelled">
          <input type="hidden" name="status_reason" value="">
          <button type="submit" class="ma-btn-quick cancel"><?= e(adm_t('btn_cancel_short')) ?></button>
        </form>

      <?php elseif ($o['status'] === 'done'): ?>
        <div style="font-weight:600; color:#15803d; font-size:0.9rem;"><?= e(adm_t('order_done_success')) ?></div>
      <?php elseif ($o['status'] === 'cancelled'): ?>
        <div style="font-weight:600; color:#b91c1c; font-size:0.9rem;"><?= e(adm_t('order_cancelled_notice')) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <script>
    function copyToClipboard(btn, text) {
      if (!navigator.clipboard) return;
      navigator.clipboard.writeText(text).then(function() {
        var oldHtml = btn.innerHTML;
        btn.classList.add('copied');
        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
        setTimeout(function() {
          btn.classList.remove('copied');
          btn.innerHTML = oldHtml;
        }, 1500);
      });
    }

    function promptCancelOrderMobile(form) {
      const reason = prompt('<?= e(adm_t('prompt_cancel_reason')) ?>', '<?= e(adm_t('default_cancel_reason')) ?>');
      if (reason === null) return false;
      const clean = reason.trim();
      if (!clean) {
        alert('<?= e(adm_t('alert_cancel_reason_required')) ?>');
        return false;
      }
      form.querySelector('input[name="status_reason"]').value = clean;
      return true;
    }
  </script>

  <!-- Customer Card -->
  <div class="ma-cust-card">
    <div style="font-weight:700; font-size:1rem; margin-bottom:10px; color:#111827;">
      👤 <?= e(ma_t('customer')) ?>
    </div>
    <div class="ma-cust-row">
      <span class="ma-cust-label"><?= e(ma_t('customer')) ?>:</span>
      <span class="ma-cust-val"><?= e($o['customer_name']) ?></span>
    </div>
    <div class="ma-cust-row">
      <span class="ma-cust-label"><?= e(ma_t('phone')) ?>:</span>
      <span class="ma-cust-val">
        <a href="tel:<?= e($o['phone']) ?>"><?= e($o['phone']) ?></a>
        <button type="button" class="btn-copy-icon" title="<?= e(adm_t('copy_phone_title')) ?>" onclick="copyToClipboard(this, '<?= e($o['phone']) ?>')">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
        </button>
      </span>
    </div>
    <div class="ma-cust-row">
      <span class="ma-cust-label"><?= e(ma_t('address')) ?>:</span>
      <span class="ma-cust-val"><?= nl2br(e($o['address'])) ?></span>
    </div>
    <div class="ma-cust-row">
      <span class="ma-cust-label"><?= e(ma_t('payment')) ?>:</span>
      <span class="ma-cust-val">
        <?php if ($o['payment_method'] === 'transfer'): ?>
          <strong class="badge-payment-transfer">
            <?= e(adm_t('badge_transfer_success')) ?>
          </strong>
        <?php elseif ($o['payment_method'] === 'cod' || $o['payment_method'] === 'cash'): ?>
          <strong class="badge-payment-cash">
            <?= e(adm_t('badge_cash_delivery')) ?>
          </strong>
        <?php else: ?>
          <strong><?= e(payment_label($o['payment_method'])) ?></strong>
        <?php endif; ?>
      </span>
    </div>

    <!-- Quick Touch Actions -->
    <div class="ma-cust-actions">
      <a class="ma-touch-btn" href="tel:<?= e($o['phone']) ?>"><?= e(adm_t('call_customer')) ?></a>
      <a class="ma-touch-btn" href="https://www.google.com/maps/search/?api=1&query=<?= rawurlencode($o['address']) ?>" target="_blank" rel="noopener noreferrer"><?= e(adm_t('open_map')) ?></a>
    </div>

    <?php if (!empty($o['order_note'])): ?>
      <div class="ma-cust-note-box">
        <strong>📝 <?= e(ma_t('note')) ?>:</strong>
        <div><?= nl2br(e($o['order_note'])) ?></div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Order Items Card -->
  <div class="ma-item-card">
    <div style="font-weight:700; font-size:1rem; margin-bottom:10px; color:#111827;">
      <?= e(adm_t('order_details_heading')) ?> (<?= e(adm_t('items_count', ['count' => count($items)])) ?>)
    </div>
    <div class="ma-item-list">
      <?php foreach ($items as $i): ?>
        <div class="ma-item-row">
          <div class="ma-item-head">
            <span><?= e($i['product_name']) ?></span>
            <span style="color:#2563eb;">×<?= $i['qty'] ?></span>
          </div>
          <?php if (!empty($i['group_member_name'])): ?>
            <div style="font-size:0.75rem; color:#4338ca; margin-top:2px;">
              <?= e(adm_t('item_ordered_by', ['name' => $i['group_member_name']])) ?>
            </div>
          <?php endif; ?>
          <div class="ma-item-specs">
            <?php if (!empty($i['cup_size'])): ?><span><?= e(adm_t('cup_size_prefix', ['size' => $i['cup_size']])) ?></span> · <?php endif; ?>
            <span><?= e(adm_t('ice_prefix', ['val' => $i['ice_choice'] ?: adm_t('ice_sugar_standard')])) ?></span> · 
            <span><?= e(adm_t('sugar_prefix', ['val' => $i['sugar_choice'] ?: adm_t('ice_sugar_standard')])) ?></span>
            <?php if (!empty($i['toppings_text'])): ?>
              <div style="color:#374151; font-weight:500; margin-top:2px;">+ <?= e($i['toppings_text']) ?></div>
            <?php endif; ?>
          </div>
          <div class="ma-item-bottom">
            <span style="color:#6b7280; font-size:0.8rem;"><?= e(currency()) ?><?= money($i['unit_price']) ?> <?= e(adm_t('per_cup')) ?></span>
            <span class="ma-item-price-tag"><?= e(currency()) ?><?= money($i['line_total']) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="ma-totals" style="margin-top:14px; border-top:1px dashed #d1d5db; padding-top:12px;">
      <?php if ((float)($o['subtotal_amount'] ?? 0) > 0): ?>
        <div style="display:flex; justify-content:space-between; font-size:0.88rem; margin-bottom:4px;">
          <span><?= e(ma_t('subtotal')) ?>:</span>
          <b><?= e(currency()) ?><?= money($o['subtotal_amount']) ?></b>
        </div>
      <?php endif; ?>
      <?php if (!empty($o['coupon_code'])): ?>
        <div style="display:flex; justify-content:space-between; font-size:0.88rem; margin-bottom:4px; color:#16a34a;">
          <span><?= e(adm_t('coupon_code_label', ['code' => $o['coupon_code']])) ?></span>
          <b>-<?= e(currency()) ?><?= money($o['discount_amount'] ?? 0) ?></b>
        </div>
      <?php endif; ?>
      <div style="display:flex; justify-content:space-between; font-size:1.15rem; font-weight:800; color:#b91c1c; border-top:1px solid #e5e7eb; padding-top:6px; margin-top:6px;">
        <span><?= e(ma_t('total')) ?>:</span>
        <span><?= e(currency()) ?><?= money($o['total_amount']) ?></span>
      </div>
    </div>
  </div>

  <!-- Dispatch Store Panel -->
  <?= hds_render_order_dispatch($o) ?>

  <!-- Payment Panel -->
  <?= htp_render_staff($o) ?>

  <!-- Group Payment (nếu có) -->
  <?php $groupPayment = hyg_order_payment_summary($o); if ($groupPayment): ?>
    <section class="ma-panel"><?= hyg_render_payment_overview($groupPayment) ?></section>
  <?php endif; ?>

  <!-- Email Delivery Section -->
  <section class="ma-panel">
    <h2><?= e(adm_t('email_notification_title')) ?></h2>
    <div style="font-size:0.88rem; display:flex; flex-direction:column; gap:6px;">
      <div><?= e(adm_t('email_status_label')) ?> <strong><?= e(order_notification_status_label((string)($o['notification_email_status'] ?? 'pending'))) ?></strong></div>
      <div><?= e(adm_t('email_attempts_label')) ?> <?= number_format((int)($o['notification_email_attempts'] ?? 0)) ?></div>
      <?php if (!empty($o['notification_email_sent_at'])): ?>
        <div><?= e(adm_t('email_sent_at_label')) ?> <?= e($o['notification_email_sent_at']) ?></div>
      <?php endif; ?>
    </div>
    <form method="post" style="margin-top:10px;">
      <?= csrf_field() ?>
      <button class="ma-btn" type="submit" name="action" value="resend_email" style="width:100%;">
        <?= e(adm_t('btn_resend_email')) ?>
      </button>
    </form>
  </section>

  <!-- Lịch sử xử lý đơn -->
  <?= htow_render_history($o) ?>

</div>

<?php ma_footer(); ?>
