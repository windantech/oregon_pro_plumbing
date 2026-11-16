<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_method('GET');
$id=trim((string)($_GET['session_id']??''));
if(!preg_match('/^cs_(test_|live_)[A-Za-z0-9_]{8,}$/',$id)) fail('Invalid checkout reference.');
try {
    $q=db()->prepare('SELECT booking_code FROM payments WHERE session_id=?');$q->execute([$id]);
    $p=$q->fetch();if(!$p) fail('Payment reference not found.',404);
    if(stripe_api_ready()) {
        $session=stripe_request('GET','checkout/sessions/'.rawurlencode($id));
        sync_payment_session($session);
    }
    json_response(['booking'=>public_booking(get_booking($p['booking_code']))]);
} catch(Throwable $e) {error_log('Checkout verification failure: '.$e->getMessage());fail('We are confirming your payment. Check job status shortly.',503);}
