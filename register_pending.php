<?php
require_once 'config.php';

// If already logged in, go to dashboard
if (isLoggedIn()) redirect('dashboard.php');

// Grab registration info from session (set right after INSERT in register.php)
$school_id = $_SESSION['registered_school_id'] ?? null;
$full_name  = $_SESSION['registered_name'] ?? null;

// Clear them so refreshing doesn't re-show stale data
unset($_SESSION['registered_school_id'], $_SESSION['registered_name']);

// If someone lands here directly with no session data, redirect to register
if (!$school_id) redirect('register.php');

$page_title = 'Registration Submitted';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo SITE_NAME; ?> — Registration Submitted</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; font-family: 'Inter', sans-serif; background: #111; }

.bg-img {
    position: fixed;
    inset: 0;
    background: url('images/scc4.png') center/cover no-repeat;
    filter: brightness(0.35);
    z-index: 0;
}

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
.brand img { width:80px; height:44px; object-fit:contain; }
.brand-name { font-size:.95rem; font-weight:800; color:#111; text-transform:uppercase; letter-spacing:-.3px; display:block; }
.brand-sub  { font-size:.62rem; color:#9ca3af; font-weight:500; letter-spacing:.5px; display:block; }

.page {
    position: fixed;
    top: 64px; left: 0; right: 0; bottom: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.card {
    background: #fff;
    border-radius: 20px;
    padding: 44px 40px;
    max-width: 520px;
    width: 100%;
    text-align: center;
    box-shadow: 0 24px 60px rgba(0,0,0,0.4);
}

.icon-wrap {
    width: 80px; height: 80px;
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 20px;
    font-size: 2.2rem; color: white;
}

h1 { font-size: 1.6rem; font-weight: 900; color: #111; margin-bottom: 8px; letter-spacing: -.5px; }
.subtitle { font-size: .9rem; color: #6b7280; margin-bottom: 28px; line-height: 1.6; }

.id-box {
    background: #f0fdf4;
    border: 2px dashed #10b981;
    border-radius: 12px;
    padding: 18px 24px;
    margin-bottom: 24px;
}
.id-box .label { font-size: .72rem; font-weight: 700; color: #059669; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
.id-box .id-value {
    font-size: 1.5rem; font-weight: 900; color: #111;
    font-family: monospace; letter-spacing: 2px;
}
.id-box .id-note { font-size: .72rem; color: #6b7280; margin-top: 6px; }

.steps {
    text-align: left;
    background: #f9fafb;
    border-radius: 12px;
    padding: 18px 20px;
    margin-bottom: 24px;
}
.steps h4 { font-size: .8rem; font-weight: 700; color: #374151; margin-bottom: 12px; text-transform: uppercase; letter-spacing: .5px; }
.step-item {
    display: flex; align-items: flex-start; gap: 12px;
    margin-bottom: 10px; font-size: .82rem; color: #4b5563;
}
.step-item:last-child { margin-bottom: 0; }
.step-num {
    width: 22px; height: 22px; flex-shrink: 0;
    background: #dc2626; color: #fff;
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: .68rem; font-weight: 700;
}

.btn-login {
    display: inline-flex; align-items: center; gap: 8px;
    width: 100%; padding: 13px;
    background: #111; color: #fff;
    border: 2px solid #111; border-radius: 8px;
    font-size: .9rem; font-weight: 700;
    text-decoration: none; justify-content: center;
    transition: all .2s;
}
.btn-login:hover { background: transparent; color: #111; }

@media (max-width: 520px) {
    .card { padding: 28px 20px; }
    .topbar { padding: 0 16px; }
}
</style>
</head>
<body>

<div class="bg-img"></div>

<nav class="topbar">
    <a href="login.php" class="brand">
        <img src="images/scc.png" alt="SCC Logo">
        <div>
            <span class="brand-name">BSIT Tutoring</span>
            <span class="brand-sub">St. Cecilia's College — Cebu</span>
        </div>
    </a>
</nav>

<div class="page">
    <div class="card">

        <div class="icon-wrap">
            <i class="fas fa-check"></i>
        </div>

        <h1>Registration Submitted!</h1>
        <p class="subtitle">
            <?php if ($full_name): ?>
                Hi <strong><?php echo htmlspecialchars(explode(' ', $full_name)[0]); ?></strong>, your account has been created and is now waiting for admin approval.
            <?php else: ?>
                Your account has been created and is now waiting for admin approval.
            <?php endif; ?>
        </p>

        <?php if ($school_id): ?>
        <div class="id-box">
            <div class="label"><i class="fas fa-id-card"></i> Your School ID</div>
            <div class="id-value"><?php echo htmlspecialchars($school_id); ?></div>
            <div class="id-note">Save this — you'll need it to log in</div>
        </div>
        <?php endif; ?>

        <div class="steps">
            <h4>What happens next?</h4>
            <div class="step-item">
                <div class="step-num">1</div>
                <span>An admin will review and approve your account (usually within 24 hours)</span>
            </div>
            <div class="step-item">
                <div class="step-num">2</div>
                <span>You'll receive a notification once your account is approved</span>
            </div>
            <div class="step-item">
                <div class="step-num">3</div>
                <span>Log in with your username and password to start booking sessions</span>
            </div>
        </div>

        <a href="login.php" class="btn-login">
            <i class="fas fa-sign-in-alt"></i> Go to Login
        </a>

    </div>
</div>

</body>
</html>
