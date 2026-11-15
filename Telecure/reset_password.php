<?php
require_once 'includes/config.php';
require_once 'includes/mailer.php';

if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$msg   = '';
$error = '';
$step  = sanitize($_GET['step'] ?? '1');
$token = sanitize($_GET['token'] ?? '');

// The reset link is only shown on screen when the site is opened from the
// same computer that runs the server (localhost). On a real server it must be
// e-mailed instead, otherwise anyone could reset anyone's password.
$isLocalhost = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);

function findValidReset($db, $token) {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $stmt = $db->prepare("SELECT id, user_id FROM password_resets
                          WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()");
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch() ?: null;
}

// ── Step 1: Request reset link ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    requireCsrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $db    = getDB();

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter the e-mail address you registered with.';
    } elseif (!mailConfigured()) {
        // Not about any one account, so safe to say out loud: nothing can be sent until an admin sets up e-mail.
        $error = 'E-mail sending is not set up on this site yet, so a reset link cannot be sent. '
               . 'Please contact the site administrator (Admin Panel > Email Settings).';
        if ($isLocalhost) {
            $error .= ' (Local demo: sign in as the administrator and open Email Settings.)';
        }
    } else {
        // The link is always sent to the address stored on the account (the one used at registration).
        $stmt = $db->prepare("SELECT id, full_name, email FROM users WHERE LOWER(email)=? AND is_active=1");
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        // Same message whether or not the account exists (prevents email enumeration).
        $msg = 'If that email is registered, a reset link has been sent to it. It arrives within a minute or two - check your spam folder too. '
             . 'The link works once and expires in 1 hour.';

        if ($u) {
            // Throttle: at most one reset e-mail per account per minute (stops mail-bombing a victim).
            $recent = $db->prepare("SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)");
            $recent->execute([$u['id']]);

            if ((int)$recent->fetchColumn() > 0) {
                $msg .= ' (If you just asked for one, please wait a minute before requesting another.)';
            } else {
                $resetToken = bin2hex(random_bytes(32));
                // Invalidate older links, then store only a HASH of the new token.
                $db->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$u['id']]);
                $db->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))")
                   ->execute([$u['id'], hash('sha256', $resetToken)]);

                $base = mailBaseUrl();
                $link = $base . '/reset_password.php?step=2&token=' . $resetToken;
                $sent = false; $mailErr = '';

                if ($base === '') {
                    $mailErr = 'The site address is not set (Admin Panel > Email Settings), so no link can be e-mailed safely.';
                } else {
                    list($html, $text) = passwordResetEmail($u['full_name'], $link);
                    list($sent, $mailErr) = sendMail($u['email'], $u['full_name'], 'Reset your TeleCure password', $html, $text);
                }
                if (!$sent) error_log('TeleCure reset e-mail failed for user ' . $u['id'] . ': ' . $mailErr);

                // Visitors always get the same generic message. Only when browsing from this
                // very computer (localhost) do we also show the link / the technical reason,
                // so you can still test and debug without a working mail server.
                if ($isLocalhost && !$sent) {
                    $msg = 'E-mail could not be sent (' . e($mailErr) . ')<br>Local demo mode: '
                         . '<a href="' . e(SITE_URL . '/reset_password.php?step=2&token=' . $resetToken) . '">click here to reset your password</a>';
                }
            }
        }
    }
}

// ── Step 2: link opened - check token ────────────────────────────────
if ($step === '2' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!findValidReset(getDB(), $token)) {
        $error = 'Invalid or expired reset link. Please request a new one.';
        $step  = '1';
    }
}

// ── Step 2: new password submitted ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_reset'])) {
    requireCsrf();
    $token   = sanitize($_POST['token'] ?? '');
    $pass    = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $db      = getDB();
    $reset   = findValidReset($db, $token);

    if (!$reset) {
        $error = 'Invalid or expired reset link. Please request a new one.';
        $step  = '1';
    } elseif ($pwErr = passwordPolicyError($pass)) {
        $error = $pwErr;
        $step  = '2';
    } elseif ($pass !== $confirm) {
        $error = 'Passwords do not match.';
        $step  = '2';
    } else {
        $db->prepare("UPDATE users SET password=? WHERE id=?")
           ->execute([password_hash($pass, PASSWORD_DEFAULT), $reset['user_id']]);
        $db->prepare("DELETE FROM password_resets WHERE user_id=?")->execute([$reset['user_id']]);
        $msg  = 'Password updated!';
        $step = 'done';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - TeleCure</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="auth-page">
<div class="auth-wrapper">
    <div class="auth-left">
        <a href="index.php" class="auth-logo">
            <svg viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg" width="50">
                <circle cx="30" cy="30" r="28" fill="rgba(255,255,255,0.2)"/>
                <rect x="25" y="15" width="10" height="30" rx="3" fill="white"/>
                <rect x="15" y="25" width="30" height="10" rx="3" fill="white"/>
            </svg>
            <span>TeleCure</span>
        </a>
        <div class="auth-left-content">
            <h2>Recover Your Account</h2>
            <p>Enter your email address and we'll send you a link to reset your password securely.</p>
            <ul class="auth-features-list">
                <li><i class="fas fa-check-circle"></i> Secure token-based reset</li>
                <li><i class="fas fa-check-circle"></i> Link expires in 1 hour</li>
                <li><i class="fas fa-check-circle"></i> Your data stays protected</li>
            </ul>
        </div>
    </div>
    <div class="auth-right">
        <div class="auth-form-container" style="max-width:420px;">

            <?php if ($step === 'done'): ?>
            <div style="text-align:center;padding:40px 0;">
                <div style="width:72px;height:72px;background:linear-gradient(135deg,var(--primary),var(--primary-mid));border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:1.8rem;color:white;">
                    <i class="fas fa-check"></i>
                </div>
                <h1 style="font-size:1.6rem;">Password Updated!</h1>
                <p style="color:var(--text-light);margin:12px 0 28px;">Your password has been changed successfully.</p>
                <a href="login.php" class="btn-primary" style="display:inline-flex;">
                    <i class="fas fa-sign-in-alt"></i> Sign In Now
                </a>
            </div>

            <?php elseif ($step === '2'): ?>
            <h1>New Password</h1>
            <p class="auth-subtitle">Enter and confirm your new password below.</p>

            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div><?php endif; ?>

            <form method="POST" action="reset_password.php?step=2" class="auth-form">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> New Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="new_password" id="np" placeholder="8+ chars, a number &amp; a symbol" required autocomplete="new-password" data-password-rules="resetRules">
                        <button type="button" class="toggle-pass" onclick="togglePass('np')"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <ul class="pw-rules" id="resetRules" aria-live="polite"></ul>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Confirm Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" id="cp" placeholder="Repeat password" required>
                        <button type="button" class="toggle-pass" onclick="togglePass('cp')"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <button type="submit" name="do_reset" class="btn-submit">
                    <i class="fas fa-key"></i> Update Password
                </button>
            </form>

            <?php else: ?>
            <h1>Forgot Password?</h1>
            <p class="auth-subtitle">Remember it? <a href="login.php">Sign in</a></p>

            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div><?php endif; ?>
            <?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-info-circle"></i> <?= $msg ?></div><?php endif; ?>

            <form method="POST" action="reset_password.php" class="auth-form">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" name="email" placeholder="your@email.com" required>
                </div>
                <button type="submit" name="request_reset" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
                <div style="text-align:center;margin-top:20px;">
                    <a href="login.php" style="color:var(--text-light);font-size:0.88rem;">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
function togglePass(id){
    var inp=document.getElementById(id);
    inp.type=inp.type==='password'?'text':'password';
}
</script>
<script src="js/password-rules.js"></script>
</body>
</html>
