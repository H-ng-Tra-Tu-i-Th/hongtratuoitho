<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/promotion_views.php';
require_once __DIR__ . '/lib/game_entry.php';

header('Cache-Control: private, no-store');

$no = is_string($_SESSION['last_order_no'] ?? null) ? $_SESSION['last_order_no'] : '';
$savedOrder = hge_last_order();

$paramOrder = is_string($_GET['order'] ?? null) ? trim($_GET['order']) : '';
if (!$savedOrder && $paramOrder !== '' && preg_match('/^[A-Za-z0-9_-]{1,80}$/D', $paramOrder)) {
    $found = hyg_one('SELECT * FROM orders WHERE order_no = ? LIMIT 1', [$paramOrder]);
    if ($found && htp_customer_allowed($found)) {
        $savedOrder = $found;
        $no = $paramOrder;
    }
}

$promotion = $_SESSION['last_order_promotion'] ?? null;
$gameEntry = hge_order_state($savedOrder);

if ($gameEntry['state'] === 'ready') {
    header('Location: ' . $gameEntry['url'], true, 303);
    exit;
}

// Fetch order items if order exists
$orderItems = [];
if ($savedOrder && !empty($savedOrder['id'])) {
    $orderItems = hyg_all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC', [(int)$savedOrder['id']]);
}

// Check payment receipt state
$receipt = ($savedOrder && !empty($savedOrder['id'])) ? htp_receipt((int)$savedOrder['id']) : null;
$isConfirmed = $receipt && in_array($receipt['status'] ?? '', ['confirmed', 'overpaid'], true);
$isTransfer = ($savedOrder['payment_method'] ?? '') === 'transfer';
$orderNo = $no ?: ($savedOrder['order_no'] ?? '');

// If customer has an unconfirmed transfer order, send them to payment.php to pay first
if ($savedOrder && $isTransfer && !$isConfirmed) {
    header('Location: ' . base_url('payment.php?order=' . rawurlencode($savedOrder['order_no'])), true, 303);
    exit;
}

front_header(t('order_sent'));
?>
<link rel="stylesheet" href="<?= e(base_url('assets/css/sepay-payment.css?v=1.0.0')) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/order-success.css?v=1.0.0')) ?>">

<div class="order-success-container">
  <?php if ($savedOrder || $orderNo): ?>
    <!-- 1. Hero Confirmation Banner -->
    <header class="order-success-hero">
      <div class="order-hero-icon-wrap" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
      </div>
      <span class="order-hero-eyebrow"><?= e(sepay_t('success_eyebrow')) ?></span>
      <h1 class="order-hero-title"><?= e(sepay_t('success_heading')) ?></h1>
      <p class="order-hero-desc">
        <?php if ($isTransfer && $isConfirmed): ?>
          <?= e(sepay_t('sepay_success_desc_order')) ?>
        <?php else: ?>
          <?= e(sepay_t('success_desc_cash')) ?>
        <?php endif; ?>
      </p>

      <?php if ($orderNo): ?>
        <div class="order-code-badge">
          <span class="order-code-label"><?= e(t('order_number')) ?>:</span>
          <strong class="order-code-val"><?= e($orderNo) ?></strong>
          <button type="button" class="btn-order-copy" data-sepay-copy="<?= e($orderNo) ?>" title="<?= e(sepay_t('success_copy_code')) ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
            </svg>
            <span><?= e(sepay_t('success_copy_code')) ?></span>
          </button>
        </div>
      <?php endif; ?>
    </header>

    <!-- 2. Responsive Two-Column Layout -->
    <div class="order-success-layout">
      <!-- Left Column: Payment & Fulfillment Details -->
      <main class="order-success-primary">
        <!-- A. Payment Status Card -->
        <?php if ($savedOrder && $isTransfer && $isConfirmed): ?>
          <section class="order-paid-status-card" aria-label="<?= e(sepay_t('sepay_success_title')) ?>">
            <div class="order-paid-badge">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              <?= e(sepay_t('sepay_success_title')) ?>
            </div>
            <p><?= e(sepay_t('sepay_success_desc_order')) ?></p>
            <div class="order-paid-meta">
              <span><?= e(sepay_t('sepay_amount')) ?>: <strong><?= e(currency()) ?><?= money($savedOrder['total_amount'] ?? 0) ?></strong></span>
              <span><?= e(sepay_t('sepay_status')) ?>: <b class="badge-success"><?= e(sepay_t('sepay_status_paid')) ?></b></span>
            </div>
          </section>
        <?php elseif ($savedOrder): ?>
          <section class="order-cod-card" aria-label="<?= e(sepay_t('success_cod_title')) ?>">
            <div class="order-cod-head">
              <div class="order-cod-icon" aria-hidden="true">💵</div>
              <h2 class="order-cod-title"><?= e(sepay_t('success_cod_title')) ?></h2>
            </div>
            <p class="order-cod-desc"><?= e(sepay_t('success_cod_desc')) ?></p>
            <div class="order-cod-total-box">
              <span><?= e(t('total')) ?>:</span>
              <strong><?= e(currency()) ?><?= money($savedOrder['total_amount'] ?? 0) ?></strong>
            </div>
          </section>
        <?php endif; ?>

        <!-- B. Fulfillment & Delivery Information -->
        <?php if ($savedOrder): ?>
          <section class="order-info-card">
            <div class="order-card-header">
              <h2>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                  <circle cx="12" cy="10" r="3"></circle>
                </svg>
                <?= e(sepay_t('success_delivery_title')) ?>
              </h2>
            </div>

            <div class="order-delivery-grid">
              <div class="order-delivery-item">
                <div class="order-delivery-item-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                </div>
                <div class="order-delivery-item-body">
                  <div class="order-delivery-label"><?= e(sepay_t('success_recipient')) ?></div>
                  <div class="order-delivery-text">
                    <strong><?= e($savedOrder['customer_name'] ?: t('customer')) ?></strong>
                    <?php if (!empty($savedOrder['phone'])): ?>
                      · <a href="tel:<?= e($savedOrder['phone']) ?>"><?= e($savedOrder['phone']) ?></a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <div class="order-delivery-item">
                <div class="order-delivery-item-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                  </svg>
                </div>
                <div class="order-delivery-item-body">
                  <div class="order-delivery-label"><?= e(t('delivery_address')) ?></div>
                  <div class="order-delivery-text">
                    <?= e($savedOrder['address'] ?: '—') ?>
                  </div>
                </div>
              </div>
            </div>

            <?php if (!empty($savedOrder['dispatch_store_name'])): ?>
              <div class="order-store-badge-card">
                <div class="order-store-info">
                  <span class="order-delivery-label"><?= e(sepay_t('success_store_title')) ?>:</span>
                  <strong><?= e($savedOrder['dispatch_store_name']) ?></strong>
                  <?php if (!empty($savedOrder['dispatch_store_address'])): ?>
                    <span><?= e($savedOrder['dispatch_store_address']) ?></span>
                  <?php endif; ?>
                </div>
                <a class="btn-store-call" href="tel:0939032638">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                  </svg>
                  <span><?= e(sepay_t('success_call_branch')) ?></span>
                </a>
              </div>
            <?php endif; ?>

            <?php if (!empty($savedOrder['order_note'])): ?>
              <div class="order-note-callout">
                <strong><?= e(sepay_t('success_order_note')) ?></strong> <?= e($savedOrder['order_note']) ?>
              </div>
            <?php endif; ?>
          </section>
        <?php endif; ?>
      </main>

      <!-- Right Column: Order Items Summary & Companion Actions -->
      <aside class="order-success-sidebar">
        <!-- A. Order Items Summary -->
        <?php if ($orderItems): ?>
          <section class="order-items-card">
            <div class="order-card-header">
              <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                  <line x1="3" y1="6" x2="21" y2="6"></line>
                  <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
                <?= e(sepay_t('success_order_items')) ?> (<?= count($orderItems) ?>)
              </h3>
            </div>

            <div class="order-items-list">
              <?php foreach ($orderItems as $item): ?>
                <div class="order-summary-item">
                  <div class="order-item-main">
                    <h4>
                      <span class="order-item-qty">x<?= (int)$item['qty'] ?></span>
                      <?= e($item['product_name']) ?>
                      <?php if (!empty($item['cup_size'])): ?> · <?= e($item['cup_size']) ?><?php endif; ?>
                    </h4>
                    <div class="order-item-meta">
                      <?php
                        $opts = array_filter([$item['ice_choice'] ?? '', $item['sugar_choice'] ?? '']);
                        if ($opts) echo e(implode(' / ', $opts));
                      ?>
                      <?php if (!empty($item['toppings_text'])): ?>
                        <div>+ <?= e($item['toppings_text']) ?></div>
                      <?php endif; ?>
                      <?php if (!empty($item['group_member_name'])): ?>
                        <div class="muted"><?= e(gt('ordered_by')) ?>: <?= e($item['group_member_name']) ?></div>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="order-item-price">
                    <?= e(currency()) ?><?= money($item['line_total']) ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <!-- Financial Breakdown -->
            <div class="order-totals-breakdown">
              <div class="order-total-row">
                <span><?= e(t('subtotal')) ?></span>
                <strong><?= e(currency()) ?><?= money($savedOrder['subtotal_amount'] ?? 0) ?></strong>
              </div>
              <?php if (!empty($savedOrder['discount_amount']) && (float)$savedOrder['discount_amount'] > 0): ?>
                <div class="order-total-row discount">
                  <span>
                    <?= e(t('discount_amount') ?? 'Giảm giá') ?>
                    <?php if (!empty($savedOrder['coupon_code'])): ?>(<?= e($savedOrder['coupon_code']) ?>)<?php endif; ?>
                  </span>
                  <strong>-<?= e(currency()) ?><?= money($savedOrder['discount_amount']) ?></strong>
                </div>
              <?php endif; ?>
              <div class="order-total-row final">
                <span><?= e(t('total')) ?></span>
                <strong><?= e(currency()) ?><?= money($savedOrder['total_amount'] ?? 0) ?></strong>
              </div>
            </div>
          </section>
        <?php endif; ?>

        <!-- B. Lucky Mini-game Entry (If eligible) -->
        <?= hge_render($gameEntry, base_url('actions/game_status.php')) ?>

        <!-- C. Promotion Voucher Summary (If any) -->
        <?php if (is_array($promotion)): ?>
          <div class="order-promotion-slot">
            <?= promotion_summary($promotion) ?>
          </div>
        <?php endif; ?>

        <!-- D. Quick Action Buttons -->
        <section class="order-actions-card">
          <div class="order-action-btns">
            <a class="btn-primary-order" href="<?= e(base_url('index.php')) ?>">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <polyline points="15 18 9 12 15 6"></polyline>
              </svg>
              <span><?= e(sepay_t('success_order_more')) ?></span>
            </a>
          </div>
        </section>
      </aside>
    </div>

    <!-- 3. Guest Session Orders (Discrete bottom bar) -->
    <?php $sessionOrdersHtml = htp_render_session_orders(); ?>
    <?php if ($sessionOrdersHtml): ?>
      <div class="order-session-chips">
        <?= $sessionOrdersHtml ?>
      </div>
    <?php endif; ?>

  <?php else: ?>
    <!-- Empty Fallback State -->
    <div class="empty">
      <div class="empty-icon">☕</div>
      <h2><?= e(t('cart_empty')) ?></h2>
      <p><?= e(t('cart_empty_desc')) ?></p>
      <a class="btn primary" href="<?= e(base_url('index.php')) ?>"><?= e(t('return_menu')) ?></a>
    </div>
  <?php endif; ?>
</div>

<?= sepay_render_js_i18n() ?>
<script src="<?= e(base_url('assets/js/sepay-payment.js?v=1.0.0')) ?>" defer></script>
<?php front_footer(); ?>
