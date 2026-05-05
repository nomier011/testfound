<?php
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) die('Access denied.');
require_once 'config.php';
$conn = getConnection();

echo "<pre style='font-family:monospace;font-size:13px;padding:20px;background:#f5f5f5;'>";

// 1. Show all users
echo "=== ALL USERS ===\n";
$users = $conn->query("SELECT id, username, email, role, status, password FROM users")->fetchAll();
if (!$users) { echo "NO USERS FOUND IN DATABASE!\n"; }
foreach ($users as $u) {
    echo "ID:{$u['id']} | user:{$u['username']} | role:{$u['role']} | status:{$u['status']} | pw_hash:{$u['password']}\n";
}

// 2. Test exact query login.php uses
echo "\n=== LOGIN QUERY TEST ===\n";
$test = 'admin';
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
$stmt->execute([$test, $test]);
$row = $stmt->fetch();
echo "Query for '$test': " . ($row ? "FOUND (id={$row['id']}, status={$row['status']})" : "NOT FOUND") . "\n";

// 3. Test password_verify
$passwords = ['Admin@1234','admin123','Admin1234','admin'];
foreach ($passwords as $pw) {
    if ($row) {
        $ok = password_verify($pw, $row['password']);
        echo "password_verify('$pw') = " . ($ok ? "✅ TRUE" : "false") . "\n";
    }
}

// 4. Check session config
echo "\n=== SESSION CONFIG ===\n";
echo "samesite: " . ini_get('session.cookie_samesite') . "\n";
echo "httponly: " . ini_get('session.cookie_httponly') . "\n";
echo "session_id: " . session_id() . "\n";

// 5. Check CSRF
echo "\n=== CSRF TOKEN ===\n";
echo "csrf in session: " . ($_SESSION['csrf_token'] ?? 'NOT SET') . "\n";

echo "</pre>";
?>
