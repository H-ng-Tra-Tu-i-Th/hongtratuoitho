<?php
/** Run manually or via cPanel cron. No credentials or member details are printed. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('HYM_SYNC_CLI',true);
require_once __DIR__.'/../lib/bootstrap.php';
$options=getopt('',['member:','limit:','force']);
$id=isset($options['member'])?filter_var($options['member'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):null;
$limit=isset($options['limit'])?filter_var($options['limit'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000]]):100;
if($id===false||$limit===false){fwrite(STDERR,"Invalid sync options.\n");exit(2);}
try{
    $sql='SELECT m.id FROM members m LEFT JOIN member_sync_states s ON s.member_id=m.id WHERE m.phone_verified_at IS NOT NULL';
    if($id!==null)$sql.=' AND m.id=?';
    $sql.=' ORDER BY COALESCE(s.last_attempt_at,m.created_at),m.id LIMIT '.(int)$limit;
    $st=db()->prepare($sql);$st->execute($id===null?[]:[$id]);$ids=$st->fetchAll(PDO::FETCH_COLUMN);$summary=[];
    foreach($ids as $memberId){$state=hym_sync_member((int)$memberId,array_key_exists('force',$options));$status=$state['status'];$summary[$status]=($summary[$status]??0)+1;}
    echo json_encode(['processed'=>count($ids),'states'=>$summary],JSON_THROW_ON_ERROR).PHP_EOL;
    exit(isset($summary['error'])?1:0);
}catch(Throwable $e){fwrite(STDERR,"Member synchronization unavailable.\n");exit(1);}
