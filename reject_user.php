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
    $reason = $_POST['rejection_reason'] ?? '';
    
    $conn = getConnection();
    $stmt = $conn->prepare("UPDATE users SET status = 'rejected', rejection_reason = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
    $stmt->execute([$reason, $admin['id'], $user_id]);
    
    setFlash("User rejected!", 'info');
    redirect('admin_dashboard.php');
}
?>