<?php
require_once 'config.php';

if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'logout', 'User logged out');
}

logout();
redirect('login.php');