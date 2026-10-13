<?php
/** Game art is uploaded as checked raster images; the admin never accepts a client path. */
function hgr_media_relative_valid(string $path): bool {
    return preg_match('~\Auploads/minigame/[a-f0-9]{32}\.(?:jpg|png|webp)\z~D',$path)===1;
}
function hgr_media_directory(): string {
    return dirname(__DIR__).DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'minigame';
}
function hgr_media_prepare_directory(): string {
    $parent=dirname(__DIR__).DIRECTORY_SEPARATOR.'uploads';$directory=hgr_media_directory();
    if(is_link($parent)||is_link($directory))throw new RuntimeException('game_media_storage');
    if(!is_dir($parent)&&!mkdir($parent,0755,true)&&!is_dir($parent))throw new RuntimeException('game_media_storage');
    if(!is_dir($directory)&&!mkdir($directory,0755)&&!is_dir($directory))throw new RuntimeException('game_media_storage');
    $guard="Options -Indexes -ExecCGI\n<FilesMatch \"^(?![a-f0-9]{32}\\.(jpg|png|webp)$)\">\n  <IfModule mod_authz_core.c>\n    Require all denied\n  </IfModule>\n  <IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n  </IfModule>\n</FilesMatch>\n<FilesMatch \"\\.(jpg|png|webp)$\">\n  SetHandler default-handler\n</FilesMatch>\n";
    foreach(['.htaccess'=>$guard,'index.html'=>''] as $name=>$contents){
        $file=$directory.DIRECTORY_SEPARATOR.$name;
        if(is_link($file))throw new RuntimeException('game_media_storage');
        if(!is_file($file)||($name==='.htaccess'&&file_get_contents($file)!==$contents)){
            if(file_put_contents($file,$contents,LOCK_EX)===false)throw new RuntimeException('game_media_storage');
            @chmod($file,0644);
        }
    }
    return $directory;
}
/** Validate every image before any settings transaction or upload move starts. */
function hgr_media_inspect_upload(array $file): ?array {
    $error=$file['error']??UPLOAD_ERR_NO_FILE;
    if(!is_int($error)&&!(is_string($error)&&ctype_digit($error)))throw new InvalidArgumentException('game_media_invalid');
    if((int)$error===UPLOAD_ERR_NO_FILE)return null;
    if((int)$error!==UPLOAD_ERR_OK)throw new InvalidArgumentException('game_media_upload');
    $temporary=$file['tmp_name']??null;
    if(!is_string($temporary)||$temporary===''||!is_uploaded_file($temporary))throw new InvalidArgumentException('game_media_upload');
    $size=filesize($temporary);
    if($size===false||$size<1||$size>2*1024*1024)throw new InvalidArgumentException('game_media_size');
    $image=@getimagesize($temporary);$extensions=[IMAGETYPE_JPEG=>'jpg',IMAGETYPE_PNG=>'png',IMAGETYPE_WEBP=>'webp'];
    if(!$image||!isset($extensions[$image[2]])||($image['mime']??'')!==['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][$extensions[$image[2]]])throw new InvalidArgumentException('game_media_type');
    if($image[0]<1||$image[1]<1||$image[0]>4096||$image[1]>4096)throw new InvalidArgumentException('game_media_dimensions');
    return ['temporary'=>$temporary,'extension'=>$extensions[$image[2]]];
}
function hgr_media_store(array $checked,array &$created): string {
    $extension=$checked['extension']??'';$temporary=$checked['temporary']??'';
    if(!in_array($extension,['jpg','png','webp'],true)||!is_string($temporary)||!is_uploaded_file($temporary))throw new InvalidArgumentException('game_media_upload');
    $directory=hgr_media_prepare_directory();
    do{$name=bin2hex(random_bytes(16)).'.'.$extension;$destination=$directory.DIRECTORY_SEPARATOR.$name;}while(file_exists($destination));
    if(!move_uploaded_file($temporary,$destination))throw new RuntimeException('game_media_upload');
    $relative='uploads/minigame/'.$name;$created[]=$relative;@chmod($destination,0644);
    return $relative;
}
/** Only request-generated names inside this fixed directory are eligible for failure cleanup. */
function hgr_media_cleanup(array $created): void {
    $directory=hgr_media_directory();$resolved=realpath($directory);
    if($resolved===false||is_link($directory))return;
    foreach($created as $relative){
        if(!is_string($relative)||!hgr_media_relative_valid($relative))continue;
        $file=$directory.DIRECTORY_SEPARATOR.basename($relative);
        if(is_link($file)||!is_file($file))continue;
        $actual=realpath($file);
        if($actual!==false&&dirname($actual)===$resolved)@unlink($actual);
    }
}
