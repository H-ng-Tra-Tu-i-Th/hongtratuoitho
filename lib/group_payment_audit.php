<?php
/** Group payment evidence is append-only and survives member removal. No bank operation is performed. */
function hyg_payment_audit_schema(): void {
    $key='group_payment_audit_version';$version='19.15.0';
    if(setting($key,'')===$version)return;
    $pdo=db();if($pdo->inTransaction())throw new RuntimeException('group_payment_audit_schema_inside_transaction');
    if((int)$pdo->query("SELECT GET_LOCK(CONCAT(DATABASE(),':group-payment-audit'),10)")->fetchColumn()!==1)throw new RuntimeException('group_payment_audit_schema_busy');
    try{
        $st=$pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$st->execute([$key]);
        if((string)$st->fetchColumn()===$version){$GLOBALS['__drink_order_setting_cache'][$key]=$version;return;}
        $pdo->exec(hyg_payment_event_ddl());
        foreach([['group_rooms','payment_expires_at','DATETIME NULL'],['group_members','payment_received_amount','DECIMAL(12,2) NOT NULL DEFAULT 0'],['group_members','payment_refunded_amount','DECIMAL(12,2) NOT NULL DEFAULT 0']] as [$table,$column,$definition])if(!column_exists($table,$column))$pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        $pdo->beginTransaction();
        try{
            $pdo->exec("UPDATE group_rooms SET payment_expires_at=GREATEST(expires_at,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 24 HOUR)) WHERE status='locked' AND payment_expires_at IS NULL");
            foreach(hyg_all('SELECT * FROM group_rooms ORDER BY id FOR UPDATE') as $room){
                $members=hyg_all('SELECT * FROM group_members WHERE room_id=? ORDER BY id',[$room['id']]);
                $hostId=0;foreach($members as $member)if(hash_equals((string)$room['owner_hash'],(string)$member['device_hash'])){$hostId=(int)$member['id'];break;}
                foreach($members as $member){
                    if(hyg_one('SELECT id FROM group_payment_events WHERE seed_key=?',['legacy-member-'.$member['id']]))continue;
                    $valid=$hostId>0&&(int)($room['payment_protocol_version']??1)>=2&&$member['payment_status']==='paid'&&!empty($member['payment_confirmed_at'])&&(int)($member['payment_confirmed_by_member_id']??0)===$hostId;
                    $received=$valid?hyg_money((float)($member['payment_confirmed_amount']??0)):0.0;
                    hyg_exec('UPDATE group_members SET payment_received_amount=? WHERE id=?',[$received,$member['id']]);
                    $member['payment_received_amount']=$received;
                    hyg_payment_event($room,$member,0,'legacy_snapshot',$received,'',null,$member,'legacy-member-'.$member['id']);
                }
            }
            set_setting($key,$version);$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();unset($GLOBALS['__drink_order_setting_cache'][$key]);throw $e;}
    }finally{$pdo->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(),':group-payment-audit'))");}
}
function hyg_payment_event_ddl(): string {
    return "CREATE TABLE IF NOT EXISTS group_payment_events (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,room_id BIGINT UNSIGNED NOT NULL,member_id BIGINT UNSIGNED NULL,
        actor_member_id BIGINT UNSIGNED NULL,event_type VARCHAR(32) NOT NULL,amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        reason VARCHAR(240) NOT NULL DEFAULT '',reference VARCHAR(80) NOT NULL,member_name VARCHAR(80) NOT NULL DEFAULT '',
        quote_signature CHAR(64) NULL,room_revision BIGINT UNSIGNED NOT NULL,snapshot_json LONGTEXT NOT NULL,
        seed_key VARCHAR(80) NULL UNIQUE,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_group_payment_room(room_id,id),KEY idx_group_payment_member(room_id,member_id,id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
}
function hyg_payment_reference(array $room,int $memberId): string {return 'HYG-'.strtoupper((string)$room['public_id']).'-'.$memberId;}
function hyg_payment_event(array $room,?array $member,int $actor,string $type,float $amount,string $reason='',?array $before=null,?array $after=null,?string $seed=null): void {
    if(!db()->inTransaction())throw new RuntimeException('group_payment_event_requires_transaction');
    $id=$member?(int)$member['id']:null;
    $allowed=['due','receipt','payment_expires_at','payment_method','payment_status','payment_completed_at','payment_requested_at','payment_requested_amount','payment_confirmed_at','payment_confirmed_by_member_id','payment_confirmed_amount','payment_invalidated_at','payment_invalidation_reason','payment_received_amount','payment_refunded_amount'];
    $settlement=json_decode((string)($room['settlement_json']??''),true);
    $snapshot=['before'=>$before===null?null:array_intersect_key($before,array_flip($allowed)),'after'=>$after===null?null:array_intersect_key($after,array_flip($allowed)),
        'collection_mode'=>$room['collection_mode']??$settlement['payment']['collection_mode']??'store','due_context'=>$settlement['payment']['members'][$id??0]['due']??null];
    hyg_exec('INSERT INTO group_payment_events(room_id,member_id,actor_member_id,event_type,amount,reason,reference,member_name,quote_signature,room_revision,snapshot_json,seed_key,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())',[
        $room['id'],$id,$actor?:null,$type,round($amount,2),$reason,$id?hyg_payment_reference($room,$id):'HYG-'.strtoupper($room['public_id']),$member['nickname']??'',
        $room['lock_signature']??null,$room['revision'],json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR),$seed]);
}
function hyg_payment_events(array $room,?int $memberId=null): array {
    return hyg_all('SELECT id,member_id,actor_member_id,event_type,amount,reason,reference,member_name,created_at FROM group_payment_events WHERE room_id=?'.($memberId!==null?' AND member_id=?':'').' ORDER BY id DESC LIMIT 100',$memberId!==null?[$room['id'],$memberId]:[$room['id']]);
}
function hyg_payment_reason(array $data): string {$reason=hyg_text($data['reason']??'',240);if(preg_match_all('/./us',$reason)<3)hyg_fail('payment_reason_required');return $reason;}
function hyg_payment_input_amount(mixed $value): float {
    if(!is_string($value)||!preg_match('/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/D',$value))hyg_fail('invalid_input');
    return hyg_money((float)$value);
}
function hyg_payment_control_quote(array $room,array $data): void {
    if(hyg_positive_int($data['revision']??null)!==(int)$room['revision']||!hyg_payment_quote_signature_valid($room,$data['quote_signature']??null))hyg_fail('changed_reload');
}
/** Correct only the recorded receipt; this action never sends or refunds money. */
function hyg_payment_control(array $room,array $actor,string $action,array $data): void {
    hyg_payment_control_quote($room,$data);$reason=hyg_payment_reason($data);
    $quote=hyg_locked_quote($room,true);$target=hyg_one('SELECT * FROM group_members WHERE id=? AND room_id=?',[hyg_positive_int($data['member_id']??null),$room['id']]);
    if(!$target)hyg_fail('not_member');$person=hyg_member_payment($quote['payment'],(int)$target['id']);
    if(!$person||(int)$person['payer_member_id']!==(int)$target['id'])hyg_fail('payment_no_amount');
    $net=hyg_member_ledger_amounts($target)['net_received'];$amount=0.0;
    if($action==='payment_reject'){
        if($person['status']!=='requested'||($target['payment_status']!=='requested'&&$net>0))hyg_fail('payment_reject_received');
        hyg_exec("UPDATE group_members SET payment_status='rejected',payment_invalidation_reason='rejected',payment_invalidated_at=UTC_TIMESTAMP() WHERE id=?",[$target['id']]);
    }elseif($action==='payment_refund'){
        if(($data['refund_completed']??'')!=='1')hyg_fail('payment_refund_ack_required');
        $amount=hyg_payment_input_amount($data['amount']??null);if($amount<=0||$amount>$net)hyg_fail('payment_refund_invalid');
        if($target['payment_status']==='requested'&&!$person['amount_changed']&&(float)($person['requested_amount']??0)>0)hyg_fail('payment_refund_pending_report');
        hyg_exec("UPDATE group_members SET payment_status='refunded',payment_refunded_amount=payment_refunded_amount+?,payment_invalidated_at=UTC_TIMESTAMP(),payment_invalidation_reason='refunded' WHERE id=?",[$amount,$target['id']]);
        // Returning only an overpayment restores a previous matching confirmation without collecting again.
        if((float)$person['due']>0&&hyg_money($net-$amount)===hyg_money((float)$person['due'])&&!empty($target['payment_confirmed_at'])&&(int)$target['payment_confirmed_by_member_id']===(int)$quote['payment']['host_member_id'])
            hyg_exec("UPDATE group_members SET payment_status='paid',payment_confirmed_amount=? WHERE id=?",[$person['due'],$target['id']]);
    }elseif($action==='payment_correct'){
        if(($data['correction_ack']??'')!=='1')hyg_fail('payment_correction_ack_required');
        if($target['payment_status']==='requested'&&!$person['amount_changed']&&(float)($person['requested_amount']??0)>0)hyg_fail('payment_correction_pending_report');
        $correctionTarget=$data['correction_target']??'received';if(!is_string($correctionTarget)||!in_array($correctionTarget,['received','refunded'],true))hyg_fail('invalid_input');
        $corrected=hyg_payment_input_amount($data['amount']??null);$received=(float)$target['payment_received_amount'];$refunded=(float)$target['payment_refunded_amount'];
        if(empty($target['payment_confirmed_at']))hyg_fail('payment_correction_invalid');
        if($correctionTarget==='received'){
            if($corrected<$refunded||$corrected>=$received)hyg_fail('payment_correction_invalid');$amount=$corrected-$received;
            hyg_exec("UPDATE group_members SET payment_status='rejected',payment_received_amount=?,payment_invalidated_at=UTC_TIMESTAMP(),payment_invalidation_reason='corrected' WHERE id=?",[$corrected,$target['id']]);
        }else{
            if($corrected>$received||$corrected>=$refunded)hyg_fail('payment_refund_correction_invalid');$amount=$corrected-$refunded;
            hyg_exec("UPDATE group_members SET payment_status='rejected',payment_refunded_amount=?,payment_invalidated_at=UTC_TIMESTAMP(),payment_invalidation_reason='refund_corrected' WHERE id=?",[$corrected,$target['id']]);
            if((float)$person['due']>0&&hyg_money($received-$corrected)===hyg_money((float)$person['due'])&&(int)$target['payment_confirmed_by_member_id']===(int)$quote['payment']['host_member_id'])
                hyg_exec("UPDATE group_members SET payment_status='paid',payment_confirmed_amount=? WHERE id=?",[$person['due'],$target['id']]);
        }
    }else hyg_fail('invalid_input');
    $after=hyg_one('SELECT * FROM group_members WHERE id=?',[$target['id']]);
    $eventType=$action==='payment_correct'&&($correctionTarget??'received')==='refunded'?'correct_refund':substr($action,8);
    hyg_payment_event($room,$target,(int)$actor['id'],$eventType,$amount,$reason,$target,$after);
}
