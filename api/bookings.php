<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/smtp.php';
require_once __DIR__.'/../includes/whatsapp.php';
if (!installed()) fail('Online requests are currently unavailable. Please contact us.',503);
try {
    if (($_SERVER['REQUEST_METHOD']??'')==='GET') {
        $token=strtolower(trim((string)($_GET['payment_token']??'')));
        if ($token!=='') {
            $booking=booking_from_payment_token($token);
            if (!$booking) fail('Saved request not found or payment link expired.',404);
            json_response(['booking'=>public_booking($booking),'paymentsAvailable'=>configured_payments()]);
        }
        $code=strtoupper(trim((string)($_GET['code']??'')));
        if (!preg_match('/^OPP-[A-F0-9]{24}$/',$code)) fail('Booking not found.',404);
        $booking=get_booking($code);if (!$booking) fail('Booking not found.',404);
        json_response(['booking'=>public_booking($booking),'paymentsAvailable'=>configured_payments()]);
    }
    require_method('POST');
    $d=request_json();
    if (!empty($d['website'])) json_response(['ok'=>true]);
    $recent=array_values(array_filter($_SESSION['booking_times']??[],fn($t)=>(int)$t>time()-3600));
    if(count($recent)>=6) fail('Too many requests. Please contact us directly.',429);
    $serviceId=clean($d['serviceId']??'',30);
    if(!array_key_exists($serviceId,PRICE_DEFAULTS)) fail('Choose a service.');
    $city=clean($d['city']??'',80);$street=clean($d['address']??'',200);
    $zip=clean($d['zip']??'',10);$first=clean($d['firstName']??'',80);
    $last=clean($d['lastName']??'',80);$phone=clean($d['phone']??'',40);
    $email=clean($d['email']??'',180);$details=clean($d['details']??'',1200);
    $urgency=clean($d['urgency']??'',70);
    $whatsappOptIn=($d['whatsappOptIn']??false)===true;
    if($city===''||$street===''||$first===''||$last===''||!preg_match('/^97\d{3}$/',$zip)
       ||!preg_match('/^[0-9+() .-]{7,40}$/',$phone)||!filter_var($email,FILTER_VALIDATE_EMAIL)) {
        fail('Please check your contact details and Oregon address.');
    }
    if(!in_array($urgency,['As soon as possible','Today, if possible','Tomorrow','Flexible / this week'],true)) fail('Invalid timing selection.');
    $labor=service_price($serviceId);$estimate=$labor===null?null:fee_cents()+$labor;
    $code='OPP-'.strtoupper(bin2hex(random_bytes(12)));$now=gmdate('c');
    // Apply this schema addition automatically on existing MySQL/SQLite installs.
    ensure_payment_token_table();
    if($whatsappOptIn) opp_wa_optin_table();
    $pdo=db();$pdo->beginTransaction();
    try {
        $q=$pdo->prepare('INSERT INTO bookings (code,service_id,city,street,zip,details,urgency,first_name,last_name,phone,email,estimate_cents,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute([$code,$serviceId,$city,$street,$zip,$details,$urgency,$first,$last,$phone,$email,$estimate,$now,$now]);
        $paymentToken=new_payment_token($code);
        if($whatsappOptIn) opp_wa_record_optin($code);
        $pdo->commit();
    } catch(Throwable $storageError) {
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $storageError;
    }
    $_SESSION['booking_times']=array_merge($recent,[time()]);
    // Booking already exists in the database. SMTP delivery can never make the
    // customer believe the booking was rejected (or cause duplicate submissions).
    $savedBooking=get_booking($code) ?: [];
    try { opp_notify_new_booking($savedBooking); }
    catch(Throwable $notificationError) {
        error_log('Booking '.$code.' saved; business SMTP notification failed: '.$notificationError->getMessage());
    }
    try { opp_notify_customer_booking($savedBooking); }
    catch(Throwable $notificationError) {
        error_log('Booking '.$code.' saved; customer SMTP notification failed: '.$notificationError->getMessage());
    }
    try { opp_wa_notify_new_booking($savedBooking); }
    catch(Throwable $notificationError) {
        error_log('Booking '.$code.' saved; business WhatsApp alert failed: '.$notificationError->getMessage());
    }
    try { opp_wa_notify_customer_booking($savedBooking); }
    catch(Throwable $notificationError) {
        error_log('Booking '.$code.' saved; customer WhatsApp message failed: '.$notificationError->getMessage());
    }
    json_response(['saved'=>true,'booking'=>public_booking(get_booking($code)),'paymentToken'=>$paymentToken,'paymentsAvailable'=>configured_payments()],201);
} catch(PDOException $e) {error_log('Booking database failure: '.$e->getMessage());fail('Unable to save your request. Please contact us directly.',503);}
  catch(Throwable $e) {error_log('Booking failure: '.$e->getMessage());fail('Unable to save your request. Please try again.',503);}
