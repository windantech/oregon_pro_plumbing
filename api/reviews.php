<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/reviews.php';
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
$method=(string)($_SERVER['REQUEST_METHOD']??'GET');
if ($method==='GET') {
    if (!installed()) json_response(['reviews'=>[]]);
    try {
        $db=db();opp_review_table($db);
        $q=$db->query("SELECT display_name,city,rating,review_text,created_at FROM customer_reviews WHERE status='approved' ORDER BY created_at DESC LIMIT 24");
        $rows=[];foreach($q->fetchAll() as $r) $rows[]=[
            'name'=>(string)$r['display_name'],'city'=>(string)$r['city'],
            'rating'=>(int)$r['rating'],'text'=>(string)$r['review_text'],
            'date'=>(string)$r['created_at']
        ];
        json_response(['reviews'=>$rows]);
    } catch (Throwable $e) { error_log('Reviews read failure: '.$e->getMessage());json_response(['reviews'=>[]]); }
}
if ($method!=='POST') fail('Method not allowed.',405);
if (!installed()) fail('The website is not configured to accept reviews.',503);
try {
    $p=request_json();
    if (trim((string)($p['fax_number']??''))!=='') fail('Unable to accept this submission.');
    $code=strtoupper(clean($p['booking_code']??'',32));
    $phone4=clean($p['phone_last4']??'',4);
    $name=clean($p['display_name']??'',55);
    $text=clean($p['review_text']??'',700);
    $rating=filter_var($p['rating']??null,FILTER_VALIDATE_INT);
    if (!preg_match('/^OPP-[A-F0-9]{24}$/',$code)||!preg_match('/^\d{4}$/',$phone4)) fail('Please check the completed booking reference and phone digits.');
    if (strlen($name)<2 || strlen($text)<20 || $rating===false || $rating<1 || $rating>5 || ($p['consent']??false)!==true) fail('Please complete all review fields and consent to publication.');
    $booking=get_booking($code);
    $digits=preg_replace('/\D/','',(string)($booking['phone']??''));
    if (!$booking || (int)$booking['status']!==4 || strlen((string)$digits)<4 || !hash_equals(substr((string)$digits,-4),$phone4)) {
        fail('We could not verify a completed booking using those details. Please contact the company if you need help.',403);
    }
    $db=db();opp_review_table($db);
    $query=$db->prepare('SELECT id FROM customer_reviews WHERE booking_code=?');$query->execute([$code]);
    if ($query->fetch()) fail('Feedback has already been submitted for this booking.',409);
    $now=gmdate('c');
    $db->prepare("INSERT INTO customer_reviews (booking_code,display_name,city,rating,review_text,status,source,created_at,updated_at) VALUES (?,?,?,?,?,'pending','booking',?,?)")
       ->execute([$code,$name,(string)$booking['city'],$rating,$text,$now,$now]);
    json_response(['ok'=>true,'message'=>'Your review has been received and is awaiting approval.'],201);
} catch (PDOException $e) {
    error_log('Review submission DB issue: '.$e->getMessage());
    fail('Unable to submit feedback. Please contact us.',503);
} catch(Throwable $e) {
    error_log('Review submission failure: '.$e->getMessage());
    fail('Unable to submit feedback at this time.',503);
}
