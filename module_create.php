<?php
require_once 'config.php';
$page_title = 'Create Module';
if (!isLoggedIn()) redirect('login.php');
$user = getUserById($_SESSION['user_id']);
if (!in_array($user['role'], ['tutor','admin'])) redirect('dashboard.php');
$conn = getConnection();

$subjects = $conn->query("SELECT * FROM subjects ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { setFlash("Invalid request.", 'danger'); redirect('module_create.php'); }
    $title       = trim($_POST['title'] ?? '');
    $subject_id  = sanitizeInt($_POST['subject_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $content     = $_POST['content'] ?? '';
    $video_url   = trim($_POST['video_url'] ?? '');
    $difficulty  = in_array($_POST['difficulty']??'', ['beginner','intermediate','advanced']) ? $_POST['difficulty'] : 'beginner';
    $order_num   = sanitizeInt($_POST['order_num'] ?? 1);

    if (!$title || !$subject_id) { setFlash("Title and subject are required.", 'danger'); }
    else {
        try {
            $stmt = $conn->prepare("INSERT INTO modules (author_id, subject_id, title, description, content, video_url, difficulty, order_num, status) VALUES (?,?,?,?,?,?,?,?,'published')");
            $stmt->execute([$user['id'], $subject_id, $title, $description, $content, $video_url ?: null, $difficulty, $order_num]);
            setFlash("Module created successfully!", 'success');
            redirect('modules.php');
        } catch (PDOException $e) {
            error_log($e->getMessage());
            setFlash("Failed to create module.", 'danger');
        }
    }
}
?>
<?php include 'header.php'; ?>
<div class="page-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="page-hero">
        <div class="page-hero-content">
            <h1>Create Module</h1>
            <p>Share your knowledge with students</p>
        </div>
    </div>

    <div class="page-inner">
    <?php $flash = getFlash(); if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['message']); ?></div><?php endif; ?>
    <div class="content-card">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label>Module Title *</label>
                    <input type="text" name="title" placeholder="e.g., Introduction to HTML" required>
                </div>
                <div class="form-group">
                    <label>Subject *</label>
                    <select name="subject_id" required>
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $s): ?><option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Difficulty</label>
                    <select name="difficulty">
                        <option value="beginner">Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Order Number</label>
                    <input type="number" name="order_num" value="1" min="1">
                </div>
            </div>
            <div class="form-group">
                <label>Short Description</label>
                <textarea name="description" rows="2" placeholder="Brief summary of this module..."></textarea>
            </div>
            <div class="form-group">
                <label>Module Content * <small style="color:#999;">(HTML supported — use &lt;h2&gt;, &lt;p&gt;, &lt;pre&gt;&lt;code&gt;, &lt;ul&gt; etc.)</small></label>
                <textarea name="content" rows="18" placeholder="Write your lesson content here. You can use HTML tags for formatting.&#10;&#10;Example:&#10;&lt;h2&gt;What is HTML?&lt;/h2&gt;&#10;&lt;p&gt;HTML stands for HyperText Markup Language...&lt;/p&gt;&#10;&lt;pre&gt;&lt;code&gt;&lt;!DOCTYPE html&gt;&lt;/code&gt;&lt;/pre&gt;" style="font-family:monospace; font-size:0.88rem;"></textarea>
            </div>
            <div class="form-group">
                <label>Video URL <small style="color:#999;">(YouTube link optional)</small></label>
                <input type="url" name="video_url" placeholder="https://www.youtube.com/watch?v=...">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Publish Module</button>
                <a href="modules.php" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<style>
.form-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; }
.form-actions { display:flex; gap:12px; margin-top:20px; }
.alert { padding:12px 16px; border-radius:10px; margin-bottom:16px; }
.alert-success { background:#f0fff4; color:#1e7e34; border:1px solid #c3e6cb; }
.alert-danger { background:#fff0f0; color:#c0392b; border:1px solid #f5c6cb; }
</style>
<?php include 'footer.php'; ?>
