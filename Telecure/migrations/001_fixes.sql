-- Run this ONCE in phpMyAdmin (SQL tab) on an EXISTING telecure_db to apply the fixes.
-- A fresh import of telecure_db.sql already includes all of this.
USE telecure_db;

-- 1. Real administrator flag (admin.php used to let any doctor in).
ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;
UPDATE users SET is_admin = 1 WHERE email = 'sarah@telecure.com';   -- change to YOUR admin's email

-- 2. Secure password-reset tokens (they used to be stored in users.bio and were publicly visible).
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_token (token_hash),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
-- Remove any reset tokens that leaked into bios (and invalidate them).
UPDATE users SET bio = '' WHERE bio LIKE 'RESET:%';

-- 3. Undo the double HTML-encoding the old sanitize() stored in the database
--    (e.g. "O&#039;Brien" -> "O'Brien"). Safe to run once.
UPDATE users SET full_name = REPLACE(REPLACE(REPLACE(REPLACE(full_name,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>');
UPDATE users SET full_name = REPLACE(full_name,'&amp;','&');
UPDATE users SET bio = REPLACE(REPLACE(REPLACE(REPLACE(bio,'&#039;',''''),'&quot;','"'),'&lt;','<'),'&gt;','>');
UPDATE users SET bio = REPLACE(bio,'&amp;','&');

-- 4. Performance indexes + missing foreign key
ALTER TABLE appointments ADD INDEX idx_slot (doctor_id, appointment_date, appointment_time);
ALTER TABLE appointments ADD INDEX idx_patient (patient_id, appointment_date);
ALTER TABLE messages ADD INDEX idx_thread (sender_id, receiver_id, id);
ALTER TABLE messages ADD INDEX idx_unread (receiver_id, is_read);

-- 5. Seeded doctors: use the local photos (copied to uploads/avatars/) instead of randomuser.me URLs.
UPDATE users SET profile_photo = 'dr_sarah_johnson.jpg'  WHERE email = 'sarah@telecure.com'   AND profile_photo LIKE 'http%';
UPDATE users SET profile_photo = 'dr_michael_osei.jpg'   WHERE email = 'michael@telecure.com' AND profile_photo LIKE 'http%';
UPDATE users SET profile_photo = 'dr_priya_naidoo.png'   WHERE email = 'priya@telecure.com'   AND profile_photo LIKE 'http%';
