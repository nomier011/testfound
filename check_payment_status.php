<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'unknown']);
    exit();
}

$data              = json_decode(file_get_contents('php://input'), true);
$payment_intent_id = trim($data['payment_intent_id'] ?? '');
$booking_id        = sanitizeInt($data['booking_id'] ?? 0);

if (!$payment_intent_id || !$booking_id) {
    echo json_encode(['status' => 'unknown']);
    exit();
}

// Verify the booking belongs to the logged-in student
$conn = getConnection();
$stmt = $conn->prepare("SELECT b.id FROM bookings b JOIN payments p ON p.booking_id = b.id WHERE b.id = ? AND b.student_id = ? AND p.payment_intent_id = ?");
$stmt->execute([$booking_id, $_SESSION['user_id'], $payment_intent_id]);
if (!$stmt->fetch()) {
    echo json_encode(['status' => 'unknown']);
    exit();
}

// Check payment status from PayMongo checkout session
$url = PAYMONGO_BASE_URL . "/checkout_sessions/" . urlencode($payment_intent_id);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Basic " . base64_encode(PAYMONGO_SECRET_KEY . ":")
]);

$response = curl_exec($ch);
$curl_err  = curl_errno($ch);
curl_close($ch);

if ($curl_err) {
    error_log("PayMongo cURL error: " . $curl_err);
    echo json_encode(['status' => 'unknown']);
    exit();
}

$result = json_decode($response, true);

if (isset($result['data']['attributes']['payment_status'])) {
    $pm_status = $result['data']['attributes']['payment_status'];

    if ($pm_status === 'paid') {
        try {
            $conn->beginTransaction();

            $conn->prepare("UPDATE payments SET status = 'completed', payment_date = NOW() WHERE booking_id = ?")
                 ->execute([$booking_id]);
            $conn->prepare("UPDATE bookings SET payment_status = 'paid', status = 'approved' WHERE id = ?")
                 ->execute([$booking_id]);

            $conn->commit();
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Payment status update error: " . $e->getMessage());
        }

        echo json_encode(['status' => 'paid']);
    } else {
        echo json_encode(['status' => htmlspecialchars($pm_status)]);
    }
} else {
    echo json_encode(['status' => 'unknown']);
}
?>