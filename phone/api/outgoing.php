<?php declare(strict_types=1);
require dirname(__DIR__).'/app/core.php';
twilio_only('api/outgoing.php');
if(!cfg('outbound_enabled'))twiml('<Say voice="alice">Outbound calling is not enabled. Please contact your administrator.</Say><Hangup/>');
$destination=number_e164((string)($_POST['To']??''));
// A conservative outbound policy: US +1 NANP numbers only. Restrict premium ranges.
if(!preg_match('/^\+1[2-9]\d{2}[2-9]\d{6}$/',$destination)||preg_match('/^\+1(?:900|976)/',$destination)){
 twiml('<Say voice="alice">This version of the calling portal supports standard United States and Canada numbers only.</Say><Hangup/>');
}
$from=(string)($_POST['From']??'');
if(!preg_match('/^(client:)?agent_\d+$/',$from))twiml('<Say voice="alice">This caller is not authorized.</Say><Hangup/>');
$id=(int)preg_replace('/\D/','',$from);$q=database()->prepare('SELECT is_active FROM users WHERE id=?');$q->execute([$id]);if(!(int)$q->fetchColumn())twiml('<Say voice="alice">This staff account is unavailable.</Say><Hangup/>');
// Basic outbound abuse guard: maximum 20 calls per agent in any rolling 15 minutes.
$cutoff=gmdate('Y-m-d H:i:s',time()-900);
$q=database()->prepare("SELECT COUNT(*) FROM call_logs WHERE direction='outbound' AND from_number=? AND created_at>=?");
$q->execute([$from,$cutoff]);
if((int)$q->fetchColumn()>=20)twiml('<Say voice="alice">Calling limit reached. Please contact your administrator.</Say><Hangup/>');
write_call((string)($_POST['CallSid']??''),'outbound',$from,$destination,'initiated');
$caller=number_e164((string)cfg('public_number'));
// Don't log/record conversations; only store call metadata.
$markup='<Dial callerId="'.xml($caller).'" answerOnBridge="true" timeLimit="1200" timeout="30"><Number>'.xml($destination).'</Number></Dial>';
twiml($markup);
