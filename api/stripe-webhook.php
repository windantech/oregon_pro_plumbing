<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_method('POST');
if(!stripe_webhook_ready()) {http_response_code(503);exit;}
$payload=file_get_contents('php://input',false,null,0,1024*1024);
if(!verify_stripe_signature((string)$payload,(string)($_SERVER['HTTP_STRIPE_SIGNATURE']??''),(string)config('STRIPE_WEBHOOK_SECRET'))) {http_response_code(400);exit;}
$event=json_decode((string)$payload,true);
if(!is_array($event)||empty($event['id'])||empty($event['type'])) {http_response_code(400);exit;}
try {
    $db=db();$q=$db->prepare('SELECT event_id FROM stripe_events WHERE event_id=?');
    $q->execute([(string)$event['id']]);if($q->fetch()) {http_response_code(200);exit;}
    $s=$event['data']['object']??[];
    if(in_array($event['type'],['checkout.session.completed','checkout.session.async_payment_succeeded'],true)) {
       sync_payment_session(is_array($s)?$s:[]);
    } elseif (in_array($event['type'],['checkout.session.async_payment_failed','checkout.session.expired'],true)) {
       $db->prepare("UPDATE payments SET status='failed' WHERE session_id=? AND status='pending'")->execute([(string)($s['id']??'')]);
    }
    $db->prepare('INSERT INTO stripe_events (event_id,created_at) VALUES (?,?)')->execute([(string)$event['id'],gmdate('c')]);
    http_response_code(200);
} catch(Throwable $e) {error_log('Stripe webhook failure: '.$e->getMessage());http_response_code(500);}
