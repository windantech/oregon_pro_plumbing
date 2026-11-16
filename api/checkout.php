<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_method('POST');$d=request_json();
$code=strtoupper(clean($d['code']??'',40));$token=strtolower(clean($d['paymentToken']??'',80));
$kind=clean($d['kind']??'',20);
if(!in_array($kind,['call','full'],true)) fail('Invalid checkout request.');
if($token==='') {
    if(!preg_match('/^OPP-[A-F0-9]{24}$/',$code)) fail('Invalid checkout request.');
} elseif(!preg_match('/^[a-f0-9]{64}$/D',$token)) fail('Invalid checkout request.');
if(!configured_payments()) fail('Online payment is currently unavailable. Please contact us.',503);
try {
    $booking=$token!==''?booking_from_payment_token($token):get_booking($code);
    if(!$booking) fail('Booking not found or payment link expired.',404);
    $code=$booking['code'];
    $total=$kind==='call'?fee_cents():$booking['estimate_cents'];
    if($total===null) fail('An on-site estimate is required before paying the full amount.',409);
    // A service-call deposit is only the outstanding portion of that fee, not the full quote.
    $due=max(0,(int)$total-(int)$booking['paid_cents']);
    if($due<50) fail('This amount has already been paid or is not payable online.',409);
    $pending=db()->prepare("SELECT * FROM payments WHERE booking_code=? AND status='pending' AND expires_at>? ORDER BY id DESC LIMIT 1");
    $pending->execute([$code,time()+30]);
    if($p=$pending->fetch()) {
        if($p['kind']===$kind&&(int)$p['amount_cents']===$due&&str_starts_with((string)$p['checkout_url'],'https://checkout.stripe.com/')) json_response(['url'=>$p['checkout_url']]);
        fail('Another payment is in progress. Finish that checkout or try again when it expires.',409);
    }
    $sessionParams=[
        'mode'=>'payment','payment_method_types'=>['card'],
        'line_items'=>[['price_data'=>[
            'currency'=>'usd','unit_amount'=>$due,
            'product_data'=>['name'=>$kind==='call'?'Plumbing service call':'Plumbing service estimate balance',
              'description'=>PRICE_DEFAULTS[$booking['service_id']]['name'].' · '.$booking['city'].', Oregon']
        ],'quantity'=>1]],
        'client_reference_id'=>$code,
        'metadata'=>['booking_code'=>$code,'payment_kind'=>$kind],
        'success_url'=>rtrim((string)config('APP_URL'),'/').'/?payment=return&session_id={CHECKOUT_SESSION_ID}#jobs',
        'cancel_url'=>rtrim((string)config('APP_URL'),'/').'/?payment=cancel#booking',
        'expires_at'=>time()+3600,
    ];
    if($booking['email']!=='') $sessionParams['customer_email']=$booking['email'];
    $session=stripe_request('POST','checkout/sessions',$sessionParams,'opp-'.hash('sha256',$code.'-'.$kind.'-'.$due.'-'.intdiv(time(),3600)));
    $id=(string)($session['id']??'');$url=(string)($session['url']??'');
    if(!preg_match('/^cs_(test_|live_)[A-Za-z0-9_]+$/',$id)||!str_starts_with($url,'https://checkout.stripe.com/')) throw new RuntimeException('Invalid Stripe session response.');
    db()->prepare('INSERT INTO payments (session_id,booking_code,kind,amount_cents,checkout_url,expires_at,created_at) VALUES (?,?,?,?,?,?,?)')
      ->execute([$id,$code,$kind,$due,$url,(int)($session['expires_at']??(time()+3600)),gmdate('c')]);
    json_response(['url'=>$url]);
} catch(Throwable $e) {error_log('Stripe checkout failure: '.$e->getMessage());fail('Secure checkout is unavailable. Please try again.',503);}
