<?php
require __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-cache, no-store, must-revalidate');

// Keep the API key on the server and rate-limit requests before proxying them.
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
session_write_close();
if ($rateData['count'] > 40) {
    http_response_code(429);
    echo json_encode([
        'status' => 'ERROR',
        'message' => 'Bạn đã gửi quá nhiều yêu cầu định vị. Vui lòng chờ 1 phút.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$apiKey = defined('GOOGLE_MAPS_API_KEY') ? trim((string)GOOGLE_MAPS_API_KEY) : '';
if ($apiKey === '' && function_exists('setting')) {
    $apiKey = trim((string)setting('google_maps_api_key', ''));
}
if ($apiKey === '') {
    http_response_code(503);
    echo json_encode([
        'status' => 'ERROR',
        'message' => 'Chưa cấu hình GOOGLE_MAPS_API_KEY trên máy chủ.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

function call_google_api(string $url, ?array $payload = null, array $headers = []): array
{
    $ch = curl_init($url);
    $requestHeaders = array_merge(['Accept: application/json'], $headers);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => $requestHeaders,
    ];
    if ($payload !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
    }
    curl_setopt_array($ch, $options);
    $res = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($res === false) {
        return ['status' => 'ERROR', 'http_code' => 502, 'message' => 'Lỗi kết nối tới Google Maps API: ' . $err];
    }
    try {
        $json = json_decode($res, true, 512, JSON_THROW_ON_ERROR);
        return ['status' => 'OK', 'http_code' => $httpCode ?: 200, 'data' => $json];
    } catch (JsonException $e) {
        return ['status' => 'ERROR', 'http_code' => 502, 'message' => 'Phản hồi Google Maps API không hợp lệ.'];
    }
}

function google_error(array $resp): void
{
    http_response_code($resp['http_code'] ?: 502);
    echo json_encode([
        'status' => 'ERROR',
        'message' => $resp['message'] ?? 'Google Maps API request failed.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function validate_coords_list(string $raw, int $maxPoints = 30, int $maxLen = 2000): bool
{
    if ($raw === '' || strlen($raw) > $maxLen) {
        return false;
    }
    $points = explode('|', $raw);
    if (count($points) === 0 || count($points) > $maxPoints) {
        return false;
    }
    foreach ($points as $point) {
        $parts = explode(',', trim($point));
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

function parse_coordinate(string $value): array
{
    [$lat, $lng] = array_map('floatval', explode(',', trim($value)));
    return [
        'waypoint' => [
            'location' => [
                'latLng' => ['latitude' => $lat, 'longitude' => $lng],
            ],
        ],
    ];
}

function duration_seconds(string $duration): int
{
    return (int)round((float)rtrim($duration, 's'));
}

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
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?latlng='
            . urlencode("{$lat},{$lng}") . '&language=vi&key=' . urlencode($apiKey);
        $resp = call_google_api($url);
        if ($resp['status'] !== 'OK') {
            google_error($resp);
        }

        if (isset($resp['data']['results'][0])) {
            $components = $resp['data']['results'][0]['address_components'] ?? [];
            if (!empty($components) && empty($resp['data']['results'][0]['compound'])) {
                $resp['data']['results'][0]['compound'] = extract_google_compound($components);
            }
        }

        http_response_code($resp['http_code']);
        echo json_encode($resp['data'], JSON_UNESCAPED_UNICODE);
        break;

    case 'geocode':
        $address = preg_replace('/[\x00-\x1F\x7F]/u', '', trim((string)($_GET['address'] ?? $_POST['address'] ?? '')));
        $len = mb_strlen($address);
        if ($len < 3 || $len > 300) {
            http_response_code(400);
            echo json_encode(['status' => 'ERROR', 'message' => 'Địa chỉ phải từ 3 đến 300 ký tự.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?address='
            . urlencode($address) . '&language=vi&region=vn&key=' . urlencode($apiKey);
        $resp = call_google_api($url);
        if ($resp['status'] !== 'OK') {
            google_error($resp);
        }

        if (isset($resp['data']['results'][0])) {
            $components = $resp['data']['results'][0]['address_components'] ?? [];
            if (!empty($components) && empty($resp['data']['results'][0]['compound'])) {
                $resp['data']['results'][0]['compound'] = extract_google_compound($components);
            }
        }

        http_response_code($resp['http_code']);
        echo json_encode($resp['data'], JSON_UNESCAPED_UNICODE);
        break;

    case 'distance_matrix':
        $origins = trim((string)($_GET['origins'] ?? $_POST['origins'] ?? ''));
        $destinations = trim((string)($_GET['destinations'] ?? $_POST['destinations'] ?? ''));
        if (!validate_coords_list($origins, 1, 50) || !validate_coords_list($destinations, 30, 2000)) {
            http_response_code(400);
            echo json_encode(['status' => 'ERROR', 'message' => 'Tham số origins hoặc destinations không đúng định dạng tọa độ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $destinationPoints = explode('|', $destinations);
        $payload = [
            'origins' => array_map('parse_coordinate', [$origins]),
            'destinations' => array_map('parse_coordinate', $destinationPoints),
            'travelMode' => 'TWO_WHEELER',
            'languageCode' => 'vi-VN',
            'units' => 'METRIC',
        ];
        $url = 'https://routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix?key=' . urlencode($apiKey);
        $resp = call_google_api($url, $payload, [
            'X-Goog-FieldMask: originIndex,destinationIndex,status,condition,distanceMeters,duration',
        ]);
        if ($resp['status'] !== 'OK') {
            google_error($resp);
        }

        $elements = array_fill(0, count($destinationPoints), [
            'status' => 'ERROR',
            'distance' => null,
            'duration' => null,
        ]);
        foreach (($resp['data'] ?? []) as $route) {
            $index = (int)($route['destinationIndex'] ?? -1);
            if ($index < 0 || $index >= count($elements)) {
                continue;
            }
            $ok = (($route['condition'] ?? '') === 'ROUTE_EXISTS');
            $elements[$index] = [
                'status' => $ok ? 'OK' : ($route['condition'] ?? 'ERROR'),
                'distance' => $ok ? [
                    'text' => number_format((float)($route['distanceMeters'] ?? 0) / 1000, 1, ',', '.') . ' km',
                    'value' => (int)($route['distanceMeters'] ?? 0),
                ] : null,
                'duration' => $ok ? [
                    'text' => duration_seconds((string)($route['duration'] ?? '0s')) . ' giây',
                    'value' => duration_seconds((string)($route['duration'] ?? '0s')),
                ] : null,
            ];
        }
        echo json_encode(['rows' => [['elements' => $elements]], 'status' => 'OK'], JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'ERROR', 'message' => 'Hành động (action) không được hỗ trợ.'], JSON_UNESCAPED_UNICODE);
        break;
}
