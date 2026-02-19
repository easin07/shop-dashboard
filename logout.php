<?php

require_once('/home/gmpsvasy/public_html/config.php');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destroy the session
session_destroy();

// Clear session variables
$_SESSION = [];

// Delete session cookie if it exists
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect to login page (index.php)
header('Location: index.php');
exit;
?>
