# Oregon Pro Plumbing — SMTP Email Upgrade

This is an **update to your already-installed website**, not a new installation. It does not change the booking database, Stripe configuration, or public pages.

## Upload using cPanel / File Manager (no terminal)

1. Before uploading, download backups of these website files: `api/bookings.php`, `admin/index.php`, `admin/settings.php`.
2. Open your site's document root (commonly `public_html`) using File Manager.
3. Upload `Oregon_Pro_Plumbing_SMTP_Update.zip` and choose **Extract** in that folder.
4. Let File Manager overwrite the existing **three** PHP files. The update also adds `includes/smtp.php`.
5. Do **not** delete your database or private configuration folder. Do **not** run `setup.php` again.
6. Open `https://oregonproplumbing.com/admin/settings.php` and sign in.

> If the extracted files appear inside a second `Oregon_Pro_Plumbing_SMTP_Update` folder, move its `admin`, `api`, and `includes` contents into the matching existing folders. They must be merged, not left nested.

## Configure SMTP

1. Create an email mailbox using **hosting → Email Accounts**, if you do not already have one. Example: `bookings@oregonproplumbing.com`.
2. Open **Connect Devices / Set Up Mail Client** for the correct SMTP server, secure port, username and password. If using a different provider, use its official SMTP credentials and verified sending address.
3. In **Admin → Website, email & payment settings** fill in:
   - **Booking notification email**: the inbox receiving alerts (may be different from the sending mailbox)
   - **SMTP server**: the actual server supplied by your host (e.g., `mail.yourdomain.com`, only if confirmed)
   - **Port 465 + SSL/TLS** or **Port 587 + STARTTLS** as specified by your provider
   - **SMTP username** and **SMTP password/app password**
   - **From email**: the same account or a sender approved by your provider
   - **Sender name**: Oregon Pro Plumbing
4. Initially leave **Send automatic email alerts** unchecked and click **Save all settings**.
5. Click **Send test email** and check the notification inbox, including spam/junk.
6. When the test arrives, check **Send automatic email alerts**, then click **Save all settings** again.
7. Submit a new booking. It should appear in `/admin/` and trigger an email to your configured address.

## Hosting requirements

- Existing installed PHP website (PHP 8.1+).
- PHP **OpenSSL** extension enabled.
- Outbound SMTP permitted by your hosting provider to your SMTP server.
- SMTP service supporting **AUTH PLAIN or LOGIN over TLS**. OAuth2-only services need a compatible app password/SMTP relay instead; this update does not implement XOAUTH2.
- Your SMTP service account must be approved to send from your configured From address.

## Troubleshooting

| Error | Check |
| --- | --- |
| Could not connect to SMTP host | Correct server and port? Hosting firewall/outbound SMTP restriction? |
| SMTP authentication failed / code 535 | Username, password, app password, provider SMTP authentication settings |
| STARTTLS unavailable | Correct port/security combination: typically 587 + STARTTLS or 465 + SSL/TLS |
| TLS certificate could not be verified | Use the provider's real hostname and valid certificate. Do not disable verification. |
| Test accepted but not received | Spam folder, sender verification, SPF/DKIM/DMARC, provider suppression or logs |

## Security and behavior

- Your SMTP credentials are saved with the existing server-side private configuration **outside the public site folder**. They are not returned to the browser and are never included in a booking email.
- Booking records are saved to the database first; SMTP delivery failure cannot change a successful booking into a failed one.
- When outgoing email fails, the details are written to your PHP server error log. The message is **not automatically retried**; bookings remain visible in the operations dashboard.
- This update sends **business notifications for new bookings** only. It does not send customer confirmations or alter Stripe webhooks.
- The SMTP test sends only to the saved booking notification address; tests are limited to once every 45 seconds per administrator session.

**Please do not send SMTP or Stripe passwords in WhatsApp.** Enter them in the protected admin settings page over HTTPS.
