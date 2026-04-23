<?php
require_once 'config.php';
$page_title = 'My Bookings';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
$conn = getConnection();

if ($user['role'] == 'student') {
    $stmt = $conn->prepare("
        SELECT b.*, s.name as subject_name, u.full_name as tutor_name, u.profile_pic as tutor_pic
        FROM bookings b
        JOIN subjects s ON b.subject_id = s.id
        JOIN users u ON b.tutor_id = u.id
        WHERE b.student_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $bookings = $stmt->fetchAll();
} else {
    $stmt = $conn->prepare("
        SELECT b.*, s.name as subject_name, u.full_name as student_name, u.profile_pic as student_pic
        FROM bookings b
        JOIN subjects s ON b.subject_id = s.id
        JOIN users u ON b.student_id = u.id
        WHERE b.tutor_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $bookings = $stmt->fetchAll();
}
?>

<?php include 'header.php'; ?>

<div class="dashboard-container">
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="images/scclogo.png" alt="SCC Logo">
            <h3><?php echo ucfirst($user['role']); ?> Menu</h3>
        </div>
        <nav class="sidebar-nav">
            <?php if ($user['role'] == 'student'): ?>
                <a href="student_dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="book_session.php" class="sidebar-link"><i class="fas fa-calendar-plus"></i> Book a Tutor</a>
                <a href="my_bookings.php" class="sidebar-link active"><i class="fas fa-list-alt"></i> My Bookings</a>
                <a href="profile.php" class="sidebar-link"><i class="fas fa-user-circle"></i> Profile</a>
            <?php else: ?>
                <a href="tutor_dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="my_bookings.php" class="sidebar-link active"><i class="fas fa-list-alt"></i> Booking Requests</a>
                <a href="profile.php" class="sidebar-link"><i class="fas fa-user-circle"></i> Profile</a>
            <?php endif; ?>
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
                    <div class="user-role"><?php echo ucfirst($user['role']); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="welcome-banner">
            <h1><i class="fas fa-list-alt"></i> <?php echo $user['role'] == 'student' ? 'My Bookings' : 'Booking Requests'; ?></h1>
            <p>View and manage all your tutoring sessions</p>
        </div>
        
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
        <?php endif; ?>
        
        <div class="filter-tabs">
            <button class="tab-btn active" onclick="filterBookings('all')">All</button>
            <button class="tab-btn" onclick="filterBookings('pending')">Pending</button>
            <button class="tab-btn" onclick="filterBookings('approved')">Approved</button>
            <button class="tab-btn" onclick="filterBookings('completed')">Completed</button>
            <button class="tab-btn" onclick="filterBookings('cancelled')">Cancelled</button>
            <button class="tab-btn" onclick="filterBookings('rejected')">Rejected</button>
        </div>
        
        <?php if ($bookings): ?>
            <?php foreach ($bookings as $booking): ?>
                <div class="booking-card" data-status="<?php echo $booking['status']; ?>">
                    <div class="booking-header">
                        <div>
                            <span class="status-badge status-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span>
                            <span class="status-badge status-<?php echo $booking['payment_status']; ?>"><?php echo ucfirst($booking['payment_status']); ?></span>
                        </div>
                        <div class="booking-date">📅 <?php echo date('F d, Y', strtotime($booking['booking_date'])); ?></div>
                    </div>
                    
                    <div class="booking-body">
                        <div class="booking-info">
                            <?php if ($user['role'] == 'student'): ?>
                                <div class="info-row">
                                    <span class="info-label">Tutor:</span>
                                    <span class="info-value">
                                        <?php if ($booking['tutor_pic']): ?>
                                            <img src="uploads/<?php echo $booking['tutor_pic']; ?>" class="avatar-small">
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($booking['tutor_name']); ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="info-row">
                                    <span class="info-label">Student:</span>
                                    <span class="info-value">
                                        <?php if ($booking['student_pic']): ?>
                                            <img src="uploads/<?php echo $booking['student_pic']; ?>" class="avatar-small">
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($booking['student_name']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <div class="info-row">
                                <span class="info-label">Subject:</span>
                                <span class="info-value"><?php echo htmlspecialchars($booking['subject_name']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Time:</span>
                                <span class="info-value"><?php echo date('h:i A', strtotime($booking['start_time'])); ?> - <?php echo date('h:i A', strtotime($booking['end_time'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Duration:</span>
                                <span class="info-value"><?php echo $booking['duration']; ?> hours</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Amount:</span>
                                <span class="info-value amount">₱<?php echo number_format($booking['amount'], 2); ?></span>
                            </div>
                            <?php if ($booking['notes']): ?>
                                <div class="info-row notes">
                                    <span class="info-label">Notes:</span>
                                    <span class="info-value">"<?php echo htmlspecialchars($booking['notes']); ?>"</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="booking-actions">
                            <?php if ($user['role'] == 'student'): ?>
                                <?php if ($booking['status'] == 'pending'): ?>
                                    <div class="pending-message">
                                        <i class="fas fa-clock"></i> Waiting for tutor approval
                                    </div>
                                    <form method="POST" action="cancel_booking.php" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <button type="submit" class="action-btn cancel" onclick="return confirm('Cancel this booking?')">❌ Cancel</button>
                                    </form>
                                <?php elseif ($booking['status'] == 'approved' && $booking['payment_status'] == 'pending'): ?>
                                    <a href="payment.php?booking_id=<?php echo $booking['id']; ?>" class="action-btn pay">💰 Pay Now</a>
                                    <form method="POST" action="cancel_booking.php" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <button type="submit" class="action-btn cancel" onclick="return confirm('Cancel this booking?')">❌ Cancel</button>
                                    </form>
                                <?php elseif ($booking['status'] == 'approved' && $booking['payment_status'] == 'paid'): ?>
                                    <div class="approved-message">
                                        <i class="fas fa-check-circle"></i> Paid - Session confirmed
                                    </div>
                                <?php elseif ($booking['status'] == 'rejected'): ?>
                                    <div class="rejected-message">
                                        <i class="fas fa-times-circle"></i> Booking rejected by tutor
                                    </div>
                                <?php elseif ($booking['status'] == 'completed'): ?>
                                    <div class="completed-message">
                                        <i class="fas fa-star"></i> Session completed
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($booking['payment_status'] == 'paid'): ?>
                                    <a href="download_receipt.php?booking_id=<?php echo $booking['id']; ?>" class="btn-download" target="_blank">
                                        <i class="fas fa-download"></i> Download Receipt
                                    </a>
                                <?php endif; ?>
                                
                            <?php else: ?>
                                <!-- Tutor Actions -->
                                <?php if ($booking['status'] == 'pending'): ?>
                                    <form method="POST" action="update_booking_status.php" style="display: inline-block;">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <input type="hidden" name="status" value="approved">
                                        <button type="submit" class="action-btn approve" onclick="return confirm('Approve this session?')">✓ Approve</button>
                                    </form>
                                    <form method="POST" action="update_booking_status.php" style="display: inline-block;">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <input type="hidden" name="status" value="rejected">
                                        <button type="submit" class="action-btn reject" onclick="return confirm('Reject this session?')">✗ Reject</button>
                                    </form>
                                <?php elseif ($booking['status'] == 'approved'): ?>
                                    <form method="POST" action="update_booking_status.php" style="display: inline-block;">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <input type="hidden" name="status" value="completed">
                                        <button type="submit" class="action-btn complete" onclick="return confirm('Mark as completed?')">✓ Mark Complete</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-data">
                <i class="fas fa-calendar-alt"></i>
                <p>No bookings found.</p>
                <?php if ($user['role'] == 'student'): ?>
                    <a href="book_session.php" class="btn-primary">Book Your First Session</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.alert {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.alert-success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}
.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}
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
.booking-card {
    background: rgba(255,255,255,0.88);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    transition: all 0.3s;
    border: 1px solid rgba(255,255,255,0.3);
}
.booking-card:hover {
    transform: translateX(5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
.booking-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(0,0,0,0.1);
    flex-wrap: wrap;
    gap: 10px;
}
.booking-date {
    color: #666;
}
.booking-body {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}
.booking-info {
    flex: 1;
}
.info-row {
    display: flex;
    margin-bottom: 8px;
}
.info-label {
    width: 120px;
    color: #666;
}
.info-value {
    flex: 1;
}
.info-value.amount {
    color: var(--success-green);
    font-weight: bold;
}
.notes .info-value {
    font-style: italic;
    color: #666;
}
.avatar-small {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    object-fit: cover;
    vertical-align: middle;
    margin-right: 8px;
}
.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}
.status-approved, .status-paid {
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
.booking-actions {
    min-width: 180px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.action-btn {
    display: block;
    width: 100%;
    padding: 8px 12px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
    transition: all 0.3s;
}
.action-btn.pay {
    background: var(--success-green);
    color: white;
}
.action-btn.cancel {
    background: var(--danger-red);
    color: white;
}
.action-btn.approve {
    background: var(--success-green);
    color: white;
}
.action-btn.reject {
    background: var(--danger-red);
    color: white;
}
.action-btn.complete {
    background: var(--info-blue);
    color: white;
}
.action-btn:hover {
    transform: translateY(-2px);
}
.pending-message, .approved-message, .completed-message, .rejected-message {
    text-align: center;
    padding: 10px;
    border-radius: 8px;
    font-weight: 500;
}
.pending-message {
    background: #fff3cd;
    color: #856404;
}
.approved-message {
    background: #d4edda;
    color: #155724;
}
.completed-message {
    background: #cfe2ff;
    color: #084298;
}
.rejected-message {
    background: #f8d7da;
    color: #721c24;
}
.btn-download {
    display: block;
    text-align: center;
    padding: 8px 12px;
    background: rgba(33,150,243,0.8);
    color: white;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.3s;
}
.btn-download:hover {
    background: rgba(33,150,243,1);
    transform: translateY(-2px);
}
.no-data {
    text-align: center;
    padding: 60px;
    background: rgba(255,255,255,0.88);
    border-radius: 15px;
    color: #999;
}
.no-data i {
    font-size: 3rem;
    margin-bottom: 15px;
}
.no-data .btn-primary {
    display: inline-block;
    margin-top: 15px;
    padding: 10px 20px;
    background: var(--primary-red);
    color: white;
    text-decoration: none;
    border-radius: 8px;
}
@media (max-width: 768px) {
    .booking-body {
        flex-direction: column;
    }
    .booking-actions {
        width: 100%;
    }
    .info-row {
        flex-direction: column;
    }
    .info-label {
        width: auto;
        margin-bottom: 3px;
    }
}
</style>

<script>
function filterBookings(status) {
    const cards = document.querySelectorAll('.booking-card');
    const tabs = document.querySelectorAll('.tab-btn');
    tabs.forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');
    cards.forEach(card => {
        if (status === 'all' || card.dataset.status === status) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>

<?php include 'footer.php'; ?>