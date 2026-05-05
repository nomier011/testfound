<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_user = null;
if (isset($_SESSION['user_id'])) {
    $current_user = getUserById($_SESSION['user_id']);
}

$page_title = $page_title ?? SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="theme-color" content="#dc2626">
    <meta name="description" content="BSIT Tutoring System - A comprehensive peer tutoring platform for BSIT students">
    <meta name="author" content="BSIT Tutoring System">
    <meta name="robots" content="noindex, nofollow">
    
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="images/favicon.png">
    <link rel="apple-touch-icon" href="images/apple-touch-icon.png">
</head>
<body>
    <!-- Background Layers -->
    <div class="bg-layer bg-layer-1"></div>
    <div class="bg-layer bg-layer-2"></div>
    <div class="bg-layer bg-layer-3"></div>
    
    <!-- Toast Notification Container -->
    <div id="toast-container" class="toast-container"></div>
    
    <script>
    // Toast notification system
    const toastContainer = document.getElementById('toast-container');
    
    function showToast(message, type = 'success', duration = 5000) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        
        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas ${icons[type] || icons.success}"></i>
            </div>
            <div class="toast-content">
                <div class="toast-message">${escapeHtml(message)}</div>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        toastContainer.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Auto-hide flash messages
    <?php $flash = getFlash(); if ($flash): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showToast('<?php echo addslashes($flash['message']); ?>', '<?php echo $flash['type']; ?>');
    });
    <?php endif; ?>
    
    // Toast styles
    const toastStyles = document.createElement('style');
    toastStyles.textContent = `
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .toast {
            display: flex;
            align-items: center;
            gap: 12px;
            background: white;
            border-radius: 12px;
            padding: 14px 18px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
            animation: toastSlideIn 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            border-left: 4px solid;
            min-width: 300px;
            max-width: 450px;
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.98);
        }
        .toast-success { border-left-color: #10b981; }
        .toast-error { border-left-color: #ef4444; }
        .toast-warning { border-left-color: #f59e0b; }
        .toast-info { border-left-color: #3b82f6; }
        .toast-icon { font-size: 1.25rem; }
        .toast-success .toast-icon { color: #10b981; }
        .toast-error .toast-icon { color: #ef4444; }
        .toast-warning .toast-icon { color: #f59e0b; }
        .toast-info .toast-icon { color: #3b82f6; }
        .toast-content { flex: 1; }
        .toast-message { font-size: 0.875rem; color: #1f2937; line-height: 1.4; }
        .toast-close {
            background: none;
            border: none;
            cursor: pointer;
            color: #9ca3af;
            font-size: 0.875rem;
            transition: color 0.2s;
            padding: 4px;
        }
        .toast-close:hover { color: #4b5563; }
        @keyframes toastSlideIn {
            from { opacity: 0; transform: translateX(100%); }
            to { opacity: 1; transform: translateX(0); }
        }
        @media (max-width: 640px) {
            .toast-container { top: 10px; right: 10px; left: 10px; }
            .toast { min-width: auto; max-width: none; width: calc(100% - 20px); }
        }
    `;
    document.head.appendChild(toastStyles);
    </script>