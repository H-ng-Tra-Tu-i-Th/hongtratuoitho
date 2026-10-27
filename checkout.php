<?php
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/group_payment_views.php';
require_once __DIR__ . '/lib/promotion_views.php';
require_once __DIR__ . '/lib/customer_checkout.php';
require_once __DIR__ . '/lib/group_views.php'; // hyg_render_steps(): group order guide
header('Cache-Control: private, no-store, max-age=0');
$groupCheckout = null;
$cart = $_SESSION['cart'] ?? [];
if (!empty($_GET['group'])) {
  try {
    $groupCheckout = hyg_checkout(hyg_code($_GET['group']));
    if ($groupCheckout['already'])
      redirect(hyg_room_url($_GET['group']));
    $cart = $groupCheckout['cart'];
  } catch (Throwable $e) {
    hyg_error_page($e);
  }
}
// Recover an uncertain ordinary submission before an already-used coupon can invalidate its cart.
if (!$groupCheckout && ($uncertainToken = hcq_unknown_token(hcq_context()))) {
  $uncertainRequest = hcq_lookup($uncertainToken, hcq_context());
  try {
    $recovered = hcq_existing_order($uncertainToken, hcq_context());
    if (!$recovered)
      $recovered = hyg_one('SELECT * FROM orders WHERE order_no=?', [$uncertainRequest['order_no']]);
    if ($recovered && htp_customer_allowed($recovered)) {
      try {
        if ($cart && hash_equals($uncertainRequest['signature'], promotion_quote_signature($cart))) {
          $_SESSION['cart'] = [];
          clear_coupon();
          unset($_SESSION['promotion_mode']);
        }
      } catch (Throwable $ignored) {
      }
      hcq_saved($uncertainToken, $recovered['order_no']);
      header('Location: ' . base_url('payment.php?order=' . rawurlencode($recovered['order_no'])), true, 303);
      exit;
    }
  } catch (Throwable $ignored) {
  }
  front_header(t('order_info'));
  echo '<section class="panel"><div class="alert danger" role="alert"><p>' . e(htp_ux('unknown')) . '</p><a class="btn" href="' . e(base_url('payment.php?order=' . rawurlencode($uncertainRequest['order_no']))) . '">' . e(htp_ux('check_order')) . '</a></div><a class="btn" href="' . e(base_url('index.php')) . '">' . e(t('return_menu')) . '</a></section>';
  front_footer();
  exit;
}
if (!$cart)
  redirect(base_url('index.php'));
$promotion = $groupCheckout ? promotion_room_choice($groupCheckout['room'], $cart) : promotion_cart_choice($cart);
if (!$groupCheckout && !$promotion['valid']) {
  $_SESSION['coupon_error'] = pt('selection_changed');
  hcgs_release_expired();
  redirect(base_url('cart.php'));
}
$coupon = $groupCheckout ? $groupCheckout['coupon'] : cart_coupon_summary($cart);
if (!$groupCheckout && $promotion['mode'] === 'reward' && (float) $coupon['discount'] <= 0) {
  $_SESSION['coupon_error'] = pt('reward_no_discount');
  hcgs_release_expired();
  redirect(base_url('cart.php'));
}
$checkoutSubtotal = cart_subtotal($cart);
$checkoutTotal = max(0, $checkoutSubtotal - $coupon['discount']);
$groupPayment = $groupCheckout ? hyg_payment_summary($groupCheckout['room'], $cart, $coupon) : null;
$groupPayCode = $groupPayment ? htp_group_order_method($groupPayment) : '';
$hostCollect = ($groupPayment['collection_mode'] ?? 'store') === 'host';
$bankAccounts = active_bank_accounts();
if (!$groupCheckout || $hostCollect)
  $bankAccounts = []; // Resolve the recipient from the saved order, never a recommendation.
$checkoutMember = hym_current_member();
$memberDefaults = hym_checkout_defaults($checkoutMember);
$checkoutContext = hcq_context($groupCheckout['room']['public_id'] ?? '');
$draft = hcq_draft($checkoutContext);
$values = $draft['values'];
$fieldErrors = $draft['errors'];
$values += ['customer_name' => $memberDefaults['name'], 'phone' => $memberDefaults['phone'], 'address' => $memberDefaults['address'], 'payment_method' => 'transfer', 'invoice_required' => '0'];
foreach (hcq_fields() as $field => $max)
  $values[$field] ??= '';

$sessionOrders = $_SESSION['customer_payment_orders'] ?? [];
if (!is_array($sessionOrders)) $sessionOrders = [];
if (!empty($_SESSION['last_order_no'])) $sessionOrders[] = $_SESSION['last_order_no'];
$checkPhone = !empty($values['phone']) ? $values['phone'] : ($memberDefaults['phone'] ?? '');

$pendingPaymentOrder = customer_find_pending_payment_order(
  $checkoutMember ? (int)$checkoutMember['id'] : null,
  $checkPhone,
  $sessionOrders
);
$checkoutError = $_SESSION['checkout_errors'][$checkoutContext] ?? '';
unset($_SESSION['checkout_errors'][$checkoutContext]);
$checkoutSignature = $groupCheckout ? (string) $groupCheckout['room']['lock_signature'] : promotion_quote_signature($cart);
$checkoutToken = hcq_request($checkoutContext, $checkoutSignature);
$checkoutRequest = hcq_lookup($checkoutToken, $checkoutContext);
$checkoutUnknown = ($checkoutRequest['state'] ?? '') === 'unknown';
if ($checkoutUnknown) {
  try {
    $recovered = hcq_existing_order($checkoutToken, $checkoutContext);
    if (!$recovered)
      $recovered = hyg_one('SELECT * FROM orders WHERE order_no=?', [$checkoutRequest['order_no']]);
    if ($recovered && htp_customer_allowed($recovered)) {
      if (!$groupCheckout && $cart && hash_equals($checkoutRequest['signature'], promotion_quote_signature($cart))) {
        $_SESSION['cart'] = [];
        clear_coupon();
        unset($_SESSION['promotion_mode']);
      }
      hcq_saved($checkoutToken, $recovered['order_no']);
      header('Location: ' . base_url('payment.php?order=' . rawurlencode($recovered['order_no'])), true, 303);
      exit;
    }
  } catch (Throwable $e) {
  }
}
// Every full ten drinks grants one chance now; the session locks the cart (or the group room) until it is submitted or expires.
$gameSession = null;
if (!$checkoutUnknown) {
  $gameChances = $promotion['mode'] === 'draw' && (float)$coupon['discount'] <= 0 ? (int)$promotion['expected_chances'] : 0;
  $gameSession = hcgs_open((int)$promotion['eligible_qty'], $gameChances, (string)($groupCheckout['room']['public_id'] ?? ''));
  // A prize from an earlier, already paid order must not show up in this session's game.
  try {
    hcgs_forget_old_board($gameSession);
  } catch (Throwable $ignored) {
  }
}
// Money prizes won in this session are summed and taken off the bill straight away.
// An expired session keeps applying the prizes it already won.
$gamePrizeSession = $gameSession ?: hcgs_prize_row((string)($groupCheckout['room']['public_id'] ?? ''));
$gameCash = hcgs_cash_discount($cart, $coupon, $gamePrizeSession);
$checkoutTotal = max(0, $checkoutTotal - $gameCash['discount']);
// Free drink/topping prizes are added as 0đ gift lines with the quantity set in the prize settings.
$gameGifts = hcgs_gift_rewards($gamePrizeSession);
$gameFlash = $_SESSION['hcgs_flash'] ?? '';
unset($_SESSION['hcgs_flash']);
if ($groupCheckout) $GLOBALS['hyg_header_room'] = $groupCheckout['room'];
front_header(t('order_info'));
?>
<?php /* TẠM TẮT khối "Tên nhóm / Về giỏ nhóm"
<?php if ($groupCheckout): ?><section class="hyg-checkout-note"><strong><?= e(gt('order_group')) ?>：<?= e($groupCheckout['room']['title']) ?></strong>
    <p><?= e($hostCollect ? htp('collection_before_order') : gt($groupPayment['mode'] === 'split' ? 'split_owner_checkout' : 'owner_checkout')) ?></p>
    <p><?= e(gt('locked_snapshot')) ?></p><a class="btn" href="<?= e(hyg_room_url($groupCheckout['room']['public_id'])) ?>"><?= e(gt('back_room')) ?></a>
  </section><?php endif; ?>
*/ ?>
<?php if ($groupCheckout): ?>
  <!-- Group order guide: the group checkout is step 4 (choose an offer & confirm the order). -->
  <?= hyg_render_steps(4) ?>
<?php endif; ?>
<div class="checkout-wrap">
  <section>
    <span class="eyebrow"><?= e(t('checkout')) ?></span>
    <h1><?= e(t('order_info')) ?></h1>
    <!-- Toast Alert nổi 3 giây thông báo lỗi và tự động cuộn lên xem (Cả Mobile & Desktop) -->
    <div id="pending-toast-alert" class="pending-toast-alert" role="alert" aria-live="assertive" style="<?= $pendingPaymentOrder ? '' : 'display:none;' ?>">
      <div class="pending-toast-content">
        <span class="pending-toast-icon" aria-hidden="true">⚠️</span>
        <div class="pending-toast-text">
          <strong id="pending-toast-title"><?= e(client_t('pending_order_toast_title')) ?></strong>
          <span id="pending-toast-desc"><?= $pendingPaymentOrder ? client_t('pending_order_toast_desc', ['order_no' => e($pendingPaymentOrder['order_no'])]) : '' ?></span>
        </div>
        <button type="button" class="pending-toast-close" id="pending-toast-close" aria-label="Close">&times;</button>
      </div>
      <div class="pending-toast-progress-bar">
        <div class="pending-toast-progress-fill" id="pending-toast-progress"></div>
      </div>
    </div>

    <div class="alert danger checkout-error-summary" id="pending-order-alert" role="alert" tabindex="-1" style="<?= $pendingPaymentOrder ? '' : 'display:none;' ?>">
      <p>
        <strong id="pending-alert-title"><?= e(client_t('pending_order_exists_title')) ?></strong><br>
        <span id="pending-alert-desc">
          <?php if ($pendingPaymentOrder): ?>
            <?= client_t('pending_order_exists_desc', [
              'order_no' => e($pendingPaymentOrder['order_no']),
              'amount' => e(currency()) . money($pendingPaymentOrder['total_amount']),
            ]) ?>
          <?php endif; ?>
        </span>
      </p>
      <div style="margin-top: 10px;">
        <a class="btn primary" id="pending-alert-pay-btn" href="<?= $pendingPaymentOrder ? e(base_url('payment.php?order=' . rawurlencode($pendingPaymentOrder['order_no']))) : '#' ?>">
          <?= e(client_t('pending_order_pay_now')) ?>
        </a>
      </div>
    </div>

    <?php if ($checkoutUnknown): ?>
      <div class="alert danger checkout-error-summary" role="alert">
        <p><?= e(htp_ux('unknown')) ?></p><a class="btn"
          href="<?= e(base_url('payment.php?order=' . rawurlencode($checkoutRequest['order_no']))) ?>"><?= e(htp_ux('check_order')) ?></a>
      </div><?php elseif ($checkoutError || $fieldErrors): ?>
      <div class="alert danger checkout-error-summary" role="alert" tabindex="-1" data-checkout-errors>
        <?= e($checkoutError ?: htp_ux('draft_error')) ?></div><?php endif; ?>
    <?php if ($gameFlash): ?><div class="alert danger" role="alert"><?= e($gameFlash) ?></div><?php endif; ?>
    <?php if ($gameSession): ?>
      <section class="alert success checkout-game-session" data-game-session data-remaining="<?= max(0, (int)$gameSession['remaining']) ?>">
        <p><strong><?= e(hcgs_text('opened', ['qty' => (int)$gameSession['drink_qty'], 'chances' => (int)$gameSession['granted_chances']])) ?></strong></p>
        <p><?= e(hcgs_text($groupCheckout ? 'group_locked' : 'locked')) ?></p>
        <p><?= e(hcgs_text('expires', ['minutes' => intdiv(HCGS_TTL, 60)])) ?></p>
        <p><?= e(hcgs_text('forfeit_note')) ?></p>
        <p><?= e(hcgs_text('remaining')) ?>: <b data-game-session-clock>--:--</b></p>
        <button type="button" class="btn primary" onclick="openMinigameModal()">🎮 Chơi Mini Game nhận quà</button>
      </section>
      <script>
        (function() {
          const box = document.querySelector('[data-game-session]');
          const clock = box && box.querySelector('[data-game-session-clock]');
          if (!clock) return;
          let end = Date.now() + Number(box.dataset.remaining) * 1000;
          // The Mini Game restarts the clock after every finished play.
          window.hcgsSetClock = function(seconds) {
            end = Date.now() + Math.max(0, Number(seconds) || 0) * 1000;
          };
          const tick = function() {
            const left = Math.max(0, Math.round((end - Date.now()) / 1000));
            clock.textContent = String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
            // Expiry is decided on the server; reload slightly after it so the session is already ended.
            if (left === 0) {
              clearInterval(timer);
              setTimeout(function() {
                location.reload();
              }, 1500);
            }
          };
          const timer = setInterval(tick, 1000);
          tick();
        })();
      </script>
    <?php endif; ?>
    <?php
    $isMember = (bool)$checkoutMember;
    $savedDeliveryAddress = $isMember ? trim((string)($checkoutMember['delivery_address'] ?? '')) : '';
    $hasSavedAddress = ($savedDeliveryAddress !== '');
    $addressMode = $hasSavedAddress ? 'saved' : 'new';
    if (!empty($_POST['address_mode']) && in_array($_POST['address_mode'], ['saved', 'new'], true)) {
      $addressMode = $_POST['address_mode'];
    } elseif (!empty($_SESSION['checkout_drafts'][$checkoutContext]['values']['address_mode'])) {
      $addressMode = $_SESSION['checkout_drafts'][$checkoutContext]['values']['address_mode'];
    }
    ?>

    <?php if (!$isMember): ?>
      <div class="member-checkout-note">
        <p><?= e(sepay_t('addr_guest_login_hint')) ?></p><a
          href="<?= e(base_url('member/index.php')) ?>"><?= e(mt('member_entry')) ?></a>
      </div>
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/location-selector.css?v=1.2.2')) ?>">
    <div class="location-container" data-location-container
      data-location-endpoint="<?= e(base_url('api/location.php')) ?>"
      data-stores-endpoint="<?= e(base_url('api/stores.php')) ?>">
      <form method="post" action="<?= e(base_url('actions/submit_order.php')) ?>" class="checkout-form" id="checkoutForm"
        data-checkout-form data-game-remaining="<?= hcgs_remaining($gameSession) ?>" data-submitting-label="<?= e(htp_ux('submitting')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="checkout_token" value="<?= e($checkoutToken) ?>">
        <input type="hidden" name="selected_store_id" value="">
        <?php if (!$groupCheckout): ?><input type="hidden" name="promotion_signature"
            value="<?= e(promotion_quote_signature($cart)) ?>"><?php endif; ?>
        <?php if ($groupCheckout): ?><input type="hidden" name="group_id"
            value="<?= e($groupCheckout['room']['public_id']) ?>"><input type="hidden" name="group_revision"
            value="<?= (int) $groupCheckout['room']['revision'] ?>"><input type="hidden" name="group_signature"
            value="<?= e($groupCheckout['room']['lock_signature']) ?>"><?php endif; ?>
        <label><?= e(t('customer_name')) ?><input name="customer_name" value="<?= e($values['customer_name']) ?>"
            required maxlength="100" autocomplete="name" <?= hcq_field_attrs($fieldErrors, 'customer_name') ?>><?= hcq_field_error($fieldErrors, 'customer_name') ?></label>
        <label><?= e(t('phone')) ?><input name="phone" value="<?= e($values['phone']) ?>" required maxlength="50"
            inputmode="tel" autocomplete="tel" <?= hcq_field_attrs($fieldErrors, 'phone') ?>><?= hcq_field_error($fieldErrors, 'phone') ?></label>

        <div class="address-input-wrap">
          <div class="address-input-action-bar">
            <label class="address-input-label">
              <span><?= e(t('delivery_address')) ?></span>
            </label>
          </div>

          <?php if ($hasSavedAddress): ?>
            <div class="address-mode-selector" data-address-mode-selector>
              <!-- (a) Giao tới địa chỉ đã lưu -->
              <label class="address-mode-card <?= $addressMode === 'saved' ? 'is-selected' : '' ?>" for="address-mode-saved">
                <div class="mode-radio-header">
                  <input type="radio" id="address-mode-saved" name="address_mode" value="saved" <?= $addressMode === 'saved' ? 'checked' : '' ?> class="mode-radio-input" data-mode-radio>
                  <span class="mode-radio-title"><?= e(sepay_t('addr_mode_saved')) ?></span>
                  <span class="mode-badge-saved">✓ <?= e(sepay_t('addr_saved_verified_badge')) ?></span>
                </div>
                <div class="saved-address-content">
                  <div class="saved-address-info">
                    <svg class="saved-address-pin-icon" viewBox="0 0 24 24" aria-hidden="true">
                      <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
                    </svg>
                    <p class="saved-address-text" data-saved-address-display><?= e($savedDeliveryAddress) ?></p>
                  </div>
                  <button type="button" class="btn-edit-saved-addr" id="btn-edit-saved-addr" title="<?= e(sepay_t('addr_btn_edit')) ?>" data-btn-edit-saved>
                    <svg class="btn-edit-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    <span><?= e(sepay_t('addr_btn_edit')) ?></span>
                  </button>
                </div>
              </label>

              <!-- (b) Giao tới địa chỉ khác -->
              <label class="address-mode-card <?= $addressMode === 'new' ? 'is-selected' : '' ?>" for="address-mode-new">
                <div class="mode-radio-header">
                  <input type="radio" id="address-mode-new" name="address_mode" value="new" <?= $addressMode === 'new' ? 'checked' : '' ?> class="mode-radio-input" data-mode-radio>
                  <span class="mode-radio-title"><?= e(sepay_t('addr_mode_new')) ?></span>
                </div>
              </label>
            </div>
          <?php else: ?>
            <input type="hidden" name="address_mode" value="new" data-mode-hidden>
          <?php endif; ?>

          <!-- Luồng chọn địa chỉ 2 cấp từ JSON cục bộ (Shopee style) trong Mini Card gọn gàng -->
          <div class="address-cascade-container" data-address-cascade
            data-json-url="<?= e(base_url('assets/data/vietnam-provinces.json?v=' . filemtime(__DIR__ . '/assets/data/vietnam-provinces.json'))) ?>"
            data-saved-address="<?= e($savedDeliveryAddress) ?>"
            data-has-saved="<?= $hasSavedAddress ? '1' : '0' ?>"
            data-address-mode="<?= e($addressMode) ?>"
            style="<?= ($hasSavedAddress && $addressMode === 'saved') ? 'display:none;' : '' ?>">

            <div class="address-box-header">
              <div class="address-box-header-titles">
                <span class="address-box-badge">📍 <?= e(sepay_t('addr_box_title')) ?></span>
                <span class="address-box-sub"><?= e(sepay_t('addr_box_sub')) ?></span>
              </div>
              <button type="button" class="btn-gps-trigger" data-btn-gps title="<?= e(sepay_t('gps_tooltip')) ?>">
                <span class="gps-radar-dot" aria-hidden="true"></span>
                <svg class="gps-icon-crosshair" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3c-.46-4.17-3.77-7.48-7.94-7.94V1h-2v2.06C6.83 3.52 3.52 6.83 3.06 11H1v2h2.06c.46 4.17 3.77 7.48 7.94 7.94V23h2v-2.06c4.17-.46 7.48-3.77 7.94-7.94H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z" />
                </svg>
                <span class="btn-gps-text"><?= e(sepay_t('gps_use_current')) ?></span>
              </button>
            </div>

            <!-- Hàng 2 cột: Tỉnh / Thành phố & Phường / Xã -->
            <div class="address-two-col-grid">
              <!-- Bước 1: Chọn Tỉnh / Thành phố -->
              <div class="address-field-group">
                <label for="address-province-input" class="address-sub-label">
                  <span class="sub-label-title"><?= e(sepay_t('addr_province_label')) ?></span><span class="required-star">*</span>
                </label>
                <div class="address-input-relative">
                  <input type="text" id="address-province-input" class="address-cascade-input"
                    placeholder="<?= e(sepay_t('addr_province_placeholder')) ?>" autocomplete="off">
                  <div class="autocomplete-dropdown" data-province-dropdown></div>
                </div>
                <input type="hidden" name="province_code" value="<?= e($values['province_code'] ?? '') ?>" data-province-code>
                <input type="hidden" name="province_name" value="<?= e($values['province_name'] ?? '') ?>" data-province-name>
              </div>

              <!-- Bước 2: Chọn Phường / Xã (bị khóa cho đến khi chọn Tỉnh) -->
              <div class="address-field-group">
                <label for="address-ward-input" class="address-sub-label">
                  <span class="sub-label-title"><?= e(sepay_t('addr_ward_label')) ?></span><span class="required-star">*</span>
                </label>
                <div class="address-input-relative">
                  <input type="text" id="address-ward-input" class="address-cascade-input"
                    placeholder="<?= e(sepay_t('addr_ward_placeholder_disabled')) ?>" autocomplete="off" disabled>
                  <div class="autocomplete-dropdown" data-ward-dropdown></div>
                </div>
                <input type="hidden" name="ward_code" value="<?= e($values['ward_code'] ?? '') ?>" data-ward-code>
                <input type="hidden" name="ward_name" value="<?= e($values['ward_name'] ?? '') ?>" data-ward-name>
              </div>
            </div>

            <!-- Bước 3: Số nhà, tên đường -->
            <div class="address-field-group">
              <label for="address-street-input" class="address-sub-label">
                <span class="sub-label-title"><?= e(sepay_t('addr_street_label')) ?></span><span class="required-star">*</span>
              </label>
              <input type="text" id="address-street-input" class="address-cascade-input"
                placeholder="<?= e(sepay_t('addr_street_placeholder')) ?>" maxlength="150"
                value="<?= e($values['street_address'] ?? '') ?>" data-street-address>
            </div>

            <!-- Nút xác nhận & tìm chi nhánh gần nhất (Hạn chế call API thừa) -->
            <div class="address-find-store-wrap">
              <button type="button" class="btn-trigger-find-store" data-btn-find-store disabled>
                <span class="btn-find-icon">🎯</span>
                <span class="btn-find-text"><?= e(sepay_t('addr_btn_confirm')) ?></span>
              </button>
              <div class="find-store-helper-text">
                <?= e(sepay_t('addr_hint')) ?>
              </div>
            </div>

            <?php if ($isMember): ?>
              <div class="save-profile-checkbox-wrap" id="save-profile-wrap">
                <label class="save-profile-label">
                  <input type="checkbox" name="save_to_profile" value="1" <?= (!$hasSavedAddress) ? 'checked' : '' ?> class="save-profile-checkbox" data-save-profile-checkbox>
                  <span class="save-profile-text"><?= e(sepay_t($hasSavedAddress ? 'addr_save_default_profile' : 'addr_save_new_profile')) ?></span>
                </label>
              </div>
            <?php endif; ?>

            <!-- Trường address tổng hợp gửi lên backend để lưu vào đơn hàng -->
            <textarea id="delivery-address-field" name="address" style="display:none;"
              <?= hcq_field_attrs($fieldErrors, 'address') ?>><?= e($values['address']) ?></textarea>
            <input type="hidden" name="delivery_address" id="delivery-address-alias" value="<?= e($values['address']) ?>">

            <div class="address-select-warning" data-address-warning role="alert"></div>
            <input type="hidden" name="address_verified" value="0" data-address-verified-input>
            <?= hcq_field_error($fieldErrors, 'address') ?>
          </div>
        </div>

        <!-- Banner chi nhánh phục vụ tối ưu (ẩn khỏi giao diện theo yêu cầu nhưng vẫn giữ DOM cho script hoạt động) -->
        <div class="location-store-banner" data-location-banner style="display: none !important;">
          <div class="location-banner-main">
            <div class="location-banner-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24">
                <path
                  d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
              </svg>
            </div>
            <div class="location-banner-content">
              <span class="location-banner-eyebrow"><?= e(sepay_t('gps_optimal_store')) ?></span>
              <div class="location-banner-title" data-banner-store-name><?= e(sepay_t('gps_auto_detecting')) ?></div>
              <div class="location-banner-distance" data-banner-distance-wrap style="display:none;">
                <span class="location-banner-badge" data-banner-distance-text></span>
                <span class="location-banner-duration" data-banner-duration-text></span>
                <span class="location-banner-shipping" data-banner-shipping-text style="display:none;"></span>
              </div>
              <div class="location-banner-reason" data-banner-reason-wrap style="display:none;">
                <span data-banner-reason-text></span>
              </div>
            </div>
          </div>
          <button type="button" class="location-banner-btn-change" data-btn-change-store><?= e(sepay_t('gps_change_store')) ?></button>
        </div>

        <fieldset>
          <legend><?= e(t('payment_method')) ?></legend>
          <?php if (false && $groupPayment): ?>
            <input type="hidden" name="payment_method" value="<?= e($groupPayCode) ?>">
            <p>
              <?= e($hostCollect ? htp('merchant_host') : gt($groupPayment['mode'] === 'split' ? 'pay_split' : 'pay_together')) ?> <?php if ($hostCollect || $groupPayment['mode'] === 'together'): ?>
                · <?= e(payment_label($groupPayCode)) ?><?php endif; ?></p>
          <?php else: ?>
            <label class="inline-radio"><input type="radio" name="payment_method" value="transfer" data-payment-toggle
                <?= $values['payment_method'] !== 'cash' ? 'checked' : '' ?>> <?= e(t('transfer')) ?></label>
            <label class="inline-radio"><input type="radio" name="payment_method" value="cash" data-payment-toggle
                <?= $values['payment_method'] === 'cash' ? 'checked' : '' ?>> <?= e(t('cash')) ?></label>
          <?php endif; ?>
        </fieldset>

        <?php if (!$groupPayment || $groupPayCode === 'transfer'): ?>

          <!-- close-test-Hai | Chuyển thanh toán thẻ trụ sở chính sang chi nhanh tự nhận chuyển khoản -->

          <!-- <section class="transfer-payment-panel" data-transfer-info>
        <div class="transfer-payment-head">
          <strong><?= e(t('transfer_payment_info')) ?></strong>
          <small<?= !$bankAccounts ? ' role="status"' : '' ?>><?= e(!$groupCheckout ? htp('order_first') : ($hostCollect ? htp('collection_before_order') : t($bankAccounts ? 'transfer_payment_help' : 'transfer_payment_later'))) ?></small>
        </div>
        <div class="transfer-account-list">
          <?php foreach ($bankAccounts as $bank): ?>
          <article class="transfer-account-card">
            <?php if (!empty($bank['qr_image'])): ?>
              <div class="transfer-qr"><img src="<?= e(base_url('uploads/' . $bank['qr_image'])) ?>" alt="<?= e(t('transfer_qr_alt')) ?>"></div>
            <?php endif; ?>
            <div class="transfer-account-details">
              <strong><?= e($bank['bank_name']) ?></strong>
              <div><span><?= e(t('account_number')) ?></span><b><?= e($bank['account_number']) ?></b></div>
              <div><span><?= e(t('account_name')) ?></span><b><?= e($bank['account_name']) ?></b></div>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      </section> -->
        <?php endif; ?>

        <fieldset>
          <legend><?= e(t('invoice_question')) ?></legend>
          <label class="inline-radio"><input type="radio" name="invoice_required" value="1" data-invoice-toggle
              <?= $values['invoice_required'] === '1' ? 'checked' : '' ?>> <?= e(t('yes')) ?></label>
          <label class="inline-radio"><input type="radio" name="invoice_required" value="0" data-invoice-toggle
              <?= $values['invoice_required'] !== '1' ? 'checked' : '' ?>> <?= e(t('no')) ?></label>
        </fieldset>

        <div class="invoice-fields" data-invoice-fields>
          <div class="invoice-fields-head">
            <strong><?= e(t('invoice_details')) ?></strong>
            <small><?= e(t('invoice_fields_required')) ?></small>
          </div>
          <div class="invoice-grid">
            <label><?= e(t('company_name')) ?><input name="invoice_company_name"
                value="<?= e($values['invoice_company_name']) ?>" maxlength="200" autocomplete="organization"
                data-invoice-required<?= hcq_field_attrs($fieldErrors, 'invoice_company_name') ?>><?= hcq_field_error($fieldErrors, 'invoice_company_name') ?></label>
            <label><?= e(t('tax_id')) ?><input name="invoice_tax_id" value="<?= e($values['invoice_tax_id']) ?>"
                maxlength="50" inputmode="numeric" data-invoice-required<?= hcq_field_attrs($fieldErrors, 'invoice_tax_id') ?>><?= hcq_field_error($fieldErrors, 'invoice_tax_id') ?></label>
            <label class="span-2"><?= e(t('company_address')) ?><textarea name="invoice_company_address" maxlength="255"
                rows="2" autocomplete="street-address" data-invoice-required<?= hcq_field_attrs($fieldErrors, 'invoice_company_address') ?>><?= e($values['invoice_company_address']) ?></textarea><?= hcq_field_error($fieldErrors, 'invoice_company_address') ?></label>
            <label><?= e(t('company_representative')) ?> <small
                class="field-optional">(<?= e(t('optional')) ?>)</small><input name="invoice_company_representative"
                value="<?= e($values['invoice_company_representative']) ?>" maxlength="120"></label>
            <label><?= e(t('company_phone')) ?><input name="invoice_company_phone"
                value="<?= e($values['invoice_company_phone']) ?>" maxlength="50" inputmode="tel" autocomplete="tel"
                data-invoice-required<?= hcq_field_attrs($fieldErrors, 'invoice_company_phone') ?>><?= hcq_field_error($fieldErrors, 'invoice_company_phone') ?></label>
            <label class="span-2"><?= e(t('invoice_email')) ?><input name="invoice_email"
                value="<?= e($values['invoice_email']) ?>" type="email" maxlength="190" autocomplete="email"
                data-invoice-required<?= hcq_field_attrs($fieldErrors, 'invoice_email') ?>><?= hcq_field_error($fieldErrors, 'invoice_email') ?></label>
          </div>
        </div>

        <label><?= e(t('order_note')) ?><textarea name="order_note" maxlength="1000" rows="4"
            placeholder="<?= e(t('order_note_placeholder')) ?>"><?= e($values['order_note']) ?></textarea></label>

        <div class="preorder-notice" role="note" aria-label="<?= e(t('preorder_notice')) ?>">
          <strong><?= e(t('preorder_notice')) ?></strong>
          <p><?= e(t('checkout_confirmation_notice')) ?></p>
          <p><?= e(t('checkout_data_usage_notice')) ?></p>
        </div>

        <div class="checkout-submit-wrapper <?= $pendingPaymentOrder ? 'has-pending-order' : '' ?>" id="checkout-submit-wrapper" data-blocked="<?= $pendingPaymentOrder ? '1' : '0' ?>">
          <button class="btn primary block checkout-submit <?= $pendingPaymentOrder ? 'is-blocked' : '' ?>" id="checkout-submit-button" type="submit" aria-disabled="<?= $pendingPaymentOrder ? 'true' : 'false' ?>"><?= e(t('submit_order')) ?></button>
        </div>
      </form>

      <!-- Modal chọn chi nhánh / Bottom Sheet -->
      <div class="store-modal-backdrop" data-store-modal role="dialog" aria-modal="true"
        aria-labelledby="store-modal-title">
        <div class="store-modal-card">
          <div class="store-modal-header">
            <h3 id="store-modal-title"><?= e(sepay_t('gps_select_store_title')) ?></h3>
            <button type="button" class="store-modal-close" data-modal-close aria-label="<?= e(sepay_t('gps_close')) ?>">&times;</button>
          </div>
          <div class="store-modal-search">
            <input type="text" placeholder="<?= e(sepay_t('gps_search_store_placeholder')) ?>" data-store-search>
          </div>
          <div class="store-modal-list" data-store-list>
            <!-- Danh sách chi nhánh cập nhật động theo khoảng cách từ Google Maps -->
          </div>
        </div>
      </div>
    </div><!-- /.location-container -->
  </section>

  <aside class="order-review">
    <?= promotion_summary($promotion) ?>
    <?php if ($groupPayment): ?> <?= hyg_render_payment_overview($groupPayment) ?><?php endif; ?>
      <h2><?= e(t('order_details')) ?></h2>
      <?php foreach ($cart as $r): ?>
        <div class="review-row">
          <div>
            <?php if (!empty($r['group_member_name'])): ?><span
                class="hyg-member-tag"><?= e(gt('ordered_by')) ?>：<?= e($r['group_member_name']) ?></span><?php endif; ?><strong><?= e($r['name']) ?><?php if (!empty($r['cup_size'])): ?>
              · <?= e($r['cup_size']) ?><?php endif; ?> × <?= $r['qty'] ?></strong>
            <small><?= e($r['ice']) ?> /
              <?= e($r['sugar']) ?></small><?php if (!empty($r['toppings'])): ?><small><?= e(t('toppings')) ?>：<?= e(implode('、', array_map(fn($top) => $top['display_name'] ?? $top['name'], $r['toppings']))) ?></small><?php endif; ?>
          </div>
          <b><?= e(currency()) ?><?= money($r['unit_price'] * $r['qty']) ?></b>
        </div>
      <?php endforeach; ?>
      <?php foreach ($gameGifts as $gift): $pick = $gift['item']; ?>
        <div class="review-row">
          <div>
            <strong>🎁 Quà Mini Game · <?= e($pick['name'] . ($pick['cup_size'] !== '' ? ' · ' . $pick['cup_size'] : '')) ?> × <?= (int)$gift['qty'] ?></strong>
            <small><?= e($gift['kind'] === 'tea' ? 'Tặng ' . $gift['qty'] . ' ly' : 'Tặng ' . $gift['qty'] . ' phần topping') ?> · <?= e($gift['code']) ?></small>
          </div>
          <b><?= e(currency()) ?>0</b>
        </div>
      <?php endforeach; ?>
      <div class="review-totals">
        <div><span><?= e(t('subtotal')) ?></span><strong><?= e(currency()) ?><?= money($checkoutSubtotal) ?></strong>
        </div><?php if ($coupon['discount'] > 0): ?>
          <div class="discount-line"><span><?= e(t('coupon_discount')) ?> ·
              <?= e($coupon['code']) ?></span><strong>-<?= e(currency()) ?><?= money($coupon['discount']) ?></strong></div>
        <?php endif; ?><?php if ($gameCash['discount'] > 0): ?>
          <div class="discount-line"><span>Quà Mini Game (<?= count($gameCash['codes']) ?> mã) · <?= e(implode(', ', $gameCash['codes'])) ?></span><strong>-<?= e(currency()) ?><?= money($gameCash['discount']) ?></strong></div>
        <?php endif; ?>
        <div class="shipping-review-line" data-review-shipping-wrap style="display:none;">
          <span><?= e(sepay_t('shipping_fee_label')) ?></span>
          <strong data-review-shipping-text></strong>
        </div>
        <div class="review-total">
          <span><?= e(t('total')) ?></span><strong><?= e(currency()) ?><?= money($checkoutTotal) ?></strong>
        </div>
      </div>
  </aside>
</div>

<!-- App Native Style Centered Loading Overlay -->
<div class="app-loading-overlay" id="checkout-loading-overlay" aria-hidden="true" style="display: none;">
  <div class="app-loading-box">
    <svg class="app-loading-spinner" viewBox="0 0 100 100" width="56" height="56" aria-hidden="true">
      <circle cx="61.1" cy="15.8" r="8.2" fill="#555555" />
      <circle cx="79.1" cy="28.8" r="9.8" fill="#444444" />
      <circle cx="79.1" cy="71.2" r="4.2" fill="#dcdcdc" />
      <circle cx="61.1" cy="84.2" r="5.2" fill="#c4c4c4" />
      <circle cx="35.4" cy="82.9" r="6.0" fill="#a8a8a8" />
      <circle cx="16.6" cy="63.5" r="6.8" fill="#888888" />
      <circle cx="16.8" cy="35.9" r="7.4" fill="#707070" />
      <circle cx="35.9" cy="16.8" r="7.8" fill="#606060" />
    </svg>
  </div>
</div>

<?= client_render_js_i18n() ?>
<?= sepay_render_js_i18n() ?>
<script src="<?= e(base_url('assets/js/google-location.js?v=' . filemtime(__DIR__ . '/assets/js/google-location.js'))) ?>" defer></script>

<style>
  /* Toast Alert 3s nổi thông báo lỗi và tự động cuộn lên xem (Tối ưu cho cả Mobile & Desktop) */
  .pending-toast-alert {
    position: fixed;
    top: max(16px, env(safe-area-inset-top, 16px));
    left: 50%;
    transform: translateX(-50%) translateY(-35px);
    background: #ffffff;
    border-left: 5px solid #dc2626;
    border-radius: 12px;
    box-shadow: 0 14px 35px rgba(0, 0, 0, 0.28), 0 4px 12px rgba(220, 38, 38, 0.2);
    padding: 14px 16px 12px 14px;
    z-index: 2147483647;
    width: calc(100% - 24px);
    max-width: 440px;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.35s cubic-bezier(0.16, 1, 0.3, 1), transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    -webkit-tap-highlight-color: transparent;
    box-sizing: border-box;
  }

  .pending-toast-alert.is-visible {
    opacity: 1;
    pointer-events: auto;
    transform: translateX(-50%) translateY(0);
  }

  .pending-toast-content {
    display: flex;
    align-items: flex-start;
    gap: 12px;
  }

  .pending-toast-icon {
    font-size: 24px;
    line-height: 1.1;
    flex-shrink: 0;
  }

  .pending-toast-text {
    flex: 1;
    min-width: 0;
  }

  .pending-toast-text strong {
    display: block;
    font-size: 15px;
    color: #991b1b;
    margin-bottom: 3px;
    line-height: 1.3;
  }

  .pending-toast-text span {
    display: block;
    font-size: 13px;
    color: #4b5563;
    line-height: 1.45;
  }

  .pending-toast-close {
    background: none;
    border: none;
    font-size: 22px;
    color: #9ca3af;
    cursor: pointer;
    padding: 0 6px;
    line-height: 1;
    touch-action: manipulation;
  }

  .pending-toast-close:hover,
  .pending-toast-close:active {
    color: #111827;
  }

  .pending-toast-progress-bar {
    margin-top: 10px;
    height: 3.5px;
    background: #fee2e2;
    border-radius: 2px;
    overflow: hidden;
  }

  .pending-toast-progress-fill {
    height: 100%;
    background: #dc2626;
    width: 100%;
    transition: width 3s linear;
  }

  .pending-toast-progress-fill.animating {
    width: 0% !important;
  }

  /* Hiệu ứng nhấp nháy làm nổi bật khối đơn pending khi cuộn lên xem */
  @keyframes pendingAlertPulse {
    0% {
      box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.85);
    }

    50% {
      box-shadow: 0 0 0 14px rgba(220, 38, 38, 0);
    }

    100% {
      box-shadow: 0 0 0 0 rgba(220, 38, 38, 0);
    }
  }

  .pending-pulse-target {
    animation: pendingAlertPulse 1.2s ease-in-out 2;
    border: 2px solid #dc2626 !important;
  }

  .checkout-submit.is-blocked {
    opacity: 0.55 !important;
    filter: grayscale(0.3);
    cursor: not-allowed !important;
    touch-action: manipulation;
  }

  .checkout-submit-wrapper.has-pending-order {
    cursor: not-allowed;
    touch-action: manipulation;
  }
</style>

<script>
  (function() {
    window.__HAS_PENDING_ORDER = <?= $pendingPaymentOrder ? 'true' : 'false' ?>;
    const checkPendingEndpoint = <?= json_encode(base_url('api/check_pending_order.php')) ?>;

    const toast = document.getElementById('pending-toast-alert');
    const progressFill = document.getElementById('pending-toast-progress');
    const alertTarget = document.getElementById('pending-order-alert');
    const alertTitle = document.getElementById('pending-alert-title');
    const alertDesc = document.getElementById('pending-alert-desc');
    const alertPayBtn = document.getElementById('pending-alert-pay-btn');
    const toastTitle = document.getElementById('pending-toast-title');
    const toastDesc = document.getElementById('pending-toast-desc');
    const closeBtn = document.getElementById('pending-toast-close');
    const submitWrap = document.getElementById('checkout-submit-wrapper');
    const submitBtn = document.getElementById('checkout-submit-button');
    const checkoutForm = document.querySelector('form[data-checkout-form]');
    const phoneInput = document.querySelector('input[name="phone"]');

    let toastTimer = null;
    let checkTimer = null;

    function showToastAndScroll() {
      if (!toast || !alertTarget) return;

      // 1. Đảm bảo container alert đang hiển thị
      alertTarget.style.display = '';

      // 2. Ẩn bàn phím ảo trên mobile để không bị che khuất tầm nhìn
      if (document.activeElement && typeof document.activeElement.blur === 'function') {
        document.activeElement.blur();
      }

      // 3. Cuộn mượt màn hình kéo lên đúng vị trí banner cảnh báo (hoạt động hoàn hảo trên cả Mobile & Desktop)
      try {
        const rect = alertTarget.getBoundingClientRect();
        const targetY = window.pageYOffset + rect.top - 80;
        window.scrollTo({
          top: Math.max(0, targetY),
          behavior: 'smooth'
        });
      } catch (e) {
        alertTarget.scrollIntoView({
          behavior: 'smooth',
          block: 'center'
        });
      }

      // Hiệu ứng pulse highlight khối cảnh báo
      alertTarget.classList.add('pending-pulse-target');
      setTimeout(function() {
        alertTarget.classList.remove('pending-pulse-target');
      }, 3000);

      // 4. Bật Toast Alert nổi bật
      clearTimeout(toastTimer);
      toast.style.display = '';
      if (progressFill) {
        progressFill.classList.remove('animating');
        progressFill.style.width = '100%';
      }

      toast.classList.add('is-visible');

      // Chạy thanh tiến trình đếm ngược 3 giây
      requestAnimationFrame(function() {
        requestAnimationFrame(function() {
          if (progressFill) {
            progressFill.classList.add('animating');
          }
        });
      });

      // 5. Tự động ẩn sau đúng 3 giây (3000ms)
      toastTimer = setTimeout(function() {
        hideToast();
      }, 3000);
    }

    function hideToast() {
      if (toast) {
        toast.classList.remove('is-visible');
      }
    }

    if (closeBtn) {
      ['click', 'touchend'].forEach(function(evt) {
        closeBtn.addEventListener(evt, function(e) {
          e.preventDefault();
          hideToast();
        });
      });
    }

    // Cập nhật trạng thái giao diện khi phát hiện có hoặc không có đơn pending
    function updatePendingState(data) {
      if (data && data.has_pending) {
        window.__HAS_PENDING_ORDER = true;

        // Cập nhật text trong Banner cảnh báo
        if (alertTitle) alertTitle.textContent = data.title || 'Bạn đang có đơn hàng chờ thanh toán!';
        if (alertDesc) alertDesc.textContent = data.desc || '';
        if (alertPayBtn) {
          alertPayBtn.href = data.payment_url || '#';
          alertPayBtn.textContent = data.pay_now_text || 'Đi tới trang thanh toán';
        }
        if (alertTarget) alertTarget.style.display = '';

        // Cập nhật text trong Toast 3s
        if (toastTitle) toastTitle.textContent = data.toast_title || 'Đang có đơn hàng chờ thanh toán!';
        if (toastDesc) toastDesc.textContent = data.toast_desc || '';

        // Khóa nút Đặt hàng
        if (submitBtn) {
          submitBtn.classList.add('is-blocked');
          submitBtn.setAttribute('aria-disabled', 'true');
        }
        if (submitWrap) {
          submitWrap.classList.add('has-pending-order');
          submitWrap.setAttribute('data-blocked', '1');
        }

        // Bật Toast Alert 3s và cuộn lên xem ngay lập tức mà KHÔNG CẦN F5!
        showToastAndScroll();
      } else {
        window.__HAS_PENDING_ORDER = false;
        if (alertTarget) alertTarget.style.display = 'none';
        if (submitBtn) {
          submitBtn.classList.remove('is-blocked');
          submitBtn.removeAttribute('aria-disabled');
        }
        if (submitWrap) {
          submitWrap.classList.remove('has-pending-order');
          submitWrap.setAttribute('data-blocked', '0');
        }
        hideToast();
      }
    }

    // Kiểm tra Realtime số điện thoại khi người dùng nhập/thay đổi mà KHÔNG CẦN F5
    function checkPhoneRealtime(phoneVal) {
      clearTimeout(checkTimer);
      checkTimer = setTimeout(function() {
        const clean = (phoneVal || '').replace(/\D+/g, '');
        if (clean.length < 8) return;

        fetch(checkPendingEndpoint + '?phone=' + encodeURIComponent(phoneVal), {
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          })
          .then(function(res) {
            return res.json();
          })
          .then(function(res) {
            if (res && res.ok) {
              updatePendingState(res);
            }
          })
          .catch(function(err) {
            console.warn('[checkout] Lỗi kiểm tra pending order:', err);
          });
      }, 350);
    }

    if (phoneInput) {
      ['input', 'change', 'blur', 'paste'].forEach(function(evt) {
        phoneInput.addEventListener(evt, function() {
          checkPhoneRealtime(phoneInput.value);
        });
      });
    }

    // Xử lý sự kiện chặn bấm Đặt hàng (Hỗ trợ 100% Mobile Touch & Desktop Click)
    function handleBlockedAttempt(e) {
      if (window.__HAS_PENDING_ORDER) {
        if (e) {
          e.preventDefault();
          e.stopPropagation();
        }
        showToastAndScroll();
        return false;
      }
    }

    if (submitBtn) {
      ['click', 'touchstart', 'touchend', 'pointerdown'].forEach(function(evt) {
        submitBtn.addEventListener(evt, function(e) {
          if (window.__HAS_PENDING_ORDER) {
            handleBlockedAttempt(e);
          }
        }, {
          passive: false
        });
      });
    }

    if (submitWrap) {
      ['click', 'touchstart', 'touchend'].forEach(function(evt) {
        submitWrap.addEventListener(evt, function(e) {
          if (window.__HAS_PENDING_ORDER) {
            handleBlockedAttempt(e);
          }
        }, {
          passive: false
        });
      });
    }

    if (checkoutForm) {
      checkoutForm.addEventListener('submit', function(e) {
        if (window.__HAS_PENDING_ORDER) {
          handleBlockedAttempt(e);
        }
      });
    }

    // Nếu ngay từ lúc tải trang đã có đơn pending, tự động cuộn lên xem sau 300ms
    if (window.__HAS_PENDING_ORDER) {
      window.addEventListener('load', function() {
        setTimeout(showToastAndScroll, 300);
      });
    }
  })();
</script>
<!-- Dialog Mua 10ly nước được chơi game: chỉ khi khách chọn "Mua 10 nhận lượt rút thưởng" và đủ 10 ly -->
<?php if ($gameSession) require_once __DIR__ . '/minigame/minigame_dialog.php'; ?>
<?php if ($gameSession): ?>
  <!-- Submitting with unplayed chances asks first: they are forfeited, not kept for later. -->
  <dialog id="gameForfeitModal" class="kyuc-confirm">
    <h3><?= str_replace('%N%', '<b data-forfeit-count>' . hcgs_remaining($gameSession) . '</b>', e(hcgs_text('forfeit_title', ['n' => '%N%']))) ?></h3>
    <p><?= e(hcgs_text('forfeit_body')) ?></p>
    <div class="kyuc-confirm-actions">
      <button type="button" class="btn primary" data-forfeit-play>🎮 <?= e(hcgs_text('play_now')) ?></button>
      <button type="button" class="btn" data-forfeit-submit><?= e(hcgs_text('forfeit_submit')) ?></button>
    </div>
  </dialog>
  <style>
    .kyuc-confirm {
      border: none;
      border-radius: 16px;
      padding: 24px;
      max-width: 420px;
      width: calc(100vw - 32px);
      box-shadow: 0 10px 30px rgba(0, 0, 0, .3)
    }

    .kyuc-confirm::backdrop {
      background: rgba(0, 0, 0, .55)
    }

    .kyuc-confirm h3 {
      margin: 0 0 8px
    }

    .kyuc-confirm-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      justify-content: flex-end;
      margin-top: 16px
    }
  </style>
  <script>
    (function() {
      const form = document.querySelector('[data-checkout-form]');
      const ask = document.getElementById('gameForfeitModal');
      if (!form || !ask) return;
      let confirmed = false;
      // Registered before app.js, so the order is not marked as submitting while the customer decides.
      form.addEventListener('submit', function(event) {
        if (window.__HAS_PENDING_ORDER) return;
        const left = Number(form.dataset.gameRemaining) || 0;
        if (confirmed || left < 1) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        ask.querySelector('[data-forfeit-count]').textContent = String(left);
        ask.showModal();
      });
      ask.querySelector('[data-forfeit-play]').addEventListener('click', function() {
        ask.close();
        openMinigameModal();
      });
      ask.querySelector('[data-forfeit-submit]').addEventListener('click', function() {
        confirmed = true;
        ask.close();
        form.requestSubmit();
      });
    })();
  </script>
<?php endif; ?>

<?php if ($groupCheckout): ?>
  <script>
    // Browser Back must not return to a group checkout; send the customer to the home page instead.
    (function() {
      const home = <?= json_encode(base_url(''), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;
      // Arrived here with Back/Forward (fresh load or restored from the back/forward cache).
      const nav = performance.getEntriesByType('navigation')[0];
      window.addEventListener('pageshow', event => {
        if (event.persisted || (nav && nav.type === 'back_forward')) location.replace(home);
      });
      // Lets the group room know it was reached by leaving this page (see group/index.php).
      window.addEventListener('pagehide', () => {
        try { sessionStorage.setItem('hygLeftCheckout', String(Date.now())); } catch (_) {}
      });
      // When the order is sent, point this history entry at the home page (replaceState adds no entry).
      window.addEventListener('submit', event => {
        if (event.defaultPrevented || !event.target.matches('[data-checkout-form]')) return;
        history.replaceState(null, '', home);
      });
    })();
  </script>
<?php endif; ?>

<?php front_footer(); ?>