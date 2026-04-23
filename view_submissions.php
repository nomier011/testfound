<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
if ($user['role'] != 'tutor') {
    redirect('dashboard.php');
}

$task_id = $_GET['task_id'] ?? 0;
$conn = getConnection();

// Get assignment details
$stmt = $conn->prepare("
    SELECT t.*, s.name as subject_name
    FROM tasks t
    JOIN subjects s ON t.subject_id = s.id
    WHERE t.id = ? AND t.tutor_id = ?
");
$stmt->execute([$task_id, $user['id']]);
$assignment = $stmt->fetch();

if (!$assignment) {
    redirect('tutor_dashboard.php');
}

// Get all submissions for this assignment
$stmt = $conn->prepare("
    SELECT ts.*, u.full_name as student_name, u.email as student_email, u.profile_pic as student_pic
    FROM task_submissions ts
    JOIN users u ON ts.student_id = u.id
    WHERE ts.task_id = ?
    ORDER BY ts.submitted_at DESC
");
$stmt->execute([$task_id]);
$submissions = $stmt->fetchAll();

// Get students who haven't submitted yet (enrolled in this subject)
// For now, we'll show all students who have booked sessions with this tutor for this subject
$stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.full_name, u.email, u.profile_pic
    FROM bookings b
    JOIN users u ON b.student_id = u.id
    WHERE b.tutor_id = ? AND b.subject_id = ? AND b.status IN ('approved', 'completed')
");
$stmt->execute([$user['id'], $assignment['subject_id']]);
$all_students = $stmt->fetchAll();

// Track which students have submitted
$submitted_student_ids = array_column($submissions, 'student_id');
$pending_students = array_filter($all_students, function($student) use ($submitted_student_ids) {
    return !in_array($student['id'], $submitted_student_ids);
});

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['grade_submission'])) {
    $submission_id = $_POST['submission_id'];
    $grade = $_POST['grade'];
    $feedback = $_POST['feedback'];
    
    $stmt = $conn->prepare("UPDATE task_submissions SET grade = ?, feedback = ?, status = 'graded', graded_at = NOW() WHERE id = ?");
    $stmt->execute([$grade, $feedback, $submission_id]);
    
    // Notify student
    $stmt = $conn->prepare("SELECT student_id FROM task_submissions WHERE id = ?");
    $stmt->execute([$submission_id]);
    $student_id = $stmt->fetch()['student_id'];
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'assignment_graded')");
    $stmt->execute([$student_id, "Your assignment '{$assignment['title']}' has been graded. Grade: {$grade}/100"]);
    
    setFlash("Grade submitted successfully!", 'success');
    redirect("view_submissions.php?task_id={$task_id}");
}
?>

<?php include 'header.php'; ?>

<div class="dashboard-container">
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="images/scclogo.png" alt="SCC Logo">
            <h3>Tutor Menu</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="tutor_dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="my_bookings.php" class="sidebar-link"><i class="fas fa-calendar-alt"></i> My Sessions</a>
            <a href="create_assignment.php" class="sidebar-link"><i class="fas fa-plus-circle"></i> Create Assignment</a>
            <a href="profile.php" class="sidebar-link"><i class="fas fa-user-circle"></i> Profile</a>
            <a href="logout.php" class="sidebar-link logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <?php if ($user['profile_pic']): ?>
                        <img src="uploads/<?php echo $user['profile_pic']; ?>">
                    <?php else: ?>
                        <?php echo substr($user['full_name'], 0, 1); ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                    <div class="user-role">Tutor</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="welcome-banner">
            <h1><i class="fas fa-tasks"></i> Assignment Submissions</h1>
            <p><?php echo htmlspecialchars($assignment['title']); ?> - <?php echo htmlspecialchars($assignment['subject_name']); ?></p>
        </div>
        
        <!-- Assignment Info Card -->
        <div class="assignment-info-card">
            <div class="info-row">
                <span class="label">Description:</span>
                <span class="value"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Due Date:</span>
                <span class="value">
                    <?php if ($assignment['due_date']): ?>
                        <?php echo date('F d, Y', strtotime($assignment['due_date'])); ?>
                        <?php if ($assignment['due_time']): ?>
                            at <?php echo date('h:i A', strtotime($assignment['due_time'])); ?>
                        <?php endif; ?>
                    <?php else: ?>
                        No due date
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-row">
                <span class="label">Submissions:</span>
                <span class="value"><?php echo count($submissions); ?> submitted</span>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid-small">
            <div class="stat-card-small">
                <div class="stat-number-small"><?php echo count($submissions); ?></div>
                <div>Submitted</div>
            </div>
            <div class="stat-card-small">
                <div class="stat-number-small"><?php echo count($pending_students); ?></div>
                <div>Pending</div>
            </div>
            <div class="stat-card-small">
                <div class="stat-number-small"><?php echo count(array_filter($submissions, function($s) { return $s['status'] == 'graded'; })); ?></div>
                <div>Graded</div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="submission-tabs">
            <button class="tab-btn active" data-tab="submitted">Submitted (<?php echo count($submissions); ?>)</button>
            <button class="tab-btn" data-tab="pending">Pending (<?php echo count($pending_students); ?>)</button>
        </div>
        
        <!-- Submitted Tab -->
        <div id="tab-submitted" class="tab-content active">
            <?php if ($submissions): ?>
                <?php foreach ($submissions as $submission): ?>
                    <div class="submission-card">
                        <div class="submission-header">
                            <div class="student-info">
                                <div class="student-avatar">
                                    <?php if ($submission['student_pic']): ?>
                                        <img src="uploads/<?php echo $submission['student_pic']; ?>">
                                    <?php else: ?>
                                        <?php echo substr($submission['student_name'], 0, 1); ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h4><?php echo htmlspecialchars($submission['student_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($submission['student_email']); ?></p>
                                </div>
                            </div>
                            <div class="submission-status">
                                <?php if ($submission['status'] == 'graded'): ?>
                                    <span class="badge-graded"><i class="fas fa-check-circle"></i> Graded</span>
                                <?php else: ?>
                                    <span class="badge-submitted"><i class="fas fa-clock"></i> Pending Grade</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="submission-details">
                            <?php if ($submission['submission_text']): ?>
                                <div class="detail-section">
                                    <strong>Submission Text:</strong>
                                    <div class="submission-text"><?php echo nl2br(htmlspecialchars($submission['submission_text'])); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($submission['submission_file']): ?>
                                <div class="detail-section">
                                    <strong>Attached File:</strong>
                                    <div>
                                        <a href="uploads/submissions/<?php echo $submission['submission_file']; ?>" target="_blank" class="download-link">
                                            <i class="fas fa-download"></i> <?php echo $submission['submission_file']; ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="detail-section">
                                <strong>Submitted:</strong> <?php echo date('F d, Y h:i A', strtotime($submission['submitted_at'])); ?>
                            </div>
                            
                            <?php if ($submission['grade']): ?>
                                <div class="detail-section grade-section">
                                    <strong>Grade:</strong> <?php echo $submission['grade']; ?>/100
                                    <?php if ($submission['feedback']): ?>
                                        <div class="feedback"><strong>Feedback:</strong> <?php echo nl2br(htmlspecialchars($submission['feedback'])); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($submission['status'] != 'graded'): ?>
                            <div class="grade-form">
                                <button class="btn-grade" onclick="openGradeModal(<?php echo $submission['id']; ?>, '<?php echo htmlspecialchars($submission['student_name']); ?>')">
                                    <i class="fas fa-star"></i> Grade Submission
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">No submissions yet.</div>
            <?php endif; ?>
        </div>
        
        <!-- Pending Tab (Students who haven't submitted) -->
        <div id="tab-pending" class="tab-content" style="display: none;">
            <?php if ($pending_students): ?>
                <?php foreach ($pending_students as $student): ?>
                    <div class="pending-student-card">
                        <div class="student-info">
                            <div class="student-avatar">
                                <?php if ($student['profile_pic']): ?>
                                    <img src="uploads/<?php echo $student['profile_pic']; ?>">
                                <?php else: ?>
                                    <?php echo substr($student['full_name'], 0, 1); ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h4><?php echo htmlspecialchars($student['full_name']); ?></h4>
                                <p><?php echo htmlspecialchars($student['email']); ?></p>
                            </div>
                        </div>
                        <div class="pending-status">
                            <span class="badge-pending"><i class="fas fa-hourglass-half"></i> Not Submitted</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">All students have submitted!</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Grade Modal -->
<div id="gradeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Grade Submission</h2>
            <span class="close" onclick="closeGradeModal()">&times;</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="submission_id" id="grade_submission_id">
            <div class="form-group">
                <label>Student: <span id="grade_student_name"></span></label>
            </div>
            <div class="form-group">
                <label>Grade (0-100)</label>
                <input type="number" name="grade" min="0" max="100" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Feedback (Optional)</label>
                <textarea name="feedback" rows="3" placeholder="Provide feedback to the student..."></textarea>
            </div>
            <div class="modal-buttons">
                <button type="submit" name="grade_submission" class="btn-primary">Submit Grade</button>
                <button type="button" class="btn-secondary" onclick="closeGradeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.assignment-info-card {
    background: rgba(255,255,255,0.88);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid rgba(255,255,255,0.3);
}

.info-row {
    display: flex;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.info-row:last-child {
    border-bottom: none;
}

.label {
    width: 100px;
    font-weight: 600;
    color: #333;
}

.value {
    flex: 1;
    color: #666;
}

.stats-grid-small {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card-small {
    background: rgba(255,255,255,0.88);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 15px;
    text-align: center;
    border: 1px solid rgba(255,255,255,0.3);
}

.stat-number-small {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary-red);
}

.submission-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.submission-tabs .tab-btn {
    padding: 10px 20px;
    background: rgba(255,255,255,0.8);
    border: 1px solid rgba(0,0,0,0.1);
    border-radius: 25px;
    cursor: pointer;
    transition: all 0.3s;
}

.submission-tabs .tab-btn:hover,
.submission-tabs .tab-btn.active {
    background: var(--primary-red);
    color: white;
}

.submission-card {
    background: rgba(255,255,255,0.88);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid rgba(255,255,255,0.3);
    transition: all 0.3s;
}

.submission-card:hover {
    transform: translateX(5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.submission-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    flex-wrap: wrap;
    gap: 10px;
}

.student-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.student-avatar {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--primary-red), var(--primary-blue));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: var(--primary-gold);
    border: 2px solid var(--primary-gold);
    overflow: hidden;
}

.student-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.submission-status .badge-submitted,
.submission-status .badge-graded {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-submitted {
    background: rgba(33,150,243,0.2);
    color: #2196F3;
}

.badge-graded {
    background: rgba(76,175,80,0.2);
    color: #4CAF50;
}

.badge-pending {
    background: rgba(255,152,0,0.2);
    color: #FF9800;
}

.submission-details {
    margin-bottom: 15px;
}

.detail-section {
    margin-bottom: 12px;
}

.submission-text {
    background: #f5f5f5;
    padding: 12px;
    border-radius: 8px;
    margin-top: 5px;
    font-size: 0.9rem;
    line-height: 1.5;
}

.download-link {
    display: inline-block;
    margin-top: 5px;
    color: var(--primary-blue);
    text-decoration: none;
}

.download-link:hover {
    text-decoration: underline;
}

.grade-section {
    background: #e8f4fd;
    padding: 10px;
    border-radius: 8px;
}

.feedback {
    margin-top: 8px;
    font-style: italic;
    color: #555;
}

.grade-form {
    text-align: right;
    padding-top: 10px;
    border-top: 1px solid #eee;
}

.btn-grade {
    background: var(--primary-red);
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 25px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-grade:hover {
    background: var(--primary-blue);
    transform: translateY(-2px);
}

.pending-student-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: rgba(255,255,255,0.88);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    margin-bottom: 10px;
    border: 1px solid rgba(255,255,255,0.3);
}

.pending-status .badge-pending {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 15px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    animation: slideIn 0.3s ease;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 2px solid var(--primary-red);
}

.modal-header h2 {
    color: var(--primary-red);
    margin: 0;
}

.modal-header .close {
    font-size: 1.5rem;
    cursor: pointer;
    color: #999;
}

.modal-buttons {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.btn-secondary {
    background: #666;
    color: white;
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
}

.no-data {
    text-align: center;
    padding: 40px;
    color: #999;
}

@keyframes slideIn {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

@media (max-width: 768px) {
    .submission-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .stats-grid-small {
        grid-template-columns: 1fr;
    }
    
    .info-row {
        flex-direction: column;
    }
    
    .label {
        width: auto;
        margin-bottom: 5px;
    }
}
</style>

<script>
document.querySelectorAll('.submission-tabs .tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const tab = this.dataset.tab;
        
        document.querySelectorAll('.submission-tabs .tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
        
        this.classList.add('active');
        document.getElementById(`tab-${tab}`).style.display = 'block';
    });
});

// Grade Modal
function openGradeModal(submissionId, studentName) {
    document.getElementById('grade_submission_id').value = submissionId;
    document.getElementById('grade_student_name').innerHTML = studentName;
    document.getElementById('gradeModal').style.display = 'flex';
}

function closeGradeModal() {
    document.getElementById('gradeModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('gradeModal');
    if (event.target === modal) {
        closeGradeModal();
    }
}
</script>

<?php include 'footer.php'; ?>