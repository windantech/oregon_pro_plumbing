<?php
declare(strict_types=1);
// Oregon Pro Plumbing WhatsApp notifications via the Twilio Messages API.
// Business-initiated WhatsApp messages MUST use pre-approved Content Templates (ContentSid HX…).
// Customers only receive WhatsApp messages when they ticked the opt-in box on the booking form.
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) { http_response_code(403); exit; }
require_once __DIR__.'/bootstrap.php';

const OPP_WA_STAGE_TEXT = [
    0 => 'Booked',
    1 => 'A technician has been assigned',
    2 => 'Your technician is on the way',
    3 => 'Your technician is on site',
    4 => 'Job completed',
];

function opp_wa_values(?array $values = null): array {
    $c = $values ?? app_config();
    return [
        'WA_ENABLED' => (string)($c['WA_ENABLED'] ?? '0'),
        'TWILIO_ACCOUNT_SID' => trim((string)($c['TWILIO_ACCOUNT_SID'] ?? '')),
        'TWILIO_AUTH_TOKEN' => (string)($c['TWILIO_AUTH_TOKEN'] ?? ''),
        'WA_FROM' => trim((string)($c['WA_FROM'] ?? '')),
        'WA_BUSINESS_TO' => trim((string)($c['WA_BUSINESS_TO'] ?? '')),
        'WA_TPL_BUSINESS' => trim((string)($c['WA_TPL_BUSINESS'] ?? '')),
        'WA_TPL_CUSTOMER_BOOKING' => trim((string)($c['WA_TPL_CUSTOMER_BOOKING'] ?? '')),
        'WA_TPL_CUSTOMER_STAGE' => trim((string)($c['WA_TPL_CUSTOMER_STAGE'] ?? '')),
    ];
}

/** Strict E.164 (+15035550123). Returns '' when invalid. */
function opp_wa_e164(string $value): string {
    $clean = preg_replace('/[\s()\-.]/', '', $value) ?? '';
    return preg_match('/^\+[1-9]\d{7,14}$/D', $clean) ? $clean : '';
}

/** Customer phones are typed freely on the form; US 10-digit numbers get +1. */
function opp_wa_customer_number(string $phone): string {
    $phone = trim($phone);
    if (str_starts_with($phone, '+')) return opp_wa_e164($phone);
    $digits = preg_replace('/\D/', '', $phone) ?? '';
    if (strlen($digits) === 10 && $digits[0] !== '0' && $digits[0] !== '1') return '+1'.$digits;
    if (strlen($digits) === 11 && $digits[0] === '1') return '+'.$digits;
    return '';
}

function opp_wa_valid_template(string $sid): bool { return (bool)preg_match('/^HX[a-f0-9]{32}$/iD', $sid); }

/** Throws with a readable reason when the core Twilio settings are unusable. */
function opp_wa_validate(array $values): void {
    $c = opp_wa_values($values);
    if (!extension_loaded('curl')) throw new RuntimeException('WhatsApp notifications need the PHP cURL extension.');
    if (!preg_match('/^AC[a-f0-9]{32}$/iD', $c['TWILIO_ACCOUNT_SID'])) throw new RuntimeException('Twilio Account SID should start with AC followed by 32 characters.');
    if (strlen($c['TWILIO_AUTH_TOKEN']) < 16 || strlen($c['TWILIO_AUTH_TOKEN']) > 256) throw new RuntimeException('Enter your Twilio Auth Token.');
    if (opp_wa_e164($c['WA_FROM']) === '') throw new RuntimeException('WhatsApp sender must be in +1… international format.');
    if ($c['WA_BUSINESS_TO'] !== '' && opp_wa_e164($c['WA_BUSINESS_TO']) === '') throw new RuntimeException('Business WhatsApp number must be in international format, e.g. +2547… or +1503…');
    foreach (['WA_TPL_BUSINESS' => 'New-booking alert', 'WA_TPL_CUSTOMER_BOOKING' => 'Customer request-received', 'WA_TPL_CUSTOMER_STAGE' => 'Customer status-update'] as $key => $label) {
        if ($c[$key] !== '' && !opp_wa_valid_template($c[$key])) throw new RuntimeException($label.' template SID should start with HX followed by 32 characters.');
    }
}

function opp_wa_ready(?array $values = null): bool {
    $c = opp_wa_values($values);
    if ($c['WA_ENABLED'] !== '1') return false;
    try { opp_wa_validate($c); return true; } catch (Throwable) { return false; }
}

/** WhatsApp template variables may not contain newlines/tabs or long runs of spaces, and cannot be empty. */
function opp_wa_var(mixed $value, int $max = 120): string {
    $s = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    $s = function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
    return $s === '' ? '-' : $s;
}

/** Sends one template message. Returns the Twilio message SID (SM…/MM…). */
function opp_wa_send(string $to, string $templateSid, array $variables, ?array $values = null): string {
    $c = opp_wa_values($values);
    opp_wa_validate($c);
    $to = opp_wa_e164($to);
    if ($to === '') throw new RuntimeException('Invalid WhatsApp recipient number.');
    if (!opp_wa_valid_template($templateSid)) throw new RuntimeException('WhatsApp template is not configured.');
    $vars = [];
    foreach (array_values($variables) as $i => $v) $vars[(string)($i + 1)] = opp_wa_var($v);
    $fields = [
        'From' => 'whatsapp:'.opp_wa_e164($c['WA_FROM']),
        'To' => 'whatsapp:'.$to,
        'ContentSid' => $templateSid,
        'ContentVariables' => json_encode($vars, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ];
    $ch = curl_init('https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode($c['TWILIO_ACCOUNT_SID']).'/Messages.json');
    if ($ch === false) throw new RuntimeException('Cannot initialize the WhatsApp connection.');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields, '', '&', PHP_QUERY_RFC3986),
        CURLOPT_USERPWD => $c['TWILIO_ACCOUNT_SID'].':'.$c['TWILIO_AUTH_TOKEN'],
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: OregonProPlumbing/1.0'],
        CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $response = curl_exec($ch); $error = curl_error($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($response === false) throw new RuntimeException('Twilio connection failed: '.$error);
    $json = json_decode((string)$response, true);
    if (!is_array($json) || $status < 200 || $status >= 300) {
        // Twilio error codes are documented at twilio.com/docs/api/errors (e.g. 63016 = outside 24h window, 21656 = bad variables).
        $code = is_array($json) ? (string)($json['code'] ?? '') : '';
        $msg = is_array($json) ? clean($json['message'] ?? 'Twilio error', 200) : 'Twilio error';
        throw new RuntimeException('WhatsApp send failed (HTTP '.$status.($code !== '' ? ', Twilio error '.$code : '').'): '.$msg);
    }
    return (string)($json['sid'] ?? '');
}

/* ---------- Customer opt-in (separate table: safe automatic migration on existing installs) ---------- */
function opp_wa_optin_table(): void {
    $pdo = db();
    if ((string)config('DB_DRIVER', 'sqlite') === 'mysql') {
        $pdo->exec('CREATE TABLE IF NOT EXISTS booking_whatsapp_optin (booking_code VARCHAR(32) PRIMARY KEY, created_at VARCHAR(40) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    } else {
        $pdo->exec('CREATE TABLE IF NOT EXISTS booking_whatsapp_optin (booking_code TEXT PRIMARY KEY, created_at TEXT NOT NULL)');
    }
}
function opp_wa_record_optin(string $code): void {
    opp_wa_optin_table();
    $driver = (string)config('DB_DRIVER', 'sqlite');
    $sql = $driver === 'mysql'
        ? 'INSERT IGNORE INTO booking_whatsapp_optin (booking_code, created_at) VALUES (?, ?)'
        : 'INSERT OR IGNORE INTO booking_whatsapp_optin (booking_code, created_at) VALUES (?, ?)';
    db()->prepare($sql)->execute([$code, gmdate('c')]);
}
function opp_wa_has_optin(string $code): bool {
    opp_wa_optin_table();
    $q = db()->prepare('SELECT 1 FROM booking_whatsapp_optin WHERE booking_code = ?');
    $q->execute([$code]);
    return (bool)$q->fetchColumn();
}

/* ---------- Notifications ---------- */
function opp_wa_service_name(array $b): string { return PRICE_DEFAULTS[(string)($b['service_id'] ?? '')]['name'] ?? 'Plumbing service'; }

/**
 * Business alert template variables:
 * {{1}} booking reference, {{2}} service, {{3}} city, {{4}} customer name, {{5}} customer phone, {{6}} timing
 */
function opp_wa_notify_new_booking(array $b): void {
    $c = opp_wa_values();
    if (!opp_wa_ready() || $c['WA_BUSINESS_TO'] === '' || $c['WA_TPL_BUSINESS'] === '') return;
    opp_wa_send($c['WA_BUSINESS_TO'], $c['WA_TPL_BUSINESS'], [
        $b['code'] ?? '', opp_wa_service_name($b), ($b['city'] ?? '').', OR',
        trim(($b['first_name'] ?? '').' '.($b['last_name'] ?? '')), $b['phone'] ?? '', $b['urgency'] ?? '',
    ]);
}

/** Customer request-received template: {{1}} first name, {{2}} service, {{3}} city */
function opp_wa_notify_customer_booking(array $b): string {
    $c = opp_wa_values();
    if (!opp_wa_ready() || $c['WA_TPL_CUSTOMER_BOOKING'] === '') return 'unavailable';
    if (!opp_wa_has_optin((string)($b['code'] ?? ''))) return 'no_optin';
    $to = opp_wa_customer_number((string)($b['phone'] ?? ''));
    if ($to === '') return 'bad_number';
    opp_wa_send($to, $c['WA_TPL_CUSTOMER_BOOKING'], [$b['first_name'] ?? 'there', opp_wa_service_name($b), $b['city'] ?? 'Oregon']);
    return 'sent';
}

/** Customer status-update template: {{1}} first name, {{2}} service, {{3}} status text */
function opp_wa_notify_customer_stage(array $b, int $previous, int $current): string {
    if ($previous === $current || !isset(OPP_WA_STAGE_TEXT[$current])) return 'unchanged';
    $c = opp_wa_values();
    if (!opp_wa_ready() || $c['WA_TPL_CUSTOMER_STAGE'] === '') return 'unavailable';
    if (!opp_wa_has_optin((string)($b['code'] ?? ''))) return 'no_optin';
    $to = opp_wa_customer_number((string)($b['phone'] ?? ''));
    if ($to === '') return 'bad_number';
    opp_wa_send($to, $c['WA_TPL_CUSTOMER_STAGE'], [$b['first_name'] ?? 'there', opp_wa_service_name($b), OPP_WA_STAGE_TEXT[$current]]);
    return 'sent';
}
