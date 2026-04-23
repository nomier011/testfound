<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$admin = getUserById($_SESSION['user_id']);
if ($admin['role'] != 'admin') {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_POST['user_id'] ?? 0;
    
    $conn = getConnection();
    
    // Delete user's bookings first (cascade should handle, but just in case)
    $stmt = $conn->prepare("DELETE FROM bookings WHERE student_id = ? OR tutor_id = ?");
    $stmt->execute([$user_id, $user_id]);
    
    // Delete user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
    $stmt->execute([$user_id]);
    
    setFlash("User deleted successfully!", 'success');
    redirect('admin_dashboard.php');
}
?>