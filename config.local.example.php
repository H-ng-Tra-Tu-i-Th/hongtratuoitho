<?php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'huiyitea_shop');
define('DB_USER', 'root');
define('DB_PASS', 'rootpassword');
define('APP_BASE_URL', 'http://localhost:8000');
define('APP_TIMEZONE', 'Asia/Ho_Chi_Minh');

// Local test override only: change to 60 for a one-minute payment hold.
define('PAYMENT_HOLD_SECONDS', 900);
// SePay VietQR Payment Configuration (sepay.vn)
define('SEPAY_API_KEY', 'YOUR_SEPAY_API_KEY');
define('SEPAY_WEBHOOK_TOKEN', 'YOUR_SEPAY_WEBHOOK_TOKEN');

// Zalo login (developers.zalo.me). Leave empty to hide the button.
define('ZALO_APP_ID', '');
define('ZALO_APP_SECRET', '');
// Optional: defaults to APP_BASE_URL/member/zalo.php; must match a Callback URL in the Zalo app.
define('ZALO_REDIRECT_URI', '');
// Zalo Mini App login: Secret Key of the Mini App (empty = use ZALO_APP_SECRET).
define('ZALO_MINIAPP_SECRET', '');

// Google Maps Platform API key. Enable Geocoding API and Routes API.
define('GOOGLE_MAPS_API_KEY', 'YOUR_GOOGLE_MAPS_API_KEY');
