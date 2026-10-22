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
    require_once __DIR__ . '/lib/order_workflow.php';
    if (function_exists('htow_cancel_expired_orders')) {
        try {
            htow_cancel_expired_orders(10);
        } catch (Throwable $ignored) {}
    }
    $order = htp_customer_order($_GET['order'] ?? '');
    if ($method === 'POST') {
        $csrf = $_POST['csrf'] ?? null;
        $expected = $_SESSION['_csrf'] ?? null;
        if (!is_string($csrf) || !is_string($expected) || $expected === '' || !hash_equals($expected, $csrf)) {
            htp_fail('csrf_expired');
        }
        $action = $_POST['action'] ?? null;
        if ($action === 'report') {
            htp_report($order['order_no'], $_POST['account_code'] ?? null, $_POST['payment_quote'] ?? null);
            if (!is_array($_SESSION['transfer_payment_flash'] ?? null)) {
                $_SESSION['transfer_payment_flash'] = [];
            }
            $_SESSION['transfer_payment_flash'][$order['order_no']] = 'report_saved';
            header('Location: ' . base_url('payment.php?order=' . rawurlencode($order['order_no'])), true, 303);
            exit;
        } elseif ($action === 'cancel_pending') {
            $receipt = htp_receipt((int)$order['id']);
            if ($order['status'] !== 'new' || ($receipt && in_array($receipt['status'] ?? '', ['confirmed', 'overpaid'], true))) {
                throw new RuntimeException('Cannot cancel order in current state.');
            }

            $pdo = db();
            $pdo->beginTransaction();
            try {
                $currentOrder = hyg_one('SELECT * FROM orders WHERE id=? FOR UPDATE', [(int)$order['id']]);
                if ($currentOrder && $currentOrder['status'] === 'new') {
                    $statusVer = (int)$currentOrder['status_version'];
                    $pdo->prepare('UPDATE orders SET status="cancelled", status_version=status_version+1 WHERE id=? AND status_version=?')
                        ->execute([(int)$order['id'], $statusVer]);
                    $cancelReason = !empty($_POST['reorder']) ? 'Khách hàng hủy đơn để đặt lại' : 'Khách hàng chủ động hủy đơn chờ chuyển khoản';
                    $pdo->prepare('INSERT INTO order_status_events(order_id, from_status, to_status, from_version, to_version, admin_id, reason, created_at) VALUES(?,?,?,?,?,?,?,UTC_TIMESTAMP())')
                        ->execute([(int)$order['id'], 'new', 'cancelled', $statusVer, $statusVer + 1, 0, $cancelReason]);
                    
                    $r = hyg_one('SELECT * FROM transfer_order_receipts WHERE order_id=? FOR UPDATE', [(int)$order['id']]);
                    if ($r) {
                        $pdo->prepare('UPDATE transfer_order_receipts SET version=version+1 WHERE order_id=?')->execute([(int)$order['id']]);
                    }
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }

            // Remove from last_order_no but keep in customer_payment_orders
            // so the customer can still view their cancelled order details on this page
            if (isset($_SESSION['last_order_no']) && $_SESSION['last_order_no'] === $order['order_no']) {
                unset($_SESSION['last_order_no']);
            }

            $shouldReorder = !empty($_POST['reorder']);
            if ($shouldReorder) {
                $orderItems = hyg_all('SELECT * FROM order_items WHERE order_id=? ORDER BY id ASC', [(int)$order['id']]);
                if ($orderItems) {
                    $_SESSION['cart'] = [];
                    foreach ($orderItems as $item) {
                        $key = bin2hex(random_bytes(8));
                        $_SESSION['cart'][$key] = [
                            'product_id' => $item['product_id'] ? (int)$item['product_id'] : null,
                            'standalone_topping_id' => $item['standalone_topping_id'] ? (int)$item['standalone_topping_id'] : null,
                            'name' => $item['product_name'],
                            'cup_size' => $item['cup_size'],
                            'base_price' => (float)$item['base_price'],
                            'ice' => $item['ice_choice'],
                            'sugar' => $item['sugar_choice'],
                            'toppings' => [],
                            'unit_price' => (float)$item['unit_price'],
                            'qty' => (int)$item['qty'],
                            'lang' => current_lang(),
                        ];
                    }
                }
                $_SESSION['cart_flash'] = client_t('cancel_order_reorder_success');
                header('Location: ' . base_url('checkout.php'), true, 303);
                exit;
            }

            if (!is_array($_SESSION['transfer_payment_flash'] ?? null)) {
                $_SESSION['transfer_payment_flash'] = [];
            }
            $_SESSION['transfer_payment_flash'][$order['order_no']] = 'order_cancelled';
            header('Location: ' . base_url('payment.php?order=' . rawurlencode($order['order_no'])), true, 303);
            exit;
        }
        htp_fail('changed');
    }
    $flash = is_array($_SESSION['transfer_payment_flash'] ?? null) ? ($_SESSION['transfer_payment_flash'][$order['order_no']] ?? null) : null;
    if (is_array($_SESSION['transfer_payment_flash'] ?? null)) {
        unset($_SESSION['transfer_payment_flash'][$order['order_no']]);
    }
    if ($flash === 'report_saved') {
        $message = htp($flash);
    } elseif ($flash === 'order_cancelled') {
        $message = client_t('cancel_order_success');
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
        if ((float)($order['total_amount'] ?? 0) <= 0 || (isset($receipt['status']) && $receipt['status'] === 'not_required')) {
            header('Location: ' . base_url('success.php?order=' . rawurlencode($order['order_no'])), true, 303);
            exit;
        }
        $paymentHtml = $receipt ? htp_render_customer($order) : '<p>' . e(htp('legacy')) . '</p>';
    } catch (Throwable $e) {
        $error = htp_error($e);
    }
}

$isTransfer = ($order && ($order['payment_method'] ?? '') === 'transfer');
$isCancelled = ($order && ($order['status'] ?? '') === 'cancelled');
$isConfirmed = !$isCancelled && (($order && !$isTransfer) || (isset($receipt) && (in_array($receipt['status'] ?? '', ['confirmed', 'overpaid'], true) || ((float)($receipt['received_amount'] ?? 0) >= (float)($receipt['amount'] ?? 0) && (float)($receipt['amount'] ?? 0) > 0))));

$pageTitle = $isCancelled
    ? sepay_t('payment_cancelled_title')
    : ($isConfirmed
        ? ($isTransfer ? sepay_t('sepay_success_title') : sepay_t('payment_cash_title'))
        : ($isTransfer ? sepay_t('sepay_card_title') : sepay_t('payment_cash_title')));

$heroEyebrow = $isCancelled
    ? sepay_t('payment_cancelled_eyebrow')
    : ($isConfirmed
        ? ($isTransfer ? sepay_t('sepay_success_title') : sepay_t('payment_cash_eyebrow'))
        : ($isTransfer ? sepay_t('payment_gateway_title') : sepay_t('payment_cash_eyebrow')));

$heroTitle = $isCancelled
    ? sepay_t('payment_cancelled_title')
    : ($isConfirmed
        ? ($isTransfer ? sepay_t('sepay_success_title') : sepay_t('payment_cash_title'))
        : ($isTransfer ? sepay_t('sepay_card_title') : sepay_t('payment_cash_title')));

$heroDesc = $isCancelled
    ? sepay_t('payment_cancelled_desc')
    : ($isConfirmed
        ? ($isTransfer ? sepay_t('sepay_success_desc_order') : sepay_t('payment_cash_desc'))
        : ($isTransfer ? sepay_t('payment_gateway_desc') : sepay_t('payment_cash_desc')));

$calloutNote = ($isCancelled || $isConfirmed)
    ? null
    : ($isTransfer ? sepay_t('payment_important_note') : sepay_t('payment_cash_important_note'));

front_header($pageTitle);
?>
<link rel="stylesheet" href="<?= e(base_url('assets/css/sepay-payment.css?v=' . filemtime(__DIR__ . '/assets/css/sepay-payment.css'))) ?>">

<div class="payment-page-container">
  <?php if ($message): ?>
    <div class="alert success" role="status"><?= e($message) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert danger" role="alert"><?= e($error) ?></div>
  <?php endif; ?>

  <?php if ($order): ?>
    <?php if (!$isConfirmed && !$isCancelled): ?>
    <!-- 1. Modern Hero Banner -->
    <header class="payment-page-hero <?= $isCancelled ? 'is-cancelled' : ($isTransfer ? 'is-transfer' : 'is-cash') ?>">
      <div class="payment-hero-icon-wrap <?= $isCancelled ? 'is-cancelled' : ($isTransfer ? 'is-transfer' : 'is-cash') ?>" aria-hidden="true">
        <?php if ($isCancelled): ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="15" y1="9" x2="9" y2="15"></line>
            <line x1="9" y1="9" x2="15" y2="15"></line>
          </svg>
        <?php elseif ($isTransfer): ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="5" width="20" height="14" rx="2"></rect>
            <line x1="2" y1="10" x2="22" y2="10"></line>
          </svg>
        <?php else: ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="6" width="20" height="12" rx="2"></rect>
            <circle cx="12" cy="12" r="2.5"></circle>
            <path d="M6 12h.01M18 12h.01"></path>
          </svg>
        <?php endif; ?>
      </div>
      <span class="payment-hero-eyebrow <?= $isCancelled ? 'is-cancelled' : ($isTransfer ? 'is-transfer' : 'is-cash') ?>"><?= e($heroEyebrow) ?></span>
      <h1 class="payment-hero-title"><?= e($heroTitle) ?></h1>
      <p class="payment-hero-desc"><?= e($heroDesc) ?></p>

      <div class="payment-code-badge" data-sepay-copy="<?= e($order['order_no']) ?>" title="<?= e(sepay_t('success_copy_code')) ?>" role="button" tabindex="0">
        <span class="payment-code-label"><?= e(t('order_number')) ?>:</span>
        <strong class="payment-code-val"><?= e($order['order_no']) ?></strong>
        <span class="payment-code-copy-icon" aria-hidden="true">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
          </svg>
        </span>
      </div>
    </header>
    <?php endif; ?>

    <!-- 2. Payment Main Component (VietQR or Cash Card) -->
    <div class="payment-main-wrapper">
      <?= $paymentHtml ?>
    </div>

    <!-- 3. Important Tips / Notes Callout -->
    <?php if ($calloutNote): ?>
      <div class="payment-info-callout <?= $isTransfer ? 'is-transfer' : 'is-cash' ?>">
        <div class="payment-callout-icon" aria-hidden="true"><?= $isTransfer ? '💡' : '💵' ?></div>
        <div class="payment-callout-text">
          <?= e($calloutNote) ?>
        </div>
      </div>
    <?php endif; ?>

    <?php
      $storePhone = '';
      $storeName = '';
      if (!empty($order['dispatch_store_id'])) {
        $store = hyg_one('SELECT name, phone FROM dispatch_stores WHERE id=?', [(int)$order['dispatch_store_id']]);
        $storePhone = trim((string)($store['phone'] ?? ''));
        $storeName = trim((string)($store['name'] ?? ''));
      }
      $dial = preg_replace('/[^0-9+]/', '', $storePhone);
      $hasStorePhone = ($storePhone !== '' && preg_match('/^\+?[0-9]{6,20}$/D', $dial));
    ?>

    <!-- 4. Action Buttons Bar -->
    <div class="payment-actions-bar">
      <?php
        // Nút Hủy đơn: DUY NHẤT cho trường hợp đơn đang chờ thanh toán chuyển khoản
        $canCancelTransfer = ($order['status'] === 'new'
          && ($order['payment_method'] ?? '') === 'transfer'
          && (!isset($receipt) || ((float)($receipt['received_amount'] ?? 0) <= 0 && !in_array($receipt['status'] ?? '', ['confirmed', 'overpaid'], true)))
        );
      ?>
      <?php if ($canCancelTransfer): ?>
        <form method="post" action="<?= e(base_url('payment.php?order=' . rawurlencode($order['order_no']))) ?>" onsubmit="return confirm('<?= e(client_t('cancel_order_confirm')) ?>');" style="display:inline-flex;margin:0;" data-cancel-order-form>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="cancel_pending">
          <input type="hidden" name="reorder" value="0">
          <button type="submit" class="btn-payment-action danger" title="<?= e(client_t('cancel_order_only_btn')) ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="15" y1="9" x2="9" y2="15"></line>
              <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>
            <span><?= e(client_t('cancel_order_only_btn')) ?></span>
          </button>
        </form>
      <?php endif; ?>

      <a class="btn-payment-action primary" href="<?= e(base_url('index.php')) ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
        <span><?= e(t('return_menu')) ?></span>
      </a>
    </div>

    <?php if ($hasStorePhone): ?>
      <!-- 4.1 Branch Support Hotline -->
      <div class="payment-support-contact">
        <span><?= e(client_t('payment_support_need_help')) ?></span>
        <?php if ($storeName !== ''): ?>
          <span class="payment-support-store">(<?= e($storeName) ?>)</span>
        <?php endif; ?>
        <a class="payment-support-phone" href="tel:<?= e($dial) ?>" title="<?= e(sepay_t('success_call_branch')) ?>">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
          </svg>
          <strong><?= e($storePhone) ?></strong>
        </a>
      </div>
    <?php endif; ?>

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
