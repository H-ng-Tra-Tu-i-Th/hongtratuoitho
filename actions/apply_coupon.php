<?php
require __DIR__.'/../lib/bootstrap.php';
require_once __DIR__.'/../lib/promotion_views.php';
if($_SERVER['REQUEST_METHOD']!=='POST') exit;
verify_csrf();
$action=$_POST['action']??'apply';
if($action==='remove'){
    clear_coupon();
    $_SESSION['coupon_message']=t('coupon_removed');
    redirect(base_url('cart.php'));
}
try{
    if($action!=='apply')throw new RuntimeException('promotion_invalid');
    $choice=promotion_selection_from_post($_POST);
    $_SESSION['promotion_mode']=$choice['mode'];
    if($choice['code']!=='')$_SESSION['coupon_code']=$choice['code'];else unset($_SESSION['coupon_code']);
    $_SESSION['coupon_message']=pt('selection_saved');
}catch(Throwable $e){
    $_SESSION['coupon_error']=t('coupon_invalid');
}
redirect(base_url('cart.php'));
