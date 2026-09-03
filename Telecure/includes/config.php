<?php
// TeleCure - Database Configuration
// Place this file in includes/config.php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Change to your phpMyAdmin username
define('DB_PASS', '');            // Change to your phpMyAdmin password
define('DB_NAME', 'telecure_db');
define('SITE_NAME', 'TeleCure');
define('SITE_URL', 'http://localhost/telecure');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('AVATAR_UPLOAD_DIR', UPLOAD_PATH . 'avatars/');
define('AVATAR_UPLOAD_URL', 'uploads/avatars/');
define('AVATAR_MAX_BYTES', 3 * 1024 * 1024); // 3MB
define('AVATAR_ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'webp']);

// Create PDO connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth helpers
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

function currentUser() {
    if (!isLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT u.*, dp.specialization, dp.consultation_fee, dp.is_verified, dp.rating, dp.years_experience 
                          FROM users u 
                          LEFT JOIN doctor_profiles dp ON u.id = dp.user_id 
                          WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// ── Avatar helpers ──────────────────────────────────────────────────
// Returns a displayable photo URL for a user row. Falls back to a
// generated initials avatar if no real photo was uploaded/seeded.
function avatarUrl($row, $size = 80) {
    $name = $row['full_name'] ?? $row['name'] ?? '?';
    $photo = $row['profile_photo'] ?? null;

    if (!empty($photo) && $photo !== 'default_avatar.png') {
        // Seeded/demo photos are stored as full URLs.
        if (preg_match('#^https?://#i', $photo)) {
            return $photo;
        }
        // User-uploaded photos are stored as a filename inside uploads/avatars/.
        $localPath = AVATAR_UPLOAD_DIR . basename($photo);
        if (file_exists($localPath)) {
            // Relative path (matches how css/js/etc. are referenced) so this
            // works no matter what folder the app is deployed into, instead
            // of depending on SITE_URL being configured correctly.
            return AVATAR_UPLOAD_URL . rawurlencode(basename($photo));
        }
    }

    $role = $row['role'] ?? 'patient';
    $bg = $role === 'doctor' ? '1565C0' : 'E3F2FD';
    $fg = $role === 'doctor' ? 'fff' : '1565C0';
    return "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background={$bg}&color={$fg}&size={$size}&bold=true";
}

// Ensures the avatar upload directory exists.
function ensureAvatarDir() {
    if (!is_dir(AVATAR_UPLOAD_DIR)) {
        mkdir(AVATAR_UPLOAD_DIR, 0755, true);
    }
}

// Validates and moves an uploaded profile photo ($_FILES['field']).
// Returns the stored filename on success, or throws an Exception with
// a user-friendly message on failure.
function handleAvatarUpload($field, $userIdHint = 'user') {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // nothing uploaded
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Photo upload failed. Please try again.');
    }
    if ($file['size'] > AVATAR_MAX_BYTES) {
        throw new Exception('Photo must be smaller than 3MB.');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, AVATAR_ALLOWED_EXT, true)) {
        throw new Exception('Photo must be a JPG, PNG, or WEBP image.');
    }
    // Verify it's actually an image, not just a renamed file.
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        throw new Exception('The uploaded file is not a valid image.');
    }

    ensureAvatarDir();
    $filename = 'avatar_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $userIdHint) . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = AVATAR_UPLOAD_DIR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new Exception('Could not save the uploaded photo.');
    }
    return $filename;
}

function addNotification($userId, $title, $message, $type = 'general') {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $title, $message, $type]);
}

function getUnreadNotifications($userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

function getUnreadMessages($userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}
?>
