OREGON PRO PLUMBING — TWILIO ACCESS TOKEN ERROR 20101 FIX

Purpose:
Updates the portal's generated Twilio Voice SDK Access Token to include
its required JWT header content type: cty = twilio-fpa;v=1.

INSTALLATION — no terminal required:
1. Sign in to your cPanel File Manager.
2. Open public_html/phone/app/ (or the matching installed portal folder).
3. Download a backup of the existing core.php file.
4. Upload OPP_Twilio_AccessToken_20101_Fix.zip into public_html/phone/
   and extract it there, replacing app/core.php.
   Alternatively, upload the included app/core.php directly to public_html/phone/app/.
5. Sign out and sign back in to https://oregonproplumbing.com/phone/
6. Refresh with Ctrl+Shift+R and press Start phone.

IF THE ERROR REMAINS:
- In Phone Portal > Settings, check that these all belong to the SAME
  Twilio account and Region (US1):
  * Twilio Account SID (starts AC)
  * Standard/Main API Key SID (starts SK)
  * API Key Secret from THAT API Key (NOT Account Auth Token)
  * TwiML Application SID (starts AP)
- Don't paste API secrets, Auth Tokens or Access Tokens into chat/screenshots.
- Generate a new Standard API Key if you cannot establish which secret
  belongs to the configured SID. Enter its SK SID + secret through Settings.
- If the server clock is significantly wrong, ask your hosting provider to fix it.

This patch does NOT change your Studio flow, phone number, staff accounts,
login credentials, database, or your private Twilio configuration.
This patch has been syntax-tested and locally verified for JWT structure
and signature; live registration still requires your Twilio credentials.
