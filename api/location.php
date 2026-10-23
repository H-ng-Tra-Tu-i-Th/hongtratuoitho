<?php
require __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-cache, no-store, must-revalidate');

// Simple session-based rate limiting to prevent abuse/proxy attacks on Google Maps API
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$now = time();
$ipHash = substr(hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'local')), 0, 16);
$rateKey = 'loc_rate_' . $ipHash;
$rateData = $_SESSION[$rateKey] ?? ['count' => 0, 'start' => $now];
if (
    !is_array($rateData)
    || !isset($rateData['count'], $rateData['start'])
    || !is_int($rateData['count'])
    || !is_int($rateData['start'])
    || $rateData['count'] < 0
    || $rateData['start'] < 0
) {
    $rateData = ['count' => 0, 'start' => $now];
}
if ($now - $rateData['start'] > 60 || $rateData['start'] > $now) {
    $rateData = ['count' => 1, 'start' => $now];
} else {
    $rateData['count']++;
}
$_SESSION[$rateKey] = $rateData;
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
if ($rateData['count'] > 40) {
    http_response_code(429);
    echo json_encode([
        'status' => 'ERROR',
        'message' => 'Bạn đã gửi quá nhiều yêu cầu định vị. Vui lòng chờ 1 phút.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Google Maps Platform API Key
$googleApiKey = defined('GOOGLE_MAPS_API_KEY') ? trim((string)GOOGLE_MAPS_API_KEY) : '';
if ($googleApiKey === '' && function_exists('setting')) {
    $googleApiKey = trim((string)setting('google_maps_api_key', ''));
}

if ($googleApiKey === '') {
    http_response_code(503);
    echo json_encode([
        'status' => 'ERROR',
        'message' => 'Chưa cấu hình GOOGLE_MAPS_API_KEY trên máy chủ.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

function call_google_maps_api(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $res = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($res === false) {
        return ['status' => 'ERROR', 'http_code' => 500, 'message' => 'Lỗi kết nối tới Google Maps: ' . $err];
    }
    try {
        $json = json_decode($res, true, 512, JSON_THROW_ON_ERROR);
        return ['status' => 'OK', 'http_code' => $httpCode ?: 200, 'data' => $json];
    } catch (JsonException $e) {
        return ['status' => 'ERROR', 'http_code' => 502, 'message' => 'Phản hồi từ Google Maps không hợp lệ.'];
    }
}

/**
 * Extracts compound address (province, district, commune, street name)
 * from Google Maps address_components for frontend cascade and profile integration.
 */
function extract_google_compound(array $components): array
{
    $compound = [
        'province' => '',
        'district' => '',
        'commune' => '',
        'name' => '',
    ];
    $streetNumber = '';
    $route = '';
    foreach ($components as $c) {
        $types = $c['types'] ?? [];
        if (in_array('administrative_area_level_1', $types, true)) {
            $compound['province'] = $c['long_name'] ?? '';
        } elseif (in_array('administrative_area_level_2', $types, true)) {
            $compound['district'] = $c['long_name'] ?? '';
        } elseif (in_array('sublocality_level_1', $types, true) || in_array('administrative_area_level_3', $types, true)) {
            $compound['commune'] = $c['long_name'] ?? '';
        } elseif (in_array('route', $types, true)) {
            $route = $c['long_name'] ?? '';
        } elseif (in_array('street_number', $types, true)) {
            $streetNumber = $c['long_name'] ?? '';
        }
    }
    $compound['name'] = trim("$streetNumber $route");
    return $compound;
}

/**
 * Validates a pipe-separated string of lat,lng points.
 * Returns true if valid, false otherwise.
 */
function validate_coords_list(string $raw, int $maxPoints = 30, int $maxLen = 2000): bool
{
    if (strlen($raw) === 0 || strlen($raw) > $maxLen) {
        return false;
    }
    $points = explode('|', $raw);
    if (count($points) === 0 || count($points) > $maxPoints) {
        return false;
    }
    foreach ($points as $p) {
        $parts = explode(',', trim($p));
        if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
            return false;
        }
        $lat = (float)$parts[0];
        $lng = (float)$parts[1];
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return false;
        }
    }
    return true;
}

switch ($action) {
    case 'reverse_geocode':
        $latStr = trim((string)($_GET['lat'] ?? $_POST['lat'] ?? ''));
        $lngStr = trim((string)($_GET['lng'] ?? $_POST['lng'] ?? ''));
        if (!is_numeric($latStr) || !is_numeric($lngStr)) {
            http_response_code(400);
            echo json_encode(['status' => 'ERROR', 'message' => 'Tọa độ lat, lng không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $lat = (float)$latStr;
        $lng = (float)$lngStr;
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            http_response_code(400);
            echo json_encode(['status' => 'ERROR', 'message' => 'Tọa độ nằm ngoài phạm vi hợp lệ (-90..90, -180..180).'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Google Maps Platform Geocoding API: latlng=${lat},${lng}, key, language=vi, region=vn
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?latlng=' . urlencode("{$lat},{$lng}") . '&key=' . urlencode($googleApiKey) . '&language=vi&region=vn';
        $resp = call_google_maps_api($url);

        // Normalize compound for frontend auto-selection
        if ($resp['status'] === 'OK' && isset($resp['data']['results'][0])) {
            $components = $resp['data']['results'][0]['address_components'] ?? [];
            if (!empty($components) && empty($resp['data']['results'][0]['compound'])) {
                $resp['data']['results'][0]['compound'] = extract_google_compound($components);
            }
        }

        http_response_code($resp['http_code'] ?: 200);
        echo json_encode($resp['data'] ?? ['status' => 'ERROR', 'message' => $resp['message']], JSON_UNESCAPED_UNICODE);
        break;

    case 'geocode':
        $address = trim((string)($_GET['address'] ?? $_POST['address'] ?? ''));
        // Clean control characters
        $address = preg_replace('/[\x00-\x1F\x7F]/u', '', $address);
        $len = mb_strlen($address);
        if ($len < 3 || $len > 300) {
            http_response_code(400);
            echo json_encode(['status' => 'ERROR', 'message' => 'Địa chỉ phải từ 3 đến 300 ký tự.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Google Maps Platform Geocoding API: key, language=vi, region=vn
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&key=' . urlencode($googleApiKey) . '&language=vi&region=vn';
        $resp = call_google_maps_api($url);

        if ($resp['status'] === 'OK' && isset($resp['data']['results'][0])) {
            $components = $resp['data']['results'][0]['address_components'] ?? [];
            if (!empty($components) && empty($resp['data']['results'][0]['compound'])) {
                $resp['data']['results'][0]['compound'] = extract_google_compound($components);
            }
        }

        http_response_code($resp['http_code'] ?: 200);
        echo json_encode($resp['data'] ?? ['status' => 'ERROR', 'message' => $resp['message']], JSON_UNESCAPED_UNICODE);
        break;

    case 'distance_matrix':
        $origins = trim((string)($_GET['origins'] ?? $_POST['origins'] ?? ''));
        $destinations = trim((string)($_GET['destinations'] ?? $_POST['destinations'] ?? ''));
        $rawVehicle = trim((string)($_GET['vehicle'] ?? $_POST['vehicle'] ?? 'bike'));

        if (!validate_coords_list($origins, 5, 200) || !validate_coords_list($destinations, 30, 2000)) {
            http_response_code(400);
            echo json_encode(['status' => 'ERROR', 'message' => 'Tham số origins hoặc destinations không đúng định dạng tọa độ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Google Maps Distance Matrix REST API: các mode hợp lệ là 'driving', 'walking', 'bicycling', 'transit'.
        // 'two_wheeler' chỉ có trong Routes API v2 và sẽ gây lỗi INVALID_REQUEST trên endpoint này.
        // Đối với giao hàng bằng xe máy/ô tô theo đường bộ, mode chuẩn là 'driving'.
        $validModes = ['driving', 'walking', 'bicycling', 'transit'];
        $mode = in_array($rawVehicle, $validModes, true) ? $rawVehicle : 'driving';
        $url = 'https://maps.googleapis.com/maps/api/distancematrix/json?origins=' . urlencode($origins) . '&destinations=' . urlencode($destinations) . '&mode=' . urlencode($mode) . '&key=' . urlencode($googleApiKey) . '&language=vi&region=vn';
        $resp = call_google_maps_api($url);

        http_response_code($resp['http_code'] ?: 200);
        echo json_encode($resp['data'] ?? ['status' => 'ERROR', 'message' => $resp['message']], JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Hành động (action) không được hỗ trợ.'], JSON_UNESCAPED_UNICODE);
        break;
}
