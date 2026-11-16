<?php
declare(strict_types=1);
// WhatsApp inbox for the staff portal. Uses the portal's existing Twilio Account SID + Auth Token.
// app/ is denied to web visitors by app/.htaccess; endpoints in api/ include this file.
require_once __DIR__.'/core.php';

function wa_number(): string {
    return number_e164((string)cfg('whatsapp_number')) ?: number_e164((string)cfg('public_number'));
}
function wa_ready(): bool {
    return (bool)preg_match('/^AC[a-f0-9]{32}$/i', (string)cfg('twilio_account_sid'))
        && strlen((string)cfg('twilio_auth_token')) >= 16 && wa_number() !== '';
}
function wa_now(): string { return gmdate('Y-m-d H:i:s'); }

function wa_schema(): void {
    static $done = false; if ($done) return; $done = true;
    $db = database();
    if ((string)cfg('db_driver', 'sqlite') === 'mysql') {
        $db->exec('CREATE TABLE IF NOT EXISTS wa_messages (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, sid VARCHAR(64) NULL UNIQUE,
            contact VARCHAR(20) NOT NULL, direction VARCHAR(4) NOT NULL, body TEXT NOT NULL, media TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT \'\', error_code VARCHAR(10) NOT NULL DEFAULT \'\',
            profile_name VARCHAR(120) NOT NULL DEFAULT \'\', agent_id INT UNSIGNED NULL,
            is_read TINYINT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            INDEX wa_contact_idx (contact, id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS wa_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT, sid TEXT UNIQUE, contact TEXT NOT NULL, direction TEXT NOT NULL,
            body TEXT NOT NULL, media TEXT, status TEXT NOT NULL DEFAULT '', error_code TEXT NOT NULL DEFAULT '',
            profile_name TEXT NOT NULL DEFAULT '', agent_id INTEGER, is_read INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
        $db->exec('CREATE INDEX IF NOT EXISTS wa_contact_idx ON wa_messages (contact, id)');
    }
}

/** "whatsapp:+15035550123" -> "+15035550123" */
function wa_contact(string $address): string {
    if (!str_starts_with($address, 'whatsapp:')) return '';
    return number_e164(substr($address, 9));
}

function wa_text(string $v, int $max): string {
    $v = str_replace("\0", '', $v);
    return function_exists('mb_substr') ? mb_substr($v, 0, $max, 'UTF-8') : substr($v, 0, $max);
}

/** Customer service window: free-form replies are allowed for 24h after the customer's last message. */
function wa_last_inbound(string $contact): ?string {
    $q = database()->prepare("SELECT MAX(created_at) FROM wa_messages WHERE contact=? AND direction='in'");
    $q->execute([$contact]);
    $v = $q->fetchColumn();
    return $v ? (string)$v : null;
}
function wa_window_open(?string $lastInbound): bool {
    if (!$lastInbound) return false;
    $t = strtotime($lastInbound.' UTC');
    return $t !== false && $t > time() - 86400 + 60;
}

function wa_twilio_post(string $path, array $fields): array {
    $account = (string)cfg('twilio_account_sid');
    $ch = curl_init('https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode($account).'/'.$path);
    if ($ch === false) throw new RuntimeException('Cannot connect to Twilio.');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields, '', '&', PHP_QUERY_RFC3986),
        CURLOPT_USERPWD => $account.':'.(string)cfg('twilio_auth_token'),
        CURLOPT_HTTPHEADER => ['Accept: application/json'], CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($ch); $err = curl_error($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($raw === false) throw new RuntimeException('Twilio connection failed: '.$err);
    $json = json_decode((string)$raw, true);
    if (!is_array($json) || $status < 200 || $status >= 300) {
        $code = is_array($json) ? (string)($json['code'] ?? '') : '';
        $hint = $code === '63016' ? ' The 24-hour reply window has closed.' : '';
        throw new RuntimeException('Twilio rejected the message'.($code !== '' ? ' (error '.$code.')' : '').'.'.$hint);
    }
    return $json;
}

function wa_send_text(string $contact, string $body, int $agentId): array {
    if (!wa_ready()) throw new RuntimeException('WhatsApp is not configured. An administrator must save the Twilio Account SID, Auth Token and WhatsApp number in Settings.');
    $body = trim(wa_text($body, 1600));
    if ($body === '') throw new RuntimeException('Type a message first.');
    if (!wa_window_open(wa_last_inbound($contact))) throw new RuntimeException('More than 24 hours have passed since this customer last messaged. WhatsApp only allows approved templates until they write again.');
    $res = wa_twilio_post('Messages.json', [
        'From' => 'whatsapp:'.wa_number(), 'To' => 'whatsapp:'.$contact, 'Body' => $body,
        'StatusCallback' => public_url('api/whatsapp-status.php'),
    ]);
    $sid = (string)($res['sid'] ?? '');
    $now = wa_now();
    database()->prepare('INSERT INTO wa_messages (sid,contact,direction,body,media,status,agent_id,is_read,created_at,updated_at) VALUES (?,?,?,?,NULL,?,?,1,?,?)')
        ->execute([$sid !== '' ? $sid : null, $contact, 'out', $body, (string)($res['status'] ?? 'queued'), $agentId, $now, $now]);
    return ['sid' => $sid, 'status' => (string)($res['status'] ?? 'queued')];
}
