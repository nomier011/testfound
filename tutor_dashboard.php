<?php
require_once 'config.php';
$page_title = 'Tutor Dashboard';

requireLogin();
$user = getCurrentUser();
requireRole('tutor');

$conn = getConnection();

// Handle availability toggle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_availability'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $new_status = isset($_POST['is_available']) ? 1 : 0;
        $stmt = $conn->prepare("UPDATE users SET is_available = ? WHERE id = ?");
        $stmt->execute([$new_status, $user['id']]);
        setFlash("Your availability has been updated!", 'success');
        logActivity($user['id'], 'toggle_availability', "Set availability to " . ($new_status ? 'available' : 'unavailable'));
    }
    redirect('tutor_dashboard.php');
}

// Get statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_requests,
        COUNT(CASE WHEN status = 'approved' AND booking_date >= CURDATE() THEN 1 END) as upcoming_sessions,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_sessions,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_earnings
    FROM bookings WHERE tutor_id = ?
");
$stmt->execute([$user['id']]);
$stats = $stmt->fetch();

// Get recent ratings
$stmt = $conn->prepare("
    SELECT r.rating, r.feedback, r.created_at, u.full_name as student_name
    FROM ratings r
    JOIN bookings b ON r.booking_id = b.id
    JOIN users u ON r.student_id = u.id
    WHERE b.tutor_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->execute([$user['id']]);
$recent_ratings = $stmt->fetchAll();

$avg_rating = 0;
if ($recent_ratings) {
    $stmt = $conn->prepare("SELECT AVG(rating) as avg FROM ratings r JOIN bookings b ON r.booking_id = b.id WHERE b.tutor_id = ?");
    $stmt->execute([$user['id']]);
    $avg_rating = round($stmt->fetch()['avg'] ?? 0, 1);
}

// Get pending requests
$stmt = $conn->prepare("
    SELECT b.*, s.name as subject_name, u.full_name as student_name, u.profile_pic
    FROM bookings b
    JOIN subjects s ON b.subject_id = s.id
    JOIN users u ON b.student_id = u.id
    WHERE b.tutor_id = ? AND b.status = 'pending'
    ORDER BY b.created_at ASC
");
$stmt->execute([$user['id']]);
$pending_requests = $stmt->fetchAll();

// Get upcoming sessions
$stmt = $conn->prepare("
    SELECT b.*, s.name as subject_name, u.full_name as student_name
    FROM bookings b
    JOIN subjects s ON b.subject_id = s.id
    JOIN users u ON b.student_id = u.id
    WHERE b.tutor_id = ? AND b.status = 'approved' AND b.booking_date >= CURDATE()
    ORDER BY b.booking_date ASC, b.start_time ASC
    LIMIT 10
");
$stmt->execute([$user['id']]);
$upcoming_sessions = $stmt->fetchAll();

// Get active assignments
$stmt = $conn->prepare("
    SELECT t.*, s.name as subject_name,
           (SELECT COUNT(*) FROM task_submissions WHERE task_id = t.id) as submissions_count
    FROM tasks t
    JOIN subjects s ON t.subject_id = s.id
    WHERE t.tutor_id = ? AND t.status = 'active'
    ORDER BY t.due_date ASC
    LIMIT 5
");
$stmt->execute([$user['id']]);
$active_assignments = $stmt->fetchAll();
?>

<?php include 'header.php'; ?>

<div class="page-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="page-hero">
        <div class="page-hero-content" style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:16px;">
            <div>
                <h1>Welcome back, <?php echo htmlspecialchars(explode(' ',$user['full_name'])[0]); ?>!</h1>
                <p>Here's your teaching dashboard and performance overview.</p>
            </div>
            <!-- Availability toggle -->
            <form method="POST" style="display:flex;align-items:center;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                <input type="hidden" name="toggle_availability" value="1">
                <label style="position:relative;display:inline-block;width:48px;height:26px;cursor:pointer;">
                    <input type="checkbox" name="is_available" value="1" onchange="this.form.submit()"
                           <?php echo ($user['is_available'] ?? 1) ? 'checked' : ''; ?>
                           style="opacity:0;width:0;height:0;">
                    <span style="position:absolute;inset:0;background:<?php echo ($user['is_available']??1)?'#10b981':'#9ca3af';?>;border-radius:34px;transition:.3s;">
                        <span style="position:absolute;width:20px;height:20px;background:#fff;border-radius:50%;top:3px;left:<?php echo ($user['is_available']??1)?'25px':'3px';?>;transition:.3s;"></span>
                    </span>
                </label>
                <span style="font-size:.82rem;font-weight:600;color:<?php echo ($user['is_available']??1)?'#10b981':'rgba(255,255,255,.6)';?>;">
                    <?php echo ($user['is_available']??1)?'Available':'Unavailable'; ?>
                </span>
            </form>
        </div>
    </div>

    <div class="page-inner">

        <!-- Stats -->
        <div class="stats-grid" style="grid-template-columns:repeat(5,1fr);">
            <div class="stat-card" onclick="location.href='my_bookings.php'" style="cursor:pointer;">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div><div class="stat-number"><?php echo $stats['pending_requests']??0; ?></div><div class="stat-label">Pending</div></div>
            </div>
            <div class="stat-card" onclick="location.href='my_bookings.php'" style="cursor:pointer;">
                <div class="stat-icon" style="background:rgba(16,185,129,.1);color:#10b981;"><i class="fas fa-calendar-check"></i></div>
                <div><div class="stat-number"><?php echo $stats['upcoming_sessions']??0; ?></div><div class="stat-label">Upcoming</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(59,130,246,.1);color:#3b82f6;"><i class="fas fa-check-circle"></i></div>
                <div><div class="stat-number"><?php echo $stats['completed_sessions']??0; ?></div><div class="stat-label">Completed</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(245,158,11,.1);color:#f59e0b;"><i class="fas fa-star"></i></div>
                <div><div class="stat-number"><?php echo $avg_rating ?: '—'; ?></div><div class="stat-label">Avg Rating</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(16,185,129,.1);color:#10b981;"><i class="fas fa-peso-sign"></i></div>
                <div><div class="stat-number">₱<?php echo number_format($stats['total_earnings']??0,0); ?></div><div class="stat-label">Earnings</div></div>
            </div>
        </div>

        <!-- Pending + Upcoming grid -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">

            <!-- Pending Requests -->
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> Pending Requests</h3>
                    <?php if (count($pending_requests)>0): ?>
                        <span style="background:#dc2626;color:#fff;border-radius:20px;padding:2px 10px;font-size:.72rem;font-weight:700;"><?php echo count($pending_requests); ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($pending_requests): ?>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <?php foreach ($pending_requests as $r): ?>
                    <div style="display:flex;align-items:center;gap:12px;padding:12px;background:#f9fafb;border-radius:10px;">
                        <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#dc2626,#2563eb);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:.9rem;flex-shrink:0;overflow:hidden;">
                            <?php if ($r['profile_pic']): ?><img src="uploads/profiles/<?php echo htmlspecialchars($r['profile_pic']); ?>" style="width:100%;height:100%;object-fit:cover;"><?php else: ?><?php echo strtoupper(substr($r['student_name'],0,1)); ?><?php endif; ?>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:.85rem;font-weight:600;color:#111;"><?php echo htmlspecialchars($r['student_name']); ?></div>
                            <div style="font-size:.75rem;color:#6b7280;"><?php echo htmlspecialchars($r['subject_name']); ?> · <?php echo date('M d', strtotime($r['booking_date'])); ?> <?php echo date('h:i A', strtotime($r['start_time'])); ?></div>
                        </div>
                        <div style="display:flex;gap:6px;flex-shrink:0;">
                            <form method="POST" action="update_booking_status.php">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                                <input type="hidden" name="booking_id" value="<?php echo $r['id']; ?>">
                                <input type="hidden" name="status" value="approved">
                                <button type="submit" style="padding:5px 12px;background:#10b981;color:#fff;border:none;border-radius:6px;font-size:.72rem;font-weight:700;cursor:pointer;">✓ Approve</button>
                            </form>
                            <form method="POST" action="update_booking_status.php">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                                <input type="hidden" name="booking_id" value="<?php echo $r['id']; ?>">
                                <input type="hidden" name="status" value="rejected">
                                <button type="submit" style="padding:5px 12px;background:#ef4444;color:#fff;border:none;border-radius:6px;font-size:.72rem;font-weight:700;cursor:pointer;">✗ Decline</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="no-data"><i class="fas fa-check-circle" style="font-size:2rem;color:#10b981;margin-bottom:8px;display:block;"></i><p>No pending requests</p></div>
                <?php endif; ?>
            </div>

            <!-- Upcoming Sessions -->
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-alt"></i> Upcoming Sessions</h3>
                    <a href="my_bookings.php" class="view-all">View All →</a>
                </div>
                <?php if ($upcoming_sessions): ?>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php foreach ($upcoming_sessions as $s): ?>
                    <div style="display:flex;align-items:center;gap:12px;padding:10px;background:#f9fafb;border-radius:10px;">
                        <div style="text-align:center;background:#dc2626;color:#fff;border-radius:10px;padding:6px 10px;min-width:52px;flex-shrink:0;">
                            <span style="font-size:1.1rem;font-weight:800;display:block;line-height:1;"><?php echo date('d',strtotime($s['booking_date'])); ?></span>
                            <span style="font-size:.62rem;text-transform:uppercase;"><?php echo date('M',strtotime($s['booking_date'])); ?></span>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:.85rem;font-weight:600;color:#111;"><?php echo htmlspecialchars($s['student_name']); ?></div>
                            <div style="font-size:.75rem;color:#6b7280;"><?php echo htmlspecialchars($s['subject_name']); ?></div>
                            <div style="font-size:.72rem;color:#9ca3af;"><?php echo date('h:i A',strtotime($s['start_time'])); ?> – <?php echo date('h:i A',strtotime($s['end_time'])); ?></div>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:5px;align-items:flex-end;flex-shrink:0;">
                            <span style="background:#dcfce7;color:#166534;padding:3px 10px;border-radius:20px;font-size:.68rem;font-weight:700;">Approved</span>
                            <a href="set_meet_link.php?booking_id=<?php echo $s['id']; ?>"
                               style="background:#dc2626;color:#fff;padding:5px 12px;border-radius:6px;font-size:.72rem;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:5px;">
                                <i class="fas fa-video"></i> <?php echo !empty($s['meet_link']) ? 'Update Link' : 'Share Meet Link'; ?>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="no-data"><i class="fas fa-calendar-times" style="font-size:2rem;color:#d1d5db;margin-bottom:8px;display:block;"></i><p>No upcoming sessions</p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Active Assignments -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-tasks"></i> Active Assignments</h3>
                <a href="create_assignment.php" class="view-all">+ Create New</a>
            </div>
            <?php if ($active_assignments): ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;">
                <?php foreach ($active_assignments as $a): ?>
                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:16px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;gap:8px;">
                        <h4 style="font-size:.875rem;font-weight:700;color:#111;"><?php echo htmlspecialchars($a['title']); ?></h4>
                        <span style="background:#e5e7eb;padding:2px 8px;border-radius:20px;font-size:.68rem;white-space:nowrap;"><?php echo htmlspecialchars($a['subject_name']); ?></span>
                    </div>
                    <div style="display:flex;gap:14px;font-size:.75rem;color:#6b7280;margin-bottom:12px;">
                        <span><i class="fas fa-calendar" style="margin-right:4px;"></i><?php echo $a['due_date'] ? date('M d, Y',strtotime($a['due_date'])) : 'No deadline'; ?></span>
                        <span><i class="fas fa-upload" style="margin-right:4px;"></i><?php echo $a['submissions_count']; ?> submitted</span>
                    </div>
                    <a href="view_submissions.php?task_id=<?php echo $a['id']; ?>" class="btn-primary btn-sm" style="display:inline-flex;">View Submissions</a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="no-data"><i class="fas fa-tasks" style="font-size:2rem;color:#d1d5db;margin-bottom:8px;display:block;"></i><p>No active assignments. <a href="create_assignment.php" style="color:#dc2626;">Create one →</a></p></div>
            <?php endif; ?>
        </div>

        <!-- Recent Ratings -->
        <?php if ($recent_ratings): ?>
        <div class="content-card">
            <div class="card-header"><h3><i class="fas fa-star"></i> Recent Feedback</h3></div>
            <div style="display:flex;flex-direction:column;gap:16px;">
                <?php foreach ($recent_ratings as $rating): ?>
                <div style="display:flex;gap:14px;padding-bottom:16px;border-bottom:1px solid #f3f4f6;">
                    <div style="flex-shrink:0;">
                        <?php for($i=1;$i<=5;$i++): ?>
                            <i class="fas fa-star" style="color:<?php echo $i<=$rating['rating']?'#f59e0b':'#e5e7eb';?>;font-size:.8rem;"></i>
                        <?php endfor; ?>
                    </div>
                    <div>
                        <p style="font-size:.85rem;font-style:italic;color:#374151;margin-bottom:4px;">"<?php echo htmlspecialchars($rating['feedback']?:'No comment.'); ?>"</p>
                        <small style="font-size:.72rem;color:#9ca3af;">— <?php echo htmlspecialchars($rating['student_name']); ?> · <?php echo date('M d, Y',strtotime($rating['created_at'])); ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /page-inner -->
</div><!-- /page-wrapper -->

<?php include 'footer.php'; ?>