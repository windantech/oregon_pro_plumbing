<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/reviews.php';
if (!installed()) {header('Location: ../setup.php');exit;}
header('Cache-Control: no-store');header('X-Frame-Options: DENY');header('X-Content-Type-Options: nosniff');
if (empty($_SESSION['opp_admin_auth'])) {header('Location: index.php');exit;}
$token=$_SESSION['opp_admin_csrf']??=bin2hex(random_bytes(24));
$db=db();opp_review_table($db);$error='';
if (($_SERVER['REQUEST_METHOD']??'')==='POST') {
  if (!hash_equals($token,(string)($_POST['token']??''))) {http_response_code(403);exit('Invalid request token.');}
  $action=(string)($_POST['action']??'');
  try {
    if (in_array($action,['approved','rejected','pending'],true)) {
      $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
      if ($id===false || $id<=0) throw new RuntimeException('Invalid review.');
      $db->prepare('UPDATE customer_reviews SET status=?,updated_at=? WHERE id=?')->execute([$action,gmdate('c'),$id]);
      header('Location: reviews.php?updated=1');exit;
    } elseif ($action==='add') {
      $name=clean($_POST['display_name']??'',55);$city=clean($_POST['city']??'',100);
      $text=clean($_POST['review_text']??'',700);$rating=filter_var($_POST['rating']??null,FILTER_VALIDATE_INT);
      if (strlen($name)<2 || strlen($city)<2 || strlen($text)<20 || $rating===false || $rating<1||$rating>5 || !isset($_POST['permission'])) throw new RuntimeException('Complete the review fields and confirm permission to publish the genuine feedback.');
      $now=gmdate('c');
      $db->prepare("INSERT INTO customer_reviews(booking_code,display_name,city,rating,review_text,status,source,created_at,updated_at) VALUES(NULL,?,?,?,?, 'pending','admin',?,?)")
         ->execute([$name,$city,$rating,$text,$now,$now]);
      header('Location: reviews.php?updated=1');exit;
    }
  } catch(Throwable $e) {$error=$e->getMessage();}
}
$reviews=$db->query("SELECT id,booking_code,display_name,city,rating,review_text,status,source,created_at FROM customer_reviews ORDER BY CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END,created_at DESC LIMIT 200")->fetchAll();
$count=['pending'=>0,'approved'=>0,'rejected'=>0];foreach($reviews as $r)if(isset($count[$r['status']]))$count[$r['status']]++;
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/><meta name="robots" content="noindex,nofollow"/><title>Customer Reviews | Oregon Pro Plumbing</title>
<style>:root{font-family:system-ui,Arial,sans-serif;color:#153e31;background:#f4f8f6}*{box-sizing:border-box}body{margin:0}header{background:#104330;color:white;padding:20px max(20px,calc((100vw - 1130px)/2));display:flex;gap:18px;justify-content:space-between;align-items:center}a{color:#0c8051}header a{color:white}main{max-width:1120px;margin:36px auto;padding:0 20px}.panel,.review{background:white;border-radius:16px;padding:23px;border:1px solid #dbe7e0;box-shadow:0 8px 22px #174d3a10}.panel{margin-bottom:22px}.counts{display:flex;gap:14px;flex-wrap:wrap}.counts b{background:#e3f6ed;border-radius:30px;padding:6px 12px}input,textarea,select{width:100%;padding:11px;border:1px solid #bdd1c7;border-radius:8px;font:inherit;margin:6px 0 13px}label{font-weight:650;font-size:14px}.grid{display:grid;grid-template-columns:1fr 1fr 120px;gap:13px}button{background:#147b49;color:white;border:0;padding:10px 15px;border-radius:8px;cursor:pointer;font:700 14px system-ui}.small{font-size:12px;color:#577669}.reviews{display:grid;gap:14px}.review p{max-width:950px;line-height:1.6}.review strong{font-size:18px}.pill{display:inline-block;padding:4px 10px;border-radius:18px;background:#e8f2ee;color:#145439;font-size:12px;font-weight:750}.action{display:flex;gap:10px;flex-wrap:wrap}.red{background:#a7473f}.gray{background:#60766c}@media(max-width:700px){.grid{grid-template-columns:1fr}header{flex-direction:column;align-items:flex-start}}</style>
</head><body><header><strong>Oregon Pro Plumbing · Customer Reviews</strong><a href="index.php">← Back to operations</a></header><main><h1>Customer feedback</h1><div class="counts"><b><?= $count['pending'] ?> awaiting approval</b><b><?= $count['approved'] ?> published</b><b><?= $count['rejected'] ?> declined</b></div>
<p class="small">Only approve genuine reviews. Never invent or modify customer feedback or assert a reviewer is verified without a corresponding completed booking.</p>
<?php if($error): ?><p style="color:#a7473f"><?=h($error)?></p><?php endif; ?><?php if(isset($_GET['updated'])): ?><p style="color:#16804f">Review settings updated.</p><?php endif; ?>
<section class="panel"><h2>Add a genuine review received outside this website</h2><p class="small">Only use feedback received from an actual customer who has given permission to publish the text and display name.</p><form method="post"><input type="hidden" name="token" value="<?=h($token)?>"/><input type="hidden" name="action" value="add"/><div class="grid"><div><label>Public display name</label><input name="display_name" placeholder="e.g. J. Smith" maxlength="55" required/></div><div><label>Oregon city</label><input name="city" placeholder="e.g. Portland" maxlength="100" required/></div><div><label>Rating</label><select name="rating" required><option value="5">5 stars</option><option value="4">4 stars</option><option value="3">3 stars</option><option value="2">2 stars</option><option value="1">1 star</option></select></div></div><label>Customer's actual words</label><textarea name="review_text" minlength="20" maxlength="700" rows="3" required></textarea><label style="display:flex;align-items:flex-start;gap:9px"><input type="checkbox" name="permission" value="1" style="width:auto;margin:2px 0" required/> The reviewer is a real customer and gave permission to publish this feedback.</label><p><button type="submit">Add for review</button></p></form></section>
<section class="reviews"><h2>Submitted reviews</h2><?php foreach($reviews as $r): ?><article class="review"><div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap"><strong><?=h($r['display_name'])?> · <?=h($r['city'])?></strong><span class="pill"><?=h(ucfirst($r['status']))?></span></div><div style="color:#f0a900;margin:7px 0"><?=str_repeat('★',max(1,min(5,(int)$r['rating'])))?></div><p><?=nl2br(h($r['review_text']))?></p><p class="small">Source: <?=h($r['source']==='booking'?'Completed booking':'Provided by administrator')?><?= $r['booking_code']?' · '.h($r['booking_code']):'' ?> · Received <?=h($r['created_at'])?></p>
<div class="action"><form method="post"><input type="hidden" name="token" value="<?=h($token)?>"/><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"/><button name="action" value="approved" type="submit">Approve & publish</button></form><form method="post"><input type="hidden" name="token" value="<?=h($token)?>"/><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"/><button class="gray" name="action" value="pending" type="submit">Hold</button></form><form method="post"><input type="hidden" name="token" value="<?=h($token)?>"/><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"/><button class="red" name="action" value="rejected" type="submit">Reject</button></form></div></article><?php endforeach; ?><?php if(!$reviews): ?><div class="panel">No customer reviews have been submitted yet.</div><?php endif; ?></section>
</main></body></html>
