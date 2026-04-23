<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);

// Redirect admin to admin dashboard
if ($user['role'] == 'admin') {
    redirect('admin_dashboard.php');
}

$conn = getConnection();

// Get counts based on role
if ($user['role'] == 'student') {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE student_id = ? AND status = 'pending' AND payment_status = 'pending'");
    $stmt->execute([$user['id']]);
    $pending_payment = $stmt->fetch()['count'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE student_id = ? AND status = 'pending' AND payment_status = 'paid'");
    $stmt->execute([$user['id']]);
    $pending_approval = $stmt->fetch()['count'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE student_id = ? AND status = 'approved'");
    $stmt->execute([$user['id']]);
    $approved_count = $stmt->fetch()['count'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE student_id = ? AND status = 'completed'");
    $stmt->execute([$user['id']]);
    $completed_count = $stmt->fetch()['count'];
    
    // Get upcoming bookings
    $stmt = $conn->prepare("
        SELECT b.*, s.name as subject_name, u.full_name as tutor_name, u.profile_pic as tutor_pic
        FROM bookings b
        JOIN subjects s ON b.subject_id = s.id
        JOIN users u ON b.tutor_id = u.id
        WHERE b.student_id = ? AND b.status IN ('approved', 'pending')
        ORDER BY b.booking_date ASC LIMIT 5
    ");
    $stmt->execute([$user['id']]);
    $upcoming_bookings = $stmt->fetchAll();
} else {
    // Tutor - redirect to tutor dashboard
    redirect('tutor_dashboard.php');
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
            <a href="dashboard.php" class="sidebar-link active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="book_session.php" class="sidebar-link"><i class="fas fa-calendar-plus"></i> Book a Tutor</a>
            <a href="my_bookings.php" class="sidebar-link"><i class="fas fa-list-alt"></i> My Bookings</a>
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
            <h1>Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>! 👋</h1>
            <p>Ready to learn something new today?</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-credit-card"></i></div>
                <div>
                    <div class="stat-number"><?php echo $pending_payment; ?></div>
                    <div>Pending Payment</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="stat-number"><?php echo $pending_approval; ?></div>
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
        </div>
        
        <div class="quick-action-grid">
            <a href="book_session.php" class="quick-action-card">
                <i class="fas fa-calendar-plus"></i>
                <div>Book a Tutor</div>
            </a>
            <a href="my_bookings.php" class="quick-action-card">
                <i class="fas fa-list-alt"></i>
                <div>My Bookings</div>
            </a>
            <a href="profile.php" class="quick-action-card">
                <i class="fas fa-user-circle"></i>
                <div>Update Profile</div>
            </a>
        </div>
        
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-alt"></i> Recent Bookings</h3>
                <a href="my_bookings.php" class="view-all">View All</a>
            </div>
            <?php if ($upcoming_bookings): ?>
                <?php foreach ($upcoming_bookings as $booking): ?>
                    <div class="booking-item">
                        <div>
                            <strong><?php echo htmlspecialchars($booking['subject_name']); ?></strong>
                            <div>with <?php echo htmlspecialchars($booking['tutor_name']); ?></div>
                            <small><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?> at <?php echo date('h:i A', strtotime($booking['start_time'])); ?></small>
                        </div>
                        <span class="status-badge status-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">No bookings yet. <a href="book_session.php">Book your first session!</a></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Dashboard Layout */
.dashboard-container {
    display: flex;
    min-height: 100vh;
}

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
}

.sidebar-link:hover, .sidebar-link.active {
    background: rgba(139,0,0,0.8);
}

.sidebar-link i {
    width: 25px;
    margin-right: 12px;
    font-size: 1.2rem;
}

.sidebar-link.logout {
    margin-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
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

/* Welcome Banner */
.welcome-banner {
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 25px 30px;
    margin-bottom: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border: 1px solid rgba(255,255,255,0.3);
}

.welcome-banner h1 {
    color: var(--primary-red);
    margin-bottom: 5px;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s;
    border: 1px solid rgba(255,255,255,0.3);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    background: rgba(255,255,255,0.95);
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

/* Quick Actions */
.quick-action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.quick-action-card {
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    text-decoration: none;
    transition: all 0.3s;
    border: 1px solid rgba(255,255,255,0.3);
}

.quick-action-card:hover {
    transform: translateY(-5px);
    background: var(--primary-red);
    color: white;
}

.quick-action-card i {
    font-size: 2rem;
    margin-bottom: 10px;
    color: var(--primary-red);
}

.quick-action-card:hover i {
    color: white;
}

.quick-action-card div {
    color: #333;
}

.quick-action-card:hover div {
    color: white;
}

.logout-card {
    background: var(--danger-red);
    color: white;
}

.logout-card i {
    color: white;
}

.logout-card div {
    color: white;
}

/* Content Card */
.content-card {
    background: rgba(255, 255, 255, 0.88);
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

.booking-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}

.booking-item:last-child {
    border-bottom: none;
}

/* Status Badges */
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

.status-pending {
    background: #FFC107;
    color: #333;
}

.status-completed {
    background: #2196F3;
    color: white;
}

.status-cancelled, .status-rejected {
    background: #f44336;
    color: white;
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
    
    .quick-action-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .quick-action-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'footer.php'; ?>