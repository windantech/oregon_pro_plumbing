<?php
declare(strict_types=1);
// Oregon Pro Plumbing SMTP transport. No local mail() function or Composer required.
// This file is loaded by the booking endpoint and authenticated administrator settings.
// Only authenticated SMTP over verified TLS is supported.
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) { http_response_code(403); exit; }

function opp_smtp_values(?array $values = null): array {
    $c = $values ?? app_config();
    return [
      'SMTP_ENABLED' => (string)($c['SMTP_ENABLED'] ?? '0'),
      'SMTP_HOST' => trim((string)($c['SMTP_HOST'] ?? '')),
      'SMTP_PORT' => (int)($c['SMTP_PORT'] ?? 465),
      'SMTP_ENCRYPTION' => (string)($c['SMTP_ENCRYPTION'] ?? 'ssl'),
      'SMTP_USERNAME' => trim((string)($c['SMTP_USERNAME'] ?? '')),
      'SMTP_PASSWORD' => (string)($c['SMTP_PASSWORD'] ?? ''),
      'SMTP_FROM_EMAIL' => trim((string)($c['SMTP_FROM_EMAIL'] ?? '')),
      'SMTP_FROM_NAME' => trim((string)($c['SMTP_FROM_NAME'] ?? 'Oregon Pro Plumbing')),
      'BOOKING_NOTIFY_EMAIL' => trim((string)($c['BOOKING_NOTIFY_EMAIL'] ?? '')),
      'APP_URL' => trim((string)($c['APP_URL'] ?? ''))
    ];
}

function opp_smtp_validate(array $values): void {
    $c = opp_smtp_values($values);
    if (!extension_loaded('openssl')) throw new RuntimeException('Your host needs the PHP OpenSSL extension to send secure SMTP email.');
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9.\-]{0,251}[A-Za-z0-9]$/D', $c['SMTP_HOST'])
        && !preg_match('/^[A-Za-z0-9]$/D', $c['SMTP_HOST'])) throw new RuntimeException('Enter a valid SMTP server hostname (for example mail.yourdomain.com).');
    if ($c['SMTP_PORT'] < 1 || $c['SMTP_PORT'] > 65535) throw new RuntimeException('SMTP port must be between 1 and 65535.');
    if (!in_array($c['SMTP_ENCRYPTION'], ['ssl','tls'], true)) throw new RuntimeException('Select SSL/TLS (port 465) or STARTTLS (port 587).');
    if ($c['SMTP_USERNAME'] === '' || strlen($c['SMTP_USERNAME']) > 254) throw new RuntimeException('Enter your SMTP username.');
    if ($c['SMTP_PASSWORD'] === '' || strlen($c['SMTP_PASSWORD']) > 4096) throw new RuntimeException('Enter your SMTP password or app password.');
    if (!filter_var($c['SMTP_FROM_EMAIL'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid sender email address.');
    if ($c['SMTP_FROM_NAME'] === '' || strlen($c['SMTP_FROM_NAME']) > 100 || preg_match('/[\r\n]/', $c['SMTP_FROM_NAME'])) throw new RuntimeException('Enter a sender name without line breaks.');
    if (!filter_var($c['BOOKING_NOTIFY_EMAIL'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter an email address to receive booking notifications.');
}

function opp_smtp_ready(?array $values = null): bool {
    $c = opp_smtp_values($values);
    if ($c['SMTP_ENABLED'] !== '1') return false;
    try { opp_smtp_validate($c); return true; } catch (Throwable) { return false; }
}

/** @return array{0:int,1:string} */
function opp_smtp_reply($socket): array {
    $response = '';
    for ($i = 0; $i < 40; $i++) {
        $line = @fgets($socket, 8192);
        if ($line === false) {
            $meta = stream_get_meta_data($socket);
            throw new RuntimeException(!empty($meta['timed_out']) ? 'SMTP server timed out.' : 'SMTP server closed the connection.');
        }
        if (strlen($response) > 16384) throw new RuntimeException('Unexpectedly large SMTP reply.');
        $response .= $line;
        if (preg_match('/^(\d{3}) ([^\r\n]*)/', $line, $parts)) return [(int)$parts[1], $response];
        if (!preg_match('/^\d{3}-/', $line)) throw new RuntimeException('Unexpected response from the SMTP server.');
    }
    throw new RuntimeException('SMTP server sent too many response lines.');
}

function opp_smtp_write($socket, string $line): void {
    $total = strlen($line);
    for ($offset = 0; $offset < $total;) {
        $sent = @fwrite($socket, substr($line, $offset));
        if ($sent === false || $sent === 0) throw new RuntimeException('Could not send data to the SMTP server.');
        $offset += $sent;
    }
}

/** @return array{0:int,1:string} */
function opp_smtp_command($socket, string $command): array {
    opp_smtp_write($socket, $command . "\r\n");
    return opp_smtp_reply($socket);
}

function opp_smtp_expect(array $reply, array $allowed, string $stage): void {
    if (!in_array($reply[0], $allowed, true)) {
        // Do not expose remote-server diagnostic text; SMTP servers can echo PII.
        throw new RuntimeException($stage.' failed (SMTP code '.$reply[0].'). Check your email account settings.');
    }
}

function opp_smtp_send(string $to, string $subject, string $body, ?string $replyTo = null, ?array $values = null, ?string $customerBookingCode = null): void {
    $c = opp_smtp_values($values);
    opp_smtp_validate($c); // The admin's send-test action may run while alerts are disabled.
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Invalid email recipient.');
    }
    // The business inbox remains permitted for admin alerts and SMTP tests.
    // Customer delivery is allowed ONLY for the exact email stored on the specific booking.
    // A booking form cannot turn this SMTP transport into an open mail relay.
    $isAdminRecipient = filter_var($c['BOOKING_NOTIFY_EMAIL'], FILTER_VALIDATE_EMAIL)
        && hash_equals(strtolower($c['BOOKING_NOTIFY_EMAIL']), strtolower($to));
    $isBookingRecipient = false;
    if ($customerBookingCode !== null && preg_match('/^OPP-[A-F0-9]{24}$/D', $customerBookingCode)) {
        $storedBooking = get_booking($customerBookingCode);
        $savedEmail = trim((string)($storedBooking['email'] ?? ''));
        $isBookingRecipient = filter_var($savedEmail, FILTER_VALIDATE_EMAIL)
            && hash_equals(strtolower($savedEmail), strtolower($to));
    }
    if (!$isAdminRecipient && !$isBookingRecipient) {
        throw new RuntimeException('Recipient is not an authorized business inbox or booking customer.');
    }
    if (preg_match('/[\r\n]/', $subject) || strlen($subject) > 180) throw new RuntimeException('Invalid email subject.');
    if ($replyTo !== null && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $replyTo = null;
    if (strlen($body) > 16000) throw new RuntimeException('Email message is too large.');

    $host = $c['SMTP_HOST']; $port = $c['SMTP_PORT'];
    $context = stream_context_create(['ssl' => [
      'verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false,
      'peer_name' => $host, 'SNI_enabled' => true,
    ]]);
    $target = ($c['SMTP_ENCRYPTION'] === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $errorNumber = 0; $errorText = '';
    $socket = @stream_socket_client($target, $errorNumber, $errorText, 8, STREAM_CLIENT_CONNECT, $context);
    if (!is_resource($socket)) throw new RuntimeException('Could not connect to the SMTP host. Check hostname, port, encryption, SSL certificate, and outbound SMTP hosting restrictions.');
    stream_set_timeout($socket, 12);
    try {
        opp_smtp_expect(opp_smtp_reply($socket), [220], 'SMTP greeting');
        $ehloHost = parse_url($c['APP_URL'], PHP_URL_HOST) ?: 'oregonproplumbing.com';
        if (!preg_match('/^[a-zA-Z0-9.\-]+$/D', $ehloHost)) $ehloHost = 'oregonproplumbing.com';
        $ehlo = opp_smtp_command($socket, 'EHLO '.$ehloHost);
        opp_smtp_expect($ehlo, [250], 'SMTP greeting');
        if ($c['SMTP_ENCRYPTION'] === 'tls') {
            if (!preg_match('/(?:^|\r\n)250[- ]STARTTLS(?:\r\n|$)/i', $ehlo[1])) {
                throw new RuntimeException('The SMTP server does not offer STARTTLS on this port. Use port 587 with STARTTLS, or port 465 with SSL/TLS.');
            }
            opp_smtp_expect(opp_smtp_command($socket, 'STARTTLS'), [220], 'STARTTLS');
            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Could not establish secure TLS with the SMTP server. Check its certificate and hostname.');
            }
            $ehlo = opp_smtp_command($socket, 'EHLO '.$ehloHost);
            opp_smtp_expect($ehlo, [250], 'Secure SMTP greeting');
        }
        $advertised = $ehlo[1];
        if (preg_match('/(?:^|\r\n)250[- ]AUTH[^\r\n]*\bPLAIN\b/i', $advertised)) {
            $auth = base64_encode("\0".$c['SMTP_USERNAME']."\0".$c['SMTP_PASSWORD']);
            opp_smtp_expect(opp_smtp_command($socket, 'AUTH PLAIN '.$auth), [235], 'SMTP authentication');
        } elseif (preg_match('/(?:^|\r\n)250[- ]AUTH[^\r\n]*\bLOGIN\b/i', $advertised)) {
            opp_smtp_expect(opp_smtp_command($socket, 'AUTH LOGIN'), [334], 'SMTP authentication');
            opp_smtp_expect(opp_smtp_command($socket, base64_encode($c['SMTP_USERNAME'])), [334], 'SMTP username');
            opp_smtp_expect(opp_smtp_command($socket, base64_encode($c['SMTP_PASSWORD'])), [235], 'SMTP password');
        } else {
            throw new RuntimeException('SMTP server did not advertise supported password authentication after TLS.');
        }
        opp_smtp_expect(opp_smtp_command($socket, 'MAIL FROM:<'.$c['SMTP_FROM_EMAIL'].'>'), [250], 'Sender');
        opp_smtp_expect(opp_smtp_command($socket, 'RCPT TO:<'.$to.'>'), [250,251], 'Recipient');
        opp_smtp_expect(opp_smtp_command($socket, 'DATA'), [354], 'Message submission');

        $domain = parse_url($c['APP_URL'], PHP_URL_HOST) ?: 'oregonproplumbing.com';
        $name = '=?UTF-8?B?'.base64_encode($c['SMTP_FROM_NAME']).'?=';
        // Base64 body avoids header injection and preserves Unicode customer details.
        $headers = [
          'Date: '.gmdate('D, d M Y H:i:s').' +0000',
          'From: '.$name.' <'.$c['SMTP_FROM_EMAIL'].'>',
          'To: <'.$to.'>',
          'Subject: '.(preg_match('/[^\x20-\x7E]/', $subject) ? '=?UTF-8?B?'.base64_encode($subject).'?=' : $subject),
          'Message-ID: <'.bin2hex(random_bytes(12)).'@'.$domain.'>',
          'MIME-Version: 1.0',
          'Content-Type: text/plain; charset=UTF-8',
          'Content-Transfer-Encoding: base64',
          'X-Mailer: Oregon Pro Plumbing'
        ];
        if ($replyTo !== null) $headers[] = 'Reply-To: <'.$replyTo.'>';
        $message = implode("\r\n", $headers)."\r\n\r\n".chunk_split(base64_encode($body), 76, "\r\n");
        // Dot-stuff any line starting with a dot as required by SMTP.
        $message = preg_replace('/(^|\r\n)\./', '$1..', $message) ?? $message;
        opp_smtp_write($socket, $message."\r\n.\r\n");
        opp_smtp_expect(opp_smtp_reply($socket), [250], 'Email acceptance');
        try { opp_smtp_command($socket, 'QUIT'); } catch (Throwable) { /* Message already accepted. */ }
    } finally { fclose($socket); }
}

function opp_notify_new_booking(array $booking): void {
    if (!opp_smtp_ready()) return;
    $code = (string)$booking['code'];
    $service = PRICE_DEFAULTS[$booking['service_id']]['name'] ?? 'Plumbing service';
    $text = "NEW BOOKING — OREGON PRO PLUMBING\n\n"
      . "Reference: {$code}\n"
      . "Service: {$service}\n"
      . "Requested timing: {$booking['urgency']}\n"
      . "Customer: {$booking['first_name']} {$booking['last_name']}\n"
      . "Phone: {$booking['phone']}\n"
      . "Email: {$booking['email']}\n"
      . "Address: {$booking['street']}, {$booking['city']}, OR {$booking['zip']}\n"
      . "Notes: {$booking['details']}\n\n"
      . 'Review requests: '.rtrim((string)config('APP_URL'),'/')."/admin/\n";
    $reply = filter_var((string)$booking['email'], FILTER_VALIDATE_EMAIL) ? (string)$booking['email'] : null;
    opp_smtp_send((string)config('BOOKING_NOTIFY_EMAIL'), 'New plumbing booking '.$code, $text, $reply);
}


/** Customer emails never disclose the private booking code before verified payment. */
function opp_notify_customer_booking(array $booking): void {
    if (!opp_smtp_ready()) return;
    $email=trim((string)($booking['email']??''));
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) return;
    $code=(string)($booking['code']??'');
    $first=clean($booking['first_name']??'Customer',80);
    $service=PRICE_DEFAULTS[(string)($booking['service_id']??'')]['name']??'Plumbing service';
    $date=substr((string)($booking['created_at']??''),0,10);
    $paid=(int)($booking['paid_cents']??0)>0;
    $body="Hi {$first},\n\n"
      ."Thank you for contacting Oregon Pro Plumbing. We received your service request.\n\n"
      ."Service: {$service}\n"
      ."Requested timing: ".clean($booking['urgency']??'',70)."\n"
      ."Location: ".clean($booking['city']??'',80).", Oregon\n"
      .($date!==''?"Date received: {$date}\n":'')
      ."\nYour request has been recorded. Our team will review it and contact you to confirm scheduling and availability.\n"
      ."Submitting a request does not mean a technician has been dispatched.\n\n";
    if ($paid) $body.="Booking reference: {$code}\n\n";
    else $body.="Please keep an eye on your email for updates from our team.\n\n";
    $body.="Oregon Pro Plumbing\n".rtrim((string)config('APP_URL'),'/' )."\n";
    opp_smtp_send($email,'We received your plumbing request | Oregon Pro Plumbing',$body,null,null,$code);
}

/** Only changed job stages trigger emails, and unpaid requests never expose the reference. */
function opp_notify_customer_stage_change(array $booking, int $previous, int $current): void {
    if (!opp_smtp_ready() || $current===$previous) return;
    $email=trim((string)($booking['email']??''));
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) return;
    $labels=['Booked','Assigned','On the way','On site','Done'];
    if (!isset($labels[$current])) return;
    $code=(string)($booking['code']??'');
    $first=clean($booking['first_name']??'Customer',80);
    $service=PRICE_DEFAULTS[(string)($booking['service_id']??'')]['name']??'Plumbing service';
    $paid=(int)($booking['paid_cents']??0)>0;
    $stage=$labels[$current];
    $body="Hi {$first},\n\n"
      ."There is an update to your Oregon Pro Plumbing service request.\n\n"
      ."Service: {$service}\n"
      ."Current status: {$stage}\n"
      ."Location: ".clean($booking['city']??'',80).", Oregon\n\n";
    if ($paid) {
        $body.="Booking reference: {$code}\n"
          ."You can view your job status at ".rtrim((string)config('APP_URL'),'/')."/#jobs\n\n";
    } else {
        $body.="Our team will confirm scheduling and availability directly.\n\n";
    }
    $body.="If you have questions, simply reply to this email or contact our team.\n\nOregon Pro Plumbing\n";
    opp_smtp_send($email,'Plumbing request update: '.$stage.' | Oregon Pro Plumbing',$body,null,null,$code);
}
