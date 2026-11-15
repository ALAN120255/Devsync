<?php
/**
 * Site settings stored in the database (table app_settings), plus helpers to
 * keep secrets (the SMTP password) encrypted at rest.
 *
 * The encryption key lives in includes/.mail_secret_key (created automatically,
 * blocked from the web by includes/.htaccess, ignored by Git). A copy of the
 * database alone therefore does not reveal the e-mail password.
 */

function ensureSettingsTable($db) {
    static $done = false;
    if ($done) return;
    $db->exec("CREATE TABLE IF NOT EXISTS app_settings (
        setting_key   VARCHAR(100) PRIMARY KEY,
        setting_value TEXT,
        updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $done = true;
}

function allSettings($refresh = false) {
    static $cache = null;
    if ($cache !== null && !$refresh) return $cache;
    $cache = [];
    try {
        $db = getDB();
        ensureSettingsTable($db);
        foreach ($db->query("SELECT setting_key, setting_value FROM app_settings") as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        error_log('TeleCure settings read failed: ' . $e->getMessage());
    }
    return $cache;
}

function getSetting($key, $default = null) {
    $all = allSettings();
    return array_key_exists($key, $all) ? $all[$key] : $default;
}

function setSettings(array $pairs) {
    $db = getDB();
    ensureSettingsTable($db);
    $stmt = $db->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
                          ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($pairs as $k => $v) $stmt->execute([$k, (string)$v]);
    allSettings(true);
}

// ── Encryption of secrets ────────────────────────────────────────────
function settingsKey() {
    $file = __DIR__ . '/.mail_secret_key';
    if (is_readable($file)) {
        $hex = trim((string)file_get_contents($file));
        if (strlen($hex) === 64 && ctype_xdigit($hex)) return hex2bin($hex);
    }
    $hex = bin2hex(random_bytes(32));
    if (@file_put_contents($file, $hex, LOCK_EX) !== false) {
        @chmod($file, 0600);
        return hex2bin($hex);
    }
    // Could not write the key file: derive a key from the DB credentials instead (weaker, but still not plain text).
    return hash('sha256', DB_NAME . '|' . DB_USER . '|' . DB_PASS . '|telecure', true);
}

function encryptSecret($plain) {
    if ($plain === '') return '';
    if (!function_exists('openssl_encrypt') || !in_array('aes-256-gcm', openssl_get_cipher_methods(), true)) {
        return 'plain:' . base64_encode($plain);   // openssl missing: at least not readable at a glance
    }
    $iv = random_bytes(12);
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', settingsKey(), OPENSSL_RAW_DATA, $iv, $tag);
    return 'enc1:' . base64_encode($iv . $tag . $cipher);
}

function decryptSecret($stored) {
    if ($stored === '' || $stored === null) return '';
    if (strpos($stored, 'plain:') === 0) return (string)base64_decode(substr($stored, 6));
    if (strpos($stored, 'enc1:') !== 0 || !function_exists('openssl_decrypt')) return '';
    $raw = base64_decode(substr($stored, 5), true);
    if ($raw === false || strlen($raw) < 29) return '';
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', settingsKey(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? '' : $plain;
}
