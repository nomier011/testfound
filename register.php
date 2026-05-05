<?php
require_once 'config.php';
$page_title = 'Create Account';

if (isLoggedIn()) redirect('dashboard.php');

$error = null;
$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request. Please try again.";
    } else {
        $full_name        = sanitizeString($_POST['full_name'] ?? '');
        $username         = sanitizeString($_POST['username'] ?? '');
        $email            = sanitizeEmail($_POST['email'] ?? '');
        $password         = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $year_level       = sanitizeInt($_POST['year_level'] ?? 1);

        if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
            $error = "Please fill in all required fields.";
        } elseif (!validateEmail($email)) {
            $error = "Please enter a valid email address.";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $error = "Password must contain at least one uppercase letter.";
        } elseif (!preg_match('/[0-9]/', $password)) {
            $error = "Password must contain at least one number.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            // Check username/email uniqueness
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = "Username or email already exists.";
            } else {
                // Auto-generate unique School ID: BSIT-YYYY-XXXXX
                do {
                    $school_id = 'BSIT-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
                    $stmt = $conn->prepare("SELECT id FROM users WHERE school_id = ?");
                    $stmt->execute([$school_id]);
                } while ($stmt->fetch()); // retry if collision

                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt   = $conn->prepare("INSERT INTO users (school_id, username, password, email, role, full_name, year_level, status) VALUES (?,?,?,?,?,?,?,'pending')");
                $stmt->execute([$school_id, $username, $hashed, $email, 'student', $full_name, $year_level]);
                $new_user_id = $conn->lastInsertId();

                // Also add to school_records for reference
                try {
                    $conn->prepare("INSERT INTO school_records (school_id, full_name, role, year_level, is_registered) VALUES (?,?,'student',?,1)")
                         ->execute([$school_id, $full_name, $year_level]);
                } catch (Exception $e) { /* ignore duplicate */ }

                // Notify all admins
                $admins = $conn->query("SELECT id FROM users WHERE role = 'admin' AND status = 'approved'")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($admins as $admin_id) {
                    sendNotification($admin_id, "New student registration pending approval: {$full_name} (ID: {$school_id})", 'registration');
                }

                // Store school_id in session so the pending page can show it
                $_SESSION['registered_school_id'] = $school_id;
                $_SESSION['registered_name']       = $full_name;

                redirect('register_pending.php');
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
<title><?php echo SITE_NAME; ?> — Create Account</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ── Reset ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; overflow: hidden; font-family: 'Inter', sans-serif; background: #111; }

/* ── Full-screen background image ── */
.bg-img {
    position: fixed;
    inset: 0;
    background: url('images/scc4.png') center/cover no-repeat;
    filter: brightness(0.35);
    z-index: 0;
}

/* ── Topbar ── */
.topbar {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: 64px;
    background: rgba(255,255,255,0.97);
    backdrop-filter: blur(16px);
    border-bottom: 1px solid rgba(0,0,0,0.08);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 32px;
    z-index: 200;
    box-shadow: 0 2px 20px rgba(0,0,0,0.06);
}
.brand { display:flex; align-items:center; gap:12px; text-decoration:none; }
.brand img { width:38px; height:38px; object-fit:contain; }
.brand-name { font-size:.95rem; font-weight:800; color:#111; text-transform:uppercase; letter-spacing:-.3px; display:block; }
.brand-sub  { font-size:.62rem; color:#9ca3af; font-weight:500; letter-spacing:.5px; display:block; }
.topbar-right { display:flex; align-items:center; gap:8px; }
.topbar-right a {
    font-size:.82rem; font-weight:600; color:#374151; text-decoration:none;
    padding:8px 16px; border-radius:6px; border:1.5px solid #e5e7eb; transition:all .18s;
}
.topbar-right a:hover { border-color:#111; color:#111; }

/* ── Page wrapper — fills viewport below topbar ── */
.page {
    position: fixed;
    top: 64px; left: 0; right: 0; bottom: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

/* ── Inner row — this is the "content area" ── */
.inner {
    position: relative;          /* overlay is relative to this */
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 48px;
    max-width: 1100px;
    width: 100%;
    padding: 0 32px;
}

/* ── Overlay covers ONLY the inner row ── */
.inner-overlay {
    position: absolute;
    inset: -40px -32px;          /* bleed a bit beyond padding */
    background: linear-gradient(135deg, rgba(139,0,0,0.50) 0%, rgba(0,30,80,0.60) 100%);
    border-radius: 24px;
    z-index: 0;
}

/* ── Hero text (left) ── */
.hero { position:relative; z-index:1; flex:1; min-width:0; }
.eyebrow { font-size:.7rem; font-weight:700; letter-spacing:3px; text-transform:uppercase; color:rgba(255,255,255,.6); margin-bottom:12px; }
.hero-title { font-size:clamp(2rem,4.5vw,3.8rem); font-weight:900; color:#fff; letter-spacing:-2px; line-height:.95; text-transform:uppercase; margin-bottom:18px; }
.hero-sub { font-size:.9rem; color:rgba(255,255,255,.75); line-height:1.7; max-width:360px; margin-bottom:28px; }
.features { display:flex; flex-direction:column; gap:12px; }
.feature { display:flex; align-items:center; gap:12px; color:rgba(255,255,255,.85); font-size:.82rem; }
.feature-icon { width:34px; height:34px; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.2); border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:.85rem; flex-shrink:0; }

/* ── Register card (right) ── */
.card {
    position: relative; z-index: 1;
    flex-shrink: 0;
    width: 100%; max-width: 440px;
    background: #fff;
    border-radius: 20px;
    padding: 32px 36px;
    box-shadow: 0 24px 60px rgba(0,0,0,0.4);
    overflow-y: auto;
    max-height: calc(100vh - 64px - 40px);
    scrollbar-width: none;
}
.card::-webkit-scrollbar { display: none; }
.card-head { margin-bottom:20px; padding-bottom:14px; border-bottom:1px solid #f3f4f6; }
.card-head h2 { font-size:1.3rem; font-weight:900; color:#111; margin-bottom:3px; letter-spacing:-.5px; }
.card-head p  { font-size:.78rem; color:#9ca3af; }

/* ── Form ── */
.fg { margin-bottom:10px; }
.fg label { display:block; font-size:.72rem; font-weight:600; color:#374151; margin-bottom:3px; }
.req { color:#dc2626; margin-left:2px; }
.iw { position:relative; display:flex; align-items:center; }
.iw .fi { position:absolute; left:11px; color:#9ca3af; font-size:.75rem; pointer-events:none; }
.iw input, .iw select {
    width:100%; padding:9px 32px;
    border:1.5px solid #e5e7eb; border-radius:8px;
    font-size:.82rem; font-family:inherit;
    background:#f9fafb; color:#111; transition:all .18s;
}
.iw input:focus, .iw select:focus { outline:none; border-color:#dc2626; background:#fff; box-shadow:0 0 0 3px rgba(220,38,38,.08); }
.iw .tpw { position:absolute; right:10px; background:none; border:none; cursor:pointer; color:#9ca3af; font-size:.75rem; padding:0; }
.iw .tpw:hover { color:#dc2626; }
.valid-input   { border-color:#10b981!important; background:#f0fdf4!important; }
.invalid-input { border-color:#ef4444!important; background:#fef2f2!important; }
.id-status { font-size:.68rem; margin-top:3px; display:flex; align-items:center; gap:4px; }
.id-status.valid   { color:#059669; }
.id-status.invalid { color:#dc2626; }
.id-status.checking{ color:#d97706; }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.hint { font-size:.63rem; color:#9ca3af; margin-top:2px; }

/* ── Alerts ── */
.alert { padding:10px 13px; border-radius:7px; margin-bottom:14px; display:flex; align-items:flex-start; gap:8px; font-size:.8rem; line-height:1.5; }
.alert-error   { background:#fef2f2; color:#dc2626; border-left:3px solid #dc2626; }
.alert-success { background:#ecfdf5; color:#059669; border-left:3px solid #10b981; }

/* ── Submit ── */
.btn-reg { width:100%; padding:11px; background:#111; color:#fff; border:2px solid #111; border-radius:8px; font-size:.875rem; font-weight:700; cursor:pointer; letter-spacing:.4px; transition:all .2s; display:flex; align-items:center; justify-content:center; gap:7px; margin-top:8px; }
.btn-reg:hover { background:transparent; color:#111; }

/* ── Footer ── */
.card-foot { text-align:center; margin-top:14px; padding-top:12px; border-top:1px solid #f3f4f6; font-size:.78rem; color:#6b7280; }
.card-foot a { color:#dc2626; font-weight:600; text-decoration:none; }

/* ── Responsive ── */
@media(max-width:860px) {
    .hero { display:none; }
    .inner { justify-content:center; padding:0 16px; }
    .inner-overlay { display:none; }
    .card { max-width:100%; }
}
@media(max-width:520px) {
    .form-row { grid-template-columns:1fr; gap:0; }
    .topbar { padding:0 16px; }
    .card { padding:20px 16px; }
}
</style>
</head>
<body>

<!-- Background -->
<div class="bg-img"></div>

<!-- Topbar -->
<nav class="topbar">
    <a href="login.php" class="brand">
        <img src="images/scc.png" alt="SCC Logo" style="width:80px;height:44px;object-fit:contain;">
        <div>
            <span class="brand-name">BSIT Tutoring</span>
            <span class="brand-sub">St. Cecilia's College — Cebu</span>
        </div>
    </a>
    <div class="topbar-right">
        <a href="about.php">About</a>
        <a href="contact.php">Contact</a>
        <a href="login.php">Sign In</a>
    </div>
</nav>

<!-- Page -->
<div class="page">
    <div class="inner">

        <!-- Overlay — same size as inner -->
        <div class="inner-overlay"></div>

        <!-- Left: hero -->
        <div class="hero">
            <p class="eyebrow">Join the Community</p>
            <h1 class="hero-title">START<br>YOUR<br>JOURNEY.</h1>
            <p class="hero-sub">Create your account and connect with qualified peer tutors in the BSIT program.</p>
            <div class="features">
                <div class="feature"><div class="feature-icon"><i class="fas fa-calendar-check"></i></div><span>Book tutoring sessions anytime</span></div>
                <div class="feature"><div class="feature-icon"><i class="fas fa-book-open"></i></div><span>Access learning modules &amp; quizzes</span></div>
                <div class="feature"><div class="feature-icon"><i class="fas fa-chart-line"></i></div><span>Track your academic progress</span></div>
                <div class="feature"><div class="feature-icon"><i class="fas fa-code"></i></div><span>Practice coding exercises</span></div>
            </div>
        </div>

        <!-- Right: card -->
        <div class="card">
            <div class="card-head">
                <h2>Create Account</h2>
                <p>Fill in your details to get started</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle" style="flex-shrink:0;margin-top:1px;"></i><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" id="regForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">

                <div class="fg">
                    <label>Full Name <span class="req">*</span></label>
                    <div class="iw">
                        <i class="fas fa-user fi"></i>
                        <input type="text" id="full_name" name="full_name" placeholder="Enter your full name"
                               value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="fg">
                        <label>Username <span class="req">*</span></label>
                        <div class="iw">
                            <i class="fas fa-at fi"></i>
                            <input type="text" name="username" placeholder="Choose a username"
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="fg">
                        <label>Year Level</label>
                        <div class="iw">
                            <i class="fas fa-graduation-cap fi"></i>
                            <select name="year_level" id="year_level">
                                <option value="1">1st Year</option>
                                <option value="2" selected>2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="fg">
                    <label>Email Address <span class="req">*</span></label>
                    <div class="iw">
                        <i class="fas fa-envelope fi"></i>
                        <input type="email" name="email" placeholder="your@email.com"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="fg">
                        <label>Password <span class="req">*</span></label>
                        <div class="iw">
                            <i class="fas fa-lock fi"></i>
                            <input type="password" id="pw1" name="password" placeholder="Min. 8 chars" required>
                            <button type="button" class="tpw" onclick="tpw('pw1',this)"><i class="fas fa-eye"></i></button>
                        </div>
                        <div class="hint">Uppercase, lowercase &amp; number</div>
                    </div>
                    <div class="fg">
                        <label>Confirm Password <span class="req">*</span></label>
                        <div class="iw">
                            <i class="fas fa-lock fi"></i>
                            <input type="password" id="pw2" name="confirm_password" placeholder="Repeat" required>
                            <button type="button" class="tpw" onclick="tpw('pw2',this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-reg"><i class="fas fa-user-plus"></i> Create Account</button>
            </form>

            <div class="card-foot"><p>Already have an account? <a href="login.php">Sign In</a></p></div>

        </div><!-- /card -->

    </div><!-- /inner -->
</div><!-- /page -->

<script>
function tpw(id, btn) {
    const f = document.getElementById(id);
    const i = btn.querySelector('i');
    f.type = f.type === 'password' ? 'text' : 'password';
    i.classList.toggle('fa-eye');
    i.classList.toggle('fa-eye-slash');
}

document.getElementById('regForm')?.addEventListener('submit', function(e) {
    if (document.getElementById('pw1').value !== document.getElementById('pw2').value) {
        e.preventDefault(); alert('Passwords do not match!');
    }
});
</script>
</body>
</html>
