<?php
require_once 'config.php';

$page_title = 'Make Payment';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
if ($user['role'] != 'student') {
    redirect('dashboard.php');
}

$booking_id = $_GET['booking_id'] ?? 0;
$conn = getConnection();

// Get booking details - must be approved to pay
$stmt = $conn->prepare("
    SELECT b.*, sub.name as subject_name, u.full_name as tutor_name, u.hourly_rate
    FROM bookings b
    JOIN subjects sub ON b.subject_id = sub.id
    JOIN users u ON b.tutor_id = u.id
    WHERE b.id = ? AND b.student_id = ? AND b.status = 'approved' AND b.payment_status = 'pending'
");
$stmt->execute([$booking_id, $user['id']]);
$booking = $stmt->fetch();

if (!$booking) {
    setFlash("Invalid booking or payment not required.", 'danger');
    redirect('my_bookings.php');
}

// Process payment when method is selected
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['payment_method'])) {
    $payment_method = $_POST['payment_method'];
    
    // For Cash payment - direct approval
    if ($payment_method == 'cash') {
        $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = ?");
        $stmt->execute([$booking_id]);
        
        $stmt = $conn->prepare("INSERT INTO payments (booking_id, student_id, amount, payment_method, status, payment_date) VALUES (?, ?, ?, 'cash', 'completed', NOW())");
        $stmt->execute([$booking_id, $user['id'], $booking['amount']]);
        
        setFlash("✅ Booking confirmed! Please pay cash to the tutor before the session.", 'success');
        redirect('my_bookings.php');
    }
    
    // For online payments - redirect to PayMongo
    $amount_cents = intval($booking['amount'] * 100);
    
    $payload = [
        "data" => [
            "attributes" => [
                "send_email_receipt" => true,
                "show_description" => true,
                "show_line_items" => true,
                "payment_method_types" => [$payment_method],
                "line_items" => [
                    [
                        "name" => "Tutoring Session: {$booking['subject_name']}",
                        "quantity" => 1,
                        "amount" => $amount_cents,
                        "currency" => "PHP"
                    ]
                ],
                "reference_number" => "BSIT-" . $booking_id . "-" . time(),
                "description" => "Payment for tutoring session",
                "success_url" => SITE_URL . "payment_success.php?booking_id=" . $booking_id,
                "cancel_url" => SITE_URL . "payment_cancel.php?booking_id=" . $booking_id,
                "billing" => [
                    "name" => $user['full_name'],
                    "email" => $user['email']
                ]
            ]
        ]
    ];
    
    $ch = curl_init(PAYMONGO_BASE_URL . "/checkout_sessions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Basic " . base64_encode(PAYMONGO_SECRET_KEY . ":")
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200 || $httpCode == 201) {
        $result = json_decode($response, true);
        $checkout_url = $result['data']['attributes']['checkout_url'];
        
        header("Location: " . $checkout_url);
        exit();
    } else {
        setFlash("Failed to create checkout session. Please try again.", 'danger');
    }
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
            <a href="student_dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
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
            <h1><i class="fas fa-credit-card"></i> Complete Payment</h1>
            <p>Your session has been approved! Complete payment to confirm.</p>
        </div>
        
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
        <?php endif; ?>
        
        <div class="payment-container">
            <div class="payment-summary">
                <h3>Session Summary</h3>
                <div class="summary-item">
                    <span>Tutor:</span>
                    <strong><?php echo htmlspecialchars($booking['tutor_name']); ?></strong>
                </div>
                <div class="summary-item">
                    <span>Subject:</span>
                    <strong><?php echo htmlspecialchars($booking['subject_name']); ?></strong>
                </div>
                <div class="summary-item">
                    <span>Date:</span>
                    <strong><?php echo date('F d, Y', strtotime($booking['booking_date'])); ?></strong>
                </div>
                <div class="summary-item">
                    <span>Time:</span>
                    <strong><?php echo date('h:i A', strtotime($booking['start_time'])); ?> - <?php echo date('h:i A', strtotime($booking['end_time'])); ?></strong>
                </div>
                <div class="summary-item">
                    <span>Duration:</span>
                    <strong><?php echo $booking['duration']; ?> hours</strong>
                </div>
                <div class="summary-item total">
                    <span>Total Amount:</span>
                    <strong class="total-amount">₱<?php echo number_format($booking['amount'], 2); ?></strong>
                </div>
            </div>
            
            <div class="payment-form">
                <h3>Select Payment Method</h3>
                
                <form method="POST" action="">
                    <div class="payment-methods">
                        <button type="submit" name="payment_method" value="gcash" class="payment-method-btn">
                            <i class="fas fa-mobile-alt"></i> GCash
                        </button>
                        <button type="submit" name="payment_method" value="paymaya" class="payment-method-btn">
                            <i class="fas fa-wallet"></i> PayMaya
                        </button>
                        <button type="submit" name="payment_method" value="grab_pay" class="payment-method-btn">
                            <i class="fas fa-taxi"></i> GrabPay
                        </button>
                        <button type="submit" name="payment_method" value="card" class="payment-method-btn">
                            <i class="fas fa-credit-card"></i> Credit/Debit Card
                        </button>
                        <button type="submit" name="payment_method" value="cash" class="payment-method-btn cash-btn">
                            <i class="fas fa-money-bill"></i> Cash
                        </button>
                    </div>
                </form>
                
                <div class="payment-note">
                    <i class="fas fa-lock"></i> Secure payment powered by PayMongo
                </div>
            </div>
        </div>
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
.payment-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    margin-bottom: 25px;
}
.payment-summary, .payment-form {
    background: rgba(255,255,255,0.88);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 25px;
    border: 1px solid rgba(255,255,255,0.3);
}
.payment-summary h3, .payment-form h3 {
    color: var(--primary-red);
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--primary-red);
}
.summary-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}
.summary-item.total {
    margin-top: 10px;
    padding-top: 15px;
    border-top: 2px solid var(--primary-red);
    border-bottom: none;
    font-size: 1.1rem;
}
.total-amount {
    color: var(--success-green);
    font-size: 1.3rem;
}
.payment-methods {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}
.payment-method-btn {
    width: 100%;
    padding: 15px;
    background: rgba(139,0,0,0.8);
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 10px;
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-align: center;
}
.payment-method-btn i {
    margin-right: 8px;
}
.payment-method-btn:hover {
    background: rgba(139,0,0,1);
    transform: translateY(-2px);
}
.cash-btn {
    background: rgba(76,175,80,0.8);
}
.cash-btn:hover {
    background: rgba(76,175,80,1);
}
.payment-note {
    background: #e8f4fd;
    padding: 12px;
    border-radius: 8px;
    text-align: center;
    margin-top: 20px;
    font-size: 0.85rem;
}
@media (max-width: 768px) {
    .payment-container {
        grid-template-columns: 1fr;
    }
    .payment-methods {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'footer.php'; ?>