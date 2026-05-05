<?php
require_once 'config.php';

if (!isLoggedIn()) {
    echo json_encode(['subjects' => []]);
    exit();
}

$tutor_id = sanitizeInt($_GET['tutor_id'] ?? 0);
$conn = getConnection();

$stmt = $conn->prepare("
    SELECT s.id, s.name 
    FROM subjects s
    JOIN tutor_subjects ts ON s.id = ts.subject_id
    WHERE ts.tutor_id = ?
");
$stmt->execute([$tutor_id]);
$subjects = $stmt->fetchAll();

echo json_encode(['subjects' => $subjects]);
?>