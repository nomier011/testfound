<?php
require_once 'config.php';
$page_title = 'My Bookings';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
if ($user['role'] != 'student') {
    redirect('index.php');
}

$conn = getConnection();

$stmt = $conn->prepare("
    SELECT b.*, s.name as subject_name, u.full_name as tutor_name, u.profile_pic as tutor_pic
    FROM bookings b
    JOIN subjects s ON b.subject_id = s.id
    JOIN users u ON b.tutor_id = u.id
    WHERE b.student_id = ?
    ORDER BY b.created_at DESC
");
$stmt->execute([$user['id']]);
$sessions = $stmt->fetchAll();
?>

<?php include 'header.php'; ?>

<div class="page-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="page-hero">
        <div class="page-hero-content">
            <h1>My Bookings</h1>
            <p>View all your tutoring sessions</p>
        </div>
    </div>

    <div class="page-inner">
    
    <div class="filter-tabs">
        <button class="tab-btn active" onclick="filterBookings('all')">All</button>
        <button class="tab-btn" onclick="filterBookings('approved')">Approved</button>
        <button class="tab-btn" onclick="filterBookings('pending')">Pending</button>
        <button class="tab-btn" onclick="filterBookings('completed')">Completed</button>
        <button class="tab-btn" onclick="filterBookings('rejected')">Rejected</button>
    </div>
    
    <?php if ($sessions): ?>
        <?php foreach ($sessions as $session): ?>
            <div class="booking-card" data-status="<?php echo $session['status']; ?>" style="background: rgba(255,255,255,0.95); border-radius: 15px; padding: 20px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid var(--border-color);">
                    <div>
                        <span class="status-badge status-<?php echo $session['status']; ?>"><?php echo ucfirst($session['status']); ?></span>
                        <span class="status-badge status-<?php echo $session['payment_status']; ?>" style="margin-left: 10px;"><?php echo ucfirst($session['payment_status']); ?></span>
                    </div>
                    <div class="booking-date">📅 <?php echo date('F d, Y', strtotime($session['booking_date'])); ?></div>
                </div>
                
                <div style="display: flex; justify-content: space-between; gap: 20px;">
                    <div style="flex: 1;">
                        <div style="display: flex; margin-bottom: 8px;">
                            <span style="width: 100px; color: var(--gray);">Tutor:</span>
                            <span>
                                <?php if ($session['tutor_pic']): ?>
                                    <img src="uploads/<?php echo $session['tutor_pic']; ?>" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover; vertical-align: middle; margin-right: 8px;">
                                <?php endif; ?>
                                <?php echo $session['tutor_name']; ?>
                            </span>
                        </div>
                        <div style="display: flex; margin-bottom: 8px;"><span style="width: 100px; color: var(--gray);">Subject:</span><span><?php echo $session['subject_name']; ?></span></div>
                        <div style="display: flex; margin-bottom: 8px;"><span style="width: 100px; color: var(--gray);">Time:</span><span><?php echo date('h:i A', strtotime($session['start_time'])); ?> - <?php echo date('h:i A', strtotime($session['end_time'])); ?></span></div>
                        <div style="display: flex; margin-bottom: 8px;"><span style="width: 100px; color: var(--gray);">Duration:</span><span><?php echo $session['duration']; ?> hour(s)</span></div>
                        <div style="display: flex; margin-bottom: 8px;"><span style="width: 100px; color: var(--gray);">Amount:</span><span style="color: var(--success-green); font-weight: bold;">₱<?php echo number_format($session['amount'], 2); ?></span></div>
                        <?php if ($session['notes']): ?>
                            <div style="display: flex; margin-bottom: 8px;"><span style="width: 100px; color: var(--gray);">Notes:</span><span style="font-style: italic;">"<?php echo htmlspecialchars($session['notes']); ?>"</span></div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 10px; min-width: 120px;">
                        <?php if ($session['status'] == 'pending'): ?>
                            <form method="POST" action="cancel_booking.php">
                                <input type="hidden" name="booking_id" value="<?php echo $session['id']; ?>">
                                <button type="submit" class="action-btn reject" style="width: 100%;" onclick="return confirm('Cancel this booking?')">❌ Cancel</button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($session['status'] == 'approved' && $session['payment_status'] != 'paid'): ?>
                            <a href="payment.php?booking_id=<?php echo $session['id']; ?>" class="action-btn approve" style="text-align: center;">💰 Pay Now</a>
                        <?php endif; ?>

                        <?php if ($session['status'] == 'approved' && $session['payment_status'] == 'paid'): ?>
                            <a href="session_call.php?booking_id=<?php echo $session['id']; ?>" target="_blank"
                               style="display:flex;align-items:center;justify-content:center;gap:6px;padding:10px 14px;background:#dc2626;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;font-size:.82rem;width:100%;">
                                <i class="fas fa-video"></i> Join Call
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($session['status'] == 'completed'): ?>
                            <?php
                            $stmt = $conn->prepare("SELECT id FROM ratings WHERE booking_id = ?");
                            $stmt->execute([$session['id']]);
                            $history = $stmt->fetch();
                            ?>
                            <?php if (!$history): ?>
                                <a href="rate_session.php?booking_id=<?php echo $session['id']; ?>" class="action-btn approve" style="text-align: center;">⭐ Rate</a>
                            <?php else: ?>
                                <div style="text-align: center; padding: 8px; background: #f5f5f5; border-radius: 5px;">✓ Rated</div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-data">No bookings found. <a href="book_session.php">Book your first session!</a></div>
    <?php endif; ?>
    </div>
</div>

<!-- Rating Modal -->
<div id="ratingModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>Rate Your Session</h2>
        <form method="POST" action="rate_session.php" id="ratingForm">
            <input type="hidden" name="session_id" id="ratingSessionId">
            <div class="form-group">
                <label>How was your session?</label>
                <div class="star-rating">
                    <input type="radio" name="rating" value="5" id="star5"><label for="star5">★</label>
                    <input type="radio" name="rating" value="4" id="star4"><label for="star4">★</label>
                    <input type="radio" name="rating" value="3" id="star3"><label for="star3">★</label>
                    <input type="radio" name="rating" value="2" id="star2"><label for="star2">★</label>
                    <input type="radio" name="rating" value="1" id="star1"><label for="star1">★</label>
                </div>
            </div>
            <div class="form-group">
                <label for="feedback">Feedback (Optional)</label>
                <textarea id="feedback" name="feedback" rows="4" placeholder="Share your experience..."></textarea>
            </div>
            <button type="submit" class="btn-login">Submit Rating</button>
        </form>
    </div>
</div>

<script>
function filterBookings(status) {
    const cards = document.querySelectorAll('.booking-card');
    const tabs = document.querySelectorAll('.tab-btn');
    tabs.forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');
    cards.forEach(card => {
        card.style.display = (status === 'all' || card.dataset.status === status) ? 'block' : 'none';
    });
}

function showRatingModal(sessionId) {
    document.getElementById('ratingSessionId').value = sessionId;
    document.getElementById('ratingModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('ratingModal').style.display = 'none';
}
</script>

<?php include 'footer.php'; ?>