<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
if ($user['role'] != 'student') {
    redirect('dashboard.php');
}

$task_id = $_GET['task_id'] ?? 0;
$conn = getConnection();

// Get assignment
$stmt = $conn->prepare("
    SELECT t.*, s.name as subject_name, u.full_name as tutor_name
    FROM tasks t
    JOIN subjects s ON t.subject_id = s.id
    JOIN users u ON t.tutor_id = u.id
    WHERE t.id = ?
");
$stmt->execute([$task_id]);
$assignment = $stmt->fetch();

if (!$assignment) {
    redirect('my_assignments.php');
}

// Get student's submission
$stmt = $conn->prepare("SELECT * FROM task_submissions WHERE task_id = ? AND student_id = ?");
$stmt->execute([$task_id, $user['id']]);
$submission = $stmt->fetch();

if (!$submission) {
    redirect('submit_assignment.php?id=' . $task_id);
}

$page_title = 'View Submission';
?>
<?php include 'header.php'; ?>

<div class="page-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="page-hero">
        <div class="page-hero-content">
            <h1>View Submission</h1>
            <p>Review your submitted work</p>
        </div>
    </div>

    <div class="page-inner">

        <!-- Assignment Info -->
        <div class="content-card" style="margin-bottom: 20px;">
            <div class="card-header"><h3>Assignment Details</h3></div>
            <div class="info-row"><span class="label">Subject:</span><span class="value"><?php echo htmlspecialchars($assignment['subject_name']); ?></span></div>
            <div class="info-row"><span class="label">Tutor:</span><span class="value"><?php echo htmlspecialchars($assignment['tutor_name']); ?></span></div>
            <div class="info-row"><span class="label">Due Date:</span><span class="value"><?php echo $assignment['due_date'] ? date('F d, Y', strtotime($assignment['due_date'])) : 'No due date'; ?></span></div>
            <?php if ($assignment['description']): ?>
                <div class="info-row"><span class="label">Instructions:</span><span class="value"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></span></div>
            <?php endif; ?>
        </div>

        <!-- Submission -->
        <div class="content-card" style="margin-bottom: 20px;">
            <div class="card-header">
                <h3>Your Submission</h3>
                <span class="status-badge <?php echo $submission['status'] == 'graded' ? 'status-completed' : 'status-pending'; ?>">
                    <?php echo ucfirst($submission['status']); ?>
                </span>
            </div>
            <div class="info-row"><span class="label">Submitted:</span><span class="value"><?php echo date('F d, Y h:i A', strtotime($submission['submitted_at'])); ?></span></div>

            <?php if ($submission['submission_text']): ?>
                <div style="margin-top: 15px;">
                    <strong>Answer:</strong>
                    <div style="background: #f9f9f9; padding: 15px; border-radius: 10px; margin-top: 8px; line-height: 1.6; color: #333;">
                        <?php echo nl2br(htmlspecialchars($submission['submission_text'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($submission['submission_file']): ?>
                <div style="margin-top: 15px;">
                    <strong>Attached File:</strong>
                    <a href="uploads/submissions/<?php echo htmlspecialchars($submission['submission_file']); ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 8px; margin-top: 8px; color: var(--primary-blue); text-decoration: none;">
                        <i class="fas fa-paperclip"></i> <?php echo htmlspecialchars($submission['submission_file']); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Grade & Feedback -->
        <?php if ($submission['status'] == 'graded'): ?>
        <div class="content-card">
            <div class="card-header"><h3><i class="fas fa-star"></i> Grade & Feedback</h3></div>
            <div class="grade-display">
                <div class="grade-circle">
                    <span class="grade-number"><?php echo number_format($submission['grade'], 0); ?></span>
                    <span class="grade-max">/100</span>
                </div>
                <div class="grade-label">
                    <?php
                    $g = floatval($submission['grade']);
                    if ($g >= 90) echo '<span class="grade-excellent">Excellent!</span>';
                    elseif ($g >= 80) echo '<span class="grade-good">Very Good</span>';
                    elseif ($g >= 70) echo '<span class="grade-average">Good</span>';
                    elseif ($g >= 60) echo '<span class="grade-passing">Passing</span>';
                    else echo '<span class="grade-failing">Needs Improvement</span>';
                    ?>
                </div>
            </div>
            <?php if ($submission['feedback']): ?>
                <div style="background: #f0f8ff; padding: 15px; border-radius: 10px; margin-top: 15px; border-left: 4px solid var(--primary-blue);">
                    <strong>Tutor Feedback:</strong>
                    <p style="margin-top: 8px; color: #333; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($submission['feedback'])); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div style="margin-top: 20px;">
            <a href="my_assignments.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back to Assignments</a>
        </div>
    </div>
</div>

<style>
.info-row { display: flex; padding: 10px 0; border-bottom: 1px solid #eee; }
.info-row:last-child { border-bottom: none; }
.label { width: 120px; font-weight: 600; color: #555; flex-shrink: 0; }
.value { flex: 1; color: #333; }
.grade-display { display: flex; align-items: center; gap: 20px; padding: 20px 0; }
.grade-circle { display: flex; align-items: baseline; gap: 4px; }
.grade-number { font-size: 3rem; font-weight: 800; color: var(--primary-red); }
.grade-max { font-size: 1.2rem; color: #999; }
.grade-excellent { color: #4CAF50; font-weight: 700; font-size: 1.1rem; }
.grade-good { color: #2196F3; font-weight: 700; font-size: 1.1rem; }
.grade-average { color: #FF9800; font-weight: 700; font-size: 1.1rem; }
.grade-passing { color: #FFC107; font-weight: 700; font-size: 1.1rem; }
.grade-failing { color: #f44336; font-weight: 700; font-size: 1.1rem; }
.btn-secondary { display: inline-flex; align-items: center; gap: 8px; background: #666; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; transition: all 0.3s; }
.btn-secondary:hover { background: #555; transform: translateY(-2px); }
@media (max-width: 768px) {
    .info-row { flex-direction: column; }
    .label { width: auto; margin-bottom: 4px; }
}
</style>

<?php include 'footer.php'; ?>
