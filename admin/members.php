<?php
require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/../lib/layout.php';
require_admin();
$staffQuery=db()->prepare('SELECT id FROM admins WHERE id=?');
$staffQuery->execute([(int)$_SESSION['admin_id']]);
if(!$staffQuery->fetch()) { unset($_SESSION['admin_id']);redirect(base_url('admin/login.php')); }
header('Cache-Control: no-store, private');
$message='';$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if(!is_string($_POST['csrf']??null)||empty($_SESSION['_csrf'])) { http_response_code(419);exit(e(t('csrf_expired'))); }
    verify_csrf();
    $id=max(0,(int)($_POST['member_id']??0));
    $action=(string)($_POST['action']??'');
    try {
        if ($action==='verify') {
            hym_verify_member_phone($id,(int)$_SESSION['admin_id']);
            $message=ma('verified_saved');
        } elseif ($action==='sync') {
            $query=db()->prepare('SELECT id FROM members WHERE id=?');$query->execute([$id]);
            if (!$query->fetch()) throw new RuntimeException('member_missing');
            $result=hym_sync_member($id,true);
            $feedback=mt((string)($result['message_key']??'member_sync_failed'));
            if (($result['status']??'')==='synced') $message=$feedback;
            else $error=$feedback;
        } else {
            http_response_code(400);$error=ma('save_failed');
        }
    } catch(Throwable $exception) {
        $error=ma('save_failed');
    }
}
$search=trim((string)($_GET['q']??''));
if (strlen($search)>160) $search=substr($search,0,160);
$phoneSearch=preg_replace('/[\s().+\-]/','',$search);
if (preg_match('/^0\d{9,10}$/',$phoneSearch)) $phoneSearch='84'.substr($phoneSearch,1);
$query=db()->prepare('SELECT id,phone,display_name,phone_verified_at,phone_verified_by,created_at FROM members WHERE (?=\'\' OR phone LIKE ? OR display_name LIKE ?) ORDER BY id DESC LIMIT 100');
$query->execute([$search,'%'.$phoneSearch.'%','%'.$search.'%']);$members=$query->fetchAll();
$memberDate=static function($value): string {
    if (!$value) return ma('unknown');
    try {
        return (new DateTimeImmutable((string)$value,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('Y-m-d H:i');
    } catch(Throwable $exception) { return ma('unknown'); }
};
admin_header(ma('members'));
?>
<?php if($message):?><div class="alert success" role="status"><?=e($message)?></div><?php endif;?>
<?php if($error):?><div class="alert danger" role="alert"><?=e($error)?></div><?php endif;?>
<section class="panel">
  <p><?=e(ma('source'))?></p>
  <p class="field-hint"><?=e(ma('verify_help'))?></p>
  <div class="member-admin-tools"><form method="get" class="form-grid"><label><?=e(ma('search'))?><input name="q" value="<?=e($search)?>" maxlength="160" autocomplete="off"></label><button class="btn" type="submit"><?=e(ma('search_button'))?></button></form><a class="btn" href="<?=e(base_url('admin/member-settings.php'))?>"><?=e(ma('configuration'))?></a></div>
  <?php if(!$members):?><p><?=e(ma('empty'))?></p><?php else:?>
  <div class="table-wrap"><table><thead><tr><th><?=e(ma('name'))?></th><th><?=e(ma('phone'))?></th><th><?=e(ma('joined'))?></th><th><?=e(ma('verification'))?></th><th><?=e(ma('last_sync'))?></th><th></th></tr></thead><tbody>
  <?php foreach($members as $member):$sync=hym_sync_state((int)$member['id']);?>
    <tr><td><?=e($member['display_name'])?></td><td><?=e($member['phone'])?></td><td><?=e($memberDate($member['created_at']))?></td><td><?=e(ma($member['phone_verified_at']?'verified':'pending'))?><?php if($member['phone_verified_at']):?><small class="field-hint"><?=e($memberDate($member['phone_verified_at']))?></small><?php endif;?></td><td><?=e($memberDate($sync['last_success_at']??null))?></td><td>
      <?php if(!$member['phone_verified_at']):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="verify"><input type="hidden" name="member_id" value="<?=(int)$member['id']?>"><button class="btn small" type="submit"><?=e(ma('verify'))?></button></form>
      <?php else:?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="sync"><input type="hidden" name="member_id" value="<?=(int)$member['id']?>"><button class="btn small" type="submit"><?=e(ma('sync'))?></button></form><?php endif;?>
    </td></tr>
  <?php endforeach;?>
  </tbody></table></div>
  <?php endif;?>
</section>
<?php admin_footer(); ?>
