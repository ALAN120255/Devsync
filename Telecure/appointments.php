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
    requireCsrf();
    $doctorId = (int)($_POST['doctor_id'] ?? 0);
    $date     = sanitize($_POST['appointment_date'] ?? '');
    $time     = sanitize($_POST['appointment_time'] ?? '');
    $type     = sanitize($_POST['type'] ?? 'video');
    $reason   = sanitize($_POST['reason'] ?? '');

    $dateObj  = DateTime::createFromFormat('Y-m-d', $date);
    $validDate = $dateObj && $dateObj->format('Y-m-d') === $date;

    if ($role !== 'patient') {
        $error = 'Only patients can book appointments.';
    } elseif (!$doctorId || !$date || !$time) {
        $error = 'Please fill in all required fields.';
    } elseif (!$validDate) {
        $error = 'Please choose a valid date.';
    } elseif (!in_array($type, ['video', 'chat', 'in_person'], true)) {
        $error = 'Invalid consultation type.';
    } elseif (!in_array($time, appointmentSlots(), true)) {
        $error = 'Please choose one of the available time slots.';
    } elseif (strtotime("$date $time") <= time()) {
        $error = 'Please select a date and time in the future.';
    } else {
        // The doctor must exist, be active and verified.
        $dchk = $db->prepare("SELECT u.full_name FROM users u JOIN doctor_profiles dp ON dp.user_id = u.id
                              WHERE u.id = ? AND u.role = 'doctor' AND u.is_active = 1 AND dp.is_verified = 1");
        $dchk->execute([$doctorId]);
        if (!$dchk->fetch()) {
            $error = 'That doctor is not available for booking.';
        } else {
            // Check for conflicts inside a transaction so two patients cannot grab the same slot.
            $db->beginTransaction();
            try {
                // Lock the doctor's row first: concurrent bookings for this doctor queue up here,
                // so the conflict check below is always against the latest data.
                $db->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE")->execute([$doctorId]);
                $check = $db->prepare("SELECT id FROM appointments WHERE doctor_id=? AND appointment_date=? AND appointment_time=? AND status NOT IN ('cancelled') FOR UPDATE");
                $check->execute([$doctorId, $date, $time]);
                if ($check->fetch()) {
                    $db->rollBack();
                    $error = 'Sorry - someone else just booked that slot. The list below now shows the times that are still free.';
                } else {
                    $stmt = $db->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, type, reason, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                    $stmt->execute([$user['id'], $doctorId, $date, $time, $type, $reason]);
                    $apptId = $db->lastInsertId();

                    // Create video session if needed
                    if ($type === 'video') {
                        $roomName = 'telecure-' . $apptId . '-' . bin2hex(random_bytes(4));
                        $db->prepare("INSERT INTO video_sessions (appointment_id, room_name) VALUES (?, ?)")->execute([$apptId, $roomName]);
                    }
                    $db->commit();

                    addNotification($doctorId, 'New Appointment Request', $user['full_name'] . ' booked an appointment on ' . date('M j, Y', strtotime($date)) . ' at ' . date('g:i A', strtotime($time)), 'appointment');
                    $msg = 'Appointment booked successfully! The doctor will confirm shortly.';
                }
            } catch (Exception $ex) {
                if ($db->inTransaction()) $db->rollBack();
                $error = 'Could not book the appointment. Please try again.';
            }
        }
    }
}

// Update appointment status.
//  - Doctors can confirm / complete / cancel THEIR appointments.
//  - Patients can cancel THEIR OWN appointments (the Cancel button used to do nothing for patients).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    requireCsrf();
    $apptId = (int)($_POST['appointment_id'] ?? 0);
    $status = sanitize($_POST['status'] ?? '');
    $allowed = ($role === 'doctor') ? ['confirmed', 'completed', 'cancelled'] : ['cancelled'];

    if (!in_array($status, $allowed, true)) {
        $error = 'You are not allowed to do that.';
    } else {
        $ownerCol = ($role === 'doctor') ? 'doctor_id' : 'patient_id';
        $otherCol = ($role === 'doctor') ? 'patient_id' : 'doctor_id';

        $cur = $db->prepare("SELECT status, $otherCol AS other_id FROM appointments WHERE id=? AND $ownerCol=?");
        $cur->execute([$apptId, $user['id']]);
        $row = $cur->fetch();

        if (!$row) {
            $error = 'Appointment not found.';
        } elseif (in_array($row['status'], ['cancelled', 'completed'], true)) {
            $error = 'This appointment is already ' . $row['status'] . '.';
        } elseif ($status === 'confirmed' && $row['status'] !== 'pending') {
            $error = 'Only pending appointments can be confirmed.';
        } elseif ($status === 'completed' && $row['status'] !== 'confirmed') {
            $error = 'Confirm the appointment before marking it completed.';
        } else {
            $db->prepare("UPDATE appointments SET status=? WHERE id=? AND $ownerCol=?")->execute([$status, $apptId, $user['id']]);
            $who = ($role === 'doctor') ? doctorName($user['full_name']) : $user['full_name'];
            addNotification($row['other_id'], 'Appointment ' . ucfirst($status), $who . ' marked your appointment as ' . $status . '.', 'appointment');
            $msg = 'Appointment ' . $status . '.';
        }
    }
}

// Get appointments
$filter = sanitize($_GET['filter'] ?? 'upcoming');
if (!in_array($filter, ['upcoming','past','pending','confirmed','cancelled'], true)) $filter = 'upcoming';
$filterMap = [
    // Judged by date AND time (1 hour grace so a consultation that is under way stays "upcoming").
    'upcoming' => "AND TIMESTAMP(a.appointment_date, a.appointment_time) >= DATE_SUB(NOW(), INTERVAL 1 HOUR) AND a.status NOT IN ('cancelled')",
    'past' => "AND TIMESTAMP(a.appointment_date, a.appointment_time) < DATE_SUB(NOW(), INTERVAL 1 HOUR)",
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

$bookingFailed = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment']) && !empty($error));
$selectedDoctor = $bookingFailed ? (int)($_POST['doctor_id'] ?? 0) : (isset($_GET['doctor']) ? (int)$_GET['doctor'] : null);
$formDate = $bookingFailed ? sanitize($_POST['appointment_date'] ?? '') : sanitize($_GET['date'] ?? '');
$formTime = $bookingFailed ? sanitize($_POST['appointment_time'] ?? '') : sanitize($_GET['time'] ?? '');
$formType = $bookingFailed ? sanitize($_POST['type'] ?? 'video') : 'video';
$formReason = $bookingFailed ? sanitize($_POST['reason'] ?? '') : '';
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
            <button class="btn-primary" onclick="openBooking()">
                <i class="fas fa-calendar-plus"></i> Book Appointment
            </button>
            <?php endif; ?>
        </div>

        <?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($msg) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div><?php endif; ?>

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
                <h3>No <?= e($filter) ?> appointments</h3>
                <?php if ($role === 'patient'): ?>
                <p>Book your first appointment with one of our doctors</p>
                <button onclick="openBooking()" class="btn-primary">Book Now</button>
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
                    <a href="video.php?room=<?= urlencode($appt['video_room']) ?>" class="btn-video-join">
                        <i class="fas fa-video"></i> Join Video Call
                    </a>
                    <?php endif; ?>
                    <a href="chat.php?user=<?= $role === 'patient' ? ($appt['doctor_id'] ?? '') : $appt['patient_id'] ?>" class="btn-chat-sm">
                        <i class="fas fa-comment"></i> Message
                    </a>
                    <?php if ($role === 'doctor' && $appt['status'] === 'pending'): ?>
                    <form method="POST" style="display:inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="appointment_id" value="<?= (int)$appt['id'] ?>">
                        <input type="hidden" name="status" value="confirmed">
                        <button type="submit" name="update_status" class="btn-confirm-sm">
                            <i class="fas fa-check"></i> Confirm
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if ($role === 'doctor' && $appt['status'] === 'confirmed'): ?>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Mark this consultation as completed?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="appointment_id" value="<?= (int)$appt['id'] ?>">
                        <input type="hidden" name="status" value="completed">
                        <button type="submit" name="update_status" class="btn-confirm-sm">
                            <i class="fas fa-check-double"></i> Complete
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if ($appt['status'] !== 'cancelled' && $appt['status'] !== 'completed'): ?>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Cancel this appointment?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="appointment_id" value="<?= (int)$appt['id'] ?>">
                        <input type="hidden" name="status" value="cancelled">
                        <button type="submit" name="update_status" class="btn-cancel-sm">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if ($role === 'patient' && $appt['status'] === 'completed'): ?>
                    <a href="doctor_profile.php?id=<?= (int)$appt['doctor_id'] ?>#reviews" class="btn-chat-sm"><i class="fas fa-star"></i> Review</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- Book Appointment Modal -->
<div class="modal-overlay" id="bookModal" style="display:<?= (($_GET['action'] ?? '') === 'book' || $bookingFailed) ? 'flex' : 'none' ?>">
    <div class="modal modal-large">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-plus"></i> Book Appointment</h3>
            <button onclick="document.getElementById('bookModal').style.display='none'"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" action="appointments.php" class="book-form">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label>Choose Doctor</label>
                    <select name="doctor_id" id="bookDoctor" required onchange="updateFee(this); loadSlots(false)">
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
                        <input type="date" name="appointment_date" id="bookDate" required min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($formDate) ?>" onchange="loadSlots(false)">
                    </div>
                    <div class="form-group">
                        <label>Preferred Time</label>
                        <select name="appointment_time" id="bookTime" required>
                            <option value="">Select doctor &amp; date first</option>
                        </select>
                        <small id="liveStatus" class="live-status off"><span class="live-dot"></span> Waiting for doctor &amp; date</small>
                    </div>
                </div>
                <div class="form-group">
                    <label>Consultation Type</label>
                    <div class="type-selector">
                        <label class="type-option">
                            <input type="radio" name="type" value="video" <?= $formType === 'video' ? 'checked' : '' ?>>
                            <div class="type-card">
                                <i class="fas fa-video"></i>
                                <span>Video Call</span>
                            </div>
                        </label>
                        <label class="type-option">
                            <input type="radio" name="type" value="chat" <?= $formType === 'chat' ? 'checked' : '' ?>>
                            <div class="type-card">
                                <i class="fas fa-comment-medical"></i>
                                <span>Chat</span>
                            </div>
                        </label>
                        <label class="type-option">
                            <input type="radio" name="type" value="in_person" <?= $formType === 'in_person' ? 'checked' : '' ?>>
                            <div class="type-card">
                                <i class="fas fa-hospital"></i>
                                <span>In-Person</span>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Reason for Visit</label>
                    <textarea name="reason" placeholder="Briefly describe your symptoms or reason for appointment..." rows="3"><?= htmlspecialchars($formReason) ?></textarea>
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

// ── Live slot availability (api/appointments.php?action=slots) ─────────────────
// Re-checked every 5 seconds while the booking window is open, and instantly when the
// doctor/date changes or the tab regains focus, so the list is never stale.
var preselectTime = <?= json_encode($formTime) ?>;
var SLOT_REFRESH_MS = 5000;
var slotTimer = null, slotReq = 0;

function bookModalOpen() {
    var m = document.getElementById('bookModal');
    return m && m.style.display !== 'none';
}

function setLiveStatus(text, ok) {
    var el = document.getElementById('liveStatus');
    if (!el) return;
    el.className = 'live-status ' + (ok ? 'on' : 'off');
    el.innerHTML = '<span class="live-dot"></span> ' + text;
}

function loadSlots(silent) {
    var doc  = document.getElementById('bookDoctor').value;
    var date = document.getElementById('bookDate').value;
    var sel  = document.getElementById('bookTime');
    if (!doc || !date) { sel.innerHTML = '<option value="">Select doctor &amp; date first</option>'; setLiveStatus('Waiting for doctor &amp; date', false); return; }

    var keep = sel.value || preselectTime;           // remember what the patient had picked
    var myReq = ++slotReq;
    if (!silent) sel.innerHTML = '<option value="">Loading...</option>';

    fetch('api/appointments.php?action=slots&doctor_id=' + encodeURIComponent(doc) + '&date=' + encodeURIComponent(date) + '&_=' + Date.now(), { cache: 'no-store' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (myReq !== slotReq) return;           // a newer request is already in flight
            var slots = data.slots || [];
            var free = slots.filter(function (s) { return s.available; });
            sel.innerHTML = '';
            if (!free.length) {
                sel.innerHTML = '<option value="">No free slots on this date</option>';
            } else {
                var placeholder = document.createElement('option');
                placeholder.value = ''; placeholder.textContent = 'Select a time (' + free.length + ' free)';
                sel.appendChild(placeholder);
                free.forEach(function (s) {
                    var o = document.createElement('option');
                    o.value = s.time; o.textContent = s.label;
                    sel.appendChild(o);
                });
                if (keep && free.some(function (s) { return s.time === keep; })) sel.value = keep;
            }
            if (keep && sel.value !== keep && silent) {
                setLiveStatus('Your chosen time was just taken - please pick another', false);
                return;
            }
            preselectTime = '';
            setLiveStatus('Live &middot; updated ' + (data.now_label || new Date().toLocaleTimeString()), true);
        })
        .catch(function () { if (myReq === slotReq && !silent) sel.innerHTML = '<option value="">Could not load slots</option>'; setLiveStatus('Connection lost - retrying...', false); });
}

function startSlotPolling() {
    stopSlotPolling();
    slotTimer = setInterval(function () { if (!document.hidden && bookModalOpen()) loadSlots(true); }, SLOT_REFRESH_MS);
}
function stopSlotPolling() { if (slotTimer) { clearInterval(slotTimer); slotTimer = null; } }
document.addEventListener('visibilitychange', function () { if (!document.hidden && bookModalOpen()) loadSlots(true); });
window.addEventListener('focus', function () { if (bookModalOpen()) loadSlots(true); });

// Opening the booking window refreshes immediately.
function openBooking() {
    document.getElementById('bookModal').style.display = 'flex';
    loadSlots(false);
}

// ── Keep the appointment list itself up to date (new requests, confirmations, cancellations) ──
var listSig = null;
function checkList() {
    if (document.hidden || bookModalOpen()) return;   // never yank the page away while someone is booking
    fetch('api/appointments.php?action=signature&_=' + Date.now(), { cache: 'no-store' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (listSig === null) { listSig = d.sig; return; }
            // replace() = a fresh GET, so the browser never re-submits a booking form.
            if (d.sig !== listSig && !bookModalOpen()) location.replace(location.pathname + location.search);
        }).catch(function () {});
}
checkList();
setInterval(checkList, 8000);

document.addEventListener('DOMContentLoaded', function () {
    var d = document.getElementById('bookDoctor');
    if (d && d.value) updateFee(d);
    loadSlots(false);
    startSlotPolling();
});

document.getElementById('bookModal')?.addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>
</body>
</html>
