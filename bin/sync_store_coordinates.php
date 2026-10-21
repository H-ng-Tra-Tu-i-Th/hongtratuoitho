<?php
require __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/dispatch.php';

$apiKey = defined('GOONG_API_KEY') ? GOONG_API_KEY : '';
if (empty($apiKey)) {
    echo "ERROR: GOONG_API_KEY is not defined in config.\n";
    exit(1);
}

$pdo = db();
$stores = $pdo->query('SELECT id, name, address, lat, lng FROM dispatch_stores WHERE enabled=1 ORDER BY id')->fetchAll();

echo "Starting Goong geocoding for " . count($stores) . " stores...\n";

foreach ($stores as $s) {
    $id = (int)$s['id'];
    $name = $s['name'];
    $addr = $s['address'];

    // Clean address for geocoding
    $cleanAddr = preg_replace('/\s+/', ' ', trim($addr));
    
    // Call Goong geocoding via curl
    $url = 'https://rsapi.goong.io/geocode?address=' . urlencode($cleanAddr) . '&api_key=' . urlencode($apiKey);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && $res) {
        $json = json_decode($res, true);
        $loc = $json['results'][0]['geometry']['location'] ?? null;
        if ($loc && isset($loc['lat'], $loc['lng']) && is_numeric($loc['lat']) && is_numeric($loc['lng'])) {
            $lat = (float)$loc['lat'];
            $lng = (float)$loc['lng'];
            if ($lat >= -90.0 && $lat <= 90.0 && $lng >= -180.0 && $lng <= 180.0) {
                $upd = $pdo->prepare('UPDATE dispatch_stores SET lat=?, lng=?, coord_source="google", geocoded_at=UTC_TIMESTAMP(), expires_at=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY) WHERE id=?');
                $upd->execute([$lat, $lng, $id]);
                echo " [OK] Store #$id ($name): lat=$lat, lng=$lng | Address: $cleanAddr\n";
            } else {
                echo " [WARN] Store #$id ($name): Coordinates out of bounds (lat=$lat, lng=$lng)\n";
            }
        } else {
            echo " [WARN] Store #$id ($name): Could not find geometry location. Result status: " . ($json['status'] ?? 'unknown') . "\n";
        }
    } else {
        echo " [ERROR] Store #$id ($name): HTTP $code\n";
    }

    usleep(150000); // 150ms throttle
}

echo "Done syncing store coordinates!\n";
