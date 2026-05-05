<?php
require_once 'config.php';
$page_title = 'Contact Us';

$sent = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request. Please try again.";
    } else {
        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (!$name || !$email || !$subject || !$message) {
            $error = "Please fill in all fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Log as notification to admin (id=1)
            try {
                $conn = getConnection();
                $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (1, ?, 'contact')")
                     ->execute(["Contact form from $name ($email): $subject — $message"]);
            } catch (Exception $e) { /* table may not exist yet */ }
            $sent = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contact Us — BSIT Tutoring System</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/style.css">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',sans-serif;background:#f9fafb;color:#111;}

/* ── Topbar ── */
.topbar{position:fixed;top:0;left:0;right:0;height:64px;background:rgba(255,255,255,0.97);backdrop-filter:blur(16px);border-bottom:1px solid rgba(0,0,0,0.08);display:flex;align-items:center;justify-content:space-between;padding:0 40px;z-index:100;box-shadow:0 2px 20px rgba(0,0,0,0.06);}
.topbar-brand{display:flex;align-items:center;gap:12px;text-decoration:none;}
.topbar-brand img{width:38px;height:38px;object-fit:contain;}
.brand-name{font-size:.95rem;font-weight:800;color:#111;text-transform:uppercase;letter-spacing:-.3px;display:block;}
.brand-sub{font-size:.62rem;color:#9ca3af;font-weight:500;letter-spacing:.5px;display:block;}
.topbar-links{display:flex;align-items:center;gap:4px;}
.topbar-links a{padding:8px 14px;border-radius:8px;font-size:.82rem;font-weight:500;color:#374151;text-decoration:none;transition:all .18s;}
.topbar-links a:hover{background:#f3f4f6;color:#111;}
.topbar-links a.active{color:#dc2626;background:rgba(220,38,38,.07);font-weight:600;}
.topbar-right{display:flex;gap:10px;}
.btn-nav{padding:9px 20px;border-radius:6px;font-size:.82rem;font-weight:700;text-decoration:none;transition:all .2s;letter-spacing:.3px;}
.btn-nav-outline{border:1.5px solid #e5e7eb;color:#374151;}
.btn-nav-outline:hover{border-color:#111;color:#111;}
.btn-nav-solid{background:#111;color:#fff;border:1.5px solid #111;}
.btn-nav-solid:hover{background:transparent;color:#111;}

/* ── Hero ── */
.hero{position:relative;height:380px;display:flex;align-items:center;justify-content:center;text-align:center;overflow:hidden;margin-top:64px;}
.hero-bg{position:absolute;inset:0;background:url('images/scc4.png') center/cover no-repeat;filter:brightness(.35);}
.hero-overlay{position:absolute;inset:0;background:linear-gradient(135deg,rgba(139,0,0,.55),rgba(0,30,80,.65));}
.hero-content{position:relative;z-index:2;padding:0 24px;}
.hero-eyebrow{font-size:.72rem;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:rgba(255,255,255,.6);margin-bottom:14px;}
.hero-title{font-size:clamp(2.5rem,6vw,4.5rem);font-weight:900;color:#fff;letter-spacing:-2px;line-height:.95;text-transform:uppercase;margin-bottom:18px;}
.hero-sub{font-size:1rem;color:rgba(255,255,255,.8);max-width:480px;margin:0 auto;line-height:1.7;}

/* ── Main layout ── */
.contact-wrapper{max-width:1100px;margin:0 auto;padding:80px 40px;display:grid;grid-template-columns:1fr 1.4fr;gap:60px;align-items:start;}

/* ── Info side ── */
.info-label{font-size:.72rem;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:#dc2626;margin-bottom:12px;}
.info-title{font-size:clamp(1.6rem,2.5vw,2.2rem);font-weight:900;color:#111;letter-spacing:-1px;line-height:1.1;margin-bottom:20px;}
.info-body{font-size:.9rem;color:#6b7280;line-height:1.8;margin-bottom:40px;}

.contact-items{display:flex;flex-direction:column;gap:24px;}
.contact-item{display:flex;gap:16px;align-items:flex-start;}
.ci-icon{width:48px;height:48px;background:#fef2f2;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#dc2626;flex-shrink:0;}
.ci-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#9ca3af;margin-bottom:4px;}
.ci-value{font-size:.9rem;font-weight:600;color:#111;line-height:1.5;}
.ci-value a{color:#111;text-decoration:none;}
.ci-value a:hover{color:#dc2626;}

.social-row{display:flex;gap:12px;margin-top:36px;}
.social-btn{width:42px;height:42px;border-radius:10px;border:1.5px solid #e5e7eb;display:flex;align-items:center;justify-content:center;color:#374151;text-decoration:none;font-size:.95rem;transition:all .18s;}
.social-btn:hover{border-color:#dc2626;color:#dc2626;background:#fef2f2;}

/* ── Form side ── */
.form-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:36px;box-shadow:0 4px 24px rgba(0,0,0,.06);}
.form-card h3{font-size:1.1rem;font-weight:800;color:#111;margin-bottom:6px;}
.form-card .form-sub{font-size:.82rem;color:#9ca3af;margin-bottom:28px;}

.fg{margin-bottom:18px;}
.fg label{display:block;font-size:.78rem;font-weight:600;color:#374151;margin-bottom:6px;}
.fg input,.fg select,.fg textarea{width:100%;padding:11px 14px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:.875rem;font-family:inherit;background:#f9fafb;color:#111;transition:all .18s;resize:vertical;}
.fg input:focus,.fg select:focus,.fg textarea:focus{outline:none;border-color:#dc2626;background:#fff;box-shadow:0 0 0 3px rgba(220,38,38,.08);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}

.btn-submit{width:100%;padding:13px;background:#111;color:#fff;border:2px solid #111;border-radius:6px;font-size:.9rem;font-weight:700;cursor:pointer;letter-spacing:.5px;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:4px;}
.btn-submit:hover{background:transparent;color:#111;}

.alert{padding:12px 16px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-size:.85rem;}
.alert-error{background:#fef2f2;color:#dc2626;border-left:3px solid #dc2626;}
.alert-success{background:#ecfdf5;color:#059669;border-left:3px solid #10b981;}

/* ── Map strip ── */
.map-strip{background:#111;padding:60px 40px;text-align:center;}
.map-strip h2{font-size:1.6rem;font-weight:900;color:#fff;letter-spacing:-1px;text-transform:uppercase;margin-bottom:8px;}
.map-strip p{color:#9ca3af;font-size:.875rem;margin-bottom:32px;}
.map-frame{max-width:900px;margin:0 auto;border-radius:12px;overflow:hidden;border:3px solid rgba(255,255,255,.1);}
.map-frame iframe{width:100%;height:380px;border:none;display:block;}

/* ── Footer ── */
footer{background:#0a0a0a;padding:32px 40px;text-align:center;}
footer p{font-size:.78rem;color:#4b5563;}
footer a{color:#dc2626;text-decoration:none;}

@media(max-width:860px){
    .contact-wrapper{grid-template-columns:1fr;gap:40px;padding:60px 20px;}
    .topbar{padding:0 16px;}
    .topbar-links{display:none;}
    .map-strip{padding:48px 20px;}
}
@media(max-width:520px){
    .form-row{grid-template-columns:1fr;}
    .form-card{padding:24px 18px;}
}
</style>
</head>
<body>

<!-- Topbar -->
<nav class="topbar">
    <a href="index.php" class="topbar-brand">
        <img src="images/scc.png" alt="SCC Logo" style="width:80px;height:44px;object-fit:contain;">
        <div>
            <span class="brand-name">BSIT Tutoring</span>
            <span class="brand-sub">St. Cecilia's College — Cebu</span>
        </div>
    </a>
    <div class="topbar-links">
        <?php if (isLoggedIn()): ?>
            <a href="dashboard.php">Dashboard</a>
        <?php endif; ?>
    </div>
    <div class="topbar-right">
        <a href="about.php" class="btn-nav btn-nav-outline" style="padding:8px 16px;">About</a>
        <a href="contact.php" class="btn-nav btn-nav-outline active-link" style="padding:8px 16px;">Contact</a>
        <?php if (isLoggedIn()): ?>
            <a href="dashboard.php" class="btn-nav btn-nav-solid">Go to Dashboard</a>
        <?php else: ?>
            <a href="login.php" class="btn-nav btn-nav-outline">Sign In</a>
            <a href="register.php" class="btn-nav btn-nav-solid">Get Started</a>
        <?php endif; ?>
    </div>
</nav>

<!-- Hero -->
<div class="hero">
    <div class="hero-bg"></div>
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <p class="hero-eyebrow">Get In Touch</p>
        <h1 class="hero-title">CONTACT<br>US</h1>
        <p class="hero-sub">Have questions? We're here to help. Reach out to us anytime.</p>
    </div>
</div>

<!-- Contact Section -->
<div class="contact-wrapper">

    <!-- Left: Info -->
    <div>
        <p class="info-label">Contact Information</p>
        <h2 class="info-title">We'd love to<br>hear from you.</h2>
        <p class="info-body">Whether you have a question about features, need help with your account, or want to become a tutor — our team is ready to answer all your questions.</p>

        <div class="contact-items">
            <div class="contact-item">
                <div class="ci-icon"><i class="fas fa-map-marker-alt"></i></div>
                <div>
                    <div class="ci-label">Address</div>
                    <div class="ci-value">Cebu South National Highway, Ward II<br>Minglanilla, Cebu, Philippines</div>
                </div>
            </div>
            <div class="contact-item">
                <div class="ci-icon"><i class="fas fa-envelope"></i></div>
                <div>
                    <div class="ci-label">Email</div>
                    <div class="ci-value">
                        <a href="mailto:support@bsit-tutoring.edu.ph">support@bsit-tutoring.edu.ph</a><br>
                        <a href="mailto:admin@bsit-tutoring.edu.ph">admin@bsit-tutoring.edu.ph</a>
                    </div>
                </div>
            </div>
            <div class="contact-item">
                <div class="ci-icon"><i class="fas fa-phone"></i></div>
                <div>
                    <div class="ci-label">Phone</div>
                    <div class="ci-value">
                        <a href="tel:+63321234567">(032) 123-4567</a><br>
                        <a href="tel:+639171234567">+63 917 123 4567</a>
                    </div>
                </div>
            </div>
            <div class="contact-item">
                <div class="ci-icon"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="ci-label">Office Hours</div>
                    <div class="ci-value">Monday – Friday: 8:00 AM – 5:00 PM<br>Saturday: 8:00 AM – 12:00 PM</div>
                </div>
            </div>
        </div>

        <div class="social-row">
            <a href="#" class="social-btn" title="Facebook"><i class="fab fa-facebook-f"></i></a>
            <a href="#" class="social-btn" title="Instagram"><i class="fab fa-instagram"></i></a>
            <a href="#" class="social-btn" title="Twitter/X"><i class="fab fa-x-twitter"></i></a>
            <a href="#" class="social-btn" title="YouTube"><i class="fab fa-youtube"></i></a>
        </div>
    </div>

    <!-- Right: Form -->
    <div class="form-card">
        <h3>Send us a Message</h3>
        <p class="form-sub">We'll get back to you within 24 hours.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($sent): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Message sent! We'll get back to you soon.
            </div>
        <?php else: ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">

            <div class="form-row">
                <div class="fg">
                    <label>Full Name *</label>
                    <input type="text" name="name" placeholder="Your full name"
                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                </div>
                <div class="fg">
                    <label>Email Address *</label>
                    <input type="email" name="email" placeholder="your@email.com"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="fg">
                <label>Subject *</label>
                <select name="subject" required>
                    <option value="">Select a topic...</option>
                    <option value="Account Issue"       <?php echo ($_POST['subject']??'')==='Account Issue'?'selected':''; ?>>Account Issue</option>
                    <option value="Booking Help"        <?php echo ($_POST['subject']??'')==='Booking Help'?'selected':''; ?>>Booking Help</option>
                    <option value="Payment Concern"     <?php echo ($_POST['subject']??'')==='Payment Concern'?'selected':''; ?>>Payment Concern</option>
                    <option value="Become a Tutor"      <?php echo ($_POST['subject']??'')==='Become a Tutor'?'selected':''; ?>>Become a Tutor</option>
                    <option value="Technical Problem"   <?php echo ($_POST['subject']??'')==='Technical Problem'?'selected':''; ?>>Technical Problem</option>
                    <option value="General Inquiry"     <?php echo ($_POST['subject']??'')==='General Inquiry'?'selected':''; ?>>General Inquiry</option>
                    <option value="Other"               <?php echo ($_POST['subject']??'')==='Other'?'selected':''; ?>>Other</option>
                </select>
            </div>

            <div class="fg">
                <label>Message *</label>
                <textarea name="message" rows="6" placeholder="Write your message here..."
                          required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-paper-plane"></i> Send Message
            </button>
        </form>

        <?php endif; ?>
    </div>

</div>

<!-- Map -->
<div class="map-strip">
    <h2>Find Us</h2>
    <p>St. Cecilia's College — Cebu, Inc. · Minglanilla, Cebu</p>
    <div class="map-frame">
        <iframe
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3926.0!2d123.9!3d10.23!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33a999b1b1b1b1b1%3A0x1!2sSt.+Cecilia's+College+Cebu!5e0!3m2!1sen!2sph!4v1"
            allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade">
        </iframe>
    </div>
</div>

<!-- Footer -->
<footer>
    <p>© <?php echo date('Y'); ?> BSIT Tutoring System — St. Cecilia's College Cebu, Inc. &nbsp;|&nbsp;
       <a href="about.php">About</a> &nbsp;·&nbsp;
       <a href="contact.php">Contact</a> &nbsp;·&nbsp;
       <a href="login.php">Sign In</a>
    </p>
</footer>

</body>
</html>
