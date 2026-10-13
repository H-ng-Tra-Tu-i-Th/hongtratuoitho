<?php
require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/../lib/layout.php';
require_admin();
$msg='';$error='';

function bank_qr_upload(string $field,string $current=''): string {
    if(!isset($_FILES[$field]) || $_FILES[$field]['error']===UPLOAD_ERR_NO_FILE) return $current;
    if($_FILES[$field]['error']!==UPLOAD_ERR_OK) throw new RuntimeException(t('image_upload_failed'));
    $f=$_FILES[$field];
    if($f['size']>5*1024*1024) throw new RuntimeException(t('image_too_large'));
    $info=@getimagesize($f['tmp_name']);
    $allow=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!$info || !isset($allow[$info['mime']])) throw new RuntimeException(t('image_type_error'));
    $dir=__DIR__.'/../uploads'; if(!is_dir($dir)) @mkdir($dir,0755,true);
    $fn='bank-qr-'.date('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$allow[$info['mime']];
    if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$fn)) throw new RuntimeException(t('image_upload_failed'));
    if($current && is_file($dir.'/'.$current) && $current!=='bank-vietinbank-default.jpg') @unlink($dir.'/'.$current);
    return $fn;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=(string)($_POST['action']??'save');
    try{
        if($action==='delete'){
            $id=max(0,(int)($_POST['id']??0));
            $st=db()->prepare('SELECT qr_image FROM bank_accounts WHERE id=?');$st->execute([$id]);$qr=(string)($st->fetchColumn()?:'');
            db()->prepare('DELETE FROM bank_accounts WHERE id=?')->execute([$id]);
            if($qr && $qr!=='bank-vietinbank-default.jpg' && is_file(__DIR__.'/../uploads/'.$qr)) @unlink(__DIR__.'/../uploads/'.$qr);
            $msg=t('bank_account_deleted');
        } else {
            $id=max(0,(int)($_POST['id']??0));
            $bank=trim((string)($_POST['bank_name']??''));
            $number=trim((string)($_POST['account_number']??''));
            $name=trim((string)($_POST['account_name']??''));
            if($bank===''||$number===''||$name==='') throw new RuntimeException(t('bank_fields_required'));
            $active=isset($_POST['active'])?1:0;
            $sort=(int)($_POST['sort_order']??0);
            if($id>0){
                $st=db()->prepare('SELECT qr_image FROM bank_accounts WHERE id=?');$st->execute([$id]);$current=(string)($st->fetchColumn()?:'');
                $qr=bank_qr_upload('qr_image',$current);
                if(isset($_POST['remove_qr']) && $qr){
                    if($qr!=='bank-vietinbank-default.jpg' && is_file(__DIR__.'/../uploads/'.$qr)) @unlink(__DIR__.'/../uploads/'.$qr);
                    $qr='';
                }
                $st=db()->prepare('UPDATE bank_accounts SET bank_name=?,account_number=?,account_name=?,qr_image=?,active=?,sort_order=? WHERE id=?');
                $st->execute([$bank,$number,$name,$qr,$active,$sort,$id]);
                $msg=t('bank_account_updated');
            }else{
                $qr=bank_qr_upload('qr_image','');
                $st=db()->prepare('INSERT INTO bank_accounts(bank_name,account_number,account_name,qr_image,active,sort_order) VALUES(?,?,?,?,?,?)');
                $st->execute([$bank,$number,$name,$qr,$active,$sort]);
                $msg=t('bank_account_added');
            }
        }
    }catch(Throwable $e){$error=$e->getMessage();}
}
$accounts=db()->query('SELECT * FROM bank_accounts ORDER BY sort_order,id')->fetchAll();
admin_header(t('transfer_accounts'));
?>
<?php if($msg):?><div class="alert success"><?=e($msg)?></div><?php endif;?>
<?php if($error):?><div class="alert danger"><?=e($error)?></div><?php endif;?>

<section class="panel">
  <h2><?=e(t('add_transfer_account'))?></h2>
  <form method="post" enctype="multipart/form-data" class="bank-admin-form">
    <?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="0">
    <label><?=e(t('bank_name'))?><input name="bank_name" maxlength="120" required placeholder="VietinBank"></label>
    <label><?=e(t('account_number'))?><input name="account_number" maxlength="80" required inputmode="numeric"></label>
    <label><?=e(t('account_name'))?><input name="account_name" maxlength="160" required></label>
    <label><?=e(t('qr_image'))?><input type="file" name="qr_image" accept="image/jpeg,image/png,image/webp"><small class="field-hint"><?=e(t('qr_image_help'))?></small></label>
    <label><?=e(t('sort'))?><input type="number" name="sort_order" value="0"></label>
    <label class="toggle-row"><input type="checkbox" name="active" value="1" checked><span><strong><?=e(t('active'))?></strong></span></label>
    <button class="btn primary" type="submit"><?=e(t('add'))?></button>
  </form>
</section>

<section class="panel">
  <h2><?=e(t('existing_transfer_accounts'))?></h2>
  <?php if(!$accounts):?><p class="muted"><?=e(t('no_transfer_accounts'))?></p><?php endif;?>
  <div class="bank-admin-list">
  <?php foreach($accounts as $a):?>
    <article class="bank-admin-card">
      <form method="post" enctype="multipart/form-data" class="bank-admin-form">
        <?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$a['id']?>">
        <div class="bank-admin-qr">
          <?php if(!empty($a['qr_image'])):?><img src="<?=e(base_url('uploads/'.$a['qr_image']))?>" alt="<?=e($a['bank_name'])?> QR"><?php else:?><div class="bank-qr-placeholder">QR</div><?php endif;?>
        </div>
        <label><?=e(t('bank_name'))?><input name="bank_name" maxlength="120" required value="<?=e($a['bank_name'])?>"></label>
        <label><?=e(t('account_number'))?><input name="account_number" maxlength="80" required value="<?=e($a['account_number'])?>"></label>
        <label><?=e(t('account_name'))?><input name="account_name" maxlength="160" required value="<?=e($a['account_name'])?>"></label>
        <label><?=e(t('qr_image'))?><input type="file" name="qr_image" accept="image/jpeg,image/png,image/webp"></label>
        <?php if(!empty($a['qr_image'])):?><label class="check-row"><input type="checkbox" name="remove_qr"><span><?=e(t('remove_qr'))?></span></label><?php endif;?>
        <label><?=e(t('sort'))?><input type="number" name="sort_order" value="<?=e($a['sort_order'])?>"></label>
        <label class="toggle-row"><input type="checkbox" name="active" value="1" <?=$a['active']?'checked':''?>><span><strong><?=e(t('active'))?></strong></span></label>
        <button class="btn primary" type="submit"><?=e(t('save'))?></button>
      </form>
      <form method="post" class="bank-delete-form" onsubmit="return confirm('<?=e(t('confirm_delete'))?>')">
        <?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$a['id']?>">
        <button class="btn danger" type="submit"><?=e(t('delete'))?></button>
      </form>
    </article>
  <?php endforeach;?>
  </div>
</section>
<?php admin_footer();?>
