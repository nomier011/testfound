<?php
require_once 'config.php';
$page_title = 'About Us';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>About Us — BSIT Tutoring System</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/style.css">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',sans-serif;background:#f9fafb;color:#111;}

/* ── Topnav ── */
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
.hero{position:relative;height:480px;display:flex;align-items:center;justify-content:center;text-align:center;overflow:hidden;margin-top:64px;}
.hero-bg{position:absolute;inset:0;background:url('images/scc4.png') center/cover no-repeat;filter:brightness(.35);}
.hero-overlay{position:absolute;inset:0;background:linear-gradient(135deg,rgba(139,0,0,.55),rgba(0,30,80,.65));}
.hero-content{position:relative;z-index:2;padding:0 24px;}
.hero-eyebrow{font-size:.72rem;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:rgba(255,255,255,.6);margin-bottom:14px;}
.hero-title{font-size:clamp(2.5rem,6vw,4.5rem);font-weight:900;color:#fff;letter-spacing:-2px;line-height:.95;text-transform:uppercase;margin-bottom:18px;}
.hero-sub{font-size:1rem;color:rgba(255,255,255,.8);max-width:520px;margin:0 auto;line-height:1.7;}

/* ── Sections ── */
.section{padding:80px 40px;max-width:1100px;margin:0 auto;}
.section-label{font-size:.72rem;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:#dc2626;margin-bottom:12px;}
.section-title{font-size:clamp(1.8rem,3vw,2.8rem);font-weight:900;color:#111;letter-spacing:-1px;line-height:1.1;margin-bottom:20px;}
.section-body{font-size:.95rem;color:#4b5563;line-height:1.8;max-width:680px;}

/* ── Mission/Vision cards ── */
.mv-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:48px;}
.mv-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:32px;transition:all .2s;}
.mv-card:hover{box-shadow:0 8px 32px rgba(0,0,0,.08);transform:translateY(-3px);}
.mv-icon{width:52px;height:52px;background:#fef2f2;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#dc2626;margin-bottom:18px;}
.mv-card h3{font-size:1.1rem;font-weight:800;color:#111;margin-bottom:10px;text-transform:uppercase;letter-spacing:-.3px;}
.mv-card p{font-size:.875rem;color:#6b7280;line-height:1.7;}

/* ── Stats strip ── */
.stats-strip{background:#111;padding:60px 40px;}
.stats-inner{max-width:1100px;margin:0 auto;display:grid;grid-template-columns:repeat(4,1fr);gap:32px;text-align:center;}
.stat-item .num{font-size:2.5rem;font-weight:900;color:#fff;letter-spacing:-1px;}
.stat-item .lbl{font-size:.78rem;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;margin-top:4px;}

/* ── Team ── */
.team-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:24px;margin-top:48px;}
.team-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:28px 20px;text-align:center;transition:all .2s;}
.team-card:hover{box-shadow:0 8px 32px rgba(0,0,0,.08);transform:translateY(-3px);}
.team-avatar{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#dc2626,#2563eb);display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:900;color:#fff;margin:0 auto 16px;}
.team-card h4{font-size:.95rem;font-weight:700;color:#111;margin-bottom:4px;}
.team-card .role{font-size:.75rem;color:#dc2626;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;}
.team-card p{font-size:.78rem;color:#9ca3af;line-height:1.5;}

/* ── Values ── */
.values-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-top:48px;}
.value-item{display:flex;gap:16px;align-items:flex-start;}
.value-icon{width:44px;height:44px;background:#fef2f2;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#dc2626;flex-shrink:0;}
.value-item h4{font-size:.9rem;font-weight:700;color:#111;margin-bottom:4px;}
.value-item p{font-size:.8rem;color:#6b7280;line-height:1.6;}

/* ── Divider ── */
.divider{height:1px;background:#e5e7eb;max-width:1100px;margin:0 auto;}

/* ── CTA ── */
.cta-section{background:#111;padding:80px 40px;text-align:center;}
.cta-section h2{font-size:clamp(1.8rem,3vw,2.8rem);font-weight:900;color:#fff;letter-spacing:-1px;margin-bottom:16px;text-transform:uppercase;}
.cta-section p{color:#9ca3af;font-size:.95rem;margin-bottom:32px;}
.cta-btns{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;}
.cta-btn{padding:14px 32px;border-radius:4px;font-size:.9rem;font-weight:700;letter-spacing:.5px;text-decoration:none;transition:all .2s;}
.cta-btn-white{background:#fff;color:#111;border:2px solid #fff;}
.cta-btn-white:hover{background:transparent;color:#fff;}
.cta-btn-outline{background:transparent;color:#fff;border:2px solid rgba(255,255,255,.4);}
.cta-btn-outline:hover{border-color:#fff;}

/* ── Footer ── */
footer{background:#0a0a0a;padding:32px 40px;text-align:center;}
footer p{font-size:.78rem;color:#4b5563;}
footer a{color:#dc2626;text-decoration:none;}

@media(max-width:768px){
    .topbar{padding:0 16px;}
    .topbar-links{display:none;}
    .mv-grid,.values-grid{grid-template-columns:1fr;}
    .stats-inner{grid-template-columns:repeat(2,1fr);}
    .section{padding:60px 20px;}
    .stats-strip{padding:48px 20px;}
}
@media(max-width:480px){
    .stats-inner{grid-template-columns:1fr;}
    .team-grid{grid-template-columns:1fr 1fr;}
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
        <a href="about.php" class="btn-nav btn-nav-outline active-link" style="padding:8px 16px;">About</a>
        <a href="contact.php" class="btn-nav btn-nav-outline" style="padding:8px 16px;">Contact</a>
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
        <p class="hero-eyebrow">Who We Are</p>
        <h1 class="hero-title">ABOUT<br>US</h1>
        <p class="hero-sub">A student-led peer tutoring platform built to help BSIT students of St. Cecilia's College — Cebu succeed academically.</p>
    </div>
</div>

<!-- About Section -->
<div class="section">
    <p class="section-label">Our Story</p>
    <h2 class="section-title">Built by students,<br>for students.</h2>
    <p class="section-body">
        The BSIT Tutoring System was created to bridge the gap between struggling students and their more experienced peers. 
        Recognizing the challenges that come with subjects like programming, databases, networking, and system development, 
        this platform provides a space where students can seek guidance, share knowledge, and improve their skills.
    </p>
    <p class="section-body" style="margin-top:16px;">
        More than just improving academic performance, this initiative encourages collaboration, builds confidence, 
        and promotes continuous learning among BSIT students at St. Cecilia's College — Cebu, Inc.
    </p>

    <!-- Mission / Vision -->
    <div class="mv-grid">
        <div class="mv-card">
            <div class="mv-icon"><i class="fas fa-bullseye"></i></div>
            <h3>Our Mission</h3>
            <p>To provide accessible, high-quality peer tutoring that empowers BSIT students to master their coursework, develop practical IT skills, and achieve academic excellence through collaborative learning.</p>
        </div>
        <div class="mv-card">
            <div class="mv-icon"><i class="fas fa-eye"></i></div>
            <h3>Our Vision</h3>
            <p>To become the leading peer tutoring platform for IT students in Cebu — fostering a community where every student has the support they need to become a competent and future-ready IT professional.</p>
        </div>
    </div>
</div>

<!-- Stats Strip -->
<div class="stats-strip">
    <div class="stats-inner">
        <div class="stat-item">
            <div class="num">4+</div>
            <div class="lbl">Core Subjects</div>
        </div>
        <div class="stat-item">
            <div class="num">100%</div>
            <div class="lbl">Peer-to-Peer</div>
        </div>
        <div class="stat-item">
            <div class="num">24/7</div>
            <div class="lbl">Platform Access</div>
        </div>
        <div class="stat-item">
            <div class="num">SCC</div>
            <div class="lbl">Cebu Campus</div>
        </div>
    </div>
</div>

<!-- Values -->
<div class="section">
    <p class="section-label">What We Stand For</p>
    <h2 class="section-title">Our Core Values</h2>
    <div class="values-grid">
        <div class="value-item">
            <div class="value-icon"><i class="fas fa-handshake"></i></div>
            <div>
                <h4>Collaboration</h4>
                <p>We believe students learn best when they help each other. Peer tutoring builds both the tutor and the student.</p>
            </div>
        </div>
        <div class="value-item">
            <div class="value-icon"><i class="fas fa-star"></i></div>
            <div>
                <h4>Excellence</h4>
                <p>We hold ourselves to high academic standards and encourage every student to strive for their personal best.</p>
            </div>
        </div>
        <div class="value-item">
            <div class="value-icon"><i class="fas fa-shield-alt"></i></div>
            <div>
                <h4>Integrity</h4>
                <p>Honest feedback, fair ratings, and transparent processes ensure trust between students and tutors.</p>
            </div>
        </div>
        <div class="value-item">
            <div class="value-icon"><i class="fas fa-universal-access"></i></div>
            <div>
                <h4>Accessibility</h4>
                <p>Every BSIT student deserves academic support regardless of their background or current skill level.</p>
            </div>
        </div>
        <div class="value-item">
            <div class="value-icon"><i class="fas fa-lightbulb"></i></div>
            <div>
                <h4>Innovation</h4>
                <p>We continuously improve the platform with new features like coding practice, quizzes, and progress tracking.</p>
            </div>
        </div>
        <div class="value-item">
            <div class="value-icon"><i class="fas fa-users"></i></div>
            <div>
                <h4>Community</h4>
                <p>We foster a welcoming environment where students feel comfortable asking questions and sharing knowledge.</p>
            </div>
        </div>
    </div>
</div>

<div class="divider"></div>

<!-- Subjects Covered -->
<div class="section">
    <p class="section-label">What We Cover</p>
    <h2 class="section-title">Subjects Available</h2>
    <div class="mv-grid" style="margin-top:32px;">
        <div class="mv-card">
            <div class="mv-icon"><i class="fas fa-globe"></i></div>
            <h3>IM207 — Web Systems</h3>
            <p>HTML, CSS, JavaScript, PHP, and modern web frameworks. Build real-world web applications from scratch.</p>
        </div>
        <div class="mv-card">
            <div class="mv-icon"><i class="fas fa-code"></i></div>
            <h3>PF205 — Programming Fundamentals</h3>
            <p>Core programming concepts using Python and Java. Algorithms, data structures, and problem-solving techniques.</p>
        </div>
        <div class="mv-card">
            <div class="mv-icon"><i class="fas fa-network-wired"></i></div>
            <h3>NET208 — Networking</h3>
            <p>Computer networks, TCP/IP protocols, network security, and infrastructure design fundamentals.</p>
        </div>
        <div class="mv-card">
            <div class="mv-icon"><i class="fas fa-cogs"></i></div>
            <h3>IPT209 — Integrative Programming</h3>
            <p>Advanced programming concepts, system integration, APIs, and full-stack development techniques.</p>
        </div>
    </div>
</div>

<!-- CTA -->
<div class="cta-section">
    <h2>Ready to Start<br>Learning?</h2>
    <p>Join hundreds of BSIT students already using the platform.</p>
    <div class="cta-btns">
        <a href="register.php" class="cta-btn cta-btn-white">Create Free Account</a>
        <a href="contact.php" class="cta-btn cta-btn-outline">Contact Us</a>
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
