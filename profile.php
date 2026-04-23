<?php
require_once 'config.php';
$page_title = 'My Profile';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
$conn = getConnection();
$error = null;
$success = null;

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_FILES['profile_pic'])) {
    $full_name = $_POST['full_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $bio = $_POST['bio'] ?? '';
    
    if ($user['role'] == 'tutor') {
        $hourly_rate = $_POST['hourly_rate'] ?? 500;
        $expertise = $_POST['expertise'] ?? '';
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, address = ?, bio = ?, hourly_rate = ?, expertise = ?, is_available = ? WHERE id = ?");
        $stmt->execute([$full_name, $phone, $address, $bio, $hourly_rate, $expertise, $is_available, $user['id']]);
        
        // Update subjects
        $subjects_selected = $_POST['subjects'] ?? [];
        $stmt = $conn->prepare("DELETE FROM tutor_subjects WHERE tutor_id = ?");
        $stmt->execute([$user['id']]);
        
        if (!empty($subjects_selected)) {
            $stmt = $conn->prepare("INSERT INTO tutor_subjects (tutor_id, subject_id) VALUES (?, ?)");
            foreach ($subjects_selected as $subject_id) {
                $stmt->execute([$user['id'], $subject_id]);
            }
        }
    } else {
        $year_level = $_POST['year_level'] ?? 1;
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, address = ?, bio = ?, year_level = ? WHERE id = ?");
        $stmt->execute([$full_name, $phone, $address, $bio, $year_level, $user['id']]);
    }
    
    setFlash("Profile updated successfully!");
    redirect('profile.php');
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_pic'])) {
    $file = $_FILES['profile_pic'];
    if ($file['error'] == 0) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($ext, $allowed)) {
            $new_filename = time() . '_' . $user['id'] . '.' . $ext;
            move_uploaded_file($file['tmp_name'], 'uploads/' . $new_filename);
            
            if ($user['profile_pic'] && file_exists('uploads/' . $user['profile_pic'])) {
                unlink('uploads/' . $user['profile_pic']);
            }
            
            $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $stmt->execute([$new_filename, $user['id']]);
            setFlash("Profile picture updated!");
            redirect('profile.php');
        } else {
            $error = "Invalid file type. Allowed: JPG, PNG, GIF";
        }
    }
}

// Handle remove picture
if (isset($_GET['remove_pic'])) {
    if ($user['profile_pic'] && file_exists('uploads/' . $user['profile_pic'])) {
        unlink('uploads/' . $user['profile_pic']);
    }
    $stmt = $conn->prepare("UPDATE users SET profile_pic = NULL WHERE id = ?");
    $stmt->execute([$user['id']]);
    setFlash("Profile picture removed!");
    redirect('profile.php');
}

// Get subjects for tutor
$subjects = [];
$tutor_subject_ids = [];
if ($user['role'] == 'tutor') {
    $subjects = $conn->query("SELECT * FROM subjects ORDER BY name")->fetchAll();
    $stmt = $conn->prepare("SELECT subject_id FROM tutor_subjects WHERE tutor_id = ?");
    $stmt->execute([$user['id']]);
    $tutor_subject_ids = array_column($stmt->fetchAll(), 'subject_id');
}
?>

<?php include 'header.php'; ?>

<div class="dashboard-container">
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="images/scclogo.png" alt="SCC Logo">
            <h3><?php echo ucfirst($user['role']); ?> Menu</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <?php if ($user['role'] == 'student'): ?>
                <a href="book_session.php" class="sidebar-link"><i class="fas fa-calendar-plus"></i> Book a Tutor</a>
                <a href="my_bookings.php" class="sidebar-link"><i class="fas fa-list-alt"></i> My Bookings</a>
            <?php else: ?>
                <a href="my_bookings.php" class="sidebar-link"><i class="fas fa-list-alt"></i> Booking Requests</a>
            <?php endif; ?>
            <a href="profile.php" class="sidebar-link active"><i class="fas fa-user-circle"></i> Profile</a>
            <a href="logout.php" class="sidebar-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <?php if ($user['profile_pic']): ?>
                        <img src="uploads/<?php echo $user['profile_pic']; ?>">
                    <?php else: ?>
                        <?php echo substr($user['full_name'], 0, 1); ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="user-name"><?php echo $user['full_name']; ?></div>
                    <div class="user-role"><?php echo ucfirst($user['role']); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="welcome-banner">
            <h1><i class="fas fa-user-circle"></i> My Profile</h1>
            <p>Manage your personal information</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: 300px 1fr; gap: 25px;">
            <!-- Profile Picture Section -->
            <div class="content-card">
                <h3 style="margin-bottom: 15px;">Profile Picture</h3>
                <div style="text-align: center;">
                    <div class="user-avatar" style="width: 150px; height: 150px; margin: 0 auto 20px; font-size: 3rem;">
                        <?php if ($user['profile_pic']): ?>
                            <img src="uploads/<?php echo $user['profile_pic']; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <?php echo substr($user['full_name'], 0, 1); ?>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="file" name="profile_pic" accept="image/*" style="margin-bottom: 10px;">
                        <button type="submit" class="btn-primary" style="width: 100%;">Upload Picture</button>
                    </form>
                    
                    <?php if ($user['profile_pic']): ?>
                        <a href="?remove_pic=1" class="btn-primary" style="display: block; margin-top: 10px; background: var(--danger-red); text-align: center; text-decoration: none;">Remove Picture</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Personal Information Section -->
            <div class="content-card">
                <h3 style="margin-bottom: 15px;">Personal Information</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled style="background: #f5f5f5;">
                    </div>
                    
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" rows="2"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Bio</label>
                        <textarea name="bio" rows="3"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    </div>
                    
                    <?php if ($user['role'] == 'student'): ?>
                        <div class="form-group">
                            <label>Year Level</label>
                            <select name="year_level">
                                <option value="1" <?php echo ($user['year_level'] ?? 1) == 1 ? 'selected' : ''; ?>>1st Year</option>
                                <option value="2" <?php echo ($user['year_level'] ?? 1) == 2 ? 'selected' : ''; ?>>2nd Year</option>
                                <option value="3" <?php echo ($user['year_level'] ?? 1) == 3 ? 'selected' : ''; ?>>3rd Year</option>
                                <option value="4" <?php echo ($user['year_level'] ?? 1) == 4 ? 'selected' : ''; ?>>4th Year</option>
                            </select>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label>Hourly Rate (₱)</label>
                            <input type="number" name="hourly_rate" step="50" value="<?php echo $user['hourly_rate'] ?? 500; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Expertise</label>
                            <input type="text" name="expertise" value="<?php echo htmlspecialchars($user['expertise'] ?? ''); ?>" placeholder="e.g., Python, Web Development">
                        </div>
                        
                        <div class="form-group">
                            <label>Subjects You Teach</label>
                            <div class="subjects-grid">
                                <?php foreach ($subjects as $subject): ?>
                                    <label class="subject-checkbox">
                                        <input type="checkbox" name="subjects[]" value="<?php echo $subject['id']; ?>" <?php echo in_array($subject['id'], $tutor_subject_ids) ? 'checked' : ''; ?>>
                                        <?php echo $subject['name']; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_available" value="1" <?php echo ($user['is_available'] ?? 1) ? 'checked' : ''; ?>>
                                Available for Tutoring
                            </label>
                        </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn-primary">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.subjects-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-top: 10px;
}
.subject-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: normal;
    cursor: pointer;
}
@media (max-width: 768px) {
    .subjects-grid { grid-template-columns: 1fr; }
}
</style>

<?php include 'footer.php'; ?>