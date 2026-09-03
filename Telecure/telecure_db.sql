-- TeleCure Database Schema
-- Import this into phpMyAdmin

CREATE DATABASE IF NOT EXISTS telecure_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE telecure_db;

-- Users table (both patients and doctors)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('patient','doctor') NOT NULL DEFAULT 'patient',
    phone VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('male','female','other'),
    profile_photo VARCHAR(255) DEFAULT 'default_avatar.png',
    bio TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    last_seen TIMESTAMP NULL
);

-- Doctor profiles (extra info for doctors)
CREATE TABLE doctor_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    specialization VARCHAR(150),
    license_number VARCHAR(100) UNIQUE,
    years_experience INT DEFAULT 0,
    consultation_fee DECIMAL(10,2) DEFAULT 0.00,
    availability_days VARCHAR(100),
    is_verified TINYINT(1) DEFAULT 0,
    rating DECIMAL(3,2) DEFAULT 0.00,
    total_reviews INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Appointments
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    type ENUM('video','in_person','chat') DEFAULT 'video',
    status ENUM('pending','confirmed','completed','cancelled') DEFAULT 'pending',
    reason TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Chat messages
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text','image','file') DEFAULT 'text',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Prescriptions
CREATE TABLE prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    patient_id INT NOT NULL,
    appointment_id INT,
    diagnosis TEXT,
    notes TEXT,
    issued_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Prescription medications
CREATE TABLE prescription_medications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT NOT NULL,
    medication_name VARCHAR(200) NOT NULL,
    dosage VARCHAR(100),
    frequency VARCHAR(100),
    duration VARCHAR(100),
    instructions TEXT,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE
);

-- Video consultation sessions
CREATE TABLE video_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    room_name VARCHAR(255) UNIQUE NOT NULL,
    started_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    status ENUM('waiting','active','ended') DEFAULT 'waiting',
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
);

-- Reviews/ratings
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_id INT,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id),
    FOREIGN KEY (doctor_id) REFERENCES users(id)
);

-- Notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200),
    message TEXT,
    type ENUM('appointment','message','prescription','general') DEFAULT 'general',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert sample doctor accounts (password: Doctor123!)
INSERT INTO users (full_name, email, password, role, phone, gender, profile_photo, bio) VALUES
('Dr. Sarah Johnson', 'sarah@telecure.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', '+27 11 000 0001', 'female', 'https://randomuser.me/api/portraits/women/68.jpg', 'Cardiologist with 15 years of experience'),
('Dr. Michael Osei', 'michael@telecure.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', '+27 11 000 0002', 'male', 'https://randomuser.me/api/portraits/men/32.jpg', 'General Practitioner specializing in family medicine'),
('Dr. Priya Naidoo', 'priya@telecure.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', '+27 11 000 0003', 'female', 'https://randomuser.me/api/portraits/women/44.jpg', 'Pediatrician with a passion for child health');

INSERT INTO doctor_profiles (user_id, specialization, license_number, years_experience, consultation_fee, is_verified, rating) VALUES
(1, 'Cardiology', 'MP12345', 15, 850.00, 1, 4.8),
(2, 'General Practice', 'MP12346', 10, 600.00, 1, 4.6),
(3, 'Pediatrics', 'MP12347', 8, 700.00, 1, 4.9);
