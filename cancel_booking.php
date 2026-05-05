<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash("Invalid request.", 'danger');
    redirect('my_bookings.php');
}

$user       = getUserById($_SESSION['user_id']);
$booking_id = sanitizeInt($_POST['booking_id'] ?? 0);
$conn       = getConnection();

// Verify ownership and cancellable state
$stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ? AND student_id = ? AND payment_status = 'pending' AND status IN ('pending', 'approved')");
$stmt->execute([$booking_id, $user['id']]);
$booking = $stmt->fetch();

if ($booking) {
    $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$booking_id]);
    setFlash("Booking cancelled successfully.", 'success');
} else {
    setFlash("Cannot cancel this booking. It may already be paid or completed.", 'danger');
}

redirect('my_bookings.php');
?>