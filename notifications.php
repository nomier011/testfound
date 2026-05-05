<?php
require_once 'config.php';
$page_title = 'Notifications';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
$conn = getConnection();

// Mark all as read
$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
$stmt->execute([$user['id']]);

// Get all notifications
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

$dashboard = $user['role'] == 'student' ? 'student_dashboard.php' : ($user['role'] == 'tutor' ? 'tutor_dashboard.php' : 'admin_dashboard.php');
?>
<?php include 'header.php'; ?>

<div class="page-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="page-hero">
        <div class="page-hero-content">
            <h1>Notifications</h1>
            <p>Stay updated with your latest alerts</p>
        </div>
    </div>

    <div class="page-inner">

        <div class="notifications-list">
            <?php if ($notifications): ?>
                <?php foreach ($notifications as $notif): ?>
                    <div class="notif-card <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                        <div class="notif-icon">
                            <?php
                            $icon = 'fa-bell';
                            if (strpos($notif['type'], 'booking') !== false) $icon = 'fa-calendar-alt';
                            elseif (strpos($notif['type'], 'payment') !== false) $icon = 'fa-credit-card';
                            elseif (strpos($notif['type'], 'assignment') !== false) $icon = 'fa-tasks';
                            ?>
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div class="notif-body">
                            <p><?php echo $notif['message']; ?></p>
                            <small><?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-notif">
                    <i class="fas fa-bell-slash"></i>
                    <p>No notifications yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.notifications-list { display: flex; flex-direction: column; gap: 12px; }
.notif-card {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    background: rgba(255,255,255,0.88);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 16px 20px;
    border: 1px solid rgba(255,255,255,0.3);
    transition: all 0.3s;
}
.notif-card.unread {
    border-left: 4px solid var(--primary-red);
    background: rgba(255,255,255,0.95);
}
.notif-card:hover { transform: translateX(4px); box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
.notif-icon {
    width: 42px;
    height: 42px;
    background: rgba(139,0,0,0.1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-red);
    font-size: 1.1rem;
    flex-shrink: 0;
}
.notif-body p { margin: 0 0 4px; color: #333; font-size: 0.9rem; }
.notif-body small { color: #999; font-size: 0.75rem; }
.no-notif { text-align: center; padding: 60px; background: rgba(255,255,255,0.88); border-radius: 15px; color: #999; }
.no-notif i { font-size: 3rem; margin-bottom: 15px; color: #ccc; display: block; }
</style>

<?php include 'footer.php'; ?>
