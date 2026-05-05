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
    $action       = $_POST['action'] ?? '';
    $redirect_tab = in_array($_POST['redirect_tab'] ?? '', ['pending', 'users']) ? $_POST['redirect_tab'] : 'pending';

    $conn      = getConnection();
    $user_data = getUserById($user_id);

    if (!$user_data) {
        setFlash("User not found.", 'danger');
        redirect('admin_dashboard.php?tab=' . $redirect_tab);
    }

    if ($action == 'approve') {
        $conn->prepare("UPDATE users SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?")
             ->execute([$admin['id'], $user_id]);

        sendNotification($user_id, "🎉 Your account has been approved! You can now log in and start using the system.", 'account');
        logActivity($admin['id'], 'approve_user', "Approved user: {$user_data['username']} (ID: {$user_id})");
        setFlash("User <strong>" . htmlspecialchars($user_data['full_name']) . "</strong> approved successfully!", 'success');
    }

    redirect('admin_dashboard.php?tab=' . $redirect_tab);
}
