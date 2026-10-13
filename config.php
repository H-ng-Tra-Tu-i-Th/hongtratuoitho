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

date_default_timezone_set(APP_TIMEZONE ?? 'Asia/Taipei');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('drink_order_sid');
    session_start();
}
