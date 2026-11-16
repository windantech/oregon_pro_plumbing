<?php declare(strict_types=1);
require dirname(__DIR__).'/app/core.php';
twilio_only('api/incoming.php');
$from=(string)($_POST['From']??'');$to=(string)($_POST['To']??'');
write_call((string)($_POST['CallSid']??''),'incoming',$from,$to,'ringing');
$users=database()->query('SELECT id FROM users WHERE is_active=1 AND receive_calls=1 ORDER BY CASE WHEN role=\'admin\' THEN 0 ELSE 1 END,id ASC LIMIT 8')->fetchAll(PDO::FETCH_COLUMN);
$greeting=cfg('greeting_enabled')?'<Say voice="alice">Thank you for calling Oregon Pro Plumbing. Please hold while we connect your call.</Say>':'';
if(!$users){twiml($greeting.'<Redirect method="POST">'.xml(public_url('api/fallback.php')).'</Redirect>');}
$clients='';foreach($users as $id){$clients.='<Client>'.xml(agent_identity((int)$id)).'</Client>';}
$caller=number_e164((string)cfg('public_number'));
$dial='<Dial timeout="20" answerOnBridge="true" action="'.xml(public_url('api/fallback.php')).'" method="POST" callerId="'.xml($caller).'">'.$clients.'</Dial>';
twiml($greeting.$dial);
