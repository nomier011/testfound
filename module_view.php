<?php
require_once 'config.php';
$page_title = 'Module';
if (!isLoggedIn()) redirect('login.php');
$user = getUserById($_SESSION['user_id']);
$conn = getConnection();

$module_id = sanitizeInt($_GET['id'] ?? 0);

try {
    $stmt = $conn->prepare("SELECT m.*, s.name as subject_name, u.full_name as author_name FROM modules m JOIN subjects s ON m.subject_id=s.id JOIN users u ON m.author_id=u.id WHERE m.id=? AND m.status='published'");
    $stmt->execute([$module_id]);
    $module = $stmt->fetch();
} catch (PDOException $e) { $module = null; }

if (!$module) { setFlash("Module not found.", 'danger'); redirect('modules.php'); }
$page_title = htmlspecialchars($module['title']);

// Mark as completed/in-progress
try {
    $stmt = $conn->prepare("INSERT INTO module_progress (user_id, module_id, status, completed_at) VALUES (?,?,'completed',NOW()) ON DUPLICATE KEY UPDATE status='completed', completed_at=NOW()");
    $stmt->execute([$user['id'], $module_id]);
} catch (PDOException $e) {}

// Get related quizzes
try {
    $stmt = $conn->prepare("SELECT id, title FROM quizzes WHERE module_id=? AND status='published'");
    $stmt->execute([$module_id]);
    $quizzes = $stmt->fetchAll();
} catch (PDOException $e) { $quizzes = []; }

// Prev / Next modules in same subject
try {
    $stmt = $conn->prepare("SELECT id, title FROM modules WHERE subject_id=? AND status='published' AND order_num > ? ORDER BY order_num ASC LIMIT 1");
    $stmt->execute([$module['subject_id'], $module['order_num'] ?? 0]);
    $next = $stmt->fetch();
    $stmt = $conn->prepare("SELECT id, title FROM modules WHERE subject_id=? AND status='published' AND order_num < ? ORDER BY order_num DESC LIMIT 1");
    $stmt->execute([$module['subject_id'], $module['order_num'] ?? 0]);
    $prev = $stmt->fetch();
} catch (PDOException $e) { $next = $prev = null; }
?>
<?php include 'header.php'; ?>
<div class="page-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="page-hero">
        <div class="page-hero-content">
            <h1><?php echo htmlspecialchars($module['title']); ?></h1>
            <p><?php echo htmlspecialchars($module['subject_name']); ?></p>
        </div>
    </div>

    <div class="page-inner">

    <div class="breadcrumb">
        <a href="modules.php">Modules</a> <i class="fas fa-chevron-right"></i>
        <span><?php echo htmlspecialchars($module['subject_name']); ?></span> <i class="fas fa-chevron-right"></i>
        <span><?php echo htmlspecialchars($module['title']); ?></span>
    </div>

    <div class="module-layout">
        <!-- Main Content -->
        <div class="module-body">
            <div class="module-header-card">
                <div class="module-subject-tag"><?php echo htmlspecialchars($module['subject_name']); ?></div>
                <h1><?php echo htmlspecialchars($module['title']); ?></h1>
                <div class="module-meta">
                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($module['author_name']); ?></span>
                    <span><i class="fas fa-layer-group"></i> <?php echo ucfirst($module['difficulty'] ?? 'Beginner'); ?></span>
                    <span><i class="fas fa-check-circle" style="color:#4CAF50;"></i> Completed</span>
                </div>
            </div>

            <div class="module-content-card">
                <?php echo $module['content']; // HTML content stored by tutor ?>
            </div>

            <?php if ($module['video_url']): ?>
            <div class="content-card" style="margin-top:20px;">
                <h3><i class="fas fa-play-circle"></i> Video Lesson</h3>
                <div class="video-wrap">
                    <?php
                    // Support YouTube embeds
                    $vid = $module['video_url'];
                    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $vid, $m2)) {
                        echo '<iframe src="https://www.youtube.com/embed/'.htmlspecialchars($m2[1]).'" frameborder="0" allowfullscreen></iframe>';
                    } else {
                        echo '<a href="'.htmlspecialchars($vid).'" target="_blank" class="btn-primary">Watch Video</a>';
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Navigation -->
            <div class="module-nav">
                <?php if ($prev): ?>
                <a href="module_view.php?id=<?php echo $prev['id']; ?>" class="nav-btn prev"><i class="fas fa-arrow-left"></i> <?php echo htmlspecialchars($prev['title']); ?></a>
                <?php else: ?><span></span><?php endif; ?>
                <?php if ($next): ?>
                <a href="module_view.php?id=<?php echo $next['id']; ?>" class="nav-btn next"><?php echo htmlspecialchars($next['title']); ?> <i class="fas fa-arrow-right"></i></a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar Panel -->
        <div class="module-sidebar">
            <?php if ($quizzes): ?>
            <div class="side-card">
                <h4><i class="fas fa-question-circle"></i> Related Quizzes</h4>
                <?php foreach ($quizzes as $q): ?>
                <a href="quiz_take.php?id=<?php echo $q['id']; ?>" class="quiz-link">
                    <i class="fas fa-play-circle"></i> <?php echo htmlspecialchars($q['title']); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="side-card">
                <h4><i class="fas fa-lightbulb"></i> Quick Actions</h4>
                <a href="coding_practice.php" class="side-action-btn"><i class="fas fa-code"></i> Practice Coding</a>
                <a href="book_session.php" class="side-action-btn"><i class="fas fa-calendar-plus"></i> Book a Tutor</a>
                <a href="modules.php?subject=<?php echo $module['subject_id']; ?>" class="side-action-btn"><i class="fas fa-list"></i> More in this Subject</a>
            </div>
        </div>
    </div>
    </div>
</div>
<style>
.breadcrumb { font-size:0.82rem; color:#888; margin-bottom:20px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.breadcrumb a { color:var(--primary-red); text-decoration:none; }
.module-layout { display:grid; grid-template-columns:1fr 280px; gap:24px; }
.module-header-card { background:rgba(255,255,255,0.92); border-radius:16px; padding:28px; margin-bottom:20px; border:1px solid rgba(255,255,255,0.4); }
.module-subject-tag { font-size:0.7rem; font-weight:700; color:var(--primary-red); text-transform:uppercase; letter-spacing:1px; margin-bottom:10px; }
.module-header-card h1 { font-size:1.6rem; color:#1a1a2e; margin-bottom:12px; }
.module-meta { display:flex; gap:16px; font-size:0.8rem; color:#888; flex-wrap:wrap; }
.module-content-card { background:rgba(255,255,255,0.92); border-radius:16px; padding:32px; border:1px solid rgba(255,255,255,0.4); line-height:1.8; color:#333; }
.module-content-card h2,.module-content-card h3 { color:var(--primary-red); margin:24px 0 12px; }
.module-content-card pre { background:#1e1e2e; color:#cdd6f4; padding:20px; border-radius:10px; overflow-x:auto; font-size:0.88rem; line-height:1.6; }
.module-content-card code { background:#f0f0f0; padding:2px 6px; border-radius:4px; font-size:0.88rem; }
.module-content-card pre code { background:none; padding:0; }
.module-content-card ul,.module-content-card ol { padding-left:24px; margin:12px 0; }
.module-content-card li { margin-bottom:6px; }
.video-wrap { position:relative; padding-bottom:56.25%; height:0; overflow:hidden; border-radius:12px; }
.video-wrap iframe { position:absolute; top:0; left:0; width:100%; height:100%; }
.module-nav { display:flex; justify-content:space-between; margin-top:24px; gap:12px; }
.nav-btn { padding:12px 20px; border-radius:10px; text-decoration:none; font-weight:600; font-size:0.85rem; background:rgba(255,255,255,0.9); color:#333; border:1px solid #ddd; transition:all 0.2s; display:flex; align-items:center; gap:8px; }
.nav-btn:hover { background:var(--primary-red); color:white; border-color:var(--primary-red); }
.module-sidebar { display:flex; flex-direction:column; gap:16px; }
.side-card { background:rgba(255,255,255,0.92); border-radius:14px; padding:20px; border:1px solid rgba(255,255,255,0.4); }
.side-card h4 { color:var(--primary-red); margin-bottom:14px; font-size:0.9rem; }
.quiz-link { display:flex; align-items:center; gap:8px; padding:10px 12px; background:#f5f5f5; border-radius:8px; text-decoration:none; color:#333; font-size:0.85rem; margin-bottom:8px; transition:all 0.2s; }
.quiz-link:hover { background:var(--primary-red); color:white; }
.side-action-btn { display:flex; align-items:center; gap:8px; padding:10px 12px; background:linear-gradient(135deg,var(--primary-red),var(--primary-blue)); color:white; border-radius:8px; text-decoration:none; font-size:0.82rem; font-weight:600; margin-bottom:8px; transition:all 0.2s; }
.side-action-btn:hover { opacity:0.9; }
@media(max-width:900px){ .module-layout{grid-template-columns:1fr;} }
</style>
<?php include 'footer.php'; ?>
