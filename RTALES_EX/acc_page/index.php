<?php
// acc_page/index.php
require_once '../includes/config.php'; // Include config.php

// Redirect if not authenticated
redirectIfNotAuthenticated();

// Get authenticated user ID
$userId = $_SESSION['user_id'];

// Fetch authenticated user details from the database
$user = getAuthenticatedUser();

// Handle user not found after authentication check (shouldn't happen if getAuthenticatedUser works)
if (!$user) {
    $_SESSION['error_message'] = 'User profile could not be loaded.';
    header('Location: ../autentikasi/logout.php'); // Force logout if user somehow invalidates
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

     // You would add handlers for other editable fields here if they were implemented
     // For example, if you added input fields for full_name, age, gender in the form
     // if (isset($_POST['full_name'])) {
     //     $update_data['full_name'] = trim($_POST['full_name']);
     //     $has_update = true;
     // }
     // if (isset($_POST['age'])) {
     //     $age = filter_var($_POST['age'], FILTER_VALIDATE_INT);
     //     if ($age !== false && $age > 0) {
     //          $update_data['age'] = $age;
     //          $has_update = true;
     //     } else {
     //          $errors[] = 'Invalid age provided.';
     //     }
     // }


     // Handle profile image upload (more complex, needs file handling)
     // This requires a file input in the form and logic similar to movie poster upload

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
                    <li><a href="../autentikasi/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
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
                         <!-- Optional: Add edit icon for profile image upload -->
                         <!-- <i class="fas fa-camera edit-icon" title="Change Profile Image"></i> -->
                    </div>
                    <div class="profile-details">
                        <h1>
                            <span id="displayName"><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></span>
                            <!-- Edit icon for display name (currently not implemented via form) -->
                            <!-- <i class="fas fa-pen edit-icon" onclick="toggleEdit('displayName')"></i> -->
                        </h1>
                        <p class="username">
                            @<span id="username"><?php echo htmlspecialchars($user['username']); ?></span>
                            <!-- Edit icon for username (usually disabled) -->
                            <!-- <i class="fas fa-pen edit-icon" onclick="toggleEdit('username')"></i> -->
                        </p>
                         <p class="user-meta"><?php echo htmlspecialchars($user['age'] ?? 'N/A'); ?> | <?php echo htmlspecialchars($user['gender'] ?? 'N/A'); ?></p>
                    </div>
                </div>
                <div class="about-me">
                    <h2>ABOUT ME:</h2>
                    <!-- Bio section - make it editable -->
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
                            <div class="movie-card" onclick="window.location.href='../review/movie-details.php?id=<?php echo $movie['movie_id']; ?>'">
                                <div class="movie-poster">
                                    <img src="<?php echo htmlspecialchars(WEB_UPLOAD_DIR_POSTERS . $movie['poster_image'] ?? '../gambar/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                                    <div class="movie-actions">
                                         <!-- Optional: Add edit/delete action buttons directly here -->
                                         <!-- <a href="../manage/edit.php?id=<?php echo $movie['movie_id']; ?>" class="action-btn" title="Edit Movie"><i class="fas fa-edit"></i></a> -->
                                         <!-- Delete form (should point to manage/indeks.php handler) -->
                                         <!-- <form action="../manage/indeks.php" method="POST" onsubmit="return confirm('Delete movie?');">
                                             <input type="hidden" name="delete_movie_id" value="<?php echo $movie['movie_id']; ?>">
                                             <button type="submit" class="action-btn" title="Delete Movie"><i class="fas fa-trash"></i></button>
                                         </form> -->
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
        // JavaScript for bio editing
        function enableBioEdit() {
            const bioElement = document.getElementById('bio');
            const currentText = bioElement.textContent.trim();

            // Check if already in edit mode
            if (bioElement.querySelector('form')) { // Check for the form element instead of textarea
                return;
            }

            const textarea = document.createElement('textarea');
            textarea.className = 'edit-input bio-input';
            textarea.value = (currentText === 'Click to add bio...' || currentText === '') ? '' : currentText;
            textarea.placeholder = 'Write something about yourself...';

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

             // Add keydown listener to textarea for saving on Enter (optional)
            // textarea.addEventListener('keydown', function(event) {
            //     // Check if Enter key is pressed (and not Shift+Enter for newline)
            //     if (event.key === 'Enter' && !event.shiftKey) {
            //         event.preventDefault(); // Prevent default newline
            //         form.submit(); // Submit the form
            //     }
            // });
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
         .btn-save-bio, .btn-cancel-bio {
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

     </style>
</body>
</html>