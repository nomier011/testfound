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
        redirect('admin_dashboard.php');
    }

    $user_id = sanitizeInt($_POST['user_id'] ?? 0);

    // Prevent admin from deleting themselves
    if ($user_id === (int)$admin['id']) {
        setFlash("You cannot delete your own account.", 'danger');
        redirect('admin_dashboard.php');
    }

    $conn = getConnection();

    try {
        $conn->beginTransaction();

        // Delete related records (cascade should handle it, but be explicit)
        $conn->prepare("DELETE FROM bookings WHERE student_id = ? OR tutor_id = ?")->execute([$user_id, $user_id]);
        $conn->prepare("DELETE FROM payments WHERE student_id = ?")->execute([$user_id]);
        $conn->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$user_id]);

        // Remove profile picture if present
        $stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();
        if ($row && $row['profile_pic']) {
            $pic_path = UPLOAD_PATH . basename($row['profile_pic']);
            if (file_exists($pic_path)) {
                unlink($pic_path);
            }
        }

        // Only delete non-admin accounts
        $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'")->execute([$user_id]);

        $conn->commit();
        setFlash("User deleted successfully!", 'success');
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Delete user error: " . $e->getMessage());
        setFlash("Failed to delete user. Please try again.", 'danger');
    }

    redirect('admin_dashboard.php');
}
?>