<?php
// logout.php
require_once '../includes/config.php'; // Include config.php

// Ensure session is active (should be handled by config.php)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Unset specific user-related session variables
unset($_SESSION['user_id']);
unset($_SESSION['google_login']); // Unset google login marker

// Optionally clear all session variables (might affect other non-user session data)
// $_SESSION = array();

// Delete the session cookie (if one exists)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to the login page
header('Location: form-login.php');
exit;
?>