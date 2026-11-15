<?php
/**
 * TeleCure – OPTIONAL default e-mail (SMTP) settings.
 *
 * You normally do NOT need to edit this file: sign in as an administrator and use
 * Email Settings in the sidebar. Values saved there override everything below.
 *
 * Easiest free option: a Gmail account.
 *   1. Turn on 2-Step Verification:  https://myaccount.google.com/security
 *   2. Create an "App password":     https://myaccount.google.com/apppasswords
 *   3. Put the Gmail address in MAIL_USERNAME and the 16-character app
 *      password (NOT your normal Gmail password) in MAIL_PASSWORD.
 *
 * Other providers work too (Brevo, Mailtrap, Outlook ...): just change the
 * host / port / encryption. Never commit real passwords to Git/GitHub.
 */

define('MAIL_ENABLED',    false);                  // set to true once the details below are filled in
define('MAIL_HOST',       'smtp.gmail.com');
define('MAIL_PORT',       587);                    // 587 = STARTTLS, 465 = SSL
define('MAIL_ENCRYPTION', 'tls');                  // 'tls' (port 587), 'ssl' (port 465) or '' (none, local testing only)
define('MAIL_USERNAME',   'your.address@gmail.com');
define('MAIL_PASSWORD',   'your-16-char-app-password');
define('MAIL_FROM_EMAIL', MAIL_USERNAME);          // Gmail forces this to be your own address
define('MAIL_FROM_NAME',  'TeleCure');

// Fixed public address of the site, used inside e-mailed links, e.g.
//   'http://localhost/Devsync/Telecure'   or   'https://www.yourdomain.com'
// IMPORTANT: leave it fixed. Building links from the visitor's request lets an
// attacker send victims a reset link that points at THEIR website.
define('APP_BASE_URL',    'http://localhost/Devsync/Telecure');

// Windows/WAMP sometimes can't verify the mail server's certificate
// ("certificate verify failed"). Prefer fixing php.ini (openssl.cafile);
// only set this to false as a last resort on your own laptop.
define('MAIL_VERIFY_SSL', true);

define('MAIL_DEBUG',      false);                  // true = write the SMTP conversation to the PHP error log
