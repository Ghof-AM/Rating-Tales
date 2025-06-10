<?php
// acc_page/index.php
require_once '../includes/config.php'; // Include config.php
// auth_helper.php is no longer needed, its functions are in config.php or database.php

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

    // Handle username/display name updates (from modal)
    if (isset($_POST['edit_field']) && isset($_POST['edit_value'])) {
        $field = $_POST['edit_field'];
        $value = trim($_POST['edit_value']);

        if (empty($value)) {
            $errors[] = ucwords($field) . ' cannot be empty!'; // Use field name in error
        } else if ($field === 'username') {
            // Check if username already exists (excluding the current user)
            if (isUsernameOrEmailExists($value, '', $userId)) { // Check only username uniqueness
                $errors[] = 'Username already taken!';
            } else {
                $update_data['username'] = $value;
                $has_update = true;
            }
        } else if ($field === 'displayName') {
            $update_data['full_name'] = $value;
            $has_update = true;
        }
        // Add other editable fields here if needed
        // } else if ($field === 'email') { ... email validation and uniqueness check ... }
        // } else if ($field === 'age') { ... validate int ... }
        // } else if ($field === 'gender') { ... validate enum ... }
    }

     // Note: Profile image upload is handled by a separate AJAX call to upload_profile_image.php


    if (empty($errors) && $has_update) {
        // Update the user in the database using the updateUser function
        if (updateUser($userId, $update_data)) {
            $_SESSION['success_message'] = 'Profile updated successfully!';
             // Refresh user data after update
            $user = getAuthenticatedUser(); // Re-fetch user details from DB
             $success = true;
        } else {
            // This might happen if execute fails for some reason (e.g., DB constraint violation not caught by checks)
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
     <link rel="stylesheet" href="../review/styles.css"> <!-- Keep review styles for movie grid -->
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
                        <label for="profile_image_upload" class="edit-icon">
                            <i class="fas fa-camera" title="Change Profile Image"></i>
                        </label>
                        <!-- The actual file input, hidden -->
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
                         <!-- Add more fields like email, age, gender here if you implement modals for them -->
                          <!-- <p class="user-meta">Email: <span id="email" onclick="showEditModal('email', '<?php // echo htmlspecialchars($user['email']); ?>')"><?php // echo htmlspecialchars($user['email']); ?></span> <i class="fas fa-pen edit-icon"></i></p> -->
                    </div>
                </div>
                <div class="about-me">
                    <h2>ABOUT ME:</h2>
                    <!-- Bio section - make it editable -->
                    <div class="about-content" id="bio" onclick="enableBioEdit()">
                         <?php
                            $bio_text = $user['bio'] ?? '';
                            // Display placeholder if bio is empty
                            if (empty(trim($bio_text))) {
                                echo 'Click to add bio...';
                            } else {
                                // Use nl2br and htmlspecialchars for displaying bio content
                                echo nl2br(htmlspecialchars($bio_text));
                            }
                         ?>
                    </div>
                </div>
            </div>
            <div class="posts-section">
                <h2>Movies I Uploaded</h2>
                <div class="movies-grid review-grid">
                    <?php if (!empty($uploadedMovies)): ?>
                        <?php foreach ($uploadedMovies as $movie): ?>
                            <div class="movie-card" onclick="window.location.href='../review/movie-details.php?id=<?php echo $movie['movie_id']; ?>'"> <!-- Make the card clickable -->
                                <div class="movie-poster">
                                    <img src="<?php echo htmlspecialchars(WEB_UPLOAD_DIR_POSTERS . $movie['poster_image'] ?? '../gambar/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                                    <div class="movie-actions">
                                         <!-- Link to edit page -->
                                         <a href="../manage/edit.php?id=<?php echo $movie['movie_id']; ?>" class="action-btn" title="Edit Movie"><i class="fas fa-edit"></i></a>
                                         <!-- Delete form (should point to manage/indeks.php handler) -->
                                         <form action="../manage/indeks.php" method="POST" onsubmit="return confirm('Are you sure you want to delete &quot;<?php echo htmlspecialchars($movie['title']); ?>&quot;? This cannot be undone.');">
                                             <input type="hidden" name="delete_movie_id" value="<?php echo $movie['movie_id']; ?>">
                                             <button type="submit" class="action-btn" title="Delete Movie"><i class="fas fa-trash"></i></button>
                                         </form>
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
                // We don't need update_profile=1 here as upload_profile_image.php is a dedicated script

                fetch('upload_profile_image.php', { // Fetch to the separate upload script
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                     if (!response.ok) {
                         // Handle HTTP errors (e.g., 400, 500)
                         return response.text().then(text => { throw new Error('HTTP error! Status: ' + response.status + ' - ' + text); });
                     }
                     return response.json();
                 })
                .then(data => {
                    if (data.success) {
                        // Update image preview src (if it exists)
                        const profileImg = document.querySelector('.profile-image img');
                        if (profileImg) {
                             profileImg.src = data.image_url;
                        }
                        // Show success message (can use the alert div)
                        displayMessage('Profile image updated successfully!', 'success');
                        // Optionally reload the page to ensure sidebar/header updates
                        // window.location.reload(); // Might be jarring, rely on JS update + message
                    } else {
                        // Show error message
                        displayMessage(data.message || 'Failed to update profile image', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error uploading profile image:', error);
                    displayMessage('An error occurred while uploading profile image: ' + error.message, 'error');
                });
            }
        }

         // Helper function to display messages in the alert divs
         function displayMessage(message, type) {
             const alertContainer = document.querySelector('main.main-content');
             // Remove any existing alerts first
             const existingAlerts = alertContainer.querySelectorAll('.alert');
             existingAlerts.forEach(alert => alert.remove());

             const alertDiv = document.createElement('div');
             alertDiv.className = `alert ${type}`;
             alertDiv.innerHTML = message; // Use innerHTML because message might contain <br> from PHP errors

             // Insert before the profile header
             const profileHeader = document.querySelector('.profile-header');
             if (profileHeader) {
                 alertContainer.insertBefore(alertDiv, profileHeader);
             } else {
                 // Fallback if profile header not found
                 alertContainer.prepend(alertDiv);
             }

             // Optional: Automatically hide message after a few seconds
             // setTimeout(() => {
             //     alertDiv.remove();
             // }, 5000); // Hide after 5 seconds
         }


        // JavaScript for bio editing
        function enableBioEdit() {
            const bioElement = document.getElementById('bio');
             // Get current content, handle placeholder
            const currentText = bioElement.textContent.trim();
             const initialBioValue = (currentText === 'Click to add bio...') ? '' : currentText;


            // Check if already in edit mode
            if (bioElement.querySelector('form')) { // Check for the form element instead of textarea
                return;
            }

            const textarea = document.createElement('textarea');
            textarea.className = 'edit-input bio-input';
            textarea.name = 'bio'; // Crucial for POST data
            textarea.value = initialBioValue;
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
            form.style.display = 'flex'; // Use flex to layout textarea and buttons
            form.style.flexDirection = 'column'; // Stack them

            const hiddenUpdateInput = document.createElement('input');
            hiddenUpdateInput.type = 'hidden';
            hiddenUpdateInput.name = 'update_profile'; // Identifier for the POST handler
            hiddenUpdateInput.value = '1';

            // The textarea itself will send the 'bio' data because it has the 'name="bio"' attribute

            // Replace content with form
            bioElement.innerHTML = ''; // Clear previous content
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
                // Restore original content with preserved line breaks and placeholder logic
                bioElement.innerHTML = formatBioForDisplay(initialBioValue);
            };

             // Add keydown listener to textarea for saving on Ctrl+Enter (optional)
            textarea.addEventListener('keydown', function(event) {
                 // Check if Enter key is pressed and Ctrl/Cmd is held
                 if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
                     event.preventDefault(); // Prevent default newline
                     form.submit(); // Submit the form
                 }
            });
        }

         // Helper function for displaying bio (combines nl2br and htmlspecialchars)
         function formatBioForDisplay(str) {
             if (typeof str !== 'string') return str;
             const escapedStr = htmlspecialchars(str);
             // If empty after trim, show placeholder
             if (escapedStr.trim() === '') {
                 return 'Click to add bio...';
             }
             // Replace \r\n, \r, or \n with <br>
             return escapedStr.replace(/(?:\r\n|\r|\n)/g, '<br>');
         }


          // Helper function for HTML escaping (client-side) - already exists, ensuring it's here
         function htmlspecialchars(str) {
             if (typeof str !== 'string') return str;
             // Create a temporary DOM element to leverage browser's escaping
             const div = document.createElement('div');
             div.appendChild(document.createTextNode(str));
             return div.innerHTML;

             // // Or manually, be careful with entity names
             // return str.replace(/&/g, '&amp;')
             //           .replace(/</g, '&lt;')
             //           .replace(/>/g, '&gt;')
             //           .replace(/"/g, '&quot;')
             //           .replace(/'/g, '&#039;');
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

         /* Adjust movie-card cursor */
         .movies-grid.review-grid .movie-card {
             cursor: pointer; /* Indicate clickability */
         }


         /* Styles for the display bio area */
        #bio {
            cursor: pointer;
            transition: background-color 0.3s;
             padding: 10px;
             border-radius: 8px;
             line-height: 1.6;
             color: #ccc;
             white-space: pre-wrap; /* Preserve line breaks */
             min-height: 100px; /* Ensure click area is large enough */
             display: block; /* Make it a block element */
        }

        #bio:hover {
            background-color: #2a2a2a;
        }

        #bio form { /* Style the form wrapper when in edit mode */
            padding: 0;
            margin: 0;
            background: none; /* Remove background from form wrapper */
        }

        /* Style for the bio textarea in edit mode */
        #bio textarea {
             background-color: #363636; /* Darker background for input */
             border: 1px solid #00ffff;
             border-radius: 4px;
             color: #ffffff;
             padding: 8px 12px;
             font-size: inherit;
             width: 100%;
             min-height: 100px;
             resize: vertical;
             outline: none;
             font-family: inherit;
             transition: border-color 0.3s, box-shadow 0.3s;
             line-height: 1.5;
        }
         #bio textarea:focus {
             border-color: #00cccc;
             box-shadow: 0 0 0 2px rgba(0, 255, 255, 0.2);
         }


     </style>
    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Edit <span id="editFieldName"></span></h2>
            <form id="editForm" method="POST" action="index.php">
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
        // Modal functions for Username/Display Name
        function showEditModal(field, currentValue) {
            const modal = document.getElementById('editModal');
            const editInput = document.getElementById('editInput');
            const editField = document.getElementById('editField');
            const editFieldName = document.getElementById('editFieldName');

            editInput.value = currentValue;
            editField.value = field;
            editFieldName.textContent = field === 'username' ? 'Username' : 'Display Name';

            modal.style.display = 'block';
            editInput.focus(); // Set focus to the input field
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
             // Clear input and field name when closing
            document.getElementById('editInput').value = '';
            document.getElementById('editField').value = '';
            document.getElementById('editFieldName').textContent = '';
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

         // Add keydown listener for Escape key to close modal
         document.addEventListener('keydown', function(event) {
             const modal = document.getElementById('editModal');
             if (event.key === 'Escape' && modal && modal.style.display === 'block') {
                 closeEditModal();
             }
         });

         // Add keydown listener for Enter key to submit modal form
         document.getElementById('editInput').addEventListener('keydown', function(event) {
             if (event.key === 'Enter') {
                 event.preventDefault(); // Prevent default behavior (newline)
                 document.getElementById('editForm').submit();
             }
         });


    </script>

    <style>
        /* Modal styles - Keep existing */
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
        #displayName, #username {
            cursor: pointer;
            transition: color 0.3s ease;
        }

        #displayName:hover, #username:hover {
            color: #00ffff;
        }

        /* Ensure edit icon visibility consistency */
        .profile-details h1 .edit-icon,
        .profile-details p .edit-icon {
             font-size: 0.8em;
             margin-left: 8px;
             color: #00ffff;
             opacity: 0.8; /* Always slightly visible */
             transition: opacity 0.3s ease;
        }


         #displayName:hover .edit-icon,
         #username:hover .edit-icon {
             opacity: 1; /* Full opacity on hover */
         }
         /* Add similar styles for email/age/gender if added to the details */


    </style>
</body>
</html>