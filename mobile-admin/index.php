<?php
require __DIR__.'/_common.php'; ma_require_admin();
require_once __DIR__.'/../lib/dispatch_views.php';
require_once __DIR__.'/../lib/order_workflow.php';
hds_view_require_staff(ma_url('login.php'));
$error=$_SESSION['order_list_error']??'';unset($_SESSION['order_list_error']);
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $oid=(int)($_POST['order_id']??0); $action=(string)($_POST['action']??'');
    try{if($oid>0&&$action==='accept'){ require_order_access($oid); htow_change($oid,'accepted',$_POST['status_version']??null,pwf('quick_accept'),(int)$_SESSION['admin_id']);}else throw new InvalidArgumentException('invalid_action');}catch(Throwable $exception){$_SESSION['order_list_error']=htow_error($exception);}
    redirect(ma_url('index.php'.(!empty($_GET['status'])?'?status='.urlencode((string)$_GET['status']):'')));
}
$status=is_string($_GET['status']??'')?$_GET['status']??'':'';$orders=htp_list_query($_GET,120);$dispatchList=hds_order_dispatch_list($orders);
$counts=htow_status_counts();
ma_header(ma_t('orders')); ?>
<?php if($error):?><div class="hds-notice danger" role="alert"><?=e($error)?></div><?php endif;?>
<?=htp_render_list_filters(ma_url('index.php'),$_GET)?>
<section class="ma-dashboard-head"><div><span class="ma-kicker">ORDER DESK</span><h1><?=e(ma_t('orders'))?></h1></div><a class="ma-refresh" href="<?=e(ma_url('index.php'))?>">↻ <?=e(ma_t('refresh'))?></a></section>
<nav class="ma-status-tabs"><a class="<?=$status===''?'active':''?>" href="index.php"><b><?=array_sum($counts)?></b><span><?=e(ma_t('all'))?></span></a><a class="<?=$status==='new'?'active':''?>" href="?status=new"><b><?=$counts['new']?></b><span><?=e(ma_t('new'))?></span></a><a class="<?=$status==='accepted'?'active':''?>" href="?status=accepted"><b><?=$counts['accepted']?></b><span><?=e(ma_t('accepted'))?></span></a><a class="<?=$status==='done'?'active':''?>" href="?status=done"><b><?=$counts['done']?></b><span><?=e(ma_t('done'))?></span></a></nav>
<?php if(!$orders):?><div class="ma-empty"><?=e(ma_t('empty'))?></div><?php else:?><div class="ma-order-list"><?php foreach($orders as $o):?><article class="ma-order-card <?=$o['status']==='new'?'is-new':''?>"><a class="ma-order-link" href="<?=e(ma_url('order.php?id='.$o['id']))?>"><div class="ma-order-top"><strong>#<?=e($o['order_no'])?></strong><span class="ma-status <?=e($o['status'])?>"><?=e(ma_status_label($o['status']))?></span></div><div class="ma-order-customer"><b><?=e($o['customer_name'])?></b><span><?=e($o['phone'])?></span></div><div class="ma-order-meta"><span><?=e(format_datetime($o['created_at']))?></span><strong><?=e(currency())?><?=money($o['total_amount'])?></strong></div><p class="ma-order-meta"><span><?=e(pwf($o['financial_state']))?></span><strong><?=e(pwf('net_received'))?> <?=e(currency())?><?=money((float)($o['received_amount']??0)-(float)($o['refunded_amount']??0))?></strong></p><?=hds_render_order_badge($o,$dispatchList[(int)$o['id']]??[])?></a><?php if($o['status']==='new'):?><form method="post" class="ma-quick-form"><?=csrf_field()?><input type="hidden" name="order_id" value="<?=$o['id']?>"><input type="hidden" name="status_version" value="<?=(int)$o['status_version']?>"><button class="ma-btn accept wide" name="action" value="accept" type="submit">✓ <?=e(ma_t('quick_accept'))?></button></form><?php endif;?></article><?php endforeach;?></div><?php endif;?>
<?php ma_footer(); ?>
