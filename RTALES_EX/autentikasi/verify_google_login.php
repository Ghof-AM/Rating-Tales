<?php
// autentikasi/verify_google_login.php
require_once '../includes/config.php'; // Include config.php for session, DB, and GOOGLE_CLIENT_ID

// Set response header to JSON
header('Content-Type: application/json');

// Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get the ID token from the POST data
$id_token = $_POST['credential'] ?? '';

if (empty($id_token)) {
    echo json_encode(['success' => false, 'message' => 'No Google credential received']);
    exit;
}

// --- Verify the ID token ---
// Use Google's tokeninfo endpoint for simple server-side validation
$url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . $id_token;
$response = file_get_contents($url);

if ($response === false) {
    error_log("Failed to fetch Google tokeninfo for token: " . substr($id_token, 0, 30) . '...');
    echo json_encode(['success' => false, 'message' => 'Failed to verify Google token with Google.']);
    exit;
}

$token_info = json_decode($response, true);

// Check if token verification was successful and valid for your app
if (!isset($token_info['aud']) || $token_info['aud'] !== GOOGLE_CLIENT_ID) {
     // Check if the audience matches your client ID
     error_log("Google token verification failed: Invalid audience. Received: " . ($token_info['aud'] ?? 'N/A') . ", Expected: " . GOOGLE_CLIENT_ID);
     echo json_encode(['success' => false, 'message' => 'Google token verification failed: Invalid audience.']);
     exit;
}

// Basic checks (issuer, expiry - although tokeninfo does this)
// 'iss' should be accounts.google.com or https://accounts.google.com
if (!isset($token_info['iss']) || !in_array($token_info['iss'], ['accounts.google.com', 'https://accounts.google.com'])) {
     error_log("Google token verification failed: Invalid issuer. Received: " . ($token_info['iss'] ?? 'N/A'));
     echo json_encode(['success' => false, 'message' => 'Google token verification failed: Invalid issuer.']);
     exit;
}

// Check expiry (tokeninfo usually handles this implicitly, but explicit check is safer)
if (!isset($token_info['exp']) || $token_info['exp'] < time()) {
     error_log("Google token verification failed: Token expired for Google ID: " . ($token_info['sub'] ?? 'N/A'));
     echo json_encode(['success' => false, 'message' => 'Google token verification failed: Token expired.']);
     exit;
}


// Extract user information from the verified token
$google_id = $token_info['sub'] ?? null; // 'sub' is the unique Google User ID
$email = $token_info['email'] ?? null;
$full_name = $token_info['name'] ?? null;
$username = $token_info['email'] ?? null; // Use email as a fallback username
$profile_image = $token_info['picture'] ?? null;

if (empty($google_id) || empty($email)) {
     error_log("Google token verification failed: Missing required data (Google ID or email)");
     echo json_encode(['success' => false, 'message' => 'Google token did not contain required user data.']);
     exit;
}


// --- Authenticate or Register the user in your database ---
try {
    // 1. Try to find the user by Google ID
    $user = getUserByGoogleId($google_id);

    if ($user) {
        // User found by Google ID - Log them in
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['google_login'] = true; // Optional marker

        // Regenerate session ID
        session_regenerate_id(true);

        // Redirect to intended URL or beranda
        $redirect_url = '../beranda/index.php';
        if (isset($_SESSION['intended_url'])) {
            $redirect_url = $_SESSION['intended_url'];
            unset($_SESSION['intended_url']);
        }

        echo json_encode(['success' => true, 'redirect' => $redirect_url]);
        exit;

    } else {
        // User not found by Google ID - Try to find by email
        $user_by_email = getUserByEmail($email);

        if ($user_by_email) {
            // User found by email - Link Google ID to this existing account
            // This might indicate they previously registered with email/password
            // or logged in with Google before the google_id column was added.
            // If they logged in before the google_id column, updating ensures the link.
            // If they have a local account with the same email, you might want
            // a more complex flow (e.g., prompt them to link accounts, require password),
            // but for simplicity, we'll link it automatically assuming email ownership implies account ownership.

            // Check if the existing account already has a google_id (shouldn't happen if we got here)
            if (!empty($user_by_email['google_id']) && $user_by_email['google_id'] !== $google_id) {
                 // This scenario is complex: two different Google accounts linked to the same email OR an error.
                 // For now, prevent linking if an ID already exists.
                 error_log("Attempted to link Google ID {$google_id} to user {$user_by_email['user_id']} with email {$email}, but user already has Google ID {$user_by_email['google_id']}.");
                 echo json_encode(['success' => false, 'message' => 'An account with this email is already linked to a different Google account.']);
                 exit;
            }

            // Update existing user with Google ID and potentially profile image
            $update_data = ['google_id' => $google_id];
             if (!empty($profile_image) && empty($user_by_email['profile_image'])) {
                 // Only update profile image if the user doesn't have one already
                 $update_data['profile_image'] = $profile_image;
             }
            updateUser($user_by_email['user_id'], $update_data);

            // Log in the existing user
            $_SESSION['user_id'] = $user_by_email['user_id'];
            $_SESSION['google_login'] = true;

            session_regenerate_id(true);

            $redirect_url = '../beranda/index.php';
            if (isset($_SESSION['intended_url'])) {
                $redirect_url = $_SESSION['intended_url'];
                unset($_SESSION['intended_url']);
            }

            echo json_encode(['success' => true, 'redirect' => $redirect_url]);
            exit;

        } else {
            // User not found by Google ID or email - Register a new account
            // Generate a unique username if email isn't suitable or if you need separate usernames
            // A simple approach: use email prefix, add numbers if it conflicts
            $base_username = explode('@', $email)[0];
            $new_username = $base_username;
            $i = 1;
            // Use the isUsernameOrEmailExists function to check username uniqueness
            while (isUsernameOrEmailExists($new_username, '')) { // Check only username part
                 $new_username = $base_username . $i;
                 $i++;
            }

            // Create the new user account with Google info
            $inserted = createUser(
                $full_name ?? $new_username, // Use full_name if available, else generated username
                $new_username,
                $email,
                null, // No local password for Google users
                null, // Age can be added later
                null, // Gender can be added later
                $google_id,
                $profile_image
            );

            if ($inserted) {
                $newUserId = $pdo->lastInsertId();
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['google_login'] = true;

                session_regenerate_id(true);

                 $_SESSION['success_message'] = 'Google registration successful!';

                $redirect_url = '../beranda/index.php';
                if (isset($_SESSION['intended_url'])) {
                    $redirect_url = $_SESSION['intended_url'];
                    unset($_SESSION['intended_url']);
                }

                echo json_encode(['success' => true, 'redirect' => $redirect_url]);
                exit;
            } else {
                error_log("Database error: Failed to create new user for Google ID: {$google_id}");
                echo json_encode(['success' => false, 'message' => 'Failed to create new user account in database.']);
                exit;
            }
        }

    }

} catch (PDOException $e) {
    error_log("Database error during Google login/registration: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An internal database error occurred.']);
    exit;
}
?>