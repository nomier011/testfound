<?php
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    http_response_code(403); die('Access denied.');
}
require_once 'config.php';
$conn = getConnection();
$results = [];

// ── Ensure subjects exist first ───────────────────────────────────────────────
$conn->exec("INSERT IGNORE INTO subjects (name, description) VALUES
    ('IM207 - Web Systems',              'Web development with HTML, CSS, JavaScript'),
    ('PF205 - Programming Fundamentals', 'Programming basics with Python and Java'),
    ('NET208 - Networking',              'Computer networks and protocols'),
    ('IPT209 - Integrative Programming', 'Advanced programming and system integration')");

// ── Ensure school_records exist ───────────────────────────────────────────────
$conn->exec("INSERT IGNORE INTO school_records (school_id, full_name, role, year_level, is_registered) VALUES
    ('2021-T001', 'Maria Santos',  'tutor',   4, 1),
    ('2024-S001', 'Juan Dela Cruz','student', 2, 1)");

// ── Helper: upsert user ───────────────────────────────────────────────────────
function upsertUser($conn, $data) {
    // Remove existing user with same username OR email
    $conn->prepare("DELETE FROM users WHERE username = ? OR email = ?")
         ->execute([$data['username'], $data['email']]);

    $hash = password_hash($data['password'], PASSWORD_BCRYPT);
    $conn->prepare("
        INSERT INTO users
            (school_id, username, password, email, role, full_name,
             year_level, hourly_rate, expertise, status, is_available)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', 1)
    ")->execute([
        $data['school_id']   ?? null,
        $data['username'],
        $hash,
        $data['email'],
        $data['role'],
        $data['full_name'],
        $data['year_level']  ?? null,
        $data['hourly_rate'] ?? null,
        $data['expertise']   ?? null,
    ]);
    return $conn->lastInsertId();
}

// ── 1. ADMIN ──────────────────────────────────────────────────────────────────
try {
    upsertUser($conn, [
        'username'  => 'admin',
        'password'  => 'Admin@1234',
        'email'     => 'admin@bsit.edu',
        'role'      => 'admin',
        'full_name' => 'System Administrator',
    ]);
    $results[] = ['ok', 'Admin', 'admin', 'Admin@1234'];
} catch (Exception $e) {
    $results[] = ['err', 'Admin', $e->getMessage()];
}

// ── 2. TUTOR ──────────────────────────────────────────────────────────────────
try {
    $tutor_id = upsertUser($conn, [
        'school_id'   => '2021-T001',
        'username'    => 'tutor1',
        'password'    => 'Tutor@1234',
        'email'       => 'tutor1@bsit.edu',
        'role'        => 'tutor',
        'full_name'   => 'Maria Santos',
        'hourly_rate' => 500,
        'expertise'   => 'Web Systems, Programming, Networking',
    ]);

    // Assign all subjects
    $conn->prepare("DELETE FROM tutor_subjects WHERE tutor_id = ?")->execute([$tutor_id]);
    $subjects = $conn->query("SELECT id FROM subjects")->fetchAll(PDO::FETCH_COLUMN);
    $stmt = $conn->prepare("INSERT IGNORE INTO tutor_subjects (tutor_id, subject_id) VALUES (?,?)");
    foreach ($subjects as $sid) $stmt->execute([$tutor_id, $sid]);

    $results[] = ['ok', 'Tutor', 'tutor1', 'Tutor@1234'];
} catch (Exception $e) {
    $results[] = ['err', 'Tutor', $e->getMessage()];
}

// ── 3. STUDENT ────────────────────────────────────────────────────────────────
try {
    upsertUser($conn, [
        'school_id'  => '2024-S001',
        'username'   => 'student1',
        'password'   => 'Student@1234',
        'email'      => 'student1@bsit.edu',
        'role'       => 'student',
        'full_name'  => 'Juan Dela Cruz',
        'year_level' => 2,
    ]);
    $results[] = ['ok', 'Student', 'student1', 'Student@1234'];
} catch (Exception $e) {
    $results[] = ['err', 'Student', $e->getMessage()];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Create Accounts</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:system-ui,sans-serif;background:#f3f4f6;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;}
  .card{background:#fff;border-radius:16px;padding:36px;max-width:520px;width:100%;box-shadow:0 10px 40px rgba(0,0,0,.1);}
  h1{font-size:1.3rem;font-weight:800;color:#111;margin-bottom:6px;}
  .sub{font-size:.82rem;color:#9ca3af;margin-bottom:24px;}
  table{width:100%;border-collapse:collapse;margin-bottom:20px;}
  th{background:#111;color:#fff;padding:10px 14px;font-size:.75rem;text-align:left;font-weight:700;letter-spacing:.5px;text-transform:uppercase;}
  th:first-child{border-radius:8px 0 0 0;}th:last-child{border-radius:0 8px 0 0;}
  td{padding:12px 14px;font-size:.875rem;border-bottom:1px solid #f3f4f6;color:#374151;}
  tr:last-child td{border-bottom:none;}
  .ok td:first-child{border-left:3px solid #10b981;}
  .err td{color:#dc2626;font-size:.8rem;}
  .err td:first-child{border-left:3px solid #dc2626;}
  .badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;}
  .badge-admin{background:#fee2e2;color:#991b1b;}
  .badge-tutor{background:#ede9fe;color:#5b21b6;}
  .badge-student{background:#dbeafe;color:#1e40af;}
  .pw{font-family:monospace;background:#f9fafb;padding:3px 8px;border-radius:4px;border:1px solid #e5e7eb;}
  .warn{background:#fffbeb;border:1px solid #fcd34d;border-radius:10px;padding:14px 16px;font-size:.82rem;color:#92400e;margin-bottom:20px;}
  .btn{display:block;width:100%;padding:13px;background:#111;color:#fff;border:2px solid #111;border-radius:6px;font-size:.9rem;font-weight:700;text-align:center;text-decoration:none;transition:all .2s;}
  .btn:hover{background:transparent;color:#111;}
</style>
</head>
<body>
<div class="card">
    <h1>✅ Demo Accounts Ready</h1>
    <p class="sub">Use these credentials to log in and test the system</p>

    <table>
        <thead><tr><th>Role</th><th>Username</th><th>Password</th></tr></thead>
        <tbody>
        <?php foreach ($results as $r): ?>
            <?php if ($r[0] === 'err'): ?>
            <tr class="err">
                <td><?php echo $r[1]; ?></td>
                <td colspan="2">❌ <?php echo htmlspecialchars($r[2]); ?></td>
            </tr>
            <?php else: ?>
            <tr class="ok">
                <td><span class="badge badge-<?php echo strtolower($r[1]); ?>"><?php echo $r[1]; ?></span></td>
                <td><strong><?php echo $r[2]; ?></strong></td>
                <td><span class="pw"><?php echo $r[3]; ?></span></td>
            </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="warn">⚠️ <strong>Delete this file after use:</strong> <code>create_accounts.php</code></div>
    <a href="login.php" class="btn">Go to Login →</a>
</div>
</body>
</html>
