<?php
require_once 'config.php';
$page_title = 'Book a Tutor';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getUserById($_SESSION['user_id']);
if ($user['role'] != 'student') {
    redirect('dashboard.php');
}

$conn = getConnection();
$error = null;

// Get all subjects
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tutor_id = $_POST['tutor_id'] ?? 0;
    $subject_id = $_POST['subject_id'] ?? 0;
    $booking_date = $_POST['booking_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $duration = $_POST['duration'] ?? 1;
    $notes = $_POST['notes'] ?? '';
    
    // Get tutor details
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$tutor_id]);
    $tutor = $stmt->fetch();
    
    if (!$tutor) {
        $error = "Tutor not found";
    } else {
        $end_time = date('H:i:s', strtotime($start_time) + ($duration * 3600));
        $amount = $tutor['hourly_rate'] * $duration;
        
        // Create booking with status 'pending' (waiting for tutor approval)
        $stmt = $conn->prepare("INSERT INTO bookings (student_id, tutor_id, subject_id, booking_date, start_time, end_time, duration, amount, notes, status, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')");
        $stmt->execute([$user['id'], $tutor_id, $subject_id, $booking_date, $start_time, $end_time, $duration, $amount, $notes]);
        $booking_id = $conn->lastInsertId();
        
        // Create notification for tutor
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)");
        $stmt->execute([$tutor_id, "New booking request from {$user['full_name']} for {$booking_date}", 'booking_request']);
        
        setFlash("Booking request sent! Please wait for tutor approval.", 'success');
        redirect('my_bookings.php');
    }
}
?>

<?php include 'header.php'; ?>

<div class="dashboard-container">
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="images/scclogo.png" alt="SCC Logo">
            <h3>Student Menu</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="student_dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="book_session.php" class="sidebar-link active"><i class="fas fa-calendar-plus"></i> Book a Tutor</a>
            <a href="my_bookings.php" class="sidebar-link"><i class="fas fa-list-alt"></i> My Bookings</a>
            <a href="profile.php" class="sidebar-link"><i class="fas fa-user-circle"></i> Profile</a>
            <a href="logout.php" class="sidebar-link logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
                    <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                    <div class="user-role">Student</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="welcome-banner">
            <h1><i class="fas fa-calendar-plus"></i> Book a Tutor</h1>
            <p>Select a subject, then choose your preferred tutor</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="booking-steps">
            <div class="step active" id="step1Indicator">
                <div class="step-number">1</div>
                <div class="step-label">Choose Subject</div>
            </div>
            <div class="step" id="step2Indicator">
                <div class="step-number">2</div>
                <div class="step-label">Select Tutor</div>
            </div>
            <div class="step" id="step3Indicator">
                <div class="step-number">3</div>
                <div class="step-label">Schedule</div>
            </div>
        </div>
        
        <!-- Step 1: Subject Selection -->
        <div id="step1" class="step-content active">
            <div class="content-card">
                <h3>Select a Subject</h3>
                <div class="subjects-grid">
                    <?php foreach ($subjects as $subject): ?>
                        <div class="subject-card" onclick="selectSubject(<?php echo $subject['id']; ?>, '<?php echo htmlspecialchars($subject['name']); ?>')">
                            <i class="fas fa-book"></i>
                            <h4><?php echo htmlspecialchars($subject['name']); ?></h4>
                            <p><?php echo htmlspecialchars($subject['description']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Step 2: Tutor Selection -->
        <div id="step2" class="step-content" style="display: none;">
            <div class="content-card">
                <h3>Select a Tutor for <span id="selectedSubjectName"></span></h3>
                <div id="tutorsList" class="tutors-grid">
                    <div class="loading">Loading tutors...</div>
                </div>
                <div class="navigation-buttons">
                    <button type="button" class="btn-secondary" onclick="goToStep(1)">← Back to Subjects</button>
                </div>
            </div>
        </div>
        
        <!-- Step 3: Schedule -->
        <div id="step3" class="step-content" style="display: none;">
            <div class="content-card">
                <h3>Schedule Your Session</h3>
                <div id="selectedTutorInfo" class="selected-tutor-info"></div>
                
                <form method="POST" action="" id="bookingForm">
                    <input type="hidden" name="tutor_id" id="selectedTutorId">
                    <input type="hidden" name="subject_id" id="selectedSubjectId">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Booking Date</label>
                            <input type="date" name="booking_date" id="booking_date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Start Time</label>
                            <input type="time" name="start_time" id="start_time" min="08:00" max="20:00" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Duration (hours)</label>
                        <select name="duration" id="duration">
                            <option value="1">1 hour</option>
                            <option value="1.5">1.5 hours</option>
                            <option value="2">2 hours</option>
                            <option value="2.5">2.5 hours</option>
                            <option value="3">3 hours</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Additional Notes</label>
                        <textarea name="notes" rows="3" placeholder="Any specific topics or questions..."></textarea>
                    </div>
                    
                    <div class="info-message" style="margin-top: 15px;">
                        <i class="fas fa-info-circle"></i> After booking, the tutor needs to approve your request. You will be notified once approved.
                    </div>
                    
                    <div class="navigation-buttons">
                        <button type="button" class="btn-secondary" onclick="goToStep(2)">← Back to Tutors</button>
                        <button type="submit" class="btn-primary">Send Booking Request →</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let selectedSubjectId = null;
let selectedSubjectName = null;
let selectedTutorId = null;

function selectSubject(subjectId, subjectName) {
    selectedSubjectId = subjectId;
    selectedSubjectName = subjectName;
    document.getElementById('selectedSubjectId').value = subjectId;
    document.getElementById('selectedSubjectName').textContent = subjectName;
    
    document.querySelectorAll('.subject-card').forEach(card => {
        card.classList.remove('selected');
    });
    event.currentTarget.classList.add('selected');
    
    loadTutors(subjectId);
    goToStep(2);
}

function loadTutors(subjectId) {
    const tutorsList = document.getElementById('tutorsList');
    tutorsList.innerHTML = '<div class="loading">Loading tutors...</div>';
    
    fetch(`get_tutors_by_subject.php?subject_id=${subjectId}`)
        .then(response => response.json())
        .then(data => {
            tutorsList.innerHTML = '';
            
            if (data.tutors.length === 0) {
                tutorsList.innerHTML = '<div class="no-tutors">No tutors available for this subject at the moment.</div>';
                return;
            }
            
            data.tutors.forEach(tutor => {
                const tutorCard = document.createElement('div');
                tutorCard.className = 'tutor-card';
                tutorCard.onclick = () => selectTutor(tutor.id, tutor.full_name, tutor.hourly_rate, tutor.expertise, tutor.bio, tutor.profile_pic, tutor.avg_rating);
                
                let starsHtml = '';
                const avgRating = tutor.avg_rating || 0;
                for (let i = 1; i <= 5; i++) {
                    if (i <= Math.round(avgRating)) {
                        starsHtml += '<i class="fas fa-star"></i>';
                    } else {
                        starsHtml += '<i class="far fa-star"></i>';
                    }
                }
                
                tutorCard.innerHTML = `
                    <div class="tutor-avatar">
                        ${tutor.profile_pic ? `<img src="uploads/${tutor.profile_pic}">` : `<span>${tutor.full_name.charAt(0)}</span>`}
                    </div>
                    <div class="tutor-info">
                        <h4>${tutor.full_name}</h4>
                        <div class="tutor-rating">
                            ${starsHtml}
                            <span>(${tutor.total_ratings || 0} reviews)</span>
                        </div>
                        <div class="tutor-expertise">${tutor.expertise || 'General Tutor'}</div>
                    </div>
                    <div class="tutor-rate">
                        ₱${parseFloat(tutor.hourly_rate).toLocaleString()}<span>/hr</span>
                    </div>
                    <div class="tutor-select">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                `;
                tutorsList.appendChild(tutorCard);
            });
        })
        .catch(error => {
            tutorsList.innerHTML = '<div class="error">Error loading tutors. Please try again.</div>';
        });
}

function selectTutor(id, name, rate, expertise, bio, profilePic, avgRating) {
    selectedTutorId = id;
    document.getElementById('selectedTutorId').value = id;
    
    document.querySelectorAll('.tutor-card').forEach(card => {
        card.classList.remove('selected');
    });
    event.currentTarget.classList.add('selected');
    
    let profilePicHtml = '';
    if (profilePic) {
        profilePicHtml = `<img src="uploads/${profilePic}" alt="${name}">`;
    } else {
        profilePicHtml = `<span>${name.charAt(0)}</span>`;
    }
    
    let starsHtml = '';
    for (let i = 1; i <= 5; i++) {
        if (i <= Math.round(avgRating || 0)) {
            starsHtml += '<i class="fas fa-star"></i>';
        } else {
            starsHtml += '<i class="far fa-star"></i>';
        }
    }
    
    document.getElementById('selectedTutorInfo').innerHTML = `
        <div class="selected-tutor">
            <div class="selected-tutor-avatar">
                ${profilePicHtml}
            </div>
            <div class="selected-tutor-details">
                <h4>${name}</h4>
                <div class="tutor-rating">${starsHtml} (${avgRating || 0} reviews)</div>
                <div class="tutor-expertise"><strong>Expertise:</strong> ${expertise || 'General Tutor'}</div>
                <div class="tutor-rate"><strong>Rate:</strong> ₱${parseFloat(rate).toLocaleString()}/hour</div>
            </div>
        </div>
    `;
    
    goToStep(3);
}

function goToStep(step) {
    document.getElementById('step1Indicator').classList.remove('active');
    document.getElementById('step2Indicator').classList.remove('active');
    document.getElementById('step3Indicator').classList.remove('active');
    
    if (step === 1) document.getElementById('step1Indicator').classList.add('active');
    else if (step === 2) document.getElementById('step2Indicator').classList.add('active');
    else if (step === 3) document.getElementById('step3Indicator').classList.add('active');
    
    document.getElementById('step1').style.display = step === 1 ? 'block' : 'none';
    document.getElementById('step2').style.display = step === 2 ? 'block' : 'none';
    document.getElementById('step3').style.display = step === 3 ? 'block' : 'none';
}

document.getElementById('booking_date').min = new Date().toISOString().split('T')[0];
</script>

<style>
.booking-steps {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    background: rgba(255,255,255,0.88);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    border: 1px solid rgba(255,255,255,0.3);
}
.step {
    flex: 1;
    text-align: center;
    position: relative;
}
.step:not(:last-child):after {
    content: '';
    position: absolute;
    top: 15px;
    right: -10px;
    width: 20px;
    height: 2px;
    background: #ddd;
}
.step.active .step-number {
    background: var(--primary-red);
    color: white;
    border-color: var(--primary-red);
}
.step.active .step-label {
    color: var(--primary-red);
    font-weight: bold;
}
.step-number {
    width: 30px;
    height: 30px;
    background: white;
    border: 2px solid #ddd;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-bottom: 5px;
}
.step-label {
    font-size: 0.8rem;
    color: #666;
}
.subjects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 20px;
}
.subject-card {
    background: white;
    border: 2px solid #eee;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}
.subject-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}
.subject-card.selected {
    border-color: var(--primary-red);
    background: rgba(139,0,0,0.05);
}
.subject-card i {
    font-size: 2rem;
    color: var(--primary-red);
    margin-bottom: 10px;
}
.tutors-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
    max-height: 500px;
    overflow-y: auto;
}
.tutor-card {
    display: flex;
    align-items: center;
    gap: 15px;
    background: white;
    border: 2px solid #eee;
    border-radius: 15px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.3s;
}
.tutor-card:hover {
    transform: translateX(5px);
    border-color: var(--primary-red);
}
.tutor-card.selected {
    border-color: var(--primary-red);
    background: rgba(139,0,0,0.05);
}
.tutor-avatar {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--primary-red), var(--primary-blue));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary-gold);
    border: 2px solid var(--primary-gold);
    overflow: hidden;
    flex-shrink: 0;
}
.tutor-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.tutor-info {
    flex: 1;
}
.tutor-info h4 {
    margin-bottom: 5px;
}
.tutor-rating {
    color: #FFC107;
    font-size: 0.8rem;
}
.tutor-expertise {
    font-size: 0.75rem;
    color: #666;
}
.tutor-rate {
    font-weight: bold;
    color: var(--success-green);
}
.selected-tutor-info {
    background: #f9f9f9;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
}
.selected-tutor {
    display: flex;
    gap: 20px;
}
.selected-tutor-avatar {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--primary-red), var(--primary-blue));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: bold;
    color: var(--primary-gold);
    border: 2px solid var(--primary-gold);
    overflow: hidden;
    flex-shrink: 0;
}
.selected-tutor-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.selected-tutor-details {
    flex: 1;
}
.info-message {
    background: #d1ecf1;
    color: #0c5460;
    padding: 12px;
    border-radius: 8px;
    border-left: 4px solid #17a2b8;
}
.navigation-buttons {
    display: flex;
    justify-content: space-between;
    margin-top: 25px;
    gap: 15px;
}
.btn-secondary {
    background: #666;
    color: white;
    padding: 12px 25px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
}
@media (max-width: 768px) {
    .booking-steps {
        flex-wrap: wrap;
    }
    .tutors-grid {
        grid-template-columns: 1fr;
    }
    .subjects-grid {
        grid-template-columns: 1fr;
    }
    .selected-tutor {
        flex-direction: column;
        text-align: center;
    }
    .selected-tutor-avatar {
        margin: 0 auto;
    }
    .navigation-buttons {
        flex-direction: column;
    }
}
</style>

<?php include 'footer.php'; ?>