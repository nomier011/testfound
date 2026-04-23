<?php
require_once 'config.php';
$page_title = 'View Instructors';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
if ($user['role'] != 'student') {
    redirect('index.php');
}

$conn = getConnection();

// Get all tutors
$stmt = $conn->prepare("
    SELECT u.*, GROUP_CONCAT(DISTINCT sub.name) as subjects,
           (SELECT AVG(rating) FROM session_history WHERE tutor_id = u.id) as avg_rating
    FROM users u
    LEFT JOIN tutor_subjects ts ON u.id = ts.tutor_id
    LEFT JOIN subjects sub ON ts.subject_id = sub.id
    WHERE u.role = 'tutor'
    GROUP BY u.id
    ORDER BY u.full_name
");
$stmt->execute();
$tutors = $stmt->fetchAll();

// Get all subjects for filter
$stmt = $conn->query("SELECT * FROM subjects ORDER BY name");
$subjects = $stmt->fetchAll();
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
            <a href="view_instructors.php" class="sidebar-link active"><i class="fas fa-chalkboard-teacher"></i> View Instructors</a>
            <a href="view_bookings.php" class="sidebar-link"><i class="fas fa-list-alt"></i> View Bookings</a>
            <a href="view_grades.php" class="sidebar-link"><i class="fas fa-chart-line"></i> View Grades</a>
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
        <h1><i class="fas fa-chalkboard-teacher"></i> Our Instructors</h1>
        <p>Browse and book sessions with our expert tutors</p>
    </div>
    
    <div class="filter-section">
        <select id="subjectFilter" class="filter-select" onchange="filterInstructors()">
            <option value="all">All Subjects</option>
            <?php foreach ($subjects as $subject): ?>
                <option value="<?php echo $subject['id']; ?>"><?php echo $subject['name']; ?></option>
            <?php endforeach; ?>
        </select>
        
        <select id="availabilityFilter" class="filter-select" onchange="filterInstructors()" style="margin-left: 10px;">
            <option value="all">All Tutors</option>
            <option value="available">Available Now</option>
            <option value="unavailable">Unavailable</option>
        </select>
    </div>
    
    <div class="instructors-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; margin-top: 25px;">
        <?php foreach ($tutors as $tutor): ?>
            <div class="instructor-card" data-tutor-id="<?php echo $tutor['id']; ?>" data-available="<?php echo $tutor['is_available']; ?>" data-subjects="<?php 
                $subj_ids = [];
                $stmt2 = $conn->prepare("SELECT subject_id FROM tutor_subjects WHERE tutor_id = ?");
                $stmt2->execute([$tutor['id']]);
                while ($row = $stmt2->fetch()) {
                    $subj_ids[] = $row['subject_id'];
                }
                echo implode(',', $subj_ids);
            ?>" style="background: rgba(255,255,255,0.95); border-radius: 20px; padding: 25px; position: relative; transition: all 0.3s;">
                
                <div class="instructor-status" style="position: absolute; top: 15px; right: 15px; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; background: <?php echo $tutor['is_available'] ? 'rgba(76,175,80,0.2)' : 'rgba(244,67,54,0.2)'; ?>; color: <?php echo $tutor['is_available'] ? 'var(--success-green)' : 'var(--danger-red)'; ?>; border: 1px solid <?php echo $tutor['is_available'] ? 'var(--success-green)' : 'var(--danger-red)'; ?>;">
                    <?php echo $tutor['is_available'] ? '● Available' : '○ Unavailable'; ?>
                </div>
                
                <div class="instructor-avatar" style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--primary-red), var(--primary-blue)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: bold; color: var(--primary-gold); margin: 0 auto 15px; border: 3px solid var(--primary-gold); overflow: hidden;">
                    <?php if ($tutor['profile_pic']): ?>
                        <img src="uploads/<?php echo $tutor['profile_pic']; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <?php echo substr($tutor['full_name'], 0, 1); ?>
                    <?php endif; ?>
                </div>
                
                <h3 style="text-align: center; margin-bottom: 10px;"><?php echo $tutor['full_name']; ?></h3>
                
                <div class="instructor-subjects" style="display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-bottom: 15px;">
                    <?php
                    $subj_names = explode(',', $tutor['subjects']);
                    foreach ($subj_names as $subj):
                        if (trim($subj)):
                    ?>
                        <span class="subject-tag" style="background: rgba(0,0,0,0.05); padding: 4px 12px; border-radius: 20px; font-size: 0.8rem;"><?php echo trim($subj); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 15px; padding: 15px 0; border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color);">
                    <div style="text-align: center;">
                        <div style="font-size: 0.7rem; color: var(--gray);">Rate</div>
                        <div style="font-weight: bold;">₱<?php echo number_format($tutor['hourly_rate'], 2); ?>/hr</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 0.7rem; color: var(--gray);">Rating</div>
                        <div style="font-weight: bold; color: var(--warning-yellow);"><?php echo $tutor['avg_rating'] ? number_format($tutor['avg_rating'], 1) . ' ⭐' : 'New'; ?></div>
                    </div>
                </div>
                
                <div class="instructor-bio" style="color: var(--gray); font-size: 0.9rem; text-align: center; margin-bottom: 15px;">
                    <?php echo $tutor['bio'] ?: 'Experienced tutor ready to help you succeed!'; ?>
                </div>
                
                <?php if ($tutor['is_available']): ?>
                    <button onclick="selectTutor(<?php echo $tutor['id']; ?>)" class="book-btn" style="display: block; width: 100%; padding: 12px; background: linear-gradient(135deg, var(--primary-red), var(--primary-blue)); color: white; border: none; border-radius: 10px; font-weight: 600; cursor: pointer;">Book Session →</button>
                <?php else: ?>
                    <button class="book-btn" disabled style="display: block; width: 100%; padding: 12px; background: #ccc; color: white; border: none; border-radius: 10px; font-weight: 600; cursor: not-allowed;">Currently Unavailable</button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Subject Selection Modal -->
<div id="subjectModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeSubjectModal()">&times;</span>
        <h2>Select Subject</h2>
        <p id="selectedTutorName"></p>
        <div id="tutorSubjectsList" style="margin: 20px 0;"></div>
        <div style="display: flex; gap: 10px;">
            <button onclick="proceedToBooking()" class="btn-login" id="proceedBtn" disabled>Proceed to Booking</button>
            <button onclick="closeSubjectModal()" class="btn-login" style="background: var(--gray);">Cancel</button>
        </div>
    </div>
</div>

<script>
let selectedTutorId = null;
let selectedSubjectId = null;

function filterInstructors() {
    const subjectFilter = document.getElementById('subjectFilter').value;
    const availabilityFilter = document.getElementById('availabilityFilter').value;
    const cards = document.querySelectorAll('.instructor-card');
    
    cards.forEach(card => {
        let showBySubject = true;
        let showByAvailability = true;
        
        if (subjectFilter !== 'all') {
            const subjects = card.dataset.subjects.split(',');
            showBySubject = subjects.includes(subjectFilter);
        }
        
        if (availabilityFilter !== 'all') {
            const isAvailable = card.dataset.available == '1';
            showByAvailability = availabilityFilter === 'available' ? isAvailable : !isAvailable;
        }
        
        card.style.display = (showBySubject && showByAvailability) ? 'block' : 'none';
    });
}

function selectTutor(tutorId) {
    selectedTutorId = tutorId;
    const tutorCard = document.querySelector(`[data-tutor-id="${tutorId}"]`);
    const tutorName = tutorCard.querySelector('h3').textContent;
    
    document.getElementById('selectedTutorName').innerHTML = `<strong>Tutor:</strong> ${tutorName}`;
    
    fetch(`get_tutor_subjects.php?tutor_id=${tutorId}`)
        .then(response => response.json())
        .then(data => {
            const subjectsList = document.getElementById('tutorSubjectsList');
            subjectsList.innerHTML = '';
            data.subjects.forEach(subject => {
                const div = document.createElement('div');
                div.className = 'subject-option';
                div.style.cssText = 'padding: 12px; margin-bottom: 8px; background: #f5f5f5; border-radius: 8px; cursor: pointer;';
                div.onclick = () => selectSubject(subject.id, div);
                div.innerHTML = `<input type="radio" name="subject" value="${subject.id}" style="margin-right: 10px;"> ${subject.name}`;
                subjectsList.appendChild(div);
            });
        });
    
    document.getElementById('subjectModal').style.display = 'block';
}

function selectSubject(subjectId, element) {
    selectedSubjectId = subjectId;
    document.querySelectorAll('.subject-option').forEach(opt => opt.style.background = '#f5f5f5');
    element.style.background = '#e0e0e0';
    document.getElementById('proceedBtn').disabled = false;
}

function proceedToBooking() {
    if (selectedTutorId && selectedSubjectId) {
        window.location.href = `book_session.php?tutor=${selectedTutorId}&subject=${selectedSubjectId}`;
    }
}

function closeSubjectModal() {
    document.getElementById('subjectModal').style.display = 'none';
    selectedTutorId = null;
    selectedSubjectId = null;
    document.getElementById('proceedBtn').disabled = true;
}
</script>

<?php include 'footer.php'; ?>