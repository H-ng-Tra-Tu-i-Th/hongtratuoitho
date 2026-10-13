<?php
require __DIR__.'/../lib/bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit;}
try{
    hyg_headers();hyg_schema();hyg_csrf();$action=(string)($_POST['action']??'');
    if($action==='create'){$account=htp_group_input($_POST,$_FILES);$r=hyg_create($_POST['title']??'',$_POST['nickname']??'',!empty($_POST['copy_cart']),(string)($_POST['request_key']??''),$account);}
    elseif($action==='join'){$r=hyg_join((string)($_POST['invite']??''),$_POST['nickname']??'');}
    else{$id=hyg_code($_POST['room_id']??'');hyg_mutate($id,$action,$_POST);$r=hyg_room($id);if(in_array($action,['payment_reject','payment_refund','payment_correct','payment_extend'],true))$_SESSION['hyg_message']=gt('payment_controls_saved');}
    header('Location: '.hyg_room_url($r['public_id']),true,303);exit;
}catch(Throwable $e){
    if($e instanceof TransferPaymentError){$_SESSION['hyg_message']=htp_error($e);header('Location: '.base_url('group/'),true,303);exit;}
    if($e instanceof GroupOrderError){
        $_SESSION['hyg_message']=gt($e->getMessage());$return=base_url('group/');
        try{if(!empty($_POST['room_id'])){$r=hyg_room(hyg_code($_POST['room_id']));hyg_member($r);$return=hyg_room_url($r['public_id']);}elseif(!empty($_POST['invite'])&&preg_match('/^[a-f0-9]{64}$/D',(string)$_POST['invite']))$return=base_url('group/?invite='.$_POST['invite']);}catch(Throwable $ignored){}
        header('Location: '.$return,true,303);exit;
    }
    hyg_error_page($e);
}
