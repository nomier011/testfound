<?php
require_once 'config.php';

if (!isLoggedIn()) redirect('login.php');

$user = getUserById($_SESSION['user_id']);
if (!in_array($user['role'], ['student','tutor'])) redirect('dashboard.php');

$conn = getConnection();

if ($user['role'] === 'tutor') {
    // Tutor sees assignments they created
    $page_title = 'My Assignments';
    $stmt = $conn->prepare("
        SELECT t.*, s.name as subject_name,
               COUNT(ts.id) as submission_count
        FROM tasks t
        JOIN subjects s ON t.subject_id = s.id
        LEFT JOIN task_submissions ts ON ts.task_id = t.id
        WHERE t.tutor_id = ?
        GROUP BY t.id
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $assignments = $stmt->fetchAll();
} else {
    // Student sees all active assignments
    $page_title = 'My Assignments';
    $stmt = $conn->prepare("
        SELECT t.*, s.name as subject_name, u.full_name as tutor_name,
               (SELECT status FROM task_submissions WHERE task_id = t.id AND student_id = ?) as submission_status,
               (SELECT submitted_at FROM task_submissions WHERE task_id = t.id AND student_id = ?) as submitted_at,
               (SELECT grade FROM task_submissions WHERE task_id = t.id AND student_id = ?) as grade,
               (SELECT feedback FROM task_submissions WHERE task_id = t.id AND student_id = ?) as feedback
        FROM tasks t
        JOIN subjects s ON t.subject_id = s.id
        JOIN users u ON t.tutor_id = u.id
        WHERE t.status = 'active'
        ORDER BY t.due_date ASC, t.created_at DESC
    ");
    $stmt->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
    $assignments = $stmt->fetchAll();
}
?>

<?php include 'header.php'; ?>

<div class="page-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="page-hero">
        <div class="page-hero-content">
            <h1><?php echo $user['role'] === 'tutor' ? 'My Assignments' : 'My Assignments'; ?></h1>
            <p><?php echo $user['role'] === 'tutor' ? 'Manage assignments you created' : 'View and submit your assignments'; ?></p>
        </div>
    </div>

    <div class="page-inner">

        <?php if ($user['role'] === 'tutor'): ?>
        <!-- TUTOR VIEW -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h3 style="font-size:1rem;font-weight:700;color:#111;"><?php echo count($assignments); ?> Assignment(s)</h3>
            <a href="create_assignment.php" class="btn-primary btn-sm"><i class="fas fa-plus"></i> Create New</a>
        </div>
        <?php if ($assignments): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;">
            <?php foreach ($assignments as $a): ?>
            <div class="content-card" style="margin-bottom:0;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;gap:8px;">
                    <h4 style="font-size:.95rem;font-weight:700;color:#111;"><?php echo htmlspecialchars($a['title']); ?></h4>
                    <span style="background:<?php echo $a['status']==='active'?'#dcfce7':'#f3f4f6'; ?>;color:<?php echo $a['status']==='active'?'#166534':'#6b7280'; ?>;padding:2px 10px;border-radius:20px;font-size:.68rem;font-weight:700;white-space:nowrap;"><?php echo ucfirst($a['status']); ?></span>
                </div>
                <p style="font-size:.78rem;color:#6b7280;margin-bottom:10px;"><?php echo htmlspecialchars($a['subject_name']); ?></p>
                <div style="display:flex;gap:14px;font-size:.75rem;color:#9ca3af;margin-bottom:14px;">
                    <span><i class="fas fa-calendar" style="margin-right:4px;"></i><?php echo $a['due_date'] ? date('M d, Y', strtotime($a['due_date'])) : 'No deadline'; ?></span>
                    <span><i class="fas fa-upload" style="margin-right:4px;"></i><?php echo $a['submission_count']; ?> submitted</span>
                </div>
                <div style="display:flex;gap:8px;">
                    <a href="view_submissions.php?task_id=<?php echo $a['id']; ?>" class="btn-primary btn-sm"><i class="fas fa-eye"></i> View Submissions</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="content-card"><div class="no-data"><i class="fas fa-tasks" style="font-size:2rem;color:#d1d5db;display:block;margin-bottom:8px;"></i><p>No assignments yet. <a href="create_assignment.php" style="color:#dc2626;">Create one →</a></p></div></div>
        <?php endif; ?>

        <?php else: ?>
        <!-- STUDENT VIEW -->
        <div class="filter-tabs">
            <button class="tab-btn active" data-filter="all">All</button>
            <button class="tab-btn" data-filter="pending">Pending</button>
            <button class="tab-btn" data-filter="submitted">Submitted</button>
            <button class="tab-btn" data-filter="graded">Graded</button>
        </div>
        
        <div class="assignments-list">
            <?php if ($assignments): ?>
                <?php foreach ($assignments as $assignment): ?>
                    <div class="assignment-card" data-status="<?php echo $assignment['submission_status'] ?: 'pending'; ?>">
                        <div class="assignment-header">
                            <div class="assignment-subject"><?php echo htmlspecialchars($assignment['subject_name']); ?></div>
                            <div class="assignment-status-badge">
                                <?php if ($assignment['submission_status'] == 'submitted'): ?>
                                    <span class="badge-submitted"><i class="fas fa-clock"></i> Submitted</span>
                                <?php elseif ($assignment['submission_status'] == 'graded'): ?>
                                    <span class="badge-graded"><i class="fas fa-star"></i> Graded</span>
                                <?php else: ?>
                                    <span class="badge-pending"><i class="fas fa-hourglass-half"></i> Pending</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="assignment-title"><?php echo htmlspecialchars($assignment['title']); ?></div>
                        <div class="assignment-description"><?php echo nl2br(htmlspecialchars(substr($assignment['description'], 0, 150))); ?><?php echo strlen($assignment['description']) > 150 ? '...' : ''; ?></div>
                        <div class="assignment-meta">
                            <div class="meta-item">
                                <i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($assignment['tutor_name']); ?>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar-alt"></i> 
                                <?php if ($assignment['due_date']): ?>
                                    Due: <?php echo date('M d, Y', strtotime($assignment['due_date'])); ?>
                                <?php else: ?>
                                    No due date
                                <?php endif; ?>
                            </div>
                            <?php if ($assignment['submission_status'] == 'submitted' && $assignment['submitted_at']): ?>
                                <div class="meta-item">
                                    <i class="fas fa-check-circle"></i> Submitted: <?php echo date('M d, Y', strtotime($assignment['submitted_at'])); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($assignment['grade']): ?>
                                <div class="meta-item grade">
                                    <i class="fas fa-star"></i> Grade: <?php echo $assignment['grade']; ?>/100
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($assignment['feedback']): ?>
                            <div class="assignment-feedback">
                                <strong>Feedback:</strong> <?php echo htmlspecialchars($assignment['feedback']); ?>
                            </div>
                        <?php endif; ?>
                        <div class="assignment-actions">
                            <?php if ($assignment['submission_status'] == 'submitted' || $assignment['submission_status'] == 'graded'): ?>
                                <a href="view_submission.php?task_id=<?php echo $assignment['id']; ?>" class="btn-view">View Submission</a>
                            <?php else: ?>
                                <a href="submit_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn-submit">Submit Assignment</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-assignments">
                    <i class="fas fa-tasks"></i>
                    <p>No assignments yet. Check back later!</p>
                </div>
            <?php endif; ?>
        </div><!-- /assignments-list -->

        <?php endif; // end student view ?>

    </div><!-- /page-inner -->
</div><!-- /page-wrapper -->

<script>
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const filter = this.dataset.filter;
        
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        document.querySelectorAll('.assignment-card').forEach(card => {
            const status = card.dataset.status;
            if (filter === 'all' || status === filter) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
});
</script>

<style>
.filter-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.tab-btn {
    padding: 8px 20px;
    background: rgba(255,255,255,0.8);
    border: 1px solid rgba(0,0,0,0.1);
    border-radius: 25px;
    cursor: pointer;
    transition: all 0.3s;
}

.tab-btn:hover, .tab-btn.active {
    background: var(--primary-red);
    color: white;
}

.assignments-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.assignment-card {
    background: rgba(255,255,255,0.88);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    border: 1px solid rgba(255,255,255,0.3);
    transition: all 0.3s;
}

.assignment-card:hover {
    transform: translateX(5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.assignment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    flex-wrap: wrap;
    gap: 10px;
}

.assignment-subject {
    background: linear-gradient(135deg, var(--primary-red), var(--primary-blue));
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}

.badge-submitted, .badge-graded, .badge-pending {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}

.badge-submitted {
    background: rgba(33,150,243,0.2);
    color: #2196F3;
}

.badge-graded {
    background: rgba(255,193,7,0.2);
    color: #FF8F00;
}

.badge-pending {
    background: rgba(255,152,0,0.2);
    color: #FF9800;
}

.assignment-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 10px;
    color: #333;
}

.assignment-description {
    color: #666;
    font-size: 0.85rem;
    margin-bottom: 15px;
    line-height: 1.5;
}

.assignment-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.meta-item {
    font-size: 0.75rem;
    color: #999;
}

.meta-item i {
    margin-right: 4px;
}

.meta-item.grade {
    color: var(--success-green);
    font-weight: 600;
}

.assignment-feedback {
    background: #f5f5f5;
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-size: 0.85rem;
}

.assignment-actions {
    text-align: right;
}

.btn-submit, .btn-view {
    display: inline-block;
    padding: 8px 20px;
    border-radius: 25px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.3s;
}

.btn-submit {
    background: var(--primary-red);
    color: white;
}

.btn-submit:hover {
    background: var(--primary-blue);
    transform: translateY(-2px);
}

.btn-view {
    background: #f0f0f0;
    color: #333;
}

.btn-view:hover {
    background: var(--primary-red);
    color: white;
}

.no-assignments {
    text-align: center;
    padding: 60px;
    background: rgba(255,255,255,0.88);
    border-radius: 15px;
    color: #999;
}

.no-assignments i {
    font-size: 3rem;
    margin-bottom: 15px;
    color: #ccc;
}

@media (max-width: 768px) {
    .assignment-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .assignment-meta {
        flex-direction: column;
        gap: 8px;
    }
}
</style>

<?php include 'footer.php'; ?>