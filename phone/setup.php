<?php
declare(strict_types=1);
require __DIR__.'/app/core.php';
if(portal_installed()){header('Location: index.php');exit;}
start_portal_session();$error='';$success=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  verify_csrf();
  if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off')throw new RuntimeException('HTTPS is required. Open this installer using https://');
  $username=strtolower(trim((string)($_POST['username']??'')));
  $display=trim((string)($_POST['display']??''));$pass=(string)($_POST['password']??'');
  $dbType=(string)($_POST['db_type']??'sqlite');
  if(!preg_match('/^[a-z][a-z0-9_]{2,29}$/',$username))throw new RuntimeException('Username: 3–30 letters, digits and underscores; start with a letter.');
  if(strlen($display)<2||strlen($display)>100)throw new RuntimeException('Enter an administrator display name.');
  if(strlen($pass)<12)throw new RuntimeException('Use an administrator password of 12 characters or longer.');
  if(!in_array($dbType,['sqlite','mysql'],true))throw new RuntimeException('Choose a valid database.');
  $url=trim((string)($_POST['base_url']??''));$url=rtrim($url,'/');
  if(!filter_var($url,FILTER_VALIDATE_URL)||parse_url($url,PHP_URL_SCHEME)!=='https')throw new RuntimeException('Enter the secure HTTPS URL of your portal, including /phone.');
  $number=number_e164((string)($_POST['twilio_number']??''));
  if(!$number)throw new RuntimeException('Enter your Twilio number in +1 international format.');
  $values=['base_url'=>$url,'public_number'=>$number,'db_driver'=>$dbType,
    'db_host'=>trim((string)($_POST['db_host']??'localhost')),
    'db_name'=>trim((string)($_POST['db_name']??'')),'db_user'=>trim((string)($_POST['db_user']??'')),'db_pass'=>(string)($_POST['db_pass']??''),
    'twilio_account_sid'=>'','twilio_auth_token'=>'','twilio_api_key_sid'=>'','twilio_api_key_secret'=>'','twiml_app_sid'=>'',
    'forward_number'=>'','forward_enabled'=>false,'outbound_enabled'=>false,'greeting_enabled'=>true,
    'created_at'=>gmdate('c'),'app_key'=>bin2hex(random_bytes(32))];
  if($dbType==='sqlite'){
    if(!extension_loaded('pdo_sqlite'))throw new RuntimeException('PHP PDO SQLite is unavailable. Select MySQL instead.');
    $dir=private_dir();if(!is_dir($dir)&&!@mkdir($dir,0700,true))throw new RuntimeException('Your hosting cannot create storage outside public_html: '.$dir);
    $db=new PDO('sqlite:'.$dir.'/phone.sqlite');
  }else{
    if(!extension_loaded('pdo_mysql'))throw new RuntimeException('PHP PDO MySQL is unavailable.');
    if(!$values['db_name']||!$values['db_user'])throw new RuntimeException('Enter your MySQL database name and user.');
    $db=new PDO('mysql:host='.$values['db_host'].';dbname='.$values['db_name'].';charset=utf8mb4',$values['db_user'],$values['db_pass']);
  }
  $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
  create_schema($db,$dbType);
  $q=$db->query('SELECT COUNT(*) FROM users');if((int)$q->fetchColumn()>0)throw new RuntimeException('An administrator already exists in this database. Do not install twice.');
  $stmt=$db->prepare("INSERT INTO users(username,display_name,password_hash,role,receive_calls,is_active) VALUES(?,?,?,'admin',1,1)");
  $stmt->execute([$username,$display,password_hash($pass,PASSWORD_DEFAULT)]);
  write_config($values);$success=true;
 }catch(Throwable $ex){$error=$ex->getMessage();error_log('Phone installer: '.get_class($ex));}
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Install Oregon Pro Plumbing Phone</title><link rel="stylesheet" href="assets/style.css"></head>
<body class="auth-body"><div class="auth-shell setup-shell"><div class="auth-brand"><img src="assets/brand.webp" alt="Oregon Pro Plumbing"/><span>OREGON PRO<br><b>PLUMBING</b></span></div><div class="auth-card"><span class="eyebrow">NO TERMINAL REQUIRED</span><h1>Set up your calling portal</h1><p>Private storage is created outside your public website folder. Your Stripe and plumbing-booking site are not affected.</p>
<?php if($error):?><div class="notice error"><?=e($error)?></div><?php endif;?><?php if($success):?><div class="notice good">Installation complete. <a href="login.php">Log in to your portal</a>. Then delete or rename setup.php using File Manager.</div><?php else:?>
<form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?=e(csrf())?>">
<h3>1. Administrator</h3><div class="form-grid"><label>Display name<input name="display" required placeholder="Office Manager"></label><label>Username<input name="username" required placeholder="manager"></label></div><label>Strong password (12+ characters)<input type="password" name="password" required minlength="12" autocomplete="new-password"></label>
<h3>2. Website and database</h3><label>Portal URL (HTTPS)<input type="url" name="base_url" required value="<?=e(((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https://':'https://').($_SERVER['HTTP_HOST']??'your-domain.com').rtrim(dirname($_SERVER['SCRIPT_NAME']??''),'/'))?>"></label><label>Public Twilio phone number<input name="twilio_number" required value="+15853160597"></label><label>Database type<select name="db_type" id="dbType"><option value="sqlite">SQLite — simplest if supported</option><option value="mysql">MySQL — use your cPanel database</option></select></label><div id="mysqlFields" style="display:none"><div class="form-grid"><label>MySQL host<input name="db_host" value="localhost"></label><label>Database name<input name="db_name" placeholder="cpanel_phone"></label></div><div class="form-grid"><label>MySQL user<input name="db_user"></label><label>MySQL password<input type="password" name="db_pass"></label></div></div>
<div class="notice">Requires PHP 8.1+, HTTPS, PDO SQLite or PDO MySQL, and writable home directory outside the website document root. Your Twilio keys are added after installation.</div><button class="primary wide" type="submit">Install calling portal <span>→</span></button></form><?php endif;?></div></div><script>const s=document.getElementById('dbType');const f=document.getElementById('mysqlFields');s?.addEventListener('change',()=>f.style.display=s.value==='mysql'?'block':'none');</script></body></html>
