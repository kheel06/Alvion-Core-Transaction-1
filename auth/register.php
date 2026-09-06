<?php
require_once '../config/config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Helper function to ensure profile_picture column exists
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

// Helper function to ensure patient_id column exists
if (!function_exists('ensureUsersPatientIdColumn')) {
    function ensureUsersPatientIdColumn(PDO $db): void {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'patient_id'");
            if ($stmt && $stmt->rowCount() === 0) {
                $db->exec("ALTER TABLE users ADD COLUMN patient_id VARCHAR(20) NULL AFTER id");
                // Add unique index for patient_id
                try {
                    $db->exec("ALTER TABLE users ADD UNIQUE KEY `patient_id` (`patient_id`)");
                } catch (PDOException $e) {
                    // Index might already exist, ignore
                    error_log("Note: patient_id index may already exist: " . $e->getMessage());
                }
            }
        } catch (PDOException $e) {
            error_log("Failed to ensure users.patient_id column: " . $e->getMessage());
        }
    }
}

// Function to generate patient ID in format: PAT-YYYYMMDD-NNNN
if (!function_exists('generatePatientId')) {
    function generatePatientId(PDO $db): string {
        $prefix = 'PAT';
        $date = date('Ymd'); // Format: YYYYMMDD
        $datePart = $date;
        
        // Find the highest auto-increment number for today
        $query = "SELECT patient_id FROM users 
                  WHERE patient_id LIKE :pattern 
                  ORDER BY patient_id DESC 
                  LIMIT 1";
        $pattern = $prefix . '-' . $datePart . '-%';
        $stmt = $db->prepare($query);
        $stmt->bindParam(':pattern', $pattern);
        $stmt->execute();
        
        $lastId = $stmt->fetchColumn();
        $nextNumber = 1;
        
        if ($lastId) {
            // Extract the number part (last 4 digits after the last dash)
            $parts = explode('-', $lastId);
            if (count($parts) === 3 && isset($parts[2])) {
                $lastNumber = (int)$parts[2];
                $nextNumber = $lastNumber + 1;
            }
        }
        
        // Format with leading zeros (4 digits)
        $autoIncrement = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        
        return $prefix . '-' . $datePart . '-' . $autoIncrement;
    }
}


// Profile picture upload directory
$upload_dir = __DIR__ . '/../assets/uploads/profile_pictures/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Account credentials
    $username = sanitizeInput($_POST['username']);
    $password_input = sanitizeInput($_POST['password']);
    $confirm_password = sanitizeInput($_POST['confirm_password']);
    $email = sanitizeInput($_POST['email']);
    $accept_terms = isset($_POST['accept_terms']) ? true : false;

    // Personal Information
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $middle_name = isset($_POST['middle_name']) ? sanitizeInput($_POST['middle_name']) : null;
    $suffix = isset($_POST['suffix']) && !empty($_POST['suffix']) ? sanitizeInput($_POST['suffix']) : null;
    $birth_date = isset($_POST['birth_date']) ? sanitizeInput($_POST['birth_date']) : null;
    $gender = isset($_POST['gender']) ? sanitizeInput($_POST['gender']) : null;
    $civil_status = isset($_POST['civil_status']) ? sanitizeInput($_POST['civil_status']) : null;
    $nationality = isset($_POST['nationality']) ? sanitizeInput($_POST['nationality']) : 'Filipino';
    $birth_place = isset($_POST['birth_place']) ? sanitizeInput($_POST['birth_place']) : null;
    
    // Occupation
    $occupation = isset($_POST['occupation']) ? sanitizeInput($_POST['occupation']) : null;
    if ($occupation === 'Other' && isset($_POST['occupation_other']) && !empty($_POST['occupation_other'])) {
        $occupation = sanitizeInput($_POST['occupation_other']);
    }
    
    // Government ID
    $government_id_type = isset($_POST['government_id_type']) ? sanitizeInput($_POST['government_id_type']) : null;
    $government_id_number = isset($_POST['government_id_number']) ? sanitizeInput($_POST['government_id_number']) : null;
    
    // Address
    $house_number = isset($_POST['house_number']) ? sanitizeInput($_POST['house_number']) : null;
    $region = isset($_POST['region']) ? sanitizeInput($_POST['region']) : null;
    $province = isset($_POST['province']) ? sanitizeInput($_POST['province']) : null;
    $city_municipality = isset($_POST['city_municipality']) ? sanitizeInput($_POST['city_municipality']) : null;
    $barangay = isset($_POST['barangay']) ? sanitizeInput($_POST['barangay']) : null;
    $zip_code = isset($_POST['zip_code']) ? sanitizeInput($_POST['zip_code']) : null;
    
    // Contact
    $contact_number = isset($_POST['contact_number']) ? sanitizeInput($_POST['contact_number']) : null;
    $emergency_contact_name = isset($_POST['emergency_contact_name']) ? sanitizeInput($_POST['emergency_contact_name']) : null;
    $emergency_contact_number = isset($_POST['emergency_contact_number']) ? sanitizeInput($_POST['emergency_contact_number']) : null;
    $emergency_contact_relationship = isset($_POST['emergency_contact_relationship']) ? sanitizeInput($_POST['emergency_contact_relationship']) : null;

    // Validate terms acceptance
    if (!$accept_terms) {
        $_SESSION['error'] = "You must accept the Terms and Conditions and Privacy Policy to create an account.";
    } elseif ($password_input !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match!";
    } else {
        $password = password_hash($password_input, PASSWORD_DEFAULT);

        try {
            // Check if username or email already exists
            $check_query = "SELECT * FROM users WHERE username = :username OR email = :email";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();

            if ($check_stmt->rowCount() > 0) {
                $_SESSION['error'] = "Username or email already exists.";
            } else {
                // Handle profile picture upload
                $profile_picture_path = null;
                
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
                        // Continue without profile picture if validation fails
                    } elseif ($file_size > 2097152) { // 2MB
                        $_SESSION['error'] = "File size too large. Maximum size is 2MB.";
                        // Continue without profile picture if validation fails
                    } else {
                        // Generate unique filename (will use user_id after insert)
                        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                        $temp_filename = 'profile_temp_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $temp_upload_path = $upload_dir . $temp_filename;
                        
                        if (move_uploaded_file($file_tmp, $temp_upload_path)) {
                            $profile_picture_path = $temp_filename; // Store temp filename
                        }
                    }
                }
                
                // Insert user
                ensureUsersProfilePictureColumn($db);
                ensureUsersPatientIdColumn($db);
                
                // Get patient role_id (id = 4 for patient role)
                $patient_role_id = 4;
                try {
                    $role_query = "SELECT id FROM roles WHERE role_name = 'patient' LIMIT 1";
                    $role_stmt = $db->prepare($role_query);
                    $role_stmt->execute();
                    $role_data = $role_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($role_data && isset($role_data['id'])) {
                        $patient_role_id = (int)$role_data['id'];
                    }
                } catch (PDOException $e) {
                    error_log("Failed to fetch patient role_id: " . $e->getMessage());
                    // Default to 4 if lookup fails
                }
                
                // Start transaction
                $db->beginTransaction();
                
                try {
                    // Generate patient ID
                    $patient_id = generatePatientId($db);
                    
                    // Calculate age from birth_date
                    $age = null;
                    if ($birth_date) {
                        $birth = new DateTime($birth_date);
                        $today = new DateTime();
                        $age = $today->diff($birth)->y;
                    }
                    
                    // Clean phone numbers (remove spaces)
                    $contact_number_clean = $contact_number ? str_replace(' ', '', $contact_number) : null;
                    $emergency_contact_number_clean = $emergency_contact_number ? str_replace(' ', '', $emergency_contact_number) : null;
                    
                    // Handle occupation_other
                    $occupation_other_value = null;
                    if ($occupation === 'Other' && isset($_POST['occupation_other']) && !empty($_POST['occupation_other'])) {
                        $occupation_other_value = sanitizeInput($_POST['occupation_other']);
                        $occupation = null; // Set occupation to null if "Other" is selected
                    }
                    
                    // Insert user with all columns
                    $query = "INSERT INTO users (
                        patient_id, username, password, email, first_name, last_name, middle_name, suffix,
                        birth_date, age, gender, civil_status, nationality, birth_place,
                        occupation, occupation_other, government_id_type, government_id_number,
                        house_number, barangay, city_municipality, province, region, zip_code,
                        contact_number, emergency_contact_name, emergency_contact_number,
                        emergency_contact_relationship, profile_picture, role_id, status, is_active
                    ) VALUES (
                        :patient_id, :username, :password, :email, :first_name, :last_name, :middle_name, :suffix,
                        :birth_date, :age, :gender, :civil_status, :nationality, :birth_place,
                        :occupation, :occupation_other, :government_id_type, :government_id_number,
                        :house_number, :barangay, :city_municipality, :province, :region, :zip_code,
                        :contact_number, :emergency_contact_name, :emergency_contact_number,
                        :emergency_contact_relationship, :profile_picture, :role_id, :status, :is_active
                    )";
                    
                    $status = 'active';
                    $is_active = 1;
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':patient_id', $patient_id);
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':password', $password);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':first_name', $first_name);
                    $stmt->bindParam(':last_name', $last_name);
                    $stmt->bindParam(':middle_name', $middle_name);
                    $stmt->bindParam(':suffix', $suffix);
                    $stmt->bindParam(':birth_date', $birth_date);
                    $stmt->bindParam(':age', $age, PDO::PARAM_INT);
                    $stmt->bindParam(':gender', $gender);
                    $stmt->bindParam(':civil_status', $civil_status);
                    $stmt->bindParam(':nationality', $nationality);
                    $stmt->bindParam(':birth_place', $birth_place);
                    $stmt->bindParam(':occupation', $occupation);
                    $stmt->bindParam(':occupation_other', $occupation_other_value);
                    $stmt->bindParam(':government_id_type', $government_id_type);
                    $stmt->bindParam(':government_id_number', $government_id_number);
                    $stmt->bindParam(':house_number', $house_number);
                    $stmt->bindParam(':barangay', $barangay);
                    $stmt->bindParam(':city_municipality', $city_municipality);
                    $stmt->bindParam(':province', $province);
                    $stmt->bindParam(':region', $region);
                    $stmt->bindParam(':zip_code', $zip_code);
                    $stmt->bindParam(':contact_number', $contact_number_clean);
                    $stmt->bindParam(':emergency_contact_name', $emergency_contact_name);
                    $stmt->bindParam(':emergency_contact_number', $emergency_contact_number_clean);
                    $stmt->bindParam(':emergency_contact_relationship', $emergency_contact_relationship);
                    $stmt->bindValue(':profile_picture', null, PDO::PARAM_NULL);
                    $stmt->bindParam(':role_id', $patient_role_id, PDO::PARAM_INT);
                    $stmt->bindParam(':status', $status);
                    $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
                    
                    if (!$stmt->execute()) {
                        $errorInfo = $stmt->errorInfo();
                        throw new Exception("Failed to create user account: " . ($errorInfo[2] ?? "Unknown error"));
                    }
                    
                    $user_id = $db->lastInsertId();
                    
                    // Rename profile picture file with user_id if uploaded
                    $profile_picture_relative = null;
                    if ($profile_picture_path) {
                        $file_extension = pathinfo($profile_picture_path, PATHINFO_EXTENSION);
                        $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
                        $new_upload_path = $upload_dir . $new_filename;
                        $old_path = $upload_dir . $profile_picture_path;
                        
                        if (file_exists($old_path) && rename($old_path, $new_upload_path)) {
                            $profile_picture_relative = 'assets/uploads/profile_pictures/' . $new_filename;
                            $update_pic_query = "UPDATE users SET profile_picture = :profile_picture WHERE id = :user_id";
                            $update_pic_stmt = $db->prepare($update_pic_query);
                            $update_pic_stmt->bindParam(':profile_picture', $profile_picture_relative);
                            $update_pic_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                            $update_pic_stmt->execute();
                        }
                    }
                    
                    // Commit transaction
                    $db->commit();
                    
                    $_SESSION['success'] = "🎉 Account created successfully! You can now login with your credentials.";
                    header("Location: login.php");
                    exit();
                    
                } catch (Exception $e) {
                    // Rollback transaction on error
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $_SESSION['error'] = "Registration error: " . $e->getMessage();
                    error_log("Registration error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
                }
            }
        } catch (PDOException $exception) {
            // Rollback transaction if still active
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['error'] = "Registration error: " . $exception->getMessage();
            error_log("Registration PDO error: " . $exception->getMessage() . " | Code: " . $exception->getCode());
        } catch (Exception $exception) {
            // Rollback transaction if still active
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['error'] = "Registration error: " . $exception->getMessage();
            error_log("Registration error: " . $exception->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full bg-gradient-to-br from-[#e6f4f4] to-[#f5f8fb]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
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
        .login-input::placeholder {
            font-size: 0.85rem;
            color: rgba(15, 118, 110, 0.85);
        }
        .login-input:invalid:not(:focus):not(:placeholder-shown) {
            border-color: #ef4444;
        }
        .login-input.border-red-500 {
            border-color: #ef4444 !important;
        }
        .auto-dismiss {
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        /* Ensure icons are visible in select fields */
        select.login-input {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23008080' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            padding-right: 2.5rem;
        }
        select.login-input:focus {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%230f766e' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
        }
        /* Ensure left-side icons are visible above select fields */
        .relative > span[class*="absolute"] {
            z-index: 10;
        }
        select.login-input {
            position: relative;
            z-index: 1;
        }
        .step-indicator {
            transition: all 0.3s ease;
        }
        .step-indicator.active {
            background-color: #008080;
            color: white;
        }
        .step-indicator.completed {
            background-color: #10b981;
            color: white;
        }
        .step-content {
            display: none;
        }
        .step-content.active {
            display: block;
        }
        #cameraPreview {
            display: none;
            max-width: 100%;
            max-height: 400px;
        }
        #cameraPreview.active {
            display: block;
            width: 100%;
            max-width: 500px;
            height: auto;
            max-height: 400px;
            transform: scaleX(-1);
            -webkit-transform: scaleX(-1);
            -moz-transform: scaleX(-1);
            -ms-transform: scaleX(-1);
        }
    </style>
</head>
<body class="h-full">
    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 flex flex-col gap-3 pointer-events-none"></div>
    
    <div class="min-h-full flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-3xl bg-white rounded-3xl overflow-hidden login-card">
            <div class="px-8 py-10 flex flex-col justify-center">
                    <div>
                        <a href="../landing.php" class="inline-flex items-center text-sm text-gray-500 hover:text-[#008080] mb-4 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                            Back to Home
                        </a>
                        <div class="text-center">
                            <div class="flex justify-center mb-4">
                                <img src="<?php echo BASE_URL; ?>/assets/img/alvion-logo-removebg.png" alt="Alvion logo" class="h-14 md:h-16">
                            </div>
                            <h2 class="text-3xl font-semibold text-[#008080]">Patient Registration</h2>
                            <p class="mt-3 text-sm text-gray-500">Complete the steps to create your Alvion account.</p>
                        </div>
                    </div>

                    <!-- Step Indicators -->
                    <div class="mt-6 mb-6 relative">
                        <!-- Connecting Lines (behind circles) -->
                        <div class="absolute top-5 left-0 right-0 h-1 flex z-0">
                            <div id="step1-line" class="flex-1 bg-[#008080] transition-all duration-300 -mx-5"></div>
                            <div id="step2-line" class="flex-1 bg-gray-200 transition-all duration-300 -mx-5"></div>
                            <div id="step3-line" class="flex-1 bg-gray-200 transition-all duration-300 -mx-5"></div>
                            <div id="step4-line" class="flex-1 bg-gray-200 transition-all duration-300 -mx-5"></div>
                            <div id="step5-line" class="flex-1 bg-gray-200 transition-all duration-300 -mx-5"></div>
                        </div>
                        
                        <!-- Steps with Circles and Labels -->
                        <div class="flex items-start relative z-10">
                            <!-- Step 1 -->
                            <div class="flex-1 flex flex-col items-center">
                                <div id="step1-indicator" class="step-indicator active w-10 h-10 rounded-full flex items-center justify-center text-sm font-semibold bg-[#008080] text-white border-2 border-[#008080] mb-2">1</div>
                                <p id="step1-label" class="text-xs font-medium text-center text-[#008080] whitespace-nowrap">Personal Information</p>
                            </div>
                            
                            <!-- Step 2 -->
                            <div class="flex-1 flex flex-col items-center">
                                <div id="step2-indicator" class="step-indicator w-10 h-10 rounded-full flex items-center justify-center text-sm font-semibold bg-white text-gray-600 border-2 border-gray-300 mb-2">2</div>
                                <p id="step2-label" class="text-xs font-medium text-center text-gray-500 whitespace-nowrap">Address</p>
                            </div>
                            
                            <!-- Step 3 -->
                            <div class="flex-1 flex flex-col items-center">
                                <div id="step3-indicator" class="step-indicator w-10 h-10 rounded-full flex items-center justify-center text-sm font-semibold bg-white text-gray-600 border-2 border-gray-300 mb-2">3</div>
                                <p id="step3-label" class="text-xs font-medium text-center text-gray-500 whitespace-nowrap">Contact</p>
                            </div>
                            
                            <!-- Step 4 -->
                            <div class="flex-1 flex flex-col items-center">
                                <div id="step4-indicator" class="step-indicator w-10 h-10 rounded-full flex items-center justify-center text-sm font-semibold bg-white text-gray-600 border-2 border-gray-300 mb-2">4</div>
                                <p id="step4-label" class="text-xs font-medium text-center text-gray-500 whitespace-nowrap">Create Password</p>
                            </div>
                            
                            <!-- Step 5 -->
                            <div class="flex-1 flex flex-col items-center">
                                <div id="step5-indicator" class="step-indicator w-10 h-10 rounded-full flex items-center justify-center text-sm font-semibold bg-white text-gray-600 border-2 border-gray-300 mb-2">5</div>
                                <p id="step5-label" class="text-xs font-medium text-center text-gray-500 whitespace-nowrap">Photo Setup</p>
                            </div>
                            
                            <!-- Step 6 -->
                            <div class="flex-1 flex flex-col items-center">
                                <div id="step6-indicator" class="step-indicator w-10 h-10 rounded-full flex items-center justify-center text-sm font-semibold bg-white text-gray-600 border-2 border-gray-300 mb-2">6</div>
                                <p id="step6-label" class="text-xs font-medium text-center text-gray-500 whitespace-nowrap">Terms & Privacy</p>
                            </div>
                        </div>
                    </div>


                    <form id="registrationForm" class="mt-4" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="register" value="1">

                    <!-- Step 1: Personal Information -->
                        <div id="step1" class="step-content active">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="space-y-2">
                                    <label for="first_name" class="block text-sm font-medium text-gray-600 mb-1">First Name <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                        </span>
                                        <input id="first_name" name="first_name" type="text" required 
                                               class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition"
                                           placeholder="Juan">
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <label for="last_name" class="block text-sm font-medium text-gray-600 mb-1">Last Name <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                        </span>
                                        <input id="last_name" name="last_name" type="text" required 
                                               class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition"
                                           placeholder="Dela Cruz">
                                    </div>
                                </div>

                                <div class="space-y-2">
                                <label for="middle_name" class="block text-sm font-medium text-gray-600 mb-1">Middle Name</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    </span>
                                    <input id="middle_name" name="middle_name" type="text"
                                           class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition"
                                           placeholder="Santos">
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="suffix" class="block text-sm font-medium text-gray-600 mb-1">Suffix</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
                                        </span>
                                    <select id="suffix" name="suffix"
                                            class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition">
                                        <option value="">None</option>
                                        <option value="Jr.">Jr.</option>
                                        <option value="Sr.">Sr.</option>
                                        <option value="II">II</option>
                                        <option value="III">III</option>
                                        <option value="IV">IV</option>
                                    </select>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="birth_date" class="block text-sm font-medium text-gray-600 mb-1">Birth Date <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                                    </span>
                                    <input id="birth_date" name="birth_date" type="date" required
                                           class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition">
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="age" class="block text-sm font-medium text-gray-600 mb-1">Age</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22h14"/><path d="M5 2h14"/><path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"/><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"/></svg>
                                    </span>
                                    <input id="age" name="age" type="text" readonly
                                           class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition bg-gray-100"
                                           placeholder="Automatically calculated">
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="gender" class="block text-sm font-medium text-gray-600 mb-1">Gender <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                    </span>
                                    <select id="gender" name="gender" required
                                            class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition">
                                        <option value="">Select gender</option>
                                        <option value="Female">Female</option>
                                        <option value="Male">Male</option>
                                        <option value="Rather Not Say">Rather Not Say</option>
                                    </select>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="civil_status" class="block text-sm font-medium text-gray-600 mb-1">Civil Status <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                    </span>
                                    <select id="civil_status" name="civil_status" required
                                            class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition">
                                        <option value="">Select status</option>
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Widowed">Widowed</option>
                                        <option value="Separated">Separated</option>
                                        <option value="Divorced">Divorced</option>
                                    </select>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="nationality" class="block text-sm font-medium text-gray-600 mb-1">Nationality <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" x2="4" y1="22" y2="15"/></svg>
                                    </span>
                                    <input id="nationality" name="nationality" type="text" required value="Filipino"
                                           class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition">
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="birth_place" class="block text-sm font-medium text-gray-600 mb-1">Birth Place <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                                    </span>
                                    <input id="birth_place" name="birth_place" type="text" required
                                               class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition"
                                           placeholder="City or municipality of birth">
                                    </div>
                            </div>

                            <div class="space-y-2">
                                <label for="occupation" class="block text-sm font-medium text-gray-600 mb-1">Occupation <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/></svg>
                                    </span>
                                    <select id="occupation" name="occupation" required
                                            class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition">
                                        <option value="">Select occupation</option>
                                        <option value="Student">Student</option>
                                        <option value="Unemployed">Unemployed</option>
                                        <option value="Self-Employed">Self-Employed</option>
                                        <option value="Freelancer">Freelancer</option>
                                        <option value="Business Owner">Business Owner</option>
                                        <option value="Homemaker">Homemaker</option>
                                        <option value="Retired">Retired</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div id="occupation_other_container" class="mt-2 hidden">
                                    <input type="text" id="occupation_other" name="occupation_other"
                                           class="login-input w-full pl-3 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition"
                                           placeholder="Please specify">
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="government_id_type" class="block text-sm font-medium text-gray-600 mb-1">Government ID <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    </span>
                                    <select id="government_id_type" name="government_id_type" required
                                            class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition">
                                        <option value="">Select ID type</option>
                                        <option value="PhilHealth ID">PhilHealth ID</option>
                                        <option value="SSS ID">SSS ID</option>
                                        <option value="GSIS ID">GSIS ID</option>
                                        <option value="TIN">TIN</option>
                                        <option value="Passport ID">Passport ID</option>
                                    </select>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="government_id_number" class="block text-sm font-medium text-gray-600 mb-1">Government ID Number <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="9" y2="9"/><line x1="4" x2="20" y1="15" y2="15"/><line x1="10" x2="8" y1="3" y2="21"/><line x1="16" x2="14" y1="3" y2="21"/></svg>
                                    </span>
                                    <input id="government_id_number" name="government_id_number" type="text" required
                                           class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition"
                                           placeholder="Select ID type first">
                                </div>
                                <p id="government_id_format_hint" class="text-xs text-gray-500 hidden"></p>
                            </div>
                        </div>

                        <div class="mt-6">
                            <button type="button" id="nextToStep2" 
                                    class="w-full bg-[#008080] hover:bg-[#0f766e] text-white font-semibold py-3 rounded-full shadow-lg transition">
                                Next
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Address Information -->
                    <div id="step2" class="step-content">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="space-y-2 md:col-span-2">
                                <label for="house_number" class="block text-sm font-medium text-gray-600 mb-1">House No. & Street <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                                    </span>
                                    <input id="house_number" name="house_number" type="text" required
                                           class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition"
                                           placeholder="e.g., 123 Mabini St.">
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="region" class="block text-sm font-medium text-gray-600 mb-1">Region <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" x2="22" y1="12" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                                    </span>
                                    <select id="region" name="region" required
                                            class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition">
                                        <option value="">Loading regions...</option>
                                    </select>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="province" class="block text-sm font-medium text-gray-600 mb-1">Province <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                                    </span>
                                    <select id="province" name="province" required disabled
                                            class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition">
                                        <option value="">Select region first</option>
                                    </select>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="city_municipality" class="block text-sm font-medium text-gray-600 mb-1">City / Municipality <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>
                                    </span>
                                    <select id="city_municipality" name="city_municipality" required disabled
                                            class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition">
                                        <option value="">Select province first</option>
                                    </select>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="barangay" class="block text-sm font-medium text-gray-600 mb-1">Barangay <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/><line x1="9" x2="9" y1="3" y2="21"/><line x1="15" x2="15" y1="6" y2="18"/></svg>
                                    </span>
                                    <select id="barangay" name="barangay" required disabled
                                            class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition">
                                        <option value="">Select city/municipality first</option>
                                    </select>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="zip_code" class="block text-sm font-medium text-gray-600 mb-1">ZIP Code <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                                    </span>
                                    <input id="zip_code" name="zip_code" type="text" maxlength="4" pattern="\d{4}" required
                                           class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition"
                                           placeholder="e.g., 1000">
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex gap-3">
                            <button type="button" id="backToStep1" 
                                    class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 rounded-full transition">
                                Previous
                            </button>
                            <button type="button" id="nextToStep3" 
                                    class="flex-1 bg-[#008080] hover:bg-[#0f766e] text-white font-semibold py-3 rounded-full shadow-lg transition">
                                Next
                            </button>
                        </div>
                    </div>

                    <!-- Step 3: Contact & Account Information -->
                    <div id="step3" class="step-content">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="space-y-2">
                                <label for="contact_number" class="block text-sm font-medium text-gray-600 mb-1">Contact Number <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                    </span>
                                    <input id="contact_number" name="contact_number" type="tel" required maxlength="13" pattern="^09\d{2}\s\d{3}\s\d{4}$" value="09"
                                           class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition"
                                           placeholder="09XX XXX XXXX" inputmode="numeric">
                                </div>
                                <p class="text-xs text-gray-500">Format: 09XX XXX XXXX</p>
                                </div>

                                <div class="space-y-2">
                                    <label for="email" class="block text-sm font-medium text-gray-600 mb-1">Email Address <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                                        </span>
                                        <input id="email" name="email" type="email" required autocomplete="email"
                                               class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition"
                                               placeholder="you@example.com">
                                    </div>
                                </div>

                            <div class="space-y-2">
                                <label for="emergency_contact_name" class="block text-sm font-medium text-gray-600 mb-1">Emergency Contact Name <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
                                    </span>
                                    <input id="emergency_contact_name" name="emergency_contact_name" type="text" required
                                           class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition"
                                           placeholder="Full name of emergency contact">
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="emergency_contact_number" class="block text-sm font-medium text-gray-600 mb-1">Emergency Contact Number <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/><polyline points="22 2 22 8 16 8"/></svg>
                                    </span>
                                    <input id="emergency_contact_number" name="emergency_contact_number" type="tel" required maxlength="13" pattern="^09\d{2}\s\d{3}\s\d{4}$" value="09"
                                           class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition"
                                           placeholder="09XX XXX XXXX" inputmode="numeric">
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="emergency_contact_relationship" class="block text-sm font-medium text-gray-600 mb-1">Relationship <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 12h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 14"/><path d="M7 18h1c.5 0 .9-.2 1.2-.6l4.6-5.4a1 1 0 0 1 1.5.1l2.7 3.3a1 1 0 0 0 1.4.1l2.6-2.4a1 1 0 0 1 1.6.6v4a1 1 0 0 1-1 1h-4.5"/><path d="m5 12-2 2"/></svg>
                                    </span>
                                    <select id="emergency_contact_relationship" name="emergency_contact_relationship" required
                                            class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition">
                                        <option value="">Select relationship</option>
                                        <option value="Mother">Mother</option>
                                        <option value="Father">Father</option>
                                        <option value="Brother">Brother</option>
                                        <option value="Sister">Sister</option>
                                        <option value="Guardian">Guardian</option>
                                        <option value="Child">Child</option>
                                    </select>
                                </div>
                            </div>

                        </div>

                        <div class="mt-6 flex gap-3">
                            <button type="button" id="backToStep2" 
                                    class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 rounded-full transition">
                                Previous
                            </button>
                            <button type="button" id="nextToStep4" 
                                    class="flex-1 bg-[#008080] hover:bg-[#0f766e] text-white font-semibold py-3 rounded-full shadow-lg transition">
                                Next
                            </button>
                        </div>
                    </div>

                    <!-- Step 4: Create Password -->
                    <div id="step4" class="step-content">
                        <div class="space-y-6">
                            <div class="text-center">
                                <h3 class="text-xl font-semibold text-gray-800 mb-2">Create Your Password</h3>
                                <p class="text-sm text-gray-500">Choose a strong password to secure your account</p>
                            </div>

                            <div class="space-y-2">
                                <label for="username" class="block text-sm font-medium text-gray-600 mb-1">Username <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
                                    </span>
                                    <input id="username" name="username" type="text" required autocomplete="username"
                                           class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition"
                                           placeholder="Username" pattern="[a-zA-Z0-9_]{3,20}" title="Username must be 3-20 characters (letters, numbers, underscore only)">
                                </div>
                                <p class="mt-1 text-xs text-gray-500">3-20 characters, letters, numbers, underscore only</p>
                            </div>

                            <div class="space-y-2">
                                <label for="password" class="block text-sm font-medium text-gray-600 mb-1">Password <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    </span>
                                    <input id="password" name="password" type="password" required 
                                           class="login-input w-full pl-10 pr-10 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition"
                                           placeholder="Create password">
                                    <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 flex items-center pr-3 text-[#008080] hover:text-[#0f766e] transition-colors">
                                        <svg id="passwordEyeIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                </div>
                                <!-- Password Strength Indicator -->
                                <div id="password-strength-container" class="mt-2 hidden">
                                    <div class="flex items-center gap-2 mb-1">
                                        <div id="password-strength-bar" class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                                            <div id="password-strength-fill" class="h-full transition-all duration-300" style="width: 0%"></div>
                                        </div>
                                        <span id="password-strength-text" class="text-xs font-medium"></span>
                                    </div>
                                    <ul id="password-requirements" class="text-xs text-gray-600 space-y-1">
                                        <li id="req-length" class="flex items-center gap-2">
                                            <svg class="text-gray-400" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>
                                            <span>At least 8 characters</span>
                                        </li>
                                        <li id="req-uppercase" class="flex items-center gap-2">
                                            <svg class="text-gray-400" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>
                                            <span>One uppercase letter</span>
                                        </li>
                                        <li id="req-lowercase" class="flex items-center gap-2">
                                            <svg class="text-gray-400" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>
                                            <span>One lowercase letter</span>
                                        </li>
                                        <li id="req-number" class="flex items-center gap-2">
                                            <svg class="text-gray-400" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>
                                            <span>One number</span>
                                        </li>
                                        <li id="req-special" class="flex items-center gap-2">
                                            <svg class="text-gray-400" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>
                                            <span>One special character</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="confirm_password" class="block text-sm font-medium text-gray-600 mb-1">Confirm Password <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#008080]">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-8.5 8.5"/></svg>
                                    </span>
                                    <input id="confirm_password" name="confirm_password" type="password" required 
                                           class="login-input w-full pl-10 pr-10 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#008080] focus:border-transparent transition"
                                           placeholder="Confirm password">
                                    <button type="button" id="toggleConfirmPassword" class="absolute inset-y-0 right-0 flex items-center pr-3 text-[#008080] hover:text-[#0f766e] transition-colors">
                                        <svg id="confirmPasswordEyeIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                </div>
                                <p id="password-match-message" class="mt-1 text-xs text-red-600 hidden"></p>
                            </div>
                        </div>

                        <div class="mt-6 flex gap-3">
                            <button type="button" id="backToStep3" 
                                    class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 rounded-full transition">
                                Previous
                            </button>
                            <button type="button" id="nextToStep5" 
                                    class="flex-1 bg-[#008080] hover:bg-[#0f766e] text-white font-semibold py-3 rounded-full shadow-lg transition">
                                Next
                            </button>
                        </div>
                    </div>

                    <!-- Step 5: Photo Setup -->
                    <div id="step5" class="step-content">
                            <div class="space-y-6">
                                <div class="text-center">
                                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Set Up Your Profile Picture</h3>
                                    <p class="text-sm text-gray-500">Upload a photo or take one with your camera</p>
                                </div>

                                <!-- Profile Picture Preview -->
                                <div class="flex flex-col items-center space-y-4 w-full">
                                    <div class="relative w-full flex justify-center items-center min-h-[200px]">
                                        <div id="profilePreview" class="w-32 h-32 bg-purple-500 rounded-lg flex items-center justify-center text-white font-semibold text-4xl border-2 border-gray-200">
                                            <span id="previewInitials">?</span>
                                        </div>
                                        <img id="previewImage" src="" alt="Preview" class="hidden w-32 h-32 rounded-lg object-cover border-2 border-gray-200">
                                        <video id="cameraPreview" class="hidden rounded-lg object-cover border-2 border-gray-200 shadow-lg bg-gray-900" autoplay playsinline></video>
                                    </div>

                                    <!-- Upload Option -->
                                    <div class="w-full space-y-3">
                                        <label for="profile_picture" class="block text-sm font-medium text-gray-600 mb-2">Upload Photo</label>
                                        <div class="relative">
                                            <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/jpg,image/png,image/gif" 
                                                   class="hidden">
                                            <button type="button" id="uploadBtn" 
                                                    class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-3 rounded-full transition flex items-center justify-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
                                                Choose File
                                            </button>
                                        </div>
                                        <p class="text-xs text-gray-500 text-center">Maximum file size: 2MB (JPEG, PNG, GIF)</p>
                                    </div>

                                    <!-- Camera Option -->
                                    <div class="w-full space-y-3">
                                        <label class="block text-sm font-medium text-gray-600 mb-2">Take Photo</label>
                                        <div class="flex gap-2">
                                            <button type="button" id="startCameraBtn" 
                                                    class="flex-1 bg-[#008080] hover:bg-[#0f766e] text-white font-medium py-3 rounded-full transition flex items-center justify-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
                                                Open Camera
                                            </button>
                                            <button type="button" id="captureBtn" 
                                                    class="hidden flex-1 bg-green-600 hover:bg-green-700 text-white font-medium py-3 rounded-full transition flex items-center justify-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                                                Capture
                                            </button>
                                            <button type="button" id="stopCameraBtn" 
                                                    class="hidden flex-1 bg-red-600 hover:bg-red-700 text-white font-medium py-3 rounded-full transition flex items-center justify-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
                                                Cancel
                                            </button>
                                            <button type="button" id="retakeBtn" 
                                                    class="hidden flex-1 bg-orange-600 hover:bg-orange-700 text-white font-medium py-3 rounded-full transition flex items-center justify-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.3"/></svg>
                                                Retake
                                            </button>
                                        </div>
                                        <canvas id="captureCanvas" class="hidden"></canvas>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 flex gap-3">
                                <button type="button" id="backToStep4" 
                                        class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 rounded-full transition">
                                    Previous
                                </button>
                                <button type="button" id="nextToStep6" 
                                        class="flex-1 bg-[#008080] hover:bg-[#0f766e] text-white font-semibold py-3 rounded-full shadow-lg transition">
                                    Next
                                </button>
                            </div>
                        </div>

                        <!-- Step 6: Terms and Privacy Policy -->
                        <div id="step6" class="step-content">
                            <div class="space-y-6">
                                <div class="text-center">
                                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Terms & Privacy Policy</h3>
                                    <p class="text-sm text-gray-500">Please read and accept our terms to continue</p>
                                </div>

                                <!-- Terms and Privacy Policy Content -->
                                <div class="bg-gray-50 rounded-lg p-4 max-h-64 overflow-y-auto border border-gray-200">
                                    <div class="space-y-4 text-sm text-gray-700">
                                        <div>
                                            <h4 class="font-semibold text-gray-900 mb-2">Terms and Conditions</h4>
                                            <p class="mb-2">By creating an account, you agree to the following terms:</p>
                                            <ul class="list-disc list-inside space-y-1 ml-2">
                                                <li>You are responsible for maintaining the confidentiality of your account credentials.</li>
                                                <li>You agree to provide accurate and complete information during registration.</li>
                                                <li>You will not use the service for any unlawful purpose.</li>
                                                <li>You understand that your medical information will be kept confidential and secure.</li>
                                                <li>The hospital reserves the right to suspend or terminate accounts that violate these terms.</li>
                                            </ul>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-900 mb-2">Privacy Policy</h4>
                                            <p class="mb-2">We are committed to protecting your privacy:</p>
                                            <ul class="list-disc list-inside space-y-1 ml-2">
                                                <li>Your personal and medical information is collected solely for healthcare purposes.</li>
                                                <li>We implement security measures to protect your data from unauthorized access.</li>
                                                <li>Your information will not be shared with third parties without your consent, except as required by law.</li>
                                                <li>You have the right to access, update, or delete your personal information.</li>
                                                <li>We use cookies and similar technologies to enhance your experience.</li>
                                            </ul>
                                        </div>
                                        <div class="pt-2 border-t border-gray-300">
                                            <p class="text-xs text-gray-600">
                                                <strong>Last Updated:</strong> <?php echo date('F j, Y'); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Acceptance Checkbox -->
                                <div class="flex items-start space-x-3">
                                    <input type="checkbox" id="accept_terms" name="accept_terms" required
                                           class="mt-1 w-4 h-4 text-[#008080] border-gray-300 rounded focus:ring-[#008080]">
                                    <label for="accept_terms" class="text-sm text-gray-700">
                                        I have read and agree to the <strong>Terms and Conditions</strong> and <strong>Privacy Policy</strong>.
                                        <span class="text-red-500">*</span>
                                    </label>
                                </div>
                            </div>

                            <div class="mt-6 flex gap-3">
                                <button type="button" id="backToStep5" 
                                        class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 rounded-full transition">
                                    Previous
                                </button>
                                <button type="submit" id="submitBtn" 
                                        class="flex-1 bg-[#008080] hover:bg-[#0f766e] text-white font-semibold py-3 rounded-full shadow-lg transition">
                                    Create Account
                                </button>
                            </div>
                        </div>
                    </form>

                    <p class="mt-8 text-sm text-gray-500 text-center">
                        Already have an account?
                        <a href="login.php" class="font-semibold text-[#008080] hover:text-[#0f766e]">Login</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            
            let currentStep = 1;
            const totalSteps = 6;
            let stream = null;
            let capturedImageBlob = null;
            const PSGC_API_BASE = 'https://psgc.gitlab.io/api';

            // Track touched fields to only show validation after user interaction
            const touchedFields = new Set();
            
            const firstNameField = document.getElementById('first_name');
            const middleNameField = document.getElementById('middle_name');
            const lastNameField = document.getElementById('last_name');
            const birthDateInput = document.getElementById('birth_date');
            const ageInput = document.getElementById('age');
            const passwordField = document.getElementById('password');
            const confirmPasswordField = document.getElementById('confirm_password');
            const matchMessage = document.getElementById('password-match-message');
            const contactNumberField = document.getElementById('contact_number');
            const emergencyNumberField = document.getElementById('emergency_contact_number');
            const regionSelect = document.getElementById('region');
            const provinceSelect = document.getElementById('province');
            const citySelect = document.getElementById('city_municipality');
            const barangaySelect = document.getElementById('barangay');

            // Step navigation
            function showStep(step) {
                document.querySelectorAll('.step-content').forEach(el => el.classList.remove('active'));
                const stepElement = document.getElementById(`step${step}`);
                if (stepElement) {
                    stepElement.classList.add('active');
                }
                
                for (let i = 1; i <= totalSteps; i++) {
                    const indicator = document.getElementById(`step${i}-indicator`);
                    const stepLabel = document.getElementById(`step${i}-label`);
                    
                    if (i < step) {
                        indicator?.classList.remove('active', 'bg-white', 'text-gray-600', 'border-gray-300');
                        indicator?.classList.add('completed', 'bg-[#008080]', 'text-white', 'border-[#008080]');
                        if (indicator) indicator.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
                        if (stepLabel) {
                            stepLabel.classList.remove('text-gray-500');
                            stepLabel.classList.add('text-[#008080]');
                        }
                            const line = document.getElementById(`step${i}-line`);
                            if (line) {
                                line.classList.remove('bg-gray-200');
                                line.classList.add('bg-[#008080]');
                        }
                    } else if (i === step) {
                        indicator?.classList.remove('completed', 'bg-white', 'text-gray-600', 'border-gray-300');
                        indicator?.classList.add('active', 'bg-[#008080]', 'text-white', 'border-[#008080]');
                        if (indicator) indicator.textContent = i;
                        if (stepLabel) {
                            stepLabel.classList.remove('text-gray-500');
                            stepLabel.classList.add('text-[#008080]');
                        }
                            const prevLine = document.getElementById(`step${i-1}-line`);
                        if (prevLine && i > 1) {
                                prevLine.classList.remove('bg-gray-200');
                                prevLine.classList.add('bg-[#008080]');
                        }
                    } else {
                        indicator?.classList.remove('active', 'completed', 'bg-[#008080]', 'text-white', 'border-[#008080]');
                        indicator?.classList.add('bg-white', 'text-gray-600', 'border-gray-300');
                        if (indicator) indicator.textContent = i;
                        if (stepLabel) {
                            stepLabel.classList.remove('text-[#008080]');
                            stepLabel.classList.add('text-gray-500');
                        }
                            const prevLine = document.getElementById(`step${i-1}-line`);
                        if (prevLine && i > step) {
                                prevLine.classList.remove('bg-[#008080]');
                                prevLine.classList.add('bg-gray-200');
                        }
                    }
                }
            }

            function setButtonLoading(button, text) {
                if (!button || button.dataset.loading === 'true') return;
                button.dataset.loading = 'true';
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.innerHTML = `
                    <span class="inline-flex items-center justify-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                        </svg>
                        <span>${text}</span>
                    </span>
                `;
            }

            function validateStep(stepId) {
                const stepElement = document.getElementById(stepId);
                if (!stepElement) return true;
                let isValid = true;
                const fields = stepElement.querySelectorAll('input, select, textarea');
                fields.forEach(field => {
                    // Skip hidden fields unless they're required
                    if (field.closest('.hidden') && !field.hasAttribute('required')) {
                        return;
                    }
                    // Skip readonly fields
                    if (field.hasAttribute('readonly')) {
                        return;
                    }
                    if (field.hasAttribute('required')) {
                        // Mark field as touched when validating
                        const fieldId = field.id;
                        if (fieldId) {
                            touchedFields.add(fieldId);
                        }
                        
                        if (!field.checkValidity() || (field.type === 'select-one' && !field.value)) {
                            field.classList.add('border-red-500');
                            isValid = false;
                        } else {
                            field.classList.remove('border-red-500');
                        }
                    }
                });

                // Special validation for occupation "Other"
                if (stepId === 'step1') {
                    const occupationField = document.getElementById('occupation');
                    const occupation = occupationField?.value;
                    if (occupation === 'Other') {
                        const otherInput = document.getElementById('occupation_other');
                        if (otherInput) {
                            touchedFields.add('occupation_other');
                            if (!otherInput.value.trim()) {
                                otherInput.classList.add('border-red-500');
                                isValid = false;
                            } else {
                                otherInput.classList.remove('border-red-500');
                            }
                        }
                    }
                }

                if (stepId === 'step4') {
                    isValid = validatePasswordMatch() && isValid;
                }

                return isValid;
            }

            function validatePasswordMatch() {
                const password = passwordField?.value || '';
                const confirmPassword = confirmPasswordField?.value || '';

                if (!matchMessage) return password === confirmPassword;

                if (confirmPassword.length === 0) {
                    matchMessage.classList.add('hidden');
                    matchMessage.classList.remove('text-green-600', 'text-red-600');
                    return password === confirmPassword;
                }

                if (password !== confirmPassword) {
                    matchMessage.textContent = 'Passwords do not match';
                    matchMessage.classList.remove('hidden', 'text-green-600');
                    matchMessage.classList.add('text-red-600');
                    return false;
                } else {
                    matchMessage.textContent = 'Passwords match';
                    matchMessage.classList.remove('hidden', 'text-red-600');
                    matchMessage.classList.add('text-green-600');
                    return true;
                }
            }

            function updatePreviewInitials() {
                const first = firstNameField?.value.trim().charAt(0).toUpperCase() || '';
                const last = lastNameField?.value.trim().charAt(0).toUpperCase() || '';
                const previewInitials = document.getElementById('previewInitials');
                if (previewInitials) {
                    previewInitials.textContent = (first + last) || '?';
                }
            }

            function calculateAge(dateValue) {
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

            birthDateInput?.addEventListener('change', () => {
                const age = calculateAge(birthDateInput.value);
                ageInput.value = age !== '' ? age : '';
            });

            [firstNameField, lastNameField, middleNameField].forEach(field => {
                field?.addEventListener('input', updatePreviewInitials);
            });

            // Occupation "Other" option handler
            const occupationSelect = document.getElementById('occupation');
            const occupationOtherContainer = document.getElementById('occupation_other_container');
            const occupationOtherInput = document.getElementById('occupation_other');

            occupationSelect?.addEventListener('change', function() {
                if (this.value === 'Other') {
                    occupationOtherContainer?.classList.remove('hidden');
                    occupationOtherInput?.setAttribute('required', 'required');
                } else {
                    occupationOtherContainer?.classList.add('hidden');
                    occupationOtherInput?.removeAttribute('required');
                    occupationOtherInput.value = '';
                }
            });

            // Phone formatting
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

            [contactNumberField, emergencyNumberField].forEach(field => {
                if (field) {
                    const handler = () => formatPhilippineNumberField(field);
                    field.addEventListener('input', handler);
                    field.addEventListener('blur', handler);
                    handler();
                }
            });

            const sanitizePhoneValue = (value = '') => value.replace(/\D/g, '').slice(0, 11);

            // Government ID formatting
            const governmentIdTypeSelect = document.getElementById('government_id_type');
            const governmentIdNumberInput = document.getElementById('government_id_number');
            const governmentIdFormatHint = document.getElementById('government_id_format_hint');

            const governmentIdFormats = {
                'PhilHealth ID': {
                    pattern: /^(\d{4})-?(\d{4})-?(\d{4})$/,
                    format: (value) => {
                        const digits = value.replace(/\D/g, '').slice(0, 12);
                        if (digits.length === 0) return '';
                        if (digits.length <= 4) return digits;
                        if (digits.length <= 8) return `${digits.slice(0, 4)}-${digits.slice(4)}`;
                        return `${digits.slice(0, 4)}-${digits.slice(4, 8)}-${digits.slice(8)}`;
                    },
                    placeholder: '0123-4567-8912',
                    hint: 'Format: 12 digits (0123-4567-8912)',
                    maxLength: 14,
                    validation: /^\d{4}-\d{4}-\d{4}$/
                },
                'SSS ID': {
                    pattern: /^(\d{4})-?(\d{6})-?(\d{2})$/,
                    format: (value) => {
                        const digits = value.replace(/\D/g, '').slice(0, 12);
                        if (digits.length === 0) return '';
                        if (digits.length <= 4) return digits;
                        if (digits.length <= 10) return `${digits.slice(0, 4)}-${digits.slice(4)}`;
                        return `${digits.slice(0, 4)}-${digits.slice(4, 10)}-${digits.slice(10)}`;
                    },
                    placeholder: '0123-456789-12',
                    hint: 'Format: 12 digits (0123-456789-12)',
                    maxLength: 15,
                    validation: /^\d{4}-\d{6}-\d{2}$/
                },
                'GSIS ID': {
                    pattern: /^(\d{4})-?(\d{6})-?(\d{2})$/,
                    format: (value) => {
                        const digits = value.replace(/\D/g, '').slice(0, 12);
                        if (digits.length === 0) return '';
                        if (digits.length <= 4) return digits;
                        if (digits.length <= 10) return `${digits.slice(0, 4)}-${digits.slice(4)}`;
                        return `${digits.slice(0, 4)}-${digits.slice(4, 10)}-${digits.slice(10)}`;
                    },
                    placeholder: '0123-456789-12',
                    hint: 'Format: 12 digits (0123-456789-12)',
                    maxLength: 15,
                    validation: /^\d{4}-\d{6}-\d{2}$/
                },
                'TIN': {
                    pattern: /^(\d{3})-?(\d{3})-?(\d{3})$/,
                    format: (value) => {
                        const digits = value.replace(/\D/g, '').slice(0, 9);
                        if (digits.length === 0) return '';
                        if (digits.length <= 3) return digits;
                        if (digits.length <= 6) return `${digits.slice(0, 3)}-${digits.slice(3)}`;
                        return `${digits.slice(0, 3)}-${digits.slice(3, 6)}-${digits.slice(6)}`;
                    },
                    placeholder: '123-456-789',
                    hint: 'Format: 9 digits (123-456-789)',
                    maxLength: 11,
                    validation: /^\d{3}-\d{3}-\d{3}$/
                },
                'Passport ID': {
                    pattern: /^([A-Za-z])(\d{7})$/,
                    format: (value) => {
                        const cleaned = value.replace(/[^A-Za-z0-9]/g, '');
                        if (cleaned.length === 0) return '';
                        const letter = cleaned.match(/[A-Za-z]/)?.[0] || '';
                        const digits = cleaned.replace(/[A-Za-z]/g, '').slice(0, 7);
                        return letter ? (letter.toUpperCase() + digits) : digits;
                    },
                    placeholder: 'A1234567',
                    hint: 'Format: 1 letter + 7 digits (A1234567)',
                    maxLength: 8,
                    validation: /^[A-Z]\d{7}$/
                }
            };

            function updateGovernmentIdFormat() {
                const selectedType = governmentIdTypeSelect?.value || '';
                const formatConfig = governmentIdFormats[selectedType];
                
                if (!formatConfig) {
                    governmentIdNumberInput.placeholder = 'Select ID type first';
                    governmentIdNumberInput.maxLength = 50;
                    governmentIdNumberInput.pattern = '';
                    governmentIdFormatHint.classList.add('hidden');
                    return;
                }

                governmentIdNumberInput.placeholder = formatConfig.placeholder;
                governmentIdNumberInput.maxLength = formatConfig.maxLength;
                governmentIdNumberInput.pattern = formatConfig.validation.source;
                governmentIdFormatHint.textContent = formatConfig.hint;
                governmentIdFormatHint.classList.remove('hidden');
            }

            function formatGovernmentIdNumber() {
                const selectedType = governmentIdTypeSelect?.value || '';
                const formatConfig = governmentIdFormats[selectedType];
                
                if (!formatConfig || !governmentIdNumberInput) return;
                
                const currentValue = governmentIdNumberInput.value;
                const formatted = formatConfig.format(currentValue);
                
                if (formatted !== currentValue) {
                    governmentIdNumberInput.value = formatted;
                }
            }

            governmentIdTypeSelect?.addEventListener('change', function() {
                updateGovernmentIdFormat();
                governmentIdNumberInput.value = '';
                governmentIdNumberInput.classList.remove('border-red-500');
            });

            governmentIdNumberInput?.addEventListener('input', function() {
                formatGovernmentIdNumber();
                // Mark as touched
                touchedFields.add('government_id_number');
                // Validate on input - only show red if touched and invalid
                const selectedType = governmentIdTypeSelect?.value || '';
                const formatConfig = governmentIdFormats[selectedType];
                if (formatConfig && this.value) {
                    const isValid = formatConfig.validation.test(this.value);
                    this.classList.toggle('border-red-500', !isValid && this.value.length > 0);
                } else if (this.value.length > 0) {
                    // If user has entered something but format is wrong
                    this.classList.add('border-red-500');
                } else {
                    this.classList.remove('border-red-500');
                }
            });

            governmentIdNumberInput?.addEventListener('blur', function() {
                formatGovernmentIdNumber();
            });

            // Address cascading
            function resetSelect(select, message, disable = true) {
                if (!select) return;
                select.innerHTML = `<option value="">${message}</option>`;
                select.disabled = disable;
            }

            function populateSelect(select, items, placeholder) {
                if (!select) return;
                select.innerHTML = `<option value="">${placeholder}</option>`;
                items.forEach(item => {
                    const option = document.createElement('option');
                    const label = item.label ?? item.name ?? item.value ?? '';
                    option.value = item.value ?? item.name ?? item.code ?? '';
                    option.textContent = label;
                    if (item.code) {
                        option.dataset.code = item.code;
                    }
                    if (item.scope) {
                        option.dataset.scope = item.scope;
                    }
                    select.appendChild(option);
                });
                select.disabled = items.length === 0;
                if (items.length === 0) {
                    select.innerHTML = `<option value="">No data available</option>`;
                }
            }

            async function fetchJSON(url) {
                const response = await fetch(url);
                if (!response.ok) {
                    throw new Error(`Unable to fetch ${url}`);
                }
                return response.json();
            }

            async function loadRegions() {
                resetSelect(regionSelect, 'Loading regions...', true);
                try {
                    const regions = await fetchJSON(`${PSGC_API_BASE}/regions`);
                    regions.sort((a, b) => a.name.localeCompare(b.name));
                    const regionOptions = regions.map(region => ({
                        label: region.name,
                        value: region.name,
                        code: region.code
                    }));
                    populateSelect(regionSelect, regionOptions, 'Select region');
                } catch (error) {
                    console.error('Region fetch error:', error);
                    resetSelect(regionSelect, 'Unable to load regions. Refresh to retry.', true);
                }
            }

            regionSelect?.addEventListener('change', async (event) => {
                const selectedOption = event.target.selectedOptions[0];
                resetSelect(provinceSelect, 'Loading provinces...', true);
                resetSelect(citySelect, 'Select province first');
                resetSelect(barangaySelect, 'Select city/municipality first');
                if (!selectedOption || !selectedOption.dataset.code) {
                    resetSelect(provinceSelect, 'Select region first');
                    return;
                }
                const regionCode = selectedOption.dataset.code;
                try {
                    let provinceOptions = [];
                    const provinces = await fetchJSON(`${PSGC_API_BASE}/regions/${regionCode}/provinces`);
                    provinces.sort((a, b) => a.name.localeCompare(b.name));
                    provinceOptions = provinces.map(province => ({
                        label: province.name,
                        value: province.name,
                        code: province.code,
                        scope: 'province'
                    }));

                    if (provinceOptions.length === 0) {
                        const districts = await fetchJSON(`${PSGC_API_BASE}/regions/${regionCode}/districts`);
                        districts.sort((a, b) => a.name.localeCompare(b.name));
                        provinceOptions = districts.map(district => ({
                            label: district.name,
                            value: district.name,
                            code: district.code,
                            scope: 'district'
                        }));
                    }

                    populateSelect(provinceSelect, provinceOptions, 'Select province/district');
                } catch (error) {
                    console.error('Province fetch error:', error);
                    resetSelect(provinceSelect, 'Unable to load provinces. Try again.', true);
                }
            });

            provinceSelect?.addEventListener('change', async (event) => {
                const selectedOption = event.target.selectedOptions[0];
                resetSelect(citySelect, 'Loading cities/municipalities...', true);
                resetSelect(barangaySelect, 'Select city/municipality first');
                if (!selectedOption || !selectedOption.dataset.code) {
                    resetSelect(citySelect, 'Select province first');
                    return;
                }
                const provinceCode = selectedOption.dataset.code;
                const locationScope = selectedOption.dataset.scope || 'province';
                try {
                    let municipalities = [];
                    let endpoint = `${PSGC_API_BASE}/provinces/${provinceCode}/cities-municipalities`;
                    if (locationScope === 'district') {
                        endpoint = `${PSGC_API_BASE}/districts/${provinceCode}/cities-municipalities`;
                    }
                    municipalities = await fetchJSON(endpoint);
                    municipalities.sort((a, b) => a.name.localeCompare(b.name));
                    const cityOptions = municipalities.map(city => ({
                        label: city.name,
                        value: city.name,
                        code: city.code
                    }));
                    populateSelect(citySelect, cityOptions, 'Select city/municipality');
                } catch (error) {
                    console.error('City fetch error:', error);
                    resetSelect(citySelect, 'Unable to load cities. Try again.', true);
                }
            });

            citySelect?.addEventListener('change', async (event) => {
                const selectedOption = event.target.selectedOptions[0];
                resetSelect(barangaySelect, 'Loading barangays...', true);
                if (!selectedOption || !selectedOption.dataset.code) {
                    resetSelect(barangaySelect, 'Select city/municipality first');
                    return;
                }
                const cityCode = selectedOption.dataset.code;
                try {
                    const barangays = await fetchJSON(`${PSGC_API_BASE}/cities-municipalities/${cityCode}/barangays`);
                    barangays.sort((a, b) => a.name.localeCompare(b.name));
                    const barangayOptions = barangays.map(barangay => ({
                        label: barangay.name,
                        value: barangay.name,
                        code: barangay.code
                    }));
                    populateSelect(barangaySelect, barangayOptions, 'Select barangay');
                } catch (error) {
                    console.error('Barangay fetch error:', error);
                    resetSelect(barangaySelect, 'Unable to load barangays. Try again.', true);
                }
            });

            loadRegions();

            // Password strength checking
            function checkPasswordStrength(password) {
                if (!password) return { strength: 0, label: '', color: '' };
                
                let strength = 0;
                const checks = {
                    length: password.length >= 8,
                    uppercase: /[A-Z]/.test(password),
                    lowercase: /[a-z]/.test(password),
                    number: /[0-9]/.test(password),
                    special: /[^A-Za-z0-9]/.test(password)
                };
                
                strength = Object.values(checks).filter(Boolean).length;
                
                let label = '';
                let color = '';
                
                if (strength <= 2) {
                    label = 'Weak';
                    color = '#ef4444'; // red
                } else if (strength === 3 || strength === 4) {
                    label = 'Medium';
                    color = '#f59e0b'; // amber
                } else {
                    label = 'Strong';
                    color = '#10b981'; // green
                }
                
                return { strength, label, color, checks };
            }

            function updatePasswordStrength() {
                const password = passwordField?.value || '';
                const strengthContainer = document.getElementById('password-strength-container');
                const strengthFill = document.getElementById('password-strength-fill');
                const strengthText = document.getElementById('password-strength-text');
                
                if (!strengthContainer || !strengthFill || !strengthText) return;
                
                if (password.length === 0) {
                    strengthContainer.classList.add('hidden');
                    return;
                }
                
                strengthContainer.classList.remove('hidden');
                const result = checkPasswordStrength(password);
                const percentage = (result.strength / 5) * 100;
                
                strengthFill.style.width = percentage + '%';
                strengthFill.style.backgroundColor = result.color;
                strengthText.textContent = result.label;
                strengthText.style.color = result.color;
                
                // Update requirement checkmarks
                const reqIds = ['req-length', 'req-uppercase', 'req-lowercase', 'req-number', 'req-special'];
                const checkKeys = ['length', 'uppercase', 'lowercase', 'number', 'special'];
                
                reqIds.forEach((reqId, index) => {
                    const reqElement = document.getElementById(reqId);
                    if (reqElement) {
                        const icon = reqElement.querySelector('svg');
                        const span = reqElement.querySelector('span');
                        if (result.checks[checkKeys[index]]) {
                            icon.outerHTML = '<svg class="text-green-500" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
                            if (span) span.classList.add('text-green-600');
                        } else {
                            icon.outerHTML = '<svg class="text-gray-400" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>';
                            if (span) span.classList.remove('text-green-600');
                        }
                    }
                });
            }

            passwordField?.addEventListener('input', updatePasswordStrength);

            // Navigation buttons
            const buttonActions = [
                { id: 'nextToStep2', action: () => { if (validateStep('step1')) { currentStep = 2; showStep(2); updatePreviewInitials(); } } },
                { id: 'backToStep1', action: () => { currentStep = 1; showStep(1); } },
                { id: 'nextToStep3', action: () => { if (validateStep('step2')) { currentStep = 3; showStep(3); } } },
                { id: 'backToStep2', action: () => { currentStep = 2; showStep(2); } },
                { id: 'nextToStep4', action: () => { if (validateStep('step3')) { currentStep = 4; showStep(4); } } },
                { id: 'backToStep3', action: () => { currentStep = 3; showStep(3); } },
                { id: 'nextToStep5', action: () => { if (validateStep('step4')) { currentStep = 5; showStep(5); } } },
                { id: 'backToStep4', action: () => { currentStep = 4; showStep(4); } },
                { id: 'nextToStep6', action: () => { currentStep = 6; showStep(6); } },
                { id: 'backToStep5', action: () => { currentStep = 5; showStep(5); } }
            ];

            buttonActions.forEach(({ id, action }) => {
                const button = document.getElementById(id);
                button?.addEventListener('click', action);
            });

            // Enter key shortcuts
            const step1Inputs = document.querySelectorAll('#step1 input, #step1 select');
            step1Inputs.forEach(input => {
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.altKey) {
                        e.preventDefault();
                        document.getElementById('nextToStep2')?.click();
                    }
                });
            });

            const step2Fields = document.querySelectorAll('#step2 input, #step2 select');
            step2Fields.forEach(field => {
                field.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.altKey) {
                        e.preventDefault();
                        document.getElementById('nextToStep3')?.click();
                    }
                });
            });

            const step3Fields = document.querySelectorAll('#step3 input, #step3 select');
            step3Fields.forEach(field => {
                field.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.altKey) {
                        e.preventDefault();
                        document.getElementById('nextToStep4')?.click();
                    }
                });
            });

            const step4Fields = document.querySelectorAll('#step4 input');
            step4Fields.forEach(field => {
                field.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.altKey) {
                        e.preventDefault();
                        document.getElementById('nextToStep5')?.click();
                    }
                });
            });

            const step6Fields = document.querySelectorAll('#step6 input');
            step6Fields.forEach(field => {
                field.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.altKey) {
                        e.preventDefault();
                        if (document.getElementById('accept_terms')?.checked) {
                            const submitBtn = document.getElementById('submitBtn');
                            if (submitBtn && !submitBtn.disabled) {
                                setButtonLoading(submitBtn, 'Creating Account...');
                                document.getElementById('registrationForm').submit();
                            }
                        }
                    }
                });
            });

            // Password visibility toggles
            const togglePasswordBtn = document.getElementById('togglePassword');
            const passwordEyeIcon = document.getElementById('passwordEyeIcon');
            togglePasswordBtn?.addEventListener('click', function() {
                if (!passwordField) return;
                const type = passwordField.type === 'password' ? 'text' : 'password';
                passwordField.type = type;
                const passwordEyeIcon = document.getElementById('passwordEyeIcon');
                if (passwordEyeIcon) {
                    if (type === 'password') {
                        passwordEyeIcon.outerHTML = '<svg id="passwordEyeIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
                    } else {
                        passwordEyeIcon.outerHTML = '<svg id="passwordEyeIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>';
                    }
                }
            });

            const toggleConfirmPasswordBtn = document.getElementById('toggleConfirmPassword');
            const confirmPasswordEyeIcon = document.getElementById('confirmPasswordEyeIcon');
            toggleConfirmPasswordBtn?.addEventListener('click', function() {
                if (!confirmPasswordField) return;
                const type = confirmPasswordField.type === 'password' ? 'text' : 'password';
                confirmPasswordField.type = type;
                const confirmPasswordEyeIcon = document.getElementById('confirmPasswordEyeIcon');
                if (confirmPasswordEyeIcon) {
                    if (type === 'password') {
                        confirmPasswordEyeIcon.outerHTML = '<svg id="confirmPasswordEyeIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
                    } else {
                        confirmPasswordEyeIcon.outerHTML = '<svg id="confirmPasswordEyeIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>';
                    }
                }
            });

            confirmPasswordField?.addEventListener('input', validatePasswordMatch);
            passwordField?.addEventListener('input', validatePasswordMatch);

            const fieldsToWatch = [
                'first_name', 'middle_name', 'last_name', 'birth_date', 'gender', 'civil_status',
                'nationality', 'birth_place', 'occupation', 'government_id_type', 'government_id_number',
                'house_number', 'region', 'province', 'city_municipality', 'barangay', 'zip_code',
                'contact_number', 'email', 'emergency_contact_name', 'emergency_contact_number',
                'emergency_contact_relationship', 'username', 'password', 'confirm_password'
            ];

            fieldsToWatch.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (!field) return;
                
                // Mark field as touched when user interacts with it
                const markAsTouched = function() {
                    touchedFields.add(fieldId);
                };
                
                // Validate field - only show red border if touched and invalid
                const validateField = function() {
                    if (!touchedFields.has(fieldId)) {
                        // Don't show validation until field is touched
                        return;
                    }
                    
                    if (field.checkValidity() && (!field.hasAttribute('required') || field.value.trim() !== '')) {
                        field.classList.remove('border-red-500');
                    } else {
                        // Only show red border if field is required and empty/invalid
                        if (field.hasAttribute('required') && (!field.value || field.value.trim() === '')) {
                            field.classList.add('border-red-500');
                        } else if (!field.checkValidity()) {
                            field.classList.add('border-red-500');
                        } else {
                            field.classList.remove('border-red-500');
                        }
                    }
                };
                
                // Mark as touched on first interaction
                field.addEventListener('focus', markAsTouched, { once: true });
                field.addEventListener('input', function() {
                    markAsTouched();
                    validateField();
                });
                field.addEventListener('change', function() {
                    markAsTouched();
                    validateField();
                });
                field.addEventListener('blur', function() {
                    markAsTouched();
                    validateField();
                });
            });

            // File upload
            const profilePictureInput = document.getElementById('profile_picture');
            const uploadBtn = document.getElementById('uploadBtn');
            const previewImage = document.getElementById('previewImage');
            const profilePreview = document.getElementById('profilePreview');

            uploadBtn.addEventListener('click', function() {
                profilePictureInput.click();
            });

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
                    reader.onload = function(evt) {
                        previewImage.src = evt.target.result;
                        previewImage.classList.remove('hidden');
                        profilePreview.classList.add('hidden');
                        capturedImageBlob = null; // Clear camera capture
                        
                        // Show retake button when file is uploaded
                        retakeBtn.classList.remove('hidden');
                        startCameraBtn.classList.add('hidden');
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Camera functionality
            const startCameraBtn = document.getElementById('startCameraBtn');
            const captureBtn = document.getElementById('captureBtn');
            const stopCameraBtn = document.getElementById('stopCameraBtn');
            const retakeBtn = document.getElementById('retakeBtn');
            const cameraPreview = document.getElementById('cameraPreview');
            const captureCanvas = document.getElementById('captureCanvas');

            startCameraBtn.addEventListener('click', async function() {
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ 
                        video: { 
                            facingMode: 'user',
                            width: { ideal: 640 },
                            height: { ideal: 480 }
                        } 
                    });
                    cameraPreview.srcObject = stream;
                    cameraPreview.classList.add('active');
                    cameraPreview.classList.remove('hidden');
                    profilePreview.classList.add('hidden');
                    previewImage.classList.add('hidden');
                    
                    // Ensure video loads and plays
                    cameraPreview.onloadedmetadata = function() {
                        cameraPreview.play().catch(err => {
                            console.error('Error playing video:', err);
                        });
                    };
                    
                    startCameraBtn.classList.add('hidden');
                    captureBtn.classList.remove('hidden');
                    stopCameraBtn.classList.remove('hidden');
                } catch (err) {
                    showErrorAlert('Camera Access', 'Unable to access camera. Please check your permissions.');
                    console.error('Camera error:', err);
                }
            });

            captureBtn.addEventListener('click', function() {
                const context = captureCanvas.getContext('2d');
                captureCanvas.width = cameraPreview.videoWidth;
                captureCanvas.height = cameraPreview.videoHeight;
                
                // Flip horizontally to get normal orientation (since preview is mirrored)
                context.translate(captureCanvas.width, 0);
                context.scale(-1, 1);
                context.drawImage(cameraPreview, 0, 0);
                
                captureCanvas.toBlob(function(blob) {
                    capturedImageBlob = blob;
                    const url = URL.createObjectURL(blob);
                    previewImage.src = url;
                    previewImage.classList.remove('hidden');
                    cameraPreview.classList.add('hidden');
                    cameraPreview.classList.remove('active');
                    
                    // Stop camera
                    if (stream) {
                        stream.getTracks().forEach(track => track.stop());
                        stream = null;
                    }
                    
                    startCameraBtn.classList.add('hidden');
                    captureBtn.classList.add('hidden');
                    stopCameraBtn.classList.add('hidden');
                    retakeBtn.classList.remove('hidden');
                    
                    // Create a File object from blob and set it to the input
                    const file = new File([blob], 'camera-capture.jpg', { type: 'image/jpeg' });
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    profilePictureInput.files = dataTransfer.files;
                }, 'image/jpeg', 0.9);
            });

            stopCameraBtn.addEventListener('click', function() {
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                    stream = null;
                }
                cameraPreview.classList.add('hidden');
                cameraPreview.classList.remove('active');
                profilePreview.classList.remove('hidden');
                
                startCameraBtn.classList.remove('hidden');
                captureBtn.classList.add('hidden');
                stopCameraBtn.classList.add('hidden');
                retakeBtn.classList.add('hidden');
            });

            // Retake functionality - automatically reopens camera
            retakeBtn.addEventListener('click', async function() {
                // Clear the captured image
                const currentSrc = previewImage.src;
                previewImage.classList.add('hidden');
                previewImage.src = '';
                // Revoke blob URL if it exists
                if (currentSrc && currentSrc.startsWith('blob:')) {
                    URL.revokeObjectURL(currentSrc);
                }
                profilePreview.classList.add('hidden');
                capturedImageBlob = null;
                
                // Clear file input
                profilePictureInput.value = '';
                
                // Stop camera if it's running
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                    stream = null;
                }
                cameraPreview.srcObject = null;
                
                // Hide retake button and show capture/stop buttons
                retakeBtn.classList.add('hidden');
                startCameraBtn.classList.add('hidden');
                
                // Automatically reopen camera
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ 
                        video: { 
                            facingMode: 'user',
                            width: { ideal: 640 },
                            height: { ideal: 480 }
                        } 
                    });
                    cameraPreview.srcObject = stream;
                    cameraPreview.classList.add('active');
                    cameraPreview.classList.remove('hidden');
                    
                    // Ensure video loads and plays
                    cameraPreview.onloadedmetadata = function() {
                        cameraPreview.play().catch(err => {
                            console.error('Error playing video:', err);
                        });
                    };
                    
                    captureBtn.classList.remove('hidden');
                    stopCameraBtn.classList.remove('hidden');
                } catch (err) {
                    showErrorAlert('Camera Access', 'Unable to access camera. Please check your permissions.');
                    console.error('Camera error:', err);
                    // If camera fails, show the start camera button again
                    startCameraBtn.classList.remove('hidden');
                    profilePreview.classList.remove('hidden');
                }
            });

            // Form submission
            const registrationForm = document.getElementById('registrationForm');
            const submitBtn = document.getElementById('submitBtn');
            
            if (registrationForm && submitBtn) {
                registrationForm.addEventListener('submit', function(e) {
                    if (!document.getElementById('accept_terms').checked) {
                        e.preventDefault();
                        showErrorAlert('Terms Required', 'Please accept the Terms and Conditions and Privacy Policy to continue.');
                        return false;
                    }
                    if (contactNumberField) {
                        contactNumberField.value = sanitizePhoneValue(contactNumberField.value);
                    }
                    if (emergencyNumberField) {
                        emergencyNumberField.value = sanitizePhoneValue(emergencyNumberField.value);
                    }
                    // Show loading state
                    setButtonLoading(submitBtn, 'Creating Account...');
                });
            }

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