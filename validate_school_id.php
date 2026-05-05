<?php
require_once 'config.php';
header('Content-Type: application/json');

$school_id = trim($_POST['school_id'] ?? '');

if (empty($school_id)) {
    echo json_encode(['valid' => false, 'message' => 'Please enter your School ID.']);
    exit;
}

$conn = getConnection();
$stmt = $conn->prepare("SELECT * FROM school_records WHERE school_id = ? AND is_registered = 0");
$stmt->execute([$school_id]);
$record = $stmt->fetch();

if (!$record) {
    echo json_encode(['valid' => false, 'message' => 'Invalid School ID or already registered.']);
    exit;
}

echo json_encode([
    'valid' => true,
    'name' => $record['full_name'],
    'role' => $record['role'],
    'year_level' => $record['year_level'],
    'message' => 'School ID verified!'
]);