-- ============================================================
-- BSIT TUTORING SYSTEM - COMPLETE DATABASE SCHEMA v2.0
-- ============================================================

DROP DATABASE IF EXISTS bsit_tutoring_db;
CREATE DATABASE bsit_tutoring_db;
USE bsit_tutoring_db;

-- ============================================================
-- USERS TABLE
-- ============================================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    school_id VARCHAR(20) UNIQUE,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('student', 'tutor', 'admin') NOT NULL DEFAULT 'student',
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    bio TEXT,
    year_level INT DEFAULT 1,
    hourly_rate DECIMAL(10,2) DEFAULT 500.00,
    expertise TEXT,
    profile_pic VARCHAR(255),
    is_available BOOLEAN DEFAULT TRUE,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT,
    approved_by INT,
    approved_at DATETIME,
    last_active DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_email (email),
    INDEX idx_school_id (school_id),
    INDEX idx_is_available (is_available),
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- SUBJECTS TABLE
-- ============================================================
CREATE TABLE subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default subjects
INSERT INTO subjects (name, description) VALUES
('IM207 - Web Systems', 'Learn modern web development with HTML, CSS, JavaScript, and frameworks'),
('PF205 - Programming Fundamentals', 'Master programming basics with Python and Java'),
('NET208 - Networking', 'Computer networks, protocols, and security fundamentals'),
('IPT209 - Integrative Programming', 'Advanced programming concepts and system integration');

-- ============================================================
-- TUTOR SUBJECTS TABLE
-- ============================================================
CREATE TABLE tutor_subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tutor_id INT NOT NULL,
    subject_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_tutor_subject (tutor_id, subject_id),
    FOREIGN KEY (tutor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    INDEX idx_tutor (tutor_id),
    INDEX idx_subject (subject_id)
);

-- ============================================================
-- BOOKINGS TABLE
-- ============================================================
CREATE TABLE bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_number VARCHAR(20) UNIQUE NOT NULL,
    student_id INT NOT NULL,
    tutor_id INT NOT NULL,
    subject_id INT NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    duration DECIMAL(3,1) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'approved', 'completed', 'cancelled', 'rejected') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50),
    payment_intent_id VARCHAR(255),
    notes TEXT,
    cancelled_by INT,
    cancelled_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_student (student_id),
    INDEX idx_tutor (tutor_id),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_booking_date (booking_date),
    INDEX idx_booking_number (booking_number),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tutor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- PAYMENTS TABLE
-- ============================================================
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_number VARCHAR(50) UNIQUE NOT NULL,
    booking_id INT NOT NULL,
    student_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50),
    payment_intent_id VARCHAR(255),
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    payment_date DATETIME,
    receipt_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_booking (booking_id),
    INDEX idx_student (student_id),
    INDEX idx_transaction (transaction_number),
    INDEX idx_status (status),
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- RATINGS TABLE
-- ============================================================
CREATE TABLE ratings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL UNIQUE,
    student_id INT NOT NULL,
    tutor_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_tutor (tutor_id),
    INDEX idx_booking (booking_id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tutor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- TASKS (ASSIGNMENTS) TABLE
-- ============================================================
CREATE TABLE tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_number VARCHAR(20) UNIQUE NOT NULL,
    tutor_id INT NOT NULL,
    subject_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    due_date DATE,
    due_time TIME,
    max_points INT DEFAULT 100,
    status ENUM('active', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_tutor (tutor_id),
    INDEX idx_subject (subject_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date),
    INDEX idx_task_number (task_number),
    FOREIGN KEY (tutor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- ============================================================
-- TASK SUBMISSIONS TABLE
-- ============================================================
CREATE TABLE task_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    student_id INT NOT NULL,
    submission_text TEXT,
    submission_file VARCHAR(255),
    status ENUM('draft', 'submitted', 'graded', 'late') DEFAULT 'draft',
    grade DECIMAL(5,2),
    feedback TEXT,
    submitted_at DATETIME,
    graded_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_submission (task_id, student_id),
    INDEX idx_task (task_id),
    INDEX idx_student (student_id),
    INDEX idx_status (status),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- LEARNING MODULES TABLE
-- ============================================================
CREATE TABLE modules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    module_number VARCHAR(20) UNIQUE NOT NULL,
    author_id INT NOT NULL,
    subject_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content LONGTEXT,
    video_url VARCHAR(500),
    difficulty ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    order_num INT DEFAULT 1,
    status ENUM('draft', 'published', 'archived') DEFAULT 'published',
    views_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_author (author_id),
    INDEX idx_subject (subject_id),
    INDEX idx_status (status),
    INDEX idx_module_number (module_number),
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- ============================================================
-- MODULE PROGRESS TABLE
-- ============================================================
CREATE TABLE module_progress (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    module_id INT NOT NULL,
    status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
    progress_percent INT DEFAULT 0,
    started_at DATETIME,
    completed_at DATETIME,
    last_accessed DATETIME,
    
    UNIQUE KEY unique_progress (user_id, module_id),
    INDEX idx_user (user_id),
    INDEX idx_module (module_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
);

-- ============================================================
-- QUIZZES TABLE
-- ============================================================
CREATE TABLE quizzes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quiz_number VARCHAR(20) UNIQUE NOT NULL,
    author_id INT NOT NULL,
    subject_id INT NOT NULL,
    module_id INT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    time_limit INT DEFAULT 30,
    passing_score INT DEFAULT 60,
    attempts_allowed INT DEFAULT 1,
    status ENUM('draft', 'published', 'archived') DEFAULT 'published',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_author (author_id),
    INDEX idx_subject (subject_id),
    INDEX idx_module (module_id),
    INDEX idx_status (status),
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE SET NULL
);

-- ============================================================
-- QUIZ QUESTIONS TABLE
-- ============================================================
CREATE TABLE quiz_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quiz_id INT NOT NULL,
    question TEXT NOT NULL,
    type ENUM('multiple_choice', 'true_false', 'short_answer', 'coding') DEFAULT 'multiple_choice',
    options JSON,
    correct_answer VARCHAR(500) NOT NULL,
    points INT DEFAULT 1,
    order_num INT DEFAULT 1,
    
    INDEX idx_quiz (quiz_id),
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

-- ============================================================
-- QUIZ ATTEMPTS TABLE
-- ============================================================
CREATE TABLE quiz_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quiz_id INT NOT NULL,
    user_id INT NOT NULL,
    score INT DEFAULT 0,
    total_points INT DEFAULT 0,
    percentage DECIMAL(5,2),
    passed BOOLEAN DEFAULT FALSE,
    answers JSON,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    
    INDEX idx_quiz (quiz_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- NOTIFICATIONS TABLE
-- ============================================================
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255),
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'general',
    is_read BOOLEAN DEFAULT FALSE,
    link VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- SCHOOL RECORDS TABLE
-- ============================================================
CREATE TABLE school_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    school_id VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('student', 'tutor') DEFAULT 'student',
    year_level INT DEFAULT 1,
    course VARCHAR(50) DEFAULT 'BSIT',
    is_registered BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_school_id (school_id),
    INDEX idx_is_registered (is_registered)
);

-- ============================================================
-- ACTIVITY LOGS TABLE
-- ============================================================
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- SYSTEM SETTINGS TABLE
-- ============================================================
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'BSIT Tutoring System', 'text', 'Site name displayed throughout the system'),
('site_email', 'support@bsit-tutoring.edu.ph', 'text', 'Default system email address'),
('maintenance_mode', '0', 'boolean', 'Put system in maintenance mode'),
('booking_requires_approval', '1', 'boolean', 'Require admin approval for bookings'),
('default_hourly_rate', '500', 'number', 'Default hourly rate for tutors'),
('max_booking_days_advance', '30', 'number', 'Maximum days in advance for booking');

-- ============================================================
-- CREATE DEFAULT ADMIN ACCOUNT
-- ============================================================
-- Password: Admin@2024!
INSERT INTO users (school_id, username, password, email, role, full_name, status, approved_at) VALUES
('ADMIN001', 'admin', '$2y$10$YourHashedPasswordHere', 'admin@bsit.edu.ph', 'admin', 'System Administrator', 'approved', NOW());

-- ============================================================
-- CREATE SAMPLE SCHOOL RECORDS
-- ============================================================
INSERT INTO school_records (school_id, full_name, role, year_level) VALUES
('2024-00001', 'Juan Dela Cruz', 'student', 2),
('2024-00002', 'Maria Santos', 'student', 2),
('2024-00003', 'Jose Rizal', 'tutor', 4),
('2024-00004', 'Gabriela Silang', 'tutor', 4),
('2024-00005', 'Andres Bonifacio', 'student', 3),
('2024-00006', 'Melchora Aquino', 'student', 1);

-- ============================================================
-- TRIGGERS
-- ============================================================

-- Generate booking number before insert
DELIMITER //
CREATE TRIGGER before_booking_insert
BEFORE INSERT ON bookings
FOR EACH ROW
BEGIN
    IF NEW.booking_number IS NULL THEN
        SET NEW.booking_number = CONCAT('BK-', DATE_FORMAT(NOW(), '%Y%m'), '-', LPAD((SELECT IFNULL(MAX(id), 0) + 1 FROM bookings), 6, '0'));
    END IF;
END//
DELIMITER ;

-- Generate task number before insert
DELIMITER //
CREATE TRIGGER before_task_insert
BEFORE INSERT ON tasks
FOR EACH ROW
BEGIN
    IF NEW.task_number IS NULL THEN
        SET NEW.task_number = CONCAT('TSK-', DATE_FORMAT(NOW(), '%Y%m'), '-', LPAD((SELECT IFNULL(MAX(id), 0) + 1 FROM tasks), 6, '0'));
    END IF;
END//
DELIMITER ;

-- Generate transaction number before payment insert
DELIMITER //
CREATE TRIGGER before_payment_insert
BEFORE INSERT ON payments
FOR EACH ROW
BEGIN
    IF NEW.transaction_number IS NULL THEN
        SET NEW.transaction_number = CONCAT('TXN-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD((SELECT IFNULL(MAX(id), 0) + 1 FROM payments), 8, '0'));
    END IF;
END//
DELIMITER ;

-- Generate module number before insert
DELIMITER //
CREATE TRIGGER before_module_insert
BEFORE INSERT ON modules
FOR EACH ROW
BEGIN
    IF NEW.module_number IS NULL THEN
        SET NEW.module_number = CONCAT('MOD-', DATE_FORMAT(NOW(), '%Y%m'), '-', LPAD((SELECT IFNULL(MAX(id), 0) + 1 FROM modules), 6, '0'));
    END IF;
END//
DELIMITER ;

-- Generate quiz number before insert
DELIMITER //
CREATE TRIGGER before_quiz_insert
BEFORE INSERT ON quizzes
FOR EACH ROW
BEGIN
    IF NEW.quiz_number IS NULL THEN
        SET NEW.quiz_number = CONCAT('QZ-', DATE_FORMAT(NOW(), '%Y%m'), '-', LPAD((SELECT IFNULL(MAX(id), 0) + 1 FROM quizzes), 6, '0'));
    END IF;
END//
DELIMITER ;