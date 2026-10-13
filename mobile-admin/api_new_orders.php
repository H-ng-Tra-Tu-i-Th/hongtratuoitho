<?php
require __DIR__.'/_common.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');
if(!admin_logged_in()){http_response_code(401);echo json_encode(['ok'=>false]);exit;}
$after=max(0,(int)($_GET['after']??0));$watch=max(0,(int)($_GET['watch']??0));
try{
    $st=db()->prepare("SELECT id,order_no,customer_name,total_amount,created_at FROM orders WHERE id>? AND status='new' ORDER BY id ASC LIMIT 20");$st->execute([$after]);$orders=$st->fetchAll();
    $watchStatus=null;if($watch>0){$ws=db()->prepare('SELECT status FROM orders WHERE id=?');$ws->execute([$watch]);$v=$ws->fetchColumn();$watchStatus=$v===false?'missing':(string)$v;}
    $pending=db()->query("SELECT id,order_no,customer_name,total_amount,created_at FROM (SELECT id,order_no,customer_name,total_amount,created_at FROM orders WHERE status='new' ORDER BY id DESC LIMIT 50) AS p ORDER BY id ASC")->fetchAll();
    $newCount=(int)db()->query("SELECT COUNT(*) FROM orders WHERE status='new'")->fetchColumn();
    echo json_encode(['ok'=>true,'latest'=>latest_order_id(),'orders'=>$orders,'pending_orders'=>$pending,'watch_status'=>$watchStatus,'new_count'=>$newCount],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false]);}
