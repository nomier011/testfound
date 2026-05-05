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
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash("Invalid request.", 'danger');
        redirect('admin_dashboard.php?tab=pending');
    }

    $user_id      = sanitizeInt($_POST['user_id'] ?? 0);
    $reason       = trim($_POST['rejection_reason'] ?? '');
    $redirect_tab = in_array($_POST['redirect_tab'] ?? '', ['pending', 'users']) ? $_POST['redirect_tab'] : 'pending';

    $conn      = getConnection();
    $user_data = getUserById($user_id);

    if (!$user_data) {
        setFlash("User not found.", 'danger');
        redirect('admin_dashboard.php?tab=' . $redirect_tab);
    }

    $reason_text = $reason ?: 'No reason provided.';

    $conn->prepare("UPDATE users SET status = 'rejected', rejection_reason = ?, approved_by = ?, approved_at = NOW() WHERE id = ?")
         ->execute([$reason_text, $admin['id'], $user_id]);

    sendNotification($user_id, "❌ Your account registration was rejected. Reason: {$reason_text}", 'account');
    logActivity($admin['id'], 'reject_user', "Rejected user: {$user_data['username']} (ID: {$user_id}). Reason: {$reason_text}");
    setFlash("User <strong>" . htmlspecialchars($user_data['full_name']) . "</strong> rejected.", 'info');

    redirect('admin_dashboard.php?tab=' . $redirect_tab);
}
