<?php
require_once 'config.php';
$page_title = 'Register - BSIT Tutoring';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = null;
$success = null;
$conn = getConnection();

// Get subjects
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $year_level = $_POST['year_level'] ?? 1;
    $hourly_rate = $_POST['hourly_rate'] ?? 0;
    $subjects_selected = $_POST['subjects'] ?? [];
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    
    if ($stmt->fetch()) {
        $error = "Username or email already exists";
    } else {
        // Insert user with 'pending' status
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, full_name, year_level, hourly_rate, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$username, $password, $email, $role, $full_name, $year_level, $hourly_rate]);
        $user_id = $conn->lastInsertId();
        
        // Insert tutor subjects
        if ($role == 'tutor' && !empty($subjects_selected)) {
            $stmt = $conn->prepare("INSERT INTO tutor_subjects (tutor_id, subject_id) VALUES (?, ?)");
            foreach ($subjects_selected as $subject_id) {
                $stmt->execute([$user_id, $subject_id]);
            }
        }
        
        $success = "Registration successful! Your account is pending admin approval.";
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
            <h1>Join BSIT Tutoring</h1>
            <p>Start your learning journey with expert tutors today</p>
            
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
                    <h2>Create Account</h2>
                </div>
                
                <?php if ($error): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="success-message"><?php echo $success; ?></div>
                    <div class="info-message">Please wait for admin approval. You will be notified once approved.</div>
                <?php endif; ?>
                
                <?php if (!$success): ?>
                <form method="POST" action="" class="auth-form" id="registerForm">
                    <!-- Role Selection Toggle -->
                    <div class="role-toggle">
                        <div class="role-option <?php echo (!isset($_POST['role']) || $_POST['role'] == 'student') ? 'selected' : ''; ?>" data-role="student" onclick="selectRole('student')">
                            <i class="fas fa-user-graduate"></i> Student
                        </div>
                        <div class="role-option <?php echo (isset($_POST['role']) && $_POST['role'] == 'tutor') ? 'selected' : ''; ?>" data-role="tutor" onclick="selectRole('tutor')">
                            <i class="fas fa-chalkboard-teacher"></i> Tutor
                        </div>
                    </div>
                    <input type="hidden" name="role" id="role" value="student">
                    
                    <div class="form-group">
                        <input type="text" name="full_name" placeholder="Full Name" required>
                    </div>
                    <div class="form-group">
                        <input type="text" name="username" placeholder="Username" required>
                    </div>
                    <div class="form-group">
                        <input type="email" name="email" placeholder="Email" required>
                    </div>
                    
                    <div class="form-group" id="yearLevelGroup">
                        <select name="year_level">
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                    
                    <div id="tutorFields" style="display: none;">
                        <div class="form-group">
                            <input type="number" name="hourly_rate" step="50" placeholder="Hourly Rate (₱)" value="500">
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.85rem;">Subjects You Can Teach</label>
                            <div class="subjects-grid">
                                <?php foreach ($subjects as $subject): ?>
                                    <label class="subject-checkbox">
                                        <input type="checkbox" name="subjects[]" value="<?php echo $subject['id']; ?>">
                                        <?php echo $subject['name']; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <input type="password" name="password" id="password" placeholder="Password" required>
                    </div>
                    <div class="form-group">
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
                    </div>
                    
                    <button type="submit" class="btn-register">Sign Up</button>
                </form>
                
                <div class="auth-divider">
                    <span>or</span>
                </div>
                
                <div class="auth-footer">
                    <a href="login.php">Already have an account? Log In</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

<script>
function selectRole(role) {
    document.getElementById('role').value = role;
    document.querySelectorAll('.role-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    document.querySelector(`.role-option[data-role="${role}"]`).classList.add('selected');
    
    // Show/hide tutor fields
    const tutorFields = document.getElementById('tutorFields');
    const yearLevelGroup = document.getElementById('yearLevelGroup');
    
    if (role === 'tutor') {
        tutorFields.style.display = 'block';
        yearLevelGroup.style.display = 'none';
    } else {
        tutorFields.style.display = 'none';
        yearLevelGroup.style.display = 'block';
    }
}

document.getElementById('registerForm')?.addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    
    if (password !== confirm) {
        e.preventDefault();
        alert('Passwords do not match!');
    }
});
</script>

<style>
.subjects-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    margin-top: 8px;
}
.subject-checkbox {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    cursor: pointer;
}
.subject-checkbox input {
    width: 16px;
    height: 16px;
    cursor: pointer;
}
</style>