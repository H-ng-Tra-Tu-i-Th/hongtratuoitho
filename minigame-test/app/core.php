<?php
declare(strict_types=1);
/* Ký Ức 10 Ly / standalone v1.1.0. No Composer, external services or shared databases. */
if (!defined('KYUC_APP')) { http_response_code(404); exit; }
const KYUC_VERSION = '1.1.0';
const DATA_PREFIX = "<?php http_response_code(404); exit; ?>\n";

final class ApiError extends RuntimeException {
    public function __construct(public string $key, public int $status = 400) { parent::__construct($key); }
}
function fail(string $key, int $status = 400): never { throw new ApiError($key, $status); }
function is_secure(): bool { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443; }
function is_local(): bool { return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true); }
function headers_safe(): void {
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: same-origin');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; font-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}
function session_begin(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
    session_name('KYUC10_' . substr(hash('sha256', dirname(__DIR__)),0,12));
    $base = str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    session_set_cookie_params(['lifetime'=>0,'path'=>rtrim($base,'/').'/', 'secure'=>is_secure(),'httponly'=>true,'samesite'=>'Lax']);
    session_start();
    if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    if (isset($_SESSION['last_seen']) && time() - (int)$_SESSION['last_seen'] > 7200) {
        unset($_SESSION['uid'], $_SESSION['av']); session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = time();
}
function csrf_check(): void {
    $s = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? '');
    if (!$s || !hash_equals((string)($_SESSION['csrf'] ?? ''), $s)) fail('csrf',403);
}
function cfg(): array { static $c; return $c ??= require __DIR__.'/config.php'; }
function uid(): string { return bin2hex(random_bytes(12)); }
function text(mixed $v, int $max=100): string {
    if (!is_string($v)) fail('invalid'); $v=trim($v);
    if (strlen($v) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $v)) fail('invalid'); return $v;
}
function integer(mixed $v, int $min, int $max): int {
    if (filter_var($v, FILTER_VALIDATE_INT) === false || (int)$v < $min || (int)$v > $max) fail('invalid'); return (int)$v;
}
function username(mixed $v): string {
    $s=strtolower(text($v,32)); if (!preg_match('/^[a-z0-9][a-z0-9_.-]{3,31}$/D',$s)) fail('username'); return $s;
}
function password_valid(mixed $p): string {
    if (!is_string($p) || strlen($p)>72 || str_contains($p,"\0")) fail('password');
    $count=preg_match_all('/./us',$p); if($count===false || $count<10) fail('password'); return $p;
}
function code_normal(string $v): string { return preg_replace('/[^A-Z0-9]/','', strtoupper($v)); }
function order_normal(mixed $v): string {
    $v=strtoupper(text($v,100)); if (!preg_match('/^[A-Z0-9][A-Z0-9._\/-]{2,99}$/D',$v)) fail('order_format'); return $v;
}
function code_hash(string $v): string { return hash_hmac('sha256',code_normal($v),cfg()['secret']); }
function new_code(string $prefix): string {
    $chars='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $v='';
    for($i=0;$i<16;$i++) $v.=$chars[random_int(0,strlen($chars)-1)];
    return $prefix.'-'.implode('-',str_split($v,4));
}
function storage_path_safe(string $dir): string {
    $real=realpath($dir); if (!$real || !is_dir($real) || !is_writable($real)) fail('storage_path');
    $doc=realpath($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__));
    $app=realpath(dirname(__DIR__));
    foreach(array_filter([$doc,$app]) as $public) {
        if ($real === $public || str_starts_with($real,$public.DIRECTORY_SEPARATOR)) fail('storage_public');
    }
    if (preg_match('~/(public_html|www|httpdocs|htdocs)(/|$)~i',str_replace('\\','/',$real))) fail('storage_public');
    return $real;
}
/** All mutations use one exclusive lock plus same-directory atomic replacement.
 * Stored JSON and a previous-state backup are outside the public document root.
 * This is intentionally a small-campaign file store, NOT a multi-server database.
 */
function store(callable $fn, bool $write=true): mixed {
    $dir=cfg()['data_dir']; $lock=fopen($dir.'/transaction.lock','c');
    if (!$lock || !flock($lock, $write ? LOCK_EX : LOCK_SH)) throw new RuntimeException('Store lock unavailable');
    try {
        $raw=file_get_contents($dir.'/state.php');
        if ($raw === false || !str_starts_with($raw,DATA_PREFIX)) throw new RuntimeException('Invalid store file');
        $d=json_decode(substr($raw,strlen(DATA_PREFIX)),true,512,JSON_THROW_ON_ERROR);
        $result=$fn($d);
        if ($write) {
            $next=DATA_PREFIX.json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            $tmp=$dir.'/state-'.bin2hex(random_bytes(8)).'.php';
            $f=fopen($tmp,'xb'); if (!$f) throw new RuntimeException('Store temp unavailable');
            chmod($tmp,0600);
            try {
                $remaining=$next; while(strlen($remaining)) {
                    $n=fwrite($f,$remaining); if ($n===false || $n===0) throw new RuntimeException('Store write failed');
                    $remaining=substr($remaining,$n);
                }
                fflush($f); if(function_exists('fsync')) fsync($f);
            } finally { fclose($f); }
            // Keep one prior valid snapshot. Off-host backups are still necessary.
            if (!copy($dir.'/state.php',$dir.'/previous.php')) { @unlink($tmp); throw new RuntimeException('Store backup failed'); }
            chmod($dir.'/previous.php',0600);
            if(!rename($tmp,$dir.'/state.php')) { @unlink($tmp); throw new RuntimeException('Store replace failed'); }
        }
        return $result;
    } finally { flock($lock,LOCK_UN); fclose($lock); }
}
function audit(array &$d,string $event,string $detail=''): void {
    $d['audit'][]=['at'=>time(),'actor'=>$_SESSION['uid'] ?? 'system','event'=>$event,'detail'=>$detail];
    if(count($d['audit'])>5000) $d['audit']=array_slice($d['audit'],-5000);
}
function rate_limit(string $scope,int $limit=25,int $seconds=900): void {
    $key=hash_hmac('sha256',$scope,cfg()['secret']);
    $ok=store(function(array &$d) use($key,$limit,$seconds) {
        $now=time();
        foreach($d['limits'] as $k=>$v) if($v['until']<$now) unset($d['limits'][$k]);
        $item=$d['limits'][$key] ?? ['until'=>$now+$seconds,'n'=>0];
        if($item['n'] >= $limit) return false;
        $item['n']++; $d['limits'][$key]=$item; return true;
    });
    if(!$ok) fail('rate_limit',429);
}
function public_user(array $u): array { return array_intersect_key($u,array_flip(['id','username','name','role','active','created'])); }
function require_user(array $d,bool $admin=false): array {
    $u=$d['users'][$_SESSION['uid'] ?? ''] ?? null;
    if(!$u || !$u['active'] || $u['auth_version'] !== ($_SESSION['av'] ?? -1)) fail('login_required',401);
    if($admin && $u['role']!=='admin') fail('forbidden',403); return $u;
}
function sign_in(array $u): void {
    session_regenerate_id(true); $_SESSION['uid']=$u['id']; $_SESSION['av']=$u['auth_version'];
    $_SESSION['csrf']=bin2hex(random_bytes(24));
}
function coupon_public(array $c): array {
    if($c['status']==='active' && $c['expires_at']<=time()) $c['status']='expired'; return $c;
}
function game_public(array $g): array {
    if (isset($g['matching'])) $g['matching_progress'] = matching_summary($g['matching']);
    unset($g['code_hash'], $g['matching']);
    $g['status']=$g['revoked_at'] ? 'revoked' : ($g['played_at'] ? 'played' : ($g['expires_at']<=time() ? 'expired' : ($g['claimed_at'] ? 'ready' : 'issued')));
    return $g;
}
function eligible_rewards(array $d): array {
    return array_values(array_filter($d['rewards'],fn($r)=>$r['active'] && $r['weight']>0 && $r['stock']!==0));
}
function choose_reward(array $d): array {
    $eligible=eligible_rewards($d); $sum=array_sum(array_column($eligible,'weight')); if($sum<=0) fail('no_rewards',409);
    $n=random_int(1,$sum); foreach($eligible as $r) { $n-=$r['weight']; if($n<=0) return $r; } throw new RuntimeException('Unreachable reward');
}
function fallback_ok(array $d): bool {
    foreach($d['rewards'] as $r) if($r['active'] && $r['weight']>0 && $r['stock']===-1) return true; return false;
}
function seed_data(string $name,string $admin,string $pass): array {
    $id=uid();
    $make=function($id,$vi,$zh,$tvi,$tzh,$w,$type,$spend,$cups,$val,$icon){return [
        'id'=>$id,'name_vi'=>$vi,'name_zh'=>$zh,'terms_vi'=>$tvi,'terms_zh'=>$tzh,
        'weight'=>$w,'type'=>$type,'min_spend'=>$spend,'min_cups'=>$cups,'value'=>$val,'icon'=>$icon,
        'days'=>7,'stock'=>-1,'active'=>true
    ];};
    return ['schema'=>1,'version'=>KYUC_VERSION,'created'=>time(),
      'settings'=>['active'=>false,'demo'=>true,'threshold'=>10,'code_days'=>7,'support'=>'','campaign_vi'=>'Ký Ức 10 Ly','campaign_zh'=>'10 杯童年回憶','store_vi'=>'Cửa hàng tham gia chương trình','store_zh'=>'活動指定門市','privacy_vi'=>'Chỉ thu thập tên tài khoản, biệt danh và lịch sử trò chơi/đổi quà. Liên hệ cửa hàng để yêu cầu xem, sửa hoặc xóa dữ liệu.','privacy_zh'=>'僅收集會員帳號、暱稱與遊戲／核銷紀錄。查詢、更正或刪除資料請洽門市。'],
      'users'=>[$id=>['id'=>$id,'username'=>$admin,'name'=>$name,'password'=>password_hash($pass,PASSWORD_DEFAULT),'role'=>'admin','active'=>true,'auth_version'=>1,'created'=>time()]],
      'rewards'=>[
        'topping'=>$make('topping','Tặng 1 topping','免費加料 1 份','Tặng 1 topping chỉ định khi mua ít nhất 1 ly đồ uống trong đơn tiếp theo.','下次購買至少 1 杯付費飲料，贈送指定加料 1 份。',50,'topping',0,1,0,'tea'),
        'discount'=>$make('discount','Giảm 5.000đ','折抵 5,000đ','Giảm 5.000đ cho đơn tiếp theo từ 50.000đ.','下一筆訂單滿 50,000đ 可折 5,000đ。',35,'discount',50000,1,5000,'ticket'),
        'tea'=>$make('tea','Tặng 1 ly hồng trà','回憶紅茶 1 杯','Tặng 1 Hồng Trà Tuổi Thơ khi mua 4 ly trả phí trong đơn tiếp theo.','下次購買 4 杯付費飲料，贈送指定回憶紅茶 1 杯。',15,'drink',0,4,0,'cup')
      ],'games'=>[],'coupons'=>[],'audit'=>[],'limits'=>[]];
}
function output_json(array $out,int $status=200): never {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($out+['csrf'=>$_SESSION['csrf'] ?? ''],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); exit;
}

require_once __DIR__.'/matching.php';
