<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$payment_intent_id = $data['payment_intent_id'] ?? '';
$amount = $data['amount'] ?? 0;
$payment_method = $data['payment_method'] ?? '';
$booking_id = $data['booking_id'] ?? 0;
$student_name = $data['student_name'] ?? '';
$student_email = $data['student_email'] ?? '';

if (!$payment_intent_id || !$payment_method) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit();
}

// Map payment method to PayMongo source type
$source_type = '';
switch ($payment_method) {
    case 'gcash':
        $source_type = 'gcash';
        break;
    case 'paymaya':
        $source_type = 'paymaya';
        break;
    case 'grab_pay':
        $source_type = 'grab_pay';
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid payment method']);
        exit();
}

// Create a payment source
$url = PAYMONGO_BASE_URL . "/payment_intents/" . $payment_intent_id . "/sources";

$amount_cents = intval($amount * 100);

$payload = [
    "data" => [
        "attributes" => [
            "type" => $source_type,
            "amount" => $amount_cents,
            "currency" => "PHP",
            "redirect" => [
                "success" => SITE_URL . "payment_success.php?booking_id=" . $booking_id,
                "failed" => SITE_URL . "payment_failed.php?booking_id=" . $booking_id
            ],
            "billing" => [
                "name" => $student_name,
                "email" => $student_email
            ]
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

if ($httpCode == 200 || $httpCode == 201) {
    $result = json_decode($response, true);
    
    // Get the redirect URL
    $redirect_url = $result['data']['attributes']['redirect']['checkout_url'] ?? null;
    
    if ($redirect_url) {
        // Save the source ID to database
        $conn = getConnection();
        $source_id = $result['data']['id'];
        $stmt = $conn->prepare("UPDATE payments SET transaction_id = ? WHERE booking_id = ?");
        $stmt->execute([$source_id, $booking_id]);
        
        echo json_encode(['success' => true, 'redirect_url' => $redirect_url]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No redirect URL received']);
    }
} else {
    $error_msg = 'Failed to create payment source';
    $result = json_decode($response, true);
    if (isset($result['errors'][0]['detail'])) {
        $error_msg = $result['errors'][0]['detail'];
    }
    echo json_encode(['success' => false, 'error' => $error_msg]);
}
?>