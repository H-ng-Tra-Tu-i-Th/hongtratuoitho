<?php
require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/../lib/layout.php';
require_admin();

function topping_edit_upload_image(string $current=''): string {
    if(!isset($_FILES['image']) || $_FILES['image']['error']!==UPLOAD_ERR_OK) return $current;
    $f=$_FILES['image'];
    if($f['size']>5*1024*1024) exit(e(t('image_too_large')));
    $info=getimagesize($f['tmp_name']);
    $allow=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!$info || !isset($allow[$info['mime']])) exit(e(t('image_type_error')));
    $dir=__DIR__.'/../uploads';
    if(!is_dir($dir) && !@mkdir($dir,0755,true)) exit(e(t('image_upload_failed')));
    $fn='top-'.date('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$allow[$info['mime']];
    if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$fn)) exit(e(t('image_upload_failed')));
    if($current && is_file($dir.'/'.$current)) @unlink($dir.'/'.$current);
    return $fn;
}

$id=(int)($_GET['id']??0);
$p=[
    'name'=>'','name_vi'=>'','name_en'=>'','name_zh'=>'',
    'description_vi'=>'','description_en'=>'','description_zh'=>'',
    'price'=>'','sell_separately'=>0,'retail_price'=>'','image'=>'',
    'sort_order'=>0,'active'=>1,'is_new'=>0,'is_hot'=>0
];
if($id){
    $st=db()->prepare('SELECT * FROM toppings WHERE id=?');
    $st->execute([$id]);
    $found=$st->fetch();
    if(!$found){ http_response_code(404); exit(e(t('product_not_found'))); }
    $p=$found;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $act=$_POST['act']??'save';
    $id=(int)($_POST['id']??0);

    if($act==='delete' && $id){
        $st=db()->prepare('SELECT image FROM toppings WHERE id=?');
        $st->execute([$id]);
        $img=(string)($st->fetchColumn()?:'');
        db()->prepare('DELETE FROM toppings WHERE id=?')->execute([$id]);
        if($img && is_file(__DIR__.'/../uploads/'.$img)) @unlink(__DIR__.'/../uploads/'.$img);
        redirect('toppings.php');
    }

    $vi=trim($_POST['name_vi']??'');
    $en=trim($_POST['name_en']??'');
    $zh=trim($_POST['name_zh']??'');
    $name=$vi?:($en?:$zh);
    $dvi=trim($_POST['description_vi']??'');
    $den=trim($_POST['description_en']??'');
    $dzh=trim($_POST['description_zh']??'');
    $price=(float)($_POST['price']??0);
    $sell=isset($_POST['sell_separately'])?1:0;
    $retail=(float)($_POST['retail_price']??0);
    $sort=(int)($_POST['sort_order']??0);
    $active=isset($_POST['active'])?1:0;
    $isNew=isset($_POST['is_new'])?1:0;
    $isHot=isset($_POST['is_hot'])?1:0;
    $image=(string)($_POST['current_image']??'');

    if($name==='' || ($_POST['price']??'')==='' || $price<0) exit(e(t('incomplete_data')));
    if($sell && (($_POST['retail_price']??'')==='' || $retail<0)) exit(e(t('standalone_price_required')));

    $image=topping_edit_upload_image($image);
    if(isset($_POST['remove_image']) && $image){
        if(is_file(__DIR__.'/../uploads/'.$image)) @unlink(__DIR__.'/../uploads/'.$image);
        $image='';
    }

    if($id){
        db()->prepare('UPDATE toppings SET name=?,name_vi=?,name_en=?,name_zh=?,description_vi=?,description_en=?,description_zh=?,price=?,sell_separately=?,retail_price=?,image=?,sort_order=?,active=?,is_new=?,is_hot=? WHERE id=?')
            ->execute([$name,$vi?:null,$en?:null,$zh?:null,$dvi?:null,$den?:null,$dzh?:null,$price,$sell,$sell?$retail:null,$image?:null,$sort,$active,$isNew,$isHot,$id]);
    }else{
        db()->prepare('INSERT INTO toppings(name,name_vi,name_en,name_zh,description_vi,description_en,description_zh,price,sell_separately,retail_price,image,sort_order,active,is_new,is_hot) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$name,$vi?:null,$en?:null,$zh?:null,$dvi?:null,$den?:null,$dzh?:null,$price,$sell,$sell?$retail:null,$image?:null,$sort,$active,$isNew,$isHot]);
    }
    redirect('toppings.php');
}

admin_header($id?t('edit_topping'):t('add_topping'));
?>
<section class="panel narrow">
<form method="post" enctype="multipart/form-data" class="form-grid ui-form-grid" data-topping-price-form>
    <?=csrf_field()?>
    <input type="hidden" name="act" value="save">
    <input type="hidden" name="id" value="<?=$id?>">
    <input type="hidden" name="current_image" value="<?=e($p['image']??'')?>">

    <div class="multilang-box"><strong><?=e(t('topping_name'))?></strong><div class="multilang-grid">
        <label><?=e(t('name_vi'))?><input name="name_vi" value="<?=e($p['name_vi']?:$p['name'])?>" required></label>
        <label><?=e(t('name_en'))?><input name="name_en" value="<?=e($p['name_en']??'')?>"></label>
        <label><?=e(t('name_zh'))?><input name="name_zh" value="<?=e($p['name_zh']??'')?>"></label>
    </div><div class="field-hint"><?=e(t('language_fields_help'))?></div></div>

    <div class="multilang-box"><strong><?=e(t('topping_description'))?></strong><div class="multilang-grid">
        <label><?=e(t('description_vi'))?><textarea name="description_vi" rows="4"><?=e($p['description_vi']??'')?></textarea></label>
        <label><?=e(t('description_en'))?><textarea name="description_en" rows="4"><?=e($p['description_en']??'')?></textarea></label>
        <label><?=e(t('description_zh'))?><textarea name="description_zh" rows="4"><?=e($p['description_zh']??'')?></textarea></label>
    </div></div>

    <div class="pricing-box">
        <label><?=e(t('surcharge'))?><input name="price" type="number" min="0" step="1" value="<?=e((string)$p['price'])?>" required></label>
        <label class="toggle-row"><input type="checkbox" name="sell_separately" value="1" data-topping-retail-toggle <?=!empty($p['sell_separately'])?'checked':''?>> <span><strong><?=e(t('allow_standalone_sale'))?></strong><small><?=e(t('allow_standalone_sale_help'))?></small></span></label>
        <div data-topping-retail-price><label><?=e(t('standalone_price'))?><input name="retail_price" type="number" min="0" step="1" value="<?=e($p['retail_price']??'')?>"></label></div>
    </div>

    <label><?=e(t('topping_image'))?><input type="file" name="image" accept="image/jpeg,image/png,image/webp"><small><?=e(t('image_help'))?></small></label>
    <?php if(!empty($p['image'])):?><div><img class="edit-preview" src="<?=e(base_url('uploads/'.$p['image']))?>" alt=""><label class="check-row"><input type="checkbox" name="remove_image"> <span><?=e(t('remove_current_image'))?></span></label></div><?php endif;?>

    <label><?=e(t('sort'))?><input name="sort_order" type="number" value="<?=e((string)$p['sort_order'])?>"></label>
    <div class="recommend-flags ui-full"><label class="check-chip"><input type="checkbox" name="is_new" <?=!empty($p['is_new'])?'checked':''?>> <span><?=e(t('mark_new'))?></span></label><label class="check-chip"><input type="checkbox" name="is_hot" <?=!empty($p['is_hot'])?'checked':''?>> <span><?=e(t('mark_hot'))?></span></label></div>
    <label class="toggle-row"><input type="checkbox" name="active" <?=!empty($p['active'])?'checked':''?>> <span><strong><?=e(t('enable_topping'))?></strong></span></label>

    <div class="editor-actions topping-edit-actions">
        <button class="btn primary" type="submit"><?=e(t('save_topping'))?></button>
        <a class="btn" href="toppings.php"><?=e(t('back_to_list'))?></a>
        <?php if($id):?><button class="btn danger" type="submit" name="act" value="delete" onclick="return confirm('<?=e(t('confirm_delete'))?>')"><?=e(t('delete'))?></button><?php endif;?>
        <?php if($id && !empty($p['sell_separately'])):?><a class="btn" target="_blank" href="<?=e(base_url('topping.php?id='.$id))?>"><?=e(t('view_product_page'))?></a><?php endif;?>
    </div>
</form>
</section>
<?php admin_footer();?>
