<?php declare(strict_types=1);
// Streams a customer's WhatsApp photo/file to signed-in staff. Twilio media URLs are fetched with the account credentials.
require dirname(__DIR__).'/app/whatsapp.php';
need_user();
$id=(int)($_GET['id']??0);$i=(int)($_GET['i']??-1);
wa_schema();$q=database()->prepare("SELECT media FROM wa_messages WHERE id=? AND direction='in'");$q->execute([$id]);
$list=json_decode((string)$q->fetchColumn(),true);$file=is_array($list)?($list[$i]??null):null;
$url=is_array($file)?(string)($file['u']??''):'';
if(!preg_match('#^https://api\.twilio\.com/2010-04-01/Accounts/AC[a-f0-9]{32}/Messages/(SM|MM)[a-f0-9]{32}/Media/ME[a-f0-9]{32}$#i',$url)){http_response_code(404);exit('Not found');}
$account=(string)cfg('twilio_account_sid');
$ch=curl_init($url);$data='';$limit=20*1024*1024;
curl_setopt_array($ch,[CURLOPT_USERPWD=>$account.':'.(string)cfg('twilio_auth_token'),CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>3,
  CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>40,
  CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
  CURLOPT_WRITEFUNCTION=>function($c,$chunk)use(&$data,$limit){$data.=$chunk;return strlen($data)>$limit?0:strlen($chunk);}]);
$ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
if(!$ok||$status!==200){http_response_code(502);exit('Attachment unavailable. It may have been deleted from Twilio.');}
$type=strtolower((string)($file['t']??''));
$inline=['image/jpeg','image/png','image/webp','image/gif','video/mp4','audio/ogg','audio/mpeg','audio/amr','audio/mp4','application/pdf'];
header('Content-Type: '.(in_array($type,$inline,true)?$type:'application/octet-stream'));
header('Content-Disposition: '.(in_array($type,$inline,true)?'inline':'attachment').'; filename="whatsapp-'.$id.'-'.$i.'"');
header('X-Content-Type-Options: nosniff');header("Content-Security-Policy: sandbox; default-src 'none'; img-src 'self'; media-src 'self'");
header('Cache-Control: private, max-age=3600');header('Content-Length: '.strlen($data));
echo $data;
