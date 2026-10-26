<?php

/** Checkout drafts and request outcomes stay in the current authenticated session. */
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}
function hcq_context(string $group = ''): string
{
    return $group !== '' ? 'group:' . $group : 'ordinary';
}
function hcq_fields(): array
{
    return ['customer_name' => 100, 'phone' => 50, 'address' => 255, 'payment_method' => 16, 'invoice_required' => 1, 'invoice_company_name' => 200, 'invoice_tax_id' => 50, 'invoice_company_address' => 255, 'invoice_company_representative' => 120, 'invoice_company_phone' => 50, 'invoice_email' => 190, 'order_note' => 1000, 'address_mode' => 5, 'province_code' => 10, 'province_name' => 100, 'ward_code' => 10, 'ward_name' => 100, 'street_address' => 150];
}
function hcq_capture(string $context, array $input): array
{
    $values = [];
    $errors = [];
    foreach (hcq_fields() as $key => $max) {
        $raw = $input[$key] ?? '';
        if (!is_string($raw) && !is_int($raw)) {
            $errors[$key] = 'incomplete_data';
            $raw = '';
        }
        $value = trim((string)$raw);
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        if ($length > $max) {
            $errors[$key] = 'incomplete_data';
            $value = function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
        }
        $values[$key] = $value;
    }
    foreach (['customer_name', 'phone', 'address'] as $key) if ($values[$key] === '') $errors[$key] = 'incomplete_data';
    if (!in_array($values['payment_method'], ['transfer', 'cash', 'split'], true)) $errors['payment_method'] = 'incomplete_data';
    if (!in_array($values['invoice_required'], ['0', '1'], true)) $errors['invoice_required'] = 'incomplete_data';
    if ($values['invoice_required'] === '1') {
        foreach (['invoice_company_name', 'invoice_tax_id', 'invoice_company_address', 'invoice_company_phone', 'invoice_email'] as $key) if ($values[$key] === '') $errors[$key] = 'invoice_fields_required';
        if ($values['invoice_email'] !== '' && !filter_var($values['invoice_email'], FILTER_VALIDATE_EMAIL)) $errors['invoice_email'] = 'invalid_email';
    }
    $drafts = $_SESSION['checkout_drafts'] ?? [];
    if (!is_array($drafts)) $drafts = [];
    unset($drafts[$context]);
    $drafts[$context] = ['values' => $values, 'errors' => $errors, 'saved_at' => time(), 'member_id' => (int)($_SESSION['member_id'] ?? 0)];
    $_SESSION['checkout_drafts'] = array_slice($drafts, -20, null, true);
    return ['values' => $values, 'errors' => $errors];
}
function hcq_draft(string $context): array
{
    $draft = $_SESSION['checkout_drafts'][$context] ?? [];
    if (!is_array($draft) || ($draft['saved_at'] ?? 0) < time() - 7200 || (int)($draft['member_id'] ?? -1) !== (int)($_SESSION['member_id'] ?? 0)) return ['values' => [], 'errors' => []];
    return ['values' => is_array($draft['values'] ?? null) ? $draft['values'] : [], 'errors' => is_array($draft['errors'] ?? null) ? $draft['errors'] : []];
}
function hcq_back(string $group = ''): string
{
    return base_url('checkout.php' . ($group !== '' ? '?group=' . rawurlencode($group) : ''));
}
function hcq_fail(string $context, string $group, string $message): never
{
    $_SESSION['checkout_errors'][$context] = $message;
    header('Location: ' . hcq_back($group), true, 303);
    exit;
}
function hcq_request(string $context, string $signature): string
{
    $requests = $_SESSION['checkout_requests'] ?? [];
    if (!is_array($requests)) $requests = [];
    // An unresolved submission always takes precedence, including after quote changes or long absences.
    if ($unknown = hcq_unknown_token($context)) return $unknown;
    foreach ($requests as $token => $r) if (is_array($r) && ($r['context'] ?? '') === $context && ($r['signature'] ?? '') === $signature && (int)($r['member_id'] ?? -1) === (int)($_SESSION['member_id'] ?? 0) && ($r['created_at'] ?? 0) > time() - 7200 && ($r['state'] ?? '') === 'open') return (string)$token;
    $token = bin2hex(random_bytes(24));
    $requests[$token] = ['context' => $context, 'signature' => $signature, 'member_id' => (int)($_SESSION['member_id'] ?? 0), 'created_at' => time(), 'state' => 'open'];
    $_SESSION['checkout_requests'] = hcq_trim_requests($requests);
    return $token;
}
function hcq_unknown_token(string $context): ?string
{
    foreach ($_SESSION['checkout_requests'] ?? [] as $token => $r) if (is_array($r) && ($r['context'] ?? '') === $context && (int)($r['member_id'] ?? -1) === (int)($_SESSION['member_id'] ?? 0) && ($r['state'] ?? '') === 'unknown') return (string)$token;
    return null;
}
function hcq_trim_requests(array $requests): array
{
    $unknown = [];
    $open = [];
    $saved = [];
    foreach ($requests as $token => $request) {
        if (!is_array($request)) continue;
        switch ($request['state'] ?? '') {
            case 'unknown':
                $unknown[] = $token;
                break;
            case 'open':
                $open[] = $token;
                break;
            case 'saved':
                $saved[] = $token;
                break;
        }
    }
    // Ordinary history is bounded independently. Unresolved references cannot be evicted by it.
    $keep = array_fill_keys(array_merge($unknown, array_slice($open, -20), array_slice($saved, -20)), true);
    return array_intersect_key($requests, $keep);
}
function hcq_lookup(mixed $token, string $context): ?array
{
    if (!is_string($token) || !preg_match('/^[a-f0-9]{48}$/D', $token)) return null;
    $r = $_SESSION['checkout_requests'][$token] ?? null;
    if (is_array($r) && ($r['state'] ?? '') === 'open' && ($r['created_at'] ?? 0) <= time() - 7200) return null;
    return is_array($r) && ($r['context'] ?? '') === $context && (int)($r['member_id'] ?? -1) === (int)($_SESSION['member_id'] ?? 0) ? $r : null;
}
function hcq_saved(string $token, string $number, string $state = 'saved'): void
{
    if (isset($_SESSION['checkout_requests'][$token])) {
        $request = $_SESSION['checkout_requests'][$token];
        unset($_SESSION['checkout_requests'][$token]);
        $request['state'] = $state;
        $request['order_no'] = $number;
        $_SESSION['checkout_requests'][$token] = $request;
        $_SESSION['checkout_requests'] = hcq_trim_requests($_SESSION['checkout_requests']);
    }
}
function hcq_outcome(mixed $token, string $context): ?array
{
    $request = hcq_lookup($token, $context);
    return $request && in_array($request['state'] ?? '', ['saved', 'unknown'], true) ? $request : null;
}
function hcq_existing_order(string $token, string $context): ?array
{
    $request = hcq_lookup($token, $context);
    if (!$request) return null;
    $order = hyg_one('SELECT * FROM orders WHERE checkout_request_hash=?', [hash('sha256', $token)]);
    if (!$order) return null;
    if ((int)$request['member_id'] === 0 && empty($order['member_id']) && empty($order['group_room_id']) && $context === 'ordinary') htp_remember_customer_order($order['order_no']);
    if (!htp_customer_allowed($order)) htp_fail('forbidden');
    return $order;
}
function hcq_field_error(array $errors, string $name): string
{
    return isset($errors[$name]) ? '<small class="checkout-field-error" id="error-' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">' . e(t($errors[$name])) . '</small>' : '';
}
function hcq_field_attrs(array $errors, string $name): string
{
    return isset($errors[$name]) ? ' aria-invalid="true" aria-describedby="error-' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"' : '';
}
const HCQ_LOCATION_TOKEN_TTL = 7200;
/** Issued only by checkout.php: api/location.php (paid Google calls) answers only sessions that opened checkout. */
function hcq_location_token(): string
{
    $saved = $_SESSION['hcq_location_token'] ?? null;
    if (is_array($saved) && is_string($saved['token'] ?? null) && time() - (int)($saved['issued_at'] ?? 0) < HCQ_LOCATION_TOKEN_TTL - 600) return $saved['token'];
    $token = bin2hex(random_bytes(24));
    $_SESSION['hcq_location_token'] = ['token' => $token, 'issued_at' => time()];
    return $token;
}
function hcq_location_token_valid(mixed $given): bool
{
    $saved = $_SESSION['hcq_location_token'] ?? null;
    return is_string($given) && $given !== '' && is_array($saved) && is_string($saved['token'] ?? null)
        && time() - (int)($saved['issued_at'] ?? 0) < HCQ_LOCATION_TOKEN_TTL && hash_equals($saved['token'], $given);
}
/** Per-IP counter kept outside the session, so dropping the cookie does not reset it. False once over $limit in the window. */
function hcq_ip_rate_allow(string $bucket, int $limit, int $window = 60): bool
{
    $dir = sys_get_temp_dir() . '/hongtra-rate';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) return true;
    $file = $dir . '/' . $bucket . '-' . substr(hash('sha256', client_ip()), 0, 24) . '.json';
    $handle = @fopen($file, 'c+');
    if ($handle === false) return true;
    try {
        if (!flock($handle, LOCK_EX)) return true;
        $now = time();
        $data = json_decode((string)stream_get_contents($handle), true);
        if (!is_array($data) || !is_int($data['start'] ?? null) || !is_int($data['count'] ?? null) || $now - $data['start'] >= $window || $data['start'] > $now) $data = ['start' => $now, 'count' => 0];
        $data['count']++;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($data));
        fflush($handle);
        return $data['count'] <= $limit;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
