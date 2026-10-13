<?php
/** Local reward ownership. CRM points and coupons never enter these wallets. */
function hym_reward_schema(): void {
    static $done=false;if($done)return;$pdo=db();
    if($pdo->inTransaction())throw new RuntimeException('member_rewards_schema_inside_transaction');
    $get=$pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$get->execute(['member_rewards_schema_version']);
    if((string)$get->fetchColumn()!=='19.6.0'){
        $lock='hym-rewards:'.substr(hash('sha256',(string)$pdo->query('SELECT DATABASE()')->fetchColumn()),0,40);
        $st=$pdo->prepare('SELECT GET_LOCK(?,10)');$st->execute([$lock]);if((int)$st->fetchColumn()!==1)throw new RuntimeException('member_rewards_schema_busy');
        try{
            $get->execute(['member_rewards_schema_version']);
            if((string)$get->fetchColumn()!=='19.6.0'){
                $sql=file_get_contents(__DIR__.'/member_rewards_schema.sql');if($sql===false)throw new RuntimeException('member_rewards_schema_missing');
                foreach(explode(';',$sql) as $statement)if(trim($statement)!=='')$pdo->exec($statement);
                foreach([['member_id','BIGINT UNSIGNED NULL'],['claimed_member_id','BIGINT UNSIGNED NULL'],['claimed_wallet_token','VARCHAR(64) NULL'],['claimed_at','DATETIME NULL'],['claimed_chances','INT NOT NULL DEFAULT 0'],['claimed_earned','INT NOT NULL DEFAULT 0'],['claimed_played','INT NOT NULL DEFAULT 0']] as [$column,$definition]){
                    if(!column_exists('game_wallets',$column))$pdo->exec("ALTER TABLE game_wallets ADD COLUMN `$column` $definition");
                }
                $index=$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='game_wallets' AND INDEX_NAME='uq_game_wallet_member'")->fetchColumn();
                if(!(int)$index)$pdo->exec('ALTER TABLE game_wallets ADD UNIQUE KEY uq_game_wallet_member(member_id)');
                $mark=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');$mark->execute(['member_rewards_schema_version','19.6.0']);
            }
        }finally{$release=$pdo->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$lock]);}
    }
    $done=true;
}
function hym_reward_token_valid(mixed $token): bool { return is_string($token)&&preg_match('/^[a-f0-9]{32}$/D',$token)===1; }
function hym_reward_one(string $sql,array $params=[]): ?array { $st=db()->prepare($sql);$st->execute($params);return $st->fetch()?:null; }
function hym_reward_exec(string $sql,array $params=[]): void { $st=db()->prepare($sql);$st->execute($params); }
function hym_reward_current_member_id(): int { $member=function_exists('hym_current_member')?hym_current_member():null;return $member?(int)$member['id']:0; }
/** Internal canonical lookup; trusted order ownership may differ from the current staff session. */
function hym_reward_member_wallet(int $memberId,bool $create=false,bool $lock=false): string {
    if($memberId<1||!hym_reward_one('SELECT id FROM members WHERE id=?',[$memberId]))return '';
    // The unique member key serializes creation across devices.
    // Insert before a locking read so two new wallets cannot deadlock on an empty index gap.
    if($create)hym_reward_exec('INSERT INTO game_wallets(wallet_token,member_id) VALUES(?,?) ON DUPLICATE KEY UPDATE member_id=member_id',[bin2hex(random_bytes(16)),$memberId]);
    $row=hym_reward_one('SELECT wallet_token FROM game_wallets WHERE member_id=?'.($lock?' FOR UPDATE':''),[$memberId]);
    if(!$row&&$create)throw new RuntimeException('member_rewards_wallet_missing');return $row?(string)$row['wallet_token']:'';
}
function hym_reward_guest_session_token(): string {
    // Login can claim only a wallet previously bound to this server session.
    $token=$_SESSION['game_wallet_token']??'';return hym_reward_token_valid($token)?$token:'';
}
/** Single-claim transfer. The session identity is the sole authority for the recipient. */
function hym_reward_claim_guest(string $guestToken): bool {
    $memberId=hym_reward_current_member_id();if(!$memberId||!hym_reward_token_valid($guestToken)||!hash_equals(hym_reward_guest_session_token(),$guestToken))return false;
    $pdo=db();$own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();
    try{
        // Guest before canonical is the common lock order for claims and pending guest awards.
        hym_reward_exec('INSERT INTO game_wallets(wallet_token) VALUES(?) ON DUPLICATE KEY UPDATE wallet_token=wallet_token',[$guestToken]);
        $guest=hym_reward_one('SELECT * FROM game_wallets WHERE wallet_token=? FOR UPDATE',[$guestToken]);
        if(!$guest||$guest['member_id']!==null||$guest['claimed_member_id']!==null||$guest['claimed_wallet_token']!==null){if($own)$pdo->commit();return false;}
        $target=hym_reward_member_wallet($memberId,true,true);
        hym_reward_exec('UPDATE game_wallets SET available_chances=available_chances+?,total_earned=total_earned+?,total_played=total_played+? WHERE wallet_token=?',[(int)$guest['available_chances'],(int)$guest['total_earned'],(int)$guest['total_played'],$target]);
        hym_reward_exec('UPDATE customer_coupons SET wallet_token=? WHERE wallet_token=?',[$target,$guestToken]);
        hym_reward_exec('UPDATE game_plays SET wallet_token=? WHERE wallet_token=?',[$target,$guestToken]);
        hym_reward_exec('UPDATE game_wallets SET claimed_member_id=?,claimed_wallet_token=?,claimed_at=UTC_TIMESTAMP(),claimed_chances=available_chances,claimed_earned=total_earned,claimed_played=total_played,available_chances=0,total_earned=0,total_played=0 WHERE wallet_token=?',[$memberId,$target,$guestToken]);
        if($own)$pdo->commit();return true;
    }catch(Throwable $e){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
}
/** Check a supplied token against the active account/device and the locked ownership row. */
function hym_reward_wallet_accessible(string $token,bool $lock=false): bool {
    if(!hym_reward_token_valid($token))return false;$memberId=hym_reward_current_member_id();
    if($memberId){if(!hash_equals(hym_reward_member_wallet($memberId,false),$token))return false;}
    elseif(!hash_equals(hym_reward_guest_session_token(),$token))return false;
    $row=hym_reward_one('SELECT member_id,claimed_member_id,claimed_wallet_token FROM game_wallets WHERE wallet_token=?'.($lock?' FOR UPDATE':''),[$token]);
    if(!$row)return !$memberId&&!$lock;
    return $memberId?(int)($row['member_id']??0)===$memberId&&$row['claimed_member_id']===null&&$row['claimed_wallet_token']===null:
        $row['member_id']===null&&$row['claimed_member_id']===null&&$row['claimed_wallet_token']===null;
}
/** Clear all reward selections on an account boundary; member tokens never become device cookies. */
function hym_reward_clear_session(): void {
    unset($_SESSION['customer_payment_orders'],$_SESSION['checkout_drafts'],$_SESSION['checkout_requests'],$_SESSION['checkout_errors'],$_SESSION['transfer_payment_flash']);
    unset($_SESSION['game_wallet_token'],$_SESSION['game_wallet_scope'],$_SESSION['game_wallet_claim_member'],$_SESSION['coupon_code'],$_SESSION['last_game_coupon_code'],$_SESSION['last_game_coupon_amount'],$_SESSION['last_order_no'],$_SESSION['last_order_promotion'],$_SESSION['last_order_expected_game_chances'],$_SESSION['last_order_eligible_drinks']);
    $_SESSION['promotion_mode']='none';
}
/** Caller holds the order transaction. Award destination follows the saved order, never the active session. */
function hym_reward_award_target(array $order): string {
    if(!db()->inTransaction())throw new RuntimeException('member_rewards_award_requires_transaction');
    $memberId=(int)($order['member_id']??0);if($memberId>0)return hym_reward_member_wallet($memberId,true,true);
    $token=(string)($order['game_wallet_token']??'');if(!hym_reward_token_valid($token))return '';
    hym_reward_exec('INSERT INTO game_wallets(wallet_token) VALUES(?) ON DUPLICATE KEY UPDATE wallet_token=wallet_token',[$token]);
    $source=hym_reward_one('SELECT * FROM game_wallets WHERE wallet_token=? FOR UPDATE',[$token]);
    if(!$source)return '';
    if($source['claimed_member_id']!==null){
        $canonical=hym_reward_member_wallet((int)$source['claimed_member_id'],false,true);
        if($canonical===''||!hash_equals($canonical,(string)$source['claimed_wallet_token']))throw new RuntimeException('member_rewards_claim_invalid');
        return $canonical;
    }
    if($source['member_id']!==null)return hym_reward_member_wallet((int)$source['member_id'],false,true);
    return $token;
}
