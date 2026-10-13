<?php
require __DIR__.'/_common.php';
$returnOrder=max(0,(int)($_GET['return_order']??0)); $loginTarget=ma_url($returnOrder?'order.php?id='.$returnOrder:'index.php');
if(admin_logged_in()) redirect($loginTarget);
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $u=is_string($_POST['username']??null)?trim($_POST['username']):''; $p=is_string($_POST['password']??null)?$_POST['password']:'';
    if(haa_sign_in($u,$p)){ redirect($loginTarget); }
    $err=ma_t('login_error');
}
?><!doctype html><html lang="<?=e(html_lang())?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="<?=e(theme_primary_color())?>"><link rel="icon" href="<?=e(base_url('assets/icons/favicon.png?v=19'))?>"><link rel="apple-touch-icon" href="<?=e(base_url('assets/icons/apple-touch-icon.png?v=19'))?>"><link rel="manifest" href="<?=e(ma_url('manifest.webmanifest?v=1.1'))?>"><link rel="stylesheet" href="<?=e(ma_url('style.css?v=1.1'))?>"><title><?=e(ma_t('app_name'))?></title></head><body class="ma-login-bg <?=e(theme_body_class())?>"><main class="ma-login-card"><img class="ma-login-icon" src="<?=e(base_url('assets/icons/order-icon-192.png?v=19'))?>" alt=""><div class="ma-login-head"><h1><?=e(ma_t('app_name'))?></h1><?=ma_lang_switcher()?></div><?php if($err):?><div class="ma-error"><?=e($err)?></div><?php endif;?><form method="post" class="ma-form"><?=csrf_field()?><label><?=e(ma_t('username'))?><input name="username" autocomplete="username" required autofocus></label><label><?=e(ma_t('password'))?><input name="password" type="password" autocomplete="current-password" required></label><button class="ma-btn primary wide" type="submit"><?=e(ma_t('login'))?></button></form></main></body></html>
