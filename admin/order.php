<?php
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/group_payment_views.php';
require_once __DIR__ . '/../lib/dispatch_views.php';
require_once __DIR__ . '/../lib/order_workflow.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) redirect(base_url('admin/index.php'));
hds_view_require_staff(base_url('admin/login.php?return_order=' . $id));

$st = db()->prepare('SELECT * FROM orders WHERE id=?');
$st->execute([$id]);
$o = $st->fetch();
if (!$o) exit(e(t('order_not_found')));
require_order_access($o);

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  try {
    $action = $_POST['action'] ?? null;
    if (!is_string($action)) throw new InvalidArgumentException('invalid_action');
    if (in_array($action, ['dispatch_refresh', 'dispatch_assign', 'dispatch_adopt', 'dispatch_clear'], true)) {
      if (admin_store_id() !== null) throw new InvalidArgumentException('forbidden');
      $_SESSION['dispatch_order_flash'] = ['id' => $id, 'key' => hds_view_order_action($id, $action, $_POST, (int) $_SESSION['admin_id'])];
      redirect(base_url('admin/order.php?id=' . $id . '#hds-dispatch-heading'));
    } elseif ($action === 'confirm_merchant_payment') {
      htp_confirm($id, $_POST['payment_version'] ?? null, (int) $_SESSION['admin_id']);
      $_SESSION['admin_order_msg'] = htp('staff_saved');
      redirect(base_url('admin/order.php?id=' . $id));
    } elseif (in_array($action, ['payment_receive', 'payment_correct', 'payment_reject', 'payment_refund'], true)) {
      htp_staff_action($id, $action, $_POST, (int) $_SESSION['admin_id']);
      $_SESSION['admin_order_msg'] = pwf('action_saved');
      redirect(base_url('admin/order.php?id=' . $id));
    } elseif ($action === 'resend_email') {
      $detail = '';
      if (send_order_notification_by_id($id, $detail)) {
        $_SESSION['admin_order_msg'] = t('order_email_resent');
      } else {
        $_SESSION['admin_order_error'] = t('order_email_resend_failed', ['detail' => $detail ?: 'unknown']);
      }
      redirect(base_url('admin/order.php?id=' . $id));
    } elseif ($action === 'status') {
      htow_change($id, $_POST['status'] ?? null, $_POST['status_version'] ?? null, $_POST['status_reason'] ?? null, (int) $_SESSION['admin_id']);
      $_SESSION['admin_order_msg'] = pwf('status_saved');
      redirect(base_url('admin/order.php?id=' . $id));
    } else {
      throw new InvalidArgumentException('invalid_action');
    }
  } catch (Throwable $exception) {
    $error = htow_error($exception);
  }
}

if (isset($_SESSION['admin_order_msg'])) {
  $msg = $_SESSION['admin_order_msg'];
  unset($_SESSION['admin_order_msg']);
}
if (isset($_SESSION['admin_order_error'])) {
  $error = $_SESSION['admin_order_error'];
  unset($_SESSION['admin_order_error']);
}

$dispatchFlash = $_SESSION['dispatch_order_flash'] ?? null;
unset($_SESSION['dispatch_order_flash']);
if (is_array($dispatchFlash) && (int) ($dispatchFlash['id'] ?? 0) === $id && in_array($dispatchFlash['key'] ?? '', ['dispatch_saved', 'dispatch_refreshed'], true)) {
  $msg = dt($dispatchFlash['key']);
}

$st = db()->prepare('SELECT * FROM orders WHERE id=?');
$st->execute([$id]);
$o = $st->fetch();
if (!$o) exit(e(t('order_not_found')));

$st = db()->prepare('SELECT * FROM order_items WHERE order_id=? ORDER BY id ASC');
$st->execute([$id]);
$items = $st->fetchAll();
$eligibleQty = order_eligible_drink_qty($id);

// Đọc thông tin chi nhánh phân phối trực tiếp từ dispatch_stores
$dispatchStore = null;
if (!empty($o['dispatch_store_id'])) {
  $dispatchStore = hyg_one('SELECT * FROM dispatch_stores WHERE id = ?', [(int)$o['dispatch_store_id']]);
}

$targets = htow_targets($o['status']);
$ver = (int)$o['status_version'];

admin_header(t('order_no') . ' ' . $o['order_no']);
?>
<link rel="stylesheet" href="<?= e(base_url('assets/css/admin-order.css?v=' . filemtime(__DIR__ . '/../assets/css/admin-order.css'))) ?>">

<div class="order-detail-container">

  <!-- Top Action Bar -->
  <div class="order-top-nav">
    <a class="order-back-btn" href="<?= e(base_url('admin/index.php')) ?>">
      ← <?= e(adm_t('order_back_list')) ?>
    </a>
    <!-- <div style="display: flex; gap: 8px;">
      <button type="button" class="btn-action-quick btn-print" onclick="window.print()">
        🖨️ <?= e(adm_t('order_print')) ?>
      </button>
    </div> -->
  </div>

  <?php if ($msg): ?>
    <div class="alert success" role="status" style="margin: 0;"><?= e($msg) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert danger" role="alert" style="margin: 0;"><?= e($error) ?></div>
  <?php endif; ?>

  <!-- Hero Header Card -->
  <div class="order-hero-card">
    <div class="order-hero-left">
      <h1 class="order-hero-no">
        #<?= e($o['order_no']) ?>
        <button type="button" class="btn-copy-icon" title="<?= e(adm_t('copy_order_no_title')) ?>" onclick="copyToClipboard(this, '<?= e($o['order_no']) ?>')">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
          </svg>
        </button>
        <span class="order-status-pill <?= e($o['status']) ?>">
          <?= e(status_label($o['status'])) ?>
        </span>
      </h1>
      <div class="order-hero-meta">
        <span>⏰ <strong><?= e(adm_t('order_time')) ?></strong> <?= e(format_datetime($o['created_at'])) ?></span>
        <?php if ($o['payment_method'] === 'transfer'): ?>
          <span>💳 <strong><?= e(adm_t('order_payment')) ?></strong> <strong class="badge-payment-transfer"><?= e(adm_t('badge_transfer_success')) ?></strong></span>
        <?php elseif ($o['payment_method'] === 'cod' || $o['payment_method'] === 'cash'): ?>
          <span>💳 <strong><?= e(adm_t('order_payment')) ?></strong> <strong class="badge-payment-cash"><?= e(adm_t('badge_cash_delivery')) ?></strong></span>
        <?php else: ?>
          <span>💳 <strong><?= e(adm_t('order_payment')) ?></strong> <strong><?= e(payment_label($o['payment_method'])) ?></strong></span>
        <?php endif; ?>
        <?php if (!empty($dispatchStore['name'])): ?>
          <span>🏬 <strong><?= e(adm_t('order_branch')) ?></strong> <?= e($dispatchStore['name']) ?></span>
        <?php elseif (!empty($o['dispatch_store_name'])): ?>
          <span>🏬 <strong><?= e(adm_t('order_branch')) ?></strong> <?= e($o['dispatch_store_name']) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <div class="order-hero-right">
      <div class="order-hero-total-box">
        <span class="order-hero-total-label"><?= e(adm_t('order_total_payment')) ?></span>
        <span class="order-hero-total-val"><?= e(currency()) ?><?= money($o['total_amount']) ?></span>
      </div>
    </div>
  </div>

  <!-- Quick Actions Bar for Order Status -->
  <div class="order-actions-bar">
    <div class="order-actions-label">
      <span><?= e(adm_t('quick_actions_label')) ?></span>
    </div>
    <div class="order-actions-btns">
      <?php if ($o['status'] === 'new'): ?>
        <!-- Nhận đơn hàng -->
        <form method="post" style="display:inline;" onsubmit="return confirm('<?= e(adm_t('confirm_accept_msg')) ?>');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="status_version" value="<?= $ver ?>">
          <input type="hidden" name="status" value="accepted">
          <input type="hidden" name="status_reason" value="<?= e(adm_t('reason_staff_accept')) ?>">
          <button type="submit" class="btn-action-quick btn-accept">
            <?= e(adm_t('action_accept_order')) ?>
          </button>
        </form>

        <!-- Hủy đơn hàng -->
        <form method="post" style="display:inline;" onsubmit="return promptCancelOrder(this);">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="status_version" value="<?= $ver ?>">
          <input type="hidden" name="status" value="cancelled">
          <input type="hidden" name="status_reason" id="cancel_reason_new" value="">
          <button type="submit" class="btn-action-quick btn-cancel">
            <?= e(adm_t('action_cancel_order')) ?>
          </button>
        </form>

      <?php elseif ($o['status'] === 'accepted'): ?>
        <!-- Hoàn tất đơn hàng -->
        <form method="post" style="display:inline;" onsubmit="return confirm('<?= e(adm_t('confirm_complete_msg')) ?>');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="status_version" value="<?= $ver ?>">
          <input type="hidden" name="status" value="done">
          <input type="hidden" name="status_reason" value="<?= e(adm_t('reason_staff_complete')) ?>">
          <button type="submit" class="btn-action-quick btn-complete">
            <?= e(adm_t('action_complete_order')) ?>
          </button>
        </form>

        <!-- Hủy đơn hàng -->
        <form method="post" style="display:inline;" onsubmit="return promptCancelOrder(this);">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="status_version" value="<?= $ver ?>">
          <input type="hidden" name="status" value="cancelled">
          <input type="hidden" name="status_reason" id="cancel_reason_accepted" value="">
          <button type="submit" class="btn-action-quick btn-cancel">
            <?= e(adm_t('action_cancel_order')) ?>
          </button>
        </form>

      <?php elseif ($o['status'] === 'done'): ?>
        <span style="color: #15803d; font-weight: 600;"><?= e(adm_t('order_done_success')) ?></span>
        <?php if (in_array('cancelled', $targets, true)): ?>
          <form method="post" style="display:inline;" onsubmit="return promptCancelOrder(this);">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="status_version" value="<?= $ver ?>">
            <input type="hidden" name="status" value="cancelled">
            <input type="hidden" name="status_reason" value="">
            <button type="submit" class="btn-action-quick btn-cancel" style="padding: 5px 12px; font-size: 0.85rem;">
              <?= e(adm_t('action_revoke_order')) ?>
            </button>
          </form>
        <?php endif; ?>

      <?php elseif ($o['status'] === 'cancelled'): ?>
        <span style="color: #b91c1c; font-weight: 600;"><?= e(adm_t('order_cancelled_notice')) ?></span>
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

    function promptCancelOrder(form) {
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

  <!-- Group Order Banner (nếu có) -->
  <?php if (!empty($o['group_title'])): ?>
    <div class="order-card" style="border-left: 4px solid #4f46e5; background: #f5f3ff;">
      <h3 style="margin: 0 0 6px 0; color: #4338ca; font-size: 1rem;">👥 <?= e(gt('title')) ?>: <?= e($o['group_title']) ?></h3>
      <p class="muted" style="margin: 0; font-size: 0.88rem;"><?= e(gt('rules')) ?></p>
    </div>
  <?php endif; ?>

  <!-- Main 2-Column Grid -->
  <div class="order-main-grid">

    <!-- CỘT TRÁI: Khách hàng, Chi nhánh phân phối, Hóa đơn VAT, Email -->
    <div class="order-col-left">

      <!-- Thông tin Khách hàng -->
      <div class="order-card">
        <div class="order-card-title">
          <span>👤 <?= e(adm_t('customer_delivery_info')) ?></span>
        </div>
        <div class="customer-info-list">
          <div class="customer-info-label"><?= e(adm_t('customer_name_label')) ?>:</div>
          <div class="customer-info-val"><?= e($o['customer_name']) ?></div>

          <div class="customer-info-label"><?= e(t('phone')) ?>:</div>
          <div class="customer-info-val">
            <a href="tel:<?= e($o['phone']) ?>"><?= e($o['phone']) ?></a>
            <button type="button" class="btn-copy-icon" title="<?= e(adm_t('copy_phone_title')) ?>" onclick="copyToClipboard(this, '<?= e($o['phone']) ?>')">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
              </svg>
            </button>
          </div>

          <div class="customer-info-label"><?= e(t('address')) ?>:</div>
          <div class="customer-info-val">
            <?= nl2br(e($o['address'])) ?>
            <div style="margin-top: 4px;">
              <a href="https://www.google.com/maps/search/?api=1&query=<?= rawurlencode($o['address']) ?>" target="_blank" rel="noopener noreferrer" style="font-size: 0.84rem; display: inline-flex; align-items: center; gap: 4px;">
                <?= e(adm_t('open_google_maps')) ?>
              </a>
            </div>
          </div>

          <div class="customer-info-label"><?= e(t('payment')) ?>:</div>
          <div class="customer-info-val">
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
          </div>

          <div class="customer-info-label"><?= e(t('invoice')) ?>:</div>
          <div class="customer-info-val"><?= $o['invoice_required'] ? e(adm_t('invoice_required_yes')) : e(adm_t('invoice_required_no')) ?></div>
        </div>

        <?php if (!empty($o['order_note'])): ?>
          <div class="customer-note-callout">
            <strong><?= e(adm_t('customer_note_heading')) ?></strong>
            <?= nl2br(e($o['order_note'])) ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Chi nhánh phân phối đơn (Dispatch) -->
      <?= hds_render_order_dispatch($o) ?>

      <!-- Thông tin Hóa đơn VAT (nếu có) -->
      <?php if (!empty($o['invoice_required'])): ?>
        <div class="order-card" style="border-left: 4px solid #2563eb;">
          <div class="order-card-title">
            <span><?= e(adm_t('invoice_vat_title')) ?></span>
          </div>
          <div class="customer-info-list">
            <div class="customer-info-label"><?= e(adm_t('company_name_label')) ?></div>
            <div class="customer-info-val"><?= e($o['invoice_company_name'] ?? '—') ?></div>

            <div class="customer-info-label"><?= e(adm_t('tax_id_label')) ?></div>
            <div class="customer-info-val"><code><?= e($o['invoice_tax_id'] ?? '—') ?></code></div>

            <div class="customer-info-label"><?= e(adm_t('company_address_label')) ?></div>
            <div class="customer-info-val"><?= nl2br(e($o['invoice_company_address'] ?? '—')) ?></div>

            <div class="customer-info-label"><?= e(adm_t('company_rep_label')) ?></div>
            <div class="customer-info-val"><?= e(($o['invoice_company_representative'] ?? '') ?: '—') ?></div>

            <div class="customer-info-label"><?= e(adm_t('company_phone_label')) ?></div>
            <div class="customer-info-val"><?= e(($o['invoice_company_phone'] ?? '') ?: '—') ?></div>

            <div class="customer-info-label"><?= e(adm_t('invoice_email_label')) ?></div>
            <div class="customer-info-val">
              <?php if (!empty($o['invoice_email'])): ?>
                <a href="mailto:<?= e($o['invoice_email']) ?>"><?= e($o['invoice_email']) ?></a>
              <?php else: ?>
                —
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Trạng thái Email thông báo -->
      <div class="order-card">
        <div class="order-card-title">
          <span><?= e(adm_t('email_notification_title')) ?></span>
        </div>
        <div class="customer-info-list" style="grid-template-columns: 110px 1fr;">
          <div class="customer-info-label"><?= e(adm_t('email_status_label')) ?></div>
          <div class="customer-info-val">
            <span class="status <?= e(($o['notification_email_status'] ?? 'pending') === 'sent' ? 'done' : (($o['notification_email_status'] ?? 'pending') === 'failed' ? 'cancelled' : 'new')) ?>">
              <?= e(order_notification_status_label((string)($o['notification_email_status'] ?? 'pending'))) ?>
            </span>
          </div>

          <div class="customer-info-label"><?= e(adm_t('email_attempts_label')) ?></div>
          <div class="customer-info-val"><?= number_format((int)($o['notification_email_attempts'] ?? 0)) ?></div>

          <?php if (!empty($o['notification_email_sent_at'])): ?>
            <div class="customer-info-label"><?= e(adm_t('email_sent_at_label')) ?></div>
            <div class="customer-info-val"><?= e(format_datetime($o['notification_email_sent_at'])) ?></div>
          <?php endif; ?>
        </div>

        <form method="post" style="margin-top: 14px;">
          <?= csrf_field() ?>
          <button class="btn small" type="submit" name="action" value="resend_email">
            <?= e(adm_t('btn_resend_email')) ?>
          </button>
        </form>
      </div>

    </div>

    <!-- CỘT PHẢI: Chi tiết món đặt, Thanh toán & Thu ngân, Đơn nhóm -->
    <div class="order-col-right">

      <!-- Danh sách món nước / Chi tiết đơn hàng -->
      <div class="order-card">
        <div class="order-card-title">
          <span><?= e(adm_t('order_details_heading')) ?></span>
          <span style="font-size: 0.85rem; color: #6b7280; font-weight: normal;"><?= e(adm_t('items_count', ['count' => count($items)])) ?></span>
        </div>
        <div class="table-wrap" style="overflow-x: auto;">
          <table class="order-items-table">
            <thead>
              <tr>
                <th style="min-width: 140px;"><?= e(t('drink')) ?></th>
                <th><?= e(t('cup_size')) ?></th>
                <th><?= e(adm_t('th_ice_sugar')) ?></th>
                <th><?= e(t('toppings')) ?></th>
                <th style="text-align: center;"><?= e(adm_t('th_qty')) ?></th>
                <th style="text-align: right;"><?= e(t('unit_price')) ?></th>
                <th style="text-align: right;"><?= e(t('subtotal')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $i): ?>
                <tr>
                  <td class="item-name-box">
                    <strong><?= e($i['product_name']) ?></strong>
                    <?php if (!empty($i['group_member_name'])): ?>
                      <span class="item-group-buyer">👤 <?= e(gt('ordered_by')) ?>: <?= e($i['group_member_name']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td><?= e($i['cup_size'] ?? '—') ?></td>
                  <td class="item-options-text">
                    <?= e(adm_t('ice_prefix', ['val' => $i['ice_choice'] ?: adm_t('ice_sugar_standard')])) ?><br>
                    <?= e(adm_t('sugar_prefix', ['val' => $i['sugar_choice'] ?: adm_t('ice_sugar_standard')])) ?>
                  </td>
                  <td>
                    <?php if (!empty($i['toppings_text'])): ?>
                      <span class="item-toppings-pill"><?= e($i['toppings_text']) ?></span>
                    <?php else: ?>
                      <span style="color: #9ca3af;">—</span>
                    <?php endif; ?>
                  </td>
                  <td style="text-align: center; font-weight: 700; font-size: 1rem;">
                    <?= $i['qty'] ?>
                  </td>
                  <td style="text-align: right; color: #4b5563;">
                    <?= e(currency()) ?><?= money($i['unit_price']) ?>
                  </td>
                  <td style="text-align: right; font-weight: 700; color: #111827;">
                    <?= e(currency()) ?><?= money($i['line_total']) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Bảng tính tiền & Khuyến mãi -->
        <div class="order-summary-box">
          <?php if (isset($o['subtotal_amount']) && (float)$o['subtotal_amount'] > 0): ?>
            <div class="order-summary-row">
              <span><?= e(adm_t('subtotal_label')) ?></span>
              <strong><?= e(currency()) ?><?= money($o['subtotal_amount']) ?></strong>
            </div>
          <?php endif; ?>

          <?php if (!empty($o['coupon_code'])): ?>
            <div class="order-summary-row discount-line">
              <span><?= e(adm_t('coupon_code_label', ['code' => $o['coupon_code']])) ?></span>
              <strong>-<?= e(currency()) ?><?= money($o['discount_amount'] ?? 0) ?></strong>
            </div>
          <?php endif; ?>

          <div class="order-summary-row grand-total">
            <span><?= e(adm_t('order_total_payment')) ?>:</span>
            <span><?= e(currency()) ?><?= money($o['total_amount']) ?></span>
          </div>

          <?php if (minigame_enabled()): ?>
            <div style="font-size: 0.8rem; color: #6b7280; margin-top: 4px;">
              <?= e(adm_t('minigame_status', ['eligible' => number_format($eligibleQty), 'awarded' => number_format((int)($o['game_chances_awarded'] ?? 0))])) ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Thanh toán & Thu ngân & Đối soát -->
      <?= htp_render_staff($o) ?>

      <!-- Group Payment Overview (nếu là đơn nhóm) -->
      <?php $groupPayment = hyg_order_payment_summary($o);
      if ($groupPayment): ?>
        <section class="panel"><?= hyg_render_payment_overview($groupPayment) ?></section>
      <?php endif; ?>

    </div>

  </div>

  <!-- Lịch sử thay đổi trạng thái (Audit History) - Toàn chiều rộng bên dưới -->
  <div class="order-bottom-full">
    <?= htow_render_history($o) ?>
  </div>

</div>
<?= htow_render_history($o) ?>

<section class="panel">
  <h2><?= e(t('order_email_delivery')) ?></h2>
  <dl class="meta">
    <dt><?= e(t('email_recipient')) ?></dt>
    <dd><?php $mailRecipients = order_notification_recipients();
        if ($mailRecipients): ?><?php foreach ($mailRecipients as $mailRecipient): ?><div class="email-recipient-display"><?= e($mailRecipient) ?></div><?php endforeach; ?><?php else: ?>—<?php endif; ?></dd>
    <dt><?= e(t('email_status')) ?></dt>
    <dd><span class="status <?= e(($o['notification_email_status'] ?? 'pending') === 'sent' ? 'done' : (($o['notification_email_status'] ?? 'pending') === 'failed' ? 'cancelled' : (($o['notification_email_status'] ?? 'pending') === 'partial' ? 'accepted' : 'new'))) ?>"><?= e(order_notification_status_label((string)($o['notification_email_status'] ?? 'pending'))) ?></span></dd>
    <dt><?= e(t('email_attempts')) ?></dt>
    <dd><?= number_format((int)($o['notification_email_attempts'] ?? 0)) ?></dd>
    <dt><?= e(t('email_sent_at')) ?></dt>
    <dd><?= e(!empty($o['notification_email_sent_at']) ? format_datetime($o['notification_email_sent_at']) : '—') ?></dd>
    <dt><?= e(t('email_diagnostic')) ?></dt>
    <dd><code><?= e(($o['notification_email_detail'] ?? '') ?: '—') ?></code></dd>
  </dl>
  <form method="post" class="form-grid"><?= csrf_field() ?><button class="btn" type="submit" name="action" value="resend_email"><?= e(t('resend_order_email')) ?></button></form>
</section>

<?php if (!empty($o['invoice_required'])): ?>
  <section class="panel">
    <h2><?= e(t('invoice_info')) ?></h2>
    <dl class="meta invoice-meta">
      <dt><?= e(t('company_name')) ?></dt>
      <dd><?= e($o['invoice_company_name'] ?? '') ?></dd>
      <dt><?= e(t('tax_id')) ?></dt>
      <dd><?= e($o['invoice_tax_id'] ?? '') ?></dd>
      <dt><?= e(t('company_address')) ?></dt>
      <dd><?= nl2br(e($o['invoice_company_address'] ?? '')) ?></dd>
      <dt><?= e(t('company_representative')) ?></dt>
      <dd><?= e(($o['invoice_company_representative'] ?? '') ?: '—') ?></dd>
      <dt><?= e(t('company_phone')) ?></dt>
      <dd><?= e($o['invoice_company_phone'] ?? '') ?></dd>
      <dt><?= e(t('invoice_email')) ?></dt>
      <dd><?php if (!empty($o['invoice_email'])): ?><a href="mailto:<?= e($o['invoice_email']) ?>"><?= e($o['invoice_email']) ?></a><?php else: ?>—<?php endif; ?></dd>
    </dl>
  </section>
<?php endif; ?>

<section class="panel">
  <h2><?= e(t('order_details')) ?></h2>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e(t('drink')) ?></th>
          <th><?= e(t('cup_size')) ?></th>
          <th><?= e(t('ice')) ?></th>
          <th><?= e(t('sugar')) ?></th>
          <th><?= e(t('toppings')) ?></th>
          <th><?= e(t('quantity')) ?></th>
          <th><?= e(t('unit_price')) ?></th>
          <th><?= e(t('subtotal')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $i): ?><tr>
            <td><?= e($i['product_name']) ?><?php if (!empty($i['group_member_name'])): ?><small class="hyg-order-tag"><?= e(gt('ordered_by')) ?>：<?= e($i['group_member_name']) ?></small><?php endif; ?></td>
            <td><?= e($i['cup_size'] ?? '—') ?></td>
            <td><?= e($i['ice_choice'] ?: '—') ?></td>
            <td><?= e($i['sugar_choice'] ?: '—') ?></td>
            <td><?= e($i['toppings_text'] ?: '—') ?></td>
            <td><?= $i['qty'] ?></td>
            <td><?= e(currency()) ?><?= money($i['unit_price']) ?></td>
            <td><?= e(currency()) ?><?= money($i['line_total']) ?></td>
          </tr><?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="order-total-breakdown">
    <?php if (!empty($o['coupon_code'])): ?><div><span><?= e(t('coupon')) ?> · <?= e($o['coupon_code']) ?></span><strong><?= e(t('applied')) ?></strong></div><?php endif; ?>
    <?php if (isset($o['subtotal_amount']) && (float)$o['subtotal_amount'] > 0): ?><div><span><?= e(t('subtotal')) ?></span><strong><?= e(currency()) ?><?= money($o['subtotal_amount']) ?></strong></div><?php endif; ?>
    <?php if (!empty($o['discount_amount'])): ?><div class="discount-line"><span><?= e(t('coupon_discount')) ?></span><strong>-<?= e(currency()) ?><?= money($o['discount_amount']) ?></strong></div><?php endif; ?>
    <div class="grand-total"><?= e(t('grand_total')) ?> <strong><?= e(currency()) ?><?= money($o['total_amount']) ?></strong></div>
  </div>
</section>
<?php admin_footer(); ?>