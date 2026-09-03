<?php
require_once 'includes/config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$success = '';
$role = isset($_GET['role']) ? $_GET['role'] : 'patient';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name    = sanitize($_POST['full_name'] ?? '');
    $email        = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password     = $_POST['password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';
    $role         = sanitize($_POST['role'] ?? 'patient');
    $phone        = sanitize($_POST['phone'] ?? '');
    $dob          = sanitize($_POST['date_of_birth'] ?? '');
    $gender       = sanitize($_POST['gender'] ?? '');
    $specialization = sanitize($_POST['specialization'] ?? '');
    $license_number = sanitize($_POST['license_number'] ?? '');
    $years_exp    = (int)($_POST['years_experience'] ?? 0);
    $fee          = (float)($_POST['consultation_fee'] ?? 0);
    $bio          = sanitize($_POST['bio'] ?? '');

    // Validation
    if (empty($full_name)) $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm_pass) $errors[] = 'Passwords do not match.';
    if (!in_array($role, ['patient','doctor'])) $errors[] = 'Invalid role selected.';
    if ($role === 'doctor' && empty($specialization)) $errors[] = 'Specialization is required for doctors.';
    if ($role === 'doctor' && empty($license_number)) $errors[] = 'License number is required for doctors.';

    // Profile photo is required for every new account.
    $photoFilename = null;
    if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'A profile photo is required to register.';
    } elseif (empty($errors)) {
        try {
            $photoFilename = handleAvatarUpload('profile_photo', $email ?: 'user');
            if ($photoFilename === null) {
                $errors[] = 'A profile photo is required to register.';
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (empty($errors)) {
        $db = getDB();
        // Check email unique
        $check = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'An account with this email already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("INSERT INTO users (full_name, email, password, role, phone, date_of_birth, gender, profile_photo, bio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$full_name, $email, $hashed, $role, $phone, $dob ?: null, $gender, $photoFilename, $bio]);
                $userId = $db->lastInsertId();

                if ($role === 'doctor') {
                    $dStmt = $db->prepare("INSERT INTO doctor_profiles (user_id, specialization, license_number, years_experience, consultation_fee) VALUES (?, ?, ?, ?, ?)");
                    $dStmt->execute([$userId, $specialization, $license_number, $years_exp, $fee]);
                }

                $db->commit();

                // Auto-login
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_role'] = $role;
                $_SESSION['user_name'] = $full_name;

                header('Location: dashboard.php?welcome=1');
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                // Clean up the uploaded file since the account wasn't created.
                if ($photoFilename && file_exists(AVATAR_UPLOAD_DIR . $photoFilename)) {
                    @unlink(AVATAR_UPLOAD_DIR . $photoFilename);
                }
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - TeleCure</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="auth-page">

<div class="auth-wrapper">
    <!-- Left Panel -->
    <div class="auth-left">
        <a href="index.php" class="auth-logo">
            <svg viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg" width="50">
                <circle cx="30" cy="30" r="28" fill="rgba(255,255,255,0.2)" />
                <rect x="25" y="15" width="10" height="30" rx="3" fill="white"/>
                <rect x="15" y="25" width="30" height="10" rx="3" fill="white"/>
            </svg>
            <span>TeleCure</span>
        </a>
        <div class="auth-left-content">
            <h2>Join the Future of Healthcare</h2>
            <p>Connect with certified doctors or patients on South Africa's leading telemedicine platform.</p>
            <ul class="auth-features-list">
                <li><i class="fas fa-check-circle"></i> Video consultations with HD quality</li>
                <li><i class="fas fa-check-circle"></i> Secure digital prescriptions</li>
                <li><i class="fas fa-check-circle"></i> Flexible appointment scheduling</li>
                <li><i class="fas fa-check-circle"></i> POPIA compliant & secure</li>
            </ul>
        </div>
        <div class="auth-left-wave">
            <svg viewBox="0 0 400 200" xmlns="http://www.w3.org/2000/svg">
                <path d="M0,100 C100,50 300,150 400,100 L400,200 L0,200 Z" fill="rgba(255,255,255,0.05)"/>
                <path d="M0,130 C100,80 300,180 400,130 L400,200 L0,200 Z" fill="rgba(255,255,255,0.05)"/>
            </svg>
        </div>
    </div>

    <!-- Right Panel -->
    <div class="auth-right">
        <div class="auth-form-container">
            <h1>Create Account</h1>
            <p class="auth-subtitle">Already have an account? <a href="login.php">Sign in</a></p>

            <!-- Role Toggle -->
            <div class="role-toggle">
                <button type="button" class="role-btn <?= $role === 'patient' ? 'active' : '' ?>" data-role="patient" onclick="switchRole('patient')">
                    <i class="fas fa-user"></i> Patient
                </button>
                <button type="button" class="role-btn <?= $role === 'doctor' ? 'active' : '' ?>" data-role="doctor" onclick="switchRole('doctor')">
                    <i class="fas fa-user-md"></i> Doctor
                </button>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <ul><?php foreach($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
            </div>
            <?php endif; ?>

            <form method="POST" action="register.php" id="registerForm" class="auth-form" enctype="multipart/form-data">
                <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($role) ?>">

                <div class="form-group photo-upload-group">
                    <label><i class="fas fa-camera"></i> Profile Photo <span style="color:#e53e3e;">*</span></label>
                    <div class="photo-upload-box" id="photoUploadBox" onclick="document.getElementById('profilePhotoInput').click()">
                        <img id="photoPreview" class="photo-upload-preview" style="display:none;" alt="Preview">
                        <div id="photoUploadPlaceholder" class="photo-upload-placeholder">
                            <i class="fas fa-user-circle"></i>
                            <span>Click to upload a photo</span>
                            <small>JPG, PNG or WEBP — max 3MB</small>
                        </div>
                    </div>
                    <input type="file" name="profile_photo" id="profilePhotoInput" accept="image/jpeg,image/png,image/webp" required style="display:none;" onchange="previewPhoto(this)">
                    <small style="color: var(--text-light); font-size: 0.78rem;">A clear photo helps patients and doctors recognize each other during consultations.</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" name="full_name" placeholder="Dr. John Smith" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" name="email" placeholder="your@email.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="password" placeholder="Min 8 characters" required>
                            <button type="button" class="toggle-pass" onclick="togglePass('password')"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Confirm Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="confirm_password" id="confirm_password" placeholder="Repeat password" required>
                            <button type="button" class="toggle-pass" onclick="togglePass('confirm_password')"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="tel" name="phone" placeholder="+27 00 000 0000" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-venus-mars"></i> Gender</label>
                        <select name="gender">
                            <option value="">Select gender</option>
                            <option value="male" <?= ($_POST['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= ($_POST['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                            <option value="other" <?= ($_POST['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-birthday-cake"></i> Date of Birth</label>
                    <input type="date" name="date_of_birth" value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>">
                </div>

                <!-- Doctor-specific fields -->
                <div id="doctorFields" style="display: <?= $role === 'doctor' ? 'block' : 'none' ?>">
                    <div class="doctor-fields-divider">
                        <span><i class="fas fa-stethoscope"></i> Doctor Information</span>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-briefcase-medical"></i> Specialization</label>
                            <select name="specialization">
                                <option value="">Select specialization</option>
                                <option>General Practice</option>
                                <option>Cardiology</option>
                                <option>Pediatrics</option>
                                <option>Dermatology</option>
                                <option>Orthopedics</option>
                                <option>Neurology</option>
                                <option>Psychiatry</option>
                                <option>Gynecology</option>
                                <option>Ophthalmology</option>
                                <option>ENT</option>
                                <option>Oncology</option>
                                <option>Radiology</option>
                                <option>Anesthesiology</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-id-card"></i> License Number</label>
                            <input type="text" name="license_number" placeholder="MP12345">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-clock"></i> Years of Experience</label>
                            <input type="number" name="years_experience" placeholder="5" min="0" max="60">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-coins"></i> Consultation Fee (ZAR)</label>
                            <input type="number" name="consultation_fee" placeholder="600" min="0" step="50">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Bio (optional)</label>
                    <textarea name="bio" placeholder="Tell us a little about yourself..." rows="3"><?= htmlspecialchars($_POST['bio'] ?? '') ?></textarea>
                </div>

                <div class="form-group checkbox-group">
                    <input type="checkbox" id="terms" required>
                    <label for="terms">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></label>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-user-plus"></i> Create Account
                    <span class="btn-loader"></span>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function previewPhoto(input) {
    const preview = document.getElementById('photoPreview');
    const placeholder = document.getElementById('photoUploadPlaceholder');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
            placeholder.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function switchRole(role) {
    document.getElementById('roleInput').value = role;
    document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`[data-role="${role}"]`).classList.add('active');
    document.getElementById('doctorFields').style.display = role === 'doctor' ? 'block' : 'none';
}

function togglePass(id) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
}

// Password strength indicator
document.getElementById('password')?.addEventListener('input', function() {
    const val = this.value;
    let strength = 0;
    if (val.length >= 8) strength++;
    if (/[A-Z]/.test(val)) strength++;
    if (/[0-9]/.test(val)) strength++;
    if (/[^A-Za-z0-9]/.test(val)) strength++;
    // Could add strength meter UI here
});
</script>
</body>
</html>
