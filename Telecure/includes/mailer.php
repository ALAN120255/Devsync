<?php
/**
 * Tiny wrapper around PHPMailer.
 *   [$ok, $error] = sendMail($toEmail, $toName, $subject, $htmlBody, $textBody);
 * $error is a technical message for logs / local debugging - never show it to
 * visitors on a live site.
 */
require_once __DIR__ . '/mail_config.php';   // optional defaults (the Admin > Email Settings page overrides them)
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

/**
 * Effective e-mail settings: values saved on the Admin > Email Settings page win;
 * anything not saved there falls back to includes/mail_config.php.
 */
function mailSettings() {
    $d = function ($const, $fallback) { return defined($const) ? constant($const) : $fallback; };
    $enabled = getSetting('mail_enabled');
    $verify  = getSetting('mail_verify_ssl');
    return [
        'enabled'    => $enabled === null ? (bool)$d('MAIL_ENABLED', false) : $enabled === '1',
        'host'       => getSetting('mail_host',       $d('MAIL_HOST', 'smtp.gmail.com')),
        'port'       => (int)getSetting('mail_port',  $d('MAIL_PORT', 587)),
        'encryption' => getSetting('mail_encryption', $d('MAIL_ENCRYPTION', 'tls')),
        'username'   => getSetting('mail_username',   $d('MAIL_USERNAME', '')),
        // Once settings were saved on the website, the database is the only source: no saved password
        // means no password (never fall back to the placeholder in mail_config.php).
        'password'   => getSetting('mail_host') !== null
                            ? decryptSecret((string)getSetting('mail_password', ''))
                            : $d('MAIL_PASSWORD', ''),
        'from_email' => getSetting('mail_from_email', $d('MAIL_FROM_EMAIL', '')),
        'from_name'  => getSetting('mail_from_name',  $d('MAIL_FROM_NAME', 'TeleCure')),
        'base_url'   => getSetting('mail_base_url',   $d('APP_BASE_URL', '')),
        'verify_ssl' => $verify === null ? (bool)$d('MAIL_VERIFY_SSL', true) : $verify === '1',
    ];
}

function mailConfigured($m = null) {
    $m = $m ?: mailSettings();
    if (!$m['enabled'] || $m['host'] === '') return false;
    if ($m['username'] === 'your.address@gmail.com' || $m['password'] === 'your-16-char-app-password') return false; // untouched placeholders
    // A username needs a password; servers without login (local relays / test tools) may have neither.
    return $m['username'] === '' || $m['password'] !== '';
}

function sendMail($toEmail, $toName, $subject, $htmlBody, $textBody = '') {
    $m = mailSettings();
    if (!mailConfigured($m)) {
        return [false, 'E-mail is not set up yet. An administrator can do this under Admin Panel > Email Settings.'];
    }
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $m['host'];
        $mail->Port       = $m['port'];
        $mail->SMTPAuth   = ($m['username'] !== '');
        $mail->Username   = $m['username'];
        $mail->Password   = $m['password'];
        $mail->Timeout    = 12;                       // don't hang the page if the server is unreachable
        $mail->CharSet    = 'UTF-8';
        if ($m['encryption'] === 'tls')      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        elseif ($m['encryption'] === 'ssl')  $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        else { $mail->SMTPSecure = ''; $mail->SMTPAutoTLS = false; }
        if (!$m['verify_ssl']) {
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
        }
        if (defined('MAIL_DEBUG') && MAIL_DEBUG) {
            $mail->SMTPDebug   = 2;
            $mail->Debugoutput = function ($str) { error_log('SMTP: ' . trim($str)); };
        }

        $from = $m['from_email'];
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) $from = $m['username'];            // login is usually the address
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) $from = 'no-reply@telecure.local'; // last resort (local relays only)
        $mail->setFrom($from, $m['from_name']);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody !== '' ? $textBody : trim(strip_tags($htmlBody));
        $mail->send();
        return [true, ''];
    } catch (MailException $ex) {
        return [false, $mail->ErrorInfo ?: $ex->getMessage()];
    } catch (Exception $ex) {
        return [false, $ex->getMessage()];
    }
}

/** Public base URL to put in e-mailed links, or '' if it can't be trusted. */
function mailBaseUrl() {
    $m = mailSettings();
    if ($m['base_url'] !== '') return rtrim($m['base_url'], '/');
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host) ? SITE_URL : '';
}

function passwordResetEmail($name, $link) {
    $n = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $l = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
    $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:auto;color:#1f2937">'
          . '<h2 style="color:#1565C0;margin-bottom:4px">TeleCure</h2>'
          . '<p>Hi ' . $n . ',</p>'
          . '<p>We received a request to reset your TeleCure password. Click the button below to choose a new one. '
          . 'This link works <strong>once</strong> and expires in <strong>1 hour</strong>.</p>'
          . '<p style="text-align:center;margin:28px 0"><a href="' . $l . '" style="background:#1565C0;color:#fff;'
          . 'padding:12px 26px;border-radius:8px;text-decoration:none;font-weight:bold">Reset my password</a></p>'
          . '<p style="font-size:13px;color:#6b7280">Button not working? Copy this address into your browser:<br>' . $l . '</p>'
          . '<hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0">'
          . '<p style="font-size:13px;color:#6b7280">If you did not request this, you can safely ignore this e-mail &ndash; your password will not change.</p>'
          . '</div>';
    $text = "Hi $name,\r\n\r\nWe received a request to reset your TeleCure password.\r\n"
          . "Open this link (valid once, for 1 hour):\r\n$link\r\n\r\n"
          . "If you did not request this, ignore this e-mail - your password will not change.";
    return [$html, $text];
}
