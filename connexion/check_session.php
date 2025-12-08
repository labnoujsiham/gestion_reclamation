<?php
// Include this file at the top of protected pages (dashboard.php, etc.)
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit();
}

// Optional: Check if session is still valid (timeout after 2 hours)
$timeout_duration = 7200; // 2 hours in seconds

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header('Location: connexion.php?timeout=1');
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();
?>