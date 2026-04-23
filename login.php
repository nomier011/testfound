<?php
require_once 'config.php';
$page_title = 'Login - BSIT Tutoring';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && $user['password'] == $password) {
        if ($user['status'] == 'pending') {
            $error = "Your account is pending admin approval.";
        } elseif ($user['status'] == 'rejected') {
            $error = "Your account has been rejected. Contact administrator.";
        } elseif ($user['status'] == 'approved') {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            setFlash("Welcome back, {$user['full_name']}!", 'success');
            
            if ($user['role'] == 'admin') {
                redirect('admin_dashboard.php');
            } elseif ($user['role'] == 'student') {
                redirect('student_dashboard.php');
            } elseif ($user['role'] == 'tutor') {
                redirect('tutor_dashboard.php');
            }
        }
    } else {
        $error = "Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Background Layers -->
    <div class="bg-scc4"></div>
    <div class="bg-overlay"></div>
    <div class="bg-bsit"></div>
    <div class="bg-scc-corner"></div>
    
    <div class="auth-container">
        <div class="auth-left">
            <h1>BSIT Tutoring</h1>
            <p>Connect with expert tutors and excel in your studies</p>
            
            <div class="auth-features">
                <div class="feature-item">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Expert Tutors in BSIT Fields</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Flexible Scheduling</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-credit-card"></i>
                    <span>Secure Payments via GCash/PayMongo</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-tasks"></i>
                    <span>Assignment Management</span>
                </div>
            </div>
        </div>
        
        <div class="auth-right">
            <div class="auth-form-container">
                <div class="auth-logo">
                    <img src="images/scclogo.png" alt="SCC Logo">
                    <h2>Welcome Back!</h2>
                </div>
                
                <?php if ($error): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php $flash = getFlash(); if ($flash): ?>
                    <div class="success-message"><?php echo $flash['message']; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" class="auth-form">
                    <div class="form-group">
                        <input type="text" name="username" placeholder="Username" required>
                    </div>
                    <div class="form-group">
                        <input type="password" name="password" placeholder="Password" required>
                    </div>
                    <button type="submit" class="btn-login">Log In</button>
                </form>
                
                <div class="auth-divider">
                    <span>or</span>
                </div>
                
                <div class="auth-footer">
                    <a href="register.php">Create New Account</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>