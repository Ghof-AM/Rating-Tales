<?php
// autentikasi/form-login.php
require_once '../includes/config.php'; // Include config.php and database functions

// Redirect if already authenticated
if (isAuthenticated()) {
    header('Location: ../beranda/index.php');
    exit;
}

// --- Logika generate CAPTCHA (Server-side) ---
// Generate CAPTCHA new if not set or if redirected back due to error
if (!isset($_SESSION['captcha_code']) || isset($_SESSION['error_message'])) {
     $_SESSION['captcha_code'] = generateRandomString(6);
}

// --- Proses Form Login ---
$error_message = null;
$success_message = null;

// Retrieve input values from session if redirected back due to error (improves UX)
$username_input = $_SESSION['login_username_input'] ?? '';
// CAPTCHA input is intentionally NOT pre-filled for security

// Get messages from session and clear them
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Clear stored inputs from session AFTER retrieving messages
unset($_SESSION['login_username_input']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil dan bersihkan input
    $username_input_post = trim($_POST['username'] ?? '');
    $password_input = $_POST['password'] ?? '';
    $captcha_input_post = trim($_POST['captcha_input'] ?? '');

    // Store inputs in session in case of redirect (for UX)
    $_SESSION['login_username_input'] = $username_input_post;

    // --- Validasi Server-side ---
    $errors = [];
    if (empty($username_input_post)) $errors[] = 'Username/Email is required.';
    if (empty($password_input)) $errors[] = 'Password is required.';
    if (empty($captcha_input_post)) $errors[] = 'CAPTCHA is required.';

    // --- Validasi CAPTCHA ---
    // Check CAPTCHA only if other required fields are present to avoid regenerating CAPTCHA on empty fields error
    if (empty($errors)) {
         if (!isset($_SESSION['captcha_code']) || strtolower($captcha_input_post) !== strtolower($_SESSION['captcha_code'])) {
             $errors[] = 'Invalid CAPTCHA.';
             // CAPTCHA is regenerated at the top of the file if there are errors
         } else {
             // CAPTCHA valid, unset it immediately to prevent reuse
             unset($_SESSION['captcha_code']);
         }
    }


    // If there are validation errors, store them in session and redirect
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode('<br>', $errors);
        header('Location: form-login.php'); // Redirect back to show error and new CAPTCHA
        exit;
    }

    // --- If all validations pass, proceed to DB checks ---
    try {
        // Cek pengguna berdasarkan username atau email
        // Using getUserByEmail from database.php which handles both cases via a single query
        $user = getUserByEmail($username_input_post);

        // Verifikasi password
        if ($user && password_verify($password_input, $user['password'])) {
            // Password correct, set session user_id
            $_SESSION['user_id'] = $user['user_id'];

            // Regenerate session ID after successful login to prevent Session Fixation Attacks
            session_regenerate_id(true);

            // Redirect to intended URL if set, otherwise to beranda
            $redirect_url = '../beranda/index.php';
            if (isset($_SESSION['intended_url'])) {
                $redirect_url = $_SESSION['intended_url'];
                unset($_SESSION['intended_url']); // Clear the intended URL
            }

            header('Location: ' . $redirect_url);
            exit;

        } else {
            // Username/Email or password incorrect
            $_SESSION['error_message'] = 'Incorrect Username/Email or password.';
             // CAPTCHA is regenerated at the top of the file if there are errors
            header('Location: form-login.php');
            exit;
        }

    } catch (PDOException $e) {
        error_log("Database error during login: " . $e->getMessage());
        $_SESSION['error_message'] = 'An internal error occurred. Please try again.';
         // CAPTCHA is regenerated at the top of the file if there are errors
        header('Location: form-login.php');
        exit;
    }
}


// Get CAPTCHA code from session for client-side drawing
$captchaCodeForClient = $_SESSION['captcha_code'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Tales - Login</title>
    <link rel="icon" type="image/png" href="../gambar/Untitled142_20250310223718.png" sizes="16x16">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Sign-In -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>
    <div class="form-container login-form">
        <h2>Login</h2>

        <?php if ($error_message): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

         <?php if ($success_message): ?>
            <p class="success-message"><?php echo htmlspecialchars($success_message); ?></p>
        <?php endif; ?>

        <form action="form-login.php" method="POST">
            <div class="input-group">
                <label for="username">Username or Email</label>
                <input type="text" name="username" id="username" placeholder="Username or Email" required value="<?php echo htmlspecialchars($username_input); ?>">
            </div>
            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" placeholder="Password" required>
            </div>

            <div class="remember-me">
                <input type="checkbox" name="remember_me" id="rememberMe">
                <label for="rememberMe">Remember Me</label>
            </div>

             <div class="input-group">
                 <label>Verify CAPTCHA</label>
                 <div class="captcha-container">
                    <canvas id="captchaCanvas" width="150" height="40"></canvas>
                    <button type="button" onclick="generateCaptcha()" class="btn-reload" title="Reload CAPTCHA"><i class="fas fa-sync-alt"></i></button>
                 </div>
                 <input type="text" name="captcha_input" id="captchaInput" placeholder="Enter CAPTCHA" required autocomplete="off"> <!-- Value is NOT prefilled for security -->
            </div>

            <button type="submit" class="btn">Login</button>
        </form>

        <div class="form-link separator">OR</div>

        <!-- Google Sign-In Button -->
        <div class="google-signin-container">
            <!-- The following div is provided by Google Identity Services -->
            <div id="g_id_onload"
                 data-client_id="<?php echo GOOGLE_CLIENT_ID; ?>"
                 data-callback="handleCredentialResponse"
                 data-auto_prompt="false">
            </div>
            <div class="g_id_signin"
                 data-type="standard"
                 data-size="large"
                 data-theme="filled_blue"
                 data-text="signin_with"
                 data-shape="rectangular"
                 data-logo_alignment="left">
            </div>
        </div>


        <p class="form-link">Don't have an account? <a href="form-register.php">Register here</a></p>
    </div>

    <script src="animation.js"></script>
    <script>
        // Variable to store the current CAPTCHA code
        // Using PHP to insert the code from the session
        let currentCaptchaCode = "<?php echo htmlspecialchars($captchaCodeForClient); ?>";

        const captchaInput = document.getElementById('captchaInput');
        const captchaCanvas = document.getElementById('captchaCanvas');


        function drawCaptcha(code) {
            if (!captchaCanvas) return;
            const ctx = captchaCanvas.getContext('2d');
            ctx.clearRect(0, 0, captchaCanvas.width, captchaCanvas.height);
            ctx.fillStyle = "#0a192f"; // Background color
            ctx.fillRect(0, 0, captchaCanvas.width, captchaCanvas.height);

            ctx.font = "24px Arial";
            ctx.fillStyle = "#00e4f9"; // Text color
            ctx.strokeStyle = "#00bcd4"; // Noise line color
            ctx.lineWidth = 1;

            // Draw random lines
            for (let i = 0; i < 5; i++) {
                 ctx.beginPath();
                 ctx.moveTo(Math.random() * captchaCanvas.width, Math.random() * captchaCanvas.height);
                 ctx.lineTo(Math.random() * captchaCanvas.width, Math.random() * captchaCanvas.height);
                 ctx.stroke();
            }

            // Draw CAPTCHA text with slight variations
            const textStartX = 10;
            const textY = 30;
            const charSpacing = 23;

            ctx.save();
            ctx.translate(textStartX, textY);

            for (let i = 0; i < code.length; i++) {
                ctx.save();
                const angle = (Math.random() * 20 - 10) * Math.PI / 180;
                ctx.rotate(angle);
                const offsetX = Math.random() * 5 - 2.5;
                const offsetY = Math.random() * 5 - 2.5;
                ctx.fillText(code[i], offsetX + i * charSpacing, offsetY);
                ctx.restore();
            }
            ctx.restore();
        }

        // Function to generate new CAPTCHA (using Fetch API)
        async function generateCaptcha() {
            try {
                const response = await fetch('generate_captcha.php');
                if (!response.ok) {
                     throw new Error('Failed to load new CAPTCHA (status: ' + response.status + ')');
                 }
                const newCaptchaCode = await response.text();
                currentCaptchaCode = newCaptchaCode; // Update global variable
                drawCaptcha(currentCaptchaCode); // Redraw canvas
                captchaInput.value = ''; // Clear input field
            } catch (error) {
                console.error("Error generating CAPTCHA:", error);
                // Optionally display an error message on the page
            }
        }

        // Initial drawing
        document.addEventListener('DOMContentLoaded', () => {
            drawCaptcha(currentCaptchaCode);
             // Clear CAPTCHA input on page load for security
             captchaInput.value = '';
        });

        // Google Sign-In handler
        function handleCredentialResponse(response) {
           console.log("Encoded JWT ID token: " + response.credential);
           // Send this token to your server for validation
           // Use fetch API to send the token to a PHP script
           fetch('verify_google_login.php', {
               method: 'POST',
               headers: {
                   'Content-Type': 'application/x-www-form-urlencoded' // Or 'application/json' if you prefer
               },
               body: 'credential=' + response.credential
           })
           .then(response => response.json()) // Assuming your PHP returns JSON
           .then(data => {
               if (data.success) {
                   // Redirect on successful login/registration
                   window.location.href = data.redirect || '../beranda/index.php'; // Redirect to provided URL or default
               } else {
                   // Display error message
                   alert('Google login failed: ' + (data.message || 'Unknown error')); // Simple alert for now
                   // Optionally display the error message on the page
                   const errorMessageElement = document.querySelector('.error-message');
                   if (errorMessageElement) {
                       errorMessageElement.innerText = 'Google login failed: ' + (data.message || 'Unknown error');
                       errorMessageElement.style.display = 'block';
                   } else {
                        const newErrorMessageElement = document.createElement('p');
                        newErrorMessageElement.className = 'error-message';
                        newErrorMessageElement.innerText = 'Google login failed: ' + (data.message || 'Unknown error');
                        document.querySelector('.form-container').prepend(newErrorMessageElement);
                   }

               }
           })
           .catch(error => {
               console.error('Error verifying Google token:', error);
               alert('An error occurred during Google login.');
           });
        }
    </script>
</body>
</html>