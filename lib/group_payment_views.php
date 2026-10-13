<?php
/** Read-only settlement summary shared by checkout and both order desks. */
function hyg_render_payment_overview(array $summary): string {
    $hostId=(int)$summary['host_member_id'];$approval=(int)($summary['protocol_version']??1)>=2;$hostCollect=($summary['collection_mode']??'store')==='host';
    ob_start(); ?>
    <section class="hyg-payment-overview">
      <h2><?=e(gt('settlement_title'))?></h2>
      <p><strong><?=e(gt('checkout_mode'))?>：</strong><?=e(gt($summary['mode']==='split'?'pay_split':'pay_together'))?></p>
      <dl class="hyg-settlement-totals">
        <div><dt><?=e(gt('host_subtotal'))?></dt><dd><?=e(currency())?><?=money($summary['host_subtotal'])?></dd></div>
        <div><dt><?=e(gt('host_advance'))?></dt><dd><?=e(currency())?><?=money($summary['host_advance'])?></dd></div>
        <?php if($summary['host_discount']>0):?><div><dt><?=e(gt('discount'))?></dt><dd>−<?=e(currency())?><?=money($summary['host_discount'])?></dd></div><?php endif;?>
        <div class="hyg-settlement-grand"><dt><?=e($hostCollect?htp('contribution'):gt('host_due'))?></dt><dd><?=e(currency())?><?=money($summary['host_due'])?></dd></div>
      </dl>
      <?php if($hostCollect):?><p class="hyg-muted"><?=e(htp('collected_hint'))?></p><p><strong><?=e(htp('merchant_total'))?>：<?=e(currency())?><?=money($summary['total'])?></strong></p><?php endif;?>
      <?php if($approval&&$summary['frozen']):?><p class="hyg-payment-progress"><strong><?=e(gt('payment_confirmation_progress',['confirmed'=>(int)($summary['confirmed_payers']??0),'required'=>(int)($summary['required_payers']??0)]))?></strong></p><?php endif;?>
      <div class="hyg-settlement-members">
        <?php foreach($summary['members'] as $member): $isHost=(int)$member['id']===$hostId;$payerId=(int)($member['payer_member_id']??$member['id']);$payerDue=(float)($summary['members'][$payerId]['due']??$member['due']);$hasPayment=$payerDue>0&&((float)$member['due']>0||(float)$member['covered_amount']>0);$coveredPayer=(float)$member['covered_amount']>0&&$payerId!==(int)$member['id'];$paid=$hasPayment&&$member['status']==='paid';$requested=$hasPayment&&$member['status']==='requested';
          $statusKey=!$hasPayment?'payment_none':($paid?'paid':($requested?'requested':(in_array($member['status'],['rejected','refunded'],true)?'payment_'.($member['status']==='rejected'?'rejected':'refunded_status'):'pending'))); ?>
        <details class="hyg-settlement-person">
          <summary><strong><?=e($member['nickname'])?></strong><span class="hyg-payment-status <?=$paid?'is-paid':($requested?'is-requested':'')?>"><?=e($hostCollect&&$paid?htp($isHost||$coveredPayer?'funds_ready':'collected'):gt($statusKey))?></span></summary>
          <dl class="hyg-settlement-totals">
            <div><dt><?=e(gt('subtotal'))?></dt><dd><?=e(currency())?><?=money($member['subtotal'])?></dd></div>
            <div><dt><?=e(gt('payment_method'))?></dt><dd><?=e(gt($member['method']==='cash'?'cash':($member['method']==='host_advance'?'host_advance':'bank_transfer')))?></dd></div>
            <div><dt><?=e(gt('settlement_due'))?></dt><dd><?=e(currency())?><?=money($isHost?$summary['host_due']:$member['due'])?></dd></div>
            <?php if(isset($member['received_amount'])):?>
            <div><dt><?=e(gt('payment_received'))?></dt><dd><?=e(currency())?><?=money($member['received_amount'])?></dd></div>
            <div><dt><?=e(gt('payment_refunded'))?></dt><dd><?=e(currency())?><?=money($member['refunded_amount'])?></dd></div>
            <div><dt><?=e(gt('payment_increment_label'))?></dt><dd><?=e(currency())?><?=money($member['outstanding'])?></dd></div>
            <?php if($member['refund_due']>0):?><div><dt><?=e(gt('payment_refund_due'))?></dt><dd><?=e(currency())?><?=money($member['refund_due'])?></dd></div><?php endif;?>
            <?php endif;?>
          </dl>
          <?php if($approval&&$hasPayment&&!empty($member['amount_changed'])):?><p class="hyg-muted hyg-payment-amount-changed"><?=e(gt($coveredPayer?'payment_covered_amount_changed_hint':($isHost?'payment_host_amount_changed_hint':'payment_amount_changed_hint'),['amount'=>currency().money($member['due'])]))?></p><?php endif;?>
          <?php if($approval&&$payerDue>0&&($paid||$requested)):?><p class="hyg-muted hyg-settlement-note"><?=e(gt($paid?'payment_confirmed_hint':'payment_requested_hint'))?></p><?php endif;?>
        </details>
        <?php endforeach;?>
      </div>
      <p class="hyg-muted"><?=e($hostCollect?htp('collected_hint'):gt($approval?'self_reported':'payment_historical_hint'))?></p>
    </section>
    <?php return ob_get_clean();
}
/** All controls record an already checked outcome; none execute a bank transfer. */
function hyg_render_payment_controls(array $room,array $person): string {
    ob_start(); ?>
    <?php if(!empty($person['can_reject'])||!empty($person['can_refund'])||!empty($person['can_correct'])):?><details class="hyg-payment-controls"><summary><?=e(gt('payment_history'))?></summary><p class="hyg-muted"><?=e(gt('payment_control_hint'))?></p>
    <?php foreach(['payment_reject'=>'can_reject','payment_refund'=>'can_refund','payment_correct'=>'can_correct_received'] as $action=>$permission):if(empty($person[$permission]))continue;?>
      <form method="post" action="<?=e(base_url('group/action.php'))?>" class="hyg-payment-form" data-confirm="<?=e(gt($action))?>">
        <?=hyg_payment_action_fields($room,$action,(int)$person['id'])?>
        <?php if($action==='payment_correct'):?><input type="hidden" name="correction_target" value="received"><?php endif;?>
        <?php if($action!=='payment_reject'):?><label><?=e(gt($action==='payment_correct'?'payment_correct_amount':$action))?><input type="number" name="amount" min="<?=$action==='payment_refund'?'0.01':'0'?>" max="<?=e((string)($action==='payment_refund'?$person['net_received']:$person['received_amount']))?>" step="0.01" value="<?=e((string)($action==='payment_correct'?0:($person['refund_due']>0?$person['refund_due']:$person['net_received'])))?>" required></label>
        <label><input type="checkbox" name="<?=$action==='payment_refund'?'refund_completed':'correction_ack'?>" value="1" required> <?=e(gt($action==='payment_refund'?'payment_refund_ack':'payment_correction_ack'))?></label><?php endif;?>
        <label><?=e(gt('payment_reason'))?><input name="reason" required minlength="3" maxlength="240"></label><button class="btn danger block" type="submit"><?=e(gt($action))?></button>
      </form>
    <?php endforeach;?>
    <?php if(!empty($person['can_correct_refunded'])):?><form method="post" action="<?=e(base_url('group/action.php'))?>" class="hyg-payment-form" data-confirm="<?=e(gt('payment_correct_refund'))?>">
      <?=hyg_payment_action_fields($room,'payment_correct',(int)$person['id'])?><input type="hidden" name="correction_target" value="refunded">
      <label><?=e(gt('payment_correct_refund_amount'))?><input type="number" name="amount" min="0" max="<?=e((string)$person['refunded_amount'])?>" step="0.01" value="0" required></label>
      <label><input type="checkbox" name="correction_ack" value="1" required> <?=e(gt('payment_correction_refund_ack'))?></label>
      <label><?=e(gt('payment_reason'))?><input name="reason" required minlength="3" maxlength="240"></label><button class="btn danger block" type="submit"><?=e(gt('payment_correct_refund'))?></button>
    </form><?php endif;?>
    </details><?php endif;?>
    <?php return ob_get_clean();
}
function hyg_render_payment_history(array $events): string {
    if(!$events)return '';ob_start(); ?>
    <details class="hyg-payment-history hyg-card"><summary><?=e(gt('payment_history'))?></summary><p class="hyg-muted"><?=e(gt('payment_history_hint'))?></p>
      <?php foreach($events as $event):?><article><strong><?=e(gt('event_'.$event['event_type']))?> · <?=e($event['member_name'])?></strong><p><?=e($event['created_at'])?> UTC · <?=e(currency())?><?=money($event['amount'])?></p><small><?=e($event['reference'])?></small><?php if($event['reason']!==''):?><p><?=e($event['reason'])?></p><?php endif;?></article><?php endforeach;?>
    </details>
    <?php return ob_get_clean();
}
