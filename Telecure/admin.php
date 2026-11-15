<?php
require_once 'includes/config.php';
requireLogin();

$user = currentUser();
$db   = getDB();

// Admin guard. This used to let ANY doctor in - including a brand-new, unverified
// self-registered one, who could then verify themselves and disable other accounts.
// Access now needs users.is_admin = 1 (see migrations/001_fixes.sql).
if (!isAdmin($user)) {
    http_response_code(403);
    header('Location: dashboard.php');
    exit;
}

// ── Stats ────────────────────────────────────────────────────────────
$totalPatients = $db->query("SELECT COUNT(*) FROM users WHERE role='patient'")->fetchColumn();
$totalDoctors  = $db->query("SELECT COUNT(*) FROM users WHERE role='doctor'")->fetchColumn();
$totalAppts    = $db->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$totalRx       = $db->query("SELECT COUNT(*) FROM prescriptions")->fetchColumn();
$pendingAppts  = $db->query("SELECT COUNT(*) FROM appointments WHERE status='pending'")->fetchColumn();

// ── Recent registrations ─────────────────────────────────────────────
$recentUsers = $db->query(
    "SELECT u.id, u.full_name, u.email, u.role, u.created_at, u.is_active, u.profile_photo
     FROM users u
     LEFT JOIN doctor_profiles dp ON dp.user_id = u.id
     ORDER BY (u.role = 'doctor' AND dp.is_verified = 0) DESC, u.created_at DESC
     LIMIT 50"
)->fetchAll();

// ── Recent appointments ──────────────────────────────────────────────
$recentAppts = $db->query(
    "SELECT a.*, up.full_name AS patient_name, ud.full_name AS doctor_name
     FROM appointments a
     JOIN users up ON a.patient_id = up.id
     JOIN users ud ON a.doctor_id  = ud.id
     ORDER BY a.created_at DESC LIMIT 10"
)->fetchAll();

// ── Toggle user active ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user'])) {
    requireCsrf();
    $tid    = (int)($_POST['user_id'] ?? 0);
    $active = empty($_POST['is_active']) ? 0 : 1;
    if ($tid === (int)$user['id']) {            // never lock yourself out
        header('Location: admin.php?msg=self');
        exit;
    }
    $db->prepare("UPDATE users SET is_active=? WHERE id=? AND is_admin=0")->execute([$active, $tid]);
    header('Location: admin.php?msg=user_updated');
    exit;
}

// ── Verify doctor ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_doctor'])) {
    requireCsrf();
    $did = (int)($_POST['doctor_id'] ?? 0);
    $db->prepare("UPDATE doctor_profiles SET is_verified=1 WHERE user_id=?")->execute([$did]);
    addNotification($did,'Account Verified','Congratulations! Your doctor account has been verified on TeleCure.','general');
    header('Location: admin.php?msg=verified');
    exit;
}

$msgLabels = array(
    'user_updated' => 'User status updated.',
    'verified'     => 'Doctor account verified!',
    'self'         => 'You cannot deactivate your own account.',
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - TeleCure</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    .admin-stats { display:grid; grid-template-columns:repeat(5,1fr); gap:16px; margin-bottom:28px; }
    .admin-stat-card {
        background:white; border-radius:var(--radius-md); border:1px solid var(--border);
        padding:20px 18px; text-align:center; box-shadow:var(--shadow-sm);
        transition:var(--transition);
    }
    .admin-stat-card:hover { transform:translateY(-3px); box-shadow:var(--shadow-md); }
    .admin-stat-card i { font-size:1.6rem; margin-bottom:10px; display:block; }
    .admin-stat-card strong { display:block; font-size:1.6rem; font-weight:800; color:var(--text-dark); }
    .admin-stat-card span { font-size:0.78rem; color:var(--text-light); }

    .admin-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
    .admin-card { background:white; border-radius:var(--radius-md); border:1px solid var(--border); box-shadow:var(--shadow-sm); overflow:hidden; }
    .admin-card-header { padding:18px 22px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
    .admin-card-header h3 { font-size:0.95rem; display:flex; align-items:center; gap:8px; }
    .admin-card-header h3 i { color:var(--primary); }

    .admin-table { width:100%; border-collapse:collapse; font-size:0.83rem; }
    .admin-table th { background:var(--bg); color:var(--text-light); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.8px; padding:10px 16px; text-align:left; }
    .admin-table td { padding:12px 16px; border-bottom:1px solid var(--border); vertical-align:middle; }
    .admin-table tr:last-child td { border-bottom:none; }
    .admin-table tr:hover td { background:var(--bg); }

    .user-row-avatar { display:flex; align-items:center; gap:10px; }
    .user-row-avatar img { width:32px; height:32px; border-radius:50%; }
    .role-pill {
        display:inline-block; padding:2px 10px; border-radius:50px; font-size:0.7rem; font-weight:700; text-transform:uppercase;
    }
    .role-pill.doctor { background:rgba(21,101,192,0.1); color:var(--primary); }
    .role-pill.patient { background:rgba(0,151,167,0.1); color:var(--teal); }

    .toggle-form { display:inline; }
    .btn-activate { background:#F0FDF4; color:#15803D; border:1px solid #86EFAC; padding:4px 10px; border-radius:50px; font-size:0.72rem; font-weight:600; cursor:pointer; font-family:inherit; transition:var(--transition); }
    .btn-activate:hover { background:#10B981; color:white; border-color:#10B981; }
    .btn-deactivate { background:#FEF2F2; color:#DC2626; border:1px solid #FCA5A5; padding:4px 10px; border-radius:50px; font-size:0.72rem; font-weight:600; cursor:pointer; font-family:inherit; transition:var(--transition); }
    .btn-deactivate:hover { background:#EF4444; color:white; border-color:#EF4444; }
    .btn-verify { background:rgba(21,101,192,0.1); color:var(--primary); border:1px solid rgba(21,101,192,0.2); padding:4px 10px; border-radius:50px; font-size:0.72px; font-weight:600; cursor:pointer; font-family:inherit; font-size:0.72rem; transition:var(--transition); }
    .btn-verify:hover { background:var(--primary); color:white; }

    .pending-badge { background:#FEF3C7; color:#92400E; padding:2px 8px; border-radius:50px; font-size:0.7rem; font-weight:700; }

    @media(max-width:1100px){ .admin-stats{grid-template-columns:repeat(3,1fr);} .admin-grid{grid-template-columns:1fr;} }
    </style>
</head>
<body class="dashboard-page">
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
    <?php include 'includes/topbar.php'; ?>
    <div class="dashboard-body">

        <div class="page-header">
            <div>
                <h1><i class="fas fa-shield-alt" style="color:var(--primary);font-size:1.4rem;"></i> Admin Panel</h1>
                <p>Manage users, appointments, and platform activity</p>
            </div>
            <a href="admin_email.php" class="btn-primary" style="text-decoration:none"><i class="fas fa-envelope-open-text"></i> Email Settings</a>
        </div>

        <?php if (isset($_GET['msg']) && isset($msgLabels[$_GET['msg']])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $msgLabels[$_GET['msg']] ?></div>
        <?php endif; ?>

        <!-- Platform Stats -->
        <div class="admin-stats">
            <div class="admin-stat-card">
                <i class="fas fa-users" style="color:var(--primary)"></i>
                <strong><?= $totalPatients ?></strong>
                <span>Patients</span>
            </div>
            <div class="admin-stat-card">
                <i class="fas fa-user-md" style="color:var(--primary-mid)"></i>
                <strong><?= $totalDoctors ?></strong>
                <span>Doctors</span>
            </div>
            <div class="admin-stat-card">
                <i class="fas fa-calendar-check" style="color:var(--teal)"></i>
                <strong><?= $totalAppts ?></strong>
                <span>Appointments</span>
            </div>
            <div class="admin-stat-card">
                <i class="fas fa-file-prescription" style="color:#10B981"></i>
                <strong><?= $totalRx ?></strong>
                <span>Prescriptions</span>
            </div>
            <div class="admin-stat-card">
                <i class="fas fa-clock" style="color:#F59E0B"></i>
                <strong><?= $pendingAppts ?></strong>
                <span>Pending Appts</span>
            </div>
        </div>

        <div class="admin-grid">
            <!-- Users Table -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3><i class="fas fa-users"></i> Recent Users</h3>
                    <span style="font-size:0.8rem;color:var(--text-light)"><?= count($recentUsers) ?> shown</span>
                </div>
                <div style="overflow-x:auto;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $u): ?>
                            <tr>
                                <td>
                                    <div class="user-row-avatar">
                                        <img src="<?= avatarUrl($u, 32) ?>" alt="">
                                        <div>
                                            <strong style="font-size:0.85rem"><?= htmlspecialchars($u['full_name']) ?></strong>
                                            <div style="font-size:0.75rem;color:var(--text-light)"><?= htmlspecialchars($u['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="role-pill <?= $u['role'] ?>"><?= $u['role'] ?></span></td>
                                <td style="color:var(--text-light)"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <?php if ($u['id'] !== $user['id']): ?>
                                    <form class="toggle-form" method="POST" action="admin.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="is_active" value="<?= $u['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" name="toggle_user" class="<?= $u['is_active'] ? 'btn-deactivate' : 'btn-activate' ?>">
                                            <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                    <?php if ($u['role'] === 'doctor'): ?>
                                    <?php
                                    $isVer = $db->prepare("SELECT is_verified FROM doctor_profiles WHERE user_id=?");
                                    $isVer->execute([$u['id']]);
                                    $vRow = $isVer->fetch();
                                    if ($vRow && !$vRow['is_verified']): ?>
                                    <form class="toggle-form" method="POST" action="admin.php" style="margin-left:6px;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="doctor_id" value="<?= $u['id'] ?>">
                                        <button type="submit" name="verify_doctor" class="btn-verify">Verify</button>
                                    </form>
                                    <?php elseif ($vRow && $vRow['is_verified']): ?>
                                    <span style="color:#10B981;font-size:0.72rem;font-weight:600;margin-left:6px;"><i class="fas fa-check-circle"></i> Verified</span>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span style="color:var(--text-light);font-size:0.75rem;">You</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Appointments Table -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3><i class="fas fa-calendar-alt"></i> Recent Appointments</h3>
                    <?php if ($pendingAppts > 0): ?>
                    <span class="pending-badge"><?= $pendingAppts ?> pending</span>
                    <?php endif; ?>
                </div>
                <div style="overflow-x:auto;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentAppts as $a):
                                $sc = array('pending'=>'status-yellow','confirmed'=>'status-green','completed'=>'status-green','cancelled'=>'status-red');
                                $cls = isset($sc[$a['status']]) ? $sc[$a['status']] : 'status-gray';
                            ?>
                            <tr>
                                <td style="font-size:0.83rem"><?= htmlspecialchars($a['patient_name']) ?></td>
                                <td style="font-size:0.83rem"><?= htmlspecialchars($a['doctor_name']) ?></td>
                                <td style="color:var(--text-light);font-size:0.78rem"><?= date('M j', strtotime($a['appointment_date'])) ?> <?= date('g:i A', strtotime($a['appointment_time'])) ?></td>
                                <td><span class="status-badge <?= $cls ?>"><?= ucfirst($a['status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
<script src="js/dashboard.js"></script>
</body>
</html>
