<?php
// manage/upload.php
require_once '../includes/config.php'; // Include config.php

// Redirect if not authenticated
redirectIfNotAuthenticated();

// Get authenticated user ID
$userId = $_SESSION['user_id'];

$error_message = null;
$success_message = null;

// Initialize variables for form values (useful for pre-filling on error)
$title = $_SESSION['upload_form_data']['title'] ?? '';
$summary = $_SESSION['upload_form_data']['summary'] ?? '';
$release_date = $_SESSION['upload_form_data']['release_date'] ?? '';
$duration_hours = $_SESSION['upload_form_data']['duration_hours'] ?? '';
$duration_minutes = $_SESSION['upload_form_data']['duration_minutes'] ?? '';
$age_rating = $_SESSION['upload_form_data']['age_rating'] ?? '';
$genres = $_SESSION['upload_form_data']['genres'] ?? [];
$trailer_url = $_SESSION['upload_form_data']['trailer_url'] ?? '';

// Clear stored form data from session
unset($_SESSION['upload_form_data']);


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Collect and Sanitize Input
    $title = trim($_POST['movie-title'] ?? '');
    $summary = trim($_POST['movie-summary'] ?? '');
    $release_date = $_POST['release-date'] ?? '';
    $duration_hours = filter_var($_POST['duration-hours'] ?? '', FILTER_VALIDATE_INT); // Use '' for initial state check
    $duration_minutes = filter_var($_POST['duration-minutes'] ?? '', FILTER_VALIDATE_INT);
    $age_rating = $_POST['age-rating'] ?? '';
    $genres = $_POST['genre'] ?? [];
    $trailer_url = trim($_POST['trailer-link'] ?? '');
    // Trailer file will be handled via $_FILES

    // Store current inputs in session in case of errors and redirect
    $_SESSION['upload_form_data'] = [
        'title' => $title,
        'summary' => $summary,
        'release_date' => $release_date,
        'duration_hours' => $_POST['duration-hours'] ?? '', // Store raw input for display
        'duration_minutes' => $_POST['duration-minutes'] ?? '', // Store raw input for display
        'age_rating' => $age_rating,
        'genres' => $genres,
        'trailer_url' => $trailer_url,
        // File inputs cannot be easily stored/retained in session
    ];


    $errors = [];

    // 2. Validate Input
    if (empty($title)) $errors[] = 'Movie Title is required.';
    if (empty($release_date)) $errors[] = 'Release Date is required.';
    if ($duration_hours === false || $duration_hours === '' || $duration_hours < 0) $errors[] = 'Valid Duration (Hours) is required.';
    if ($duration_minutes === false || $duration_minutes === '' || $duration_minutes < 0 || $duration_minutes > 59) $errors[] = 'Valid Duration (Minutes) is required (0-59).';
    if (empty($age_rating)) $errors[] = 'Age Rating is required.';
    if (empty($genres)) $errors[] = 'At least one Genre must be selected.';

    // Validate genre values against allowed ENUM values
    $allowed_genres = ['action', 'adventure', 'comedy', 'drama', 'horror', 'supernatural', 'animation', 'sci-fi'];
    foreach ($genres as $genre) {
        if (!in_array($genre, $allowed_genres)) {
            $errors[] = 'Invalid genre selected.';
            break;
        }
    }

    // 3. Handle File Uploads (Poster)
    $poster_image_path = null;
    $poster_upload_success = false;

    if (isset($_FILES['movie-poster']) && $_FILES['movie-poster']['error'] === UPLOAD_ERR_OK) {
        $posterFile = $_FILES['movie-poster'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']; // Allowed image types
        $maxFileSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($posterFile['type'], $allowedTypes)) {
            $errors[] = 'Invalid poster file type. Only JPG, PNG, GIF, WEBP are allowed.';
        }
        if ($posterFile['size'] > $maxFileSize) {
            $errors[] = 'Poster file is too large. Maximum size is 5MB.';
        }

        // If no file errors yet, process the upload
        if (empty($errors)) {
            $fileExtension = pathinfo($posterFile['name'], PATHINFO_EXTENSION);
            $newFileName = uniqid('poster_', true) . '.' . $fileExtension;
            $destination = UPLOAD_DIR_POSTERS . $newFileName; // Use absolute path for moving

            if (move_uploaded_file($posterFile['tmp_name'], $destination)) {
                $poster_image_path = $newFileName; // Store just the filename in DB
                $poster_upload_success = true;
            } else {
                $errors[] = 'Failed to upload poster file. Check server permissions.';
            }
        }
    } else {
        // Poster is required for upload
        $errors[] = 'Movie Poster file is required.';
        // Handle specific upload errors if file was attempted but failed
        if (isset($_FILES['movie-poster']) && $_FILES['movie-poster']['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Poster upload error: ' . $_FILES['movie-poster']['error'];
        }
    }

    // 4. Handle File Uploads (Trailer File - Optional) and Trailer URL
    $trailer_file_path = null;
    $trailer_upload_success = false; // Track if trailer file upload was successful

    if (isset($_FILES['trailer-file']) && $_FILES['trailer-file']['error'] === UPLOAD_ERR_OK) {
        $trailerFile = $_FILES['trailer-file'];
        $allowedTypes = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime']; // Allowed video types
        $maxFileSize = 50 * 1024 * 1024; // 50MB

        if (!in_array($trailerFile['type'], $allowedTypes)) {
            $errors[] = 'Invalid trailer file type. Only MP4, WebM, Ogg, MOV are allowed.';
        }
        if ($trailerFile['size'] > $maxFileSize) {
            $errors[] = 'Trailer file is too large. Maximum size is 50MB.';
        }

        // If no file errors yet, process the upload
        if (empty($errors)) {
            $fileExtension = pathinfo($trailerFile['name'], PATHINFO_EXTENSION);
            $newFileName = uniqid('trailer_', true) . '.' . $fileExtension;
            $destination = UPLOAD_DIR_TRAILERS . $newFileName; // Use absolute path for moving

            if (move_uploaded_file($trailerFile['tmp_name'], $destination)) {
                $trailer_file_path = $newFileName; // Store just the filename in DB
                $trailer_upload_success = true;
                $trailer_url = null; // If file is uploaded, prioritize file and clear the URL
            } else {
                $errors[] = 'Failed to upload trailer file. Check server permissions.';
            }
        }
    } else {
        // Handle specific upload errors for trailer file if attempted
        if (isset($_FILES['trailer-file']) && $_FILES['trailer-file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Trailer file upload error: ' . $_FILES['trailer-file']['error'];
        }
    }

    // Check if at least one trailer source is provided
    if (empty($trailer_url) && empty($trailer_file_path)) {
        $errors[] = 'Either a Trailer URL or a Trailer File is required.';
    }


    // 5. If no errors, insert into database
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insert movie into movies table
            $movieId = createMovie(
                $title,
                $summary,
                $release_date,
                $duration_hours,
                $duration_minutes,
                $age_rating,
                $poster_image_path, // Filename from successful upload
                $trailer_url,       // URL if provided and file not uploaded
                $trailer_file_path, // Filename if file uploaded
                $userId             // Log the uploader
            );

            // Insert genres into movie_genres table
            foreach ($genres as $genre) {
                addMovieGenre($movieId, $genre);
            }

            $pdo->commit();

            $_SESSION['success_message'] = 'Movie uploaded successfully!';
            header('Location: indeks.php'); // Redirect to manage page
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Database error during movie upload: " . $e->getMessage());
            $errors[] = 'An internal error occurred while saving the movie.';

            // Clean up uploaded files if DB insertion failed
            if ($poster_upload_success && file_exists(UPLOAD_DIR_POSTERS . $poster_image_path)) {
                unlink(UPLOAD_DIR_POSTERS . $poster_image_path);
            }
            if ($trailer_upload_success && file_exists(UPLOAD_DIR_TRAILERS . $trailer_file_path)) {
                unlink(UPLOAD_DIR_TRAILERS . $trailer_file_path);
            }
        }
    }

    // If there were any errors, set the error message and redirect back
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode('<br>', $errors);
        // Form data already saved to session at the start
        header('Location: upload.php'); // Redirect back to show errors and pre-fill form
        exit;
    }
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
    <title>Upload Movie - RatingTales</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="upload.css">
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
                <li><a href="../autentifikasi/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <div class="upload-container">
                <h1>Upload New Movie</h1>

                <?php if ($success_message): ?>
                    <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert error"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <form class="upload-form" action="upload.php" method="post" enctype="multipart/form-data">
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
                                <p class="trailer-note" style="margin-top: 10px;">Only one trailer source (URL or File) is needed.</p>
                            </div>
                        </div>

                        <div class="form-side">
                            <div class="poster-upload">
                                <label for="movie-poster">Movie Poster</label>
                                <div class="upload-area" id="upload-area">
                                    <i class="fas fa-image"></i>
                                    <p>Click or drag image here</p>
                                    <input type="file" id="movie-poster" name="movie-poster" accept="image/*" required>
                                    <img id="poster-preview" src="#" alt="Poster Preview" style="display: none; max-width: 100%; max-height: 100%; object-fit: contain;">
                                </div>
                                <p class="trailer-note" style="margin-top: 5px;">(Recommended: Aspect Ratio 2:3, Max 5MB)</p>
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
                        <button type="submit" class="submit-btn">Upload Movie</button>
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

        posterInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    posterPreview.src = e.target.result;
                    posterPreview.style.display = 'block';
                    uploadAreaIcon.style.display = 'none'; // Hide icon
                    uploadAreaText.style.display = 'none'; // Hide text
                    posterPreview.style.objectFit = 'contain'; // Set object-fit for preview
                }
                reader.readAsDataURL(file); // Read the file as a data URL
            } else {
                // Reset if no file is selected
                posterPreview.src = '#';
                posterPreview.style.display = 'none';
                uploadAreaIcon.style.display = ''; // Show icon
                uploadAreaText.style.display = ''; // Show text
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
            if (this.value.trim() !== '') {
                trailerFileInput.disabled = true; // Disable file input if URL is entered
                // Also clear file input if a URL is entered after a file was selected
                trailerFileInput.value = '';
            } else {
                trailerFileInput.disabled = false; // Enable file input if URL is empty
            }
        });

        trailerFileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                trailerLinkInput.disabled = true; // Disable URL input if file is selected
                // Also clear URL input if a file is selected after a URL was entered
                trailerLinkInput.value = '';
            } else {
                trailerLinkInput.disabled = false; // Enable URL input if file selection is cleared
            }
        });

        // Initial check on page load (important if form data is pre-filled after error)
        document.addEventListener('DOMContentLoaded', () => {
            if (trailerLinkInput.value.trim() !== '') {
                trailerFileInput.disabled = true;
            } else if (trailerFileInput.files.length > 0) { // Check files property directly
                trailerLinkInput.disabled = true;
            }
            // If editing and poster already exists, show it
            // This requires fetching movie data in PHP first and pre-filling an attribute on #poster-preview
            // Example (assuming PHP provides $existing_poster_url):
            // const existingPosterUrl = "<?php // echo htmlspecialchars($existing_poster_url ?? ''); 
                                            ?>";
            // if (existingPosterUrl) {
            //     posterPreview.src = existingPosterUrl;
            //     posterPreview.style.display = 'block';
            //     uploadAreaIcon.style.display = 'none';
            //     uploadAreaText.style.display = 'none';
            //      posterPreview.style.objectFit = 'cover'; // Use cover for existing poster
            // }
        });

        // Helper function for HTML escaping (client-side) - good practice if displaying user input again
        function htmlspecialchars(str) {
            if (typeof str !== 'string') return str;
            return str.replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    </script>
</body>

</html>