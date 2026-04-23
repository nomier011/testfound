<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
$booking_id = $_GET['booking_id'] ?? 0;
$conn = getConnection();

if ($booking_id) {
    // Update booking status
    $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'paid', status = 'approved' WHERE id = ? AND student_id = ?");
    $stmt->execute([$booking_id, $user['id']]);
    
    // Update payment record
    $stmt = $conn->prepare("UPDATE payments SET status = 'completed', payment_date = NOW() WHERE booking_id = ?");
    $stmt->execute([$booking_id]);
    
    setFlash("✅ Payment successful! Your booking has been confirmed.", 'success');
} else {
    setFlash("Invalid payment reference.", 'danger');
}

redirect('my_bookings.php');
?>