<?php
declare(strict_types=1);
// This application's business configuration and database live OUTSIDE the web directory.
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) { http_response_code(403); exit; }
// Set a private one-time code before running setup.php on a NEW install (never commit the real value).
const INSTALL_SETUP_CODE = 'CHANGE-ME-BEFORE-INSTALL';
const WEBSITE_ROOT = __DIR__ . '/..';
const PRICE_DEFAULTS = [
 'burst'=>['name'=>'Burst Pipe Repair','labor'=>28500], 'leak'=>['name'=>'Slow leak','labor'=>17500],
 'drain'=>['name'=>'Drain Cleaning Services','labor'=>14900], 'hot'=>['name'=>'No hot water','labor'=>16500],
 'heater'=>['name'=>'Water Heater Services','labor'=>45000], 'fixture'=>['name'=>'New fixture','labor'=>22500],
 'pump'=>['name'=>'Sump or well pump','labor'=>35000], 'remodel'=>['name'=>'Bathroom remodel','labor'=>null],
 'general'=>['name'=>'General Plumbing','labor'=>null],
 'detect'=>['name'=>'Leak Detection','labor'=>null],
 'faucet_repair'=>['name'=>'Faucet Repair','labor'=>null],
 'pipe_repair'=>['name'=>'Pipe Leak Repair','labor'=>null],
 'toilet_repair'=>['name'=>'Toilet Repair','labor'=>null],
 'toilet_install'=>['name'=>'Toilet Installation','labor'=>null],
 'faucet_install'=>['name'=>'Faucet Installation','labor'=>null],
 'disposal_install'=>['name'=>'Garbage Disposal Installation','labor'=>null],
 'sewer_lines'=>['name'=>'Sewer & Water Lines','labor'=>null],
 'emergency'=>['name'=>'Emergency Plumbing','labor'=>null]
];
function storage_location(): string {
    $website = realpath(WEBSITE_ROOT) ?: WEBSITE_ROOT;
    $home = $_SERVER['HOME'] ?? getenv('HOME') ?: '';
    if (is_string($home) && $home !== '' && is_dir($home)) {
        $h = realpath($home);
        if ($h && !str_starts_with($h.'/', rtrim($website,'/').'/') && is_writable($h)) {
            return $h.'/oregon_pro_plumbing_private';
        }
    }
    return dirname($website).'/oregon_pro_plumbing_private';
}
function config_path(): string { return storage_location().'/config.php'; }
function installed(): bool { return is_file(config_path()); }
function app_config(): array {
    static $cfg=null;
    if ($cfg !== null) return $cfg;
    if (!installed()) return $cfg=[];
    $read=require config_path();
    return $cfg=is_array($read)?$read:[];
}
function config(string $key, mixed $default=''): mixed { return app_config()[$key] ?? $default; }
function write_config(array $settings): void {
    $dir=storage_location();
    if (!is_dir($dir) && !@mkdir($dir,0700,true)) throw new RuntimeException('Cannot create the private storage folder.');
    if (!is_writable($dir)) throw new RuntimeException('Private storage folder is not writable.');
    // Deny visitors on Apache if a host has a surprising directory alias.
    @file_put_contents($dir.'/.htaccess', "Require all denied\nDeny from all\n");
    @file_put_contents($dir.'/index.html', '');
    $settings['CONFIG_UPDATED_AT']=gmdate('c');
    $body="<?php\n// Oregon Pro Plumbing private settings. This file must NOT be in public_html.\nreturn ".var_export($settings,true).";\n";
    $temp=$dir.'/settings-'.bin2hex(random_bytes(8)).'.tmp';
    if (file_put_contents($temp,$body,LOCK_EX)===false) throw new RuntimeException('Could not save settings.');
    @chmod($temp,0600);
    if (!@rename($temp, config_path())) { @unlink($temp); throw new RuntimeException('Could not save configuration.'); }
    @chmod(config_path(),0600);
}
function fee_cents(): int { return max(0,(int)config('SERVICE_CALL_FEE_CENTS',12900)); }
function service_price(string $id): ?int {
    if (!array_key_exists($id, PRICE_DEFAULTS)) return null;
    $configured=config('SERVICE_LABOR_CENTS',[]);
    $value=is_array($configured)&&array_key_exists($id,$configured)?$configured[$id]:PRICE_DEFAULTS[$id]['labor'];
    return $value===null?null:max(0,(int)$value);
}
function secure_app_url(): bool { $url=parse_url((string)config('APP_URL')); return ($url['scheme']??'')==='https' && !empty($url['host']); }
function stripe_api_ready(): bool {
    return installed() && extension_loaded('curl') && preg_match('/^sk_(test|live)_\S{8,}$/',(string)config('STRIPE_SECRET_KEY'));
}
function stripe_webhook_ready(): bool {
    return installed() && preg_match('/^whsec_\S{8,}$/',(string)config('STRIPE_WEBHOOK_SECRET'));
}
function configured_payments(): bool {
    return secure_app_url() && stripe_api_ready() && stripe_webhook_ready()
       && config('PAYMENTS_ENABLED')==='1' && config('PRICING_CONFIRMED')==='1';
}

function db(): PDO {
    static $pdo=null;
    if ($pdo instanceof PDO) return $pdo;
    $driver=config('DB_DRIVER','sqlite');
    if ($driver==='sqlite') {
        if (!extension_loaded('pdo_sqlite')) throw new RuntimeException('SQLite is not enabled on the hosting account.');
        $pdo=new PDO('sqlite:'.storage_location().'/bookings.sqlite');
    } elseif ($driver==='mysql') {
        if (!extension_loaded('pdo_mysql')) throw new RuntimeException('MySQL PDO is not enabled on the hosting account.');
        $pdo=new PDO('mysql:host='.config('DB_HOST','localhost').';port='.(int)config('DB_PORT',3306).';dbname='.config('DB_NAME').';charset=utf8mb4',(string)config('DB_USER'),(string)config('DB_PASSWORD'),[PDO::ATTR_EMULATE_PREPARES=>false]);
    } else { throw new RuntimeException('Unsupported database selection.'); }
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
    if ($driver==='sqlite') { $pdo->exec('PRAGMA busy_timeout=5000'); $pdo->exec('PRAGMA foreign_keys=ON'); $pdo->exec('PRAGMA journal_mode=WAL'); }
    return $pdo;
}
function create_tables(PDO $db, string $type): void {
    if ($type==='mysql') {
        $db->exec("CREATE TABLE IF NOT EXISTS bookings (
            code VARCHAR(32) PRIMARY KEY, service_id VARCHAR(25) NOT NULL, city VARCHAR(120) NOT NULL,
            street VARCHAR(250) NOT NULL, zip VARCHAR(10) NOT NULL, details TEXT NOT NULL, urgency VARCHAR(90) NOT NULL,
            first_name VARCHAR(90) NOT NULL, last_name VARCHAR(90) NOT NULL, phone VARCHAR(48) NOT NULL,
            email VARCHAR(200) NOT NULL, status TINYINT NOT NULL DEFAULT 0, estimate_cents BIGINT NULL,
            paid_cents BIGINT NOT NULL DEFAULT 0, created_at VARCHAR(40) NOT NULL, updated_at VARCHAR(40) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS payments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, session_id VARCHAR(255) NOT NULL UNIQUE,
            booking_code VARCHAR(32) NOT NULL, kind VARCHAR(16) NOT NULL, amount_cents BIGINT NOT NULL,
            currency VARCHAR(8) NOT NULL DEFAULT 'usd', status VARCHAR(20) NOT NULL DEFAULT 'pending',
            checkout_url TEXT NULL, expires_at BIGINT NULL, created_at VARCHAR(40) NOT NULL, paid_at VARCHAR(40) NULL,
            INDEX booking_payment_idx (booking_code), FOREIGN KEY (booking_code) REFERENCES bookings(code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec('CREATE TABLE IF NOT EXISTS stripe_events (event_id VARCHAR(255) PRIMARY KEY, created_at VARCHAR(40) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS bookings (
           code TEXT PRIMARY KEY, service_id TEXT NOT NULL, city TEXT NOT NULL, street TEXT NOT NULL, zip TEXT NOT NULL,
           details TEXT NOT NULL DEFAULT '', urgency TEXT NOT NULL, first_name TEXT NOT NULL, last_name TEXT NOT NULL,
           phone TEXT NOT NULL, email TEXT NOT NULL DEFAULT '', status INTEGER NOT NULL DEFAULT 0,
           estimate_cents INTEGER, paid_cents INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL, updated_at TEXT NOT NULL
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS payments (
           id INTEGER PRIMARY KEY AUTOINCREMENT, session_id TEXT NOT NULL UNIQUE, booking_code TEXT NOT NULL,
           kind TEXT NOT NULL, amount_cents INTEGER NOT NULL, currency TEXT NOT NULL DEFAULT 'usd',
           status TEXT NOT NULL DEFAULT 'pending', checkout_url TEXT, expires_at INTEGER,
           created_at TEXT NOT NULL, paid_at TEXT, FOREIGN KEY(booking_code) REFERENCES bookings(code)
        )");
        $db->exec('CREATE TABLE IF NOT EXISTS stripe_events (event_id TEXT PRIMARY KEY, created_at TEXT NOT NULL)');
    }
}
function json_response(array $data,int $status=200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;
}
function fail(string $m,int $s=400): never { json_response(['error'=>$m],$s); }
function require_method(string $m): void { if (($_SERVER['REQUEST_METHOD']??'')!==$m) fail('Method not allowed.',405); }
/**
 * Allow only the production site's HTTPS origins (apex and its www alias).
 * This avoids rejecting legitimate customers who visit www.oregonproplumbing.com
 * while maintaining the same-origin guard against unrelated websites.
 */
function allowed_site_origin(string $origin): bool {
    $expected=parse_url((string)config('APP_URL'));
    $actual=parse_url($origin);
    if (!is_array($expected) || !is_array($actual)) return false;
    $configuredHost=strtolower(rtrim((string)($expected['host']??''),'.'));
    $requestHost=strtolower(rtrim((string)($actual['host']??''),'.'));
    if ($configuredHost==='' || $requestHost==='') return false;
    if (strtolower((string)($expected['scheme']??''))!=='https'
        || strtolower((string)($actual['scheme']??''))!=='https') return false;
    if ((int)($actual['port']??443)!==443) return false;
    foreach (['user','pass','path','query','fragment'] as $part) {
        if (array_key_exists($part,$actual) && (string)$actual[$part]!=='') return false;
    }
    $apex=str_starts_with($configuredHost,'www.') ? substr($configuredHost,4) : $configuredHost;
    return $requestHost===$apex || $requestHost==='www.'.$apex;
}
function request_json(): array {
    // Browsers may use the www or non-www hostname. Both belong to this site.
    $origin=(string)($_SERVER['HTTP_ORIGIN']??'');
    if ($origin!=='' && !allowed_site_origin($origin)) {
        $actual=parse_url($origin);
        $observed=is_array($actual)?strtolower((string)($actual['host']??'')):'invalid';
        // Only public hostname metadata is logged; no customer/payment credentials.
        error_log('Oregon Pro Plumbing origin check rejected host: '.$observed);
        fail('Request origin not allowed.',403);
    }
    if (!str_contains((string)($_SERVER['CONTENT_TYPE']??''),'application/json')) fail('Expected JSON.',415);
    $raw=file_get_contents('php://input',false,null,0,16384);
    $data=json_decode((string)$raw,true);
    if (!is_array($data)) fail('Invalid request.');
    return $data;
}
function clean(mixed $value,int $max=160): string {
    if (!is_scalar($value)) return '';
    $s=trim((string)$value);
    $s=preg_replace('/[\x00-\x1F\x7F]/u',' ',$s)??'';
    return function_exists('mb_substr')?mb_substr($s,0,$max,'UTF-8'):substr($s,0,$max);
}
/** A bearer payment token is separate from the internal OPP booking reference. */
function ensure_payment_token_table(): void {
    $pdo=db();
    if ((string)config('DB_DRIVER','sqlite')==='mysql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS booking_payment_tokens (
          token_hash CHAR(64) PRIMARY KEY, booking_code VARCHAR(32) NOT NULL,
          expires_at BIGINT NOT NULL, created_at VARCHAR(40) NOT NULL,
          INDEX payment_tokens_booking_idx (booking_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $pdo->exec('CREATE TABLE IF NOT EXISTS booking_payment_tokens (
          token_hash TEXT PRIMARY KEY, booking_code TEXT NOT NULL,
          expires_at INTEGER NOT NULL, created_at TEXT NOT NULL)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS payment_tokens_booking_idx ON booking_payment_tokens (booking_code)');
    }
}
function new_payment_token(string $bookingCode): string {
    // Requires ensure_payment_token_table() before calling.
    $token=bin2hex(random_bytes(32));
    db()->prepare('INSERT INTO booking_payment_tokens (token_hash,booking_code,expires_at,created_at) VALUES (?,?,?,?)')
      ->execute([hash('sha256',$token),$bookingCode,time()+90*86400,gmdate('c')]);
    return $token;
}
function booking_from_payment_token(string $token): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/D',$token)) return null;
    ensure_payment_token_table();
    $q=db()->prepare('SELECT b.* FROM booking_payment_tokens t INNER JOIN bookings b ON b.code=t.booking_code WHERE t.token_hash=? AND t.expires_at>? LIMIT 1');
    $q->execute([hash('sha256',$token),time()]);
    return $q->fetch() ?: null;
}
function get_booking(string $code): ?array {
    $q=db()->prepare('SELECT * FROM bookings WHERE code=?');$q->execute([$code]);return $q->fetch()?:null;
}
function public_booking(array $b): array {
    $amount=$b['estimate_cents']===null?null:(int)$b['estimate_cents'];$paid=(int)$b['paid_cents'];
    return ['code'=>$paid>0?$b['code']:null,'service'=>PRICE_DEFAULTS[$b['service_id']]['name'],'serviceId'=>$b['service_id'],
      'area'=>$b['city'],'status'=>(int)$b['status'],'created'=>$b['created_at'],
      'initialQuote'=>$amount===null?null:$amount/100,'callFee'=>fee_cents()/100,'paidAmount'=>$paid/100,
      'paymentStatus'=>$paid===0?'unpaid':($amount!==null&&$paid>=$amount?'paid':'partial')];
}
function stripe_request(string $method,string $endpoint,?array $fields=null,string $idempotencyKey=''): array {
    if (!stripe_api_ready()) throw new RuntimeException('Stripe API is not configured.');
    $ch=curl_init('https://api.stripe.com/v1/'.$endpoint);
    if ($ch===false) throw new RuntimeException('Cannot initialize the payment connection.');
    $headers=['Authorization: Bearer '.config('STRIPE_SECRET_KEY'),'User-Agent: OregonProPlumbing/1.0', 'Accept: application/json'];
    if ($idempotencyKey!=='') $headers[]='Idempotency-Key: '.$idempotencyKey;
    $options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>25,CURLOPT_HTTPHEADER=>$headers,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2];
    if ($method==='POST') {
        $options[CURLOPT_POST]=true;
        $options[CURLOPT_POSTFIELDS]=http_build_query($fields??[],'','&',PHP_QUERY_RFC3986);
    }
    curl_setopt_array($ch,$options);
    $response=curl_exec($ch);$error=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if ($response===false) throw new RuntimeException('Stripe connection failed: '.$error);
    $decoded=json_decode($response,true);
    if (!is_array($decoded)||$status<200||$status>=300) {
        $desc=$decoded['error']['message']??'Stripe service error';
        throw new RuntimeException('Stripe request failed ('.$status.'): '.clean($desc,220));
    }
    return $decoded;
}
function verify_stripe_signature(string $payload,string $signature,string $secret,int $tolerance=300): bool {
    if ($payload===''||$signature===''||$secret==='') return false;
    $time=null;$candidates=[];
    foreach (explode(',',$signature) as $segment) {
        $pair=explode('=',trim($segment),2);
        if (count($pair)!==2) continue;
        if ($pair[0]==='t' && ctype_digit($pair[1])) $time=(int)$pair[1];
        if ($pair[0]==='v1' && preg_match('/^[a-f0-9]{64}$/i',$pair[1])) $candidates[]=strtolower($pair[1]);
    }
    if ($time===null||abs(time()-$time)>$tolerance||!$candidates) return false;
    $expected=hash_hmac('sha256',$time.'.'.$payload,$secret);
    foreach ($candidates as $candidate) if (hash_equals($expected,$candidate)) return true;
    return false;
}
function sync_payment_session(array $session): void {
    $sid=(string)($session['id']??'');
    if ($sid===''||($session['payment_status']??'')!=='paid') return;
    $db=db();$db->beginTransaction();
    try {
        $q=$db->prepare('SELECT * FROM payments WHERE session_id=?');$q->execute([$sid]);$payment=$q->fetch();
        if (!$payment) { $db->rollBack(); throw new RuntimeException('Unknown checkout session.'); }
        if ($payment['status']==='paid') { $db->commit();return; }
        if ((int)($session['amount_total']??-1)!==(int)$payment['amount_cents']
            || strtolower((string)($session['currency']??''))!=='usd'
            || (string)($session['metadata']['booking_code']??'')!==$payment['booking_code']
            || (string)($session['client_reference_id']??'')!==$payment['booking_code']) {
            $db->rollBack();throw new RuntimeException('Stripe payment verification did not match booking.');
        }
        $db->prepare("UPDATE payments SET status='paid', paid_at=? WHERE session_id=?")->execute([gmdate('c'),$sid]);
        $db->prepare('UPDATE bookings SET paid_cents=paid_cents+?,updated_at=? WHERE code=?')
           ->execute([(int)$payment['amount_cents'],gmdate('c'),$payment['booking_code']]);
        $db->commit();
    } catch (Throwable $e) { if($db->inTransaction()) $db->rollBack();throw $e; }
}
function h(mixed $v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
if (session_status()!==PHP_SESSION_ACTIVE && PHP_SAPI!=='cli') {
    ini_set('session.use_strict_mode','1');ini_set('session.cookie_httponly','1');
    ini_set('session.cookie_samesite','Lax');session_name('oregon_pro_plumbing');
    session_set_cookie_params(['httponly'=>true,'secure'=>((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||secure_app_url()),'samesite'=>'Lax','path'=>'/']);
    session_start();
}
