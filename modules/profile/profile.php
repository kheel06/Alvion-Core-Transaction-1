<?php
/**
 * User Profile Page
 * Allows users to view and edit their own profile information
 */
require_once '../../config/config.php';
requireAuth();

if (!function_exists('ensureUsersProfilePictureColumn')) {
    function ensureUsersProfilePictureColumn(PDO $db): void {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
            if ($stmt && $stmt->rowCount() === 0) {
                $db->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL AFTER last_name");
            }
        } catch (PDOException $e) {
            error_log("Failed to ensure users.profile_picture column: " . $e->getMessage());
        }
    }
}

/**
 * Remove user profile picture
 * Deletes the file and updates the database, then clears session
 */
if (!function_exists('removeUserProfilePicture')) {
    function removeUserProfilePicture(PDO $db, int $user_id, string $upload_dir, ?string $current_picture_path = null): array {
        $result = ['success' => false, 'message' => ''];
        
        try {
            // Delete physical file if it exists
            if (!empty($current_picture_path)) {
                $old_file = $upload_dir . basename($current_picture_path);
                if (file_exists($old_file)) {
                    if (!unlink($old_file)) {
                        error_log("Failed to delete profile picture file: {$old_file}");
                    }
                }
            }
            
            // Update database to set profile_picture to NULL
            $update_query = "UPDATE users SET profile_picture = NULL WHERE id = :user_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            
            if ($update_stmt->execute()) {
                // Clear session variable so header.php reflects the change
                unset($_SESSION['profile_picture']);
                
                $result['success'] = true;
                $result['message'] = "Profile picture removed successfully!";
            } else {
                $result['message'] = "Failed to remove profile picture from database.";
            }
        } catch (PDOException $e) {
            error_log("Error removing profile picture: " . $e->getMessage());
            $result['message'] = "Error removing profile picture: " . $e->getMessage();
        }
        
        return $result;
    }
}

$page_title = "My Profile";

// Get current user information - fetch ALL fields from users table
try {
    // Fetch all columns from users table
    $user_query = "SELECT 
        id, username, email, password, first_name, last_name, middle_name, suffix,
        birth_date, age, gender, civil_status, nationality, birth_place,
        occupation, occupation_other, government_id_type, government_id_number,
        house_number, barangay, city_municipality, province, region, zip_code,
        contact_number, emergency_contact_name, emergency_contact_number,
        emergency_contact_relationship, profile_picture, role_id, status, is_active,
        last_login, created_at, updated_at
        FROM users WHERE id = :user_id";
    
    $user_stmt = $db->prepare($user_query);
    $user_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $user_stmt->execute();
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['error'] = "User not found.";
        header("Location: ../../index.php");
        exit();
    }

    // Set default values for fields that might be NULL
    $user['profile_picture'] = $user['profile_picture'] ?? null;
    $user['middle_name'] = $user['middle_name'] ?? '';
    $user['suffix'] = $user['suffix'] ?? '';
    $user['birth_date'] = $user['birth_date'] ?? '';
    $user['age'] = $user['age'] ?? '';
    $user['gender'] = $user['gender'] ?? '';
    $user['civil_status'] = $user['civil_status'] ?? '';
    $user['nationality'] = $user['nationality'] ?? 'Filipino';
    $user['birth_place'] = $user['birth_place'] ?? '';
    $user['occupation'] = $user['occupation'] ?? '';
    $user['occupation_other'] = $user['occupation_other'] ?? '';
    $user['government_id_type'] = $user['government_id_type'] ?? '';
    $user['government_id_number'] = $user['government_id_number'] ?? '';
    $user['house_number'] = $user['house_number'] ?? '';
    $user['barangay'] = $user['barangay'] ?? '';
    $user['city_municipality'] = $user['city_municipality'] ?? '';
    $user['province'] = $user['province'] ?? '';
    $user['region'] = $user['region'] ?? '';
    $user['zip_code'] = $user['zip_code'] ?? '';
    $user['contact_number'] = $user['contact_number'] ?? '';
    $user['emergency_contact_name'] = $user['emergency_contact_name'] ?? '';
    $user['emergency_contact_number'] = $user['emergency_contact_number'] ?? '';
    $user['emergency_contact_relationship'] = $user['emergency_contact_relationship'] ?? '';

    if (!empty($user['profile_picture'])) {
        $_SESSION['profile_picture'] = $user['profile_picture'];
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error loading profile: " . $e->getMessage();
    header("Location: ../../index.php");
    exit();
}

// Profile picture upload directory
$upload_dir = __DIR__ . '/../../assets/uploads/profile_pictures/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Handle form submission - update all profile fields
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Personal Information
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $middle_name = isset($_POST['middle_name']) && !empty($_POST['middle_name']) ? sanitizeInput($_POST['middle_name']) : null;
    $suffix = isset($_POST['suffix']) && !empty($_POST['suffix']) ? sanitizeInput($_POST['suffix']) : null;
    $email = sanitizeInput($_POST['email'] ?? '');
    $username = sanitizeInput($_POST['username'] ?? '');
    $birth_date = isset($_POST['birth_date']) && !empty($_POST['birth_date']) ? sanitizeInput($_POST['birth_date']) : null;
    $gender = isset($_POST['gender']) && !empty($_POST['gender']) ? sanitizeInput($_POST['gender']) : null;
    $civil_status = isset($_POST['civil_status']) && !empty($_POST['civil_status']) ? sanitizeInput($_POST['civil_status']) : null;
    $nationality = isset($_POST['nationality']) && !empty($_POST['nationality']) ? sanitizeInput($_POST['nationality']) : 'Filipino';
    $birth_place = isset($_POST['birth_place']) && !empty($_POST['birth_place']) ? sanitizeInput($_POST['birth_place']) : null;
    
    // Calculate age from birth_date
    $age = null;
    if ($birth_date) {
        try {
            $birth = new DateTime($birth_date);
            $today = new DateTime();
            $age = $today->diff($birth)->y;
        } catch (Exception $e) {
            $age = null;
        }
    }
    
    // Occupation
    $occupation = isset($_POST['occupation']) && !empty($_POST['occupation']) ? sanitizeInput($_POST['occupation']) : null;
    $occupation_other = null;
    if ($occupation === 'Other' && isset($_POST['occupation_other']) && !empty($_POST['occupation_other'])) {
        $occupation_other = sanitizeInput($_POST['occupation_other']);
        $occupation = null;
    }
    
    // Government ID
    $government_id_type = isset($_POST['government_id_type']) && !empty($_POST['government_id_type']) ? sanitizeInput($_POST['government_id_type']) : null;
    $government_id_number = isset($_POST['government_id_number']) && !empty($_POST['government_id_number']) ? sanitizeInput($_POST['government_id_number']) : null;
    
    // Address
    $house_number = isset($_POST['house_number']) && !empty($_POST['house_number']) ? sanitizeInput($_POST['house_number']) : null;
    $region = isset($_POST['region']) && !empty($_POST['region']) ? sanitizeInput($_POST['region']) : null;
    $province = isset($_POST['province']) && !empty($_POST['province']) ? sanitizeInput($_POST['province']) : null;
    $city_municipality = isset($_POST['city_municipality']) && !empty($_POST['city_municipality']) ? sanitizeInput($_POST['city_municipality']) : null;
    $barangay = isset($_POST['barangay']) && !empty($_POST['barangay']) ? sanitizeInput($_POST['barangay']) : null;
    $zip_code = isset($_POST['zip_code']) && !empty($_POST['zip_code']) ? sanitizeInput($_POST['zip_code']) : null;
    
    // Contact
    $contact_number = isset($_POST['contact_number']) && !empty($_POST['contact_number']) ? sanitizeInput($_POST['contact_number']) : null;
    $contact_number_clean = $contact_number ? str_replace(' ', '', $contact_number) : null;
    
    // Emergency Contact
    $emergency_contact_name = isset($_POST['emergency_contact_name']) && !empty($_POST['emergency_contact_name']) ? sanitizeInput($_POST['emergency_contact_name']) : null;
    $emergency_contact_number = isset($_POST['emergency_contact_number']) && !empty($_POST['emergency_contact_number']) ? sanitizeInput($_POST['emergency_contact_number']) : null;
    $emergency_contact_number_clean = $emergency_contact_number ? str_replace(' ', '', $emergency_contact_number) : null;
    $emergency_contact_relationship = isset($_POST['emergency_contact_relationship']) && !empty($_POST['emergency_contact_relationship']) ? sanitizeInput($_POST['emergency_contact_relationship']) : null;
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email address.";
    } else {
        try {
            // Check if email or username is already taken by another user
            $check_query = "SELECT id FROM users WHERE (email = :email OR username = :username) AND id != :user_id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $_SESSION['error'] = "Email or username already taken by another user.";
            } else {
                // Update user profile with ALL fields
                $update_query = "UPDATE users SET 
                    first_name = :first_name, 
                    last_name = :last_name, 
                    middle_name = :middle_name,
                    suffix = :suffix,
                    email = :email, 
                    username = :username,
                    birth_date = :birth_date,
                    age = :age,
                    gender = :gender,
                    civil_status = :civil_status,
                    nationality = :nationality,
                    birth_place = :birth_place,
                    occupation = :occupation,
                    occupation_other = :occupation_other,
                    government_id_type = :government_id_type,
                    government_id_number = :government_id_number,
                    house_number = :house_number,
                    barangay = :barangay,
                    city_municipality = :city_municipality,
                    province = :province,
                    region = :region,
                    zip_code = :zip_code,
                    contact_number = :contact_number,
                    emergency_contact_name = :emergency_contact_name,
                    emergency_contact_number = :emergency_contact_number,
                    emergency_contact_relationship = :emergency_contact_relationship
                    WHERE id = :user_id";
                
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':first_name', $first_name);
                $update_stmt->bindParam(':last_name', $last_name);
                $update_stmt->bindParam(':middle_name', $middle_name, PDO::PARAM_NULL);
                $update_stmt->bindParam(':suffix', $suffix, PDO::PARAM_NULL);
                $update_stmt->bindParam(':email', $email);
                $update_stmt->bindParam(':username', $username);
                $update_stmt->bindParam(':birth_date', $birth_date, PDO::PARAM_NULL);
                $update_stmt->bindParam(':age', $age, PDO::PARAM_INT);
                $update_stmt->bindParam(':gender', $gender, PDO::PARAM_NULL);
                $update_stmt->bindParam(':civil_status', $civil_status, PDO::PARAM_NULL);
                $update_stmt->bindParam(':nationality', $nationality);
                $update_stmt->bindParam(':birth_place', $birth_place, PDO::PARAM_NULL);
                $update_stmt->bindParam(':occupation', $occupation, PDO::PARAM_NULL);
                $update_stmt->bindParam(':occupation_other', $occupation_other, PDO::PARAM_NULL);
                $update_stmt->bindParam(':government_id_type', $government_id_type, PDO::PARAM_NULL);
                $update_stmt->bindParam(':government_id_number', $government_id_number, PDO::PARAM_NULL);
                $update_stmt->bindParam(':house_number', $house_number, PDO::PARAM_NULL);
                $update_stmt->bindParam(':barangay', $barangay, PDO::PARAM_NULL);
                $update_stmt->bindParam(':city_municipality', $city_municipality, PDO::PARAM_NULL);
                $update_stmt->bindParam(':province', $province, PDO::PARAM_NULL);
                $update_stmt->bindParam(':region', $region, PDO::PARAM_NULL);
                $update_stmt->bindParam(':zip_code', $zip_code, PDO::PARAM_NULL);
                $update_stmt->bindParam(':contact_number', $contact_number_clean, PDO::PARAM_NULL);
                $update_stmt->bindParam(':emergency_contact_name', $emergency_contact_name, PDO::PARAM_NULL);
                $update_stmt->bindParam(':emergency_contact_number', $emergency_contact_number_clean, PDO::PARAM_NULL);
                $update_stmt->bindParam(':emergency_contact_relationship', $emergency_contact_relationship, PDO::PARAM_NULL);
                $update_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                
                if ($update_stmt->execute()) {
                    // Update session variables
                    $_SESSION['first_name'] = $first_name;
                    $_SESSION['last_name'] = $last_name;
                    if ($email) {
                        $_SESSION['email'] = $email;
                    }
                    $_SESSION['username'] = $username;
                    
                    // Reload user data to get updated information
                    $user_query = "SELECT 
                        id, username, email, first_name, last_name, middle_name, suffix,
                        birth_date, age, gender, civil_status, nationality, birth_place,
                        occupation, occupation_other, government_id_type, government_id_number,
                        house_number, barangay, city_municipality, province, region, zip_code,
                        contact_number, emergency_contact_name, emergency_contact_number,
                        emergency_contact_relationship, profile_picture, status, created_at
                        FROM users WHERE id = :user_id";
                    $user_stmt = $db->prepare($user_query);
                    $user_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                    $user_stmt->execute();
                    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Set defaults for NULL values
                    $user['profile_picture'] = $user['profile_picture'] ?? null;
                    $user['middle_name'] = $user['middle_name'] ?? '';
                    $user['suffix'] = $user['suffix'] ?? '';
                    $user['birth_date'] = $user['birth_date'] ?? '';
                    $user['age'] = $user['age'] ?? '';
                    $user['gender'] = $user['gender'] ?? '';
                    $user['civil_status'] = $user['civil_status'] ?? '';
                    $user['nationality'] = $user['nationality'] ?? 'Filipino';
                    $user['birth_place'] = $user['birth_place'] ?? '';
                    $user['occupation'] = $user['occupation'] ?? '';
                    $user['occupation_other'] = $user['occupation_other'] ?? '';
                    $user['government_id_type'] = $user['government_id_type'] ?? '';
                    $user['government_id_number'] = $user['government_id_number'] ?? '';
                    $user['house_number'] = $user['house_number'] ?? '';
                    $user['barangay'] = $user['barangay'] ?? '';
                    $user['city_municipality'] = $user['city_municipality'] ?? '';
                    $user['province'] = $user['province'] ?? '';
                    $user['region'] = $user['region'] ?? '';
                    $user['zip_code'] = $user['zip_code'] ?? '';
                    $user['contact_number'] = $user['contact_number'] ?? '';
                    $user['emergency_contact_name'] = $user['emergency_contact_name'] ?? '';
                    $user['emergency_contact_number'] = $user['emergency_contact_number'] ?? '';
                    $user['emergency_contact_relationship'] = $user['emergency_contact_relationship'] ?? '';
                    
                    if (!empty($user['profile_picture'])) {
                        $_SESSION['profile_picture'] = $user['profile_picture'];
                    }
                    
                    $_SESSION['success'] = "Profile updated successfully!";
                    header("Location: profile.php");
                    exit();
                } else {
                    $_SESSION['error'] = "Failed to update profile.";
                }
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating profile: " . $e->getMessage();
            error_log("Profile update error: " . $e->getMessage());
        }
    }
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_profile_picture'])) {
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_type = $file['type'];
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error'] = "Invalid file type. Please upload a JPEG, PNG, or GIF image.";
        } elseif ($file_size > 2097152) { // 2MB limit
            $_SESSION['error'] = "File size too large. Maximum size is 2MB.";
        } else {
            // Generate unique filename
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            // Delete old profile picture if exists
            if (!empty($user['profile_picture'])) {
                $old_file = $upload_dir . basename($user['profile_picture']);
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }
            
            // Move uploaded file
            if (move_uploaded_file($file_tmp, $upload_path)) {
                ensureUsersProfilePictureColumn($db);
                
                $relative_path = 'assets/uploads/profile_pictures/' . $new_filename;
                $update_query = "UPDATE users SET profile_picture = :profile_picture WHERE id = :user_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':profile_picture', $relative_path);
                $update_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                
                if ($update_stmt->execute()) {
                    // Update session with profile picture
                    $_SESSION['profile_picture'] = $relative_path;
                    
                    // Reload user data with all fields
                    $user_query = "SELECT 
                        id, username, email, first_name, last_name, middle_name, suffix,
                        birth_date, age, gender, civil_status, nationality, birth_place,
                        occupation, occupation_other, government_id_type, government_id_number,
                        house_number, barangay, city_municipality, province, region, zip_code,
                        contact_number, emergency_contact_name, emergency_contact_number,
                        emergency_contact_relationship, profile_picture, status, created_at
                        FROM users WHERE id = :user_id";
                    $user_stmt = $db->prepare($user_query);
                    $user_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                    $user_stmt->execute();
                    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Set defaults for NULL values
                    $user['profile_picture'] = $user['profile_picture'] ?? null;
                    $user['middle_name'] = $user['middle_name'] ?? '';
                    $user['suffix'] = $user['suffix'] ?? '';
                    $user['birth_date'] = $user['birth_date'] ?? '';
                    $user['age'] = $user['age'] ?? '';
                    $user['gender'] = $user['gender'] ?? '';
                    $user['civil_status'] = $user['civil_status'] ?? '';
                    $user['nationality'] = $user['nationality'] ?? 'Filipino';
                    $user['birth_place'] = $user['birth_place'] ?? '';
                    $user['occupation'] = $user['occupation'] ?? '';
                    $user['occupation_other'] = $user['occupation_other'] ?? '';
                    $user['government_id_type'] = $user['government_id_type'] ?? '';
                    $user['government_id_number'] = $user['government_id_number'] ?? '';
                    $user['house_number'] = $user['house_number'] ?? '';
                    $user['barangay'] = $user['barangay'] ?? '';
                    $user['city_municipality'] = $user['city_municipality'] ?? '';
                    $user['province'] = $user['province'] ?? '';
                    $user['region'] = $user['region'] ?? '';
                    $user['zip_code'] = $user['zip_code'] ?? '';
                    $user['contact_number'] = $user['contact_number'] ?? '';
                    $user['emergency_contact_name'] = $user['emergency_contact_name'] ?? '';
                    $user['emergency_contact_number'] = $user['emergency_contact_number'] ?? '';
                    $user['emergency_contact_relationship'] = $user['emergency_contact_relationship'] ?? '';
                    
                    $_SESSION['success'] = "Profile picture updated successfully!";
                    header("Location: profile.php");
                    exit();
                } else {
                    $_SESSION['error'] = "Failed to update profile picture in database.";
                }
            } else {
                $_SESSION['error'] = "Failed to upload profile picture.";
            }
        }
    } else {
        $_SESSION['error'] = "Please select a valid image file.";
    }
}

// Handle profile picture deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_profile_picture'])) {
    $remove_result = removeUserProfilePicture($db, $_SESSION['user_id'], $upload_dir, $user['profile_picture'] ?? null);
    
    if ($remove_result['success']) {
        // Reload user data to reflect changes with all fields
        $user_query = "SELECT 
            id, username, email, first_name, last_name, middle_name, suffix,
            birth_date, age, gender, civil_status, nationality, birth_place,
            occupation, occupation_other, government_id_type, government_id_number,
            house_number, barangay, city_municipality, province, region, zip_code,
            contact_number, emergency_contact_name, emergency_contact_number,
            emergency_contact_relationship, profile_picture, status, created_at
            FROM users WHERE id = :user_id";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $user_stmt->execute();
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Set defaults for NULL values
        $user['profile_picture'] = $user['profile_picture'] ?? null;
        $user['middle_name'] = $user['middle_name'] ?? '';
        $user['suffix'] = $user['suffix'] ?? '';
        $user['birth_date'] = $user['birth_date'] ?? '';
        $user['age'] = $user['age'] ?? '';
        $user['gender'] = $user['gender'] ?? '';
        $user['civil_status'] = $user['civil_status'] ?? '';
        $user['nationality'] = $user['nationality'] ?? 'Filipino';
        $user['birth_place'] = $user['birth_place'] ?? '';
        $user['occupation'] = $user['occupation'] ?? '';
        $user['occupation_other'] = $user['occupation_other'] ?? '';
        $user['government_id_type'] = $user['government_id_type'] ?? '';
        $user['government_id_number'] = $user['government_id_number'] ?? '';
        $user['house_number'] = $user['house_number'] ?? '';
        $user['barangay'] = $user['barangay'] ?? '';
        $user['city_municipality'] = $user['city_municipality'] ?? '';
        $user['province'] = $user['province'] ?? '';
        $user['region'] = $user['region'] ?? '';
        $user['zip_code'] = $user['zip_code'] ?? '';
        $user['contact_number'] = $user['contact_number'] ?? '';
        $user['emergency_contact_name'] = $user['emergency_contact_name'] ?? '';
        $user['emergency_contact_number'] = $user['emergency_contact_number'] ?? '';
        $user['emergency_contact_relationship'] = $user['emergency_contact_relationship'] ?? '';
        
        $_SESSION['success'] = $remove_result['message'];
        header("Location: profile.php");
        exit();
    } else {
        $_SESSION['error'] = $remove_result['message'];
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['error'] = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['error'] = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters long.";
    } else {
        try {
            // Verify current password
            $verify_query = "SELECT password FROM users WHERE id = :user_id";
            $verify_stmt = $db->prepare($verify_query);
            $verify_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $verify_stmt->execute();
            $user_data = $verify_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_data && password_verify($current_password, $user_data['password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password = :password WHERE id = :user_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':password', $hashed_password);
                $update_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                
                if ($update_stmt->execute()) {
                    $_SESSION['success'] = "Password changed successfully!";
                    header("Location: profile.php");
                    exit();
                } else {
                    $_SESSION['error'] = "Failed to change password.";
                }
            } else {
                $_SESSION['error'] = "Current password is incorrect.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error changing password: " . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">My Profile</h1>
            <p class="text-gray-600 dark:text-gray-400">Manage your account information and settings</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <!-- Profile Information -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Personal Information -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Personal Information</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update your personal details</p>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <!-- Name Fields -->
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">First Name <span class="text-red-500">*</span></label>
                            <input type="text" name="first_name" id="first_name" required
                                value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Name <span class="text-red-500">*</span></label>
                            <input type="text" name="last_name" id="last_name" required
                                value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label for="middle_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Middle Name</label>
                            <input type="text" name="middle_name" id="middle_name"
                                value="<?php echo htmlspecialchars($user['middle_name'] ?? ''); ?>"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="suffix" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Suffix</label>
                            <select name="suffix" id="suffix"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                <option value="">None</option>
                                <option value="Jr." <?php echo ($user['suffix'] ?? '') === 'Jr.' ? 'selected' : ''; ?>>Jr.</option>
                                <option value="Sr." <?php echo ($user['suffix'] ?? '') === 'Sr.' ? 'selected' : ''; ?>>Sr.</option>
                                <option value="II" <?php echo ($user['suffix'] ?? '') === 'II' ? 'selected' : ''; ?>>II</option>
                                <option value="III" <?php echo ($user['suffix'] ?? '') === 'III' ? 'selected' : ''; ?>>III</option>
                                <option value="IV" <?php echo ($user['suffix'] ?? '') === 'IV' ? 'selected' : ''; ?>>IV</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Account Credentials -->
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address <span class="text-red-500">*</span></label>
                            <input type="email" name="email" id="email" required
                                value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Username <span class="text-red-500">*</span></label>
                            <input type="text" name="username" id="username" required
                                value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                    
                    <!-- Birth Information -->
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                        <div>
                            <label for="birth_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Birth Date</label>
                            <input type="date" name="birth_date" id="birth_date"
                                value="<?php echo htmlspecialchars($user['birth_date'] ?? ''); ?>"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="age" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Age</label>
                            <input type="text" name="age" id="age" readonly
                                value="<?php echo htmlspecialchars($user['age'] ?? ''); ?>"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-gray-100 dark:bg-gray-600 focus:outline-none dark:text-white">
                        </div>
                        
                        <div>
                            <label for="gender" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gender</label>
                            <select name="gender" id="gender"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                <option value="">Select gender</option>
                                <option value="Female" <?php echo ($user['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Male" <?php echo ($user['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Rather Not Say" <?php echo ($user['gender'] ?? '') === 'Rather Not Say' ? 'selected' : ''; ?>>Rather Not Say</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label for="civil_status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Civil Status</label>
                            <select name="civil_status" id="civil_status"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                <option value="">Select status</option>
                                <option value="Single" <?php echo ($user['civil_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
                                <option value="Married" <?php echo ($user['civil_status'] ?? '') === 'Married' ? 'selected' : ''; ?>>Married</option>
                                <option value="Widowed" <?php echo ($user['civil_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                <option value="Separated" <?php echo ($user['civil_status'] ?? '') === 'Separated' ? 'selected' : ''; ?>>Separated</option>
                                <option value="Divorced" <?php echo ($user['civil_status'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="nationality" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nationality</label>
                            <input type="text" name="nationality" id="nationality"
                                value="<?php echo htmlspecialchars($user['nationality'] ?? 'Filipino'); ?>"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                    
                    <div>
                        <label for="birth_place" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Birth Place</label>
                        <input type="text" name="birth_place" id="birth_place"
                            value="<?php echo htmlspecialchars($user['birth_place'] ?? ''); ?>"
                            placeholder="City or municipality of birth"
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <!-- Occupation -->
                    <div>
                        <label for="occupation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Occupation</label>
                        <select name="occupation" id="occupation"
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select occupation</option>
                            <option value="Student" <?php echo ($user['occupation'] ?? '') === 'Student' ? 'selected' : ''; ?>>Student</option>
                            <option value="Unemployed" <?php echo ($user['occupation'] ?? '') === 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                            <option value="Self-Employed" <?php echo ($user['occupation'] ?? '') === 'Self-Employed' ? 'selected' : ''; ?>>Self-Employed</option>
                            <option value="Freelancer" <?php echo ($user['occupation'] ?? '') === 'Freelancer' ? 'selected' : ''; ?>>Freelancer</option>
                            <option value="Business Owner" <?php echo ($user['occupation'] ?? '') === 'Business Owner' ? 'selected' : ''; ?>>Business Owner</option>
                            <option value="Homemaker" <?php echo ($user['occupation'] ?? '') === 'Homemaker' ? 'selected' : ''; ?>>Homemaker</option>
                            <option value="Retired" <?php echo ($user['occupation'] ?? '') === 'Retired' ? 'selected' : ''; ?>>Retired</option>
                            <option value="Other" <?php echo ($user['occupation'] === null && !empty($user['occupation_other'])) ? 'selected' : ''; ?>>Other</option>
                        </select>
                        <div id="occupation_other_container" class="mt-2 <?php echo ($user['occupation'] === null && !empty($user['occupation_other'])) ? '' : 'hidden'; ?>">
                            <input type="text" name="occupation_other" id="occupation_other"
                                value="<?php echo htmlspecialchars($user['occupation_other'] ?? ''); ?>"
                                placeholder="Please specify"
                                class="block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                    
                    <!-- Government ID -->
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label for="government_id_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Government ID Type</label>
                            <select name="government_id_type" id="government_id_type"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                <option value="">Select ID type</option>
                                <option value="PhilHealth ID" <?php echo ($user['government_id_type'] ?? '') === 'PhilHealth ID' ? 'selected' : ''; ?>>PhilHealth ID</option>
                                <option value="SSS ID" <?php echo ($user['government_id_type'] ?? '') === 'SSS ID' ? 'selected' : ''; ?>>SSS ID</option>
                                <option value="GSIS ID" <?php echo ($user['government_id_type'] ?? '') === 'GSIS ID' ? 'selected' : ''; ?>>GSIS ID</option>
                                <option value="TIN" <?php echo ($user['government_id_type'] ?? '') === 'TIN' ? 'selected' : ''; ?>>TIN</option>
                                <option value="Passport ID" <?php echo ($user['government_id_type'] ?? '') === 'Passport ID' ? 'selected' : ''; ?>>Passport ID</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="government_id_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Government ID Number</label>
                            <input type="text" name="government_id_number" id="government_id_number"
                                value="<?php echo htmlspecialchars($user['government_id_number'] ?? ''); ?>"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                    
                    <!-- Address Information -->
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                        <h4 class="text-md font-medium text-gray-900 dark:text-white mb-4">Address Information</h4>
                        
                        <div class="mb-4">
                            <label for="house_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">House No. & Street</label>
                            <input type="text" name="house_number" id="house_number"
                                value="<?php echo htmlspecialchars($user['house_number'] ?? ''); ?>"
                                placeholder="e.g., 123 Mabini St."
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <label for="region" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Region</label>
                                <input type="text" name="region" id="region"
                                    value="<?php echo htmlspecialchars($user['region'] ?? ''); ?>"
                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label for="province" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Province</label>
                                <input type="text" name="province" id="province"
                                    value="<?php echo htmlspecialchars($user['province'] ?? ''); ?>"
                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 mt-4">
                            <div>
                                <label for="city_municipality" class="block text-sm font-medium text-gray-700 dark:text-gray-300">City / Municipality</label>
                                <input type="text" name="city_municipality" id="city_municipality"
                                    value="<?php echo htmlspecialchars($user['city_municipality'] ?? ''); ?>"
                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label for="barangay" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Barangay</label>
                                <input type="text" name="barangay" id="barangay"
                                    value="<?php echo htmlspecialchars($user['barangay'] ?? ''); ?>"
                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label for="zip_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">ZIP Code</label>
                            <input type="text" name="zip_code" id="zip_code" maxlength="4" pattern="\d{4}"
                                value="<?php echo htmlspecialchars($user['zip_code'] ?? ''); ?>"
                                placeholder="e.g., 1000"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                        <h4 class="text-md font-medium text-gray-900 dark:text-white mb-4">Contact Information</h4>
                        
                        <div>
                            <label for="contact_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contact Number</label>
                            <input type="tel" name="contact_number" id="contact_number" maxlength="13"
                                value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>"
                                placeholder="09XX XXX XXXX"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Format: 09XX XXX XXXX</p>
                        </div>
                    </div>
                    
                    <!-- Emergency Contact -->
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                        <h4 class="text-md font-medium text-gray-900 dark:text-white mb-4">Emergency Contact</h4>
                        
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <label for="emergency_contact_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Emergency Contact Name</label>
                                <input type="text" name="emergency_contact_name" id="emergency_contact_name"
                                    value="<?php echo htmlspecialchars($user['emergency_contact_name'] ?? ''); ?>"
                                    placeholder="Full name of emergency contact"
                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label for="emergency_contact_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Emergency Contact Number</label>
                                <input type="tel" name="emergency_contact_number" id="emergency_contact_number" maxlength="13"
                                    value="<?php echo htmlspecialchars($user['emergency_contact_number'] ?? ''); ?>"
                                    placeholder="09XX XXX XXXX"
                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label for="emergency_contact_relationship" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Relationship</label>
                            <select name="emergency_contact_relationship" id="emergency_contact_relationship"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                <option value="">Select relationship</option>
                                <option value="Mother" <?php echo ($user['emergency_contact_relationship'] ?? '') === 'Mother' ? 'selected' : ''; ?>>Mother</option>
                                <option value="Father" <?php echo ($user['emergency_contact_relationship'] ?? '') === 'Father' ? 'selected' : ''; ?>>Father</option>
                                <option value="Brother" <?php echo ($user['emergency_contact_relationship'] ?? '') === 'Brother' ? 'selected' : ''; ?>>Brother</option>
                                <option value="Sister" <?php echo ($user['emergency_contact_relationship'] ?? '') === 'Sister' ? 'selected' : ''; ?>>Sister</option>
                                <option value="Guardian" <?php echo ($user['emergency_contact_relationship'] ?? '') === 'Guardian' ? 'selected' : ''; ?>>Guardian</option>
                                <option value="Child" <?php echo ($user['emergency_contact_relationship'] ?? '') === 'Child' ? 'selected' : ''; ?>>Child</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Change Password -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Change Password</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update your password to keep your account secure</p>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Current Password</label>
                        <input type="password" name="current_password" id="current_password" required
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">New Password</label>
                        <input type="password" name="new_password" id="new_password" required minlength="6"
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Must be at least 6 characters long</p>
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" required minlength="6"
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Profile Summary -->
    <div class="space-y-6">
        <!-- Profile Picture -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Profile Picture</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Upload or change your profile picture</p>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <div class="flex flex-col items-center space-y-4">
                    <!-- Profile Picture Display -->
                    <div class="relative">
                        <?php if (!empty($user['profile_picture']) && file_exists(__DIR__ . '/../../' . $user['profile_picture'])): ?>
                            <img src="<?php echo BASE_URL . '/' . htmlspecialchars($user['profile_picture']); ?>" 
                                 alt="Profile Picture" 
                                 id="profilePicturePreview"
                                 class="w-32 h-32 rounded-lg object-cover border-2 border-gray-200 dark:border-gray-700">
                        <?php else: ?>
                            <?php 
                            $first_initial = strtoupper(substr($user['first_name'] ?? '', 0, 1));
                            $last_initial = strtoupper(substr($user['last_name'] ?? '', 0, 1));
                            $user_initials = $first_initial . $last_initial;
                            ?>
                            <div id="profilePicturePreview" class="w-32 h-32 bg-purple-500 rounded-lg flex items-center justify-center text-white font-semibold text-4xl border-2 border-gray-200 dark:border-gray-700">
                                <?php echo $user_initials; ?>
                            </div>
                        <?php endif; ?>
                        <div id="imagePreviewContainer" class="hidden mt-2">
                            <img id="imagePreview" src="" alt="Preview" class="w-32 h-32 rounded-lg object-cover border-2 border-primary-500">
                        </div>
                    </div>
                    
                    <!-- Upload Form -->
                    <form method="POST" enctype="multipart/form-data" class="w-full">
                        <input type="hidden" name="upload_profile_picture" value="1">
                        <div class="space-y-3">
                            <label for="profile_picture" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Choose Image
                            </label>
                            <input type="file" 
                                   name="profile_picture" 
                                   id="profile_picture" 
                                   accept="image/jpeg,image/jpg,image/png,image/gif"
                                   class="block w-full text-sm text-gray-500 dark:text-gray-400
                                          file:mr-4 file:py-2 file:px-4
                                          file:rounded-md file:border-0
                                          file:text-sm file:font-semibold
                                          file:bg-primary-50 file:text-primary-700
                                          hover:file:bg-primary-100
                                          dark:file:bg-primary-900 dark:file:text-primary-300
                                          dark:hover:file:bg-primary-800
                                          cursor-pointer">
                            <p class="text-xs text-gray-500 dark:text-gray-400">JPEG, PNG or GIF. Max size: 2MB</p>
                            
                            <div class="flex space-x-2">
                                <button type="submit" 
                                        class="flex-1 px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 text-sm">
                                    Upload Picture
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <?php if (!empty($user['profile_picture'])): ?>
                    <form method="POST" class="w-full delete-profile-picture-form" onsubmit="event.preventDefault(); showConfirmAlert('Remove Profile Picture', 'Are you sure you want to remove your profile picture?').then(confirmed => { if(confirmed) this.submit(); }); return false;">
                        <input type="hidden" name="delete_profile_picture" value="1">
                        <button type="submit" 
                                class="w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 text-sm">
                            Remove Picture
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Account Summary -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Account Summary</h3>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <div class="flex flex-col items-center">
                    <?php 
                    $first_initial = strtoupper(substr($user['first_name'] ?? '', 0, 1));
                    $last_initial = strtoupper(substr($user['last_name'] ?? '', 0, 1));
                    $user_initials = $first_initial . $last_initial;
                    ?>
                    <?php if (!empty($user['profile_picture']) && file_exists(__DIR__ . '/../../' . $user['profile_picture'])): ?>
                        <img src="<?php echo BASE_URL . '/' . htmlspecialchars($user['profile_picture']); ?>" 
                             alt="Profile Picture" 
                             class="w-20 h-20 rounded-md object-cover mb-4 border-2 border-gray-200 dark:border-gray-700">
                    <?php else: ?>
                        <div class="w-20 h-20 bg-purple-500 rounded-md flex items-center justify-center text-white flex-shrink-0 font-semibold text-2xl mb-4">
                            <?php echo $user_initials; ?>
                        </div>
                    <?php endif; ?>
                    <?php
                    $display_first_name = $user['first_name'] ?? ($_SESSION['first_name'] ?? '');
                    $display_last_name = $user['last_name'] ?? ($_SESSION['last_name'] ?? '');
                    $display_email = $user['email'] ?? ($_SESSION['email'] ?? $user['username'] ?? '');
                    ?>
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white">
                        <?php echo htmlspecialchars(trim($display_first_name . ' ' . $display_last_name)); ?>
                    </h4>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        <?php echo htmlspecialchars($display_email); ?>
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">
                        <?php echo ucfirst(str_replace('_', ' ', $_SESSION['role_name'] ?? 'user')); ?>
                    </p>
                </div>
                
                <div class="mt-6 space-y-4">
                    <?php
                    $account_status = strtolower($user['status'] ?? 'active');
                    $status_is_active = $account_status === 'active';
                    ?>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Account Status</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'; ?>">
                                <?php echo ucfirst($account_status); ?>
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Member Since</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                            <?php echo formatDate($user['created_at'] ?? date('Y-m-d')); ?>
                        </dd>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
// Image preview functionality and form enhancements
document.addEventListener('DOMContentLoaded', function() {
    const profilePictureInput = document.getElementById('profile_picture');
    const imagePreview = document.getElementById('imagePreview');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    const profilePicturePreview = document.getElementById('profilePicturePreview');
    
    // Age calculation from birth date
    const birthDateInput = document.getElementById('birth_date');
    const ageInput = document.getElementById('age');
    
    function calculateAge(dateValue) {
        if (!dateValue) return '';
        const birthDate = new Date(dateValue);
        if (Number.isNaN(birthDate.getTime())) return '';
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        return age >= 0 ? age : '';
    }
    
    if (birthDateInput && ageInput) {
        birthDateInput.addEventListener('change', function() {
            const age = calculateAge(this.value);
            ageInput.value = age !== '' ? age : '';
        });
    }
    
    // Occupation "Other" option handler
    const occupationSelect = document.getElementById('occupation');
    const occupationOtherContainer = document.getElementById('occupation_other_container');
    const occupationOtherInput = document.getElementById('occupation_other');
    
    if (occupationSelect && occupationOtherContainer && occupationOtherInput) {
        occupationSelect.addEventListener('change', function() {
            if (this.value === 'Other') {
                occupationOtherContainer.classList.remove('hidden');
                occupationOtherInput.setAttribute('required', 'required');
            } else {
                occupationOtherContainer.classList.add('hidden');
                occupationOtherInput.removeAttribute('required');
                occupationOtherInput.value = '';
            }
        });
    }
    
    // Phone number formatting
    function formatPhilippineNumberField(field) {
        if (!field) return;
        let digits = field.value.replace(/\D/g, '');
        if (digits.length === 0) {
            field.value = '';
            return;
        }
        if (!digits.startsWith('09')) {
            digits = '09' + digits.replace(/^0+/, '');
        }
        digits = digits.slice(0, 11);
        let formatted = digits;
        if (digits.length > 7) {
            formatted = `${digits.slice(0, 4)} ${digits.slice(4, 7)} ${digits.slice(7)}`;
        } else if (digits.length > 4) {
            formatted = `${digits.slice(0, 4)} ${digits.slice(4)}`;
        }
        field.value = formatted.trim();
    }
    
    const contactNumberField = document.getElementById('contact_number');
    const emergencyNumberField = document.getElementById('emergency_contact_number');
    
    [contactNumberField, emergencyNumberField].forEach(field => {
        if (field) {
            field.addEventListener('input', () => formatPhilippineNumberField(field));
            field.addEventListener('blur', () => formatPhilippineNumberField(field));
        }
    });
    
    // Image preview functionality
    if (profilePictureInput) {
        profilePictureInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    showErrorAlert('Invalid File Type', 'Please upload a JPEG, PNG, or GIF image.');
                    e.target.value = '';
                    return;
                }
                
                // Validate file size (2MB)
                if (file.size > 2097152) {
                    showErrorAlert('File Too Large', 'File size too large. Maximum size is 2MB.');
                    e.target.value = '';
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreviewContainer.classList.remove('hidden');
                    if (profilePicturePreview.tagName === 'IMG') {
                        profilePicturePreview.style.display = 'none';
                    } else {
                        profilePicturePreview.style.display = 'none';
                    }
                };
                reader.readAsDataURL(file);
            } else {
                imagePreviewContainer.classList.add('hidden');
                if (profilePicturePreview.tagName === 'IMG') {
                    profilePicturePreview.style.display = 'block';
                } else {
                    profilePicturePreview.style.display = 'flex';
                }
            }
        });
    }
});
</script>
