<?php
require_once 'config.php';
$page_title = 'Payment Successful';
if (!isLoggedIn()) redirect('login.php');

$user       = getUserById($_SESSION['user_id']);
$conn       = getConnection();
$booking_id = sanitizeInt($_GET['booking_id'] ?? 0);
$booking    = null;

if ($booking_id) {
    $stmt = $conn->prepare("
        SELECT b.*, s.name as subject_name, u.full_name as tutor_name
        FROM bookings b
        JOIN subjects s ON b.subject_id = s.id
        JOIN users u ON b.tutor_id = u.id
        WHERE b.id = ? AND b.student_id = ?
    ");
    $stmt->execute([$booking_id, $user['id']]);
    $booking = $stmt->fetch();
}

if ($booking) {
    // Get the payment record to find the checkout session ID
    $stmt = $conn->prepare("SELECT * FROM payments WHERE booking_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$booking_id]);
    $payment = $stmt->fetch();

    // If already paid (webhook fired first), just ensure booking status is also approved
    if ($booking['payment_status'] === 'paid') {
        if ($booking['status'] !== 'approved' && $booking['status'] !== 'completed') {
            $conn->prepare("UPDATE bookings SET status = 'approved' WHERE id = ?")
                 ->execute([$booking_id]);
        }
        // Refresh booking
        $stmt = $conn->prepare("SELECT b.*, s.name as subject_name, u.full_name as tutor_name FROM bookings b JOIN subjects s ON b.subject_id=s.id JOIN users u ON b.tutor_id=u.id WHERE b.id=?");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch();
    } elseif ($payment && $payment['payment_intent_id']) {
        // Verify with PayMongo checkout session
        $ch = curl_init(PAYMONGO_BASE_URL . "/checkout_sessions/" . urlencode($payment['payment_intent_id']));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Basic " . base64_encode(PAYMONGO_SECRET_KEY . ":")
        ]);
        $response = curl_exec($ch);
        $curl_err  = curl_errno($ch);
        curl_close($ch);

        if ($curl_err) {
            error_log("payment_success cURL error: " . $curl_err);
        }

        $result    = json_decode($response, true);
        $pm_status = $result['data']['attributes']['payment_status'] ?? 'unknown';

        error_log("payment_success: booking_id=$booking_id checkout_id={$payment['payment_intent_id']} pm_status=$pm_status");

        if ($pm_status === 'paid') {
            try {
                $conn->beginTransaction();
                $conn->prepare("UPDATE bookings SET payment_status = 'paid', status = 'approved' WHERE id = ?")
                     ->execute([$booking_id]);
                $conn->prepare("UPDATE payments SET status = 'completed', payment_date = NOW() WHERE booking_id = ?")
                     ->execute([$booking_id]);
                // Notify tutor
                $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'payment')")
                     ->execute([
                         $booking['tutor_id'],
                         "💰 Payment received from {$user['full_name']} for {$booking['subject_name']} on " . date('M d, Y', strtotime($booking['booking_date'])) . ". Booking is now active."
                     ]);
                // Notify student
                $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'payment')")
                     ->execute([
                         $user['id'],
                         "✅ Payment confirmed for your session with {$booking['tutor_name']} on " . date('M d, Y', strtotime($booking['booking_date'])) . "."
                     ]);
                $conn->commit();
                // Refresh booking data
                $stmt = $conn->prepare("SELECT b.*, s.name as subject_name, u.full_name as tutor_name FROM bookings b JOIN subjects s ON b.subject_id=s.id JOIN users u ON b.tutor_id=u.id WHERE b.id=?");
                $stmt->execute([$booking_id]);
                $booking = $stmt->fetch();
            } catch (PDOException $e) {
                $conn->rollBack();
                error_log("Payment success update error: " . $e->getMessage());
            }
        } else {
            // PayMongo says not paid yet — could be a delay, show success page anyway
            // but log it so we can investigate
            error_log("payment_success: PayMongo status is '$pm_status' for booking $booking_id — not updating DB");
        }
    } elseif (!$payment) {
        error_log("payment_success: No payment record found for booking_id=$booking_id");
    }
}
?>
<?php include 'header.php'; ?>
<div style="display:flex;align-items:center;justify-content:center;min-height:80vh;padding:20px;">
<div style="background:#fff;border-radius:20px;padding:48px 40px;max-width:520px;width:100%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.12);">

    <!-- Success icon -->
    <div style="width:80px;height:80px;background:linear-gradient(135deg,#10b981,#059669);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:2.2rem;color:white;">
        <i class="fas fa-check"></i>
    </div>

    <h1 style="color:#059669;font-size:1.6rem;font-weight:900;margin-bottom:6px;">Payment Confirmed!</h1>
    <p style="color:#6b7280;margin-bottom:24px;font-size:.9rem;">Your session is booked and the tutor has been notified.</p>

    <?php if ($booking): ?>
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:20px;margin-bottom:24px;text-align:left;">
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #d1fae5;font-size:.875rem;">
            <span style="color:#6b7280;">Subject</span>
            <strong><?php echo htmlspecialchars($booking['subject_name']); ?></strong>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #d1fae5;font-size:.875rem;">
            <span style="color:#6b7280;">Tutor</span>
            <strong><?php echo htmlspecialchars($booking['tutor_name']); ?></strong>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #d1fae5;font-size:.875rem;">
            <span style="color:#6b7280;">Date</span>
            <strong><?php echo date('F d, Y', strtotime($booking['booking_date'])); ?></strong>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #d1fae5;font-size:.875rem;">
            <span style="color:#6b7280;">Time</span>
            <strong><?php echo date('h:i A', strtotime($booking['start_time'])); ?> – <?php echo date('h:i A', strtotime($booking['end_time'])); ?></strong>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:.875rem;">
            <span style="color:#6b7280;">Amount Paid</span>
            <strong style="color:#059669;font-size:1rem;">₱<?php echo number_format($booking['amount'],2); ?></strong>
        </div>
    </div>

    <!-- Video call button — shown if session is today or upcoming -->
    <?php
    $session_start = strtotime($booking['booking_date'] . ' ' . ($booking['start_time'] ?? '00:00'));
    $session_end   = strtotime($booking['booking_date'] . ' ' . ($booking['end_time'] ?? '23:59'));
    $now = time();
    $can_join = $now >= ($session_start - 600) && $now <= $session_end;
    ?>
    <?php if ($can_join): ?>
    <a href="session_call.php?booking_id=<?php echo $booking_id; ?>" target="_blank"
       style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:14px;background:#dc2626;color:#fff;border-radius:10px;text-decoration:none;font-weight:700;font-size:.95rem;margin-bottom:12px;transition:all .2s;">
        <i class="fas fa-video"></i> Join Video Call Now
    </a>
    <?php else: ?>
    <div style="background:#fef9c3;border:1px solid #fde68a;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:.82rem;color:#92400e;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-info-circle"></i>
        The video call button will appear 10 minutes before your session on <strong><?php echo date('M d', strtotime($booking['booking_date'])); ?></strong>.
    </div>
    <?php endif; ?>

    <?php endif; ?>

    <a href="my_bookings.php"
       style="display:inline-flex;align-items:center;gap:8px;padding:12px 28px;background:#111;color:#fff;border:2px solid #111;border-radius:8px;text-decoration:none;font-weight:700;font-size:.875rem;transition:all .2s;">
        <i class="fas fa-list"></i> View My Bookings
    </a>
</div>
</div>
<?php include 'footer.php'; ?>
