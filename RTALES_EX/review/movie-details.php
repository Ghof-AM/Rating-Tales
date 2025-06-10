<?php
// review/movie-details.php
require_once '../includes/config.php'; // Include config.php

// Redirect if not authenticated (optional, but good practice for review page actions)
// redirectIfNotAuthenticated(); // Removed redirect for public viewing, actions will check login state

// Get authenticated user details (if logged in)
$userId = null;
$user = null;
if (isAuthenticated()) {
    $userId = $_SESSION['user_id'];
    $user = getAuthenticatedUser(); // Fetch user details for comments (username, profile_image)
}


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

// Fetch the current user's review for this movie (if logged in)
$userReview = null;
if ($userId) {
    $userReview = getUserReviewForMovie($movieId, $userId);
}


// Check if the movie is favorited by the current user (if logged in)
$isFavorited = $userId ? isMovieFavorited($movieId, $userId) : false;

// Handle comment and rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    // Check if logged in to submit review
    if (!$userId) {
         $_SESSION['error_message'] = 'You must be logged in to leave a review.';
         // Store intended URL before redirecting to login
         $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
         header('Location: ../autentikasi/form-login.php');
         exit;
    }

    $commentText = trim($_POST['comment'] ?? '');
    $submittedRating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_FLOAT);

     // Basic validation for rating
     if ($submittedRating === false || $submittedRating < 0.5 || $submittedRating > 5) {
          $_SESSION['error_message'] = 'Please provide a valid rating (0.5 to 5).';
     } else {
          // Allow empty comment with rating, but trim it
          if (createReview($movieId, $userId, $submittedRating, $commentText)) {
              $_SESSION['success_message'] = 'Your review has been submitted!';
              // Update the $userReview variable after successful submission
              $userReview = getUserReviewForMovie($movieId, $userId);
               // Re-fetch comments to include the new/updated one immediately
               $comments = getMovieReviews($movieId);
          } else {
              $_SESSION['error_message'] = 'Failed to submit your review.';
          }
     }

    // Redirect using GET to prevent form resubmission on refresh
    // This also clears POST data and allows session messages to show
    header("Location: movie-details.php?id={$movieId}");
    exit;
}


// Handle Favorite/Unfavorite action (using POST for robustness)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_favorite'])) {
     // Check if logged in to favorite
    if (!$userId) {
         $_SESSION['error_message'] = 'You must be logged in to favorite movies.';
          // Store intended URL before redirecting to login
         $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
         header('Location: ../autentikasi/form-login.php');
         exit;
    }

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
     // Redirect back to the movie details page using GET
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

// Determine trailer source URL/Path
$trailerSrc = null; // This will hold the src for the iframe or video tag
$isYouTube = false;

if (!empty($movie['trailer_url'])) {
    // Assume YouTube URL and extract video ID
    parse_str( parse_url( $movie['trailer_url'], PHP_URL_QUERY ), $vars );
    $youtubeVideoId = $vars['v'] ?? null;
    if ($youtubeVideoId) {
        $trailerSrc = "https://www.youtube.com/embed/{$youtubeVideoId}";
        $isYouTube = true;
    } else {
         // Handle other video URL types if needed (basic passthrough)
         // Note: Directly embedding external URLs that aren't standard embed formats (like iframe src) might not work.
         // This requires testing or validation of the URL format.
         // For simplicity, if it's not a standard YouTube URL, assume it might be a direct video URL.
         $trailerSrc = htmlspecialchars($movie['trailer_url']); // Pass the URL as is
         $isYouTube = false; // Treat as potential direct video link
    }

} elseif (!empty($movie['trailer_file'])) {
    // Assume local file path, construct web accessible URL
    $trailerSrc = htmlspecialchars(WEB_UPLOAD_DIR_TRAILERS . $movie['trailer_file']); // Adjust path if necessary
    $isYouTube = false; // It's a local file
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
             flex-shrink: 0;
             margin: auto; /* Center if wraps */
             /* Fallback background if image fails */
            background-color: #363636;
             position: relative; /* Needed for ::before */
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
             display: block; /* Remove extra space below image */
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
             line-height: 1.5; /* Improve vertical alignment */
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
             text-decoration: none; /* Ensure links styled as buttons don't have underline */
             color: inherit; /* Inherit color for links acting as buttons */
        }

        .action-button.watch-trailer { /* Use class for clarity */
            background-color: #e50914;
            ```css
            color: white;
        }

        .action-button.add-favorite { /* Use class for clarity */
            background-color: #333;
            color: white;
        }
         .action-button.add-favorite.favorited {
             background-color: #00ffff;
             color: #1a1a1a;
         }


        .action-button:hover {
            transform: translateY(-3px);
            opacity: 0.9;
        }
         .action-button:disabled {
             background-color: #555;
             cursor: not-allowed;
             opacity: 0.7;
             transform: none;
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
         .comment-submit-btn:disabled {
             background-color: #555;
             cursor: not-allowed;
             opacity: 0.7;
             transform: none;
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
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.9em;
             color: #b0e0e6;
             flex-wrap: wrap; /* Allow header content to wrap */
             gap: 10px; /* Add gap for wrapped items */
        }

        .comment-header strong {
             color: #00ffff;
             font-weight: bold;
             margin-right: 10px;
        }
         .comment-header .user-info {
             display: flex;
             align-items: center;
             flex-shrink: 0; /* Prevent user info from shrinking */
         }

         .comment-rating-display {
             display: flex;
             align-items: center;
             gap: 5px;
             margin-right: 10px;
             flex-shrink: 0; /* Prevent rating display from shrinking */
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
             flex-shrink: 0; /* Prevent actions from shrinking */
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
             white-space: pre-wrap; /* Preserve line breaks */
        }

        /* Modal styles (Trailer) */
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
        .video-container video { /* Added video tag for local files */
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
             color: #666; /* Match other empty states */
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
                 <?php if (isAuthenticated()): // Check if logged in ?>
                <li><a href="../favorite/index.php"><i class="fas fa-heart"></i> <span>Favourites</span></a></li>
                 <?php endif; ?>
                <li class="active"><a href="index.php"><i class="fas fa-star"></i> <span>Review</span></a></li>
                 <?php if (isAuthenticated()): // Check if logged in ?>
                <li><a href="../manage/indeks.php"><i class="fas fa-film"></i> <span>Manage</span></a></li>
                 <li><a href="../acc_page/index.php"><i class="fas fa-user"></i> <span>Profile</span></a></li>
                 <?php endif; ?>
            </ul>
            <div class="bottom-links">
                <ul>
                    <?php if (isAuthenticated()): // Check if logged in ?>
                    <li><a href="../autentikasi/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
                    <?php else: ?>
                    <li><a href="../autentikasi/form-login.php"><i class="fas fa-sign-in-alt"></i> <span>Login</span></a></li>
                    <?php endif; ?>
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
                             <!-- Display average rating stars -->
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
                            <?php if ($trailerSrc): ?>
                                <button class="action-button watch-trailer" onclick="playTrailer('<?php echo $trailerSrc; ?>', <?php echo $isYouTube ? 'true' : 'false'; ?>)">
                                    <i class="fas fa-play"></i>
                                    <span>Watch Trailer</span>
                                </button>
                            <?php endif; ?>

                             <!-- Favorite/Unfavorite button (using POST form) -->
                             <?php if (isAuthenticated()): // Only show if logged in ?>
                             <form action="movie-details.php?id=<?php echo $movie['movie_id']; ?>" method="POST" style="margin:0; padding:0;">
                                 <input type="hidden" name="movie_id" value="<?php echo $movie['movie_id']; ?>">
                                 <button type="submit" name="toggle_favorite" value="<?php echo $isFavorited ? 'unfavorite' : 'favorite'; ?>"
                                         class="action-button add-favorite <?php echo $isFavorited ? 'favorited' : ''; ?>">
                                     <i class="fas fa-heart"></i>
                                     <span id="favorite-text"><?php echo $isFavorited ? 'Remove from Favorites' : 'Add to Favorites'; ?></span>
                                 </button>
                             </form>
                             <?php else: ?>
                                <!-- If not logged in, show a disabled or link button -->
                                <a href="../autentikasi/form-login.php?intended_url=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="action-button add-favorite" title="Login to Add to Favorites">
                                     <i class="fas fa-heart"></i>
                                     <span>Login to Favorite</span>
                                </a>
                             <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="comments-section">
                    <h2 class="comments-header">Comments & Reviews</h2>

                     <?php if (isAuthenticated()): // Only show review input if logged in ?>
                     <div class="comment-input-area">
                         <h3><?php echo $userReview ? 'Edit Your Review' : 'Leave a Review'; ?></h3>
                         <form action="movie-details.php?id=<?php echo $movie['movie_id']; ?>" method="POST">
                             <div class="rating-input-stars" id="rating-input-stars">
                                 <i class="far fa-star" data-rating="1"></i>
                                 <i class="far fa-star" data-rating="2"></i>
                                 <i class="far fa-star" data-rating="3"></i>
                                 <i class="far fa-star" data-rating="4"></i>
                                 <i class="far fa-star" data-rating="5"></i>
                                 <!-- Set initial value from $userReview if exists -->
                                 <input type="hidden" name="rating" id="user-rating" value="<?php echo htmlspecialchars($userReview['rating'] ?? 0); ?>">
                             </div>
                             <textarea class="comment-input" name="comment" placeholder="Write your comment or review here..."><?php echo htmlspecialchars($userReview['comment'] ?? ''); ?></textarea>
                              <input type="hidden" name="submit_review" value="1"> <!-- Hidden input to identify review submission -->
                             <button type="submit" class="comment-submit-btn">Submit Review</button>
                         </form>
                     </div>
                      <?php else: ?>
                          <!-- Show login prompt if not logged in -->
                          <div class="empty-state" style="background-color: #1a1a1a; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                              <i class="fas fa-sign-in-alt" style="color: #00ffff;"></i>
                              <p>You must be <a href="../autentikasi/form-login.php?intended_url=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">logged in</a> to leave a review.</p>
                          </div>
                      <?php endif; ?>


                    <div class="comment-list">
                        <?php if (!empty($comments)): ?>
                            <?php foreach ($comments as $comment): ?>
                                <div class="comment">
                                    <div class="comment-header">
                                         <div class="user-info">
                                             <!-- Use full_name if available, fallback to username -->
                                             <img src="<?php echo htmlspecialchars($comment['profile_image'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($comment['full_name'] ?? $comment['username']) . '&background=random&color=fff&size=25'); ?>" alt="Avatar" style="width: 25px; height: 25px; border-radius: 50%; margin-right: 10px; object-fit: cover;">
                                             <strong><?php echo htmlspecialchars($comment['full_name'] ?? $comment['username']); ?></strong>
                                         </div>
                                         <div style="display: flex; align-items: center; gap: 15px;">
                                            <div class="comment-rating-display">
                                                 <div class="stars">
                                                     <?php
                                                     $comment_rating = floatval($comment['rating']);
                                                     $full_stars = floor($comment_rating);
                                                     $half_star = ($comment_rating - $full_stars) >= 0.5;
                                                     $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);

                                                     for ($i = 0; $i < $full_stars; $i++) echo '<i class="fas fa-star"></i>';
                                                     if ($half_star) echo '<i class="fas fa-star-half-alt"></i>';
                                                     for ($i = 0; $i < $empty_stars; $i++) echo '<i class="far fa-star"></i>';
                                                     ?>
                                                 </div>
                                                <span>(<?php echo htmlspecialchars(number_format($comment_rating, 1)); ?>/5)</span>
                                            </div>
                                            <div class="comment-actions">
                                                <!-- Basic Placeholder Actions (Like/Dislike/Reply) -->
                                                <!-- Add logic here if you want to implement these features -->
                                                <!-- <i class="fas fa-thumbs-up" title="Like"></i> -->
                                                <!-- <i class="fas fa-thumbs-down" title="Dislike"></i> -->
                                                <!-- <i class="fas fa-reply" title="Reply"></i> -->
                                                 <span style="font-size: 0.9em; color: #888;"><?php echo (new DateTime($comment['created_at']))->format('Y-m-d H:i'); ?></span>
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

    <!-- Trailer Modal -->
    <div id="trailer-modal" class="trailer-modal">
        <div class="trailer-content">
            <span class="close-trailer" onclick="closeTrailer()">&times;</span>
            <div class="video-container">
                 <!-- Conditional rendering for iframe (YouTube) or video (local file) -->
                 <!-- These elements will be dynamically updated by playTrailer JS function -->
                 <iframe id="trailer-iframe" src="" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                 <video id="trailer-video" src="" controls></video>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
         // JavaScript for rating input
        const ratingStars = document.querySelectorAll('#rating-input-stars i');
        const userRatingInput = document.getElementById('user-rating');
        // Get initial rating from hidden input if user has a review
        let currentRating = parseFloat(userRatingInput.value) || 0;


         // Add data-rating attribute to stars if not already present
         ratingStars.forEach((star, index) => {
             star.setAttribute('data-rating', (index + 1).toString()); // Ensure data-rating is set as string
         });


        ratingStars.forEach(star => {
            star.addEventListener('mouseover', function() {
                const hoverRating = parseFloat(this.getAttribute('data-rating'));
                highlightStars(hoverRating, false); // Highlight based on hover
            });

            star.addEventListener('mouseout', function() {
                 // Revert to the currently selected rating
                highlightStars(currentRating, true); // Highlight based on clicked/saved state
            });

            star.addEventListener('click', function() {
                const clickedRating = parseFloat(this.getAttribute('data-rating'));
                if (currentRating === clickedRating) {
                    // If clicking the currently selected star, reset rating
                    currentRating = 0;
                } else {
                    currentRating = clickedRating; // Update selected rating
                }
                userRatingInput.value = currentRating; // Set hidden input value
                highlightStars(currentRating, true); // Highlight and mark as rated
            });
        });

        function highlightStars(rating, isClickedState) {
            ratingStars.forEach((star, index) => {
                const starRating = index + 1; // Use index + 1 for star value (1 to 5)

                star.classList.remove('hovered', 'rated', 'fas', 'far', 'fa-star-half-alt'); // Reset classes

                if (starRating <= rating) {
                    star.classList.add('fas', 'fa-star'); // Full star
                    star.classList.add(isClickedState ? 'rated' : 'hovered');
                } else if (starRating - 0.5 <= rating) {
                     star.classList.add('fas', 'fa-star-half-alt'); // Half star
                     star.classList.add(isClickedState ? 'rated' : 'hovered');
                } else {
                    star.classList.add('far', 'fa-star'); // Empty star
                }
            });
        }

        // Initial highlight based on the loaded user review rating
        highlightStars(currentRating, true);


         // JavaScript for trailer modal
        const trailerModal = document.getElementById('trailer-modal');
        const trailerIframe = document.getElementById('trailer-iframe'); // For YouTube
        const trailerVideo = document.getElementById('trailer-video'); // For local files
        const watchTrailerButton = document.querySelector('.action-button.watch-trailer');

        function playTrailer(videoSrc, isYouTube = true) {
            if (videoSrc) {
                 // Hide both initially
                 trailerIframe.style.display = 'none';
                 trailerVideo.style.display = 'none';
                 // Ensure video is paused and iframe src is cleared before setting the new source
                 trailerVideo.pause();
                 trailerVideo.removeAttribute('src'); // Remove src to unload
                 trailerIframe.removeAttribute('src'); // Remove src

                 if (isYouTube) {
                    trailerIframe.src = videoSrc + '?autoplay=1'; // Add autoplay
                    trailerIframe.style.display = 'block';
                 } else {
                    trailerVideo.src = videoSrc;
                    trailerVideo.style.display = 'block';
                    trailerVideo.load(); // Load the video
                    trailerVideo.play(); // Start playing
                 }
                trailerModal.classList.add('active');
            } else {
                // This case should ideally be prevented by the PHP if check,
                // but keep as a fallback.
                alert('Trailer not available.');
            }
        }

        function closeTrailer() {
            // Stop playback and clear sources when closing
            if (trailerIframe && trailerIframe.style.display !== 'none') {
                 trailerIframe.contentWindow.postMessage('{"event":"command","func":"stopVideo","args":""}', '*'); // YouTube stop
                 trailerIframe.removeAttribute('src'); // Clear src completely
                 trailerIframe.style.display = 'none';
            }
            if (trailerVideo && trailerVideo.style.display !== 'none') {
                 trailerVideo.pause(); // Pause local video
                 trailerVideo.currentTime = 0; // Reset time
                 trailerVideo.removeAttribute('src'); // Clear src completely
                 trailerVideo.style.display = 'none';
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
        if(closeButton) {
             closeButton.addEventListener('click', closeTrailer);
        }

        // Make the Watch Trailer button trigger the modal
        if(watchTrailerButton) {
             // Get trailer src and type from PHP variables embedded in attributes or data
             const trailerSrc = watchTrailerButton.getAttribute('onclick').match(/playTrailer\('(.*?)', (true|false)\)/);
             if (trailerSrc && trailerSrc[1]) {
                  const src = trailerSrc[1];
                  const isYouTube = trailerSrc[2] === 'true';

                  // Remove the inline onclick
                  watchTrailerButton.removeAttribute('onclick');

                  // Add the event listener
                  watchTrailerButton.addEventListener('click', function() {
                      playTrailer(src, isYouTube);
                  });
             }
        }


     // Helper function for HTML escaping (client-side) - already exists, ensuring it's here
     function htmlspecialchars(str) {
         if (typeof str !== 'string') return str;
          // Create a temporary DOM element to leverage browser's escaping
         const div = document.createElement('div');
         div.appendChild(document.createTextNode(str));
         return div.innerHTML;
     }
    }); // End DOMContentLoaded
    </script>
</body>
</html>