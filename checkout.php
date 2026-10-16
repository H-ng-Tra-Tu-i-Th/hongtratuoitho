<?php
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/group_payment_views.php';
require_once __DIR__ . '/lib/promotion_views.php';
require_once __DIR__ . '/lib/customer_checkout.php';
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
  redirect(base_url('cart.php'));
}
$coupon = $groupCheckout ? $groupCheckout['coupon'] : cart_coupon_summary($cart);
if (!$groupCheckout && $promotion['mode'] === 'reward' && (float) $coupon['discount'] <= 0) {
  $_SESSION['coupon_error'] = pt('reward_no_discount');
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
front_header(t('order_info'));
?>
<?php if ($groupCheckout): ?>
  <section class="hyg-checkout-note">
    <strong><?= e(gt('order_group')) ?>：<?= e($groupCheckout['room']['title']) ?></strong>
    <p>
      <?= e($hostCollect ? htp('collection_before_order') : gt($groupPayment['mode'] === 'split' ? 'split_owner_checkout' : 'owner_checkout')) ?>
    </p>
    <p><?= e(gt('locked_snapshot')) ?></p><a class="btn"
      href="<?= e(hyg_room_url($groupCheckout['room']['public_id'])) ?>"><?= e(gt('back_room')) ?></a>
  </section><?php endif; ?>
<div class="checkout-wrap">
  <section>
    <span class="eyebrow"><?= e(t('checkout')) ?></span>
    <h1><?= e(t('order_info')) ?></h1>
    <?php if ($checkoutUnknown): ?>
      <div class="alert danger checkout-error-summary" role="alert">
        <p><?= e(htp_ux('unknown')) ?></p><a class="btn"
          href="<?= e(base_url('payment.php?order=' . rawurlencode($checkoutRequest['order_no']))) ?>"><?= e(htp_ux('check_order')) ?></a>
      </div><?php elseif ($checkoutError || $fieldErrors): ?>
      <div class="alert danger checkout-error-summary" role="alert" tabindex="-1" data-checkout-errors>
        <?= e($checkoutError ?: htp_ux('draft_error')) ?></div><?php endif; ?>
    <div class="member-checkout-note">
      <p><?= e(mt($checkoutMember ? 'checkout_member_notice' : 'checkout_member_guest')) ?></p><a
        href="<?= e(base_url('member/index.php')) ?>"><?= e(mt('member_entry')) ?></a>
    </div>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/location-selector.css?v=1.2.0')) ?>">
    <div class="location-container" data-location-container
      data-goong-key="<?= e(defined('GOONG_API_KEY') ? GOONG_API_KEY : '') ?>"
      data-stores-endpoint="<?= e(base_url('api/stores.php')) ?>">
      <form method="post" action="<?= e(base_url('actions/submit_order.php')) ?>" class="checkout-form"
        data-checkout-form data-submitting-label="<?= e(htp_ux('submitting')) ?>">
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
            <label for="delivery-address-field" class="address-input-label">
              <span><?= e(t('delivery_address')) ?></span>
            </label>
            <button type="button" class="btn-gps-trigger" data-btn-gps
              title="Tự động lấy vị trí hiện tại của bạn qua GPS">
              <span class="gps-radar-dot" aria-hidden="true"></span>
              <svg class="gps-icon-crosshair" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3c-.46-4.17-3.77-7.48-7.94-7.94V1h-2v2.06C6.83 3.52 3.52 6.83 3.06 11H1v2h2.06c.46 4.17 3.77 7.48 7.94 7.94V23h2v-2.06c4.17-.46 7.48-3.77 7.94-7.94H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z" />
              </svg>
              <span class="btn-gps-text">Dùng vị trí hiện tại</span>
            </button>
          </div>
          <textarea id="delivery-address-field" name="address" required maxlength="255" rows="3"
            autocomplete="street-address" placeholder="Nhập địa chỉ nhận hàng (bắt buộc chọn từ gợi ý bên dưới)..."
            <?= hcq_field_attrs($fieldErrors, 'address') ?>><?= e($values['address']) ?></textarea>
          <div class="autocomplete-dropdown" data-autocomplete-dropdown></div>
          <div class="address-select-warning" data-address-warning role="alert"></div>
          <input type="hidden" name="address_verified" value="0" data-address-verified-input>
          <?= hcq_field_error($fieldErrors, 'address') ?>
        </div>

        <!-- Banner chi nhánh phục vụ tối ưu (nằm ngay dưới ô địa chỉ giao hàng) -->
        <div class="location-store-banner" data-location-banner>
          <div class="location-banner-main">
            <div class="location-banner-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24">
                <path
                  d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
              </svg>
            </div>
            <div class="location-banner-content">
              <span class="location-banner-eyebrow">Chi nhánh phục vụ tối ưu</span>
              <div class="location-banner-title" data-banner-store-name>Đang tự động chọn chi nhánh gần nhất...</div>
              <div class="location-banner-distance" data-banner-distance-wrap style="display:none;">
                <span class="location-banner-badge" data-banner-distance-text></span>
                <span class="location-banner-duration" data-banner-duration-text></span>
              </div>
              <div class="location-banner-reason" data-banner-reason-wrap style="display:none;">
                <span data-banner-reason-text></span>
              </div>
            </div>
          </div>
          <button type="button" class="location-banner-btn-change" data-btn-change-store>Đổi chi nhánh</button>
        </div>

        <!-- close-test-Hai Thêm css ẩn thông tin thanh toán (Thanh toán chung · Chuyển khoản) -->
        <fieldset style="display: none;">
          <legend><?= e(t('payment_method')) ?></legend>
          <?php if ($groupPayment): ?>
            <input type="hidden" name="payment_method" value="<?= e($groupPayCode) ?>">
            <p>
              <?= e($hostCollect ? htp('merchant_host') : gt($groupPayment['mode'] === 'split' ? 'pay_split' : 'pay_together')) ?>  <?php if ($hostCollect || $groupPayment['mode'] === 'together'): ?>
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

        <button class="btn primary block checkout-submit" type="submit" <?= $checkoutUnknown ? 'disabled' : '' ?>><?= e(t('submit_order')) ?></button>
      </form>

      <!-- Modal chọn chi nhánh / Bottom Sheet -->
      <div class="store-modal-backdrop" data-store-modal role="dialog" aria-modal="true"
        aria-labelledby="store-modal-title">
        <div class="store-modal-card">
          <div class="store-modal-header">
            <h3 id="store-modal-title">Chọn chi nhánh phục vụ</h3>
            <button type="button" class="store-modal-close" data-modal-close aria-label="Đóng">&times;</button>
          </div>
          <div class="store-modal-search">
            <input type="text" placeholder="Tìm kiếm tên chi nhánh hoặc địa chỉ..." data-store-search>
          </div>
          <div class="store-modal-list" data-store-list>
            <!-- Danh sách chi nhánh cập nhật động theo khoảng cách từ Goong Maps -->
          </div>
        </div>
      </div>
    </div><!-- /.location-container -->
  </section>

  <aside class="order-review">
    <?= promotion_summary($promotion) ?>
    <?php if ($groupPayment): ?>  <?= hyg_render_payment_overview($groupPayment) ?><?php endif; ?>
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
    <div class="review-totals">
      <div><span><?= e(t('subtotal')) ?></span><strong><?= e(currency()) ?><?= money($checkoutSubtotal) ?></strong>
      </div><?php if ($coupon['discount'] > 0): ?>
        <div class="discount-line"><span><?= e(t('coupon_discount')) ?> ·
            <?= e($coupon['code']) ?></span><strong>-<?= e(currency()) ?><?= money($coupon['discount']) ?></strong></div>
      <?php endif; ?>
      <div class="review-total">
        <span><?= e(t('total')) ?></span><strong><?= e(currency()) ?><?= money($checkoutTotal) ?></strong></div>
    </div>
  </aside>
</div>
<script src="<?= e(base_url('assets/js/goong-location.js?v=1.0.0')) ?>" defer></script>
<?php front_footer(); ?>