<?php
// manage/indeks.php
require_once '../includes/config.php'; // Include config.php

// Redirect if not authenticated
redirectIfNotAuthenticated();

// Get authenticated user ID
$userId = $_SESSION['user_id'];

// Fetch movies uploaded by the current user
$movies = getUserUploadedMovies($userId);

// Handle delete movie action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_movie_id'])) {
    $movieIdToDelete = filter_var($_POST['delete_movie_id'], FILTER_VALIDATE_INT);

    if ($movieIdToDelete) {
        try {
            $pdo->beginTransaction();

            // IMPORTANT: Due to ON DELETE CASCADE in the schema, deleting the movie
            // from the 'movies' table will automatically delete associated rows
            // in 'movie_genres', 'reviews', and 'favorites'.

            // Prepare and execute the delete statement for the movie table
            // Ensure only the uploader can delete their movie
            $stmt = $pdo->prepare("DELETE FROM movies WHERE movie_id = ? AND uploaded_by = ?");
            $stmt->execute([$movieIdToDelete, $userId]);

            // Check if a row was actually deleted
            if ($stmt->rowCount() > 0) {
                // Optional: Delete associated files (poster, trailer) from the server
                // This is more complex as you need the file paths BEFORE deleting the DB record.
                // To do this safely, you would fetch the movie first, get the paths, then start transaction,
                // delete DB record, commit, then delete files.
                // For simplicity here, file deletion is omitted, but consider adding it.
                // Example (requires fetching movie before deletion):
                /*
                 $old_movie = getMovieById($movieIdToDelete); // Fetch BEFORE delete
                 if ($old_movie) {
                      if (!empty($old_movie['poster_image']) && file_exists(UPLOAD_DIR_POSTERS . $old_movie['poster_image'])) {
                          unlink(UPLOAD_DIR_POSTERS . $old_movie['poster_image']);
                      }
                      if (!empty($old_movie['trailer_file']) && file_exists(UPLOAD_DIR_TRAILERS . $old_movie['trailer_file'])) {
                           unlink(UPLOAD_DIR_TRAILERS . $old_movie['trailer_file']);
                      }
                 }
                 */

                $pdo->commit();
                $_SESSION['success_message'] = 'Movie deleted successfully.';
            } else {
                // Movie not found OR not uploaded by the current user
                $pdo->rollBack();
                $_SESSION['error_message'] = 'Movie not found or you do not have permission to delete it.';
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Database error during movie deletion: " . $e->getMessage());
            $_SESSION['error_message'] = 'An internal error occurred while deleting the movie.';
        }
    } else {
        $_SESSION['error_message'] = 'Invalid movie ID.';
    }

    header('Location: indeks.php'); // Redirect to prevent form resubmission
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
    <title>Manage Movies - RatingTales</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="../review/styles.css"> <!-- Include review styles for movie card look -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <!-- Sidebar -->
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

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>Manage Movies</h1>
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search movies...">
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>


            <!-- Movies Grid -->
            <div class="movies-grid review-grid">
                <?php if (empty($movies)): ?>
                    <div class="empty-state full-width">
                        <i class="fas fa-film"></i>
                        <p>No movies uploaded yet</p>
                        <p class="subtitle">Start adding your movies by clicking the upload button below</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($movies as $movie): ?>
                        <div class="movie-card">
                            <div class="movie-poster">
                                <img src="<?php echo htmlspecialchars(WEB_UPLOAD_DIR_POSTERS . $movie['poster_image'] ?? '../gambar/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                                <div class="movie-actions">
                                    <!-- Edit Button -->
                                    <a href="edit.php?id=<?php echo $movie['movie_id']; ?>" class="action-btn" title="Edit Movie">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    <!-- Delete Button -->
                                    <form action="indeks.php" method="POST" onsubmit="return confirm('Are you sure you want to delete the movie &quot;<?php echo htmlspecialchars($movie['title']); ?>&quot;? This action cannot be undone.');">
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
                                        $average_rating = (float)($movie['average_rating'] ?? 0);
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
                                    <span class="rating-count">(<?php echo htmlspecialchars($average_rating); ?>)</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <!-- Edit All button - currently not functional for multi-edit -->
                <!-- <button class="edit-all-btn" title="Edit All Movies">
                    <i class="fas fa-edit"></i>
                </button> -->
                <a href="upload.php" class="upload-btn" title="Upload New Movie">
                    <i class="fas fa-plus"></i>
                </a>
            </div>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const movieCards = document.querySelectorAll('.movies-grid .movie-card');
            const moviesGrid = document.querySelector('.movies-grid');
            const initialEmptyState = document.querySelector('.empty-state.full-width');


            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                let visibleCardCount = 0;

                movieCards.forEach(card => {
                    const title = card.querySelector('h3').textContent.toLowerCase();
                    const info = card.querySelector('.movie-info').textContent.toLowerCase();

                    if (title.includes(searchTerm) || info.includes(searchTerm)) {
                        card.style.display = '';
                        visibleCardCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Handle empty state visibility
                const searchEmptyState = document.querySelector('.search-empty-state');

                if (visibleCardCount === 0 && searchTerm !== '') {
                    if (!searchEmptyState) {
                        // Hide the initial empty state if it exists and we are searching
                        if (initialEmptyState) initialEmptyState.style.display = 'none';

                        const emptyState = document.createElement('div');
                        emptyState.className = 'empty-state search-empty-state full-width';
                        emptyState.innerHTML = `
                            <i class="fas fa-search"></i>
                            <p>No uploaded movies found matching "${htmlspecialchars(searchTerm)}"</p>
                            <p class="subtitle">Try a different search term</p>
                        `;
                        moviesGrid.appendChild(emptyState);
                    } else {
                        // Update text if search empty state already exists
                        searchEmptyState.querySelector('p:first-of-type').innerText = `No uploaded movies found matching "${htmlspecialchars(searchTerm)}"`;
                        searchEmptyState.style.display = 'flex';
                    }
                } else {
                    // Remove search empty state if cards are visible or search is cleared
                    if (searchEmptyState) {
                        searchEmptyState.remove();
                    }
                    // Show initial empty state if no movies were loaded AND search is cleared
                    if (movieCards.length === 0 && searchTerm === '' && initialEmptyState) {
                        initialEmptyState.style.display = 'flex';
                    }
                }
            });
            // Trigger search when search button is clicked (if you add one)
            // const searchButton = document.querySelector('.search-bar button');
            // if(searchButton) {
            //     searchButton.addEventListener('click', function() {
            //         const event = new Event('input');
            //         searchInput.dispatchEvent(event);
            //     });
            // }
        });

        // Helper function for HTML escaping (client-side)
        function htmlspecialchars(str) {
            if (typeof str !== 'string') return str;
            return str.replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;') // Keep > as is for HTML structure, but usually encode
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    </script>
</body>

</html>