<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$booking_id = sanitizeInt($_GET['booking_id'] ?? 0);
$conn = getConnection();

// Get booking details
$stmt = $conn->prepare("
    SELECT b.*, sub.name as subject_name, u.full_name as tutor_name,
           p.payment_method, p.payment_date
    FROM bookings b
    JOIN subjects sub ON b.subject_id = sub.id
    JOIN users u ON b.tutor_id = u.id
    LEFT JOIN payments p ON b.id = p.booking_id
    WHERE b.id = ? AND b.student_id = ?
");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking || $booking['payment_status'] != 'paid') {
    redirect('my_bookings.php');
}

$user = getUserById($_SESSION['user_id']);

// Map payment method to display name
$payment_method_display = [
    'gcash' => 'GCash',
    'paymaya' => 'PayMaya',
    'grab_pay' => 'GrabPay',
    'card' => 'Credit/Debit Card'
];

$payment_method_name = $payment_method_display[$booking['payment_method']] ?? ucfirst($booking['payment_method']);

// Create HTML receipt
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .receipt {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #8B0000;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #8B0000;
            margin: 0;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .receipt-title {
            text-align: center;
            margin: 20px 0;
        }
        .receipt-title h2 {
            color: #333;
        }
        .details {
            margin: 20px 0;
        }
        .row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .row .label {
            font-weight: bold;
            color: #555;
        }
        .row .value {
            color: #333;
        }
        .total {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 2px solid #8B0000;
            font-size: 1.2em;
        }
        .total .label {
            font-weight: bold;
        }
        .total .value {
            color: #4CAF50;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 0.8em;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <h1>BSIT Tutoring System</h1>
            <p>St. Cecilia\'s College - Cebu, Inc.</p>
            <p>Cebu South National Highway, Ward II, Minglanilla, Cebu</p>
        </div>
        
        <div class="receipt-title">
            <h2>OFFICIAL PAYMENT RECEIPT</h2>
        </div>
        
        <div class="details">
            <div class="row">
                <span class="label">Receipt Number:</span>
                <span class="value">RCP-' . str_pad($booking['id'], 6, '0', STR_PAD_LEFT) . '</span>
            </div>
            <div class="row">
                <span class="label">Payment Date:</span>
                <span class="value">' . date('F d, Y h:i A', strtotime($booking['payment_date'] ?? $booking['created_at'])) . '</span>
            </div>
            <div class="row">
                <span class="label">Payment Method:</span>
                <span class="value">' . $payment_method_name . '</span>
            </div>
        </div>
        
        <div class="receipt-title">
            <h3>Session Details</h3>
        </div>
        
        <div class="details">
            <div class="row">
                <span class="label">Student Name:</span>
                <span class="value">' . htmlspecialchars($user['full_name']) . '</span>
            </div>
            <div class="row">
                <span class="label">Tutor Name:</span>
                <span class="value">' . htmlspecialchars($booking['tutor_name']) . '</span>
            </div>
            <div class="row">
                <span class="label">Subject:</span>
                <span class="value">' . htmlspecialchars($booking['subject_name']) . '</span>
            </div>
            <div class="row">
                <span class="label">Session Date:</span>
                <span class="value">' . date('F d, Y', strtotime($booking['booking_date'])) . '</span>
            </div>
            <div class="row">
                <span class="label">Time:</span>
                <span class="value">' . date('h:i A', strtotime($booking['start_time'])) . ' - ' . date('h:i A', strtotime($booking['end_time'])) . '</span>
            </div>
            <div class="row">
                <span class="label">Duration:</span>
                <span class="value">' . $booking['duration'] . ' hour(s)</span>
            </div>
            <div class="row total">
                <span class="label">Amount Paid:</span>
                <span class="value">₱' . number_format($booking['amount'], 2) . '</span>
            </div>
        </div>
        
        <div class="footer">
            <p>Thank you for using BSIT Tutoring System!</p>
            <p>This is a system-generated receipt. For any concerns, please contact support.</p>
        </div>
    </div>
</body>
</html>';

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="receipt_' . $booking_id . '.html"');
header('Content-Length: ' . strlen($html));
echo $html;
exit();
?>