<?php
require_once 'config.php';

$conn = getConnection();

// Check payments table columns
$stmt = $conn->query("DESCRIBE payments");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

$missing_columns = [];

if (!in_array('payment_date', $columns)) {
    $conn->exec("ALTER TABLE payments ADD COLUMN payment_date TIMESTAMP NULL");
    echo "Added payment_date column<br>";
}

if (!in_array('payment_method', $columns)) {
    $conn->exec("ALTER TABLE payments ADD COLUMN payment_method VARCHAR(50) NULL");
    echo "Added payment_method column<br>";
}

echo "Database fix completed!";
?>