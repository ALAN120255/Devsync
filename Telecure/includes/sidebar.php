<?php
// Sidebar include - requires $user to be set
$role = $user['role'];
$currentPage = basename($_SERVER['PHP_SELF']);
$avatarUrl = avatarUrl($user, 80);
$unreadMsgCount = getUnreadMessages($user['id']);
$unreadNotifCount = getUnreadNotifications($user['id']);
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <svg viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg" width="36">
                <circle cx="30" cy="30" r="28" fill="url(#sideGrad)" />
                <rect x="25" y="15" width="10" height="30" rx="3" fill="white"/>
                <rect x="15" y="25" width="30" height="10" rx="3" fill="white"/>
                <defs>
                    <linearGradient id="sideGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:#1565C0"/>
                        <stop offset="100%" style="stop-color:#42A5F5"/>
                    </linearGradient>
                </defs>
            </svg>
            <span>TeleCure</span>
        </div>
        <button class="sidebar-close" id="sidebarClose"><i class="fas fa-times"></i></button>
    </div>

    <div class="sidebar-user">
        <img src="<?= $avatarUrl ?>" alt="Avatar" class="sidebar-avatar">
        <div class="sidebar-user-info">
            <strong><?= htmlspecialchars($user['full_name']) ?></strong>
            <span class="sidebar-role <?= $role ?>">
                <i class="fas fa-<?= $role === 'doctor' ? 'stethoscope' : 'user' ?>"></i>
                <?= ucfirst($role) ?>
                <?php if ($role === 'doctor' && ($user['is_verified'] ?? 0)): ?>
                <i class="fas fa-check-circle verified-tick"></i>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Main</div>
        <a href="dashboard.php" class="nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i> <span>Dashboard</span>
        </a>
        <a href="appointments.php" class="nav-item <?= $currentPage === 'appointments.php' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> <span>Appointments</span>
        </a>
        <a href="chat.php" class="nav-item <?= $currentPage === 'chat.php' ? 'active' : '' ?>">
            <i class="fas fa-comments"></i> <span>Messages</span>
            <?php if ($unreadMsgCount > 0): ?>
            <span class="nav-badge"><?= $unreadMsgCount ?></span>
            <?php endif; ?>
        </a>
        <a href="video.php" class="nav-item <?= $currentPage === 'video.php' ? 'active' : '' ?>">
            <i class="fas fa-video"></i> <span>Video Consult</span>
        </a>
        <a href="prescriptions.php" class="nav-item <?= $currentPage === 'prescriptions.php' ? 'active' : '' ?>">
            <i class="fas fa-file-prescription"></i> <span>Prescriptions</span>
        </a>

        <div class="nav-section-label">Account</div>
        <a href="profile.php" class="nav-item <?= $currentPage === 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user-circle"></i> <span>My Profile</span>
        </a>
        <?php if ($role === 'patient'): ?>
        <a href="find_doctors.php" class="nav-item <?= $currentPage === 'find_doctors.php' ? 'active' : '' ?>">
            <i class="fas fa-search"></i> <span>Find Doctors</span>
        </a>
        <?php endif; ?>
        <?php if ($role === 'doctor'): ?>
        <a href="admin.php" class="nav-item <?= $currentPage === 'admin.php' ? 'active' : '' ?>">
            <i class="fas fa-shield-alt"></i> <span>Admin Panel</span>
        </a>
        <?php endif; ?>
        <a href="notifications.php" class="nav-item <?= $currentPage === 'notifications.php' ? 'active' : '' ?>">
            <i class="fas fa-bell"></i> <span>Notifications</span>
            <?php if ($unreadNotifCount > 0): ?>
            <span class="nav-badge"><?= $unreadNotifCount ?></span>
            <?php endif; ?>
        </a>
        <a href="logout.php" class="nav-item logout">
            <i class="fas fa-sign-out-alt"></i> <span>Sign Out</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="health-tip">
            <i class="fas fa-lightbulb"></i>
            <p>Tip: Stay hydrated — drink 8 glasses of water daily!</p>
        </div>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
