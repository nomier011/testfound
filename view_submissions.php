<?php
require_once 'config.php';
$page_title = 'View Submissions';
if (!isLoggedIn()) redirect('login.php');
$user = getUserById($_SESSION['user_id']);
if ($user['role'] != 'tutor') redirect('dashboard.php');

$conn = getConnection();
$task_id = intval($_GET['task_id'] ?? 0);

// Verify task belongs to this tutor
$stmt = $conn->prepare("SELECT t.*, s.name as subject_name FROM tasks t JOIN subjects s ON t.subject_id=s.id WHERE t.id=? AND t.tutor_id=?");
$stmt->execute([$task_id, $user['id']]);
$task = $stmt->fetch();
if (!$task) { setFlash("Task not found.", 'danger'); redirect('tutor_dashboard.php'); }

// Handle grading
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['grade_submission'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash("Invalid request.", 'danger');
        redirect("view_submissions.php?task_id=$task_id");
    }
    $sub_id   = sanitizeInt($_POST['submission_id'] ?? 0);
    $grade    = sanitizeFloat($_POST['grade'] ?? 0);
    if ($grade < 0 || $grade > 100) { $grade = 0; }
    $feedback = trim($_POST['feedback'] ?? '');
    $stmt = $conn->prepare("UPDATE task_submissions SET grade=?, feedback=?, status='graded', graded_at=NOW() WHERE id=? AND task_id=?");
    $stmt->execute([$grade, $feedback, $sub_id, $task_id]);
    // Notify student
    $stmt2 = $conn->prepare("SELECT student_id FROM task_submissions WHERE id=?");
    $stmt2->execute([$sub_id]);
    $sub = $stmt2->fetch();
    if ($sub) {
        $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'assignment')")
             ->execute([$sub['student_id'], "Your submission for '{$task['title']}' has been graded: $grade/100"]);
    }
    setFlash("Submission graded!", 'success');
    redirect("view_submissions.php?task_id=$task_id");
}

// Get all submissions
$stmt = $conn->prepare("SELECT ts.*, u.full_name as student_name, u.school_id FROM task_submissions ts JOIN users u ON ts.student_id=u.id WHERE ts.task_id=? ORDER BY ts.submitted_at DESC");
$stmt->execute([$task_id]);
$submissions = $stmt->fetchAll();
?>
<?php include 'header.php'; ?>
<div class="page-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="page-hero">
        <div class="page-hero-content">
            <h1>Student Submissions</h1>
            <p>Review and grade student work</p>
        </div>
    </div>

    <div class="page-inner">
    <?php $flash = getFlash(); if ($flash): ?>
        <div style="padding:12px 16px; border-radius:10px; margin-bottom:16px; background:<?php echo $flash['type']=='success'?'#f0fff4':'#fff0f0'; ?>; color:<?php echo $flash['type']=='success'?'#1e7e34':'#c0392b'; ?>; border:1px solid <?php echo $flash['type']=='success'?'#c3e6cb':'#f5c6cb'; ?>;">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <?php if ($submissions): ?>
    <?php foreach ($submissions as $sub): ?>
    <div class="content-card" style="margin-bottom:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <div>
                <strong><?php echo htmlspecialchars($sub['student_name']); ?></strong>
                <?php if ($sub['school_id']): ?><span style="color:#888; font-size:0.8rem; margin-left:8px;"><?php echo htmlspecialchars($sub['school_id']); ?></span><?php endif; ?>
            </div>
            <div>
                <span class="status-badge status-<?php echo $sub['status']=='graded'?'approved':'pending'; ?>"><?php echo ucfirst($sub['status']); ?></span>
                <?php if ($sub['grade'] !== null): ?><span style="margin-left:8px; font-weight:700; color:var(--primary-red);"><?php echo $sub['grade']; ?>/100</span><?php endif; ?>
            </div>
        </div>
        <?php if ($sub['submission_text']): ?>
            <div style="background:#f9f9f9; border-radius:8px; padding:14px; margin-bottom:12px; font-size:0.9rem; line-height:1.6;"><?php echo nl2br(htmlspecialchars($sub['submission_text'])); ?></div>
        <?php endif; ?>
        <?php if ($sub['submission_file']): ?>
            <p style="margin-bottom:12px;"><a href="uploads/submissions/<?php echo htmlspecialchars($sub['submission_file']); ?>" target="_blank" style="color:var(--primary-red);"><i class="fas fa-paperclip"></i> View Attachment</a></p>
        <?php endif; ?>
        <?php if ($sub['feedback']): ?>
            <p style="color:#555; font-size:0.85rem; margin-bottom:12px;"><strong>Feedback:</strong> <?php echo htmlspecialchars($sub['feedback']); ?></p>
        <?php endif; ?>
        <form method="POST" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
            <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
            <input type="hidden" name="grade_submission" value="1">
            <div>
                <label style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:4px;">Grade (0–100)</label>
                <input type="number" name="grade" min="0" max="100" step="0.5" value="<?php echo $sub['grade'] ?? ''; ?>" required style="width:100px; padding:8px 12px; border:1.5px solid #e0e0e0; border-radius:8px; font-family:inherit;">
            </div>
            <div style="flex:1; min-width:200px;">
                <label style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:4px;">Feedback</label>
                <input type="text" name="feedback" value="<?php echo htmlspecialchars($sub['feedback'] ?? ''); ?>" placeholder="Optional feedback..." style="width:100%; padding:8px 12px; border:1.5px solid #e0e0e0; border-radius:8px; font-family:inherit;">
            </div>
            <button type="submit" style="padding:9px 20px; background:linear-gradient(135deg,var(--primary-red),var(--primary-blue)); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer;">
                <?php echo $sub['status']=='graded' ? 'Update Grade' : 'Grade'; ?>
            </button>
        </form>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
        <div class="content-card"><div class="no-data">No submissions yet for this assignment.</div></div>
    <?php endif; ?>
    <div style="margin-top:16px;"><a href="create_assignment.php" style="color:var(--primary-red);"><i class="fas fa-arrow-left"></i> Back to Assignments</a></div>
    </div>
</div>
<?php include 'footer.php'; ?>
