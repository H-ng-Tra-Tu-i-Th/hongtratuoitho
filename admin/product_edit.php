<?php
require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/../lib/layout.php';
require_admin();

$id=(int)($_GET['id']??0);
$p=[
    'category_id'=>'','name'=>'','name_vi'=>'','name_en'=>'','name_zh'=>'',
    'price'=>'','has_sizes'=>0,'price_l'=>'','price_xl'=>'','image'=>'',
    'ice_profile'=>'I2','sugar_profile'=>'S2','active'=>1,'sort_order'=>0,'is_new'=>0,'is_hot'=>0
];
if($id){
    $st=db()->prepare('SELECT * FROM products WHERE id=?');
    $st->execute([$id]);
    $p=$st->fetch()?:$p;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $id=(int)($_POST['id']??0);
    $cat=(int)($_POST['category_id']??0);
    $vi=trim($_POST['name_vi']??'');
    $en=trim($_POST['name_en']??'');
    $zh=trim($_POST['name_zh']??'');
    $name=$vi?:($en?:$zh);
    $hasSizes=isset($_POST['has_sizes'])?1:0;
    $price=(float)($_POST['price']??0);
    $priceL=(float)($_POST['price_l']??0);
    $priceXL=(float)($_POST['price_xl']??0);
    $ice=$_POST['ice_profile']??'I2';
    $sugar=$_POST['sugar_profile']??'S2';
    $active=isset($_POST['active'])?1:0;
    $isNew=isset($_POST['is_new'])?1:0; $isHot=isset($_POST['is_hot'])?1:0;
    $sort=(int)($_POST['sort_order']??0);
    $image=$_POST['current_image']??'';
    $recommended=array_values(array_unique(array_filter(array_map('intval',$_POST['recommended_toppings']??[]),fn($v)=>$v>0)));

    if(!preg_match('/^I[1-4]$/',$ice)||!preg_match('/^S[1-5]$/',$sugar)) exit(e(t('profile_error')));
    if($name==='') exit(e(t('incomplete_data')));
    if($hasSizes){
        if($priceL<0 || $priceXL<0 || ($_POST['price_l']??'')==='' || ($_POST['price_xl']??'')==='') exit(e(t('size_price_required')));
        $price=$priceL;
    } elseif($price<0 || ($_POST['price']??'')==='') exit(e(t('incomplete_data')));

    if(isset($_FILES['image'])&&$_FILES['image']['error']===UPLOAD_ERR_OK){
        $f=$_FILES['image'];
        if($f['size']>5*1024*1024) exit(e(t('image_too_large')));
        $info=getimagesize($f['tmp_name']);
        $allow=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if(!$info||!isset($allow[$info['mime']])) exit(e(t('image_type_error')));
        $fn=date('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$allow[$info['mime']];
        $uploadDir=__DIR__.'/../uploads';
        if(!is_dir($uploadDir) && !@mkdir($uploadDir,0755,true)) exit(e(t('image_upload_failed')));
        if(!move_uploaded_file($f['tmp_name'],$uploadDir.'/'.$fn)) exit(e(t('image_upload_failed')));
        if($image&&is_file(__DIR__.'/../uploads/'.$image)) @unlink(__DIR__.'/../uploads/'.$image);
        $image=$fn;
    }
    if(isset($_POST['remove_image'])&&$image){
        if(is_file(__DIR__.'/../uploads/'.$image)) @unlink(__DIR__.'/../uploads/'.$image);
        $image='';
    }

    $pdo=db();
    $pdo->beginTransaction();
    try{
        if($id){
            $pdo->prepare('UPDATE products SET category_id=?,name=?,name_vi=?,name_en=?,name_zh=?,price=?,has_sizes=?,price_l=?,price_xl=?,image=?,ice_profile=?,sugar_profile=?,active=?,sort_order=?,is_new=?,is_hot=? WHERE id=?')
                ->execute([$cat,$name,$vi?:null,$en?:null,$zh?:null,$price,$hasSizes,$hasSizes?$priceL:null,$hasSizes?$priceXL:null,$image?:null,$ice,$sugar,$active,$sort,$isNew,$isHot,$id]);
        } else {
            $pdo->prepare('INSERT INTO products(category_id,name,name_vi,name_en,name_zh,price,has_sizes,price_l,price_xl,image,ice_profile,sugar_profile,active,sort_order,is_new,is_hot) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$cat,$name,$vi?:null,$en?:null,$zh?:null,$price,$hasSizes,$hasSizes?$priceL:null,$hasSizes?$priceXL:null,$image?:null,$ice,$sugar,$active,$sort,$isNew,$isHot]);
            $id=(int)$pdo->lastInsertId();
        }
        $pdo->prepare('DELETE FROM product_recommended_toppings WHERE product_id=?')->execute([$id]);
        if($recommended){
            $valid=[];
            $ph=implode(',',array_fill(0,count($recommended),'?'));
            $st=$pdo->prepare("SELECT id FROM toppings WHERE id IN ($ph)");$st->execute($recommended);
            $valid=array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));
            $ins=$pdo->prepare('INSERT INTO product_recommended_toppings(product_id,topping_id,sort_order) VALUES(?,?,?)');
            foreach($valid as $order=>$tid)$ins->execute([$id,$tid,$order]);
        }
        $pdo->commit();
    }catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
    redirect('products.php');
}

$cats=db()->query('SELECT * FROM categories ORDER BY sort_order,id')->fetchAll();
$iceRows=db()->query("SELECT * FROM option_profiles WHERE profile_type='ice' ORDER BY code")->fetchAll();
$sugarRows=db()->query("SELECT * FROM option_profiles WHERE profile_type='sugar' ORDER BY code")->fetchAll();
$toppingRows=db()->query('SELECT * FROM toppings WHERE active=1 ORDER BY sort_order,id')->fetchAll();
$recommendedIds=[];
if($id){$st=db()->prepare('SELECT topping_id FROM product_recommended_toppings WHERE product_id=? ORDER BY sort_order,topping_id');$st->execute([$id]);$recommendedIds=array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));}
admin_header($id?t('edit_drink'):t('add_drink'));
?>
<section class="panel narrow">
<form method="post" enctype="multipart/form-data" class="form-grid ui-form-grid" data-size-price-form>
    <?=csrf_field()?>
    <input type="hidden" name="id" value="<?=$id?>">
    <input type="hidden" name="current_image" value="<?=e($p['image'])?>">


    <div class="multilang-box"><strong><?=e(t('drink_name'))?></strong><div class="multilang-grid">
        <label><?=e(t('name_vi'))?><input name="name_vi" value="<?=e($p['name_vi']?:$p['name'])?>" required></label>
        <label><?=e(t('name_en'))?><input name="name_en" value="<?=e($p['name_en']??'')?>"></label>
        <label><?=e(t('name_zh'))?><input name="name_zh" value="<?=e($p['name_zh']??'')?>"></label>
    </div><div class="field-hint"><?=e(t('language_fields_help'))?></div></div>

    <label><?=e(t('category'))?><select name="category_id" required><?php foreach($cats as $c):?><option value="<?=$c['id']?>" <?=$p['category_id']==$c['id']?'selected':''?>><?=e(localized_name($c))?></option><?php endforeach;?></select></label>
    <label><?=e(t('drink_image'))?><input name="image" type="file" accept="image/jpeg,image/png,image/webp"><small><?=e(t('image_help'))?></small></label>
    <div class="pricing-box">
        <label class="toggle-row"><input type="checkbox" name="has_sizes" value="1" data-size-toggle <?=!empty($p['has_sizes'])?'checked':''?>> <span><strong><?=e(t('enable_sizes'))?></strong><small><?=e(t('sized_drink_help'))?></small></span></label>
        <div class="single-price-field" data-single-price><label><?=e(t('single_price'))?><input name="price" type="number" min="0" step="1" value="<?=e($p['price'])?>"></label></div>
        <div class="size-price-grid" data-size-prices><label><?=e(t('price_l'))?><input name="price_l" type="number" min="0" step="1" value="<?=e($p['price_l']??'')?>"></label><label><?=e(t('price_xl'))?><input name="price_xl" type="number" min="0" step="1" value="<?=e($p['price_xl']??'')?>"></label></div>
    </div>

    <?php if($p['image']):?><div><img class="edit-preview" src="<?=e(base_url('uploads/'.$p['image']))?>" alt=""><label class="check-row"><input type="checkbox" name="remove_image"> <span><?=e(t('remove_current_image'))?></span></label></div><?php endif;?>
    <label><?=e(t('ice_profile'))?><select name="ice_profile"><?php foreach($iceRows as $r):?><option value="<?=e($r['code'])?>" <?=$p['ice_profile']===$r['code']?'selected':''?>><?=e($r['code'].'｜'.profile_display_name($r))?></option><?php endforeach;?></select></label>
    <label><?=e(t('sugar_profile'))?><select name="sugar_profile"><?php foreach($sugarRows as $r):?><option value="<?=e($r['code'])?>" <?=$p['sugar_profile']===$r['code']?'selected':''?>><?=e($r['code'].'｜'.profile_display_name($r))?></option><?php endforeach;?></select></label>

    <div class="recommended-admin-box"><div><strong><?=e(t('recommended_toppings'))?></strong><p class="field-hint"><?=e(t('recommended_toppings_help'))?></p></div><div class="recommended-admin-grid"><?php foreach($toppingRows as $top):?><label class="check-chip"><input type="checkbox" name="recommended_toppings[]" value="<?=$top['id']?>" <?=in_array((int)$top['id'],$recommendedIds,true)?'checked':''?>><span><?=e(localized_name($top))?> · +<?=e(currency())?><?=money($top['price'])?></span></label><?php endforeach;?></div></div>

    <label><?=e(t('sort'))?><input name="sort_order" type="number" value="<?=e($p['sort_order'])?>"></label>
    <div class="recommend-flags"><label class="check-chip"><input type="checkbox" name="is_new" <?=!empty($p['is_new'])?'checked':''?>> <span><?=e(t('mark_new'))?></span></label><label class="check-chip"><input type="checkbox" name="is_hot" <?=!empty($p['is_hot'])?'checked':''?>> <span><?=e(t('mark_hot'))?></span></label></div>
    <label class="toggle-row"><input type="checkbox" name="active" <?=$p['active']?'checked':''?>> <span><strong><?=e(t('enable_drink'))?></strong></span></label>
    <button class="btn primary"><?=e(t('save_drink'))?></button>
</form>
</section>
<?php admin_footer();?>
