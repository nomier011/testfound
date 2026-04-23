<?php
require_once 'config.php';

if (!isLoggedIn()) {
    echo json_encode(['tutors' => []]);
    exit();
}

$subject_id = $_GET['subject_id'] ?? 0;
$conn = getConnection();

$stmt = $conn->prepare("
    SELECT u.id, u.full_name, u.hourly_rate, u.expertise, u.bio, u.profile_pic, u.is_available,
           (SELECT AVG(rating) FROM ratings r 
            JOIN bookings b ON r.booking_id = b.id 
            WHERE b.tutor_id = u.id) as avg_rating,
           (SELECT COUNT(*) FROM ratings r 
            JOIN bookings b ON r.booking_id = b.id 
            WHERE b.tutor_id = u.id) as total_ratings
    FROM users u
    JOIN tutor_subjects ts ON u.id = ts.tutor_id
    WHERE ts.subject_id = ? AND u.role = 'tutor' AND u.is_available = 1
    GROUP BY u.id
    ORDER BY avg_rating DESC
");
$stmt->execute([$subject_id]);
$tutors = $stmt->fetchAll();

echo json_encode(['tutors' => $tutors]);
?>