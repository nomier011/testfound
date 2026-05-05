<?php
/**
 * Auto-complete sessions whose end time has passed.
 * Called silently on every page load via config.php
 */
function autoCompleteSessions() {
    try {
        $conn = getConnection();
        // Mark approved+paid sessions as completed if end time has passed
        $conn->prepare("
            UPDATE bookings
            SET status = 'completed'
            WHERE status = 'approved'
              AND payment_status = 'paid'
              AND CONCAT(booking_date, ' ', end_time) < NOW()
        ")->execute();
    } catch (Exception $e) {
        error_log("autoCompleteSessions error: " . $e->getMessage());
    }
}
