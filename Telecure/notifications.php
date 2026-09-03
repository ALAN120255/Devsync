<?php
require_once 'includes/config.php';
requireLogin();

$user = currentUser();
$db = getDB();

// Mark all as read
if (isset($_GET['mark_all'])) {
    $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$user['id']]);
    header('Location: notifications.php');
    exit;
}

// Mark single as read
if (isset($_GET['mark'])) {
    $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([(int)$_GET['mark'], $user['id']]);
}

// Delete notification
if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM notifications WHERE id=? AND user_id=?")->execute([(int)$_GET['delete'], $user['id']]);
    header('Location: notifications.php');
    exit;
}

$filter = sanitize($_GET['filter'] ?? 'all');
$filterSQL = $filter !== 'all' ? "AND n.type = '$filter'" : '';

$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? $filterSQL ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - TeleCure</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .notif-page-list { display: flex; flex-direction: column; gap: 10px; }
        .notif-full-card {
            display: flex; gap: 16px; align-items: flex-start;
            background: white; border-radius: var(--radius-md);
            border: 1px solid var(--border); padding: 18px 20px;
            box-shadow: var(--shadow-sm); transition: var(--transition);
        }
        .notif-full-card.unread {
            border-left: 4px solid var(--primary); background: var(--primary-pale);
        }
        .notif-full-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .notif-type-icon {
            width: 44px; height: 44px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; flex-shrink: 0;
        }
        .notif-type-icon.appointment { background: rgba(21,101,192,0.1); color: var(--primary); }
        .notif-type-icon.message { background: rgba(0,151,167,0.1); color: var(--teal); }
        .notif-type-icon.prescription { background: rgba(16,185,129,0.1); color: #10B981; }
        .notif-type-icon.general { background: rgba(245,158,11,0.1); color: #F59E0B; }
        .notif-full-body { flex: 1; }
        .notif-full-body strong { display: block; font-size: 0.92rem; margin-bottom: 4px; }
        .notif-full-body p { font-size: 0.85rem; color: var(--text-mid); margin-bottom: 6px; line-height: 1.5; }
        .notif-full-body small { font-size: 0.75rem; color: var(--text-light); }
        .notif-actions { display: flex; gap: 8px; align-items: center; flex-shrink: 0; }
        .btn-notif-read, .btn-notif-del {
            width: 32px; height: 32px; border-radius: 50%; display: flex;
            align-items: center; justify-content: center; font-size: 0.78rem;
            transition: var(--transition);
        }
        .btn-notif-read { background: var(--bg); color: var(--primary); }
        .btn-notif-read:hover { background: var(--primary); color: white; }
        .btn-notif-del { background: #FEF2F2; color: #EF4444; }
        .btn-notif-del:hover { background: #EF4444; color: white; }
        .notif-type-filter { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 20px; }
    </style>
</head>
<body class="dashboard-page">

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div class="dashboard-body">
        <div class="page-header">
            <div>
                <h1>Notifications</h1>
                <p>Stay updated with your appointments, messages and prescriptions</p>
            </div>
            <a href="notifications.php?mark_all=1" class="btn-outline">
                <i class="fas fa-check-double"></i> Mark All Read
            </a>
        </div>

        <!-- Filter -->
        <div class="notif-type-filter">
            <?php foreach (['all','appointment','message','prescription','general'] as $f): ?>
            <a href="notifications.php?filter=<?= $f ?>" class="filter-tab <?= $filter === $f ? 'active' : '' ?>">
                <?= ucfirst($f) ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Notifications List -->
        <div class="notif-page-list">
            <?php if (empty($notifications)): ?>
            <div class="empty-state" style="background: white; border-radius: var(--radius-md); border: 1px solid var(--border); padding: 60px;">
                <i class="fas fa-bell-slash"></i>
                <h3>No notifications</h3>
                <p>You're all caught up! Notifications will appear here as activity happens.</p>
            </div>
            <?php else: foreach ($notifications as $n): ?>
            <?php
                $nIconMap = array('appointment'=>'calendar-check','message'=>'comment-dots','prescription'=>'prescription-bottle-medical');
                $nIcon = isset($nIconMap[$n['type']]) ? $nIconMap[$n['type']] : 'bell';
            ?>
            <div class="notif-full-card <?= !$n['is_read'] ? 'unread' : '' ?>">
                <div class="notif-type-icon <?= htmlspecialchars($n['type']) ?>">
                    <i class="fas fa-<?= $nIcon ?>"></i>
                </div>
                <div class="notif-full-body">
                    <strong><?= htmlspecialchars($n['title']) ?></strong>
                    <p><?= htmlspecialchars($n['message']) ?></p>
                    <small>
                        <i class="fas fa-clock"></i>
                        <?= date('l, F j, Y \a\t g:i A', strtotime($n['created_at'])) ?>
                        · <?= ucfirst($n['type']) ?>
                    </small>
                </div>
                <div class="notif-actions">
                    <?php if (!$n['is_read']): ?>
                    <a href="notifications.php?mark=<?= $n['id'] ?>" class="btn-notif-read" title="Mark as read">
                        <i class="fas fa-check"></i>
                    </a>
                    <?php endif; ?>
                    <a href="notifications.php?delete=<?= $n['id'] ?>" class="btn-notif-del" title="Delete" onclick="return confirm('Delete this notification?')">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<script src="js/dashboard.js"></script>
</body>
</html>
