<?php
/** v18.5 branding migration: localize the ordering suffix while preserving the Hồng Trà Tuổi Thơ company name. */
function ensure_v185_branding(): void {
    static $done=false;
    if($done) return;
    $done=true;
    try {
        if(setting('v185_branding_applied','0')!=='1'){
            $vi='Hồng Trà Tuổi Thơ Đặt đồ uống trực tuyến';
            $en='Hồng Trà Tuổi Thơ Online Ordering';
            $zh='Hồng Trà Tuổi Thơ 線上點餐';
            set_setting('store_name',$vi);
            set_setting('store_name_vi',$vi);
            set_setting('store_name_en',$en);
            set_setting('store_name_zh',$zh);
            set_setting('v185_branding_applied','1');
        }
    } catch(Throwable $e) {}
}
