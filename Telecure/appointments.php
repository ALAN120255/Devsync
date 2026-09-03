<?php
require_once 'includes/config.php';
requireLogin();

$user = currentUser();
$db = getDB();
$role = $user['role'];
$msg = '';
$error = '';

// Book appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $doctorId = (int)$_POST['doctor_id'];
    $date     = sanitize($_POST['appointment_date']);
    $time     = sanitize($_POST['appointment_time']);
    $type     = sanitize($_POST['type']);
    $reason   = sanitize($_POST['reason']);

    if ($role !== 'patient') {
        $error = 'Only patients can book appointments.';
    } elseif (!$doctorId || !$date || !$time) {
        $error = 'Please fill in all required fields.';
    } elseif (strtotime($date) < strtotime('today')) {
        $error = 'Please select a future date.';
    } else {
        // Check for conflicts
        $check = $db->prepare("SELECT id FROM appointments WHERE doctor_id=? AND appointment_date=? AND appointment_time=? AND status NOT IN ('cancelled')");
        $check->execute([$doctorId, $date, $time]);
        if ($check->fetch()) {
            $error = 'This time slot is already booked. Please choose another time.';
        } else {
            $stmt = $db->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, type, reason, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$user['id'], $doctorId, $date, $time, $type, $reason]);
            $apptId = $db->lastInsertId();

            // Create video session if needed
            if ($type === 'video') {
                $roomName = 'telecure-' . $apptId . '-' . bin2hex(random_bytes(4));
                $db->prepare("INSERT INTO video_sessions (appointment_id, room_name) VALUES (?, ?)")->execute([$apptId, $roomName]);
            }

            // Notify doctor
            $doctorInfo = $db->prepare("SELECT full_name FROM users WHERE id=?")->execute([$doctorId]);
            addNotification($doctorId, 'New Appointment Request', $user['full_name'] . ' booked an appointment on ' . date('M j, Y', strtotime($date)) . ' at ' . date('g:i A', strtotime($time)), 'appointment');

            $msg = 'Appointment booked successfully! The doctor will confirm shortly.';
        }
    }
}

// Update appointment status (doctor action)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $apptId  = (int)$_POST['appointment_id'];
    $status  = sanitize($_POST['status']);
    $allowed = ['confirmed','completed','cancelled'];
    
    if ($role === 'doctor' && in_array($status, $allowed)) {
        $db->prepare("UPDATE appointments SET status=? WHERE id=? AND doctor_id=?")->execute([$status, $apptId, $user['id']]);
        
        // Get patient id to notify
        $pt = $db->prepare("SELECT patient_id FROM appointments WHERE id=?");
        $pt->execute([$apptId]);
        $pid = $pt->fetchColumn();
        addNotification($pid, 'Appointment ' . ucfirst($status), 'Your appointment has been ' . $status . '.', 'appointment');
        $msg = 'Appointment status updated!';
    }
}

// Get appointments
$filter = sanitize($_GET['filter'] ?? 'upcoming');
$filterMap = [
    'upcoming' => "AND a.appointment_date >= CURDATE() AND a.status NOT IN ('cancelled')",
    'past' => "AND a.appointment_date < CURDATE()",
    'pending' => "AND a.status = 'pending'",
    'confirmed' => "AND a.status = 'confirmed'",
    'cancelled' => "AND a.status = 'cancelled'"
];
$filterSQL = $filterMap[$filter] ?? '';

if ($role === 'patient') {
    $query = "SELECT a.*, u.full_name as other_name, u.profile_photo as other_photo, u.role as other_role, dp.specialization, dp.consultation_fee,
                vs.room_name as video_room
              FROM appointments a
              JOIN users u ON a.doctor_id = u.id
              LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
              LEFT JOIN video_sessions vs ON vs.appointment_id = a.id
              WHERE a.patient_id = ? $filterSQL
              ORDER BY a.appointment_date " . ($filter === 'past' ? 'DESC' : 'ASC') . ", a.appointment_time ASC";
} else {
    $query = "SELECT a.*, u.full_name as other_name, u.phone as patient_phone, u.profile_photo as other_photo, u.role as other_role,
                vs.room_name as video_room
              FROM appointments a
              JOIN users u ON a.patient_id = u.id
              LEFT JOIN video_sessions vs ON vs.appointment_id = a.id
              WHERE a.doctor_id = ? $filterSQL
              ORDER BY a.appointment_date " . ($filter === 'past' ? 'DESC' : 'ASC') . ", a.appointment_time ASC";
}

$stmt = $db->prepare($query);
$stmt->execute([$user['id']]);
$appointments = $stmt->fetchAll();

// Get doctors list for booking
$doctors = $db->query("SELECT u.id, u.full_name, dp.specialization, dp.consultation_fee, dp.rating
    FROM users u JOIN doctor_profiles dp ON u.id = dp.user_id
    WHERE u.role='doctor' AND dp.is_verified=1 ORDER BY dp.rating DESC")->fetchAll();

$selectedDoctor = isset($_GET['doctor']) ? (int)$_GET['doctor'] : null;
$showBooking = ($_GET['action'] ?? '') === 'book' || $role === 'patient';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - TeleCure</title>
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
        <div class="page-header">
            <div>
                <h1>Appointments</h1>
                <p><?= $role === 'patient' ? 'Manage your appointments with doctors' : 'Your patient appointments' ?></p>
            </div>
            <?php if ($role === 'patient'): ?>
            <button class="btn-primary" onclick="document.getElementById('bookModal').style.display='flex'">
                <i class="fas fa-calendar-plus"></i> Book Appointment
            </button>
            <?php endif; ?>
        </div>

        <?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <?php foreach (['upcoming','pending','confirmed','past','cancelled'] as $f): ?>
            <a href="appointments.php?filter=<?= $f ?>" class="filter-tab <?= $filter === $f ? 'active' : '' ?>">
                <?= ucfirst($f) ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Appointments List -->
        <div class="appointments-grid">
            <?php if (empty($appointments)): ?>
            <div class="empty-state full-width">
                <i class="fas fa-calendar-times"></i>
                <h3>No <?= $filter ?> appointments</h3>
                <?php if ($role === 'patient'): ?>
                <p>Book your first appointment with one of our doctors</p>
                <button onclick="document.getElementById('bookModal').style.display='flex'" class="btn-primary">Book Now</button>
                <?php else: ?>
                <p>No patient appointments in this category</p>
                <?php endif; ?>
            </div>
            <?php else: foreach ($appointments as $appt):
                $statusColors = ['pending'=>'#F59E0B','confirmed'=>'#10B981','completed'=>'#6366F1','cancelled'=>'#EF4444'];
                $statusColor = $statusColors[$appt['status']] ?? '#6B7280';
            ?>
            <div class="appointment-card" style="--status-color: <?= $statusColor ?>">
                <div class="appt-card-header">
                    <div class="appt-card-avatar">
                        <img src="<?= avatarUrl(['full_name' => $appt['other_name'], 'profile_photo' => $appt['other_photo'] ?? null, 'role' => $appt['other_role'] ?? ($role === 'patient' ? 'doctor' : 'patient')], 50) ?>" alt="">
                    </div>
                    <div class="appt-card-info">
                        <h4><?= htmlspecialchars($appt['other_name']) ?></h4>
                        <?php if (isset($appt['specialization'])): ?>
                        <span class="spec-tag"><?= htmlspecialchars($appt['specialization']) ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="appt-status-badge" style="background: <?= $statusColor ?>20; color: <?= $statusColor ?>; border: 1px solid <?= $statusColor ?>40">
                        <?= ucfirst($appt['status']) ?>
                    </span>
                </div>
                <div class="appt-card-body">
                    <div class="appt-detail"><i class="fas fa-calendar"></i> <?= date('l, F j, Y', strtotime($appt['appointment_date'])) ?></div>
                    <div class="appt-detail"><i class="fas fa-clock"></i> <?= date('g:i A', strtotime($appt['appointment_time'])) ?></div>
                    <div class="appt-detail"><i class="fas fa-<?= $appt['type'] === 'video' ? 'video' : ($appt['type'] === 'chat' ? 'comment' : 'hospital') ?>"></i> <?= ucfirst($appt['type']) ?> Consultation</div>
                    <?php if (isset($appt['consultation_fee'])): ?>
                    <div class="appt-detail"><i class="fas fa-coins"></i> R<?= number_format($appt['consultation_fee'], 2) ?></div>
                    <?php endif; ?>
                    <?php if ($appt['reason']): ?>
                    <div class="appt-reason"><i class="fas fa-notes-medical"></i> <?= htmlspecialchars($appt['reason']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="appt-card-actions">
                    <?php if ($appt['type'] === 'video' && $appt['status'] === 'confirmed' && isset($appt['video_room'])): ?>
                    <a href="video.php?room=<?= $appt['video_room'] ?>" class="btn-video-join">
                        <i class="fas fa-video"></i> Join Video Call
                    </a>
                    <?php endif; ?>
                    <a href="chat.php?user=<?= $role === 'patient' ? ($appt['doctor_id'] ?? '') : $appt['patient_id'] ?>" class="btn-chat-sm">
                        <i class="fas fa-comment"></i> Message
                    </a>
                    <?php if ($role === 'doctor' && $appt['status'] === 'pending'): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="appointment_id" value="<?= $appt['id'] ?>">
                        <input type="hidden" name="status" value="confirmed">
                        <button type="submit" name="update_status" class="btn-confirm-sm">
                            <i class="fas fa-check"></i> Confirm
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if ($appt['status'] !== 'cancelled' && $appt['status'] !== 'completed'): ?>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Cancel this appointment?')">
                        <input type="hidden" name="appointment_id" value="<?= $appt['id'] ?>">
                        <input type="hidden" name="status" value="cancelled">
                        <button type="submit" name="update_status" class="btn-cancel-sm">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- Book Appointment Modal -->
<div class="modal-overlay" id="bookModal" style="display:<?= ($_GET['action'] ?? '') === 'book' ? 'flex' : 'none' ?>">
    <div class="modal modal-large">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-plus"></i> Book Appointment</h3>
            <button onclick="document.getElementById('bookModal').style.display='none'"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" action="appointments.php" class="book-form">
                <div class="form-group">
                    <label>Choose Doctor</label>
                    <select name="doctor_id" required onchange="updateFee(this)">
                        <option value="">Select a doctor...</option>
                        <?php foreach ($doctors as $doc): ?>
                        <option value="<?= $doc['id'] ?>" data-fee="<?= $doc['consultation_fee'] ?>" <?= $selectedDoctor == $doc['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($doc['full_name']) ?> - <?= htmlspecialchars($doc['specialization']) ?> ⭐ <?= number_format($doc['rating'], 1) ?> (R<?= number_format($doc['consultation_fee'], 0) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="feeDisplay" style="display:none" class="fee-display">
                    <i class="fas fa-coins"></i> Consultation Fee: <strong id="feeAmount">R0</strong>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Appointment Date</label>
                        <input type="date" name="appointment_date" required min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_GET['date'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Preferred Time</label>
                        <select name="appointment_time" required>
                            <?php
                            $times = ['08:00','08:30','09:00','09:30','10:00','10:30','11:00','11:30','12:00','12:30','14:00','14:30','15:00','15:30','16:00','16:30'];
                            foreach ($times as $t): ?>
                            <option value="<?= $t ?>" <?= (isset($_GET['time']) && $_GET['time']===$t) ? 'selected' : '' ?>><?= date('g:i A', strtotime($t)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Consultation Type</label>
                    <div class="type-selector">
                        <label class="type-option">
                            <input type="radio" name="type" value="video" checked>
                            <div class="type-card">
                                <i class="fas fa-video"></i>
                                <span>Video Call</span>
                            </div>
                        </label>
                        <label class="type-option">
                            <input type="radio" name="type" value="chat">
                            <div class="type-card">
                                <i class="fas fa-comment-medical"></i>
                                <span>Chat</span>
                            </div>
                        </label>
                        <label class="type-option">
                            <input type="radio" name="type" value="in_person">
                            <div class="type-card">
                                <i class="fas fa-hospital"></i>
                                <span>In-Person</span>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Reason for Visit</label>
                    <textarea name="reason" placeholder="Briefly describe your symptoms or reason for appointment..." rows="3"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="document.getElementById('bookModal').style.display='none'" class="btn-outline-cancel">Cancel</button>
                    <button type="submit" name="book_appointment" class="btn-primary">
                        <i class="fas fa-calendar-check"></i> Book Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateFee(sel) {
    const opt = sel.options[sel.selectedIndex];
    const fee = opt.dataset.fee;
    if (fee) {
        document.getElementById('feeDisplay').style.display = 'block';
        document.getElementById('feeAmount').textContent = 'R' + parseFloat(fee).toFixed(2);
    } else {
        document.getElementById('feeDisplay').style.display = 'none';
    }
}

document.getElementById('bookModal')?.addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>
</body>
</html>
