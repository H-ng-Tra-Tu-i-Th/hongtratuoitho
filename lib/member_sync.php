<?php
/** Only these two server-side GET endpoints are supported. No iPOS write path exists. */
function hym_config_path(): string { return defined('HYM_CONFIG_FILE')?(string)HYM_CONFIG_FILE:dirname(__DIR__).'/config.member.local.php'; }
function hym_config_defaults(): array {
    return ['enabled'=>false,'base_url'=>'https://api.foodbook.vn','pos_parent'=>'','token'=>'','transactions_enabled'=>false,'allowed_sources'=>[],
        'source_timezone'=>'Asia/Ho_Chi_Minh'];
}
function hym_validate_config(array $input): array {
    $config=hym_config_defaults();
    foreach(['enabled','transactions_enabled'] as $key)$config[$key]=!empty($input[$key]);
    $config['base_url']=rtrim((string)($input['base_url']??$config['base_url']),'/');
    if($config['base_url']!=='https://api.foodbook.vn')hym_fail('member_config_invalid');
    $config['pos_parent']=trim((string)($input['pos_parent']??''));
    if($config['pos_parent']!==''&&!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$config['pos_parent']))hym_fail('member_config_invalid');
    $config['token']=trim((string)($input['token']??''));
    if(strlen($config['token'])>2048||preg_match('/[\x00-\x20\x7F]/',$config['token']))hym_fail('member_config_invalid');
    $config['source_timezone']=(string)($input['source_timezone']??$config['source_timezone']);
    if(!in_array($config['source_timezone'],DateTimeZone::listIdentifiers(),true))hym_fail('member_config_invalid');
    $sources=$input['allowed_sources']??[];
    if(!is_array($sources)||count($sources)>100)hym_fail('member_config_invalid');
    $seen=[];
    foreach($sources as $source){
        if(!is_array($source))hym_fail('member_config_invalid');
        $row=[];
        foreach(['channel','source','sale_type'] as $key){
            if(!isset($source[$key])||!is_scalar($source[$key]))hym_fail('member_config_invalid');
            $value=trim((string)$source[$key]);
            if($value===''||strlen($value)>100||preg_match('/[\x00-\x1F\x7F]/',$value))hym_fail('member_config_invalid');
            $row[$key]=$value;
        }
        $signature=json_encode($row,JSON_THROW_ON_ERROR);
        if(!isset($seen[$signature])){$config['allowed_sources'][]=$row;$seen[$signature]=true;}
    }
    if($config['transactions_enabled']&&!$config['allowed_sources'])hym_fail('member_config_invalid');
    return $config;
}
function hym_private_config(): array {
    if(isset($GLOBALS['hym_private_config_cache']))return $GLOBALS['hym_private_config_cache'];
    $path=hym_config_path();$input=[];
    if(is_file($path)){
        try{$input=(static function(string $file){return require $file;})($path);}catch(Throwable $e){hym_fail('member_config_invalid');}
        if(!is_array($input))hym_fail('member_config_invalid');
    }
    return $GLOBALS['hym_private_config_cache']=hym_validate_config($input);
}
/** Safe for templates and admin forms: the credential itself is never returned. */
function hym_config(): array {
    $config=hym_private_config();$config['token_configured']=$config['token']!=='';unset($config['token']);return $config;
}
function hym_save_config(array $input): void {
    if(!hym_staff())hym_fail('member_login_failed');
    $old=hym_private_config();
    $next=array_replace($old,$input);
    if(!array_key_exists('token',$input)||trim((string)$input['token'])==='')$next['token']=$old['token'];
    if(!empty($input['clear_token']))$next['token']='';
    $config=hym_validate_config($next);$path=hym_config_path();
    // Keep even the temporary credential file behind PHP execution, never a text download.
    $temp=dirname($path).'/.member-config-'.bin2hex(random_bytes(16)).'.php';
    $handle=@fopen($temp,'xb');
    if($handle===false)hym_fail('member_config_save_failed');
    try{
        @chmod($temp,0600);
        $content="<?php\n// Local server-only iPOS settings. Never include in deployment archives.\nreturn ".var_export($config,true).";\n";
        $written=0;
        while($written<strlen($content)){$part=fwrite($handle,substr($content,$written));if($part===false||$part===0)hym_fail('member_config_save_failed');$written+=$part;}
        if(!fflush($handle))hym_fail('member_config_save_failed');
        fclose($handle);$handle=null;
        if(!@rename($temp,$path))hym_fail('member_config_save_failed');
        @chmod($path,0600);if(function_exists('opcache_invalidate'))@opcache_invalidate($path,true);
        unset($GLOBALS['hym_private_config_cache']);
    }finally{if(is_resource($handle))fclose($handle);if(is_file($temp))@unlink($temp);}
}
function hym_config_fingerprint(array $config): string { return hash('sha256',json_encode($config,JSON_THROW_ON_ERROR)); }
function hym_number(mixed $value): float {
    if(!is_int($value)&&!is_float($value)&&!is_string($value))hym_fail('member_sync_failed');
    if(!is_numeric($value)||!is_finite((float)$value)||abs((float)$value)>999999999999.0)hym_fail('member_sync_failed');
    return (float)$value;
}
function hym_source_text(mixed $value,int $max): string {
    if(!is_string($value)&&!is_int($value))hym_fail('member_sync_failed');
    try{return hym_text((string)$value,$max);}catch(Throwable $e){hym_fail('member_sync_failed');}
}
function hym_external_id(mixed $value): string {
    if($value===null||$value===''||$value===0||$value==='0')return '';
    return hym_source_text($value,128);
}
function hym_api_response_ok(array $response): bool {
    if(!empty($response['error'])||!empty($response['errors'])||!empty($response['error_code']))return false;
    if(array_key_exists('code',$response)&&!in_array($response['code'],[0,'0',200,'200'],true))return false;
    if(array_key_exists('success',$response)&&!in_array($response['success'],[true,1,'1'],true))return false;
    if(array_key_exists('status',$response)&&!in_array($response['status'],[true,1,'1',200,'200','ok','success'],true))return false;
    return true;
}
function hym_parse_profile(array $response,string $phone,string $brand): ?array {
    if(!array_key_exists('data',$response)||!hym_api_response_ok($response))hym_fail('member_sync_failed');
    $row=$response['data'];
    if($row===null||$row===[])return null;
    if(!is_array($row)||array_is_list($row))hym_fail('member_sync_failed');
    try{$reportedPhone=hym_normalize_phone((string)($row['phone_number']??''));}catch(Throwable $e){hym_fail('member_sync_failed');}
    if(!hash_equals($phone,$reportedPhone)||(string)($row['pos_parent']??'')!==$brand)hym_fail('member_sync_failed');
    $birthday='';
    if(is_string($row['birthday']??null)&&preg_match('/^(\d{4})-(\d{2})-(\d{2})/',$row['birthday'],$parts)&&checkdate((int)$parts[2],(int)$parts[3],(int)$parts[1]))$birthday=substr($row['birthday'],0,10);
    $externalId=hym_external_id($row['membership_id_new']??null);
    if($externalId==='')$externalId=hym_external_id($row['id']??null);
    return ['external_member_id'=>$externalId,'name'=>hym_source_text($row['name']??'',100),
        'birthday'=>$birthday,'tier_name'=>hym_source_text($row['membership_type_name']??'',120),
        'points'=>isset($row['point'])?hym_number($row['point']):null];
}
function hym_transaction_allowed(array $row,array $allowed): bool {
    $channels=$row['channels']??null;$type=$row['sale_type']??null;
    if(!is_array($channels)||!array_is_list($channels)||!$channels||!is_string($type))return false;
    foreach($channels as $channel){
        if(!is_array($channel)||!isset($channel['channel'],$channel['source'])||!is_scalar($channel['channel'])||!is_scalar($channel['source']))return false;
        $found=false;
        foreach($allowed as $entry)if((string)$channel['channel']===$entry['channel']&&(string)$channel['source']===$entry['source']&&$type===$entry['sale_type']){$found=true;break;}
        if(!$found)return false;
    }
    return true;
}
function hym_transaction_owned(array $row,string $phone,string $externalId): bool {
    if(in_array($row['is_phone_number']??null,[1,'1',true],true)){
        try{return hash_equals($phone,hym_normalize_phone((string)($row['membership_id']??'')));}catch(Throwable $e){return false;}
    }
    return $externalId!==''&&hym_external_id($row['membership_id_new']??null)===$externalId;
}
function hym_transaction_key(string $brand,string $posId,string $tranId): string {
    return hash('sha256',json_encode([$brand,$posId,$tranId],JSON_THROW_ON_ERROR));
}
function hym_source_date(mixed $value,string $timezone): string {
    if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:?\d{2})?)?$/D',$value))hym_fail('member_sync_failed');
    try{$date=new DateTimeImmutable($value,new DateTimeZone($timezone));$errors=DateTimeImmutable::getLastErrors();
        if($errors!==false&&($errors['warning_count']||$errors['error_count']))hym_fail('member_sync_failed');
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }catch(Throwable $e){hym_fail('member_sync_failed');}
}
function hym_parse_transaction(array $row,array $config): array {
    $brand=hym_source_text($row['pos_parent']??'',100);$pos=hym_source_text($row['pos_id']??'',100);$tran=hym_source_text($row['tran_id']??'',128);
    if($brand!==$config['pos_parent']||$pos===''||$tran==='')hym_fail('member_sync_failed');
    $items=$row['items']??null;
    if(!is_array($items)||!array_is_list($items)||count($items)>500)hym_fail('member_sync_failed');
    $safeItems=[];
    foreach($items as $item){
        if(!is_array($item))hym_fail('member_sync_failed');
        $safeItems[]=['name'=>hym_source_text($item['item_name']??'',255),'quantity'=>hym_number($item['quantity']??null),
            'unit_price'=>hym_number($item['price']??null),'amount'=>hym_number($item['amount']??null)];
    }
    $channels=[];
    foreach($row['channels'] as $channel)$channels[]=['channel'=>hym_source_text($channel['channel'],100),'source'=>hym_source_text($channel['source'],100)];
    return ['source_key'=>hym_transaction_key($brand,$pos,$tran),'pos_parent'=>$brand,'pos_id'=>$pos,'tran_id'=>$tran,
        'occurred_at'=>hym_source_date($row['tran_date']??null,$config['source_timezone']),'pos_name'=>hym_source_text($row['pos_name']??'',160),
        'reported_amount'=>hym_number($row['total_amount']??null),'currency'=>'VND','items'=>$safeItems,'source_channels'=>$channels,
        'sale_type'=>hym_source_text($row['sale_type']??'',32)];
}
/** Injectable decoded-response transport for isolated tests; it is never populated from HTTP input. */
function hym_api_get(string $path,array $query,array $config,float $deadline): array {
    if(!in_array($path,['/ipos/ws/xpartner/membership_detail','/ipos/ws/xpartner/member_transactions'],true))hym_fail('member_sync_failed');
    if(($config['base_url']??'')!=='https://api.foodbook.vn')hym_fail('member_sync_failed');
    if(microtime(true)>=$deadline)hym_fail('member_sync_failed');
    if(isset($GLOBALS['hym_api_transport'])&&is_callable($GLOBALS['hym_api_transport'])){
        try{$result=($GLOBALS['hym_api_transport'])($path,$query,$config);}catch(Throwable $e){hym_fail('member_sync_failed');}
        if(!is_array($result))hym_fail('member_sync_failed');return $result;
    }
    if(!function_exists('curl_init'))hym_fail('member_sync_failed');
    $url='https://api.foodbook.vn'.$path.'?'.http_build_query(['access_token'=>$config['token']]+$query,'','&',PHP_QUERY_RFC3986);
    $body='';$curl=curl_init($url);
    if($curl===false)hym_fail('member_sync_failed');
    try{
        curl_setopt_array($curl,[CURLOPT_HTTPGET=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>3,
            CURLOPT_TIMEOUT=>max(1,min(10,(int)ceil($deadline-microtime(true)))),CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_USERAGENT=>'HuiyiTea-Member-ReadOnly/19.4',
            CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>2097152)return 0;$body.=$chunk;return strlen($chunk);}]);
        if(curl_exec($curl)===false||(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE)!==200)hym_fail('member_sync_failed');
        try{$result=json_decode($body,true,64,JSON_THROW_ON_ERROR|JSON_BIGINT_AS_STRING);}catch(Throwable $e){hym_fail('member_sync_failed');}
        if(!is_array($result))hym_fail('member_sync_failed');return $result;
    }finally{curl_close($curl);}
}
function hym_fetch_transactions(array $config,string $phone,string $externalId,float $deadline): array {
    $staged=[];$pageHashes=[];$count=0;
    for($page=1;$page<=50;$page++){
        $response=hym_api_get('/ipos/ws/xpartner/member_transactions',['pos_parent'=>$config['pos_parent'],'user_id'=>$phone,'page'=>$page],$config,$deadline);
        if(!hym_api_response_ok($response)||!is_array($response['data']??null)||!array_key_exists('is_next',$response))hym_fail('member_sync_failed');
        $rows=$response['data']['sale_logs']??null;
        if(!is_array($rows)||!array_is_list($rows)||count($rows)>1000)hym_fail('member_sync_failed');
        $hash=hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
        if(isset($pageHashes[$hash])&&$rows)hym_fail('member_sync_failed');$pageHashes[$hash]=true;
        foreach($rows as $row){
            if(++$count>10000||!is_array($row)||!hym_transaction_owned($row,$phone,$externalId))hym_fail('member_sync_failed');
            if(!hym_transaction_allowed($row,$config['allowed_sources']))continue;
            $safe=hym_parse_transaction($row,$config);$key=$safe['source_key'];
            if(isset($staged[$key])&&$staged[$key]!==$safe)hym_fail('member_sync_failed');
            $staged[$key]=$safe;
        }
        if(in_array($response['is_next'],[0,'0',false],true))return array_values($staged);
        if(!in_array($response['is_next'],[1,'1',true],true)||!$rows)hym_fail('member_sync_failed');
    }
    hym_fail('member_sync_failed');
}
function hym_sync_state_row(int $id): array {
    $st=db()->prepare('SELECT status,message_key,last_attempt_at,last_success_at,config_fingerprint FROM member_sync_states WHERE member_id=?');$st->execute([$id]);
    return $st->fetch()?:['status'=>'never','message_key'=>'member_sync_never','last_attempt_at'=>null,'last_success_at'=>null,'config_fingerprint'=>''];
}
function hym_state_safe(array $state): array { return array_intersect_key($state,array_flip(['status','message_key','last_attempt_at','last_success_at'])); }
function hym_sync_state(int $id): array {
    if(!hym_can_access($id))return ['status'=>'never','message_key'=>'member_sync_never','last_attempt_at'=>null,'last_success_at'=>null];
    $member=hym_member_row($id);$state=hym_sync_state_row($id);
    if(!$member||!$member['phone_verified_at'])return array_replace(hym_state_safe($state),['status'=>'verification_required','message_key'=>'member_sync_verification_required']);
    $config=hym_config();
    if(!$config['enabled']||!$config['token_configured']||$config['pos_parent']==='')return array_replace(hym_state_safe($state),['status'=>'not_configured','message_key'=>'member_sync_not_configured']);
    // A previous brand or source policy must not be presented as a current successful import.
    if($state['config_fingerprint']!==''&&$state['config_fingerprint']!==hym_config_fingerprint(hym_private_config())){
        $visible=array_replace(hym_state_safe($state),['last_success_at'=>null]);
        if($state['status']==='synced')$visible=array_replace($visible,['status'=>'never','message_key'=>'member_sync_never']);
        return $visible;
    }
    return hym_state_safe($state);
}
function hym_profile(int $id): ?array {
    if(!hym_can_access($id)||!($member=hym_member_row($id))||!$member['phone_verified_at'])return null;
    $private=hym_private_config();if(!$private['enabled']||$private['pos_parent']==='')return null;
    $scope=hym_sync_state_row($id);if($scope['config_fingerprint']!==hym_config_fingerprint($private))return null;
    $config=hym_config();
    $st=db()->prepare('SELECT name,birthday,tier_name,points,last_synced_at FROM member_ipos_profiles WHERE member_id=? AND source_brand=?');$st->execute([$id,$config['pos_parent']]);
    $row=$st->fetch();if(!$row)return null;
    if($row['points']!==null)$row['points']=(float)$row['points'];return $row;
}
function hym_store_transactions(int $id): array {
    if(!hym_can_access($id)||!($member=hym_member_row($id))||!$member['phone_verified_at'])return [];
    $private=hym_private_config();if(!$private['enabled']||!$private['transactions_enabled'])return [];
    $scope=hym_sync_state_row($id);if($scope['config_fingerprint']!==hym_config_fingerprint($private))return [];
    $config=hym_config();
    $st=db()->prepare('SELECT source_key,pos_parent,pos_id,tran_id,occurred_at,pos_name,reported_amount,currency,items_json,source_channels_json,sale_type,last_synced_at FROM member_ipos_transactions WHERE member_id=? AND pos_parent=? ORDER BY occurred_at DESC,id DESC');
    $st->execute([$id,$config['pos_parent']]);$rows=[];
    foreach($st->fetchAll() as $row){
        $row['items']=json_decode($row['items_json'],true);$row['source_channels']=json_decode($row['source_channels_json'],true);
        if(!is_array($row['items'])||!is_array($row['source_channels'])||!hym_transaction_allowed(['channels'=>$row['source_channels'],'sale_type'=>$row['sale_type']],$config['allowed_sources']))continue;
        unset($row['items_json'],$row['source_channels_json']);$row['reported_amount']=(float)$row['reported_amount'];$rows[]=$row;
    }
    return $rows;
}
function hym_sync_member(int $id,bool $force=false): array {
    if(!hym_can_access($id))hym_fail('member_login_failed');
    $member=hym_member_row($id);if(!$member)hym_fail('member_invalid_profile');
    if(!$member['phone_verified_at'])return hym_sync_state($id);
    $config=hym_private_config();
    if(!$config['enabled']||$config['token']===''||$config['pos_parent']==='')return hym_sync_state($id);
    $pdo=db();if($pdo->inTransaction())hym_fail('member_sync_failed');
    $lock='hym-sync:'.substr(hash('sha256',(string)$pdo->query('SELECT DATABASE()')->fetchColumn().'|'.$id),0,40);
    $get=$pdo->prepare('SELECT GET_LOCK(?,0)');$get->execute([$lock]);
    if((int)$get->fetchColumn()!==1)return array_replace(hym_sync_state($id),['status'=>'busy','message_key'=>'member_sync_busy']);
    try{
        $state=hym_sync_state_row($id);$fingerprint=hym_config_fingerprint($config);$now=time();
        $attempt=$state['last_attempt_at']?strtotime($state['last_attempt_at'].' UTC'):0;
        $success=$state['last_success_at']?strtotime($state['last_success_at'].' UTC'):0;
        if($attempt&&$now-$attempt<60)return $force?array_replace(hym_state_safe($state),['status'=>'limited','message_key'=>'member_sync_limited']):hym_state_safe($state);
        if(!$force&&$success&&$now-$success<300&&$state['status']==='synced'&&$state['config_fingerprint']===$fingerprint)return array_replace(hym_state_safe($state),['message_key'=>'member_sync_cached']);
        $stamp=gmdate('Y-m-d H:i:s');
        $mark=$pdo->prepare("INSERT INTO member_sync_states(member_id,last_attempt_at) VALUES(?,?) ON DUPLICATE KEY UPDATE last_attempt_at=VALUES(last_attempt_at)");$mark->execute([$id,$stamp]);
        try{
            $deadline=microtime(true)+45;
            $response=hym_api_get('/ipos/ws/xpartner/membership_detail',['pos_parent'=>$config['pos_parent'],'user_id'=>$member['phone']],$config,$deadline);
            $profile=hym_parse_profile($response,$member['phone'],$config['pos_parent']);
            if($profile===null){
                // Keep the successful cache's scope: a not-found lookup cannot bless old data under new settings.
                $mark=$pdo->prepare("UPDATE member_sync_states SET status='not_found',message_key='member_sync_not_found' WHERE member_id=?");$mark->execute([$id]);
                return hym_sync_state($id);
            }
            $transactions=$config['transactions_enabled']?hym_fetch_transactions($config,$member['phone'],$profile['external_member_id'],$deadline):null;
            // Remote IO is complete. Local profile + the entire staged bill snapshot commit together.
            $pdo->beginTransaction();
            $check=$pdo->prepare('SELECT phone,phone_verified_at FROM members WHERE id=? FOR UPDATE');$check->execute([$id]);$current=$check->fetch();
            if(!$current||!$current['phone_verified_at']||$current['phone']!==$member['phone'])hym_fail('member_sync_failed');
            $write=$pdo->prepare('INSERT INTO member_ipos_profiles(member_id,source_brand,external_member_id,name,birthday,tier_name,points,last_synced_at) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE source_brand=VALUES(source_brand),external_member_id=VALUES(external_member_id),name=VALUES(name),birthday=VALUES(birthday),tier_name=VALUES(tier_name),points=VALUES(points),last_synced_at=VALUES(last_synced_at)');
            $write->execute([$id,$config['pos_parent'],$profile['external_member_id'],$profile['name'],$profile['birthday'],$profile['tier_name'],$profile['points'],$stamp]);
            if($transactions!==null){
                $delete=$pdo->prepare('DELETE FROM member_ipos_transactions WHERE member_id=?');$delete->execute([$id]);
                $write=$pdo->prepare('INSERT INTO member_ipos_transactions(member_id,source_key,pos_parent,pos_id,tran_id,occurred_at,pos_name,reported_amount,currency,items_json,source_channels_json,sale_type,last_synced_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
                foreach($transactions as $row)$write->execute([$id,$row['source_key'],$row['pos_parent'],$row['pos_id'],$row['tran_id'],$row['occurred_at'],$row['pos_name'],$row['reported_amount'],$row['currency'],json_encode($row['items'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),json_encode($row['source_channels'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$row['sale_type'],$stamp]);
            }
            $key=$transactions===null?'member_sync_profile_only':'member_sync_synced';
            $mark=$pdo->prepare("UPDATE member_sync_states SET status='synced',message_key=?,last_success_at=?,config_fingerprint=? WHERE member_id=?");$mark->execute([$key,$stamp,$fingerprint,$id]);
            $pdo->commit();return hym_sync_state($id);
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            $mark=$pdo->prepare("UPDATE member_sync_states SET status='error',message_key='member_sync_failed' WHERE member_id=?");$mark->execute([$id]);
            return hym_sync_state($id);
        }
    }finally{$release=$pdo->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$lock]);}
}
