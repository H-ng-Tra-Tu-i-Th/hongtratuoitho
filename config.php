<?php
// Main configuration loader. Run setup.php once after uploading to cPanel.
if (!file_exists(__DIR__ . '/config.local.php')) {
    if (basename($_SERVER['PHP_SELF'] ?? '') !== 'setup.php') {
        header('Location: setup.php');
        exit;
    }
    return;
}
require __DIR__ . '/config.local.php';

date_default_timezone_set(APP_TIMEZONE ?? 'Asia/Ho_Chi_Minh');

if (!defined('PAYMENT_HOLD_SECONDS')) {
    define('PAYMENT_HOLD_SECONDS', 900);
}

if (!defined('GOOGLE_MAPS_API_KEY')) {
    define('GOOGLE_MAPS_API_KEY', '');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('drink_order_sid');
    session_start();
}
