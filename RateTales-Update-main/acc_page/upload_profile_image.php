<?php
require_once '../includes/config.php';

// Redirect if not authenticated
redirectIfNotAuthenticated();

// Get authenticated user ID
$userId = $_SESSION['user_id'];

// Set response header to JSON
header('Content-Type: application/json');

// Check if file was uploaded
if (!isset($_FILES['profile_image'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

// Define allowed file types and max file size
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
$max_size = 5 * 1024 * 1024; // 5MB

$file = $_FILES['profile_image'];

// Validate file type
if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG and GIF are allowed.']);
    exit;
}

// Validate file size
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 5MB.']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = __DIR__ . '/../uploads/profile_images/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('profile_') . '.' . $extension;
$filepath = $upload_dir . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Update user profile in database
    $web_path = '../uploads/profile_images/' . $filename;
    try {
        $stmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE user_id = ?");
        if ($stmt->execute([$web_path, $userId])) {
            echo json_encode([
                'success' => true,
                'image_url' => $web_path
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update database']);
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
}