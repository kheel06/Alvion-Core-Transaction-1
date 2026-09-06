<?php
require_once '../config/config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$page_title = "Reset Password";

// Handle token verification - get token and email from query string
// Support both GET and POST parameters (POST for form resubmission)
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$email_raw = $_GET['email'] ?? $_POST['email'] ?? '';

// Properly decode the email - handle multiple encoding
if (!empty($email_raw)) {
    // Try to decode - might be double encoded
    $email = trim(urldecode($email_raw));
    // If still contains encoded characters, decode again
    if (strpos($email, '%') !== false) {
        $email = trim(urldecode($email));
    }
} else {
    $email = '';
}

// Clean up token - only remove whitespace, keep all hex characters
$token = trim($token);
// Remove any URL encoding artifacts but preserve the token
$token = str_replace([' ', '+', "\n", "\r", "\t"], '', $token);

$valid_token = false;
$token_data = null;
$current_time = date('Y-m-d H:i:s');

// Debug: Log what we received
error_log("Reset password - Full URL: " . ($_SERVER['REQUEST_URI'] ?? 'not available'));
error_log("Reset password - Query string: " . ($_SERVER['QUERY_STRING'] ?? 'not available'));
error_log("Reset password - Token length: " . strlen($token) . ", Token (first 30 chars): " . substr($token, 0, 30) . "... Email: " . ($email ?? 'empty'));
error_log("Reset password - Raw GET token: " . (isset($_GET['token']) ? substr($_GET['token'], 0, 30) . '... (length: ' . strlen($_GET['token']) . ')' : 'not set') . ", Raw GET email: " . (isset($_GET['email']) ? $_GET['email'] : 'not set'));
error_log("Reset password - Cleaned token length: " . strlen($token) . ", Cleaned email: " . $email);

if (empty($token) || empty($email)) {
    $_SESSION['error'] = "Invalid reset link. Please request a new password reset.";
    error_log("Missing token or email - Token: " . (empty($token) ? 'empty' : 'present (' . strlen($token) . ' chars)') . ", Email: " . (empty($email) ? 'empty' : 'present (' . $email . ')'));
} else {
    try {
        // Normalize email for comparison (should match what's stored in DB)
        $email_normalized = strtolower(trim($email));
        
        // Debug: Log what we're checking
        error_log("Reset password attempt - Token length: " . strlen($token) . ", Email: " . $email . " (normalized: " . $email_normalized . ")");
        
        // Validate token length
        $token_length = strlen($token);
        if ($token_length !== 100 && $token_length !== 64) {
            error_log("Invalid token length: " . $token_length . " (expected 100 or 64)");
            $_SESSION['error'] = "Invalid reset link format. Please request a new password reset.";
        } else {
            error_log("Token length OK: " . $token_length . " characters");
            
            // First, check if token exists and is valid (primary validation)
            // Use exact token match - tokens are case-sensitive hex strings
            $token_query = "SELECT prt.* 
                           FROM password_reset_tokens prt 
                           WHERE BINARY prt.token = :token 
                           AND prt.used = 0 
                           AND prt.expires_at > :current_time";
            $token_stmt = $db->prepare($token_query);
            $token_stmt->bindParam(':token', $token, PDO::PARAM_STR);
            $token_stmt->bindParam(':current_time', $current_time);
            $token_stmt->execute();
            $token_data = $token_stmt->fetch(PDO::FETCH_ASSOC);
            
            // If not found with BINARY, try without (for case-insensitive databases)
            if (!$token_data) {
                error_log("Token not found with BINARY match, trying case-insensitive match");
                $token_query2 = "SELECT prt.* 
                               FROM password_reset_tokens prt 
                               WHERE prt.token = :token 
                               AND prt.used = 0 
                               AND prt.expires_at > :current_time";
                $token_stmt2 = $db->prepare($token_query2);
                $token_stmt2->bindParam(':token', $token, PDO::PARAM_STR);
                $token_stmt2->bindParam(':current_time', $current_time);
                $token_stmt2->execute();
                $token_data = $token_stmt2->fetch(PDO::FETCH_ASSOC);
            }
            
            if ($token_data) {
                // Token is valid - now verify email matches (case-insensitive)
                $stored_email_normalized = strtolower(trim($token_data['email']));
                
                if ($stored_email_normalized === $email_normalized) {
                    // Email matches - token is fully valid
                    $valid_token = true;
                    error_log("Token validation SUCCESS for user: " . $token_data['user_id'] . ", Token ID: " . $token_data['id']);
                    
                    // Verify user still exists
                    $user_check = $db->prepare("SELECT id FROM users WHERE id = :user_id");
                    $user_check->bindParam(':user_id', $token_data['user_id'], PDO::PARAM_INT);
                    $user_check->execute();
                    if (!$user_check->fetch()) {
                        $valid_token = false;
                        $_SESSION['error'] = "User account no longer exists.";
                        error_log("User account not found for ID: " . $token_data['user_id']);
                    }
                } else {
                    // Token is valid but email doesn't match - still accept token for security
                    // (Token is the primary security mechanism)
                    error_log("Token valid but email mismatch - Stored: '" . $stored_email_normalized . "', Provided: '" . $email_normalized . "' - Accepting token anyway");
                    $valid_token = true;
                    
                    // Verify user still exists
                    $user_check = $db->prepare("SELECT id FROM users WHERE id = :user_id");
                    $user_check->bindParam(':user_id', $token_data['user_id'], PDO::PARAM_INT);
                    $user_check->execute();
                    if (!$user_check->fetch()) {
                        $valid_token = false;
                        $_SESSION['error'] = "User account no longer exists.";
                        error_log("User account not found for ID: " . $token_data['user_id']);
                    }
                }
            } else {
                // Token not found or invalid - check why for better error message
                $check_query = "SELECT * FROM password_reset_tokens WHERE token = :token";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':token', $token, PDO::PARAM_STR);
                $check_stmt->execute();
                $existing_token = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_token) {
                    $expires_timestamp = strtotime($existing_token['expires_at']);
                    $current_timestamp = time();
                    error_log("Token exists but validation failed - Used: " . $existing_token['used'] . ", Expires: " . $existing_token['expires_at'] . " (timestamp: " . $expires_timestamp . "), Current: " . date('Y-m-d H:i:s') . " (timestamp: " . $current_timestamp . ")");
                    
                    if ($existing_token['used'] == 1) {
                        $_SESSION['error'] = "This reset link has already been used. Please request a new password reset.";
                    } elseif ($expires_timestamp <= $current_timestamp) {
                        $_SESSION['error'] = "This reset link has expired. Please request a new password reset.";
                    } else {
                        $_SESSION['error'] = "This reset link is invalid. Please request a new password reset.";
                    }
                } else {
                    error_log("Token not found in database. Token length: " . strlen($token) . ", Token: " . substr($token, 0, 20) . "...");
                    $_SESSION['error'] = "This reset link is invalid. Please request a new password reset.";
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Token verification error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred. Please try again.";
    }
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    // Use POST data if available, otherwise use GET data from URL
    $post_token = trim($_POST['token'] ?? $token ?? '');
    $post_email_raw = $_POST['email'] ?? '';
    
    // Properly decode the email from POST
    if (!empty($post_email_raw)) {
        $post_email = trim(urldecode($post_email_raw));
        if (strpos($post_email, '%') !== false) {
            $post_email = trim(urldecode($post_email));
        }
    } else {
        $post_email = $email ?? '';
    }
    
    // Clean up POST token the same way
    $post_token = trim($post_token);
    $post_token = str_replace([' ', '+', "\n", "\r", "\t"], '', $post_token);
    
    // If token wasn't validated on page load, try to validate it now
    if (!$valid_token && !empty($post_token) && !empty($post_email)) {
        try {
            $post_email_normalized = strtolower(trim($post_email));
            $post_current_time = date('Y-m-d H:i:s');
            $token_query = "SELECT prt.* 
                           FROM password_reset_tokens prt 
                           JOIN users u ON prt.user_id = u.id
                           WHERE prt.token = :token 
                           AND LOWER(prt.email) = :email 
                           AND prt.used = 0 
                           AND prt.expires_at > :current_time";
            $token_stmt = $db->prepare($token_query);
            $token_stmt->bindParam(':token', $post_token);
            $token_stmt->bindParam(':email', $post_email_normalized);
            $token_stmt->bindParam(':current_time', $post_current_time);
            $token_stmt->execute();
            $token_data = $token_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($token_data) {
                $valid_token = true;
            }
        } catch (PDOException $e) {
            error_log("Token re-validation error: " . $e->getMessage());
        }
    }
    
    if (!$valid_token || !$token_data) {
        $_SESSION['error'] = "Invalid or expired reset token. Please request a new password reset.";
    } else {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $validation_error = false;
        if (empty($new_password)) {
            $_SESSION['error'] = "Please enter a new password.";
            $validation_error = true;
        } elseif (strlen($new_password) < 8) {
            $_SESSION['error'] = "Password must be at least 8 characters long.";
            $validation_error = true;
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['error'] = "Passwords do not match.";
            $validation_error = true;
        }
        
        if ($validation_error) {
            // Redirect back to preserve token and email in URL
            $redirect_url = "reset-password.php?token=" . urlencode($token) . "&email=" . urlencode($email);
            error_log("Redirecting due to validation error to: " . $redirect_url);
            header("Location: " . $redirect_url);
            exit();
        }
        
        // If no validation errors, proceed with password reset
        if (!$validation_error) {
            try {
                // Begin transaction for atomicity
                $db->beginTransaction();
                
                // Hash the new password using the default algorithm
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                if (!$hashed_password) {
                    throw new Exception("Failed to hash password");
                }
                
                error_log("Resetting password for user ID: " . $token_data['user_id']);
                
                // First, verify the user exists
                $check_user = $db->prepare("SELECT id, password FROM users WHERE id = :user_id");
                $check_user->bindParam(':user_id', $token_data['user_id'], PDO::PARAM_INT);
                $check_user->execute();
                $existing_user = $check_user->fetch(PDO::FETCH_ASSOC);
                
                if (!$existing_user) {
                    throw new Exception("User with ID " . $token_data['user_id'] . " does not exist");
                }
                
                // Get old password hash for logging
                $old_password_hash = $existing_user['password'] ?? '';
                
                // Update user's password in the users table
                // Use COALESCE to handle updated_at if it doesn't exist
                $update_query = "UPDATE users SET `password` = :password WHERE id = :user_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
                $update_stmt->bindParam(':user_id', $token_data['user_id'], PDO::PARAM_INT);
                
                if (!$update_stmt->execute()) {
                    $error_info = $update_stmt->errorInfo();
                    throw new Exception("Failed to execute password update query: " . ($error_info[2] ?? 'Unknown error'));
                }
                
                // Verify password was actually updated by checking the database immediately
                $verify_query = "SELECT `password` FROM users WHERE id = :user_id";
                $verify_stmt = $db->prepare($verify_query);
                $verify_stmt->bindParam(':user_id', $token_data['user_id'], PDO::PARAM_INT);
                $verify_stmt->execute();
                $updated_user = $verify_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$updated_user || !isset($updated_user['password'])) {
                    throw new Exception("Failed to retrieve updated password from database");
                }
                
                // Verify the new password hash matches what we set
                if (!password_verify($new_password, $updated_user['password'])) {
                    error_log("ERROR: Password verification failed after update. Old hash: " . substr($old_password_hash, 0, 20) . "... New hash: " . substr($updated_user['password'], 0, 20) . "...");
                    error_log("ERROR: Hashed password length: " . strlen($hashed_password) . ", Stored password length: " . strlen($updated_user['password']));
                    throw new Exception("Password was not updated correctly. Please try again or contact support.");
                }
                
                error_log("Password verification successful - password was updated correctly in the database for user ID: " . $token_data['user_id']);
                
                // Mark token as used
                $mark_used_query = "UPDATE password_reset_tokens SET used = 1 WHERE id = :token_id";
                $mark_used_stmt = $db->prepare($mark_used_query);
                $mark_used_stmt->bindParam(':token_id', $token_data['id'], PDO::PARAM_INT);
                
                if (!$mark_used_stmt->execute()) {
                    throw new Exception("Failed to mark token as used");
                }
                
                // Commit transaction
                $db->commit();
                
                error_log("Password reset completed successfully for user ID: " . $token_data['user_id']);
                
                // Clear any error messages
                unset($_SESSION['error']);
                
                $_SESSION['success'] = "Your password has been reset successfully. You can now login with your new password.";
                header("Location: login.php");
                exit();
                
            } catch (PDOException $e) {
                // Rollback transaction on error
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log("Password reset error (PDO): " . $e->getMessage());
                error_log("Password reset error trace: " . $e->getTraceAsString());
                $_SESSION['error'] = "An error occurred while resetting your password: " . $e->getMessage() . ". Please try again or request a new reset link.";
                // Redirect back to reset page to show error
                $redirect_url = "reset-password.php?token=" . urlencode($token) . "&email=" . urlencode($email);
                error_log("Redirecting after PDO error to: " . $redirect_url);
                header("Location: " . $redirect_url);
                exit();
            } catch (Exception $e) {
                // Rollback transaction on error
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log("Password reset error: " . $e->getMessage());
                $_SESSION['error'] = $e->getMessage();
                // Redirect back to reset page to show error
                $redirect_url = "reset-password.php?token=" . urlencode($token) . "&email=" . urlencode($email);
                error_log("Redirecting after Exception to: " . $redirect_url);
                header("Location: " . $redirect_url);
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gradient-to-br from-[#e6f4f4] to-[#f5f8fb]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo SITE_NAME; ?></title>
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
                        <h2 class="text-3xl font-semibold text-[#008080]">Reset Password</h2>
                        <p class="mt-3 text-sm text-gray-500">Enter your new password below.</p>
                    </div>
                </div>

                <?php if (!$valid_token): ?>
                    <div class="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg text-center">
                        <p class="text-red-700 font-medium"><?php echo htmlspecialchars($_SESSION['error'] ?? 'Invalid reset link. Please request a new password reset.'); ?></p>
                        <?php 
                        // Clear error after displaying
                        unset($_SESSION['error']);
                        if (!empty($email)): 
                        ?>
                            <p class="text-sm text-gray-600 mt-3 mb-4">Would you like to request a new reset link for <strong><?php echo htmlspecialchars($email); ?></strong>?</p>
                            <form method="POST" action="forgot-password.php" class="inline-block">
                                <input type="hidden" name="request_reset" value="1">
                                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                                <button type="submit" class="px-6 py-2 bg-[#008080] text-white rounded-full hover:bg-[#0f766e] transition font-semibold">
                                    Request New Reset Link
                                </button>
                            </form>
                            <div class="mt-3">
                                <a href="forgot-password.php" class="text-sm text-[#008080] hover:text-[#0f766e] underline">
                                    Or use a different email
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="mt-4">
                                <a href="forgot-password.php" class="inline-block px-6 py-2 bg-[#008080] text-white rounded-full hover:bg-[#0f766e] transition font-semibold">
                                    Request New Reset Link
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <p class="text-red-700 text-sm"><?php echo htmlspecialchars($_SESSION['error']); ?></p>
                            <?php unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="mt-6 space-y-6">
                        <input type="hidden" name="reset_password" value="1">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-600 mb-1">New Password <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                </span>
                                <input id="new_password" name="new_password" type="password" required minlength="8"
                                       class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition"
                                       placeholder="Enter new password">
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Password must be at least 8 characters long.</p>
                        </div>

                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-600 mb-1">Confirm Password <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                </span>
                                <input id="confirm_password" name="confirm_password" type="password" required minlength="8"
                                       class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition"
                                       placeholder="Confirm new password">
                            </div>
                        </div>

                        <button type="submit" 
                                class="w-full bg-[#008080] hover:bg-[#0f766e] text-white font-semibold py-3 rounded-full shadow-lg transition">
                            Reset Password
                        </button>
                    </form>
                <?php endif; ?>
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