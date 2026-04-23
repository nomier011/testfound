<?php
require_once 'config.php';
$page_title = 'Tutor Dashboard';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
if ($user['role'] != 'tutor') {
    redirect('dashboard.php');
}

$conn = getConnection();

// Handle availability toggle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_availability'])) {
    $new_status = $_POST['is_available'] == '1' ? 1 : 0;
    $stmt = $conn->prepare("UPDATE users SET is_available = ? WHERE id = ?");
    $stmt->execute([$new_status, $user['id']]);
    setFlash("Your availability has been updated!", 'success');
    redirect('tutor_dashboard.php');
}

// Get pending approval requests
$stmt = $conn->prepare("
    SELECT b.*, s.name as subject_name, 
           u.full_name as student_name, u.email as student_email, u.profile_pic as student_pic
    FROM bookings b
    JOIN subjects s ON b.subject_id = s.id
    JOIN users u ON b.student_id = u.id
    WHERE b.tutor_id = ? AND b.status = 'pending'
    ORDER BY b.created_at ASC
");
$stmt->execute([$user['id']]);
$pending_requests = $stmt->fetchAll();

// Get upcoming approved sessions
$stmt = $conn->prepare("
    SELECT b.*, s.name as subject_name, 
           u.full_name as student_name, u.profile_pic as student_pic
    FROM bookings b
    JOIN subjects s ON b.subject_id = s.id
    JOIN users u ON b.student_id = u.id
    WHERE b.tutor_id = ? AND b.status = 'approved' AND b.booking_date >= CURDATE()
    ORDER BY b.booking_date ASC, b.start_time ASC
");
$stmt->execute([$user['id']]);
$upcoming_sessions = $stmt->fetchAll();

// Get completed sessions
$stmt = $conn->prepare("
    SELECT b.*, s.name as subject_name, 
           u.full_name as student_name, u.profile_pic as student_pic,
           r.rating, r.feedback
    FROM bookings b
    JOIN subjects s ON b.subject_id = s.id
    JOIN users u ON b.student_id = u.id
    LEFT JOIN ratings r ON b.id = r.booking_id
    WHERE b.tutor_id = ? AND b.status = 'completed'
    ORDER BY b.booking_date DESC LIMIT 10
");
$stmt->execute([$user['id']]);
$completed_sessions = $stmt->fetchAll();

// Get earnings
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0) as total_earnings,
           COUNT(*) as total_sessions
    FROM bookings 
    WHERE tutor_id = ? AND payment_status = 'paid' AND status = 'completed'
");
$stmt->execute([$user['id']]);
$earnings = $stmt->fetch();

// Get tutor's subjects
$stmt = $conn->prepare("
    SELECT s.* FROM subjects s
    JOIN tutor_subjects ts ON s.id = ts.subject_id
    WHERE ts.tutor_id = ?
");
$stmt->execute([$user['id']]);
$my_subjects = $stmt->fetchAll();

// Get pending assignments count
$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM tasks WHERE tutor_id = ? AND status = 'active'
");
$stmt->execute([$user['id']]);
$assignments_count = $stmt->fetch()['count'];
?>

<?php include 'header.php'; ?>

<div class="dashboard-container">
    <!-- Left Sidebar - All Buttons Here -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="images/scclogo.png" alt="SCC Logo">
            <h3>Tutor Menu</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="tutor_dashboard.php" class="sidebar-link active">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="my_bookings.php" class="sidebar-link">
                <i class="fas fa-calendar-alt"></i> My Sessions
                <?php if (count($pending_requests) > 0): ?>
                    <span class="badge-count"><?php echo count($pending_requests); ?></span>
                <?php endif; ?>
            </a>
            <a href="create_assignment.php" class="sidebar-link">
                <i class="fas fa-plus-circle"></i> Create Assignment
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
                    <div class="user-role">Tutor</div>
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
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h1>Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>! 👋</h1>
                    <p>Ready to share your knowledge today?</p>
                </div>
            </div>
        </div>
        
        <!-- Availability Toggle -->
        <div class="availability-card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h3><i class="fas fa-toggle-on"></i> Availability Status</h3>
                    <p>When unavailable, students cannot book sessions with you</p>
                </div>
                <form method="POST" action="" style="display: flex; align-items: center; gap: 15px;">
                    <label class="switch">
                        <input type="checkbox" name="is_available" value="1" onchange="this.form.submit()" <?php echo ($user['is_available'] ?? 1) ? 'checked' : ''; ?>>
                        <span class="slider round"></span>
                    </label>
                    <input type="hidden" name="toggle_availability" value="1">
                    <span class="status-text <?php echo ($user['is_available'] ?? 1) ? 'available' : 'unavailable'; ?>">
                        <?php echo ($user['is_available'] ?? 1) ? '● Available' : '● Unavailable'; ?>
                    </span>
                </form>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
                <div>
                    <div class="stat-number">₱<?php echo number_format($earnings['total_earnings'], 2); ?></div>
                    <div>Total Earnings</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="stat-number"><?php echo count($pending_requests); ?></div>
                    <div>Pending Approvals</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                <div>
                    <div class="stat-number"><?php echo count($upcoming_sessions); ?></div>
                    <div>Upcoming Sessions</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="stat-number"><?php echo $earnings['total_sessions']; ?></div>
                    <div>Completed Sessions</div>
                </div>
            </div>
        </div>
        
        <!-- Pending Approval Requests -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-clock"></i> Pending Approval Requests</h3>
                <span class="badge-count"><?php echo count($pending_requests); ?></span>
            </div>
            <?php if ($pending_requests): ?>
                <?php foreach ($pending_requests as $request): ?>
                    <div class="request-card">
                        <div class="student-info">
                            <div class="student-avatar">
                                <?php if ($request['student_pic']): ?>
                                    <img src="uploads/<?php echo $request['student_pic']; ?>">
                                <?php else: ?>
                                    <?php echo substr($request['student_name'], 0, 1); ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h4><?php echo htmlspecialchars($request['student_name']); ?></h4>
                                <p><?php echo htmlspecialchars($request['subject_name']); ?></p>
                                <small><?php echo date('M d, Y', strtotime($request['booking_date'])); ?> at <?php echo date('h:i A', strtotime($request['start_time'])); ?></small>
                                <?php if ($request['notes']): ?>
                                    <div class="notes-text">"<?php echo htmlspecialchars($request['notes']); ?>"</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="request-actions">
                            <form method="POST" action="update_booking_status.php" style="display: inline-block;">
                                <input type="hidden" name="booking_id" value="<?php echo $request['id']; ?>">
                                <input type="hidden" name="status" value="approved">
                                <button type="submit" class="action-btn approve">✓ Approve</button>
                            </form>
                            <form method="POST" action="update_booking_status.php" style="display: inline-block;">
                                <input type="hidden" name="booking_id" value="<?php echo $request['id']; ?>">
                                <input type="hidden" name="status" value="rejected">
                                <button type="submit" class="action-btn reject">✗ Reject</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">No pending requests.</div>
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
                    <div class="session-card">
                        <div class="session-date-badge">
                            <span class="month"><?php echo date('M', strtotime($session['booking_date'])); ?></span>
                            <span class="day"><?php echo date('d', strtotime($session['booking_date'])); ?></span>
                        </div>
                        <div class="session-info">
                            <h4><?php echo htmlspecialchars($session['subject_name']); ?></h4>
                            <div class="student-info">
                                <div class="student-avatar-small">
                                    <?php if ($session['student_pic']): ?>
                                        <img src="uploads/<?php echo $session['student_pic']; ?>">
                                    <?php else: ?>
                                        <?php echo substr($session['student_name'], 0, 1); ?>
                                    <?php endif; ?>
                                </div>
                                <span><?php echo htmlspecialchars($session['student_name']); ?></span>
                            </div>
                            <div class="session-time"><?php echo date('h:i A', strtotime($session['start_time'])); ?> - <?php echo date('h:i A', strtotime($session['end_time'])); ?></div>
                        </div>
                        <div class="session-status">
                            <span class="status-badge status-approved">Approved</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">No upcoming sessions.</div>
            <?php endif; ?>
        </div>
        
        <!-- Completed Sessions -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-star"></i> Recent Completed Sessions</h3>
                <a href="my_bookings.php" class="view-all">View All</a>
            </div>
            <?php if ($completed_sessions): ?>
                <?php foreach ($completed_sessions as $session): ?>
                    <div class="completed-card">
                        <div class="completed-header">
                            <div class="student-info">
                                <div class="student-avatar-small">
                                    <?php if ($session['student_pic']): ?>
                                        <img src="uploads/<?php echo $session['student_pic']; ?>">
                                    <?php else: ?>
                                        <?php echo substr($session['student_name'], 0, 1); ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($session['student_name']); ?></strong>
                                    <div class="subject-name"><?php echo htmlspecialchars($session['subject_name']); ?></div>
                                </div>
                            </div>
                            <div class="rating-display">
                                <?php if ($session['rating']): ?>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $session['rating']): ?>
                                            <i class="fas fa-star"></i>
                                        <?php else: ?>
                                            <i class="far fa-star"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    <span>(<?php echo $session['rating']; ?>/5)</span>
                                <?php else: ?>
                                    <span>Not rated</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="completed-details">
                            <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($session['booking_date'])); ?></span>
                            <span><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($session['start_time'])); ?></span>
                            <span><i class="fas fa-peso-sign"></i> ₱<?php echo number_format($session['amount'], 2); ?></span>
                        </div>
                        <?php if ($session['feedback']): ?>
                            <div class="feedback">"<?php echo htmlspecialchars($session['feedback']); ?>"</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">No completed sessions yet.</div>
            <?php endif; ?>
        </div>
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

.availability-card {
    background: rgba(255,255,255,0.88);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
    border: 1px solid rgba(255,255,255,0.3);
}

.switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 28px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .3s;
}

.slider:before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
}

input:checked + .slider {
    background-color: var(--success-green);
}

input:checked + .slider:before {
    transform: translateX(22px);
}

.slider.round {
    border-radius: 28px;
}

.slider.round:before {
    border-radius: 50%;
}

.status-text {
    font-weight: 600;
    font-size: 0.85rem;
    padding: 4px 10px;
    border-radius: 20px;
}

.status-text.available {
    background: rgba(76,175,80,0.2);
    color: #2E7D32;
}

.status-text.unavailable {
    background: rgba(244,67,54,0.2);
    color: #C62828;
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

.request-card {
    background: #f9f9f9;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid #eee;
}

.student-info {
    display: flex;
    align-items: center;
    gap: 15px;
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

.student-avatar-small {
    width: 35px;
    height: 35px;
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

.student-avatar img, .student-avatar-small img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.notes-text {
    font-style: italic;
    color: #666;
    margin-top: 5px;
    font-size: 0.85rem;
}

.request-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    padding-top: 10px;
    border-top: 1px solid #eee;
}

.action-btn {
    padding: 8px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
}

.action-btn.approve {
    background: var(--success-green);
    color: white;
}

.action-btn.reject {
    background: var(--danger-red);
    color: white;
}

.session-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    border-bottom: 1px solid #eee;
}

.session-date-badge {
    text-align: center;
    background: var(--primary-red);
    color: white;
    border-radius: 10px;
    padding: 8px 12px;
    min-width: 60px;
}

.session-date-badge .month {
    font-size: 0.7rem;
    text-transform: uppercase;
}

.session-date-badge .day {
    font-size: 1.3rem;
    font-weight: bold;
}

.session-info {
    flex: 1;
}

.session-info h4 {
    margin-bottom: 5px;
}

.session-time {
    font-size: 0.8rem;
    color: #666;
    margin-top: 5px;
}

.session-status {
    text-align: right;
}

.completed-card {
    padding: 15px;
    border-bottom: 1px solid #eee;
}

.completed-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    flex-wrap: wrap;
    gap: 10px;
}

.subject-name {
    font-size: 0.85rem;
    color: #666;
}

.completed-details {
    display: flex;
    gap: 15px;
    font-size: 0.8rem;
    color: #666;
    margin-bottom: 8px;
    flex-wrap: wrap;
}

.feedback {
    font-style: italic;
    color: #555;
    padding: 10px;
    background: #f5f5f5;
    border-radius: 8px;
    margin-top: 8px;
}

.rating-display {
    font-size: 0.7rem;
    color: #FFC107;
}

.no-data {
    text-align: center;
    padding: 20px;
    color: #999;
}

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
    
    .student-info {
        flex-direction: column;
        text-align: center;
    }
    
    .session-card {
        flex-direction: column;
        text-align: center;
    }
    
    .completed-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .request-actions {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'footer.php'; ?>