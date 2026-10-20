<?php
/**
 * Checkout Mini Game session.
 * Opening checkout grants one chance per full ten drinks and locks the ordinary cart.
 * Submitting the order keeps everything; an idle session is fully undone after HCGS_TTL.
 */
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){http_response_code(404);exit;}
const HCGS_TTL=900;
const HCGS_KEY='checkout_game_session';

function hcgs_schema(): void {
    static $done=false;if($done)return;$done=true;
    db()->exec("CREATE TABLE IF NOT EXISTS checkout_game_sessions (
        id CHAR(32) NOT NULL PRIMARY KEY,
        wallet_token VARCHAR(64) NOT NULL,
        drink_qty INT NOT NULL DEFAULT 0,
        granted_chances INT NOT NULL DEFAULT 0,
        played_chances INT NOT NULL DEFAULT 0,
        coupon_codes TEXT NULL,
        play_ids TEXT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'open',
        order_id BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL,
        last_seen_at DATETIME NOT NULL,
        KEY idx_cgs_open(status,last_seen_at),
        KEY idx_cgs_wallet(wallet_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Gift lines (free drinks/toppings won in the Mini Game) are saved as order items tagged with their coupon.
    if(setting('hcgs_schema_version','')!=='3'){
        if(!column_exists('order_items','game_reward_code'))db()->exec('ALTER TABLE order_items ADD COLUMN game_reward_code VARCHAR(80) NULL');
        // A session opened from a group checkout belongs to that room; NULL is the ordinary cart.
        if(!column_exists('checkout_game_sessions','group_public_id'))db()->exec('ALTER TABLE checkout_game_sessions ADD COLUMN group_public_id CHAR(32) NULL');
        set_setting('hcgs_schema_version','3');
    }
}
function hcgs_text(string $key,array $vars=[]): string {
    static $text=[
        'zh'=>['forfeit_title'=>'您還有 {n} 次小遊戲機會','forfeit_body'=>'現在送出訂單，未使用的機會將被取消，不會保留到下次。','play_now'=>'立即遊玩','forfeit_submit'=>'放棄機會並送出','forfeit_note'=>'送出訂單時，未使用的機會將被取消。','opened'=>'本訂單共 {qty} 杯飲料，您獲得 {chances} 次小遊戲機會。','locked'=>'購物車已鎖定：此階段只能送出訂單、付款及遊玩小遊戲。','expires'=>'此階段自開啟起僅有效 {minutes} 分鐘；逾時未送出訂單將自動取消，未使用的機會與本階段獲得的獎品將被刪除。','expired'=>'小遊戲階段已逾時：本階段的機會與獎品已取消，購物車已解鎖。請回到購物車重新下單。','remaining'=>'剩餘時間','cart_locked'=>'您已開啟小遊戲階段，購物車暫時鎖定。請完成訂單。','group_locked'=>'團購已鎖定：此階段團主只能送出訂單及遊玩小遊戲。','group_busy'=>'您正在進行團購的小遊戲階段，請先完成團購訂單。'],
        'vi'=>['forfeit_title'=>'Bạn còn {n} lượt chơi Mini Game','forfeit_body'=>'Nếu thanh toán bây giờ, các lượt chưa chơi sẽ bị huỷ và không được cộng cho lần sau.','play_now'=>'Chơi ngay','forfeit_submit'=>'Bỏ lượt & thanh toán','forfeit_note'=>'Quà trúng được tự áp dụng vào đơn này. Lượt chưa chơi sẽ bị huỷ khi gửi đơn.','opened'=>'Đơn có {qty} ly nước — bạn nhận {chances} lượt chơi Mini Game.','locked'=>'Giỏ hàng đã khoá: trong phiên này bạn chỉ có thể gửi đơn, thanh toán và chơi Mini Game.','expires'=>'Phiên chỉ có hiệu lực {minutes} phút kể từ lúc mở. Hết giờ mà chưa gửi đơn, phiên sẽ tự huỷ: lượt chơi chưa dùng và quà đã trúng trong phiên sẽ bị xoá.','expired'=>'Phiên Mini Game đã hết hạn: lượt chơi và quà trong phiên đã bị huỷ, giỏ hàng đã mở khoá. Vui lòng quay lại giỏ hàng để đặt lại.','remaining'=>'Thời gian còn lại','cart_locked'=>'Bạn đang trong phiên Mini Game nên giỏ hàng tạm khoá. Vui lòng hoàn tất đơn hàng.','group_locked'=>'Nhóm đã khoá: trong phiên này trưởng nhóm chỉ có thể gửi đơn, thanh toán và chơi Mini Game.','group_busy'=>'Bạn đang trong phiên Mini Game của đơn nhóm. Vui lòng hoàn tất đơn nhóm trước.'],
        'en'=>['forfeit_title'=>'You still have {n} Mini Game chance(s)','forfeit_body'=>'If you pay now, unplayed chances are cancelled and will not be kept for later.','play_now'=>'Play now','forfeit_submit'=>'Skip and pay','forfeit_note'=>'Prizes you win are applied to this order. Unplayed chances are cancelled when you submit.','opened'=>'This order has {qty} drinks — you received {chances} Mini Game chances.','locked'=>'Your cart is locked: during this session you can only submit, pay and play the Mini Game.','expires'=>'This session lasts {minutes} minutes from when it opened. If the order is not submitted in time, it is cancelled: unused chances and prizes won in it will be removed.','expired'=>'Your Mini Game session has expired: its chances and prizes were cancelled and your cart is unlocked. Return to the cart to order again.','remaining'=>'Time remaining','cart_locked'=>'Your cart is locked while a Mini Game session is open. Please complete your order.','group_locked'=>'The group is locked: during this session the host can only submit, pay and play the Mini Game.','group_busy'=>'A group order Mini Game session is open. Please complete the group order first.'],
    ];
    $s=$text[current_lang()][$key]??$text['en'][$key]??$key;
    foreach($vars as $k=>$v)$s=str_replace('{'.$k.'}',(string)$v,$s);
    return $s;
}
function hcgs_session_id(): string {
    $id=$_SESSION[HCGS_KEY]??'';
    return is_string($id)&&preg_match('/^[a-f0-9]{32}$/D',$id)===1?$id:'';
}
function hcgs_list(?string $json): array {
    $list=json_decode((string)$json,true);return is_array($list)?array_values($list):[];
}

/** A session lives HCGS_TTL seconds from opening; reloads and plays never extend it. */
const HCGS_EXPIRED_SQL='created_at<=UTC_TIMESTAMP()-INTERVAL '.HCGS_TTL.' SECOND';

/** The open session of this browser session; an expired one is undone instead of returned. */
function hcgs_current(bool $touch=false): ?array {
    $id=hcgs_session_id();if($id==='')return null;
    $st=db()->prepare('SELECT *,('.HCGS_EXPIRED_SQL.') AS expired,TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP(),created_at+INTERVAL '.HCGS_TTL.' SECOND) AS remaining FROM checkout_game_sessions WHERE id=?');
    $st->execute([$id]);$row=$st->fetch();
    unset($_SESSION[HCGS_KEY]);
    // Missing means the sweep already undid it; tell the customer once.
    if(!$row){$_SESSION['hcgs_flash']=hcgs_text('expired');return null;}
    if($row['status']!=='open')return null;
    if((int)$row['expired']===1){hcgs_expire($id);$_SESSION['hcgs_flash']=hcgs_text('expired');return null;}
    $_SESSION[HCGS_KEY]=$id;
    if($touch)db()->prepare("UPDATE checkout_game_sessions SET last_seen_at=UTC_TIMESTAMP() WHERE id=? AND status='open'")->execute([$id]);
    return $row;
}
/** The group room an open session belongs to; '' for the ordinary cart. */
function hcgs_group_of(?array $row): string {
    return (string)($row['group_public_id']??'');
}
/** The open session only when it was opened for this checkout ('' = ordinary cart, else the group room). */
function hcgs_current_for(string $groupId,bool $touch=false): ?array {
    $row=hcgs_current($touch);
    return $row&&hash_equals(hcgs_group_of($row),$groupId)?$row:null;
}
function hcgs_locked(): ?array {
    // A paused campaign invalidates draw checkout, so the cart must stay editable to avoid a cart/checkout loop.
    if(!minigame_enabled())return null;
    try{return hcgs_current();}catch(Throwable $e){return null;}
}
/** The checkout page that owns an open session. */
function hcgs_checkout_url(array $row): string {
    $group=hcgs_group_of($row);
    return base_url('checkout.php'.($group!==''?'?group='.rawurlencode($group):''));
}
/** Ordinary cart edits are refused while a session is open. */
function hcgs_guard_cart(): void {
    $row=hcgs_locked();if(!$row)return;
    $_SESSION['hcgs_flash']=hcgs_text(hcgs_group_of($row)!==''?'group_busy':'cart_locked');
    redirect(hcgs_checkout_url($row));
}
/** Group room changes by the host are refused while that room's session is open. */
function hcgs_guard_group(string $groupId): void {
    $row=hcgs_locked();if(!$row||!hash_equals(hcgs_group_of($row),$groupId))return;
    $_SESSION['hcgs_flash']=hcgs_text('group_locked');
    redirect(hcgs_checkout_url($row));
}

/** Grant once per session, crediting the wallet in the same transaction that records the grant. */
function hcgs_open(int $drinkQty,int $chances,string $groupId=''): ?array {
    // One session per browser: a session of another checkout is neither shown here nor replaced.
    if($current=hcgs_current(true))return hash_equals(hcgs_group_of($current),$groupId)?$current:null;
    // Only "Điền thông tin đặt hàng" from the cart (or the host's checkout button in a group) may open a session;
    // a reload after expiry must not restart the clock.
    $flag=$_SESSION['hcgs_allow_open']??null;unset($_SESSION['hcgs_allow_open']);
    $allowed=$groupId===''?$flag===true:(is_string($flag)&&hash_equals($flag,$groupId));
    if(!$allowed||!minigame_enabled()||$chances<1)return null;
    $token=customer_game_wallet_token(true);
    if(preg_match('/^[a-f0-9]{32}$/D',$token)!==1)return null;
    $id=bin2hex(random_bytes(16));$pdo=db();
    if($pdo->inTransaction())return null;
    $pdo->beginTransaction();
    try{
        $pdo->prepare('INSERT INTO game_wallets(wallet_token,available_chances,total_earned,total_played) VALUES(?,?,?,0) ON DUPLICATE KEY UPDATE available_chances=available_chances+VALUES(available_chances),total_earned=total_earned+VALUES(total_earned)')->execute([$token,$chances,$chances]);
        $pdo->prepare("INSERT INTO checkout_game_sessions(id,wallet_token,drink_qty,granted_chances,coupon_codes,play_ids,status,group_public_id,created_at,last_seen_at) VALUES(?,?,?,?,'[]','[]','open',?,UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute([$id,$token,$drinkQty,$chances,$groupId!==''?$groupId:null]);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('[checkout_game] open failed');return null;}
    $_SESSION[HCGS_KEY]=$id;
    return hcgs_current();
}

/** Called inside the Mini Game award transaction, after the wallet row is locked. */
function hcgs_record_play_locked(string $token,string $couponCode): void {
    $id=hcgs_session_id();if($id==='')return;
    $pdo=db();if(!$pdo->inTransaction())throw new RuntimeException('checkout_game_requires_transaction');
    $st=$pdo->prepare("SELECT * FROM checkout_game_sessions WHERE id=? AND wallet_token=? AND status='open' FOR UPDATE");$st->execute([$id,$token]);
    $row=$st->fetch();
    // Plays beyond this grant spend older chances, so their prizes are never undone.
    if(!$row||(int)$row['played_chances']>=(int)$row['granted_chances'])return;
    $st=$pdo->prepare('SELECT MAX(id) FROM game_plays WHERE wallet_token=?');$st->execute([$token]);
    $codes=hcgs_list($row['coupon_codes']);if($couponCode!=='')$codes[]=$couponCode;
    $plays=hcgs_list($row['play_ids']);$plays[]=(int)$st->fetchColumn();
    $pdo->prepare('UPDATE checkout_game_sessions SET played_chances=played_chances+1,coupon_codes=?,play_ids=? WHERE id=?')
        ->execute([json_encode($codes),json_encode($plays),$id]);
}

/** Called inside the Mini Game transaction: a share bonus chance joins the open session. */
function hcgs_add_chance_locked(string $token): void {
    $id=hcgs_session_id();if($id==='')return;
    db()->prepare("UPDATE checkout_game_sessions SET granted_chances=granted_chances+1 WHERE id=? AND wallet_token=? AND status='open'")->execute([$id,$token]);
}

/** One coupon per play: every reward code won in the open session, oldest first. */
function hcgs_rewards(): ?array {
    $row=hcgs_current();if(!$row)return null;
    $codes=array_values(array_filter(hcgs_list($row['coupon_codes']),'is_string'));$rewards=[];
    if($codes){
        $in=implode(',',array_fill(0,count($codes),'?'));
        $st=db()->prepare("SELECT c.code,d.reward_json FROM customer_coupons c LEFT JOIN game_coupon_details d ON d.coupon_id=c.id WHERE c.wallet_token=? AND c.code IN ($in)");
        $st->execute(array_merge([$row['wallet_token']],$codes));
        $found=[];foreach($st->fetchAll() as $r)$found[$r['code']]=json_decode((string)$r['reward_json'],true)?:[];
        foreach($codes as $code)if(isset($found[$code])){$r=$found[$code];
            $rewards[]=['coupon_code'=>$code,'kind'=>$r['kind']??'','name'=>$r['name']??[],'expires_at'=>$r['expires_at']??''];}
    }
    $cash=0.0;foreach(hcgs_cash_coupons($row) as $c)$cash+=$c['amount'];
    return ['granted'=>(int)$row['granted_chances'],'played'=>(int)$row['played_chances'],'rewards'=>$rewards,'cash_total'=>$cash,'in_use'=>hcgs_rewards_in_use($row['id'])];
}

/** A finished board whose prize was won outside this session (e.g. a paid earlier order) is cleared, so the game starts fresh. */
function hcgs_forget_old_board(?array $row): void {
    if(!$row)return;
    $st=db()->prepare('SELECT coupon_code FROM game_matching_boards WHERE wallet_token=? AND awarded_at IS NOT NULL');$st->execute([$row['wallet_token']]);
    $code=$st->fetchColumn();
    if($code===false||in_array((string)$code,hcgs_list($row['coupon_codes']),true))return;
    db()->prepare('DELETE FROM game_matching_boards WHERE wallet_token=? AND awarded_at IS NOT NULL AND coupon_code<=>?')->execute([$row['wallet_token'],$code]);
}
/** Prizes won in an open session apply to its order straight away. */
function hcgs_rewards_in_use(string $id): bool {
    return $id!=='';
}
/** Chances of the open session that have not been played yet. */
function hcgs_remaining(?array $row): int {
    return $row?max(0,(int)$row['granted_chances']-(int)$row['played_chances']):0;
}

/** Unused, unexpired prizes won in a session, oldest first. */
function hcgs_session_coupons(array $row): array {
    $codes=array_values(array_filter(hcgs_list($row['coupon_codes']),'is_string'));if(!$codes)return [];
    $in=implode(',',array_fill(0,count($codes),'?'));
    $st=db()->prepare("SELECT c.code,c.amount,d.reward_json FROM customer_coupons c JOIN game_coupon_details d ON d.coupon_id=c.id WHERE c.wallet_token=? AND c.code IN ($in) AND c.coupon_type='game_reward' AND c.used_order_id IS NULL AND d.expires_at>UTC_TIMESTAMP()");
    $st->execute(array_merge([$row['wallet_token']],$codes));
    $found=[];
    foreach($st->fetchAll() as $r){$reward=json_decode((string)$r['reward_json'],true);if(is_array($reward))$found[$r['code']]=['code'=>$r['code'],'amount'=>(float)$r['amount'],'reward'=>$reward];}
    $list=[];foreach($codes as $code)if(isset($found[$code]))$list[]=$found[$code];
    return $list;
}
/** Money prizes of a session. */
function hcgs_cash_coupons(array $row): array {
    return array_values(array_filter(hcgs_session_coupons($row),fn($c)=>($c['reward']['kind']??'')==='discount'&&$c['amount']>0));
}

/** Free drink/topping prizes, once "Đóng & Dùng Quà" is pressed: one gift line per selected item, quantity from the prize settings. */
function hcgs_gift_rewards(?array $row): array {
    if(!$row||!hcgs_rewards_in_use((string)$row['id']))return [];
    $gifts=[];
    foreach(hcgs_session_coupons($row) as $c){
        $r=$c['reward'];$kind=$r['kind']??'';if(!in_array($kind,['topping','tea'],true))continue;
        $choices=[];
        foreach(array_map('intval',(array)($r['item_ids']??[$r['item_id']??0])) as $id){
            $size=hgr_item_size($r+['cup_size'=>''],$id);
            if($kind==='topping'){
                $item=hym_reward_one('SELECT * FROM toppings WHERE id=? AND active=1',[$id]);
                if($item)$choices[$id]=['id'=>$id,'name'=>localized_name($item),'price'=>(float)$item['price'],'cup_size'=>''];
            }else{
                $item=hym_reward_one('SELECT p.* FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=? AND p.active=1 AND c.active=1',[$id]);
                $prices=$item?(product_has_sizes($item)?product_size_options($item):[''=>(float)$item['price']]):[];
                if($item&&isset($prices[$size]))$choices[$id]=['id'=>$id,'name'=>localized_name($item),'price'=>(float)$prices[$size],'cup_size'=>$size];
            }
        }
        foreach($choices as $item)$gifts[]=['code'=>$c['code'],'kind'=>$kind,'qty'=>max(1,(int)($r['gift_qty']??1)),'item'=>$item];
    }
    return $gifts;
}

/** Sum the session's money prizes and spread them over what is left of each line after the ordinary discount. */
function hcgs_cash_discount(array $cart,array $coupon,?array $row): array {
    $out=['codes'=>[],'discount'=>0.0,'line_discounts'=>[]];
    if(!$row||!hcgs_rewards_in_use((string)$row['id']))return $out;
    $room=max(0,cart_subtotal($cart)-(float)$coupon['discount']);
    foreach(hcgs_cash_coupons($row) as $c){
        if($room<=0)break;
        $take=min($room,$c['amount']);$out['codes'][]=$c['code'];$out['discount']+=$take;$room-=$take;
    }
    $left=$out['discount'];
    foreach($cart as $key=>$r){
        if($left<=0)break;
        $line=max(0,(float)($r['unit_price']??0)*(int)($r['qty']??0)-(float)($coupon['line_discounts'][(string)$key]??0));
        $take=min($left,$line);if($take>0){$out['line_discounts'][(string)$key]=$take;$left-=$take;}
    }
    return $out;
}
/** Read the open session by id without touching the PHP session (used inside the order transaction). */
function hcgs_row(string $id): ?array {
    if($id==='')return null;
    $st=db()->prepare("SELECT * FROM checkout_game_sessions WHERE id=? AND status='open'");$st->execute([$id]);
    return $st->fetch()?:null;
}

/** Called inside the order transaction: unplayed chances are forfeited, never kept for a later order. */
function hcgs_complete_locked(string $id,int $orderId): int {
    if($id==='')return 0;
    $pdo=db();
    $st=$pdo->prepare("SELECT wallet_token FROM checkout_game_sessions WHERE id=? AND status='open'");$st->execute([$id]);
    $token=$st->fetchColumn();if($token===false)return 0;
    // Same lock order as the Mini Game: wallet, then session.
    $st=$pdo->prepare('SELECT available_chances FROM game_wallets WHERE wallet_token=? FOR UPDATE');$st->execute([$token]);
    $available=max(0,(int)$st->fetchColumn());
    $st=$pdo->prepare("SELECT granted_chances,played_chances FROM checkout_game_sessions WHERE id=? AND status='open' FOR UPDATE");$st->execute([$id]);
    $row=$st->fetch();if(!$row)return 0;
    $revoke=min($available,hcgs_remaining($row));
    if($revoke>0)$pdo->prepare('UPDATE game_wallets SET available_chances=available_chances-?,total_earned=GREATEST(0,total_earned-?) WHERE wallet_token=?')->execute([$revoke,$revoke,$token]);
    $played=(int)$row['played_chances'];
    // The paid order's board (prize shown, or a game left unfinished) is cleared for the next order.
    try{$pdo->prepare('DELETE FROM game_matching_boards WHERE wallet_token=?')->execute([$token]);}catch(Throwable $e){/* No board table yet: nothing to clear. */}
    $pdo->prepare("UPDATE checkout_game_sessions SET status='completed',order_id=? WHERE id=?")->execute([$orderId,$id]);
    $pdo->prepare('UPDATE orders SET game_award_processed=1,game_chances_awarded=? WHERE id=?')->execute([$played,$orderId]);
    return $played;
}

/** Undo an idle session: revoke unplayed chances, delete its prizes and plays, then delete the session. */
function hcgs_expire(string $id): void {
    $pdo=db();if($pdo->inTransaction())return;
    $st=$pdo->prepare('SELECT wallet_token FROM checkout_game_sessions WHERE id=?');$st->execute([$id]);
    $token=$st->fetchColumn();if($token===false)return;
    $pdo->beginTransaction();
    try{
        // Same lock order as the Mini Game: wallet, then board/session.
        $st=$pdo->prepare('SELECT available_chances FROM game_wallets WHERE wallet_token=? FOR UPDATE');$st->execute([$token]);
        $available=max(0,(int)$st->fetchColumn());
        $st=$pdo->prepare("SELECT * FROM checkout_game_sessions WHERE id=? AND status='open' AND ".HCGS_EXPIRED_SQL." FOR UPDATE");$st->execute([$id]);
        $row=$st->fetch();
        if(!$row){$pdo->commit();return;}
        $granted=(int)$row['granted_chances'];$played=(int)$row['played_chances'];
        $revoke=min($available,max(0,$granted-$played));
        $pdo->prepare('UPDATE game_wallets SET available_chances=available_chances-?,total_earned=GREATEST(0,total_earned-?),total_played=GREATEST(0,total_played-?) WHERE wallet_token=?')
            ->execute([$revoke,$revoke+$played,$played,$token]);
        $codes=array_values(array_filter(hcgs_list($row['coupon_codes']),'is_string'));
        if($codes){
            $in=implode(',',array_fill(0,count($codes),'?'));
            $pdo->prepare("DELETE FROM game_matching_boards WHERE wallet_token=? AND coupon_code IN ($in)")->execute(array_merge([$token],$codes));
            $pdo->prepare("DELETE FROM customer_coupons WHERE wallet_token=? AND used_order_id IS NULL AND code IN ($in)")->execute(array_merge([$token],$codes));
        }
        $plays=array_values(array_filter(array_map('intval',hcgs_list($row['play_ids']))));
        if($plays){
            $in=implode(',',array_fill(0,count($plays),'?'));
            $pdo->prepare("DELETE FROM game_plays WHERE wallet_token=? AND id IN ($in)")->execute(array_merge([$token],$plays));
        }
        $pdo->prepare('DELETE FROM checkout_game_sessions WHERE id=?')->execute([$id]);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('[checkout_game] expire failed');}
}

/** Customers who never come back are cleaned up by any later request. */
function hcgs_sweep(int $limit=10): void {
    try{
        $st=db()->prepare("SELECT id FROM checkout_game_sessions WHERE status='open' AND ".HCGS_EXPIRED_SQL." LIMIT ".max(1,$limit));
        $st->execute();
        foreach($st->fetchAll(PDO::FETCH_COLUMN) as $id)hcgs_expire((string)$id);
    }catch(Throwable $e){error_log('[checkout_game] sweep failed');}
}
