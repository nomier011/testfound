<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$conn = getConnection();
$new_status = $_POST['is_available'] ?? 0;

$stmt = $conn->prepare("UPDATE users SET is_available = ? WHERE id = ?");
$stmt->execute([$new_status, $_SESSION['user_id']]);

setFlash("Availability updated!");
redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
?>