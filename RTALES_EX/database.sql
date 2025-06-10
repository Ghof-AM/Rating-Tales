-- Drop database if it exists (USE WITH CAUTION - This will delete ALL your data)
-- DROP DATABASE IF EXISTS ratingtales;

-- Create database
CREATE DATABASE IF NOT EXISTS ratingtales CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE ratingtales;

-- Users table
-- Added 'google_id' column
-- Made 'password' nullable to allow users who only login via Google
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE, -- Ensure username is unique
    email VARCHAR(100) NOT NULL UNIQUE,   -- Ensure email is unique
    password VARCHAR(255),             -- Password can be NULL for Google-only accounts
    age INT,
    gender ENUM('Laki-laki', 'Perempuan'),
    bio TEXT,
    profile_image VARCHAR(255),
    google_id VARCHAR(255) UNIQUE,     -- Store Google's unique user ID, must be unique and can be NULL
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Movies table
-- Stores main movie information
CREATE TABLE movies (
    movie_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    summary TEXT,
    release_date DATE NOT NULL,
    duration_hours INT NOT NULL,
    duration_minutes INT NOT NULL,
    age_rating ENUM('G', 'PG', 'PG-13', 'R', 'NC-17') NOT NULL,
    poster_image VARCHAR(255), -- Stores the filename of the poster image
    trailer_url VARCHAR(255),  -- Stores a URL (e.g., YouTube link)
    trailer_file VARCHAR(255), -- Stores the filename of an uploaded trailer file
    uploaded_by INT NOT NULL,  -- Link to the user who uploaded the movie
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE CASCADE -- If user is deleted, their uploaded movies are deleted
);

-- Movie genres table
-- Links movies to multiple genres (many-to-many relationship)
CREATE TABLE movie_genres (
    movie_id INT NOT NULL,
    genre ENUM('action', 'adventure', 'comedy', 'drama', 'horror', 'supernatural', 'animation', 'sci-fi') NOT NULL,
    PRIMARY KEY (movie_id, genre), -- A movie has a unique set of genres
    FOREIGN KEY (movie_id) REFERENCES movies(movie_id) ON DELETE CASCADE -- If movie is deleted, its genre links are deleted
);

-- Reviews table
-- Stores user reviews and ratings for movies
CREATE TABLE reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    movie_id INT NOT NULL,
    user_id INT NOT NULL,
    rating DECIMAL(2,1) NOT NULL, -- Stores rating with one decimal place (e.g., 4.5)
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (movie_id) REFERENCES movies(movie_id) ON DELETE CASCADE, -- If movie is deleted, its reviews are deleted
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,   -- If user is deleted, their reviews are deleted
    UNIQUE KEY unique_user_movie_review (movie_id, user_id)             -- Ensures a user can only have one review per movie
);

-- Favorites table
-- Stores which movies a user has favorited
CREATE TABLE favorites (
    favorite_id INT AUTO_INCREMENT PRIMARY KEY,
    movie_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (movie_id) REFERENCES movies(movie_id) ON DELETE CASCADE, -- If movie is deleted, it's removed from favorites
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,   -- If user is deleted, their favorites are deleted
    UNIQUE KEY unique_favorite (movie_id, user_id)                     -- Ensures a user can favorite a movie only once
);

-- Create indexes for performance on commonly searched/joined columns
CREATE INDEX idx_movie_title ON movies(title);
CREATE INDEX idx_movie_release_date ON movies(release_date); -- Useful for sorting/filtering
CREATE INDEX idx_movie_age_rating ON movies(age_rating);   -- Useful for filtering
CREATE INDEX idx_movie_genre ON movie_genres(genre);
CREATE INDEX idx_review_movie_id ON reviews(movie_id);     -- Reviews linked to a movie
CREATE INDEX idx_review_user_id ON reviews(user_id);       -- Reviews by a user
CREATE INDEX idx_review_rating ON reviews(rating);         -- Filtering by rating
CREATE INDEX idx_favorite_movie_id ON favorites(movie_id);   -- Favorites linked to a movie
CREATE INDEX idx_favorite_user_id ON favorites(user_id);     -- Favorites by a user
CREATE INDEX idx_movie_uploaded_by ON movies(uploaded_by);
CREATE INDEX idx_users_username ON users(username);        -- Already unique, but index helps lookup
CREATE INDEX idx_users_email ON users(email);              -- Already unique, but index helps lookup
CREATE INDEX idx_users_google_id ON users(google_id);      -- Index for faster Google ID lookups