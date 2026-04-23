<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$payment_intent_id = $data['payment_intent_id'] ?? '';
$payment_method_id = $data['payment_method_id'] ?? '';
$booking_id = $data['booking_id'] ?? 0;
$amount = $data['amount'] ?? 0;
$payment_method = $data['payment_method'] ?? '';

if (!$payment_intent_id || !$payment_method_id) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit();
}

// Attach payment method to intent
$url = PAYMONGO_BASE_URL . "/payment_intents/" . $payment_intent_id . "/attach";

$payload = [
    "data" => [
        "attributes" => [
            "payment_method" => $payment_method_id
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Basic " . base64_encode(PAYMONGO_SECRET_KEY . ":")
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode == 200 || $httpCode == 201) {
    // Payment successful
    $conn = getConnection();
    
    // Update payment record
    $stmt = $conn->prepare("UPDATE payments SET status = 'completed', payment_date = NOW(), payment_method = ? WHERE booking_id = ?");
    $stmt->execute([$payment_method, $booking_id]);
    
    // Update booking
    $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = ?");
    $stmt->execute([$booking_id]);
    
    // Generate transaction ID
    $transaction_id = 'TXN' . time() . $booking_id;
    $stmt = $conn->prepare("UPDATE payments SET transaction_id = ? WHERE booking_id = ?");
    $stmt->execute([$transaction_id, $booking_id]);
    
    echo json_encode(['success' => true]);
} else {
    $error_msg = $result['errors'][0]['detail'] ?? 'Payment failed';
    echo json_encode(['success' => false, 'error' => $error_msg]);
}
?>