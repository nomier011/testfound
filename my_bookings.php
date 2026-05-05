<?php
require_once 'config.php';
$page_title = 'My Sessions';
if (!isLoggedIn()) redirect('login.php');
$user = getUserById($_SESSION['user_id']);
if (!in_array($user['role'], ['student','tutor'])) redirect('dashboard.php');
$conn = getConnection();

if ($user['role'] === 'tutor') {
    // Tutor sees bookings where they are the tutor
    $stmt = $conn->prepare("
        SELECT b.*, s.name as subject_name, u.full_name as student_name, u.profile_pic as student_pic
        FROM bookings b
        JOIN subjects s ON b.subject_id = s.id
        JOIN users u ON b.student_id = u.id
        WHERE b.tutor_id = ?
        ORDER BY b.booking_date DESC, b.start_time DESC
    ");
    $stmt->execute([$user['id']]);
} else {
    // Student sees their own bookings
    $stmt = $conn->prepare("
        SELECT b.*, s.name as subject_name, u.full_name as tutor_name, u.profile_pic as tutor_pic
        FROM bookings b
        JOIN subjects s ON b.subject_id = s.id
        JOIN users u ON b.tutor_id = u.id
        WHERE b.student_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$user['id']]);
}
$bookings = $stmt->fetchAll();
?>
<?php include 'header.php'; ?>
<div class="page-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="page-hero">
        <div class="page-hero-content">
            <h1>My Bookings</h1>
            <p>Manage your tutoring session bookings</p>
        </div>
    </div>

    <div class="page-inner">
    <?php $flash = getFlash(); if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>" style="margin-bottom:16px; padding:12px 16px; border-radius:10px; background:<?php echo $flash['type']=='success'?'#f0fff4':'#fff0f0'; ?>; color:<?php echo $flash['type']=='success'?'#1e7e34':'#c0392b'; ?>; border:1px solid <?php echo $flash['type']=='success'?'#c3e6cb':'#f5c6cb'; ?>;">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="filter-section" style="margin-bottom:20px; display:flex; gap:8px; flex-wrap:wrap;">
        <?php foreach(['all','pending','approved','completed','cancelled','rejected'] as $s): ?>
            <button class="tab-btn <?php echo $s=='all'?'active':''; ?>" onclick="filterBookings('<?php echo $s; ?>',this)"><?php echo ucfirst($s); ?></button>
        <?php endforeach; ?>
    </div>

    <?php if ($bookings): ?>
        <?php foreach ($bookings as $b): ?>
        <div class="content-card booking-card" data-status="<?php echo $b['status']; ?>" style="margin-bottom:16px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding-bottom:10px; border-bottom:1px solid #eee;">
                <div>
                    <span class="status-badge status-<?php echo $b['status']; ?>"><?php echo ucfirst($b['status']); ?></span>
                    <span class="status-badge status-<?php echo $b['payment_status']; ?>" style="margin-left:8px;"><?php echo ucfirst($b['payment_status']); ?> Payment</span>
                </div>
                <div style="color:#888; font-size:0.85rem;"><i class="fas fa-calendar"></i> <?php echo date('F d, Y', strtotime($b['booking_date'])); ?></div>
            </div>
            <div style="display:flex; justify-content:space-between; gap:20px; flex-wrap:wrap;">
                <div style="flex:1; min-width:200px;">
                    <?php if ($user['role'] === 'tutor'): ?>
                    <p><strong>Student:</strong> <?php echo htmlspecialchars($b['student_name'] ?? ''); ?></p>
                    <?php else: ?>
                    <p><strong>Tutor:</strong> <?php echo htmlspecialchars($b['tutor_name'] ?? ''); ?></p>
                    <?php endif; ?>
                    <p><strong>Subject:</strong> <?php echo htmlspecialchars($b['subject_name']); ?></p>
                    <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($b['start_time'])); ?> – <?php echo date('h:i A', strtotime($b['end_time'])); ?></p>
                    <p><strong>Duration:</strong> <?php echo $b['duration']; ?> hr(s)</p>
                    <p><strong>Amount:</strong> <span style="color:var(--primary-red); font-weight:700;">₱<?php echo number_format($b['amount'],2); ?></span></p>
                    <?php if ($b['notes']): ?><p><strong>Notes:</strong> <em><?php echo htmlspecialchars($b['notes']); ?></em></p><?php endif; ?>
                </div>
                <div style="display:flex; flex-direction:column; gap:8px; min-width:140px;">
                    <?php if ($user['role'] === 'tutor'): ?>
                        <?php if ($b['status'] === 'pending'): ?>
                            <form method="POST" action="update_booking_status.php">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                                <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                <input type="hidden" name="status" value="approved">
                                <button type="submit" style="width:100%;padding:8px;background:#10b981;color:#fff;border:none;border-radius:7px;font-weight:700;font-size:.8rem;cursor:pointer;">✓ Approve</button>
                            </form>
                            <form method="POST" action="update_booking_status.php">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                                <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                <input type="hidden" name="status" value="rejected">
                                <button type="submit" style="width:100%;padding:8px;background:#ef4444;color:#fff;border:none;border-radius:7px;font-weight:700;font-size:.8rem;cursor:pointer;">✗ Decline</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($b['status'] === 'approved' && $b['payment_status'] === 'paid'): ?>
                            <a href="set_meet_link.php?booking_id=<?php echo $b['id']; ?>"
                               style="display:flex;align-items:center;justify-content:center;gap:6px;padding:9px;background:#dc2626;color:#fff;border-radius:7px;text-decoration:none;font-weight:700;font-size:.8rem;">
                                <i class="fas fa-video"></i> <?php echo $b['meet_link'] ? 'Update Link' : 'Share Meet Link'; ?>
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($b['status'] == 'pending'): ?>
                            <form method="POST" action="cancel_booking.php">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                                <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                <button type="submit" class="action-btn reject" style="width:100%;" onclick="return confirm('Cancel this booking?')"><i class="fas fa-times"></i> Cancel</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($b['status'] == 'approved' && $b['payment_status'] == 'pending'): ?>
                            <a href="payment.php?booking_id=<?php echo $b['id']; ?>" class="action-btn approve" style="text-align:center;display:block;margin-bottom:6px;"><i class="fas fa-credit-card"></i> Pay Now</a>
                            <?php
                            // Show "Check Payment" if there's a pending payment record (user may have paid but not returned)
                            $pChk = $conn->prepare("SELECT id FROM payments WHERE booking_id = ? AND status = 'pending' AND payment_intent_id IS NOT NULL");
                            $pChk->execute([$b['id']]);
                            if ($pChk->fetch()):
                            ?>
                            <a href="check_payment.php?booking_id=<?php echo $b['id']; ?>" style="display:block;text-align:center;padding:7px;background:#f59e0b;color:#fff;border-radius:7px;text-decoration:none;font-weight:600;font-size:.78rem;"><i class="fas fa-sync-alt"></i> Check Payment</a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($b['status'] == 'approved' && $b['payment_status'] == 'paid'): ?>
                            <?php if ($b['meet_link']): ?>
                            <a href="<?php echo htmlspecialchars($b['meet_link']); ?>" target="_blank"
                               style="display:flex;align-items:center;justify-content:center;gap:6px;padding:9px;background:#dc2626;color:#fff;border-radius:7px;text-decoration:none;font-weight:700;font-size:.8rem;margin-bottom:6px;">
                                <i class="fas fa-video"></i> Join Meeting
                            </a>
                            <?php else: ?>
                            <span style="font-size:.72rem;color:#9ca3af;text-align:center;padding:6px 0;display:block;"><i class="fas fa-clock"></i> Waiting for link</span>
                            <?php endif; ?>
                            <a href="download_receipt.php?booking_id=<?php echo $b['id']; ?>"
                               style="display:flex;align-items:center;justify-content:center;gap:6px;padding:7px;background:#059669;color:#fff;border-radius:7px;text-decoration:none;font-weight:600;font-size:.78rem;margin-top:4px;">
                                <i class="fas fa-download"></i> Receipt
                            </a>
                        <?php endif; ?>
                        <?php if ($b['status'] == 'completed'): ?>
                            <?php $rStmt = $conn->prepare("SELECT id FROM ratings WHERE booking_id = ?"); $rStmt->execute([$b['id']]); ?>
                            <?php if (!$rStmt->fetch()): ?>
                                <a href="rate_session.php?booking_id=<?php echo $b['id']; ?>" class="action-btn view" style="text-align:center;"><i class="fas fa-star"></i> Rate</a>
                            <?php else: ?>
                                <span style="color:#f59e0b;font-size:.8rem;text-align:center;">✓ Rated</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="content-card"><div class="no-data">No bookings yet. <a href="book_session.php" style="color:var(--primary-red);">Book your first session!</a></div></div>
    <?php endif; ?>
    </div>
</div>
<style>
.tab-btn { padding:8px 16px; border:1.5px solid #ddd; border-radius:20px; background:#fff; cursor:pointer; font-size:0.85rem; transition:all 0.2s; }
.tab-btn.active, .tab-btn:hover { background:var(--primary-red); color:#fff; border-color:var(--primary-red); }
</style>
<script>
function filterBookings(status, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.booking-card').forEach(c => {
        c.style.display = (status === 'all' || c.dataset.status === status) ? '' : 'none';
    });
}
</script>
<?php include 'footer.php'; ?>
