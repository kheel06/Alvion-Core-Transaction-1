<?php
require_once '../../config/config.php';
require_once 'helpers.php';
requireAuth();
checkRole(['admin', 'receptionist', 'doctor', 'nurse']);

$page_title = "Patient Registration";
$active_tab = $_GET['tab'] ?? 'register';

// Ensure columns exist
ensureUsersProfilePictureColumn($db);
ensureUsersPatientIdColumn($db);

$upload_dir = __DIR__ . '/../../assets/uploads/profile_pictures/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Handle Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password_input = sanitizeInput($_POST['password'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $middle_name = isset($_POST['middle_name']) ? sanitizeInput($_POST['middle_name']) : null;
    $suffix = isset($_POST['suffix']) && !empty($_POST['suffix']) ? sanitizeInput($_POST['suffix']) : null;
    $birth_date = isset($_POST['birth_date']) ? sanitizeInput($_POST['birth_date']) : null;
    $gender = isset($_POST['gender']) ? sanitizeInput($_POST['gender']) : null;
    $civil_status = isset($_POST['civil_status']) ? sanitizeInput($_POST['civil_status']) : null;
    $nationality = isset($_POST['nationality']) ? sanitizeInput($_POST['nationality']) : 'Filipino';
    $birth_place = isset($_POST['birth_place']) ? sanitizeInput($_POST['birth_place']) : null;
    $occupation = isset($_POST['occupation']) ? sanitizeInput($_POST['occupation']) : null;
    $government_id_type = isset($_POST['government_id_type']) ? sanitizeInput($_POST['government_id_type']) : null;
    $government_id_number = isset($_POST['government_id_number']) ? sanitizeInput($_POST['government_id_number']) : null;
    $house_number = isset($_POST['house_number']) ? sanitizeInput($_POST['house_number']) : null;
    $region = isset($_POST['region']) ? sanitizeInput($_POST['region']) : null;
    $province = isset($_POST['province']) ? sanitizeInput($_POST['province']) : null;
    $city_municipality = isset($_POST['city_municipality']) ? sanitizeInput($_POST['city_municipality']) : null;
    $barangay = isset($_POST['barangay']) ? sanitizeInput($_POST['barangay']) : null;
    $zip_code = isset($_POST['zip_code']) ? sanitizeInput($_POST['zip_code']) : null;
    $contact_number = isset($_POST['contact_number']) ? sanitizeInput($_POST['contact_number']) : null;
    $emergency_contact_name = isset($_POST['emergency_contact_name']) ? sanitizeInput($_POST['emergency_contact_name']) : null;
    $emergency_contact_number = isset($_POST['emergency_contact_number']) ? sanitizeInput($_POST['emergency_contact_number']) : null;
    $emergency_contact_relationship = isset($_POST['emergency_contact_relationship']) ? sanitizeInput($_POST['emergency_contact_relationship']) : null;

    if (empty($username) || empty($password_input) || empty($email) || empty($first_name) || empty($last_name)) {
        $_SESSION['error'] = "Please fill in all required fields.";
    } else {
        $password = password_hash($password_input, PASSWORD_DEFAULT);
        
        try {
            $check_query = "SELECT * FROM users WHERE username = :username OR email = :email";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();

            if ($check_stmt->rowCount() > 0) {
                $_SESSION['error'] = "Username or email already exists.";
            } else {
                $db->beginTransaction();
                
                try {
                    $patient_id = generatePatientId($db);
                    $age = null;
                    if ($birth_date) {
                        $birth = new DateTime($birth_date);
                        $today = new DateTime();
                        $age = $today->diff($birth)->y;
                    }
                    
                    $contact_number_clean = $contact_number ? str_replace(' ', '', $contact_number) : null;
                    $emergency_contact_number_clean = $emergency_contact_number ? str_replace(' ', '', $emergency_contact_number) : null;
                    
                    $occupation_other_value = null;
                    if ($occupation === 'Other' && isset($_POST['occupation_other']) && !empty($_POST['occupation_other'])) {
                        $occupation_other_value = sanitizeInput($_POST['occupation_other']);
                        $occupation = null;
                    }
                    
                    $patient_role_id = getPatientRoleId($db);
                    
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
                        throw new Exception("Failed to create patient account.");
                    }
                    
                    $user_id = $db->lastInsertId();
                    
                    // Log registration
                    logRegistrationActivity($db, $user_id, 'registered', 'Patient registered by admin', $_SESSION['user_id']);
                    
                    // Handle identity verification entry
                    if ($government_id_type && $government_id_number) {
                        try {
                            $id_query = "INSERT INTO identity_verification (patient_id, id_type, id_number, verified, verified_by, verified_at) 
                                       VALUES (:patient_id, :id_type, :id_number, 0, NULL, NULL)";
                            $id_stmt = $db->prepare($id_query);
                            $id_stmt->bindParam(':patient_id', $user_id, PDO::PARAM_INT);
                            $id_stmt->bindParam(':id_type', $government_id_type);
                            $id_stmt->bindParam(':id_number', $government_id_number);
                            $id_stmt->execute();
                        } catch (PDOException $e) {
                            error_log("Failed to create identity verification entry: " . $e->getMessage());
                        }
                    }
                    
                    $db->commit();
                    $_SESSION['success'] = "Patient registered successfully! Patient ID: " . $patient_id;
                    header("Location: patient_registration.php?tab=logs");
                    exit();
                } catch (Exception $e) {
                    $db->rollBack();
                    $_SESSION['error'] = "Registration error: " . $e->getMessage();
                }
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Registration error: " . $e->getMessage();
        }
    }
}

// Get registration logs
try {
    $logs_query = "SELECT rl.*, u.first_name, u.last_name, u.patient_id, 
                   u2.first_name as performed_by_fname, u2.last_name as performed_by_lname
                   FROM registration_logs rl
                   LEFT JOIN users u ON rl.patient_id = u.id
                   LEFT JOIN users u2 ON rl.performed_by = u2.id
                   ORDER BY rl.created_at DESC
                   LIMIT 100";
    $logs_stmt = $db->prepare($logs_query);
    $logs_stmt->execute();
    $registration_logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $registration_logs = [];
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Patient Registration</h1>
    <p class="text-gray-600 dark:text-gray-400">Register new patients, update information, and verify identity & insurance</p>
</div>

<!-- Tabs -->
<div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="flex -mb-px">
            <a href="?tab=register" class="<?php echo $active_tab === 'register' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> py-4 px-6 border-b-2 font-medium text-sm">
                Register New Patient
            </a>
            <a href="?tab=logs" class="<?php echo $active_tab === 'logs' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> py-4 px-6 border-b-2 font-medium text-sm">
                View Registration Logs
            </a>
        </nav>
    </div>
</div>

<!-- Tab Content -->
<?php if ($active_tab === 'register'): ?>
    <!-- Register New Patient Tab -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <div class="text-center mb-6">
                <h2 class="text-2xl font-semibold text-[#008080]">Patient Registration</h2>
                <p class="mt-2 text-sm text-gray-500">Complete the steps to register a new patient</p>
            </div>

            <!-- Step Indicators -->
            <div class="mt-6 mb-6 relative">
                <!-- Connecting Lines (behind circles) -->
                <div class="absolute top-5 left-0 right-0 h-1 flex z-0">
                    <div id="step1-line" class="flex-1 bg-[#008080] transition-all duration-300 -mx-5"></div>
                    <div id="step2-line" class="flex-1 bg-gray-200 transition-all duration-300 -mx-5"></div>
                    <div id="step3-line" class="flex-1 bg-gray-200 transition-all duration-300 -mx-5"></div>
                    <div id="step4-line" class="flex-1 bg-gray-200 transition-all duration-300 -mx-5"></div>
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
                        <p id="step4-label" class="text-xs font-medium text-center text-gray-500 whitespace-nowrap">Account Credentials</p>
                    </div>
                </div>
            </div>

            <form id="registrationForm" class="mt-4" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="register">

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

                <!-- Step 3: Contact Information -->
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

                <!-- Step 4: Account Credentials -->
                <div id="step4" class="step-content">
                    <div class="space-y-6">
                        <div class="text-center">
                            <h3 class="text-xl font-semibold text-gray-800 mb-2">Create Account Credentials</h3>
                            <p class="text-sm text-gray-500">Set up username and password for the patient</p>
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
                        </div>
                    </div>

                    <div class="mt-6 flex gap-3">
                        <button type="button" id="backToStep3" 
                                class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 rounded-full transition">
                            Previous
                        </button>
                        <button type="submit" id="submitBtn" 
                                class="flex-1 bg-[#008080] hover:bg-[#0f766e] text-white font-semibold py-3 rounded-full shadow-lg transition">
                            Register Patient
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($active_tab === 'logs'): ?>
    <!-- View Registration Logs Tab -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Registration Logs</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Patient</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Performed By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php if (empty($registration_logs)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">No registration logs found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($registration_logs as $log): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?>
                                        <br><span class="text-xs text-gray-500"><?php echo htmlspecialchars($log['patient_id'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                            <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo $log['performed_by_fname'] ? htmlspecialchars($log['performed_by_fname'] . ' ' . $log['performed_by_lname']) : 'System'; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($log['notes'] ?? '-'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Rest of the JavaScript code remains the same -->
<style>
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
    .relative > span[class*="absolute"] {
        z-index: 10;
    }
    select.login-input {
        position: relative;
        z-index: 1;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentStep = 1;
    const totalSteps = 4;
    const PSGC_API_BASE = 'https://psgc.gitlab.io/api';

    // Track touched fields to only show validation after user interaction
    const touchedFields = new Set();
    
    const firstNameField = document.getElementById('first_name');
    const lastNameField = document.getElementById('last_name');
    const birthDateInput = document.getElementById('birth_date');
    const ageInput = document.getElementById('age');
    const passwordField = document.getElementById('password');
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
            const line = document.getElementById(`step${i}-line`);
            
            if (i < step) {
                indicator?.classList.remove('active', 'bg-white', 'text-gray-600', 'border-gray-300');
                indicator?.classList.add('completed', 'bg-[#008080]', 'text-white', 'border-[#008080]');
                if (indicator) indicator.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
                if (stepLabel) {
                    stepLabel.classList.remove('text-gray-500');
                    stepLabel.classList.add('text-[#008080]');
                }
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

        return isValid;
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

    // Password visibility toggle
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

    // Navigation buttons
    const buttonActions = [
        { id: 'nextToStep2', action: () => { if (validateStep('step1')) { currentStep = 2; showStep(2); } } },
        { id: 'backToStep1', action: () => { currentStep = 1; showStep(1); } },
        { id: 'nextToStep3', action: () => { if (validateStep('step2')) { currentStep = 3; showStep(3); } } },
        { id: 'backToStep2', action: () => { currentStep = 2; showStep(2); } },
        { id: 'nextToStep4', action: () => { if (validateStep('step3')) { currentStep = 4; showStep(4); } } },
        { id: 'backToStep3', action: () => { currentStep = 3; showStep(3); } }
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

    // Field validation on interaction
    const fieldsToWatch = [
        'first_name', 'middle_name', 'last_name', 'birth_date', 'gender', 'civil_status',
        'nationality', 'birth_place', 'occupation', 'government_id_type', 'government_id_number',
        'house_number', 'region', 'province', 'city_municipality', 'barangay', 'zip_code',
        'contact_number', 'email', 'emergency_contact_name', 'emergency_contact_number',
        'emergency_contact_relationship', 'username', 'password'
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


<?php include '../../includes/footer.php'; ?>