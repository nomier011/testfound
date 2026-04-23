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
$description = $data['description'] ?? 'Tutoring Session Payment';

// For QRPh, we create a payment source
$url = PAYMONGO_BASE_URL . "/payment_intents/" . $payment_intent_id . "/sources";

$payload = [
    "data" => [
        "attributes" => [
            "type" => "qrph",
            "amount" => intval($amount * 100),
            "currency" => "PHP",
            "redirect" => [
                "success" => SITE_URL . "payment_success.php",
                "failed" => SITE_URL . "payment_failed.php"
            ],
            "billing" => [
                "name" => $_SESSION['username'],
                "email" => getUserById($_SESSION['user_id'])['email']
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
    
    // Check if QR code is available in the response
    $qr_code = $result['data']['attributes']['qr_code']['url'] ?? null;
    
    if ($qr_code) {
        echo json_encode(['success' => true, 'qr_code' => $qr_code]);
    } else {
        echo json_encode(['success' => false, 'error' => 'QR code not generated']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to generate QR code']);
}
?>