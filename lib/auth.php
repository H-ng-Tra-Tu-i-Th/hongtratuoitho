<?php
require_once __DIR__.'/admin_accounts.php';
require_once __DIR__.'/partner.php';
function admin_logged_in(): bool { return haa_current()!==null; }
function admin_is_operator(): bool {return (haa_current()['role']??'')==='operator';}
function admin_store_id(): ?int { $cur=haa_current(); return !empty($cur['store_id'])?(int)$cur['store_id']:null; }
function admin_order_allowed(int|array $order): bool {
 $storeId=admin_store_id();
 if($storeId===null)return true;
 if(is_int($order)){
  $st=db()->prepare('SELECT dispatch_store_id FROM orders WHERE id=?');
  $st->execute([$order]);
  $row=$st->fetch();
  if(!$row)return false;
  return (int)($row['dispatch_store_id']??0)===$storeId;
 }
 return (int)($order['dispatch_store_id']??0)===$storeId;
}
function require_order_access(int|array $order): void {
 if(!admin_order_allowed($order)){
  http_response_code(403);
  header('Cache-Control: private, no-store');
  echo function_exists('haa_t')?e(haa_t('forbidden')):'Forbidden';
  exit;
 }
}
function haa_order_route_allowed(string $path): bool {
 if(!preg_match('~(?:^|/)(admin|mobile-admin)/([^/]+)$~',$path,$m))return true;
 return in_array($m[2],['index.php','order.php','api_new_orders.php','push_subscription.php','login.php','logout.php'],true);
}
function haa_enforce_route(): void {
 if(admin_is_operator()&&!haa_order_route_allowed((string)($_SERVER['SCRIPT_NAME']??''))){http_response_code(403);header('Cache-Control: private, no-store');echo e(haa_t('order_only'));exit;}
}
function require_admin(): void {
 if(!admin_logged_in()){$id=basename($_SERVER['SCRIPT_NAME']??'')==='order.php'?max(0,(int)($_GET['id']??0)):0;redirect(base_url('admin/login.php'.($id?'?return_order='.$id:'')));}
 haa_enforce_route();
}
