<?php
// Read-only room polling avoids running legacy migrations and personal-cart refresh on every 5-second poll.
require_once __DIR__.'/../config.php';
foreach(['db','helpers','i18n','csrf','auth','v170','v175','group_order','member','member_rewards','promotions','game_rewards','transfer_payments'] as $module)require_once __DIR__.'/'.$module.'.php';
init_language();
// An already-open room can make polling the first request after an update.
if(setting('member_rewards_schema_version','')!=='19.6.0'||setting('promotion_schema_version','')!=='19.6.0'||setting('game_rewards_schema_version','')!=='19.8.0'||setting('transfer_payment_schema_version','')!=='19.15.0'||setting('group_payment_audit_version','')!=='19.15.0'||setting('order_workflow_schema','')!=='19.15.0')require_once __DIR__.'/bootstrap.php';
else hyg_schema();
