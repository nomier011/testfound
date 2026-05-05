<?php
require_once 'config.php';
$page_title = 'My Sessions';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
$conn = getConnection();

// Get sessions based on role
if ($user['role'] == 'student') {
    $stmt = $conn->prepare("
        SELECT b.*, sub.name as subject_name, u.full_name as other_party, u.profile_pic as other_pic,
               u.full_name as tutor_name
        FROM bookings b
        JOIN subjects sub ON b.subject_id = sub.id
        JOIN users u ON b.tutor_id = u.id
        WHERE b.student_id = ?
        ORDER BY b.booking_date DESC
    ");
    $stmt->execute([$user['id']]);
} elseif ($user['role'] == 'tutor') {
    $stmt = $conn->prepare("
        SELECT b.*, sub.name as subject_name, u.full_name as other_party, u.profile_pic as other_pic,
               u.full_name as student_name
        FROM bookings b
        JOIN subjects sub ON b.subject_id = sub.id
        JOIN users u ON b.student_id = u.id
        WHERE b.tutor_id = ?
        ORDER BY b.booking_date DESC
    ");
    $stmt->execute([$user['id']]);
} else {
    $stmt = $conn->prepare("
        SELECT b.*, sub.name as subject_name,
               u1.full_name as student_name, u2.full_name as tutor_name
        FROM bookings b
        JOIN subjects sub ON b.subject_id = sub.id
        JOIN users u1 ON b.student_id = u1.id
        JOIN users u2 ON b.tutor_id = u2.id
        ORDER BY b.booking_date DESC
    ");
    $stmt->execute();
}

$sessions = $stmt->fetchAll();
?>

<?php include 'header.php'; ?>

<div class="main-content">
    <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="images/scclogo.png" alt="SCC Logo">
            <h3><?php echo ucfirst($user['role']); ?> Menu</h3>
        </div>
        
        <nav class="sidebar-nav">
            <?php if ($user['role'] == 'student'): ?>
                <a href="student_dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="book_session.php" class="sidebar-link"><i class="fas fa-calendar-plus"></i> Create Booking</a>
                <a href="view_instructors.php" class="sidebar-link"><i class="fas fa-chalkboard-teacher"></i> View Instructors</a>
                <a href="view_bookings.php" class="sidebar-link"><i class="fas fa-list-alt"></i> View Bookings</a>
                <a href="view_grades.php" class="sidebar-link"><i class="fas fa-chart-line"></i> View Grades</a>
                <a href="view_payments.php" class="sidebar-link"><i class="fas fa-credit-card"></i> Payment Due</a>
            <?php elseif ($user['role'] == 'tutor'): ?>
                <a href="tutor_dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="my_sessions.php" class="sidebar-link active"><i class="fas fa-list-alt"></i> My Sessions</a>
            <?php else: ?>
                <a href="admin_dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="my_sessions.php" class="sidebar-link active"><i class="fas fa-list-alt"></i> All Sessions</a>
            <?php endif; ?>
            <a href="profile.php" class="sidebar-link"><i class="fas fa-user-circle"></i> Profile</a>
            <a href="logout.php" class="sidebar-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <?php if ($user['profile_pic']): ?>
                        <img src="uploads/<?php echo $user['profile_pic']; ?>" alt="Profile">
                    <?php else: ?>
                        <?php echo substr($user['full_name'], 0, 1); ?>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo $user['full_name']; ?></div>
                    <div class="user-role"><?php echo ucfirst($user['role']); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="welcome-banner">
        <h1><i class="fas fa-list-alt"></i> My Sessions</h1>
        <p>View and manage all your tutoring sessions</p>
    </div>
    
    <div class="filter-section">
        <select id="statusFilter" class="filter-select" onchange="filterSessions()">
            <option value="all">All Sessions</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="completed">Completed</option>
            <option value="rejected">Rejected</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </div>
    
    <div class="content-card full-width">
        <?php if ($sessions): ?>
            <table class="sessions-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <?php if ($user['role'] == 'student'): ?>
                            <th>Tutor</th>
                        <?php elseif ($user['role'] == 'tutor'): ?>
                            <th>Student</th>
                        <?php else: ?>
                            <th>Student</th>
                            <th>Tutor</th>
                        <?php endif; ?>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Duration</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $session): ?>
                    <tr data-status="<?php echo $session['status']; ?>">
                        <td><?php echo $session['subject_name']; ?></td>
                        <?php if ($user['role'] == 'student'): ?>
                            <td>
                                <?php if (!empty($session['other_pic'])): ?>
                                    <img src="uploads/<?php echo $session['other_pic']; ?>" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover; vertical-align: middle; margin-right: 8px;">
                                <?php endif; ?>
                                <?php echo $session['tutor_name']; ?>
                            </td>
                        <?php elseif ($user['role'] == 'tutor'): ?>
                            <td>
                                <?php if (!empty($session['other_pic'])): ?>
                                    <img src="uploads/<?php echo $session['other_pic']; ?>" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover; vertical-align: middle; margin-right: 8px;">
                                <?php endif; ?>
                                <?php echo $session['student_name']; ?>
                            </td>
                        <?php else: ?>
                            <td><?php echo $session['student_name']; ?></td>
                            <td><?php echo $session['tutor_name']; ?></td>
                        <?php endif; ?>
                        <td><?php echo date('M d, Y', strtotime($session['booking_date'])); ?></td>
                        <td><?php echo date('h:i A', strtotime($session['start_time'])); ?> - <?php echo date('h:i A', strtotime($session['end_time'])); ?></td>
                        <td><?php echo $session['duration']; ?> hrs</td>
                        <td>₱<?php echo number_format($session['amount'], 2); ?></td>
                        <td><span class="status-badge status-<?php echo $session['status']; ?>"><?php echo ucfirst($session['status']); ?></span></td>
                        <td><span class="status-badge status-<?php echo $session['payment_status']; ?>"><?php echo ucfirst($session['payment_status']); ?></span></td>
                        <td>
                            <?php if ($session['status'] == 'pending' && $user['role'] == 'tutor'): ?>
                                <form method="POST" action="update_booking_status.php" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                                    <input type="hidden" name="booking_id" value="<?php echo $session['id']; ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" class="action-btn approve" onclick="return confirm('Approve?')">Approve</button>
                                </form>
                                <form method="POST" action="update_booking_status.php" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                                    <input type="hidden" name="booking_id" value="<?php echo $session['id']; ?>">
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" class="action-btn reject" onclick="return confirm('Reject?')">Reject</button>
                                </form>
                            <?php elseif ($session['status'] == 'pending' && $user['role'] == 'student'): ?>
                                <form method="POST" action="cancel_booking.php" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                                    <input type="hidden" name="booking_id" value="<?php echo $session['id']; ?>">
                                    <button type="submit" class="action-btn reject" onclick="return confirm('Cancel?')">Cancel</button>
                                </form>
                            <?php elseif ($session['status'] == 'approved' && $session['payment_status'] == 'pending' && $user['role'] == 'student'): ?>
                                <a href="payment.php?booking_id=<?php echo $session['id']; ?>" class="action-btn approve">Pay Now</a>
                            <?php elseif ($session['status'] == 'completed' && $user['role'] == 'student'): ?>
                                <a href="rate_session.php?booking_id=<?php echo $session['id']; ?>" class="action-btn approve">Rate</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">No sessions found.</div>
        <?php endif; ?>
    </div>
</div>

<!-- Rating Modal -->
<div id="ratingModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>Rate Your Session</h2>
        <form method="POST" action="rate_session.php" id="ratingForm">
            <input type="hidden" name="session_id" id="ratingSessionId">
            <div class="form-group">
                <label>How was your session?</label>
                <div class="star-rating">
                    <input type="radio" name="rating" value="5" id="star5"><label for="star5">★</label>
                    <input type="radio" name="rating" value="4" id="star4"><label for="star4">★</label>
                    <input type="radio" name="rating" value="3" id="star3"><label for="star3">★</label>
                    <input type="radio" name="rating" value="2" id="star2"><label for="star2">★</label>
                    <input type="radio" name="rating" value="1" id="star1"><label for="star1">★</label>
                </div>
            </div>
            <div class="form-group">
                <label for="feedback">Feedback (Optional)</label>
                <textarea id="feedback" name="feedback" rows="4" placeholder="Share your experience..."></textarea>
            </div>
            <button type="submit" class="btn-login">Submit Rating</button>
        </form>
    </div>
</div>

<script>
function filterSessions() {
    const filter = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('.sessions-table tbody tr');
    rows.forEach(row => {
        if (filter === 'all' || row.dataset.status === filter) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function showRatingModal(sessionId) {
    document.getElementById('ratingSessionId').value = sessionId;
    document.getElementById('ratingModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('ratingModal').style.display = 'none';
}
</script>

<style>
.full-width {
    width: 100%;
    overflow-x: auto;
}

.sessions-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

.sessions-table th,
.sessions-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.sessions-table th {
    background: #f5f5f5;
    font-weight: 600;
}

.filter-section {
    margin-bottom: 20px;
}

.filter-select {
    padding: 10px 15px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: var(--white);
    min-width: 200px;
}
</style>

<?php include 'footer.php'; ?>