<?php
require __DIR__.'/../lib/group_poll_bootstrap.php';
require_once __DIR__.'/../lib/game_entry.php';
header('Cache-Control: private, no-store');header('Content-Type: application/json; charset=UTF-8');header('X-Robots-Tag: noindex, nofollow');
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);header('Allow: GET');echo json_encode(['ok'=>false]);exit;}
try{echo json_encode(['ok'=>true]+hge_order_state(hge_last_order()),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
catch(Throwable $error){http_response_code(503);echo json_encode(['ok'=>false]);}
