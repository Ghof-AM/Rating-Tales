<?php
// includes/auth_helper.php

// Pastikan sesi dimulai (seharusnya sudah di config.php, tapi cek lagi)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Fungsi untuk mengecek apakah user sudah login
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Fungsi untuk mengarahkan ke halaman login jika belum login
function require_login() {
    if (!is_logged_in()) {
        // Simpan URL halaman yang diminta di session untuk redirect kembali setelah login
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        // --- PASTIKAN PATH INI BENAR SESUAI STRUKTUR WEB SERVER ANDA ---
        // Jika proyek diakses di http://localhost/R-TALES_EX-C1/, path ini sudah benar.
        // Jika proyek diakses langsung di http://localhost/, path-nya mungkin hanya '/autentikasi/form-login.php'.
        header('Location: /R-TALES_EX-C1/autentikasi/form-login.php');
        exit;
    }
}

// Fungsi untuk mendapatkan ID user yang sedang login
function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

// Fungsi untuk mendapatkan data user yang sedang login
function get_logged_in_user_data() {
    if (is_logged_in()) {
        // Include database functions to fetch user data
        // Path ini relatif dari includes/ ke config/
        require_once __DIR__ . '/../config/database.php';
        return getUserById(get_user_id());
    }
    return null;
}

?>