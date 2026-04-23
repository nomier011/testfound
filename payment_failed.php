<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$booking_id = $_GET['booking_id'] ?? 0;

setFlash("Payment failed. Please try again or use another payment method.", 'danger');
redirect('payment.php?booking_id=' . $booking_id);
?>