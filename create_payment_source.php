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
$payment_method    = trim($data['payment_method'] ?? '');
$booking_id        = sanitizeInt($data['booking_id'] ?? 0);
$student_name      = trim($data['student_name'] ?? '');
$student_email     = trim($data['student_email'] ?? '');

if (!$payment_intent_id || !$payment_method || !$booking_id) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit();
}

// Validate payment method against server-side whitelist
$allowed_source_types = ['gcash', 'paymaya', 'grab_pay'];
if (!in_array($payment_method, $allowed_source_types, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid payment method']);
    exit();
}

// Verify booking ownership and amount matches
$conn = getConnection();
$stmt = $conn->prepare("SELECT amount FROM bookings WHERE id = ? AND student_id = ?");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    echo json_encode(['success' => false, 'error' => 'Booking not found']);
    exit();
}

// Validate amount matches booking record (prevent tampering)
if (abs($booking['amount'] - $amount) > 0.01) {
    echo json_encode(['success' => false, 'error' => 'Amount mismatch']);
    exit();
}

// Create a payment source via PayMongo
$url          = PAYMONGO_BASE_URL . "/payment_intents/" . urlencode($payment_intent_id) . "/sources";
$amount_cents = intval($amount * 100);

$payload = [
    "data" => [
        "attributes" => [
            "type"     => $payment_method,
            "amount"   => $amount_cents,
            "currency" => "PHP",
            "redirect" => [
                "success" => SITE_URL . "payment_success.php?booking_id=" . $booking_id,
                "failed"  => SITE_URL . "payment_failed.php?booking_id=" . $booking_id
            ],
            "billing" => [
                "name"  => substr($student_name, 0, 255),
                "email" => filter_var($student_email, FILTER_SANITIZE_EMAIL)
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
    error_log("PayMongo cURL error (create_payment_source): " . $curl_err);
    echo json_encode(['success' => false, 'error' => 'Could not reach payment provider']);
    exit();
}

if ($httpCode == 200 || $httpCode == 201) {
    $result      = json_decode($response, true);
    $redirect_url = $result['data']['attributes']['redirect']['checkout_url'] ?? null;

    if ($redirect_url) {
        $source_id = $result['data']['id'];
        $stmt = $conn->prepare("UPDATE payments SET transaction_id = ? WHERE booking_id = ?");
        $stmt->execute([$source_id, $booking_id]);

        echo json_encode(['success' => true, 'redirect_url' => $redirect_url]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No redirect URL received']);
    }
} else {
    $error_msg = 'Failed to create payment source';
    $result    = json_decode($response, true);
    if (isset($result['errors'][0]['detail'])) {
        $error_msg = $result['errors'][0]['detail'];
    }
    error_log("PayMongo error response: " . $response);
    echo json_encode(['success' => false, 'error' => $error_msg]);
}
?>