<?php
require __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-cache, no-store, must-revalidate');

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

switch ($action) {
    case 'reverse_geocode':
        $lat = trim((string)($_GET['lat'] ?? $_POST['lat'] ?? ''));
        $lng = trim((string)($_GET['lng'] ?? $_POST['lng'] ?? ''));
        if (!is_numeric($lat) || !is_numeric($lng)) {
            http_response_code(400);
            echo json_encode(['status' => 'ERROR', 'message' => 'Tọa độ lat, lng không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $url = 'https://rsapi.goong.io/Geocode?latlng=' . urlencode("{$lat},{$lng}") . '&api_key=' . urlencode($apiKey);
        $resp = call_goong_api($url);
        http_response_code($resp['http_code'] ?: 200);
        echo json_encode($resp['data'] ?? ['status' => 'ERROR', 'message' => $resp['message']], JSON_UNESCAPED_UNICODE);
        break;

    case 'geocode':
        $address = trim((string)($_GET['address'] ?? $_POST['address'] ?? ''));
        if ($address === '') {
            http_response_code(400);
            echo json_encode(['status' => 'ERROR', 'message' => 'Địa chỉ không được để trống.'], JSON_UNESCAPED_UNICODE);
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
        $vehicle = trim((string)($_GET['vehicle'] ?? $_POST['vehicle'] ?? 'bike'));
        if ($origins === '' || $destinations === '') {
            http_response_code(400);
            echo json_encode(['status' => 'ERROR', 'message' => 'Thiếu tham số origins hoặc destinations.'], JSON_UNESCAPED_UNICODE);
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
