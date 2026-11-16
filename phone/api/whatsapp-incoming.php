<?php declare(strict_types=1);
// Twilio webhook: "Webhook URL for incoming messages" on the WhatsApp sender. Signature-verified.
require dirname(__DIR__).'/app/whatsapp.php';
twilio_only('api/whatsapp-incoming.php');
$sid=(string)($_POST['MessageSid']??'');
$contact=wa_contact((string)($_POST['From']??''));
if(!preg_match('/^(SM|MM)[a-f0-9]{32}$/i',$sid)||$contact===''){twiml('');}
$body=wa_text((string)($_POST['Body']??''),4096);
$lat=(string)($_POST['Latitude']??'');$lon=(string)($_POST['Longitude']??'');
if(is_numeric($lat)&&is_numeric($lon)){$body=trim($body."\n📍 Shared location: ".$lat.','.$lon.' https://maps.google.com/?q='.rawurlencode($lat.','.$lon));}
$media=[];$count=min(10,max(0,(int)($_POST['NumMedia']??0)));
for($i=0;$i<$count;$i++){
  $url=(string)($_POST['MediaUrl'.$i]??'');$type=strtolower((string)($_POST['MediaContentType'.$i]??''));
  if(preg_match('#^https://api\.twilio\.com/2010-04-01/Accounts/AC[a-f0-9]{32}/Messages/(SM|MM)[a-f0-9]{32}/Media/ME[a-f0-9]{32}$#i',$url)){
    $media[]=['u'=>$url,'t'=>preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#',$type)?$type:'application/octet-stream'];
  }
}
$profile=wa_text(preg_replace('/[\x00-\x1F\x7F]/u','',(string)($_POST['ProfileName']??''))??'',120);
try{
  wa_schema();$now=wa_now();
  database()->prepare("INSERT INTO wa_messages (sid,contact,direction,body,media,status,profile_name,is_read,created_at,updated_at) VALUES (?,?,'in',?,?,'received',?,0,?,?)")
    ->execute([$sid,$contact,$body,$media?json_encode($media,JSON_UNESCAPED_SLASHES):null,$profile,$now,$now]);
}catch(PDOException $e){
  // Twilio may retry a webhook; a duplicate MessageSid is already stored.
  if(!in_array((string)$e->getCode(),['23000','23505'],true))error_log('WhatsApp inbound store failed: '.$e->getCode());
}
twiml('');
