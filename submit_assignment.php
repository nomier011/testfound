<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
if ($user['role'] != 'student') {
    redirect('dashboard.php');
}

$task_id = $_GET['id'] ?? 0;
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
    $submission_text = $_POST['submission_text'] ?? '';
    
    // Handle file upload
    $submission_file = null;
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] == 0) {
        $allowed = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'png', 'zip'];
        $filename = $_FILES['submission_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = time() . '_' . $user['id'] . '_' . $task_id . '.' . $ext;
            move_uploaded_file($_FILES['submission_file']['tmp_name'], 'uploads/submissions/' . $new_filename);
            $submission_file = $new_filename;
        }
    }
    
    if ($submission) {
        // Update existing submission
        $stmt = $conn->prepare("UPDATE task_submissions SET submission_text = ?, submission_file = ?, status = 'submitted', submitted_at = NOW() WHERE task_id = ? AND student_id = ?");
        $stmt->execute([$submission_text, $submission_file, $task_id, $user['id']]);
    } else {
        // Create new submission
        $stmt = $conn->prepare("INSERT INTO task_submissions (task_id, student_id, submission_text, submission_file, status, submitted_at) VALUES (?, ?, ?, ?, 'submitted', NOW())");
        $stmt->execute([$task_id, $user['id'], $submission_text, $submission_file]);
    }
    
    setFlash("Assignment submitted successfully!", 'success');
    redirect('student_dashboard.php');
}
?>

<?php include 'header.php'; ?>

<div class="dashboard-container">
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="images/scclogo.png" alt="SCC Logo">
            <h3>Student Menu</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="student_dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="book_session.php" class="sidebar-link"><i class="fas fa-calendar-plus"></i> Book a Tutor</a>
            <a href="my_bookings.php" class="sidebar-link"><i class="fas fa-list-alt"></i> My Bookings</a>
            <a href="my_assignments.php" class="sidebar-link active"><i class="fas fa-tasks"></i> My Assignments</a>
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
                    <div class="user-role">Student</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="welcome-banner">
            <h1><i class="fas fa-upload"></i> Submit Assignment</h1>
            <p><?php echo htmlspecialchars($assignment['title']); ?></p>
        </div>
        
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