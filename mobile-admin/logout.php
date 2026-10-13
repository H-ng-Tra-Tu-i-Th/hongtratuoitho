<?php
require __DIR__.'/_common.php';
haa_clear_session(); session_regenerate_id(true);
unset($_SESSION['customer_payment_orders'],$_SESSION['last_order_no'],$_SESSION['checkout_drafts'],$_SESSION['checkout_requests'],$_SESSION['checkout_errors'],$_SESSION['transfer_payment_flash']);
redirect(ma_url('login.php'));
