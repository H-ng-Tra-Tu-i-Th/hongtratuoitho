<?php
/** Entry checks read saved orders and balances; they never award or consume a chance. */
function hge_text(string $key,array $vars=[]): string {
    $copy=[
        'zh'=>['waiting'=>'等待店家完成訂單後，系統會發放 {chances} 次遊戲機會並自動進入小遊戲。','ready'=>'已獲得 {chances} 次遊戲機會，正在進入小遊戲。','paused'=>'遊戲機會已保留，活動恢復後即可遊玩。','preparing'=>'遊戲機會已保留，獎項正在準備中。','used'=>'此訂單的遊戲機會已使用。','cancelled'=>'訂單已取消，不會發放遊戲機會。','play'=>'進入小遊戲','refresh'=>'更新遊戲機會','offline'=>'暫時無法更新遊戲機會，請稍後再試。'],
        'vi'=>['waiting'=>'Sau khi cửa hàng hoàn tất đơn, bạn sẽ nhận {chances} lượt chơi và được chuyển vào trò chơi tự động.','ready'=>'Bạn đã nhận {chances} lượt chơi. Đang mở trò chơi.','paused'=>'Lượt chơi được giữ lại. Bạn có thể chơi khi chương trình mở lại.','preparing'=>'Lượt chơi được giữ lại. Phần thưởng đang được chuẩn bị.','used'=>'Lượt chơi từ đơn này đã được sử dụng.','cancelled'=>'Đơn đã hủy nên không được nhận lượt chơi.','play'=>'Vào trò chơi','refresh'=>'Cập nhật lượt chơi','offline'=>'Chưa thể cập nhật lượt chơi. Vui lòng thử lại sau.'],
        'en'=>['waiting'=>'Once the store completes this order, you will receive {chances} game chances and enter the game automatically.','ready'=>'You have received {chances} game chances. Opening the game.','paused'=>'Your game chances are saved. Play when the campaign resumes.','preparing'=>'Your game chances are saved. Rewards are being prepared.','used'=>'The game chances from this order have been used.','cancelled'=>'This order was cancelled and will not earn a game chance.','play'=>'Enter the game','refresh'=>'Refresh game chance','offline'=>'Unable to update your game chance. Please try again later.'],
    ];
    $text=$copy[current_lang()][$key]??$copy['vi'][$key]??'';
    foreach($vars as $name=>$value)$text=str_replace('{'.$name.'}',(string)$value,$text);
    return $text;
}
function hge_last_order(): ?array {
    $no=$_SESSION['last_order_no']??'';
    if(!is_string($no)||$no===''||strlen($no)>80)return null;
    $st=db()->prepare('SELECT * FROM orders WHERE order_no=? LIMIT 1');$st->execute([$no]);return $st->fetch()?:null;
}
/** Resolve a saved guest wallet after a verified claim without creating any wallet. */
function hge_order_wallet(array $order): string {
    $memberId=hym_reward_current_member_id();$savedMember=(int)($order['member_id']??0);
    if($savedMember>0){
        if($savedMember!==$memberId)return '';
        $token=hym_reward_member_wallet($savedMember,false);
    }else{
        $token=(string)($order['game_wallet_token']??'');if(!hym_reward_token_valid($token))return '';
        $wallet=hym_reward_one('SELECT member_id,claimed_member_id,claimed_wallet_token FROM game_wallets WHERE wallet_token=?',[$token]);
        if(!$wallet)return '';
        if($wallet['claimed_member_id']!==null){
            if((int)$wallet['claimed_member_id']!==$memberId||$memberId<1)return '';
            $canonical=hym_reward_member_wallet($memberId,false);
            if($canonical===''||!hash_equals($canonical,(string)$wallet['claimed_wallet_token']))return '';
            $token=$canonical;
        }
    }
    return $token!==''&&hym_reward_wallet_accessible($token)?$token:'';
}
function hge_rewards_ready(string $token): bool {
    if(hgr_ready())return true;
    // An existing game keeps its own reward snapshot after campaign edits.
    try{
        $row=hym_reward_one('SELECT board_id,state_json FROM game_matching_boards WHERE wallet_token=? AND awarded_at IS NULL',[$token]);
        if(!$row)return false;
        $board=json_decode((string)$row['state_json'],true);
        return is_array($board)&&($board['id']??null)===$row['board_id']&&is_array($board['reward_pool']??null)&&count($board['reward_pool'])>0;
    }catch(PDOException $error){return false;}
}
function hge_order_state(?array $order): array {
    $state=['state'=>'none','url'=>'','message'=>'','poll'=>false];
    if(!$order)return $state;
    $token=hge_order_wallet($order);if($token==='')return $state;
    $mode=(string)($order['promotion_mode']??'legacy');
    if(!in_array($mode,['draw','legacy'],true)||trim((string)($order['coupon_code']??''))!==''||(float)($order['discount_amount']??0)>0)return $state;
    $expected=game_saved_chance_count(order_eligible_drink_qty((int)$order['id']),isset($order['game_chances_expected'])?(int)$order['game_chances_expected']:null);
    if($expected<1)return $state;
    $status=(string)($order['status']??'');
    if($status==='cancelled')$key='cancelled';
    elseif($status!=='done'||empty($order['game_award_processed']))$key='waiting';
    elseif((int)($order['game_chances_awarded']??0)<1)return $state;
    elseif(game_wallet_balance($token)<1)$key='used';
    elseif(!minigame_enabled())$key='paused';
    elseif(!hge_rewards_ready($token))$key='preparing';
    else $key='ready';
    $state['state']=$key;$state['message']=hge_text($key,['chances'=>$key==='ready'?(int)$order['game_chances_awarded']:$expected]);$state['poll']=in_array($key,['waiting','paused','preparing'],true);
    if($key==='ready')$state['url']=base_url('minigame/');
    return $state;
}
function hge_render(array $state,string $pollUrl=''): string {
    if($state['state']==='none')return '';
    ob_start(); ?>
<?php /* TẠM TẮT khối "Cập nhật lượt chơi"
    <section class="promotion-summary" data-game-entry data-game-state="<?=e($state['state'])?>" data-game-url="<?=e($state['url'])?>" data-game-poll="<?=e($state['poll']?$pollUrl:'')?>" data-game-offline="<?=e(hge_text('offline'))?>">
      <p data-game-entry-message role="status" aria-live="polite"><?=e($state['message'])?></p>
      <a class="btn primary" data-game-entry-link href="<?=e($state['url'])?>" <?=$state['state']==='ready'?'':'hidden'?>><?=e(hge_text('play'))?></a>
      <?php if($state['poll']&&$pollUrl!==''):?><button class="btn" type="button" data-game-entry-refresh><?=e(hge_text('refresh'))?></button><?php endif;?>
    </section>
*/ ?>
    <?php return ob_get_clean();
}
