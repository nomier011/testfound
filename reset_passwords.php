<?php
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) die('Access denied.');
require_once 'config.php';
$conn = getConnection();

// Generate real hashes
$accounts = [
    ['admin',    'Admin@1234',    'admin',   'System Administrator', 'admin@bsit.edu'],
    ['tutor1',   'Tutor@1234',    'tutor',   'Maria Santos',         'tutor1@bsit.edu'],
    ['student1', 'Student@1234',  'student', 'Juan Dela Cruz',       'student1@bsit.edu'],
];

echo "<pre style='font-family:monospace;padding:20px;font-size:13px;'>";

foreach ($accounts as [$username, $password, $role, $name, $email]) {
    $hash = password_hash($password, PASSWORD_BCRYPT);

    // Delete any existing user with this username or email
    $conn->prepare("DELETE FROM users WHERE username = ? OR email = ?")->execute([$username, $email]);

    // Insert fresh
    $conn->prepare("INSERT INTO users (username, password, email, role, full_name, status, is_available)
                    VALUES (?, ?, ?, ?, ?, 'approved', 1)")
         ->execute([$username, $hash, $email, $role, $name]);

    // Verify immediately
    $stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    $ok = password_verify($password, $row['password']);

    echo ($ok ? "✅" : "❌") . " $role | $username / $password | verify: " . ($ok ? "PASS" : "FAIL") . "\n";
}

// Assign subjects to tutor
$tutor = $conn->prepare("SELECT id FROM users WHERE username = 'tutor1'")->execute() ? 
         $conn->query("SELECT id FROM users WHERE username = 'tutor1'")->fetch() : null;
if ($tutor) {
    $conn->prepare("DELETE FROM tutor_subjects WHERE tutor_id = ?")->execute([$tutor['id']]);
    $subjects = $conn->query("SELECT id FROM subjects")->fetchAll(PDO::FETCH_COLUMN);
    $stmt = $conn->prepare("INSERT IGNORE INTO tutor_subjects (tutor_id, subject_id) VALUES (?,?)");
    foreach ($subjects as $sid) $stmt->execute([$tutor['id'], $sid]);
    echo "✅ Subjects assigned to tutor1\n";
}

echo "\nDone! <a href='login.php'>Go to Login</a> — delete this file after.";
echo "</pre>";
?>
