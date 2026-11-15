-- ============================================================
-- TeleCure Database Schema (utf8mb4-safe, InnoDB DYNAMIC row format)
-- Compatible with MySQL 5.6+, 5.7+, 8.0+ and MariaDB 10.2+
-- ============================================================

CREATE DATABASE IF NOT EXISTS telecure_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE telecure_db;

-- ------------------------------------------------------------
-- Users (patients + doctors + admins)
-- ------------------------------------------------------------
CREATE TABLE users (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    full_name      VARCHAR(150) NOT NULL,
    email          VARCHAR(150) NOT NULL,
    password       VARCHAR(255) NOT NULL,
    role           ENUM('patient','doctor') NOT NULL DEFAULT 'patient',
    phone          VARCHAR(20),
    date_of_birth  DATE,
    gender         ENUM('male','female','other'),
    profile_photo  VARCHAR(255) DEFAULT 'default_avatar.png',
    bio            TEXT,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active      TINYINT(1) DEFAULT 1,
    is_admin       TINYINT(1) NOT NULL DEFAULT 0,
    last_seen      TIMESTAMP NULL,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Doctor profiles
-- ------------------------------------------------------------
CREATE TABLE doctor_profiles (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    specialization  VARCHAR(150),
    license_number  VARCHAR(100),
    years_experience INT DEFAULT 0,
    consultation_fee DECIMAL(10,2) DEFAULT 0.00,
    availability_days VARCHAR(100),
    is_verified     TINYINT(1) DEFAULT 0,
    rating          DECIMAL(3,2) DEFAULT 0.00,
    total_reviews   INT DEFAULT 0,
    UNIQUE KEY uq_doctor_license (license_number),
    UNIQUE KEY uq_doctor_user (user_id),
    KEY idx_doctor_specialization (specialization(100)),
    CONSTRAINT fk_doctor_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Appointments
-- ------------------------------------------------------------
CREATE TABLE appointments (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    patient_id        INT NOT NULL,
    doctor_id         INT NOT NULL,
    appointment_date  DATE NOT NULL,
    appointment_time  TIME NOT NULL,
    type              ENUM('video','in_person','chat') DEFAULT 'video',
    status            ENUM('pending','confirmed','completed','cancelled') DEFAULT 'pending',
    reason            TEXT,
    notes             TEXT,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_appt_patient (patient_id, appointment_date),
    KEY idx_appt_doctor  (doctor_id, appointment_date),
    KEY idx_appt_status  (status),
    CONSTRAINT fk_appt_patient FOREIGN KEY (patient_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_appt_doctor FOREIGN KEY (doctor_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Chat messages
-- ------------------------------------------------------------
CREATE TABLE messages (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    sender_id     INT NOT NULL,
    receiver_id   INT NOT NULL,
    message       TEXT NOT NULL,
    message_type  ENUM('text','image','file') DEFAULT 'text',
    is_read       TINYINT(1) DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_thread (sender_id, receiver_id, id),
    KEY idx_unread (receiver_id, is_read),
    CONSTRAINT fk_msg_sender FOREIGN KEY (sender_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_msg_receiver FOREIGN KEY (receiver_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Prescriptions
-- ------------------------------------------------------------
CREATE TABLE prescriptions (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id      INT NOT NULL,
    patient_id     INT NOT NULL,
    appointment_id INT NULL,
    diagnosis      TEXT,
    notes          TEXT,
    issued_date    DATE NOT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rx_doctor  (doctor_id),
    KEY idx_rx_patient (patient_id, issued_date),
    KEY idx_rx_appt    (appointment_id),
    CONSTRAINT fk_rx_doctor FOREIGN KEY (doctor_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rx_patient FOREIGN KEY (patient_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rx_appt FOREIGN KEY (appointment_id)
        REFERENCES appointments(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Prescription medications
-- ------------------------------------------------------------
CREATE TABLE prescription_medications (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id  INT NOT NULL,
    medication_name  VARCHAR(200) NOT NULL,
    dosage           VARCHAR(100),
    frequency        VARCHAR(100),
    duration         VARCHAR(100),
    instructions     TEXT,
    KEY idx_med_rx (prescription_id),
    CONSTRAINT fk_med_rx FOREIGN KEY (prescription_id)
        REFERENCES prescriptions(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Video consultation sessions
-- ------------------------------------------------------------
CREATE TABLE video_sessions (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    room_name      VARCHAR(255) NOT NULL,
    started_at     TIMESTAMP NULL,
    ended_at       TIMESTAMP NULL,
    status         ENUM('waiting','active','ended') DEFAULT 'waiting',
    UNIQUE KEY uq_video_room (room_name),
    UNIQUE KEY uq_video_appt (appointment_id),
    CONSTRAINT fk_video_appt FOREIGN KEY (appointment_id)
        REFERENCES appointments(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Reviews / ratings
-- ------------------------------------------------------------
CREATE TABLE reviews (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    patient_id     INT NOT NULL,
    doctor_id      INT NOT NULL,
    appointment_id INT NULL,
    rating         INT NOT NULL,
    comment        TEXT,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_review_doctor (doctor_id, created_at),
    KEY idx_review_patient (patient_id),
    UNIQUE KEY uq_review_appt (appointment_id, patient_id),
    CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5),
    CONSTRAINT fk_review_patient FOREIGN KEY (patient_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_review_doctor FOREIGN KEY (doctor_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_review_appt FOREIGN KEY (appointment_id)
        REFERENCES appointments(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Notifications
-- ------------------------------------------------------------
CREATE TABLE notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    title      VARCHAR(200),
    message    TEXT,
    type       ENUM('appointment','message','prescription','general') DEFAULT 'general',
    is_read    TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notif_user (user_id, is_read, created_at),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Password reset tokens (SHA-256 hash of token only)
-- ------------------------------------------------------------
CREATE TABLE password_resets (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    token_hash  CHAR(64) NOT NULL,
    expires_at  DATETIME NOT NULL,
    used_at     DATETIME NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_token (token_hash),
    KEY idx_reset_user (user_id, expires_at),
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DEMO DATA
-- All demo accounts use the password:  password
-- (bcrypt hash below = "password")
-- Dr. Sarah Johnson is also the demo ADMINISTRATOR (is_admin = 1).
-- ============================================================
INSERT INTO users
    (full_name, email, password, role, phone, gender, profile_photo, bio, is_admin)
VALUES
('Dr. Sarah Johnson', 'sarah@telecure.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'doctor',  '+27 11 000 0001', 'female', 'dr_sarah_johnson.jpg',
 'Cardiologist with 15 years of experience', 1),

('Dr. Michael Osei', 'michael@telecure.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'doctor',  '+27 11 000 0002', 'male',   'dr_michael_osei.jpg',
 'General Practitioner specializing in family medicine', 0),

('Dr. Priya Naidoo', 'priya@telecure.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'doctor',  '+27 11 000 0003', 'female', 'dr_priya_naidoo.png',
 'Pediatrician with a passion for child health', 0),

('Demo Patient', 'patient@telecure.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'patient', '+27 82 000 0004', 'other',  'default_avatar.png',
 'Demo patient account for testing', 0);

INSERT INTO doctor_profiles
    (user_id, specialization, license_number, years_experience,
     consultation_fee, is_verified, rating)
VALUES
(1, 'Cardiology',       'MP12345', 15, 850.00, 1, 4.8),
(2, 'General Practice', 'MP12346', 10, 600.00, 1, 4.6),
(3, 'Pediatrics',       'MP12347',  8, 700.00, 1, 4.9);