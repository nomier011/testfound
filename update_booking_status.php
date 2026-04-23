<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
$booking_id = $_POST['booking_id'] ?? 0;
$status = $_POST['status'] ?? '';
$conn = getConnection();

// Verify tutor owns this booking
$stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ? AND tutor_id = ?");
$stmt->execute([$booking_id, $user['id']]);
$booking = $stmt->fetch();

if (!$booking) {
    setFlash("Booking not found", 'error');
    redirect('tutor_dashboard.php');
}

// Update status
$stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
$stmt->execute([$status, $booking_id]);

// Notify student
$message = $status == 'approved' 
    ? "Your booking request has been approved! You can now make payment."
    : "Your booking request has been rejected. Please book another time.";
    
$stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)");
$stmt->execute([$booking['student_id'], $message, 'booking_' . $status]);

setFlash("Booking " . ucfirst($status) . " successfully!", 'success');
redirect('tutor_dashboard.php');
?>