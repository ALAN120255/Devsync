<?php
require_once 'includes/config.php';
requireLogin();

$user = currentUser();
$db = getDB();
$role = $user['role'];
$msg = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    requireCsrf();
    $full_name = sanitize($_POST['full_name'] ?? '');
    $phone     = sanitize($_POST['phone'] ?? '');
    $gender    = sanitize($_POST['gender'] ?? '');
    $dob       = sanitize($_POST['date_of_birth'] ?? '');
    $bio       = sanitize($_POST['bio'] ?? '');
    $errors    = [];

    if ($full_name === '') $errors[] = 'Full name is required.';
    if (!in_array($gender, ['', 'male', 'female', 'other'], true)) $errors[] = 'Invalid gender.';
    if ($dob !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $dob);
        if (!$d || $d->format('Y-m-d') !== $dob || $d > new DateTime('today')) $errors[] = 'Please enter a valid date of birth.';
    }

    // Password change: needs the CURRENT password (so a hijacked open session can't lock you out).
    $newPass  = $_POST['new_password'] ?? '';
    $confPass = $_POST['confirm_new_password'] ?? '';
    $curPass  = $_POST['current_password'] ?? '';
    // E-mail change (used for password-reset e-mails, so it must be a real, unique address).
    $newEmail     = strtolower(trim($_POST['email'] ?? $user['email']));
    $emailChanged = ($newEmail !== strtolower($user['email']));
    if ($emailChanged) {
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid e-mail address.';
        } else {
            $dup = $db->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
            $dup->execute([$newEmail, $user['id']]);
            if ($dup->fetch()) $errors[] = 'That e-mail address is already used by another account.';
        }
    }

    // Changing the password or the e-mail needs the CURRENT password (so a hijacked open session can't lock you out).
    if ($newPass !== '' || $emailChanged) {
        $pw = $db->prepare("SELECT password FROM users WHERE id=?");
        $pw->execute([$user['id']]);
        if (!password_verify($curPass, (string)$pw->fetchColumn())) $errors[] = 'Your current password is incorrect.';
        elseif ($newPass !== '' && ($pwErr = passwordPolicyError($newPass))) $errors[] = 'New password: ' . lcfirst($pwErr);
        elseif ($newPass !== '' && $newPass !== $confPass) $errors[] = 'New passwords do not match.';
    }

    // Optional new profile photo
    $newPhoto = null;
    if (empty($errors)) {
        try {
            $newPhoto = handleAvatarUpload('profile_photo', 'u' . $user['id']);
        } catch (Exception $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!empty($errors)) {
        $msg = '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> ' . implode('<br>', array_map('htmlspecialchars', $errors)) . '</div>';
    } else {
        $db->beginTransaction();
        try {
            if ($newPhoto) {
                $db->prepare("UPDATE users SET profile_photo=? WHERE id=?")->execute([$newPhoto, $user['id']]);
            }
            $db->prepare("UPDATE users SET full_name=?, phone=?, gender=?, date_of_birth=?, bio=? WHERE id=?")
               ->execute([$full_name, $phone, $gender ?: null, $dob ?: null, $bio, $user['id']]);
            if ($emailChanged) {
                $db->prepare("UPDATE users SET email=? WHERE id=?")->execute([$newEmail, $user['id']]);
                $db->prepare("DELETE FROM password_resets WHERE user_id=?")->execute([$user['id']]); // old reset links die with the old address
            }

            if ($role === 'doctor') {
                $spec  = sanitize($_POST['specialization'] ?? '');
                $fee   = max(0, (float)($_POST['consultation_fee'] ?? 0));
                $years = max(0, min(60, (int)($_POST['years_experience'] ?? 0)));
                $db->prepare("UPDATE doctor_profiles SET specialization=?, consultation_fee=?, years_experience=? WHERE user_id=?")
                   ->execute([$spec, $fee, $years, $user['id']]);
            }
            if ($newPass !== '') {
                $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($newPass, PASSWORD_DEFAULT), $user['id']]);
            }
            $db->commit();

            // Remove the old locally-uploaded file (never delete seeded http(s) photos or the default).
            if ($newPhoto) {
                $oldPhoto = $user['profile_photo'] ?? null;
                if ($oldPhoto && $oldPhoto !== 'default_avatar.png' && !preg_match('#^https?://#i', $oldPhoto)) {
                    $oldPath = AVATAR_UPLOAD_DIR . basename($oldPhoto);
                    if (file_exists($oldPath)) @unlink($oldPath);
                }
            }
            $_SESSION['user_name'] = $full_name;
            $msg = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Profile updated successfully!' . ($newPass !== '' ? ' Password changed.' : '') . ($emailChanged ? ' E-mail changed - use ' . htmlspecialchars($newEmail) . ' to sign in from now on.' : '') . '</div>';
            $user = currentUser(); // Reload
        } catch (Exception $ex) {
            $db->rollBack();
            $msg = '<div class="alert alert-error">Could not save your profile. Please try again.</div>';
        }
    }
}

$avatarUrl = avatarUrl($user, 120);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - TeleCure</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .profile-layout { display: grid; grid-template-columns: 300px 1fr; gap: 28px; }
        .profile-card {
            background: white; border-radius: var(--radius-lg); border: 1px solid var(--border);
            padding: 32px 24px; text-align: center; height: fit-content;
            box-shadow: var(--shadow-sm);
        }
        .profile-avatar-wrap { position: relative; width: 110px; height: 110px; margin: 0 auto 16px; }
        .profile-avatar { width: 110px; height: 110px; border-radius: 50%; object-fit: cover; border: 4px solid var(--primary-pale); }
        .avatar-edit-btn {
            position: absolute; bottom: 4px; right: 4px;
            width: 30px; height: 30px; background: var(--primary); color: white;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 0.7rem; cursor: pointer; border: 2px solid white;
        }
        .profile-name { font-size: 1.2rem; font-weight: 700; margin-bottom: 4px; }
        .profile-role-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--primary-pale); color: var(--primary);
            padding: 4px 14px; border-radius: 50px; font-size: 0.82rem; font-weight: 600;
            margin-bottom: 16px;
        }
        .profile-stats { display: flex; gap: 16px; justify-content: center; margin: 20px 0; }
        .prof-stat { text-align: center; }
        .prof-stat strong { display: block; font-size: 1.4rem; font-weight: 800; color: var(--primary); }
        .prof-stat small { font-size: 0.75rem; color: var(--text-light); }
        .profile-info-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; font-size: 0.87rem; color: var(--text-mid); }
        .profile-info-row i { color: var(--primary-light); width: 18px; }
        .profile-form-card {
            background: white; border-radius: var(--radius-lg); border: 1px solid var(--border);
            box-shadow: var(--shadow-sm); overflow: hidden;
        }
        .form-section { padding: 28px; border-bottom: 1px solid var(--border); }
        .form-section:last-child { border-bottom: none; }
        .form-section-title { font-size: 1rem; font-weight: 700; color: var(--primary); margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        @media (max-width: 900px) { .profile-layout { grid-template-columns: 1fr; } }
    </style>
</head>
<body class="dashboard-page">

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div class="dashboard-body">
        <div class="page-header">
            <div>
                <h1>My Profile</h1>
                <p>Manage your personal information and account settings</p>
            </div>
        </div>

        <?= $msg ?>

        <div class="profile-layout">
            <!-- Left: Avatar & Info -->
            <div class="profile-card">
                <div class="profile-avatar-wrap">
                    <img src="<?= $avatarUrl ?>" alt="Avatar" class="profile-avatar" id="profileAvatarImg">
                    <label for="profilePhotoInput" class="avatar-edit-btn" title="Change photo"><i class="fas fa-camera"></i></label>
                    <input type="file" id="profilePhotoInput" name="profile_photo" form="profileForm"
                           accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="previewProfilePhoto(this)">
                </div>
                <div class="profile-name"><?= htmlspecialchars($user['full_name']) ?></div>
                <div class="profile-role-badge">
                    <i class="fas fa-<?= $role === 'doctor' ? 'stethoscope' : 'user' ?>"></i>
                    <?= ucfirst($role) ?>
                    <?php if ($role === 'doctor' && $user['is_verified']): ?>
                    <i class="fas fa-check-circle" style="color: #10B981"></i>
                    <?php endif; ?>
                </div>
                <?php if ($role === 'doctor'): ?>
                <div class="profile-stats">
                    <div class="prof-stat">
                        <strong><?= number_format($user['rating'] ?? 0, 1) ?></strong>
                        <small>Rating</small>
                    </div>
                    <div class="prof-stat">
                        <?php $cnt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id=?"); $cnt->execute([$user['id']]); ?>
                        <strong><?= $cnt->fetchColumn() ?></strong>
                        <small>Patients</small>
                    </div>
                    <div class="prof-stat">
                        <?php $rxCnt = $db->prepare("SELECT COUNT(*) FROM prescriptions WHERE doctor_id=?"); $rxCnt->execute([$user['id']]); ?>
                        <strong><?= $rxCnt->fetchColumn() ?></strong>
                        <small>Prescriptions</small>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($user['phone']): ?>
                <div class="profile-info-row"><i class="fas fa-phone"></i> <?= htmlspecialchars($user['phone']) ?></div>
                <?php endif; ?>
                <div class="profile-info-row"><i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email']) ?></div>
                <?php if ($user['gender']): ?>
                <div class="profile-info-row"><i class="fas fa-venus-mars"></i> <?= ucfirst($user['gender']) ?></div>
                <?php endif; ?>
                <?php if ($user['date_of_birth']): ?>
                <div class="profile-info-row"><i class="fas fa-birthday-cake"></i> <?= date('F j, Y', strtotime($user['date_of_birth'])) ?></div>
                <?php endif; ?>
                <?php if ($role === 'doctor'): ?>
                <div class="profile-info-row"><i class="fas fa-briefcase-medical"></i> <?= htmlspecialchars($user['specialization'] ?? '') ?></div>
                <div class="profile-info-row"><i class="fas fa-coins"></i> R<?= number_format($user['consultation_fee'] ?? 0, 0) ?>/consult</div>
                <?php endif; ?>
                <?php if ($user['bio']): ?>
                <p style="font-size: 0.85rem; color: var(--text-light); margin-top: 12px; line-height: 1.6; text-align: left;"><?= nl2br(htmlspecialchars($user['bio'])) ?></p>
                <?php endif; ?>
                <div style="margin-top: 16px; font-size: 0.75rem; color: var(--text-light);">
                    Member since <?= date('F Y', strtotime($user['created_at'])) ?>
                </div>
            </div>

            <!-- Right: Edit Form -->
            <div class="profile-form-card">
                <form method="POST" action="profile.php" id="profileForm" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <!-- Personal Info -->
                    <div class="form-section">
                        <div class="form-section-title"><i class="fas fa-user"></i> Personal Information</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                <small style="color:var(--text-light)">This is your sign-in name and where password-reset e-mails are sent. Changing it needs your current password (see "Change Password" below).</small>
                                <small style="color: var(--text-light); font-size: 0.78rem;"><i class="fas fa-info-circle"></i> Email cannot be changed</small>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+27 00 000 0000">
                            </div>
                            <div class="form-group">
                                <label>Gender</label>
                                <select name="gender">
                                    <option value="">Select gender</option>
                                    <option value="male" <?= ($user['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                                    <option value="female" <?= ($user['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                                    <option value="other" <?= ($user['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth" value="<?= htmlspecialchars($user['date_of_birth'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Bio</label>
                            <textarea name="bio" rows="3" placeholder="Tell us about yourself..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <?php if ($role === 'doctor'): ?>
                    <!-- Doctor Info -->
                    <div class="form-section">
                        <div class="form-section-title"><i class="fas fa-stethoscope"></i> Professional Information</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Specialization</label>
                                <select name="specialization">
                                    <?php $specs = ['General Practice','Cardiology','Pediatrics','Dermatology','Orthopedics','Neurology','Psychiatry','Gynecology','Ophthalmology','ENT','Oncology']; ?>
                                    <?php foreach ($specs as $s): ?>
                                    <option <?= ($user['specialization'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Consultation Fee (ZAR)</label>
                                <input type="number" name="consultation_fee" value="<?= $user['consultation_fee'] ?? 0 ?>" min="0" step="50">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Years of Experience</label>
                            <input type="number" name="years_experience" value="<?= (int)($user['years_experience'] ?? 0) ?>" min="0" max="60">
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Change Password -->
                    <div class="form-section">
                        <div class="form-section-title"><i class="fas fa-lock"></i> Change Password</div>
                        <div class="form-group">
                            <label>Current Password <small style="color:var(--text-light)">(needed to change your password or e-mail)</small></label>
                            <input type="password" name="current_password" autocomplete="current-password" placeholder="Your current password">
                        </div>
                        <ul class="pw-rules" id="profileRules" aria-live="polite"></ul>
                        <div class="form-row">
                            <div class="form-group">
                                <label>New Password</label>
                                <div class="password-wrapper">
                                    <input type="password" name="new_password" id="newPass" placeholder="Leave blank to keep current" autocomplete="new-password" data-password-rules="profileRules">
                                    <button type="button" class="toggle-pass" onclick="togglePass('newPass')"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <div class="password-wrapper">
                                    <input type="password" name="confirm_new_password" id="confPass" placeholder="Repeat new password">
                                    <button type="button" class="toggle-pass" onclick="togglePass('confPass')"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="form-section" style="border: none;">
                        <button type="submit" name="update_profile" class="btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="js/dashboard.js"></script>
<script src="js/password-rules.js"></script>
<script>
function togglePass(id) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
}

function previewProfilePhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { document.getElementById('profileAvatarImg').src = e.target.result; };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>
