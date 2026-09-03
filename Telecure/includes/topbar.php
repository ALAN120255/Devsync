<?php
// Topbar include - Compatible with PHP 5.6+
$avatarUrl = avatarUrl($user, 40);
$notifCount = getUnreadNotifications($user['id']);
$msgCount = getUnreadMessages($user['id']);

// Icon map (replaces match() for PHP 5.6+ compatibility)
$notifIconMap = array(
    'appointment'  => 'calendar',
    'message'      => 'comment',
    'prescription' => 'prescription',
);
?>
<header class="topbar">
    <div class="topbar-left">
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="breadcrumb">
            <span><?= ucfirst(str_replace('.php', '', basename($_SERVER['PHP_SELF']))) ?></span>
        </div>
    </div>
    <div class="topbar-right">
        <div class="topbar-search">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search doctors, appointments...">
        </div>
        <a href="chat.php" class="topbar-icon-btn">
            <i class="fas fa-comments"></i>
            <?php if ($msgCount > 0): ?>
            <span class="notif-dot"><?= $msgCount ?></span>
            <?php endif; ?>
        </a>
        <div class="topbar-notif" id="notifToggle">
            <button class="topbar-icon-btn">
                <i class="fas fa-bell"></i>
                <?php if ($notifCount > 0): ?>
                <span class="notif-dot"><?= $notifCount ?></span>
                <?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header">
                    <h4>Notifications</h4>
                    <a href="notifications.php?mark_all=1">Mark all read</a>
                </div>
                <?php
                $notifs = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
                $notifs->execute([$user['id']]);
                $notifList = $notifs->fetchAll();
                if (empty($notifList)): ?>
                <div class="notif-empty"><i class="fas fa-bell-slash"></i> No notifications</div>
                <?php else: foreach ($notifList as $n):
                    $nIcon = isset($notifIconMap[$n['type']]) ? $notifIconMap[$n['type']] : 'bell';
                ?>
                <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
                    <div class="notif-icon <?= htmlspecialchars($n['type']) ?>">
                        <i class="fas fa-<?= $nIcon ?>"></i>
                    </div>
                    <div>
                        <p><?= htmlspecialchars($n['title']) ?></p>
                        <small><?= date('M j, g:i A', strtotime($n['created_at'])) ?></small>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <div class="topbar-user">
            <img src="<?= $avatarUrl ?>" alt="Avatar">
            <div class="topbar-user-info">
                <?php $firstName = explode(' ', $user['full_name']); ?>
                <strong><?= htmlspecialchars($firstName[0]) ?></strong>
                <span><?= ucfirst($user['role']) ?></span>
            </div>
            <i class="fas fa-chevron-down"></i>
        </div>
    </div>
</header>
