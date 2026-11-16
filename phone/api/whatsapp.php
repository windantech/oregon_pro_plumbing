<?php declare(strict_types=1);
// Staff-only inbox API: list conversations, read a conversation, send a reply.
require dirname(__DIR__).'/app/whatsapp.php';
$user=need_user();
try{
  wa_schema();$db=database();
  $method=(string)($_SERVER['REQUEST_METHOD']??'GET');
  if($method==='GET'&&($_GET['op']??'')==='threads'){
    $rows=$db->query("SELECT m.contact, MAX(m.id) AS last_id,
        SUM(CASE WHEN m.direction='in' AND m.is_read=0 THEN 1 ELSE 0 END) AS unread,
        MAX(CASE WHEN m.direction='in' THEN m.created_at END) AS last_in,
        (SELECT p.profile_name FROM wa_messages p WHERE p.contact=m.contact AND p.direction='in' AND p.profile_name<>'' ORDER BY p.id DESC LIMIT 1) AS profile_name
      FROM wa_messages m GROUP BY m.contact ORDER BY last_id DESC LIMIT 100")->fetchAll();
    $last=[];
    if($rows){
      $ids=array_map(fn($r)=>(int)$r['last_id'],$rows);
      $q=$db->prepare('SELECT id,direction,body,media,created_at FROM wa_messages WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).')');
      $q->execute($ids);foreach($q->fetchAll() as $m)$last[(int)$m['id']]=$m;
    }
    $threads=[];$total=0;
    foreach($rows as $r){$m=$last[(int)$r['last_id']]??[];$total+=(int)$r['unread'];
      $preview=trim((string)($m['body']??''));if($preview===''&&!empty($m['media']))$preview='📎 Attachment';
      $threads[]=['contact'=>$r['contact'],'name'=>(string)($r['profile_name']??''),'unread'=>(int)$r['unread'],
        'preview'=>wa_text($preview,90),'direction'=>(string)($m['direction']??''),'at'=>(string)($m['created_at']??''),
        'windowOpen'=>wa_window_open($r['last_in']?(string)$r['last_in']:null)];}
    json_response(['threads'=>$threads,'unread'=>$total,'ready'=>wa_ready()]);
  }
  if($method==='GET'&&($_GET['op']??'')==='messages'){
    $contact=number_e164((string)($_GET['contact']??''));if($contact==='')json_response(['error'=>'Invalid contact.'],400);
    $q=$db->prepare('SELECT m.id,m.direction,m.body,m.media,m.status,m.error_code,m.created_at,u.display_name AS agent FROM wa_messages m LEFT JOIN users u ON u.id=m.agent_id WHERE m.contact=? ORDER BY m.id DESC LIMIT 200');
    $q->execute([$contact]);$msgs=array_reverse($q->fetchAll());
    $out=[];foreach($msgs as $m){$media=[];foreach((json_decode((string)($m['media']??''),true)?:[]) as $i=>$f){$media[]=['type'=>(string)($f['t']??''),'url'=>'api/whatsapp-media.php?id='.(int)$m['id'].'&i='.(int)$i];}
      $out[]=['id'=>(int)$m['id'],'direction'=>$m['direction'],'body'=>(string)$m['body'],'media'=>$media,'status'=>(string)$m['status'],'error'=>(string)$m['error_code'],'at'=>(string)$m['created_at'],'agent'=>(string)($m['agent']??'')];}
    $db->prepare("UPDATE wa_messages SET is_read=1 WHERE contact=? AND direction='in' AND is_read=0")->execute([$contact]);
    $lastIn=wa_last_inbound($contact);
    json_response(['contact'=>$contact,'messages'=>$out,'windowOpen'=>wa_window_open($lastIn),'lastInbound'=>$lastIn,'ready'=>wa_ready()]);
  }
  if($method==='POST'){
    verify_csrf();
    $contact=number_e164((string)($_POST['contact']??''));if($contact==='')json_response(['error'=>'Invalid contact.'],400);
    $res=wa_send_text($contact,(string)($_POST['body']??''),(int)$user['id']);
    json_response(['ok'=>true]+$res);
  }
  json_response(['error'=>'Unsupported request.'],400);
}catch(RuntimeException $e){json_response(['error'=>$e->getMessage()],422);}
 catch(Throwable $e){error_log('WhatsApp inbox error: '.$e->getMessage());json_response(['error'=>'WhatsApp inbox is temporarily unavailable.'],500);}
