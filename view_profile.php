<?php
require_once 'config.php';
$page_title = 'Tutor Profile';
if (!isLoggedIn()) redirect('login.php');

$conn      = getConnection();
$viewed_id = sanitizeInt($_GET['id'] ?? 0);

// Get the user being viewed
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND status = 'approved'");
$stmt->execute([$viewed_id]);
$viewed = $stmt->fetch();

if (!$viewed) {
    setFlash("Profile not found.", 'danger');
    redirect('dashboard.php');
}

$page_title = htmlspecialchars($viewed['full_name']) . ' — Profile';

// Get subjects (for tutors)
$subjects = [];
if ($viewed['role'] === 'tutor') {
    $stmt = $conn->prepare("SELECT s.name FROM subjects s JOIN tutor_subjects ts ON s.id = ts.subject_id WHERE ts.tutor_id = ?");
    $stmt->execute([$viewed_id]);
    $subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get ratings (for tutors)
$ratings = [];
$avg_rating = 0;
if ($viewed['role'] === 'tutor') {
    $stmt = $conn->prepare("
        SELECT r.rating, r.feedback, r.created_at, u.full_name as student_name
        FROM ratings r
        JOIN bookings b ON r.booking_id = b.id
        JOIN users u ON r.student_id = u.id
        WHERE b.tutor_id = ?
        ORDER BY r.created_at DESC LIMIT 10
    ");
    $stmt->execute([$viewed_id]);
    $ratings = $stmt->fetchAll();

    $stmt = $conn->prepare("SELECT ROUND(AVG(r.rating),1) as avg FROM ratings r JOIN bookings b ON r.booking_id=b.id WHERE b.tutor_id=?");
    $stmt->execute([$viewed_id]);
    $avg_rating = $stmt->fetch()['avg'] ?? 0;
}

// Completed sessions count
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM bookings WHERE tutor_id = ? AND status = 'completed'");
$stmt->execute([$viewed_id]);
$sessions_count = $stmt->fetch()['cnt'];

$pic_path = $viewed['profile_pic'] ? 'uploads/' . htmlspecialchars(basename($viewed['profile_pic'])) : null;
?>
<?php include 'header.php'; ?>

<div class="page-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="page-hero">
        <div class="page-hero-content">
            <h1><?php echo htmlspecialchars($viewed['full_name']); ?></h1>
            <p><?php echo ucfirst($viewed['role']); ?> · <?php echo htmlspecialchars($viewed['expertise'] ?? 'BSIT Tutor'); ?></p>
        </div>
    </div>

    <div class="page-inner">
        <div style="display:grid;grid-template-columns:300px 1fr;gap:24px;align-items:start;">

            <!-- Left: Avatar + quick info -->
            <div>
                <div class="content-card" style="text-align:center;">
                    <div style="width:120px;height:120px;border-radius:50%;background:linear-gradient(135deg,#dc2626,#2563eb);display:flex;align-items:center;justify-content:center;font-size:3rem;font-weight:900;color:#fff;margin:0 auto 16px;overflow:hidden;border:4px solid #f3f4f6;">
                        <?php if ($pic_path): ?>
                            <img src="<?php echo $pic_path; ?>" alt="<?php echo htmlspecialchars($viewed['full_name']); ?>" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                            <?php echo strtoupper(substr($viewed['full_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>

                    <h2 style="font-size:1.1rem;font-weight:800;color:#111;margin-bottom:4px;"><?php echo htmlspecialchars($viewed['full_name']); ?></h2>
                    <p style="font-size:.8rem;color:#6b7280;margin-bottom:16px;"><?php echo ucfirst($viewed['role']); ?></p>

                    <?php if ($viewed['role'] === 'tutor'): ?>
                    <!-- Rating -->
                    <div style="display:flex;justify-content:center;gap:3px;margin-bottom:6px;">
                        <?php for ($i=1;$i<=5;$i++): ?>
                            <i class="fas fa-star" style="color:<?php echo $i<=$avg_rating?'#f59e0b':'#e5e7eb'; ?>;font-size:.9rem;"></i>
                        <?php endfor; ?>
                    </div>
                    <p style="font-size:.78rem;color:#9ca3af;margin-bottom:16px;"><?php echo $avg_rating ? number_format($avg_rating,1).' / 5.0' : 'No ratings yet'; ?> · <?php echo $sessions_count; ?> sessions</p>

                    <!-- Rate -->
                    <div style="background:#fef2f2;border-radius:10px;padding:12px;margin-bottom:16px;">
                        <div style="font-size:1.4rem;font-weight:900;color:#dc2626;">₱<?php echo number_format($viewed['hourly_rate'],0); ?></div>
                        <div style="font-size:.72rem;color:#9ca3af;">per hour</div>
                    </div>

                    <!-- Availability -->
                    <div style="display:flex;align-items:center;justify-content:center;gap:6px;font-size:.8rem;font-weight:600;color:<?php echo $viewed['is_available']?'#059669':'#dc2626'; ?>;margin-bottom:16px;">
                        <span style="width:8px;height:8px;border-radius:50%;background:<?php echo $viewed['is_available']?'#10b981':'#ef4444'; ?>;"></span>
                        <?php echo $viewed['is_available'] ? 'Available for sessions' : 'Currently unavailable'; ?>
                    </div>

                    <?php if ($viewed['is_available']): ?>
                    <a href="book_session.php?tutor=<?php echo $viewed['id']; ?>" class="btn-primary" style="display:block;text-align:center;text-decoration:none;">
                        <i class="fas fa-calendar-plus"></i> Book a Session
                    </a>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Contact info -->
                <?php if ($viewed['phone'] || $viewed['address']): ?>
                <div class="content-card" style="margin-top:16px;">
                    <div class="card-header"><h3><i class="fas fa-info-circle"></i> Contact</h3></div>
                    <?php if ($viewed['phone']): ?>
                    <div style="display:flex;gap:10px;align-items:center;padding:8px 0;font-size:.85rem;border-bottom:1px solid #f3f4f6;">
                        <i class="fas fa-phone" style="color:#dc2626;width:16px;"></i>
                        <span><?php echo htmlspecialchars($viewed['phone']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($viewed['address']): ?>
                    <div style="display:flex;gap:10px;align-items:flex-start;padding:8px 0;font-size:.85rem;">
                        <i class="fas fa-map-marker-alt" style="color:#dc2626;width:16px;margin-top:2px;"></i>
                        <span><?php echo htmlspecialchars($viewed['address']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right: Details -->
            <div>
                <!-- Bio -->
                <?php if ($viewed['bio']): ?>
                <div class="content-card" style="margin-bottom:20px;">
                    <div class="card-header"><h3><i class="fas fa-user"></i> About</h3></div>
                    <p style="font-size:.9rem;color:#374151;line-height:1.8;"><?php echo nl2br(htmlspecialchars($viewed['bio'])); ?></p>
                </div>
                <?php endif; ?>

                <?php if ($viewed['role'] === 'tutor'): ?>
                <!-- Subjects -->
                <?php if ($subjects): ?>
                <div class="content-card" style="margin-bottom:20px;">
                    <div class="card-header"><h3><i class="fas fa-book"></i> Subjects</h3></div>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                        <?php foreach ($subjects as $s): ?>
                        <span style="background:#fef2f2;color:#dc2626;padding:6px 14px;border-radius:20px;font-size:.8rem;font-weight:600;border:1px solid #fecaca;">
                            <?php echo htmlspecialchars($s); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="content-card" style="margin-bottom:20px;">
                    <div class="card-header"><h3><i class="fas fa-chart-bar"></i> Stats</h3></div>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;text-align:center;">
                        <div style="padding:16px;background:#f9fafb;border-radius:10px;">
                            <div style="font-size:1.5rem;font-weight:800;color:#dc2626;"><?php echo $sessions_count; ?></div>
                            <div style="font-size:.72rem;color:#9ca3af;margin-top:4px;">Sessions</div>
                        </div>
                        <div style="padding:16px;background:#f9fafb;border-radius:10px;">
                            <div style="font-size:1.5rem;font-weight:800;color:#dc2626;"><?php echo $avg_rating ? number_format($avg_rating,1) : '—'; ?></div>
                            <div style="font-size:.72rem;color:#9ca3af;margin-top:4px;">Avg Rating</div>
                        </div>
                        <div style="padding:16px;background:#f9fafb;border-radius:10px;">
                            <div style="font-size:1.5rem;font-weight:800;color:#dc2626;"><?php echo count($ratings); ?></div>
                            <div style="font-size:.72rem;color:#9ca3af;margin-top:4px;">Reviews</div>
                        </div>
                    </div>
                </div>

                <!-- Reviews -->
                <?php if ($ratings): ?>
                <div class="content-card">
                    <div class="card-header"><h3><i class="fas fa-star"></i> Student Reviews</h3></div>
                    <div style="display:flex;flex-direction:column;gap:16px;">
                        <?php foreach ($ratings as $r): ?>
                        <div style="padding:14px;background:#f9fafb;border-radius:10px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                                <strong style="font-size:.85rem;"><?php echo htmlspecialchars($r['student_name']); ?></strong>
                                <div style="display:flex;gap:2px;">
                                    <?php for($i=1;$i<=5;$i++): ?>
                                        <i class="fas fa-star" style="color:<?php echo $i<=$r['rating']?'#f59e0b':'#e5e7eb'; ?>;font-size:.75rem;"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php if ($r['feedback']): ?>
                            <p style="font-size:.82rem;color:#6b7280;font-style:italic;">"<?php echo htmlspecialchars($r['feedback']); ?>"</p>
                            <?php endif; ?>
                            <small style="font-size:.7rem;color:#9ca3af;"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; // end tutor ?>

                <?php if ($viewed['role'] === 'student'): ?>
                <!-- Student info -->
                <div class="content-card">
                    <div class="card-header"><h3><i class="fas fa-graduation-cap"></i> Student Info</h3></div>
                    <div style="font-size:.875rem;color:#374151;">
                        <p style="padding:8px 0;border-bottom:1px solid #f3f4f6;"><strong>Year Level:</strong> Year <?php echo $viewed['year_level'] ?? 1; ?></p>
                        <p style="padding:8px 0;"><strong>School ID:</strong> <?php echo htmlspecialchars($viewed['school_id'] ?? '—'); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
