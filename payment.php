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

$booking_id = sanitizeInt($_GET['booking_id'] ?? 0);
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
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash("Invalid request.", 'danger');
        redirect("payment.php?booking_id=$booking_id");
    }

    $payment_method = $_POST['payment_method'];

    // Validate against allowed methods
    $allowed_methods = ['gcash', 'paymaya', 'grab_pay', 'card', 'cash'];
    if (!in_array($payment_method, $allowed_methods, true)) {
        setFlash("Invalid payment method.", 'danger');
        redirect("payment.php?booking_id=$booking_id");
    }

    // For Cash payment - direct approval
    if ($payment_method == 'cash') {
        try {
            $conn->beginTransaction();

            $txn_number = 'TXN-CASH-' . strtoupper(uniqid());

            $conn->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = ?")
                 ->execute([$booking_id]);
            $conn->prepare("INSERT INTO payments (transaction_number, booking_id, student_id, amount, payment_method, status, payment_date) VALUES (?, ?, ?, ?, 'cash', 'completed', NOW())")
                 ->execute([$txn_number, $booking_id, $user['id'], $booking['amount']]);

            $conn->commit();
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Cash payment error: " . $e->getMessage());
            setFlash("Failed to process payment. Please try again.", 'danger');
            redirect("payment.php?booking_id=$booking_id");
        }

        setFlash("Booking confirmed! Please pay cash to the tutor before the session.", 'success');

        // Notify tutor about cash payment
        try {
            $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'payment')")
                 ->execute([
                     $booking['tutor_id'],
                     "💰 Cash payment confirmed for your session with {$user['full_name']} on " . date('M d, Y', strtotime($booking['booking_date'])) . ". Session ID: #$booking_id"
                 ]);
        } catch (Exception $e) { /* fail silently */ }

        redirect('my_bookings.php');
    }

    // For online payments - redirect to PayMongo
    $amount_cents = intval($booking['amount'] * 100);

    // Map internal method names to PayMongo checkout session payment_method_types
    $pm_method_map = [
        'gcash'    => 'gcash',
        'paymaya'  => 'paymaya',
        'grab_pay' => 'grab_pay',
        'card'     => 'card',
    ];
    $pm_method = $pm_method_map[$payment_method] ?? 'gcash';

    $payload = [
        "data" => [
            "attributes" => [
                "send_email_receipt" => true,
                "show_description"   => true,
                "show_line_items"    => true,
                "payment_method_types" => [$pm_method],
                "line_items" => [
                    [
                        "name"        => "Tutoring Session: {$booking['subject_name']}",
                        "quantity"    => 1,
                        "amount"      => $amount_cents,
                        "currency"    => "PHP",
                        "description" => "Session with {$booking['tutor_name']}"
                    ]
                ],
                "reference_number" => "BSIT-" . $booking_id . "-" . time(),
                "description"      => "Tutoring session payment - Booking #{$booking_id}",
                "success_url"      => SITE_URL . "payment_success.php?booking_id=" . $booking_id,
                "cancel_url"       => SITE_URL . "payment_cancel.php?booking_id=" . $booking_id,
                "billing" => [
                    "name"  => $user['full_name'],
                    "email" => $user['email']
                ]
            ]
        ]
    ];

    $ch = curl_init(PAYMONGO_BASE_URL . "/checkout_sessions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Basic " . base64_encode(PAYMONGO_SECRET_KEY . ":")
    ]);

    $response = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_errno($ch);
    curl_close($ch);

    if ($curl_err) {
        error_log("PayMongo cURL error: " . $curl_err);
        setFlash("Could not reach payment provider. Please try again.", 'danger');
        redirect("payment.php?booking_id=$booking_id");
    }

    if ($httpCode == 200 || $httpCode == 201) {
        $result       = json_decode($response, true);
        $checkout_url = $result['data']['attributes']['checkout_url'] ?? null;
        $checkout_id  = $result['data']['id'] ?? null;

        if (!$checkout_url || !$checkout_id) {
            error_log("PayMongo missing checkout_url/id: " . $response);
            setFlash("Payment setup failed. Please try again.", 'danger');
            redirect("payment.php?booking_id=$booking_id");
        }

        // Save checkout session ID — use a guaranteed-unique transaction number
        try {
            $conn->beginTransaction();
            $txn_number = 'TXN-' . date('YmdHis') . '-' . $booking_id . '-' . bin2hex(random_bytes(4));
            // Remove any old pending payment record for this booking
            $conn->prepare("DELETE FROM payments WHERE booking_id = ? AND status = 'pending'")->execute([$booking_id]);
            // Insert fresh payment record
            $conn->prepare("INSERT INTO payments (transaction_number, booking_id, student_id, amount, payment_method, payment_intent_id, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')")
                 ->execute([$txn_number, $booking_id, $user['id'], $booking['amount'], $payment_method, $checkout_id]);
            $conn->commit();
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Payment record save error: " . $e->getMessage());
            setFlash("Could not save payment record. Please try again.", 'danger');
            redirect("payment.php?booking_id=$booking_id");
        }

        header("Location: " . $checkout_url);
        exit();
    } else {
        $result    = json_decode($response, true);
        $err       = $result['errors'][0]['detail'] ?? 'Unknown PayMongo error';
        error_log("PayMongo checkout session error (HTTP $httpCode): " . $response);
        setFlash("Payment failed: $err", 'danger');
        redirect("payment.php?booking_id=$booking_id");
    }
}
?>

<?php include 'header.php'; ?>

<div class="page-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="page-hero">
        <div class="page-hero-content">
            <h1>Complete Payment</h1>
            <p>Secure payment for your session</p>
        </div>
    </div>

    <div class="page-inner">
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
                
                <form method="POST" action="" id="paymentForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                    <input type="hidden" name="payment_method" id="selectedMethod" value="">
                    <div class="payment-methods">
                        <button type="button" class="payment-method-btn" onclick="selectMethod('gcash', this)">
                            <i class="fas fa-mobile-alt"></i> GCash
                        </button>
                        <button type="button" class="payment-method-btn" onclick="selectMethod('paymaya', this)">
                            <i class="fas fa-wallet"></i> PayMaya
                        </button>
                        <button type="button" class="payment-method-btn" onclick="selectMethod('grab_pay', this)">
                            <i class="fas fa-taxi"></i> GrabPay
                        </button>
                        <button type="button" class="payment-method-btn" onclick="selectMethod('card', this)">
                            <i class="fas fa-credit-card"></i> Credit/Debit Card
                        </button>
                        <button type="button" class="payment-method-btn cash-btn" onclick="selectMethod('cash', this)">
                            <i class="fas fa-money-bill"></i> Cash
                        </button>
                    </div>
                    <button type="submit" id="payBtn" class="payment-method-btn" style="display:none;width:100%;margin-top:10px;background:#111;font-size:1rem;padding:16px;">
                        <i class="fas fa-lock"></i> Confirm Payment
                    </button>
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
.payment-method-btn.selected {
    background: rgba(139,0,0,1);
    border: 2px solid #fff;
    box-shadow: 0 0 0 3px rgba(139,0,0,0.5);
    transform: translateY(-2px);
}
.cash-btn {
    background: rgba(76,175,80,0.8);
}
.cash-btn:hover, .cash-btn.selected {
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
<script>
function selectMethod(method, btn) {
    // Deselect all
    document.querySelectorAll('.payment-method-btn').forEach(b => b.classList.remove('selected'));
    // Select clicked
    btn.classList.add('selected');
    // Set hidden input
    document.getElementById('selectedMethod').value = method;
    // Show confirm button
    document.getElementById('payBtn').style.display = 'block';
}

document.getElementById('paymentForm').addEventListener('submit', function(e) {
    const method = document.getElementById('selectedMethod').value;
    if (!method) {
        e.preventDefault();
        alert('Please select a payment method first.');
        return;
    }
    // Disable button to prevent double submit
    const btn = document.getElementById('payBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
});
</script>