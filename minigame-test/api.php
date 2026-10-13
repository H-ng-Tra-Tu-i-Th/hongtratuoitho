<?php
declare(strict_types=1);
define('KYUC_APP',true); require __DIR__.'/app/core.php';
headers_safe(); session_begin();
try {
    if(!is_file(__DIR__.'/app/config.php')) fail('not_installed',503);
    $action=(string)($_GET['action'] ?? 'bootstrap');
    $method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
    $read=['bootstrap','wallet','admin_data'];
    if(!in_array($action,$read,true)) {
        if($method!=='POST') fail('method',405);
        if(!is_secure() && !is_local()) fail('https_required',403);
        csrf_check();
        if((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>32768) fail('invalid',413);
        $p=json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR);
        if(!is_array($p)) fail('invalid');
    } else { if($method!=='GET') fail('method',405); $p=[]; }
    $ip=(string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $result=null;
    switch($action) {
    case 'bootstrap':
        $result=store(function(array $d) {
            $u=null; try{$u=require_user($d);}catch(ApiError){}
            $rewards=eligible_rewards($d); $total=array_sum(array_column($rewards,'weight'));
            foreach($rewards as &$r) { $r['chance']=$total ? round($r['weight']/$total*100,2) : 0; unset($r['weight'],$r['stock']); } unset($r);
            $ready=[]; if($u) foreach($d['games'] as $g) if($g['user_id']===$u['id'] && game_public($g)['status']==='ready') $ready[]=game_public($g);
            return ['version'=>KYUC_VERSION,'settings'=>$d['settings'],'user'=>$u?public_user($u):null,'rewards'=>$rewards,'ready'=>$ready,'now'=>time()];
        },false); break;
    case 'register':
        rate_limit('register:'.$ip,6,3600);
        $un=username($p['username']??''); $name=text($p['name']??'',120);
        if(!$name || ($p['consent']??false)!==true) fail('consent');
        $pass=password_valid($p['password']??''); $hash=password_hash($pass,PASSWORD_DEFAULT);
        $u=store(function(array &$d) use($un,$name,$hash) {
            foreach($d['users'] as $v) if($v['username']===$un) fail('username_taken',409);
            $id=uid(); $u=['id'=>$id,'username'=>$un,'name'=>$name,'password'=>$hash,'role'=>'member','active'=>true,'auth_version'=>1,'created'=>time()];
            $d['users'][$id]=$u; audit($d,'member_registered',$un); return $u;
        }); sign_in($u); $result=['user'=>public_user($u)]; break;
    case 'login':
        rate_limit('login-ip:'.$ip,30,900);
        $un=strtolower(text($p['username']??'',32)); rate_limit('login-account:'.$un,12,900);
        $pass=$p['password']??''; if(!is_string($pass) || strlen($pass)>200) fail('invalid');
        $u=store(function(array $d) use($un){ foreach($d['users'] as $v) if($v['username']===$un) return $v; return null;},false);
        $hash=$u['password']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        if(!password_verify($pass,$hash) || !$u || !$u['active']) fail('login_failed',401);
        sign_in($u); $result=['user'=>public_user($u)]; break;
    case 'logout':
        $_SESSION=[]; session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(24)); $result=[]; break;
    case 'password':
        rate_limit('password:'.$ip,12,900); $pass=password_valid($p['new_password']??'');
        $u=store(function(array &$d) use($p,$pass){
            $u=require_user($d); if(!password_verify((string)($p['old_password']??''),$u['password'])) fail('login_failed',403);
            $d['users'][$u['id']]['password']=password_hash($pass,PASSWORD_DEFAULT);
            $d['users'][$u['id']]['auth_version']++; audit($d,'password_changed',$u['username']); return $d['users'][$u['id']];
        }); sign_in($u); $result=[]; break;
    case 'match_start':
        matching_throttle();
        $mode=text($p['mode']??'demo',8);
        if(!in_array($mode,['demo','real'],true)) fail('invalid');
        if($mode==='demo') {
            store(function(array $d){if(!$d['settings']['demo']) fail('demo_disabled',403);},false);
            if(!isset($_SESSION['matching_demo']) || ($p['restart']??false)===true)
                $_SESSION['matching_demo']=matching_new();
            $result=['board'=>matching_view($_SESSION['matching_demo'])];
        } else {
            $id=text($p['game_id']??'',40);
            $result=store(function(array &$d) use($id){
                $g=matching_game($d,$id);
                if($g['coupon_id']) return ['coupon'=>coupon_public($d['coupons'][$g['coupon_id']])];
                if(!isset($d['games'][$id]['matching'])) {
                    $d['games'][$id]['matching']=matching_new();
                    audit($d,'matching_started',$id);
                }
                return ['board'=>matching_view($d['games'][$id]['matching'])];
            });
        }
        break;
    case 'match_flip':
        matching_throttle();
        $mode=text($p['mode']??'',8); $boardId=text($p['board_id']??'',40);
        $seq=integer($p['seq']??null,0,1000000); $pos=integer($p['position']??null,0,11);
        if(!in_array($mode,['demo','real'],true)) fail('invalid');
        if($mode==='demo') {
            store(function(array $d){if(!$d['settings']['demo']) fail('demo_disabled',403);},false);
            if(!isset($_SESSION['matching_demo'])) fail('board_missing',409);
            $event=matching_flip($_SESSION['matching_demo'],$boardId,$seq,$pos);
            $result=['board'=>matching_view($_SESSION['matching_demo']),'event'=>$event];
        } else {
            $id=text($p['game_id']??'',40);
            $result=store(function(array &$d) use($id,$boardId,$seq,$pos){
                $g=matching_game($d,$id);
                if($g['coupon_id']) return ['coupon'=>coupon_public($d['coupons'][$g['coupon_id']])];
                if(!isset($d['games'][$id]['matching'])) fail('board_missing',409);
                $event=matching_flip($d['games'][$id]['matching'],$boardId,$seq,$pos);
                if($event==='complete') audit($d,'matching_completed',$id);
                return ['board'=>matching_view($d['games'][$id]['matching']),'event'=>$event];
            });
        }
        break;
    case 'demo':
        // Legacy single-card calls can no longer draw a prize without a completed matching board.
        matching_throttle();
        $b=$_SESSION['matching_demo']??null;
        if(!$b || !$b['completed_at'] || count($b['matched'])!==12) fail('matching_incomplete',409);
        $boardId=text($p['board_id']??'',40);
        if(!hash_equals($b['id'],$boardId)) fail('board_changed',409);
        $result=store(function(array $d) use($b){
            if(!$d['settings']['demo']) fail('demo_disabled',403);
            if(isset($_SESSION['matching_demo']['result'])) return $_SESSION['matching_demo']['result'];
            $r=choose_reward($d); unset($r['stock'],$r['weight']);
            return ['demo'=>true,'reward'=>$r,'game_stats'=>matching_summary($b),'code'=>null];
        },false);
        $_SESSION['matching_demo']['result']=$result;
        break;
    case 'claim':
        rate_limit('claim:'.$ip,20,900); $hash=code_hash(text($p['code']??'',60));
        $result=store(function(array &$d) use($hash){
            $u=require_user($d); if(!$d['settings']['active']) fail('campaign_paused',409);
            foreach($d['games'] as $id=>$g) {
                if(!hash_equals($g['code_hash'],$hash) || $g['user_id']!==$u['id']) continue;
                if($g['revoked_at']) fail('code_invalid',409);
                if($g['played_at']) return ['game'=>game_public($g),'coupon'=>coupon_public($d['coupons'][$g['coupon_id']])];
                if($g['expires_at']<=time()) fail('code_expired',409);
                if(!$g['claimed_at']) { $d['games'][$id]['claimed_at']=time(); audit($d,'game_claimed',$id); }
                return ['game'=>game_public($d['games'][$id])];
            }
            fail('code_invalid',404);
        }); break;
    case 'play':
        rate_limit('play:'.$ip,40,600); $id=text($p['game_id']??'',40);
        $result=store(function(array &$d) use($id){
            $u=require_user($d); $g=matching_game($d,$id);
            // Retry / double-click always returns the original persisted coupon, never draws again.
            if($g['coupon_id']) return ['coupon'=>coupon_public($d['coupons'][$g['coupon_id']]),'replayed'=>true];
            $b=$g['matching']??null;
            if(!$b || !$b['completed_at'] || count($b['matched'])!==12) fail('matching_incomplete',409);
            $r=choose_reward($d);
            if($r['stock']>0) $d['rewards'][$r['id']]['stock']--;
            unset($r['stock'],$r['weight'],$r['active']);
            $cid=uid(); $code=new_code('KY');
            while(count(array_filter($d['coupons'],fn($c)=>$c['code']===$code))) $code=new_code('KY');
            $c=['id'=>$cid,'code'=>$code,'user_id'=>$u['id'],'game_id'=>$id,'reward'=>$r,
              'game_mode'=>'matching','game_stats'=>matching_summary($b),
              'issued_at'=>time(),'expires_at'=>time()+$r['days']*86400,'status'=>'active',
              'redeemed_at'=>null,'redeemed_by'=>null,'redeem_order'=>null,'redeem_spend'=>null,'redeem_cups'=>null];
            $d['coupons'][$cid]=$c; $d['games'][$id]['played_at']=time(); $d['games'][$id]['coupon_id']=$cid;
            audit($d,'reward_issued',$id.':'.$r['id']); return ['coupon'=>$c,'replayed'=>false];
        }); break;
    case 'wallet':
        $result=store(function(array $d){
            $u=require_user($d); $coupons=[]; $games=[];
            foreach($d['coupons'] as $c) if($c['user_id']===$u['id']) $coupons[]=coupon_public($c);
            foreach($d['games'] as $g) if($g['user_id']===$u['id']) $games[]=game_public($g);
            usort($coupons,fn($a,$b)=>$b['issued_at']<=>$a['issued_at']);
            usort($games,fn($a,$b)=>$b['created_at']<=>$a['created_at']);
            return ['coupons'=>$coupons,'games'=>$games];
        },false); break;
    case 'admin_data':
        $result=store(function(array $d){
            require_user($d,true);
            $games=array_values(array_map('game_public',$d['games'])); $coupons=array_values(array_map('coupon_public',$d['coupons']));
            $stats=['members'=>count(array_filter($d['users'],fn($u)=>$u['role']==='member')),'issued'=>count($games),
              'claimed'=>count(array_filter($games,fn($g)=>(bool)$g['claimed_at'])),'played'=>count(array_filter($games,fn($g)=>(bool)$g['played_at'])),
              'redeemed'=>count(array_filter($coupons,fn($c)=>$c['status']==='redeemed')),
              'available'=>count(array_filter($coupons,fn($c)=>$c['status']==='active'))];
            usort($games,fn($a,$b)=>$b['created_at']<=>$a['created_at']); usort($coupons,fn($a,$b)=>$b['issued_at']<=>$a['issued_at']);
            $days=[]; $tz=new DateTimeZone('Asia/Ho_Chi_Minh');
            for($i=6;$i>=0;$i--) {
                $day=(new DateTimeImmutable('today',$tz))->modify('-'.$i.' days'); $start=$day->getTimestamp(); $end=$start+86400;
                $days[]=['day'=>$day->format('d/m'),'played'=>count(array_filter($coupons,fn($c)=>$c['issued_at']>=$start && $c['issued_at']<$end)),
                  'redeemed'=>count(array_filter($coupons,fn($c)=>($c['redeemed_at']??0)>=$start && ($c['redeemed_at']??0)<$end))];
            }
            return ['settings'=>$d['settings'],'rewards'=>array_values($d['rewards']),
              'users'=>array_values(array_map('public_user',$d['users'])),'games'=>array_slice($games,0,500),
              'coupons'=>array_slice($coupons,0,500),'audit'=>array_slice(array_reverse($d['audit']),0,100),
              'stats'=>$stats,'days'=>$days,'storage_bytes'=>strlen(json_encode($d))];
        },false); break;
    case 'admin_issue':
        $un=username($p['username']??''); $ref=order_normal($p['order_ref']??''); $cups=integer($p['cups']??0,1,1000);
        if(($p['confirmed']??false)!==true) fail('confirm_purchase');
        $result=store(function(array &$d) use($un,$ref,$cups){
            require_user($d,true); if(!$d['settings']['active']) fail('campaign_paused',409);
            if($cups<$d['settings']['threshold']) fail('not_enough_cups');
            $user=null; foreach($d['users'] as $u) if($u['username']===$un && $u['role']==='member' && $u['active']) $user=$u;
            if(!$user) fail('member_missing',404);
            foreach($d['games'] as $g) if($g['order_ref']===$ref) fail('order_duplicate',409);
            if(!fallback_ok($d)) fail('fallback_required');
            $id=uid(); $code=new_code('KC');
            $g=['id'=>$id,'user_id'=>$user['id'],'order_ref'=>$ref,'paid_cups'=>$cups,'threshold'=>$d['settings']['threshold'],
              'created_at'=>time(),'expires_at'=>time()+$d['settings']['code_days']*86400,'claimed_at'=>null,'played_at'=>null,
              'revoked_at'=>null,'coupon_id'=>null,'code_hash'=>code_hash($code),'suffix'=>substr($code,-4)];
            $d['games'][$id]=$g; audit($d,'code_issued',$ref.':'.$un);
            return ['game'=>game_public($g),'code'=>$code,'username'=>$un];
        }); break;
    case 'admin_reissue':
        $id=text($p['game_id']??'',40);
        $result=store(function(array &$d) use($id){
            require_user($d,true); $g=$d['games'][$id]??null;
            if(!$g || $g['played_at'] || $g['revoked_at']) fail('code_invalid',409);
            if(!$d['users'][$g['user_id']]['active']) fail('member_missing',409);
            $code=new_code('KC'); $d['games'][$id]['code_hash']=code_hash($code);
            $d['games'][$id]['suffix']=substr($code,-4); $d['games'][$id]['claimed_at']=null;
            unset($d['games'][$id]['matching']);
            $d['games'][$id]['expires_at']=time()+$d['settings']['code_days']*86400;
            audit($d,'code_rotated',$g['order_ref']);
            return ['game'=>game_public($d['games'][$id]),'code'=>$code,'username'=>$d['users'][$g['user_id']]['username']];
        }); break;
    case 'admin_revoke':
        $id=text($p['game_id']??'',40);
        $result=store(function(array &$d) use($id){
            require_user($d,true); if(!isset($d['games'][$id])) fail('not_found',404);
            $d['games'][$id]['revoked_at']=time(); $cid=$d['games'][$id]['coupon_id']; $already=false;
            if($cid) {
                if($d['coupons'][$cid]['status']==='active') $d['coupons'][$cid]['status']='revoked';
                if($d['coupons'][$cid]['status']==='redeemed') $already=true;
            }
            audit($d,'order_revoked',$d['games'][$id]['order_ref']); return ['already_redeemed'=>$already];
        }); break;
    case 'admin_lookup':
        $code=code_normal(text($p['code']??'',60));
        $result=store(function(array $d) use($code){
            require_user($d,true); foreach($d['coupons'] as $c) if(code_normal($c['code'])===$code) {
                return ['coupon'=>coupon_public($c),'user'=>public_user($d['users'][$c['user_id']]),'original_order'=>$d['games'][$c['game_id']]['order_ref']];
            } fail('coupon_missing',404);
        },false); break;
    case 'admin_redeem':
        $id=text($p['coupon_id']??'',40); $ref=order_normal($p['order_ref']??'');
        $spend=integer($p['spend']??null,0,100000000); $cups=integer($p['cups']??null,0,1000);
        if(($p['confirmed']??false)!==true) fail('confirm_redeem');
        $result=store(function(array &$d) use($id,$ref,$spend,$cups){
            $admin=require_user($d,true); $c=$d['coupons'][$id]??null;
            if(!$c) fail('coupon_missing',404);
            if(coupon_public($c)['status']!=='active') fail('coupon_unusable',409);
            $g=$d['games'][$c['game_id']];
            if($g['revoked_at'] || !$d['users'][$c['user_id']]['active']) fail('coupon_unusable',409);
            if($ref===$g['order_ref']) fail('next_order_only',409);
            foreach($d['coupons'] as $old) if($old['status']==='redeemed' && $old['redeem_order']===$ref) fail('one_coupon_order',409);
            if($spend<$c['reward']['min_spend'] || $cups<$c['reward']['min_cups']) fail('minimum_not_met',409);
            $d['coupons'][$id]=array_merge($c,['status'=>'redeemed','redeemed_at'=>time(),'redeemed_by'=>$admin['id'],'redeem_order'=>$ref,'redeem_spend'=>$spend,'redeem_cups'=>$cups]);
            audit($d,'coupon_redeemed',$c['code'].':'.$ref); return ['coupon'=>$d['coupons'][$id]];
        }); break;
    case 'admin_settings':
        $result=store(function(array &$d) use($p){
            require_user($d,true); $s=$d['settings'];
            foreach(['campaign_vi','campaign_zh','store_vi','store_zh','support','privacy_vi','privacy_zh'] as $key) {
                $s[$key]=text($p[$key]??'',in_array($key,['privacy_vi','privacy_zh'],true)?1500:300);
            }
            if(!$s['campaign_vi'] || !$s['campaign_zh'] || !$s['store_vi'] || !$s['store_zh']) fail('invalid');
            $s['active']=($p['active']??false)===true; $s['demo']=($p['demo']??false)===true;
            $s['threshold']=integer($p['threshold']??null,1,100); $s['code_days']=integer($p['code_days']??null,1,90);
            if($s['active'] && !fallback_ok($d)) fail('fallback_required');
            if($s['active'] && !$s['support']) fail('support_required');
            $d['settings']=$s; audit($d,'settings_saved'); return ['settings'=>$s];
        }); break;
    case 'admin_reward':
        $result=store(function(array &$d) use($p){
            require_user($d,true); $id=text($p['id']??'',40); if(!$id) $id=uid();
            if(isset($d['rewards'][$id])===false && count($d['rewards'])>=30) fail('reward_limit');
            $r=['id'=>$id]; foreach(['name_vi','name_zh','terms_vi','terms_zh'] as $k) { $r[$k]=text($p[$k]??'',str_starts_with($k,'terms')?900:200); if(!$r[$k]) fail('invalid'); }
            $r['weight']=integer($p['weight']??null,0,10000); $r['stock']=integer($p['stock']??null,-1,100000);
            $r['days']=integer($p['days']??null,1,90); $r['min_spend']=integer($p['min_spend']??null,0,100000000);
            $r['min_cups']=integer($p['min_cups']??null,1,1000); $r['value']=integer($p['value']??0,0,100000000);
            $r['active']=($p['active']??false)===true; $r['type']=text($p['type']??'topping',20); $r['icon']=text($p['icon']??'tea',20);
            if(!in_array($r['type'],['topping','discount','drink','gift'],true) || !in_array($r['icon'],['tea','ticket','cup','gift'],true)) fail('invalid');
            if($r['type']==='discount' && ($r['value']<1 || $r['value']>$r['min_spend'])) fail('discount_invalid');
            $d['rewards'][$id]=$r; if(!fallback_ok($d)) fail('fallback_required');
            audit($d,'reward_saved',$id); return ['reward'=>$r];
        }); break;
    case 'admin_member':
        rate_limit('member-control:'.$ip,30,900);
        $id=text($p['id']??'',40);
        $result=store(function(array &$d) use($p,$id){
            $a=require_user($d,true); $u=$d['users'][$id]??null;
            if(!$u || $u['role']!=='member') fail('member_missing',404);
            if(!password_verify((string)($p['admin_password']??''),$a['password'])) fail('login_failed',403);
            if(isset($p['new_password']) && $p['new_password']!=='') $d['users'][$id]['password']=password_hash(password_valid($p['new_password']),PASSWORD_DEFAULT);
            $d['users'][$id]['active']=($p['active']??false)===true;
            $d['users'][$id]['auth_version']++; audit($d,'member_updated',$u['username']); return [];
        }); break;
    default: fail('not_found',404);
    }
    output_json(['ok'=>true,'data'=>$result]);
} catch(ApiError $e) { output_json(['ok'=>false,'error'=>$e->key],$e->status); }
catch(JsonException $e) { output_json(['ok'=>false,'error'=>'invalid'],400); }
catch(Throwable $e) {
    error_log('KYUC10 '.get_class($e).': '.$e->getMessage());
    output_json(['ok'=>false,'error'=>'server_error'],500);
}
