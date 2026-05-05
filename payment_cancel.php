<?php
require_once 'config.php';
$page_title = 'Payment Cancelled';
if (!isLoggedIn()) redirect('login.php');
$booking_id = sanitizeInt($_GET['booking_id'] ?? 0);
?>
<?php include 'header.php'; ?>
<div style="display:flex; align-items:center; justify-content:center; min-height:80vh; padding:20px;">
<div style="background:rgba(255,255,255,0.97); border-radius:20px; padding:50px 40px; max-width:500px; width:100%; text-align:center; box-shadow:0 20px 60px rgba(0,0,0,0.15);">
    <div style="width:80px; height:80px; background:linear-gradient(135deg,#FF9800,#e65100); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 24px; font-size:2.5rem; color:white;">!</div>
    <h1 style="color:#e65100; margin-bottom:8px;">Payment Cancelled</h1>
    <p style="color:#666; margin-bottom:24px;">You cancelled the payment. Your booking is still pending payment.</p>
    <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
        <?php if ($booking_id): ?>
        <a href="payment.php?booking_id=<?php echo (int)$booking_id; ?>" style="display:inline-block; padding:12px 24px; background:linear-gradient(135deg,var(--primary-red),var(--primary-blue)); color:white; border-radius:10px; text-decoration:none; font-weight:600;">Pay Now</a>
        <?php endif; ?>
        <a href="my_bookings.php" style="display:inline-block; padding:12px 24px; background:#666; color:white; border-radius:10px; text-decoration:none; font-weight:600;">My Bookings</a>
    </div>
</div>
</div>
<?php include 'footer.php'; ?>
