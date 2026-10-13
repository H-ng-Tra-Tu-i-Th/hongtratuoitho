<?php
/** v18.4 branding migration: apply the requested site name once on existing installs. */
function ensure_v184_branding(): void {
    static $done=false;
    if($done) return;
    $done=true;
    try {
        if(setting('v184_branding_applied','0')!=='1'){
            $name='Hồng Trà Tuổi Thơ 線上點餐';
            set_setting('store_name',$name);
            set_setting('store_name_vi',$name);
            set_setting('store_name_en',$name);
            set_setting('store_name_zh',$name);
            set_setting('v184_branding_applied','1');
        }
    } catch(Throwable $e) {}
}
