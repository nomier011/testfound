<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $session_id = $_POST['session_id'] ?? 0;
    $status = $_POST['status'] ?? '';
    
    $conn = getConnection();
    $stmt = $conn->prepare("UPDATE sessions SET status = ? WHERE id = ?");
    $stmt->execute([$status, $session_id]);
    
    setFlash("Session " . ucfirst($status) . " successfully!");
}

redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
?>