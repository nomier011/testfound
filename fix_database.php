<?php
/**
 * Database Migration / Fix Script
 * Run once: http://localhost/testfound/fix_database.php
 * DELETE this file after running.
 */

// Localhost only
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    http_response_code(403); die('Access denied.');
}

require_once 'config.php';
$conn = getConnection();
$msgs = [];

function run($conn, $sql, $label) {
    global $msgs;
    try { $conn->exec($sql); $msgs[] = ['ok', $label]; }
    catch (PDOException $e) { $msgs[] = ['warn', "$label — " . $e->getMessage()]; }
}

// ── Users table columns ───────────────────────────────────────────────────────
run($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS status ENUM('pending','approved','rejected') DEFAULT 'pending'", "users.status");
run($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_by INT DEFAULT NULL", "users.approved_by");
run($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_at DATETIME DEFAULT NULL", "users.approved_at");
run($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS rejection_reason TEXT DEFAULT NULL", "users.rejection_reason");
run($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS school_id VARCHAR(20) DEFAULT NULL", "users.school_id");
run($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS hourly_rate DECIMAL(10,2) DEFAULT 500.00", "users.hourly_rate");
run($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS expertise TEXT DEFAULT NULL", "users.expertise");
run($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS year_level INT DEFAULT 1", "users.year_level");
run($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS is_available BOOLEAN DEFAULT TRUE", "users.is_available");
run($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_pic VARCHAR(255) DEFAULT NULL", "users.profile_pic");
run($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL", "users.phone");
run($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT DEFAULT NULL", "users.bio");
run($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS address TEXT DEFAULT NULL", "users.address");
run($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_active DATETIME DEFAULT NULL", "users.last_active");

// ── Payments table ────────────────────────────────────────────────────────────
run($conn, "ALTER TABLE payments ADD COLUMN IF NOT EXISTS payment_date DATETIME DEFAULT NULL", "payments.payment_date");
run($conn, "ALTER TABLE payments ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100) DEFAULT NULL", "payments.transaction_id");

// ── Bookings: reminder_sent column ───────────────────────────────────────────
run($conn, "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS reminder_sent TINYINT(1) DEFAULT 0", "bookings.reminder_sent");
run($conn, "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS meet_link VARCHAR(500) DEFAULT NULL", "bookings.meet_link");

// ── Notifications table ───────────────────────────────────────────────────────
run($conn, "CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'general',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)", "notifications table");

// ── Tasks / Assignments ───────────────────────────────────────────────────────
run($conn, "CREATE TABLE IF NOT EXISTS tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tutor_id INT NOT NULL,
    subject_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    due_date DATE DEFAULT NULL,
    due_time TIME DEFAULT NULL,
    status ENUM('active','closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tutor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
)", "tasks table");

run($conn, "CREATE TABLE IF NOT EXISTS task_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    student_id INT NOT NULL,
    submission_text TEXT,
    submission_file VARCHAR(255),
    status ENUM('submitted','graded') DEFAULT 'submitted',
    grade DECIMAL(5,2) DEFAULT NULL,
    feedback TEXT,
    submitted_at DATETIME DEFAULT NULL,
    graded_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_submission (task_id, student_id)
)", "task_submissions table");

// ── School records ────────────────────────────────────────────────────────────
run($conn, "CREATE TABLE IF NOT EXISTS school_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    school_id VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('student','tutor') DEFAULT 'student',
    year_level INT DEFAULT 1,
    is_registered BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "school_records table");

// ── Modules & Quizzes ─────────────────────────────────────────────────────────
run($conn, "CREATE TABLE IF NOT EXISTS modules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    author_id INT NOT NULL,
    subject_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content LONGTEXT,
    video_url VARCHAR(500),
    difficulty ENUM('beginner','intermediate','advanced') DEFAULT 'beginner',
    order_num INT DEFAULT 1,
    status ENUM('draft','published') DEFAULT 'published',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
)", "modules table");

run($conn, "CREATE TABLE IF NOT EXISTS module_progress (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    module_id INT NOT NULL,
    status ENUM('in_progress','completed') DEFAULT 'completed',
    completed_at DATETIME DEFAULT NULL,
    UNIQUE KEY unique_progress (user_id, module_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
)", "module_progress table");

run($conn, "CREATE TABLE IF NOT EXISTS quizzes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    author_id INT NOT NULL,
    subject_id INT NOT NULL,
    module_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    time_limit INT DEFAULT 30,
    passing_score INT DEFAULT 60,
    status ENUM('draft','published') DEFAULT 'published',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
)", "quizzes table");

run($conn, "CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quiz_id INT NOT NULL,
    question TEXT NOT NULL,
    type ENUM('mcq','true_false','coding') DEFAULT 'mcq',
    option_a VARCHAR(500), option_b VARCHAR(500),
    option_c VARCHAR(500), option_d VARCHAR(500),
    correct_answer VARCHAR(10) NOT NULL,
    points INT DEFAULT 1,
    order_num INT DEFAULT 1,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
)", "quiz_questions table");

run($conn, "CREATE TABLE IF NOT EXISTS quiz_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quiz_id INT NOT NULL,
    user_id INT NOT NULL,
    score INT DEFAULT 0,
    total_points INT DEFAULT 0,
    passed BOOLEAN DEFAULT FALSE,
    started_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)", "quiz_attempts table");

// ── Activity logs ─────────────────────────────────────────────────────────────
run($conn, "CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
)", "activity_logs table");

// ── Ratings unique key ────────────────────────────────────────────────────────
run($conn, "ALTER TABLE ratings ADD UNIQUE KEY IF NOT EXISTS unique_booking_rating (booking_id)", "ratings unique key");

// ── Approve existing users ────────────────────────────────────────────────────
run($conn, "UPDATE users SET status = 'approved' WHERE status IS NULL OR status = ''", "Approve existing users");

// ── Default admin ─────────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT id FROM users WHERE username = 'admin'");
$stmt->execute();
if (!$stmt->fetch()) {
    $hash = password_hash('admin123', PASSWORD_BCRYPT);
    $conn->prepare("INSERT INTO users (username, password, email, role, full_name, status) VALUES ('admin', ?, 'admin@bsit.edu', 'admin', 'System Administrator', 'approved')")->execute([$hash]);
    $msgs[] = ['ok', 'Admin account created (admin / admin123)'];
} else {
    $msgs[] = ['info', 'Admin account already exists'];
}

// ── Default subjects ──────────────────────────────────────────────────────────
$conn->exec("INSERT IGNORE INTO subjects (name, description) VALUES
    ('IM207 - Web Systems', 'Web development with HTML, CSS, JavaScript'),
    ('PF205 - Programming Fundamentals', 'Programming basics with Python and Java'),
    ('NET208 - Networking', 'Computer networks and protocols'),
    ('IPT209 - Integrative Programming', 'Advanced programming and system integration')");
$msgs[] = ['ok', 'Default subjects ensured'];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Database Fix</title>
<style>
  body { font-family: system-ui, sans-serif; max-width: 700px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
  h1 { color: #8B0000; margin-bottom: 20px; }
  .item { padding: 8px 14px; margin: 5px 0; border-radius: 6px; font-size: 0.88rem; }
  .ok   { background: #d1fae5; color: #065f46; }
  .warn { background: #fef3c7; color: #92400e; }
  .info { background: #dbeafe; color: #1e40af; }
  .done { margin-top: 24px; padding: 16px; background: #d1fae5; border-radius: 10px; color: #065f46; font-weight: 600; }
  a { color: #8B0000; }
</style>
</head>
<body>
<h1>🔧 Database Migration</h1>
<?php foreach ($msgs as [$type, $msg]): ?>
  <div class="item <?php echo $type; ?>"><?php echo htmlspecialchars($msg); ?></div>
<?php endforeach; ?>
<div class="done">
  ✅ Done! <a href="login.php">Go to Login →</a><br>
  <small style="font-weight:normal;">⚠️ Delete this file after use: <code>fix_database.php</code></small>
</div>
</body>
</html>
