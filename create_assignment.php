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
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash("Invalid request. Please try again.", 'danger');
        redirect('create_assignment.php');
    }

    $subject_id  = sanitizeInt($_POST['subject_id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date    = $_POST['due_date'] ?? null;
    $due_time    = $_POST['due_time'] ?? null;

    // Validate due date is not in the past
    if ($due_date && strtotime($due_date) < strtotime('today')) {
        setFlash("Due date cannot be in the past.", 'danger');
        // fall through so form re-renders with values intact
    } elseif ($title && $subject_id) {
        // Verify subject belongs to this tutor
        $stmt = $conn->prepare("SELECT id FROM tutor_subjects WHERE tutor_id = ? AND subject_id = ?");
        $stmt->execute([$user['id'], $subject_id]);
        if (!$stmt->fetch()) {
            setFlash("Invalid subject selected.", 'danger');
            redirect('create_assignment.php');
        }

        try {
            // Generate task_number in PHP as a fallback in case the DB trigger is missing
            $task_number = 'TSK-' . date('Ym') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);

            $stmt = $conn->prepare("INSERT INTO tasks (task_number, tutor_id, subject_id, title, description, due_date, due_time) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$task_number, $user['id'], $subject_id, $title, $description, $due_date ?: null, $due_time ?: null]);
            $task_id = $conn->lastInsertId();

            // Notify all students who have booked this tutor
            $notify_stmt = $conn->prepare("
                SELECT DISTINCT b.student_id FROM bookings b
                WHERE b.tutor_id = ? AND b.status IN ('approved', 'completed')
            ");
            $notify_stmt->execute([$user['id']]);
            $students = $notify_stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($students)) {
                $due_label = $due_date ? ' (Due: ' . date('M d, Y', strtotime($due_date)) . ')' : '';
                sendBulkNotification(
                    $students,
                    "New assignment posted by {$user['full_name']}: \"{$title}\"{$due_label}",
                    'assignment'
                );
            }

            setFlash("Assignment created successfully!", 'success');
            redirect('tutor_dashboard.php');
        } catch (PDOException $e) {
            error_log("Create assignment error: " . $e->getMessage());
            setFlash("Failed to create assignment: " . $e->getMessage(), 'danger');
        }
    } else {
        setFlash("Please fill in all required fields.", 'danger');
    }
}
?>

<?php include 'header.php'; ?>

<div class="page-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="page-hero">
        <div class="page-hero-content">
            <h1><i class="fas fa-plus-circle"></i> Create Assignment</h1>
            <p>Create a new assignment for your students</p>
        </div>
    </div>

    <div class="page-inner">

        <div class="content-card">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                <div class="form-group">
                    <label>Subject <span style="color:#dc2626;">*</span></label>
                    <select name="subject_id" required>
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>" <?php echo (isset($subject_id) && $subject_id == $subject['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Assignment Title <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($title ?? ''); ?>" placeholder="e.g., Chapter 1 Quiz, Programming Exercise 1" required>
                </div>

                <div class="form-group">
                    <label>Description / Instructions</label>
                    <textarea name="description" rows="5" placeholder="Describe the assignment, instructions, and requirements..."><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" name="due_date" value="<?php echo htmlspecialchars($due_date ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Due Time</label>
                        <input type="time" name="due_time" value="<?php echo htmlspecialchars($due_time ?? ''); ?>">
                    </div>
                </div>

                <div style="display:flex;gap:12px;margin-top:20px;">
                    <button type="submit" class="btn-primary">Create Assignment</button>
                    <a href="tutor_dashboard.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <div class="content-card" style="margin-top:0;">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Assignment Tips</h3>
            </div>
            <ul style="padding-left:20px;color:#4b5563;line-height:2;">
                <li>Create clear and specific assignment titles</li>
                <li>Provide detailed instructions for students</li>
                <li>Set realistic due dates</li>
                <li>Students will be notified when you create assignments</li>
                <li>You can view submissions from your dashboard</li>
            </ul>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>