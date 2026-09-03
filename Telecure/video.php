<?php
require_once 'includes/config.php';
requireLogin();

$user = currentUser();
$db = getDB();
$role = $user['role'];

$roomName = sanitize($_GET['room'] ?? '');
$appointmentId = (int)($_GET['appointment'] ?? 0);

// Get room from appointment
if ($appointmentId && !$roomName) {
    $stmt = $db->prepare("SELECT vs.room_name FROM video_sessions vs 
        JOIN appointments a ON vs.appointment_id = a.id
        WHERE vs.appointment_id = ? AND (a.patient_id = ? OR a.doctor_id = ?)");
    $stmt->execute([$appointmentId, $user['id'], $user['id']]);
    $session = $stmt->fetch();
    if ($session) $roomName = $session['room_name'];
}

// Create ad-hoc room if no appointment
if (!$roomName && isset($_GET['user'])) {
    $withUserId = (int)$_GET['user'];
    $roomName = 'telecure-adhoc-' . min($user['id'], $withUserId) . '-' . max($user['id'], $withUserId);
}

// Get appointment details for context
$apptDetails = null;
if ($appointmentId) {
    $stmt = $db->prepare("SELECT a.*, u.full_name as other_name, u.profile_photo as other_photo, u.role as other_role 
        FROM appointments a
        JOIN users u ON (CASE WHEN a.patient_id = ? THEN a.doctor_id = u.id ELSE a.patient_id = u.id END)
        WHERE a.id = ? AND (a.patient_id = ? OR a.doctor_id = ?)");
    $stmt->execute([$user['id'], $appointmentId, $user['id'], $user['id']]);
    $apptDetails = $stmt->fetch();
}

// List upcoming video appointments
$videoAppts = $db->prepare("SELECT a.*, vs.room_name,
    CASE WHEN a.patient_id = ? THEN ud.full_name ELSE up.full_name END as other_name,
    CASE WHEN a.patient_id = ? THEN ud.profile_photo ELSE up.profile_photo END as other_photo,
    CASE WHEN a.patient_id = ? THEN ud.role ELSE up.role END as other_role
    FROM appointments a
    JOIN video_sessions vs ON vs.appointment_id = a.id
    LEFT JOIN users ud ON ud.id = a.doctor_id
    LEFT JOIN users up ON up.id = a.patient_id
    WHERE (a.patient_id = ? OR a.doctor_id = ?) 
    AND a.type = 'video' 
    AND a.status = 'confirmed'
    AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 10");
$videoAppts->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
$videoApptList = $videoAppts->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $roomName ? 'Video Call' : 'Video Consultations' ?> - TeleCure</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/video.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <?php if ($roomName): ?>
    <script src='https://meet.jit.si/external_api.js'></script>
    <?php endif; ?>
</head>
<body class="dashboard-page <?= $roomName ? 'video-active' : '' ?>">

<?php if (!$roomName): ?>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
    <?php include 'includes/topbar.php'; ?>
    <div class="dashboard-body">
        <div class="page-header">
            <div>
                <h1>Video Consultations</h1>
                <p>High-quality HD video calls with your <?= $role === 'patient' ? 'doctors' : 'patients' ?></p>
            </div>
        </div>

        <div class="video-landing">
            <div class="video-hero-card">
                <div class="video-hero-icon">
                    <i class="fas fa-video"></i>
                </div>
                <h2>Ready to Connect?</h2>
                <p>Join a scheduled video consultation or start an instant call</p>
                <div class="video-quick-start">
                    <div class="form-group">
                        <label>Enter Room Code:</label>
                        <div class="room-input-wrap">
                            <input type="text" id="roomInput" placeholder="e.g. telecure-abc123" class="room-input">
                            <button onclick="joinRoom()" class="btn-primary">
                                <i class="fas fa-sign-in-alt"></i> Join
                            </button>
                        </div>
                    </div>
                    <div class="or-divider"><span>or</span></div>
                    <button onclick="startInstantCall()" class="btn-outline-blue">
                        <i class="fas fa-plus"></i> Start Instant Call
                    </button>
                </div>
            </div>

            <div class="video-appts-list">
                <h3>Upcoming Video Consultations</h3>
                <?php if (empty($videoApptList)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-video"></i>
                    <p>No upcoming video appointments</p>
                    <?php if ($role === 'patient'): ?>
                    <a href="appointments.php?action=book" class="btn-primary">Book Video Consult</a>
                    <?php endif; ?>
                </div>
                <?php else: foreach ($videoApptList as $va): ?>
                <div class="video-appt-card">
                    <div class="va-info">
                        <img src="<?= avatarUrl(['full_name' => $va['other_name'], 'profile_photo' => $va['other_photo'] ?? null, 'role' => $va['other_role'] ?? 'doctor'], 45) ?>" alt="">
                        <div>
                            <strong><?= htmlspecialchars($va['other_name']) ?></strong>
                            <span><?= date('D M j', strtotime($va['appointment_date'])) ?> at <?= date('g:i A', strtotime($va['appointment_time'])) ?></span>
                        </div>
                    </div>
                    <div class="va-actions">
                        <span class="room-code"><?= htmlspecialchars($va['room_name']) ?></span>
                        <a href="video.php?room=<?= urlencode($va['room_name']) ?>" class="btn-join-video">
                            <i class="fas fa-video"></i> Join Call
                        </a>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Video Features -->
        <div class="video-features-grid">
            <div class="vf-card">
                <i class="fas fa-hd-video" style="color: #1565C0"></i>
                <h4>HD Quality</h4>
                <p>Crystal clear 1080p video for professional consultations</p>
            </div>
            <div class="vf-card">
                <i class="fas fa-shield-alt" style="color: #0288D1"></i>
                <h4>End-to-End Encrypted</h4>
                <p>All video calls are fully encrypted and private</p>
            </div>
            <div class="vf-card">
                <i class="fas fa-share-alt" style="color: #0097A7"></i>
                <h4>Screen Sharing</h4>
                <p>Share medical reports and documents during calls</p>
            </div>
            <div class="vf-card">
                <i class="fas fa-record-vinyl" style="color: #1565C0"></i>
                <h4>No Downloads Required</h4>
                <p>Works directly in your browser — no apps needed</p>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Active Video Call -->
<div class="video-call-page">
    <div class="video-call-header">
        <div class="call-logo">
            <svg viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg" width="32">
                <circle cx="30" cy="30" r="28" fill="rgba(255,255,255,0.2)" />
                <rect x="25" y="15" width="10" height="30" rx="3" fill="white"/>
                <rect x="15" y="25" width="30" height="10" rx="3" fill="white"/>
            </svg>
            <span>TeleCure Video</span>
        </div>
        <?php if ($apptDetails): ?>
        <div class="call-participant">
            <img src="<?= avatarUrl(['full_name' => $apptDetails['other_name'], 'profile_photo' => $apptDetails['other_photo'] ?? null, 'role' => $apptDetails['other_role'] ?? 'doctor'], 35) ?>" alt="">
            <span><?= htmlspecialchars($apptDetails['other_name']) ?></span>
        </div>
        <?php endif; ?>
        <div class="call-info">
            <span id="callTimer">00:00</span>
            <span class="call-status"><i class="fas fa-circle"></i> Live</span>
        </div>
    </div>

    <div id="jitsiContainer" class="jitsi-container"></div>

    <div class="video-sidebar">
        <div class="video-notes">
            <h4><i class="fas fa-clipboard"></i> Consultation Notes</h4>
            <textarea id="consultNotes" placeholder="Take notes during the call..."></textarea>
            <button onclick="saveNotes()" class="btn-save-notes">Save Notes</button>
        </div>
        <div class="video-chat-mini">
            <h4><i class="fas fa-comment"></i> Quick Chat</h4>
            <div class="mini-chat-area" id="miniChat"></div>
            <div class="mini-chat-input">
                <input type="text" id="miniMsgInput" placeholder="Type..." onkeydown="if(event.key==='Enter')sendMiniMsg()">
                <button onclick="sendMiniMsg()"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
<?php if ($roomName): ?>
const domain = 'meet.jit.si';
const options = {
    roomName: '<?= addslashes($roomName) ?>',
    width: '100%',
    height: '100%',
    parentNode: document.querySelector('#jitsiContainer'),
    userInfo: {
        displayName: '<?= addslashes($user['full_name']) ?>',
        email: '<?= addslashes($user['email']) ?>'
    },
    configOverwrite: {
        startWithAudioMuted: false,
        startWithVideoMuted: false,
        enableWelcomePage: false,
        disableDeepLinking: true,
    },
    interfaceConfigOverwrite: {
        SHOW_JITSI_WATERMARK: false,
        SHOW_BRAND_WATERMARK: false,
        BRAND_WATERMARK_LINK: '',
        DEFAULT_BACKGROUND: '#1565C0',
        TOOLBAR_BUTTONS: ['microphone','camera','closedcaptions','desktop','fullscreen','fodeviceselection','hangup','profile','chat','recording','shortcuts','tileview','select-background','stats','settings'],
    }
};

const api = new JitsiMeetExternalAPI(domain, options);

// Call timer
let seconds = 0;
const timer = setInterval(() => {
    seconds++;
    const m = Math.floor(seconds/60).toString().padStart(2,'0');
    const s = (seconds%60).toString().padStart(2,'0');
    document.getElementById('callTimer').textContent = `${m}:${s}`;
}, 1000);

api.addEventListener('videoConferenceLeft', () => {
    clearInterval(timer);
    window.location.href = 'dashboard.php';
});

function saveNotes() {
    const notes = document.getElementById('consultNotes').value;
    localStorage.setItem('consult_notes_<?= $roomName ?>', notes);
    alert('Notes saved!');
}
document.getElementById('consultNotes').value = localStorage.getItem('consult_notes_<?= $roomName ?>') || '';
<?php else: ?>
function joinRoom() {
    const room = document.getElementById('roomInput').value.trim();
    if (room) window.location.href = 'video.php?room=' + encodeURIComponent(room);
}
function startInstantCall() {
    const room = 'telecure-instant-' + Math.random().toString(36).substr(2,8);
    window.location.href = 'video.php?room=' + room;
}
<?php endif; ?>
</script>

</body>
</html>
