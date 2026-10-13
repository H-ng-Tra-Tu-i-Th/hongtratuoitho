<?php
/** Web Push crypto (RFC 8291 / RFC 8188 / RFC 8292). PHP 8+, OpenSSL. */
function b64url_encode(string $data): string { return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); }
function b64url_decode(string $data): string {
    if (!preg_match('/^[A-Za-z0-9_-]*={0,2}$/D', $data)) return '';
    $raw=base64_decode(strtr($data, '-_', '+/'), true);
    return $raw===false ? '' : $raw;
}
function hqp_public_raw($key): string {
    $d=openssl_pkey_get_details($key);
    if (!$d || ($d['ec']['curve_name']??'')!=='prime256v1') throw new RuntimeException('invalid_p256_key');
    return "\x04".str_pad($d['ec']['x'],32,"\0",STR_PAD_LEFT).str_pad($d['ec']['y'],32,"\0",STR_PAD_LEFT);
}
function hqp_public_pem(string $raw): string {
    if (strlen($raw)!==65 || $raw[0]!=="\x04") throw new RuntimeException('invalid_subscription_key');
    $der=hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$raw;
    $pem="-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der),64,"\n")."-----END PUBLIC KEY-----\n";
    if (!@openssl_pkey_get_public($pem)) throw new RuntimeException('invalid_subscription_point');
    return $pem;
}
function hqp_hkdf_expand(string $prk, string $info, int $length): string {
    $t=''; $out='';
    for ($i=1; strlen($out)<$length; $i++) { $t=hash_hmac('sha256',$t.$info.chr($i),$prk,true); $out.=$t; }
    return substr($out,0,$length);
}
function hqp_encrypt(string $plaintext, string $p256dh, string $auth): string {
    if (strlen($plaintext)>3000) throw new RuntimeException('push_payload_too_large');
    $ua=b64url_decode($p256dh); $secret=b64url_decode($auth);
    if (strlen($secret)!==16) throw new RuntimeException('invalid_auth_secret');
    $peer=openssl_pkey_get_public(hqp_public_pem($ua));
    $local=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC,'curve_name'=>'prime256v1']);
    if (!$local) throw new RuntimeException('encryption_key_failed');
    $as=hqp_public_raw($local);
    $shared=openssl_pkey_derive($peer,$local,32);
    if ($shared===false) throw new RuntimeException('ecdh_failed');
    $prkKey=hash_hmac('sha256',$shared,$secret,true);
    $ikm=hqp_hkdf_expand($prkKey,"WebPush: info\0".$ua.$as,32);
    $salt=random_bytes(16);
    $prk=hash_hmac('sha256',$ikm,$salt,true);
    $cek=hqp_hkdf_expand($prk,"Content-Encoding: aes128gcm\0",16);
    $nonce=hqp_hkdf_expand($prk,"Content-Encoding: nonce\0",12);
    $tag='';
    $cipher=openssl_encrypt($plaintext."\x02",'aes-128-gcm',$cek,OPENSSL_RAW_DATA,$nonce,$tag,'',16);
    if ($cipher===false) throw new RuntimeException('aes_gcm_failed');
    return $salt.pack('N',4096).chr(65).$as.$cipher.$tag;
}
function der_ecdsa_to_jose(string $der, int $partLength=32): string {
    $pos=0; $length=strlen($der);
    $readLen=static function() use (&$pos,$der,$length): int {
        if($pos>=$length) throw new RuntimeException('invalid_der');
        $n=ord($der[$pos++]); if(!($n&128))return $n;
        $count=$n&127; if($count<1||$count>2||$pos+$count>$length)throw new RuntimeException('invalid_der');
        $n=0;while($count--)$n=($n<<8)|ord($der[$pos++]);return $n;
    };
    try {
        if($length<8||ord($der[$pos++])!==0x30)return '';
        $size=$readLen(); if($size!==$length-$pos)return '';
        $parts=[];
        for($i=0;$i<2;$i++){
            if($pos>=$length||ord($der[$pos++])!==2)return '';
            $n=$readLen();if($n<1||$pos+$n>$length)return '';
            $v=ltrim(substr($der,$pos,$n),"\0");$pos+=$n;
            if(strlen($v)>$partLength)return '';
            $parts[]=str_pad($v,$partLength,"\0",STR_PAD_LEFT);
        }
        return $pos===$length?implode('',$parts):'';
    } catch(Throwable $e){return '';}
}
function hqp_allowed_endpoint(string $endpoint): bool {
    if(strlen($endpoint)>4096 || preg_match('/[\x00-\x20\x7f]/',$endpoint))return false;
    $u=parse_url($endpoint);
    if(!$u||($u['scheme']??'')!=='https'||isset($u['user'])||isset($u['pass'])||isset($u['fragment'])||(isset($u['port'])&&$u['port']!==443))return false;
    $h=strtolower($u['host']??'');
    if(in_array($h,['fcm.googleapis.com','android.googleapis.com','updates.push.services.mozilla.com','push.services.mozilla.com'],true))return true;
    foreach(['.push.apple.com','.notify.windows.com'] as $suffix)if(str_ends_with($h,$suffix)&&strlen($h)>strlen($suffix))return true;
    return false;
}
