<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/smtp.php';
require_once __DIR__.'/../includes/whatsapp.php';
header('Cache-Control: no-store');header('X-Robots-Tag: noindex,nofollow');header('X-Frame-Options: DENY');
if(!installed()){header('Location: ../setup.php');exit;}
if(empty($_SESSION['opp_admin_auth'])){header('Location: index.php');exit;}
$token=$_SESSION['opp_settings_csrf']??=bin2hex(random_bytes(24));$msg='';$error='';$settings=app_config();
if(($_SERVER['REQUEST_METHOD']??'')==='POST') {
    if(!hash_equals($token,(string)($_POST['token']??''))) {http_response_code(403);exit('Invalid request token.');}
    try {
        if (($_POST['action']??'save') === 'test_email') {
            if (time() < (int)($_SESSION['opp_smtp_next_test'] ?? 0)) throw new RuntimeException('Please allow 45 seconds between test emails.');
            $_SESSION['opp_smtp_next_test'] = time() + 45;
            // Tests use saved credentials only. Never send using unsaved form values.
            $saved = app_config();
            $to = trim((string)($saved['BOOKING_NOTIFY_EMAIL'] ?? ''));
            opp_smtp_send($to, 'Oregon Pro Plumbing — SMTP test',
                "Your Oregon Pro Plumbing SMTP settings are working.\n\n"
                ."New bookings can now notify your team automatically when email alerts are enabled.\n\n"
                .'Website: '.(string)($saved['APP_URL']??''));
            header('Location: settings.php?smtp_test=ok'); exit;
        }
        if (($_POST['action']??'save') === 'test_whatsapp') {
            if (time() < (int)($_SESSION['opp_wa_next_test'] ?? 0)) throw new RuntimeException('Please allow 45 seconds between WhatsApp tests.');
            $_SESSION['opp_wa_next_test'] = time() + 45;
            // Uses SAVED settings only, like the SMTP test.
            $saved = opp_wa_values(app_config());
            if ($saved['WA_BUSINESS_TO'] === '' || $saved['WA_TPL_BUSINESS'] === '') throw new RuntimeException('Save your business WhatsApp number and the new-booking alert template SID first.');
            opp_wa_send($saved['WA_BUSINESS_TO'], $saved['WA_TPL_BUSINESS'],
                ['OPP-TEST', 'WhatsApp test', 'Portland, OR', 'Test Customer', '+15035550123', 'Test message'], app_config());
            header('Location: settings.php?wa_test=ok'); exit;
        }
        if (($_POST['action']??'save') !== 'save') throw new RuntimeException('Invalid settings action.');
        $settings['BOOKING_NOTIFY_EMAIL']=trim((string)($_POST['notify_email']??''));
        if($settings['BOOKING_NOTIFY_EMAIL']!==''&&!filter_var($settings['BOOKING_NOTIFY_EMAIL'],FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid notification email.');
        $fee=filter_var($_POST['fee']??'',FILTER_VALIDATE_FLOAT);
        if($fee===false||$fee<0||$fee>10000) throw new RuntimeException('Check the service-call fee.');
        $settings['SERVICE_CALL_FEE_CENTS']=(int)round($fee*100);
        foreach(PRICE_DEFAULTS as $id=>$info){
            $raw=trim((string)($_POST['price_'.$id]??''));$price=$raw===''?null:filter_var($raw,FILTER_VALIDATE_FLOAT);
            if($price===false||($price!==null&&($price<0||$price>50000))) throw new RuntimeException('Check the '.$info['name'].' price.');
            $settings['SERVICE_LABOR_CENTS'][$id]=$price===null?null:(int)round($price*100);
        }
        // Secure SMTP credentials are stored with the existing private website configuration.
        $settings['SMTP_HOST']=trim((string)($_POST['smtp_host']??''));
        $port=filter_var($_POST['smtp_port']??'465',FILTER_VALIDATE_INT);
        if($port===false||$port<1||$port>65535) throw new RuntimeException('Enter a valid SMTP port.');
        $settings['SMTP_PORT']=$port;
        $settings['SMTP_ENCRYPTION']=(string)($_POST['smtp_encryption']??'ssl');
        if(!in_array($settings['SMTP_ENCRYPTION'],['ssl','tls'],true)) throw new RuntimeException('Select a secure SMTP connection type.');
        $settings['SMTP_USERNAME']=trim((string)($_POST['smtp_username']??''));
        $settings['SMTP_FROM_EMAIL']=trim((string)($_POST['smtp_from_email']??''));
        $settings['SMTP_FROM_NAME']=trim((string)($_POST['smtp_from_name']??'Oregon Pro Plumbing'));
        $newSmtpPassword=(string)($_POST['smtp_password']??'');
        if($newSmtpPassword!=='') $settings['SMTP_PASSWORD']=$newSmtpPassword;
        $settings['SMTP_ENABLED']=isset($_POST['smtp_enabled'])?'1':'0';
        if ($settings['SMTP_ENABLED']==='1') opp_smtp_validate($settings);
        // WhatsApp (Twilio). The Auth Token is kept when the field is left blank.
        $settings['TWILIO_ACCOUNT_SID']=trim((string)($_POST['twilio_account_sid']??''));
        $newTwilioToken=trim((string)($_POST['twilio_auth_token']??''));
        if($newTwilioToken!=='') $settings['TWILIO_AUTH_TOKEN']=$newTwilioToken;
        $settings['WA_FROM']=opp_wa_e164((string)($_POST['wa_from']??'')) ?: trim((string)($_POST['wa_from']??''));
        $settings['WA_BUSINESS_TO']=opp_wa_e164((string)($_POST['wa_business_to']??'')) ?: trim((string)($_POST['wa_business_to']??''));
        foreach(['WA_TPL_BUSINESS'=>'wa_tpl_business','WA_TPL_CUSTOMER_BOOKING'=>'wa_tpl_customer_booking','WA_TPL_CUSTOMER_STAGE'=>'wa_tpl_customer_stage'] as $key=>$field){
            $settings[$key]=trim((string)($_POST[$field]??''));
        }
        $settings['WA_ENABLED']=isset($_POST['wa_enabled'])?'1':'0';
        if($settings['WA_ENABLED']==='1') opp_wa_validate($settings);
        $newKey=trim((string)($_POST['stripe_key']??''));
        $newWebhook=trim((string)($_POST['webhook_secret']??''));
        if($newKey!=='') {
            if(!preg_match('/^sk_(test|live)_\S{8,}$/',$newKey)) throw new RuntimeException('Stripe secret key should begin with sk_test_ or sk_live_.');
            $settings['STRIPE_SECRET_KEY']=$newKey;
        }
        if($newWebhook!=='') {
            if(!preg_match('/^whsec_\S{8,}$/',$newWebhook)) throw new RuntimeException('The Stripe endpoint signing secret should begin with whsec_.');
            $settings['STRIPE_WEBHOOK_SECRET']=$newWebhook;
        }
        if(!empty($_POST['new_password'])) {
            if(strlen((string)$_POST['new_password'])<12) throw new RuntimeException('The new password must contain at least 12 characters.');
            $settings['ADMIN_PASSWORD_HASH']=password_hash((string)$_POST['new_password'],PASSWORD_DEFAULT);
        }
        $settings['PRICING_CONFIRMED']=isset($_POST['confirmed'])?'1':'0';
        $settings['PAYMENTS_ENABLED']=isset($_POST['enable'])?'1':'0';
        if($settings['PAYMENTS_ENABLED']==='1') {
            if($settings['PRICING_CONFIRMED']!=='1') throw new RuntimeException('Confirm company prices before accepting payments.');
            if(!secure_app_url()||!extension_loaded('curl')) throw new RuntimeException('Stripe requires an HTTPS website and PHP cURL.');
            if(!preg_match('/^sk_(test|live)_\S{8,}$/',$settings['STRIPE_SECRET_KEY'])||!preg_match('/^whsec_\S{8,}$/',$settings['STRIPE_WEBHOOK_SECRET'])) {
                throw new RuntimeException('Save both Stripe credentials before enabling payments.');
            }
        }
        write_config($settings);
        header('Location: settings.php?saved=1');exit;
    } catch(Throwable $e){$error=$e->getMessage();}
}
if(isset($_GET['saved'])) $msg='Settings saved successfully.';
if(isset($_GET['wa_test']) && $_GET['wa_test']==='ok') $msg='WhatsApp test accepted by Twilio. Check the business WhatsApp (and Twilio Console → Monitor → Logs → Messaging if it does not arrive).';
if(isset($_GET['smtp_test']) && $_GET['smtp_test']==='ok') $msg='Test email accepted by your SMTP provider. Check the receiving inbox (and spam folder).';
$keySet=!empty($settings['STRIPE_SECRET_KEY']);$webhookSet=!empty($settings['STRIPE_WEBHOOK_SECRET']);
$mode=str_starts_with((string)$settings['STRIPE_SECRET_KEY'],'sk_live_')?'Live':($keySet?'Test':'Not configured');
$smtp=opp_smtp_values($settings); $smtpSaved=$smtp['SMTP_PASSWORD']!=='';
$wa=opp_wa_values($settings); $waTokenSaved=$wa['TWILIO_AUTH_TOKEN']!=='';
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Settings | Oregon Pro Plumbing</title><style>
:root{font-family:system-ui,-apple-system,sans-serif;color:#17382e;background:#f1f6f1}*{box-sizing:border-box}body{margin:0}header{background:#103a2e;color:#fff;padding:25px 6vw}header a{color:#c7f8d4}main{max-width:950px;margin:32px auto;padding:0 20px}.panel{border:1px solid #d9e6dc;background:#fff;border-radius:18px;padding:clamp(20px,4vw,34px);margin:18px 0;box-shadow:0 14px 34px #1b56300e}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}label{display:block;font-weight:700;font-size:13px;margin-bottom:7px}input{width:100%;font:inherit;border:1px solid #bbd0c1;border-radius:10px;padding:12px}input[type=checkbox]{width:auto}select{width:100%;font:inherit;border:1px solid #bbd0c1;border-radius:10px;padding:12px;background:#fff}button{background:#166547;color:#fff;border:0;border-radius:10px;padding:14px 23px;font-size:16px;font-weight:700;cursor:pointer}.muted{color:#62796b;font-size:14px;line-height:1.65}.price{display:grid;grid-template-columns:1fr 160px;gap:15px;padding:9px 0;border-bottom:1px solid #f0f2f0}.alert{padding:15px;border-radius:10px;background:#e9f5ec;border:1px solid #bee0c9}.bad{background:#fff2e9;border-color:#edc8b6}.tick{padding:12px;background:#f0f8f2;border:1px solid #cfe2d3;border-radius:12px;margin:12px 0}.tick label{font-weight:600;font-size:15px;display:flex;gap:12px;align-items:start}.small{font-size:13px}code{word-break:break-all}@media(max-width:620px){.grid{grid-template-columns:1fr}.price{grid-template-columns:1fr 120px}}</style></head><body>
<header><a href="index.php">← Operations dashboard</a><h1 style="margin-bottom:0">Website, email & payment settings</h1><p style="opacity:.8;margin-bottom:0">Oregon Pro Plumbing · Secure administrator area</p></header><main>
<?php if($msg): ?><div class="alert"><?=h($msg)?></div><?php endif; ?><?php if($error): ?><div class="alert bad"><?=h($error)?></div><?php endif;?>
<form method="post" autocomplete="off"><input type="hidden" name="token" value="<?=h($token)?>">
<section class="panel"><h2>Company settings</h2><p class="muted">Current website: <strong><?=h($settings['APP_URL']??'')?></strong>. New bookings are always saved in your dashboard, even if an email notification cannot be delivered.</p><label for="notify_email">Booking notification email</label><input id="notify_email" type="email" name="notify_email" placeholder="bookings@oregonproplumbing.com" value="<?=h($settings['BOOKING_NOTIFY_EMAIL']??'')?>"></section>
<section class="panel" id="email-settings">
  <h2>Booking email notifications (SMTP)</h2>
  <p class="muted">Configure the outgoing mail server provided by your email host. No terminal, PHP mail() function or Composer is required. Credentials stay in the website's existing private configuration folder.</p>
  <div class="grid">
    <div><label for="smtp_host">SMTP server (host)</label><input id="smtp_host" name="smtp_host" placeholder="mail.oregonproplumbing.com" value="<?=h($smtp['SMTP_HOST'])?>" autocomplete="off"></div>
    <div><label for="smtp_port">SMTP port</label><input id="smtp_port" name="smtp_port" type="number" min="1" max="65535" value="<?=h($smtp['SMTP_PORT'])?>"></div>
    <div><label for="smtp_encryption">Connection security</label><select name="smtp_encryption" id="smtp_encryption"><option value="ssl" <?=$smtp['SMTP_ENCRYPTION']==='ssl'?'selected':''?>>SSL/TLS (commonly port 465)</option><option value="tls" <?=$smtp['SMTP_ENCRYPTION']==='tls'?'selected':''?>>STARTTLS (commonly port 587)</option></select></div>
    <div><label for="smtp_username">SMTP username</label><input id="smtp_username" name="smtp_username" autocomplete="off" placeholder="bookings@oregonproplumbing.com" value="<?=h($smtp['SMTP_USERNAME'])?>"></div>
    <div><label for="smtp_password">SMTP password / app password</label><input id="smtp_password" type="password" name="smtp_password" placeholder="<?=$smtpSaved?'Password saved — leave blank to keep it':'Enter your email password / app password'?>" autocomplete="new-password"><p class="muted small">Existing password is never shown. Leave blank to retain it.</p></div>
    <div><label for="smtp_from_email">From email</label><input id="smtp_from_email" type="email" name="smtp_from_email" placeholder="bookings@oregonproplumbing.com" value="<?=h($smtp['SMTP_FROM_EMAIL'])?>"><p class="muted small">Usually matches the SMTP username or an approved sender.</p></div>
  </div>
  <label for="smtp_from_name">Sender name</label><input id="smtp_from_name" name="smtp_from_name" value="<?=h($smtp['SMTP_FROM_NAME'])?>" maxlength="100">
  <div class="tick"><label><input type="checkbox" name="smtp_enabled" <?=$smtp['SMTP_ENABLED']==='1'?'checked':''?>> Send automatic email alerts for every new booking.</label></div>
  <p class="muted">1. Save your SMTP settings. 2. Send a test email to the <strong>Booking notification email</strong> set above. 3. Confirm the email arrives. 4. Enable alerts and save. The SMTP test uses <strong>saved</strong> settings, not unsaved changes above.</p>
  <button type="submit" form="smtp-test-form" style="background:#edf6ef;color:#184d39;border:1px solid #aacbb5">Send test email</button>
  <p class="muted small">Your hosting provider must allow outgoing SMTP connections. SSL certificate verification stays enabled.</p>
</section>
<section class="panel" id="whatsapp-settings">
  <h2>WhatsApp notifications (Twilio)</h2>
  <p class="muted">Sends a WhatsApp alert to your team for every new booking, and request/status updates to customers who ticked the WhatsApp box on the booking form. WhatsApp only allows business-initiated messages that use <strong>approved templates</strong>; paste each template's Content SID (starts with <code>HX</code>) below. Leave a template blank to skip that message.</p>
  <div class="grid">
    <div><label for="twilio_account_sid">Twilio Account SID</label><input id="twilio_account_sid" name="twilio_account_sid" placeholder="AC…" value="<?=h($wa['TWILIO_ACCOUNT_SID'])?>" autocomplete="off"></div>
    <div><label for="twilio_auth_token">Twilio Auth Token</label><input id="twilio_auth_token" name="twilio_auth_token" type="password" placeholder="<?=$waTokenSaved?'Token saved — leave blank to keep':'From Twilio Console → Account info'?>" autocomplete="new-password"></div>
    <div><label for="wa_from">WhatsApp sender number</label><input id="wa_from" name="wa_from" placeholder="+15853160597" value="<?=h($wa['WA_FROM'])?>"></div>
    <div><label for="wa_business_to">Your team's WhatsApp number (receives new-booking alerts)</label><input id="wa_business_to" name="wa_business_to" placeholder="+2547… or +1503…" value="<?=h($wa['WA_BUSINESS_TO'])?>"><p class="muted small">Must be a different number from the sender.</p></div>
    <div><label for="wa_tpl_business">Template: new-booking alert (to your team)</label><input id="wa_tpl_business" name="wa_tpl_business" placeholder="HX…" value="<?=h($wa['WA_TPL_BUSINESS'])?>"><p class="muted small">{{1}} reference · {{2}} service · {{3}} city · {{4}} customer · {{5}} phone · {{6}} timing</p></div>
    <div><label for="wa_tpl_customer_booking">Template: request received (to customer)</label><input id="wa_tpl_customer_booking" name="wa_tpl_customer_booking" placeholder="HX…" value="<?=h($wa['WA_TPL_CUSTOMER_BOOKING'])?>"><p class="muted small">{{1}} first name · {{2}} service · {{3}} city</p></div>
    <div><label for="wa_tpl_customer_stage">Template: status update (to customer)</label><input id="wa_tpl_customer_stage" name="wa_tpl_customer_stage" placeholder="HX…" value="<?=h($wa['WA_TPL_CUSTOMER_STAGE'])?>"><p class="muted small">{{1}} first name · {{2}} service · {{3}} status</p></div>
  </div>
  <div class="tick"><label><input type="checkbox" name="wa_enabled" <?=$wa['WA_ENABLED']==='1'?'checked':''?>> Send WhatsApp notifications.</label></div>
  <p class="muted">Save first, then send a test. The test uses the <strong>saved</strong> new-booking alert template.</p>
  <button type="submit" form="wa-test-form" style="background:#edf6ef;color:#184d39;border:1px solid #aacbb5">Send WhatsApp test</button>
</section>
<section class="panel"><h2>Service pricing (USD)</h2><p class="muted">Prices here are used to calculate checkout amounts on the server. Update the public website's descriptions too if your actual pricing changes.</p>
<label for="fee">Service-call fee</label><input id="fee" name="fee" type="number" step="0.01" min="0" max="10000" value="<?=h(number_format(((int)$settings['SERVICE_CALL_FEE_CENTS'])/100,2,'.',''))?>">
<?php foreach(PRICE_DEFAULTS as $id=>$info): $p=$settings['SERVICE_LABOR_CENTS'][$id]??null;?><div class="price"><label for="price_<?=$id?>"><?=h($info['name'])?> labor</label><input type="number" id="price_<?=$id?>" name="price_<?=$id?>" min="0" max="50000" step="0.01" value="<?=$p===null?'':h(number_format($p/100,2,'.',''))?>" placeholder="Quote needed"></div><?php endforeach; ?>
</section>
<section class="panel"><h2>Stripe Checkout</h2><p class="muted">Current Stripe environment: <strong><?=h($mode)?></strong>. Keys are stored outside the publicly accessible website folder and are never returned to browsers.</p>
<div class="grid"><div><label for="stripe_key">Stripe secret API key</label><input id="stripe_key" name="stripe_key" type="password" placeholder="<?= $keySet?'Key saved — leave blank to keep':'sk_test_… or sk_live_…'?>" autocomplete="new-password"><p class="muted small">Get the secret key from your <a target="_blank" rel="noopener" href="https://dashboard.stripe.com/apikeys">Stripe API keys</a>.</p></div><div><label for="webhook_secret">Webhook signing secret</label><input id="webhook_secret" name="webhook_secret" type="password" placeholder="<?=$webhookSet?'Secret saved — leave blank to keep':'whsec_…'?>" autocomplete="new-password"><p class="muted small">Create a destination in Stripe for the URL below.</p></div></div>
<p class="muted">Webhook destination: <code><?=h(rtrim((string)$settings['APP_URL'],'/'))?>/api/stripe-webhook.php</code></p><p class="muted">Subscribe to <code>checkout.session.completed</code>, <code>checkout.session.async_payment_succeeded</code>, <code>checkout.session.async_payment_failed</code> and <code>checkout.session.expired</code>.</p>
<div class="tick"><label><input type="checkbox" name="confirmed" <?=($settings['PRICING_CONFIRMED']??'0')==='1'?'checked':''?>> I have verified the company's prices, service terms and payment/refund policies.</label></div>
<div class="tick"><label><input type="checkbox" name="enable" <?=($settings['PAYMENTS_ENABLED']??'0')==='1'?'checked':''?>> Enable Stripe payments after the account, webhook and verification tests are complete.</label></div>
<p class="muted"><strong>Safety:</strong> Never paste a Stripe secret key into HTML or client-side JavaScript. Enter it in this administrator-only form.</p>
</section>
<section class="panel"><h2>Administrator security</h2><label for="new_password">Change administrator password (optional)</label><input id="new_password" type="password" name="new_password" minlength="12" placeholder="Leave blank to keep your current password" autocomplete="new-password"><p class="muted">Use a unique, strong password. Keep your hosting account and Stripe account protected with MFA.</p></section>
<button type="submit" name="action" value="save">Save all settings</button>
</form>
<form method="post" id="wa-test-form"><input type="hidden" name="token" value="<?=h($token)?>"><input type="hidden" name="action" value="test_whatsapp"></form>
<form method="post" id="smtp-test-form"><input type="hidden" name="token" value="<?=h($token)?>"><input type="hidden" name="action" value="test_email"></form><p class="muted">This site's booking and payment records are kept in the hosting account's private database. Maintain backups and review hosting security regularly.</p>
</main></body></html>
