<?php
/** Owner-only account administration. No passwords/hashes in DTOs or audit logs. */
if(!function_exists('db')){http_response_code(404);exit;}
class HaaAccountError extends RuntimeException {}
function haa_fail(string $key): never {throw new HaaAccountError($key);}
function haa_query(string $sql,array $args=[]): PDOStatement {$st=db()->prepare($sql);$st->execute($args);return $st;}
function haa_schema(): void {
 static $done=false;if($done)return;$pdo=db();if($pdo->inTransaction())haa_fail('transaction');
 $marker=haa_query('SELECT setting_value FROM settings WHERE setting_key=?',['admin_accounts_schema']);
 if($marker->fetchColumn()!=='19.16.0'){
  $lock='haa-schema:'.substr(hash('sha256',(string)$pdo->query('SELECT DATABASE()')->fetchColumn()),0,40);
  if((int)haa_query('SELECT GET_LOCK(?,10)',[$lock])->fetchColumn()!==1)haa_fail('busy');
  try{if(haa_query('SELECT setting_value FROM settings WHERE setting_key=?',['admin_accounts_schema'])->fetchColumn()!=='19.16.0'){
   foreach(['role'=>"VARCHAR(16) NOT NULL DEFAULT 'admin'",'display_name'=>"VARCHAR(120) NOT NULL DEFAULT ''",'auth_version'=>'INT UNSIGNED NOT NULL DEFAULT 1','store_id'=>'BIGINT UNSIGNED NULL DEFAULT NULL'] as $col=>$definition){
    $found=haa_query('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',['admins',$col])->fetchColumn();if(!(int)$found)$pdo->exec("ALTER TABLE admins ADD COLUMN `$col` $definition");
   }
   $pdo->exec("CREATE TABLE IF NOT EXISTS admin_account_audit (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, actor_id INT UNSIGNED NOT NULL, target_id INT UNSIGNED NOT NULL, action VARCHAR(16) NOT NULL, before_json TEXT NULL, after_json TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_account_audit_created(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
   $pdo->beginTransaction();try{
    $rows=$pdo->query('SELECT id,role FROM admins ORDER BY id FOR UPDATE')->fetchAll();$owners=array_filter($rows,fn($r)=>$r['role']==='owner');
    if(!$owners&&$rows)haa_query("UPDATE admins SET role='owner' WHERE id=?",[(int)$rows[0]['id']]);
    haa_query('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',['admin_accounts_schema','19.16.0']);$pdo->commit();
   }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
  }}finally{haa_query('SELECT RELEASE_LOCK(?)',[$lock]);}
 }$done=true;
}
function haa_dto(array $row): array {return ['id'=>(int)$row['id'],'username'=>(string)$row['username'],'display_name'=>(string)($row['display_name']??''),'role'=>(string)($row['role']??'admin'),'store_id'=>isset($row['store_id'])&&$row['store_id']!==null&&$row['store_id']!==''?(int)$row['store_id']:null,'auth_version'=>(int)($row['auth_version']??1),'created_at'=>(string)($row['created_at']??'')];}
function haa_clear_session(): void {unset($_SESSION['admin_id'],$_SESSION['admin_user'],$_SESSION['admin_auth_version']);}
function haa_current(): ?array {
 $id=(int)($_SESSION['admin_id']??0);if($id<1)return null;
 $row=haa_query('SELECT id,username,display_name,role,store_id,auth_version,created_at FROM admins WHERE id=?',[$id])->fetch();
 if(!$row||!in_array($row['role'],['owner','admin','operator'],true)||(int)$row['auth_version']<1){haa_clear_session();return null;}
 // Preserve original sessions only until this account has ever been revised.
 if(!isset($_SESSION['admin_auth_version'])&&(int)$row['auth_version']===1)$_SESSION['admin_auth_version']=1;
 if((int)($_SESSION['admin_auth_version']??0)!==(int)$row['auth_version']){haa_clear_session();return null;}
 $_SESSION['admin_user']=$row['username'];return haa_dto($row);
}
function haa_sign_in(string $username,string $password): bool {
 $row=haa_query('SELECT * FROM admins WHERE username=?',[$username])->fetch();if(!$row||!in_array($row['role'],['owner','admin','operator'],true)||!password_verify($password,(string)$row['password_hash']))return false;
 session_regenerate_id(true);$_SESSION['admin_id']=(int)$row['id'];$_SESSION['admin_user']=$row['username'];$_SESSION['admin_auth_version']=(int)$row['auth_version'];return true;
}
function haa_owner(): bool {return (haa_current()['role']??'')==='owner';}
function require_owner(): void {require_admin();if(!haa_owner()){http_response_code(403);echo e(haa_t('forbidden'));exit;}}
function haa_text(mixed $value,int $limit): string {if(!is_string($value)||!preg_match('//u',$value)||preg_match('/[\x00-\x1F\x7F]/',$value))haa_fail('invalid');$v=trim($value);if(preg_match_all('/./us',$v)>$limit)haa_fail('invalid');return $v;}
function haa_credentials(array $input,bool $new): array {
 $username=haa_text($input['username']??'',80);if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.@-]{2,79}$/D',$username))haa_fail('username_help');
 $name=haa_text($input['display_name']??'',120);$role=$input['role']??'admin';if(!is_string($role)||!in_array($role,['owner','admin','operator'],true))haa_fail('invalid');
 $storeId=null;
 if($role==='operator' && !empty($input['store_id'])){
  $sid=(int)$input['store_id'];
  if($sid>0){
   $storeExists=(int)haa_query('SELECT COUNT(*) FROM dispatch_stores WHERE id=?',[$sid])->fetchColumn();
   if($storeExists>0)$storeId=$sid;
  }
 }
 $password=$input['password']??'';$confirm=$input['password_confirm']??'';
 if(!is_string($password)||!is_string($confirm)||!preg_match('//u',$password)||str_contains($password,"\0"))haa_fail('password_help');
 if($new||$password!==''){if(preg_match_all('/./us',$password)<8||strlen($password)>72)haa_fail('password_help');if(!hash_equals($password,$confirm))haa_fail('password_match');}
 elseif($confirm!=='')haa_fail('password_match');
 return ['username'=>$username,'display_name'=>$name,'role'=>$role,'store_id'=>$storeId,'password'=>$password];
}
function haa_change(string $action,array $input): int {
 if(!in_array($action,['create','update','delete'],true))haa_fail('invalid');
 $actor=haa_current();if(!$actor||$actor['role']!=='owner')haa_fail('forbidden');$values=$action==='delete'?null:haa_credentials($input,$action==='create');$pdo=db();if($pdo->inTransaction())haa_fail('transaction');
 $pdo->beginTransaction();try{
  // Serialize account changes, including the owner count. Deleted users cannot authorize a queued write.
  $all=$pdo->query('SELECT * FROM admins ORDER BY id FOR UPDATE')->fetchAll();$current=null;$target=null;$rawId=$input['id']??null;if($action!=='create'&&(!is_scalar($rawId)||!preg_match('/^[1-9][0-9]*$/D',(string)$rawId)||(float)$rawId>4294967295))haa_fail('invalid');$id=$action==='create'?0:(int)$rawId;
  foreach($all as $r){if((int)$r['id']===$actor['id'])$current=$r;if((int)$r['id']===$id)$target=$r;}
  if(!$current||$current['role']!=='owner'||(int)$current['auth_version']!==(int)($_SESSION['admin_auth_version']??0))haa_fail('forbidden');
  if($action!=='create'){
   if(!$target)haa_fail('missing');$revision=$input['revision']??null;if(!is_scalar($revision)||!preg_match('/^[1-9][0-9]*$/D',(string)$revision)||(int)$revision!==(int)$target['auth_version'])haa_fail('stale');
   $removingOwner=$target['role']==='owner'&&($action==='delete'||$values['role']!=='owner');if($removingOwner&&count(array_filter($all,fn($r)=>$r['role']==='owner'))<=1)haa_fail('last_owner');
  }
  $before=$target?haa_dto($target):null;
  if($action==='create'){
   haa_query('INSERT INTO admins(username,password_hash,display_name,role,store_id) VALUES(?,?,?,?,?)',[$values['username'],password_hash($values['password'],PASSWORD_DEFAULT),$values['display_name'],$values['role'],$values['store_id']]);$id=(int)$pdo->lastInsertId();
  }elseif($action==='update'){
   $hash=$values['password']===''?$target['password_hash']:password_hash($values['password'],PASSWORD_DEFAULT);
   haa_query('UPDATE admins SET username=?,password_hash=?,display_name=?,role=?,store_id=?,auth_version=auth_version+1 WHERE id=?',[$values['username'],$hash,$values['display_name'],$values['role'],$values['store_id'],$id]);
  }else{haa_query('DELETE FROM admins WHERE id=?',[$id]);}
  $after=$action==='delete'?null:haa_dto(haa_query('SELECT * FROM admins WHERE id=?',[$id])->fetch());
  // Audit only non-secret identity and role; never password or password hash.
  haa_query('INSERT INTO admin_account_audit(actor_id,target_id,action,before_json,after_json) VALUES(?,?,?,?,?)',[$actor['id'],$id,$action,$before?json_encode($before,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE):null,$after?json_encode($after,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE):null]);
  $pdo->commit();if($id===$actor['id']&&$action!=='create')haa_clear_session();return $id;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof PDOException&&$e->getCode()==='23000')haa_fail('duplicate');throw $e;}
}
function haa_t(string $key,?string $lang=null): string {
 $map=[
 'zh'=>['operator'=>'操作員','operators'=>'操作員','order_only'=>'操作員只能處理訂單。','accounts'=>'帳號管理','owner'=>'擁有者','admin'=>'管理員','name'=>'顯示名稱','username'=>'登入帳號','password'=>'密碼','confirm'=>'確認密碼','role'=>'角色','store'=>'所屬門市','all_stores'=>'所有門市（不限制）','create'=>'新增帳號','edit'=>'編輯帳號','save'=>'儲存變更','delete'=>'刪除帳號','cancel'=>'取消','self'=>'我自己','created'=>'建立時間','intro'=>'只有擁有者可管理帳號；擁有者與管理員的其他權限相同，操作員僅能處理訂單。','password_help'=>'密碼至少 8 個字元，最多 72 bytes。','username_help'=>'帳號為 3–80 個英數字或 . _ @ -，首字須為英數字。','leave_blank'=>'留空保留原密碼。修改帳號後，該帳號所有裝置需重新登入。','last_owner'=>'不可刪除或降級最後一位擁有者。','stale'=>'帳號已被其他人修改，請重新整理後再操作。','missing'=>'帳號已不存在。','duplicate'=>'登入帳號已被使用。','password_match'=>'兩次輸入的密碼不一致。','invalid'=>'帳號資料格式不正確。','forbidden'=>'帳號管理僅限擁有者。','busy'=>'帳號更新忙碌中，請稍後再試。','transaction'=>'帳號更新的交易狀態不正確。','saved'=>'帳號已更新。','delete_question'=>'確定刪除此帳號？刪除後此帳號的所有登入都會失效。','owner_guard'=>'至少保留一位擁有者。','no_accounts'=>'沒有帳號。','audit'=>'最近帳號變更','action'=>'操作','search'=>'搜尋名稱或帳號','total'=>'帳號總數','owners'=>'擁有者','admins'=>'管理員'],
 'vi'=>['operator'=>'Nhân viên đơn hàng','operators'=>'Nhân viên đơn hàng','order_only'=>'Nhân viên đơn hàng chỉ được xử lý đơn.','accounts'=>'Tài khoản','owner'=>'Chủ sở hữu','admin'=>'Quản trị viên','name'=>'Tên hiển thị','username'=>'Tên đăng nhập','password'=>'Mật khẩu','confirm'=>'Nhập lại mật khẩu','role'=>'Vai trò','store'=>'Chi nhánh trực thuộc','all_stores'=>'Tất cả chi nhánh (Không giới hạn)','create'=>'Tạo tài khoản','edit'=>'Sửa tài khoản','save'=>'Lưu thay đổi','delete'=>'Xóa tài khoản','cancel'=>'Hủy','self'=>'Tôi','created'=>'Ngày tạo','intro'=>'Chỉ chủ sở hữu quản lý tài khoản; chủ sở hữu và quản trị viên có quyền khác giống nhau, nhân viên đơn hàng chỉ xử lý đơn.','password_help'=>'Mật khẩu từ 8 ký tự, tối đa 72 bytes.','username_help'=>'Tài khoản 3–80 ký tự chữ/số hoặc . _ @ -; bắt đầu bằng chữ/số.','leave_blank'=>'Để trống để giữ mật khẩu. Sau khi sửa, tài khoản phải đăng nhập lại trên mọi thiết bị.','last_owner'=>'Không thể xóa hoặc hạ quyền chủ sở hữu cuối cùng.','stale'=>'Tài khoản đã được sửa; tải lại trước khi thao tác.','missing'=>'Tài khoản không còn tồn tại.','duplicate'=>'Tên đăng nhập đã được dùng.','password_match'=>'Hai mật khẩu không khớp.','invalid'=>'Thông tin tài khoản không hợp lệ.','forbidden'=>'Chỉ chủ sở hữu được quản lý tài khoản.','busy'=>'Đang cập nhật, thử lại sau.','transaction'=>'Trạng thái giao dịch không hợp lệ.','saved'=>'Đã cập nhật tài khoản.','delete_question'=>'Xóa tài khoản này? Mọi phiên đăng nhập của tài khoản sẽ mất hiệu lực.','owner_guard'=>'Luôn giữ ít nhất một chủ sở hữu.','no_accounts'=>'Chưa có tài khoản.','audit'=>'Thay đổi tài khoản gần đây','action'=>'Thao tác','search'=>'Tìm tên hoặc tài khoản','total'=>'Tổng tài khoản','owners'=>'Chủ sở hữu','admins'=>'Quản trị viên'],
 'en'=>['operator'=>'Order operator','operators'=>'Order operators','order_only'=>'Operators can only handle orders.','accounts'=>'Accounts','owner'=>'Owner','admin'=>'Administrator','name'=>'Display name','username'=>'Username','password'=>'Password','confirm'=>'Confirm password','role'=>'Role','store'=>'Assigned store','all_stores'=>'All stores (No limit)','create'=>'New account','edit'=>'Edit account','save'=>'Save changes','delete'=>'Delete account','cancel'=>'Cancel','self'=>'Me','created'=>'Created','intro'=>'Only owners manage accounts. Other permissions are identical for owners and administrators; operators only handle orders.','password_help'=>'At least 8 characters, up to 72 bytes.','username_help'=>'3–80 letters/digits or . _ @ -; start with a letter/digit.','leave_blank'=>'Leave blank to keep the password. All devices must sign in again after editing.','last_owner'=>'The last owner cannot be deleted or demoted.','stale'=>'Account was changed; reload before editing.','missing'=>'Account no longer exists.','duplicate'=>'Username is already in use.','password_match'=>'Passwords do not match.','invalid'=>'Invalid account data.','forbidden'=>'Account management is owner-only.','busy'=>'Account update busy; try again.','transaction'=>'Invalid transaction state.','saved'=>'Account updated.','delete_question'=>'Delete this account? All of its sessions will be invalidated.','owner_guard'=>'Keep at least one owner.','no_accounts'=>'No accounts.','audit'=>'Recent account changes','action'=>'Action','search'=>'Search name or username','total'=>'Total accounts','owners'=>'Owners','admins'=>'Administrators']];
 $lang=$lang??(function_exists('current_lang')?current_lang():'vi');return $map[$lang][$key]??$map['vi'][$key]??$key;
}
