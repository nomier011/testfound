<?php
require_once 'config.php';

// This file should be accessible via webhook from PayMongo
// URL: https://yourdomain.com/paymongo_webhook.php

// Get the webhook payload
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (!$data) {
    http_response_code(400);
    exit('Invalid payload');
}

// Verify webhook signature (optional but recommended)
// You can add signature verification here

$event_type = $data['data']['attributes']['type'] ?? '';
$checkout_id = $data['data']['attributes']['data']['id'] ?? '';

if ($event_type == 'checkout_session.payment_paid') {
    $conn = getConnection();
    
    // Find the payment record
    $stmt = $conn->prepare("SELECT * FROM payments WHERE payment_intent_id = ?");
    $stmt->execute([$checkout_id]);
    $payment = $stmt->fetch();
    
    if ($payment) {
        // Update payment status
        $stmt = $conn->prepare("UPDATE payments SET status = 'completed', payment_date = NOW() WHERE id = ?");
        $stmt->execute([$payment['id']]);
        
        // Update booking
        $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = ?");
        $stmt->execute([$payment['booking_id']]);
        
        // Generate transaction ID
        $transaction_id = 'TXN' . time() . $payment['booking_id'];
        $stmt = $conn->prepare("UPDATE payments SET transaction_id = ? WHERE id = ?");
        $stmt->execute([$transaction_id, $payment['id']]);
        
        // Log webhook receipt
        error_log("Webhook processed for booking: " . $payment['booking_id']);
    }
}

http_response_code(200);
echo 'OK';
?>