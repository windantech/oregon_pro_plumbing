<?php declare(strict_types=1);
// Twilio status callback for replies sent from the portal (sent / delivered / read / failed).
require dirname(__DIR__).'/app/whatsapp.php';
twilio_only('api/whatsapp-status.php');
$sid=(string)($_POST['MessageSid']??'');$status=strtolower((string)($_POST['MessageStatus']??''));
$code=preg_replace('/\D/','',(string)($_POST['ErrorCode']??''))??'';
if(preg_match('/^(SM|MM)[a-f0-9]{32}$/i',$sid)&&preg_match('/^[a-z_]{3,20}$/',$status)){
  try{wa_schema();database()->prepare("UPDATE wa_messages SET status=?,error_code=?,updated_at=? WHERE sid=? AND direction='out'")->execute([$status,substr($code,0,10),wa_now(),$sid]);}
  catch(Throwable $e){error_log('WhatsApp status update failed: '.$e->getCode());}
}
http_response_code(204);
