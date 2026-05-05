<?php
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) { die('Access denied.'); }
require_once 'config.php';
$conn = getConnection();

// Show all users
$users = $conn->query("SELECT id, username, email, role, status, LEFT(password,20) as pw_preview FROM users ORDER BY id")->fetchAll();

echo "<pre style='font-family:monospace;font-size:13px;padding:20px;'>";
echo "=== USERS IN DATABASE ===\n\n";
foreach ($users as $u) {
    echo "ID: {$u['id']} | Username: {$u['username']} | Email: {$u['email']} | Role: {$u['role']} | Status: {$u['status']} | PW starts: {$u['pw_preview']}\n";
}

// Test password verify
echo "\n=== PASSWORD TEST ===\n";
$test_users = [
    ['admin',    'Admin@1234'],
    ['tutor1',   'Tutor@1234'],
    ['student1', 'Student@1234'],
];
foreach ($test_users as [$uname, $pw]) {
    $stmt = $conn->prepare("SELECT password, status FROM users WHERE username = ?");
    $stmt->execute([$uname]);
    $row = $stmt->fetch();
    if ($row) {
        $ok = password_verify($pw, $row['password']) ? '✅ PASS' : '❌ FAIL';
        echo "$uname / $pw → $ok | status: {$row['status']}\n";
    } else {
        echo "$uname → ❌ USER NOT FOUND\n";
    }
}
echo "</pre>";
?>
