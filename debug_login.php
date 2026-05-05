<?php
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) die('Access denied.');
require_once 'config.php';
$conn = getConnection();

echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5;}table{border-collapse:collapse;width:100%;}td,th{border:1px solid #ddd;padding:8px 12px;text-align:left;}th{background:#333;color:#fff;}.ok{color:green;font-weight:bold;}.fail{color:red;font-weight:bold;}</style>";

// Show all users
echo "<h2>Users in DB</h2><table><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>PW Hash (first 20)</th></tr>";
$users = $conn->query("SELECT id, username, email, role, status, LEFT(password,20) as pw FROM users ORDER BY id")->fetchAll();
foreach ($users as $u) {
    echo "<tr><td>{$u['id']}</td><td><b>{$u['username']}</b></td><td>{$u['email']}</td><td>{$u['role']}</td><td>{$u['status']}</td><td>{$u['pw']}...</td></tr>";
}
echo "</table>";

// Test passwords
echo "<h2>Password Test</h2><table><tr><th>Username</th><th>Password Tried</th><th>User Found</th><th>PW Match</th><th>Status</th></tr>";
$tests = [
    ['admin',    'Admin@1234'],
    ['tutor1',   'Tutor@1234'],
    ['student1', 'Student@1234'],
    ['admin',    'admin123'],
    ['tutor1',   'tutor123'],
    ['student1', 'student123'],
];
foreach ($tests as [$u, $p]) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$u]);
    $row = $stmt->fetch();
    $found  = $row ? '<span class="ok">YES</span>' : '<span class="fail">NO</span>';
    $match  = ($row && password_verify($p, $row['password'])) ? '<span class="ok">✅ MATCH</span>' : '<span class="fail">❌ NO</span>';
    $status = $row ? $row['status'] : '—';
    echo "<tr><td>$u</td><td>$p</td><td>$found</td><td>$match</td><td>$status</td></tr>";
}
echo "</table>";

// Also check session cookie samesite
echo "<h2>Session Config</h2>";
echo "cookie_samesite: " . ini_get('session.cookie_samesite') . "<br>";
echo "cookie_httponly: " . ini_get('session.cookie_httponly') . "<br>";
echo "session status: " . session_status() . "<br>";
?>
