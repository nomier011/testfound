<?php
/**
 * PayMongo Controller - Live API Integration
 * Handles all PayMongo payment operations
 */

class PayMongoController {
    private $secretKey;
    private $publicKey;
    private $baseUrl;
    private $conn;
    
    /**
     * Constructor - Initialize PayMongo with live credentials
     */
    public function __construct() {
        $this->secretKey = PAYMONGO_SECRET_KEY;
        $this->publicKey = PAYMONGO_PUBLIC_KEY;
        $this->baseUrl = PAYMONGO_BASE_URL;
        $this->conn = getConnection();
    }
    
    /**
     * Create a checkout session for payment
     */
    public function createCheckoutSession($amount, $description, $booking_id, $student_name, $student_email, $payment_method = 'gcash') {
        try {
            $amount_cents = intval($amount * 100);
            
            $payload = [
                "data" => [
                    "attributes" => [
                        "send_email_receipt" => true,
                        "show_description" => true,
                        "show_line_items" => true,
                        "payment_method_types" => [$payment_method],
                        "line_items" => [
                            [
                                "name" => "Tutoring Session",
                                "quantity" => 1,
                                "amount" => $amount_cents,
                                "currency" => "PHP",
                                "description" => substr($description, 0, 100)
                            ]
                        ],
                        "reference_number" => "BSIT-" . $booking_id . "-" . time(),
                        "description" => substr($description, 0, 100),
                        "success_url" => SITE_URL . "payment_success.php?booking_id=" . $booking_id,
                        "cancel_url" => SITE_URL . "payment_cancel.php?booking_id=" . $booking_id,
                        "metadata" => [
                            "booking_id" => $booking_id,
                            "student_name" => $student_name,
                            "student_email" => $student_email
                        ],
                        "billing" => [
                            "name" => $student_name,
                            "email" => $student_email
                        ]
                    ]
                ]
            ];
            
            $response = $this->makeRequest("POST", "/checkout_sessions", $payload);
            
            if ($response && isset($response['data'])) {
                // Save checkout session ID to database
                $stmt = $this->conn->prepare("UPDATE payments SET payment_intent_id = ? WHERE booking_id = ?");
                $stmt->execute([$response['data']['id'], $booking_id]);
                
                if ($stmt->rowCount() == 0) {
                    $stmt = $this->conn->prepare("INSERT INTO payments (booking_id, student_id, amount, payment_intent_id, status) VALUES (?, ?, ?, ?, 'pending')");
                    $stmt->execute([$booking_id, $_SESSION['user_id'], $amount, $response['data']['id']]);
                }
                
                return [
                    'success' => true,
                    'checkout_url' => $response['data']['attributes']['checkout_url'],
                    'checkout_id' => $response['data']['id']
                ];
            }
            
            return ['success' => false, 'error' => 'Failed to create checkout session'];
            
        } catch (Exception $e) {
            error_log("PayMongo Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get checkout session status
     */
    public function getCheckoutSessionStatus($checkout_id) {
        try {
            $response = $this->makeRequest("GET", "/checkout_sessions/" . $checkout_id);
            
            if ($response && isset($response['data'])) {
                return [
                    'success' => true,
                    'status' => $response['data']['attributes']['payment_status'],
                    'paid_at' => $response['data']['attributes']['paid_at'] ?? null
                ];
            }
            
            return ['success' => false, 'error' => 'Failed to get checkout session'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Update payment status in database
     */
    public function updatePaymentStatus($booking_id, $status, $payment_method = null) {
        try {
            if ($status == 'paid') {
                $stmt = $this->conn->prepare("UPDATE bookings SET payment_status = 'paid', status = 'approved' WHERE id = ?");
                $stmt->execute([$booking_id]);
                
                $stmt = $this->conn->prepare("UPDATE payments SET status = 'completed', payment_date = NOW(), payment_method = ? WHERE booking_id = ?");
                $stmt->execute([$payment_method, $booking_id]);
                
                // Generate transaction ID
                $transaction_id = 'TXN' . time() . $booking_id;
                $stmt = $this->conn->prepare("UPDATE payments SET transaction_id = ? WHERE booking_id = ?");
                $stmt->execute([$transaction_id, $booking_id]);
                
                return ['success' => true];
            }
            
            return ['success' => false];
            
        } catch (Exception $e) {
            error_log("Database update error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Make HTTP request to PayMongo API
     */
    private function makeRequest($method, $endpoint, $payload = null) {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Basic " . base64_encode($this->secretKey . ":")
        ]);
        
        if ($method == "POST") {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($payload) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            error_log("CURL Error: " . curl_error($ch));
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        if ($httpCode == 200 || $httpCode == 201) {
            return json_decode($response, true);
        }
        
        error_log("PayMongo API Error: " . $response);
        return json_decode($response, true);
    }
}
?>