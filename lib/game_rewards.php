<?php
require_once __DIR__.'/game_config.php';
/** Ký Ức 10 rewards use the order platform's owned, single-use coupons. */
function hgr_schema(): void {
    static $done=false;if($done)return;
    if(db()->inTransaction())throw new RuntimeException('game_reward_schema_inside_transaction');
    db()->exec("CREATE TABLE IF NOT EXISTS game_coupon_details (
        coupon_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        reward_json LONGTEXT NOT NULL,
        expires_at DATETIME NOT NULL,
        KEY idx_game_reward_expiry(expires_at),
        CONSTRAINT fk_game_reward_coupon FOREIGN KEY(coupon_id) REFERENCES customer_coupons(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if(setting('game_rewards_schema_version','')!=='19.8.0')set_setting('game_rewards_schema_version','19.8.0');
    $done=true;
}
function hgr_text(string $key): string {
    $copy=[
        'title'=>['zh'=>'10 杯童年回憶','vi'=>'Ký Ức 10 Ly','en'=>'Ten Cups of Memories'],
        'intro'=>['zh'=>'翻開童年回憶，配對 6 組圖案，領取下一次的驚喜。','vi'=>'Lật thẻ ký ức, ghép đủ 6 cặp và nhận một món quà cho lần gặp tới.','en'=>'Match six pairs of childhood memories and earn a gift for your next order.'],
        'rules'=>['zh'=>'選擇買10抽獎，單筆訂單每滿10杯飲品獲1次機會：10杯1次、20杯2次、30杯3次，完成後發放。杯數不跨單累計，不與其他優惠共用。','vi'=>'Chọn Mua 10 nhận lượt rút thưởng: mỗi 10 ly trong cùng một đơn nhận 1 lượt khi hoàn tất; 10 ly được 1 lượt, 20 ly được 2, 30 ly được 3. Không cộng ly giữa các đơn hoặc dùng cùng ưu đãi khác.','en'=>'Choose Buy 10 for a prize draw: every 10 drinks in one completed order earns 1 play; 10 drinks earn 1, 20 earn 2, 30 earn 3. Cups do not carry over between orders; offers cannot be combined.'],
        'pairs'=>['zh'=>'配對','vi'=>'Cặp hình','en'=>'Pairs'], 'moves'=>['zh'=>'翻牌次數','vi'=>'Lượt lật','en'=>'Moves'],
        'time'=>['zh'=>'遊戲時間','vi'=>'Thời gian','en'=>'Time'], 'retry'=>['zh'=>'重新載入進度','vi'=>'Tải lại tiến độ','en'=>'Reload progress'],
        'next'=>['zh'=>'使用下次機會','vi'=>'Chơi lượt tiếp theo','en'=>'Use next play'], 'reward'=>['zh'=>'你的回憶好禮','vi'=>'Món quà của bạn','en'=>'Your memory gift'],
        'no_limit'=>['zh'=>'不限時間，配對錯誤不影響獎項。','vi'=>'Không giới hạn thời gian; ghép sai không ảnh hưởng quà.','en'=>'No time limit; mistakes do not affect your prize.'],
        'prepare'=>['zh'=>'活動準備中，請稍後再來。你的遊戲機會會保留。','vi'=>'Chương trình đang chuẩn bị. Vui lòng quay lại sau; lượt chơi của bạn vẫn được giữ.','en'=>'The campaign is being prepared. Come back later; your plays will be kept.'],
        'settings'=>['zh'=>'隨機獎項設定','vi'=>'Cài đặt quà ngẫu nhiên','en'=>'Random reward settings'],
        'settings_hint'=>['zh'=>'設定獎項、機率、使用條件與有效天數；啟用獎項的機率合計須為100%。','vi'=>'Cài đặt quà, tỷ lệ, điều kiện và hạn sử dụng; tổng tỷ lệ quà đang bật phải là 100%.','en'=>'Configure prizes, odds, conditions and validity; enabled prize odds must total 100%.'],
        'topping'=>['zh'=>'指定加料','vi'=>'Topping tặng','en'=>'Gift topping'], 'tea'=>['zh'=>'指定紅茶','vi'=>'Hồng trà tặng','en'=>'Gift tea'],
        'size'=>['zh'=>'贈送杯型','vi'=>'Cỡ ly tặng','en'=>'Gift cup size'], 'select'=>['zh'=>'請選擇','vi'=>'Vui lòng chọn','en'=>'Select an item'],
        'saved'=>['zh'=>'獎項設定已儲存。','vi'=>'Đã lưu cài đặt quà.','en'=>'Reward settings saved.'],
        'invalid'=>['zh'=>'請檢查獎項商品、機率、條件、有效天數與圖片設定。','vi'=>'Vui lòng kiểm tra món quà, tỷ lệ, điều kiện, hạn sử dụng và hình ảnh.','en'=>'Check prize items, odds, conditions, validity and images.'],
        'expires'=>['zh'=>'有效至','vi'=>'Hạn dùng','en'=>'Valid until'],
        'odds'=>['zh'=>'獎項機率','vi'=>'Tỷ lệ quà','en'=>'Prize odds'],
    ];
    $lang=function_exists('current_lang')?current_lang():'vi';return $copy[$key][$lang]??$copy[$key]['en']??$key;
}
function hgr_names(array $item): array {
    $result=[];foreach(['vi','zh','en'] as $lang)$result[$lang]=trim((string)($item['name_'.$lang]??''))?:((string)($item['name']??''));return $result;
}
function hgr_items(): ?array {
    $top=hym_reward_one('SELECT * FROM toppings WHERE id=? AND active=1',[(int)setting('hgr_topping_id','0')]);
    $tea=hym_reward_one('SELECT p.* FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=? AND p.active=1 AND c.active=1',[(int)setting('hgr_drink_id','0')]);
    if(!$top||!$tea||(float)$top['price']<=0)return null;
    $size=product_has_sizes($tea)?setting('hgr_drink_size','L'):'';
    $prices=product_has_sizes($tea)?product_size_options($tea):[''=>(float)$tea['price']];
    if(!isset($prices[$size])||$prices[$size]<=0)return null;
    return ['topping'=>$top,'tea'=>$tea,'size'=>$size];
}
function hgr_money_text(int $amount,string $lang): string { return number_format($amount,0,'.',$lang==='vi'?'.':',').'đ'; }
/** Resolve the current catalog once into terms that can be saved with a board. */
function hgr_reward_template(array $prize): array {
    $item=hgr_config_catalog_item($prize);$names=$item?hgr_names($item):[];$size=$prize['cup_size']!==''?' ('.$prize['cup_size'].')':'';
    $cups=$prize['min_paid_cups'];$spend=$prize['min_spend'];$name=[];$terms=[];
    foreach(['vi','zh','en'] as $lang){
        if($prize['kind']==='discount'){
            $amount=hgr_money_text($prize['amount'],$lang);
            $name[$lang]=['vi'=>'Giảm '.$amount,'zh'=>'折抵 '.$amount,'en'=>$amount.' off'][$lang];
            $terms[$lang]=['vi'=>'Đơn tiếp theo từ '.hgr_money_text($spend,'vi').' và ít nhất '.$cups.' ly trả phí.',
                'zh'=>'下一筆訂單滿'.hgr_money_text($spend,'zh').'，至少'.$cups.'杯付費飲品。',
                'en'=>'Next order: at least '.hgr_money_text($spend,'en').' and '.$cups.' paid drink(s).'][$lang];
        }elseif($prize['kind']==='topping'){
            $name[$lang]=['vi'=>'Tặng 1 topping: '.$names['vi'],'zh'=>'免費加料1份：'.$names['zh'],'en'=>'One free topping: '.$names['en']][$lang];
            $terms[$lang]=['vi'=>'Đơn tiếp theo mua ít nhất '.$cups.' ly trả phí và thêm '.$names['vi'].' vào ly. Giảm giá 1 phần topping.',
                'zh'=>'下一筆訂單至少買'.$cups.'杯付費飲品，並加選'.$names['zh'].'。折抵1份加料。',
                'en'=>'Next order: buy at least '.$cups.' paid drink(s) with '.$names['en'].'. One attached topping is free.'][$lang];
        }else{
            $name[$lang]=['vi'=>'Tặng 1 ly: '.$names['vi'].$size,'zh'=>'免費飲品1杯：'.$names['zh'].$size,'en'=>'One free drink: '.$names['en'].$size][$lang];
            $terms[$lang]=['vi'=>'Đơn tiếp theo mua '.$cups.' ly trả phí và thêm 1 ly '.$names['vi'].$size.'. Chỉ miễn tiền đồ uống, không gồm topping.',
                'zh'=>'下一筆訂單買'.$cups.'杯付費飲品，另加1杯'.$names['zh'].$size.'。只折抵飲品本體，不含加料。',
                'en'=>'Next order: buy '.$cups.' paid drink(s) and add one '.$names['en'].$size.'. Drink base only; toppings cost extra.'][$lang];
        }
        if($prize['kind']!=='discount'&&$spend>0)$terms[$lang].=['vi'=>' Đơn từ '.hgr_money_text($spend,'vi').'.','zh'=>' 訂單須滿'.hgr_money_text($spend,'zh').'。','en'=>' Order subtotal must reach '.hgr_money_text($spend,'en').'.'][$lang];
        $terms[$lang].=['vi'=>' Không cộng ưu đãi khác. Đặt nhóm: chỉ áp dụng cho món do trưởng nhóm tự trả hoặc trả hộ; phải giảm đủ giá trị phiếu.',
            'zh'=>' 不與其他優惠共用。團購僅適用團長自身或墊付的商品，須足額折抵。',
            'en'=>' Cannot be combined with other offers. Group orders: applies to host-paid items or advances only; the full coupon value must be applied.'][$lang];
        if($prize['name'][$lang]!=='')$name[$lang]=$prize['name'][$lang];
    }
    return ['id'=>$prize['id'],'kind'=>$prize['kind'],'weight'=>$prize['weight'],'valid_days'=>$prize['valid_days'],
        'item_id'=>$prize['item_id'],'cup_size'=>$prize['cup_size'],'amount'=>$prize['amount'],'min_spend'=>$spend,'min_paid_cups'=>$cups,'name'=>$name,'terms'=>$terms];
}
function hgr_reward_pool(?array $config=null): ?array {
    try{
        $config=hgr_validate_config($config??hgr_config());$pool=[];
        foreach($config['prizes'] as $prize)if($prize['enabled']&&$prize['weight']>0)$pool[]=hgr_reward_template($prize);
        return $pool?:null;
    }catch(InvalidArgumentException $error){return null;}
}
function hgr_public_pool(array $pool): array {
    $result=[];
    foreach($pool as $prize)$result[]=['id'=>$prize['id'],'kind'=>$prize['kind'],'name'=>$prize['name'],'terms'=>$prize['terms'],
        'odds_percent'=>$prize['weight']/100,'valid_days'=>$prize['valid_days'],'min_spend'=>$prize['min_spend'],
        'min_paid_cups'=>$prize['min_paid_cups'],'amount'=>$prize['amount']];
    return $result;
}
function hgr_odds_text(?array $pool=null): string {
    $pool=$pool??hgr_reward_pool();if(!$pool)return hgr_text('prepare');$parts=[];$lang=function_exists('current_lang')?current_lang():'vi';
    foreach($pool as $prize){$percent=rtrim(rtrim(number_format($prize['weight']/100,2,'.',''),'0'),'.');$parts[]=($prize['name'][$lang]??$prize['name']['vi']).' '.$percent.'%';}
    return implode(' · ',$parts);
}
function hgr_ready(): bool { return hgr_reward_pool()!==null; }
/** Supplied board pools stay immutable even after campaign or catalog settings change. */
function hgr_issue_reward_locked(string $token,?array $pool=null): array {
    if(!db()->inTransaction())throw new RuntimeException('game_reward_requires_transaction');
    $pool=$pool??hgr_reward_pool();if(!$pool||!array_is_list($pool)||count($pool)>12)throw new RuntimeException('rewards_unavailable');
    $total=0;
    foreach($pool as $prize){
        if(!is_array($prize)||!is_int($prize['weight']??null)||$prize['weight']<1||$prize['weight']>10000
            ||!is_int($prize['valid_days']??null)||$prize['valid_days']<1||$prize['valid_days']>365
            ||!in_array($prize['kind']??'', ['topping','tea','discount'],true)||!is_array($prize['name']??null)||!is_array($prize['terms']??null))throw new RuntimeException('rewards_unavailable');
        $total+=$prize['weight'];
    }
    if($total!==10000)throw new RuntimeException('rewards_unavailable');
    $draw=random_int(1,10000);$reward=null;
    foreach($pool as $prize){$draw-=$prize['weight'];if($draw<=0){$reward=$prize;break;}}
    if(!$reward)throw new RuntimeException('rewards_unavailable');
    do{$code='GAME-'.strtoupper(bin2hex(random_bytes(6)));$st=db()->prepare('SELECT COUNT(*) FROM customer_coupons WHERE code=?');$st->execute([$code]);}while((int)$st->fetchColumn()>0);
    db()->prepare("INSERT INTO customer_coupons(wallet_token,code,coupon_type,amount) VALUES(?,?,'game_reward',?)")->execute([$token,$code,$reward['amount']]);
    $couponId=(int)db()->lastInsertId();$expires=gmdate('Y-m-d H:i:s',time()+$reward['valid_days']*86400);
    $reward['coupon_code']=$code;$reward['coupon_amount']=$reward['amount'];$reward['expires_at']=str_replace(' ','T',$expires).'Z';
    db()->prepare('INSERT INTO game_coupon_details(coupon_id,reward_json,expires_at) VALUES(?,?,?)')->execute([$couponId,json_encode($reward,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$expires]);
    return $reward;
}
function hgr_coupon_record(array $row): ?array {
    if(($row['coupon_type']??'')!=='game_reward')return ['code'=>$row['code'],'type'=>$row['coupon_type'],'amount'=>(float)$row['amount'],'source'=>'game'];
    $details=hym_reward_one('SELECT reward_json,expires_at FROM game_coupon_details WHERE coupon_id=?',[(int)$row['id']]);
    if(!$details||strtotime($details['expires_at'].' UTC')<=time())return null;
    $reward=json_decode($details['reward_json'],true);if(!is_array($reward))return null;
    return ['code'=>$row['code'],'type'=>'game_reward','amount'=>(float)$row['amount'],'source'=>'game','reward'=>$reward];
}
function hgr_label(array $reward,string $field='name'): string {
    $data=$reward['reward']??$reward;$lang=current_lang();return (string)($data[$field][$lang]??$data[$field]['vi']??'');
}
function hgr_expiry(string $utc): string {
    return (new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('Y-m-d H:i').' (UTC+7)';
}
/** Pure cart calculation. Tea requires four paid cups plus the separate gift cup. */
function hgr_calculate(array $cart,array $record,array $result,?array $giftLineKeys=null): array {
    $reward=$record['reward']??null;if(!is_array($reward)){$result['valid']=false;return $result;}
    $drinkTotal=0.0;$qty=0;$candidate=null;
    foreach($cart as $key=>$row){
        if(empty($row['product_id'])||!empty($row['standalone_topping_id']))continue;
        $count=max(0,(int)($row['qty']??0));if($count<1)continue;
        $base=max(0,(float)($row['base_price']??0));if($base<=0)continue;$qty+=$count;$drinkTotal+=$base*$count;
        $giftEligible=$giftLineKeys===null||in_array((string)$key,$giftLineKeys,true);
        if($giftEligible&&$reward['kind']==='tea'&&(int)$row['product_id']===(int)$reward['item_id']&&(string)($row['cup_size']??'')===(string)$reward['cup_size']&&$base>0&&($candidate===null||$base<$candidate['price']))$candidate=['key'=>(string)$key,'price'=>$base];
        if($giftEligible&&$reward['kind']==='topping')foreach($row['toppings']??[] as $top){$price=max(0,(float)($top['price']??0));if((int)($top['id']??0)===(int)$reward['item_id']&&$price>0&&($candidate===null||$price<$candidate['price']))$candidate=['key'=>(string)$key,'price'=>$price];}
    }
    $result['eligible_qty']=$qty;
    $required=max(1,(int)$reward['min_paid_cups'])+($reward['kind']==='tea'?1:0);
    if($qty<$required||cart_subtotal($cart)<(float)$reward['min_spend'])return $result;
    if($reward['kind']==='discount'){
        $remaining=max(0,(float)$record['amount']);foreach($cart as $key=>$row){$take=min($remaining,max(0,(float)($row['unit_price']??0)*(int)($row['qty']??0)));if($take>0){$result['line_discounts'][(string)$key]=$take;$result['discount']+=$take;$remaining-=$take;}}
    }elseif($candidate){$result['line_discounts'][$candidate['key']]=$candidate['price'];$result['discount']=$candidate['price'];$result['free_qty']=1;}
    return $result;
}
