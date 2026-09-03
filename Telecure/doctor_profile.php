<?php
require_once 'includes/config.php';
requireLogin();

$db     = getDB();
$user   = currentUser();
$docId  = (int)($_GET['id'] ?? 0);

if (!$docId) { header('Location: find_doctors.php'); exit; }

// Load doctor
$stmt = $db->prepare(
    "SELECT u.*, dp.specialization, dp.consultation_fee, dp.rating,
            dp.total_reviews, dp.years_experience, dp.license_number, dp.is_verified
     FROM   users u
     JOIN   doctor_profiles dp ON dp.user_id = u.id
     WHERE  u.id=? AND u.role='doctor'"
);
$stmt->execute([$docId]);
$doc = $stmt->fetch();
if (!$doc) { header('Location: find_doctors.php'); exit; }

// Reviews
$revStmt = $db->prepare(
    "SELECT r.*, u.full_name AS patient_name
     FROM   reviews r JOIN users u ON r.patient_id = u.id
     WHERE  r.doctor_id=? ORDER BY r.created_at DESC LIMIT 10"
);
$revStmt->execute([$docId]);
$reviews = $revStmt->fetchAll();

// Can this patient submit a review?
$canReview = false;
if ($user['role'] === 'patient') {
    $chk = $db->prepare("SELECT id FROM appointments WHERE patient_id=? AND doctor_id=? AND status='completed' LIMIT 1");
    $chk->execute([$user['id'], $docId]);
    $canReview = (bool)$chk->fetch();
}

// Handle review submit
$reviewMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review']) && $canReview) {
    $rating  = min(5, max(1, (int)$_POST['rating']));
    $comment = sanitize($_POST['comment'] ?? '');
    // Upsert: one review per patient per doctor
    $exist = $db->prepare("SELECT id FROM reviews WHERE patient_id=? AND doctor_id=?");
    $exist->execute([$user['id'], $docId]);
    if ($exist->fetch()) {
        $db->prepare("UPDATE reviews SET rating=?, comment=? WHERE patient_id=? AND doctor_id=?")
           ->execute([$rating, $comment, $user['id'], $docId]);
    } else {
        $db->prepare("INSERT INTO reviews (patient_id,doctor_id,rating,comment) VALUES (?,?,?,?)")
           ->execute([$user['id'], $docId, $rating, $comment]);
    }
    // Recalculate average
    $avg = $db->prepare("SELECT AVG(rating), COUNT(*) FROM reviews WHERE doctor_id=?");
    $avg->execute([$docId]);
    list($avgRating, $totalRev) = $avg->fetch(PDO::FETCH_NUM);
    $db->prepare("UPDATE doctor_profiles SET rating=?, total_reviews=? WHERE user_id=?")
       ->execute([round($avgRating,2), $totalRev, $docId]);

    addNotification($docId, 'New Review', $user['full_name'].' left you a '.str_repeat('★',$rating).' review.', 'general');
    $reviewMsg = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Review submitted!</div>';
    header("Location: doctor_profile.php?id=$docId&reviewed=1");
    exit;
}

$docAvatarUrl = avatarUrl($doc, 120);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($doc['full_name']) ?> - TeleCure</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    .doc-profile-layout { display:grid; grid-template-columns:320px 1fr; gap:28px; align-items:start; }

    .doc-profile-card {
        background:white; border-radius:var(--radius-lg); border:1px solid var(--border);
        box-shadow:var(--shadow-sm); overflow:hidden; position:sticky; top:88px;
    }
    .doc-profile-banner {
        background:linear-gradient(135deg,var(--primary),var(--primary-mid));
        height:90px; position:relative;
    }
    .doc-profile-avatar {
        width:100px; height:100px; border-radius:50%;
        border:4px solid white; position:absolute; bottom:-40px; left:50%; transform:translateX(-50%);
        box-shadow:var(--shadow-md);
    }
    .doc-profile-info { padding:52px 24px 24px; text-align:center; }
    .doc-profile-name { font-size:1.2rem; font-weight:800; margin-bottom:6px; }
    .doc-profile-spec {
        display:inline-block; background:var(--primary-pale); color:var(--primary);
        padding:4px 14px; border-radius:50px; font-size:0.82rem; font-weight:600; margin-bottom:14px;
    }
    .doc-profile-verified {
        display:inline-flex; align-items:center; gap:5px;
        background:#F0FDF4; color:#15803D; font-size:0.78rem; font-weight:600;
        padding:4px 10px; border-radius:50px; margin-bottom:16px;
    }
    .doc-stat-row { display:flex; justify-content:center; gap:20px; margin:16px 0; }
    .doc-stat { text-align:center; }
    .doc-stat strong { display:block; font-size:1.4rem; font-weight:800; color:var(--primary); }
    .doc-stat small { font-size:0.72rem; color:var(--text-light); }
    .doc-info-list { text-align:left; margin-top:16px; display:flex; flex-direction:column; gap:8px; }
    .doc-info-item { display:flex; align-items:center; gap:10px; font-size:0.85rem; color:var(--text-mid); }
    .doc-info-item i { color:var(--primary-light); width:18px; text-align:center; }
    .doc-actions { padding:0 24px 24px; display:flex; flex-direction:column; gap:10px; }

    .doc-main-card {
        background:white; border-radius:var(--radius-lg); border:1px solid var(--border);
        box-shadow:var(--shadow-sm); padding:28px; margin-bottom:20px;
    }
    .doc-main-card h3 { font-size:1rem; font-weight:700; color:var(--primary); margin-bottom:16px; display:flex; align-items:center; gap:8px; border-bottom:1px solid var(--border); padding-bottom:12px; }
    .doc-bio { font-size:0.92rem; color:var(--text-mid); line-height:1.8; }

    /* Slots */
    .slots-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(90px,1fr)); gap:8px; margin-top:12px; }
    .slot-btn {
        padding:9px 6px; border-radius:8px; border:1.5px solid var(--border);
        font-size:0.78rem; font-weight:600; text-align:center; background:white;
        cursor:pointer; transition:var(--transition); color:var(--text-mid);
    }
    .slot-btn:hover:not(.taken) { border-color:var(--primary); background:var(--primary-pale); color:var(--primary); }
    .slot-btn.selected { border-color:var(--primary); background:var(--primary); color:white; }
    .slot-btn.taken { background:var(--bg); color:var(--text-light); cursor:not-allowed; text-decoration:line-through; }

    /* Reviews */
    .reviews-list { display:flex; flex-direction:column; gap:14px; }
    .review-card { background:var(--bg); border-radius:var(--radius-md); padding:16px; border:1px solid var(--border); }
    .review-header { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
    .review-avatar { width:36px; height:36px; border-radius:50%; }
    .review-meta strong { display:block; font-size:0.88rem; }
    .review-meta small { color:var(--text-light); font-size:0.75rem; }
    .review-stars { display:flex; gap:2px; margin-left:auto; }
    .review-stars i { color:#F59E0B; font-size:0.8rem; }
    .review-text { font-size:0.85rem; color:var(--text-mid); line-height:1.6; }

    /* Rating input */
    .star-rating { display:flex; gap:6px; flex-direction:row-reverse; justify-content:flex-end; margin:10px 0; }
    .star-rating input { display:none; }
    .star-rating label { font-size:1.6rem; color:#D1D5DB; cursor:pointer; transition:color 0.15s; }
    .star-rating label:hover, .star-rating label:hover ~ label,
    .star-rating input:checked ~ label { color:#F59E0B; }

    .rating-bar-row { display:flex; align-items:center; gap:12px; margin-bottom:6px; }
    .rating-bar-label { font-size:0.78rem; color:var(--text-mid); width:20px; text-align:right; }
    .rating-bar-track { flex:1; height:8px; background:var(--border); border-radius:4px; overflow:hidden; }
    .rating-bar-fill { height:100%; background:linear-gradient(90deg,#F59E0B,#FBBF24); border-radius:4px; }
    .rating-bar-count { font-size:0.75rem; color:var(--text-light); width:20px; }

    @media(max-width:900px){ .doc-profile-layout{grid-template-columns:1fr;} .doc-profile-card{position:static;} }
    </style>
</head>
<body class="dashboard-page">
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
    <?php include 'includes/topbar.php'; ?>
    <div class="dashboard-body">

        <?php if (isset($_GET['reviewed'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> Your review has been submitted. Thank you!</div>
        <?php endif; ?>

        <!-- Breadcrumb -->
        <div style="margin-bottom:20px;font-size:0.85rem;color:var(--text-light);">
            <a href="find_doctors.php" style="color:var(--primary)"><i class="fas fa-arrow-left"></i> Back to Find Doctors</a>
        </div>

        <div class="doc-profile-layout">

            <!-- Left Sticky Card -->
            <div class="doc-profile-card">
                <div class="doc-profile-banner"></div>
                <div class="doc-profile-info">
                    <img src="<?= $docAvatarUrl ?>" alt="<?= htmlspecialchars($doc['full_name']) ?>" class="doc-profile-avatar">
                    <div class="doc-profile-name"><?= htmlspecialchars($doc['full_name']) ?></div>
                    <div class="doc-profile-spec"><?= htmlspecialchars($doc['specialization'] ?? 'General Practitioner') ?></div>
                    <?php if ($doc['is_verified']): ?>
                    <div class="doc-profile-verified"><i class="fas fa-check-circle"></i> Verified Doctor</div>
                    <?php endif; ?>

                    <div class="doc-stat-row">
                        <div class="doc-stat">
                            <strong><?= number_format($doc['rating'],1) ?></strong>
                            <small>Rating</small>
                        </div>
                        <div class="doc-stat">
                            <strong><?= (int)$doc['total_reviews'] ?></strong>
                            <small>Reviews</small>
                        </div>
                        <div class="doc-stat">
                            <strong><?= (int)$doc['years_experience'] ?>yr</strong>
                            <small>Experience</small>
                        </div>
                    </div>

                    <div class="doc-info-list">
                        <div class="doc-info-item"><i class="fas fa-coins"></i> R<?= number_format($doc['consultation_fee'],0) ?> / consultation</div>
                        <div class="doc-info-item"><i class="fas fa-id-card"></i> License: <?= htmlspecialchars($doc['license_number'] ?? 'N/A') ?></div>
                        <?php if ($doc['phone']): ?>
                        <div class="doc-info-item"><i class="fas fa-phone"></i> <?= htmlspecialchars($doc['phone']) ?></div>
                        <?php endif; ?>
                        <?php if ($doc['email']): ?>
                        <div class="doc-info-item"><i class="fas fa-envelope"></i> <?= htmlspecialchars($doc['email']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($user['role'] === 'patient'): ?>
                <div class="doc-actions">
                    <a href="appointments.php?action=book&doctor=<?= $doc['id'] ?>" class="btn-primary">
                        <i class="fas fa-calendar-plus"></i> Book Appointment
                    </a>
                    <a href="chat.php?user=<?= $doc['id'] ?>" class="btn-outline">
                        <i class="fas fa-comment"></i> Send Message
                    </a>
                    <a href="video.php?user=<?= $doc['id'] ?>" class="btn-outline-blue" style="display:flex;align-items:center;justify-content:center;gap:8px;padding:11px 27px;border-radius:50px;border:2px solid var(--primary-mid);color:var(--primary-mid);font-weight:600;transition:var(--transition);">
                        <i class="fas fa-video"></i> Instant Video Call
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Content -->
            <div>
                <!-- About -->
                <div class="doc-main-card">
                    <h3><i class="fas fa-user-md"></i> About</h3>
                    <p class="doc-bio"><?= $doc['bio'] ? nl2br(htmlspecialchars($doc['bio'])) : '<em style="color:var(--text-light)">No bio provided yet.</em>' ?></p>
                </div>

                <!-- Book Slot Picker -->
                <?php if ($user['role'] === 'patient'): ?>
                <div class="doc-main-card">
                    <h3><i class="fas fa-calendar-check"></i> Check Availability</h3>
                    <div class="form-group">
                        <label>Select a Date</label>
                        <input type="date" id="slotDate" min="<?= date('Y-m-d') ?>" style="max-width:220px;"
                               onchange="loadSlots(this.value)">
                    </div>
                    <div id="slotContainer" style="display:none;">
                        <p style="font-size:0.85rem;color:var(--text-light);margin-bottom:8px;">Available time slots:</p>
                        <div class="slots-grid" id="slotsGrid"></div>
                        <div style="margin-top:16px;" id="bookSlotArea" style="display:none;">
                            <a id="bookSlotBtn" href="#" class="btn-primary" style="display:none;">
                                <i class="fas fa-calendar-plus"></i> Book This Slot
                            </a>
                        </div>
                    </div>
                    <div id="slotsLoading" style="display:none;color:var(--text-light);font-size:0.88rem;margin-top:10px;">
                        <i class="fas fa-spinner fa-spin"></i> Loading slots...
                    </div>
                </div>
                <?php endif; ?>

                <!-- Rating Distribution -->
                <?php if (!empty($reviews)): ?>
                <div class="doc-main-card">
                    <h3><i class="fas fa-chart-bar"></i> Rating Breakdown</h3>
                    <?php
                    $ratingCounts = array(5=>0,4=>0,3=>0,2=>0,1=>0);
                    foreach ($reviews as $r) {
                        $ratingCounts[(int)$r['rating']] = ($ratingCounts[(int)$r['rating']] ?? 0) + 1;
                    }
                    $total = count($reviews);
                    for ($star = 5; $star >= 1; $star--):
                        $pct = $total > 0 ? round(($ratingCounts[$star] / $total) * 100) : 0;
                    ?>
                    <div class="rating-bar-row">
                        <div class="rating-bar-label"><?= $star ?>★</div>
                        <div class="rating-bar-track">
                            <div class="rating-bar-fill" style="width:<?= $pct ?>%"></div>
                        </div>
                        <div class="rating-bar-count"><?= $ratingCounts[$star] ?></div>
                    </div>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>

                <!-- Reviews -->
                <div class="doc-main-card">
                    <h3><i class="fas fa-star"></i> Patient Reviews (<?= count($reviews) ?>)</h3>

                    <?php if ($canReview): ?>
                    <!-- Write a Review -->
                    <div style="background:var(--primary-pale);border-radius:var(--radius-md);padding:20px;margin-bottom:20px;border:1px solid rgba(21,101,192,0.15);">
                        <h4 style="font-size:0.9rem;color:var(--primary);margin-bottom:12px;"><i class="fas fa-pen"></i> Write a Review</h4>
                        <form method="POST" action="doctor_profile.php?id=<?= $docId ?>">
                            <div class="form-group">
                                <label>Your Rating</label>
                                <div class="star-rating">
                                    <?php for ($i=5;$i>=1;$i--): ?>
                                    <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>">
                                    <label for="star<?= $i ?>"><i class="fas fa-star"></i></label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Your Experience</label>
                                <textarea name="comment" rows="3" placeholder="Share your experience with this doctor..."></textarea>
                            </div>
                            <button type="submit" name="submit_review" class="btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit Review
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>

                    <div class="reviews-list">
                        <?php if (empty($reviews)): ?>
                        <div class="empty-state" style="padding:32px;">
                            <i class="fas fa-star" style="font-size:2rem;opacity:0.2;display:block;margin-bottom:12px;"></i>
                            <p>No reviews yet. Be the first to leave a review!</p>
                        </div>
                        <?php else: foreach ($reviews as $rev): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($rev['patient_name']) ?>&background=E3F2FD&color=1565C0&size=36"
                                     alt="" class="review-avatar">
                                <div class="review-meta">
                                    <strong><?= htmlspecialchars($rev['patient_name']) ?></strong>
                                    <small><?= date('M j, Y', strtotime($rev['created_at'])) ?></small>
                                </div>
                                <div class="review-stars">
                                    <?php for ($i=1;$i<=5;$i++): ?>
                                    <i class="fas fa-star" style="color:<?= $i<=$rev['rating'] ? '#F59E0B' : '#D1D5DB' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php if ($rev['comment']): ?>
                            <p class="review-text"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

            </div><!-- end right -->
        </div>
    </div>
</div>

<script src="js/dashboard.js"></script>
<script>
var DOC_ID = <?= $docId ?>;
var selectedTime = null;

function loadSlots(date) {
    if (!date) return;
    document.getElementById('slotContainer').style.display = 'none';
    document.getElementById('slotsLoading').style.display = 'block';
    document.getElementById('bookSlotBtn').style.display = 'none';
    selectedTime = null;

    fetch('api/appointments.php?action=slots&doctor_id=' + DOC_ID + '&date=' + date)
        .then(function(r){ return r.json(); })
        .then(function(slots) {
            document.getElementById('slotsLoading').style.display = 'none';
            document.getElementById('slotContainer').style.display = 'block';
            var grid = document.getElementById('slotsGrid');
            grid.innerHTML = '';
            slots.forEach(function(s) {
                var btn = document.createElement('div');
                btn.className = 'slot-btn' + (s.available ? '' : ' taken');
                btn.textContent = s.label;
                btn.title = s.available ? 'Click to select' : 'Already booked';
                if (s.available) {
                    btn.onclick = function() {
                        document.querySelectorAll('.slot-btn').forEach(function(b){ b.classList.remove('selected'); });
                        btn.classList.add('selected');
                        selectedTime = s.time;
                        var bookBtn = document.getElementById('bookSlotBtn');
                        bookBtn.style.display = 'inline-flex';
                        bookBtn.href = 'appointments.php?action=book&doctor=' + DOC_ID + '&date=' + date + '&time=' + s.time;
                    };
                }
                grid.appendChild(btn);
            });
        })
        .catch(function(){ document.getElementById('slotsLoading').style.display = 'none'; });
}
</script>
</body>
</html>
