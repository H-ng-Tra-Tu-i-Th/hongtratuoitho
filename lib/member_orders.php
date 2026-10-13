<?php
/** Local orders belong to the account that submitted them, never a phone guess. */
function hym_orders_schema(): void {
    static $done = false;
    if ($done) return;
    $pdo = db();
    if (!$pdo->query("SHOW COLUMNS FROM orders LIKE 'member_id'")->fetch()) {
        try {
            $pdo->exec('ALTER TABLE orders ADD COLUMN member_id BIGINT UNSIGNED NULL');
        } catch (PDOException $error) {
            if (!$pdo->query("SHOW COLUMNS FROM orders LIKE 'member_id'")->fetch()) throw $error;
        }
    }
    if (!$pdo->query("SHOW INDEX FROM orders WHERE Key_name='idx_orders_member'")->fetch()) {
        try {
            $pdo->exec('ALTER TABLE orders ADD KEY idx_orders_member(member_id,created_at)');
        } catch (PDOException $error) {
            if (!$pdo->query("SHOW INDEX FROM orders WHERE Key_name='idx_orders_member'")->fetch()) throw $error;
        }
    }
    $done = true;
}

function hym_platform_orders(int $memberId): array {
    $member = hym_current_member();
    if (!$member || (int)$member['id'] !== $memberId) return [];
    $query = db()->prepare('SELECT id,order_no,customer_name,phone,address,total_amount,payment_method,status,created_at FROM orders WHERE member_id=? ORDER BY id DESC LIMIT 50');
    $query->execute([$memberId]);
    return $query->fetchAll();
}

function hym_checkout_defaults(?array $member): array {
    if (!$member) return ['name'=>'', 'phone'=>'', 'address'=>''];
    $name = (string)$member['display_name'];
    if ($name === '' && !empty($member['phone_verified_at'])) {
        try {
            $profile = hym_profile((int)$member['id']);
            $name = (string)($profile['name'] ?? '');
        } catch (Throwable $error) {
            // An unavailable upstream cache must not prevent local checkout.
        }
    }
    return ['name'=>$name, 'phone'=>(string)$member['phone'], 'address'=>(string)($member['delivery_address'] ?? '')];
}
