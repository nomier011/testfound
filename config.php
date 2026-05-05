<?php
/**
 * BSIT Tutoring System - Configuration File
 * Version: 2.0 Production Ready
 */

// ============================================
// 1. SECURE SESSION CONFIGURATION
// ============================================
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.gc_maxlifetime', 7200); // 2 hours
ini_set('session.use_strict_mode', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// 2. ERROR REPORTING (Disable in production)
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Create logs directory if not exists
if (!file_exists(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// ============================================
// 3. DATABASE CONFIGURATION
// ============================================
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'bsit_tutoring_db');

// ============================================
// 4. PAYMONGO CONFIGURATION
// ============================================
define('PAYMONGO_SECRET_KEY', getenv('PAYMONGO_SECRET_KEY') ?: 'sk_test_YOUR_SECRET_KEY_HERE');
define('PAYMONGO_PUBLIC_KEY', getenv('PAYMONGO_PUBLIC_KEY') ?: 'pk_test_YOUR_PUBLIC_KEY_HERE');
define('PAYMONGO_BASE_URL', 'https://api.paymongo.com/v1');

// ============================================
// 5. SYSTEM CONFIGURATION
// ============================================
define('SITE_NAME', 'BSIT Tutoring System');
define('SITE_URL', rtrim(getenv('SITE_URL') ?: 'http://localhost/testfound/', '/') . '/');
define('SITE_EMAIL', 'support@bsit-tutoring.edu.ph');
define('ADMIN_EMAIL', 'admin@bsit-tutoring.edu.ph');

// Timezone
date_default_timezone_set('Asia/Manila');

// File uploads
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip']);
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('SUBMISSION_PATH', UPLOAD_PATH . 'submissions/');
define('PROFILE_PATH', UPLOAD_PATH . 'profiles/');

// Create necessary directories
$directories = [UPLOAD_PATH, SUBMISSION_PATH, PROFILE_PATH];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Payment methods
define('PAYMENT_METHODS', ['gcash', 'paymaya', 'grab_pay', 'qrph', 'cash']);

// Session duration for different user types (in seconds)
define('SESSION_DURATION', [
    'student' => 7200,  // 2 hours
    'tutor'   => 7200,  // 2 hours
    'admin'   => 14400  // 4 hours
]);

// Hourly rate limits
define('MIN_HOURLY_RATE', 100);
define('MAX_HOURLY_RATE', 2000);

// Booking time restrictions
define('MIN_BOOKING_HOUR', 8);   // 8 AM
define('MAX_BOOKING_HOUR', 20);  // 8 PM
define('ALLOWED_DURATIONS', [0.5, 1, 1.5, 2, 2.5, 3]);

// ============================================
// 6. DATABASE CONNECTION
// ============================================
function getConnection() {
    static $conn = null;
    
    if ($conn === null) {
        try {
            $conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => true
                ]
            );
        } catch (PDOException $e) {
            error_log("DB Connection Failed: " . $e->getMessage());
            die("Unable to connect to database. Please try again later.");
        }
    }
    return $conn;
}

// ============================================
// 7. AUTHENTICATION HELPERS
// ============================================
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        setFlash("Please login to continue.", 'warning');
        redirect('login.php');
    }
}

function requireRole($allowed_roles) {
    requireLogin();
    $role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
    if (!in_array($role, (array)$allowed_roles)) {
        setFlash("You don't have permission to access this page.", 'danger');
        redirect('dashboard.php');
    }
}

function regenerateSession() {
    session_regenerate_id(true);
}

function logout() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

// ============================================
// 8. REDIRECT HELPERS
// ============================================
function redirect($url) {
    // Prevent open redirect attacks
    if (preg_match('#^https?://#i', $url)) {
        if (strpos($url, SITE_URL) !== 0) {
            $url = 'index.php';
        }
    }
    header("Location: " . $url);
    exit();
}

function redirectBack($default = 'index.php') {
    $url = $_SERVER['HTTP_REFERER'] ?? $default;
    redirect($url);
}

// ============================================
// 9. CSRF PROTECTION
// ============================================
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

// ============================================
// 10. FLASH MESSAGES
// ============================================
function setFlash($message, $type = 'success') {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type, 'time' => time()];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function hasFlash() {
    return isset($_SESSION['flash']);
}

// ============================================
// 11. USER HELPERS
// ============================================
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) return null;
    
    static $user = null;
    if ($user === null) {
        $user = getUserById($_SESSION['user_id']);
    }
    return $user;
}

function getUserById($id) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$id]);
    return $stmt->fetch();
}

function getUserByUsername($username) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch();
}

function getUserByEmail($email) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

function updateUserLastActive($user_id) {
    try {
        $conn = getConnection();
        $stmt = $conn->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
        $stmt->execute([$user_id]);
    } catch (PDOException $e) {
        // Column may not exist yet — fail silently
        error_log("updateUserLastActive failed: " . $e->getMessage());
    }
}

// ============================================
// 12. NOTIFICATION HELPERS
// ============================================
function sendNotification($user_id, $message, $type = 'general') {
    $conn = getConnection();
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, ?, NOW())");
    return $stmt->execute([$user_id, $message, $type]);
}

function sendBulkNotification($user_ids, $message, $type = 'general') {
    if (empty($user_ids)) return false;
    
    $conn = getConnection();
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, ?, NOW())");
    
    foreach ($user_ids as $user_id) {
        $stmt->execute([$user_id, $message, $type]);
    }
    return true;
}

function getUnreadNotificationCount($user_id) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetch()['count'];
}

function markNotificationsAsRead($user_id) {
    $conn = getConnection();
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    return $stmt->execute([$user_id]);
}

// ============================================
// 13. INPUT VALIDATION & SANITIZATION
// ============================================
function sanitizeInt($value, $default = 0) {
    $filtered = filter_var($value, FILTER_VALIDATE_INT);
    return $filtered !== false ? (int)$filtered : $default;
}

function sanitizeFloat($value, $default = 0.0) {
    $filtered = filter_var($value, FILTER_VALIDATE_FLOAT);
    return $filtered !== false ? (float)$filtered : $default;
}

function sanitizeString($value, $max_length = null) {
    $value = trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
    if ($max_length && strlen($value) > $max_length) {
        $value = substr($value, 0, $max_length);
    }
    return $value;
}

function sanitizeEmail($email) {
    return filter_var($email, FILTER_SANITIZE_EMAIL);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function validateTime($time, $format = 'H:i:s') {
    $d = DateTime::createFromFormat($format, $time);
    return $d && $d->format($format) === $time;
}

// ============================================
// 14. FILE UPLOAD HELPERS
// ============================================
function uploadFile($file, $type = 'profile') {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed'];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'error' => 'File type not allowed'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'File too large'];
    }
    
    $target_dir = $type === 'profile' ? PROFILE_PATH : SUBMISSION_PATH;
    $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $target_path = $target_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'error' => 'Failed to save file'];
}

function deleteFile($filename, $type = 'profile') {
    $path = ($type === 'profile' ? PROFILE_PATH : SUBMISSION_PATH) . $filename;
    if (file_exists($path)) {
        return unlink($path);
    }
    return false;
}

// ============================================
// 15. ROLE-BASED MENU
// ============================================
function getUserMenu($role) {
    $menus = [
        'student' => [
            ['url' => 'student_dashboard.php', 'icon' => 'fa-tachometer-alt', 'label' => 'Dashboard'],
            ['url' => 'book_session.php',       'icon' => 'fa-calendar-plus',  'label' => 'Book Tutor'],
            ['url' => 'my_bookings.php',        'icon' => 'fa-list-alt',       'label' => 'Bookings'],
            ['url' => 'my_assignments.php',     'icon' => 'fa-tasks',          'label' => 'Assignments'],
            ['url' => 'modules.php',            'icon' => 'fa-book-open',      'label' => 'Modules'],
            ['url' => 'coding_practice.php',    'icon' => 'fa-code',           'label' => 'Practice'],
            ['url' => 'notifications.php',      'icon' => 'fa-bell',           'label' => 'Notifications'],
            ['url' => 'profile.php',            'icon' => 'fa-user-circle',    'label' => 'Profile'],
        ],
        'tutor' => [
            ['url' => 'tutor_dashboard.php',   'icon' => 'fa-tachometer-alt', 'label' => 'Dashboard'],
            ['url' => 'my_bookings.php',        'icon' => 'fa-calendar-alt',   'label' => 'Sessions'],
            ['url' => 'my_assignments.php',     'icon' => 'fa-tasks',          'label' => 'Assignments'],
            ['url' => 'modules.php',            'icon' => 'fa-book-open',      'label' => 'Modules'],
            ['url' => 'coding_practice.php',    'icon' => 'fa-code',           'label' => 'Practice'],
            ['url' => 'notifications.php',      'icon' => 'fa-bell',           'label' => 'Notifications'],
            ['url' => 'profile.php',            'icon' => 'fa-user-circle',    'label' => 'Profile'],
        ],
        'admin' => [
            ['url' => 'admin_dashboard.php', 'icon' => 'fa-tachometer-alt', 'label' => 'Dashboard'],
            ['url' => 'admin_dashboard.php?tab=pending', 'icon' => 'fa-user-clock', 'label' => 'Pending Approvals'],
            ['url' => 'admin_dashboard.php?tab=users', 'icon' => 'fa-users', 'label' => 'Manage Users'],
            ['url' => 'admin_dashboard.php?tab=subjects', 'icon' => 'fa-book', 'label' => 'Subjects'],
            ['url' => 'admin_dashboard.php?tab=payments', 'icon' => 'fa-credit-card', 'label' => 'Payments'],
            ['url' => 'notifications.php', 'icon' => 'fa-bell', 'label' => 'Notifications'],
            ['url' => 'profile.php', 'icon' => 'fa-user-circle', 'label' => 'Profile'],
        ]
    ];
    
    return $menus[$role] ?? $menus['student'];
}

// ============================================
// 16. ACTIVITY LOGGING
// ============================================
function logActivity($user_id, $action, $details = null) {
    try {
        $conn = getConnection();
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $user_id ?: null,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        ]);
    } catch (PDOException $e) {
        // Table may not exist yet — fail silently
        error_log("logActivity failed: " . $e->getMessage());
    }
}

// ============================================
// 17. RATE LIMITING
// ============================================
function checkRateLimit($key, $max_attempts = 5, $timeframe = 900) {
    if (!isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = ['count' => 0, 'first_attempt' => time()];
    }
    
    $data = &$_SESSION['rate_limit'][$key];
    
    if (time() - $data['first_attempt'] > $timeframe) {
        $data = ['count' => 0, 'first_attempt' => time()];
    }
    
    if ($data['count'] >= $max_attempts) {
        return false;
    }
    
    $data['count']++;
    return true;
}

function resetRateLimit($key) {
    unset($_SESSION['rate_limit'][$key]);
}

// ============================================
// 18. SECURITY HEADERS
// ============================================
function setSecurityHeaders() {
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    if (isset($_SERVER['HTTPS'])) {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    }
}

// Call security headers
setSecurityHeaders();

// Update last active for logged in users
if (isLoggedIn()) {
    updateUserLastActive($_SESSION['user_id']);
}

// Auto-complete sessions whose end time has passed
require_once __DIR__ . '/auto_complete_sessions.php';
autoCompleteSessions();
?>