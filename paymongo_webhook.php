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

    // Find the payment record by checkout session ID
    $stmt = $conn->prepare("SELECT * FROM payments WHERE payment_intent_id = ?");
    $stmt->execute([$checkout_id]);
    $payment = $stmt->fetch();

    if ($payment) {
        try {
            $conn->beginTransaction();

            // Update payment record
            $txn_number = 'TXN-WH-' . time() . '-' . $payment['booking_id'];
            $stmt = $conn->prepare("UPDATE payments SET status = 'completed', payment_date = NOW(), transaction_number = ? WHERE id = ?");
            $stmt->execute([$txn_number, $payment['id']]);

            // Update booking — set BOTH payment_status AND status
            $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'paid', status = 'approved' WHERE id = ?");
            $stmt->execute([$payment['booking_id']]);

            // Notify the student
            $bStmt = $conn->prepare("SELECT b.*, u.full_name as tutor_name FROM bookings b JOIN users u ON b.tutor_id = u.id WHERE b.id = ?");
            $bStmt->execute([$payment['booking_id']]);
            $booking = $bStmt->fetch();
            if ($booking) {
                $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'payment')")
                     ->execute([
                         $booking['student_id'],
                         "✅ Payment confirmed for your session with {$booking['tutor_name']} on " . date('M d, Y', strtotime($booking['booking_date'])) . ". Your booking is now active."
                     ]);
                // Notify tutor
                $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'payment')")
                     ->execute([
                         $booking['tutor_id'],
                         "💰 Payment received for session on " . date('M d, Y', strtotime($booking['booking_date'])) . ". Booking #" . $booking['booking_number'] . " is now active."
                     ]);
            }

            $conn->commit();
            error_log("Webhook processed for booking: " . $payment['booking_id']);
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Webhook DB error: " . $e->getMessage());
            http_response_code(500);
            exit('DB error');
        }
    }
}

http_response_code(200);
echo 'OK';
?>