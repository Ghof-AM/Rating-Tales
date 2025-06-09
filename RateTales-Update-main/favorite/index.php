<?php
// favorite/index.php
require_once '../includes/config.php'; // Include config.php

// Redirect if not authenticated
redirectIfNotAuthenticated();

// Get authenticated user ID
$userId = $_SESSION['user_id'];

// Fetch user's favorite movies
$movies = getUserFavorites($userId);

// Handle remove favorite action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_favorite_id'])) {
    $movieIdToRemove = filter_var($_POST['remove_favorite_id'], FILTER_VALIDATE_INT);
    if ($movieIdToRemove) {
        if (removeFromFavorites($movieIdToRemove, $userId)) {
            // Success, redirect back to favorites page to refresh the list
            $_SESSION['success_message'] = 'Movie removed from favorites.';
        } else {
            $_SESSION['error_message'] = 'Failed to remove movie from favorites.';
        }
    } else {
        $_SESSION['error_message'] = 'Invalid movie ID.';
    }
    header('Location: index.php'); // Redirect to prevent form resubmission
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
    <title>RATE-TALES - Favourites</title>
    <link rel="stylesheet" href="styles.css">
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
                <li class="active"><a href="#"><i class="fas fa-heart"></i> <span>Favourites</span></a></li>
                <li><a href="../review/index.php"><i class="fas fa-star"></i> <span>Review</span></a></li>
                <li><a href="../manage/indeks.php"><i class="fas fa-film"></i> <span>Manage</span></a></li>
                <li><a href="../acc_page/index.php"><i class="fas fa-user"></i> <span>Profile</span></a></li>
            </ul>
            <div class="bottom-links">
                <ul>
                    <li><a href="../autentifikasi/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
                </ul>
            </div>
        </nav>
        <main class="main-content">
            <div class="review-header">
                <h1>My Favourites</h1>
                <div class="search-bar">
                    <input type="text" id="searchInput" placeholder="Search favourites...">
                    <button><i class="fas fa-search"></i></button>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="review-grid">
                <?php if (!empty($movies)): ?>
                    <?php foreach ($movies as $movie): ?>
                        <div class="movie-card">
                            <div class="movie-poster" onclick="window.location.href='../review/movie-details.php?id=<?php echo $movie['movie_id']; ?>'">
                                <img src="<?php echo htmlspecialchars(WEB_UPLOAD_DIR_POSTERS . $movie['poster_image'] ?? '../gambar/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                                <div class="movie-actions">
                                    <!-- Form to remove favorite -->
                                    <form action="index.php" method="POST" onsubmit="return confirm('Are you sure you want to remove this movie from favorites?');">
                                        <input type="hidden" name="remove_favorite_id" value="<?php echo $movie['movie_id']; ?>">
                                        <button type="submit" class="action-btn" title="Remove from favourites"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                            <div class="movie-details">
                                <h3><?php echo htmlspecialchars($movie['title']); ?></h3>
                                <p class="movie-info"><?php echo htmlspecialchars((new DateTime($movie['release_date']))->format('Y')); ?> | <?php echo htmlspecialchars($movie['genres'] ?? 'N/A'); ?></p>
                                <div class="rating">
                                    <div class="stars">
                                        <?php
                                        // Display average rating stars
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
                        <i class="fas fa-heart-broken"></i>
                        <p>You haven't added any movies to favorites yet.</p>
                        <p class="subtitle">Find movies in the <a href="../review/index.php">Review</a> section and add them.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const movieCards = document.querySelectorAll('.review-grid .movie-card');
            const reviewGrid = document.querySelector('.review-grid');
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
                            <p>No favorites found matching "${htmlspecialchars(searchTerm)}"</p>
                            <p class="subtitle">Try a different search term</p>
                        `;
                        reviewGrid.appendChild(emptyState);
                    } else {
                        // Update text if search empty state already exists
                        searchEmptyState.querySelector('p:first-of-type').innerText = `No favorites found matching "${htmlspecialchars(searchTerm)}"`;
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

            // Trigger search when search button is clicked
            const searchButton = document.querySelector('.search-bar button');
            if (searchButton) {
                searchButton.addEventListener('click', function() {
                    const event = new Event('input');
                    searchInput.dispatchEvent(event);
                });
            }
        });

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
</body>

</html>