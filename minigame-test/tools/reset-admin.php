<?php
declare(strict_types=1);
// Command-line only. Requires the hosting account's file/terminal access.
if(PHP_SAPI !== 'cli') {http_response_code(404);exit;}
define('KYUC_APP',true); require dirname(__DIR__).'/app/core.php';
if(!is_file(dirname(__DIR__).'/app/config.php')) {fwrite(STDERR,"Not installed.\n");exit(1);}
if(count($argv)!==2) {fwrite(STDERR,"Usage: php tools/reset-admin.php ADMIN_USERNAME\n");exit(1);}
try {
    $un=username($argv[1]); $password=bin2hex(random_bytes(12)).'!K';
    store(function(array &$d) use($un,$password){
        foreach($d['users'] as $id=>$u) if($u['role']==='admin' && $u['username']===$un) {
            $d['users'][$id]['password']=password_hash($password,PASSWORD_DEFAULT);
            $d['users'][$id]['auth_version']++; $d['users'][$id]['active']=true;
            audit($d,'admin_cli_reset',$un); return;
        }
        fail('Administrator not found.');
    });
    fwrite(STDOUT,"Administrator: ".$un."\nTemporary password: ".$password."\nSign in over HTTPS and change it immediately. All previous sessions have been invalidated.\n");
} catch(Throwable $e) {fwrite(STDERR,"Reset failed: ".$e->getMessage()."\n");exit(1);}
