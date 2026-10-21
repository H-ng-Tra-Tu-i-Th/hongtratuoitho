<?php
require_once __DIR__.'/push_ui.php';
require_once __DIR__.'/dispatch_i18n.php';
require_once __DIR__.'/developer_docs.php';
function front_header(string $title=''): void {
    $store=store_name();
    $frontMember=function_exists('hym_current_member')?hym_current_member():null;
    $memberName=trim((string)($frontMember['display_name']??''));
    $memberLabel=$memberName!==''?mt('member_entry_named',['name'=>$memberName]):mt('member_entry');
    $assetVersion='19.15.0';
    $gameBalance=minigame_enabled()?game_wallet_balance():0;
    $groupContext=$GLOBALS['hyg_context']??null;
    $frontCartUrl=$groupContext?hyg_room_url($groupContext['room']['public_id']):base_url('cart.php');
    $frontCartCount=cart_count();
    if($groupContext){$frontCartCount=0;foreach(hyg_cart($groupContext['room'],false) as $row)$frontCartCount+=(int)$row['qty'];}

    ?><!doctype html><html lang="<?=e(html_lang())?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="<?=e(theme_primary_color())?>"><link rel="icon" type="image/png" href="<?=e(base_url('assets/icons/favicon.png?v=19'))?>"><link rel="apple-touch-icon" href="<?=e(base_url('assets/icons/apple-touch-icon.png?v=19'))?>"><link rel="manifest" href="<?=e(base_url('manifest.webmanifest?v=19'))?>"><link rel="stylesheet" href="<?=e(base_url('assets/css/app.css?v='.$assetVersion))?>"><link rel="stylesheet" href="<?=e(base_url('assets/css/group-order.css?v=19.8.1'))?>"><link rel="stylesheet" href="<?=e(base_url('assets/css/member.css?v=19.8.0'))?>"><title><?=e($title ? $title.'｜'.$store : $store)?></title></head><body class="front-body <?=e(theme_body_class())?>"><header class="topbar"><div class="topbar-inner"><a class="brand" href="<?=e(base_url('index.php'))?>"><span class="brand-mark brand-mark-image" aria-hidden="true"><img src="<?=e(base_url('assets/icons/order-icon-192.png?v=19'))?>" alt=""></span><span class="brand-text"><?=e($store)?></span></a><div class="topbar-actions"><?=language_switcher()?><?php if(minigame_enabled() && $gameBalance>0): ?><a class="game-link" href="<?=e(base_url('minigame/'))?>" aria-label="<?=e(t('game_center'))?>"><span aria-hidden="true">🎮</span><span class="game-link-label"><?=e(t('game_center'))?></span><?php if($gameBalance>0): ?><b><?=$gameBalance?></b><?php endif; ?></a><?php endif; ?><a class="member-link" href="<?=e(base_url('member/index.php'))?>" aria-label="<?=e($memberLabel)?>"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg><span><?=e($memberLabel)?></span></a><a class="cart-link" href="<?=e($frontCartUrl)?>" aria-label="<?=e($groupContext?gt('cart_short'):t('cart'))?>"><svg class="cart-symbol" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l1.8 9.1a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 1.9-1.4L20 8H7"/><circle cx="9" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/></svg><span class="cart-label"><?=e($groupContext?gt('cart_short'):t('cart'))?></span><span class="cart-count"><?=$frontCartCount?></span></a></div></div></header><main class="container"><?php
    if($groupContext&&empty($GLOBALS['hyg_hide_mode'])){?>
    <section class="hyg-mode"><div><strong><?=e(gt('mode'))?> · <?=e($groupContext['room']['title'])?></strong><small><?=e(gt('nickname'))?>：<?=e($groupContext['member']['nickname'])?></small></div><div class="hyg-mode-actions"><a class="btn" href="<?=e($frontCartUrl)?>"><?=e(gt('back_room'))?></a><a href="<?=e(base_url('index.php'))?>"><?=e(gt('personal'))?></a></div></section>
    <?php }

}
function front_footer(): void {
    $flash=(string)($_SESSION['cart_flash'] ?? '');
    unset($_SESSION['cart_flash']);
    $count=cart_count();
    $groupContext=$GLOBALS['hyg_context']??null;
    $floatingUrl=$groupContext?hyg_room_url($groupContext['room']['public_id']):base_url('cart.php');
    if($groupContext){$count=0;foreach(hyg_cart($groupContext['room'],false) as $row)$count+=(int)$row['qty'];}

    $page=basename($_SERVER['SCRIPT_NAME'] ?? '');
    $showFloating=empty($GLOBALS['hym_member_page']) && empty($GLOBALS['hyg_hide_mode']) && ($count>0||$groupContext) && in_array($page,['index.php','product.php','topping.php'],true);
    ?></main>
    <?php if($flash!==''): ?><div class="cart-toast" role="status" aria-live="polite"><span class="cart-toast-check" aria-hidden="true">✓</span><span><?=e($flash)?></span></div><?php endif; ?>
    <?php if($showFloating): ?><a class="floating-cart" href="<?=e($floatingUrl)?>" aria-label="<?=e(t('cart'))?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l1.8 9.1a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 1.9-1.4L20 8H7"/><circle cx="9" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/></svg><span class="floating-cart-count"><?=$count?></span></a><?php endif; ?>
    <footer class="footer"><?=e(t('online_order'))?></footer><script src="<?=e(base_url('assets/js/app.js?v=19.15.1'))?>"></script><?php if(!empty($GLOBALS['hyg_qr_page'])): ?><script src="<?=e(base_url('assets/vendor/huiyi-qrcode.js?v=19.8.0'))?>"></script><script src="<?=e(base_url('assets/js/group-qr.js?v=19.8.0'))?>"></script><?php endif; ?><script src="<?=e(base_url('assets/js/group-order.js?v=19.8.3'))?>"></script><script src="<?=e(base_url('assets/js/promotion.js?v=19.8.0'))?>"></script><script src="<?=e(base_url('assets/js/game-entry.js?v=19.9.1'))?>"></script></body></html><?php }
function admin_nav_active(string ...$files): string {
    return in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), $files, true) ? ' active' : '';
}

function admin_header(string $title=''): void {
 require_once __DIR__.'/admin_shell.php';
 $title=$title?:t('hq_admin');$latest=latest_order_id();$alertEnabled=setting('order_alert_enabled','1')==='1'?'1':'0';
 $alertMode=setting('order_alert_mode','seconds');if(!in_array($alertMode,['seconds','minutes','until_accepted'],true))$alertMode='seconds';
 $duration=(int)setting('order_alert_duration','20');$duration=$alertMode==='minutes'?max(1,min(120,$duration)):max(3,min(3600,$duration));
 $sound=trim(setting('order_alert_sound',''));$soundSource=setting('order_alert_sound_source',$sound!==''?'custom':'default');if($soundSource==='custom'&&$sound==='')$soundSource='default';
 $customSoundUrl=$sound!==''?base_url('uploads/'.$sound):'';$soundUrl=$soundSource==='custom'?$customSoundUrl:'';
 ?><!doctype html><html lang="<?=e(html_lang())?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="<?=e(theme_primary_color())?>"><link rel="icon" type="image/png" href="<?=e(base_url('assets/icons/favicon.png?v=19'))?>"><link rel="apple-touch-icon" href="<?=e(base_url('assets/icons/apple-touch-icon.png?v=19'))?>"><link rel="manifest" href="<?=e(base_url('admin/manifest.webmanifest?v=19.1'))?>"><link rel="stylesheet" href="<?=e(base_url('assets/css/app.css?v=19.15.0'))?>"><link rel="stylesheet" href="<?=e(base_url('assets/css/group-order.css?v=19.8.1'))?>"><link rel="stylesheet" href="<?=e(base_url('assets/css/dispatch.css?v=19.8.0'))?>"><link rel="stylesheet" href="<?=e(base_url('assets/css/admin-shell.css?v=19.14.3'))?>"><link rel="stylesheet" href="<?=e(base_url('assets/css/admin-ui.css?v=19.14.4'))?>"><link rel="stylesheet" href="<?=e(base_url('assets/css/admin-grid.css?v=19.14.4'))?>"><link rel="stylesheet" href="<?=e(base_url('assets/css/admin-tables.css?v=19.14.5'))?>"><title><?=e($title)?></title></head>
 <body class="admin-body admin-shell <?=e(theme_body_class())?>" data-order-alert-enabled="<?=e($alertEnabled)?>" data-order-alert-latest="<?=$latest?>" data-order-alert-url="<?=e(base_url('admin/api_new_orders.php'))?>" data-order-alert-sound="<?=e($soundUrl)?>" data-order-alert-custom-sound="<?=e($customSoundUrl)?>" data-order-alert-sound-source="<?=e($soundSource)?>" data-order-alert-mode="<?=e($alertMode)?>" data-order-alert-duration="<?=$duration?>" data-order-view-base="<?=e(base_url('admin/order.php?id='))?>" data-push-public-key="<?=e(webpush_public_key())?>" data-push-subscribe-url="<?=e(base_url('admin/push_subscription.php'))?>">
 <?php has_sidebar($title); ?>
 <div class="order-alert-toast" id="order-alert-toast" hidden><div><span class="eyebrow"><?=e(t('new_order_alert'))?></span><strong data-alert-title></strong><small data-alert-meta></small></div><div class="order-alert-actions"><a class="btn small primary" data-alert-view href="#"><?=e(t('view_order'))?></a><button class="btn small" type="button" data-alert-stop><?=e(t('stop_sound'))?></button></div></div>
 <main class="admin-container" id="admin-main"><div class="page-title"><div><span class="page-kicker"><?=e(t('headquarters'))?></span><h1><?=e($title)?></h1></div><a class="view-store-link" href="<?=e(base_url('index.php'))?>" target="_blank" rel="noopener"><?=e(t('view_store'))?></a></div><?php
}
function admin_footer(): void { ?></main><script src="<?=e(base_url('assets/js/app.js?v=19.15.1'))?>"></script><script src="<?=e(base_url('assets/js/admin-shell.js?v=19.14.5'))?>"></script><script src="<?=e(base_url('assets/js/admin-tables.js?v=19.14.5'))?>" defer></script><?php hqp_ui_config('admin'); ?></body></html><?php }
