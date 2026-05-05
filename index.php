<?php
require_once 'config.php';

if (isLoggedIn()) {
    $user = getUserById($_SESSION['user_id']);
    if ($user['role'] == 'student') {
        redirect('student_dashboard.php');
    } elseif ($user['role'] == 'tutor') {
        redirect('tutor_dashboard.php');
    } elseif ($user['role'] == 'admin') {
        redirect('admin_dashboard.php');
    }
} else {
    redirect('login.php');
}
