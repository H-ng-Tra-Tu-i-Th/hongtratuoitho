<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Exceptions/ExistingPendingOrderException.php';
if (!class_exists('ExistingPendingOrderException', false)) {
    class_alias(\Lib\Exceptions\ExistingPendingOrderException::class, 'ExistingPendingOrderException');
}
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/v170.php';
require_once __DIR__ . '/v175.php';
require_once __DIR__ . '/v179.php';
require_once __DIR__ . '/v184.php';
require_once __DIR__ . '/v185.php';
require_once __DIR__ . '/v186.php';
require_once __DIR__ . '/promotions.php';
init_language();
ensure_i18n_schema();
haa_schema();
haa_enforce_route();
ensure_v170_schema();
ensure_v175_schema();
ensure_v179_schema();
ensure_v184_branding();
ensure_v185_branding();
ensure_v186_schema();
refresh_cart_language();

require_once __DIR__ . '/group_order.php';
hyg_schema();

require_once __DIR__ . '/member.php';
require_once __DIR__ . '/member_sync.php';
require_once __DIR__ . '/member_i18n.php';
require_once __DIR__ . '/member_admin_i18n.php';
require_once __DIR__ . '/customer_checkout.php';
require_once __DIR__ . '/member_orders.php';
hym_schema();
hym_orders_schema();
require_once __DIR__ . '/member_rewards.php';
hym_reward_schema();
promotion_schema();
require_once __DIR__ . '/dispatch.php';
require_once __DIR__ . '/dispatch_i18n.php';
hds_schema();
require_once __DIR__ . '/transfer_payments.php';
htp_schema();
require_once __DIR__ . '/order_workflow.php';
htow_schema();
htow_auto_maintenance();
hds_maintenance();

require_once __DIR__ . '/game_rewards.php';
hgr_schema();
require_once __DIR__ . '/client_i18n.php';
require_once __DIR__ . '/sepay_i18n.php';
require_once __DIR__ . '/admin_i18n.php';
require_once __DIR__ . '/sepay.php';
