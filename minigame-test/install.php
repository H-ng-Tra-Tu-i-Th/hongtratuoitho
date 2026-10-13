<?php
declare(strict_types=1);
define('KYUC_APP',true); require __DIR__.'/app/core.php'; headers_safe(); session_begin();
if(is_file(__DIR__.'/app/config.php')) { header('Location: ./'); exit; }
$error=''; $done=false;
$key=is_file(__DIR__.'/setup-key.php') ? require __DIR__.'/setup-key.php' : '';
$doc=realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) ?: __DIR__;
$pos=strpos(str_replace('\\','/',$doc),'/public_html');
$home=$pos!==false ? substr($doc,0,$pos) : dirname($doc);
$default=$home.'/kyuc10_private_'.substr(hash('sha256',__DIR__),0,8);
if($_SERVER['REQUEST_METHOD']==='POST') {
    $lock=null;
    try {
        if(version_compare(PHP_VERSION,'8.2.0','<')) fail('需要 PHP 8.2 或以上 / Cần PHP 8.2+');
        if(!is_secure() && !is_local()) fail('請先開啟 HTTPS / Vui lòng bật HTTPS trước khi cài đặt.');
        csrf_check();
        if(!is_string($key) || strlen($key)<24 || !hash_equals($key,(string)($_POST['setup_key']??''))) fail('安裝金鑰不符。請先編輯 setup-key.php，設定至少 24 字元金鑰。 / Khóa cài đặt không hợp lệ.');
        $admin=username($_POST['username']??''); $name=text($_POST['name']??'',120); if(!$name) fail('請輸入管理員暱稱 / Nhập tên quản trị viên.');
        $pass=password_valid($_POST['password']??''); if($pass!==($_POST['password_confirm']??'')) fail('兩次密碼不符 / Mật khẩu không khớp.');
        if(!isset($_POST['ack'])) fail('請確認部署說明 / Vui lòng xác nhận.');
        $path=text($_POST['data_dir']??'',800);
        if(!str_starts_with($path,'/') || str_contains($path,'..')) fail('請填絕對路徑 / Nhập đường dẫn tuyệt đối.');
        $lock=fopen(__DIR__.'/app/install.lock','c');
        if(!$lock || !flock($lock,LOCK_EX)) fail('無法鎖定安裝 / Không thể khóa cài đặt.');
        if(is_file(__DIR__.'/app/config.php')) fail('已完成安裝 / Đã cài đặt.');
        if(!is_dir($path) && !mkdir($path,0700,true)) fail('無法建立資料夾；請於 public_html 外建立 / Không tạo được thư mục riêng.');
        $path=storage_path_safe($path);
        if(is_file($path.'/state.php')) fail('此資料夾已有資料，請換新資料夾 / Thư mục đã có dữ liệu.');
        $data=seed_data($name,$admin,$pass);
        $state=DATA_PREFIX.json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        if(file_put_contents($path.'/state.php',$state,LOCK_EX)===false) fail('資料寫入失敗 / Không ghi được dữ liệu.');
        chmod($path.'/state.php',0600);
        file_put_contents($path.'/.htaccess',"Require all denied\n");
        file_put_contents($path.'/index.php',"<?php http_response_code(404); exit;\n");
        $config=['data_dir'=>$path,'secret'=>bin2hex(random_bytes(32)),'installed_at'=>time()];
        $tmp=__DIR__.'/app/config-install.php';
        if(file_put_contents($tmp,"<?php\nreturn ".var_export($config,true).";\n",LOCK_EX)===false) fail('設定寫入失敗 / Không ghi được cấu hình.');
        chmod($tmp,0600);
        if(!rename($tmp,__DIR__.'/app/config.php')) fail('設定儲存失敗 / Không lưu được cấu hình.');
        sign_in(array_values($data['users'])[0]); $done=true;
    } catch(ApiError $e) { $error=$e->key; }
    catch(Throwable $e) { error_log('KYUC install: '.$e->getMessage()); $error='安裝未完成。請確認資料夾寫入權限與 PHP 版本。 / Kiểm tra quyền ghi và phiên bản PHP.'; }
    finally { if(is_resource($lock)) { flock($lock,LOCK_UN); fclose($lock); } }
}
function h(string $v): string { return htmlspecialchars($v,ENT_QUOTES,'UTF-8'); }
?><!doctype html>
<html lang="zh-Hant"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>安裝 · Ký Ức 10 Ly</title><link rel="icon" href="assets/favicon.png"><link rel="stylesheet" href="assets/style.css?v=1.1.0"></head>
<body class="setup-body"><main class="setup-card"><img class="setup-logo" src="assets/logo.png" alt="Hồng Trà Tuổi Thơ"><div class="eyebrow">STANDALONE EDITION · V1.1.0</div><h1>開啟一段新回憶</h1><p class="muted">Cài đặt Ký Ức 10 Ly · 獨立遊戲安裝</p>
<?php if($done): ?>
<div class="notice success"><strong>安裝成功 / Cài đặt thành công</strong><p>請於 File Manager 刪除 <code>install.php</code> 與 <code>setup-key.php</code>。資料已分開保存，活動預設暫停、試玩已開啟。到後台設定門市、聯絡方式及獎項後，再開啟正式活動。</p><p>Hãy xóa hai tệp cài đặt. Chương trình thật đang tạm dừng; chế độ chơi thử đã bật.</p></div>
<a class="btn primary wide" href="admin.php">進入管理後台 / Quản trị →</a><a class="btn ghost wide" href="./">前往遊戲 / Trò chơi</a>
<?php else: ?>
<?php if($error): ?><div class="notice danger" role="alert"><?=h($error)?></div><?php endif ?>
<div class="notice"><strong>不需要 MySQL · Không cần MySQL</strong><p>PHP <?=h(PHP_VERSION)?> · 檔案資料儲存於網站根目錄以外，適合單一主機的小型活動。請使用 HTTPS。請勿共用其他專案的設定或資料夾。</p></div>
<?php if(!is_string($key)||strlen($key)<24): ?><div class="notice danger">先用 cPanel File Manager 編輯 <code>setup-key.php</code>：把空字串換成自訂的 24 字元以上安裝金鑰，儲存後重新整理。<br>Đặt khóa bí mật ít nhất 24 ký tự trong <code>setup-key.php</code>.</div><?php endif ?>
<form method="post" class="stack" autocomplete="off">
<input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
<label>安裝金鑰 / Khóa cài đặt<input name="setup_key" type="password" minlength="24" required autocomplete="off"></label>
<div class="form-grid"><label>管理員帳號 / Tài khoản<input name="username" minlength="4" maxlength="32" pattern="[a-zA-Z0-9][a-zA-Z0-9_.\-]{3,31}" required autocomplete="username" placeholder="例：store_admin"></label><label>暱稱 / Tên hiển thị<input name="name" maxlength="40" value="Admin" required></label></div>
<label>密碼 / Mật khẩu<input name="password" type="password" minlength="10" maxlength="72" autocomplete="new-password" required><small>10–72 字元；與安裝金鑰不同。 / 10–72 ký tự.</small></label>
<label>確認密碼 / Xác nhận mật khẩu<input name="password_confirm" type="password" minlength="10" maxlength="72" autocomplete="new-password" required></label>
<label>私有資料夾絕對路徑 / Thư mục dữ liệu riêng<input name="data_dir" value="<?=h($default)?>" required><small>必須在 public_html 與所有網站公開根目錄以外。安裝程式會嘗試自動建立。 / Phải nằm ngoài thư mục web công khai.</small></label>
<label class="check"><input type="checkbox" name="ack" required><span>我確認此為獨立安裝，資料夾不對外公開；正式活動上線前會審核獎項與活動條款。<br>Tôi sẽ kiểm tra quà tặng và thể lệ trước khi mở chương trình.</span></label>
<button class="btn primary wide" type="submit">建立遊戲 / Cài đặt trò chơi →</button>
</form><?php endif ?><p class="fineprint">Hồng Trà Tuổi Thơ · Ký Ức 10 Ly · V1.1.0</p></main></body></html>
