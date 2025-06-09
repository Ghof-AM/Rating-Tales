<?php
// autentikasi/form-login.php
require_once '../includes/config.php'; // Include config.php

// Redirect if already authenticated
if (isAuthenticated()) {
    header('Location: ../beranda/index.php');
    exit;
}

// --- Logika generate CAPTCHA (Server-side) ---
// Generate CAPTCHA new if not set or needed after POST error
if (!isset($_SESSION['captcha_code']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['error']))) {
    $_SESSION['captcha_code'] = generateRandomString(6);
}


// --- Proses Form Login ---
$error_message = null;
$success_message = null;

// Retrieve input values from session if redirected back due to error (optional but improves UX)
$username_input = $_SESSION['login_username_input'] ?? '';
$captcha_input = $_SESSION['login_captcha_input'] ?? ''; // Note: CAPTCHA should be re-entered for security

// Clear stored inputs from session
unset($_SESSION['login_username_input']);
unset($_SESSION['login_captcha_input']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil dan bersihkan input
    $username_input_post = trim($_POST['username'] ?? '');
    $password_input = $_POST['password'] ?? '';
    $captcha_input_post = trim($_POST['captcha_input'] ?? '');

    // Store inputs in session in case of redirect (for UX)
    $_SESSION['login_username_input'] = $username_input_post;
    $_SESSION['login_captcha_input'] = $captcha_input_post; // Note: This will be cleared on page load, but useful for debugging

    // --- Validasi Server-side (Basic) ---
    if (empty($username_input_post) || empty($password_input) || empty($captcha_input_post)) {
        $_SESSION['error'] = 'Username/Email, Password, and CAPTCHA are required.';
        header('Location: form-login.php'); // Redirect back to show error and new CAPTCHA
        exit;
    }

    // --- Validasi CAPTCHA (Server-side) ---
    if (!isset($_SESSION['captcha_code']) || strtolower($captcha_input_post) !== strtolower($_SESSION['captcha_code'])) {
        $_SESSION['error'] = 'Invalid CAPTCHA.';
        // CAPTCHA already regenerated at the top if there was an error before CAPTCHA check
        header('Location: form-login.php'); // Redirect back to show error and new CAPTCHA
        exit; // Stop execution if CAPTCHA is wrong
    }

    // CAPTCHA valid, unset it immediately to prevent reuse
    unset($_SESSION['captcha_code']);


    // --- Lanjutkan proses login ---
    try {
        // Cek pengguna berdasarkan username atau email
        $stmt = $pdo->prepare("SELECT user_id, password FROM users WHERE username=? OR email=?");
        $stmt->execute([$username_input_post, $username_input_post]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);


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
            $_SESSION['error'] = 'Incorrect Username/Email or password.';
            // Ensure CAPTCHA is regenerated for the next attempt
            if (!isset($_SESSION['captcha_code'])) {
                $_SESSION['captcha_code'] = generateRandomString(6);
            }
            header('Location: form-login.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Database error during login: " . $e->getMessage());
        $_SESSION['error'] = 'An internal error occurred. Please try again.';
        if (!isset($_SESSION['captcha_code'])) {
            $_SESSION['captcha_code'] = generateRandomString(6);
        }
        header('Location: form-login.php');
        exit;
    }
}

// Get messages from session
if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
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
    <!-- Google Sign-In - Keep for now, although not implemented -->
    <!-- <script src="https://accounts.google.com/gsi/client" async defer></script> -->
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
                <p id="captchaMessage" class="error-message" style="display:none;"></p>
            </div>

            <button type="submit" class="btn">Login</button>
        </form>
        <br>
        <!-- Google Sign-In elements (commented out as not fully implemented) -->
        <!-- <div id="g_id_onload" data-client_id="YOUR_GOOGLE_CLIENT_ID" data-callback="handleCredentialResponse"></div>
        <div class="g_id_signin" data-type="standard"></div> -->


        <p class="form-link">Don't have an account? <a href="form-register.php">Register here</a></p>
    </div>

    <script src="animation.js"></script>
    <script>
        // Variable to store the current CAPTCHA code
        // Using PHP to insert the code from the session
        let currentCaptchaCode = "<?php echo htmlspecialchars($captchaCodeForClient); ?>";

        const captchaInput = document.getElementById('captchaInput');
        const captchaMessage = document.getElementById('captchaMessage');
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
                captchaMessage.style.display = 'none'; // Hide feedback message
            } catch (error) {
                console.error("Error generating CAPTCHA:", error);
                captchaMessage.innerText = 'Failed to load CAPTCHA. Try again.';
                captchaMessage.style.color = 'red';
                captchaMessage.style.display = 'block';
            }
        }

        // Initial drawing
        document.addEventListener('DOMContentLoaded', () => {
            drawCaptcha(currentCaptchaCode);
            // Optional: clear CAPTCHA input on page load for security
            captchaInput.value = '';
        });

        // Google Sign-In handler (placeholder - uncomment and implement if needed)
        // function handleCredentialResponse(response) {
        //    console.log("Encoded JWT ID token: " + response.credential);
        //    // TODO: Send this token to your server for validation
        //    // Example: window.location.href = 'verify-google-token.php?token=' + response.credential;
        // }
    </script>
</body>

</html>