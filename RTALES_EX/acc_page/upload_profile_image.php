<?php
// acc_page/upload_profile_image.php
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
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']; // Added webp
$max_size = 5 * 1024 * 1024; // 5MB

$file = $_FILES['profile_image'];

// Validate file type
if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, WEBP are allowed.']);
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
    mkdir($upload_dir, 0777, true); // Use 0777 temporarily for debugging permissions, consider 0755 or 0775 in production
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
// Use a combination of user ID and unique ID for filename clarity
$filename = 'profile_' . $userId . '_' . uniqid() . '.' . $extension;
$filepath = $upload_dir . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Update user profile in database
    $web_path = '../uploads/profile_images/' . $filename;
    try {
        // Fetch old profile image path to delete the old file
        $old_user = getUserById($userId); // Use getUserById from database.php
        $old_profile_image = $old_user['profile_image'] ?? null;

        if (updateUser($userId, ['profile_image' => $web_path])) {
             // Delete old file if it exists and is not the default/placeholder
            if (!empty($old_profile_image) && strpos($old_profile_image, 'ui-avatars.com') === false) {
                $old_file_path = __DIR__ . '/../' . $old_profile_image; // Construct absolute path
                if (file_exists($old_file_path)) {
                    @unlink($old_file_path); // Use @ to suppress errors if file is not found/deletable
                }
            }

            echo json_encode([
                'success' => true,
                'image_url' => htmlspecialchars($web_path), // Return HTML escaped URL
                'message' => 'Profile image updated successfully.'
            ]);
        } else {
            // If DB update fails, clean up the uploaded file
            @unlink($filepath);
            echo json_encode(['success' => false, 'message' => 'Failed to update database record for profile image.']);
        }
    } catch (PDOException $e) {
        // If a DB error occurs, clean up the uploaded file
        @unlink($filepath);
        error_log("Database error during profile image update: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred while updating profile image.']);
    }
} else {
    // Handle specific upload errors
    $uploadError = 'Unknown upload error.';
    switch ($_FILES['profile_image']['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $uploadError = 'File exceeds maximum allowed size.';
            break;
        case UPLOAD_ERR_PARTIAL:
            $uploadError = 'File was only partially uploaded.';
            break;
        case UPLOAD_ERR_NO_FILE:
            $uploadError = 'No file was uploaded.';
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $uploadError = 'Missing a temporary folder.';
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $uploadError = 'Failed to write file to disk.';
            break;
        case UPLOAD_ERR_EXTENSION:
            $uploadError = 'A PHP extension stopped the file upload.';
            break;
    }
    error_log("Profile image move upload failed: " . $uploadError . " - " . $file['name']);
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file: ' . $uploadError]);
}
?>