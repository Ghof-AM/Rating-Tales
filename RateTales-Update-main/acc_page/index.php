<?php
require_once '../includes/config.php'; // Include config.php
require_once '../config/auth_helper.php'; // Include auth_helper.php

// Redirect if not authenticated
redirectIfNotAuthenticated();

// Get authenticated user ID
$userId = $_SESSION['user_id'];

// Fetch authenticated user details from the database
$user = getAuthenticatedUser();

// Handle user not found after authentication check (shouldn't happen if getAuthenticatedUser works)
if (!$user) {
    $_SESSION['error_message'] = 'User profile could not be loaded.';
    header('Location: ../autentifikasi/logout.php'); // Force logout if user somehow invalidates
    exit;
}

// Fetch movies uploaded by the current user
$uploadedMovies = getUserUploadedMovies($userId);

// Handle profile update (using POST form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $update_data = [];
    $has_update = false;
    $errors = [];
    $success = false;

    // Handle bio update
    if (isset($_POST['bio'])) {
        $update_data['bio'] = trim($_POST['bio']);
        $has_update = true;
    }

    // Handle username/display name updates
    if (isset($_POST['edit_field']) && isset($_POST['edit_value'])) {
        $field = $_POST['edit_field'];
        $value = trim($_POST['edit_value']);

        if (empty($value)) {
            $errors[] = 'Value cannot be empty!';
        } else if ($field === 'username') {
            // Check if username already exists
            if (isUsernameExists($value, $userId)) {
                $errors[] = 'Username already taken!';
            } else {
                $update_data['username'] = $value;
                $has_update = true;
            }
        } else if ($field === 'displayName') {
            $update_data['full_name'] = $value;
            $has_update = true;
        }
    }

    // Add profile image upload handling (assuming `upload_profile_image.php` handles the actual file upload and returns JSON)
    // The JavaScript `uploadProfileImage` function already sends an AJAX request to `upload_profile_image.php`
    // So, this block is not directly needed here for file upload, but it's good to keep in mind for future server-side validation.
    // If you want to handle file uploads directly in this script, you'd need logic similar to:
    /*
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['profile_image']['tmp_name'];
        $file_name = $_FILES['profile_image']['name'];
        $file_size = $_FILES['profile_image']['size'];
        $file_type = $_FILES['profile_image']['type'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_ext = ['jpeg', 'jpg', 'png', 'gif'];
        if (!in_array($file_ext, $allowed_ext)) {
            $errors[] = 'Invalid image file type. Only JPG, JPEG, PNG, GIF are allowed.';
        } elseif ($file_size > 5 * 1024 * 1024) { // 5MB limit
            $errors[] = 'File size exceeds 5MB limit.';
        } else {
            // Generate a unique file name
            $new_file_name = uniqid('profile_') . '.' . $file_ext;
            $upload_path = '../uploads/profiles/' . $new_file_name; // Adjust path as per your WEB_UPLOAD_DIR_PROFILES

            if (move_uploaded_file($file_tmp_path, $upload_path)) {
                $update_data['profile_image'] = 'uploads/profiles/' . $new_file_name; // Save relative path to DB
                $has_update = true;

                // Optionally delete old profile image if it exists
                if (!empty($user['profile_image']) && file_exists('../' . $user['profile_image'])) {
                    unlink('../' . $user['profile_image']);
                }
            } else {
                $errors[] = 'Failed to upload profile image.';
            }
        }
    }
    */

    if (empty($errors) && $has_update) {
        if (updateUser($userId, $update_data)) {
            $_SESSION['success_message'] = 'Profile updated successfully!';
            // Refresh user data after update
            $user = getAuthenticatedUser(); // Re-fetch user details
            $success = true;
        } else {
            $_SESSION['error_message'] = 'Failed to update profile.';
        }
    } elseif (!empty($errors)) {
        $_SESSION['error_message'] = implode('<br>', $errors);
    } else {
        $_SESSION['error_message'] = 'No data to update.';
    }

    // Redirect to prevent form resubmission and show messages
    header('Location: index.php');
    exit;
}

// Get messages from session
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
unset($_SESSION['success_message']);
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;
unset($_SESSION['error_message']);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RATE-TALES - Profile</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="../review/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <nav class="sidebar">
            <div class="logo">
                <h2>RATE-TALES</h2>
            </div>
            <ul class="nav-links">
                <li><a href="../beranda/index.php"><i class="fas fa-home"></i> <span>Home</span></a></li>
                <li><a href="../favorite/index.php"><i class="fas fa-heart"></i> <span>Favourites</span></a></li>
                <li><a href="../review/index.php"><i class="fas fa-star"></i> <span>Review</span></a></li>
                <li><a href="../manage/indeks.php"><i class="fas fa-film"></i> <span>Manage</span></a></li>
                <li class="active"><a href="#"><i class="fas fa-user"></i> <span>Profile</span></a></li>
            </ul>
            <div class="bottom-links">
                <ul>
                    <li><a href="../autentifikasi/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
                </ul>
            </div>
        </nav>
        <main class="main-content">
            <?php if ($success_message): ?>
                <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="profile-header">
                <div class="profile-info">
                    <div class="profile-image">
                        <img src="<?php echo htmlspecialchars($user['profile_image'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($user['full_name'] ?? $user['username']) . '&background=random&color=fff&size=120'); ?>" alt="Profile Picture">
                        <label for="profile_image_upload" class="edit-icon">
                            <i class="fas fa-camera" title="Change Profile Image"></i>
                        </label>
                        <input type="file" id="profile_image_upload" name="profile_image" accept="image/*" style="display: none;" onchange="uploadProfileImage(this)">
                    </div>
                    <div class="profile-details">
                        <h1>
                            <span id="displayName" onclick="showEditModal('displayName', '<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>')"><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></span>
                            <i class="fas fa-pen edit-icon"></i>
                        </h1>
                        <p class="username">
                            @<span id="username" onclick="showEditModal('username', '<?php echo htmlspecialchars($user['username']); ?>')"><?php echo htmlspecialchars($user['username']); ?></span>
                            <i class="fas fa-pen edit-icon"></i>
                        </p>
                        <p class="user-meta"><?php echo htmlspecialchars($user['age'] ?? 'N/A'); ?> | <?php echo htmlspecialchars($user['gender'] ?? 'N/A'); ?></p>
                    </div>
                </div>
                <div class="about-me">
                    <h2>ABOUT ME:</h2>
                    <div class="about-content" id="bio" onclick="enableBioEdit()">
                        <?php echo nl2br(htmlspecialchars($user['bio'] ?? 'Click to add bio...')); ?>
                    </div>
                </div>
            </div>
            <div class="posts-section">
                <h2>Movies I Uploaded</h2>
                <div class="movies-grid review-grid">
                    <?php if (!empty($uploadedMovies)): ?>
                        <?php foreach ($uploadedMovies as $movie): ?>
                            <div class="movie-card">
                                <div class="movie-poster">
                                    <img src="<?php echo htmlspecialchars(WEB_UPLOAD_DIR_POSTERS . $movie['poster_image'] ?? '../gambar/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                                    <div class="movie-actions">
                                    </div>
                                </div>
                                <div class="movie-details">
                                    <h3><?php echo htmlspecialchars($movie['title']); ?></h3>
                                    <p class="movie-info"><?php echo htmlspecialchars((new DateTime($movie['release_date']))->format('Y')); ?> | <?php echo htmlspecialchars($movie['genres'] ?? 'N/A'); ?></p>
                                    <div class="rating">
                                        <div class="stars">
                                            <?php
                                            $average_rating = floatval($movie['average_rating']);
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $average_rating) {
                                                    echo '<i class="fas fa-star"></i>';
                                                } else if ($i - 0.5 <= $average_rating) {
                                                    echo '<i class="fas fa-star-half-alt"></i>';
                                                } else {
                                                    echo '<i class="far fa-star"></i>';
                                                }
                                            }
                                            ?>
                                        </div>
                                        <span class="rating-count">(<?php echo htmlspecialchars($movie['average_rating']); ?>)</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state full-width">
                            <i class="fas fa-film"></i>
                            <p>You haven't uploaded any movies yet.</p>
                            <p class="subtitle">Go to the <a href="../manage/indeks.php">Manage</a> section to upload your first movie.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script>
        // Function to handle profile image upload
        function uploadProfileImage(input) {
            if (input.files && input.files[0]) {
                const formData = new FormData();
                formData.append('profile_image', input.files[0]);
                formData.append('update_profile', '1'); // This will be handled by upload_profile_image.php

                fetch('upload_profile_image.php', { // Ensure this path is correct
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update image preview
                            document.querySelector('.profile-image img').src = data.image_url;
                            // Show success message
                            alert('Profile image updated successfully!');
                            // Reload page to update session or other data
                            window.location.reload();
                        } else {
                            alert(data.message || 'Failed to update profile image');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while updating profile image');
                    });
            }
        }

        // JavaScript for bio editing
        function enableBioEdit() {
            const bioElement = document.getElementById('bio');
            const currentText = bioElement.textContent.trim();

            // Check if already in edit mode (by looking for the form)
            if (bioElement.querySelector('form')) {
                return;
            }

            const textarea = document.createElement('textarea');
            textarea.name = 'bio';
            textarea.className = 'edit-input bio-input';
            textarea.value = (currentText === 'Click to add bio...' || currentText === '') ? '' : currentText;
            textarea.placeholder = 'Write something about yourself...';
            textarea.rows = 4; // Give it a reasonable number of rows

            // Create Save and Cancel buttons
            const saveButton = document.createElement('button');
            saveButton.textContent = 'Save';
            saveButton.className = 'btn-save-bio';
            saveButton.type = 'submit'; // Make save button submit the form

            const cancelButton = document.createElement('button');
            cancelButton.textContent = 'Cancel';
            cancelButton.className = 'btn-cancel-bio';
            cancelButton.type = 'button'; // Keep cancel as button


            // Create a form to wrap the textarea and buttons
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'index.php'; // Submit back to this page

            const hiddenUpdateInput = document.createElement('input');
            hiddenUpdateInput.type = 'hidden';
            hiddenUpdateInput.name = 'update_profile'; // Identifier for the POST handler
            hiddenUpdateInput.value = '1';

            // The textarea itself will send the 'bio' data because it has the 'name="bio"' attribute

            // Replace content with textarea and buttons
            bioElement.innerHTML = ''; // Use innerHTML to remove previous content including <br>
            form.appendChild(hiddenUpdateInput);
            form.appendChild(textarea);
            // Wrap buttons in a div for layout
            const buttonDiv = document.createElement('div');
            buttonDiv.style.marginTop = '10px';
            buttonDiv.style.textAlign = 'right';
            buttonDiv.appendChild(cancelButton);
            buttonDiv.appendChild(saveButton);
            form.appendChild(buttonDiv);

            bioElement.appendChild(form);
            textarea.focus();

            // Handle Cancel
            cancelButton.onclick = function() {
                // Restore original content with preserved line breaks
                let originalValue = (currentText === 'Click to add bio...' || currentText === '') ? 'Click to add bio...' : currentText;
                bioElement.innerHTML = nl2br(htmlspecialchars(originalValue)); // Use nl2br on restore
            };
        }

        // Helper function for nl2br (client-side equivalent for display)
        function nl2br(str) {
            if (typeof str !== 'string') return str;
            // Replace \r\n, \r, or \n with <br>
            return str.replace(/(?:\r\n|\r|\n)/g, '<br>');
        }
        // Helper function for HTML escaping (client-side)
        function htmlspecialchars(str) {
            if (typeof str !== 'string') return str;
            return str.replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    </script>
    <style>
        /* Add style for bio edit state buttons */
        .btn-save-bio,
        .btn-cancel-bio {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin-left: 10px;
            transition: background-color 0.3s;
        }

        .btn-save-bio {
            background-color: #00ffff;
            color: #1a1a1a;
        }

        .btn-save-bio:hover {
            background-color: #00cccc;
        }

        .btn-cancel-bio {
            background-color: #555;
            color: #fff;
        }

        .btn-cancel-bio:hover {
            background-color: #666;
        }

        /* Style for user meta info */
        .user-meta {
            color: #888;
            font-size: 14px;
            margin-top: 5px;
        }

        /* Style for movies grid on profile page */
        .movies-grid.review-grid {
            padding: 0;
        }

        /* Styles for the bio textarea */
        .bio-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #00ffff;
            border-radius: 5px;
            background-color: #2a2a2a;
            color: white;
            box-sizing: border-box;
            /* Include padding in the element's total width and height */
            resize: vertical;
            /* Allow vertical resizing */
        }
    </style>
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Edit <span id="editFieldName"></span></h2>
            <form id="editForm" method="POST">
                <input type="text" id="editInput" name="edit_value" required>
                <input type="hidden" id="editField" name="edit_field">
                <input type="hidden" name="update_profile" value="1">
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel-bio" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-save-bio">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions (keep these as they are for username/display name)
        function showEditModal(field, currentValue) {
            const modal = document.getElementById('editModal');
            const editInput = document.getElementById('editInput');
            const editField = document.getElementById('editField');
            const editFieldName = document.getElementById('editFieldName');

            editInput.value = currentValue;
            editField.value = field;
            editFieldName.textContent = field === 'username' ? 'Username' : 'Display Name';

            modal.style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeEditModal();
            }
        }

        // Close modal when clicking X
        document.querySelector('.close').onclick = closeEditModal;
    </script>

    <style>
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #1a1a1a;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #00ffff;
            border-radius: 5px;
            width: 80%;
            max-width: 500px;
            position: relative;
            color: white;
        }

        .close {
            color: #00ffff;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #00cccc;
        }

        #editInput {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #00ffff;
            border-radius: 4px;
            background-color: #2a2a2a;
            color: white;
        }

        .modal-buttons {
            text-align: right;
            margin-top: 15px;
        }

        /* Hover styles for editable fields */
        #displayName,
        #username {
            cursor: pointer;
            transition: color 0.3s ease;
        }

        #displayName:hover,
        #username:hover {
            color: #00ffff;
        }

        .profile-details .edit-icon {
            font-size: 0.8em;
            margin-left: 8px;
            color: #00ffff;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        #displayName:hover+.edit-icon,
        #username:hover+.edit-icon {
            opacity: 1;
        }
    </style>
</body>

</html>