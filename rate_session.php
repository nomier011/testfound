<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $session_id = $_POST['session_id'] ?? 0;
    $rating = $_POST['rating'] ?? 0;
    $feedback = $_POST['feedback'] ?? '';
    
    $conn = getConnection();
    
    // Check if already rated
    $stmt = $conn->prepare("SELECT id FROM session_history WHERE session_id = ?");
    $stmt->execute([$session_id]);
    
    if ($stmt->fetch()) {
        $stmt = $conn->prepare("UPDATE session_history SET rating = ?, feedback = ? WHERE session_id = ?");
        $stmt->execute([$rating, $feedback, $session_id]);
    } else {
        $stmt = $conn->prepare("INSERT INTO session_history (session_id, student_id, tutor_id, subject_id, rating, feedback) 
                                SELECT session_id, student_id, tutor_id, subject_id, ?, ? FROM sessions WHERE id = ?");
        $stmt->execute([$rating, $feedback, $session_id]);
    }
    
    setFlash("Thank you for your feedback!");
}

redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
?>