<?php require_once __DIR__ . '/includes/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - TeleCure</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    body { min-height:100vh; display:flex; align-items:center; justify-content:center; background:var(--bg); }
    .error-box { text-align:center; max-width:480px; padding:40px 24px; }
    .error-num { font-size:8rem; font-weight:800; line-height:1; background:linear-gradient(135deg,var(--primary),var(--primary-light)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; margin-bottom:8px; }
    .error-box h2 { font-size:1.6rem; margin-bottom:12px; }
    .error-box p { color:var(--text-light); margin-bottom:32px; line-height:1.6; }
    .error-actions { display:flex; gap:14px; justify-content:center; flex-wrap:wrap; }
    .error-icon { font-size:3rem; color:var(--primary-light); margin-bottom:16px; display:block; opacity:0.4; animation:float 3s ease-in-out infinite; }
    </style>
</head>
<body>
<div class="error-box">
    <i class="fas fa-heartbeat error-icon"></i>
    <div class="error-num">404</div>
    <h2>Page Not Found</h2>
    <p>The page you're looking for doesn't exist or has been moved. Let's get you back on track.</p>
    <div class="error-actions">
        <?php if (isLoggedIn()): ?>
        <a href="dashboard.php" class="btn-primary"><i class="fas fa-th-large"></i> Go to Dashboard</a>
        <?php else: ?>
        <a href="index.php" class="btn-primary"><i class="fas fa-home"></i> Go Home</a>
        <a href="login.php" class="btn-outline">Sign In</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
