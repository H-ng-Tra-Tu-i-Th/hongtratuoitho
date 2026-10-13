<?php
require __DIR__.'/lib/bootstrap.php';
require __DIR__.'/lib/layout.php';
header('Cache-Control: private, no-store, max-age=0');header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow');
$message='';$error='';$order=null;
$method=$_SERVER['REQUEST_METHOD']??'GET';if(!in_array($method,['GET','POST'],true)){http_response_code(405);header('Allow: GET, POST');exit;}
try{
    $order=htp_customer_order($_GET['order']??'');
    if($method==='POST'){
        $csrf=$_POST['csrf']??null;$expected=$_SESSION['_csrf']??null;
        if(!is_string($csrf)||!is_string($expected)||$expected===''||!hash_equals($expected,$csrf))htp_fail('csrf_expired');
        if(($_POST['action']??null)!=='report')htp_fail('changed');
        htp_report($order['order_no'],$_POST['account_code']??null,$_POST['payment_quote']??null);
        if(!is_array($_SESSION['transfer_payment_flash']??null))$_SESSION['transfer_payment_flash']=[];
        $_SESSION['transfer_payment_flash'][$order['order_no']]='report_saved';header('Location: '.base_url('payment.php?order='.rawurlencode($order['order_no'])),true,303);exit;
    }
    $flash=is_array($_SESSION['transfer_payment_flash']??null)?($_SESSION['transfer_payment_flash'][$order['order_no']]??null):null;
    if(is_array($_SESSION['transfer_payment_flash']??null))unset($_SESSION['transfer_payment_flash'][$order['order_no']]);if($flash==='report_saved')$message=htp($flash);
}catch(Throwable $e){$key=$e instanceof TransferPaymentError?$e->getMessage():'storage';$error=$key==='csrf_expired'?t('csrf_expired'):htp_error($e);http_response_code($key==='csrf_expired'?419:($key==='forbidden'?403:409));}
$paymentHtml='';if($order)try{$order=htp_customer_order($order['order_no']);$paymentHtml=htp_receipt((int)$order['id'])?htp_render_customer($order):'<p>'.e(htp('legacy')).'</p>';}catch(Throwable $e){$error=htp_error($e);}
front_header(htp('merchant_title'));
?>
<?php if($message):?><div class="alert success" role="status"><?=e($message)?></div><?php endif;?>
<?php if($error):?><div class="alert danger" role="alert"><?=e($error)?></div><?php endif;?>
<?php if($order):?><h1><?=e(htp('merchant_title'))?></h1><p><?=e(t('order_number'))?>：<strong><?=e($order['order_no'])?></strong></p><?=$paymentHtml?><?php else:$returnOrder=is_string($_GET['order']??null)&&preg_match('/^[A-Za-z0-9_-]{1,80}$/D',$_GET['order'])?$_GET['order']:'';?><p><?=e(htp_ux('session_lost'))?></p><a class="btn primary" href="<?=e(base_url('member/index.php'.($returnOrder!==''?'?return_order='.rawurlencode($returnOrder):'')))?>"><?=e(htp_ux('login'))?></a><?php endif;?>
<?=htp_render_session_orders()?>
<a class="btn" href="<?=e(base_url('index.php'))?>"><?=e(t('return_menu'))?></a>
<?php front_footer(); ?>
