<?php
require __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check SePay Webhook Authorization header
$allHeaders = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? ($allHeaders['Authorization'] ?? $allHeaders['authorization'] ?? '')
    ?? ($_SERVER['HTTP_X_SEPAY_API_KEY'] ?? '');

if (!sepay_verify_webhook_auth($authHeader)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Invalid SePay API Key or Token.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Read raw body
$rawInput = file_get_contents('php://input');
if (!is_string($rawInput) || trim($rawInput) === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Empty request body.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validate basic SePay fields
$transferType = strtolower(trim((string) ($payload['transferType'] ?? '')));
if ($transferType !== 'in') {
    // Acknowledge non-incoming transaction (e.g. fee, outgoing) without error
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Ignored outgoing/non-incoming transfer type: ' . $transferType], JSON_UNESCAPED_UNICODE);
    exit;
}

$content = (string) ($payload['content'] ?? '');
$code = (string) ($payload['code'] ?? '');
$parsed = sepay_parse_description($content, $code);

if (!$parsed) {
    // Not matching HYGM or HYG pattern
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'message' => 'No recognized HYGM or HYG payment reference found in content.',
        'content' => $content
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($parsed['type'] === 'member') {
        $result = sepay_process_member_payment($payload, (int) $parsed['id']);
    } else {
        $result = sepay_process_order_payment($payload, (string) $parsed['order_no']);
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $result['message'] ?? 'Payment processed successfully.',
        'data' => $result
    ], JSON_UNESCAPED_UNICODE);
} catch (SepayPaymentError $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[sepay_webhook] Unexpected error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error processing payment.'
    ], JSON_UNESCAPED_UNICODE);
}
