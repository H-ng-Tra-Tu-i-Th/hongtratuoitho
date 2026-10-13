<?php
require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/../lib/layout.php';
require_once __DIR__.'/../lib/promotion_views.php';
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
$returnOrder=$_GET['return_order']??'';
$returnOrder=is_string($returnOrder)&&preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$returnOrder)?$returnOrder:'';
$member=null;$platformOrders=[];$loadFailed=false;
try{
    hym_schema();
    $member=hym_current_member();
    if($member){
        $memberId=(int)$member['id'];
        $platformOrders=hym_platform_orders($memberId);
    }
}catch(Throwable $error){$loadFailed=true;}
$rewardCoupons=[];$rewardBalance=0;$rewardsFailed=false;
if($member)try{$rewardCoupons=available_game_coupons();$rewardBalance=game_wallet_balance();}catch(Throwable $error){$rewardsFailed=true;}
$flash=$_SESSION['member_flash']??null;unset($_SESSION['member_flash']);
// Legacy sync submissions must not reveal upstream status in the customer interface.
if(is_array($flash)&&str_starts_with((string)($flash['key']??''),'member_sync_'))$flash=null;
$formatDate=static function($value,bool $dateOnly=false,bool $isUtc=true): string {
    $value=trim((string)$value);
    if($value===''||str_starts_with($value,'0000-00-00'))return mt('member_not_provided');
    try{
        $date=new DateTimeImmutable($value,new DateTimeZone('UTC'));
        if(!$dateOnly&&$isUtc)$date=$date->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
        return $date->format($dateOnly?'d/m/Y':'d/m/Y H:i');
    }catch(Throwable $error){return mt('member_not_provided');}
};
$GLOBALS['hym_member_page']=true;
front_header(mt('member_title'));
?>
<div class="hym-page">
  <a class="back" href="<?=e(base_url('index.php'))?>">← <?=e(mt('member_back_menu'))?></a>
  <header class="hym-page-head">
    <div><span class="hym-eyebrow"><?=e(mt('member_entry'))?></span><h1><?=e(mt('member_title'))?></h1><p><?=e(mt('member_intro'))?></p></div>
    <?php if($member):?><form method="post" action="<?=e(base_url('member/action.php'))?>"><?=csrf_field()?><input type="hidden" name="action" value="logout"><button type="submit" class="btn"><?=e(mt('member_logout'))?></button></form><?php endif;?>
  </header>
  <?php if(is_array($flash)&&!empty($flash['key'])):$flashType=in_array($flash['type']??'',['success','info','danger'],true)?$flash['type']:'danger';$flashText=mt((string)$flash['key']);if($flashText===(string)$flash['key'])$flashText=mt('member_save_failed');?>
    <div class="alert <?=e($flashType)?>" role="status"><?=e($flashText)?></div>
  <?php endif;?>
  <?php if($loadFailed):?><div class="alert danger" role="alert"><?=e(mt('member_load_failed'))?></div><?php endif;?>
  <?php if(!$member):?>
    <div class="hym-auth-single">
      <section class="hym-card" id="member-login"><h2><?=e(mt('member_login_title'))?></h2>
        <form class="hym-form" method="post" action="<?=e(base_url('member/action.php'))?>"><?=csrf_field()?><input type="hidden" name="action" value="login"><?php if($returnOrder!==''):?><input type="hidden" name="return_order" value="<?=e($returnOrder)?>"><?php endif;?>
          <label for="member-login-phone"><?=e(mt('member_phone'))?><input id="member-login-phone" type="tel" inputmode="tel" name="phone" required maxlength="32" autocomplete="username"></label>
          <label for="member-login-password"><?=e(mt('member_password'))?><input id="member-login-password" type="password" name="password" required maxlength="72" autocomplete="current-password"></label>
          <button class="btn primary block" type="submit"><?=e(mt('member_login'))?></button>
        </form>
        <a class="btn block hym-register-link" href="<?=e(base_url('member/register.php'))?>"><?=e(mt('member_register'))?></a>
      </section>
    </div>
  <?php else:?>
    <section class="hym-card" id="member-rewards"><h2><?=e(mt('member_rewards_title'))?></h2><p class="hym-source"><?=e(mt('member_rewards_source'))?></p><p class="hym-note"><?=e(mt('member_rewards_hint'))?></p>
      <div class="hym-reward-toolbar"><strong><?=e(mt('member_draw_balance',['chances'=>(int)$rewardBalance]))?></strong><?php if(minigame_enabled() && $rewardBalance>0):?><a class="btn" href="<?=e(base_url('minigame/'))?>"><?=e(mt('member_go_draw'))?></a><?php endif;?></div>
      <?php if($rewardsFailed):?><p class="hym-note" role="status"><?=e(mt('member_rewards_load_failed'))?></p><?php elseif(!$rewardCoupons):?><p class="hym-empty"><?=e(mt('member_rewards_empty'))?></p><?php else:?><div class="hym-reward-list">
        <?php foreach($rewardCoupons as $reward):?><article class="hym-reward"><div><span class="hym-label"><?=e(pt('reward_code'))?></span><code><?=e($reward['code'])?></code><strong><?=e($reward['coupon_type']==='game_reward'?hgr_label($reward):mt('member_reward_amount',['amount'=>currency().money($reward['amount'])]))?></strong><p class="hym-note"><?=e($reward['coupon_type']==='game_reward'?hgr_label($reward,'terms').' · '.hgr_text('expires').' '.hgr_expiry((string)($reward['expires_at']??'')):mt('member_reward_rule'))?></p></div><form method="post" action="<?=e(base_url('actions/apply_coupon.php'))?>"><?=csrf_field()?><input type="hidden" name="action" value="apply"><input type="hidden" name="promotion_mode" value="reward"><input type="hidden" name="coupon_code" value="<?=e($reward['code'])?>"><button class="btn primary" type="submit"><?=e(mt('member_reward_use'))?></button></form></article><?php endforeach;?>
      </div><?php endif;?>
    </section>
      <section class="hym-card" id="member-local-profile"><h2><?=e(mt('member_local_profile'))?></h2><p class="hym-source"><?=e(mt('member_local_source'))?></p><p class="hym-note"><?=e(mt('member_local_profile_hint'))?></p>
        <dl class="hym-facts"><div><dt><?=e(mt('member_account_phone'))?></dt><dd><?=e($member['phone'])?></dd></div></dl>
        <form class="hym-form" method="post" action="<?=e(base_url('member/action.php'))?>"><?=csrf_field()?><input type="hidden" name="action" value="save_profile">
          <label for="member-profile-name"><?=e(mt('member_display_name'))?><input id="member-profile-name" name="display_name" maxlength="100" autocomplete="name" value="<?=e($member['display_name']??'')?>"></label>
          <label for="member-profile-address"><?=e(mt('member_delivery_address'))?> <span class="hym-optional"><?=e(mt('member_optional'))?></span><textarea id="member-profile-address" name="delivery_address" maxlength="255" autocomplete="street-address" rows="3"><?=e($member['delivery_address']??'')?></textarea></label>
          <button class="btn primary" type="submit"><?=e(mt('member_save_profile'))?></button>
        </form>
      </section>
    <section class="hym-card" id="member-platform-orders"><h2><?=e(mt('member_platform_orders'))?></h2><p class="hym-source"><?=e(mt('member_platform_source'))?></p>
      <?php if(!$platformOrders):?><p class="hym-empty"><?=e(mt('member_platform_empty'))?></p><?php else:?><div class="hym-order-list">
        <?php foreach($platformOrders as $order):?><article class="hym-order"><div class="hym-record-head"><div><span class="hym-label"><?=e(mt('member_order_number'))?></span><h3><?=e($order['order_no'])?></h3></div><span class="hym-order-status"><?=e(status_label((string)$order['status']))?></span></div><dl class="hym-facts">
          <div><dt><?=e(mt('member_order_date'))?></dt><dd><?=e($formatDate($order['created_at'],false,false))?></dd></div>
          <div class="hym-record-amount"><dt><?=e(mt('member_order_amount'))?></dt><dd><?=e(currency())?><?=e(money($order['total_amount']))?></dd></div>
        </dl><?php if(htp_receipt((int)$order['id'])):?><a class="btn" href="<?=e(base_url('payment.php?order='.rawurlencode($order['order_no'])))?>"><?=e(htp('payment_link'))?></a><?php endif;?></article><?php endforeach;?>
      </div><?php endif;?>
    </section>
  <?php endif;?>
</div>
<?php front_footer(); ?>
