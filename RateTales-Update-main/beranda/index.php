<?php
// beranda/index.php
require_once '../includes/config.php'; // Include config.php

// Fetch all movies from the database
$movies = getAllMovies(); // This function now fetches average_rating and genres

// Get authenticated user details (if logged in)
$user = getAuthenticatedUser();

// Determine movies for sections (simple logic for now)
// Filter out movies without posters for the slider
$movies_with_posters = array_filter($movies, function ($movie) {
    return !empty($movie['poster_image']);
});

$featured_movies = array_slice($movies_with_posters, 0, 5); // First 5 with posters for slider
$trending_movies = array_slice($movies, 0, 10); // First 10 overall for trending
$for_you_movies = array_slice($movies, 1, 10); // A different slice for variety (adjust logic as needed)
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RATE-TALES - Home</title>
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
                <li class="active"><a href="#"><i class="fas fa-home"></i> <span>Home</span></a></li>
                <li><a href="../favorite/index.php"><i class="fas fa-heart"></i> <span>Favourites</span></a></li>
                <li><a href="../review/index.php"><i class="fas fa-star"></i> <span>Review</span></a></li>
                <li><a href="../manage/indeks.php"><i class="fas fa-film"></i> <span>Manage</span></a></li>
                <?php if ($user): ?>
                    <li><a href="../acc_page/index.php"><i class="fas fa-user"></i> <span>Profile</span></a></li>
                <?php endif; ?>
            </ul>
            <div class="bottom-links">
                <ul>
                    <?php if ($user): ?>
                        <li><a href="../autentifikasi/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
                    <?php else: ?>
                        <li><a href="../autentifikasi/form-login.php"><i class="fas fa-sign-in-alt"></i> <span>Login</span></a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
        <main class="main-content">
            <div class="hero-section">
                <div class="featured-movie-slider">
                    <?php if (!empty($featured_movies)): ?>
                        <?php foreach ($featured_movies as $index => $movie): ?>
                            <div class="slide <?php echo $index === 0 ? 'active' : ''; ?>">
                                <img src="<?php echo htmlspecialchars(WEB_UPLOAD_DIR_POSTERS . $movie['poster_image'] ?? '../gambar/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                                <div class="movie-info">
                                    <h1><?php echo htmlspecialchars($movie['title']); ?></h1>
                                    <p><?php echo htmlspecialchars((new DateTime($movie['release_date']))->format('Y')); ?> | <?php echo htmlspecialchars($movie['genres'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="slide active empty-state" style="position:relative; opacity:1; display:flex; flex-direction:column; justify-content:center; align-items:center; text-align:center; height: 100%;">
                            <i class="fas fa-film" style="font-size: 3em; color:#00ffff; margin-bottom:15px;"></i>
                            <p style="color: #fff; font-size:1.2em;">No featured movies available yet.</p>
                            <?php if ($user): ?>
                                <p class="subtitle" style="color: #888; margin-top:10px;">Upload movies in the <a href="../manage/indeks.php" style="color:#00ffff; text-decoration:none;">Manage</a> section.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <section class="trending-section">
                <h2>TRENDING</h2>
                <div class="scroll-container">
                    <div class="movie-grid">
                        <?php if (!empty($trending_movies)): ?>
                            <?php foreach ($trending_movies as $movie): ?>
                                <div class="movie-card" onclick="window.location.href='../review/movie-details.php?id=<?php echo $movie['movie_id']; ?>'">
                                    <img src="<?php echo htmlspecialchars(WEB_UPLOAD_DIR_POSTERS . $movie['poster_image'] ?? '../gambar/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                                    <div class="movie-details">
                                        <h3><?php echo htmlspecialchars($movie['title']); ?></h3>
                                        <p><?php echo htmlspecialchars((new DateTime($movie['release_date']))->format('Y')); ?> | <?php echo htmlspecialchars($movie['genres'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state" style="min-width: 100%; text-align: center; padding: 20px;">
                                <p style="color: #888;">No trending movies available.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            <section class="for-you-section">
                <h2>For You</h2>
                <div class="scroll-container">
                    <div class="movie-grid">
                        <?php if (!empty($for_you_movies)): ?>
                            <?php foreach ($for_you_movies as $movie): ?>
                                <div class="movie-card" onclick="window.location.href='../review/movie-details.php?id=<?php echo $movie['movie_id']; ?>'">
                                    <img src="<?php echo htmlspecialchars(WEB_UPLOAD_DIR_POSTERS . $movie['poster_image'] ?? '../gambar/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                                    <div class="movie-details">
                                        <h3><?php echo htmlspecialchars($movie['title']); ?></h3>
                                        <p><?php echo htmlspecialchars((new DateTime($movie['release_date']))->format('Y')); ?> | <?php echo htmlspecialchars($movie['genres'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state" style="min-width: 100%; text-align: center; padding: 20px;">
                                <p style="color: #888;">No movie suggestions for you.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
        <?php if ($user): ?>
            <a href="../acc_page/index.php" class="user-profile">
                <img src="<?php echo htmlspecialchars($user['profile_image'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($user['full_name'] ?? $user['username']) . '&background=random&color=fff&size=30'); ?>" alt="User Profile" class="profile-pic">
                <span><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></span>
            </a>
        <?php else: ?>
            <a href="../autentifikasi/form-login.php" class="user-profile" style="background-color: #00ffff; color: #1a1a1a; font-weight:bold;">
                <span>Login</span>
            </a>
        <?php endif; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const slides = document.querySelectorAll('.hero-section .slide');
            if (slides.length > 0) {
                let currentSlide = 0;

                // Function to show a specific slide
                function showSlide(index) {
                    slides.forEach(slide => slide.classList.remove('active'));
                    slides[index].classList.add('active');
                }

                // Function to advance to the next slide
                function nextSlide() {
                    currentSlide = (currentSlide + 1) % slides.length;
                    showSlide(currentSlide);
                }

                // Show the first slide immediately
                showSlide(currentSlide);

                // Change slide every 5 seconds
                setInterval(nextSlide, 5000);
            }

            // Smooth scroll for movie grids (optional enhancement)
            document.querySelectorAll('.scroll-container').forEach(container => {
                container.addEventListener('wheel', (e) => {
                    // Check if the mouse wheel event is vertical, only react if it's horizontal (Shift key) or you want to force horizontal scroll
                    // Forcing horizontal scroll on vertical wheel:
                    if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) { // Only act if vertical scroll is dominant
                        e.preventDefault(); // Prevent default vertical scroll
                        container.scrollLeft += e.deltaY * 0.5; // Scroll horizontally based on vertical delta
                    } else if (e.deltaX !== 0) { // Also allow horizontal scroll from horizontal wheel
                        container.scrollLeft += e.deltaX;
                    }
                });
            });
        });
    </script>
</body>

</html>