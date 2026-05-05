<?php
require_once 'config.php';
$page_title = 'Rate Session';
if (!isLoggedIn()) redirect('login.php');
$user = getUserById($_SESSION['user_id']);
if ($user['role'] != 'student') redirect('dashboard.php');

$conn = getConnection();
$booking_id = sanitizeInt($_GET['booking_id'] ?? ($_POST['booking_id'] ?? 0));

// Handle POST submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash("Invalid request.", 'danger');
        redirect("rate_session.php?booking_id=$booking_id");
    }

    $rating   = sanitizeInt($_POST['rating'] ?? 0);
    $feedback = trim($_POST['feedback'] ?? '');

    if ($rating < 1 || $rating > 5) {
        setFlash("Please select a rating.", 'danger');
        redirect("rate_session.php?booking_id=$booking_id");
    }

    // Verify booking belongs to student and is completed
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ? AND student_id = ? AND status = 'completed'");
    $stmt->execute([$booking_id, $user['id']]);
    $booking = $stmt->fetch();

    if (!$booking) {
        setFlash("Invalid booking.", 'danger');
        redirect('my_bookings.php');
    }

    // Check not already rated
    $stmt = $conn->prepare("SELECT id FROM ratings WHERE booking_id = ?");
    $stmt->execute([$booking_id]);
    if ($stmt->fetch()) {
        setFlash("You already rated this session.", 'info');
        redirect('my_bookings.php');
    }

    $stmt = $conn->prepare("INSERT INTO ratings (booking_id, student_id, tutor_id, rating, feedback) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$booking_id, $user['id'], $booking['tutor_id'], $rating, $feedback]);

    // Notify tutor
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'general')");
    $stmt->execute([$booking['tutor_id'], $user['full_name'] . " rated your session " . $rating . "/5 stars."]);

    setFlash("Thank you for your feedback!", 'success');
    redirect('my_bookings.php');
}

// GET — show form
$stmt = $conn->prepare("SELECT b.*, s.name as subject_name, u.full_name as tutor_name FROM bookings b JOIN subjects s ON b.subject_id=s.id JOIN users u ON b.tutor_id=u.id WHERE b.id=? AND b.student_id=? AND b.status='completed'");
$stmt->execute([$booking_id, $user['id']]);
$booking = $stmt->fetch();
if (!$booking) { setFlash("Invalid booking.", 'danger'); redirect('my_bookings.php'); }
?>
<?php include 'header.php'; ?>
<div style="display:flex; align-items:center; justify-content:center; min-height:80vh; padding:20px;">
<div style="background:rgba(255,255,255,0.97); border-radius:20px; padding:40px; max-width:480px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,0.15);">
    <h2 style="margin-bottom:6px; color:#1a1a2e;"><i class="fas fa-star" style="color:#f59e0b;"></i> Rate Your Session</h2>
    <p style="color:#888; margin-bottom:24px;">Tutor: <strong><?php echo htmlspecialchars($booking['tutor_name']); ?></strong> · <?php echo htmlspecialchars($booking['subject_name']); ?></p>

    <?php $flash = getFlash(); if ($flash): ?>
        <div style="padding:12px; border-radius:8px; margin-bottom:16px; background:#fff0f0; color:#c0392b;"><?php echo htmlspecialchars($flash['message']); ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
        <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
        <div style="margin-bottom:20px;">
            <label style="display:block; font-weight:600; margin-bottom:10px;">How was your session?</label>
            <div class="star-rating" style="display:flex; flex-direction:row-reverse; justify-content:flex-end; gap:6px;">
                <?php for($i=5;$i>=1;$i--): ?>
                <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>" style="display:none;">
                <label for="star<?php echo $i; ?>" style="font-size:2rem; color:#ddd; cursor:pointer; transition:color 0.2s;">★</label>
                <?php endfor; ?>
            </div>
        </div>
        <div style="margin-bottom:20px;">
            <label style="display:block; font-weight:600; margin-bottom:8px;">Feedback (Optional)</label>
            <textarea name="feedback" rows="4" placeholder="Share your experience..." style="width:100%; padding:12px; border:1.5px solid #e0e0e0; border-radius:10px; font-family:inherit; font-size:0.9rem; resize:vertical;"></textarea>
        </div>
        <div style="display:flex; gap:12px;">
            <button type="submit" style="flex:1; padding:13px; background:linear-gradient(135deg,var(--primary-red),var(--primary-blue)); color:white; border:none; border-radius:10px; font-weight:600; cursor:pointer; font-size:0.95rem;">Submit Rating</button>
            <a href="my_bookings.php" style="flex:1; padding:13px; background:#666; color:white; border-radius:10px; text-decoration:none; font-weight:600; text-align:center;">Cancel</a>
        </div>
    </form>
</div>
</div>
<style>
.star-rating label:hover, .star-rating label:hover ~ label,
.star-rating input:checked ~ label { color: #f59e0b; }
</style>
<?php include 'footer.php'; ?>
