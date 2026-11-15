<?php
require_once 'includes/config.php';
require_once 'includes/mailer.php';
requireLogin();

$user = currentUser();
$db   = getDB();
if (!isAdmin($user)) { header('Location: dashboard.php'); exit; }

ensureSettingsTable($db);
$msg = ''; $error = ''; $testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? 'save';

    $enabled    = !empty($_POST['enabled']);
    $host       = trim($_POST['host'] ?? '');
    $port       = (int)($_POST['port'] ?? 0);
    $encryption = $_POST['encryption'] ?? 'tls';
    $username   = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';               // blank = keep the saved one
    $fromEmail  = trim($_POST['from_email'] ?? '');
    $fromName   = sanitize($_POST['from_name'] ?? '');
    $baseUrl    = rtrim(trim($_POST['base_url'] ?? ''), '/');
    $verifySsl  = !empty($_POST['verify_ssl']);

    $errors = [];
    if (!in_array($encryption, ['tls', 'ssl', ''], true)) $errors[] = 'Invalid encryption type.';
    if ($host === '' || !preg_match('/^[A-Za-z0-9.-]+$/', $host)) $errors[] = 'Enter a valid SMTP server name, e.g. smtp.gmail.com.';
    if ($port < 1 || $port > 65535) $errors[] = 'Port must be between 1 and 65535 (587 for TLS, 465 for SSL).';
    if ($username !== '' && strpos($username, '@') !== false && !filter_var($username, FILTER_VALIDATE_EMAIL)) $errors[] = 'The username looks like an e-mail address but is not valid.';
    if ($fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'The "From" address is not a valid e-mail address.';
    if ($baseUrl !== '' && !preg_match('#^https?://[A-Za-z0-9.\-:_/\[\]]+$#', $baseUrl)) $errors[] = 'The site address must start with http:// or https:// (e.g. http://localhost/Devsync/Telecure).';
    if ($fromName === '') $fromName = 'TeleCure';
    if ($enabled && $host !== 'localhost' && $host !== '127.0.0.1'
        && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL) && !filter_var($username, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a "From" e-mail address (or use your e-mail address as the username).';
    }

    if ($errors) {
        $error = implode('<br>', array_map('htmlspecialchars', $errors));
    } else {
        $pairs = [
            'mail_enabled'    => $enabled ? '1' : '0',
            'mail_host'       => $host,
            'mail_port'       => (string)$port,
            'mail_encryption' => $encryption,
            'mail_username'   => $username,
            'mail_from_email' => $fromEmail,
            'mail_from_name'  => $fromName,
            'mail_base_url'   => $baseUrl,
            'mail_verify_ssl' => $verifySsl ? '1' : '0',
        ];
        if (!empty($_POST['clear_password'])) $pairs['mail_password'] = '';
        elseif ($password !== '')             $pairs['mail_password'] = encryptSecret($password);
        setSettings($pairs);
        $msg = 'Email settings saved.';

        if ($action === 'test') {
            $m = mailSettings();
            if (!mailConfigured($m)) {
                $testResult = [false, 'Not sent: tick "Enable sending" and fill in the server details first.'];
            } else {
                $html = '<div style="font-family:Arial,sans-serif"><h2 style="color:#1565C0">TeleCure</h2>'
                      . '<p>This is a test message. If you can read it, your e-mail settings work and password-reset e-mails will be delivered.</p></div>';
                $testResult = sendMail($user['email'], $user['full_name'], 'TeleCure test e-mail', $html);
            }
        }
    }
}

$m = mailSettings();
$hasPassword = $m['password'] !== '';
// Pre-fill the site address with the one you are using right now, if none is saved yet.
$baseShown = $m['base_url'] !== '' ? $m['base_url'] : SITE_URL;
$status = mailConfigured($m);
$avatarUrl = avatarUrl($user, 80);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Settings - TeleCure</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    .mail-card { background:white; border-radius:var(--radius-md); border:1px solid var(--border); box-shadow:var(--shadow-sm); max-width:760px; }
    .mail-card-head { padding:18px 24px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
    .mail-card-head h3 { font-size:0.95rem; display:flex; align-items:center; gap:8px; }
    .mail-card-head h3 i { color:var(--primary); }
    .mail-body { padding:24px; }
    .mail-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    .mail-hint { font-size:0.78rem; color:var(--text-light); margin-top:5px; line-height:1.5; }
    .mail-section { font-size:0.72rem; text-transform:uppercase; letter-spacing:1px; color:var(--text-light); font-weight:700; margin:22px 0 12px; }
    .mail-section:first-child { margin-top:0; }
    .status-pill { padding:3px 12px; border-radius:50px; font-size:0.74rem; font-weight:700; }
    .status-pill.on { background:#F0FDF4; color:#15803D; }
    .status-pill.off { background:#FEF3C7; color:#92400E; }
    .check-line { display:flex; align-items:center; gap:8px; font-size:0.88rem; margin-bottom:10px; }
    .check-line input { width:auto; }
    .mail-actions { display:flex; gap:12px; flex-wrap:wrap; margin-top:24px; }
    .btn-secondary-mail { background:white; color:var(--primary); border:1.5px solid var(--primary); padding:11px 22px; border-radius:var(--radius-sm,10px); font-weight:600; cursor:pointer; font-family:inherit; }
    .btn-secondary-mail:hover { background:rgba(21,101,192,0.08); }
    .help-box { background:#EFF6FF; border:1px solid #BFDBFE; border-radius:10px; padding:14px 16px; font-size:0.82rem; line-height:1.6; margin-bottom:20px; }
    .help-box ol { margin:6px 0 0 18px; }
    @media(max-width:700px){ .mail-row{grid-template-columns:1fr;} }
    </style>
</head>
<body class="dashboard-page">
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
    <?php include 'includes/topbar.php'; ?>
    <div class="dashboard-body">

        <div class="page-header">
            <div>
                <h1><i class="fas fa-envelope-open-text" style="color:var(--primary);font-size:1.4rem;"></i> Email Settings</h1>
                <p>Set up the mail account TeleCure uses to send password-reset e-mails</p>
            </div>
        </div>

        <?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($msg) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>
        <?php if ($testResult): ?>
            <?php if ($testResult[0]): ?>
            <div class="alert alert-success"><i class="fas fa-paper-plane"></i> Test e-mail sent to <strong><?= e($user['email']) ?></strong>. Check that inbox (and the spam folder).</div>
            <?php else: ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> The test e-mail could not be sent:<br><small><?= e($testResult[1]) ?></small></div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="mail-card">
            <div class="mail-card-head">
                <h3><i class="fas fa-server"></i> SMTP account</h3>
                <span class="status-pill <?= $status ? 'on' : 'off' ?>"><?= $status ? 'Ready to send' : 'Not set up' ?></span>
            </div>
            <div class="mail-body">
                <div class="help-box" id="gmailHelp">
                    <strong>Using Gmail?</strong>
                    <ol>
                        <li>Turn on 2-Step Verification for the Gmail account.</li>
                        <li>Create an <em>App password</em> at <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">myaccount.google.com/apppasswords</a>.</li>
                        <li>Paste the 16-character app password below (your normal Gmail password will not work).</li>
                    </ol>
                </div>

                <form method="POST" action="admin_email.php" autocomplete="off">
                    <?= csrf_field() ?>

                    <label class="check-line"><input type="checkbox" name="enabled" value="1" <?= $m['enabled'] ? 'checked' : '' ?>> <strong>Enable sending e-mails</strong></label>

                    <div class="mail-section">Provider</div>
                    <div class="form-group">
                        <label>Quick setup</label>
                        <select id="preset">
                            <option value="">Custom / other</option>
                            <option value="gmail">Gmail</option>
                            <option value="outlook">Outlook / Microsoft 365</option>
                            <option value="brevo">Brevo (Sendinblue)</option>
                            <option value="mailtrap">Mailtrap (testing inbox)</option>
                        </select>
                        <div class="mail-hint">Fills in the server, port and encryption for you. You can still edit them.</div>
                    </div>
                    <div class="mail-row">
                        <div class="form-group"><label>SMTP server</label>
                            <input type="text" name="host" id="host" value="<?= e($m['host']) ?>" placeholder="smtp.gmail.com" required></div>
                        <div class="mail-row" style="gap:12px;">
                            <div class="form-group"><label>Port</label>
                                <input type="number" name="port" id="port" value="<?= (int)$m['port'] ?>" min="1" max="65535" required></div>
                            <div class="form-group"><label>Encryption</label>
                                <select name="encryption" id="encryption">
                                    <option value="tls" <?= $m['encryption'] === 'tls' ? 'selected' : '' ?>>TLS (587)</option>
                                    <option value="ssl" <?= $m['encryption'] === 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
                                    <option value="" <?= $m['encryption'] === '' ? 'selected' : '' ?>>None</option>
                                </select></div>
                        </div>
                    </div>

                    <div class="mail-section">Login</div>
                    <div class="mail-row">
                        <div class="form-group"><label>Username (usually the e-mail address)</label>
                            <input type="text" name="username" id="username" value="<?= e($m['username'] === 'your.address@gmail.com' ? '' : $m['username']) ?>" placeholder="you@gmail.com" autocomplete="off"></div>
                        <div class="form-group"><label>Password / App password</label>
                            <input type="password" name="password" placeholder="<?= $hasPassword ? '•••••••• (saved – leave blank to keep)' : 'Paste the app password' ?>" autocomplete="new-password">
                            <?php if ($hasPassword): ?>
                            <label class="check-line" style="margin-top:8px;font-size:0.8rem"><input type="checkbox" name="clear_password" value="1"> Remove the saved password</label>
                            <?php endif; ?>
                            <div class="mail-hint">Stored encrypted. It is never shown again after saving.</div></div>
                    </div>

                    <div class="mail-section">Sender &amp; links</div>
                    <div class="mail-row">
                        <div class="form-group"><label>"From" name</label>
                            <input type="text" name="from_name" value="<?= e($m['from_name']) ?>" placeholder="TeleCure"></div>
                        <div class="form-group"><label>"From" e-mail (optional)</label>
                            <input type="email" name="from_email" value="<?= e($m['from_email'] === 'no-reply@telecure.test' ? '' : $m['from_email']) ?>" placeholder="same as username">
                            <div class="mail-hint">Gmail always sends from your own address, whatever you enter here.</div></div>
                    </div>
                    <div class="form-group">
                        <label>Website address used in e-mailed links</label>
                        <input type="text" name="base_url" value="<?= e($baseShown) ?>" placeholder="https://www.yourdomain.com">
                        <div class="mail-hint">Must be the real address people use to reach this site, with no trailing slash. It is fixed here (not taken from the visitor's request) so nobody can trick the site into e-mailing links to another website.</div>
                    </div>
                    <label class="check-line"><input type="checkbox" name="verify_ssl" value="1" <?= $m['verify_ssl'] ? 'checked' : '' ?>> Verify the mail server's security certificate <small style="color:var(--text-light)">(untick only if you get "certificate verify failed" on your own laptop)</small></label>

                    <div class="mail-actions">
                        <button type="submit" name="action" value="save" class="btn-primary"><i class="fas fa-save"></i> Save settings</button>
                        <button type="submit" name="action" value="test" class="btn-secondary-mail"><i class="fas fa-paper-plane"></i> Save &amp; send test e-mail to me</button>
                    </div>
                    <div class="mail-hint" style="margin-top:10px">The test message goes to <?= e($user['email']) ?>.</div>
                </form>
            </div>
        </div>

    </div>
</div>
<script>
var presets = {
    gmail:    {host:'smtp.gmail.com',            port:587,  enc:'tls'},
    outlook:  {host:'smtp.office365.com',        port:587,  enc:'tls'},
    brevo:    {host:'smtp-relay.brevo.com',      port:587,  enc:'tls'},
    mailtrap: {host:'sandbox.smtp.mailtrap.io',  port:2525, enc:'tls'}
};
var sel = document.getElementById('preset');
function showHelp() { document.getElementById('gmailHelp').style.display = (sel.value === 'gmail' || sel.value === '') ? 'block' : 'none'; }
sel.addEventListener('change', function () {
    var p = presets[sel.value];
    if (p) {
        document.getElementById('host').value = p.host;
        document.getElementById('port').value = p.port;
        document.getElementById('encryption').value = p.enc;
    }
    showHelp();
});
// Pre-select the preset that matches the saved host
(function () {
    var h = document.getElementById('host').value;
    for (var k in presets) if (presets[k].host === h) sel.value = k;
    showHelp();
})();
</script>
</body>
</html>
