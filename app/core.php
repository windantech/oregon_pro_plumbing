<?php
declare(strict_types=1);

const PORTAL_ROOT = __DIR__ . '/..';
function private_dir(): string {
    $portal = realpath(PORTAL_ROOT) ?: dirname(__DIR__);
    $cursor = $portal;
    // A portal installed in public_html/phone must never store secrets beside public_html/phone.
    for ($i=0;$i<7;$i++) {
        if (in_array(strtolower(basename($cursor)), ['public_html','htdocs','www'], true)) {
            return dirname($cursor) . '/oregon-pro-phone-private';
        }
        $parent=dirname($cursor);if($parent===$cursor)break;$cursor=$parent;
    }
    $docRoot=realpath((string)($_SERVER['DOCUMENT_ROOT']??''));
    if($docRoot){return dirname($docRoot).'/oregon-pro-phone-private';}
    return dirname($portal).'/oregon-pro-phone-private';
}
function portal_installed(): bool { return is_file(private_dir() . '/config.php'); }
function config(): array {
    static $data = null;
    if ($data === null) { $path = private_dir() . '/config.php'; $data = is_file($path) ? (require $path) : []; }
    return $data;
}
function cfg(string $key, mixed $fallback = ''): mixed { return config()[$key] ?? $fallback; }
function write_config(array $values): void {
    $dir=private_dir(); if (!is_dir($dir) && !mkdir($dir,0700,true)) throw new RuntimeException('Cannot create private storage directory outside public_html. Ask the host to allow PHP to write to ' . $dir);
    $file=$dir.'/config.php'; $tmp=$file.'.'.bin2hex(random_bytes(4)).'.tmp';
    $body="<?php\n// Private settings; never place this file in public_html.\nreturn ".var_export($values,true).";\n";
    if(file_put_contents($tmp,$body,LOCK_EX)===false) throw new RuntimeException('Cannot write private configuration.');
    @chmod($tmp,0600);
    if(!rename($tmp,$file)) throw new RuntimeException('Could not save configuration.');
}
function database(): PDO {
    static $db=null; if($db instanceof PDO)return $db;
    $driver=(string)cfg('db_driver','sqlite');
    if($driver==='mysql') {
        $host=(string)cfg('db_host','localhost');$name=(string)cfg('db_name','');
        $dsn='mysql:host='.$host.';dbname='.$name.';charset=utf8mb4';
        $db=new PDO($dsn,(string)cfg('db_user'),(string)cfg('db_pass'),[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    } else {
        $path=private_dir().'/phone.sqlite';
        $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $db->exec('PRAGMA busy_timeout=3000');
    }
    return $db;
}
function create_schema(PDO $db,string $driver): void {
    if($driver==='mysql'){
        $db->exec('CREATE TABLE IF NOT EXISTS users (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, username VARCHAR(80) NOT NULL UNIQUE, display_name VARCHAR(120) NOT NULL, password_hash VARCHAR(255) NOT NULL, role VARCHAR(15) NOT NULL DEFAULT \'agent\', receive_calls TINYINT NOT NULL DEFAULT 1, is_active TINYINT NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $db->exec('CREATE TABLE IF NOT EXISTS call_logs (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, call_sid VARCHAR(70) NOT NULL UNIQUE, direction VARCHAR(16) NOT NULL, from_number VARCHAR(100) NOT NULL, to_number VARCHAR(100) NOT NULL, status VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }else{
        $db->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE, display_name TEXT NOT NULL, password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT \'agent\', receive_calls INTEGER NOT NULL DEFAULT 1, is_active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $db->exec('CREATE TABLE IF NOT EXISTS call_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, call_sid TEXT NOT NULL UNIQUE, direction TEXT NOT NULL, from_number TEXT NOT NULL, to_number TEXT NOT NULL, status TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
    }
}
function start_portal_session(): void {
    if(session_status()!==PHP_SESSION_NONE)return;
    $secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https';
    session_name('OPPPhoneSession');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
    ini_set('session.use_strict_mode','1');
    session_start();
}
function current_user(): ?array {
    start_portal_session();
    if(!portal_installed()||!isset($_SESSION['agent_id']))return null;
    $st=database()->prepare('SELECT id,username,display_name,role,receive_calls,is_active FROM users WHERE id=? LIMIT 1');
    $st->execute([$_SESSION['agent_id']]);$u=$st->fetch();
    return ($u&&$u['is_active'])?$u:null;
}
function need_user(): array {
    $user=current_user();if(!$user){if(str_contains((string)($_SERVER['REQUEST_URI']??''),'/api/'))json_response(['error'=>'Please sign in again.'],401);header('Location: login.php');exit;}return $user;
}
function need_admin(): array {$u=need_user();if($u['role']!=='admin')json_response(['error'=>'Administrator access required.'],403);return $u;}
function csrf(): string {start_portal_session();return $_SESSION['csrf']??=bin2hex(random_bytes(24));}
function verify_csrf(): void {$given=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??$_POST['csrf']??'');if(!$given||!hash_equals(csrf(),$given))json_response(['error'=>'Security token expired. Refresh the page and retry.'],403);}
function json_response(array $payload,int $status=200): never {http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
function e(mixed $v): string {return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function public_url(string $path=''): string {return rtrim((string)cfg('base_url'),'/').'/'.ltrim($path,'/');}
function sms_safe(string $v): string {return preg_replace('/[^a-zA-Z0-9_.+-]/','',$v)??'';}
function number_e164(string $v): string {$clean=preg_replace('/[\s()\-.]/','',$v)??'';return preg_match('/^\+[1-9]\d{7,14}$/',$clean)?$clean:'';}
function xml(string $v): string {return htmlspecialchars($v,ENT_QUOTES|ENT_XML1,'UTF-8');}
function twiml(string $body,int $status=200): never {http_response_code($status);header('Content-Type: application/xml; charset=utf-8');header('Cache-Control: no-store');echo '<?xml version="1.0" encoding="UTF-8"?><Response>'.$body.'</Response>';exit;}
function signed_twilio_request(string $relative): bool {
    $auth=(string)cfg('twilio_auth_token');$header=(string)($_SERVER['HTTP_X_TWILIO_SIGNATURE']??'');
    if(!$auth||!$header||($_SERVER['REQUEST_METHOD']??'')!=='POST')return false;
    // Twilio signs the EXACT public URL and form fields in sorted name order.
    $url=public_url($relative);
    $pairs=$_POST;ksort($pairs,SORT_STRING);
    foreach($pairs as $key=>$value){if(!is_scalar($value))return false;$url.=$key.(string)$value;}
    $computed=base64_encode(hash_hmac('sha1',$url,$auth,true));
    return hash_equals($computed,$header);
}
function twilio_only(string $relative): void {if(!signed_twilio_request($relative)){http_response_code(403);header('Content-Type: text/plain');exit('Invalid signature');}}
function write_call(string $sid,string $direction,string $from,string $to,string $status): void {
    if(!preg_match('/^CA[a-f0-9]{32}$/i',$sid))return;
    try{$q=database()->prepare('INSERT INTO call_logs(call_sid,direction,from_number,to_number,status) VALUES(?,?,?,?,?)');$q->execute([$sid,$direction,substr($from,0,100),substr($to,0,100),substr($status,0,40)]);}catch(PDOException $e){error_log('Call log insert: '.$e->getCode());}
}
function agent_identity(int $id): string {return 'agent_'.$id;}
function jwt_voice_token(array $user): string {
    $sid=(string)cfg('twilio_api_key_sid');$secret=(string)cfg('twilio_api_key_secret');$account=(string)cfg('twilio_account_sid');$app=(string)cfg('twiml_app_sid');
    if(!preg_match('/^SK[a-f0-9]{32}$/i',$sid)||!preg_match('/^AC[a-f0-9]{32}$/i',$account)||!preg_match('/^AP[a-f0-9]{32}$/i',$app)||strlen($secret)<12)throw new RuntimeException('Twilio API key and TwiML App settings are incomplete.');
    $now=time();$grant=['identity'=>agent_identity((int)$user['id']),'voice'=>['incoming'=>['allow'=>(bool)$user['receive_calls']],'outgoing'=>['application_sid'=>$app]]];
    $header=['typ'=>'JWT','alg'=>'HS256','cty'=>'twilio-fpa;v=1'];
    $payload=['jti'=>$sid.'-'.bin2hex(random_bytes(16)),'iss'=>$sid,'sub'=>$account,'iat'=>$now,'nbf'=>$now-5,'exp'=>$now+3600,'grants'=>$grant];
    $enc=static fn(array $a):string=>rtrim(strtr(base64_encode(json_encode($a,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)),'+/','-_'),'=');
    $data=$enc($header).'.'.$enc($payload);return $data.'.'.rtrim(strtr(base64_encode(hash_hmac('sha256',$data,$secret,true)),'+/','-_'),'=');
}
function twilio_ready(): bool {
    return (bool)(cfg('twilio_account_sid')&&cfg('twilio_auth_token')&&cfg('twilio_api_key_sid')&&cfg('twilio_api_key_secret')&&cfg('twiml_app_sid'));
}
function login_rate_file(): string {
    $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');$h=hash_hmac('sha256',$ip,(string)cfg('app_key'));
    return private_dir().'/login-rate-'.substr($h,0,32).'.json';
}
function login_is_blocked(): bool {
    $path=login_rate_file();if(!is_file($path))return false;
    $d=json_decode((string)file_get_contents($path),true);
    if(!is_array($d)||((int)($d['since']??0))<time()-900)return false;
    return (int)($d['attempts']??0)>=15;
}
function record_login_failure(): void {
    $path=login_rate_file();$d=is_file($path)?json_decode((string)file_get_contents($path),true):[];
    if(!is_array($d)||((int)($d['since']??0))<time()-900)$d=['since'=>time(),'attempts'=>0];
    $d['attempts']=(int)$d['attempts']+1;@file_put_contents($path,json_encode($d),LOCK_EX);@chmod($path,0600);
}
function reset_login_failures(): void { $f=login_rate_file();if(is_file($f))@unlink($f); }
