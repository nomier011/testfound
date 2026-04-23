<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
if ($user['role'] != 'tutor') {
    redirect('dashboard.php');
}

$conn = getConnection();

// Get tutor's subjects
$stmt = $conn->prepare("
    SELECT s.* FROM subjects s
    JOIN tutor_subjects ts ON s.id = ts.subject_id
    WHERE ts.tutor_id = ?
");
$stmt->execute([$user['id']]);
$subjects = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_id = $_POST['subject_id'] ?? 0;
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $due_date = $_POST['due_date'] ?? null;
    $due_time = $_POST['due_time'] ?? null;
    
    if ($title && $subject_id) {
        $stmt = $conn->prepare("INSERT INTO tasks (tutor_id, subject_id, title, description, due_date, due_time) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user['id'], $subject_id, $title, $description, $due_date, $due_time]);
        setFlash("Assignment created successfully!", 'success');
        redirect('tutor_dashboard.php');
    } else {
        setFlash("Please fill in all required fields", 'danger');
    }
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
            <a href="create_assignment.php" class="sidebar-link active"><i class="fas fa-plus-circle"></i> Create Assignment</a>
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
            <h1><i class="fas fa-plus-circle"></i> Create Assignment</h1>
            <p>Create a new assignment for your students</p>
        </div>
        
        <div class="content-card">
            <form method="POST" action="">
                <div class="form-group">
                    <label>Subject <span class="required">*</span></label>
                    <select name="subject_id" required>
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Assignment Title <span class="required">*</span></label>
                    <input type="text" name="title" placeholder="e.g., Chapter 1 Quiz, Programming Exercise 1" required>
                </div>
                
                <div class="form-group">
                    <label>Description / Instructions</label>
                    <textarea name="description" rows="5" placeholder="Describe the assignment, instructions, and requirements..."></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" name="due_date">
                    </div>
                    <div class="form-group">
                        <label>Due Time</label>
                        <input type="time" name="due_time">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Create Assignment</button>
                    <a href="tutor_dashboard.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
        
        <div class="info-card">
            <h4><i class="fas fa-info-circle"></i> Assignment Tips</h4>
            <ul>
                <li>Create clear and specific assignment titles</li>
                <li>Provide detailed instructions for students</li>
                <li>Set realistic due dates</li>
                <li>Students will be notified when you create assignments</li>
                <li>You can view submissions from your dashboard</li>
            </ul>
        </div>
    </div>
</div>

<style>
.required {
    color: var(--danger-red);
    margin-left: 3px;
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

.info-card {
    background: rgba(255,255,255,0.88);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    margin-top: 20px;
    border: 1px solid rgba(255,255,255,0.3);
}

.info-card h4 {
    color: var(--primary-red);
    margin-bottom: 10px;
}

.info-card ul {
    padding-left: 20px;
    color: #333;
}

.info-card li {
    margin-bottom: 5px;
}

@media (max-width: 768px) {
    .form-actions {
        flex-direction: column;
    }
}
</style>

<?php include 'footer.php'; ?>