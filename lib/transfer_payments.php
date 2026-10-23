<?php
/** Store receipts remain separate from host collection and order fulfillment. */
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){http_response_code(404);exit;}
require_once __DIR__.'/payment_workflow_i18n.php';
class TransferPaymentError extends RuntimeException {}
function htp_fail(string $key): never { throw new TransferPaymentError($key); }
function htp_schema(): void {
    static $done=false;if($done)return;$pdo=db();
    if($pdo->inTransaction())htp_fail('storage');
    $key='transfer_payment_schema_version';$st=$pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$st->execute([$key]);
    if((string)$st->fetchColumn()!=='19.15.0'){
        $name='htp:'.substr(hash('sha256',(string)$pdo->query('SELECT DATABASE()')->fetchColumn()),0,40);$lock=$pdo->prepare('SELECT GET_LOCK(?,10)');$lock->execute([$name]);if((int)$lock->fetchColumn()!==1)htp_fail('storage');
        try{
            $st->execute([$key]);if((string)$st->fetchColumn()!=='19.15.0'){
                $pdo->exec("CREATE TABLE IF NOT EXISTS transfer_store_accounts (store_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, bank_name VARCHAR(120) NOT NULL DEFAULT '', account_number VARCHAR(80) NOT NULL DEFAULT '', account_name VARCHAR(160) NOT NULL DEFAULT '', enabled TINYINT NOT NULL DEFAULT 0, qr_data MEDIUMBLOB NULL, qr_mime VARCHAR(20) NULL, updated_at DATETIME NOT NULL, updated_by_admin_id BIGINT UNSIGNED NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $pdo->exec("CREATE TABLE IF NOT EXISTS transfer_group_accounts (room_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, bank_name VARCHAR(120) NOT NULL, account_number VARCHAR(80) NOT NULL, account_name VARCHAR(160) NOT NULL, qr_data MEDIUMBLOB NULL, qr_mime VARCHAR(20) NULL, created_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $pdo->exec("CREATE TABLE IF NOT EXISTS transfer_order_receipts (order_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, status VARCHAR(16) NOT NULL DEFAULT 'pending', version INT UNSIGNED NOT NULL DEFAULT 1, amount DECIMAL(12,2) NOT NULL, recipient_json LONGTEXT NULL, qr_data MEDIUMBLOB NULL, qr_mime VARCHAR(20) NULL, reported_at DATETIME NULL, confirmed_at DATETIME NULL, confirmed_by_admin_id BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                foreach(['received_amount'=>'DECIMAL(12,2) NOT NULL DEFAULT 0','refunded_amount'=>'DECIMAL(12,2) NOT NULL DEFAULT 0','report_state'=>"VARCHAR(16) NOT NULL DEFAULT 'none'",'ledger_only'=>'TINYINT NOT NULL DEFAULT 0'] as $column=>$definition){
                    $check=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$check->execute(['transfer_order_receipts',$column]);
                    if(!(int)$check->fetchColumn())$pdo->exec("ALTER TABLE transfer_order_receipts ADD COLUMN `$column` $definition");
                }
                $pdo->exec("CREATE TABLE IF NOT EXISTS transfer_payment_events (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,order_id BIGINT UNSIGNED NOT NULL,event_key VARCHAR(100) NOT NULL,event_type VARCHAR(24) NOT NULL,amount_delta DECIMAL(12,2) NOT NULL DEFAULT 0,refund_delta DECIMAL(12,2) NOT NULL DEFAULT 0,account_code VARCHAR(40) NULL,recipient_json LONGTEXT NULL,transaction_reference VARCHAR(160) NOT NULL DEFAULT '',reference_key CHAR(64) NULL,reason VARCHAR(1000) NOT NULL DEFAULT '',admin_id BIGINT UNSIGNED NULL,before_json LONGTEXT NULL,after_json LONGTEXT NULL,created_at DATETIME NOT NULL,UNIQUE KEY uq_payment_event_key(event_key),UNIQUE KEY uq_payment_bank_reference(reference_key),KEY idx_payment_order(order_id,id),KEY idx_payment_time(created_at,id),KEY idx_payment_account(account_code,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                // A transaction plus a unique opening key makes interrupted upgrades repeatable.
                $pdo->beginTransaction();
                try{
                    $rows=$pdo->query("SELECT order_id,status,version,amount,received_amount,report_state,recipient_json,confirmed_at,confirmed_by_admin_id,created_at FROM transfer_order_receipts WHERE status IN ('confirmed','requested') FOR UPDATE");
                    while($row=$rows->fetch()){
                        if($row['status']==='confirmed'){
                            $opening=$pdo->prepare('SELECT id FROM transfer_payment_events WHERE event_key=?');$opening->execute(['legacy:'.$row['order_id']]);
                            if(!$opening->fetchColumn()&&(float)$row['received_amount']==0){
                                $snapshot=$row['recipient_json']?json_decode($row['recipient_json'],true):null;
                                $account=is_array($snapshot)?(($snapshot['source']??'legacy').':'.(int)($snapshot['id']??0)):'legacy:0';
                                $pdo->prepare('INSERT INTO transfer_payment_events(order_id,event_key,event_type,amount_delta,account_code,recipient_json,admin_id,reason,created_at) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$row['order_id'],'legacy:'.$row['order_id'],'legacy_opening',$row['amount'],$account,$row['recipient_json'],$row['confirmed_by_admin_id'],'Migrated previously confirmed receipt; original timestamps retained in receipt.',$row['confirmed_at']?:$row['created_at']]);
                                $pdo->prepare("UPDATE transfer_order_receipts SET received_amount=amount,report_state='confirmed' WHERE order_id=?")->execute([$row['order_id']]);
                            }
                        }elseif($row['status']==='requested'&&$row['report_state']==='none')$pdo->prepare("UPDATE transfer_order_receipts SET report_state='requested' WHERE order_id=?")->execute([$row['order_id']]);
                    }
                    $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$key,'19.15.0']);$pdo->commit();
                }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
            }
        }finally{$lock=$pdo->prepare('SELECT RELEASE_LOCK(?)');$lock->execute([$name]);}
    }$done=true;
}
function htp_text(mixed $value,int $max): string {
    if(!is_string($value)||!preg_match('//u',$value)||preg_match('/[\x00-\x1F\x7F]/u',$value))htp_fail('invalid_account');
    $value=trim($value);if(preg_match_all('/./us',$value)>$max)htp_fail('invalid_account');return $value;
}
function htp_reason(mixed $value): string {
    if(!is_string($value)||!preg_match('//u',$value)||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$value))htp_fail('reason_required');
    $value=trim($value);if($value===''||preg_match_all('/./us',$value)>1000)htp_fail('reason_required');return $value;
}
function htp_checked(array $input,string $key): bool {
    $value=$input[$key]??'0';if(!in_array($value,['0','1',0,1,false,true],true))htp_fail('invalid_account');return (bool)$value;
}
function htp_account_fields(array $input,string $prefix='',bool $required=true): array {
    $bank=htp_text($input[$prefix.'bank_name']??'',120);$number=htp_text($input[$prefix.'account_number']??'',80);$name=htp_text($input[$prefix.'account_name']??'',160);
    if($required&&($bank===''||$number===''||$name===''))htp_fail('account_required');
    if($number!==''&&!preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._-]{0,79}$/D',$number))htp_fail('invalid_account');
    return ['bank_name'=>$bank,'account_number'=>$number,'account_name'=>$name];
}
/** Raster only. Stored as database bytes, not executable files or client-provided paths. */
function htp_upload(array $files,string $field): ?array {
    if(!isset($files[$field]))return null;$file=$files[$field];if(!is_array($file))htp_fail('invalid_image');
    $error=$file['error']??null;if($error===UPLOAD_ERR_NO_FILE)return null;if($error!==UPLOAD_ERR_OK)htp_fail('invalid_image');
    $tmp=$file['tmp_name']??null;if(!is_string($tmp)||!is_uploaded_file($tmp))htp_fail('invalid_image');
    $size=filesize($tmp);if(!$size||$size>2*1024*1024)htp_fail('image_size');
    $image=@getimagesize($tmp);$types=[IMAGETYPE_JPEG=>'image/jpeg',IMAGETYPE_PNG=>'image/png',IMAGETYPE_WEBP=>'image/webp'];
    if(!$image||!isset($types[$image[2]])||($image['mime']??'')!==$types[$image[2]]||$image[0]<1||$image[1]<1||$image[0]>4096||$image[1]>4096)htp_fail('invalid_image');
    $bytes=file_get_contents($tmp);if(!is_string($bytes)||strlen($bytes)!==$size)htp_fail('invalid_image');return ['qr_data'=>$bytes,'qr_mime'=>$types[$image[2]]];
}
function htp_store_save(int $storeId,array $input,int $adminId): void {
    if(!array_key_exists('transfer_enabled',$input))return;hds_staff($adminId);if(!db()->inTransaction()||$storeId<1)htp_fail('storage');
    $enabled=htp_checked($input,'transfer_enabled');$account=htp_account_fields($input,'transfer_',$enabled);
    $old=hyg_one('SELECT qr_data,qr_mime FROM transfer_store_accounts WHERE store_id=? FOR UPDATE',[$storeId]);
    $upload=htp_upload($_FILES??[],'transfer_qr');$remove=htp_checked($input,'remove_transfer_qr');
    $qr=$upload??($remove?null:$old);
    db()->prepare('INSERT INTO transfer_store_accounts(store_id,bank_name,account_number,account_name,enabled,qr_data,qr_mime,updated_at,updated_by_admin_id) VALUES(?,?,?,?,?,?,?,UTC_TIMESTAMP(),?) ON DUPLICATE KEY UPDATE bank_name=VALUES(bank_name),account_number=VALUES(account_number),account_name=VALUES(account_name),enabled=VALUES(enabled),qr_data=VALUES(qr_data),qr_mime=VALUES(qr_mime),updated_at=VALUES(updated_at),updated_by_admin_id=VALUES(updated_by_admin_id)')->execute([$storeId,$account['bank_name'],$account['account_number'],$account['account_name'],(int)$enabled,$qr['qr_data']??null,$qr['qr_mime']??null,$adminId]);
}
function htp_store_fields(int $storeId): array {
    $a=hyg_one('SELECT bank_name,account_number,account_name,enabled,COALESCE(OCTET_LENGTH(qr_data),0)>0 AS has_qr FROM transfer_store_accounts WHERE store_id=?',[$storeId]);
    return ['transfer_bank_name'=>$a['bank_name']??'','transfer_account_number'=>$a['account_number']??'','transfer_account_name'=>$a['account_name']??'','transfer_enabled'=>(bool)($a['enabled']??false),'transfer_has_qr'=>(bool)($a['has_qr']??false)];
}
function htp_group_input(array $input,array $files): ?array {
    if(!htp_checked($input,'collect_by_host'))return null;
    return array_merge(htp_account_fields($input,'host_'),htp_upload($files,'host_qr')??['qr_data'=>null,'qr_mime'=>null]);
}
function htp_group_save(int $roomId,array $account): void {
    if(!db()->inTransaction())htp_fail('storage');$a=htp_account_fields($account);
    db()->prepare('INSERT INTO transfer_group_accounts(room_id,bank_name,account_number,account_name,qr_data,qr_mime,created_at) VALUES(?,?,?,?,?,?,UTC_TIMESTAMP())')->execute([$roomId,$a['bank_name'],$a['account_number'],$a['account_name'],$account['qr_data']??null,$account['qr_mime']??null]);
}
function htp_room_enrich(array $room): array {
    $a=hyg_one('SELECT room_id FROM transfer_group_accounts WHERE room_id=?',[$room['id']]);$room['collection_mode']=$a?'host':'store';return $room;
}
function htp_group_account(array $room): ?array {
    $a=hyg_one('SELECT bank_name,account_number,account_name,COALESCE(OCTET_LENGTH(qr_data),0)>0 AS has_qr FROM transfer_group_accounts WHERE room_id=?',[$room['id']]);
    if(!$a)return null;$a['qr_url']=$a['has_qr']?base_url('payment-qr.php?type=group&room='.rawurlencode($room['public_id'])):'';return $a;
}
function htp_group_order_method(array $summary): string {
    if(($summary['collection_mode']??'store')!=='host'&&$summary['mode']==='split')return 'split';
    return ($summary['members'][$summary['host_member_id']]['method']??'bank_transfer')==='cash'?'cash':'transfer';
}
function htp_initialize_order(int $orderId): void {
    if(!db()->inTransaction())htp_fail('storage');$o=hyg_one('SELECT * FROM orders WHERE id=?',[$orderId]);if(!$o)htp_fail('not_found');
    $summary=hyg_order_payment_summary($o);
    // Already-collected legacy/direct-store groups must never be asked to pay the total again.
    if(!empty($o['group_room_id'])&&($summary['collection_mode']??'store')!=='host')return;
    db()->prepare("INSERT INTO transfer_order_receipts(order_id,status,amount,created_at) VALUES(?,?,?,UTC_TIMESTAMP())")->execute([$orderId,(float)$o['total_amount']<=0?'not_required':'pending',$o['total_amount']]);
}
function htp_receipt(int $orderId): ?array {
    $r=hyg_one('SELECT order_id,status,version,amount,received_amount,refunded_amount,report_state,ledger_only,recipient_json,reported_at,confirmed_at,confirmed_by_admin_id,COALESCE(OCTET_LENGTH(qr_data),0)>0 AS has_qr FROM transfer_order_receipts WHERE order_id=?',[$orderId]);
    if(!$r)return null;$o=hyg_one('SELECT status,total_amount FROM orders WHERE id=?',[$orderId]);return htp_financial_summary($o??[],$r);
}
/** Money collected in a group room before checkout (display only; staff actions keep using the receipt row). */
function htp_group_paid(array $order): ?array {
    if(empty($order['group_room_id']))return null;
    return hyg_one('SELECT COALESCE(SUM(payment_received_amount),0) AS received,COALESCE(SUM(payment_refunded_amount),0) AS refunded FROM group_members WHERE room_id=?',[$order['group_room_id']]);
}
/** Server-owned guest order numbers only; never accept an ownership token from the client. */
function htp_remember_customer_order(string $number): void {
    if($number===''||strlen($number)>80)return;
    $list=$_SESSION['customer_payment_orders']??[];if(!is_array($list))$list=[];
    $list=array_values(array_filter($list,fn($v)=>is_string($v)&&$v!==$number));$list[]=$number;
    $_SESSION['customer_payment_orders']=array_slice($list,-50);
}
/** Guest orders are session-owned; member orders require their member; group orders require their host. */
function htp_customer_allowed(array $order): bool {
    if(!empty($order['group_room_id'])){
        if(!empty($order['member_id'])&&(int)($_SESSION['member_id']??0)===(int)$order['member_id'])return true;
        $r=hyg_one('SELECT * FROM group_rooms WHERE id=? AND order_id=?',[$order['group_room_id'],$order['id']]);
        if(!$r||!hyg_host($r))return false;try{hyg_member($r);}catch(GroupOrderError $e){return false;}return true;
    }
    if(!empty($order['member_id']))return (int)($_SESSION['member_id']??0)===(int)$order['member_id'];
    $numbers=$_SESSION['customer_payment_orders']??[];if(!is_array($numbers))$numbers=[];$numbers[]=$_SESSION['last_order_no']??null;
    foreach($numbers as $no)if(is_string($no)&&hash_equals((string)$order['order_no'],$no))return true;return false;
}
/** True while this customer has an order waiting for payment (or an open Mini Game checkout); group sharing is hidden then. */
function htp_customer_has_unpaid(): bool {
    try{
        if(function_exists('hcgs_locked')&&hcgs_locked())return true;
        $where=[];$args=[];
        if(($mid=(int)($_SESSION['member_id']??0))>0){$where[]='o.member_id=?';$args[]=$mid;}
        $numbers=$_SESSION['customer_payment_orders']??[];if(!is_array($numbers))$numbers=[];$numbers[]=$_SESSION['last_order_no']??null;
        $numbers=array_values(array_unique(array_filter($numbers,fn($v)=>is_string($v)&&$v!=='')));
        if($numbers){$where[]='o.order_no IN ('.implode(',',array_fill(0,count($numbers),'?')).')';$args=array_merge($args,$numbers);}
        if(!$where)return false;
        return (bool)hyg_one("SELECT 1 FROM orders o JOIN transfer_order_receipts r ON r.order_id=o.id WHERE (".implode(' OR ',$where).") AND o.status<>'cancelled' AND r.status IN ('pending','requested','partial') LIMIT 1",$args);
    }catch(Throwable $e){return false;}
}
function htp_customer_order(mixed $number): array {
    $no=htp_text($number,80);if($no==='')htp_fail('not_found');$o=hyg_one('SELECT * FROM orders WHERE order_no=?',[$no]);
    if(!$o||!htp_customer_allowed($o))htp_fail('forbidden');return $o;
}
function htp_account_qr_file(array $a,bool $bytes=false): array {
    $filename=(string)($a['qr_image']??'');$result=['qr_digest'=>'','qr_url'=>'','qr_data'=>null,'qr_mime'=>null];
    if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.(?:jpe?g|png|webp)$/Di',$filename))return $result;
    $path=dirname(__DIR__).'/uploads/'.$filename;if(!is_file($path)||is_link($path)||filesize($path)>5*1024*1024)return $result;
    $image=@getimagesize($path);$types=[IMAGETYPE_JPEG=>'image/jpeg',IMAGETYPE_PNG=>'image/png',IMAGETYPE_WEBP=>'image/webp'];if(!$image||!isset($types[$image[2]]))return $result;
    $result['qr_digest']=hash_file('sha256',$path);$result['qr_url']=base_url('uploads/'.$filename);
    if($bytes){$result['qr_data']=file_get_contents($path);$result['qr_mime']=$types[$image[2]];}return $result;
}
/** Recommended stores NEVER become recipients. Only explicit assignment or HQ account is used. */
function htp_order_accounts(array $order,bool $bytes=false,bool $lock=false): array {
    if($order['payment_method']==='cash')return [['code'=>'cash:0','source'=>'cash','id'=>0,'recipient'=>$order['dispatch_store_name']??htp('headquarters'),'bank_name'=>'','account_number'=>'','account_name'=>'','qr_digest'=>'','qr_url'=>'']];
    $storeId=(int)($order['dispatch_store_id']??0);
    if($storeId>0){
        $a=hyg_one('SELECT store_id,bank_name,account_number,account_name,enabled,SHA2(qr_data,256) AS qr_digest'.($bytes?',qr_data,qr_mime':'').' FROM transfer_store_accounts WHERE store_id=?'.($lock?' FOR UPDATE':''),[$storeId]);
        if(!$a||!$a['enabled']||!$a['bank_name']||!$a['account_number']||!$a['account_name'])return [];
        return [array_merge($a,['code'=>'store:'.$storeId,'source'=>'store','id'=>$storeId,'recipient'=>(string)$order['dispatch_store_name'],'qr_url'=>$a['qr_digest']?base_url('payment-qr.php?type=store&order='.rawurlencode($order['order_no'])):''])];
    }
    $rows=hyg_all('SELECT * FROM bank_accounts WHERE active=1 ORDER BY sort_order,id'.($lock?' FOR UPDATE':''));$accounts=[];
    foreach($rows as $a)$accounts[]=array_merge($a,htp_account_qr_file($a,$bytes),['code'=>'hq:'.(int)$a['id'],'source'=>'hq','id'=>(int)$a['id'],'recipient'=>htp('headquarters')]);return $accounts;
}
function htp_quote(array $order,array $receipt,array $account): string {
    return hash('sha256',json_encode([(int)$order['id'],(string)$order['order_no'],(string)$order['payment_method'],(float)$order['total_amount'],(int)($order['dispatch_revision']??0),(int)$receipt['version'],$account['code'],$account['bank_name'],$account['account_number'],$account['account_name'],$account['qr_digest']??''],JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR));
}
/** Exact decimal arithmetic for recorded money (DECIMAL(12,2)). */
function htp_cents(mixed $value,bool $signed=false): int {
    if(!is_string($value)&&!is_int($value)&&!is_float($value))htp_fail('amount_invalid');$text=(string)$value;
    if(!preg_match($signed?'/^-?\d{1,10}(?:\.\d{1,2})?$/D':'/^\d{1,10}(?:\.\d{1,2})?$/D',$text))htp_fail('amount_invalid');
    $negative=str_starts_with($text,'-');$parts=explode('.',ltrim($text,'-'));$cents=(int)$parts[0]*100+(int)str_pad($parts[1]??'',2,'0');
    if($cents>999999999999)htp_fail('amount_invalid');return $negative?-$cents:$cents;
}
function htp_decimal(int $cents): string {return ($cents<0?'-':'').intdiv(abs($cents),100).'.'.str_pad((string)(abs($cents)%100),2,'0',STR_PAD_LEFT);}
function htp_financial_summary(array $order,?array $receipt): array {
    $r=$receipt??['amount'=>$order['total_amount']??0,'received_amount'=>0,'refunded_amount'=>0,'report_state'=>'none','ledger_only'=>1,'status'=>'pending','version'=>0];
    $due=htp_cents($r['amount']);$received=htp_cents($r['received_amount']);$refunded=htp_cents($r['refunded_amount']);$net=$received-$refunded;$cancelled=($order['status']??'')==='cancelled';
    $balance=$cancelled?0:max(0,$due-$net);$extra=$cancelled?0:max(0,$net-$due);$refund=$cancelled?max(0,$net):$extra;
    if($cancelled&&$net>0)$state='refund_pending';
    elseif($cancelled&&$received>0&&$net===0)$state='refunded';
    elseif(!$receipt)$state='legacy';
    elseif($extra>0)$state='overpaid';
    elseif($due===0&&$net===0)$state='not_required';
    elseif($due>0&&$net>=$due)$state='confirmed';
    elseif($net>0)$state='partial';
    elseif($r['report_state']==='requested')$state='requested';
    elseif($r['report_state']==='rejected')$state='rejected';
    else $state='unpaid';
    return array_merge($r,['net_received'=>htp_decimal($net),'balance_due'=>htp_decimal($balance),'overpaid'=>htp_decimal($extra),'refund_due'=>htp_decimal($refund),'financial_state'=>$state]);
}
function htp_events(int $orderId): array {return hyg_all('SELECT * FROM transfer_payment_events WHERE order_id=? ORDER BY id DESC',[$orderId]);}
/** All monetary history is inserted, never updated or deleted by application actions. */
function htp_event(int $orderId,string $type,array $before,array $after,?int $adminId,string $reason='',int $receivedDelta=0,int $refundDelta=0,?array $account=null,string $reference=''): void {
    if(!db()->inTransaction())htp_fail('storage');
    $snapshot=$account?htp_account_snapshot($account):null;
    $refKey=$reference!==''&&$account&&($account['source']??'')!=='cash'&&in_array($type,['receive','confirm','refund'],true)?hash('sha256',json_encode([$type==='refund'?'refund':'receive',$snapshot['bank_name'],$snapshot['account_number'],$reference],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)):null;
    // QR byte data must never enter JSON/audit output. The recipient and financial state are retained.
    $safe=fn($r)=>array_intersect_key($r,array_flip(['status','version','amount','received_amount','refunded_amount','report_state','ledger_only','recipient_json','reported_at','confirmed_at','confirmed_by_admin_id']));
    $accountCode=$snapshot?($snapshot['source'].':'.$snapshot['id']):null;
    try{db()->prepare('INSERT INTO transfer_payment_events(order_id,event_key,event_type,amount_delta,refund_delta,account_code,recipient_json,transaction_reference,reference_key,reason,admin_id,before_json,after_json,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())')->execute([$orderId,'event:'.bin2hex(random_bytes(24)),$type,htp_decimal($receivedDelta),htp_decimal($refundDelta),$accountCode,$snapshot?json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):($after['recipient_json']??null),$reference,$refKey,$reason,$adminId,json_encode($safe($before),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode($safe($after),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);}
    catch(PDOException $error){if($error->getCode()==='23000'&&$refKey!==null)htp_fail('reference_duplicate');throw $error;}
}
function htp_account_snapshot(array $account): array {return ['source'=>$account['source']??'legacy','id'=>(int)($account['id']??0),'recipient'=>$account['recipient']??'','bank_name'=>$account['bank_name']??'','account_number'=>$account['account_number']??'','account_name'=>$account['account_name']??'','payment_method'=>($account['source']??'')==='cash'?'cash':'transfer'];}
/** Staff may account for historical/direct group receipts without creating another customer charge. */
function htp_staff_accounts(array $order,bool $lock=false): array {
    $accounts=[];$r=hyg_one('SELECT recipient_json FROM transfer_order_receipts WHERE order_id=?',[$order['id']]);
    $snapshot=json_decode($r['recipient_json']??'',true);
    if(is_array($snapshot))$accounts[]=array_merge($snapshot,['code'=>'snapshot','recipient'=>pwf('snapshot_account').' · '.($snapshot['recipient']??'')]);
    $accounts[]=array_merge(['source'=>'cash','id'=>0,'recipient'=>pwf('cash_account'),'code'=>'cash:0'],['bank_name'=>'','account_number'=>'','account_name'=>'']);
    $orderForBank=$order;$orderForBank['payment_method']='transfer';foreach(htp_order_accounts($orderForBank,false,$lock) as $a)$accounts[]=$a;
    // The actual recipient can differ from the currently assigned fulfillment branch.
    foreach(hyg_all('SELECT id,bank_name,account_number,account_name FROM bank_accounts WHERE active=1 ORDER BY sort_order,id'.($lock?' FOR UPDATE':'')) as $a){$code='hq:'.$a['id'];if(!in_array($code,array_column($accounts,'code'),true))$accounts[]=array_merge($a,['code'=>$code,'source'=>'hq','recipient'=>htp('headquarters')]);}
    foreach(hyg_all('SELECT a.store_id,a.bank_name,a.account_number,a.account_name,s.name FROM transfer_store_accounts a JOIN dispatch_stores s ON s.id=a.store_id WHERE a.enabled=1'.($lock?' FOR UPDATE':'')) as $a){$code='store:'.$a['store_id'];if(!in_array($code,array_column($accounts,'code'),true))$accounts[]=array_merge($a,['id'=>$a['store_id'],'code'=>$code,'source'=>'store','recipient'=>$a['name']]);}
    return $accounts;
}
function htp_staff_guard(int $adminId): void {
    $current=haa_current();if(!$current||(int)$current['id']!==$adminId)htp_fail('forbidden');hds_staff($adminId);
}
function htp_staff_locked(int $adminId): void {
    if(!db()->inTransaction())htp_fail('storage');
    $actor=hyg_one('SELECT id,role,auth_version FROM admins WHERE id=? FOR UPDATE',[$adminId]);
    if(!$actor||!in_array($actor['role'],['owner','admin','operator'],true)||(int)$actor['auth_version']!==(int)($_SESSION['admin_auth_version']??0)||(int)($_SESSION['admin_id']??0)!==$adminId)htp_fail('forbidden');
}
function htp_expected_version(mixed $value): int {
    if((!is_string($value)&&!is_int($value))||!preg_match('/^[1-9]\d{0,9}$/D',(string)$value))htp_fail('changed');return (int)$value;
}
function htp_staff_action(int $orderId,string $action,array $input,int $adminId): void {
    htp_staff_guard($adminId);$expected=htp_expected_version($input['payment_version']??null);
    if(!in_array($action,['payment_receive','payment_correct','payment_reject','payment_refund'],true))htp_fail('forbidden');
    $reason=htp_reason($input['reason']??'');
    $reference=htp_text($input['transaction_reference']??'',160);$pdo=db();if($pdo->inTransaction())htp_fail('storage');$pdo->beginTransaction();
    try{
        htp_staff_locked($adminId);
        $order=hyg_one('SELECT * FROM orders WHERE id=? FOR UPDATE',[$orderId]);if(!$order)htp_fail('not_found');
        $r=hyg_one('SELECT * FROM transfer_order_receipts WHERE order_id=? FOR UPDATE',[$orderId]);
        if(!$r){
            if($expected!==1)htp_fail('changed');
            $pdo->prepare("INSERT INTO transfer_order_receipts(order_id,status,amount,ledger_only,created_at) VALUES(?,'pending',?,1,UTC_TIMESTAMP())")->execute([$orderId,$order['total_amount']]);
            $r=hyg_one('SELECT * FROM transfer_order_receipts WHERE order_id=? FOR UPDATE',[$orderId]);
        }
        if((int)$r['version']!==$expected)htp_fail('changed');$before=$r;$received=htp_cents($r['received_amount']);$refunded=htp_cents($r['refunded_amount']);$delta=0;$refundDelta=0;$account=null;
        if($action==='payment_reject'){
            if($r['report_state']!=='requested')htp_fail('changed');$r['report_state']='rejected';$type='reject';
        }else{
            $delta=htp_cents($input['amount']??null,$action==='payment_correct');if($delta===0)htp_fail('amount_invalid');
            $defaultAccount=$r['recipient_json']?'snapshot':($order['payment_method']==='cash'?'cash:0':'snapshot');
            $code=$input['account_code']??$defaultAccount;if(!is_string($code))htp_fail('invalid_account');
            foreach(htp_staff_accounts($order,true) as $a)if($a['code']===$code)$account=$a;
            if(!$account)htp_fail('invalid_account');
            // Use the original snapshot bytes, rather than translated UI labels, in immutable events.
            if($code==='snapshot'){$saved=json_decode($r['recipient_json']??'',true);if(!is_array($saved))htp_fail('invalid_account');$account=array_merge($saved,['code'=>'snapshot']);}
            if($action==='payment_refund'){
                if($delta<1||$delta>$received-$refunded)htp_fail('refund_invalid');$refundDelta=$delta;$delta=0;$refunded+=$refundDelta;$type='refund';
            }elseif($action==='payment_correct'&&($input['correction_target']??'received')==='refunded'){
                $refundDelta=$delta;$delta=0;$refunded+=$refundDelta;$type='correction';
                if($refunded<0||$refunded>$received)htp_fail('refund_invalid');
            }else{
                if($action==='payment_correct'&&($input['correction_target']??'received')!=='received')htp_fail('amount_invalid');
                if($action==='payment_receive'&&$delta<1)htp_fail('amount_invalid');$received+=$delta;$type=$action==='payment_correct'?'correction':'receive';
                if($received<$refunded||$received<0||$received>999999999999)htp_fail('amount_invalid');
                if($action==='payment_receive'){
                    $r['report_state']='confirmed';
                    if(!$r['recipient_json'])$r['recipient_json']=json_encode(htp_account_snapshot($account),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
                }
            }
            if(in_array($type,['receive','refund'],true)&&$account['source']!=='cash'&&$reference==='')htp_fail('reference_required');
        }
        $r['received_amount']=htp_decimal($received);$r['refunded_amount']=htp_decimal($refunded);$r['version']=(int)$r['version']+1;
        $net=$received-$refunded;$due=htp_cents($r['amount']);$r['status']=$due===0&&$net===0?'not_required':($net>=$due&&$due>0?'confirmed':($r['report_state']==='requested'?'requested':($net>0?'partial':'pending')));
        $pdo->prepare('UPDATE transfer_order_receipts SET status=?,version=?,received_amount=?,refunded_amount=?,report_state=?,recipient_json=?,confirmed_at=IF(? IN (\'receive\',\'confirm\'),UTC_TIMESTAMP(),confirmed_at),confirmed_by_admin_id=IF(? IN (\'receive\',\'confirm\'),?,confirmed_by_admin_id) WHERE order_id=?')->execute([$r['status'],$r['version'],$r['received_amount'],$r['refunded_amount'],$r['report_state'],$r['recipient_json'],$type,$type,$adminId,$orderId]);
        $after=hyg_one('SELECT * FROM transfer_order_receipts WHERE order_id=?',[$orderId]);htp_event($orderId,$type,$before,$after,$adminId,$reason,$delta,$refundDelta,$account,$reference);$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
/** Row locks make duplicate reports idempotent and bind the report to an exact recipient/amount. */
function htp_report(string $number,mixed $code,mixed $quote): void {
    if(!is_string($code)||!preg_match('/^(?:hq|store|cash):\d{1,10}$/D',$code)||!is_string($quote)||!preg_match('/^[a-f0-9]{64}$/D',$quote))htp_fail('changed');
    $pdo=db();if($pdo->inTransaction())htp_fail('storage');$pdo->beginTransaction();
    try{
        $order=hyg_one('SELECT * FROM orders WHERE order_no=? FOR UPDATE',[$number]);if(!$order||!htp_customer_allowed($order))htp_fail('forbidden');
        if($order['status']==='cancelled')htp_fail('cancelled');$r=hyg_one('SELECT * FROM transfer_order_receipts WHERE order_id=? FOR UPDATE',[$order['id']]);if(!$r)htp_fail('legacy');
        if($r['ledger_only'])htp_fail('legacy');
        if(in_array($r['status'],['requested','confirmed'],true)){$pdo->commit();return;}
        if($r['status']!=='pending'||round((float)$r['amount'],2)!==round((float)$order['total_amount'],2))htp_fail('changed');
        $accounts=htp_order_accounts($order,true,true);$selected=null;foreach($accounts as $a)if(hash_equals($a['code'],$code))$selected=$a;
        if(!$selected||!hash_equals(htp_quote($order,$r,$selected),$quote))htp_fail('changed');
        $snapshot=['source'=>$selected['source'],'id'=>$selected['id'],'recipient'=>$selected['recipient'],'bank_name'=>$selected['bank_name'],'account_number'=>$selected['account_number'],'account_name'=>$selected['account_name'],'payment_method'=>$order['payment_method']];
        db()->prepare("UPDATE transfer_order_receipts SET status='requested',report_state='requested',version=version+1,recipient_json=?,qr_data=?,qr_mime=?,reported_at=UTC_TIMESTAMP() WHERE order_id=?")->execute([json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$selected['qr_data']??null,$selected['qr_mime']??null,$order['id']]);
        $after=hyg_one('SELECT * FROM transfer_order_receipts WHERE order_id=?',[$order['id']]);htp_event((int)$order['id'],'report',$r,$after,null,'',0,0,$selected);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
/** Staff confirmation is explicit and independent from fulfillment/status/game awards. */
function htp_confirm(int $orderId,mixed $expectedVersion,int $adminId): void {
    htp_staff_guard($adminId);$r=htp_receipt($orderId);if(!$r)htp_fail('legacy');
    if($r['status']==='confirmed'&&(int)$r['version']===htp_expected_version($expectedVersion))return;
    if($r['report_state']!=='requested')htp_fail('changed');
    $amount=htp_cents($r['amount'])-htp_cents($r['net_received']);if($amount<=0)htp_fail('changed');
    htp_staff_action($orderId,'payment_receive',['payment_version'=>$expectedVersion,'amount'=>htp_decimal($amount),'account_code'=>'snapshot','reason'=>$_POST['reason']??pwf('report_verified'),'transaction_reference'=>$_POST['transaction_reference']??''],$adminId);
}
function htp_error(Throwable $error): string {$key=$error instanceof TransferPaymentError?$error->getMessage():'storage';$text=pwf($key);return $text!==$key?$text:htp($key);}
require_once __DIR__.'/transfer_payment_views.php';
