<?php
require_once 'config.php';
$page_title = 'Learning Modules';
if (!isLoggedIn()) redirect('login.php');
$user = getUserById($_SESSION['user_id']);
$conn = getConnection();

// Get all subjects with module counts
try {
    $stmt = $conn->prepare("
        SELECT s.*, COUNT(m.id) as module_count
        FROM subjects s
        LEFT JOIN modules m ON s.id = m.subject_id AND m.status = 'published'
        GROUP BY s.id ORDER BY s.name
    ");
    $stmt->execute();
    $subjects = $stmt->fetchAll();
} catch (PDOException $e) { $subjects = []; }

// Get modules filtered by subject
$filter_subject = sanitizeInt($_GET['subject'] ?? 0);
try {
    $sql = "SELECT m.*, s.name as subject_name,
                   u.full_name as author_name,
                   (SELECT COUNT(*) FROM module_progress mp WHERE mp.module_id = m.id AND mp.user_id = ?) as completed
            FROM modules m
            JOIN subjects s ON m.subject_id = s.id
            JOIN users u ON m.author_id = u.id
            WHERE m.status = 'published'";
    $params = [$user['id']];
    if ($filter_subject) { $sql .= " AND m.subject_id = ?"; $params[] = $filter_subject; }
    $sql .= " ORDER BY s.name, m.order_num ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $modules = $stmt->fetchAll();
} catch (PDOException $e) { $modules = []; }
?>
<?php include 'header.php'; ?>
<div class="page-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="page-hero">
        <div class="page-hero-content">
            <h1>Learning Modules</h1>
            <p>Study at your own pace</p>
        </div>
    </div>

    <div class="page-inner">
    <?php $flash = getFlash(); if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
    <?php endif; ?>

    <!-- Subject Filter -->
    <div class="filter-bar">
        <a href="modules.php" class="filter-btn <?php echo !$filter_subject ? 'active' : ''; ?>">All Subjects</a>
        <?php foreach ($subjects as $s): ?>
            <a href="modules.php?subject=<?php echo $s['id']; ?>" class="filter-btn <?php echo $filter_subject == $s['id'] ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($s['name']); ?>
                <span class="filter-count"><?php echo $s['module_count']; ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($user['role'] == 'tutor'): ?>
    <div style="margin-bottom:20px;">
        <a href="module_create.php" class="btn-primary"><i class="fas fa-plus"></i> Create New Module</a>
    </div>
    <?php endif; ?>

    <?php if ($modules): ?>
    <div class="modules-grid">
        <?php foreach ($modules as $m): ?>
        <div class="module-card <?php echo $m['completed'] ? 'completed' : ''; ?>">
            <div class="module-subject-tag"><?php echo htmlspecialchars($m['subject_name']); ?></div>
            <div class="module-icon">
                <?php
                $icons = ['programming'=>'fa-code','web'=>'fa-globe','network'=>'fa-network-wired','database'=>'fa-database'];
                $icon = 'fa-book';
                foreach ($icons as $k => $v) if (stripos($m['subject_name'], $k) !== false) { $icon = $v; break; }
                ?>
                <i class="fas <?php echo $icon; ?>"></i>
            </div>
            <h3><?php echo htmlspecialchars($m['title']); ?></h3>
            <p><?php echo htmlspecialchars(substr($m['description'] ?? '', 0, 100)) . (strlen($m['description'] ?? '') > 100 ? '...' : ''); ?></p>
            <div class="module-meta">
                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($m['author_name']); ?></span>
                <span><i class="fas fa-layer-group"></i> <?php echo ucfirst($m['difficulty'] ?? 'beginner'); ?></span>
            </div>
            <div class="module-footer">
                <?php if ($m['completed']): ?>
                    <span class="completed-badge"><i class="fas fa-check-circle"></i> Completed</span>
                <?php endif; ?>
                <a href="module_view.php?id=<?php echo $m['id']; ?>" class="btn-view">
                    <?php echo $m['completed'] ? 'Review' : 'Start Learning'; ?> <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="content-card" style="text-align:center; padding:60px 20px;">
        <i class="fas fa-book-open" style="font-size:3rem; color:#ddd; margin-bottom:15px;"></i>
        <p style="color:#999;">No modules available yet.<?php echo $user['role']=='tutor' ? ' <a href="module_create.php">Create the first one!</a>' : ''; ?></p>
    </div>
    <?php endif; ?>
    </div>
</div>
<style>
.filter-bar { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:25px; }
.filter-btn { padding:8px 16px; border-radius:20px; background:rgba(255,255,255,0.7); color:#333; text-decoration:none; font-size:0.85rem; font-weight:500; border:1px solid rgba(0,0,0,0.1); transition:all 0.2s; display:flex; align-items:center; gap:6px; }
.filter-btn:hover, .filter-btn.active { background:var(--primary-red); color:white; border-color:var(--primary-red); }
.filter-count { background:rgba(255,255,255,0.3); border-radius:10px; padding:1px 7px; font-size:0.75rem; }
.filter-btn.active .filter-count { background:rgba(255,255,255,0.3); }
.modules-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px,1fr)); gap:20px; }
.module-card { background:rgba(255,255,255,0.92); border-radius:16px; padding:24px; border:2px solid rgba(255,255,255,0.4); transition:all 0.3s; position:relative; overflow:hidden; }
.module-card:hover { transform:translateY(-4px); box-shadow:0 12px 35px rgba(0,0,0,0.12); border-color:var(--primary-red); }
.module-card.completed { border-color:#4CAF50; }
.module-subject-tag { font-size:0.7rem; font-weight:700; color:var(--primary-red); text-transform:uppercase; letter-spacing:1px; margin-bottom:12px; }
.module-icon { width:56px; height:56px; background:linear-gradient(135deg,var(--primary-red),var(--primary-blue)); border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.5rem; color:white; margin-bottom:14px; }
.module-card h3 { font-size:1rem; font-weight:700; color:#1a1a2e; margin-bottom:8px; }
.module-card p { font-size:0.82rem; color:#666; line-height:1.6; margin-bottom:14px; }
.module-meta { display:flex; gap:14px; font-size:0.75rem; color:#999; margin-bottom:14px; }
.module-footer { display:flex; justify-content:space-between; align-items:center; }
.completed-badge { font-size:0.75rem; color:#4CAF50; font-weight:600; }
.btn-view { background:var(--primary-red); color:white; padding:8px 16px; border-radius:20px; text-decoration:none; font-size:0.8rem; font-weight:600; transition:all 0.2s; }
.btn-view:hover { background:#6b0000; }
.alert { padding:12px 16px; border-radius:10px; margin-bottom:16px; }
.alert-success { background:#f0fff4; color:#1e7e34; border:1px solid #c3e6cb; }
.alert-danger { background:#fff0f0; color:#c0392b; border:1px solid #f5c6cb; }
@media(max-width:768px){ .modules-grid{grid-template-columns:1fr;} }
</style>
<?php include 'footer.php'; ?>
