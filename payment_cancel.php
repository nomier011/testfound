<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$booking_id = $_GET['booking_id'] ?? 0;

setFlash("Payment was cancelled. You can try again.", 'info');
redirect('payment.php?booking_id=' . $booking_id);
?>