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
    $action = $_POST['action'] ?? '';
    
    $conn = getConnection();
    
    if ($action == 'approve') {
        $stmt = $conn->prepare("UPDATE users SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt->execute([$admin['id'], $user_id]);
        setFlash("User approved successfully!", 'success');
    }
    
    redirect('admin_dashboard.php');
}
?>