<?php
require_once 'config.php';
$page_title = 'Payments';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
if ($user['role'] != 'student') {
    redirect('index.php');
}

$conn = getConnection();

// Get all payments
$stmt = $conn->prepare("
    SELECT p.*, s.subject_id, sub.name as subject_name, s.session_date,
           u.full_name as tutor_name
    FROM payments p
    JOIN sessions s ON p.session_id = s.id
    JOIN subjects sub ON s.subject_id = sub.id
    JOIN users u ON s.tutor_id = u.id
    WHERE p.student_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$user['id']]);
$payments = $stmt->fetchAll();

// Due payments
$due_payments = array_filter($payments, function($p) {
    return $p['payment_status'] == 'pending';
});
$total_due = array_sum(array_column($due_payments, 'amount'));

// Paid payments
$paid_payments = array_filter($payments, function($p) {
    return $p['payment_status'] == 'completed';
});
$total_paid = array_sum(array_column($paid_payments, 'amount'));
?>

<?php include 'header.php'; ?>

<div class="main-content">
    <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="images/scclogo.png" alt="SCC Logo">
            <h3>Student Menu</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="student_dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="book_session.php" class="sidebar-link"><i class="fas fa-calendar-plus"></i> Create Booking</a>
            <a href="view_instructors.php" class="sidebar-link"><i class="fas fa-chalkboard-teacher"></i> View Instructors</a>
            <a href="view_bookings.php" class="sidebar-link"><i class="fas fa-list-alt"></i> View Bookings</a>
            <a href="view_grades.php" class="sidebar-link"><i class="fas fa-chart-line"></i> View Grades</a>
            <a href="view_payments.php" class="sidebar-link active"><i class="fas fa-credit-card"></i> Payment Due</a>
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
                    <div class="user-role">Student • Year <?php echo $user['year_level']; ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="welcome-banner">
        <h1><i class="fas fa-credit-card"></i> Payments</h1>
        <p>Manage your payments and view transaction history</p>
    </div>
    
    <div class="payment-summary" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px;">
        <div style="background: rgba(255,255,255,0.95); border-radius: 15px; padding: 25px; text-align: center; border-left: 4px solid var(--danger-red);">
            <div style="color: var(--gray); text-transform: uppercase; font-size: 0.9rem;">Total Due</div>
            <div style="font-size: 2rem; font-weight: bold; color: var(--danger-red);">₱<?php echo number_format($total_due, 2); ?></div>
            <div><?php echo count($due_payments); ?> pending payment(s)</div>
        </div>
        <div style="background: rgba(255,255,255,0.95); border-radius: 15px; padding: 25px; text-align: center; border-left: 4px solid var(--success-green);">
            <div style="color: var(--gray); text-transform: uppercase; font-size: 0.9rem;">Total Paid</div>
            <div style="font-size: 2rem; font-weight: bold; color: var(--success-green);">₱<?php echo number_format($total_paid, 2); ?></div>
            <div><?php echo count($paid_payments); ?> completed payment(s)</div>
        </div>
    </div>
    
    <!-- Due Payments -->
    <div class="content-card" style="margin-bottom: 30px;">
        <div class="card-header">
            <h3><i class="fas fa-clock"></i> Pending Payments</h3>
        </div>
        <?php if ($due_payments): ?>
            <?php foreach ($due_payments as $payment): ?>
                <div class="payment-item" style="display: flex; align-items: center; gap: 20px; padding: 20px; margin-bottom: 15px; background: rgba(244,67,54,0.05); border-radius: 12px; border-left: 4px solid var(--danger-red);">
                    <div style="flex: 1;">
                        <strong><?php echo $payment['subject_name']; ?></strong>
                        <div>Tutor: <?php echo $payment['tutor_name']; ?></div>
                        <div style="font-size: 0.85rem; color: var(--gray);">Session: <?php echo date('M d, Y', strtotime($payment['session_date'])); ?></div>
                        <div style="font-size: 0.85rem; color: var(--danger-red);">Due: <?php echo date('M d, Y', strtotime($payment['due_date'])); ?></div>
                    </div>
                    <div style="font-size: 1.3rem; font-weight: bold; color: var(--danger-red);">₱<?php echo number_format($payment['amount'], 2); ?></div>
                    <a href="payment.php?session_id=<?php echo $payment['session_id']; ?>" class="action-btn approve" style="text-align: center; padding: 10px 20px;">Pay Now →</a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-data">No pending payments. You're all caught up! 🎉</div>
        <?php endif; ?>
    </div>
    
    <!-- Payment History -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Payment History</h3>
        </div>
        <?php if ($paid_payments): ?>
            <table class="payments-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f5f5f5;">
                        <th style="padding: 12px; text-align: left;">Date</th>
                        <th style="padding: 12px; text-align: left;">Session</th>
                        <th style="padding: 12px; text-align: left;">Tutor</th>
                        <th style="padding: 12px; text-align: left;">Amount</th>
                        <th style="padding: 12px; text-align: left;">Method</th>
                        <th style="padding: 12px; text-align: left;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paid_payments as $payment): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 10px;"><?php echo $payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : 'N/A'; ?></td>
                            <td style="padding: 10px;"><?php echo $payment['subject_name']; ?></td>
                            <td style="padding: 10px;"><?php echo $payment['tutor_name']; ?></td>
                            <td style="padding: 10px; font-weight: bold; color: var(--success-green);">₱<?php echo number_format($payment['amount'], 2); ?></td>
                            <td style="padding: 10px;"><?php echo ucfirst($payment['payment_method'] ?? 'N/A'); ?></td>
                            <td style="padding: 10px;"><span class="status-badge status-paid">Paid</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">No payment history yet.</div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>