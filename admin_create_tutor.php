<?php
require_once 'config.php';
$page_title = 'Create Tutor Account';

if (!isLoggedIn()) redirect('login.php');
$admin = getUserById($_SESSION['user_id']);
if ($admin['role'] !== 'admin') redirect('dashboard.php');

$conn = getConnection();
$all_subjects = $conn->query("SELECT * FROM subjects ORDER BY name")->fetchAll();

$error = null;

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request.";
    } else {
        $tutor_id    = sanitizeInt($_POST['tutor_id'] ?? 0);
        $new_password = $_POST['new_password'] ?? '';

        if (!$tutor_id) {
            $error = "Invalid tutor.";
        } elseif (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters.";
        } else {
            // Verify it's actually a tutor
            $stmt = $conn->prepare("SELECT id, full_name FROM users WHERE id = ? AND role = 'tutor'");
            $stmt->execute([$tutor_id]);
            $tutor_row = $stmt->fetch();

            if (!$tutor_row) {
                $error = "Tutor not found.";
            } else {
                $hash = password_hash($new_password, PASSWORD_BCRYPT);
                $conn->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $tutor_id]);
                logActivity($admin['id'], 'reset_password', "Admin reset password for tutor: {$tutor_row['full_name']}");
                setFlash("Password for <strong>{$tutor_row['full_name']}</strong> has been reset successfully.", 'success');
                redirect('admin_create_tutor.php');
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request.";
    } else {
        $full_name   = trim($_POST['full_name'] ?? '');
        $username    = trim($_POST['username'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $password    = $_POST['password'] ?? '';
        $school_id   = trim($_POST['school_id'] ?? '');
        $hourly_rate = sanitizeFloat($_POST['hourly_rate'] ?? 500);
        $expertise   = trim($_POST['expertise'] ?? '');
        $subjects    = $_POST['subjects'] ?? [];

        if (!$full_name || !$username || !$email || !$password) {
            $error = "Please fill in all required fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif ($hourly_rate < 100 || $hourly_rate > 10000) {
            $error = "Hourly rate must be between ₱100 and ₱10,000.";
        } else {
            // Check duplicate username/email
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = "Username or email already exists.";
            } else {
                // Auto-generate school ID if not provided
                if (empty($school_id)) {
                    do {
                        $school_id = 'TUT-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
                        $chk = $conn->prepare("SELECT id FROM users WHERE school_id = ?");
                        $chk->execute([$school_id]);
                    } while ($chk->fetch());
                }

                try {
                    $conn->beginTransaction();

                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $conn->prepare("INSERT INTO users (school_id, username, password, email, role, full_name, hourly_rate, expertise, status, is_available) VALUES (?,?,?,?,'tutor',?,?,?,'approved',1)")
                         ->execute([$school_id, $username, $hash, $email, $full_name, $hourly_rate, $expertise]);
                    $tutor_id = $conn->lastInsertId();

                    // Assign subjects
                    if (!empty($subjects)) {
                        $stmt = $conn->prepare("INSERT IGNORE INTO tutor_subjects (tutor_id, subject_id) VALUES (?,?)");
                        foreach ($subjects as $sid) {
                            $stmt->execute([$tutor_id, sanitizeInt($sid)]);
                        }
                    }

                    // Add to school_records
                    try {
                        $conn->prepare("INSERT INTO school_records (school_id, full_name, role, year_level, is_registered) VALUES (?,?,'tutor',4,1)")
                             ->execute([$school_id, $full_name]);
                    } catch (Exception $e) {}

                    $conn->commit();
                    setFlash("Tutor account <strong>$username</strong> created successfully! School ID: <strong>$school_id</strong>", 'success');
                    redirect('admin_create_tutor.php');
                } catch (PDOException $e) {
                    $conn->rollBack();
                    error_log("Create tutor error: " . $e->getMessage());
                    $error = "Failed to create tutor account. Please try again.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Tutor — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include 'header.php'; ?>

<div class="page-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="page-hero">
        <div class="page-hero-content">
            <h1><i class="fas fa-user-plus"></i> Create Tutor Account</h1>
            <p>Add a new tutor to the system</p>
        </div>
    </div>

    <div class="page-inner" style="max-width:760px;">

        <?php $flash = getFlash(); if ($flash): ?>
        <div style="padding:14px 18px;border-radius:10px;margin-bottom:20px;background:<?php echo $flash['type']==='success'?'#ecfdf5':'#fef2f2'; ?>;color:<?php echo $flash['type']==='success'?'#059669':'#dc2626'; ?>;border-left:4px solid <?php echo $flash['type']==='success'?'#10b981':'#dc2626'; ?>;font-size:.875rem;">
            <i class="fas fa-<?php echo $flash['type']==='success'?'check-circle':'exclamation-circle'; ?>"></i>
            <?php echo $flash['message']; ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div style="padding:14px 18px;border-radius:10px;margin-bottom:20px;background:#fef2f2;color:#dc2626;border-left:4px solid #dc2626;font-size:.875rem;">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-chalkboard-teacher"></i> Tutor Details</h3>
                <a href="admin_dashboard.php" style="font-size:.82rem;color:#6b7280;text-decoration:none;">← Back to Dashboard</a>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">

                <!-- Row 1 -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div class="form-group">
                        <label>Full Name <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="full_name" placeholder="e.g. Maria Santos"
                               value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>School ID <small style="color:#9ca3af;">(auto-generated if blank)</small></label>
                        <input type="text" name="school_id" placeholder="e.g. TUT-2026-00001"
                               value="<?php echo htmlspecialchars($_POST['school_id'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Row 2 -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div class="form-group">
                        <label>Username <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="username" placeholder="e.g. tutor_maria"
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address <span style="color:#dc2626;">*</span></label>
                        <input type="email" name="email" placeholder="tutor@email.com"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                </div>

                <!-- Row 3 -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div class="form-group">
                        <label>Password <span style="color:#dc2626;">*</span></label>
                        <div style="position:relative;display:flex;align-items:center;">
                            <input type="password" id="pw" name="password" placeholder="Min. 8 characters" required style="padding-right:40px;">
                            <button type="button" onclick="togglePw()" style="position:absolute;right:12px;background:none;border:none;cursor:pointer;color:#9ca3af;font-size:.85rem;">
                                <i class="fas fa-eye" id="pwIcon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Hourly Rate (₱) <span style="color:#dc2626;">*</span></label>
                        <input type="number" name="hourly_rate" min="100" max="10000" step="50"
                               value="<?php echo htmlspecialchars($_POST['hourly_rate'] ?? '500'); ?>" required>
                    </div>
                </div>

                <!-- Expertise -->
                <div class="form-group">
                    <label>Expertise / Specialization</label>
                    <input type="text" name="expertise" placeholder="e.g. Web Development, Python, Networking"
                           value="<?php echo htmlspecialchars($_POST['expertise'] ?? ''); ?>">
                </div>

                <!-- Subjects -->
                <div class="form-group">
                    <label>Assign Subjects</label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px;margin-top:8px;">
                        <?php foreach ($all_subjects as $s): ?>
                        <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#f9fafb;border:1.5px solid #e5e7eb;border-radius:8px;cursor:pointer;font-size:.85rem;font-weight:500;transition:all .18s;"
                               onmouseover="this.style.borderColor='#dc2626'" onmouseout="this.style.borderColor='#e5e7eb'">
                            <input type="checkbox" name="subjects[]" value="<?php echo $s['id']; ?>"
                                   style="width:16px;height:16px;accent-color:#dc2626;"
                                   <?php echo in_array($s['id'], $_POST['subjects'] ?? []) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($s['name']); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="display:flex;gap:12px;margin-top:8px;">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-user-plus"></i> Create Tutor Account
                    </button>
                    <a href="admin_dashboard.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <!-- Existing Tutors -->
        <div class="content-card" style="margin-top:24px;">
            <div class="card-header">
                <h3><i class="fas fa-users"></i> Existing Tutors</h3>
                <span style="font-size:.78rem;color:#9ca3af;">
                    <?php
                    $count = $conn->query("SELECT COUNT(*) FROM users WHERE role='tutor'")->fetchColumn();
                    echo $count . ' tutor(s)';
                    ?>
                </span>
            </div>
            <?php
            $tutors = $conn->query("
                SELECT u.*, GROUP_CONCAT(s.name SEPARATOR ', ') as subjects
                FROM users u
                LEFT JOIN tutor_subjects ts ON ts.tutor_id = u.id
                LEFT JOIN subjects s ON s.id = ts.subject_id
                WHERE u.role = 'tutor'
                GROUP BY u.id
                ORDER BY u.created_at DESC
            ")->fetchAll();
            ?>
            <?php if ($tutors): ?>
            <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
                <thead>
                    <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                        <th style="padding:10px 14px;text-align:left;font-weight:700;color:#374151;">Name</th>
                        <th style="padding:10px 14px;text-align:left;font-weight:700;color:#374151;">Username</th>
                        <th style="padding:10px 14px;text-align:left;font-weight:700;color:#374151;">School ID</th>
                        <th style="padding:10px 14px;text-align:left;font-weight:700;color:#374151;">Rate</th>
                        <th style="padding:10px 14px;text-align:left;font-weight:700;color:#374151;">Subjects</th>
                        <th style="padding:10px 14px;text-align:left;font-weight:700;color:#374151;">Status</th>
                        <th style="padding:10px 14px;text-align:left;font-weight:700;color:#374151;">Available</th>
                        <th style="padding:10px 14px;text-align:left;font-weight:700;color:#374151;">Reset Password</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tutors as $t): ?>
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:12px 14px;font-weight:600;color:#111;"><?php echo htmlspecialchars($t['full_name']); ?></td>
                        <td style="padding:12px 14px;color:#6b7280;"><?php echo htmlspecialchars($t['username']); ?></td>
                        <td style="padding:12px 14px;color:#6b7280;font-family:monospace;"><?php echo htmlspecialchars($t['school_id'] ?? '—'); ?></td>
                        <td style="padding:12px 14px;color:#059669;font-weight:600;">₱<?php echo number_format($t['hourly_rate'], 0); ?>/hr</td>
                        <td style="padding:12px 14px;color:#6b7280;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($t['subjects'] ?? ''); ?>">
                            <?php echo htmlspecialchars($t['subjects'] ?? '—'); ?>
                        </td>
                        <td style="padding:12px 14px;">
                            <span style="padding:3px 10px;border-radius:20px;font-size:.68rem;font-weight:700;background:<?php echo $t['status']==='approved'?'#dcfce7':'#fef9c3'; ?>;color:<?php echo $t['status']==='approved'?'#166534':'#854d0e'; ?>;">
                                <?php echo ucfirst($t['status']); ?>
                            </span>
                        </td>
                        <td style="padding:12px 14px;">
                            <span style="display:inline-flex;align-items:center;gap:5px;font-size:.75rem;font-weight:600;color:<?php echo $t['is_available']?'#059669':'#dc2626'; ?>;">
                                <span style="width:7px;height:7px;border-radius:50%;background:<?php echo $t['is_available']?'#10b981':'#ef4444'; ?>;"></span>
                                <?php echo $t['is_available'] ? 'Yes' : 'No'; ?>
                            </span>
                        </td>
                        <td style="padding:12px 14px;">
                            <button onclick="openResetModal(<?php echo $t['id']; ?>, '<?php echo htmlspecialchars($t['full_name'], ENT_QUOTES); ?>')"
                                style="padding:5px 12px;background:#111;color:#fff;border:none;border-radius:6px;font-size:.75rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:5px;">
                                <i class="fas fa-key"></i> Reset
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php else: ?>
            <div class="no-data">No tutors yet.</div>
            <?php endif; ?>
        </div>

    </div><!-- /page-inner -->
</div><!-- /page-wrapper -->

<script>
function togglePw() {
    const f = document.getElementById('pw');
    const i = document.getElementById('pwIcon');
    f.type = f.type === 'password' ? 'text' : 'password';
    i.classList.toggle('fa-eye');
    i.classList.toggle('fa-eye-slash');
}

function openResetModal(id, name) {
    document.getElementById('resetTutorId').value   = id;
    document.getElementById('resetTutorName').textContent = name;
    document.getElementById('resetNewPw').value     = '';
    document.getElementById('resetModal').style.display = 'flex';
}
function closeResetModal() {
    document.getElementById('resetModal').style.display = 'none';
}
window.addEventListener('click', e => {
    if (e.target === document.getElementById('resetModal')) closeResetModal();
});
</script>

<!-- Reset Password Modal -->
<div id="resetModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:2000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:32px;width:90%;max-width:420px;box-shadow:0 24px 60px rgba(0,0,0,.15);position:relative;">
        <button onclick="closeResetModal()" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:1.2rem;cursor:pointer;color:#9ca3af;">✕</button>
        <h3 style="font-size:1rem;font-weight:800;color:#111;margin-bottom:6px;"><i class="fas fa-key" style="color:#dc2626;margin-right:8px;"></i>Reset Password</h3>
        <p style="font-size:.82rem;color:#6b7280;margin-bottom:20px;">Set a new password for <strong id="resetTutorName"></strong></p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
            <input type="hidden" name="tutor_id" id="resetTutorId">
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:.78rem;font-weight:600;color:#374151;margin-bottom:6px;">New Password</label>
                <div style="position:relative;display:flex;align-items:center;">
                    <input type="password" id="resetNewPw" name="new_password" placeholder="Min. 8 characters" required
                           style="width:100%;padding:10px 40px 10px 14px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:.875rem;font-family:inherit;">
                    <button type="button" onclick="const f=document.getElementById('resetNewPw');f.type=f.type==='password'?'text':'password';"
                            style="position:absolute;right:12px;background:none;border:none;cursor:pointer;color:#9ca3af;font-size:.85rem;">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <p style="font-size:.7rem;color:#9ca3af;margin-top:4px;">Min. 8 characters</p>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" name="reset_password"
                        style="flex:1;padding:11px;background:#111;color:#fff;border:2px solid #111;border-radius:7px;font-weight:700;font-size:.875rem;cursor:pointer;">
                    <i class="fas fa-save"></i> Save New Password
                </button>
                <button type="button" onclick="closeResetModal()"
                        style="padding:11px 18px;background:transparent;color:#374151;border:1.5px solid #e5e7eb;border-radius:7px;font-weight:600;font-size:.875rem;cursor:pointer;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>
