<?php
require_once 'config.php';
$page_title = 'Student Dashboard';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
if ($user['role'] != 'student') {
    redirect('dashboard.php');
}

$conn = getConnection();

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE student_id = ? AND status = 'pending'");
$stmt->execute([$user['id']]);
$pending_count = $stmt->fetch()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE student_id = ? AND status = 'approved'");
$stmt->execute([$user['id']]);
$approved_count = $stmt->fetch()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE student_id = ? AND status = 'completed'");
$stmt->execute([$user['id']]);
$completed_count = $stmt->fetch()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE student_id = ? AND payment_status = 'pending'");
$stmt->execute([$user['id']]);
$pending_payment = $stmt->fetch()['count'];

// Get upcoming sessions
$stmt = $conn->prepare("
    SELECT b.*, s.name as subject_name, u.full_name as tutor_name, u.profile_pic as tutor_pic
    FROM bookings b
    JOIN subjects s ON b.subject_id = s.id
    JOIN users u ON b.tutor_id = u.id
    WHERE b.student_id = ? AND b.status = 'approved'
    ORDER BY b.booking_date ASC LIMIT 5
");
$stmt->execute([$user['id']]);
$upcoming_sessions = $stmt->fetchAll();

// Get pending assignments count
$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM tasks t
    WHERE t.status = 'active' AND NOT EXISTS (
        SELECT 1 FROM task_submissions ts 
        WHERE ts.task_id = t.id AND ts.student_id = ?
    )
");
$stmt->execute([$user['id']]);
$pending_assignments = $stmt->fetch()['count'];

// Get recent assignments
$stmt = $conn->prepare("
    SELECT t.*, s.name as subject_name,
           (SELECT status FROM task_submissions WHERE task_id = t.id AND student_id = ?) as submission_status
    FROM tasks t
    JOIN subjects s ON t.subject_id = s.id
    WHERE t.status = 'active'
    ORDER BY t.due_date ASC LIMIT 3
");
$stmt->execute([$user['id']]);
$recent_assignments = $stmt->fetchAll();

// Get available tutors
$stmt = $conn->prepare("
    SELECT u.*, 
           (SELECT AVG(rating) FROM ratings r 
            JOIN bookings b ON r.booking_id = b.id 
            WHERE b.tutor_id = u.id) as avg_rating
    FROM users u
    WHERE u.role = 'tutor' AND u.is_available = 1
    LIMIT 3
");
$stmt->execute();
$available_tutors = $stmt->fetchAll();
?>

<?php include 'header.php'; ?>

<div class="dashboard-container">
    <!-- Left Sidebar - All Buttons Here -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="images/scclogo.png" alt="SCC Logo">
            <h3>Student Menu</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="student_dashboard.php" class="sidebar-link active">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="book_session.php" class="sidebar-link">
                <i class="fas fa-calendar-plus"></i> Book a Tutor
            </a>
            <a href="my_bookings.php" class="sidebar-link">
                <i class="fas fa-list-alt"></i> My Bookings
                <?php if ($pending_count > 0): ?>
                    <span class="badge-count"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="my_assignments.php" class="sidebar-link">
                <i class="fas fa-tasks"></i> My Assignments
                <?php if ($pending_assignments > 0): ?>
                    <span class="badge-count"><?php echo $pending_assignments; ?></span>
                <?php endif; ?>
            </a>
            <a href="profile.php" class="sidebar-link">
                <i class="fas fa-user-circle"></i> Profile
            </a>
            <a href="logout.php" class="sidebar-link logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
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
                    <div class="user-role">Student • Year <?php echo $user['year_level']; ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Area -->
    <div class="main-content">
        <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="welcome-banner">
            <h1>Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>! 👋</h1>
            <p>Ready to learn something new today?</p>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="stat-number"><?php echo $pending_count; ?></div>
                    <div>Pending Approval</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="stat-number"><?php echo $approved_count; ?></div>
                    <div>Approved</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-double"></i></div>
                <div>
                    <div class="stat-number"><?php echo $completed_count; ?></div>
                    <div>Completed</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-credit-card"></i></div>
                <div>
                    <div class="stat-number"><?php echo $pending_payment; ?></div>
                    <div>Pending Payment</div>
                </div>
            </div>
        </div>
        
        <!-- Pending Assignments Section -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-tasks"></i> Pending Assignments</h3>
                <a href="my_assignments.php" class="view-all">View All</a>
            </div>
            <?php if ($recent_assignments): ?>
                <?php foreach ($recent_assignments as $assignment): ?>
                    <div class="assignment-item">
                        <div class="assignment-info">
                            <div class="assignment-subject"><?php echo htmlspecialchars($assignment['subject_name']); ?></div>
                            <div class="assignment-title"><?php echo htmlspecialchars($assignment['title']); ?></div>
                            <div class="assignment-due">
                                <i class="fas fa-calendar-alt"></i> 
                                Due: <?php echo $assignment['due_date'] ? date('M d, Y', strtotime($assignment['due_date'])) : 'No due date'; ?>
                            </div>
                        </div>
                        <div class="assignment-status">
                            <?php if ($assignment['submission_status'] == 'submitted'): ?>
                                <span class="status-submitted"><i class="fas fa-check-circle"></i> Submitted</span>
                            <?php elseif ($assignment['submission_status'] == 'graded'): ?>
                                <span class="status-graded"><i class="fas fa-star"></i> Graded</span>
                            <?php else: ?>
                                <a href="submit_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn-submit">Submit</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">No pending assignments. Great job!</div>
            <?php endif; ?>
        </div>
        
        <!-- Upcoming Sessions -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-alt"></i> Upcoming Sessions</h3>
                <a href="my_bookings.php" class="view-all">View All</a>
            </div>
            <?php if ($upcoming_sessions): ?>
                <?php foreach ($upcoming_sessions as $session): ?>
                    <div class="session-item">
                        <div class="session-info">
                            <strong><?php echo htmlspecialchars($session['subject_name']); ?></strong>
                            <div>with <?php echo htmlspecialchars($session['tutor_name']); ?></div>
                            <small><?php echo date('M d, Y', strtotime($session['booking_date'])); ?> at <?php echo date('h:i A', strtotime($session['start_time'])); ?></small>
                        </div>
                        <span class="status-badge status-approved">Approved</span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">No upcoming sessions. <a href="book_session.php">Book a tutor!</a></div>
            <?php endif; ?>
        </div>
        
        <!-- Recommended Tutors -->
        <?php if ($available_tutors): ?>
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-chalkboard-teacher"></i> Recommended Tutors</h3>
                <a href="book_session.php" class="view-all">View All</a>
            </div>
            <div class="tutors-grid">
                <?php foreach ($available_tutors as $tutor): ?>
                    <div class="tutor-card">
                        <div class="tutor-avatar">
                            <?php if ($tutor['profile_pic']): ?>
                                <img src="uploads/<?php echo $tutor['profile_pic']; ?>">
                            <?php else: ?>
                                <?php echo substr($tutor['full_name'], 0, 1); ?>
                            <?php endif; ?>
                        </div>
                        <h4><?php echo htmlspecialchars($tutor['full_name']); ?></h4>
                        <div class="tutor-rate">₱<?php echo number_format($tutor['hourly_rate'], 2); ?>/hr</div>
                        <?php if ($tutor['avg_rating']): ?>
                            <div class="tutor-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= round($tutor['avg_rating'])): ?>
                                        <i class="fas fa-star"></i>
                                    <?php else: ?>
                                        <i class="far fa-star"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <span>(<?php echo number_format($tutor['avg_rating'], 1); ?>)</span>
                            </div>
                        <?php endif; ?>
                        <a href="book_session.php?tutor=<?php echo $tutor['id']; ?>" class="book-btn">Book Now</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Sidebar */
.sidebar {
    width: 280px;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(12px);
    color: white;
    position: fixed;
    left: 0;
    top: 0;
    height: 100%;
    overflow-y: auto;
    z-index: 100;
    transition: all 0.3s;
    border-right: 1px solid rgba(255,255,255,0.1);
}

.sidebar-header {
    padding: 25px;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.sidebar-header img {
    max-width: 120px;
    margin-bottom: 10px;
}

.sidebar-header h3 {
    font-size: 1.2rem;
    color: white;
}

.sidebar-nav {
    padding: 20px 0;
}

.sidebar-link {
    display: flex;
    align-items: center;
    padding: 12px 25px;
    color: white;
    text-decoration: none;
    transition: all 0.3s;
    position: relative;
}

.sidebar-link:hover, .sidebar-link.active {
    background: rgba(139,0,0,0.8);
}

.sidebar-link i {
    width: 25px;
    margin-right: 12px;
    font-size: 1.2rem;
}

.badge-count {
    background: var(--primary-red);
    color: white;
    border-radius: 50%;
    padding: 2px 8px;
    font-size: 0.7rem;
    margin-left: auto;
}

.sidebar-link.logout {
    margin-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
    padding-top: 20px;
}

.sidebar-link.logout:hover {
    background: var(--danger-red);
}

.sidebar-footer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20px 25px;
    border-top: 1px solid rgba(255,255,255,0.1);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, var(--primary-red), var(--primary-blue));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    border: 2px solid var(--primary-gold);
    overflow: hidden;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-name {
    font-weight: 600;
    font-size: 0.95rem;
    color: white;
}

.user-role {
    font-size: 0.8rem;
    opacity: 0.8;
    color: rgba(255,255,255,0.8);
}

/* Main Content */
.main-content {
    margin-left: 280px;
    padding: 20px;
    width: 100%;
    position: relative;
    z-index: 10;
}

.menu-toggle {
    display: none;
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 101;
    background: var(--primary-red);
    color: white;
    border: none;
    padding: 10px;
    border-radius: 8px;
    cursor: pointer;
}

.welcome-banner {
    background: rgba(255,255,255,0.88);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 25px 30px;
    margin-bottom: 25px;
    border: 1px solid rgba(255,255,255,0.3);
}

.welcome-banner h1 {
    color: var(--primary-red);
    margin-bottom: 5px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: rgba(255,255,255,0.88);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    border: 1px solid rgba(255,255,255,0.3);
}

.stat-icon {
    font-size: 2rem;
    width: 50px;
    height: 50px;
    background: rgba(139,0,0,0.1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: bold;
    color: var(--primary-red);
}

.content-card {
    background: rgba(255,255,255,0.88);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid rgba(255,255,255,0.3);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--primary-red);
}

.card-header h3 {
    color: #333;
}

.view-all {
    color: var(--primary-red);
    font-size: 0.85rem;
    text-decoration: none;
}

/* Assignment Item */
.assignment-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}

.assignment-item:last-child {
    border-bottom: none;
}

.assignment-subject {
    font-size: 0.7rem;
    color: var(--primary-red);
    font-weight: 600;
    text-transform: uppercase;
}

.assignment-title {
    font-weight: 600;
    margin: 5px 0;
}

.assignment-due {
    font-size: 0.7rem;
    color: #999;
}

.assignment-status {
    text-align: right;
}

.btn-submit {
    background: var(--primary-red);
    color: white;
    padding: 6px 15px;
    border-radius: 20px;
    text-decoration: none;
    font-size: 0.75rem;
}

.status-submitted, .status-graded {
    font-size: 0.75rem;
    font-weight: 500;
}

.status-submitted {
    color: var(--success-green);
}

.status-graded {
    color: #FF9800;
}

/* Session Item */
.session-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}

.session-item:last-child {
    border-bottom: none;
}

.session-info strong {
    display: block;
}

.session-info div {
    font-size: 0.85rem;
    color: #666;
}

.session-info small {
    font-size: 0.7rem;
    color: #999;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}

.status-approved {
    background: #4CAF50;
    color: white;
}

/* Tutors Grid */
.tutors-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 10px;
}

.tutor-card {
    background: white;
    border-radius: 12px;
    padding: 15px;
    text-align: center;
    border: 1px solid #eee;
}

.tutor-avatar {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--primary-red), var(--primary-blue));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: var(--primary-gold);
    margin: 0 auto 10px;
    border: 2px solid var(--primary-gold);
    overflow: hidden;
}

.tutor-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.tutor-card h4 {
    margin-bottom: 5px;
}

.tutor-rate {
    color: var(--success-green);
    font-weight: bold;
}

.tutor-rating {
    color: #FFC107;
    font-size: 0.7rem;
    margin-bottom: 10px;
}

.book-btn {
    display: inline-block;
    background: var(--primary-red);
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
    text-decoration: none;
    font-size: 0.7rem;
}

.no-data {
    text-align: center;
    padding: 20px;
    color: #999;
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar {
        left: -280px;
    }
    
    .sidebar.active {
        left: 0;
    }
    
    .main-content {
        margin-left: 0;
        padding: 15px;
    }
    
    .menu-toggle {
        display: block;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .assignment-item {
        flex-direction: column;
        text-align: center;
        gap: 10px;
    }
    
    .session-item {
        flex-direction: column;
        text-align: center;
        gap: 10px;
    }
    
    .assignment-status {
        text-align: center;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .tutors-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'footer.php'; ?>