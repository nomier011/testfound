<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user       = getUserById($_SESSION['user_id']);
$booking_id = sanitizeInt($_GET['booking_id'] ?? 0);
$conn       = getConnection();

// Verify booking belongs to this student
$stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ? AND student_id = ?");
$stmt->execute([$booking_id, $user['id']]);
$booking = $stmt->fetch();

if (!$booking) {
    setFlash("Booking not found.", 'danger');
    redirect('my_bookings.php');
}

// Check payment record
$stmt = $conn->prepare("SELECT * FROM payments WHERE booking_id = ?");
$stmt->execute([$booking_id]);
$payment = $stmt->fetch();

if (!$payment || !$payment['payment_intent_id']) {
    setFlash("No payment record found.", 'warning');
    redirect('my_bookings.php');
}

// Query PayMongo for checkout session status
$url = PAYMONGO_BASE_URL . "/checkout_sessions/" . urlencode($payment['payment_intent_id']);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Basic " . base64_encode(PAYMONGO_SECRET_KEY . ":")
]);

$response = curl_exec($ch);
$curl_err  = curl_errno($ch);
curl_close($ch);

if ($curl_err) {
    error_log("PayMongo cURL error on check_payment: " . $curl_err);
    setFlash("Could not reach payment provider. Please try again later.", 'danger');
    redirect('my_bookings.php');
}

$result = json_decode($response, true);

if (isset($result['data']['attributes']['payment_status']) &&
    $result['data']['attributes']['payment_status'] == 'paid') {

    try {
        $conn->beginTransaction();

        $conn->prepare("UPDATE bookings SET payment_status = 'paid', status = 'approved' WHERE id = ?")
             ->execute([$booking_id]);
        $conn->prepare("UPDATE payments SET status = 'completed', payment_date = NOW() WHERE booking_id = ?")
             ->execute([$booking_id]);

        $conn->commit();
        setFlash("Payment confirmed! Your booking is now active.", 'success');
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Payment update error: " . $e->getMessage());
        setFlash("Payment confirmed but failed to update records. Please contact support.", 'warning');
    }
} else {
    setFlash("Payment not yet confirmed. Please complete your payment.", 'info');
}

redirect('my_bookings.php');
?>