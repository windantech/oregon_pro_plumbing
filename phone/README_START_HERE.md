# Oregon Pro Plumbing — Browser Calling Portal

Standalone PHP + HTML + CSS + JavaScript portal. **No Composer, npm, Node.js or terminal required on your hosting.**

**Your number:** +1 585 316 0597.

## What this package does

- Administrator and technician logins
- Secure server-generated Twilio Voice Access Tokens
- Browser dial pad, incoming call alert, answer/reject, hang up, mute and DTMF keypad
- Agent routing: ring enabled staff identities; if unanswered, optionally forward to your existing Kenyan mobile
- Call metadata history and status callbacks (no call audio recording)
- Admin credentials, webhooks, staff accounts and SDK file upload
- Responsive interface and Oregon Pro Plumbing brand
- Dual database installer: SQLite **or** MySQL

## Requirements

- HTTPS website with PHP 8.1+ and `openssl`, `session`, `json`, `hash`, `PDO` and `pdo_sqlite` **or** `pdo_mysql`.
- Permission to create a private folder outside `public_html` (or outside the site's document root).
- Twilio active account, number with Voice capability and adequate funds.
- Twilio Account SID (AC...), Auth Token (private), a *Standard API Key* SID (SK...) and secret, and a TwiML App SID (AP...).
- Official Twilio Voice JavaScript SDK 2.x `dist/twilio.min.js` file (upload from Admin Settings, no server terminal needed).
- Chrome, Edge or Firefox desktop browser with microphone enabled, stable internet and headset.

## Installation using File Manager only

1. Download the ZIP, log in to your hosting File Manager, and open `public_html`.
2. Create a new folder named `phone` and upload/extract the ZIP files **inside `public_html/phone/`**. You should see `phone/setup.php`, `phone/index.php`, `phone/api/`, `phone/assets/`, and `phone/app/`.
3. Visit `https://oregonproplumbing.com/phone/setup.php` (adjust if your domain/folder differs).
4. Enter an administrator username and a **new 12+ character password**. The app tries to create private data **outside `public_html`**.
5. If SQLite is available, choose SQLite. If not, create an empty MySQL database/user in your hosting panel and enter the credentials in the installer.
6. Once installation succeeds, use File Manager to **delete `phone/setup.php`** (or rename/remove access), then sign in at `/phone/login.php`.
7. In `Settings`, add your Twilio Account SID, Auth Token, Standard API Key SID + Secret, TwiML Application SID, and US business number. Set the fallback Kenyan number only if needed; leave international forwarding disabled until ready.
8. Download the stable SDK from [Twilio's official GitHub releases](https://github.com/twilio/twilio-voice.js/releases), find `dist/twilio.min.js` within the release, and upload that file using the SDK upload control under Admin Settings. The Twilio SDK is NOT included in this ZIP.
9. Create a TwiML Application in Twilio Console, put `https://oregonproplumbing.com/phone/api/outgoing.php` into the **Voice Request URL**, choose HTTP POST, save. Paste its AP... SID in portal Settings.
10. Enable `Allow staff to dial +1 destinations` only after confirming appropriate outbound permissions and spending controls with Twilio.
11. Open `Phone & Dialer`, click `Start phone`, permit microphone, and test a permitted outgoing destination.
12. **Only after testing** decide whether to change Twilio number's incoming Voice handler from your working Studio Flow to the portal webhook `https://oregonproplumbing.com/phone/api/incoming.php` (HTTP POST). You may use the existing Studio Flow as a backup handler if Twilio's configuration UI supports it. The portal rings enabled staff identities, then optionally forwards unanswered calls to a fallback mobile.
13. Configure number's **Call Status Callback** to `https://oregonproplumbing.com/phone/api/call-events.php` (HTTP POST) if you want status updates in local history.

## Critical safeguards

- Do **not** change the working `Oregon Pro Plumbing Incoming Calls` Studio Flow until browser calling has been tested.
- Do **not** place your Twilio Auth Token or API Key Secret into JavaScript, browser console, screenshots, public GitHub or WhatsApp.
- Do **not** set the account's Auth Token as an API Key Secret; they are different credentials.
- The app does not bypass Twilio restrictions: US outbound calls may require a Business Primary Customer Profile depending on account eligibility.
- International forwarding to Kenya is chargeable; you must enable Kenya in Twilio Voice Geo Permissions. Keep forwarding switched off until required.
- Default outbound policy permits standard +1 NANP numbers only, with a per-call limit of 20 minutes. This is not a full fraud prevention service; apply Twilio geographic restrictions, alerts and spending controls.
- Keep the portal over HTTPS only. Private config and database are stored outside `public_html` where possible. Verify paths under your host's actual document root.
- The app is a functional MVP. Run end-to-end authentication, SDK, inbound, outbound, routing, call-logging and security tests before production use.

## How routing works

**Existing setup remains active** until you switch the Voice handler.

After switching:

1. US customer calls your Twilio number.
2. Twilio POSTs a signed webhook to `/phone/api/incoming.php`.
3. PHP returns TwiML to ring all `Active` team members with `Ring this user` enabled.
4. Browser users who clicked `Start phone` receive the incoming call.
5. If unanswered, Twilio calls `/phone/api/fallback.php`.
6. If configured, a call is forwarded to your verified Kenyan/mobile number; otherwise the caller hears an unavailable message.

## Twilio Console settings

**TwiML App** (used by browser-originated outgoing calls):
- Voice Request URL: `https://oregonproplumbing.com/phone/api/outgoing.php`
- Request method: `POST`

**US Number** (change only after browser tests):
- Primary Voice handler: `Webhook`, `https://oregonproplumbing.com/phone/api/incoming.php`, `POST`
- Optional secondary handler: existing `Oregon Pro Plumbing Incoming Calls` Studio Flow
- Call status callback: `https://oregonproplumbing.com/phone/api/call-events.php`, `POST`

These endpoints verify `X-Twilio-Signature`. The HTTPS domain and path saved in Portal Settings must **exactly** match what you enter in Twilio.

## Troubleshooting

| Symptom | Check |
|---|---|
| Start phone says SDK missing | Upload official `dist/twilio.min.js` in Admin Settings; refresh |
| Phone won't register | HTTPS, microphone, correct AC/SK/AP values, Voice SDK browser compatibility |
| Outbound calls fail | TwiML App Voice URL + POST, correct AP SID, allowed Twilio outbound country/identity permissions |
| Inbound calls still go to Kenya | Twilio number still points to Studio Flow; this is intentional during testing |
| Caller hears error or application failure | Twilio Call Logs/Debugger; webhook URL and Twilio signature must match exactly |
| Inbound reaches portal but computer doesn't ring | User clicked Start phone, browser remains online, user is active and opted into ring |
| Phone rings but no audio | Allow microphone, use headphones, check network WebRTC connectivity |
| Host shows 403 | Check server security/WAF or folder rights, not blanket-disable security |
| Call logs missing | Status callback not configured; see Twilio Console's Call Logs |

## Security follow-up for a larger team

For production, add persistent server-side login rate limiting, password reset with verified email, audit logging, MFA/SSO, monitoring, staff deprovisioning and more advanced outbound fraud controls.
