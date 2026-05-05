<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');

$user       = getUserById($_SESSION['user_id']);
$booking_id = sanitizeInt($_GET['booking_id'] ?? 0);
$conn       = getConnection();

// Only tutors can set the meet link
if ($user['role'] !== 'tutor') redirect('dashboard.php');

// Add meet_link column if it doesn't exist yet
try {
    $conn->exec("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS meet_link VARCHAR(500) DEFAULT NULL");
} catch (Exception $e) { /* already exists */ }

// Verify this booking belongs to this tutor and is approved+paid
$stmt = $conn->prepare("
    SELECT b.*, s.name as subject_name, u.full_name as student_name, u.id as student_id
    FROM bookings b
    JOIN subjects s ON b.subject_id = s.id
    JOIN users u    ON b.student_id = u.id
    WHERE b.id = ? AND b.tutor_id = ? AND b.status = 'approved'
");
$stmt->execute([$booking_id, $user['id']]);
$booking = $stmt->fetch();

if (!$booking) {
    setFlash("Booking not found or not yet paid.", 'danger');
    redirect('tutor_dashboard.php');
}

$error   = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request.";
    } else {
        $link = trim($_POST['meet_link'] ?? '');

        // Validate it's a Google Meet or similar video link
        if (empty($link)) {
            $error = "Please paste a meeting link.";
        } elseif (!filter_var($link, FILTER_VALIDATE_URL)) {
            $error = "Please enter a valid URL (must start with https://).";
        } else {
            // Save link to booking
            $conn->prepare("UPDATE bookings SET meet_link = ? WHERE id = ?")
                 ->execute([$link, $booking_id]);

            // Notify student with the link
            $date_str = date('M d, Y', strtotime($booking['booking_date']));
            $time_str = date('h:i A', strtotime($booking['start_time']));
            $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'session_link')")
                 ->execute([
                     $booking['student_id'],
                     "🎥 Your tutor <strong>{$user['full_name']}</strong> has shared the meeting link for your <strong>{$booking['subject_name']}</strong> session on $date_str at $time_str.<br><a href='$link' target='_blank' style='color:#dc2626;font-weight:700;'>👉 Click here to join the session</a>"
                 ]);

            $success = $link;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Set Meeting Link</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/style.css">
<style>
body { background: #f3f4f6; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
.card { background: #fff; border-radius: 16px; padding: 36px; max-width: 520px; width: 100%; box-shadow: 0 10px 40px rgba(0,0,0,.1); }
.card-icon { width: 64px; height: 64px; background: #fef2f2; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 20px; }
h1 { font-size: 1.3rem; font-weight: 800; color: #111; margin-bottom: 6px; }
.sub { font-size: .85rem; color: #6b7280; margin-bottom: 24px; }
.session-info { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; margin-bottom: 24px; }
.session-info p { font-size: .85rem; color: #374151; margin-bottom: 6px; display: flex; gap: 8px; }
.session-info p:last-child { margin-bottom: 0; }
.session-info i { color: #dc2626; width: 16px; flex-shrink: 0; margin-top: 2px; }
label { display: block; font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: 6px; }
input[type=url] { width: 100%; padding: 12px 14px; border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: .9rem; font-family: inherit; transition: all .18s; }
input[type=url]:focus { outline: none; border-color: #dc2626; box-shadow: 0 0 0 3px rgba(220,38,38,.08); }
.hint { font-size: .72rem; color: #9ca3af; margin-top: 5px; }
.btn { width: 100%; padding: 13px; background: #111; color: #fff; border: 2px solid #111; border-radius: 8px; font-size: .9rem; font-weight: 700; cursor: pointer; transition: all .2s; margin-top: 16px; display: flex; align-items: center; justify-content: center; gap: 8px; }
.btn:hover { background: transparent; color: #111; }
.alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: .85rem; display: flex; align-items: flex-start; gap: 8px; }
.alert-error   { background: #fef2f2; color: #dc2626; border-left: 3px solid #dc2626; }
.alert-success { background: #ecfdf5; color: #059669; border-left: 3px solid #10b981; }
.back { display: inline-flex; align-items: center; gap: 6px; font-size: .82rem; color: #6b7280; text-decoration: none; margin-bottom: 20px; }
.back:hover { color: #111; }
</style>
</head>
<body>
<div class="card">
    <a href="tutor_dashboard.php" class="back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

    <div class="card-icon"><i class="fas fa-video" style="color:#dc2626;"></i></div>
    <h1>Share Meeting Link</h1>
    <p class="sub">Paste your Google Meet link below. Your student will be notified instantly.</p>

    <!-- Session info -->
    <div class="session-info">
        <p><i class="fas fa-user"></i> <span><strong>Student:</strong> <?php echo htmlspecialchars($booking['student_name']); ?></span></p>
        <p><i class="fas fa-book"></i> <span><strong>Subject:</strong> <?php echo htmlspecialchars($booking['subject_name']); ?></span></p>
        <p><i class="fas fa-calendar"></i> <span><strong>Date:</strong> <?php echo date('F d, Y', strtotime($booking['booking_date'])); ?></span></p>
        <p><i class="fas fa-clock"></i> <span><strong>Time:</strong> <?php echo date('h:i A', strtotime($booking['start_time'])); ?> – <?php echo date('h:i A', strtotime($booking['end_time'])); ?></span></p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle" style="flex-shrink:0;margin-top:2px;"></i>
            <div>
                <strong>Link sent!</strong> Your student has been notified.<br>
                <a href="<?php echo htmlspecialchars($success); ?>" target="_blank" style="color:#059669;font-weight:600;">Open your meeting →</a>
            </div>
        </div>
        <a href="tutor_dashboard.php" class="btn" style="text-decoration:none;justify-content:center;">
            <i class="fas fa-tachometer-alt"></i> Back to Dashboard
        </a>
    <?php else: ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
            <label for="meet_link">Google Meet / Zoom / Teams Link</label>
            <input type="url" id="meet_link" name="meet_link"
                   placeholder="https://meet.google.com/xxx-xxxx-xxx"
                   value="<?php echo htmlspecialchars($_POST['meet_link'] ?? $booking['meet_link'] ?? ''); ?>"
                   required>
            <p class="hint"><i class="fas fa-info-circle"></i> Create a meeting at <a href="https://meet.google.com" target="_blank" style="color:#dc2626;">meet.google.com</a> and paste the link here.</p>
            <button type="submit" class="btn"><i class="fas fa-paper-plane"></i> Send Link to Student</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
