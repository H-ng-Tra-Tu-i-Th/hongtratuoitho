<?php
require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/../lib/layout.php';
require_once __DIR__.'/../lib/dispatch_views.php';
require_admin();
hds_view_require_staff(base_url('admin/login.php'));
header('Cache-Control: no-store, private');
$error='';$message='';$formInput=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        $action=$_POST['action']??null;if(!is_string($action))throw new InvalidArgumentException('invalid_action');
        if(in_array($action,['save_store','locate_store'],true)){
            $input=[];foreach(['id','expected_revision','name','address','phone','map_url','lat','lng','coord_source','preview_token','transfer_bank_name','transfer_account_number','transfer_account_name'] as $field){$value=$_POST[$field]??($field==='coord_source'?'manual':'');if(!is_string($value))throw new InvalidArgumentException('dispatch_invalid_store');$input[$field]=trim($value);}
            if(!preg_match('/^\d{1,10}$/D',$input['id']))throw new InvalidArgumentException('dispatch_invalid_store');
            $input['transfer_enabled']=htp_checked($_POST,'transfer_enabled');$input['remove_transfer_qr']=htp_checked($_POST,'remove_transfer_qr');
            $input['enabled']=hds_view_checked($_POST,'enabled','dispatch_invalid_store');$formInput=$input;
            if($action==='locate_store'){
                $location=hds_locate_store_address($input['address'],(int)$_SESSION['admin_id']);
                if(($location['status']??'')==='ready'){
                    foreach(['lat','lng','coord_source','preview_token','geocoded_at','expires_at'] as $field)$formInput[$field]=$location[$field]??null;
                    $message=dt('store_located');
                }else $error=dt('store_locate_'.(in_array($location['status']??'',['disabled','no_key','no_match','ambiguous','imprecise','geocode_failed'],true)?$location['status']:'geocode_failed'));
            }else{
                $saved=hds_store_save($input,(int)$_SESSION['admin_id']);$_SESSION['dispatch_store_flash']='store_saved';
                redirect(base_url('admin/stores.php?edit='.(int)$saved['id']));
            }
        }elseif($action==='save_maps'){
            $key=$_POST['api_key']??'';if(!is_string($key))throw new InvalidArgumentException('dispatch_invalid_config');
            hds_config_save(['enabled'=>hds_view_checked($_POST,'maps_enabled','dispatch_invalid_config'),'api_key'=>trim($key),'clear_key'=>hds_view_checked($_POST,'clear_key','dispatch_invalid_config')]);
            $_SESSION['dispatch_store_flash']='key_saved';redirect(base_url('admin/stores.php#maps-settings'));
        }else throw new InvalidArgumentException('invalid_action');
    }catch(Throwable $exception){$error=$exception instanceof TransferPaymentError?htp_error($exception):hds_view_error($exception);}
}
$flash=$_SESSION['dispatch_store_flash']??'';unset($_SESSION['dispatch_store_flash']);if(is_string($flash)&&in_array($flash,['store_saved','key_saved'],true))$message=dt($flash);
$edit=isset($_GET['edit'])&&is_string($_GET['edit'])?(int)$_GET['edit']:0;
$store=$formInput??($edit>0?hds_store_get($edit):null);
if($edit>0&&!$store&&!$formInput)$error=dt('dispatch_store_unavailable');
$store=$store??['id'=>0,'revision'=>0,'name'=>'','address'=>'','phone'=>'','map_url'=>'','lat'=>null,'lng'=>null,'enabled'=>true,'coord_source'=>'manual'];
$stores=hds_stores();$configuration=hds_config_status();
admin_header(dt('stores'));
?>
<?php if($message):?><div class="hds-notice" role="status"><?=e($message)?></div><?php endif;?>
<?php if($error):?><div class="hds-notice danger" role="alert"><?=e($error)?></div><?php endif;?>
<section class="hds-panel"><div class="hds-page-actions"><h2><?=e(dt('store_list'))?></h2><a class="hds-btn" href="<?=e(base_url('admin/stores.php#store-form'))?>"><?=e(dt('store_add'))?></a></div><p class="hds-help"><?=e(dt('store_history_hint'))?></p>
  <?php if(!$stores):?><p><?=e(dt('store_empty'))?></p><?php else:?><div class="hds-store-grid"><?php foreach($stores as $row):?><article class="hds-store-card <?=!$row['enabled']?'is-disabled':''?>"><div class="hds-store-heading"><h3><?=e($row['name'])?></h3><span class="hds-status <?=!$row['enabled']?'disabled':''?>"><?=e(dt($row['enabled']?'enabled':'disabled'))?></span></div><p><?=nl2br(e($row['address']))?></p><?php if($row['phone']!==''):?><p><?=e($row['phone'])?></p><?php endif;?><?php if(isset($row['lat'],$row['lng'])):?><p class="hds-help"><?=e(dt('store_lat'))?>: <?=e($row['lat'])?> · <?=e(dt('store_lng'))?>: <?=e($row['lng'])?></p><?php else:?><p class="hds-help"><?=e(dt('store_no_coordinates'))?></p><?php endif;?><?php $map=hds_view_store_map($row);if($map!==''):?><a class="hds-map-link" href="<?=e($map)?>" target="_blank" rel="noopener noreferrer"><?=e(dt('open_map'))?> ↗</a><?php endif;?><div><a class="hds-btn" href="<?=e(base_url('admin/stores.php?edit='.(int)$row['id'].'#store-form'))?>"><?=e(dt('edit'))?></a></div></article><?php endforeach;?></div><?php endif;?>
</section>
<div class="ui-grid ui-grid-2-1"><section class="hds-panel" id="store-form"><h2><?=e(dt((int)$store['id']>0?'store_edit':'store_add'))?></h2><form method="post" enctype="multipart/form-data" class="hds-form">
  <?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$store['id']?>"><input type="hidden" name="expected_revision" value="<?=(int)($store['expected_revision']??$store['revision']??0)?>"><input type="hidden" name="coord_source" value="<?=e($store['coord_source']??'manual')?>"><input type="hidden" name="preview_token" value="<?=e($store['preview_token']??'')?>">
  <div class="hds-field-grid"><label><?=e(dt('store_name'))?><input name="name" value="<?=e($store['name'])?>" required maxlength="160" autocomplete="organization"></label><label><?=e(dt('store_phone'))?><input name="phone" type="tel" value="<?=e($store['phone'])?>" maxlength="80" autocomplete="tel"></label><label class="full"><?=e(dt('store_address'))?><textarea name="address" required maxlength="500" rows="3" autocomplete="street-address"><?=e($store['address'])?></textarea></label><label class="full"><?=e(dt('store_map'))?><input name="map_url" type="url" value="<?=e($store['map_url'])?>" maxlength="2048" placeholder="https://maps.google.com/" autocomplete="off"></label><label><?=e(dt('store_lat'))?><input name="lat" type="number" step="any" min="-90" max="90" value="<?=e($store['lat']??'')?>"></label><label><?=e(dt('store_lng'))?><input name="lng" type="number" step="any" min="-180" max="180" value="<?=e($store['lng']??'')?>"></label></div>
  <?=htp_render_store_fields($store)?>
  <p class="hds-help"><?=e(dt('store_coordinates_hint'))?></p><button class="hds-btn" type="submit" name="action" value="locate_store" formnovalidate <?=!empty($configuration['enabled'])&&!empty($configuration['key_configured'])?'':'disabled'?>><?=e(dt('locate_store'))?></button><p class="hds-help"><?=e(dt('store_locate_hint'))?></p>
  <?php if(empty($configuration['enabled'])||empty($configuration['key_configured'])):?><p class="hds-help"><?=e(dt(empty($configuration['key_configured'])?'store_locate_no_key':'store_locate_disabled'))?> <a class="hds-map-link" href="#maps-settings"><?=e(dt('maps_settings'))?></a></p><?php endif;?>
  <?php if(isset($store['lat'],$store['lng'])&&$store['lat']!==''&&$store['lng']!==''):?><a class="hds-map-link" href="<?=e('https://www.google.com/maps/search/?api=1&query='.rawurlencode($store['lat'].','.$store['lng']))?>" target="_blank" rel="noopener noreferrer"><?=e(dt('open_map'))?> ↗</a><?php endif;?>
  <?php if(($store['coord_source']??'')==='google'):?><p class="hds-help"><?=e(!isset($store['lat'],$store['lng'])?dt('store_location_expired'):dt('location_valid_until',['date'=>hds_view_time((string)($store['expires_at']??''))]))?></p><?php endif;?>
  <label class="hds-check"><input type="checkbox" name="enabled" value="1" <?=$store['enabled']?'checked':''?>><span><?=e(dt('store_enabled'))?></span></label><div class="hds-page-actions"><button class="hds-btn primary" type="submit" name="action" value="save_store"><?=e(dt('save'))?></button><?php if((int)$store['id']>0):?><a class="hds-btn" href="<?=e(base_url('admin/stores.php#store-form'))?>"><?=e(dt('cancel'))?></a><?php endif;?></div>
</form></section>
<section class="hds-panel" id="maps-settings"><h2><?=e(dt('maps_settings'))?></h2><p class="hds-help"><?=e(dt('maps_hint'))?></p><form method="post" class="hds-form" autocomplete="off">
  <?=csrf_field()?><input type="hidden" name="action" value="save_maps"><label class="hds-check"><input type="checkbox" name="maps_enabled" value="1" <?=!empty($configuration['enabled'])?'checked':''?>><span><?=e(dt('maps_enabled'))?></span></label><label><?=e(dt('google_key'))?><input type="password" name="api_key" value="" maxlength="256" autocomplete="new-password" spellcheck="false"><small><?=e(dt(!empty($configuration['key_configured'])?'key_present':'key_missing'))?></small></label><p class="hds-help"><?=e(dt('key_hint'))?></p><label class="hds-check"><input type="checkbox" name="clear_key" value="1"><span><?=e(dt('key_clear'))?></span></label><button class="hds-btn primary" type="submit"><?=e(dt('save'))?></button>
</form></section></div>
<?php admin_footer(); ?>
