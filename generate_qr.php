<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$data              = json_decode(file_get_contents('php://input'), true);
$payment_intent_id = trim($data['payment_intent_id'] ?? '');
$amount            = sanitizeFloat($data['amount'] ?? 0);
$booking_id        = sanitizeInt($data['booking_id'] ?? 0);

if (!$payment_intent_id || !$booking_id) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit();
}

// Verify booking ownership
$conn = getConnection();
$stmt = $conn->prepare("SELECT amount FROM bookings WHERE id = ? AND student_id = ?");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    echo json_encode(['success' => false, 'error' => 'Booking not found']);
    exit();
}

// Validate amount
if (abs($booking['amount'] - $amount) > 0.01) {
    echo json_encode(['success' => false, 'error' => 'Amount mismatch']);
    exit();
}

$current_user = getUserById($_SESSION['user_id']);
$url          = PAYMONGO_BASE_URL . "/payment_intents/" . urlencode($payment_intent_id) . "/sources";

$payload = [
    "data" => [
        "attributes" => [
            "type"     => "qrph",
            "amount"   => intval($amount * 100),
            "currency" => "PHP",
            "redirect" => [
                "success" => SITE_URL . "payment_success.php?booking_id=" . $booking_id,
                "failed"  => SITE_URL . "payment_failed.php?booking_id=" . $booking_id
            ],
            "billing" => [
                "name"  => $current_user['full_name'],
                "email" => $current_user['email']
            ]
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Basic " . base64_encode(PAYMONGO_SECRET_KEY . ":")
]);

$response = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_errno($ch);
curl_close($ch);

if ($curl_err) {
    error_log("PayMongo cURL error (generate_qr): " . $curl_err);
    echo json_encode(['success' => false, 'error' => 'Could not reach payment provider']);
    exit();
}

if ($httpCode == 200 || $httpCode == 201) {
    $result  = json_decode($response, true);
    $qr_code = $result['data']['attributes']['qr_code']['url'] ?? null;

    if ($qr_code) {
        echo json_encode(['success' => true, 'qr_code' => $qr_code]);
    } else {
        echo json_encode(['success' => false, 'error' => 'QR code not generated']);
    }
} else {
    error_log("PayMongo QR error: " . $response);
    echo json_encode(['success' => false, 'error' => 'Failed to generate QR code']);
}
?>