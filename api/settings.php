<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_method('GET');
if (!installed()) fail('Booking system is being prepared. Please contact us.',503);
$prices=[];foreach(PRICE_DEFAULTS as $id=>$info) {$price=service_price($id);$prices[$id]=$price===null?null:$price/100;}
json_response(['paymentsAvailable'=>configured_payments(),'callFee'=>fee_cents()/100,'servicePrices'=>$prices]);
