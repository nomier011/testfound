<?php
/**
 * Session Reminder Script
 * Run via cron every minute:
 *   * * * * * php /path/to/testfound/send_reminders.php
 * Or manually: http://localhost/testfound/send_reminders.php
 */

// Allow CLI or localhost only
$is_cli = php_sapi_name() === 'cli';
if (!$is_cli && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    http_response_code(403); die('Access denied.');
}

require_once 'config.php';
$conn = getConnection();

$sent = 0;

// Find sessions starting in the next 15 minutes that haven't been reminded yet
$stmt = $conn->prepare("
    SELECT b.id, b.student_id, b.tutor_id, b.booking_date, b.start_time,
           s.name as subject_name,
           ut.full_name as tutor_name,
           us.full_name as student_name
    FROM bookings b
    JOIN subjects s  ON b.subject_id = s.id
    JOIN users ut    ON b.tutor_id   = ut.id
    JOIN users us    ON b.student_id = us.id
    WHERE b.status = 'approved'
      AND b.payment_status = 'paid'
      AND b.reminder_sent = 0
      AND CONCAT(b.booking_date, ' ', b.start_time) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 15 MINUTE)
");
$stmt->execute();
$sessions = $stmt->fetchAll();

foreach ($sessions as $session) {
    $time_str = date('h:i A', strtotime($session['start_time']));
    $date_str = date('M d, Y', strtotime($session['booking_date']));

    // Notify student
    sendNotification(
        $session['student_id'],
        "⏰ Reminder: Your tutoring session for {$session['subject_name']} with {$session['tutor_name']} starts at {$time_str} today. Check your notifications for the meeting link.",
        'session_reminder'
    );

    // Notify tutor to share Google Meet link
    sendNotification(
        $session['tutor_id'],
        "⏰ Your session with {$session['student_name']} for {$session['subject_name']} starts at {$time_str}. <a href='set_meet_link.php?booking_id={$session['id']}' style='color:#dc2626;font-weight:700;'>👉 Click here to share your Google Meet link</a>",
        'session_reminder'
    );

    // Mark reminder as sent
    $conn->prepare("UPDATE bookings SET reminder_sent = 1 WHERE id = ?")->execute([$session['id']]);
    $sent++;
}

if ($is_cli) {
    echo date('Y-m-d H:i:s') . " — Sent $sent reminder(s)\n";
} else {
    echo "<pre>✅ Sent $sent reminder(s) at " . date('H:i:s') . "</pre>";
}
?>
