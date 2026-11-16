<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/smtp.php';
require_once __DIR__.'/../includes/whatsapp.php';

header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Authenticated admin-only endpoint. No booking stage is changed via GET.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    json_response(['error'=>'Method not allowed.'],405);
}
if (!installed() || empty($_SESSION['opp_admin_auth'])) {
    json_response(['error'=>'Your administrator session has expired. Please sign in again.'],401);
}
$origin=(string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin!=='') {
    $host=strtolower((string)(parse_url($origin,PHP_URL_HOST)??''));
    $requestHost=strtolower((string)($_SERVER['HTTP_HOST']??''));
    $requestHost=preg_replace('/:\d+$/','',$requestHost) ?: $requestHost;
    if ($host==='' || !hash_equals($requestHost,$host)) {
        json_response(['error'=>'Request origin not accepted.'],403);
    }
}
$type=strtolower(trim((string)($_SERVER['CONTENT_TYPE']??'')));
if (!str_starts_with($type,'application/json')) {
    json_response(['error'=>'Expected JSON request.'],415);
}
$raw=file_get_contents('php://input',false,null,0,4096);
$data=json_decode((string)$raw,true);
if (!is_array($data)) json_response(['error'=>'Invalid request.'],400);
$expected=(string)($_SESSION['opp_admin_csrf']??'');
$submitted=(string)($data['csrf']??'');
if ($expected==='' || $submitted==='' || !hash_equals($expected,$submitted)) {
    json_response(['error'=>'Security token expired. Refresh the admin page and try again.'],403);
}
$ref=strtoupper(trim((string)($data['ref']??'')));
$step=$data['step']??null;
$labels=['Booked','Assigned','On the way','On site','Done'];
if (!preg_match('/^OPP-[A-F0-9]{24}$/D',$ref) || !is_int($step) || $step<0 || $step>4) {
    json_response(['error'=>'Invalid booking or job stage.'],400);
}
try {
    $db=db();
    $check=$db->prepare('SELECT * FROM bookings WHERE code=?');
    $check->execute([$ref]);
    $booking=$check->fetch();
    if (!$booking) json_response(['error'=>'Booking not found.'],404);
    $oldStage=(int)$booking['status'];
    if ($oldStage===$step) {
        json_response(['success'=>true,'stage'=>$step,'stageLabel'=>$labels[$step],
          'changed'=>false,'customerEmail'=>'unchanged','customerWhatsApp'=>'unchanged']);
    }
    // Update only if the stage hasn't changed since it was read (avoid duplicate notifications).
    $update=$db->prepare('UPDATE bookings SET status=?,updated_at=? WHERE code=? AND status=?');
    $update->execute([$step,gmdate('c'),$ref,$oldStage]);
    if ($update->rowCount()!==1) {
        json_response(['error'=>'This job was updated by another administrator. Refresh and try again.'],409);
    }
    $booking['status']=$step;
    $notification='unavailable';
    if (filter_var((string)($booking['email']??''),FILTER_VALIDATE_EMAIL) && opp_smtp_ready()) {
        try {
            opp_notify_customer_stage_change($booking,$oldStage,$step);
            // SMTP server acceptance, not proof of inbox delivery.
            $notification='accepted';
        } catch(Throwable $notificationError) {
            error_log('Job '.$ref.' stage saved; customer SMTP failed: '.$notificationError->getMessage());
            $notification='failed';
        }
    } elseif (!filter_var((string)($booking['email']??''),FILTER_VALIDATE_EMAIL)) {
        $notification='no_email';
    }
    $whatsapp='unavailable';
    try { $whatsapp=opp_wa_notify_customer_stage($booking,$oldStage,$step); }
    catch(Throwable $whatsappError) {
        error_log('Job '.$ref.' stage saved; customer WhatsApp failed: '.$whatsappError->getMessage());
        $whatsapp='failed';
    }
    json_response(['success'=>true,'stage'=>$step,'stageLabel'=>$labels[$step],
      'changed'=>true,'customerEmail'=>$notification,'customerWhatsApp'=>$whatsapp]);
} catch (Throwable $e) {
    error_log('Admin stage update failed: '.$e->getMessage());
    json_response(['error'=>'Unable to save the job stage. Please try again.'],500);
}
