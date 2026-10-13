<?php
// Scoped renewal capability: used by pushsubscriptionchange without a login cookie.
require __DIR__.'/lib/bootstrap.php';require_once __DIR__.'/lib/push_api.php';session_write_close();
if($_SERVER['REQUEST_METHOD']!=='POST')hqp_json(['ok'=>false],405);
$d=json_decode((string)file_get_contents('php://input',false,null,0,16384),true);
$id=(int)($d['device_id']??0);$token=(string)($d['token']??'');
if($id<1||!preg_match('/^[a-f0-9]{64}$/D',$token))hqp_json(['ok'=>false],400);
try{
    $q=db()->prepare('SELECT p.* FROM push_subscriptions p JOIN admins a ON a.id=p.owner_admin_id WHERE p.id=? AND p.active=1');$q->execute([$id]);$r=$q->fetch();
    if(!$r||!$r['renewal_hash']||!hash_equals($r['renewal_hash'],hash('sha256',$token)))hqp_json(['ok'=>false],403);
    if(!hash_equals((string)$r['vapid_hash'],hash('sha256',webpush_public_key())))hqp_json(['ok'=>false,'error'=>'key_changed'],409);
    [$endpoint,$p,$a]=hqp_device_input($d);
    db()->prepare('UPDATE push_subscriptions SET endpoint=?,endpoint_hash=?,p256dh=?,auth=?,last_error=NULL,last_error_at=NULL WHERE id=? AND active=1 AND renewal_hash=?')
        ->execute([$endpoint,hash('sha256',$endpoint),$p,$a,$id,hash('sha256',$token)]);
    hqp_json(['ok'=>true]);
}catch(Throwable $e){hqp_log('renew_error',$e);hqp_json(['ok'=>false,'error'=>'renew_failed'],400);}
