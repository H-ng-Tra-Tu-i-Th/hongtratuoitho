<?php
require __DIR__.'/../lib/bootstrap.php';require __DIR__.'/../lib/layout.php';require_owner();
header('Cache-Control: private, no-store, max-age=0');header('X-Robots-Tag: noindex, nofollow');
$error='';$message=(string)($_SESSION['admin_account_flash']??'');unset($_SESSION['admin_account_flash']);
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();try{
  haa_change(is_string($_POST['action']??null)?$_POST['action']:'',$_POST);
  if(!admin_logged_in())redirect(base_url('admin/login.php'));
  $_SESSION['admin_account_flash']='saved';header('Location: '.base_url('admin/accounts.php'),true,303);exit;
 }catch(HaaAccountError $e){$error=haa_t($e->getMessage());http_response_code($e->getMessage()==='forbidden'?403:409);}
}
$me=haa_current();$rows=haa_query('SELECT id,username,display_name,role,store_id,auth_version,created_at FROM admins ORDER BY role="owner" DESC,id')->fetchAll();
$stores=haa_query('SELECT id,name FROM dispatch_stores ORDER BY name ASC')->fetchAll();
$storesById=[]; foreach($stores as $st) $storesById[(int)$st['id']]=$st['name'];
$audit=haa_query('SELECT actor_id,target_id,action,created_at FROM admin_account_audit ORDER BY id DESC LIMIT 8')->fetchAll();
function haa_account_form(?array $row=null, array $stores=[]): void {
 $new=$row===null;$id=(int)($row['id']??0);
 $isOperator=($row['role']??'admin')==='operator';
 ?><form method="post" class="admin-account-form" autocomplete="off"><?=csrf_field()?><input type="hidden" name="action" value="<?=$new?'create':'update'?>"><?php if(!$new):?><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="revision" value="<?=(int)$row['auth_version']?>"><?php endif; ?>
 <label><?=e(haa_t('name'))?><input name="display_name" maxlength="120" value="<?=e($row['display_name']??'')?>"></label><label><?=e(haa_t('username'))?><input name="username" minlength="3" maxlength="80" required autocomplete="off" pattern="[A-Za-z0-9][A-Za-z0-9_.@\-]{2,79}" value="<?=e($row['username']??'')?>"><small><?=e(haa_t('username_help'))?></small></label>
 <label><?=e(haa_t('role'))?><select name="role" onchange="haaToggleStore(this)"><?php foreach(['owner','admin','operator'] as $role):?><option value="<?=$role?>"<?=($row['role']??'admin')===$role?' selected':''?>><?=e(haa_t($role))?></option><?php endforeach;?></select></label>
 <label class="haa-store-field" style="<?=$isOperator?'':'display:none;'?>"><?=e(haa_t('store'))?><select name="store_id"><option value=""><?=e(haa_t('all_stores'))?></option><?php foreach($stores as $st):?><option value="<?=$st['id']?>"<?=(isset($row['store_id']) && (int)$row['store_id']===(int)$st['id'])?' selected':''?>><?=e($st['name'])?></option><?php endforeach;?></select></label>
 <label><?=e(haa_t('password'))?><input name="password" type="password" minlength="8" autocomplete="new-password"<?=$new?' required':''?>><small><?=e($new?haa_t('password_help'):haa_t('leave_blank'))?></small></label><label><?=e(haa_t('confirm'))?><input name="password_confirm" type="password" autocomplete="new-password"<?=$new?' required':''?>></label><div class="full"><button class="btn primary" type="submit"><?=e(haa_t($new?'create':'save'))?></button></div></form><?php
}
admin_header(haa_t('accounts'));
?>
<p class="admin-account-intro"><?=e(haa_t('intro'))?></p>
<?php if($error):?><div class="alert danger" role="alert"><?=e($error)?></div><?php endif;?><?php if($message):?><div class="alert" role="status"><?=e(haa_t($message))?></div><?php endif;?>
<div class="admin-account-stats"><div class="admin-account-stat"><span><?=e(haa_t('total'))?></span><strong><?=count($rows)?></strong></div><?php foreach(['owner'=>'owners','admin'=>'admins','operator'=>'operators'] as $role=>$label):?><div class="admin-account-stat"><span><?=e(haa_t($label))?></span><strong><?=count(array_filter($rows,fn($r)=>$r['role']===$role))?></strong></div><?php endforeach;?></div>
<div class="admin-account-tools"><input type="search" class="admin-account-search" aria-label="<?=e(haa_t('search'))?>" placeholder="<?=e(haa_t('search'))?>" data-admin-account-search><details><summary class="btn primary">+ <?=e(haa_t('create'))?></summary><div class="card"><?php haa_account_form(null, $stores);?></div></details></div>
<div class="admin-account-list"><?php foreach($rows as $row):$name=trim($row['display_name'])?:$row['username'];preg_match('/^./u',$name,$initial);?><article class="admin-account-card" data-admin-account-card data-search="<?=e($name.' '.$row['username'].' '.haa_t($row['role']).' '.($storesById[(int)($row['store_id']??0)]??''))?>">
 <div class="admin-account-card-head"><span class="admin-avatar"><?=e($initial[0]??'H')?></span><div class="admin-account-card-meta"><strong><?=e($name)?><?php if((int)$row['id']===$me['id']):?><span class="admin-account-self"><?=e(haa_t('self'))?></span><?php endif;?></strong><span class="admin-account-role"><?=e(haa_t($row['role']))?><?php if($row['role']==='operator' && !empty($row['store_id'])):?> · 🏬 <?=e($storesById[(int)$row['store_id']]??'#'.$row['store_id'])?><?php endif;?></span><small><?=e($row['username'])?></small><small><?=e(haa_t('created').' · '.format_datetime($row['created_at']))?></small></div></div>
 <details><summary><?=e(haa_t('edit'))?></summary><?php haa_account_form($row, $stores);?><form method="post" class="admin-account-delete" data-admin-delete="<?=e(haa_t('delete_question').' '.$row['username'])?>"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><input type="hidden" name="revision" value="<?=(int)$row['auth_version']?>"><button type="submit"><?=e(haa_t('delete'))?></button></form></details></article><?php endforeach;?></div>
<p class="admin-account-guard"><?=e(haa_t('owner_guard'))?></p>
<?php if($audit):?><section class="admin-account-audit"><h2><?=e(haa_t('audit'))?></h2><ul><?php foreach($audit as $event):?><li><?=e(format_datetime($event['created_at']).' · #'.$event['actor_id'].' → #'.$event['target_id'].' · '.haa_t(['create'=>'create','update'=>'edit','delete'=>'delete'][$event['action']]??'action'))?></li><?php endforeach;?></ul></section><?php endif;?>
<script>
function haaToggleStore(sel) {
  var form = sel.closest('form');
  if (!form) return;
  var field = form.querySelector('.haa-store-field');
  if (!field) return;
  var isOp = sel.value === 'operator';
  field.style.display = isOp ? '' : 'none';
  if (!isOp) {
    var stSel = field.querySelector('select');
    if (stSel) stSel.value = '';
  }
}
</script>
<?php admin_footer(); ?>
