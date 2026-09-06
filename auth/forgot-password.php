<?php
require_once '../config/config.php';
require_once '../includes/email_helper.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$page_title = "Forgot Password";

// Clear any previous error messages when page loads (unless it's a form submission)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Keep success messages but clear errors on fresh page load
    if (isset($_SESSION['error']) && !isset($_GET['error'])) {
        // Don't clear if there's an error parameter in URL
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $email = sanitizeInput($_POST['email'] ?? '');
    
    if (empty($email)) {
        $_SESSION['error'] = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email address.";
    } else {
        try {
            // Normalize email to lowercase for case-insensitive comparison
            $email_normalized = strtolower(trim($email));
            
            // Check if email exists in users table (case-insensitive)
            $user_query = "SELECT id, email, first_name, last_name FROM users WHERE LOWER(email) = :email LIMIT 1";
            $user_stmt = $db->prepare($user_query);
            $user_stmt->bindParam(':email', $email_normalized);
            $user_stmt->execute();
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Only proceed if email exists in users table
            if (!$user) {
                $_SESSION['error'] = "This email address is not registered in our system. Please check your email and try again.";
            } else {
                // Ensure password_reset_tokens table exists
                try {
                    $db->exec("CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `user_id` int(11) NOT NULL,
                        `email` varchar(255) NOT NULL,
                        `token` varchar(255) NOT NULL,
                        `expires_at` datetime NOT NULL,
                        `used` tinyint(1) DEFAULT 0,
                        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `token` (`token`),
                        KEY `user_id` (`user_id`),
                        KEY `email` (`email`),
                        KEY `expires_at` (`expires_at`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
                } catch (PDOException $e) {
                    // Table might already exist, continue
                    error_log("Password reset table check: " . $e->getMessage());
                }
                
                // Invalidate any existing unused tokens for this user
                $invalidate_query = "UPDATE password_reset_tokens SET used = 1 WHERE user_id = :user_id AND used = 0";
                $invalidate_stmt = $db->prepare($invalidate_query);
                $invalidate_stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
                $invalidate_stmt->execute();
                
                // Generate secure token (100 character hex string)
                $token = bin2hex(random_bytes(50));
                
                // Set expiration to 60 minutes from now
                $expires_at = date('Y-m-d H:i:s', strtotime('+60 minutes'));
                $created_at = date('Y-m-d H:i:s');
                
                // Insert token into database
                $token_query = "INSERT INTO password_reset_tokens (user_id, email, token, expires_at, created_at) VALUES (:user_id, :email, :token, :expires_at, :created_at)";
                $token_stmt = $db->prepare($token_query);
                $token_stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
                $token_stmt->bindParam(':email', $email_normalized);
                $token_stmt->bindParam(':token', $token);
                $token_stmt->bindParam(':expires_at', $expires_at);
                $token_stmt->bindParam(':created_at', $created_at);
                
                // Debug: Log token creation
                error_log("Creating password reset token - User ID: " . $user['id'] . ", Email (normalized): " . $email_normalized . ", Token length: " . strlen($token) . ", Expires: " . $expires_at);
                
                if ($token_stmt->execute()) {
                    error_log("Token successfully inserted into database");
                    
                    // Verify token was stored correctly by retrieving it
                    $verify_query = "SELECT token, email FROM password_reset_tokens WHERE token = :token";
                    $verify_stmt = $db->prepare($verify_query);
                    $verify_stmt->bindParam(':token', $token);
                    $verify_stmt->execute();
                    $verified = $verify_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($verified) {
                        error_log("Token verification - Stored token length: " . strlen($verified['token']) . ", Stored email: " . $verified['email']);
                        if ($verified['token'] !== $token) {
                            error_log("ERROR: Token mismatch! Original: " . substr($token, 0, 20) . ", Stored: " . substr($verified['token'], 0, 20));
                        }
                    } else {
                        error_log("ERROR: Token was not found after insertion!");
                    }
                    
                    // Send password reset email
                    $user_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'User';
                    
 // In forgot-password.php, around line 85:
$email_result = sendPasswordResetEmail($user['email'], $user_name, $token, $email_normalized);
                    
                    // Debug: Check if email was actually sent
                    error_log("Password reset email attempt for: " . $user['email'] . " - Success: " . ($email_result['success'] ? 'Yes' : 'No'));
                    if (!$email_result['success']) {
                        error_log("Email error: " . $email_result['message']);
                    }
                    
                    if ($email_result['success']) {
                        $_SESSION['success'] = "Password reset link has been sent to your email. Please check your inbox.";
                        // Redirect to prevent form resubmission
                        header("Location: forgot-password.php?sent=1");
                        exit();
                    } else {
                        $_SESSION['error'] = "Failed to send password reset email. Please try again later.";
                        error_log("Password reset email failed for {$user['email']}: " . $email_result['message']);
                    }
                } else {
                    $_SESSION['error'] = "Failed to generate reset token. Please try again.";
                }
            }
        } catch (PDOException $e) {
            error_log("Password reset request error: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gradient-to-br from-[#e6f4f4] to-[#f5f8fb]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/toast.js"></script>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>/assets/img/alvion-emblem-removebg.png">
    <style>
        .login-card {
            box-shadow: 0 30px 60px rgba(0, 128, 128, 0.15);
        }
        .login-input {
            background-color: rgba(255, 255, 255, 0.85);
        }
        .login-input:focus {
            background-color: #ffffff;
        }
    </style>
</head>
<body class="h-full">
    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 flex flex-col gap-3 pointer-events-none"></div>
    
    <div class="min-h-full flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md bg-white rounded-3xl overflow-hidden login-card">
            <div class="px-8 py-10">
                <div>
                    <div class="text-center">
                        <div class="flex justify-center mb-4">
                            <img src="<?php echo BASE_URL; ?>/assets/img/alvion-logo-removebg.png" alt="Alvion logo" class="h-14 md:h-16">
                        </div>
                        <h2 class="text-3xl font-semibold text-[#008080]">Forgot Password?</h2>
                        <?php if (isset($_GET['sent']) && isset($_SESSION['success'])): ?>
                            <p class="mt-3 text-sm text-green-600 font-medium"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></p>
                        <?php else: ?>
                            <p class="mt-3 text-sm text-gray-500">Enter your email address and we'll send you a link to reset your password.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <form method="POST" class="mt-6 space-y-6">
                    <input type="hidden" name="request_reset" value="1">
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-600 mb-1">Email Address <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                            </span>
                            <input id="email" name="email" type="email" required autocomplete="email"
                                   class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition"
                                   placeholder="you@example.com"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : (isset($_GET['email']) ? htmlspecialchars(urldecode($_GET['email'])) : ''); ?>">
                        </div>
                    </div>

                    <button type="submit" 
                            class="w-full bg-[#008080] hover:bg-[#0f766e] text-white font-semibold py-3 rounded-full shadow-lg transition">
                        Send Reset Link
                    </button>
                </form>

                <p class="mt-6 text-sm text-gray-500 text-center">
                    Remember your password?
                    <a href="login.php" class="font-semibold text-[#008080] hover:text-[#0f766e]">Back to Login</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show toast notifications from PHP session
            <?php if (isset($_SESSION['error'])): ?>
                showToast('error', <?php echo json_encode($_SESSION['error']); ?>);
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                showToast('success', <?php echo json_encode($_SESSION['success']); ?>);
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>

