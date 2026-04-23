<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
$booking_id = $_POST['booking_id'] ?? 0;
$conn = getConnection();

// Check if booking belongs to student and is pending payment
$stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ? AND student_id = ? AND payment_status = 'pending'");
$stmt->execute([$booking_id, $user['id']]);
$booking = $stmt->fetch();

if ($booking) {
    // Delete the booking and associated payment
    $stmt = $conn->prepare("DELETE FROM payments WHERE booking_id = ?");
    $stmt->execute([$booking_id]);
    
    $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    
    setFlash("Booking cancelled successfully.", 'info');
} else {
    setFlash("Cannot cancel this booking.", 'error');
}

redirect('my_bookings.php');
?>