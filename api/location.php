<?php
require __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-cache, no-store, must-revalidate');

// Simple session-based rate limiting to prevent abuse/proxy attacks on Goong API
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

$apiKey = defined('GOONG_API_KEY') ? trim((string)GOONG_API_KEY) : '';
if ($apiKey === '' && function_exists('setting')) {
    $apiKey = trim((string)setting('goong_api_key', ''));
}

if ($apiKey === '') {
    http_response_code(503);
    echo json_encode([
        'status' => 'ERROR',
        'message' => 'Chưa cấu hình GOONG_API_KEY trên máy chủ.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

function call_goong_api(string $url): array
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
        return ['status' => 'ERROR', 'http_code' => 500, 'message' => 'Lỗi kết nối tới Goong: ' . $err];
    }
    try {
        $json = json_decode($res, true, 512, JSON_THROW_ON_ERROR);
        return ['status' => 'OK', 'http_code' => $httpCode ?: 200, 'data' => $json];
    } catch (JsonException $e) {
        return ['status' => 'ERROR', 'http_code' => 502, 'message' => 'Phản hồi Goong không hợp lệ.'];
    }
}

/**
 * Validates a pipe-separated string of lat,lng points.
 * Returns true if valid, false otherwise.
 */
function validate_coords_list(string $raw, int $maxPoints = 5): bool
{
    if (strlen($raw) === 0 || strlen($raw) > 300) {
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
        $url = 'https://rsapi.goong.io/Geocode?latlng=' . urlencode("{$lat},{$lng}") . '&api_key=' . urlencode($apiKey);
        $resp = call_goong_api($url);
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
        $url = 'https://rsapi.goong.io/geocode?address=' . urlencode($address) . '&api_key=' . urlencode($apiKey);
        $resp = call_goong_api($url);
        http_response_code($resp['http_code'] ?: 200);
        echo json_encode($resp['data'] ?? ['status' => 'ERROR', 'message' => $resp['message']], JSON_UNESCAPED_UNICODE);
        break;

    case 'distance_matrix':
        $origins = trim((string)($_GET['origins'] ?? $_POST['origins'] ?? ''));
        $destinations = trim((string)($_GET['destinations'] ?? $_POST['destinations'] ?? ''));
        $vehicle = 'bike';

        if (!validate_coords_list($origins, 5) || !validate_coords_list($destinations, 5)) {
            http_response_code(400);
            echo json_encode(['status' => 'ERROR', 'message' => 'Tham số origins hoặc destinations không đúng định dạng tọa độ (tối đa 5 điểm, dạng lat,lng).'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $url = 'https://rsapi.goong.io/DistanceMatrix?origins=' . urlencode($origins) . '&destinations=' . urlencode($destinations) . '&vehicle=' . urlencode($vehicle) . '&api_key=' . urlencode($apiKey);
        $resp = call_goong_api($url);
        http_response_code($resp['http_code'] ?: 200);
        echo json_encode($resp['data'] ?? ['status' => 'ERROR', 'message' => $resp['message']], JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Hành động (action) không được hỗ trợ.'], JSON_UNESCAPED_UNICODE);
        break;
}
