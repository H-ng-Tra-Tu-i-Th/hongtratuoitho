<?php
require __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/minigame.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') hymg_fail('method_not_allowed', 405);
    if (str_contains(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
        $raw = file_get_contents('php://input', false, null, 0, 16385);
        if ($raw === false || strlen($raw) > 16384) hymg_fail('invalid');
        try {
            $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            hymg_fail('invalid');
        }
        if (!is_array($data)) hymg_fail('invalid');
    } else $data = $_POST;
    $expected = $_SESSION['_csrf'] ?? null;
    $given = $data['csrf'] ?? null;
    if (!is_string($expected) || $expected === '' || !is_string($given) || !hash_equals($expected, $given)) hymg_fail('csrf_expired', 419);
    $action = defined('HYMG_LEGACY_FINISH') ? 'finish' : ($data['action'] ?? $data['mode'] ?? '');
    if (!is_string($action)) hymg_fail('invalid');
    if ($action === 'flip') hymg_matching_throttle();
    // Playing counts as returning to the checkout session.
    try {
        hcgs_forget_old_board(hcgs_current(true));
    } catch (Throwable $ignored) {
    }
    $result = hymg_request($action, $data);
    try {
        $result['checkout_session'] = hcgs_rewards();
    } catch (Throwable $ignored) {
        $result['checkout_session'] = null;
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (HymgException $error) {
    http_response_code($error->httpStatus);
    echo json_encode(['ok' => false, 'error' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('[minigame] Request failed: ' . get_class($error));
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'unavailable']);
}
