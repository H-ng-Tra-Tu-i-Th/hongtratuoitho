<?php
/** Authenticated, CSRF-protected subscription and diagnostics API shared by both backends. */
function hqp_json(array $data,int $status=200): never {
    http_response_code($status);header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');
    echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}
function hqp_device_input(array $data): array {
    if(!is_string($data['endpoint']??null)||!is_array($data['keys']??null))throw new InvalidArgumentException('invalid_subscription');
    $endpoint=trim($data['endpoint']);$keys=$data['keys'];
    if(!hqp_allowed_endpoint($endpoint))throw new InvalidArgumentException('endpoint_not_allowed');
    if(!is_string($keys['p256dh']??null)||!is_string($keys['auth']??null))throw new InvalidArgumentException('invalid_subscription_keys');
    $p=$keys['p256dh'];$a=$keys['auth'];
    try{hqp_public_pem(b64url_decode($p));}catch(Throwable $e){throw new InvalidArgumentException('invalid_subscription_key');}
    if(strlen(b64url_decode($a))!==16)throw new InvalidArgumentException('invalid_auth_secret');
    return [$endpoint,$p,$a];
}
function hqp_recent_jobs(int $sid): array {
    $q=db()->prepare('SELECT id,kind,status,attempts,http_code,detail,created_at,accepted_at,received_at FROM push_delivery_jobs WHERE subscription_id=? ORDER BY id DESC LIMIT 8');
    $q->execute([$sid]);return $q->fetchAll(PDO::FETCH_ASSOC);
}
function hqp_api(string $app): never {
    if(!in_array($app,['admin','mobile'],true))hqp_json(['ok'=>false,'error'=>'invalid_app'],400);
    if(!admin_logged_in())hqp_json(['ok'=>false,'error'=>'login_required'],401);
    $admin=(int)$_SESSION['admin_id'];$q=db()->prepare('SELECT id FROM admins WHERE id=?');$q->execute([$admin]);if(!$q->fetchColumn())hqp_json(['ok'=>false,'error'=>'login_required'],401);
    if($_SERVER['REQUEST_METHOD']==='GET')hqp_json(['ok'=>true,'runtime'=>push_runtime_diagnostics(),'version'=>HQ_PUSH_VERSION]);
    if($_SERVER['REQUEST_METHOD']!=='POST')hqp_json(['ok'=>false,'error'=>'method'],405);
    $token=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');$sessionToken=(string)($_SESSION['_csrf']??'');
    if($sessionToken===''||$token===''||!hash_equals($sessionToken,$token))hqp_json(['ok'=>false,'error'=>'csrf_expired'],419);
    $origin=$_SERVER['HTTP_ORIGIN']??'';$expected=parse_url(base_url());
    if($origin!==''){
        $o=parse_url($origin);
        if(!$o||strtolower($o['host']??'')!==strtolower($expected['host']??'')||($o['scheme']??'')!==($expected['scheme']??'')||($o['port']??443)!==($expected['port']??443))hqp_json(['ok'=>false,'error'=>'origin_mismatch'],403);
    }
    if((int)($_SERVER['CONTENT_LENGTH']??0)>16384)hqp_json(['ok'=>false,'error'=>'request_too_large'],413);
    $data=json_decode((string)file_get_contents('php://input',false,null,0,16385),true);if(!is_array($data))hqp_json(['ok'=>false,'error'=>'invalid_json'],400);
    if(!empty($GLOBALS['hqp_schema_error']))hqp_json(['ok'=>false,'error'=>'push_schema_error'],503);
    $action=(string)($data['action']??'check');
    try{
        $endpoint=trim((string)($data['endpoint']??''));if($endpoint==='')hqp_json(['ok'=>false,'error'=>'endpoint_missing'],400);
        $hash=hash('sha256',$endpoint);$q=db()->prepare('SELECT * FROM push_subscriptions WHERE endpoint_hash=?');$q->execute([$hash]);$row=$q->fetch();
        if($row&&!empty($row['owner_admin_id'])&&(int)$row['owner_admin_id']!==$admin)hqp_json(['ok'=>false,'error'=>'device_owner_mismatch'],403);
        if($action==='subscribe'){
            [$endpoint,$p,$a]=hqp_device_input($data);$public=webpush_public_key();
            if($public==='')hqp_json(['ok'=>false,'error'=>'vapid_unavailable'],503);
            if(!hash_equals($public,(string)($data['applicationServerKey']??'')))hqp_json(['ok'=>false,'error'=>'key_changed_reload'],409);
            if($row&&$row['client_app']!=='legacy'&&$row['client_app']!==$app)hqp_json(['ok'=>false,'error'=>'scope_mismatch'],409);
            $lang=in_array($data['lang']??'',['vi','en','zh'],true)?$data['lang']:'vi';$renew=bin2hex(random_bytes(32));
            $scope=base_url($app==='mobile'?'mobile-admin/':'admin/');$ua=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,250);
            $q=db()->prepare('INSERT INTO push_subscriptions(endpoint,endpoint_hash,p256dh,auth,lang,user_agent,active,client_app,owner_admin_id,scope_url,vapid_hash,renewal_hash) VALUES(?,?,?,?,?,?,1,?,?,?,?,?) ON DUPLICATE KEY UPDATE p256dh=VALUES(p256dh),auth=VALUES(auth),lang=VALUES(lang),user_agent=VALUES(user_agent),active=1,client_app=VALUES(client_app),owner_admin_id=VALUES(owner_admin_id),scope_url=VALUES(scope_url),vapid_hash=VALUES(vapid_hash),renewal_hash=VALUES(renewal_hash),last_error=NULL,last_error_at=NULL');
            $q->execute([$endpoint,$hash,$p,$a,$lang,$ua,$app,$admin,$scope,hash('sha256',$public),hash('sha256',$renew)]);
            $q=db()->prepare('SELECT id FROM push_subscriptions WHERE endpoint_hash=?');$q->execute([$hash]);
            hqp_json(['ok'=>true,'stored'=>true,'device_id'=>(int)$q->fetchColumn(),'renewal_token'=>$renew,'renewal_url'=>base_url('push-renew.php'),'scope'=>$scope,'version'=>HQ_PUSH_VERSION]);
        }
        if($action==='unsubscribe'){
            if($row)db()->prepare('UPDATE push_subscriptions SET active=0,renewal_hash=NULL WHERE id=?')->execute([$row['id']]);
            hqp_json(['ok'=>true]);
        }
        if($action==='check'){
            $registered=$row&&(int)$row['active']===1&&$row['client_app']===$app&&!empty($row['vapid_hash'])&&hash_equals($row['vapid_hash'],hash('sha256',webpush_public_key()));
            hqp_json(['ok'=>true,'registered'=>$registered,'device_id'=>$row?(int)$row['id']:null,'runtime'=>push_runtime_diagnostics(),
                'state'=>!$row?'not_saved':(!(int)$row['active']?'inactive':($registered?'registered':'repair_required')),
                'last_received_at'=>$row['last_received_at']??null,'jobs'=>$row?hqp_recent_jobs((int)$row['id']):[],'version'=>HQ_PUSH_VERSION]);
        }
        if($action==='test'){
            if(!$row||!(int)$row['active']||$row['client_app']!==$app)hqp_json(['ok'=>false,'error'=>'enable_first'],409);
            if(!hash_equals((string)$row['vapid_hash'],hash('sha256',webpush_public_key())))hqp_json(['ok'=>false,'error'=>'key_changed_reload'],409);
            // Rate-limit test sends on the server, including simultaneous requests.
            $rateKey='push_test_'.$row['id'];$lock='hqp-test-'.$row['id'];$lk=db()->prepare('SELECT GET_LOCK(?,0)');$lk->execute([$lock]);
            if((int)$lk->fetchColumn()!==1)hqp_json(['ok'=>false,'error'=>'test_rate_limit'],429);
            try{if(time()-(int)setting($rateKey,'0')<20)hqp_json(['ok'=>false,'error'=>'test_rate_limit'],429);set_setting($rateKey,(string)time());}
            finally{$lk=db()->prepare('SELECT RELEASE_LOCK(?)');$lk->execute([$lock]);}
            $delay=($data['delay']??0)===15?15:0;
            // No browser timer is used: the PHP request continues while the phone locks.
            ignore_user_abort(true);session_write_close();if($delay)sleep($delay);
            $jobId=hqp_enqueue($row,null,'test-'.bin2hex(random_bytes(12)),true);
            hqp_process_queue(1,9,null,$jobId);
            hqp_json(['ok'=>true,'job_id'=>$jobId,'jobs'=>hqp_recent_jobs((int)$row['id']),'version'=>HQ_PUSH_VERSION]);
        }
        hqp_json(['ok'=>false,'error'=>'invalid_action'],400);
    }catch(InvalidArgumentException $e){hqp_json(['ok'=>false,'error'=>$e->getMessage()],400);}
    catch(Throwable $e){hqp_log('api_error',$e);hqp_json(['ok'=>false,'error'=>'push_api_error'],500);}
}
