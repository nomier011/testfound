<?php
require_once 'config.php';
$page_title = 'Student Dashboard';

requireLogin();
$user = getCurrentUser();
requireRole('student');

$conn = getConnection();

// Get statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_bookings,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_bookings,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_bookings,
        COUNT(CASE WHEN payment_status = 'pending' AND status = 'approved' THEN 1 END) as pending_payments
    FROM bookings WHERE student_id = ?
");
$stmt->execute([$user['id']]);
$stats = $stmt->fetch();

// Get upcoming sessions
$stmt = $conn->prepare("
    SELECT b.*, s.name as subject_name, u.full_name as tutor_name, u.profile_pic
    FROM bookings b
    JOIN subjects s ON b.subject_id = s.id
    JOIN users u ON b.tutor_id = u.id
    WHERE b.student_id = ? AND b.status = 'approved' AND b.booking_date >= CURDATE()
    ORDER BY b.booking_date ASC, b.start_time ASC
    LIMIT 5
");
$stmt->execute([$user['id']]);
$upcoming_sessions = $stmt->fetchAll();

// Get pending assignments
$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM tasks t
    WHERE t.status = 'active' 
    AND NOT EXISTS (SELECT 1 FROM task_submissions ts WHERE ts.task_id = t.id AND ts.student_id = ?)
");
$stmt->execute([$user['id']]);
$pending_assignments = $stmt->fetch()['count'];

// Get module progress
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT m.id) as total, COUNT(CASE WHEN mp.status = 'completed' THEN 1 END) as completed
    FROM modules m
    LEFT JOIN module_progress mp ON mp.module_id = m.id AND mp.user_id = ?
    WHERE m.status = 'published'
");
$stmt->execute([$user['id']]);
$module_progress = $stmt->fetch();

// Get recommended tutors
$stmt = $conn->prepare("
    SELECT u.id, u.full_name, u.hourly_rate, u.profile_pic, u.expertise,
           ROUND(AVG(r.rating), 1) as avg_rating,
           COUNT(DISTINCT b.id) as session_count
    FROM users u
    LEFT JOIN bookings b ON b.tutor_id = u.id AND b.status = 'completed'
    LEFT JOIN ratings r ON r.booking_id = b.id
    WHERE u.role = 'tutor' AND u.status = 'approved' AND u.is_available = 1
    GROUP BY u.id
    ORDER BY avg_rating DESC, session_count DESC
    LIMIT 4
");
$stmt->execute();
$recommended_tutors = $stmt->fetchAll();
?>

<?php include 'header.php'; ?>

<div class="page-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="page-hero">
        <div class="page-hero-content">
            <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $user['full_name'])[0]); ?>!</h1>
            <p>Ready to learn something new today? Here's your learning journey summary.</p>
        </div>
    </div>

    <div class="page-inner">
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card" onclick="location.href='my_bookings.php?filter=pending'">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="stat-number"><?php echo $stats['pending_bookings'] ?? 0; ?></div>
                    <div class="stat-label">Pending Approval</div>
                </div>
            </div>
            <div class="stat-card" onclick="location.href='my_bookings.php?filter=approved'">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="stat-number"><?php echo $stats['approved_bookings'] ?? 0; ?></div>
                    <div class="stat-label">Approved Sessions</div>
                </div>
            </div>
            <div class="stat-card" onclick="location.href='my_bookings.php?filter=completed'">
                <div class="stat-icon"><i class="fas fa-check-double"></i></div>
                <div>
                    <div class="stat-number"><?php echo $stats['completed_bookings'] ?? 0; ?></div>
                    <div class="stat-label">Sessions Completed</div>
                </div>
            </div>
            <div class="stat-card" onclick="location.href='view_payments.php'">
                <div class="stat-icon"><i class="fas fa-credit-card"></i></div>
                <div>
                    <div class="stat-number">₱<?php echo number_format($stats['pending_payments'] * 500, 2); ?></div>
                    <div class="stat-label">Pending Payment</div>
                </div>
            </div>
        </div>
        
        <div class="content-grid">
            <!-- Upcoming Sessions -->
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-alt"></i> Upcoming Sessions</h3>
                    <a href="my_bookings.php" class="view-all">View All →</a>
                </div>
                <?php if ($upcoming_sessions): ?>
                    <div class="sessions-list">
                        <?php foreach ($upcoming_sessions as $session): ?>
                            <div class="session-item">
                                <div class="session-date">
                                    <span class="day"><?php echo date('d', strtotime($session['booking_date'])); ?></span>
                                    <span class="month"><?php echo date('M', strtotime($session['booking_date'])); ?></span>
                                </div>
                                <div class="session-info">
                                    <h4><?php echo htmlspecialchars($session['subject_name']); ?></h4>
                                    <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($session['tutor_name']); ?></p>
                                    <p><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($session['start_time'])); ?> - <?php echo date('h:i A', strtotime($session['end_time'])); ?></p>
                                </div>
                                <div class="session-actions">
                                    <span class="status-badge status-approved">Approved</span>
                                    <?php if ($session['payment_status'] == 'pending'): ?>
                                        <a href="payment.php?booking_id=<?php echo $session['id']; ?>" class="btn-sm btn-primary">Pay Now</a>
                                    <?php endif; ?>
                                    <?php if ($session['payment_status'] == 'paid'): ?>
                                    <?php if ($session['meet_link']): ?>
                                    <a href="<?php echo htmlspecialchars($session['meet_link']); ?>" target="_blank"
                                       style="background:#dc2626;color:#fff;padding:5px 12px;border-radius:6px;font-size:.72rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:5px;margin-top:4px;">
                                        <i class="fas fa-video"></i> Join Meeting
                                    </a>
                                    <?php else: ?>
                                    <span style="font-size:.7rem;color:#9ca3af;display:block;margin-top:4px;"><i class="fas fa-clock"></i> Waiting for tutor's link</span>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-calendar-times"></i>
                        <p>No upcoming sessions. <a href="book_session.php">Book a tutor →</a></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Learning Progress -->
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Learning Progress</h3>
                    <a href="modules.php" class="view-all">Continue Learning →</a>
                </div>
                <div class="progress-stats">
                    <div class="progress-circle">
                        <svg viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="45" fill="none" stroke="#e5e7eb" stroke-width="8"/>
                            <circle cx="50" cy="50" r="45" fill="none" stroke="#dc2626" stroke-width="8" 
                                    stroke-dasharray="<?php echo ($module_progress['completed'] / max(1, $module_progress['total'])) * 283; ?> 283"
                                    stroke-linecap="round" transform="rotate(-90 50 50)"/>
                        </svg>
                        <div class="progress-percent">
                            <?php echo $module_progress['total'] > 0 ? round(($module_progress['completed'] / $module_progress['total']) * 100) : 0; ?>%
                        </div>
                    </div>
                    <div class="progress-details">
                        <div class="progress-item">
                            <span class="progress-label">Modules Completed</span>
                            <span class="progress-value"><?php echo $module_progress['completed']; ?> / <?php echo $module_progress['total']; ?></span>
                        </div>
                        <div class="progress-item">
                            <span class="progress-label">Assignments Due</span>
                            <span class="progress-value"><?php echo $pending_assignments; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recommended Tutors -->
        <?php if ($recommended_tutors): ?>
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-chalkboard-teacher"></i> Recommended Tutors For You</h3>
                <a href="book_session.php" class="view-all">View All Tutors →</a>
            </div>
            <div class="tutors-grid">
                <?php foreach ($recommended_tutors as $tutor): ?>
                    <div class="tutor-card">
                        <div class="tutor-avatar">
                            <?php if ($tutor['profile_pic']): ?>
                                <img src="uploads/profiles/<?php echo htmlspecialchars($tutor['profile_pic']); ?>" alt="<?php echo htmlspecialchars($tutor['full_name']); ?>">
                            <?php else: ?>
                                <?php echo strtoupper(substr($tutor['full_name'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <h3><?php echo htmlspecialchars($tutor['full_name']); ?></h3>
                        <div class="tutor-expertise"><?php echo htmlspecialchars(substr($tutor['expertise'] ?? 'General Tutor', 0, 50)); ?></div>
                        <div class="tutor-stats">
                            <div class="stat">
                                <i class="fas fa-star"></i>
                                <span><?php echo $tutor['avg_rating'] ? number_format($tutor['avg_rating'], 1) : 'New'; ?></span>
                            </div>
                            <div class="stat">
                                <i class="fas fa-peso-sign"></i>
                                <span>₱<?php echo number_format($tutor['hourly_rate'], 2); ?>/hr</span>
                            </div>
                            <div class="stat">
                                <i class="fas fa-users"></i>
                                <span><?php echo $tutor['session_count']; ?> sessions</span>
                            </div>
                        </div>
                        <a href="book_session.php?tutor=<?php echo $tutor['id']; ?>" class="btn-primary btn-sm">Book Session →</a>
                        <a href="view_profile.php?id=<?php echo $tutor['id']; ?>" style="display:block;text-align:center;font-size:.72rem;color:#dc2626;margin-top:6px;text-decoration:none;">View Profile</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div><!-- /page-inner -->
</div><!-- /page-wrapper -->

<style>
.welcome-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}
.welcome-stats {
    display: flex;
    gap: 12px;
}
.stat-chip {
    background: rgba(220, 38, 38, 0.1);
    padding: 8px 16px;
    border-radius: 40px;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--primary-red);
}
.stat-chip i {
    margin-right: 6px;
}
.content-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}
.sessions-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.session-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 12px;
    background: rgba(0,0,0,0.02);
    border-radius: 12px;
    transition: all 0.2s;
}
.session-item:hover {
    background: rgba(0,0,0,0.04);
}
.session-date {
    text-align: center;
    background: var(--primary-red);
    color: white;
    border-radius: 12px;
    padding: 8px 12px;
    min-width: 60px;
}
.session-date .day {
    font-size: 1.25rem;
    font-weight: 700;
    display: block;
    line-height: 1;
}
.session-date .month {
    font-size: 0.7rem;
    text-transform: uppercase;
}
.session-info {
    flex: 1;
}
.session-info h4 {
    font-size: 0.95rem;
    margin-bottom: 4px;
}
.session-info p {
    font-size: 0.75rem;
    color: var(--gray-500);
    margin: 2px 0;
}
.session-info p i {
    width: 16px;
    margin-right: 4px;
}
.session-actions {
    text-align: right;
}
.progress-stats {
    display: flex;
    align-items: center;
    gap: 24px;
    padding: 16px 0;
}
.progress-circle {
    position: relative;
    width: 120px;
    height: 120px;
}
.progress-circle svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}
.progress-percent {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary-red);
}
.progress-details {
    flex: 1;
}
.progress-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid var(--gray-200);
}
.progress-item:last-child {
    border-bottom: none;
}
.progress-label {
    color: var(--gray-600);
}
.progress-value {
    font-weight: 600;
    color: var(--gray-800);
}
.tutor-card {
    text-align: center;
    padding: 20px;
    background: rgba(255,255,255,0.05);
    border-radius: 16px;
    transition: all 0.2s;
}
.tutor-card:hover {
    transform: translateY(-4px);
    background: rgba(255,255,255,0.1);
}
.tutor-avatar {
    width: 80px;
    height: 80px;
    margin: 0 auto 12px;
    background: linear-gradient(135deg, var(--primary-red), var(--primary-blue));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary-gold);
    overflow: hidden;
}
.tutor-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.tutor-expertise {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin: 8px 0;
}
.tutor-stats {
    display: flex;
    justify-content: space-around;
    margin: 16px 0;
    padding: 12px 0;
    border-top: 1px solid var(--gray-200);
    border-bottom: 1px solid var(--gray-200);
}
.tutor-stats .stat {
    text-align: center;
    font-size: 0.75rem;
}
.tutor-stats .stat i {
    display: block;
    margin-bottom: 4px;
    color: var(--primary-red);
}
@media (max-width: 768px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    .welcome-banner {
        flex-direction: column;
        text-align: center;
    }
    .session-item {
        flex-wrap: wrap;
    }
    .session-actions {
        width: 100%;
        text-align: center;
    }
    .progress-stats {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<?php include 'footer.php'; ?>