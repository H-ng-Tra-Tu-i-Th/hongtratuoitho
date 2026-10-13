<?php
require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/../lib/layout.php';
require_admin();
$rows=db()->query("SELECT p.*,c.name category_name,c.name_vi category_name_vi,c.name_en category_name_en,c.name_zh category_name_zh,COALESCE(s.sold_qty,0) sold_qty FROM products p JOIN categories c ON c.id=p.category_id LEFT JOIN (SELECT oi.product_id,SUM(oi.qty) sold_qty FROM order_items oi WHERE oi.product_id IS NOT NULL GROUP BY oi.product_id) s ON s.product_id=p.id ORDER BY c.sort_order,p.sort_order,p.id")->fetchAll();
admin_header(t('drink_management'));
?>
<div class="toolbar"><a class="btn primary" href="product_edit.php">＋ <?=e(t('add_drink'))?></a></div>
<div class="table-wrap"><table><thead><tr><th><?=e(t('image'))?></th><th><?=e(t('name'))?></th><th><?=e(t('category'))?></th><th><?=e(t('price'))?></th><th><?=e(t('cumulative_orders'))?></th><th><?=e(t('ice_profile'))?></th><th><?=e(t('sugar_profile'))?></th><th><?=e(t('status'))?></th><th></th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr>
    <td><?php if($r['image']):?><img class="thumb" src="<?=e(base_url('uploads/'.$r['image']))?>" alt=""><?php else:?>—<?php endif;?></td>
    <td><?=e(localized_name($r))?><?php if(product_has_sizes($r)):?><div class="table-note">L / XL</div><?php endif;?></td>
    <td><?=e(localized_name($r,'category_name'))?></td>
    <td><?php if(product_has_sizes($r)):?><div class="table-price-lines"><span>L <?=e(currency())?><?=money($r['price_l']??0)?></span><span>XL <?=e(currency())?><?=money($r['price_xl']??0)?></span></div><?php else:?><?=e(product_price_text($r))?><?php endif;?></td>
    <td><?=number_format((int)$r['sold_qty'])?></td>
    <td><?=e($r['ice_profile'])?></td><td><?=e($r['sugar_profile'])?></td>
    <td><?=$r['active']?e(t('active')):e(t('inactive'))?></td>
    <td><a class="btn small" href="product_edit.php?id=<?=$r['id']?>"><?=e(t('edit'))?></a></td>
</tr><?php endforeach;?></tbody></table></div>
<?php admin_footer();?>
