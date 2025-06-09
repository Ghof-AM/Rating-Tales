<?php
// review/movie-details.php
require_once '../includes/config.php'; // Include config.php

// Redirect if not authenticated
redirectIfNotAuthenticated();

// Get authenticated user ID
$userId = $_SESSION['user_id'];
$user = getAuthenticatedUser(); // Fetch user details for comments (username, profile_image)

// Get movie ID from URL parameter
$movieId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

// Handle movie not found if ID is missing or invalid
if (!$movieId) {
    $_SESSION['error_message'] = 'Invalid movie ID.';
    header('Location: index.php');
    exit;
}

// Fetch movie details from the database
$movie = getMovieById($movieId);

// Handle movie not found in DB
if (!$movie) {
    $_SESSION['error_message'] = 'Movie not found.';
    header('Location: index.php');
    exit;
}

// Fetch comments for the movie
$comments = getMovieReviews($movieId);

// Check if the movie is favorited by the current user
$isFavorited = isMovieFavorited($movieId, $userId);

// Handle comment and rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $commentText = trim($_POST['comment'] ?? '');
    $submittedRating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_FLOAT);

    // Basic validation for rating
    if ($submittedRating === false || $submittedRating < 0.5 || $submittedRating > 5) {
        $_SESSION['error_message'] = 'Please provide a valid rating (0.5 to 5).';
    } else {
        // Allow empty comment with rating, but trim it
        if (createReview($movieId, $userId, $submittedRating, $commentText)) {
            $_SESSION['success_message'] = 'Your review has been submitted!';
        } else {
            $_SESSION['error_message'] = 'Failed to submit your review.';
        }
    }

    header("Location: movie-details.php?id={$movieId}"); // Redirect after processing
    exit;
}


// Handle Favorite/Unfavorite action (using POST for robustness)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_favorite'])) {
    $action = $_POST['toggle_favorite']; // 'favorite' or 'unfavorite'
    $targetMovieId = filter_var($_POST['movie_id'] ?? null, FILTER_VALIDATE_INT);

    if ($targetMovieId && $targetMovieId === $movieId) { // Ensure action is for the current movie
        if ($action === 'favorite') {
            if (addToFavorites($targetMovieId, $userId)) {
                $_SESSION['success_message'] = 'Movie added to favorites!';
            } else {
                $_SESSION['error_message'] = 'Failed to add movie to favorites (maybe already added?).';
            }
        } elseif ($action === 'unfavorite') {
            if (removeFromFavorites($targetMovieId, $userId)) {
                $_SESSION['success_message'] = 'Movie removed from favorites!';
            } else {
                $_SESSION['error_message'] = 'Failed to remove movie from favorites.';
            }
        } else {
            $_SESSION['error_message'] = 'Invalid favorite action.';
        }
    } else {
        $_SESSION['error_message'] = 'Invalid movie ID for favorite action.';
    }
    // Redirect back to the movie details page
    header("Location: movie-details.php?id={$movieId}");
    exit;
}


// Get messages from session
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
unset($_SESSION['success_message']);
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;
unset($_SESSION['error_message']);

// Determine poster image source (using web accessible path)
$posterSrc = htmlspecialchars(WEB_UPLOAD_DIR_POSTERS . $movie['poster_image'] ?? '../gambar/placeholder.jpg');

// Determine trailer source (using web accessible paths)
$trailerSrc = null; // Changed from $trailerUrl to $trailerSrc for clarity
$isYouTubeTrailer = false; // Flag to indicate if it's a YouTube trailer

if (!empty($movie['trailer_url'])) {
    // Assume YouTube URL and extract video ID
    parse_str(parse_url($movie['trailer_url'], PHP_URL_QUERY), $vars);
    $youtubeVideoId = $vars['v'] ?? null;
    if ($youtubeVideoId) {
        $trailerSrc = "https://www.youtube.com/embed/{$youtubeVideoId}"; // CORRECTED YouTube embed URL
        $isYouTubeTrailer = true;
    } else {
        // Fallback for other video URL types if needed (basic passthrough)
        $trailerSrc = htmlspecialchars($movie['trailer_url']);
    }
} elseif (!empty($movie['trailer_file'])) {
    // Assume local file path, construct web accessible URL
    $trailerSrc = htmlspecialchars(WEB_UPLOAD_DIR_TRAILERS . $movie['trailer_file']); // Adjust path if necessary
    $isYouTubeTrailer = false;
}

// Format duration
$duration_display = '';
if ($movie['duration_hours'] > 0) {
    $duration_display .= $movie['duration_hours'] . 'h ';
}
$duration_display .= $movie['duration_minutes'] . 'm';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($movie['title']); ?> - RATE-TALES</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Specific styles for the movie details page */
        .movie-details-page {
            padding: 2rem;
            color: white;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #00ffff;
            text-decoration: none;
            margin-bottom: 2rem;
            font-size: 1.1rem;
            transition: color 0.3s;
        }

        .back-button:hover {
            color: #00cccc;
        }

        .movie-header {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .movie-poster-large {
            width: 300px;
            height: 450px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            flex-shrink: 0;
            margin: auto;
            /* Center if wraps */
            /* Fallback background if image fails */
            background-color: #363636;
        }

        @media (max-width: 768px) {
            .movie-poster-large {
                width: 200px;
                height: 300px;
            }
        }


        .movie-poster-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            /* Hide broken image icon */
            color: transparent;
            font-size: 0;
        }

        /* Show alt text or a fallback if image fails to load */
        .movie-poster-large img::before {
            content: attr(alt);
            display: block;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #363636;
            color: #ffffff;
            text-align: center;
            padding-top: 50%;
            font-size: 16px;
        }


        .movie-info-large {
            flex: 1;
            min-width: 300px;
        }

        .movie-title-large {
            font-size: 2.8rem;
            margin-bottom: 1rem;
            color: #00ffff;
        }

        .movie-meta {
            color: #888;
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }

        .rating-large {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .rating-large .stars {
            color: #ffd700;
            font-size: 1.8rem;
        }

        .rating-large .stars i {
            margin-right: 3px;
        }

        .movie-description {
            line-height: 1.8;
            margin-bottom: 2rem;
            color: #ccc;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .action-button {
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            transition: all 0.3s ease;
            font-weight: bold;
        }

        .watch-trailer {
            background-color: #e50914;
            color: white;
        }

        .add-favorite {
            background-color: #333;
            color: white;
        }

        .add-favorite.favorited {
            background-color: #00ffff;
            color: #1a1a1a;
        }


        .action-button:hover {
            transform: translateY(-3px);
            opacity: 0.9;
        }

        .comments-section {
            margin-top: 3rem;
            background-color: #242424;
            padding: 20px;
            border-radius: 15px;
        }

        .comments-header {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            color: #00ffff;
            border-bottom: 1px solid #333;
            padding-bottom: 15px;
        }

        .comment-input-area {
            margin-bottom: 2rem;
            padding: 15px;
            background-color: #1a1a1a;
            border-radius: 10px;
        }

        .comment-input-area h3 {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: #ccc;
        }

        .rating-input-stars {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 1rem;
        }

        .rating-input-stars i {
            font-size: 1.5rem;
            color: #888;
            cursor: pointer;
            transition: color 0.2s, transform 0.2s;
        }

        .rating-input-stars i:hover,
        .rating-input-stars i.hovered,
        .rating-input-stars i.rated {
            color: #ffd700;
            transform: scale(1.1);
        }

        .rating-input-stars input[type="hidden"] {
            display: none;
        }


        .comment-input {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 10px;
            background-color: #333;
            color: white;
            margin-bottom: 1rem;
            resize: vertical;
            min-height: 80px;
        }

        .comment-input:focus {
            outline: none;
            border: 1px solid #00ffff;
            box-shadow: 0 0 0 2px rgba(0, 255, 255, 0.2);
        }


        .comment-submit-btn {
            display: block;
            width: 150px;
            margin-left: auto;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            background-color: #00ffff;
            color: #1a1a1a;
            cursor: pointer;
            font-size: 1em;
            font-weight: bold;
            transition: background-color 0.3s, transform 0.3s;
        }

        .comment-submit-btn:hover {
            background-color: #00cccc;
            transform: translateY(-2px);
        }


        .comment-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .comment {
            background-color: #1a1a1a;
            padding: 1rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.9em;
            color: #b0e0e6;
            flex-wrap: wrap;
            /* Allow header content to wrap */
            gap: 10px;
            /* Add gap for wrapped items */
        }

        .comment-header strong {
            color: #00ffff;
            font-weight: bold;
            margin-right: 10px;
        }

        .comment-header .user-info {
            display: flex;
            align-items: center;
            flex-shrink: 0;
            /* Prevent user info from shrinking */
        }

        .comment-rating-display {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-right: 10px;
            flex-shrink: 0;
            /* Prevent rating display from shrinking */
        }

        .comment-rating-display .stars {
            color: #ffd700;
            font-size: 0.9em;
        }

        .comment-rating-display span {
            font-size: 0.9em;
            color: #b0e0e6;
        }


        .comment-actions {
            display: flex;
            gap: 1rem;
            color: #888;
            font-size: 0.8em;
            flex-shrink: 0;
            /* Prevent actions from shrinking */
        }

        .comment-actions i {
            cursor: pointer;
            transition: color 0.3s;
        }

        .comment-actions i:hover {
            color: #00ffff;
        }

        .comment p {
            color: #ccc;
            line-height: 1.5;
            white-space: pre-wrap;
            /* Preserve line breaks */
        }

        /* Modal styles (Trailer) - Copy from review/styles.css */
        .trailer-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .trailer-modal.active {
            display: flex;
        }

        .trailer-content {
            width: 90%;
            max-width: 1000px;
            position: relative;
        }

        .close-trailer {
            position: absolute;
            top: -40px;
            right: 0;
            color: white;
            font-size: 2.5rem;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close-trailer:hover {
            color: #ccc;
        }

        .video-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            background-color: black;
        }

        .video-container iframe,
        .video-container video {
            /* Added video tag for local files */
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }

        /* Alert Messages (Copy from favorite/styles.css) */
        .alert {
            padding: 10px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 14px;
            text-align: center;
        }

        .alert.success {
            background-color: #00ff0033;
            color: #00ff00;
            border: 1px solid #00ff0088;
        }

        .alert.error {
            background-color: #ff000033;
            color: #ff0000;
            border: 1px solid #ff000088;
        }

        /* Scrollbar Styles (Copy from review/styles.css) */
        .main-content::-webkit-scrollbar {
            width: 8px;
        }

        .main-content::-webkit-scrollbar-track {
            background: #242424;
        }

        .main-content::-webkit-scrollbar-thumb {
            background: #363636;
            border-radius: 4px;
        }

        .main-content::-webkit-scrollbar-thumb:hover {
            background: #00ffff;
        }

        /* Empty state for comments */
        .empty-state i {
            color: #666;
            /* Match other empty states */
        }
    </style>
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
                <li class="active"><a href="index.php"><i class="fas fa-star"></i> <span>Review</span></a></li>
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
            <div class="movie-details-page">
                <a href="index.php" class="back-button">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Reviews</span>
                </a>

                <?php if ($success_message): ?>
                    <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert error"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>


                <div class="movie-header">
                    <div class="movie-poster-large">
                        <img src="<?php echo $posterSrc; ?>" alt="<?php echo htmlspecialchars($movie['title']); ?> Poster">
                    </div>
                    <div class="movie-info-large">
                        <h1 class="movie-title-large"><?php echo htmlspecialchars($movie['title']); ?></h1>
                        <p class="movie-meta"><?php echo htmlspecialchars((new DateTime($movie['release_date']))->format('Y')); ?> | <?php echo htmlspecialchars($movie['genres'] ?? 'N/A'); ?> | <?php echo htmlspecialchars($movie['age_rating']); ?> | <?php echo $duration_display; ?></p>
                        <div class="rating-large">
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
                            <span id="movie-rating"><?php echo htmlspecialchars($movie['average_rating']); ?>/5</span>
                        </div>
                        <p class="movie-description"><?php echo nl2br(htmlspecialchars($movie['summary'] ?? 'No summary available.')); ?></p>
                        <div class="action-buttons">
                            <?php if ($trailerSrc): // Use $trailerSrc here 
                            ?>
                                <button class="action-button watch-trailer" onclick="playTrailer('<?php echo $trailerSrc; ?>', <?php echo $isYouTubeTrailer ? 'true' : 'false'; ?>)">
                                    <i class="fas fa-play"></i>
                                    <span>Watch Trailer</span>
                                </button>
                            <?php endif; ?>

                            <form action="movie-details.php?id=<?php echo $movie['movie_id']; ?>" method="POST" style="margin:0; padding:0;">
                                <input type="hidden" name="movie_id" value="<?php echo $movie['movie_id']; ?>">
                                <button type="submit" name="toggle_favorite" value="<?php echo $isFavorited ? 'unfavorite' : 'favorite'; ?>"
                                    class="action-button add-favorite <?php echo $isFavorited ? 'favorited' : ''; ?>">
                                    <i class="fas fa-heart"></i>
                                    <span id="favorite-text"><?php echo $isFavorited ? 'Remove from Favorites' : 'Add to Favorites'; ?></span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="comments-section">
                    <h2 class="comments-header">Comments & Reviews</h2>

                    <div class="comment-input-area">
                        <h3>Leave a Review</h3>
                        <form action="movie-details.php?id=<?php echo $movie['movie_id']; ?>" method="POST">
                            <div class="rating-input-stars" id="rating-input-stars">
                                <i class="far fa-star" data-rating="1"></i>
                                <i class="far fa-star" data-rating="2"></i>
                                <i class="far fa-star" data-rating="3"></i>
                                <i class="far fa-star" data-rating="4"></i>
                                <i class="far fa-star" data-rating="5"></i>
                                <input type="hidden" name="rating" id="user-rating" value="0">
                            </div>
                            <textarea class="comment-input" name="comment" placeholder="Write your comment or review here..."></textarea>
                            <input type="hidden" name="submit_review" value="1"> <button type="submit" class="comment-submit-btn">Submit Review</button>
                        </form>
                    </div>


                    <div class="comment-list">
                        <?php if (!empty($comments)): ?>
                            <?php foreach ($comments as $comment): ?>
                                <div class="comment">
                                    <div class="comment-header">
                                        <div class="user-info">
                                            <img src="<?php echo htmlspecialchars($comment['profile_image'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($comment['username']) . '&background=random&color=fff&size=25'); ?>" alt="Avatar" style="width: 25px; height: 25px; border-radius: 50%; margin-right: 10px; object-fit: cover;">
                                            <strong><?php echo htmlspecialchars($comment['username']); ?></strong>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 15px;">
                                            <div class="comment-rating-display">
                                                <div class="stars">
                                                    <?php
                                                    $comment_rating = floatval($comment['rating']);
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        if ($i <= $comment_rating) {
                                                            echo '<i class="fas fa-star"></i>';
                                                        } else if ($i - 0.5 <= $comment_rating) {
                                                            echo '<i class="fas fa-star-half-alt"></i>';
                                                        } else {
                                                            echo '<i class="far fa-star"></i>';
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                                <span>(<?php echo htmlspecialchars(number_format($comment_rating, 1)); ?>/5)</span>
                                            </div>
                                            <div class="comment-actions">
                                                <i class="fas fa-thumbs-up" title="Like"></i>
                                                <i class="fas fa-thumbs-down" title="Dislike"></i>
                                                <i class="fas fa-reply" title="Reply"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <p><?php echo nl2br(htmlspecialchars($comment['comment'] ?? '')); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state" style="background-color: #1a1a1a; padding: 20px; border-radius: 10px;">
                                <i class="fas fa-comment-dots"></i>
                                <p>No comments yet. Be the first to review!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="trailer-modal" class="trailer-modal">
        <div class="trailer-content">
            <span class="close-trailer" onclick="closeTrailer()">×</span>
            <div class="video-container">
                <iframe id="trailer-iframe" src="" frameborder="0" allowfullscreen style="display: <?php echo $isYouTubeTrailer ? 'block' : 'none'; ?>;"></iframe>
                <video id="trailer-video" src="" controls autoplay style="display: <?php echo $isYouTubeTrailer ? 'none' : 'block'; ?>;"></video>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // JavaScript for rating input
            const ratingStars = document.querySelectorAll('#rating-input-stars i');
            const userRatingInput = document.getElementById('user-rating');
            let currentRating = 0; // Store the selected rating (e.g., 0 for unrated, 1-5 for rated)

            // Add data-rating attribute to stars if not already present
            ratingStars.forEach((star, index) => {
                star.setAttribute('data-rating', index + 1);
            });


            ratingStars.forEach(star => {
                star.addEventListener('mouseover', function() {
                    const hoverRating = parseInt(this.getAttribute('data-rating'));
                    highlightStars(hoverRating, false); // Highlight based on hover, not clicked state
                });

                star.addEventListener('mouseout', function() {
                    // Revert to the currently selected rating
                    highlightStars(currentRating, true); // Highlight based on clicked state
                });

                star.addEventListener('click', function() {
                    currentRating = parseInt(this.getAttribute('data-rating')); // Update selected rating
                    userRatingInput.value = currentRating; // Set hidden input value
                    highlightStars(currentRating, true); // Highlight and mark as rated
                });
            });

            function highlightStars(rating, isClickedState) {
                ratingStars.forEach((star, index) => {
                    const starRating = parseInt(star.getAttribute('data-rating'));
                    star.classList.remove('hovered', 'rated'); // Remove previous states

                    if (starRating <= rating) {
                        star.classList.add(isClickedState ? 'rated' : 'hovered');
                        star.classList.remove('far');
                        star.classList.add('fas');
                    } else {
                        star.classList.remove('fas');
                        star.classList.add('far');
                    }
                });
            }

            // Initial state for rating input (if user previously rated, load it)
            // This requires fetching the user's specific review/rating on page load
            // You would add logic in PHP to get the current user's review for this movie
            // and then set the `currentRating` variable and call `highlightStars` on DOMContentLoaded.
            // Example (assuming PHP provides $userReviewRating):
            // <?php //if (!empty($userReview) && isset($userReview['rating'])): 
                ?>
            //      currentRating = parseFloat("<?php //echo $userReview['rating']; 
                                                ?>");
            //      highlightStars(currentRating, true);
            //      userRatingInput.value = currentRating; // Also set hidden input
            // <?php //endif; 
                ?>


            // JavaScript for trailer modal
            const trailerModal = document.getElementById('trailer-modal');
            const trailerIframe = document.getElementById('trailer-iframe'); // For YouTube
            const trailerVideo = document.getElementById('trailer-video'); // For local files

            window.playTrailer = function(videoSrc, isYouTube) { // Make function global
                if (videoSrc) {
                    if (isYouTube) {
                        trailerVideo.style.display = 'none'; // Hide video element
                        trailerVideo.pause(); // Pause any currently playing local video
                        trailerVideo.src = ''; // Clear local video source
                        trailerIframe.style.display = 'block'; // Show iframe
                        trailerIframe.src = videoSrc;
                    } else {
                        trailerIframe.style.display = 'none'; // Hide iframe
                        trailerIframe.src = ''; // Clear iframe src
                        trailerVideo.style.display = 'block'; // Show video element
                        trailerVideo.src = videoSrc;
                        trailerVideo.load(); // Load the video
                        trailerVideo.play(); // Start playing
                    }
                    trailerModal.classList.add('active');
                } else {
                    alert('Trailer not available.');
                }
            }

            window.closeTrailer = function() { // Make function global
                if (trailerIframe) {
                    trailerIframe.src = ''; // Stop YouTube video
                    trailerIframe.style.display = 'none'; // Hide iframe
                }
                if (trailerVideo) {
                    trailerVideo.pause(); // Pause local video
                    trailerVideo.currentTime = 0; // Reset time
                    trailerVideo.src = ''; // Unload video source
                    trailerVideo.style.display = 'none'; // Hide video element
                }
                trailerModal.classList.remove('active');
            }

            // Close modal when clicking outside the content or the close button
            trailerModal.addEventListener('click', function(e) {
                // Check if the clicked element is the modal background itself or the close button
                if (e.target === this || e.target.classList.contains('close-trailer') || e.target.closest('.close-trailer')) {
                    closeTrailer();
                }
            });

            // Add event listener to the close button specifically
            const closeButton = document.querySelector('.close-trailer');
            if (closeButton) {
                closeButton.addEventListener('click', closeTrailer);
            }


            // Helper function for HTML escaping (client-side) - consider if this is truly needed on client-side as PHP should handle it
            function htmlspecialchars(str) {
                if (typeof str !== 'string') return str;
                return str.replace(/&/g, '&amp;') // Use &amp; for HTML entities
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;'); // Use &#039; for apostrophes
            }
        }); // End DOMContentLoaded
    </script>
</body>

</html>