<?php
if (!isset($current_user) || !$current_user) {
    $current_user = getCurrentUser();
}
if (!$current_user) return;

$user = $current_user;
$menu_items = getUserMenu($user['role']);
$current_page = basename($_SERVER['SCRIPT_NAME']);
$notif_count = getUnreadNotificationCount($user['id']);
?>

<!-- ── TOP NAVBAR (Wix-style) ── -->
<nav class="topnav">
    <div class="topnav-brand">
        <img src="images/scc.png" alt="SCC Logo" class="topnav-logo">
        <div class="topnav-title">
            <span class="topnav-name">BSIT Tutoring</span>
            <span class="topnav-sub"><?php echo ucfirst($user['role']); ?> Portal</span>
        </div>
    </div>

    <ul class="topnav-links" id="topnavLinks">
        <?php foreach ($menu_items as $item):
            $item_url = explode('#', $item['url'])[0];
            $is_active = strpos($current_page, $item_url) !== false;
        ?>
        <li>
            <a href="<?php echo htmlspecialchars($item['url']); ?>"
               class="topnav-link <?php echo $is_active ? 'active' : ''; ?>">
                <i class="fas <?php echo $item['icon']; ?>"></i>
                <span><?php echo $item['label']; ?></span>
                <?php if ($item['label'] === 'Notifications' && $notif_count > 0): ?>
                    <span class="topnav-badge"><?php echo $notif_count; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="topnav-right">
        <a href="profile.php" class="topnav-user">
            <div class="topnav-avatar">
                <?php if ($user['profile_pic'] && file_exists(PROFILE_PATH . $user['profile_pic'])): ?>
                    <img src="uploads/profiles/<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="">
                <?php else: ?>
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <span class="topnav-username"><?php echo htmlspecialchars(explode(' ', $user['full_name'])[0]); ?></span>
        </a>
        <a href="logout.php" class="topnav-logout" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
        </a>
        <button class="topnav-hamburger" id="hamburgerBtn" onclick="toggleMobileMenu()" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
    </div>
</nav>

<!-- Mobile drawer -->
<div class="mobile-drawer" id="mobileDrawer">
    <?php foreach ($menu_items as $item):
        $item_url = explode('#', $item['url'])[0];
        $is_active = strpos($current_page, $item_url) !== false;
    ?>
    <a href="<?php echo htmlspecialchars($item['url']); ?>"
       class="drawer-link <?php echo $is_active ? 'active' : ''; ?>">
        <i class="fas <?php echo $item['icon']; ?>"></i>
        <?php echo $item['label']; ?>
        <?php if ($item['label'] === 'Notifications' && $notif_count > 0): ?>
            <span class="topnav-badge"><?php echo $notif_count; ?></span>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
    <a href="logout.php" class="drawer-link drawer-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
<div class="drawer-overlay" id="drawerOverlay" onclick="toggleMobileMenu()"></div>