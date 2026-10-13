<?php
// A one-event capability acknowledges display processing only. It cannot view or modify orders.
require __DIR__.'/lib/bootstrap.php';require_once __DIR__.'/lib/push_api.php';
session_write_close();
if($_SERVER['REQUEST_METHOD']!=='POST')hqp_json(['ok'=>false],405);
$d=json_decode((string)file_get_contents('php://input',false,null,0,2048),true);
$id=(int)($d['job_id']??0);$token=(string)($d['token']??'');
if($id<1||!preg_match('/^[a-f0-9]{48}$/D',$token))hqp_json(['ok'=>false],400);
try{
    $q=db()->prepare('SELECT subscription_id,receipt_hash FROM push_delivery_jobs WHERE id=?');$q->execute([$id]);$j=$q->fetch();
    if(!$j||!hash_equals($j['receipt_hash'],hash('sha256',$token)))hqp_json(['ok'=>false],403);
    db()->prepare('UPDATE push_delivery_jobs SET received_at=COALESCE(received_at,NOW()) WHERE id=?')->execute([$id]);
    db()->prepare('UPDATE push_subscriptions SET last_received_at=NOW() WHERE id=?')->execute([$j['subscription_id']]);
    hqp_json(['ok'=>true]);
}catch(Throwable $e){hqp_log('receipt_error',$e);hqp_json(['ok'=>false],500);}
