<?php
require_once 'config.php';

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user = getUserById($_SESSION['user_id']);
if ($user['role'] != 'admin') {
    echo json_encode(['error' => 'Access denied']);
    exit();
}

$user_id = $_GET['id'] ?? 0;
$conn = getConnection();

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

if (!$user_data) {
    echo json_encode(['error' => 'User not found']);
    exit();
}

// Get user statistics
$stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM bookings WHERE student_id = ?");
$stmt->execute([$user_id]);
$student_stats = $stmt->fetch();

$stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM bookings WHERE tutor_id = ? AND payment_status = 'paid'");
$stmt->execute([$user_id]);
$tutor_stats = $stmt->fetch();

$result = [
    'id' => $user_data['id'],
    'full_name' => $user_data['full_name'],
    'username' => $user_data['username'],
    'email' => $user_data['email'],
    'role' => $user_data['role'],
    'phone' => $user_data['phone'],
    'year_level' => $user_data['year_level'],
    'profile_pic' => $user_data['profile_pic'],
    'joined_date' => date('M d, Y', strtotime($user_data['created_at'])),
    'total_bookings' => $user_data['role'] == 'student' ? $student_stats['count'] : $tutor_stats['count'],
    'total_amount' => $user_data['role'] == 'student' ? $student_stats['total'] : $tutor_stats['total']
];

echo json_encode($result);
?>