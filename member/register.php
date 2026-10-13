<?php
require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/../lib/layout.php';
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
$loadFailed=false;
try{
    hym_schema();
    if(hym_current_member())redirect(base_url('member/index.php'));
}catch(Throwable $error){$loadFailed=true;}
$flash=$_SESSION['member_flash']??null;unset($_SESSION['member_flash']);
$GLOBALS['hym_member_page']=true;
front_header(mt('member_register_title'));
?>
<div class="hym-page">
  <a class="back" href="<?=e(base_url('member/index.php'))?>">← <?=e(mt('member_back_login'))?></a>
  <header class="hym-page-head"><div><span class="hym-eyebrow"><?=e(mt('member_entry'))?></span><h1><?=e(mt('member_register_title'))?></h1></div></header>
  <?php if(is_array($flash)&&!empty($flash['key'])):$flashType=in_array($flash['type']??'',['success','info','danger'],true)?$flash['type']:'danger';$flashText=mt((string)$flash['key']);if($flashText===(string)$flash['key'])$flashText=mt('member_save_failed');?>
    <div class="alert <?=e($flashType)?>" role="status"><?=e($flashText)?></div>
  <?php endif;?>
  <?php if($loadFailed):?><div class="alert danger" role="alert"><?=e(mt('member_load_failed'))?></div><?php endif;?>
  <div class="hym-auth-single">
    <section class="hym-card" id="member-register">
      <form class="hym-form" method="post" action="<?=e(base_url('member/action.php'))?>"><?=csrf_field()?><input type="hidden" name="action" value="register">
        <label for="member-register-name"><?=e(mt('member_display_name'))?> <span class="hym-optional"><?=e(mt('member_optional'))?></span><input id="member-register-name" name="display_name" maxlength="100" autocomplete="name"></label>
        <label for="member-register-phone"><?=e(mt('member_phone'))?><input id="member-register-phone" type="tel" inputmode="tel" name="phone" required maxlength="32" autocomplete="tel" aria-describedby="member-phone-hint"></label>
        <small id="member-phone-hint"><?=e(mt('member_phone_hint'))?></small>
        <label for="member-register-password"><?=e(mt('member_password'))?><input id="member-register-password" type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password" aria-describedby="member-password-hint"></label>
        <small id="member-password-hint"><?=e(mt('member_password_hint'))?></small>
        <label for="member-register-confirm"><?=e(mt('member_password_confirm'))?><input id="member-register-confirm" type="password" name="password_confirm" required minlength="8" maxlength="72" autocomplete="new-password"></label>
        <button class="btn primary block" type="submit"><?=e(mt('member_register'))?></button>
      </form>
    </section>
  </div>
</div>
<?php front_footer(); ?>
