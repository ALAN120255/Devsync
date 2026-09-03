<?php
require_once 'includes/config.php';

if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$msg   = '';
$error = '';
$step  = sanitize($_GET['step'] ?? '1');
$token = sanitize($_GET['token'] ?? '');

// ── Step 1: Request reset email ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $db    = getDB();
    $stmt  = $db->prepare("SELECT id, full_name FROM users WHERE email=? AND is_active=1");
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    // Always show success to prevent email enumeration
    $msg = 'If that email is registered, a reset link has been sent.';

    if ($u) {
        $resetToken = bin2hex(random_bytes(32));
        $expires    = date('Y-m-d H:i:s', strtotime('+1 hour'));
        // Store token (reuse bio field trick for demo; real app = separate table)
        $db->prepare("UPDATE users SET bio=? WHERE id=?")->execute(["RESET:$resetToken:$expires", $u['id']]);
        // In production: mail($email, 'Password Reset', SITE_URL."/reset_password.php?step=2&token=$resetToken");
        // For demo we show the link directly:
        $msg = 'Demo mode: <a href="reset_password.php?step=2&token='.$resetToken.'">Click here to reset password</a>';
    }
}

// ── Step 2: Enter new password ───────────────────────────────────────
if ($step === '2' && $token) {
    $db   = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE bio LIKE ?");
    $stmt->execute(["RESET:$token:%"]);
    $u = $stmt->fetch();

    if (!$u) {
        $error = 'Invalid or expired reset link. Please request a new one.';
        $step  = '1';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_reset'])) {
    $token   = sanitize($_POST['token'] ?? '');
    $pass    = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $db      = getDB();

    $stmt = $db->prepare("SELECT id, bio FROM users WHERE bio LIKE ?");
    $stmt->execute(["RESET:$token:%"]);
    $u = $stmt->fetch();

    if (!$u) {
        $error = 'Invalid or expired token.';
    } elseif (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Verify not expired
        $parts   = explode(':', $u['bio']);
        $expires = isset($parts[2]) ? $parts[2] : '';
        if ($expires && strtotime($expires) < time()) {
            $error = 'Reset link has expired. Please request a new one.';
        } else {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password=?, bio='' WHERE id=?")->execute([$hashed, $u['id']]);
            $msg  = 'Password updated! <a href="login.php">Sign in now</a>';
            $step = 'done';
        }
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

            <?php elseif ($step === '2' && !$error): ?>
            <h1>New Password</h1>
            <p class="auth-subtitle">Enter and confirm your new password below.</p>

            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>

            <form method="POST" action="reset_password.php?step=2" class="auth-form">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> New Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="new_password" id="np" placeholder="At least 8 characters" required>
                        <button type="button" class="toggle-pass" onclick="togglePass('np')"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
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

            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>
            <?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-info-circle"></i> <?= $msg ?></div><?php endif; ?>

            <form method="POST" action="reset_password.php" class="auth-form">
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
</body>
</html>
