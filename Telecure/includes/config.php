<?php
// Time zone used for ALL dates/times (appointment slots, "is this slot in the past?", e-mail expiry...).
// Without this PHP uses whatever php.ini says (often UTC or Europe/Berlin on WAMP) and the
// appointment times no longer match the clock on the wall. Change it if you are not in South Africa:
// https://www.php.net/manual/en/timezones.php
if (!defined('APP_TIMEZONE')) define('APP_TIMEZONE', 'Africa/Johannesburg');
date_default_timezone_set(APP_TIMEZONE);
// TeleCure - Database Configuration
// Place this file in includes/config.php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Change to your phpMyAdmin username
define('DB_PASS', '');            // Change to your phpMyAdmin password
define('DB_NAME', 'telecure_db');
define('SITE_NAME', 'TeleCure');
// SITE_URL is detected automatically so the app works in ANY folder name
// (e.g. /Devsync/Telecure, /telecure, or a virtual host). Override by defining
// it yourself above this line if you ever need a fixed value.
if (!defined('SITE_URL')) {
    $___scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $___host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $___doc    = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $___app    = realpath(__DIR__ . '/..');
    $___path   = '';
    if ($___doc && $___app && stripos(str_replace('\\', '/', $___app), str_replace('\\', '/', $___doc)) === 0) {
        $___path = substr(str_replace('\\', '/', $___app), strlen(str_replace('\\', '/', $___doc)));
    }
    define('SITE_URL', $___scheme . '://' . $___host . rtrim($___path, '/'));
    unset($___scheme, $___host, $___doc, $___app, $___path);
}
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
            // Make MySQL's NOW()/CURDATE() agree with PHP's clock (same UTC offset, e.g. +02:00).
            $pdo->exec("SET time_zone = '" . (new DateTime('now'))->format('P') . "'");
        } catch (PDOException $e) {
            error_log('TeleCure DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('Database connection failed. Check DB_HOST / DB_USER / DB_PASS / DB_NAME in includes/config.php and that MySQL is running.');
        }
    }
    return $pdo;
}

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
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
    $row = $stmt->fetch();
    if (!$row || (isset($row['is_active']) && !$row['is_active'])) {
        // Account was deleted or deactivated while logged in.
        $_SESSION = [];
        session_destroy();
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
    return $row;
}

// Cleans INPUT (trim + strip tags). It deliberately does NOT HTML-encode:
// encoding on the way in AND on the way out turned "O'Brien" into
// "O&amp;#039;Brien" on screen. Always escape on OUTPUT with e().
function sanitize($input) {
    return trim(strip_tags((string)$input));
}

// Fallbacks so the app still works if the PHP mbstring extension is not enabled.
if (!function_exists('mb_strlen')) {
    function mb_strlen($s, $enc = null) { return preg_match_all('/./us', (string)$s, $m); }
}
if (!function_exists('mb_substr')) {
    function mb_substr($s, $start, $len = null, $enc = null) {
        preg_match_all('/./us', (string)$s, $m);
        return implode('', array_slice($m[0], $start, $len));
    }
}

// Escape for HTML output.
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ── CSRF protection ─────────────────────────────────────────────────
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}
function csrf_valid() {
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return !empty($_SESSION['csrf_token']) && is_string($sent) && hash_equals($_SESSION['csrf_token'], $sent);
}
// Call at the top of any POST handler. Stops the request if the token is bad.
function requireCsrf($json = false) {
    if (!csrf_valid()) {
        http_response_code(419);
        if ($json) { header('Content-Type: application/json'); die(json_encode(['ok'=>false,'success'=>false,'error'=>'Session expired. Refresh the page.'])); }
        die('Your session expired or the form was invalid. Please go back, refresh the page and try again.');
    }
}

// ── Password policy (single source of truth: register, reset, profile) ──
// At least 8 characters, at least one number and at least one special character.
function passwordPolicyError($pw) {
    $pw = (string)$pw;
    if (strlen($pw) < 8)                          return 'Password must be at least 8 characters long.';
    if (!preg_match('/[0-9]/', $pw))              return 'Password must contain at least one number.';
    if (!preg_match('/[^A-Za-z0-9\s]/', $pw))     return 'Password must contain at least one special character (e.g. ! @ # $ % &).';
    return null;
}

function isAdmin($user) {
    return !empty($user['is_admin']);
}

// "Dr. Sarah Johnson" stays as is; "Sarah Johnson" becomes "Dr. Sarah Johnson".
function doctorName($name) {
    return preg_match('/^dr\.?\s/i', $name) ? $name : 'Dr. ' . $name;
}

// Single source of truth for bookable time slots (used by booking form + API).
function appointmentSlots() {
    return ['08:00','08:30','09:00','09:30','10:00','10:30','11:00','11:30','12:00','12:30',
            '14:00','14:30','15:00','15:30','16:00','16:30','17:00'];
}

// Chats are only allowed between a patient and a doctor (never self, never a disabled account).
function canMessage($db, $fromId, $toId) {
    if (!$toId || $toId == $fromId) return false;
    $stmt = $db->prepare("SELECT role FROM users WHERE id IN (?, ?) AND is_active = 1");
    $stmt->execute([$fromId, $toId]);
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    sort($roles);
    return $roles === ['doctor', 'patient'];
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
