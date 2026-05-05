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
    SELECT p.*, b.booking_date as session_date, sub.name as subject_name,
           u.full_name as tutor_name, p.status as payment_status
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    JOIN subjects sub ON b.subject_id = sub.id
    JOIN users u ON b.tutor_id = u.id
    WHERE p.student_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$user['id']]);
$payments = $stmt->fetchAll();

// Due payments — approved bookings with no completed payment
$stmt2 = $conn->prepare("
    SELECT b.*, sub.name as subject_name, u.full_name as tutor_name
    FROM bookings b
    JOIN subjects sub ON b.subject_id = sub.id
    JOIN users u ON b.tutor_id = u.id
    WHERE b.student_id = ? AND b.status = 'approved' AND b.payment_status = 'pending'
");
$stmt2->execute([$user['id']]);
$due_bookings = $stmt2->fetchAll();
$total_due = array_sum(array_column($due_bookings, 'amount'));

$paid_payments = array_filter($payments, fn($p) => $p['status'] == 'completed');
$total_paid = array_sum(array_column($paid_payments, 'amount'));
?>

<?php include 'header.php'; ?>

<div class="page-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="page-hero">
        <div class="page-hero-content">
            <h1>Payments</h1>
            <p>View your payment history</p>
        </div>
    </div>

    <div class="page-inner">
    
    <div class="payment-summary" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px;">
        <div style="background: rgba(255,255,255,0.95); border-radius: 15px; padding: 25px; text-align: center; border-left: 4px solid var(--danger-red);">
            <div style="color: var(--gray); text-transform: uppercase; font-size: 0.9rem;">Total Due</div>
            <div style="font-size: 2rem; font-weight: bold; color: var(--danger-red);">₱<?php echo number_format($total_due, 2); ?></div>
            <div><?php echo count($due_bookings); ?> pending payment(s)</div>
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
        <?php if ($due_bookings): ?>
            <?php foreach ($due_bookings as $payment): ?>
                <div class="payment-item" style="display: flex; align-items: center; gap: 20px; padding: 20px; margin-bottom: 15px; background: rgba(244,67,54,0.05); border-radius: 12px; border-left: 4px solid var(--danger-red);">
                    <div style="flex: 1;">
                        <strong><?php echo htmlspecialchars($payment['subject_name']); ?></strong>
                        <div>Tutor: <?php echo htmlspecialchars($payment['tutor_name']); ?></div>
                        <div style="font-size: 0.85rem; color: var(--gray);">Session: <?php echo date('M d, Y', strtotime($payment['booking_date'])); ?></div>
                    </div>
                    <div style="font-size: 1.3rem; font-weight: bold; color: var(--danger-red);">₱<?php echo number_format($payment['amount'], 2); ?></div>
                    <a href="payment.php?booking_id=<?php echo $payment['id']; ?>" class="action-btn approve" style="text-align: center; padding: 10px 20px;">Pay Now →</a>
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
</div>

<?php include 'footer.php'; ?>