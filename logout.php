<?php
// logout.php - This file is on GitHub
require_once('/home/gmpsvasy/public_html/config.php');

// Destroy the session
session_destroy();

// Clear session variables
$_SESSION = [];

// Delete session cookie if it exists
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect to login page using the loader
header('Location: loader.php?page=login');
exit;
?>
