<?php
// manage/edit.php
require_once '../includes/config.php'; // Include config.php

// Redirect if not authenticated
redirectIfNotAuthenticated();

// Get authenticated user ID
$userId = $_SESSION['user_id'];

// Get movie ID from URL
$movieId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

// Handle missing or invalid movie ID
if (!$movieId) {
    $_SESSION['error_message'] = 'Invalid movie ID provided for editing.';
    header('Location: indeks.php');
    exit;
}

// Fetch movie details
$movie = getMovieById($movieId);

// Handle movie not found or not uploaded by the current user
if (!$movie || $movie['uploaded_by'] != $userId) {
    $_SESSION['error_message'] = 'Movie not found or you do not have permission to edit it.';
    header('Location: indeks.php');
    exit;
}

// Initialize variables with existing movie data for form pre-filling
$title = $movie['title'];
$summary = $movie['summary'];
$release_date = $movie['release_date'];
$duration_hours = $movie['duration_hours'];
$duration_minutes = $movie['duration_minutes'];
$age_rating = $movie['age_rating'];
$genres = $movie['genres_array']; // Get genres as an array
$trailer_url = $movie['trailer_url'];
$trailer_file = $movie['trailer_file']; // Existing trailer file name
$poster_image = $movie['poster_image']; // Existing poster image name


$error_message = null;
$success_message = null;

// Handle form submission (PUT/POST method emulation if needed, but POST is standard for forms)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Collect and Sanitize Input
    // Use $_POST data for updates, fallback to existing movie data if field not submitted
    $title_post = trim($_POST['movie-title'] ?? $title);
    $summary_post = trim($_POST['movie-summary'] ?? $summary);
    $release_date_post = $_POST['release-date'] ?? $release_date;
    // Use filter_var but allow empty string if field wasn't in POST (though form should send it)
    $duration_hours_post = filter_var($_POST['duration-hours'] ?? $duration_hours, FILTER_VALIDATE_INT);
    $duration_minutes_post = filter_var($_POST['duration-minutes'] ?? $duration_minutes, FILTER_VALIDATE_INT);
    $age_rating_post = $_POST['age-rating'] ?? $age_rating;
    $genres_post = $_POST['genre'] ?? []; // Genres are selected checkboxes
    $trailer_url_post = trim($_POST['trailer-link'] ?? $trailer_url);

    $errors = [];
    $update_data = []; // Data array to pass to updateUser function

    // 2. Validate and Prepare Update Data

    // Basic fields
    if (empty($title_post)) $errors[] = 'Movie Title is required.';
    else if ($title_post !== $title) $update_data['title'] = $title_post;

    if (empty($release_date_post)) $errors[] = 'Release Date is required.';
    else if ($release_date_post !== $release_date) $update_data['release_date'] = $release_date_post;

    // Duration validation
    if ($duration_hours_post === false || $duration_hours_post === '' || $duration_hours_post < 0) $errors[] = 'Valid Duration (Hours) is required.';
    else if ($duration_hours_post !== $duration_hours) $update_data['duration_hours'] = $duration_hours_post;

    if ($duration_minutes_post === false || $duration_minutes_post === '' || $duration_minutes_post < 0 || $duration_minutes_post > 59) $errors[] = 'Valid Duration (Minutes) is required (0-59).';
    else if ($duration_minutes_post !== $duration_minutes) $update_data['duration_minutes'] = $duration_minutes_post;

    if (empty($age_rating_post)) $errors[] = 'Age Rating is required.';
    else if ($age_rating_post !== $age_rating) $update_data['age_rating'] = $age_rating_post;


    // Summary (can be empty, but trim)
    if ($summary_post !== $summary) $update_data['summary'] = $summary_post;


    // Genres (handled separately for the movie_genres table)
     $allowed_genres = ['action', 'adventure', 'comedy', 'drama', 'horror', 'supernatural', 'animation', 'sci-fi'];
     foreach ($genres_post as $genre) {
         if (!in_array($genre, $allowed_genres)) {
             $errors[] = 'Invalid genre selected.';
             $genres_post = $genres; // Revert genres to original on error to prevent saving bad data
             break; // Stop checking genres
         }
     }
     // Assume genre update will be handled separately later

    // Trailer Handling
    $new_trailer_file_path = $trailer_file; // Default to existing file
    $new_trailer_url = $trailer_url_post; // Default to submitted URL

    // Check if a new trailer file was uploaded
    if (isset($_FILES['trailer-file']) && $_FILES['trailer-file']['error'] === UPLOAD_ERR_OK) {
         $trailerFile = $_FILES['trailer-file'];
         $allowedTypes = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];
         $maxFileSize = 50 * 1024 * 1024; // 50MB

         if (!in_array($trailerFile['type'], $allowedTypes)) {
             $errors[] = 'Invalid new trailer file type. Only MP4, WebM, Ogg, MOV are allowed.';
         }
         if ($trailerFile['size'] > $maxFileSize) {
             $errors[] = 'New trailer file is too large. Maximum size is 50MB.';
         }

         // If no file errors yet, process the upload
         if (empty($errors)) {
             $fileExtension = pathinfo($trailerFile['name'], PATHINFO_EXTENSION);
             $newFileName = uniqid('trailer_', true) . '.' . $fileExtension;
             $destination = UPLOAD_DIR_TRAILERS . $newFileName;

             if (move_uploaded_file($trailerFile['tmp_name'], $destination)) {
                 $new_trailer_file_path = $newFileName; // Store new filename
                 $new_trailer_url = null; // Prioritize file, clear URL
             } else {
                 $errors[] = 'Failed to upload new trailer file.';
             }
         }
    } else if (isset($_FILES['trailer-file']) && $_FILES['trailer-file']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Handle specific upload errors for new trailer file
        $errors[] = 'New trailer file upload error: ' . $_FILES['trailer-file']['error'];
    }

     // Check if at least one trailer source is provided (URL or existing/new file)
     if (empty($new_trailer_url) && empty($new_trailer_file_path)) {
         $errors[] = 'Either a Trailer URL or a Trailer File is required.';
     } else {
          // Update trailer fields if they changed
          if ($new_trailer_url !== $trailer_url) $update_data['trailer_url'] = $new_trailer_url;
          if ($new_trailer_file_path !== $trailer_file) $update_data['trailer_file'] = $new_trailer_file_path;
     }


    // Poster Handling
    $new_poster_image_path = $poster_image; // Default to existing poster

    // Check if a new poster image was uploaded
    if (isset($_FILES['movie-poster']) && $_FILES['movie-poster']['error'] === UPLOAD_ERR_OK) {
         $posterFile = $_FILES['movie-poster'];
         $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
         $maxFileSize = 5 * 1024 * 1024; // 5MB

         if (!in_array($posterFile['type'], $allowedTypes)) {
             $errors[] = 'Invalid new poster file type. Only JPG, PNG, GIF, WEBP are allowed.';
         }
         if ($posterFile['size'] > $maxFileSize) {
             $errors[] = 'New poster file is too large. Maximum size is 5MB.';
         }

         // If no file errors yet, process the upload
         if (empty($errors)) {
             $fileExtension = pathinfo($posterFile['name'], PATHINFO_EXTENSION);
             $newFileName = uniqid('poster_', true) . '.' . $fileExtension;
             $destination = UPLOAD_DIR_POSTERS . $newFileName;

             if (move_uploaded_file($posterFile['tmp_name'], $destination)) {
                 $new_poster_image_path = $newFileName; // Store new filename
             } else {
                 $errors[] = 'Failed to upload new poster file.';
             }
         }
    } else if (isset($_FILES['movie-poster']) && $_FILES['movie-poster']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Handle specific upload errors for new poster file
         $errors[] = 'New poster file upload error: ' . $_FILES['movie-poster']['error'];
    }

     // Update poster field if it changed
     if ($new_poster_image_path !== $poster_image) {
         $update_data['poster_image'] = $new_poster_image_path;
     }


    // 3. If no errors, update database
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $db_update_success = true;

            // Update movie details in the movies table
            if (!empty($update_data)) { // Only call update if there's data to update
                 if (!updateMovie($movieId, $update_data)) {
                     $db_update_success = false;
                 }
            }

            // Update genres - Clear existing and add new ones
            // Only update if the submitted genres are different from the original genres
            // Sorting is needed for comparison as order doesn't matter
            $current_genres_sorted = $genres;
            $submitted_genres_sorted = $genres_post;
            sort($current_genres_sorted);
            sort($submitted_genres_sorted);

            if ($current_genres_sorted !== $submitted_genres_sorted) {
                 // Remove old genres
                 if (!removeMovieGenres($movieId)) {
                     $db_update_success = false;
                 } else {
                     // Add new genres
                     foreach ($genres_post as $genre) {
                         if (!addMovieGenre($movieId, $genre)) {
                             $db_update_success = false; // Record specific genre error if needed, or just fail the whole update
                             break; // Stop adding genres if one fails
                         }
                     }
                 }
            }


            if ($db_update_success) {
                $pdo->commit();

                // Clean up old files AFTER successful DB update
                // Delete old poster if a new one was uploaded
                if ($new_poster_image_path !== $poster_image && !empty($poster_image)) {
                    $old_poster_path = UPLOAD_DIR_POSTERS . $poster_image;
                    if (file_exists($old_poster_path)) {
                        @unlink($old_poster_path);
                    }
                }
                 // Delete old trailer file if a new one was uploaded
                 if ($new_trailer_file_path !== $trailer_file && !empty($trailer_file)) {
                     $old_trailer_path = UPLOAD_DIR_TRAILERS . $trailer_file;
                     if (file_exists($old_trailer_path)) {
                         @unlink($old_trailer_path);
                     }
                 }


                $_SESSION['success_message'] = 'Movie updated successfully!';
                header('Location: indeks.php'); // Redirect to manage page
                exit;

            } else {
                 // DB update failed (either main movie data or genres)
                $pdo->rollBack();
                $errors[] = 'Failed to save changes to the database.'; // Generic error, specific errors were added earlier

                // Clean up newly uploaded files if DB transaction failed
                if ($new_poster_image_path !== $poster_image && file_exists(UPLOAD_DIR_POSTERS . $new_poster_image_path)) {
                     @unlink(UPLOAD_DIR_POSTERS . $new_poster_image_path);
                }
                 if ($new_trailer_file_path !== $trailer_file && file_exists(UPLOAD_DIR_TRAILERS . $new_trailer_file_path)) {
                    @unlink(UPLOAD_DIR_TRAILERS . $new_trailer_file_path);
                }
            }


        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Database error during movie update (ID: {$movieId}, User: {$userId}): " . $e->getMessage());
            $errors[] = 'An internal error occurred while saving the movie.';

            // Clean up newly uploaded files on exception
            if ($new_poster_image_path !== $poster_image && file_exists(UPLOAD_DIR_POSTERS . $new_poster_image_path)) {
                 @unlink(UPLOAD_DIR_POSTERS . $new_poster_image_path);
            }
             if ($new_trailer_file_path !== $trailer_file && file_exists(UPLOAD_DIR_TRAILERS . $new_trailer_file_path)) {
                @unlink(UPLOAD_DIR_TRAILERS . $new_trailer_file_path);
            }
        }
    }

    // If there were any errors, set the error message
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode('<br>', $errors);
        // Keep existing movie data in variables so form is pre-filled with POST data first, then original movie data
        // No redirect needed as we're already on the edit page
        // Re-fetch movie to get potentially mixed data if some updates succeeded or if $_POST wasn't fully set
        // This is tricky - simpler to just rely on $errors and keep POST data for display
         $title = $title_post; // Update variables to show POST data
         $summary = $summary_post;
         $release_date = $release_date_post;
         $duration_hours = $duration_hours_post;
         $duration_minutes = $duration_minutes_post;
         $age_rating = $age_rating_post;
         $genres = $genres_post;
         $trailer_url = $new_trailer_url;
         $trailer_file = $new_trailer_file_path;
         $poster_image = $new_poster_image_path; // This should be the new name if upload succeeded but DB failed
                                               // But it's better to just show the OLD image on DB failure
         $poster_image = $movie['poster_image']; // Revert poster preview to original on error
         $trailer_file = $movie['trailer_file']; // Revert trailer file preview to original on error
         $trailer_url = $trailer_url_post; // Keep the submitted URL on error

    }
}

// Re-fetch movie data if not a POST request or if POST failed and we didn't explicitly update vars
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($errors)) {
     // If coming to the page initially OR if POST failed, load/reload from DB
     $movie = getMovieById($movieId); // Re-fetch clean data or updated data
     if (!$movie || $movie['uploaded_by'] != $userId) {
         $_SESSION['error_message'] = 'Movie data could not be reloaded.';
         header('Location: indeks.php');
         exit;
     }
     // Re-assign variables from (potentially updated) $movie data
     $title = $movie['title'];
     $summary = $movie['summary'];
     $release_date = $movie['release_date'];
     $duration_hours = $movie['duration_hours'];
     $duration_minutes = $movie['duration_minutes'];
     $age_rating = $movie['age_rating'];
     $genres = $movie['genres_array'];
     $trailer_url = $movie['trailer_url'];
     $trailer_file = $movie['trailer_file'];
     $poster_image = $movie['poster_image'];
}


// Get messages from session for display
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
    <title>Edit Movie: <?php echo htmlspecialchars($title); ?> - RatingTales</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="upload.css"> <!-- Use upload styles for form layout -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <nav class="sidebar">
            <h2 class="logo">RATE-TALES</h2>
            <ul class="nav-links">
                <li><a href="../beranda/index.php"><i class="fas fa-home"></i> <span>Home</span></a></li>
                <li><a href="../favorite/index.php"><i class="fas fa-heart"></i> <span>Favorites</span></a></li>
                <li><a href="../review/index.php"><i class="fas fa-star"></i> <span>Review</span></a></li>
                <li><a href="indeks.php" class="active"><i class="fas fa-film"></i> <span>Manage</span></a></li>
                 <li><a href="../acc_page/index.php"><i class="fas fa-user"></i> <span>Profile</span></a></li>
            </ul>
            <ul class="bottom-links">
                <li><a href="../autentikasi/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <div class="upload-container">
                <h1>Edit Movie: <?php echo htmlspecialchars($title); ?></h1>

                 <?php if ($success_message): ?>
                    <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert error"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <form class="upload-form" action="edit.php?id=<?php echo $movieId; ?>" method="post" enctype="multipart/form-data">
                    <div class="form-layout">
                        <div class="form-main">
                            <div class="form-group">
                                <label for="movie-title">Movie Title</label>
                                <input type="text" id="movie-title" name="movie-title" required value="<?php echo htmlspecialchars($title); ?>">
                            </div>

                            <div class="form-group">
                                <label for="movie-summary">Movie Summary</label>
                                <textarea id="movie-summary" name="movie-summary" rows="4" required><?php echo htmlspecialchars($summary); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label>Genre</label>
                                <div class="genre-options">
                                    <?php
                                    $all_genres = ['action', 'adventure', 'comedy', 'drama', 'horror', 'supernatural', 'animation', 'sci-fi'];
                                    foreach ($all_genres as $genre_option):
                                        // Check if the current movie's genres array contains this option
                                        $checked = in_array($genre_option, $genres) ? 'checked' : '';
                                    ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="genre[]" value="<?php echo $genre_option; ?>" <?php echo $checked; ?>>
                                        <span><?php echo ucwords($genre_option); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="age-rating">Age Rating</label>
                                <select id="age-rating" name="age-rating" required>
                                    <option value="">Select age rating</option>
                                    <?php
                                    $age_ratings = ['G', 'PG', 'PG-13', 'R', 'NC-17'];
                                    foreach ($age_ratings as $rating_option):
                                        $selected = ($age_rating === $rating_option) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $rating_option; ?>" <?php echo $selected; ?>><?php echo $rating_option; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="movie-trailer">Movie Trailer</label>
                                <div class="trailer-input">
                                    <input type="text" id="trailer-link" name="trailer-link" placeholder="Enter YouTube video URL" value="<?php echo htmlspecialchars($trailer_url); ?>">
                                    <span class="trailer-note">* Paste YouTube video URL</span>
                                </div>
                                <div class="trailer-upload">
                                    <input type="file" id="trailer-file" name="trailer-file" accept="video/*">
                                    <span class="trailer-note">* Or upload video file (Max 50MB)</span>
                                </div>
                                 <p class="trailer-note" style="margin-top: 10px;">Only one trailer source (URL or File) is needed. Uploading a new file or entering a URL will replace the existing one.</p>
                                <?php if (!empty($trailer_url) || !empty($trailer_file)): ?>
                                    <p class="trailer-note">Current Trailer:
                                         <?php if (!empty($trailer_url)): ?>
                                            <a href="<?php echo htmlspecialchars($trailer_url); ?>" target="_blank">Link</a>
                                         <?php elseif (!empty($trailer_file)): ?>
                                            File: <?php echo htmlspecialchars($trailer_file); ?>
                                         <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-side">
                            <div class="poster-upload">
                                <label for="movie-poster">Movie Poster</label>
                                <div class="upload-area" id="upload-area">
                                    <i class="fas fa-image"></i>
                                    <p>Click or drag image here</p>
                                     <input type="file" id="movie-poster" name="movie-poster" accept="image/*"> <!-- Not required on edit unless replacing -->
                                    <!-- Display existing poster if available -->
                                    <img id="poster-preview" src="<?php echo !empty($poster_image) ? htmlspecialchars(WEB_UPLOAD_DIR_POSTERS . $poster_image) : '#'; ?>" alt="Poster Preview" style="<?php echo !empty($poster_image) ? 'display: block; object-fit: cover;' : 'display: none;'; ?>">
                                </div>
                                <p class="trailer-note" style="margin-top: 5px;">(Recommended: Aspect Ratio 2:3, Max 5MB)</p>
                                <?php if (!empty($poster_image)): ?>
                                    <p class="trailer-note">Current Poster: <?php echo htmlspecialchars($poster_image); ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="advanced-settings">
                                <h3>Advanced Settings</h3>
                                <div class="form-group">
                                    <label for="release-date">Release Date</label>
                                    <input type="date" id="release-date" name="release-date" required value="<?php echo htmlspecialchars($release_date); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="duration-hours">Film Duration</label>
                                    <div class="duration-inputs">
                                        <div class="duration-field">
                                            <input type="number" id="duration-hours" name="duration-hours" min="0" placeholder="Hours" required value="<?php echo htmlspecialchars($duration_hours); ?>">
                                            <span>Hours</span>
                                        </div>
                                        <div class="duration-field">
                                            <input type="number" id="duration-minutes" name="duration-minutes" min="0" max="59" placeholder="Minutes" required value="<?php echo htmlspecialchars($duration_minutes); ?>">
                                            <span>Minutes</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="cancel-btn" onclick="window.location.href='indeks.php'">Cancel</button>
                        <button type="submit" class="submit-btn">Save Changes</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // JavaScript for poster preview
        const posterInput = document.getElementById('movie-poster');
        const posterPreview = document.getElementById('poster-preview');
        const uploadArea = document.getElementById('upload-area');
        const uploadAreaIcon = uploadArea.querySelector('i');
        const uploadAreaText = uploadArea.querySelector('p');

        // Initial display based on existing poster
        const hasExistingPoster = posterPreview.src && posterPreview.src !== window.location.href + '#'; // Check if src is set and not empty hash
        if (hasExistingPoster) {
             uploadAreaIcon.style.display = 'none'; // Hide icon
             uploadAreaText.style.display = 'none'; // Hide text
             posterPreview.style.objectFit = 'cover'; // Use cover for existing
        } else {
             posterPreview.style.display = 'none'; // Ensure hidden if no poster
             uploadAreaIcon.style.display = ''; // Show icon
             uploadAreaText.style.display = ''; // Show text
        }


        posterInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    posterPreview.src = e.target.result;
                    posterPreview.style.display = 'block';
                    uploadAreaIcon.style.display = 'none'; // Hide icon
                    uploadAreaText.style.display = 'none'; // Hide text
                     posterPreview.style.objectFit = 'contain'; // Use contain for new upload preview
                }
                reader.readAsDataURL(file); // Read the file as a data URL
            } else {
                // If file input is cleared (not typical for file inputs but good fallback)
                // Revert to existing poster if any, or show empty state
                 if (hasExistingPoster) {
                      // Restore existing poster preview state
                      posterPreview.src = "<?php echo !empty($poster_image) ? htmlspecialchars(WEB_UPLOAD_DIR_POSTERS . $poster_image) : '#'; ?>";
                      posterPreview.style.display = 'block';
                      uploadAreaIcon.style.display = 'none';
                      uploadAreaText.style.display = 'none';
                      posterPreview.style.objectFit = 'cover';
                 } else {
                      // Show empty state
                      posterPreview.src = '#';
                      posterPreview.style.display = 'none';
                      uploadAreaIcon.style.display = '';
                      uploadAreaText.style.display = '';
                 }
            }
        });

         // Optional: Handle drag and drop
         uploadArea.addEventListener('dragover', (e) => {
             e.preventDefault();
             uploadArea.style.borderColor = '#00ffff'; // Highlight drag area
         });

         uploadArea.addEventListener('dragleave', (e) => {
             e.preventDefault();
             uploadArea.style.borderColor = '#363636'; // Revert border color
         });

         uploadArea.addEventListener('drop', (e) => {
             e.preventDefault();
             uploadArea.style.borderColor = '#363636'; // Revert border color
             const files = e.dataTransfer.files;
             if (files.length > 0) {
                 // Check file type before assigning (basic client-side check)
                 const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                 if (allowedTypes.includes(files[0].type)) {
                      posterInput.files = files; // Assign files to input
                      posterInput.dispatchEvent(new Event('change')); // Trigger change event
                 } else {
                      alert('Invalid file type. Only JPG, PNG, GIF, WEBP are allowed.');
                 }
             }
         });


         // JavaScript to handle mutual exclusivity of trailer URL and File
         const trailerLinkInput = document.getElementById('trailer-link');
         const trailerFileInput = document.getElementById('trailer-file');

         trailerLinkInput.addEventListener('input', function() {
             // Disable file input only if the URL input has a non-empty value after trimming
             trailerFileInput.disabled = this.value.trim() !== '';
             if (trailerFileInput.disabled) {
                 trailerFileInput.value = ''; // Clear file input if URL is entered
             }
         });

         trailerFileInput.addEventListener('change', function() {
             // Disable URL input only if a file has been selected
             trailerLinkInput.disabled = this.files.length > 0;
             if (trailerLinkInput.disabled) {
                 trailerLinkInput.value = ''; // Clear URL input if file is selected
             }
         });

         // Initial check on page load (important for pre-filled values)
         document.addEventListener('DOMContentLoaded', () => {
             // Disable the counterpart input if either URL or File is already set
             if (trailerLinkInput.value.trim() !== '') {
                 trailerFileInput.disabled = true;
             } else if (trailerFileInput.files.length > 0 || "<?php echo !empty($trailer_file); ?>" === '1') {
                 // Check if a file is currently selected OR if a trailer_file exists from PHP
                 trailerLinkInput.disabled = true;
             }
              // Note: trailerFileInput.files.length > 0 check here might not work if file input was cleared client-side but not submitted
              // Relying on PHP value is more reliable for initial state from existing data.
              if ("<?php echo !empty($trailer_file); ?>" === '1') {
                   trailerLinkInput.disabled = true;
              } else if (trailerLinkInput.value.trim() !== '') {
                   trailerFileInput.disabled = true;
              }

         });

         // Helper function for HTML escaping (client-side) - good practice if displaying user input again
         function htmlspecialchars(str) {
             if (typeof str !== 'string') return str;
              // Create a temporary DOM element to leverage browser's escaping
             const div = document.createElement('div');
             div.appendChild(document.createTextNode(str));
             return div.innerHTML;
         }
    </script>
</body>
</html>