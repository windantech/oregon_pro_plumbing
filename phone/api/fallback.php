<?php declare(strict_types=1);
require dirname(__DIR__).'/app/core.php';
twilio_only('api/fallback.php');
$status=(string)($_POST['DialCallStatus']??'');
if($status==='completed'||$status==='answered'){twiml('<Hangup/>');}
$forward=number_e164((string)cfg('forward_number'));
if((bool)cfg('forward_enabled')&&$forward){
 $caller=number_e164((string)cfg('public_number'));
 // This creates a billable international outbound call when Kenya forwarding is enabled.
 twiml('<Say voice="alice">Our team is assisting other customers. Please hold for our backup line.</Say><Dial timeout="25" timeLimit="900" callerId="'.xml($caller).'" answerOnBridge="true"><Number>'.xml($forward).'</Number></Dial>');
}
twiml('<Say voice="alice">Our team is unavailable right now. Please call again shortly. Thank you for calling Oregon Pro Plumbing.</Say><Hangup/>');
