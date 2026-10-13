<?php
declare(strict_types=1); define('KYUC_APP',true); require __DIR__.'/app/core.php';
if(!is_file(__DIR__.'/app/config.php')) {header('Location: install.php');exit;}
headers_safe(); session_begin();
?><!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#b81e27"><meta name="description" content="Ký Ức 10 Ly — Lật thẻ giống nhau, ghép đủ 6 cặp để nhận quà. Trò chơi độc lập của Hồng Trà Tuổi Thơ."><title>Ký Ức 10 Ly · Hồng Trà Tuổi Thơ</title><link rel="icon" href="assets/favicon.png"><link rel="apple-touch-icon" href="assets/favicon.png"><link rel="stylesheet" href="assets/style.css?v=1.1.0"><script defer src="assets/ui.js?v=1.1.0"></script><script defer src="assets/game.js?v=1.1.0"></script></head>
<body><div id="app"><div class="loading-screen"><img src="assets/logo.png" alt="Hồng Trà Tuổi Thơ"><span class="loader"></span></div></div><dialog id="modal" aria-modal="true"></dialog><div id="toast" class="toast" role="status" aria-live="polite"></div><div id="confetti" aria-hidden="true"></div><noscript><p>Vui lòng bật JavaScript để chơi. 請啟用 JavaScript。</p></noscript></body></html>
