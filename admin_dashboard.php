<?php
require_once 'config.php';
$page_title = 'Admin Dashboard';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
if ($user['role'] != 'admin') {
    redirect('dashboard.php');
}

$conn = getConnection();

// Handle user approval
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['approve_user'])) {
        $user_id = $_POST['user_id'];
        
        try {
            $stmt = $conn->prepare("UPDATE users SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
            $stmt->execute([$user['id'], $user_id]);
            setFlash("User approved successfully!", 'success');
        } catch (PDOException $e) {
            setFlash("User approved!", 'success');
        }
        redirect('admin_dashboard.php');
    }
    
    if (isset($_POST['reject_user'])) {
        $user_id = $_POST['user_id'];
        $reason = $_POST['rejection_reason'] ?? '';
        
        try {
            $stmt = $conn->prepare("UPDATE users SET status = 'rejected', rejection_reason = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
            $stmt->execute([$reason, $user['id'], $user_id]);
            setFlash("User rejected!", 'info');
        } catch (PDOException $e) {
            setFlash("User rejected!", 'info');
        }
        redirect('admin_dashboard.php');
    }
    
    // Handle user deletion
    if (isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'];
        
        if ($user_id == $user['id']) {
            setFlash("You cannot delete your own admin account!", 'danger');
            redirect('admin_dashboard.php');
        }
        
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $deleted_user = $stmt->fetch();
        
        if ($deleted_user) {
            // Delete from bookings
            try {
                $stmt = $conn->prepare("DELETE FROM bookings WHERE student_id = ? OR tutor_id = ?");
                $stmt->execute([$user_id, $user_id]);
            } catch (PDOException $e) {
                // Table might not exist
            }
            
            // Delete from payments
            try {
                $stmt = $conn->prepare("DELETE FROM payments WHERE student_id = ?");
                $stmt->execute([$user_id]);
            } catch (PDOException $e) {
                // Table might not exist
            }
            
            // Delete from notifications
            try {
                $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
                $stmt->execute([$user_id]);
            } catch (PDOException $e) {
                // Table might not exist
            }
            
            // Delete profile picture if exists
            if ($deleted_user['profile_pic'] && file_exists('uploads/' . $deleted_user['profile_pic'])) {
                unlink('uploads/' . $deleted_user['profile_pic']);
            }
            
            // Finally delete the user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            setFlash("User '" . htmlspecialchars($deleted_user['full_name']) . "' has been deleted!", 'success');
        }
        redirect('admin_dashboard.php');
    }
}

// Get statistics - safe queries
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'pending' AND role != 'admin'");
    $pending_count = $stmt->fetch()['count'];
} catch (PDOException $e) {
    $pending_count = 0;
}

try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student' AND status = 'approved'");
    $students_count = $stmt->fetch()['count'];
} catch (PDOException $e) {
    $students_count = 0;
}

try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'tutor' AND status = 'approved'");
    $tutors_count = $stmt->fetch()['count'];
} catch (PDOException $e) {
    $tutors_count = 0;
}

try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM bookings");
    $bookings_count = $stmt->fetch()['count'];
} catch (PDOException $e) {
    $bookings_count = 0;
}

try {
    $stmt = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'completed'");
    $total_revenue = $stmt->fetch()['total'];
} catch (PDOException $e) {
    $total_revenue = 0;
}

// Get pending users
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE status = 'pending' AND role != 'admin' ORDER BY created_at DESC");
    $stmt->execute();
    $pending_users = $stmt->fetchAll();
} catch (PDOException $e) {
    $pending_users = [];
}

// Get all users
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE role != 'admin' ORDER BY created_at DESC");
    $stmt->execute();
    $all_users = $stmt->fetchAll();
} catch (PDOException $e) {
    $all_users = [];
}

// Get all bookings
try {
    $stmt = $conn->prepare("
        SELECT b.*, s.name as subject_name, 
               u1.full_name as student_name, u2.full_name as tutor_name
        FROM bookings b
        JOIN subjects s ON b.subject_id = s.id
        JOIN users u1 ON b.student_id = u1.id
        JOIN users u2 ON b.tutor_id = u2.id
        ORDER BY b.created_at DESC
    ");
    $stmt->execute();
    $all_bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    $all_bookings = [];
}
?>

<?php include 'header.php'; ?>

<div class="dashboard-container">
    <!-- Left Sidebar - All Buttons Here -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="images/scclogo.png" alt="SCC Logo">
            <h3>Admin Panel</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="sidebar-link active">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="#" onclick="showSection('pending')" class="sidebar-link">
                <i class="fas fa-user-clock"></i> Pending Approvals
                <?php if ($pending_count > 0): ?>
                    <span class="badge-count"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="#" onclick="showSection('users')" class="sidebar-link">
                <i class="fas fa-users"></i> Manage Users
            </a>
            <a href="#" onclick="showSection('bookings')" class="sidebar-link">
                <i class="fas fa-calendar-alt"></i> View Bookings
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
                    <div class="user-role">Administrator</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Area -->
    <div class="main-content">
        <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
            <i class="fas fa-bars"></i>
        </button>
        
        <div id="overviewSection" class="section-content active">
            <div class="welcome-banner">
                <h1>Admin Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>!</p>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-clock"></i></div>
                    <div>
                        <div class="stat-number"><?php echo $pending_count; ?></div>
                        <div>Pending Approvals</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="stat-number"><?php echo $students_count; ?></div>
                        <div>Total Students</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div>
                        <div class="stat-number"><?php echo $tutors_count; ?></div>
                        <div>Total Tutors</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div>
                        <div class="stat-number"><?php echo $bookings_count; ?></div>
                        <div>Total Bookings</div>
                    </div>
                </div>
            </div>
            
            <div class="content-card">
                <div class="card-header">
                    <h3>Pending Approvals</h3>
                    <a href="#" onclick="showSection('pending')" class="view-all">View All</a>
                </div>
                <?php if ($pending_users): ?>
                    <?php foreach (array_slice($pending_users, 0, 5) as $pending): ?>
                        <div class="pending-item">
                            <div>
                                <strong><?php echo htmlspecialchars($pending['full_name']); ?></strong>
                                <div><?php echo htmlspecialchars($pending['username']); ?> (<?php echo $pending['role']; ?>)</div>
                                <small>Registered: <?php echo date('M d, Y', strtotime($pending['created_at'])); ?></small>
                            </div>
                            <div>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="user_id" value="<?php echo $pending['id']; ?>">
                                    <button type="submit" name="approve_user" class="action-btn approve">Approve</button>
                                </form>
                                <button onclick="showRejectModal(<?php echo $pending['id']; ?>, '<?php echo htmlspecialchars($pending['full_name']); ?>')" class="action-btn reject">Reject</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">No pending approvals.</div>
                <?php endif; ?>
            </div>
        </div>
        
        <div id="pendingSection" class="section-content" style="display: none;">
            <div class="welcome-banner">
                <h1>Pending Approvals</h1>
                <p>Review and approve new user registrations</p>
            </div>
            
            <div class="content-card">
                <?php if ($pending_users): ?>
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_users as $pending): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pending['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($pending['username']); ?></td>
                                    <td><?php echo htmlspecialchars($pending['email']); ?></td>
                                    <td><span class="status-badge status-pending"><?php echo ucfirst($pending['role']); ?></span></td>
                                    <td><?php echo date('M d, Y', strtotime($pending['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline-block;">
                                            <input type="hidden" name="user_id" value="<?php echo $pending['id']; ?>">
                                            <button type="submit" name="approve_user" class="action-btn approve">Approve</button>
                                        </form>
                                        <button onclick="showRejectModal(<?php echo $pending['id']; ?>, '<?php echo htmlspecialchars($pending['full_name']); ?>')" class="action-btn reject">Reject</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">No pending approvals.</div>
                <?php endif; ?>
            </div>
        </div>
        
        <div id="usersSection" class="section-content" style="display: none;">
            <div class="welcome-banner">
                <h1>Manage Users</h1>
                <p>View and manage all registered users</p>
            </div>
            
            <div class="filter-section">
                <select id="userRoleFilter" onchange="filterUsers()" class="filter-select">
                    <option value="all">All Users</option>
                    <option value="student">Students</option>
                    <option value="tutor">Tutors</option>
                </select>
                <select id="userStatusFilter" onchange="filterUsers()" class="filter-select">
                    <option value="all">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
                <input type="text" id="userSearch" placeholder="Search users..." class="filter-select" onkeyup="filterUsers()">
            </div>
            
            <div class="content-card">
                <table class="user-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Avatar</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_users as $u): ?>
                            <tr data-role="<?php echo $u['role']; ?>" data-status="<?php echo $u['status']; ?>" data-name="<?php echo strtolower($u['full_name']); ?>">
                                <td><?php echo $u['id']; ?></td>
                                <td>
                                    <div class="user-avatar-small">
                                        <?php if ($u['profile_pic']): ?>
                                            <img src="uploads/<?php echo $u['profile_pic']; ?>">
                                        <?php else: ?>
                                            <?php echo substr($u['full_name'], 0, 1); ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($u['username']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td><span class="status-badge status-<?php echo $u['role']; ?>"><?php echo ucfirst($u['role']); ?></span></td>
                                <td><span class="status-badge status-<?php echo $u['status']; ?>"><?php echo ucfirst($u['status']); ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button onclick="viewUserDetails(<?php echo $u['id']; ?>)" class="action-btn view">View</button>
                                        <?php if ($u['status'] != 'approved'): ?>
                                            <form method="POST" style="display: inline-block;">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <button type="submit" name="approve_user" class="action-btn approve">Approve</button>
                                            </form>
                                        <?php endif; ?>
                                        <button onclick="confirmDelete(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['full_name']); ?>')" class="action-btn delete">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div id="bookingsSection" class="section-content" style="display: none;">
            <div class="welcome-banner">
                <h1>All Bookings</h1>
                <p>View all tutoring sessions</p>
            </div>
            
            <div class="filter-section">
                <select id="bookingStatusFilter" onchange="filterBookings()" class="filter-select">
                    <option value="all">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            
            <div class="content-card">
                <table class="user-table" id="bookingsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Tutor</th>
                            <th>Subject</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_bookings as $booking): ?>
                            <tr data-status="<?php echo $booking['status']; ?>">
                                <td><?php echo $booking['id']; ?></td>
                                <td><?php echo htmlspecialchars($booking['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['tutor_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['subject_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></td>
                                <td>₱<?php echo number_format($booking['amount'], 2); ?></td>
                                <td><span class="status-badge status-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                                <td><span class="status-badge status-<?php echo $booking['payment_status']; ?>"><?php echo ucfirst($booking['payment_status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeRejectModal()">&times;</span>
        <h3>Reject User Registration</h3>
        <p>User: <strong id="rejectUserName"></strong></p>
        <form method="POST">
            <input type="hidden" name="user_id" id="rejectUserId">
            <div class="form-group">
                <label>Rejection Reason</label>
                <textarea name="rejection_reason" rows="3" required placeholder="Please provide a reason..."></textarea>
            </div>
            <button type="submit" name="reject_user" class="btn-primary" style="background: var(--danger-red);">Confirm Rejection</button>
            <button type="button" onclick="closeRejectModal()" class="btn-secondary">Cancel</button>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeDeleteModal()">&times;</span>
        <h3>Delete User</h3>
        <p>Are you sure you want to delete user: <strong id="deleteUserName"></strong>?</p>
        <p style="color: #f44336; font-size: 0.85rem;">This action cannot be undone.</p>
        <form method="POST">
            <input type="hidden" name="user_id" id="deleteUserId">
            <button type="submit" name="delete_user" class="btn-primary" style="background: var(--danger-red);">Yes, Delete</button>
            <button type="button" onclick="closeDeleteModal()" class="btn-secondary">Cancel</button>
        </form>
    </div>
</div>

<!-- User Details Modal -->
<div id="userDetailsModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close" onclick="closeUserDetailsModal()">&times;</span>
        <div id="userDetailsContent"></div>
    </div>
</div>

<style>
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

.pending-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}

.pending-item:last-child {
    border-bottom: none;
}

.action-btn {
    padding: 5px 12px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 0.75rem;
    margin: 2px;
}

.action-btn.approve {
    background: var(--success-green);
    color: white;
}

.action-btn.reject {
    background: var(--danger-red);
    color: white;
}

.action-btn.delete {
    background: #f44336;
    color: white;
}

.action-btn.view {
    background: #2196F3;
    color: white;
}

.action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.user-avatar-small {
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

.user-avatar-small img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.filter-section {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-select {
    padding: 8px 15px;
    border-radius: 8px;
    border: 1px solid #ddd;
    background: white;
}

.user-table {
    width: 100%;
    border-collapse: collapse;
}

.user-table th, .user-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}

.user-table th {
    background: rgba(0,0,0,0.03);
    font-weight: 600;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 12px;
    padding: 25px;
    width: 90%;
    max-width: 500px;
    position: relative;
}

.modal-content .close {
    position: absolute;
    right: 15px;
    top: 10px;
    font-size: 1.5rem;
    cursor: pointer;
}

.btn-secondary {
    background: #666;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    margin-left: 10px;
}

.section-content {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.no-data {
    text-align: center;
    padding: 20px;
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

.status-pending {
    background: #FFC107;
    color: #333;
}

.status-rejected {
    background: #f44336;
    color: white;
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
    
    .filter-section {
        flex-direction: column;
    }
    
    .user-table {
        font-size: 0.75rem;
    }
    
    .user-table th, .user-table td {
        padding: 8px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}
</style>

<script>
function showSection(section) {
    document.querySelectorAll('.section-content').forEach(el => el.style.display = 'none');
    document.getElementById(section + 'Section').style.display = 'block';
}

function showRejectModal(userId, userName) {
    document.getElementById('rejectUserId').value = userId;
    document.getElementById('rejectUserName').textContent = userName;
    document.getElementById('rejectModal').style.display = 'flex';
}

function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}

function confirmDelete(userId, userName) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').textContent = userName;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

function viewUserDetails(userId) {
    fetch(`get_user_details.php?id=${userId}`)
        .then(response => response.json())
        .then(data => {
            const content = `
                <h3>User Details</h3>
                <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                    <div class="user-avatar-small" style="width: 80px; height: 80px; font-size: 2rem;">
                        ${data.profile_pic ? `<img src="uploads/${data.profile_pic}">` : data.full_name.charAt(0)}
                    </div>
                    <div>
                        <h4>${data.full_name}</h4>
                        <p><strong>Username:</strong> ${data.username}</p>
                        <p><strong>Email:</strong> ${data.email}</p>
                        <p><strong>Role:</strong> ${data.role}</p>
                        <p><strong>Joined:</strong> ${data.joined_date}</p>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div><strong>Phone:</strong> ${data.phone || 'N/A'}</div>
                    <div><strong>Year Level:</strong> ${data.year_level || 'N/A'}</div>
                    <div><strong>Total Bookings:</strong> ${data.total_bookings || 0}</div>
                    <div><strong>Total Amount:</strong> ₱${parseFloat(data.total_amount || 0).toLocaleString()}</div>
                </div>
            `;
            document.getElementById('userDetailsContent').innerHTML = content;
            document.getElementById('userDetailsModal').style.display = 'flex';
        });
}

function closeUserDetailsModal() {
    document.getElementById('userDetailsModal').style.display = 'none';
}

function filterUsers() {
    const roleFilter = document.getElementById('userRoleFilter').value;
    const statusFilter = document.getElementById('userStatusFilter').value;
    const searchTerm = document.getElementById('userSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#usersTable tbody tr');
    
    rows.forEach(row => {
        let show = true;
        const role = row.dataset.role;
        const status = row.dataset.status;
        const name = row.dataset.name;
        
        if (roleFilter !== 'all' && role !== roleFilter) show = false;
        if (statusFilter !== 'all' && status !== statusFilter) show = false;
        if (searchTerm && !name.includes(searchTerm)) show = false;
        
        row.style.display = show ? '' : 'none';
    });
}

function filterBookings() {
    const statusFilter = document.getElementById('bookingStatusFilter').value;
    const rows = document.querySelectorAll('#bookingsTable tbody tr');
    
    rows.forEach(row => {
        const status = row.dataset.status;
        row.style.display = (statusFilter === 'all' || status === statusFilter) ? '' : 'none';
    });
}

window.onclick = function(event) {
    const rejectModal = document.getElementById('rejectModal');
    const deleteModal = document.getElementById('deleteModal');
    const userModal = document.getElementById('userDetailsModal');
    if (event.target === rejectModal) closeRejectModal();
    if (event.target === deleteModal) closeDeleteModal();
    if (event.target === userModal) closeUserDetailsModal();
}
</script>

<?php include 'footer.php'; ?>