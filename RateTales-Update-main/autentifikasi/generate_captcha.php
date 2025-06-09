<?php
// generate_captcha.php
// File ini hanya bertugas menghasilkan kode CAPTCHA baru dan menyimpannya di session

// Include file konfigurasi untuk koneksi DB, session, dan helper function
require_once '../includes/config.php';

// Ensure session is active (should be handled by config.php)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Generate a new CAPTCHA code using the helper function from config.php
$newCaptchaCode = generateRandomString(6);

// Store the new code in the session, overwriting the old one
$_SESSION['captcha_code'] = $newCaptchaCode;

// Set header to tell the client this is plain text
header('Content-Type: text/plain');

// Output the new CAPTCHA code to the client
echo $newCaptchaCode;

// Stop script execution
exit;
?>