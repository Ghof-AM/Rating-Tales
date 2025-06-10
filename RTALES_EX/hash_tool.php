<?php
// hash_tool.php
// GENERATE A PASSWORD HASH

// Replace 'your_password_here' with the actual plaintext password you want to hash (e.g., '123456')
$password_to_hash = '111111';

// Generate the hash using BCRYPT (same algorithm as in your registration/database code)
$hashed_password = password_hash($password_to_hash, PASSWORD_BCRYPT);

echo "Plaintext Password: " . htmlspecialchars($password_to_hash) . "<br>";
echo "Generated Hash: " . htmlspecialchars($hashed_password) . "<br>";
echo "<br>Copy the Generated Hash above and manually paste it into the 'password' column for the user 'Ghio' in your database 'users' table.";

// IMPORTANT: Delete this file immediately after using it!
?>