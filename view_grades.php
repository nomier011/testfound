<?php
require_once 'config.php';
$page_title = 'My Grades';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
if ($user['role'] != 'student') {
    redirect('index.php');
}

$conn = getConnection();

$stmt = $conn->prepare("
    SELECT g.*, sub.name as subject_name
    FROM grades g
    JOIN subjects sub ON g.subject_id = sub.id
    WHERE g.student_id = ?
    ORDER BY g.created_at DESC
");
$stmt->execute([$user['id']]);
$grades = $stmt->fetchAll();

// Calculate GPA
$total_points = 0;
foreach ($grades as $grade) {
    $total_points += $grade['final_grade'];
}
$gpa = $grades ? $total_points / count($grades) : 0;

// Count passed/failed
$passed = 0;
$failed = 0;
foreach ($grades as $grade) {
    if ($grade['final_grade'] >= 75) {
        $passed++;
    } else {
        $failed++;
    }
}

// Group by semester
$semesters = [];
foreach ($grades as $grade) {
    $key = $grade['semester'] . ' ' . $grade['school_year'];
    if (!isset($semesters[$key])) {
        $semesters[$key] = [];
    }
    $semesters[$key][] = $grade;
}
?>

<?php include 'header.php'; ?>

<div class="main-content">
    <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="images/scclogo.png" alt="SCC Logo">
            <h3>Student Menu</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="student_dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="book_session.php" class="sidebar-link"><i class="fas fa-calendar-plus"></i> Create Booking</a>
            <a href="view_instructors.php" class="sidebar-link"><i class="fas fa-chalkboard-teacher"></i> View Instructors</a>
            <a href="view_bookings.php" class="sidebar-link"><i class="fas fa-list-alt"></i> View Bookings</a>
            <a href="view_grades.php" class="sidebar-link active"><i class="fas fa-chart-line"></i> View Grades</a>
            <a href="view_payments.php" class="sidebar-link"><i class="fas fa-credit-card"></i> Payment Due</a>
            <a href="profile.php" class="sidebar-link"><i class="fas fa-user-circle"></i> Profile</a>
            <a href="logout.php" class="sidebar-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <?php if ($user['profile_pic']): ?>
                        <img src="uploads/<?php echo $user['profile_pic']; ?>" alt="Profile">
                    <?php else: ?>
                        <?php echo substr($user['full_name'], 0, 1); ?>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo $user['full_name']; ?></div>
                    <div class="user-role">Student • Year <?php echo $user['year_level']; ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="welcome-banner">
        <h1><i class="fas fa-chart-line"></i> My Grades</h1>
        <p>Track your academic performance</p>
    </div>
    
    <div class="gpa-overview" style="display: flex; align-items: center; gap: 30px; background: rgba(255,255,255,0.95); border-radius: 20px; padding: 30px; margin-bottom: 30px;">
        <div style="text-align: center;">
            <div style="font-size: 3rem; font-weight: bold; color: var(--primary-red);"><?php echo number_format($gpa, 2); ?></div>
            <div>GPA</div>
        </div>
        <div style="flex: 1; display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
            <div style="text-align: center;"><div style="font-size: 1.8rem; font-weight: bold;"><?php echo count($grades); ?></div><div>Subjects</div></div>
            <div style="text-align: center;"><div style="font-size: 1.8rem; font-weight: bold; color: var(--success-green);"><?php echo $passed; ?></div><div>Passed</div></div>
            <div style="text-align: center;"><div style="font-size: 1.8rem; font-weight: bold; color: var(--danger-red);"><?php echo $failed; ?></div><div>Failed</div></div>
        </div>
    </div>
    
    <?php foreach ($semesters as $semester_name => $semester_grades): ?>
        <div class="content-card" style="margin-bottom: 20px;">
            <div class="card-header">
                <h3><?php echo $semester_name; ?></h3>
            </div>
            <table class="grades-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f5f5f5;">
                        <th style="padding: 12px; text-align: left;">Subject</th>
                        <th style="padding: 12px; text-align: left;">Quiz</th>
                        <th style="padding: 12px; text-align: left;">Exam</th>
                        <th style="padding: 12px; text-align: left;">Assignment</th>
                        <th style="padding: 12px; text-align: left;">Final Grade</th>
                        <th style="padding: 12px; text-align: left;">Status</th>
                        <th style="padding: 12px; text-align: left;">Instructor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($semester_grades as $grade): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 10px;"><?php echo $grade['subject_name']; ?></td>
                            <td style="padding: 10px;"><?php echo $grade['quiz'] ?? 'N/A'; ?></td>
                            <td style="padding: 10px;"><?php echo $grade['exam'] ?? 'N/A'; ?></td>
                            <td style="padding: 10px;"><?php echo $grade['assignment'] ?? 'N/A'; ?></td>
                            <td style="padding: 10px; font-weight: bold; color: <?php echo $grade['final_grade'] >= 75 ? 'var(--success-green)' : 'var(--danger-red)'; ?>;"><?php echo $grade['final_grade']; ?></td>
                            <td style="padding: 10px;"><span class="status-badge <?php echo $grade['final_grade'] >= 75 ? 'status-approved' : 'status-rejected'; ?>"><?php echo $grade['final_grade'] >= 75 ? 'PASSED' : 'FAILED'; ?></span></td>
                            <td style="padding: 10px;"><?php echo $grade['instructor']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
    
    <?php if (!$grades): ?>
        <div class="no-data">No grades available yet.</div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>