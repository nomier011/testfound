<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_user = null;
if (isset($_SESSION['user_id'])) {
    $current_user = getUserById($_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo $page_title ?? 'BSIT Tutoring System'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Background Layers - Make sure these are visible -->
    <div class="bg-scc4"></div>
    <div class="bg-overlay"></div>
    <div class="bg-bsit"></div>
    <div class="bg-scc-corner"></div>