<?php
require_once __DIR__ . '/promotion_views.php';
require_once __DIR__ . '/game_entry.php';
require_once __DIR__ . '/group_payment_views.php';

/** Host-only share panel; the QR encodes the join invitation, never an owner URL. */
function hyg_render_share(array $r): string
{
  if (!hyg_host($r) || $r['status'] !== 'open') return '';
  $expires = strtotime($r['expires_at'] . ' UTC');
  if ($expires === false || $expires <= time()) return '';
  $token = (string)($r['invite_token'] ?? '');
  if (!preg_match('/^[a-f0-9]{64}$/D', $token)) return '';
  $link = base_url('group/?invite=' . $token);
  ob_start(); ?>
  <section class="hyg-share hyg-card" data-group-share data-expires="<?= $expires ?>">
    <div class="hyg-share-head">
      <h2><?= e(gt('share_cart')) ?></h2>
      <p><?= e(gt('qr_hint')) ?></p>
    </div>
    <div class="hyg-share-layout">
      <figure class="hyg-qr-panel" aria-label="<?= e(gt('qr_title')) ?>">
        <div class="hyg-qr-frame"><img class="hyg-qr-image" data-qr-image width="224" height="224" alt="<?= e(gt('qr_alt')) ?>" hidden>
          <p data-qr-status role="status"><?= e(gt('qr_loading')) ?></p>
        </div>
        <figcaption><?= e(gt('qr_title')) ?></figcaption>
      </figure>
      <div class="hyg-share-main">
        <label for="hyg-invite"><?= e(gt('share_link')) ?></label>
        <input id="hyg-invite" value="<?= e($link) ?>" readonly autocomplete="off" spellcheck="false" aria-label="<?= e(gt('share_link')) ?>">
        <div class="hyg-share-actions">
          <button class="btn primary" type="button" data-share-url="<?= e($link) ?>" data-group-share-button data-share-title="<?= e(gt('share_text', ['title' => $r['title']])) ?>"><?= e(gt('share_group')) ?></button>
          <button class="btn" type="button" data-copy-url="<?= e($link) ?>"><?= e(gt('copy')) ?></button>
          <a class="btn hyg-qr-download" data-qr-download data-filename="huiyitea-group-<?= e(substr($r['public_id'], 0, 12)) ?>.png" aria-disabled="true" tabindex="-1"><?= e(gt('qr_download')) ?></a>
        </div>
        <p class="hyg-share-deadline"><?= e(gt('expires')) ?> <strong><?= e(date('m/d H:i T', $expires)) ?></strong> · <?= e(gt('expiry_short')) ?></p>
        <p class="hyg-qr-save-hint" data-qr-save-hint hidden><?= e(gt('qr_save_hint')) ?></p>
        <p class="hyg-share-feedback" data-share-feedback role="status" aria-live="polite"></p>
        <noscript>
          <p class="hyg-muted"><?= e(gt('qr_no_js')) ?></p>
        </noscript>
        <details class="hyg-link-options">
          <summary><?= e(gt('rotate')) ?></summary>
          <p class="hyg-muted"><?= e(gt('rotate_hint')) ?></p>
          <form method="post" action="<?= e(base_url('group/action.php')) ?>" data-confirm="<?= e(gt('rotate_confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="room_id" value="<?= e($r['public_id']) ?>"><input type="hidden" name="action" value="rotate"><button type="submit" class="hyg-text-btn"><?= e(gt('rotate')) ?></button></form>
        </details>
      </div>
    </div>
  </section>
<?php return ob_get_clean();
}
function hyg_action_fields(array $r, string $action): string
{
  return csrf_field() . '<input type="hidden" name="room_id" value="' . e($r['public_id']) . '"><input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="revision" value="' . (int)$r['revision'] . '">';
}
function hyg_payment_action_fields(array $r, string $action, int $memberId = 0, ?float $paymentAmount = null): string
{
  return hyg_action_fields($r, $action) . '<input type="hidden" name="quote_signature" value="' . e($r['lock_signature'] ?? '') . '">' . ($memberId > 0 ? '<input type="hidden" name="member_id" value="' . $memberId . '">' : '') . ($paymentAmount !== null ? '<input type="hidden" name="payment_amount" value="' . e((string)hyg_money($paymentAmount)) . '">' : '');
}
/** Store transfer information appears in the payer's expanded card. */
function hyg_render_member_banks(array $accounts): string
{
  ob_start(); ?>
  <div class="hyg-bank-accounts"><strong><?= e(t('transfer_payment_info')) ?></strong>
    <?php if (!$accounts): ?><p class="hyg-muted"><?= e(t('transfer_payment_later')) ?></p><?php endif; ?>
    <?php foreach ($accounts as $bank): ?><article class="hyg-bank-account">
        <?php if (!empty($bank['qr_image'])): ?><img loading="lazy" src="<?= e(base_url('uploads/' . $bank['qr_image'])) ?>" alt="<?= e(t('transfer_qr_alt')) ?>" width="160" height="160"><?php endif; ?>
        <div><strong><?= e($bank['bank_name']) ?></strong>
          <dl>
            <dt><?= e(t('account_number')) ?></dt>
            <dd><?= e($bank['account_number']) ?></dd>
            <dt><?= e(t('account_name')) ?></dt>
            <dd><?= e($bank['account_name']) ?></dd>
          </dl>
        </div>
      </article><?php endforeach; ?>
  </div>
<?php return ob_get_clean();
}
function hyg_render_room(array $r): string
{
  $m = hyg_member($r);
  $host = hyg_host($r);
  $paymentMembers = hyg_all('SELECT * FROM group_members WHERE room_id=? ORDER BY id', [$r['id']]);
  $expired = ($r['status'] === 'open' && strtotime($r['expires_at'] . ' UTC') <= time()) || ($r['status'] === 'locked' && !hyg_room_payment_active($r, $paymentMembers));
  $open = $r['status'] === 'open' && !$expired;
  $status = $expired ? 'expired' : $r['status'];
  $members = hyg_all('SELECT m.id,m.nickname,EXISTS(SELECT 1 FROM group_items i WHERE i.room_id=m.room_id AND i.member_id=m.id) AS ready FROM group_members m WHERE m.room_id=? ORDER BY m.id', [$r['id']]);
  $cart = hyg_cart($r, false);
  $coupon = hyg_coupon($r, $cart, false);
  $payment = hyg_payment_summary($r, $cart, $coupon);
  $subtotal = $payment['subtotal'];
  $split = $payment['mode'] === 'split';
  $banks = active_bank_accounts();
  $drinks = 0;
  $portions = 0;
  $hasInvalid = false;
  $approval = (int)($payment['protocol_version'] ?? 1) >= 2;
  $hasReports = !empty($payment['has_reports'] ?? $payment['has_paid']);
  $allConfirmed = !empty($payment['all_confirmed']);
  $hostCollect = ($payment['collection_mode'] ?? 'store') === 'host';
  $promotion = promotion_room_choice($r, $cart);
  if ($r['status'] === 'submitted') $members = array_values(array_map(fn($person) => ['id' => (int)$person['id'], 'nickname' => $person['nickname'], 'ready' => false], $payment['members']));
  foreach ($cart as $row) {
    if (!empty($row['product_id'])) $drinks += (int)$row['qty'];
    else $portions += (int)$row['qty'];
    if (!empty($row['_invalid'])) $hasInvalid = true;
  }
  $ready = count(array_filter($members, fn($x) => !empty($x['ready'])));
  $order = $r['order_id'] ? hyg_one('SELECT * FROM orders WHERE id=?', [$r['order_id']]) : null;
  ob_start(); ?>
  <div id="hyg-room-live" data-revision="<?= e($r['revision']) ?>" data-state="<?= e($status) ?>">
    <div class="hyg-room-state"><span class="hyg-status hyg-<?= e($status) ?>"><?= e(gt($status)) ?></span><?php if ($open): ?><span><?= e(gt('ready_count', ['ready' => $ready, 'total' => count($members)])) ?></span><?php endif; ?><?php if (in_array($r['status'], ['open', 'locked'], true)): ?><small><?= e(gt($r['status'] === 'locked' ? 'payment_deadline' : 'expires')) ?> <?= e(date('m/d H:i T', strtotime(($r['status'] === 'locked' ? ($r['payment_expires_at'] ?? $r['expires_at']) : $r['expires_at']) . ' UTC'))) ?></small><?php endif; ?></div>
    <?php if ($order): ?><section class="hyg-card hyg-sent">
        <h2><?= e(gt('submitted')) ?></h2><strong><?= e($order['order_no']) ?></strong>
        <p><?= e(t(status_label_key($order['status']))) ?></p>
        <p><?= e(gt('sent_note')) ?></p>
      </section><?php endif; ?>
    <?php if ($order && $host): ?><?= hge_render(hge_order_state($order)) ?><?php endif; ?>
    <?php if ($hostCollect): ?><div class="alert info"><?= e(htp('collected_hint')) ?></div><?php endif; ?>
    <?php if ($order && $host && function_exists('htp_render_customer')): ?><?= htp_render_customer($order) ?><?php endif; ?>
    <?php if ($status === 'expired'): ?><div class="alert danger"><?= e(gt('room_expired')) ?> <a class="hyg-text-btn" href="<?= e(base_url('group/')) ?>"><?= e(gt('create')) ?></a></div><?php endif; ?>
    <?php if ($r['status'] === 'locked' && hyg_room_has_payment_work($paymentMembers)): ?><p class="alert info"><?= e(gt('payment_recovery')) ?></p><?php endif; ?>
    <?php if ($status === 'locked'): ?><div class="alert info"><?= e(gt(!empty($payment['legacy']) ? 'settlement_unavailable' : ($split ? 'waiting_split' : 'waiting'))) ?></div><?php endif; ?>
    <div class="hyg-room-grid">
      <div class="hyg-members">
        <?php foreach ($members as $person): $own = (int)$person['id'] === (int)$m['id'];
          $rows = array_filter($cart, fn($x) => (int)$x['group_member_id'] === (int)$person['id']);
          $memberPayment = hyg_member_payment($payment, (int)$person['id']);
          $isHost = !empty($memberPayment['is_host']);
          $payable = (float)$memberPayment['due'];
          $covered = (float)($memberPayment['covered_amount'] ?? 0);
          $payerId = (int)($memberPayment['payer_member_id'] ?? $person['id']);
          $payerDue = (float)($payment['members'][$payerId]['due'] ?? $payable);
          $hasPayment = ($payable > 0 || $covered > 0) && $payerDue > 0;
          $coveredPayer = $covered > 0 && $payerId !== (int)$person['id'];
          $paid = $hasPayment && $memberPayment['status'] === 'paid';
          $requested = $hasPayment && $memberPayment['status'] === 'requested';
          $paymentLabel = !$hasPayment ? 'payment_none' : ($paid ? 'payment_paid' : ($requested ? 'payment_requested' : ($coveredPayer ? 'payment_await_host' : 'payment_pending')));
          $canRequest = $own && !empty($memberPayment['can_request']);
          $canConfirm = $host && !empty($memberPayment['can_confirm']);
          $canRemove = $host && !empty($memberPayment['can_remove']);
          $changed = !empty($memberPayment['amount_changed']); ?>
          <?php $newPayment = (float)($memberPayment['outstanding'] ?? $payable);
          $retainedFunds = (float)($memberPayment['net_received'] ?? 0); ?>

          <!-- close-test-Hai | Đóng mở Dialog tắt  Đổi thẻ detail => div || đổi sumary => <div> -->
          <div class="hyg-person hyg-card" data-member-id="<?= (int)$person['id'] ?>"> <!-- <detail></detail> -->
            <div class="hyg-person-head"><strong><?= e($person['nickname']) ?> <!-- <summary></summary> -->
              </strong>
              <span class="hyg-person-status">
                <span class="hyg-ready <?= $open && !empty($person['ready']) ? 'is-ready' : '' ?>">
                  <!-- <?= e(gt($open ? (!empty($person['ready']) ? 'ready_label' : 'selecting') : $status)) ?> -->
                </span><?php if (!$payment['legacy']): ?>
                  <span class="hyg-payment-status <?= $paid ? 'is-paid' : ($requested ? 'is-requested' : '') ?>"><?= e($hostCollect && $paid ? htp($isHost || $coveredPayer ? 'funds_ready' : 'collected') : gt($paymentLabel)) ?></span><?php endif; ?>
              </span>
            </div>


            <div class="hyg-person-body">
              <?php if ($own || $isHost): ?><p class="hyg-person-identity"><?php if ($own): ?><span class="hyg-you"><?= e(gt('you')) ?></span><?php endif; ?><?php if ($isHost): ?><span><?= e(gt('host')) ?></span><?php endif; ?></p><?php endif; ?>
              <?php if (!$rows): ?><p class="hyg-muted"><?= e(gt('empty_person')) ?></p><?php endif; ?>
              <?php foreach ($rows as $key => $row): $editable = $open && ($own || $host);
                $invalid = !empty($row['_invalid']); ?>
                <article class="hyg-item <?= $invalid ? 'is-invalid' : '' ?>">
                  <div class="hyg-item-head"><strong><?= e($row['name'] ?? '') ?></strong><span>× <?= (int)($row['qty'] ?? 0) ?></span></div>
                  <p class="hyg-options"><?= e(implode(' · ', array_filter([$row['cup_size'] ?? '', $row['ice'] ?? '', $row['sugar'] ?? '']))) ?></p>
                  <?php if (!empty($row['toppings'])): ?><p class="hyg-options"><?= e(t('toppings')) ?> · <?= e(implode('、', array_map(fn($x) => $x['display_name'] ?? $x['name'], $row['toppings']))) ?></p><?php endif; ?>
                  <?php if ($invalid): ?><p class="hyg-item-error"><?= e(gt($row['_invalid'])) ?></p><?php endif; ?>
                  <div class="hyg-item-bottom"><b><?= e(currency()) ?><?= money(($row['unit_price'] ?? 0) * ($row['qty'] ?? 0)) ?></b><?php if ($editable): ?><div class="hyg-item-actions">
                        <?php if ($own): $path = !empty($row['product_id']) ? 'product.php?id=' . $row['product_id'] : 'topping.php?id=' . $row['standalone_topping_id']; ?><a class="btn small" href="<?= e(base_url($path . '&group=' . $r['public_id'] . '&edit=' . $row['_item_id'])) ?>"><?= e(gt('edit')) ?></a><?php endif; ?>
                        <form method="post" action="<?= e(base_url('group/action.php')) ?>" data-confirm="<?= e(gt('remove_confirm')) ?>"><?= hyg_action_fields($r, 'remove') ?><input type="hidden" name="item_id" value="<?= $row['_item_id'] ?>"><input type="hidden" name="item_version" value="<?= $row['_item_version'] ?>"><button class="btn small danger" type="submit"><?= e(gt('remove')) ?></button></form>
                      </div><?php endif; ?>
                  </div>
                  <?php if ($editable && !$invalid): ?><form method="post" action="<?= e(base_url('group/action.php')) ?>" class="hyg-qty-form"><?= hyg_action_fields($r, 'quantity') ?><input type="hidden" name="item_id" value="<?= $row['_item_id'] ?>"><input type="hidden" name="item_version" value="<?= $row['_item_version'] ?>"><label><?= e(t('quantity')) ?><input aria-label="<?= e(t('quantity')) ?> <?= e($row['name']) ?>" name="qty" type="number" inputmode="numeric" min="1" max="20" value="<?= (int)$row['qty'] ?>"></label><button class="btn small" type="submit"><?= e(gt('update_qty')) ?></button></form><?php endif; ?>
                </article>
              <?php endforeach; ?>
              <?php if ($rows): ?><div class="hyg-person-total"><small><?= e(gt('subtotal')) ?></small><b><?= e(currency()) ?><?= money($memberPayment['subtotal']) ?></b></div><?php endif; ?>
              <?php if (!$payment['legacy'] && ($hasPayment || $isHost || $rows || ($memberPayment['net_received'] ?? 0) > 0)): ?><section class="hyg-member-payment" aria-label="<?= e(gt('payment_summary')) ?>">
                  <?php if ($isHost): ?><div class="hyg-totals-line"><span><?= e(gt('member_own_due')) ?></span><b><?= e(currency()) ?><?= money($payment['host_subtotal']) ?></b></div>
                    <div class="hyg-totals-line"><span><?= e(gt('host_advances')) ?></span><b><?= e(currency()) ?><?= money($payment['host_advance']) ?></b></div><?php if ($payment['host_discount'] > 0): ?><div class="hyg-totals-line"><span><?= e(gt('host_discount')) ?></span><b>−<?= e(currency()) ?><?= money($payment['host_discount']) ?></b></div><?php endif; ?><div class="hyg-person-total"><strong><?= e($hostCollect ? htp('contribution') : gt('host_due')) ?></strong><b><?= e(currency()) ?><?= money($payable) ?></b></div>
                  <?php elseif ($covered > 0): ?><p class="hyg-muted"><?= e(gt('covered_by_host', ['amount' => currency() . money($covered)])) ?></p>
                    <div class="hyg-person-total"><strong><?= e(gt('member_due')) ?></strong><b><?= e(currency()) ?><?= money($payable) ?></b></div>
                  <?php else: ?><div class="hyg-person-total"><strong><?= e(gt('member_due')) ?></strong><b><?= e(currency()) ?><?= money($payable) ?></b></div><?php endif; ?>
                  <?php if ($own && $open && ($split || $isHost)): ?>

                    <form class="hyg-payment-form" method="post" action="<?= e(base_url('group/action.php')) ?>">
                      <?= hyg_action_fields($r, 'payment_method') ?>

                      <label><?= e(gt('payment_method')) ?>
                        <select name="payment_method" onchange="this.form.requestSubmit()">
                          <option value="bank_transfer" <?= $memberPayment['method'] === 'bank_transfer' ? 'selected' : '' ?>><?= e(gt('payment_transfer')) ?></option>
                          <option value="cash" <?= $memberPayment['method'] === 'cash' ? 'selected' : '' ?>><?= e(gt('payment_cash')) ?></option>
                          <?php if (!$isHost): ?>
                            <option value="host_advance" <?= $memberPayment['method'] === 'host_advance' ? 'selected' : '' ?>><?= e(gt('payment_host_advance')) ?></option>
                          <?php endif; ?>
                        </select>
                      </label>
                    </form>

                  <?php else: ?><p class="hyg-payment-method"><?= e(gt('payment_method')) ?>：<strong><?= e(gt(['bank_transfer' => 'payment_transfer', 'cash' => 'payment_cash', 'host_advance' => 'payment_host_advance'][$memberPayment['method']] ?? 'payment_transfer')) ?></strong></p><?php endif; ?>
                  <?php if ($changed && $approval && $hasPayment): ?><p class="hyg-payment-amount-changed" role="status"><?= e(gt($coveredPayer ? 'payment_covered_amount_changed_hint' : ($isHost ? 'payment_host_amount_changed_hint' : 'payment_amount_changed_hint'), ['amount' => currency() . money($payable)])) ?></p><?php endif; ?>
                  <?php if ($own && $hasPayment && $open): ?><p class="hyg-muted"><?= e(gt('payment_locked_hint')) ?></p><?php endif; ?>
                  <?php if (!$hasPayment): ?><p class="hyg-muted"><?= e(gt('payment_none')) ?></p><?php endif; ?>
                  <?php if (($own || $host) && ($canRequest || $canConfirm)): ?><p class="alert info hyg-payment-increment"><strong><?= e(gt('payment_increment_label')) ?>：<?= e(currency()) ?><?= money($newPayment) ?></strong><?php if ($retainedFunds > 0): ?><br><?= e(gt($newPayment > 0 ? 'payment_increment_only' : 'payment_increment_zero', ['net' => currency() . money($retainedFunds), 'amount' => currency() . money($newPayment)])) ?><?php endif; ?></p><?php endif; ?>
                  <?php if ($own && $newPayment > 0 && ($canRequest || $canConfirm)): ?>
                    <?php if ($hostCollect && $isHost): ?><p class="hyg-muted"><?= e(htp('host_contribution')) ?></p>
                    <?php else: ?>
                      <?php if (!$isHost): ?><p class="hyg-muted"><?= e($hostCollect ? htp($memberPayment['method'] === 'cash' ? 'cash_host' : 'pay_host') : gt($memberPayment['method'] === 'cash' ? 'payment_cash_hint' : 'payment_transfer_hint')) ?></p><?php endif; ?>
                      <?php if ($memberPayment['method'] === 'bank_transfer'): ?><?php if ($hostCollect): $hostAccount = htp_group_account($r);
                                                                                    if ($hostAccount) echo htp_render_account($hostAccount);
                                                                                    else echo '<p>' . e(htp('missing')) . '</p>';
                                                                                  else: ?><?= hyg_render_member_banks($banks) ?><?php endif; ?><p class="hyg-payment-reference"><?= e(gt('payment_reference_unique', ['reference' => hyg_payment_reference($r, (int)$person['id'])])) ?></p><?php endif; ?>
                  <?php endif; ?>
                <?php endif; ?>
                <?php if ($canRequest): ?><div class="hyg-payment-approval">
                    <form method="post" action="<?= e(base_url('group/action.php')) ?>" data-confirm="<?= e(gt('payment_report_increment_prompt', ['amount' => currency() . money($newPayment)])) ?>">
                      <?= hyg_payment_action_fields($r, 'payment_complete', (int)$person['id'], $newPayment) ?>
                      <button class="btn primary block" type="submit"><?= e(gt('payment_complete')) ?> · <?= e(currency()) ?><?= money($newPayment) ?>
                      </button>
                    </form>
                    <p class="hyg-muted"><?= e(gt('payment_self_report')) ?></p>
                  </div><?php endif; ?>
                <?php if ($canConfirm): ?><div class="hyg-payment-approval">
                    <p class="hyg-muted"><?= e(gt('payment_increment_review', ['amount' => currency() . money($newPayment), 'net' => currency() . money($retainedFunds)])) ?></p>
                    <form method="post" action="<?= e(base_url('group/action.php')) ?>" data-confirm="<?= e(gt('payment_confirm_increment_prompt', ['name' => $person['nickname'], 'amount' => currency() . money($newPayment)])) ?>"><?= hyg_payment_action_fields($r, 'payment_confirm', (int)$person['id'], $newPayment) ?><button class="btn primary block" type="submit"><?= e($hostCollect && $own ? htp('contribution_confirm') : gt('payment_confirm')) ?> · <?= e(currency()) ?><?= money($newPayment) ?></button></form>
                  </div><?php endif; ?>
                <?php if ($approval && $hasPayment && $requested && !$canRequest && !$canConfirm): ?><p class="hyg-muted"><?= e(gt('payment_requested_hint')) ?></p><?php endif; ?>
                <?php if ($approval && $hasPayment && $paid): ?><p class="hyg-muted"><?= e(gt('payment_confirmed_hint')) ?></p><?php endif; ?>
                <?php if ($covered > 0 && $approval): ?><p class="hyg-muted"><?= e(gt('payment_advance_hint')) ?></p><?php endif; ?>
                <?php if (!$approval && $status === 'submitted'): ?><p class="hyg-muted"><?= e(gt('payment_historical_hint')) ?></p><?php endif; ?>
                <?php if (in_array($memberPayment['status'], ['rejected', 'refunded'], true)): ?><p class="hyg-muted"><?= e(gt($memberPayment['status'] === 'rejected' ? 'payment_rejected' : 'payment_refunded_status')) ?></p><?php endif; ?>
                <?php if (isset($memberPayment['received_amount']) && ($memberPayment['received_amount'] > 0 || $memberPayment['refunded_amount'] > 0)): ?><dl class="hyg-settlement-totals">
                    <div>
                      <dt><?= e(gt('payment_received')) ?></dt>
                      <dd><?= e(currency()) ?><?= money($memberPayment['received_amount']) ?></dd>
                    </div>
                    <div>
                      <dt><?= e(gt('payment_refunded')) ?></dt>
                      <dd><?= e(currency()) ?><?= money($memberPayment['refunded_amount']) ?></dd>
                    </div>
                    <div>
                      <dt><?= e(gt('payment_net')) ?></dt>
                      <dd><?= e(currency()) ?><?= money($memberPayment['net_received']) ?></dd>
                    </div><?php if ($memberPayment['refund_due'] > 0): ?><div>
                        <dt><?= e(gt('payment_refund_due')) ?></dt>
                        <dd><?= e(currency()) ?><?= money($memberPayment['refund_due']) ?></dd>
                      </div><?php endif; ?>
                  </dl><?php endif; ?>
                <?php if ($host): ?><?= hyg_render_payment_controls($r, $memberPayment) ?><?php endif; ?>
                </section><?php endif; ?>
              <?php if ($canRemove): ?><div class="hyg-member-manage">
                  <form method="post" action="<?= e(base_url('group/action.php')) ?>" data-confirm="<?= e(gt('remove_member_confirm', ['name' => $person['nickname']])) ?>"><?= hyg_action_fields($r, 'remove_member') ?><input type="hidden" name="member_id" value="<?= (int)$person['id'] ?>"><button class="btn danger block" type="submit"><?= e(gt('remove_member')) ?></button></form>
                  <p class="hyg-muted"><?= e(gt('remove_member_hint')) ?></p>
                </div><?php endif; ?>
            </div>
          </div><?php endforeach; ?>

        <?php if ($open): ?><div class="hyg-join-actions"><a class="btn primary" href="<?= e(base_url('index.php?group=' . $r['public_id'])) ?>"><?= e(gt('choose')) ?></a></div>
          <p class="hyg-muted"><?= e(gt('automatic_ready_hint')) ?></p><?php endif; ?>
      </div>
      <aside class="hyg-summary hyg-card"><span class="hyg-eyebrow">HỒNG TRÀ TUỔI THƠ</span>
        <h2><?= e(gt($order ? 'final_total' : 'total')) ?></h2>
        <div class="hyg-total"><?= e(currency()) ?><?= money($order ? $order['total_amount'] : $payment['total']) ?></div>
        <p class="hyg-counts"><?= e(gt('people', ['n' => count($members)])) ?> · <?= e(gt('cups', ['n' => $drinks])) ?><?php if ($portions): ?><br><?= e(gt('portions', ['n' => $portions])) ?><?php endif; ?></p>
        <div class="hyg-totals-line"><span><?= e(gt('subtotal')) ?></span><b><?= e(currency()) ?><?= money($subtotal) ?></b></div>
        <?php if ($payment['discount'] > 0): ?><div class="hyg-totals-line"><span><?= e(gt('discount')) ?></span><b>−<?= e(currency()) ?><?= money($payment['discount']) ?></b></div><?php endif; ?>
        <div class="hyg-totals-line"><span><?= e(gt('checkout_mode')) ?></span><b><?= e(gt($split ? 'checkout_split' : 'checkout_together')) ?></b></div>
        <?php if (!$payment['legacy']): ?><div class="hyg-totals-line"><span><?= e(gt('host_advances')) ?></span><b><?= e(currency()) ?><?= money($payment['host_advance']) ?></b></div>
          <div class="hyg-totals-line hyg-host-due"><span><?= e($hostCollect ? htp('contribution') : gt('host_due')) ?></span><b><?= e(currency()) ?><?= money($payment['host_due']) ?></b></div><?php endif; ?>
        <?php if ($approval && $payment['frozen']): ?><div class="hyg-payment-progress" role="status"><strong><?= e(gt('payment_confirmation_progress', ['confirmed' => (int)($payment['confirmed_payers'] ?? 0), 'required' => (int)($payment['required_payers'] ?? 0)])) ?></strong>
            <p class="hyg-muted"><?= e(gt(!$cart ? 'empty_room' : ($allConfirmed ? 'payments_all_confirmed' : 'payments_unconfirmed'))) ?></p>
          </div><?php endif; ?>
        <?= promotion_summary($promotion) ?>
        <?php if ($status === 'locked'): ?><p class="hyg-muted"><?= e(pt('frozen')) ?></p><?php endif; ?>

        <?php if ($open && $host): ?>

          <!-- close-test-Hai | Cách thanh toán -->

          <!-- <form class="hyg-payment-form hyg-checkout-mode-form" action="<?= e(base_url('group/action.php')) ?>" method="post"><?= hyg_action_fields($r, 'checkout_mode') ?><label><?= e(gt('checkout_mode')) ?><select name="checkout_mode">
                <option value="together" <?= !$split ? 'selected' : '' ?>><?= e(gt('checkout_together')) ?></option>
                <option value="split" <?= $split ? 'selected' : '' ?>><?= e(gt('checkout_split')) ?></option>
              </select></label><button class="btn" type="submit"><?= e(gt('apply')) ?></button><small><?= e(gt($split ? 'split_hint' : 'together_hint')) ?></small><small><?= e(gt('checkout_mode_hint')) ?></small>
          </form> -->

          <?= promotion_selector($promotion, ['group_public' => $r['public_id'], 'revision' => $r['revision'], 'host_discount_available' => $payment['host_obligation'] > 0]) ?>

          <form action="<?= e(base_url('group/action.php')) ?>" method="post" data-confirm="<?= e(gt('lock_confirm')) ?>">
            <?= hyg_action_fields($r, 'lock') ?><button class="btn primary block" type="submit" <?= !$cart || $hasInvalid ? 'disabled' : '' ?>><?= e(gt('lock')) ?>
            </button>
          </form><?php endif; ?>
        <?php if ($status === 'locked' && $host): ?><?php if (!$payment['legacy']): ?><?php if (!empty($payment['can_checkout']) && $cart && !$hasInvalid): ?>
        <a class="btn primary block" href="<?= e(base_url('checkout.php?group=' . $r['public_id'])) ?>"><?= e(gt($split ? 'submit_group' : 'checkout')) ?>
        </a>
      <?php else: ?>

        <!-- close-test-Hai | btn thanh toán chung dư thừa => bởi ở bước xác nhận thanh toán là thanh toán rồi phải chuyển đế trang nhập thông tin thanh toán  -->
        <button class="btn primary block" type="button" disabled>
          <?= e(gt($split ? 'submit_group' : 'checkout')) ?>
        </button><?php endif; ?><?php endif; ?><?php if (!$hasReports): ?>

        <!-- btn mở lại chọn món  -->
        <form action="<?= e(base_url('group/action.php')) ?>" method="post"><?= hyg_action_fields($r, 'reopen') ?>
          <button class="btn block" type="submit"><?= e(gt('reopen')) ?></button>
        </form><?php else: ?><p class="hyg-muted"><?= e(gt('payment_locked')) ?></p><?php endif; ?><?php endif; ?>

        <?php if ($r['status'] === 'locked' && $host): ?>

          <!-- closs-test-Hai | btn Gia Hạn thanh toán -->

          <!-- <form action="<?= e(base_url('group/action.php')) ?>" method="post" class="hyg-payment-form"><?= hyg_payment_action_fields($r, 'payment_extend') ?>
            <label><?= e(gt('payment_reason')) ?>
              <input name="reason" required minlength="3" maxlength="240">
            </label>
            <button class="btn block" type="submit"><?= e(gt('payment_extend')) ?></button>
          </form> -->
        <?php endif; ?>

        <?php if (in_array($r['status'], ['open', 'locked'], true) && $host): ?>
          <form action="<?= e(base_url('group/action.php')) ?>" method="post" class="hyg-payment-form" data-confirm="<?= e(gt('cancel_confirm')) ?>"><?= hyg_payment_action_fields($r, 'cancel') ?><?php if ($r['status'] === 'locked'): ?>
            <p class="hyg-muted"><?= e(gt('payment_cancel_hint')) ?></p><?php endif; ?><label><?= e(gt('payment_reason')) ?><input name="reason" required minlength="3" maxlength="240"></label><button class="btn danger block" type="submit"><?= e(gt('cancel')) ?></button>
          </form><?php endif; ?>
      </aside>
    </div>
    <?= hyg_render_payment_history(hyg_payment_events($r, $host ? null : (int)$m['id'])) ?>
  </div>
<?php return ob_get_clean();
}
// Match the original status vocabulary; do not change order state semantics.
function status_label_key(string $status): string
{
  return ['new' => 'new_order', 'accepted' => 'accepted', 'done' => 'completed', 'cancelled' => 'cancelled'][$status] ?? 'new_order';
}
