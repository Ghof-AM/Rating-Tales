<?php
// config/database.php

$host = 'localhost';
$dbname = 'ratetales';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Set encoding for proper character handling
    $pdo->exec("SET NAMES 'utf8mb4'");
    $pdo->exec("SET CHARACTER SET utf8mb4");

} catch(PDOException $e) {
    // Log the error instead of dying on a live site
    error_log("Database connection failed: " . $e->getMessage());
    // Provide a user-friendly message
    die("Oops! Something went wrong with the database connection. Please try again later.");
}

// Define upload directories
define('UPLOAD_DIR_POSTERS', __DIR__ . '/../uploads/posters/'); // Use absolute path
define('UPLOAD_DIR_TRAILERS', __DIR__ . '/../uploads/trailers/'); // Use absolute path
define('WEB_UPLOAD_DIR_POSTERS', '../uploads/posters/'); // Web accessible path
define('WEB_UPLOAD_DIR_TRAILERS', '../uploads/trailers/'); // Web accessible path


// Create upload directories if they don't exist
if (!is_dir(UPLOAD_DIR_POSTERS)) {
    mkdir(UPLOAD_DIR_POSTERS, 0775, true); // Use 0775 permissions
}
if (!is_dir(UPLOAD_DIR_TRAILERS)) {
    mkdir(UPLOAD_DIR_TRAILERS, 0775, true); // Use 0775 permissions
}

// --- User Functions ---

// Added full_name, age, gender, bio, google_id to createUser
function createUser($full_name, $username, $email, $password = null, $age = null, $gender = null, $google_id = null, $profile_image = null, $bio = null) {
    global $pdo;
    $hashedPassword = $password ? password_hash($password, PASSWORD_BCRYPT) : null; // Hash password if provided

    $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, password, age, gender, google_id, profile_image, bio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$full_name, $username, $email, $hashedPassword, $age, $gender, $google_id, $profile_image, $bio]);
}

// Get user by ID
function getUserById($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT user_id, full_name, username, email, profile_image, age, gender, bio FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

// Get user by Email
function getUserByEmail($email) {
     global $pdo;
     $stmt = $pdo->prepare("SELECT user_id, full_name, username, email, profile_image, age, gender, bio FROM users WHERE email = ?");
     $stmt->execute([$email]);
     return $stmt->fetch();
}

// Get user by Google ID
function getUserByGoogleId($googleId) {
     global $pdo;
     $stmt = $pdo->prepare("SELECT user_id, full_name, username, email, profile_image, age, gender, bio FROM users WHERE google_id = ?");
     $stmt->execute([$googleId]);
     return $stmt->fetch();
}


// Check if username or email already exists
function isUsernameOrEmailExists($username, $email, $excludeUserId = null) {
    global $pdo;

    $query = "SELECT COUNT(*) FROM users WHERE username = ? OR email = ?";
    $params = [$username, $email];

    if ($excludeUserId !== null) {
        $query .= " AND user_id != ?";
        $params[] = $excludeUserId;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchColumn() > 0;
}

// Added updateUser function - Handles multiple fields dynamically
function updateUser($userId, $data) {
    global $pdo;
    $updates = [];
    $params = [];
    $allowed_fields = ['full_name', 'username', 'email', 'profile_image', 'age', 'gender', 'bio', 'password']; // Added password

    foreach ($data as $key => $value) {
        // Basic validation for allowed update fields
        if (in_array($key, $allowed_fields)) {
            // Handle password hashing if included in data
            if ($key === 'password' && !empty($value)) {
                $value = password_hash($value, PASSWORD_BCRYPT);
            } else if ($key === 'password' && empty($value)) {
                 // Skip if password is empty (don't update password with empty value)
                 continue;
            }
             // Use backticks for column names in case they are reserved words
            $updates[] = "`{$key}` = ?";
            $params[] = $value;
        }
    }

    if (empty($updates)) {
        return false; // Nothing to update
    }

    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE user_id = ?";
    $params[] = $userId;

    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}


// --- Movie Functions ---

// Added uploaded_by
function createMovie($title, $summary, $release_date, $duration_hours, $duration_minutes, $age_rating, $poster_image, $trailer_url, $trailer_file, $uploaded_by) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO movies (title, summary, release_date, duration_hours, duration_minutes, age_rating, poster_image, trailer_url, trailer_file, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $summary, $release_date, $duration_hours, $duration_minutes, $age_rating, $poster_image, $trailer_url, $trailer_file, $uploaded_by]);
    return $pdo->lastInsertId(); // Return the ID of the newly created movie
}

function addMovieGenre($movie_id, $genre) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT IGNORE INTO movie_genres (movie_id, genre) VALUES (?, ?)"); // Use INSERT IGNORE to prevent duplicates
    return $stmt->execute([$movie_id, $genre]);
}

// Function to remove all genres for a movie
function removeMovieGenres($movie_id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM movie_genres WHERE movie_id = ?");
    return $stmt->execute([$movie_id]);
}


function getMovieById($movieId) {
    global $pdo;
    // Fetch movie details along with its genres and the uploader's username
    $stmt = $pdo->prepare("
        SELECT
            m.*,
            GROUP_CONCAT(mg.genre SEPARATOR ', ') as genres, -- Use separator for clarity
            u.username as uploader_username
        FROM movies m
        LEFT JOIN movie_genres mg ON m.movie_id = mg.movie_id
        JOIN users u ON m.uploaded_by = u.user_id
        WHERE m.movie_id = ?
        GROUP BY m.movie_id
    ");
    $stmt->execute([$movieId]);
    $movie = $stmt->fetch();

    // Add average rating to the movie data
    if ($movie) {
        // Ensure genres is an array if needed, currently a comma-separated string
         if (!empty($movie['genres'])) {
             $movie['genres_array'] = explode(', ', $movie['genres']);
         } else {
             $movie['genres_array'] = [];
         }
        $movie['average_rating'] = getMovieAverageRating($movieId); // Use the helper function
    }

    return $movie;
}


function getAllMovies() {
    global $pdo;
    // Fetch all movies along with genres and average rating
    $stmt = $pdo->prepare("
        SELECT
            m.*,
            GROUP_CONCAT(mg.genre SEPARATOR ', ') as genres,
            (SELECT AVG(rating) FROM reviews WHERE movie_id = m.movie_id) as average_rating
        FROM movies m
        LEFT JOIN movie_genres mg ON m.movie_id = mg.movie_id
        GROUP BY m.movie_id
        ORDER BY m.created_at DESC
    ");
    $stmt->execute();
    $movies = $stmt->fetchAll();

    // Convert genres string to array for consistency
    foreach ($movies as &$movie) {
         if (!empty($movie['genres'])) {
             $movie['genres_array'] = explode(', ', $movie['genres']);
         } else {
             $movie['genres_array'] = [];
         }
    }
     unset($movie); // Break the reference with the last element

    return $movies;
}

// Added function to get movies uploaded by a specific user
function getUserUploadedMovies($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT
            m.*,
            GROUP_CONCAT(mg.genre SEPARATOR ', ') as genres,
            (SELECT AVG(rating) FROM reviews WHERE movie_id = m.movie_id) as average_rating
        FROM movies m
        LEFT JOIN movie_genres mg ON m.movie_id = mg.movie_id
        WHERE m.uploaded_by = ?
        GROUP BY m.movie_id
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$userId]);
     $movies = $stmt->fetchAll();

     // Convert genres string to array for consistency
     foreach ($movies as &$movie) {
          if (!empty($movie['genres'])) {
              $movie['genres_array'] = explode(', ', $movie['genres']);
          } else {
              $movie['genres_array'] = [];
          }
     }
      unset($movie); // Break the reference with the last element

     return $movies;
}

// Added function to update a movie
function updateMovie($movieId, $data) {
    global $pdo;
    $updates = [];
    $params = [];
    $allowed_fields = ['title', 'summary', 'release_date', 'duration_hours', 'duration_minutes', 'age_rating', 'poster_image', 'trailer_url', 'trailer_file'];

    foreach ($data as $key => $value) {
        if (in_array($key, $allowed_fields)) {
            $updates[] = "`{$key}` = ?";
            $params[] = $value;
        }
    }

    if (empty($updates)) {
        return false; // Nothing to update
    }

    $sql = "UPDATE movies SET " . implode(', ', $updates) . " WHERE movie_id = ?";
    $params[] = $movieId;

    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}


// --- Review Functions ---

// Supports inserting a new review or updating an existing one (upsert)
// Relies on the UNIQUE KEY unique_user_movie_review (movie_id, user_id) added to the reviews table
function createReview($movie_id, $user_id, $rating, $comment) {
    global $pdo;
    // Using ON DUPLICATE KEY UPDATE now that the unique key exists
    $stmt = $pdo->prepare("
        INSERT INTO reviews (movie_id, user_id, rating, comment)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), created_at = CURRENT_TIMESTAMP -- Update timestamp on update
    ");
     return $stmt->execute([$movie_id, $user_id, $rating, $comment]);
}

function getMovieReviews($movieId) {
    global $pdo;
    // Fetch reviews along with reviewer's username and profile image
    $stmt = $pdo->prepare("
        SELECT
            r.*,
            u.username,
            u.full_name, -- Fetch full_name too, might be useful
            u.profile_image
        FROM reviews r
        JOIN users u ON r.user_id = u.user_id
        WHERE r.movie_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$movieId]);
    return $stmt->fetchAll();
}

// Function to get a single user's review for a specific movie
function getUserReviewForMovie($movieId, $userId) {
     global $pdo;
     $stmt = $pdo->prepare("
         SELECT
             r.*,
             u.username,
             u.full_name,
             u.profile_image
         FROM reviews r
         JOIN users u ON r.user_id = u.user_id
         WHERE r.movie_id = ? AND r.user_id = ? LIMIT 1
     ");
     $stmt->execute([$movieId, $userId]);
     return $stmt->fetch();
}


// Helper function to calculate average rating for a movie
function getMovieAverageRating($movieId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT AVG(rating) as average_rating FROM reviews WHERE movie_id = ?");
    $stmt->execute([$movieId]);
    $result = $stmt->fetch();
    // Return formatted rating or 'N/A'
    return $result && $result['average_rating'] !== null ? number_format((float)$result['average_rating'], 1, '.', '') : 'N/A'; // Cast to float, specify decimal point
}


// --- Favorite Functions ---

function addToFavorites($movie_id, $user_id) {
    global $pdo;
    // Use INSERT IGNORE to prevent errors if the favorite already exists (due to UNIQUE KEY)
    $stmt = $pdo->prepare("INSERT IGNORE INTO favorites (movie_id, user_id) VALUES (?, ?)");
    return $stmt->execute([$movie_id, $user_id]);
}

function removeFromFavorites($movie_id, $user_id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM favorites WHERE movie_id = ? AND user_id = ?");
    return $stmt->execute([$movie_id, $user_id]);
}

function getUserFavorites($userId) {
    global $pdo;
    // Fetch user favorites along with genres and average rating
    $stmt = $pdo->prepare("
        SELECT
            m.*,
            GROUP_CONCAT(mg.genre SEPARATOR ', ') as genres,
            (SELECT AVG(rating) FROM reviews WHERE movie_id = m.movie_id) as average_rating
        FROM favorites f
        JOIN movies m ON f.movie_id = m.movie_id
        LEFT JOIN movie_genres mg ON m.movie_id = mg.movie_id
        WHERE f.user_id = ?
        GROUP BY m.movie_id
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$userId]);
    $movies = $stmt->fetchAll();

     // Convert genres string to array for consistency
     foreach ($movies as &$movie) {
          if (!empty($movie['genres'])) {
              $movie['genres_array'] = explode(', ', $movie['genres']);
          } else {
              $movie['genres_array'] = [];
          }
     }
      unset($movie); // Break the reference with the last element

     return $movies;
}

// Helper function to check if a movie is favorited by the current user
function isMovieFavorited($movieId, $userId) {
    global $pdo;
    if (!$userId) return false; // Cannot favorite if not logged in
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE movie_id = ? AND user_id = ?");
    $stmt->execute([$movieId, $userId]);
    return $stmt->fetchColumn() > 0;
}


?>