<?php declare(strict_types=1);require dirname(__DIR__).'/app/core.php';if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['error'=>'Use POST'],405);$admin=need_admin();verify_csrf();$op=(string)($_POST['op']??'');
try{
 if($op==='settings'){
  $new=config();
  foreach(['twilio_account_sid'=>'/^AC[a-f0-9]{32}$/i','twilio_api_key_sid'=>'/^SK[a-f0-9]{32}$/i','twiml_app_sid'=>'/^AP[a-f0-9]{32}$/i'] as $field=>$pattern){
   $v=trim((string)($_POST[$field]??''));if($v!==''){if(!preg_match($pattern,$v))throw new RuntimeException('Invalid '.$field.' format.');$new[$field]=$v;}
  }
  foreach(['twilio_auth_token','twilio_api_key_secret'] as $field){$v=trim((string)($_POST[$field]??''));if($v!==''){if(strlen($v)<12||strlen($v)>256)throw new RuntimeException('Invalid '.$field.' length.');$new[$field]=$v;}}
  $url=rtrim(trim((string)($_POST['base_url']??'')),'/');if(!filter_var($url,FILTER_VALIDATE_URL)||parse_url($url,PHP_URL_SCHEME)!=='https')throw new RuntimeException('Portal URL must use HTTPS.');$new['base_url']=$url;
  $n=number_e164((string)($_POST['public_number']??''));if(!$n)throw new RuntimeException('Invalid public Twilio phone number.');$new['public_number']=$n;
  $f=number_e164((string)($_POST['forward_number']??''));if(trim((string)($_POST['forward_number']??''))!==''&&!$f)throw new RuntimeException('Enter a valid forwarding number in international format.');
  $new['forward_number']=$f;
  $w=trim((string)($_POST['whatsapp_number']??''));$we=number_e164($w);if($w!==''&&!$we)throw new RuntimeException('Enter the WhatsApp number in +1 international format, or leave it blank.');
  $new['whatsapp_number']=$we;
  $new['outbound_enabled']=isset($_POST['outbound_enabled']);
  $new['forward_enabled']=isset($_POST['forward_enabled'])&&$f!=='';
  $new['greeting_enabled']=isset($_POST['greeting_enabled']);
  write_config($new);json_response(['ok'=>true,'message'=>'Settings saved. Existing secrets were retained when left blank.']);
 }
 if($op==='new_user'){
  $u=strtolower(trim((string)($_POST['username']??'')));$name=trim((string)($_POST['display_name']??''));$pass=(string)($_POST['password']??'');
  if(!preg_match('/^[a-z][a-z0-9_]{2,29}$/',$u)||strlen($name)<2||strlen($name)>100||strlen($pass)<12)throw new RuntimeException('Provide a username, display name and password of at least 12 characters.');
  $role=in_array($_POST['role']??'',['admin','agent'],true)?(string)$_POST['role']:'agent';
  $q=database()->prepare('INSERT INTO users(username,display_name,password_hash,role,receive_calls,is_active) VALUES(?,?,?,?,1,1)');$q->execute([$u,$name,password_hash($pass,PASSWORD_DEFAULT),$role]);json_response(['ok'=>true,'message'=>'Team member added.']);
 }
 if($op==='update_user'){
  $id=(int)($_POST['user_id']??0);if($id<1)throw new RuntimeException('Invalid user ID.');
  $old=database()->prepare('SELECT id,role,is_active FROM users WHERE id=?');$old->execute([$id]);$row=$old->fetch();if(!$row)throw new RuntimeException('User not found.');
  $active=isset($_POST['is_active'])?1:0;$receive=isset($_POST['receive_calls'])?1:0;
  if($id===(int)$admin['id']&&!$active)throw new RuntimeException('You cannot disable your own account.');
  $q=database()->prepare('UPDATE users SET is_active=?,receive_calls=? WHERE id=?');$q->execute([$active,$receive,$id]);
  $pass=(string)($_POST['password']??'');if($pass!==''){if(strlen($pass)<12)throw new RuntimeException('New password must be at least 12 characters.');database()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($pass,PASSWORD_DEFAULT),$id]);}
  json_response(['ok'=>true,'message'=>'Team member updated.']);
 }
 if($op==='sdk_upload'){
  if(!isset($_FILES['sdk'])||($_FILES['sdk']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Choose the official Twilio twilio.min.js file.');
  $f=$_FILES['sdk'];if($f['size']<30000||$f['size']>5000000)throw new RuntimeException('SDK file size must be between 30 KB and 5 MB.');
  if(basename((string)$f['name'])!=='twilio.min.js')throw new RuntimeException('File must be named twilio.min.js.');
  $bytes=file_get_contents((string)$f['tmp_name']);
  if($bytes===false||(stripos($bytes,'twilio')===false && !str_contains($bytes,'Device')))throw new RuntimeException('This does not look like the Twilio SDK. Download the official release.');
  $path=dirname(__DIR__).'/assets/vendor/twilio.min.js';
  if(file_put_contents($path,$bytes,LOCK_EX)===false)throw new RuntimeException('Cannot save SDK; check write permissions on assets/vendor.');
  @chmod($path,0644);json_response(['ok'=>true,'message'=>'Twilio Voice SDK uploaded. Reload the portal to initialize calling.']);
 }
 json_response(['error'=>'Unsupported operation.'],400);
}catch(Throwable $e){json_response(['error'=>$e->getMessage()],422);}
