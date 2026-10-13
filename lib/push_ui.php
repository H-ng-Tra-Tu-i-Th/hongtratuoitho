<?php
function hqp_ui_config(string $app): void {
    $mobile=$app==='mobile';$prefix=$mobile?'mobile-admin/':'admin/';
    $sound=trim(setting('order_alert_sound',''));$source=setting('order_alert_sound_source',$sound!==''?'custom':'default');
    $config=['version'=>HQ_PUSH_VERSION,'app'=>$app,'lang'=>current_lang(),'publicKey'=>webpush_public_key(),
        'api'=>base_url($prefix.'push_subscription.php'),'worker'=>base_url($prefix.'sw.js?v='.($mobile?'1.1':'19.1')),
        'scope'=>base_url($prefix),'legacyScope'=>base_url(),'csrf'=>csrf_token(),
        'ordersApi'=>base_url($prefix.'api_new_orders.php'),'orderUrl'=>base_url($prefix.'order.php'),
        'loginUrl'=>base_url($prefix.'login.php'),'latest'=>latest_order_id(),
        'soundEnabled'=>setting('order_alert_enabled','1')==='1','soundUrl'=>($source==='custom'&&$sound!=='')?base_url('uploads/'.$sound):'',
        'mode'=>setting('order_alert_mode','seconds'),'duration'=>(int)setting('order_alert_duration','20')];
    echo '<link rel="stylesheet" href="'.e(base_url('assets/css/order-notifications.css?v=19.1')).'">';
    echo '<script type="application/json" id="hq-notification-config">'.json_encode($config,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';
    echo '<script defer src="'.e(base_url('assets/js/order-notifications.js?v=19.1')).'"></script>';
}
