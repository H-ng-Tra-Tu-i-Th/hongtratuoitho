<?php
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/promotion_views.php';
hcgs_guard_cart();

if (!promotion_cart_choice()['valid']) {
  clear_coupon();
  $_SESSION['coupon_error'] = pt('selection_changed');
}
front_header(t('cart'));
$cart = $_SESSION['cart'] ?? [];
$coupon = cart_coupon_summary();
$couponMsg = $_SESSION['coupon_message'] ?? '';
$couponErr = $_SESSION['coupon_error'] ?? '';
$gameFlash = $_SESSION['hcgs_flash'] ?? '';
unset($_SESSION['coupon_message'], $_SESSION['coupon_error'], $_SESSION['hcgs_flash']);
// Groups this device joined, same list as group/ "Nhóm của tôi".
$myRooms = [];
try {
  $device = hyg_device();
  if ($device !== '') $myRooms = hyg_all('SELECT r.public_id,r.title,r.status,r.expires_at FROM group_rooms r JOIN group_members m ON m.room_id=r.id WHERE m.device_hash=? ORDER BY r.id DESC LIMIT 20', [$device]);
} catch (Throwable $e) {
  $myRooms = [];
}
?>
<?php if ($gameFlash): ?><div class="alert danger" role="alert"><?= e($gameFlash) ?></div><?php endif; ?>
<div class="section-head">
  <div><span class="eyebrow"><?= e(t('your_order')) ?></span>
    <h1><?= e(t('cart')) ?></h1>
  </div>
</div>
<?php if (!hyg_in_active_group()): ?>
<section class="hyg-entry">
  <div><strong><?= e(gt('share_cart')) ?></strong>
    <p><?= e(gt('copy_hint')) ?></p>
  </div><a class="btn primary" href="<?= e(hym_login_gate_url('group')) ?>"><?= e(gt('create')) ?></a>
</section>
<?php endif; ?>
<?php if ($myRooms): ?>
  <section class="hyg-card">
    <h2><?= e(gt('my_groups')) ?></h2>
    <div class="hyg-room-list">
      <?php foreach ($myRooms as $room):
        $s = $room['status'];
        if (in_array($s, ['open', 'locked'], true) && strtotime($room['expires_at'] . ' UTC') <= time()) $s = 'expired'; ?>

        <!-- Chỉ hiện thị những đơn hàng đang chọn món và chờ gửi đơn -->
        <?php if (in_array($s, ['open', 'locked'], true)): ?>
          <?php
          // A locked group that is ready to send (this device is the host, every payment confirmed) opens the
          // "send order" form directly. checkout.php shows an error page for a group that is not ready, so any
          // other group keeps the room page, which explains what is still missing.
          $roomUrl = hyg_room_url($room['public_id']);
          $readyToSend = false;
          if ($s === 'locked') {
            try {
              hyg_checkout((string) $room['public_id']);
              $roomUrl = base_url('checkout.php?group=' . rawurlencode((string) $room['public_id']));
              $readyToSend = true;
            } catch (Throwable $notReady) {
              // Not the host, payments unconfirmed, empty room… (or any lookup error): keep the room page.
            }
          }
          ?>
          <a class="hyg-room-link<?= $readyToSend ? ' is-ready' : '' ?>" href="<?= e($roomUrl) ?>">
            <strong><?= e($room['title']) ?></strong>
            <span class="hyg-room-meta">
              <span class="hyg-status is-<?= e($s) ?>"><?= e(gt($s)) ?></span>
              <span class="hyg-room-cta"><?= e(gt($readyToSend ? 'room_cta_send' : 'room_cta_open')) ?> ›</span>
            </span>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
<?php if (!$cart): ?>
  <div class="empty">
    <h2><?= e(t('cart_empty')) ?></h2>
    <p><?= e(t('cart_empty_desc')) ?></p><a class="btn primary" href="<?= e(base_url('index.php')) ?>"><?= e(t('return_menu')) ?></a>
  </div>
<?php else: ?>
  <form method="post" action="<?= e(base_url('actions/update_cart.php')) ?>">
    <?= csrf_field() ?>
    <div class="cart-list">
      <?php foreach ($cart as $key => $r): ?>
        <article class="cart-item">
          <div class="cart-main">
            <h3><?= e($r['name']) ?></h3>
            <?php if (!empty($r['cup_size'])): ?><p class="cart-size"><?= e(t('cup_size')) ?>：<strong><?= e($r['cup_size']) ?></strong></p><?php endif; ?>
            <?php if (trim((string)($r['ice'] ?? '')) !== '' || trim((string)($r['sugar'] ?? '')) !== ''): ?><p><?= e($r['ice']) ?><?= ($r['ice'] !== '' && $r['sugar'] !== '') ? ' ・ ' : '' ?><?= e($r['sugar']) ?></p><?php endif; ?>
            <?php if (!empty($r['toppings'])): ?><p><?= e(t('toppings')) ?>：<?= e(implode('、', array_map(fn($top) => $top['display_name'] ?? $top['name'] ?? '', $r['toppings']))) ?></p><?php endif; ?>
          </div>
          <div class="cart-side">
            <strong><?= e(currency()) ?><?= money($r['unit_price'] * $r['qty']) ?></strong>
            <label class="cart-qty-field"><span><?= e(t('quantity')) ?></span><input aria-label="<?= e(t('quantity')) ?>" type="number" name="qty[<?= e($key) ?>]" min="0" max="<?= order_qty_max() ?>" value="<?= $r['qty'] ?>"></label>
            <button class="cart-remove-btn" type="submit" name="remove_key" value="<?= e($key) ?>" aria-label="<?= e(t('remove')) ?> <?= e($r['name']) ?>"><svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 7h16M9 7V4h6v3m-8 0 1 13h8l1-13M10 11v5m4-5v5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
              </svg><span><?= e(t('remove')) ?></span></button>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <div class="cart-actions"><button class="btn" type="submit"><?= e(t('update_cart')) ?></button></div>
  </form>

  <?php if ($couponMsg): ?><div class="alert success"><?= e($couponMsg) ?></div><?php endif; ?>
  <?php if ($couponErr): ?><div class="alert danger"><?= e($couponErr) ?></div><?php endif; ?>
  <?= promotion_selector(promotion_cart_choice($cart)) ?>
  <?php if (promotion_cart_mode() === 'reward' && (float)$coupon['discount'] <= 0): ?><p class="promotion-help" role="status"><?= e(pt('reward_no_discount')) ?></p><?php endif; ?>

  <div class="cart-summary detailed">
    <div><span><?= e(t('subtotal')) ?></span><strong><?= e(currency()) ?><?= money(cart_subtotal()) ?></strong></div>
    <?php if (cart_discount() > 0): ?><div class="discount-line"><span><?= e(t('coupon_discount')) ?><?php if ($coupon['code']): ?> · <?= e($coupon['code']) ?><?php endif; ?></span><strong>-<?= e(currency()) ?><?= money(cart_discount()) ?></strong></div><?php endif; ?>
    <div class="total-line"><span><?= e(t('total')) ?></span><strong><?= e(currency()) ?><?= money(cart_total()) ?></strong></div>
  </div>
  <div class="cart-actions checkout-action"><button class="btn primary" type="submit" form="cart-promotion-selector" name="continue_to_checkout" value="1"><?= e(t('checkout_info')) ?></button></div>
<?php endif; ?>

<!-- Footer website -->
<?php front_footer(); ?>