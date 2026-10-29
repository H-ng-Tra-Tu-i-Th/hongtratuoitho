<?php
require __DIR__.'/../lib/bootstrap.php';

// The customer finished adding drinks: checkout reopens the Mini Game session with the new drink count.
unset($_SESSION['hcgs_adding']);
if(!empty($_SESSION['cart'])){
    $_SESSION['hcgs_allow_open']=true;
    redirect(base_url('checkout.php'));
}
redirect(base_url('cart.php'));
