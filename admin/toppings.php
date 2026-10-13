<?php
require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/../lib/layout.php';
require_admin();

$rows=db()->query('SELECT * FROM toppings ORDER BY sort_order,id')->fetchAll();
admin_header(t('shared_toppings'));
?>
<div class="alert info"><?=e(t('shared_toppings_info'))?> <?=e(t('standalone_topping_info'))?></div>
<div class="toolbar"><a class="btn primary" href="topping_edit.php">＋ <?=e(t('add_topping'))?></a></div>
<div class="table-wrap"><table><thead><tr>
    <th><?=e(t('image'))?></th>
    <th><?=e(t('name'))?></th>
    <th><?=e(t('surcharge'))?></th>
    <th><?=e(t('standalone_sale'))?></th>
    <th><?=e(t('standalone_price'))?></th>
    <th><?=e(t('sort'))?></th>
    <th><?=e(t('status'))?></th>
    <th></th>
</tr></thead><tbody>
<?php if(!$rows):?><tr><td colspan="8"><?=e(t('no_data'))?></td></tr><?php endif;?>
<?php foreach($rows as $r):?><tr>
    <td><?php if(!empty($r['image'])):?><img class="thumb" src="<?=e(base_url('uploads/'.$r['image']))?>" alt=""><?php else:?>—<?php endif;?></td>
    <td>
        <strong><?=e(localized_name($r))?></strong>
        <?php if(!empty($r['is_new']) || !empty($r['is_hot'])):?><div class="table-note"><?php if(!empty($r['is_new'])):?><?=e(t('mark_new'))?><?php endif;?><?php if(!empty($r['is_new']) && !empty($r['is_hot'])):?> · <?php endif;?><?php if(!empty($r['is_hot'])):?><?=e(t('mark_hot'))?><?php endif;?></div><?php endif;?>
    </td>
    <td>+<?=e(currency())?><?=money($r['price'])?></td>
    <td><?=!empty($r['sell_separately'])?e(t('yes')):e(t('no'))?></td>
    <td><?=!empty($r['sell_separately'])&&$r['retail_price']!==null?e(currency()).money($r['retail_price']):'—'?></td>
    <td><?=number_format((int)$r['sort_order'])?></td>
    <td><?=$r['active']?e(t('active')):e(t('inactive'))?></td>
    <td><a class="btn small" href="topping_edit.php?id=<?=$r['id']?>"><?=e(t('edit'))?></a></td>
</tr><?php endforeach;?></tbody></table></div>
<?php admin_footer();?>
