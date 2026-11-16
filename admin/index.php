<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/smtp.php';
if (!installed()) { header('Location: ../setup.php'); exit; }
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

$token = $_SESSION['opp_admin_csrf'] ??= bin2hex(random_bytes(24));
$message = '';
$set = config('ADMIN_PASSWORD_HASH') !== '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($token, (string)($_POST['token'] ?? ''))) { http_response_code(403); exit('Invalid request token.'); }
    $action = $_POST['action'] ?? '';
    if ($action === 'login' && $set) {
        if (time() < ($_SESSION['opp_login_locked_until'] ?? 0)) $message = 'Too many attempts. Try again later.';
        elseif (password_verify((string)($_POST['password'] ?? ''), config('ADMIN_PASSWORD_HASH'))) {
            session_regenerate_id(true);
            $_SESSION['opp_admin_auth'] = true;
            $_SESSION['opp_login_failures'] = 0;
            header('Location: index.php'); exit;
        } else {
            $_SESSION['opp_login_failures'] = ($_SESSION['opp_login_failures'] ?? 0) + 1;
            if ($_SESSION['opp_login_failures'] >= 6) $_SESSION['opp_login_locked_until'] = time() + 900;
            $message = 'Unable to sign in.';
        }
    } elseif ($action === 'logout' && !empty($_SESSION['opp_admin_auth'])) {
        unset($_SESSION['opp_admin_auth']); session_regenerate_id(true);
        header('Location: index.php'); exit;
    }
}
$auth = $set && !empty($_SESSION['opp_admin_auth']);
$jobs = [];
if ($auth) {
    $jobs = db()->query('SELECT code,service_id,city,street,zip,details,urgency,first_name,last_name,phone,email,status,estimate_cents,paid_cents,created_at FROM bookings ORDER BY created_at DESC LIMIT 200')->fetchAll();
}
$statuses = ['Booked', 'Assigned', 'On the way', 'On site', 'Done'];
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Operations | Oregon Pro Plumbing</title><style>
:root{font-family:system-ui,-apple-system,sans-serif;color:#18312d;background:#f4f7f4}*{box-sizing:border-box}body{margin:0}header{background:#12332d;color:white;padding:22px max(20px,calc((100vw - 1180px)/2))}header h1{margin:0;font-size:24px}main{max-width:1180px;margin:40px auto;padding:0 20px}.panel{background:white;border:1px solid #dce8e1;border-radius:18px;padding:24px;box-shadow:0 12px 45px #17392f12}label{display:block;font-size:14px;font-weight:600;margin-bottom:7px}input,select{font:inherit;padding:11px;border:1px solid #b8c9c1;border-radius:9px;max-width:100%}button{font:inherit;background:#145c47;border:0;color:white;padding:11px 19px;border-radius:9px;font-weight:700;cursor:pointer}button:hover{background:#0c4938}.jobs{display:grid;gap:16px}.job{background:#fff;border:1px solid #dce8e1;border-radius:15px;padding:22px;display:grid;grid-template-columns:1fr auto;gap:22px}.meta{color:#62766c;font-size:13px;margin:6px 0}.status{color:#155a40;font-weight:700}.text{max-width:700px;overflow-wrap:anywhere}.pill{display:inline-block;background:#e5f3e8;padding:6px 10px;border-radius:99px;font-size:12px}.row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.toprow{display:flex;justify-content:space-between;gap:18px;align-items:center;margin-bottom:25px}.message{color:#a14529}.stage-feedback{font-size:12px;color:#155a40;line-height:1.4;margin:10px 0 0;max-width:245px;min-height:1.1em}.stage-feedback.error{color:#a14529}.opp-stage-form button:disabled{opacity:.65;cursor:wait}.muted{color:#61736b}@media(max-width:650px){.job{grid-template-columns:1fr}.toprow{align-items:start;flex-direction:column}}</style></head><body>
<header><h1>Oregon Pro Plumbing · Operations</h1></header><main>
<?php if (!$set): ?>
  <section class="panel"><h2>Administrator access not configured</h2><p>Set <code>ADMIN_PASSWORD_HASH</code> in the server's private <code>.env</code> file to enable operations access.</p></section>
<?php elseif (!$auth): ?>
  <section class="panel" style="max-width:430px;margin:auto"><h2>Administrator sign in</h2><p class="message"><?=h($message)?></p><form method="post"><input type="hidden" name="token" value="<?=h($token)?>"><input type="hidden" name="action" value="login"><label for="password">Password</label><input id="password" type="password" name="password" autocomplete="current-password" required style="width:100%;margin-bottom:17px"><button type="submit">Sign in</button></form></section>
<?php else: ?>
  <div class="toprow"><div><h2 style="margin:0">Booking requests</h2><p class="muted"><?=count($jobs)?> most recent jobs · Payment information is updated only after Stripe verification.</p></div><form method="post"><input type="hidden" name="action" value="logout"><input type="hidden" name="token" value="<?=h($token)?>"><button type="submit">Sign out</button></form></div>
  <?php if ($message): ?><p class="message"><?=h($message)?></p><?php endif; ?>
  <?php if (!opp_smtp_ready()): ?>
  <section class="panel" style="border-color:#e7c898;margin-bottom:18px;background:#fffaf0"><strong>Booking email notifications are not enabled.</strong><p class="muted" style="margin-bottom:0">Bookings are saved here even when email is unavailable. <a href="settings.php">Configure secure SMTP and send a test email</a> to activate alerts.</p></section>
  <?php endif; ?>
  <p><a href="settings.php" style="font-weight:700;color:#126244">⚙ Manage email, Stripe, pricing, and website settings →</a> &nbsp; | &nbsp; <a href="reviews.php" style="font-weight:700;color:#126244">★ Manage customer reviews →</a></p>
  <div class="jobs"><?php foreach($jobs as $job): ?>
    <article class="job" id="<?=h($job['code'])?>"><div>
      <div class="row"><strong><?=h($job['code'])?></strong><span class="pill opp-stage-pill"><?=h($statuses[$job['status']] ?? 'Booked')?></span></div>
      <p class="meta"><?=h($job['created_at'])?> · <?=h(PRICE_DEFAULTS[$job['service_id']]['name'])?> · <?=h($job['city'])?>, OR</p>
      <p class="text"><strong><?=h($job['first_name'].' '.$job['last_name'])?></strong> · <?=h($job['phone'])?> · <?=h($job['email'])?></p>
      <p class="text"><?=h($job['street'])?>, <?=h($job['city'])?>, OR <?=h($job['zip'])?><br><?=h($job['details'])?></p>
      <p class="meta">Requested timing: <?=h($job['urgency'])?></p>
      <p><span class="pill" style="background:<?=((int)$job['paid_cents']>0?'#e1f4e7':'#fff1dd')?>;color:<?=((int)$job['paid_cents']>0?'#17673b':'#8a5012')?>"><?=((int)$job['paid_cents']>0?'Payment received':'Unpaid request')?></span> <strong>Paid through Stripe: $<?=number_format((int)$job['paid_cents']/100,2)?></strong><?php if($job['estimate_cents']!==null): ?> / Starting estimate $<?=number_format((int)$job['estimate_cents']/100,2)?><?php endif;?></p>
    </div><form class="opp-stage-form" data-job-ref="<?=h($job['code'])?>"><input type="hidden" name="csrf" value="<?=h($token)?>"><label for="stage-<?=h($job['code'])?>">Job stage</label><select id="stage-<?=h($job['code'])?>" name="job_stage"><?php foreach($statuses as $i=>$name): ?><option value="<?=$i?>" <?=$i===(int)$job['status']?'selected':''?>><?=h($name)?></option><?php endforeach; ?></select><div style="height:10px"></div><button type="submit">Save stage</button><p class="stage-feedback" role="status" aria-live="polite"></p><noscript>JavaScript is needed to update a job stage.</noscript></form></article>
  <?php endforeach; ?><?php if(!$jobs): ?><section class="panel">No bookings have been received.</section><?php endif;?></div>
<?php endif; ?>
</main>
<?php if ($auth): ?>
<script>
'use strict';
// Same-origin AJAX avoids the regular form POST that some shared-host WAF rules reject.
document.querySelectorAll('.opp-stage-form').forEach(form => {
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const button = form.querySelector('button[type="submit"]');
    const feedback = form.querySelector('.stage-feedback');
    const dropdown = form.querySelector('select[name="job_stage"]');
    const ref = form.dataset.jobRef;
    const token = form.querySelector('input[name="csrf"]').value;
    feedback.classList.remove('error');
    feedback.textContent = 'Saving…';
    button.disabled = true;
    try {
      const response = await fetch('../api/update-booking-stage.php', {
        method:'POST', credentials:'same-origin', cache:'no-store',
        headers:{'Accept':'application/json','Content-Type':'application/json'},
        body:JSON.stringify({ref:ref,step:Number(dropdown.value),csrf:token})
      });
      const kind=(response.headers.get('content-type')||'').toLowerCase();
      let result = {};
      if (kind.includes('application/json')) result=await response.json();
      if (!response.ok || !result.success) {
        if (response.status===403 && !kind.includes('application/json')) {
          throw new Error('Hosting security blocked this update (403). Ask your host to review ModSecurity for /api/update-booking-stage.php.');
        }
        if (response.status===401) throw new Error('Admin session expired. Refresh the page and sign in again.');
        throw new Error(result.error||('Could not update job ('+response.status+').'));
      }
      form.closest('.job').querySelector('.opp-stage-pill').textContent=result.stageLabel;
      const emailText={accepted:' Customer notification accepted by SMTP.',
        unavailable:' Customer email not sent: SMTP is unavailable.',
        no_email:' No customer email address was saved for this request.',
        failed:' Customer email could not be sent; check SMTP settings.',
        unchanged:' No email sent because the stage did not change.'};
      const waText={sent:' WhatsApp update sent.',failed:' WhatsApp update failed; check WhatsApp settings.',
        bad_number:' WhatsApp not sent: phone number could not be read.',no_optin:' Customer did not opt in to WhatsApp.'};
      feedback.textContent=(result.changed?'Stage updated.':'Stage unchanged.')+(emailText[result.customerEmail]||'')+(waText[result.customerWhatsApp]||'');
    } catch (error) {
      feedback.classList.add('error');
      feedback.textContent=error instanceof Error?error.message:'Unable to update stage.';
    } finally {
      button.disabled=false;
    }
  });
});
</script>
<?php endif; ?>
</body></html>
