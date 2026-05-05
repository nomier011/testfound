<?php
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) die('Access denied.');
require_once 'config.php';
$conn = getConnection();

echo "<pre style='font-family:monospace;font-size:13px;padding:20px;background:#f5f5f5;'>";

// Show recent payments
echo "=== RECENT PAYMENTS ===\n";
$payments = $conn->query("SELECT p.*, b.payment_status as booking_payment_status, b.status as booking_status FROM payments p JOIN bookings b ON p.booking_id=b.id ORDER BY p.created_at DESC LIMIT 5")->fetchAll();
foreach ($payments as $p) {
    echo "Payment ID:{$p['id']} | Booking:{$p['booking_id']} | Method:{$p['payment_method']} | Status:{$p['status']} | Booking Status:{$p['booking_payment_status']} | checkout_id:{$p['payment_intent_id']}\n";
}

// Test PayMongo API with latest checkout session
if (!empty($payments[0]['payment_intent_id'])) {
    $checkout_id = $payments[0]['payment_intent_id'];
    echo "\n=== PAYMONGO CHECKOUT STATUS for: $checkout_id ===\n";

    $ch = curl_init(PAYMONGO_BASE_URL . "/checkout_sessions/" . urlencode($checkout_id));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Basic " . base64_encode(PAYMONGO_SECRET_KEY . ":")
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);
    echo "HTTP Code: $httpCode\n";
    echo "Payment Status: " . ($result['data']['attributes']['payment_status'] ?? 'N/A') . "\n";
    echo "Status: " . ($result['data']['attributes']['status'] ?? 'N/A') . "\n";
    echo "Full response:\n";
    print_r($result['data']['attributes'] ?? $result);
}

// Show SITE_URL
echo "\n=== CONFIG ===\n";
echo "SITE_URL: " . SITE_URL . "\n";
echo "PAYMONGO_SECRET_KEY starts with: " . substr(PAYMONGO_SECRET_KEY, 0, 10) . "...\n";

echo "</pre>";
?>
