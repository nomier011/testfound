<?php
require_once 'config.php';
$page_title = 'Sign In';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = null;

// Rate limiting
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rate_key = 'login_attempts_' . md5($ip);

if (!isset($_SESSION[$rate_key])) {
    $_SESSION[$rate_key] = ['count' => 0, 'first_attempt' => time()];
}

$rate = &$_SESSION[$rate_key];
if (time() - $rate['first_attempt'] > 900) {
    $rate = ['count' => 0, 'first_attempt' => time()];
}
$rate_limited = $rate['count'] >= 5;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request. Please try again.";
    } elseif ($rate_limited) {
        $error = "Too many login attempts. Please wait 15 minutes.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = "Please enter your username and password.";
        } else {
            $conn = getConnection();
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Reset rate limit on success
                unset($_SESSION[$rate_key]);

                // Check account status
                if ($user['status'] == 'pending') {
                    $error = "Your account is pending admin approval. You'll receive a notification once approved.";
                } elseif ($user['status'] == 'rejected') {
                    $error = "Your account has been rejected. Reason: " . htmlspecialchars($user['rejection_reason'] ?? 'Contact admin.');
                } else {
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_name'] = $user['full_name'];
                    
                    // Update last login
                    $stmt = $conn->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    // Log activity
                    logActivity($user['id'], 'login', 'User logged in successfully');
                    
                    setFlash("Welcome back, " . htmlspecialchars($user['full_name']) . "!", 'success');
                    
                    // Redirect based on role
                    switch ($user['role']) {
                        case 'admin': redirect('admin_dashboard.php'); break;
                        case 'tutor': redirect('tutor_dashboard.php'); break;
                        default: redirect('student_dashboard.php');
                    }
                }
            } else {
                $rate['count']++;
                $error = "Invalid username or password.";
                logActivity(0, 'login_failed', "Failed login attempt for username: $username from IP: $ip");
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
    <title><?php echo SITE_NAME; ?> - Sign In</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { background: #111; }
        .auth-card {
            background: rgba(255,255,255,0.97);
            border-radius: 16px;
            padding: 36px;
            box-shadow: 0 32px 64px rgba(0,0,0,0.3);
        }
        .auth-logo { text-align:center; margin-bottom:24px; }
        .auth-logo img { width:300px; height:90px; object-fit:contain; margin-bottom:12px; }
        .auth-logo h1 { font-size:1.4rem; font-weight:800; color:#111; margin-bottom:4px; }
        .auth-logo p { color:var(--gray-500); font-size:0.82rem; }
        .input-group { margin-bottom:18px; }
        .input-group label { display:block; font-size:0.8rem; font-weight:600; margin-bottom:6px; color:var(--gray-700); }
        .input-wrapper { position:relative; display:flex; align-items:center; }
        .input-wrapper i.fa-user, .input-wrapper i.fa-lock { position:absolute; left:13px; color:var(--gray-400); font-size:0.85rem; pointer-events:none; }
        .input-wrapper input { width:100%; padding:11px 42px; border:1.5px solid var(--gray-200); border-radius:8px; font-size:0.9rem; font-family:inherit; background:var(--gray-50); transition:all 0.18s; }
        .input-wrapper input:focus { outline:none; border-color:var(--primary-red); background:#fff; box-shadow:0 0 0 3px rgba(220,38,38,0.08); }
        .toggle-password { position:absolute; right:12px; background:none; border:none; cursor:pointer; color:var(--gray-400); font-size:0.85rem; }
        .btn-signin { width:100%; padding:13px; background:#111; color:#fff; border:2px solid #111; border-radius:6px; font-size:0.95rem; font-weight:700; cursor:pointer; letter-spacing:0.5px; transition:all 0.2s; }
        .btn-signin:hover { background:transparent; color:#111; }
        .auth-footer { text-align:center; margin-top:20px; padding-top:20px; border-top:1px solid var(--gray-200); font-size:0.85rem; color:var(--gray-500); }
        .auth-footer a { color:var(--primary-red); font-weight:600; }
        .alert { padding:11px 14px; border-radius:8px; margin-bottom:16px; display:flex; align-items:center; gap:9px; font-size:0.85rem; }
        .alert-error { background:#fef2f2; color:var(--danger); border-left:3px solid var(--danger); }
        @media(max-width:768px) {
            .hero-section > .hero-content { flex-direction:column !important; text-align:center !important; }
            .hero-title { font-size:2.5rem !important; }
            .auth-card { max-width:100% !important; }
        }
    </style>
</head>
<body>
    <!-- Full-bleed hero background -->
    <div class="bg-layer bg-layer-1" style="display:block; filter:blur(0) brightness(0.45);"></div>
    <div class="bg-layer" style="background:linear-gradient(to bottom right,rgba(139,0,0,0.55),rgba(0,30,80,0.65)); z-index:-9;"></div>

    <!-- Clean white topbar -->
    <nav class="topnav">
        <div class="topnav-brand">
            <img src="images/scc.png" alt="SCC Logo" class="topnav-logo">
            <div class="topnav-title">
                <span class="topnav-name">BSIT Tutoring</span>
                <span class="topnav-sub">St. Cecilia's College — Cebu</span>
            </div>
        </div>
        <div class="topnav-right">
            <a href="about.php"   style="padding:8px 14px;border-radius:8px;font-size:.82rem;font-weight:500;color:#374151;text-decoration:none;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='transparent'">About</a>
            <a href="contact.php" style="padding:8px 14px;border-radius:8px;font-size:.82rem;font-weight:500;color:#374151;text-decoration:none;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='transparent'">Contact</a>
            <a href="register.php" class="btn-secondary btn-sm">Create Account</a>
        </div>
    </nav>

    <!-- Hero section with login card -->
    <div class="hero-section">
        <div class="hero-content" style="max-width:1100px; display:flex; align-items:center; justify-content:space-between; gap:60px; text-align:left; padding:40px 24px;">

            <!-- Left: bold text -->
            <div style="flex:1; min-width:0;">
                <p class="hero-eyebrow">BSIT Peer Tutoring Platform</p>
                <h1 class="hero-title" style="font-size:clamp(2.5rem,6vw,5rem);">LEARN.<br>GROW.<br>SUCCEED.</h1>
                <p class="hero-subtitle" style="max-width:420px;">Connect with qualified peer tutors, book sessions, and track your academic progress — all in one place.</p>
                <a href="register.php" class="hero-cta">Get Started Free</a>
            </div>

            <!-- Right: login card -->
            <div class="auth-card" style="flex-shrink:0; width:100%; max-width:420px;">
                <div class="auth-logo">
                    <img src="images/scclogo.png" alt="SCC Logo">
                    <h1>Sign In</h1>
                    <p>Continue your learning journey</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">

                    <div class="input-group">
                        <label for="username">Username or Email</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" id="username" name="username"
                                   placeholder="Enter your username or email"
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                   required autofocus>
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password"
                                   placeholder="Enter your password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-signin">Sign In →</button>
                </form>

                <div class="auth-footer">
                    <p>Don't have an account? <a href="register.php">Create Account</a></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const p = document.getElementById('password');
            const i = document.querySelector('.toggle-password i');
            p.type = p.type === 'password' ? 'text' : 'password';
            i.classList.toggle('fa-eye'); i.classList.toggle('fa-eye-slash');
        }
    </script>
</body>
</html>