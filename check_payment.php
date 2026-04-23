<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
$booking_id = $_GET['booking_id'] ?? 0;
$conn = getConnection();

// Check if booking exists
$stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ? AND student_id = ?");
$stmt->execute([$booking_id, $user['id']]);
$booking = $stmt->fetch();

if ($booking) {
    // Check if there's a payment record
    $stmt = $conn->prepare("SELECT * FROM payments WHERE booking_id = ?");
    $stmt->execute([$booking_id]);
    $payment = $stmt->fetch();
    
    if ($payment && $payment['payment_intent_id']) {
        // Check with PayMongo
        $url = PAYMONGO_BASE_URL . "/checkout_sessions/" . $payment['payment_intent_id'];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Basic " . base64_encode(PAYMONGO_SECRET_KEY . ":")
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if (isset($result['data']['attributes']['payment_status']) && 
            $result['data']['attributes']['payment_status'] == 'paid') {
            
            // Update booking
            $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'paid', status = 'approved' WHERE id = ?");
            $stmt->execute([$booking_id]);
            
            // Update payment
            $stmt = $conn->prepare("UPDATE payments SET status = 'completed', payment_date = NOW() WHERE booking_id = ?");
            $stmt->execute([$booking_id]);
            
            setFlash("Payment confirmed! Your booking is now active.", 'success');
        } else {
            setFlash("Payment not yet confirmed. Please complete your payment.", 'info');
        }
    } else {
        setFlash("No payment record found.", 'warning');
    }
} else {
    setFlash("Booking not found.", 'danger');
}

redirect('my_bookings.php');
?>