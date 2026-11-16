<?php declare(strict_types=1);
require dirname(__DIR__).'/app/core.php';
twilio_only('api/call-events.php');
$sid=(string)($_POST['CallSid']??'');$status=(string)($_POST['CallStatus']??'');
if(preg_match('/^CA[a-f0-9]{32}$/i',$sid)&&preg_match('/^[a-z_-]{3,35}$/i',$status)){
 $q=database()->prepare('UPDATE call_logs SET status=?, updated_at=CURRENT_TIMESTAMP WHERE call_sid=?');$q->execute([$status,$sid]);
 if($q->rowCount()===0){write_call($sid,'callback',(string)($_POST['From']??''),(string)($_POST['To']??''),$status);}
}
header('Content-Type: text/plain');http_response_code(204);
