<?php
require_once 'includes/config.php';
requireLogin();

$user = currentUser();
$db = getDB();
$role = $user['role'];

// Stats
if ($role === 'patient') {
    $appointments = $db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ?");
    $appointments->execute([$user['id']]);
    $apptCount = $appointments->fetchColumn();

    $prescriptions = $db->prepare("SELECT COUNT(*) FROM prescriptions WHERE patient_id = ?");
    $prescriptions->execute([$user['id']]);
    $prescCount = $prescriptions->fetchColumn();

    $unreadMsgs = getUnreadMessages($user['id']);
    $notifications = getUnreadNotifications($user['id']);

    // Upcoming appointments
    $upcoming = $db->prepare("SELECT a.*, u.full_name as doctor_name, u.profile_photo as other_photo, u.role as other_role, dp.specialization 
        FROM appointments a 
        JOIN users u ON a.doctor_id = u.id 
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        WHERE a.patient_id = ? AND a.status IN ('pending','confirmed') AND a.appointment_date >= CURDATE()
        ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 5");
    $upcoming->execute([$user['id']]);
    $upcomingAppts = $upcoming->fetchAll();

    // Recent prescriptions
    $recentRx = $db->prepare("SELECT p.*, u.full_name as doctor_name, dp.specialization 
        FROM prescriptions p 
        JOIN users u ON p.doctor_id = u.id 
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        WHERE p.patient_id = ? ORDER BY p.created_at DESC LIMIT 3");
    $recentRx->execute([$user['id']]);
    $recentPrescriptions = $recentRx->fetchAll();

} else {
    // Doctor stats
    $appointments = $db->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ?");
    $appointments->execute([$user['id']]);
    $apptCount = $appointments->fetchColumn();

    $patients = $db->prepare("SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE doctor_id = ?");
    $patients->execute([$user['id']]);
    $patientCount = $patients->fetchColumn();

    $prescriptions = $db->prepare("SELECT COUNT(*) FROM prescriptions WHERE doctor_id = ?");
    $prescriptions->execute([$user['id']]);
    $prescCount = $prescriptions->fetchColumn();

    $unreadMsgs = getUnreadMessages($user['id']);

    // Today's appointments
    $upcoming = $db->prepare("SELECT a.*, u.full_name as patient_name, u.phone as patient_phone, u.profile_photo as other_photo, u.role as other_role
        FROM appointments a 
        JOIN users u ON a.patient_id = u.id 
        WHERE a.doctor_id = ? AND a.appointment_date >= CURDATE()
        ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 5");
    $upcoming->execute([$user['id']]);
    $upcomingAppts = $upcoming->fetchAll();
}

// Recent messages (chat list)
$recentChats = $db->prepare("
    SELECT DISTINCT
        CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END as other_user_id,
        u.full_name, u.role, u.profile_photo,
        m.message as last_message, m.created_at as last_time,
        SUM(CASE WHEN m.receiver_id = ? AND m.is_read = 0 THEN 1 ELSE 0 END) as unread_count
    FROM messages m
    JOIN users u ON u.id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
    WHERE m.sender_id = ? OR m.receiver_id = ?
    GROUP BY other_user_id, u.full_name, u.role, u.profile_photo, m.message, m.created_at
    ORDER BY m.created_at DESC LIMIT 5
");
$recentChats->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
$chats = $recentChats->fetchAll();

$avatarUrl = avatarUrl($user, 80);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TeleCure</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-page">

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div class="dashboard-body">
        <?php if (isset($_GET['welcome'])): ?>
        <div class="welcome-banner">
            <div class="welcome-content">
                <i class="fas fa-party-horn"></i>
                <div>
                    <h3>Welcome to TeleCure, <?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?>! 🎉</h3>
                    <p>Your account has been created. Let's get you started!</p>
                </div>
            </div>
            <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1>Dashboard</h1>
                <p>Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>, <?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?>! Here's your overview.</p>
            </div>
            <div class="header-actions">
                <?php if ($role === 'patient'): ?>
                <a href="appointments.php?action=book" class="btn-primary">
                    <i class="fas fa-plus"></i> Book Appointment
                </a>
                <?php else: ?>
                <a href="prescriptions.php?action=write" class="btn-primary">
                    <i class="fas fa-pen-nib"></i> Write Prescription
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card blue animate-card" style="--delay: 0.1s">
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-info">
                    <span class="stat-value"><?= $apptCount ?></span>
                    <span class="stat-label">Total Appointments</span>
                </div>
                <div class="stat-trend up"><i class="fas fa-arrow-up"></i> 12%</div>
            </div>
            <div class="stat-card teal animate-card" style="--delay: 0.2s">
                <div class="stat-icon"><i class="fas fa-comments"></i></div>
                <div class="stat-info">
                    <span class="stat-value"><?= $unreadMsgs ?></span>
                    <span class="stat-label">Unread Messages</span>
                </div>
                <?php if ($unreadMsgs > 0): ?>
                <div class="stat-badge"><?= $unreadMsgs ?> new</div>
                <?php endif; ?>
            </div>
            <div class="stat-card light-blue animate-card" style="--delay: 0.3s">
                <div class="stat-icon"><i class="fas fa-file-medical"></i></div>
                <div class="stat-info">
                    <span class="stat-value"><?= $prescCount ?></span>
                    <span class="stat-label"><?= $role === 'doctor' ? 'Prescriptions Issued' : 'My Prescriptions' ?></span>
                </div>
            </div>
            <?php if ($role === 'doctor'): ?>
            <div class="stat-card green animate-card" style="--delay: 0.4s">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <span class="stat-value"><?= $patientCount ?></span>
                    <span class="stat-label">Total Patients</span>
                </div>
            </div>
            <?php else: ?>
            <div class="stat-card green animate-card" style="--delay: 0.4s">
                <div class="stat-icon"><i class="fas fa-video"></i></div>
                <div class="stat-info">
                    <span class="stat-value">0</span>
                    <span class="stat-label">Video Consultations</span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="dashboard-grid">
            <!-- Upcoming Appointments -->
            <div class="dash-card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-alt"></i> Upcoming Appointments</h3>
                    <a href="appointments.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="card-body">
                    <?php if (empty($upcomingAppts)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-plus"></i>
                        <p>No upcoming appointments</p>
                        <?php if ($role === 'patient'): ?>
                        <a href="appointments.php?action=book" class="btn-sm-primary">Book Now</a>
                        <?php endif; ?>
                    </div>
                    <?php else: foreach ($upcomingAppts as $appt):
                        $otherName = $role === 'patient' ? $appt['doctor_name'] : $appt['patient_name'];
                        $otherParts = array_slice(explode(' ', $otherName), 0, 2);
                        $initials = '';
                        foreach ($otherParts as $w) { $initials .= $w[0]; }
                        switch($appt['status']) {
                            case 'confirmed': $statusClass = 'status-green'; break;
                            case 'pending':   $statusClass = 'status-yellow'; break;
                            case 'cancelled': $statusClass = 'status-red'; break;
                            default:          $statusClass = 'status-gray';
                        }
                    ?>
                    <div class="appointment-item">
                        <div class="appt-avatar">
                            <img src="<?= avatarUrl(['full_name' => $otherName, 'profile_photo' => $appt['other_photo'] ?? null, 'role' => $appt['other_role'] ?? ($role === 'patient' ? 'doctor' : 'patient')], 45) ?>" alt="<?= htmlspecialchars($otherName) ?>">
                        </div>
                        <div class="appt-info">
                            <strong><?= htmlspecialchars($otherName) ?></strong>
                            <?php if ($role === 'patient' && isset($appt['specialization'])): ?>
                            <small><?= htmlspecialchars($appt['specialization']) ?></small>
                            <?php endif; ?>
                            <div class="appt-meta">
                                <span><i class="fas fa-calendar"></i> <?= date('D, M j', strtotime($appt['appointment_date'])) ?></span>
                                <span><i class="fas fa-clock"></i> <?= date('g:i A', strtotime($appt['appointment_time'])) ?></span>
                                <span><i class="fas <?= $appt['type'] === 'video' ? 'fa-video' : 'fa-comment' ?>"></i> <?= ucfirst($appt['type']) ?></span>
                            </div>
                        </div>
                        <div class="appt-actions">
                            <span class="status-badge <?= $statusClass ?>"><?= ucfirst($appt['status']) ?></span>
                            <?php if ($appt['type'] === 'video' && $appt['status'] === 'confirmed'): ?>
                            <a href="video.php?appointment=<?= $appt['id'] ?>" class="btn-video-sm">
                                <i class="fas fa-video"></i> Join
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- Recent Messages -->
            <div class="dash-card">
                <div class="card-header">
                    <h3><i class="fas fa-comments"></i> Recent Messages</h3>
                    <a href="chat.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="card-body">
                    <?php if (empty($chats)): ?>
                    <div class="empty-state">
                        <i class="fas fa-comment-medical"></i>
                        <p>No messages yet</p>
                        <a href="<?= $role === 'patient' ? 'appointments.php?action=book' : 'chat.php' ?>" class="btn-sm-primary">Start Chat</a>
                    </div>
                    <?php else: foreach ($chats as $chat): ?>
                    <a href="chat.php?user=<?= $chat['other_user_id'] ?>" class="chat-item <?= $chat['unread_count'] > 0 ? 'unread' : '' ?>">
                        <div class="chat-avatar">
                            <img src="<?= avatarUrl($chat, 45) ?>" alt="">
                            <span class="online-dot"></span>
                        </div>
                        <div class="chat-info">
                            <strong><?= htmlspecialchars($chat['full_name']) ?></strong>
                            <small class="role-tag"><?= ucfirst($chat['role']) ?></small>
                            <p class="last-msg"><?= htmlspecialchars(substr($chat['last_message'], 0, 40)) ?>...</p>
                        </div>
                        <div class="chat-meta">
                            <span class="time"><?= date('g:i A', strtotime($chat['last_time'])) ?></span>
                            <?php if ($chat['unread_count'] > 0): ?>
                            <span class="unread-badge"><?= $chat['unread_count'] ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="dash-card quick-actions-card">
                <div class="card-header">
                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div class="quick-actions-grid">
                        <?php if ($role === 'patient'): ?>
                        <a href="appointments.php?action=book" class="quick-action-btn">
                            <i class="fas fa-calendar-plus"></i>
                            <span>Book Appointment</span>
                        </a>
                        <a href="chat.php" class="quick-action-btn">
                            <i class="fas fa-comment-medical"></i>
                            <span>Message Doctor</span>
                        </a>
                        <a href="prescriptions.php" class="quick-action-btn">
                            <i class="fas fa-prescription-bottle"></i>
                            <span>My Prescriptions</span>
                        </a>
                        <a href="video.php" class="quick-action-btn">
                            <i class="fas fa-video"></i>
                            <span>Video Call</span>
                        </a>
                        <?php else: ?>
                        <a href="appointments.php" class="quick-action-btn">
                            <i class="fas fa-calendar-check"></i>
                            <span>My Schedule</span>
                        </a>
                        <a href="chat.php" class="quick-action-btn">
                            <i class="fas fa-comment-dots"></i>
                            <span>Patient Chats</span>
                        </a>
                        <a href="prescriptions.php?action=write" class="quick-action-btn">
                            <i class="fas fa-pen-nib"></i>
                            <span>Write Prescription</span>
                        </a>
                        <a href="video.php" class="quick-action-btn">
                            <i class="fas fa-video"></i>
                            <span>Start Video Call</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($role === 'patient' && !empty($recentPrescriptions)): ?>
            <!-- Recent Prescriptions for patient -->
            <div class="dash-card">
                <div class="card-header">
                    <h3><i class="fas fa-file-prescription"></i> Recent Prescriptions</h3>
                    <a href="prescriptions.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="card-body">
                    <?php foreach ($recentPrescriptions as $rx): ?>
                    <div class="prescription-item">
                        <div class="rx-icon"><i class="fas fa-prescription"></i></div>
                        <div class="rx-info">
                            <strong>Dr. <?= htmlspecialchars($rx['doctor_name']) ?></strong>
                            <small><?= htmlspecialchars($rx['specialization'] ?? 'General') ?></small>
                            <p><?= htmlspecialchars(substr($rx['diagnosis'] ?? 'Prescription', 0, 50)) ?></p>
                        </div>
                        <div class="rx-meta">
                            <span><?= date('M j, Y', strtotime($rx['issued_date'])) ?></span>
                            <a href="prescriptions.php?view=<?= $rx['id'] ?>" class="btn-view-rx">View</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="js/dashboard.js"></script>
</body>
</html>
