<?php
require_once __DIR__.'/dispatch_i18n.php';
function hds_view_require_staff(string $loginUrl): void {
    try{hds_staff();}catch(Throwable $error){if($error->getMessage()!=='dispatch_forbidden')throw $error;unset($_SESSION['admin_id']);redirect($loginUrl);}
}
function hds_view_checked(array $data,string $field,string $error): bool {
    if(!array_key_exists($field,$data))return false;
    if(!is_string($data[$field])||$data[$field]!=='1')throw new InvalidArgumentException($error);
    return true;
}
function hds_view_time(string $value): string {
    if($value==='')return '—';
    try{return (new DateTimeImmutable($value,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i');}catch(Throwable $error){return '—';}
}
/** Only validated Google links are opened; rendering never fetches a link. */
function hds_view_map_url(string $url): string {
    try{return hds_map_url($url);}catch(Throwable $error){return '';}
}
function hds_view_store_map(array $store): string {
    $url=hds_view_map_url((string)($store['map_url']??''));if($url!=='')return $url;
    $address=trim((string)($store['address']??''));return $address!==''?'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address):'';
}
function hds_view_error(Throwable $error): string {
    $key=$error->getMessage();$text=dt($key);
    return $text!==$key?$text:dt('save_failed');
}
function hds_view_revision(mixed $value): int {
    if(!is_string($value)||!preg_match('/^\d{1,10}$/D',$value))throw new InvalidArgumentException('dispatch_changed');
    return (int)$value;
}
/** Explicit dispatch actions cannot fall through into order-status updates. */
function hds_view_order_action(int $orderId,string $action,array $data,int $adminId): string {
    $revision=hds_view_revision($data['dispatch_revision']??null);
    if($action==='dispatch_refresh'){hds_refresh_order($orderId,$adminId,$revision);return 'dispatch_refreshed';}
    if($action==='dispatch_clear'){hds_assign_order($orderId,null,$adminId,$revision);return 'dispatch_saved';}
    if($action==='dispatch_assign'){
        $value=$data['store_id']??null;
        if($value === '' || $value === '0' || $value === null){
            hds_assign_order($orderId,null,$adminId,$revision);
            return 'dispatch_saved';
        }
        if($value === 'auto_refresh' || $value === 'refresh'){
            hds_refresh_order($orderId,$adminId,$revision);
            return 'dispatch_refreshed';
        }
        if(!is_string($value)||!preg_match('/^[1-9]\d{0,9}$/D',$value))throw new InvalidArgumentException('dispatch_store_unavailable');
        hds_assign_order($orderId,(int)$value,$adminId,$revision);
        return 'dispatch_saved';
    }
    if($action==='dispatch_adopt'){
        $dispatch=hds_order_dispatch($orderId);if(!$dispatch)throw new InvalidArgumentException('dispatch_order_not_found');
        if((int)$dispatch['revision']!==$revision)throw new InvalidArgumentException('dispatch_changed');
        if(($dispatch['status']??'')!=='ready'||!empty($dispatch['stale'])||empty($dispatch['recommended_store']['id']))throw new InvalidArgumentException('dispatch_store_unavailable');
        $shown=$data['recommended_store_id']??null;if(!is_string($shown)||!preg_match('/^[1-9]\d{0,9}$/D',$shown)||(int)$shown!==(int)$dispatch['recommended_store']['id'])throw new InvalidArgumentException('dispatch_changed');
        hds_assign_order($orderId,(int)$dispatch['recommended_store']['id'],$adminId,$revision);return 'dispatch_saved';
    }
    throw new InvalidArgumentException('invalid_action');
}
function hds_render_order_badge(array $order,array $dispatch): string {
    $assigned=$dispatch['assigned_store']??null;$recommended=$dispatch['recommended_store']??null;
    ob_start(); ?><div class="hds-list-summary"><span><?=e(dt('assigned'))?></span><strong><?=e($assigned['name']??dt('unassigned'))?></strong><?php if(!$assigned&&$recommended&&($dispatch['status']??'')==='ready'):?><small><?=e(dt('suggested'))?>: <?=e($recommended['name'])?></small><?php endif;?></div><?php return ob_get_clean();
}
/** Shared responsive panel; GET rendering never geocodes or changes assignment. */
function hds_render_order_dispatch(array $order): string {
    $dispatch=hds_order_dispatch((int)$order['id']);if(!$dispatch)return '';
    $assigned=$dispatch['assigned_store']??null;$recommended=$dispatch['recommended_store']??null;
    $editable=!empty($dispatch['can_assign'])&&in_array($order['status'],['new','accepted'],true);
    $ready=($dispatch['status']??'')==='ready'&&empty($dispatch['stale'])&&$recommended&&isset($dispatch['lat'],$dispatch['lng']);
    $stores=$editable?hds_stores(true):[];$history=hds_history((int)$order['id']);$revision=(int)$dispatch['revision'];
    ob_start(); ?>
    <section class="hds-panel" aria-labelledby="hds-dispatch-heading"><h2 id="hds-dispatch-heading"><?=e(dt('dispatch'))?></h2>
      <div class="hds-dispatch-grid">
        <div class="hds-store-summary is-assigned"><span><?=e(dt('assigned'))?></span><strong><?=e($assigned['name']??dt('unassigned'))?></strong>
          <?php if($assigned):?><p><?=nl2br(e($assigned['address']??''))?></p><?php if(!empty($assigned['phone'])):?><p><?=e($assigned['phone'])?></p><?php endif;?><?php $map=hds_view_store_map($assigned);if($map!==''):?><a class="hds-map-link" href="<?=e($map)?>" target="_blank" rel="noopener noreferrer"><?=e(dt('open_map'))?> ↗</a><?php endif;?><?php if(isset($assigned['enabled'])&&!$assigned['enabled']):?><p class="hds-help"><?=e(dt('inactive_assigned'))?></p><?php endif;?><?php endif;?>
        </div>
        <?php if($ready && $recommended):?>
        <div class="hds-store-summary"><span><?=e(dt('suggested'))?></span>
          <strong><?=e($recommended['name'])?></strong><p><?=nl2br(e($recommended['address']??''))?></p><?php $map=hds_view_store_map($recommended);if($map!==''):?><a class="hds-map-link" href="<?=e($map)?>" target="_blank" rel="noopener noreferrer"><?=e(dt('open_map'))?> ↗</a><?php endif;?><?php if(isset($dispatch['distance_km'])&&is_numeric($dispatch['distance_km'])):?><p class="hds-distance"><?=e(dt('distance'))?> · <?=e(dt('distance_value',['distance'=>number_format((float)$dispatch['distance_km'],2)]))?></p><?php endif;?><p class="hds-help"><?=e(dt('recommendation_hint'))?></p>
        </div>
        <?php elseif(!$assigned):?>
        <div class="hds-store-summary"><span><?=e(dt('suggested'))?></span>
          <strong><?=e(dt('no_suggestion'))?></strong><p class="hds-help"><?=e(dt((string)($dispatch['message_key']??'dispatch_pending')))?></p>
        </div>
        <?php endif;?>
      </div>
      <?php if($editable):?>
      <?php 
        $currentSelectedId = isset($assigned['id']) ? (int)$assigned['id'] : (!empty($recommended['id']) ? (int)$recommended['id'] : 0);
        $currentSelectedAddress = $assigned['address'] ?? ($recommended['address'] ?? ($stores[0]['address'] ?? ''));
      ?>
      <div class="hds-controls">
        <form method="post" class="hds-manual-wrap" style="display:flex; flex-direction:column; gap:8px;">
          <?=csrf_field()?>
          <input type="hidden" name="dispatch_revision" value="<?=$revision?>">
          <label for="hds-store-select" style="font-weight:700; font-size:15px; color:#374151;">
            <?=e(dt('manual'))?>
          </label>
          <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <select name="store_id" id="hds-store-select" onchange="hdsUpdateStoreAddress(this)" style="flex:1; min-width:200px;" required>
              <?php foreach($stores as $store):?>
                <option value="<?=(int)$store['id']?>" 
                  data-address="<?=e($store['address'] ?? '')?>"
                  <?=$currentSelectedId === (int)$store['id'] ? 'selected' : ''?>>
                  <?=e($store['name'])?><?=!empty($store['address']) ? ' — ' . e(mb_strimwidth($store['address'], 0, 45, '...')) : ''?>
                </option>
              <?php endforeach;?>
            </select>
            <button class="hds-btn primary" name="action" value="dispatch_assign" type="submit" style="white-space:nowrap;" <?=$stores?'':'disabled'?>>
              <?=e(dt('assign'))?>
            </button>
          </div>
          <div id="hds-store-addr-box" style="font-size:0.83rem; color:#64748b; display:flex; align-items:flex-start; gap:4px; line-height:1.4; padding:2px 0;">
            <span style="flex-shrink:0;">📍</span>
            <span id="hds-store-addr-text"><?=e($currentSelectedAddress ?: adm_t('store_address_empty'))?></span>
          </div>
        </form>
        <script>
          function hdsUpdateStoreAddress(sel) {
            var opt = sel.options[sel.selectedIndex];
            var addr = opt ? (opt.getAttribute('data-address') || '') : '';
            var el = document.getElementById('hds-store-addr-text');
            if (el) el.textContent = addr || '<?= e(adm_t('store_address_empty')) ?>';
          }
        </script>
      </div>
      <?php else:?><p class="hds-help"><?=e(dt('terminal_hint'))?></p><?php endif;?>
      <details class="hds-history"><summary><?=e(dt('audit'))?></summary><?php if(!$history):?><p class="hds-help"><?=e(dt('audit_empty'))?></p><?php else:?><ol><?php foreach($history as $entry):?><li><strong><?=e(($entry['store_id']??null)?($entry['name']??dt('none')):dt('unassigned'))?></strong><span><?=e(($entry['admin_name']??'')?:dt('staff_number',['id'=>(int)($entry['admin_id']??0)]))?> · <?=e(hds_view_time((string)($entry['created_at']??'')))?></span><?php if(!empty($entry['address'])):?><p><?=e($entry['address'])?></p><?php endif;?></li><?php endforeach;?></ol><?php endif;?></details>
    </section>
    <?php return ob_get_clean();
}
