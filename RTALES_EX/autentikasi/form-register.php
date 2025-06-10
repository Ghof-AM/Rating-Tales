<?php
// autentikasi/form-register.php
require_once '../includes/config.php'; // Include config.php and database functions

// Redirect if already authenticated
if (isAuthenticated()) {
    header('Location: ../beranda/index.php');
    exit;
}
// --- Logika generate CAPTCHA (Server-side) ---
// Generate CAPTCHA new if not set or needed after POST error
if (!isset($_SESSION['captcha_code']) || isset($_SESSION['error_message'])) {
     $_SESSION['captcha_code'] = generateRandomString(6);
}

// --- Proses Form Register ---
$error_message = null;
$success_message = null;

// Retrieve input values from session if redirected back due to error (improves UX)
// Note: Password fields are NOT pre-filled for security
$full_name = $_SESSION['register_full_name'] ?? '';
$username = $_SESSION['register_username'] ?? '';
$age_input = $_SESSION['register_age'] ?? '';
$gender = $_SESSION['register_gender'] ?? '';
$email = $_SESSION['register_email'] ?? '';
$agree = $_SESSION['register_agree'] ?? false;

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
unset($_SESSION['register_full_name']);
unset($_SESSION['register_username']);
unset($_SESSION['register_age']);
unset($_SESSION['register_gender']);
unset($_SESSION['register_email']);
unset($_SESSION['register_agree']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil dan bersihkan input
    $full_name_post = trim($_POST['full_name'] ?? '');
    $username_post = trim($_POST['username'] ?? '');
    $age_input_post = $_POST['age'] ?? '';
    $gender_post = $_POST['gender'] ?? '';
    $email_post = trim($_POST['email'] ?? '');
    $password_post = $_POST['password'] ?? '';
    $confirm_password_post = $_POST['confirm_password'] ?? '';
    $captcha_input_post = trim($_POST['captcha_input'] ?? ''); // Trim captcha input too
    $agree_post = isset($_POST['agree']);

     // Store inputs in session in case of redirect (for UX on error)
     $_SESSION['register_full_name'] = $full_name_post;
     $_SESSION['register_username'] = $username_post;
     $_SESSION['register_age'] = $age_input_post;
     $_SESSION['register_gender'] = $gender_post;
     $_SESSION['register_email'] = $email_post;
     $_SESSION['register_agree'] = $agree_post;


    // --- Validasi Server-side ---
    $errors = [];

    if (empty($full_name_post)) $errors[] = 'Full Name is required.';
    if (empty($username_post)) $errors[] = 'Username is required.';
    if (empty($email_post)) $errors[] = 'Email is required.';
    if (empty($password_post)) $errors[] = 'Password is required.';
    if (empty($confirm_password_post)) $errors[] = 'Confirm Password is required.';
    if (empty($captcha_input_post)) $errors[] = 'CAPTCHA is required.';
    if (!$agree_post) $errors[] = 'You must agree to the User Agreement.';

    // Validate Age: check if numeric and > 0
    $age = filter_var($age_input_post, FILTER_VALIDATE_INT);
    if ($age === false || $age <= 0) {
        $errors[] = 'Invalid Age.';
    }

    // Validate Gender: check against allowed options
    $allowed_genders = ['Laki-laki', 'Perempuan'];
    if (!in_array($gender_post, $allowed_genders)) {
        $errors[] = 'Invalid Gender selection.';
    }

    if ($password_post !== $confirm_password_post) {
        $errors[] = 'Password and Confirm Password do not match.';
    }

    if (strlen($password_post) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    // Validate Email Format (basic)
    if (!filter_var($email_post, FILTER_VALIDATE_EMAIL)) {
         $errors[] = 'Invalid Email format.';
    }

    // Validate Username format (optional: allow only letters, numbers, underscore)
    // if (!preg_match('/^[a-zA-Z0-9_]+$/', $username_post)) {
    //     $errors[] = 'Username can only contain letters, numbers, and underscores.';
    // }


    // --- Validasi CAPTCHA (Server-side) ---
    // Check CAPTCHA only if other required fields are present
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
        header('Location: form-register.php');
        exit;
    }

    // --- If all validations pass, proceed to DB checks and INSERT ---

    // Check if username or email already exists
    try {
        // Use the refactored function from database.php
        if (isUsernameOrEmailExists($username_post, $email_post)) {
            $_SESSION['error_message'] = 'Username or email is already registered.';
             // CAPTCHA is regenerated at the top of the file if there are errors
            header('Location: form-register.php');
            exit;
        }

        // Save new user to database using the createUser function
        // Password hashing is now handled inside createUser
        $inserted = createUser(
            $full_name_post,
            $username_post,
            $email_post,
            $password_post, // Pass raw password, function will hash it
            $age, // Use the validated integer age
            $gender_post
            // profile_image, bio, google_id are null by default or not provided here
        );


        if ($inserted) {
             // Get the ID of the newly created user
            $userId = $pdo->lastInsertId();
            $_SESSION['user_id'] = $userId;

            // Regenerate session ID after successful registration
            session_regenerate_id(true);

            $_SESSION['success_message'] = 'Registration successful! Welcome, ' . htmlspecialchars($username_post) . '!';

            // Redirect to intended URL if set, otherwise to beranda
            $redirect_url = '../beranda/index.php';
            if (isset($_SESSION['intended_url'])) {
                $redirect_url = $_SESSION['intended_url'];
                unset($_SESSION['intended_url']); // Clear the intended URL
            }

            header('Location: ' . $redirect_url); // Redirect to beranda or intended page
            exit;
        } else {
             // This else block might be hit if execute returns false for other reasons
            $_SESSION['error_message'] = 'Failed to create user account.';
             // CAPTCHA is regenerated at the top of the file if there are errors
            header('Location: form-register.php');
            exit;
        }


    } catch (PDOException $e) {
        error_log("Database error during registration: " . $e->getMessage());
        $_SESSION['error_message'] = 'An internal error occurred during registration. Please try again.';
         // CAPTCHA is regenerated at the top of the file if there are errors
        header('Location: form-register.php');
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
    <link rel="icon" type="image/png" href="../gambar/Untitled142_20250310223718.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Rate Tales - Register</title>
    <!-- Google Sign-In -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>
    <div class="form-container register-form">
        <h2>Register Account</h2>

        <?php if ($error_message): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

         <?php if ($success_message): ?>
            <p class="success-message"><?php echo htmlspecialchars($success_message); ?></p>
        <?php endif; ?>


        <form method="POST" action="form-register.php" onsubmit="return validateForm()">
            <div class="input-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="full_name" placeholder="Enter your full name" required value="<?php echo htmlspecialchars($full_name); ?>">
            </div>
            <div class="input-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter a username" required value="<?php echo htmlspecialchars($username); ?>">
            </div>
            <div class="input-group">
                <label for="usia">Age</label>
                <input type="number" id="usia" name="age" placeholder="Your age" required min="1" value="<?php echo htmlspecialchars($age_input); ?>">
            </div>
            <div class="input-group">
                <label for="gender">Gender</label>
                <select id="gender" name="gender" required>
                    <option value="">Select...</option>
                    <option value="Laki-laki" <?php echo ($gender === 'Laki-laki') ? 'selected' : ''; ?>>Male</option>
                    <option value="Perempuan" <?php echo ($gender === 'Perempuan') ? 'selected' : ''; ?>>Female</option>
                </select>
            </div>
            <div class="input-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required value="<?php echo htmlspecialchars($email); ?>">
            </div>
             <!-- Password Fields - Don't pre-fill for security -->
            <div class="input-group">
                <label for="password">Create Password</label>
                <input type="password" id="password" name="password" placeholder="Create your password" required minlength="6">
            </div>
            <div class="input-group">
                <label for="confirm-password">Confirm Password</label>
                <input type="password" id="confirm-password" name="confirm_password" placeholder="Repeat your password" required>
            </div>

            <div class="input-group">
                 <label>Verify CAPTCHA</label>
                 <div class="captcha-container">
                    <canvas id="captchaCanvas" width="150" height="40"></canvas>
                    <button type="button" onclick="generateCaptcha()" class="btn-reload" title="Reload CAPTCHA"><i class="fas fa-sync-alt"></i></button>
                 </div>
                 <input type="text" name="captcha_input" id="captchaInput" placeholder="Enter CAPTCHA" required autocomplete="off"> <!-- Value is NOT pre-filled for security -->
            </div>

            <div style="text-align: center; margin-bottom: 10px; margin-top: 20px;">
                <button type="button" id="agreement-btn">Read User Agreement</button>
            </div>

            <div class="input-group agreement-checkbox">
                <input type="checkbox" id="agree-checkbox" name="agree" required <?php echo $agree ? 'checked' : ''; ?>>
                <label for="agree-checkbox">I agree to the <a href="#" id="show-agreement-link">User Agreement</a></label>
            </div>

            <button type="submit" class="btn" id="register-submit-btn" disabled>Register</button> <!-- Disabled by default -->
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
                  data-text="signup_with"
                  data-shape="rectangular"
                  data-logo_alignment="left">
             </div>
         </div>


        <p class="form-link">Already have an account? <a href="form-login.php">Login here</a></p>
    </div>

    <!-- Agreement Modal HTML -->
    <div id="agreement-modal">
        <div>
            <h3>User Agreement</h3>
            <p>
                <h5><b>Privacy Policy</b></h5>
                This Privacy Policy explains how Rate Tales (“we”) collects, stores, uses, and protects your personal data during your use of this site. All data management activities are carried out in accordance with the provisions of the Law of the Republic of Indonesia Number 27 of 2022 concerning Personal Data Protection (UU PDP). By using this site and registering your account, you provide explicit consent to us to process your personal data as described in this policy.
                We may collect personal information directly when you register or use features on the site, such as full name, email address, and information related to your activity on the site, including viewing preferences, reviews, ratings, and interaction history. All data we collect is used for legitimate and proportionate purposes, namely to improve your experience using our services. We use it to provide personalized features, provide content recommendations, perform internal analysis, and - with your consent - deliver promotional information or relevant content.
                Data personal Anda akan disimpan selama akun Anda masih aktif, atau selama diperlukan untuk mendukung tujuan layanan. Kami menerapkan langkah-langkah teknis dan organisasi yang sesuai untuk melindungi data Anda dari akses yang tidak sah, kebocoran, atau penyalahgunaan. Kami tidak akan membagikan data personal Anda kepada pihak ketiga tanpa persetujuan eksplisit dari Anda, kecuali jika diharuskan oleh hukum atau dalam konteks penegakan hukum dan kewajiban hukum lainnya.
                Sesuai dengan ketentuan UU PDP, Anda sebagai pemilik data memiliki hak untuk mengakses data personal Anda, meminta perbaikan atau penghapusan data, menarik kembali persetujuan atas pemprosesan data, serta mengajukan keberatan atas pemprosesan tertentu. Kami menghormati hak-hak tersebut dan akan menindaklanjuti setiap permintaan yang Anda sampaikan melalui saluran kontak resmi yang tersedia di situs kami.
                Kami dapat memperbarui isi Kebijakan Privasi ini dari waktu ke waktu, terutama jika terjadi perubahan peraturan atau perkembangan teknologi yang memengaruhi cara kami memproses data personal Anda. Perubahan signifikan akan kami sampaikan melalui notifikasi di situs atau email. Dengan terus menggunakan layanan kami setelah perubahan diberlakukan, Anda dianggap telah menyetujui kebijakan yang diperbarui.
                Jika Anda memiliki pertanyaan, permintaan, atau keluhan terkait kebijakan ini atau penggunaan data personal Anda, Anda dapat menghubungi kami melalui alamat email atau formulir kontak resmi yang tersedia di situs. Dengan menggunakan situs ini, Anda menyatakan telah membaca, memahami, dan menyetujui isi Kebijakan Privasi ini serta memberikan persetujuan eksplisit atas pengumpulan dan pemprosesan data personal Anda oleh kami.
            </p>
            <button id="close-agreement" class="btn">Close</button>
        </div>
    </div>


    <script src="animation.js"></script>
    <script>
        // Variable to store the current CAPTCHA code on the client side
        let currentCaptchaCode = "<?php echo htmlspecialchars($captchaCodeForClient); ?>";

        const captchaInput = document.getElementById('captchaInput');
        const captchaCanvas = document.getElementById('captchaCanvas');
        const agreeCheckbox = document.getElementById('agree-checkbox');
        const registerSubmitBtn = document.getElementById('register-submit-btn');


        function drawCaptcha(code) {
            if (!captchaCanvas) return;
            const ctx = captchaCanvas.getContext('2d');
            ctx.clearRect(0, 0, captchaCanvas.width, captchaCanvas.height);
            ctx.fillStyle = "#0a192f";
            ctx.fillRect(0, 0, captchaCanvas.width, captchaCanvas.height);

            ctx.font = "24px Arial";
            ctx.fillStyle = "#00e4f9";
            ctx.strokeStyle = "#00bcd4";
            ctx.lineWidth = 1;

            for (let i = 0; i < 5; i++) {
                ctx.beginPath();
                ctx.moveTo(Math.random() * captchaCanvas.width, Math.random() * captchaCanvas.height);
                ctx.lineTo(Math.random() * captchaCanvas.width, Math.random() * captchaCanvas.height);
                ctx.stroke();
            }

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
                currentCaptchaCode = newCaptchaCode;
                drawCaptcha(currentCaptchaCode);
                captchaInput.value = '';
            } catch (error) {
                console.error("Error generating CAPTCHA:", error);
                // Optionally display an error message on the page
            }
        }

        // Initial drawing
        document.addEventListener('DOMContentLoaded', () => {
            drawCaptcha(currentCaptchaCode);

             // Set initial state of the Register button based on the agreement checkbox
             if(registerSubmitBtn && agreeCheckbox) {
                 registerSubmitBtn.disabled = !agreeCheckbox.checked;
             }
             // Optional: clear CAPTCHA input on page load for security
             captchaInput.value = '';
        });


        // Client-side form validation
        function validateForm() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            const agreeCheckbox = document.getElementById('agree-checkbox');
            const captchaInput = document.getElementById('captchaInput').value; // Get current input value

            // Basic password match check
            if (password !== confirmPassword) {
                alert('Password and Confirm Password do not match!');
                return false; // Prevent form submission
            }

             // Check agreement
             if (!agreeCheckbox.checked) {
                 alert('You must agree to the User Agreement.');
                 return false; // Prevent form submission
            }

            // Client-side CAPTCHA check (optional, server-side is mandatory)
            // This provides immediate feedback but is not the primary security check
            // if (captchaInput.toLowerCase() !== currentCaptchaCode.toLowerCase()) {
            //      alert('Invalid CAPTCHA!');
            //      generateCaptcha(); // Regenerate CAPTCHA on client-side failure
            //      return false; // Prevent form submission
            // }

            // If all client-side checks pass, allow form submission
            // Server-side validation (including CAPTCHA) will run upon POST
            return true;
        }

        // Modal Agreement Logic
        const agreementBtn = document.getElementById('agreement-btn');
        const showAgreementLink = document.getElementById('show-agreement-link');
        const agreementModal = document.getElementById('agreement-modal');
        const closeAgreement = document.getElementById('close-agreement');


        function showAgreementModal() {
            if(agreementModal) {
                 agreementModal.style.display = 'flex'; // Use flex to center
            }
        }

        if(agreementBtn) agreementBtn.addEventListener('click', showAgreementModal);
        if(showAgreementLink) showAgreementLink.addEventListener('click', (e) => {
            e.preventDefault();
            showAgreementModal();
        });
        if(closeAgreement) closeAgreement.addEventListener('click', () => {
            if(agreementModal) agreementModal.style.display = 'none';
        });

        // Close modal if clicking outside content
        if(agreementModal) {
            agreementModal.addEventListener('click', (e) => {
                if (e.target === agreementModal) {
                    agreementModal.style.display = 'none';
                }
            });
        }

        // Enable/disable Register button based on agreement checkbox
        if(agreeCheckbox && registerSubmitBtn) {
            agreeCheckbox.addEventListener('change', () => {
                registerSubmitBtn.disabled = !agreeCheckbox.checked;
            });
        }

        // Google Sign-In handler
         function handleCredentialResponse(response) {
            console.log("Encoded JWT ID token: " + response.credential);
            // Send this token to your server for validation
            fetch('verify_google_login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'credential=' + response.credential
            })
            .then(response => response.json()) // Assuming your PHP returns JSON
            .then(data => {
                if (data.success) {
                    // Redirect on successful login/registration
                    window.location.href = data.redirect || '../beranda/index.php';
                } else {
                    // Display error message
                     alert('Google registration failed: ' + (data.message || 'Unknown error')); // Simple alert for now
                     const errorMessageElement = document.querySelector('.error-message');
                     if (errorMessageElement) {
                         errorMessageElement.innerText = 'Google registration failed: ' + (data.message || 'Unknown error');
                         errorMessageElement.style.display = 'block';
                     } else {
                          const newErrorMessageElement = document.createElement('p');
                          newErrorMessageElement.className = 'error-message';
                          newErrorMessageElement.innerText = 'Google registration failed: ' + (data.message || 'Unknown error');
                          document.querySelector('.form-container').prepend(newErrorMessageElement);
                     }
                }
            })
            .catch(error => {
                console.error('Error verifying Google token:', error);
                alert('An error occurred during Google registration.');
            });
         }
    </script>
</body>
</html>