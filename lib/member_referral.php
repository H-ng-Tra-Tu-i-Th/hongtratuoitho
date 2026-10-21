<?php
/**
 * Group referral (grandparent → parent → child):
 * when a group order is submitted, every member of the group whose parent_id is NULL becomes a child of the
 * group's leader, and the leader's own parent (the grandparent) earns group_referral_points once per group.
 *
 * settings:
 *   group_referral_enabled  1 = on, 0 = off (both parent assignment and points)
 *   group_referral_points   points the leader's parent receives per submitted group order
 */
const HYM_REFERRAL_SCHEMA = '19.16.2';

function hym_referral_schema(): void {
    static $done=false;
    if($done)return;
    $pdo=db();
    if($pdo->inTransaction())throw new RuntimeException('referral_schema_inside_transaction');
    if(setting('member_referral_schema_version','')!==HYM_REFERRAL_SCHEMA){
        hym_schema();
        $lock=$pdo->query("SELECT GET_LOCK(CONCAT(DATABASE(),':referral-schema'),10)")->fetchColumn();
        if((int)$lock!==1)throw new RuntimeException('referral_schema_busy');
        try{
            $version=$pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$version->execute(['member_referral_schema_version']);
            if((string)$version->fetchColumn()!==HYM_REFERRAL_SCHEMA){
                $changes=[['members','parent_id','BIGINT UNSIGNED NULL'],['members','points_balance','INT NOT NULL DEFAULT 0'],
                    ['group_rooms','owner_member_id','BIGINT UNSIGNED NULL'],['group_members','member_id','BIGINT UNSIGNED NULL']];
                foreach($changes as [$table,$col,$def])if(!column_exists($table,$col))$pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
                $index=$pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
                foreach([['members','idx_member_parent','parent_id'],['group_rooms','idx_group_owner_member','owner_member_id'],['group_members','idx_group_member_account','member_id']] as [$table,$name,$col]){
                    $index->execute([$table,$name]);if(!$index->fetchColumn())$pdo->exec("ALTER TABLE `$table` ADD KEY `$name` (`$col`)");
                }
                // One row per credit; the unique key makes a retried order submit credit only once.
                $pdo->exec("CREATE TABLE IF NOT EXISTS member_point_transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 member_id BIGINT UNSIGNED NOT NULL,
 points INT NOT NULL,
 reason VARCHAR(32) NOT NULL,
 order_id BIGINT UNSIGNED NULL,
 group_room_id BIGINT UNSIGNED NULL,
 source_member_id BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_member_point_order(reason,order_id),
 KEY idx_member_points(member_id,created_at),
 CONSTRAINT fk_member_point_member FOREIGN KEY(member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                // 19.16.1 briefly keyed credits per child; credits are per group order again.
                $index->execute(['member_point_transactions','uq_member_point_source']);
                if($index->fetchColumn())$pdo->exec('ALTER TABLE member_point_transactions DROP INDEX uq_member_point_source');
                $index->execute(['member_point_transactions','uq_member_point_order']);
                if(!$index->fetchColumn())$pdo->exec('ALTER TABLE member_point_transactions ADD UNIQUE KEY uq_member_point_order(reason,order_id)');
                $defaults=$pdo->prepare('INSERT IGNORE INTO settings(setting_key,setting_value) VALUES(?,?)');
                $defaults->execute(['group_referral_enabled','1']);$defaults->execute(['group_referral_points','1']);
                set_setting('member_referral_schema_version',HYM_REFERRAL_SCHEMA);
            }else $GLOBALS['__drink_order_setting_cache']['member_referral_schema_version']=HYM_REFERRAL_SCHEMA;
        }finally{$pdo->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(),':referral-schema'))");}
    }
    $done=true;
}
function hym_referral_enabled(): bool { return setting('group_referral_enabled','1')==='1'; }
function hym_referral_points(): int { return max(0,min(1000000,(int)setting('group_referral_points','1'))); }

/**
 * Runs inside the order transaction, right after the order and its items are saved.
 * It never fails the order: errors are logged and the order still commits.
 */
function hym_group_referral_after_order(int $roomId,int $orderId): void {
    try{
        if(!hym_referral_enabled())return;
        $room=hyg_one('SELECT id,owner_member_id FROM group_rooms WHERE id=?',[$roomId]);
        $ownerId=(int)($room['owner_member_id']??0);if($ownerId<1)return;
        $owner=hyg_one('SELECT id,parent_id FROM members WHERE id=? FOR UPDATE',[$ownerId]);if(!$owner)return;

        // The leader's own ancestors (and the leader) never become the leader's children: that would loop.
        $ancestors=[$ownerId=>true];$cursor=(int)($owner['parent_id']??0);
        for($depth=0;$cursor>0&&!isset($ancestors[$cursor])&&$depth<100;$depth++){
            $ancestors[$cursor]=true;$row=hyg_one('SELECT parent_id FROM members WHERE id=?',[$cursor]);$cursor=(int)($row['parent_id']??0);
        }
        foreach(hyg_all('SELECT DISTINCT member_id FROM group_members WHERE room_id=? AND member_id IS NOT NULL',[$roomId]) as $row){
            $childId=(int)$row['member_id'];if($childId<1||isset($ancestors[$childId]))continue;
            // A member who already has children heads their own branch (e.g. a root with parent_id NULL);
            // joining someone else's group later must not hang that whole branch under the new leader.
            if(hyg_one('SELECT id FROM members WHERE parent_id=? LIMIT 1',[$childId]))continue;
            hyg_exec('UPDATE members SET parent_id=? WHERE id=? AND parent_id IS NULL',[$ownerId,$childId]);
        }

        // The grandparent (leader's parent) earns points once for each group the leader submits.
        $parentId=(int)($owner['parent_id']??0);$points=hym_referral_points();
        if($parentId<1||$points<1)return;
        $credit=db()->prepare("INSERT IGNORE INTO member_point_transactions(member_id,points,reason,order_id,group_room_id,source_member_id) VALUES(?,?,'group_order',?,?,?)");
        $credit->execute([$parentId,$points,$orderId,$roomId,$ownerId]);
        if($credit->rowCount()===1)hyg_exec('UPDATE members SET points_balance=points_balance+? WHERE id=?',[$points,$parentId]);
    }catch(Throwable $e){
        error_log('group_referral order '.$orderId.': '.$e->getMessage());
    }
}
