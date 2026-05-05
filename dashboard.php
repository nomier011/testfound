<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);

if (!$user) {
    // User not found in DB — clear session and go to login
    session_destroy();
    redirect('login.php');
}

$role = $user['role'] ?? $_SESSION['role'] ?? '';

if ($role == 'admin') {
    redirect('admin_dashboard.php');
} elseif ($role == 'student') {
    redirect('student_dashboard.php');
} elseif ($role == 'tutor') {
    redirect('tutor_dashboard.php');
} else {
    session_destroy();
    redirect('login.php');
}
