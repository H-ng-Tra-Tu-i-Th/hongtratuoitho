<?php
require __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/dispatch.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

try {
    $rawStores = hds_stores(true);
    $stores = array_map(function($s) {
        return [
            'id' => (int)$s['id'],
            'name' => (string)$s['name'],
            'address' => (string)$s['address'],
            'phone' => (string)($s['phone'] ?? ''),
            'lat' => $s['lat'] !== null ? (float)$s['lat'] : null,
            'lng' => $s['lng'] !== null ? (float)$s['lng'] : null,
            'is_active' => (bool)$s['enabled'],
        ];
    }, $rawStores);

    echo json_encode([
        'success' => true,
        'data' => $stores,
        'count' => count($stores),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Không thể tải danh sách chi nhánh: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
