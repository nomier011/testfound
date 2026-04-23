<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'unknown']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$payment_intent_id = $data['payment_intent_id'] ?? '';
$booking_id = $data['booking_id'] ?? 0;

// Check payment status from PayMongo
$url = PAYMONGO_BASE_URL . "/payment_intents/" . $payment_intent_id;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Basic " . base64_encode(PAYMONGO_SECRET_KEY . ":")
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (isset($result['data']['attributes']['status'])) {
    $status = $result['data']['attributes']['status'];
    
    if ($status == 'succeeded') {
        // Update database
        $conn = getConnection();
        $stmt = $conn->prepare("UPDATE payments SET status = 'completed', payment_date = NOW() WHERE booking_id = ?");
        $stmt->execute([$booking_id]);
        
        $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = ?");
        $stmt->execute([$booking_id]);
        
        echo json_encode(['status' => 'paid']);
    } else {
        echo json_encode(['status' => $status]);
    }
} else {
    echo json_encode(['status' => 'unknown']);
}
?>