<?php
// manage/edit.php
require_once '../includes/config.php'; // Include config.php

// Redirect if not authenticated
redirectIfNotAuthenticated();

$userId = $_SESSION['user_id'];
$movieId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Redirect if no movie ID is provided or invalid
if (!$movieId) {
    $_SESSION['error_message'] = 'Invalid movie ID.';
    header('Location: indeks.php');
    exit;
}

// Fetch the movie data
$movie = getMovieById($movieId);

// Check if the movie exists and if the current user is the uploader
if (!$movie || $movie['uploaded_by'] != $userId) {
    $_SESSION['error_message'] = 'Movie not found or you do not have permission to edit it.';
    header('Location: indeks.php');
    exit;
}

$success_message = null;
$error_message = null;

// Handle form submission for updating movie
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = filter_var($_POST['title'], FILTER_SANITIZE_STRING);
    $summary = filter_var($_POST['summary'], FILTER_SANITIZE_STRING);
    $release_date = filter_var($_POST['release_date'], FILTER_SANITIZE_STRING);
    $duration_hours = filter_var($_POST['duration_hours'], FILTER_VALIDATE_INT);
    $duration_minutes = filter_var($_POST['duration_minutes'], FILTER_VALIDATE_INT);
    $age_rating = filter_var($_POST['age_rating'], FILTER_SANITIZE_STRING);
    $trailer_url = filter_var($_POST['trailer_url'], FILTER_VALIDATE_URL);
    $genres = isset($_POST['genres']) && is_array($_POST['genres']) ? $_POST['genres'] : [];

    $newPosterImage = $movie['poster_image']; // Keep existing if not updated
    $newTrailerFile = $movie['trailer_file']; // Keep existing if not updated

    // Validate inputs
    if (empty($title) || empty($summary) || empty($release_date) || empty($age_rating)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        try {
            $pdo->beginTransaction();

            // Handle poster image upload
            if (isset($_FILES['poster_image']) && $_FILES['poster_image']['error'] === UPLOAD_ERR_OK) {
                $posterFileName = basename($_FILES['poster_image']['name']);
                $posterTargetPath = UPLOAD_DIR_POSTERS . $posterFileName;
                $posterFileType = strtolower(pathinfo($posterTargetPath, PATHINFO_EXTENSION));
                $allowedPosterTypes = ['jpg', 'jpeg', 'png', 'gif'];

                if (in_array($posterFileType, $allowedPosterTypes)) {
                    if (move_uploaded_file($_FILES['poster_image']['tmp_name'], $posterTargetPath)) {
                        // Delete old poster if it exists and is different
                        if ($movie['poster_image'] && $movie['poster_image'] !== $posterFileName && file_exists(UPLOAD_DIR_POSTERS . $movie['poster_image'])) {
                            unlink(UPLOAD_DIR_POSTERS . $movie['poster_image']);
                        }
                        $newPosterImage = $posterFileName;
                    } else {
                        throw new Exception('Failed to upload poster image.');
                    }
                } else {
                    throw new Exception('Invalid poster image type. Only JPG, JPEG, PNG, GIF are allowed.');
                }
            }

            // Handle trailer file upload
            if (isset($_FILES['trailer_file']) && $_FILES['trailer_file']['error'] === UPLOAD_ERR_OK) {
                $trailerFileName = basename($_FILES['trailer_file']['name']);
                $trailerTargetPath = UPLOAD_DIR_TRAILERS . $trailerFileName;
                $trailerFileType = strtolower(pathinfo($trailerTargetPath, PATHINFO_EXTENSION));
                $allowedTrailerTypes = ['mp4', 'avi', 'mov', 'webm'];

                if (in_array($trailerFileType, $allowedTrailerTypes)) {
                    if (move_uploaded_file($_FILES['trailer_file']['tmp_name'], $trailerTargetPath)) {
                        // Delete old trailer if it exists and is different
                        if ($movie['trailer_file'] && $movie['trailer_file'] !== $trailerFileName && file_exists(UPLOAD_DIR_TRAILERS . $movie['trailer_file'])) {
                            unlink(UPLOAD_DIR_TRAILERS . $movie['trailer_file']);
                        }
                        $newTrailerFile = $trailerFileName;
                    } else {
                        throw new Exception('Failed to upload trailer file.');
                    }
                } else {
                    throw new Exception('Invalid trailer file type. Only MP4, AVI, MOV, WEBM are allowed.');
                }
            }

            // Update movie details
            updateMovie(
                $movieId,
                $title,
                $summary,
                $release_date,
                $duration_hours,
                $duration_minutes,
                $age_rating,
                $newPosterImage,
                $trailer_url,
                $newTrailerFile
            );

            // Update genres: first delete existing, then add new ones
            deleteAllMovieGenres($movieId);
            foreach ($genres as $genre) {
                addMovieGenre($movieId, $genre);
            }

            $pdo->commit();
            $_SESSION['success_message'] = 'Movie updated successfully.';
            // Refresh movie data after update for display
            $movie = getMovieById($movieId);
            header('Location: indeks.php'); // Redirect to manage page
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Movie update error: " . $e->getMessage());
            $error_message = 'Error updating movie: ' . $e->getMessage();
        }
    }
}

// Get messages from session (if any)
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : $success_message;
unset($_SESSION['success_message']);
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : $error_message;
unset($_SESSION['error_message']);

// Prepare genres for checkbox display
$allGenres = ['Action', 'Adventure', 'Animation', 'Comedy', 'Crime', 'Documentary', 'Drama', 'Family', 'Fantasy', 'History', 'Horror', 'Music', 'Mystery', 'Romance', 'Science Fiction', 'Thriller', 'War', 'Western'];
$movieGenresArray = explode(', ', $movie['genres'] ?? ''); // Convert comma-separated string to array
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Movie - RatingTales</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="../review/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Specific styles for edit/upload form */
        .form-container {
            background-color: var(--card-bg-color);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 800px;
            margin: 20px auto;
            color: var(--text-color);
        }

        .form-container h2 {
            text-align: center;
            margin-bottom: 25px;
            color: var(--primary-color);
            font-size: 2em;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: var(--text-color);
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="date"],
        .form-group input[type="url"],
        .form-group textarea,
        .form-group select {
            width: calc(100% - 20px);
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background-color: var(--input-bg-color);
            color: var(--text-color);
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="number"]:focus,
        .form-group input[type="date"]:focus,
        .form-group input[type="url"]:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .duration-group {
            display: flex;
            gap: 15px;
        }

        .duration-group input {
            flex: 1;
        }

        .file-upload-group {
            margin-bottom: 20px;
        }

        .file-upload-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: var(--text-color);
        }

        .file-upload-wrapper {
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: var(--input-bg-color);
        }

        .file-upload-wrapper input[type="file"] {
            flex-grow: 1;
            padding: 5px 0;
            color: var(--text-color);
        }

        .file-upload-wrapper .current-file-display {
            font-size: 0.9em;
            color: var(--text-color-light);
            word-break: break-all;
            flex-shrink: 0;
            padding-right: 10px;
        }

        .genres-group {
            margin-bottom: 20px;
        }

        .genres-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
            color: var(--text-color);
        }

        .genre-checkboxes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
        }

        .genre-checkboxes label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: normal;
            font-size: 0.95em;
        }

        .genre-checkboxes input[type="checkbox"] {
            margin-right: 8px;
            transform: scale(1.1);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }

        .form-actions button {
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.1em;
            transition: background-color 0.3s ease;
        }

        .form-actions .submit-btn {
            background-color: var(--primary-color);
            color: #fff;
        }

        .form-actions .submit-btn:hover {
            background-color: var(--primary-color-dark);
        }

        .form-actions .cancel-btn {
            background-color: var(--button-bg-color);
            color: var(--button-text-color);
        }

        .form-actions .cancel-btn:hover {
            background-color: #ddd;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 1em;
            text-align: center;
        }

        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="sidebar">
            <div class="logo">
                <h2>RATE-TALES</h2>
            </div>
            <ul class="nav-links">
                <li><a href="../beranda/index.php"><i class="fas fa-home"></i> <span>Home</span></a></li>
                <li><a href="../favorite/index.php"><i class="fas fa-heart"></i> <span>Favourites</span></a></li>
                <li><a href="../review/index.php"><i class="fas fa-star"></i> <span>Review</span></a></li>
                <li class="active"><a href="indeks.php"><i class="fas fa-film"></i> <span>Manage</span></a></li>
                <li><a href="../acc_page/index.php"><i class="fas fa-user"></i> <span>Profile</span></a></li>
            </ul>
            <ul class="bottom-links">
                <li><a href="../autentifikasi/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>

        <main class="main-content">
            <div class="header">
                <h1>Edit Movie</h1>
            </div>

            <?php if ($success_message) : ?>
                <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if ($error_message) : ?>
                <div class="alert error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="form-container">
                <form action="edit.php?id=<?php echo htmlspecialchars($movieId); ?>" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="title">Movie Title</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($movie['title'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="summary">Summary</label>
                        <textarea id="summary" name="summary" required><?php echo htmlspecialchars($movie['summary'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="release_date">Release Date</label>
                        <input type="date" id="release_date" name="release_date" value="<?php echo htmlspecialchars($movie['release_date'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Duration</label>
                        <div class="duration-group">
                            <input type="number" id="duration_hours" name="duration_hours" placeholder="Hours" min="0" value="<?php echo htmlspecialchars($movie['duration_hours'] ?? ''); ?>" required>
                            <input type="number" id="duration_minutes" name="duration_minutes" placeholder="Minutes" min="0" max="59" value="<?php echo htmlspecialchars($movie['duration_minutes'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="age_rating">Age Rating</label>
                        <select id="age_rating" name="age_rating" required>
                            <option value="">Select Age Rating</option>
                            <option value="G" <?php echo ($movie['age_rating'] == 'G') ? 'selected' : ''; ?>>G - General Audiences</option>
                            <option value="PG" <?php echo ($movie['age_rating'] == 'PG') ? 'selected' : ''; ?>>PG - Parental Guidance Suggested</option>
                            <option value="PG-13" <?php echo ($movie['age_rating'] == 'PG-13') ? 'selected' : ''; ?>>PG-13 - Parents Strongly Cautioned</option>
                            <option value="R" <?php echo ($movie['age_rating'] == 'R') ? 'selected' : ''; ?>>R - Restricted</option>
                            <option value="NC-17" <?php echo ($movie['age_rating'] == 'NC-17') ? 'selected' : ''; ?>>NC-17 - Adults Only</option>
                        </select>
                    </div>

                    <div class="file-upload-group">
                        <label for="poster_image">Poster Image</label>
                        <div class="file-upload-wrapper">
                            <input type="file" id="poster_image" name="poster_image" accept="image/*">
                            <?php if (!empty($movie['poster_image'])) : ?>
                                <span class="current-file-display">Current: <?php echo htmlspecialchars($movie['poster_image']); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($movie['poster_image'])) : ?>
                            <small style="color: var(--text-color-light); display: block; margin-top: 5px;">Leave blank to keep current poster.</small>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="trailer_url">Trailer URL (YouTube, Vimeo, etc.)</label>
                        <input type="url" id="trailer_url" name="trailer_url" value="<?php echo htmlspecialchars($movie['trailer_url'] ?? ''); ?>" placeholder="e.g., https://www.youtube.com/watch?v=dQw4w9WgXcQ">
                        <small style="color: var(--text-color-light);">If you upload a file, the URL will be ignored.</small>
                    </div>

                    <div class="file-upload-group">
                        <label for="trailer_file">Trailer File (MP4, AVI, MOV, WEBM)</label>
                        <div class="file-upload-wrapper">
                            <input type="file" id="trailer_file" name="trailer_file" accept="video/*">
                            <?php if (!empty($movie['trailer_file'])) : ?>
                                <span class="current-file-display">Current: <?php echo htmlspecialchars($movie['trailer_file']); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($movie['trailer_file'])) : ?>
                            <small style="color: var(--text-color-light); display: block; margin-top: 5px;">Leave blank to keep current trailer or use URL.</small>
                        <?php endif; ?>
                    </div>

                    <div class="genres-group">
                        <label>Genres</label>
                        <div class="genre-checkboxes">
                            <?php foreach ($allGenres as $genre) : ?>
                                <label>
                                    <input type="checkbox" name="genres[]" value="<?php echo htmlspecialchars($genre); ?>" <?php echo in_array($genre, $movieGenresArray) ? 'checked' : ''; ?>>
                                    <?php echo htmlspecialchars($genre); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="indeks.php" class="cancel-btn button">Cancel</a>
                        <button type="submit" class="submit-btn">Update Movie</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>

</html>