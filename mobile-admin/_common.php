<?php
require_once __DIR__.'/../lib/bootstrap.php';
require_once __DIR__.'/../lib/push_ui.php';
require_once __DIR__.'/../lib/dispatch_i18n.php';

function ma_url(string $path=''): string { return base_url('mobile-admin/'.ltrim($path,'/')); }
function ma_require_admin(): void { if(!admin_logged_in()) { $id=basename($_SERVER['SCRIPT_NAME']??'')==='order.php'?max(0,(int)($_GET['id']??0)):0; redirect(ma_url('login.php'.($id?'?return_order='.$id:''))); } }
function ma_t(string $key): string {
    $lang=current_lang();
    $map=[
        'vi'=>[
            'main_menu'=>'Menu quản trị','app_name'=>'Hồng Trà Tuổi Thơ · Nhận đơn','orders'=>'Đơn hàng','all'=>'Tất cả','new'=>'Đơn mới','accepted'=>'Đã nhận','done'=>'Hoàn tất','cancelled'=>'Đã hủy',
            'install'=>'Cài đặt App','push'=>'Bật thông báo','push_on'=>'Đã bật thông báo','sound'=>'Bật chuông','sound_on'=>'Chuông đã bật','logout'=>'Đăng xuất','view'=>'Xem đơn','accept'=>'Nhận đơn','back'=>'Quay lại',
            'customer'=>'Khách hàng','phone'=>'Điện thoại','address'=>'Địa chỉ','payment'=>'Thanh toán','note'=>'Ghi chú','time'=>'Thời gian','status'=>'Trạng thái','update'=>'Cập nhật trạng thái','details'=>'Chi tiết đơn hàng','qty'=>'Số lượng','price'=>'Thành tiền','total'=>'Tổng cộng','subtotal'=>'Tạm tính','discount'=>'Giảm giá','toppings'=>'Topping','ice'=>'Đá','sugar'=>'Đường','size'=>'Kích cỡ',
            'new_alert'=>'Có đơn hàng mới','stop'=>'Dừng chuông','notification_help'=>'Cho phép thông báo để nhận đơn ngay cả khi ứng dụng đang ở nền.','sound_help'=>'Chạm một lần để cho phép trình duyệt phát âm báo đơn mới.','install_ios'=>'Trên iPhone/iPad: mở menu Chia sẻ → Thêm vào Màn hình chính.','login'=>'Đăng nhập','username'=>'Tài khoản','password'=>'Mật khẩu','login_error'=>'Sai tài khoản hoặc mật khẩu.','empty'=>'Chưa có đơn hàng.','invoice'=>'Hóa đơn','yes'=>'Có','no'=>'Không','refresh'=>'Làm mới','quick_accept'=>'Nhận đơn ngay','installed'=>'Đã cài đặt','install_unavailable'=>'Hãy dùng chức năng “Thêm vào màn hình chính” của trình duyệt.'
        ],
        'en'=>[
            'main_menu'=>'Admin menu','app_name'=>'Hồng Trà Tuổi Thơ · Order Desk','orders'=>'Orders','all'=>'All','new'=>'New','accepted'=>'Accepted','done'=>'Completed','cancelled'=>'Cancelled',
            'install'=>'Install App','push'=>'Enable notifications','push_on'=>'Notifications enabled','sound'=>'Enable ring','sound_on'=>'Ring enabled','logout'=>'Log out','view'=>'View','accept'=>'Accept','back'=>'Back',
            'customer'=>'Customer','phone'=>'Phone','address'=>'Address','payment'=>'Payment','note'=>'Note','time'=>'Time','status'=>'Status','update'=>'Update status','details'=>'Order details','qty'=>'Qty','price'=>'Amount','total'=>'Total','subtotal'=>'Subtotal','discount'=>'Discount','toppings'=>'Toppings','ice'=>'Ice','sugar'=>'Sugar','size'=>'Size',
            'new_alert'=>'New order received','stop'=>'Stop ring','notification_help'=>'Allow notifications to receive new-order alerts while the app is in the background.','sound_help'=>'Tap once to allow the browser to play the new-order ring.','install_ios'=>'On iPhone/iPad: Share → Add to Home Screen.','login'=>'Log in','username'=>'Username','password'=>'Password','login_error'=>'Invalid username or password.','empty'=>'No orders yet.','invoice'=>'Invoice','yes'=>'Yes','no'=>'No','refresh'=>'Refresh','quick_accept'=>'Accept now','installed'=>'Installed','install_unavailable'=>'Use your browser’s “Add to Home Screen” option.'
        ],
        'zh'=>[
            'main_menu'=>'後台主目錄','app_name'=>'Hồng Trà Tuổi Thơ · 手機接單','orders'=>'訂單','all'=>'全部','new'=>'新訂單','accepted'=>'已接單','done'=>'已完成','cancelled'=>'已取消',
            'install'=>'安裝手機 App','push'=>'啟用推播','push_on'=>'推播已啟用','sound'=>'啟用鈴聲','sound_on'=>'鈴聲已啟用','logout'=>'登出','view'=>'查看訂單','accept'=>'接單','back'=>'返回',
            'customer'=>'客戶','phone'=>'電話','address'=>'地址','payment'=>'付款方式','note'=>'訂單備註','time'=>'時間','status'=>'狀態','update'=>'更新狀態','details'=>'訂單明細','qty'=>'數量','price'=>'金額','total'=>'總金額','subtotal'=>'小計','discount'=>'優惠折抵','toppings'=>'配料','ice'=>'冰量','sugar'=>'甜度','size'=>'杯型',
            'new_alert'=>'收到新訂單','stop'=>'停止鈴聲','notification_help'=>'允許通知後，即使 App 在背景也能收到新訂單系統通知。','sound_help'=>'點一次啟用，讓瀏覽器允許播放新訂單鈴聲。','install_ios'=>'iPhone/iPad：分享 → 加入主畫面。','login'=>'登入','username'=>'帳號','password'=>'密碼','login_error'=>'帳號或密碼錯誤。','empty'=>'目前沒有訂單。','invoice'=>'發票','yes'=>'是','no'=>'否','refresh'=>'重新整理','quick_accept'=>'立即接單','installed'=>'已安裝','install_unavailable'=>'請使用瀏覽器的「加入主畫面」功能。'
        ]
    ];
    return $map[$lang][$key] ?? $map['vi'][$key] ?? $key;
}
function ma_status_label(string $s): string { return ma_t(in_array($s,['new','accepted','done','cancelled'],true)?$s:'status'); }
function ma_lang_switcher(): string {
    $q=$_GET; unset($q['lang']); $path=basename($_SERVER['PHP_SELF']??'index.php');
    $out='<div class="ma-langs">';
    foreach(['vi'=>'VI','en'=>'EN','zh'=>'中'] as $lang=>$label){ $q['lang']=$lang; $href=$path.'?'.http_build_query($q); $out.='<a class="'.(current_lang()===$lang?'active':'').'" href="'.e($href).'">'.e($label).'</a>'; }
    return $out.'</div>';
}
function ma_sound_settings(): array {
    $enabled=setting('order_alert_enabled','1')==='1';
    $mode=setting('order_alert_mode','seconds'); if(!in_array($mode,['seconds','minutes','until_accepted'],true))$mode='seconds';
    $duration=(int)setting('order_alert_duration','20');
    if($mode==='minutes')$duration=max(1,min(120,$duration)); else $duration=max(3,min(3600,$duration));
    $sound=trim(setting('order_alert_sound','')); $source=setting('order_alert_sound_source',$sound!==''?'custom':'default');
    if($source==='custom'&&$sound==='')$source='default';
    return ['enabled'=>$enabled,'mode'=>$mode,'duration'=>$duration,'sound_url'=>($source==='custom'&&$sound!=='')?base_url('uploads/'.$sound):''];
}
function ma_header(string $title=''): void {
    $s=ma_sound_settings(); $latest=latest_order_id(); $primary=theme_primary_color();
    ?><!doctype html><html lang="<?=e(html_lang())?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="<?=e($primary)?>"><link rel="icon" type="image/png" href="<?=e(base_url('assets/icons/favicon.png?v=19'))?>"><link rel="apple-touch-icon" href="<?=e(base_url('assets/icons/apple-touch-icon.png?v=19'))?>"><link rel="manifest" href="<?=e(ma_url('manifest.webmanifest?v=1.1'))?>"><link rel="stylesheet" href="<?=e(ma_url('style.css?v=19.7.0'))?>"><link rel="stylesheet" href="<?=e(base_url('assets/css/group-order.css?v=19.7.0'))?>"><link rel="stylesheet" href="<?=e(base_url('assets/css/dispatch.css?v=19.7.0'))?>"><link rel="stylesheet" href="<?=e(base_url('assets/css/admin-ui.css?v=19.14.5'))?>"><link rel="stylesheet" href="<?=e(base_url('assets/css/admin-grid.css?v=19.14.4'))?>"><link rel="stylesheet" href="<?=e(base_url('assets/css/admin-tables.css?v=19.14.6'))?>"><link rel="stylesheet" href="<?=e(base_url('assets/css/payment-flow.css?v=19.15.0'))?>"><title><?=e($title?:ma_t('app_name'))?></title></head>
    <body class="admin-mobile <?=e(theme_body_class())?>" data-alert-enabled="<?=$s['enabled']?'1':'0'?>" data-alert-latest="<?=$latest?>" data-alert-url="<?=e(ma_url('api_new_orders.php'))?>" data-alert-sound="<?=e($s['sound_url'])?>" data-alert-mode="<?=e($s['mode'])?>" data-alert-duration="<?=$s['duration']?>" data-order-url="<?=e(ma_url('order.php?id='))?>" data-push-key="<?=e(webpush_public_key())?>" data-push-url="<?=e(ma_url('push_subscription.php'))?>">
    <header class="ma-top"><a class="ma-brand" href="<?=e(ma_url('index.php'))?>"><img src="<?=e(base_url('assets/icons/order-icon-192.png?v=19'))?>" alt=""><span><?=e(ma_t('app_name'))?></span></a><div class="ma-top-actions"><?=ma_lang_switcher()?><a class="ma-logout" href="<?=e(ma_url('logout.php'))?>"><?=e(ma_t('logout'))?></a></div></header>
    <div class="ma-toolbar"><a class="ma-tool ma-home" href="<?=e(base_url('admin/index.php#admin-sidebar'))?>"><span aria-hidden="true">←</span> <?=e(ma_t('main_menu'))?></a><?php if(!admin_is_operator()): ?><a class="ma-tool hds-nav" href="<?=e(base_url('admin/stores.php'))?>"><?=e(dt('stores'))?></a><?php endif; ?><?php if(haa_owner()): ?><a class="ma-tool" href="<?=e(base_url('admin/accounts.php'))?>"><?=e(haa_t('accounts'))?></a><?php endif; ?><button type="button" id="ma-install" class="ma-tool">⬇ <?=e(ma_t('install'))?></button><button type="button" id="ma-push" class="ma-tool">📲 <span><?=e(ma_t('push'))?></span></button><button type="button" id="ma-sound" class="ma-tool">🔔 <span><?=e(ma_t('sound'))?></span></button></div>
    <div class="ma-alert" id="ma-alert" hidden><div><small><?=e(ma_t('new_alert'))?></small><strong data-alert-title></strong><span data-alert-meta></span></div><div class="ma-alert-actions"><a data-alert-view class="ma-btn primary" href="#"><?=e(ma_t('view'))?></a><button data-alert-stop class="ma-btn" type="button"><?=e(ma_t('stop'))?></button></div></div>
    <main class="ma-main"><?php
}
function ma_footer(): void { ?><footer class="ma-footer">Mobile Order Desk · v1.2 · App v19.15.0 / Push Core v19.1</footer></main><script src="<?=e(ma_url('app.js?v=1.1'))?>"></script><?php hqp_ui_config('mobile'); ?></body></html><?php }
