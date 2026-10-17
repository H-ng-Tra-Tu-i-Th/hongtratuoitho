<?php
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/layout.php';

header('Cache-Control: private, no-store, max-age=0');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

$message = '';
$error = '';
$order = null;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    exit;
}

try {
    $order = htp_customer_order($_GET['order'] ?? '');
    if ($method === 'POST') {
        $csrf = $_POST['csrf'] ?? null;
        $expected = $_SESSION['_csrf'] ?? null;
        if (!is_string($csrf) || !is_string($expected) || $expected === '' || !hash_equals($expected, $csrf)) {
            htp_fail('csrf_expired');
        }
        if (($_POST['action'] ?? null) !== 'report') {
            htp_fail('changed');
        }
        htp_report($order['order_no'], $_POST['account_code'] ?? null, $_POST['payment_quote'] ?? null);
        if (!is_array($_SESSION['transfer_payment_flash'] ?? null)) {
            $_SESSION['transfer_payment_flash'] = [];
        }
        $_SESSION['transfer_payment_flash'][$order['order_no']] = 'report_saved';
        header('Location: ' . base_url('payment.php?order=' . rawurlencode($order['order_no'])), true, 303);
        exit;
    }
    $flash = is_array($_SESSION['transfer_payment_flash'] ?? null) ? ($_SESSION['transfer_payment_flash'][$order['order_no']] ?? null) : null;
    if (is_array($_SESSION['transfer_payment_flash'] ?? null)) {
        unset($_SESSION['transfer_payment_flash'][$order['order_no']]);
    }
    if ($flash === 'report_saved') {
        $message = htp($flash);
    }
} catch (Throwable $e) {
    $key = $e instanceof TransferPaymentError ? $e->getMessage() : 'storage';
    $error = $key === 'csrf_expired' ? t('csrf_expired') : htp_error($e);
    http_response_code($key === 'csrf_expired' ? 419 : ($key === 'forbidden' ? 403 : 409));
}

$paymentHtml = '';
if ($order) {
    try {
        $order = htp_customer_order($order['order_no']);
        $receipt = htp_receipt((int)$order['id']);
        // If order has already been confirmed/paid, forward immediately to success receipt page
        if ($receipt && in_array($receipt['status'] ?? '', ['confirmed', 'overpaid'], true)) {
            header('Location: ' . base_url('success.php?order=' . rawurlencode($order['order_no'])), true, 303);
            exit;
        }
        $paymentHtml = $receipt ? htp_render_customer($order) : '<p>' . e(htp('legacy')) . '</p>';
    } catch (Throwable $e) {
        $error = htp_error($e);
    }
}

front_header(sepay_t('sepay_card_title'));
?>
<link rel="stylesheet" href="<?= e(base_url('assets/css/sepay-payment.css?v=1.0.1')) ?>">

<div class="payment-page-container">
  <?php if ($message): ?>
    <div class="alert success" role="status"><?= e($message) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert danger" role="alert"><?= e($error) ?></div>
  <?php endif; ?>

  <?php if ($order): ?>
    <!-- 1. Modern Hero Banner -->
    <header class="payment-page-hero">
      <div class="payment-hero-icon-wrap" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="2" y="5" width="20" height="14" rx="2"></rect>
          <line x1="2" y1="10" x2="22" y2="10"></line>
        </svg>
      </div>
      <span class="payment-hero-eyebrow"><?= e(sepay_t('payment_gateway_title')) ?></span>
      <h1 class="payment-hero-title"><?= e(sepay_t('sepay_card_title')) ?></h1>
      <p class="payment-hero-desc"><?= e(sepay_t('payment_gateway_desc')) ?></p>

      <div class="payment-code-badge">
        <span class="payment-code-label"><?= e(t('order_number')) ?>:</span>
        <strong class="payment-code-val"><?= e($order['order_no']) ?></strong>
        <button type="button" class="btn-order-copy" data-sepay-copy="<?= e($order['order_no']) ?>" title="<?= e(sepay_t('success_copy_code')) ?>">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
          </svg>
          <span><?= e(sepay_t('success_copy_code')) ?></span>
        </button>
      </div>
    </header>

    <!-- 2. VietQR Card Component (rendered with SePay live polling) -->
    <div class="payment-main-wrapper">
      <?= $paymentHtml ?>
    </div>

    <!-- 3. Important Tips / Notes Callout -->
    <div class="payment-info-callout">
      <div class="payment-callout-icon" aria-hidden="true">💡</div>
      <div class="payment-callout-text">
        <?= e(sepay_t('payment_important_note')) ?>
      </div>
    </div>

    <!-- 4. Action Buttons Bar -->
    <div class="payment-actions-bar">
      <a class="btn-payment-action secondary" href="<?= e(base_url('payment.php?order=' . rawurlencode($order['order_no']))) ?>" data-payment-refresh>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <polyline points="23 4 23 10 17 10"></polyline>
          <polyline points="1 20 1 14 7 14"></polyline>
          <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
        </svg>
        <span><?= e(htp('refresh')) ?></span>
      </a>

      <?php if (!empty($order['dispatch_store_id'])): ?>
        <?php
          $store = hyg_one('SELECT name, phone FROM dispatch_stores WHERE id=?', [(int)$order['dispatch_store_id']]);
          $storePhone = trim((string)($store['phone'] ?? ''));
          $dial = preg_replace('/[^0-9+]/', '', $storePhone);
        ?>
        <?php if ($storePhone !== '' && preg_match('/^\+?[0-9]{6,20}$/D', $dial)): ?>
          <a class="btn-payment-action secondary" href="tel:<?= e($dial) ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
            </svg>
            <span><?= e(sepay_t('success_call_branch')) ?> (<?= e($storePhone) ?>)</span>
          </a>
        <?php endif; ?>
      <?php endif; ?>

      <a class="btn-payment-action primary" href="<?= e(base_url('index.php')) ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
        <span><?= e(t('return_menu')) ?></span>
      </a>
    </div>

    <!-- 5. Guest Session Orders (Discrete bottom chips) -->
    <?php $sessionOrdersHtml = htp_render_session_orders(); ?>
    <?php if ($sessionOrdersHtml): ?>
      <div class="payment-session-orders-wrap">
        <?= $sessionOrdersHtml ?>
      </div>
    <?php endif; ?>

  <?php else: ?>
    <?php
      $returnOrder = is_string($_GET['order'] ?? null) && preg_match('/^[A-Za-z0-9_-]{1,80}$/D', $_GET['order']) ? $_GET['order'] : '';
    ?>
    <div class="payment-lost-card">
      <div class="payment-lost-icon">⚠️</div>
      <h2><?= e(htp_ux('session_lost')) ?></h2>
      <div class="payment-actions-bar">
        <a class="btn-payment-action primary" href="<?= e(base_url('member/index.php' . ($returnOrder !== '' ? '?return_order=' . rawurlencode($returnOrder) : ''))) ?>"><?= e(htp_ux('login')) ?></a>
        <a class="btn-payment-action secondary" href="<?= e(base_url('index.php')) ?>"><?= e(t('return_menu')) ?></a>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php front_footer(); ?>
