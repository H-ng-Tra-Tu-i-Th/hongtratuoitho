<?php
require __DIR__.'/lib/bootstrap.php';
header('Cache-Control: private, no-store, max-age=0');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');header('X-Robots-Tag: noindex, nofollow');header("Content-Security-Policy: default-src 'none'; sandbox");
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){http_response_code(405);exit;}
try{
    $type=$_GET['type']??null;$row=null;$staff=false;
    if(!empty($_SESSION['admin_id'])){hds_staff((int)$_SESSION['admin_id']);$staff=true;}
    if($type==='group'){
        $r=hyg_room(hyg_code($_GET['room']??''));hyg_member($r);
        $expired=$r['status']==='open'&&strtotime($r['expires_at'].' UTC')<=time();
        if($r['status']==='locked')$expired=!hyg_room_payment_active($r,hyg_all('SELECT * FROM group_members WHERE room_id=?',[$r['id']]));
        if(in_array($r['status'],['cancelled','expired'],true)||$expired)htp_fail('forbidden');
        $row=hyg_one('SELECT qr_data,qr_mime FROM transfer_group_accounts WHERE room_id=?',[$r['id']]);
    }elseif($type==='store'&&$staff&&isset($_GET['store'])){
        if(admin_is_operator())htp_fail('forbidden');
        $raw=$_GET['store'];if(!is_string($raw)||!preg_match('/^\d{1,10}$/D',$raw)||(int)$raw<1)htp_fail('forbidden');
        $row=hyg_one('SELECT qr_data,qr_mime FROM transfer_store_accounts WHERE store_id=?',[(int)$raw]);
    }elseif(in_array($type,['store','receipt'],true)){
        $no=htp_text($_GET['order']??'',80);
        $o=$staff?hyg_one('SELECT * FROM orders WHERE order_no=?',[$no]):htp_customer_order($no);if(!$o)htp_fail('forbidden');
        if($type==='receipt')$row=hyg_one('SELECT qr_data,qr_mime FROM transfer_order_receipts WHERE order_id=?',[$o['id']]);
        else{
            if(!(int)($o['dispatch_store_id']??0)||$o['status']==='cancelled')htp_fail('forbidden');
            $row=hyg_one('SELECT qr_data,qr_mime FROM transfer_store_accounts WHERE store_id=? AND enabled=1',[$o['dispatch_store_id']]);
        }
    }else htp_fail('forbidden');
    if(!$row||!is_string($row['qr_data'])||$row['qr_data']===''||!in_array($row['qr_mime'],['image/jpeg','image/png','image/webp'],true)){http_response_code(404);exit;}
    header('Content-Type: '.$row['qr_mime']);header('Content-Length: '.strlen($row['qr_data']));
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD')echo $row['qr_data'];
}catch(Throwable $e){http_response_code(403);}
