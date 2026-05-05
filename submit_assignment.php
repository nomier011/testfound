<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
if ($user['role'] != 'student') {
    redirect('dashboard.php');
}

$task_id = sanitizeInt($_GET['id'] ?? 0);
$conn = getConnection();

// Get assignment details
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
    redirect('student_dashboard.php');
}

// Check if already submitted
$stmt = $conn->prepare("SELECT * FROM task_submissions WHERE task_id = ? AND student_id = ?");
$stmt->execute([$task_id, $user['id']]);
$submission = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash("Invalid request.", 'danger');
        redirect("submit_assignment.php?id=$task_id");
    }

    $submission_text = trim($_POST['submission_text'] ?? '');

    // Handle file upload
    $submission_file = null;
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] == UPLOAD_ERR_OK) {
        $allowed     = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'png', 'zip'];
        $max_size    = 10 * 1024 * 1024; // 10 MB
        $filename    = $_FILES['submission_file']['name'];
        $ext         = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $file_size   = $_FILES['submission_file']['size'];

        if (!in_array($ext, $allowed)) {
            setFlash("Invalid file type. Allowed: PDF, DOC, DOCX, TXT, JPG, PNG, ZIP.", 'danger');
            redirect("submit_assignment.php?id=$task_id");
        }
        if ($file_size > $max_size) {
            setFlash("File too large. Maximum size is 10MB.", 'danger');
            redirect("submit_assignment.php?id=$task_id");
        }

        $submissions_dir = UPLOAD_PATH . 'submissions/';
        if (!file_exists($submissions_dir)) {
            mkdir($submissions_dir, 0755, true);
        }
        $new_filename = time() . '_' . $user['id'] . '_' . $task_id . '.' . $ext;
        move_uploaded_file($_FILES['submission_file']['tmp_name'], $submissions_dir . $new_filename);
        $submission_file = $new_filename;
    }

    if ($submission) {
        $stmt = $conn->prepare("UPDATE task_submissions SET submission_text = ?, submission_file = ?, status = 'submitted', submitted_at = NOW() WHERE task_id = ? AND student_id = ?");
        $stmt->execute([$submission_text, $submission_file, $task_id, $user['id']]);
    } else {
        $stmt = $conn->prepare("INSERT INTO task_submissions (task_id, student_id, submission_text, submission_file, status, submitted_at) VALUES (?, ?, ?, ?, 'submitted', NOW())");
        $stmt->execute([$task_id, $user['id'], $submission_text, $submission_file]);
    }

    setFlash("Assignment submitted successfully!", 'success');
    redirect('student_dashboard.php');
}
?>

<?php include 'header.php'; ?>

<div class="page-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="page-hero">
        <div class="page-hero-content">
            <h1>Submit Assignment</h1>
            <p>Upload your work for review</p>
        </div>
    </div>

    <div class="page-inner">
        
        <div class="assignment-details">
            <div class="info-row">
                <span class="label">Subject:</span>
                <span class="value"><?php echo htmlspecialchars($assignment['subject_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Tutor:</span>
                <span class="value"><?php echo htmlspecialchars($assignment['tutor_name']); ?></span>
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
                <span class="label">Description:</span>
                <span class="value"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></span>
            </div>
        </div>
        
        <div class="content-card">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                <div class="form-group">
                    <label>Your Answer / Submission</label>
                    <textarea name="submission_text" rows="8" placeholder="Write your answer here..."><?php echo htmlspecialchars($submission['submission_text'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Attach File (Optional)</label>
                    <input type="file" name="submission_file" accept=".pdf,.doc,.docx,.txt,.jpg,.png,.zip">
                    <small>Accepted formats: PDF, DOC, DOCX, TXT, JPG, PNG, ZIP (Max 10MB)</small>
                    <?php if (!empty($submission['submission_file'])): ?>
                        <div class="uploaded-file">
                            <i class="fas fa-paperclip"></i> Previously uploaded: <?php echo $submission['submission_file']; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Submit Assignment</button>
                    <a href="student_dashboard.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.assignment-details {
    background: rgba(255,255,255,0.88);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid rgba(255,255,255,0.3);
}

.info-row {
    display: flex;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.info-row:last-child {
    border-bottom: none;
}

.label {
    width: 120px;
    font-weight: 600;
    color: #333;
}

.value {
    flex: 1;
    color: #666;
}

.form-actions {
    display: flex;
    gap: 15px;
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
    text-decoration: none;
    text-align: center;
    transition: all 0.3s;
}

.btn-secondary:hover {
    background: #555;
}

.uploaded-file {
    margin-top: 10px;
    padding: 8px;
    background: #e8f4fd;
    border-radius: 8px;
    font-size: 0.85rem;
}

small {
    display: block;
    color: #999;
    font-size: 0.7rem;
    margin-top: 5px;
}

@media (max-width: 768px) {
    .info-row {
        flex-direction: column;
    }
    
    .label {
        width: auto;
        margin-bottom: 5px;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<?php include 'footer.php'; ?>