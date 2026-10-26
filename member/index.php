<?php
// The Zalo app's registered callback is this page; hand the OAuth response to the Zalo handler.
if (isset($_GET['state']) && (isset($_GET['code']) || isset($_GET['error']))) {
  header('Location: zalo.php?' . ($_SERVER['QUERY_STRING'] ?? ''));
  exit;
}
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/promotion_views.php';
require_once __DIR__ . '/../lib/order_detail_view.php';
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
$returnOrder = $_GET['return_order'] ?? '';
$returnOrder = is_string($returnOrder) && preg_match('/^[A-Za-z0-9_-]{1,80}$/D', $returnOrder) ? $returnOrder : '';
$member = null;
$platformOrders = [];
$loadFailed = false;
try {
  hym_schema();
  $member = hym_current_member();
  if ($member) {
    $memberId = (int)$member['id'];
    $platformOrders = hym_platform_orders($memberId);
  }
} catch (Throwable $error) {
  $loadFailed = true;
}
// A guest sent here from a members-only entry point (e.g. "share cart") returns there once signed in.
$returnTarget = $_GET['return'] ?? '';
if (is_string($returnTarget) && $returnTarget !== '') hym_remember_return($returnTarget);
if ($member && ($returnPath = hym_take_return()) !== null) {
  unset($_SESSION['member_flash']);
  redirect(base_url($returnPath));
}
// Group referral points (lib/member_referral.php): balance and how many members sit directly under this one.
$groupPoints = 0;
$groupChildren = 0;
if ($member) try {
  $st = db()->prepare('SELECT points_balance,(SELECT COUNT(*) FROM members c WHERE c.parent_id=m.id) children FROM members m WHERE m.id=?');
  $st->execute([(int)$member['id']]);
  $row = $st->fetch() ?: [];
  $groupPoints = (int)($row['points_balance'] ?? 0);
  $groupChildren = (int)($row['children'] ?? 0);
} catch (Throwable $error) {
}
$rewardCoupons = [];
$rewardBalance = 0;
$rewardsFailed = false;
if ($member) try {
  $rewardCoupons = available_game_coupons();
  $rewardBalance = game_wallet_balance();
} catch (Throwable $error) {
  $rewardsFailed = true;
}
$flash = $_SESSION['member_flash'] ?? null;
unset($_SESSION['member_flash']);
// Legacy sync submissions must not reveal upstream status in the customer interface.
if (is_array($flash) && str_starts_with((string)($flash['key'] ?? ''), 'member_sync_')) $flash = null;
$formatDate = static function ($value, bool $dateOnly = false, bool $isUtc = true): string {
  $value = trim((string)$value);
  if ($value === '' || str_starts_with($value, '0000-00-00')) return mt('member_not_provided');
  try {
    $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
    if (!$dateOnly && $isUtc) $date = $date->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
    return $date->format($dateOnly ? 'd/m/Y' : 'd/m/Y H:i');
  } catch (Throwable $error) {
    return mt('member_not_provided');
  }
};
$GLOBALS['hym_member_page'] = true;
front_header(mt('member_title'));
?>
<div class="hym-page<?= $member ? '' : ' hym-page-auth' ?>">
  <a class="back" href="<?= e(base_url('index.php')) ?>">← <?= e(mt('member_back_menu')) ?></a>
  <header class="hym-page-head">
    <div><span class="hym-eyebrow"><?= e(mt('member_entry')) ?></span>
      <h1><?= e(mt('member_title')) ?></h1>
      <?php if ($member): ?>
        <div class="hym-points" aria-label="<?= e(mt('member_group_points_title')) ?>">
          <span class="hym-points-icon" aria-hidden="true">★</span>
          <div class="hym-points-text">
            <strong><?= e(mt('member_group_points_title')) ?></strong>
            <small><?= e(mt('member_group_points_hint', ['points' => hym_referral_points()])) ?></small>
            <span class="hym-points-children"><?= e(mt('member_group_children', ['count' => $groupChildren])) ?></span>
          </div>
          <div class="hym-points-value"><b><?= number_format($groupPoints, 0, ',', '.') ?></b><span><?= e(mt('member_group_points_unit')) ?></span></div>
        </div>
      <?php else: ?><p><?= e(mt('member_intro')) ?></p><?php endif; ?>
    </div>
  </header>
  <?php if (is_array($flash) && !empty($flash['key'])): $flashType = in_array($flash['type'] ?? '', ['success', 'info', 'danger'], true) ? $flash['type'] : 'danger';
    $flashText = mt((string)$flash['key']);
    if ($flashText === (string)$flash['key']) $flashText = mt('member_save_failed'); ?>
    <div class="alert <?= e($flashType) ?>" role="status"><?= e($flashText) ?></div>
  <?php endif; ?>
  <?php if ($loadFailed): ?><div class="alert danger" role="alert"><?= e(mt('member_load_failed')) ?></div><?php endif; ?>
  <?php if (!$member): ?>
    <div class="hym-auth-single">
      <section class="hym-card" id="member-login">
        <h2><?= e(mt('member_login_title')) ?></h2>
        <form class="hym-form" method="post" action="<?= e(base_url('member/action.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="login"><?php if ($returnOrder !== ''): ?><input type="hidden" name="return_order" value="<?= e($returnOrder) ?>"><?php endif; ?>
          <label for="member-login-phone"><?= e(mt('member_phone')) ?><input id="member-login-phone" type="tel" inputmode="tel" name="phone" required maxlength="32" autocomplete="username"></label>
          <label for="member-login-password"><?= e(mt('member_password')) ?><input id="member-login-password" type="password" name="password" required maxlength="72" autocomplete="current-password"></label>
          <button class="btn primary block" type="submit"><?= e(mt('member_login')) ?></button>
        </form>

        <!-- Đăng nhập xác thực qua zalo -->
        <?php if (hym_zalo_enabled()): ?>
          <div class="hym-auth-divider"><span><?= e(mt('member_or')) ?></span></div>
          <a class="btn block btn-zalo-login" href="<?= e(base_url('member/zalo.php' . ($returnOrder !== '' ? '?return_order=' . rawurlencode($returnOrder) : ''))) ?>">
            <svg viewBox="0 0 48 48" aria-hidden="true">
              <rect width="48" height="48" rx="12" fill="#fff" /><text x="24" y="30" text-anchor="middle" font-family="Arial,sans-serif" font-size="15" font-weight="700" fill="#0068FF">Zalo</text>
            </svg>
            <span><?= e(mt('member_login_zalo')) ?></span>
          </a>
        <?php endif; ?>

        <a class="btn block hym-register-link" href="<?= e(base_url('member/register.php')) ?>"><?= e(mt('member_register')) ?></a>
      </section>
    </div>
  <?php else: ?>
    <section class="hym-card" id="member-rewards">
      <h2><?= e(mt('member_rewards_title')) ?></h2>
      <p class="hym-source"><?= e(mt('member_rewards_source')) ?></p>
      <p class="hym-note"><?= e(mt('member_rewards_hint')) ?></p>
      <div class="hym-reward-toolbar"><strong><?= e(mt('member_draw_balance', ['chances' => (int)$rewardBalance])) ?></strong><?php if (minigame_enabled() && $rewardBalance > 0): ?><a class="btn" href="<?= e(base_url('minigame/')) ?>"><?= e(mt('member_go_draw')) ?></a><?php endif; ?></div>
      <?php if ($rewardsFailed): ?><p class="hym-note" role="status"><?= e(mt('member_rewards_load_failed')) ?></p><?php elseif (!$rewardCoupons): ?><p class="hym-empty"><?= e(mt('member_rewards_empty')) ?></p><?php else: ?>

        <!-- close-test-Hai | Danh sách thẻ khuyến mãi -->
        <!-- <div class="hym-reward-list">
          <?php foreach ($rewardCoupons as $reward): ?><article class="hym-reward">
              <div><span class="hym-label"><?= e(pt('reward_code')) ?></span><code><?= e($reward['code']) ?></code><strong><?= e($reward['coupon_type'] === 'game_reward' ? hgr_label($reward) : mt('member_reward_amount', ['amount' => currency() . money($reward['amount'])])) ?></strong>
                <p class="hym-note"><?= e($reward['coupon_type'] === 'game_reward' ? hgr_label($reward, 'terms') . ' · ' . hgr_text('expires') . ' ' . hgr_expiry((string)($reward['expires_at'] ?? '')) : mt('member_reward_rule')) ?></p>
              </div>
              <form method="post" action="<?= e(base_url('actions/apply_coupon.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="apply"><input type="hidden" name="promotion_mode" value="reward"><input type="hidden" name="coupon_code" value="<?= e($reward['code']) ?>"><button class="btn primary" type="submit"><?= e(mt('member_reward_use')) ?></button></form>
            </article><?php endforeach; ?>
        </div> -->
      <?php endif; ?>
    </section>
    <section class="hym-card" id="member-local-profile">
      <h2><?= e(mt('member_local_profile')) ?></h2>
      <p class="hym-source"><?= e(mt('member_local_source')) ?></p>
      <p class="hym-note"><?= e(mt('member_local_profile_hint')) ?></p>
      <dl class="hym-facts">
        <div>
          <dt><?= e(mt('member_account_phone')) ?></dt>
          <dd><?= e($member['phone']) ?></dd>
        </div>
      </dl>
      <link rel="stylesheet" href="<?= e(base_url('assets/css/location-selector.css?v=1.2.0')) ?>">
      <form class="hym-form" method="post" action="<?= e(base_url('member/action.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="save_profile">
        <label for="member-profile-name"><?= e(mt('member_display_name')) ?><input id="member-profile-name" name="display_name" maxlength="100" autocomplete="name" value="<?= e($member['display_name'] ?? '') ?>"></label>

        <div class="address-input-wrap">
          <div class="address-cascade-container" data-address-cascade
            data-json-url="<?= e(base_url('assets/data/vietnam-provinces.json?v=' . filemtime(__DIR__ . '/../assets/data/vietnam-provinces.json'))) ?>"
            data-location-endpoint="<?= e(base_url('api/location.php')) ?>">

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
                <input type="hidden" name="province_code" value="" data-province-code>
                <input type="hidden" name="province_name" value="" data-province-name>
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
                <input type="hidden" name="ward_code" value="" data-ward-code>
                <input type="hidden" name="ward_name" value="" data-ward-name>
              </div>
            </div>

            <!-- Bước 3: Số nhà, tên đường -->
            <div class="address-field-group">
              <label for="address-street-input" class="address-sub-label">
                <span class="sub-label-title"><?= e(sepay_t('addr_street_label')) ?></span><span class="required-star">*</span>
              </label>
              <input type="text" id="address-street-input" class="address-cascade-input"
                placeholder="<?= e(sepay_t('addr_street_placeholder')) ?>" maxlength="150"
                value="" data-street-address autocomplete="off">
            </div>

            <!-- Trường address tổng hợp gửi lên backend để lưu vào hồ sơ -->
            <textarea id="member-profile-address" name="delivery_address" style="display:none;"><?= e($member['delivery_address'] ?? '') ?></textarea>

            <div class="address-select-warning" data-address-warning role="alert"></div>
          </div>
        </div>

        <button class="btn primary" type="submit"><?= e(mt('member_save_profile')) ?></button>
      </form>
    </section>
    <section class="hym-card" id="member-platform-orders">
      <h2><?= e(mt('member_platform_orders')) ?></h2>
      <p class="hym-source"><?= e(mt('member_platform_source')) ?></p>
      <?php if (!$platformOrders): ?><p class="hym-empty"><?= e(mt('member_platform_empty')) ?></p><?php else: ?><div class="hym-order-list">
          <?php foreach ($platformOrders as $order): ?><article class="hym-order">
              <div class="hym-record-head">
                <div><span class="hym-label"><?= e(mt('member_order_number')) ?></span>
                  <h3><?= e($order['order_no']) ?> <span class="hym-order-type">- <?= e(mt(empty($order['group_room_id']) ? 'member_order_single' : 'member_order_group')) ?></span></h3>
                </div><span class="hym-order-status"><?= e(status_label((string)$order['status'])) ?></span>
              </div>
              <dl class="hym-facts">
                <div>
                  <dt><?= e(mt('member_order_date')) ?></dt>
                  <dd><?= e(format_datetime($order['created_at'], 'd/m/Y H:i')) ?></dd>
                </div>
                <div class="hym-record-amount">
                  <dt><?= e(mt('member_order_amount')) ?></dt>
                  <dd><?= e(currency()) ?><?= e(money($order['total_amount'])) ?></dd>
                </div>
              </dl>
              <?php if ($detail = order_detail_render($order)): ?><details class="hym-order-detail">
                  <summary><?= e(mt('member_order_detail')) ?></summary><?= $detail ?>
                </details><?php endif; ?><?php if (htp_receipt((int)$order['id'])): ?><a class="btn" href="<?= e(base_url('payment.php?order=' . rawurlencode($order['order_no']))) ?>"><?= e(htp('payment_link')) ?></a><?php endif; ?>
            </article><?php endforeach; ?>
        </div><?php endif; ?>
    </section>
  <?php endif; ?>
</div>
<?php if ($member): ?>
  <?= sepay_render_js_i18n() ?>
  <script src="<?= e(base_url('assets/js/member-location.js?v=' . filemtime(__DIR__ . '/../assets/js/member-location.js'))) ?>" defer></script>
<?php endif; ?>
<script>
  // Giới hạn danh sách đơn website hiển thị 3 đơn, phần còn lại cuộn trong khung
  (function() {
    var list = document.querySelector('#member-platform-orders .hym-order-list');
    if (!list || list.children.length <= 3) return;
    function cap() {
      list.classList.remove('is-capped');
      list.style.maxHeight = '';
      list.style.overflowY = '';
      var listTop = list.getBoundingClientRect().top;
      var third = list.children[2].getBoundingClientRect();
      list.style.maxHeight = Math.ceil(third.bottom - listTop + 2) + 'px';
      list.style.overflowY = 'auto';
      list.classList.add('is-capped');
    }
    cap();
    window.addEventListener('resize', cap);
  })();
</script>
<?php front_footer(); ?>
