<?php
const HQ_PUSH_VERSION='19.1';
function hqp_log(string $code, ?Throwable $e=null): void {
    // Do not log endpoints, subscription secrets, customer data, or private keys.
    error_log('[HuiYiTea push] '.$code.($e?' ['.get_class($e).']':''));
}
function ensure_v186_schema(): void {
    static $done=false;if($done)return;$done=true;
    try {
        if(setting('push_schema_191','')!=='1'){
            db()->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, endpoint TEXT NOT NULL,
                p256dh TEXT DEFAULT NULL, auth TEXT DEFAULT NULL, lang VARCHAR(8) NOT NULL DEFAULT 'vi',
                user_agent VARCHAR(255) DEFAULT NULL, active TINYINT(1) NOT NULL DEFAULT 1,
                last_success_at DATETIME DEFAULT NULL,last_error_at DATETIME DEFAULT NULL,last_error VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_endpoint_prefix (endpoint(190)), KEY idx_push_active(active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            foreach([
                'client_app'=>"VARCHAR(12) NOT NULL DEFAULT 'legacy'",'owner_admin_id'=>'INT UNSIGNED DEFAULT NULL',
                'endpoint_hash'=>'CHAR(64) DEFAULT NULL','scope_url'=>'VARCHAR(512) DEFAULT NULL',
                'vapid_hash'=>'CHAR(64) DEFAULT NULL','renewal_hash'=>'CHAR(64) DEFAULT NULL',
                'last_received_at'=>'DATETIME DEFAULT NULL'
            ] as $column=>$definition){
                if(!column_exists('push_subscriptions',$column)){
                    try{db()->exec("ALTER TABLE push_subscriptions ADD COLUMN `$column` $definition");}
                    catch(Throwable $e){if(!column_exists('push_subscriptions',$column))throw $e;}
                }
            }
            db()->exec('UPDATE push_subscriptions SET endpoint_hash=SHA2(endpoint,256) WHERE endpoint_hash IS NULL');
            $indexes=db()->query('SHOW INDEX FROM push_subscriptions')->fetchAll(PDO::FETCH_ASSOC);
            $names=array_column($indexes,'Key_name');
            if(!in_array('uniq_push_endpoint_hash',$names,true)){
                try{db()->exec('ALTER TABLE push_subscriptions ADD UNIQUE KEY uniq_push_endpoint_hash(endpoint_hash)');}
                catch(Throwable $e){$check=db()->query("SHOW INDEX FROM push_subscriptions WHERE Key_name='uniq_push_endpoint_hash'")->fetch();if(!$check)throw $e;}
            }
            if(in_array('uniq_endpoint_prefix',$names,true)){
                try{db()->exec('ALTER TABLE push_subscriptions DROP INDEX uniq_endpoint_prefix');}catch(Throwable $e){}
            }
            db()->exec("CREATE TABLE IF NOT EXISTS push_delivery_jobs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_key VARCHAR(100) NOT NULL,subscription_id INT UNSIGNED NOT NULL,
                order_id INT UNSIGNED DEFAULT NULL,kind VARCHAR(12) NOT NULL DEFAULT 'order',payload_json TEXT NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'queued',attempts INT UNSIGNED NOT NULL DEFAULT 0,
                due_at DATETIME NOT NULL,expires_at DATETIME NOT NULL,locked_at DATETIME DEFAULT NULL,
                http_code SMALLINT UNSIGNED DEFAULT NULL,detail VARCHAR(255) DEFAULT NULL,
                accepted_at DATETIME DEFAULT NULL,received_at DATETIME DEFAULT NULL,receipt_hash CHAR(64) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_push_event(event_key,subscription_id),KEY idx_push_due(status,due_at),KEY idx_push_subscription(subscription_id,id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            set_setting('push_schema_191','1');
        }
        ensure_vapid_keys();
    }catch(Throwable $e){$GLOBALS['hqp_schema_error']='push_schema_or_key_error';hqp_log('schema_or_key_error',$e);}
}
function ensure_vapid_keys(): array {
    static $cached=null;if($cached!==null)return $cached;
    $empty=['public'=>'','private'=>''];
    if(!function_exists('openssl_pkey_new'))return $empty;
    // Serialize initial generation: preserve the key already associated with subscriptions.
    $lock='hqp-vapid-'.substr(hash('sha256',defined('DB_NAME')?DB_NAME:'default'),0,20);
    $st=db()->prepare('SELECT GET_LOCK(?,5)');$st->execute([$lock]);
    if((int)$st->fetchColumn()!==1)throw new RuntimeException('vapid_key_lock_busy');
    try{
        $st=db()->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('webpush_vapid_public','webpush_vapid_private_pem')");
        $v=$st->fetchAll(PDO::FETCH_KEY_PAIR);$pub=trim($v['webpush_vapid_public']??'');$priv=$v['webpush_vapid_private_pem']??'';
        if($pub!==''||$priv!==''){
            $key=$priv!==''?openssl_pkey_get_private($priv):false;
            if(!$key||!hash_equals(hqp_public_raw($key),b64url_decode($pub)))throw new RuntimeException('vapid_key_pair_invalid');
            return $cached=['public'=>$pub,'private'=>$priv];
        }
        $res=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC,'curve_name'=>'prime256v1']);
        if(!$res||!openssl_pkey_export($res,$private))throw new RuntimeException('vapid_generate_failed');
        $public=b64url_encode(hqp_public_raw($res));
        $pdo=db();$own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();
        try{set_setting('webpush_vapid_public',$public);set_setting('webpush_vapid_private_pem',$private);if($own)$pdo->commit();}
        catch(Throwable $e){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
        return $cached=['public'=>$public,'private'=>$private];
    }finally{$st=db()->prepare('SELECT RELEASE_LOCK(?)');$st->execute([$lock]);}
}
function webpush_public_key(): string { try{return ensure_vapid_keys()['public'];}catch(Throwable $e){return '';} }
function webpush_vapid_jwt(string $endpoint): ?array {
    if(!hqp_allowed_endpoint($endpoint))return null;
    $keys=ensure_vapid_keys();if(!$keys['public'])return null;
    $u=parse_url($endpoint);$aud='https://'.strtolower($u['host']);
    $h=b64url_encode(json_encode(['typ'=>'JWT','alg'=>'ES256']));
    $p=b64url_encode(json_encode(['aud'=>$aud,'exp'=>time()+3600,'sub'=>'mailto:no-reply@huiyitea.vn'],JSON_UNESCAPED_SLASHES));
    $input=$h.'.'.$p;$sig='';if(!openssl_sign($input,$sig,$keys['private'],OPENSSL_ALGO_SHA256))return null;
    $sig=der_ecdsa_to_jose($sig);return $sig!==''?['jwt'=>$input.'.'.b64url_encode($sig),'public'=>$keys['public']]:null;
}
function hqp_send(array $subscription,array $payload,int $ttl=3600): array {
    $fail=static fn($code,$detail,$retry=false)=>['accepted'=>false,'http_code'=>$code,'detail'=>$detail,'retry'=>$retry];
    $endpoint=(string)$subscription['endpoint'];
    if(!hqp_allowed_endpoint($endpoint))return $fail(0,'endpoint_not_allowed');
    if(!function_exists('curl_init'))return $fail(0,'curl_unavailable');
    try{
        $v=webpush_vapid_jwt($endpoint);if(!$v)return $fail(0,'vapid_unavailable');
        if(!empty($subscription['vapid_hash'])&&!hash_equals($subscription['vapid_hash'],hash('sha256',$v['public'])))return $fail(0,'vapid_changed_resubscribe');
        $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $encoded=hqp_encrypt($json,(string)$subscription['p256dh'],(string)$subscription['auth']);
        $ch=curl_init($endpoint);$response='';
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$encoded,
            CURLOPT_HTTPHEADER=>['TTL: '.max(1,min(3600,$ttl)),'Urgency: high',
                'Authorization: vapid t='.$v['jwt'].', k='.$v['public'],
                'Content-Encoding: aes128gcm','Content-Type: application/octet-stream','Content-Length: '.strlen($encoded),'Expect:'],
            CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_TIMEOUT=>8,CURLOPT_CONNECTTIMEOUT=>4,
            CURLOPT_USERAGENT=>'HuiYiTea-WebPush/'.HQ_PUSH_VERSION,
            CURLOPT_WRITEFUNCTION=>static function($ch,$part)use(&$response){if(strlen($response)<4096)$response.=substr($part,0,4096-strlen($response));return strlen($part);}
        ]);
        curl_exec($ch);$errno=curl_errno($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if($errno)return $fail($code,'curl_'.$errno,true);
        if(in_array($code,[200,201,202],true))return ['accepted'=>true,'http_code'=>$code,'detail'=>'provider_accepted','retry'=>false];
        // Persist provider reason code only, not a response possibly echoing secrets.
        $reason=json_decode($response,true);$suffix=is_array($reason)?($reason['reason']??$reason['errno']??''):'';
        if(!is_scalar($suffix))$suffix='';$suffix=preg_replace('/[^a-zA-Z0-9_-]/','',substr((string)$suffix,0,70));
        return $fail($code,'http_'.$code.($suffix!==''?'_'.$suffix:''),$code===408||$code===429||$code>=500);
    }catch(Throwable $e){hqp_log('encrypt_or_send_error',$e);return $fail(0,'encrypt_or_key_error');}
}
function push_runtime_diagnostics(): array {
    $active=0;$legacy=0;
    try{$active=(int)db()->query("SELECT COUNT(*) FROM push_subscriptions WHERE active=1 AND client_app IN ('admin','mobile')")->fetchColumn();$legacy=(int)db()->query("SELECT COUNT(*) FROM push_subscriptions WHERE active=1 AND client_app='legacy'")->fetchColumn();}catch(Throwable $e){}
    return ['version'=>HQ_PUSH_VERSION,'openssl'=>function_exists('openssl_pkey_derive'),'curl'=>function_exists('curl_init'),'vapid'=>webpush_public_key()!=='',
        'active'=>$active,'legacy'=>$legacy,'schema_error'=>$GLOBALS['hqp_schema_error']??'',
        'cron_last_run'=>setting('push_cron_last_run',''),'host'=>parse_url(base_url(),PHP_URL_HOST)];
}
function hqp_payload(array $device,?array $order,bool $test=false): array {
    $lang=$device['lang']??'vi';$app=$device['client_app']==='mobile'?'mobile-admin/':'admin/';
    $texts=['vi'=>['Có đơn hàng mới','Thử thông báo từ máy chủ','Mở ứng dụng để xem đơn.'],
        'zh'=>['收到新訂單','伺服器推播測試','請開啟接單後台查看。'],
        'en'=>['New order received','Server push test','Open the order desk for details.']];
    $t=$texts[$lang]??$texts['vi'];$no=$order['order_no']??'';
    return ['title'=>'Hồng Trà Tuổi Thơ','body'=>$test?$t[1]:$t[0].($no!==''?' #'.$no:'').' · '.$t[2],
        'lang'=>$lang==='zh'?'zh-Hant':$lang,'order_id'=>(int)($order['id']??0),'test'=>$test,
        'tag'=>'hq-'.substr(hash('sha256',base_url()),0,8).'-'.($test?bin2hex(random_bytes(6)):'order-'.($order['id']??0)),
        'url'=>base_url($app.($order?'order.php?id='.(int)$order['id']:'index.php')),
        'icon'=>base_url('assets/icons/order-icon-192.png'),'badge'=>base_url('assets/icons/favicon.png')];
}
function hqp_enqueue(array $device,?array $order,string $event,bool $test=false): int {
    $token=bin2hex(random_bytes(24));$payload=hqp_payload($device,$order,$test);
    $payload['receipt_token']=$token;$payload['receipt_url']=base_url('push-receipt.php');
    $st=db()->prepare("INSERT IGNORE INTO push_delivery_jobs(event_key,subscription_id,order_id,kind,payload_json,due_at,expires_at,receipt_hash) VALUES(?,?,?,?,?,NOW(),DATE_ADD(NOW(),INTERVAL 1 HOUR),?)");
    $st->execute([$event,$device['id'],$order['id']??null,$test?'test':'order',json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),hash('sha256',$token)]);
    $q=db()->prepare('SELECT id FROM push_delivery_jobs WHERE event_key=? AND subscription_id=?');$q->execute([$event,$device['id']]);return (int)$q->fetchColumn();
}
function send_new_order_push_notifications(?int $orderId=null,bool $deliverNow=true): array {
    $result=['sent'=>0,'failed'=>0,'queued'=>0];
    try{
        if(!$orderId){$orderId=(int)db()->query('SELECT MAX(id) FROM orders')->fetchColumn();}
        $st=db()->prepare('SELECT * FROM orders WHERE id=?');$st->execute([$orderId]);$order=$st->fetch();if(!$order)return $result;
        // Legacy records are left untouched, but need an explicit new registration.
        $rows=db()->query("SELECT p.* FROM push_subscriptions p JOIN admins a ON a.id=p.owner_admin_id WHERE p.active=1 AND p.client_app IN ('admin','mobile')")->fetchAll();
        foreach($rows as $row){hqp_enqueue($row,$order,'order-'.$orderId);$result['queued']++;}
        if($deliverNow){$attempt=hqp_process_queue(20,9,$orderId);$result['sent']=$attempt['accepted'];$result['failed']=$attempt['failed'];}
    }catch(Throwable $e){hqp_log('enqueue_error',$e);$result['error']='enqueue_error';}
    return $result;
}
function hqp_process_queue(int $limit=20,int $budget=40,?int $orderId=null,?int $jobId=null): array {
    $result=['accepted'=>0,'failed'=>0,'retried'=>0];$start=microtime(true);
    // Reclaim jobs only after a crashed worker's lease expires.
    db()->exec("UPDATE push_delivery_jobs SET status='retry',due_at=NOW(),locked_at=NULL WHERE status='sending' AND locked_at<DATE_SUB(NOW(),INTERVAL 2 MINUTE)");
    $sql="SELECT id FROM push_delivery_jobs WHERE status IN ('queued','retry') AND due_at<=NOW()";$args=[];
    if($orderId!==null){$sql.=' AND order_id=?';$args[]=$orderId;}
    if($jobId!==null){$sql.=' AND id=?';$args[]=$jobId;}
    $sql.=' ORDER BY id LIMIT '.max(1,min(100,$limit));$st=db()->prepare($sql);$st->execute($args);$ids=$st->fetchAll(PDO::FETCH_COLUMN);
    foreach($ids as $id){
        if(microtime(true)-$start>$budget)break;
        $claim=db()->prepare("UPDATE push_delivery_jobs SET status='sending',locked_at=NOW(),attempts=attempts+1 WHERE id=? AND status IN ('queued','retry') AND due_at<=NOW()");$claim->execute([$id]);if($claim->rowCount()!==1)continue;
        try{
            $q=db()->prepare('SELECT *,UNIX_TIMESTAMP(expires_at) AS expires_unix FROM push_delivery_jobs WHERE id=?');$q->execute([$id]);$job=$q->fetch();
            $q=db()->prepare('SELECT p.* FROM push_subscriptions p JOIN admins a ON a.id=p.owner_admin_id WHERE p.id=?');$q->execute([$job['subscription_id']]);$sub=$q->fetch();
            if(!$sub||!(int)$sub['active']){$state='cancelled';$r=['http_code'=>0,'detail'=>'subscription_inactive'];}
            elseif((int)$job['expires_unix']<=time()){$state='expired';$r=['http_code'=>0,'detail'=>'ttl_expired'];}
            else{
                $orderNew=true;
                if($job['order_id']){$q=db()->prepare('SELECT status FROM orders WHERE id=?');$q->execute([$job['order_id']]);$orderNew=$q->fetchColumn()==='new';}
                if(!$orderNew){$state='cancelled';$r=['http_code'=>0,'detail'=>'order_already_handled'];}
                else{
                    $payload=json_decode($job['payload_json'],true,512,JSON_THROW_ON_ERROR);$payload['job_id']=(int)$id;
                    $r=hqp_send($sub,$payload,max(1,(int)$job['expires_unix']-time()));
                    $state=$r['accepted']?'accepted':(($r['retry']&&(int)$job['attempts']<4)?'retry':'failed');
                    if($r['accepted']){db()->prepare('UPDATE push_subscriptions SET last_success_at=NOW(),last_error=NULL,last_error_at=NULL WHERE id=?')->execute([$sub['id']]);$result['accepted']++;}
                    else{
                        db()->prepare('UPDATE push_subscriptions SET last_error=?,last_error_at=NOW() WHERE id=?')->execute([$r['detail'],$sub['id']]);
                        if(in_array($r['http_code'],[404,410],true))db()->prepare('UPDATE push_subscriptions SET active=0 WHERE id=?')->execute([$sub['id']]);
                        $result[$state==='retry'?'retried':'failed']++;
                    }
                }
            }
            $delay=min(900,60*(2**max(0,(int)$job['attempts']-1)));
            db()->prepare("UPDATE push_delivery_jobs SET status=?,http_code=?,detail=?,locked_at=NULL,due_at=DATE_ADD(NOW(),INTERVAL $delay SECOND),accepted_at=IF(?='accepted',NOW(),accepted_at) WHERE id=?")
                ->execute([$state,$r['http_code'],$r['detail'],$state,$id]);
        }catch(Throwable $e){hqp_log('delivery_error',$e);db()->prepare("UPDATE push_delivery_jobs SET status=IF(attempts<4,'retry','failed'),detail='delivery_exception',locked_at=NULL,due_at=DATE_ADD(NOW(),INTERVAL 2 MINUTE) WHERE id=?")->execute([$id]);$result['failed']++;}
    }
    return $result;
}
