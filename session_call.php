<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');

$user    = getUserById($_SESSION['user_id']);
$booking_id = sanitizeInt($_GET['booking_id'] ?? 0);
$conn    = getConnection();

// Verify user is part of this booking (paid or approved)
$stmt = $conn->prepare("
    SELECT b.*, s.name as subject_name,
           ut.full_name as tutor_name,
           us.full_name as student_name
    FROM bookings b
    JOIN subjects s ON b.subject_id = s.id
    JOIN users ut   ON b.tutor_id   = ut.id
    JOIN users us   ON b.student_id = us.id
    WHERE b.id = ?
      AND (b.student_id = ? OR b.tutor_id = ?)
      AND b.status IN ('approved','completed')
      AND b.payment_status = 'paid'
");
$stmt->execute([$booking_id, $user['id'], $user['id']]);
$booking = $stmt->fetch();

if (!$booking) {
    setFlash("Session not found. Make sure the booking is approved and paid.", 'danger');
    redirect('my_bookings.php');
}

// Generate a unique, deterministic room name from booking ID
// Using a hash so the room name isn't guessable from just the ID
$room_name = 'bsit-session-' . hash('sha256', 'bsit_tutoring_' . $booking_id . '_secret');
$room_name = substr($room_name, 0, 32); // Jitsi room names should be reasonably short

$page_title = 'Video Session — ' . $booking['subject_name'];
$is_tutor   = $user['id'] == $booking['tutor_id'];
$other_name = $is_tutor ? $booking['student_name'] : $booking['tutor_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($page_title); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src='https://meet.jit.si/external_api.js'></script>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; font-family: 'Inter', sans-serif; background: #0f1117; color: #e2e8f0; overflow: hidden; }

/* ── Top bar ── */
.call-topbar {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: 56px;
    background: rgba(255,255,255,0.97);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    z-index: 100;
    border-bottom: 1px solid #e5e7eb;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}
.call-info { display: flex; align-items: center; gap: 14px; }
.call-info img { width: 32px; height: 32px; object-fit: contain; }
.call-subject { font-size: .9rem; font-weight: 700; color: #111; }
.call-with    { font-size: .75rem; color: #6b7280; }
.call-status  { display: flex; align-items: center; gap: 8px; font-size: .78rem; }
.status-dot   { width: 8px; height: 8px; border-radius: 50%; background: #10b981; animation: pulse 1.5s infinite; }
@keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:.4;} }
.call-actions { display: flex; gap: 10px; }
.btn-end {
    padding: 8px 18px;
    background: #ef4444;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: .82rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: background .2s;
}
.btn-end:hover { background: #dc2626; }
.btn-back {
    padding: 8px 16px;
    background: transparent;
    color: #374151;
    border: 1.5px solid #e5e7eb;
    border-radius: 6px;
    font-size: .82rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all .2s;
}
.btn-back:hover { border-color: #111; color: #111; }

/* ── Video container ── */
#jitsi-container {
    position: fixed;
    top: 56px; left: 0; right: 0; bottom: 0;
}

/* ── Loading overlay ── */
#loading {
    position: fixed;
    inset: 0;
    top: 56px;
    background: #0f1117;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 20px;
    z-index: 50;
}
.spinner {
    width: 48px; height: 48px;
    border: 4px solid rgba(255,255,255,.1);
    border-top-color: #dc2626;
    border-radius: 50%;
    animation: spin .8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
#loading p { font-size: .9rem; color: #9ca3af; }

/* ── Session info panel ── */
.session-info-bar {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    background: rgba(15,17,23,0.9);
    backdrop-filter: blur(8px);
    padding: 10px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    z-index: 100;
    border-top: 1px solid rgba(255,255,255,.08);
    font-size: .78rem;
    color: #9ca3af;
}
.session-info-bar span { display: flex; align-items: center; gap: 6px; }
.session-info-bar i { color: #dc2626; }
#timer { font-weight: 700; color: #e2e8f0; font-size: .85rem; }
</style>
</head>
<body>

<!-- Top bar -->
<div class="call-topbar">
    <div class="call-info">
        <img src="images/scclogo.png" alt="SCC">
        <div>
            <div class="call-subject"><?php echo htmlspecialchars($booking['subject_name']); ?></div>
            <div class="call-with">with <?php echo htmlspecialchars($other_name); ?></div>
        </div>
    </div>
    <div class="call-status">
        <div class="status-dot"></div>
        <span>Live Session</span>
    </div>
    <div class="call-actions">
        <a href="my_bookings.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
        <button class="btn-end" onclick="endCall()"><i class="fas fa-phone-slash"></i> End Call</button>
    </div>
</div>

<!-- Loading -->
<div id="loading">
    <div class="spinner"></div>
    <p>Connecting to session room...</p>
</div>

<!-- Jitsi container -->
<div id="jitsi-container"></div>

<!-- Bottom info bar -->
<div class="session-info-bar">
    <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></span>
    <span><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($booking['start_time'])); ?> – <?php echo date('h:i A', strtotime($booking['end_time'])); ?></span>
    <span><i class="fas fa-stopwatch"></i> Duration: <span id="timer">00:00</span></span>
    <span><i class="fas fa-shield-alt"></i> Encrypted · Powered by Jitsi</span>
</div>

<script>
const roomName  = '<?php echo $room_name; ?>';
const userName  = '<?php echo addslashes(htmlspecialchars($user['full_name'])); ?>';
const isHost    = <?php echo $is_tutor ? 'true' : 'false'; ?>;

// Init Jitsi
const api = new JitsiMeetExternalAPI('meet.jit.si', {
    roomName: roomName,
    width: '100%',
    height: '100%',
    parentNode: document.getElementById('jitsi-container'),
    userInfo: { displayName: userName },
    configOverwrite: {
        startWithAudioMuted: false,
        startWithVideoMuted: false,
        enableWelcomePage: false,
        prejoinPageEnabled: false,
        disableDeepLinking: true,
        toolbarButtons: [
            'microphone','camera','closedcaptions','desktop',
            'fullscreen','fodeviceselection','hangup','chat',
            'raisehand','videoquality','filmstrip','tileview','help'
        ],
    },
    interfaceConfigOverwrite: {
        SHOW_JITSI_WATERMARK: false,
        SHOW_WATERMARK_FOR_GUESTS: false,
        SHOW_BRAND_WATERMARK: false,
        BRAND_WATERMARK_LINK: '',
        SHOW_POWERED_BY: false,
        TOOLBAR_ALWAYS_VISIBLE: false,
        DEFAULT_BACKGROUND: '#0f1117',
        MOBILE_APP_PROMO: false,
    }
});

// Hide loading once ready
api.addEventListener('videoConferenceJoined', () => {
    document.getElementById('loading').style.display = 'none';
});

// Session timer
let seconds = 0;
const timerEl = document.getElementById('timer');
setInterval(() => {
    seconds++;
    const m = String(Math.floor(seconds / 60)).padStart(2, '0');
    const s = String(seconds % 60).padStart(2, '0');
    timerEl.textContent = m + ':' + s;
}, 1000);

function endCall() {
    if (confirm('End the video call?')) {
        api.executeCommand('hangup');
        setTimeout(() => { window.location.href = 'my_bookings.php'; }, 1000);
    }
}

// Auto-redirect when remote participant leaves (for students)
api.addEventListener('participantLeft', () => {
    if (!isHost) {
        setTimeout(() => {
            if (confirm('The tutor has left the session. Return to bookings?')) {
                window.location.href = 'my_bookings.php';
            }
        }, 2000);
    }
});
</script>
</body>
</html>
