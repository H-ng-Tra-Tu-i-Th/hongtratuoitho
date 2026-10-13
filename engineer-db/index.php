<?php
/** Dedicated HTTPS database login; deliberately does not load the ordering application. */
require __DIR__.'/scope.php';
header('Cache-Control: private, no-store, max-age=0');header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');
if(!huiyi_engineer_db_https($_SERVER)){http_response_code(403);header('Content-Type: text/plain; charset=UTF-8');exit('HTTPS is required. Use https://shop.huiyitea.vn/engineer-db/');}
if(!empty($_SERVER['PATH_INFO'])||!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','POST','HEAD'],true)){http_response_code(405);exit('Unsupported request.');}
if(!huiyi_engineer_db_scope($_GET,$_POST)){http_response_code(403);exit('This interface is restricted to the dedicated shop database account.');}
$_GET['server']=HUIYI_ENGINEER_DB_SERVER;$_GET['username']=HUIYI_ENGINEER_DB_USER;$_GET['db']=HUIYI_ENGINEER_DB_DATABASE;
// Permanent logins are disabled; retain Adminer's separate session-password encryption key.
unset($_COOKIE['adminer_permanent']);
if(isset($_POST['auth'])&&!huiyi_engineer_db_login_key($_COOKIE)){
    http_response_code(403);exit('Open the database login page first to establish the secure login cookie.');
}
ini_set('session.use_only_cookies','1');ini_set('session.use_strict_mode','1');ini_set('session.use_trans_sid','0');
if(session_status()!==PHP_SESSION_NONE){http_response_code(500);exit('The database interface needs an independent session.');}
// Let the official vendor start its isolated adminer_sid session and scoped HTTPS cookies.
// Starting it here would make the vendor's session ini initialization run while active.
ini_set('session.cookie_secure','1');ini_set('session.cookie_httponly','1');
function adminer_object() {
    static $instance;if($instance)return $instance;
    // The official compiled single-file release omits parameter types. Preserve those signatures.
    class HuiyiShopAdminer extends \Adminer\Adminer {
        function name(){return 'HuiYi Shop · Engineer Database';}
        function credentials(){return [HUIYI_ENGINEER_DB_SERVER,HUIYI_ENGINEER_DB_USER,\Adminer\get_password()];}
        function database(){return HUIYI_ENGINEER_DB_DATABASE;}
        function databases($flush=true){return [HUIYI_ENGINEER_DB_DATABASE];}
        function login($login,$password){
            if($login!==HUIYI_ENGINEER_DB_USER||!is_string($password)||$password===''||\Adminer\SERVER!==HUIYI_ENGINEER_DB_SERVER||\Adminer\DB!==HUIYI_ENGINEER_DB_DATABASE)return false;
            return parent::login($login,$password);
        }
        function permanentLogin($create=false){return '';}
        function verifyLoginToken(){return true;}
        function verifyVersion(){return false;}
        function serviceWorker(){}
        function manifest(){return [];}
        function importServerPath(){return '';}
        function sqlUrlLength(){return 0;}
        function headers(){
            header('Cache-Control: private, no-store, max-age=0');header('X-Robots-Tag: noindex, nofollow, noarchive');
            header('Referrer-Policy: no-referrer');header('X-Frame-Options: DENY');
        }
        function loginForm(){
            echo '<p>Connect only to the dedicated shop database. Enter the database account password; FTP and ordering-admin passwords are separate.</p><table class="layout">';
            foreach(['driver'=>'server','server'=>HUIYI_ENGINEER_DB_SERVER,'username'=>HUIYI_ENGINEER_DB_USER,'db'=>HUIYI_ENGINEER_DB_DATABASE] as $key=>$value){
                $label=$key==='driver'?'System':ucfirst($key);$display=$key==='driver'?'MySQL / MariaDB':$value;
                echo '<tr><th>'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'<td><strong>'.htmlspecialchars($display,ENT_QUOTES,'UTF-8').'</strong><input type="hidden" name="auth['.$key.']" value="'.htmlspecialchars($value,ENT_QUOTES,'UTF-8').'">';
            }
            echo '<tr><th>Password<td><input type="password" name="auth[password]" autocomplete="current-password" required autofocus></table><p><input type="submit" value="Login">';
        }
    }
    return $instance=new HuiyiShopAdminer;
}
$vendor=__DIR__.'/vendor/adminer-6.1.2-mysql.php';
if(!is_file($vendor)||!hash_equals('30d600b5d8750600b7cf7d8e4652f27d3bcd23ca6c9e1e6399a9b4a274fd7c0e',hash_file('sha256',$vendor))){http_response_code(503);exit('Verified database interface unavailable.');}
require $vendor;
