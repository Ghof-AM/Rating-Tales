<?php
// includes/config.php

// Start a session if one hasn't been started already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set timezone (optional but recommended)
date_default_timezone_set('Asia/Jakarta'); // Example: Jakarta timezone

// Define site root path (helps with absolute paths if needed, currently using relative)
// define('SITE_ROOT', '/R-TALES_EX-C1'); // Adjust if your project is not in a subfolder

// Include the database connection and functions file
require_once __DIR__ . '/../config/database.php'; // Use __DIR__ for reliable path

// Configure error reporting for debugging (Disable on production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set up a basic error log (make sure the 'logs' directory exists and is writable)
// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/../logs/php-error.log');

// --- Google Sign-In Configuration ---
// You need to get your Google Client ID from Google Cloud Console
// Instructions: https://developers.google.com/identity/gsi/web/guides/get-started
define('GOOGLE_CLIENT_ID', '151997318112-fbtg6t6tp0e51pjis40qim4ls2pljjem.apps.googleusercontent.com'); // <-- REPLACE THIS WITH YOUR ACTUAL CLIENT ID

// Helper function to generate random string for CAPTCHA
function generateRandomString($length = 6) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

// Function to check if a user is authenticated
function isAuthenticated() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

// Function to redirect to login if not authenticated
function redirectIfNotAuthenticated() {
    if (!isAuthenticated()) {
        // Store the intended URL before redirecting
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
        header('Location: ../autentikasi/form-login.php');
        exit;
    }
}

// Function to fetch authenticated user details
// Calls getUserById from database.php
function getAuthenticatedUser() {
    if (isAuthenticated()) {
        $userId = $_SESSION['user_id'];
        // Fetch user details from the database
        $user = getUserById($userId);
        if ($user) {
            // User found, return details
            return $user;
        } else {
            // User not found in DB (maybe deleted?), clear session and redirect
            // Also unset Google login session marker if it exists
            unset($_SESSION['user_id']);
            unset($_SESSION['google_login']);
            session_regenerate_id(true); // Regenerate ID after clearing session
            header('Location: ../autentikasi/form-login.php'); // Redirect to login
            exit;
        }
    }
    return null; // Return null if not authenticated
}

?>