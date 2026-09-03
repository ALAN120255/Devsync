<?php
require_once 'includes/config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['full_name'];

            // Update last seen
            $db->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$user['id']]);

            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TeleCure</title>
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
                <circle cx="30" cy="30" r="28" fill="rgba(255,255,255,0.2)" />
                <rect x="25" y="15" width="10" height="30" rx="3" fill="white"/>
                <rect x="15" y="25" width="30" height="10" rx="3" fill="white"/>
            </svg>
            <span>TeleCure</span>
        </a>
        <div class="auth-left-content">
            <h2>Welcome Back!</h2>
            <p>Access your telehealth dashboard and connect with healthcare professionals from anywhere.</p>
            <div class="login-illustration">
                <div class="pulse-ring"></div>
                <div class="pulse-ring delay"></div>
                <div class="heart-icon"><i class="fas fa-heartbeat"></i></div>
            </div>
        </div>
        <div class="auth-left-wave">
            <svg viewBox="0 0 400 200" xmlns="http://www.w3.org/2000/svg">
                <path d="M0,100 C100,50 300,150 400,100 L400,200 L0,200 Z" fill="rgba(255,255,255,0.05)"/>
            </svg>
        </div>
    </div>

    <div class="auth-right">
        <div class="auth-form-container">
            <h1>Sign In</h1>
            <p class="auth-subtitle">Don't have an account? <a href="register.php">Register now</a></p>

            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['registered'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Account created successfully! Please sign in.
            </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="auth-form">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" name="email" placeholder="your@email.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="loginPass" placeholder="Your password" required>
                        <button type="button" class="toggle-pass" onclick="togglePass('loginPass')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-row form-remember">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember"> Remember me
                    </label>
                    <a href="reset_password.php" class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>

                <div class="demo-accounts">
                    <p>Demo Accounts:</p>
                    <div class="demo-btns">
                        <button type="button" onclick="fillDemo('sarah@telecure.com','password')">
                            <i class="fas fa-user-md"></i> Demo Doctor
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function togglePass(id) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
}
function fillDemo(email, pass) {
    document.querySelector('input[name="email"]').value = email;
    document.getElementById('loginPass').value = pass;
}
</script>
</body>
</html>
