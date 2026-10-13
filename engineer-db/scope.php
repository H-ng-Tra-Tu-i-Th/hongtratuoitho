<?php
/** Fixed deployment scope. This file contains public identifiers, never credentials. */
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){http_response_code(404);exit;}
const HUIYI_ENGINEER_DB_SERVER='localhost';
const HUIYI_ENGINEER_DB_USER='huiyitea_shop_huiyi_it';
const HUIYI_ENGINEER_DB_DATABASE='huiyitea_shop';
function huiyi_engineer_db_https(array $server): bool {
    // Match the application's origin HTTPS signal. Client-supplied proxy headers confer no trust.
    $https=$server['HTTPS']??'';
    return (is_string($https)&&$https!==''&&strcasecmp($https,'off')!==0&&$https!=='0')||(string)($server['SERVER_PORT']??'')==='443';
}
function huiyi_engineer_db_login_key(array $cookies): bool {
    // Match the vendor's PHP truthiness check: the string "0" is not an encryption key.
    return isset($cookies['adminer_key'])&&is_string($cookies['adminer_key'])&&!empty($cookies['adminer_key']);
}
function huiyi_engineer_db_scope(array $get,array $post): bool {
    foreach(['server'=>HUIYI_ENGINEER_DB_SERVER,'username'=>HUIYI_ENGINEER_DB_USER,'db'=>HUIYI_ENGINEER_DB_DATABASE] as $field=>$expected)
        if(array_key_exists($field,$get)&&(!is_string($get[$field])||$get[$field]!==$expected))return false;
    foreach(['pgsql','sqlite','sqlite2','mssql','oracle','mongo','elastic','redis','driver'] as $driver)if(array_key_exists($driver,$get))return false;
    if(array_key_exists('auth',$post)){
        if(!is_array($post['auth']))return false;
        foreach(['driver'=>'server','server'=>HUIYI_ENGINEER_DB_SERVER,'username'=>HUIYI_ENGINEER_DB_USER,'db'=>HUIYI_ENGINEER_DB_DATABASE] as $field=>$expected)
            if(!isset($post['auth'][$field])||!is_string($post['auth'][$field])||$post['auth'][$field]!==$expected)return false;
        if(!isset($post['auth']['password'])||!is_string($post['auth']['password'])||!empty($post['auth']['permanent']))return false;
    }
    // No passkey/password-less setup files or permanent-login credential sharing.
    if(isset($post['password_less'])||isset($post['plugins'])||isset($get['plugins']))return false;
    return true;
}
