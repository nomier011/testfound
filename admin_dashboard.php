<?php
require_once 'config.php';
$page_title = 'Admin Dashboard';

requireLogin();
$user = getCurrentUser();
requireRole('admin');

$conn = getConnection();
$active_tab = $_GET['tab'] ?? 'dashboard';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash("Security token mismatch. Please try again.", 'danger');
        redirect('admin_dashboard.php?tab=pending');
    }

    if (isset($_POST['approve_user'])) {
        $user_id = sanitizeInt($_POST['user_id'] ?? 0);
        $redirect_tab = sanitizeString($_POST['redirect_tab'] ?? 'pending');

        $user_data = getUserById($user_id);
        if ($user_data) {
            $conn->prepare("UPDATE users SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?")
                 ->execute([$user['id'], $user_id]);

            sendNotification($user_id, "🎉 Your account has been approved! You can now log in and start using the system.", 'account');
            logActivity($user['id'], 'approve_user', "Approved user: {$user_data['username']}");
            setFlash("User <strong>" . htmlspecialchars($user_data['full_name']) . "</strong> approved successfully!", 'success');
        } else {
            setFlash("User not found.", 'danger');
        }
        redirect('admin_dashboard.php?tab=' . $redirect_tab);
    }

    if (isset($_POST['reject_user'])) {
        $user_id = sanitizeInt($_POST['user_id'] ?? 0);
        $reason  = sanitizeString($_POST['rejection_reason'] ?? '');
        $redirect_tab = sanitizeString($_POST['redirect_tab'] ?? 'pending');

        $user_data = getUserById($user_id);
        if ($user_data) {
            $conn->prepare("UPDATE users SET status = 'rejected', rejection_reason = ?, approved_by = ?, approved_at = NOW() WHERE id = ?")
                 ->execute([$reason, $user['id'], $user_id]);

            $reason_text = $reason ?: 'No reason provided.';
            sendNotification($user_id, "❌ Your account registration was rejected. Reason: {$reason_text}", 'account');
            logActivity($user['id'], 'reject_user', "Rejected user: {$user_data['username']}. Reason: {$reason_text}");
            setFlash("User <strong>" . htmlspecialchars($user_data['full_name']) . "</strong> rejected.", 'info');
        } else {
            setFlash("User not found.", 'danger');
        }
        redirect('admin_dashboard.php?tab=' . $redirect_tab);
    }

    if (isset($_POST['delete_user'])) {
        $user_id = sanitizeInt($_POST['user_id'] ?? 0);
        $redirect_tab = sanitizeString($_POST['redirect_tab'] ?? 'users');

        if ($user_id === (int)$user['id']) {
            setFlash("You cannot delete your own account.", 'danger');
            redirect('admin_dashboard.php?tab=' . $redirect_tab);
        }

        $user_data = getUserById($user_id);
        if ($user_data && $user_data['role'] !== 'admin') {
            try {
                $conn->beginTransaction();

                // Remove profile picture if present
                if ($user_data['profile_pic']) {
                    $pic_path = PROFILE_PATH . basename($user_data['profile_pic']);
                    if (file_exists($pic_path)) unlink($pic_path);
                }

                // Explicit cascade cleanup
                $conn->prepare("DELETE FROM bookings WHERE student_id = ? OR tutor_id = ?")->execute([$user_id, $user_id]);
                $conn->prepare("DELETE FROM payments WHERE student_id = ?")->execute([$user_id]);
                $conn->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$user_id]);
                $conn->prepare("DELETE FROM tutor_subjects WHERE tutor_id = ?")->execute([$user_id]);
                $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'")->execute([$user_id]);

                $conn->commit();
                logActivity($user['id'], 'delete_user', "Deleted user: {$user_data['username']} (ID: {$user_id})");
                setFlash("User <strong>" . htmlspecialchars($user_data['full_name']) . "</strong> deleted successfully.", 'success');
            } catch (PDOException $e) {
                $conn->rollBack();
                error_log("Delete user error: " . $e->getMessage());
                setFlash("Failed to delete user. Please try again.", 'danger');
            }
        } else {
            setFlash("User not found or cannot be deleted.", 'danger');
        }
        redirect('admin_dashboard.php?tab=' . $redirect_tab);
    }

    // ── Subject management ────────────────────────────────────────────────────
    if (isset($_POST['add_subject'])) {
        $name = trim(sanitizeString($_POST['subject_name'] ?? ''));
        $desc = trim(sanitizeString($_POST['subject_desc'] ?? ''));
        if ($name) {
            try {
                $conn->prepare("INSERT INTO subjects (name, description) VALUES (?, ?)")
                     ->execute([$name, $desc]);
                setFlash("Subject <strong>" . htmlspecialchars($name) . "</strong> added.", 'success');
            } catch (PDOException $e) {
                setFlash("Subject already exists or could not be added.", 'danger');
            }
        } else {
            setFlash("Subject name is required.", 'danger');
        }
        redirect('admin_dashboard.php?tab=subjects');
    }

    if (isset($_POST['edit_subject'])) {
        $sid  = sanitizeInt($_POST['subject_id'] ?? 0);
        $name = trim(sanitizeString($_POST['subject_name'] ?? ''));
        $desc = trim(sanitizeString($_POST['subject_desc'] ?? ''));
        if ($sid && $name) {
            try {
                $conn->prepare("UPDATE subjects SET name = ?, description = ? WHERE id = ?")
                     ->execute([$name, $desc, $sid]);
                setFlash("Subject updated.", 'success');
            } catch (PDOException $e) {
                setFlash("Could not update subject.", 'danger');
            }
        }
        redirect('admin_dashboard.php?tab=subjects');
    }

    if (isset($_POST['delete_subject'])) {
        $sid = sanitizeInt($_POST['subject_id'] ?? 0);
        if ($sid) {
            try {
                $conn->prepare("DELETE FROM subjects WHERE id = ?")->execute([$sid]);
                setFlash("Subject deleted.", 'info');
            } catch (PDOException $e) {
                setFlash("Cannot delete — subject is in use by bookings or modules.", 'danger');
            }
        }
        redirect('admin_dashboard.php?tab=subjects');
    }
}

// Get statistics
$total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'")->fetch()['count'];
$pending_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'pending' AND role != 'admin'")->fetch()['count'];
$students_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student' AND status = 'approved'")->fetch()['count'];
$tutors_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'tutor' AND status = 'approved'")->fetch()['count'];
$total_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings")->fetch()['count'];
$completed_sessions = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'completed'")->fetch()['count'];
$total_revenue = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'completed'")->fetch()['total'];

// Monthly revenue for chart
$monthly_revenue = $conn->query("
    SELECT DATE_FORMAT(payment_date, '%b %Y') as month, SUM(amount) as total
    FROM payments WHERE status = 'completed' AND payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
    ORDER BY MIN(payment_date)
")->fetchAll();

// Get pending users
$pending_users = $conn->prepare("SELECT * FROM users WHERE status = 'pending' AND role != 'admin' ORDER BY created_at DESC");
$pending_users->execute();
$pending_users = $pending_users->fetchAll();

// Get all users
$all_users = $conn->prepare("SELECT * FROM users WHERE role != 'admin' ORDER BY created_at DESC");
$all_users->execute();
$all_users = $all_users->fetchAll();

// Get recent bookings
$recent_bookings = $conn->prepare("
    SELECT b.*, s.name as subject_name, u1.full_name as student_name, u2.full_name as tutor_name
    FROM bookings b
    JOIN subjects s ON b.subject_id = s.id
    JOIN users u1 ON b.student_id = u1.id
    JOIN users u2 ON b.tutor_id = u2.id
    ORDER BY b.created_at DESC LIMIT 10
");
$recent_bookings->execute();
$recent_bookings = $recent_bookings->fetchAll();

// Get recent payments
$recent_payments = $conn->prepare("
    SELECT p.*, u.full_name as student_name
    FROM payments p
    JOIN users u ON p.student_id = u.id
    ORDER BY p.created_at DESC LIMIT 10
");
$recent_payments->execute();
$recent_payments = $recent_payments->fetchAll();

// Get all subjects with usage counts
$all_subjects = $conn->query("
    SELECT s.*,
           COUNT(DISTINCT ts.tutor_id) as tutor_count,
           COUNT(DISTINCT b.id)        as booking_count,
           COUNT(DISTINCT m.id)        as module_count
    FROM subjects s
    LEFT JOIN tutor_subjects ts ON ts.subject_id = s.id
    LEFT JOIN bookings b        ON b.subject_id  = s.id
    LEFT JOIN modules m         ON m.subject_id  = s.id
    GROUP BY s.id
    ORDER BY s.name
")->fetchAll();
?>

<?php include 'header.php'; ?>

<div class="dashboard-container">
    <?php include 'sidebar.php'; ?>
    
    <main class="main-content">
        <div class="welcome-banner">
            <h1>Admin Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>! Here's your system overview.</p>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div style="margin-bottom:20px;padding:14px 18px;border-radius:10px;display:flex;align-items:center;gap:10px;font-weight:500;
                <?php
                switch($flash['type']) {
                    case 'success': echo 'background:#f0fdf4;color:#059669;border:1px solid #bbf7d0;'; break;
                    case 'danger':  echo 'background:#fef2f2;color:#dc2626;border:1px solid #fecaca;'; break;
                    case 'info':    echo 'background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;'; break;
                    default:        echo 'background:#fffbeb;color:#92400e;border:1px solid #fde68a;'; break;
                }
                ?>">
                <i class="fas fa-<?php echo $flash['type']=='success'?'check-circle':($flash['type']=='info'?'info-circle':'exclamation-circle'); ?>"></i>
                <span><?php echo $flash['message']; ?></span>
            </div>
        <?php endif; ?>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card" onclick="location.href='?tab=pending'">
                <div class="stat-icon"><i class="fas fa-user-clock"></i></div>
                <div>
                    <div class="stat-number"><?php echo $pending_count; ?></div>
                    <div class="stat-label">Pending Approvals</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div>
                    <div class="stat-number"><?php echo $total_users; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
                <div>
                    <div class="stat-number"><?php echo $students_count; ?></div>
                    <div class="stat-label">Students</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <div>
                    <div class="stat-number"><?php echo $tutors_count; ?></div>
                    <div class="stat-label">Tutors</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                <div>
                    <div class="stat-number"><?php echo $total_bookings; ?></div>
                    <div class="stat-label">Total Bookings</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-double"></i></div>
                <div>
                    <div class="stat-number"><?php echo $completed_sessions; ?></div>
                    <div class="stat-label">Completed Sessions</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
                <div>
                    <div class="stat-number">₱<?php echo number_format($total_revenue, 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Tabs -->
        <div class="dashboard-tabs">
            <button class="tab-btn <?php echo $active_tab == 'dashboard' ? 'active' : ''; ?>" onclick="location.href='?tab=dashboard'">
                <i class="fas fa-chart-line"></i> Overview
            </button>
            <button class="tab-btn <?php echo $active_tab == 'pending' ? 'active' : ''; ?>" onclick="location.href='?tab=pending'">
                <i class="fas fa-user-clock"></i> Pending Approvals
                <?php if ($pending_count > 0): ?>
                    <span class="badge-count"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </button>
            <button class="tab-btn <?php echo $active_tab == 'users' ? 'active' : ''; ?>" onclick="location.href='?tab=users'">
                <i class="fas fa-users"></i> All Users
            </button>
            <button class="tab-btn" onclick="location.href='admin_create_tutor.php'">
                <i class="fas fa-user-plus"></i> Create Tutor
            </button>
            <button class="tab-btn <?php echo $active_tab == 'bookings' ? 'active' : ''; ?>" onclick="location.href='?tab=bookings'">
                <i class="fas fa-calendar-alt"></i> Bookings
            </button>
            <button class="tab-btn <?php echo $active_tab == 'subjects' ? 'active' : ''; ?>" onclick="location.href='?tab=subjects'">
                <i class="fas fa-book"></i> Subjects
            </button>
            <button class="tab-btn <?php echo $active_tab == 'payments' ? 'active' : ''; ?>" onclick="location.href='?tab=payments'">
                <i class="fas fa-credit-card"></i> Payments
            </button>
        </div>
        
        <!-- Dashboard Overview Tab -->
        <?php if ($active_tab == 'dashboard'): ?>
            <div class="content-grid">
                <!-- Revenue Chart -->
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-line"></i> Revenue Trend</h3>
                    </div>
                    <canvas id="revenueChart" height="250"></canvas>
                </div>
                
                <!-- Recent Bookings -->
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-alt"></i> Recent Bookings</h3>
                        <a href="?tab=bookings" class="view-all">View All →</a>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr><th>Student</th><th>Tutor</th><th>Subject</th><th>Date</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($recent_bookings, 0, 5) as $booking): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(substr($booking['student_name'], 0, 20)); ?></td>
                                        <td><?php echo htmlspecialchars(substr($booking['tutor_name'], 0, 20)); ?></td>
                                        <td><?php echo htmlspecialchars(substr($booking['subject_name'], 0, 25)); ?></td>
                                        <td><?php echo date('M d', strtotime($booking['booking_date'])); ?></td>
                                        <td><span class="status-badge status-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Recent Payments -->
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-credit-card"></i> Recent Payments</h3>
                    <a href="?tab=payments" class="view-all">View All →</a>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Student</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_payments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(substr($payment['student_name'], 0, 25)); ?></td>
                                    <td><strong>₱<?php echo number_format($payment['amount'], 2); ?></strong></td>
                                    <td><?php echo ucfirst($payment['payment_method'] ?? 'N/A'); ?></td>
                                    <td><?php echo $payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : date('M d', strtotime($payment['created_at'])); ?></td>
                                    <td><span class="status-badge status-<?php echo $payment['status']; ?>"><?php echo ucfirst($payment['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Pending Approvals Tab -->
        <?php if ($active_tab == 'pending'): ?>
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-user-clock"></i> Pending User Approvals</h3>
                </div>
                <?php if ($pending_users): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr><th>Name</th><th>School ID</th><th>Username</th><th>Email</th><th>Role</th><th>Registered</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_users as $pending): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($pending['full_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($pending['school_id'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($pending['username']); ?></td>
                                        <td><?php echo htmlspecialchars($pending['email']); ?></td>
                                        <td><span class="status-badge status-<?php echo $pending['role']; ?>"><?php echo ucfirst($pending['role']); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($pending['created_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <form method="POST" style="display: inline-block;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $pending['id']; ?>">
                                                    <input type="hidden" name="redirect_tab" value="pending">
                                                    <input type="hidden" name="approve_user" value="1">
                                                    <button type="submit" class="btn-sm btn-approve">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                <button onclick="showRejectModal(<?php echo $pending['id']; ?>, '<?php echo htmlspecialchars($pending['full_name'], ENT_QUOTES); ?>')" 
                                                        class="btn-sm btn-reject">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-check-circle"></i>
                        <p>No pending approvals. All caught up!</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- All Users Tab -->
        <?php if ($active_tab == 'users'): ?>
            <!-- Sub-tabs -->
            <div style="display:flex;gap:8px;margin-bottom:20px;">
                <button onclick="switchUserTab('students')" id="tab-students"
                    style="padding:9px 22px;border-radius:8px;font-size:.85rem;font-weight:700;cursor:pointer;border:2px solid #dc2626;background:#dc2626;color:#fff;transition:all .2s;">
                    <i class="fas fa-user-graduate"></i> Students
                    <span style="background:rgba(255,255,255,.25);border-radius:20px;padding:1px 8px;font-size:.72rem;margin-left:6px;"><?php echo count(array_filter($all_users, fn($u) => $u['role']==='student')); ?></span>
                </button>
                <button onclick="switchUserTab('tutors')" id="tab-tutors"
                    style="padding:9px 22px;border-radius:8px;font-size:.85rem;font-weight:700;cursor:pointer;border:2px solid #e5e7eb;background:#fff;color:#374151;transition:all .2s;">
                    <i class="fas fa-chalkboard-teacher"></i> Tutors
                    <span style="background:#f3f4f6;border-radius:20px;padding:1px 8px;font-size:.72rem;margin-left:6px;"><?php echo count(array_filter($all_users, fn($u) => $u['role']==='tutor')); ?></span>
                </button>
            </div>

            <!-- Students Table -->
            <div id="panel-students" class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-user-graduate"></i> Students</h3>
                    <input type="text" id="studentSearch" placeholder="Search students..." class="search-input" onkeyup="filterTable('studentSearch','studentsTable')">
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="studentsTable">
                        <thead>
                            <tr><th>ID</th><th>Name</th><th>Username</th><th>Email</th><th>Year</th><th>School ID</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_filter($all_users, fn($u) => $u['role']==='student') as $u): ?>
                                <tr data-name="<?php echo strtolower($u['full_name']); ?>">
                                    <td><?php echo $u['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($u['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td><?php echo $u['year_level'] ? 'Year '.$u['year_level'] : '—'; ?></td>
                                    <td><?php echo htmlspecialchars($u['school_id'] ?? '—'); ?></td>
                                    <td><span class="status-badge status-<?php echo $u['status']; ?>"><?php echo ucfirst($u['status']); ?></span></td>
                                    <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($u['status'] != 'approved'): ?>
                                                <form method="POST" style="display:inline-block;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <input type="hidden" name="redirect_tab" value="users">
                                                    <input type="hidden" name="approve_user" value="1">
                                                    <button type="submit" class="btn-sm btn-approve" title="Approve"><i class="fas fa-check"></i></button>
                                                </form>
                                            <?php endif; ?>
                                            <button onclick="confirmDelete(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['full_name'], ENT_QUOTES); ?>', 'users')" class="btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tutors Table -->
            <div id="panel-tutors" class="content-card" style="display:none;">
                <div class="card-header">
                    <h3><i class="fas fa-chalkboard-teacher"></i> Tutors</h3>
                    <input type="text" id="tutorSearch" placeholder="Search tutors..." class="search-input" onkeyup="filterTable('tutorSearch','tutorsTable')">
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="tutorsTable">
                        <thead>
                            <tr><th>ID</th><th>Name</th><th>Username</th><th>Email</th><th>Hourly Rate</th><th>Expertise</th><th>Available</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_filter($all_users, fn($u) => $u['role']==='tutor') as $u): ?>
                                <tr data-name="<?php echo strtolower($u['full_name']); ?>">
                                    <td><?php echo $u['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($u['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td>₱<?php echo number_format($u['hourly_rate'] ?? 0, 2); ?>/hr</td>
                                    <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($u['expertise'] ?? ''); ?>"><?php echo htmlspecialchars(substr($u['expertise'] ?? '—', 0, 40)); ?></td>
                                    <td>
                                        <span style="display:inline-flex;align-items:center;gap:5px;font-size:.75rem;font-weight:600;color:<?php echo ($u['is_available']??1)?'#059669':'#dc2626'; ?>">
                                            <span style="width:7px;height:7px;border-radius:50%;background:<?php echo ($u['is_available']??1)?'#10b981':'#ef4444'; ?>;"></span>
                                            <?php echo ($u['is_available']??1)?'Yes':'No'; ?>
                                        </span>
                                    </td>
                                    <td><span class="status-badge status-<?php echo $u['status']; ?>"><?php echo ucfirst($u['status']); ?></span></td>
                                    <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($u['status'] != 'approved'): ?>
                                                <form method="POST" style="display:inline-block;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <input type="hidden" name="redirect_tab" value="users">
                                                    <input type="hidden" name="approve_user" value="1">
                                                    <button type="submit" class="btn-sm btn-approve" title="Approve"><i class="fas fa-check"></i></button>
                                                </form>
                                            <?php endif; ?>
                                            <button onclick="confirmDelete(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['full_name'], ENT_QUOTES); ?>', 'users')" class="btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <script>
            function switchUserTab(tab) {
                const isStudents = tab === 'students';
                document.getElementById('panel-students').style.display = isStudents ? 'block' : 'none';
                document.getElementById('panel-tutors').style.display   = isStudents ? 'none'  : 'block';
                document.getElementById('tab-students').style.background    = isStudents ? '#dc2626' : '#fff';
                document.getElementById('tab-students').style.color         = isStudents ? '#fff'    : '#374151';
                document.getElementById('tab-students').style.borderColor   = isStudents ? '#dc2626' : '#e5e7eb';
                document.getElementById('tab-tutors').style.background      = isStudents ? '#fff'    : '#dc2626';
                document.getElementById('tab-tutors').style.color           = isStudents ? '#374151' : '#fff';
                document.getElementById('tab-tutors').style.borderColor     = isStudents ? '#e5e7eb' : '#dc2626';
            }
            function filterTable(inputId, tableId) {
                const q = document.getElementById(inputId).value.toLowerCase();
                document.querySelectorAll('#' + tableId + ' tbody tr').forEach(row => {
                    row.style.display = row.dataset.name?.includes(q) ? '' : 'none';
                });
            }
            </script>
        <?php endif; ?>
        
        <!-- Bookings Tab -->
        <?php if ($active_tab == 'bookings'): ?>
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-alt"></i> All Bookings</h3>
                    <select id="bookingStatusFilter" class="filter-select">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="bookingsTable">
                        <thead>
                            <tr><th>ID</th><th>Student</th><th>Tutor</th><th>Subject</th><th>Date</th><th>Amount</th><th>Status</th><th>Payment</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_bookings as $booking): ?>
                                <tr data-status="<?php echo $booking['status']; ?>">
                                    <td><?php echo $booking['id']; ?></td>
                                    <td><?php echo htmlspecialchars($booking['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['tutor_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['subject_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></td>
                                    <td><strong>₱<?php echo number_format($booking['amount'], 2); ?></strong></td>
                                    <td><span class="status-badge status-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                                    <td><span class="status-badge status-<?php echo $booking['payment_status']; ?>"><?php echo ucfirst($booking['payment_status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Subjects Tab -->
        <?php if ($active_tab == 'subjects'): ?>
            <!-- Add Subject Form -->
            <div class="content-card" style="margin-bottom:20px;">
                <div class="card-header">
                    <h3><i class="fas fa-plus-circle"></i> Add New Subject</h3>
                </div>
                <form method="POST" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                    <input type="hidden" name="add_subject" value="1">
                    <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0;">
                        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:4px;">Subject Name <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="subject_name" placeholder="e.g., CS101 - Data Structures" required
                               style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:.875rem;">
                    </div>
                    <div class="form-group" style="flex:2;min-width:260px;margin-bottom:0;">
                        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:4px;">Description</label>
                        <input type="text" name="subject_desc" placeholder="Brief description of this subject"
                               style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:.875rem;">
                    </div>
                    <button type="submit" class="btn-sm btn-approve" style="padding:9px 20px;font-size:.85rem;white-space:nowrap;">
                        <i class="fas fa-plus"></i> Add Subject
                    </button>
                </form>
            </div>

            <!-- Subjects List -->
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-book"></i> All Subjects (<?php echo count($all_subjects); ?>)</h3>
                </div>
                <?php if ($all_subjects): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Subject Name</th>
                                <th>Description</th>
                                <th>Tutors</th>
                                <th>Bookings</th>
                                <th>Modules</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_subjects as $subj): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($subj['name']); ?></strong></td>
                                <td style="color:#6b7280;font-size:.85rem;max-width:260px;">
                                    <?php echo htmlspecialchars($subj['description'] ?? '—'); ?>
                                </td>
                                <td><span style="background:#ede9fe;color:#5b21b6;padding:2px 10px;border-radius:20px;font-size:.75rem;font-weight:700;"><?php echo $subj['tutor_count']; ?></span></td>
                                <td><span style="background:#dbeafe;color:#1e40af;padding:2px 10px;border-radius:20px;font-size:.75rem;font-weight:700;"><?php echo $subj['booking_count']; ?></span></td>
                                <td><span style="background:#dcfce7;color:#166534;padding:2px 10px;border-radius:20px;font-size:.75rem;font-weight:700;"><?php echo $subj['module_count']; ?></span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button onclick="openEditSubject(<?php echo $subj['id']; ?>, '<?php echo htmlspecialchars($subj['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($subj['description'] ?? '', ENT_QUOTES); ?>')"
                                                class="btn-sm" style="background:#f59e0b;color:#fff;">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <?php if ($subj['booking_count'] == 0 && $subj['module_count'] == 0): ?>
                                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('Delete subject \'<?php echo htmlspecialchars($subj['name'], ENT_QUOTES); ?>\'? This cannot be undone.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                                            <input type="hidden" name="subject_id" value="<?php echo $subj['id']; ?>">
                                            <input type="hidden" name="delete_subject" value="1">
                                            <button type="submit" class="btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                        <?php else: ?>
                                        <span style="font-size:.72rem;color:#9ca3af;" title="Cannot delete — subject is in use"><i class="fas fa-lock"></i> In use</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="no-data"><i class="fas fa-book"></i><p>No subjects yet. Add one above.</p></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Payments Tab -->
        <?php if ($active_tab == 'payments'): ?>
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-credit-card"></i> All Payments</h3>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Transaction ID</th><th>Student</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_payments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['transaction_number'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($payment['student_name']); ?></td>
                                    <td><strong>₱<?php echo number_format($payment['amount'], 2); ?></strong></td>
                                    <td><?php echo ucfirst($payment['payment_method'] ?? 'N/A'); ?></td>
                                    <td><?php echo $payment['payment_date'] ? date('M d, Y h:i A', strtotime($payment['payment_date'])) : date('M d, Y', strtotime($payment['created_at'])); ?></td>
                                    <td><span class="status-badge status-<?php echo $payment['status']; ?>"><?php echo ucfirst($payment['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <div class="total-revenue">
                        <strong>Total Revenue:</strong> ₱<?php echo number_format($total_revenue, 2); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Edit Subject Modal -->
<div id="editSubjectModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('editSubjectModal').style.display='none'">&times;</span>
        <h3><i class="fas fa-edit"></i> Edit Subject</h3>
        <form method="POST" style="margin-top:16px;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
            <input type="hidden" name="subject_id" id="editSubjectId">
            <input type="hidden" name="edit_subject" value="1">
            <div class="form-group">
                <label>Subject Name <span style="color:#dc2626;">*</span></label>
                <input type="text" name="subject_name" id="editSubjectName" required
                       style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-family:inherit;">
            </div>
            <div class="form-group" style="margin-top:12px;">
                <label>Description</label>
                <textarea name="subject_desc" id="editSubjectDesc" rows="3"
                          style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-family:inherit;resize:vertical;"></textarea>
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn-sm btn-approve" style="padding:9px 20px;">Save Changes</button>
                <button type="button" onclick="document.getElementById('editSubjectModal').style.display='none'" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeRejectModal()">&times;</span>
        <h3>Reject User Registration</h3>
        <p>User: <strong id="rejectUserName"></strong></p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
            <input type="hidden" name="user_id" id="rejectUserId">
            <input type="hidden" name="redirect_tab" id="rejectRedirectTab" value="pending">
            <input type="hidden" name="reject_user" value="1">
            <div class="form-group">
                <label for="rejection_reason">Rejection Reason</label>
                <textarea id="rejection_reason" name="rejection_reason" rows="3" required 
                          placeholder="Please provide a reason for rejection..."></textarea>
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn-primary btn-danger">Confirm Rejection</button>
                <button type="button" onclick="closeRejectModal()" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeDeleteModal()">&times;</span>
        <h3>Delete User</h3>
        <p>Are you sure you want to delete user: <strong id="deleteUserName"></strong>?</p>
        <p class="warning-text">This action cannot be undone. All associated data will be removed.</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
            <input type="hidden" name="user_id" id="deleteUserId">
            <input type="hidden" name="redirect_tab" id="deleteRedirectTab" value="users">
            <input type="hidden" name="delete_user" value="1">
            <div class="modal-actions">
                <button type="submit" class="btn-primary btn-danger">Yes, Delete User</button>
                <button type="button" onclick="closeDeleteModal()" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.dashboard-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    flex-wrap: wrap;
    border-bottom: 1px solid var(--gray-200);
    padding-bottom: 12px;
}
.tab-btn {
    padding: 10px 20px;
    background: transparent;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.tab-btn i {
    font-size: 0.9rem;
}
.tab-btn:hover {
    background: var(--gray-100);
}
.tab-btn.active {
    background: var(--primary-red);
    color: white;
}
.badge-count {
    background: rgba(255,255,255,0.3);
    color: white;
    border-radius: 20px;
    padding: 2px 8px;
    font-size: 0.7rem;
}
.content-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}
.table-responsive {
    overflow-x: auto;
}
.data-table {
    width: 100%;
    border-collapse: collapse;
}
.data-table th, .data-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--gray-200);
}
.data-table th {
    background: var(--gray-50);
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.data-table tr:hover td {
    background: var(--gray-50);
}
.search-input {
    padding: 8px 16px;
    border: 2px solid var(--gray-200);
    border-radius: 12px;
    font-size: 0.875rem;
    width: 250px;
}
.search-input:focus {
    outline: none;
    border-color: var(--primary-red);
}
.action-buttons {
    display: flex;
    gap: 6px;
}
.btn-sm {
    padding: 6px 12px;
    border: none;
    border-radius: 8px;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.btn-approve {
    background: var(--success);
    color: white;
}
.btn-approve:hover {
    background: var(--success-dark);
}
.btn-reject, .btn-danger {
    background: var(--danger);
    color: white;
}
.btn-reject:hover, .btn-danger:hover {
    background: #dc2626;
}
.warning-text {
    color: var(--danger);
    font-size: 0.875rem;
    margin-top: 8px;
}
.modal-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
    justify-content: flex-end;
}
.filter-select {
    padding: 8px 16px;
    border: 2px solid var(--gray-200);
    border-radius: 12px;
    font-size: 0.875rem;
}
.card-footer {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--gray-200);
    text-align: right;
}
.total-revenue {
    font-size: 1.1rem;
}
@media (max-width: 768px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    .dashboard-tabs {
        flex-wrap: wrap;
    }
    .tab-btn {
        padding: 6px 12px;
        font-size: 0.8rem;
    }
    .search-input {
        width: 100%;
    }
}
</style>

<script>
// Revenue Chart
<?php if ($active_tab == 'dashboard' && $monthly_revenue): ?>
const revenueCtx = document.getElementById('revenueChart')?.getContext('2d');
if (revenueCtx) {
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($monthly_revenue, 'month')); ?>,
            datasets: [{
                label: 'Revenue (₱)',
                data: <?php echo json_encode(array_map('floatval', array_column($monthly_revenue, 'total'))); ?>,
                borderColor: '#dc2626',
                backgroundColor: 'rgba(220, 38, 38, 0.1)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#dc2626',
                pointBorderColor: '#fff',
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (ctx) => `₱${ctx.raw.toLocaleString()}` } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { callback: (v) => '₱' + v.toLocaleString() } }
            }
        }
    });
}
<?php endif; ?>

// User search
function filterUsers() {
    const search = document.getElementById('userSearch')?.value.toLowerCase();
    if (!search) return;
    document.querySelectorAll('#usersTable tbody tr').forEach(row => {
        const name = row.dataset.name;
        row.style.display = name?.includes(search) ? '' : 'none';
    });
}
document.getElementById('userSearch')?.addEventListener('keyup', filterUsers);

// Booking filter
function filterBookings() {
    const filter = document.getElementById('bookingStatusFilter')?.value;
    if (!filter) return;
    document.querySelectorAll('#bookingsTable tbody tr').forEach(row => {
        const status = row.dataset.status;
        row.style.display = (filter === 'all' || status === filter) ? '' : 'none';
    });
}
document.getElementById('bookingStatusFilter')?.addEventListener('change', filterBookings);

// Modal functions
// Subject modal
function openEditSubject(id, name, desc) {
    document.getElementById('editSubjectId').value   = id;
    document.getElementById('editSubjectName').value = name;
    document.getElementById('editSubjectDesc').value = desc;
    document.getElementById('editSubjectModal').style.display = 'flex';
}

function showRejectModal(userId, userName, tab = 'pending') {
    document.getElementById('rejectUserId').value = userId;
    document.getElementById('rejectUserName').textContent = userName;
    document.getElementById('rejectRedirectTab').value = tab;
    document.getElementById('rejection_reason').value = '';
    document.getElementById('rejectModal').style.display = 'flex';
}
function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}
function confirmDelete(userId, userName, tab = 'users') {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').textContent = userName;
    document.getElementById('deleteRedirectTab').value = tab;
    document.getElementById('deleteModal').style.display = 'flex';
}
function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php include 'footer.php'; ?>