<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
header('Cache-Control: no-store');header('X-Robots-Tag: noindex, nofollow');header('X-Frame-Options: DENY');
$installed=installed();$errors=[];$success=false;
$secureConnection=(($_SERVER['HTTPS']??'off')!=='off') || (($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https');
$isLocal=in_array(strtolower((string)($_SERVER['HTTP_HOST']??'')),['localhost','127.0.0.1','127.0.0.1:8844'],true);
$token=$_SESSION['opp_setup_csrf']??=bin2hex(random_bytes(24));
$drivers=PDO::getAvailableDrivers();
$proposedUrl=((($_SERVER['HTTPS']??'off')!=='off')?'https':'http').'://'.($_SERVER['HTTP_HOST']??'oregonproplumbing.com');
if(!$installed&&($_SERVER['REQUEST_METHOD']??'')==='POST') {
    if(!$secureConnection && !$isLocal) $errors[]='For your security, open this installer using HTTPS before entering passwords.';
    if(str_starts_with(INSTALL_SETUP_CODE,'CHANGE-ME')) $errors[]='Edit INSTALL_SETUP_CODE in includes/bootstrap.php to a private code before installing.';
    elseif(!hash_equals(INSTALL_SETUP_CODE,trim((string)($_POST['setup_code']??'')))) $errors[]='Incorrect one-time installation code. Check the supplied setup guide.';
    if(!hash_equals($token,(string)($_POST['token']??''))) $errors[]='Please refresh the setup form and try again.';
    $url=rtrim(trim((string)($_POST['app_url']??'')),'/');
    $pieces=parse_url($url);
    if(!is_array($pieces)||($pieces['scheme']??'')!=='https'||empty($pieces['host'])||!filter_var($url,FILTER_VALIDATE_URL)) $errors[]='Enter the final HTTPS website address (SSL must be active).';
    $type=(string)($_POST['db_type']??'sqlite');
    if(!in_array($type,['sqlite','mysql'],true)||!in_array($type,$drivers,true)) $errors[]='Your selected database driver is unavailable. Select a supported option.';
    $password=(string)($_POST['admin_password']??'');
    if(strlen($password)<12) $errors[]='Choose an administrator password at least 12 characters long.';
    if($password!==(string)($_POST['admin_confirm']??'')) $errors[]='Administrator passwords do not match.';
    $email=trim((string)($_POST['notify_email']??''));
    if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[]='Enter a valid notification email, or leave the field blank.';
    $fee=filter_var($_POST['service_fee']??'',FILTER_VALIDATE_FLOAT);
    if($fee===false||$fee<0||$fee>10000) $errors[]='Enter a valid service-call amount (USD).';
    $rates=[];
    foreach(PRICE_DEFAULTS as $id=>$info){
        $raw=trim((string)($_POST['price_'.$id]??''));
        $val=$raw===''?null:filter_var($raw,FILTER_VALIDATE_FLOAT);
        if($val===false||($val!==null&&($val<0||$val>50000))) $errors[]='Check the '.$info['name'].' labor amount.';
        $rates[$id]=$val===null?null:(int)round($val*100);
    }
    $settings=[
      'APP_URL'=>$url,'DB_DRIVER'=>$type,'DB_HOST'=>trim((string)($_POST['db_host']??'localhost')),
      'DB_PORT'=>(int)($_POST['db_port']??3306), 'DB_NAME'=>trim((string)($_POST['db_name']??'')),
      'DB_USER'=>trim((string)($_POST['db_user']??'')), 'DB_PASSWORD'=>(string)($_POST['db_password']??''),
      'ADMIN_PASSWORD_HASH'=>password_hash($password,PASSWORD_DEFAULT),
      'BOOKING_NOTIFY_EMAIL'=>$email,'SERVICE_CALL_FEE_CENTS'=>(int)round((float)$fee*100),
      'SERVICE_LABOR_CENTS'=>$rates,'STRIPE_SECRET_KEY'=>'','STRIPE_WEBHOOK_SECRET'=>'',
      'PAYMENTS_ENABLED'=>'0','PRICING_CONFIRMED'=>'0',
    ];
    if($type==='mysql') {
       if($settings['DB_NAME']===''||$settings['DB_USER']===''||$settings['DB_HOST']==='') $errors[]='MySQL database name, host, and user are required.';
       if($settings['DB_PORT']<1||$settings['DB_PORT']>65535) $errors[]='Invalid MySQL port.';
    }
    if(!$errors) {
       try {
          $folder=storage_location();
          if(!is_dir($folder)&&!@mkdir($folder,0700,true)) throw new RuntimeException('Private storage cannot be created.');
          $root=realpath((string)($_SERVER['DOCUMENT_ROOT']??''));$private=realpath($folder);
          if(!$private||($root&&($private===$root||str_starts_with($private,$root.'/')))) {
             throw new RuntimeException('Private storage would be publicly accessible. Ask the host for a writable folder above public_html.');
          }
          if(!is_writable($folder)) throw new RuntimeException('Private storage is not writable on your hosting plan.');
          // The database must be reachable before installation is considered complete.
          if($type==='sqlite') {
              $test=new PDO('sqlite:'.$folder.'/bookings.sqlite');
              $test->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
          } else {
              $test=new PDO('mysql:host='.$settings['DB_HOST'].';port='.$settings['DB_PORT'].';dbname='.$settings['DB_NAME'].';charset=utf8mb4',
                    $settings['DB_USER'],$settings['DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
          }
          create_tables($test,$type);$test=null;
          write_config($settings);$installed=true;$success=true;
          session_regenerate_id(true);
       } catch(Throwable $ex) {
          error_log('Website setup failed: '.$ex->getMessage());
          $errors[]='Setup could not finish: '.$ex->getMessage();
       }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Website Setup | Oregon Pro Plumbing</title>
<style>:root{font-family:Inter,system-ui,-apple-system,sans-serif;color:#183d34;background:#f0f5f0}*{box-sizing:border-box}body{margin:0}.top{background:#10372e;color:#fff;padding:24px 7vw}.top a{color:#c6f8d8}.layout{max-width:930px;margin:32px auto;padding:0 17px}.card{border-radius:18px;background:#fff;padding:clamp(22px,4vw,40px);border:1px solid #d7e4da;box-shadow:0 16px 46px #16422c0d}h1{margin:0;font-size:27px}h2{font-size:21px;margin-top:35px}.muted{color:#66786f;line-height:1.6}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.field{margin-bottom:15px}label{display:block;font-weight:700;font-size:13px;margin-bottom:7px}input,select{width:100%;border:1px solid #bacdc2;background:#fff;border-radius:10px;padding:13px;font:inherit}input:focus,select:focus{outline:2px solid #2b9367}button,.button{background:#145b42;color:white;border:none;border-radius:11px;padding:14px 24px;font-weight:700;text-decoration:none;display:inline-block;cursor:pointer;font:inherit}button:hover{background:#0d4532}.notice{padding:16px;border-radius:12px;background:#eaf6ee;border:1px solid #c3decf;margin-bottom:22px;line-height:1.55}.error{background:#fff1ea;border-color:#f2c5ad}.driver{display:inline-flex;align-items:center;margin:7px 20px 10px 0;gap:6px}.driver input{width:auto}.price{display:grid;grid-template-columns:1fr 160px;gap:15px;align-items:center;padding:8px 0;border-bottom:1px solid #edf2ed}.footer{padding:24px 0;color:#65786e;font-size:13px}@media(max-width:650px){.grid{grid-template-columns:1fr}.price{grid-template-columns:1fr 120px}.card{padding:20px}}</style></head><body>
<header class="top"><h1>Oregon Pro Plumbing</h1><div style="opacity:.82;margin-top:5px">Website installation · no terminal required</div></header>
<main class="layout"><article class="card">
<?php if($success): ?>
 <div class="notice"><strong>Website installed successfully.</strong> Your database and administrator login have been created outside the website folder.</div>
 <h2>Next steps</h2><ol><li><strong>Delete <code>setup.php</code> using File Manager.</strong></li><li>Open <a href="admin/">Operations dashboard</a> and sign in with the password you chose.</li><li>Go to <a href="admin/settings.php">Payment & pricing settings</a> when ready to add your Stripe keys.</li><li>Set your Stripe webhook URL to <code><?=h(rtrim($url,'/'))?>/api/stripe-webhook.php</code>.</li><li>Make a small Stripe test-mode payment and verify it before enabling live payments.</li></ol><p><a class="button" href="admin/">Open operations dashboard</a></p>
<?php elseif($installed): ?>
 <div class="notice"><strong>Website is already installed.</strong> You can manage Stripe keys, pricing, and bookings from the dashboard.</div><p><a class="button" href="admin/">Open operations dashboard</a></p><p class="muted"><strong>Security:</strong> Delete the <code>setup.php</code> file using File Manager.</p>
<?php else: ?>
 <h2 style="margin-top:0">Set up your website</h2><p class="muted">Upload the website files to your hosting account, then fill this form once. Payment processing stays off until you add Stripe keys in the admin area.</p>
 <?php if(!$secureConnection && !$isLocal): ?><div class="notice error">Security requirement: please reopen this page using <strong>https://</strong> before entering administrator or database passwords.</div><?php endif;?>
 <?php if($errors): ?><div class="notice error"><strong>Setup needs attention</strong><ul><?php foreach($errors as $error): ?><li><?=h($error)?></li><?php endforeach;?></ul></div><?php endif;?>
 <?php if(!extension_loaded('curl')): ?><div class="notice error">PHP cURL is not enabled. Bookings can still work, but Stripe payments require the host to enable cURL.</div><?php endif;?>
 <form method="post"><input type="hidden" name="token" value="<?=h($token)?>">
  <h2>1. Website & administrator</h2>
  <div class="field"><label for="setup_code">One-time installation code (provided with your download)</label><input id="setup_code" name="setup_code" required autocomplete="off" placeholder="OPP-…"><small class="muted">Prevents anyone else from claiming the website before you finish installation.</small></div>
  <div class="field"><label for="app_url">Website URL (must use HTTPS)</label><input id="app_url" name="app_url" type="url" required value="<?=h($_POST['app_url']??$proposedUrl)?>" placeholder="https://oregonproplumbing.com"></div>
  <div class="grid"><div class="field"><label for="pw">Administrator password (12+ characters)</label><input id="pw" name="admin_password" type="password" autocomplete="new-password" minlength="12" required></div><div class="field"><label for="pw2">Repeat password</label><input id="pw2" name="admin_confirm" type="password" autocomplete="new-password" minlength="12" required></div></div>
  <div class="field"><label for="email">Email for new booking alerts (optional)</label><input id="email" name="notify_email" type="email" value="<?=h($_POST['notify_email']??'')?>" placeholder="bookings@oregonproplumbing.com"><small class="muted">Emails require your hosting provider's PHP mail service to be configured. All bookings are also saved in Operations.</small></div>
  <h2>2. Database</h2><p class="muted">Choose SQLite when available for the simplest installation. Select MySQL if your host does not support SQLite.</p>
  <?php if(!$drivers||(!in_array('sqlite',$drivers,true)&&!in_array('mysql',$drivers,true))): ?><div class="notice error">No supported PHP database driver is enabled. In cPanel's PHP Extensions, ask the host to enable PDO SQLite or PDO MySQL.</div><?php endif;?>
  <?php foreach(['sqlite'=>'SQLite (automatic, no database creation)','mysql'=>'MySQL (database created first in cPanel)'] as $driver=>$desc): ?>
    <label class="driver"><input type="radio" name="db_type" value="<?=$driver?>" <?=(!in_array($driver,$drivers,true))?'disabled':''?> <?=($_POST['db_type']??(in_array('sqlite',$drivers,true)?'sqlite':'mysql'))===$driver?'checked':''?>><span><?=$desc?><?=in_array($driver,$drivers,true)?'':' — not enabled'?></span></label>
  <?php endforeach;?>
  <div class="notice" style="margin-top:10px">If you choose MySQL, open <b>cPanel → MySQL Databases</b>, create a database and database user, and assign the user <b>All Privileges</b>. Then enter those credentials below.</div>
  <div class="grid"><div class="field"><label>Database host</label><input name="db_host" value="<?=h($_POST['db_host']??'localhost')?>"></div><div class="field"><label>Database port</label><input name="db_port" inputmode="numeric" value="<?=h($_POST['db_port']??'3306')?>"></div><div class="field"><label>Database name</label><input name="db_name" value="<?=h($_POST['db_name']??'')?>" placeholder="account_oregon"></div><div class="field"><label>Database username</label><input name="db_user" value="<?=h($_POST['db_user']??'')?>"></div></div>
  <div class="field"><label>Database password</label><input type="password" name="db_password" autocomplete="new-password"></div>
  <h2>3. Your prices</h2><p class="muted">These figures are starting values from the prior website. Check each amount against your actual company pricing before accepting any payments. They are editable later.</p>
  <div class="field"><label for="fee">Service-call fee (USD)</label><input id="fee" name="service_fee" inputmode="decimal" type="number" min="0" max="10000" step="0.01" value="<?=h($_POST['service_fee']??'129.00')?>"></div>
  <?php foreach(PRICE_DEFAULTS as $id=>$info): ?><div class="price"><label for="price_<?=$id?>"><?=h($info['name'])?> labor (USD)</label><input id="price_<?=$id?>" name="price_<?=$id?>" type="number" min="0" max="50000" step="0.01" value="<?=h($_POST['price_'.$id]??($info['labor']===null?'':number_format($info['labor']/100,2,'.','')))?>" placeholder="On-site estimate"></div><?php endforeach; ?>
  <p class="muted">Do not use this page to enter Stripe keys. You can add them securely inside the administrator settings after setup.</p><button type="submit" style="margin-top:20px">Install website →</button>
 </form>
<?php endif; ?>
</article><div class="footer">Oregon Pro Plumbing · Administrator installation. Keep this URL private until installation is complete.</div></main></body></html>
