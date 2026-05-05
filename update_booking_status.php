<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash("Invalid request.", 'danger');
    redirectBack();
}

$user = getCurrentUser();
requireRole('tutor');

$booking_id = sanitizeInt($_POST['booking_id'] ?? 0);
$status = $_POST['status'] ?? '';

$allowed_statuses = ['approved', 'rejected'];
if (!in_array($status, $allowed_statuses)) {
    setFlash("Invalid status.", 'danger');
    redirectBack();
}

$conn = getConnection();

// Verify tutor owns this booking
$stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ? AND tutor_id = ?");
$stmt->execute([$booking_id, $user['id']]);
$booking = $stmt->fetch();

if (!$booking) {
    setFlash("Booking not found.", 'danger');
    redirectBack();
}

try {
    $conn->beginTransaction();
    
    $conn->prepare("UPDATE bookings SET status = ?, updated_at = NOW() WHERE id = ?")
         ->execute([$status, $booking_id]);
    
    $message = $status == 'approved' 
        ? "Your booking request has been approved! You can now proceed to payment."
        : "Your booking request has been rejected. Please book another time.";
    
    sendNotification($booking['student_id'], $message, 'booking');
    logActivity($user['id'], 'update_booking', "Updated booking #$booking_id to $status");
    
    $conn->commit();
    setFlash("Booking " . ucfirst($status) . " successfully!", 'success');
} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Update booking error: " . $e->getMessage());
    setFlash("Failed to update booking.", 'danger');
}

redirectBack();