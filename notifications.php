<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
$conn = getConnection();

// Mark notification as read
if (isset($_GET['read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['read'], $user['id']]);
    redirect('notifications.php');
}

// Mark all as read
if (isset($_GET['read_all'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    redirect('notifications.php');
}

// Get notifications
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

// Unread count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$user['id']]);
$unread_count = $stmt->fetch()['count'];
?>

<?php include 'header.php'; ?>

<div class="container" style="max-width: 800px; margin: 80px auto;">
    <div style="background: rgba(255,255,255,0.95); border-radius: 20px; padding: 25px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--primary-red);">
            <h2><i class="fas fa-bell"></i> Notifications</h2>
            <?php if ($unread_count > 0): ?>
                <a href="?read_all=1" class="btn-login" style="width: auto; padding: 8px 20px;">Mark All Read</a>
            <?php endif; ?>
        </div>
        
        <?php if ($notifications): ?>
            <?php foreach ($notifications as $notification): ?>
                <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>" style="padding: 15px; margin-bottom: 10px; border-radius: 10px; border-left: 4px solid <?php echo $notification['is_read'] ? '#ccc' : 'var(--primary-red)'; ?>; background: <?php echo $notification['is_read'] ? '#f9f9f9' : '#fff3cd'; ?>;">
                    <div><?php echo htmlspecialchars($notification['message']); ?></div>
                    <small style="color: #999;"><?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></small>
                    <?php if (!$notification['is_read']): ?>
                        <a href="?read=<?php echo $notification['id']; ?>" style="float: right; margin-top: -25px;">Mark read</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-data">No notifications yet.</div>
        <?php endif; ?>
        
        <div style="margin-top: 20px;">
            <a href="javascript:history.back()" class="btn-login" style="width: auto; padding: 8px 20px; background: var(--gray);"><i class="fas fa-arrow-left"></i> Go Back</a>
        </div>
    </div>
</div>

<style>
.notification-item.unread { background: #fff3cd; }
.notification-item.read { background: #f9f9f9; }
.no-data { text-align: center; padding: 40px; color: #999; }
@media (max-width: 768px) {
    .container { margin: 60px auto; padding: 15px; }
}
</style>

<?php include 'footer.php'; ?>