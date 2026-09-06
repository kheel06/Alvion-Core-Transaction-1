<?php
require_once '../../config/config.php';
require_once 'helpers.php';
requireAuth();
checkRole(['admin', 'receptionist', 'doctor', 'nurse', 'billing_staff']);

$page_title = "View Patients";
$search_term = $_GET['search'] ?? '';
$initial_filter = $_GET['initial'] ?? ''; // Filter by first name initial (A-Z)
$patient_type_filter = $_GET['patient_type'] ?? ''; // outpatient, inpatient
$admission_status_filter = $_GET['admission_status'] ?? ''; // active, admitted, discharged
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'list'; // list, profile, ehr, history
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'info'; // info, update, verify

// Get patient role ID
$patient_role_id = getPatientRoleId($db);

// Search patients
$patients = [];
if (!empty($search_term) || !empty($initial_filter) || !empty($patient_type_filter) || !empty($admission_status_filter) || $view_mode === 'list') {
    $patients = searchPatients($db, $search_term, $initial_filter, 50, 0, $patient_type_filter, $admission_status_filter);
}

// Get specific patient if ID provided
$patient = null;
if ($patient_id > 0) {
    $patient = getPatientFromUsers($db, $patient_id);
    if (!$patient) {
        $_SESSION['error'] = "Patient not found.";
        header("Location: view_patients.php");
        exit();
    }
}

// Handle Export
if (isset($_GET['export']) && $patient_id > 0) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="patient_' . $patient_id . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Field', 'Value']);
    if ($patient) {
        foreach ($patient as $key => $value) {
            if ($key !== 'password') {
                fputcsv($output, [ucfirst(str_replace('_', ' ', $key)), $value]);
            }
        }
    }
    fclose($output);
    exit();
}

// Get patient medical history (read-only)
$medical_history = [];
if ($patient_id > 0) {
    try {
        // Get appointments
        $appointments_query = "SELECT a.*, u.first_name as doctor_fname, u.last_name as doctor_lname 
                              FROM appointments a
                              LEFT JOIN users u ON a.doctor_id = u.id
                              WHERE a.patient_id = :patient_id
                              ORDER BY a.appointment_date DESC";
        $appointments_stmt = $db->prepare($appointments_query);
        $appointments_stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $appointments_stmt->execute();
        $medical_history['appointments'] = $appointments_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $medical_history['appointments'] = [];
    }
    
    try {
        // Get teleconsultations
        $teleconsult_query = "SELECT t.*, u.first_name as doctor_fname, u.last_name as doctor_lname 
                              FROM teleconsultations t
                              LEFT JOIN users u ON t.doctor_id = u.id
                              WHERE t.patient_id = :patient_id
                              ORDER BY t.consultation_date DESC";
        $teleconsult_stmt = $db->prepare($teleconsult_query);
        $teleconsult_stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $teleconsult_stmt->execute();
        $medical_history['teleconsultations'] = $teleconsult_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $medical_history['teleconsultations'] = [];
    }
}

// Get patient insurance
$patient_insurance = [];
if ($patient_id > 0) {
    try {
        $insurance_query = "SELECT pi.*, ip.provider_name, ip.provider_type 
                           FROM patient_insurance pi
                           LEFT JOIN insurance_providers ip ON pi.provider_id = ip.id
                           WHERE pi.patient_id = :patient_id
                           ORDER BY pi.status DESC";
        $insurance_stmt = $db->prepare($insurance_query);
        $insurance_stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $insurance_stmt->execute();
        $patient_insurance = $insurance_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $patient_insurance = [];
    }
}

// Handle Update Patient (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_patient') {
    // Check if user is admin
    $user_role = strtolower(trim($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? $_SESSION['role'] ?? ''));
    if ($user_role !== 'admin') {
        $_SESSION['error'] = "Unauthorized access.";
        header("Location: view_patients.php");
        exit();
    }
    $update_patient_id = (int)$_POST['patient_id'];
    
    if ($update_patient_id > 0) {
        try {
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
            $blood_type = isset($_POST['blood_type']) ? sanitizeInput($_POST['blood_type']) : null;
            $known_allergies = isset($_POST['known_allergies']) ? sanitizeInput($_POST['known_allergies']) : null;
            $pre_existing_conditions = isset($_POST['pre_existing_conditions']) ? sanitizeInput($_POST['pre_existing_conditions']) : null;
            $current_medications = isset($_POST['current_medications']) ? sanitizeInput($_POST['current_medications']) : null;
            
            // Calculate age
            $age = null;
            if ($birth_date) {
                $birth = new DateTime($birth_date);
                $today = new DateTime();
                $age = $today->diff($birth)->y;
            }
            
            // Clean phone numbers
            $contact_number_clean = $contact_number ? str_replace(' ', '', $contact_number) : null;
            $emergency_contact_number_clean = $emergency_contact_number ? str_replace(' ', '', $emergency_contact_number) : null;
            
            // Check if patient exists in patients table
            $check_stmt = $db->prepare("SELECT COUNT(*) FROM patients WHERE id = :id");
            $check_stmt->bindParam(':id', $update_patient_id, PDO::PARAM_INT);
            $check_stmt->execute();
            $in_patients_table = $check_stmt->fetchColumn() > 0;

            if ($in_patients_table) {
                // Update patients table
                $query = "UPDATE patients SET
                            first_name = :first_name,
                            last_name = :last_name,
                            middle_name = :middle_name,
                            birth_date = :birth_date,
                            gender = :gender,
                            civil_status = :civil_status,
                            nationality = :nationality,
                            house_no_street = :house_number,
                            barangay = :barangay,
                            zip_code = :zip_code,
                            contact_number = :contact_number,
                            blood_type = :blood_type,
                            known_allergies = :known_allergies,
                            pre_existing_conditions = :pre_existing_conditions,
                            occupation = :occupation,
                            updated_at = NOW()
                          WHERE id = :patient_id";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':first_name', $first_name);
                $stmt->bindParam(':last_name', $last_name);
                $stmt->bindParam(':middle_name', $middle_name);
                $stmt->bindParam(':birth_date', $birth_date);
                $stmt->bindParam(':gender', $gender);
                $stmt->bindParam(':civil_status', $civil_status);
                $stmt->bindParam(':nationality', $nationality);
                $stmt->bindParam(':house_number', $house_number);
                $stmt->bindParam(':barangay', $barangay);
                $stmt->bindParam(':zip_code', $zip_code);
                $stmt->bindParam(':contact_number', $contact_number_clean);
                $stmt->bindParam(':blood_type', $blood_type);
                $stmt->bindParam(':known_allergies', $known_allergies);
                $stmt->bindParam(':pre_existing_conditions', $pre_existing_conditions);
                $stmt->bindParam(':occupation', $occupation);
                $stmt->bindParam(':patient_id', $update_patient_id, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                // Fallback: Update users table
                $query = "UPDATE users SET
                            first_name = :first_name,
                            last_name = :last_name,
                            middle_name = :middle_name,
                            birth_date = :birth_date,
                            gender = :gender,
                            civil_status = :civil_status,
                            nationality = :nationality,
                            contact_number = :contact_number,
                            blood_type = :blood_type,
                            known_allergies = :known_allergies,
                            pre_existing_conditions = :pre_existing_conditions,
                            updated_at = NOW()
                          WHERE id = :patient_id";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':first_name', $first_name);
                $stmt->bindParam(':last_name', $last_name);
                $stmt->bindParam(':middle_name', $middle_name);
                $stmt->bindParam(':birth_date', $birth_date);
                $stmt->bindParam(':gender', $gender);
                $stmt->bindParam(':civil_status', $civil_status);
                $stmt->bindParam(':nationality', $nationality);
                $stmt->bindParam(':contact_number', $contact_number_clean);
                $stmt->bindParam(':blood_type', $blood_type);
                $stmt->bindParam(':known_allergies', $known_allergies);
                $stmt->bindParam(':pre_existing_conditions', $pre_existing_conditions);
                $stmt->bindParam(':patient_id', $update_patient_id, PDO::PARAM_INT);
                $stmt->execute();
            }
            
            // Update identity verification if ID changed
            if ($government_id_type && $government_id_number) {
                try {
                    $check_id = $db->prepare("SELECT id FROM identity_verification WHERE patient_id = :patient_id");
                    $check_id->bindParam(':patient_id', $update_patient_id, PDO::PARAM_INT);
                    $check_id->execute();
                    
                    if ($check_id->rowCount() > 0) {
                        $update_id = $db->prepare("UPDATE identity_verification SET id_type = :id_type, id_number = :id_number WHERE patient_id = :patient_id");
                        $update_id->bindParam(':id_type', $government_id_type);
                        $update_id->bindParam(':id_number', $government_id_number);
                        $update_id->bindParam(':patient_id', $update_patient_id, PDO::PARAM_INT);
                        $update_id->execute();
                    } else {
                        $insert_id = $db->prepare("INSERT INTO identity_verification (patient_id, id_type, id_number, verified) VALUES (:patient_id, :id_type, :id_number, 0)");
                        $insert_id->bindParam(':patient_id', $update_patient_id, PDO::PARAM_INT);
                        $insert_id->bindParam(':id_type', $government_id_type);
                        $insert_id->bindParam(':id_number', $government_id_number);
                        $insert_id->execute();
                    }
                } catch (PDOException $e) {
                    error_log("Failed to update identity verification: " . $e->getMessage());
                }
            }
            
            $_SESSION['success'] = "Patient information updated successfully.";
            header("Location: ?id=" . $update_patient_id . "&view=profile&tab=update");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating patient: " . $e->getMessage();
        }
    }
}

// Handle Verify Identity (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_identity') {
    // Check if user is admin
    $user_role = strtolower(trim($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? $_SESSION['role'] ?? ''));
    if ($user_role !== 'admin') {
        $_SESSION['error'] = "Unauthorized access.";
        header("Location: view_patients.php");
        exit();
    }
    $verify_patient_id = (int)$_POST['patient_id'];
    try {
        $query = "UPDATE identity_verification SET verified = 1, verified_by = :verified_by, verified_at = NOW() 
                  WHERE patient_id = :patient_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':patient_id', $verify_patient_id, PDO::PARAM_INT);
        $stmt->bindParam(':verified_by', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        
        $_SESSION['success'] = "Identity verified successfully.";
        header("Location: ?id=" . $verify_patient_id . "&view=profile&tab=verify");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error verifying identity: " . $e->getMessage();
    }
}

// Handle Verify Insurance (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_insurance') {
    // Check if user is admin
    $user_role = strtolower(trim($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? $_SESSION['role'] ?? ''));
    if ($user_role !== 'admin') {
        $_SESSION['error'] = "Unauthorized access.";
        header("Location: view_patients.php");
        exit();
    }
    $verify_patient_id = (int)$_POST['patient_id'];
    $insurance_id = (int)$_POST['insurance_id'];
    try {
        $query = "UPDATE patient_insurance SET status = 'active', verified_by = :verified_by, verified_at = NOW() 
                  WHERE id = :insurance_id AND patient_id = :patient_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);
        $stmt->bindParam(':patient_id', $verify_patient_id, PDO::PARAM_INT);
        $stmt->bindParam(':verified_by', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        
        $_SESSION['success'] = "Insurance verified successfully.";
        header("Location: ?id=" . $verify_patient_id . "&view=profile&tab=verify");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error verifying insurance: " . $e->getMessage();
    }
}

// Get identity verification data
$identity_verification = null;
if ($patient_id > 0) {
    try {
        $id_verify_query = "SELECT iv.*, u.first_name as verified_by_fname, u.last_name as verified_by_lname 
                           FROM identity_verification iv
                           LEFT JOIN users u ON iv.verified_by = u.id
                           WHERE iv.patient_id = :patient_id
                           LIMIT 1";
        $id_verify_stmt = $db->prepare($id_verify_query);
        $id_verify_stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $id_verify_stmt->execute();
        $identity_verification = $id_verify_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $identity_verification = null;
    }
}

// Get EHR data
$ehr_data = [];
if ($patient_id > 0 && $view_mode === 'ehr') {
    try {
        // Get latest vital signs
        $vitals_query = "SELECT vs.*, u.first_name as recorded_fname, u.last_name as recorded_lname 
                        FROM ehr_vital_signs vs
                        LEFT JOIN users u ON vs.recorded_by = u.id
                        WHERE vs.patient_id = :patient_id
                        ORDER BY vs.recorded_at DESC LIMIT 10";
        $vitals_stmt = $db->prepare($vitals_query);
        $vitals_stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $vitals_stmt->execute();
        $ehr_data['vital_signs'] = $vitals_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $ehr_data['vital_signs'] = [];
    }
    
    try {
        // Get diagnoses
        $diagnoses_query = "SELECT d.*, u.first_name as doctor_fname, u.last_name as doctor_lname 
                           FROM ehr_diagnoses d
                           LEFT JOIN users u ON d.diagnosed_by = u.id
                           WHERE d.patient_id = :patient_id
                           ORDER BY d.diagnosis_date DESC";
        $diagnoses_stmt = $db->prepare($diagnoses_query);
        $diagnoses_stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $diagnoses_stmt->execute();
        $ehr_data['diagnoses'] = $diagnoses_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $ehr_data['diagnoses'] = [];
    }
    
    try {
        // Get medications
        $medications_query = "SELECT m.*, u.first_name as doctor_fname, u.last_name as doctor_lname 
                             FROM ehr_medications m
                             LEFT JOIN users u ON m.prescribed_by = u.id
                             WHERE m.patient_id = :patient_id
                             ORDER BY m.prescription_date DESC";
        $medications_stmt = $db->prepare($medications_query);
        $medications_stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $medications_stmt->execute();
        $ehr_data['medications'] = $medications_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $ehr_data['medications'] = [];
    }
    
    try {
        // Get lab results
        $lab_query = "SELECT l.*, u1.first_name as ordered_fname, u1.last_name as ordered_lname,
                             u2.first_name as processed_fname, u2.last_name as processed_lname
                      FROM ehr_lab_results l
                      LEFT JOIN users u1 ON l.ordered_by = u1.id
                      LEFT JOIN users u2 ON l.processed_by = u2.id
                      WHERE l.patient_id = :patient_id
                      ORDER BY l.order_date DESC LIMIT 20";
        $lab_stmt = $db->prepare($lab_query);
        $lab_stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $lab_stmt->execute();
        $ehr_data['lab_results'] = $lab_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $ehr_data['lab_results'] = [];
    }
    
    try {
        // Get procedures
        $procedures_query = "SELECT p.*, u.first_name as doctor_fname, u.last_name as doctor_lname 
                            FROM ehr_procedures p
                            LEFT JOIN users u ON p.performed_by = u.id
                            WHERE p.patient_id = :patient_id
                            ORDER BY p.procedure_date DESC";
        $procedures_stmt = $db->prepare($procedures_query);
        $procedures_stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $procedures_stmt->execute();
        $ehr_data['procedures'] = $procedures_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $ehr_data['procedures'] = [];
    }
    
    try {
        // Get allergies
        $allergies_query = "SELECT * FROM ehr_allergies 
                           WHERE patient_id = :patient_id
                           ORDER BY severity DESC, allergen ASC";
        $allergies_stmt = $db->prepare($allergies_query);
        $allergies_stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $allergies_stmt->execute();
        $ehr_data['allergies'] = $allergies_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $ehr_data['allergies'] = [];
    }
    
    try {
        // Get immunizations
        $immunizations_query = "SELECT i.*, u.first_name as provider_fname, u.last_name as provider_lname 
                               FROM ehr_immunizations i
                               LEFT JOIN users u ON i.administered_by = u.id
                               WHERE i.patient_id = :patient_id
                               ORDER BY i.administration_date DESC";
        $immunizations_stmt = $db->prepare($immunizations_query);
        $immunizations_stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $immunizations_stmt->execute();
        $ehr_data['immunizations'] = $immunizations_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $ehr_data['immunizations'] = [];
    }
    
    try {
        // Get clinical notes
        $notes_query = "SELECT n.*, u.first_name as author_fname, u.last_name as author_lname 
                       FROM ehr_clinical_notes n
                       LEFT JOIN users u ON n.note_author = u.id
                       WHERE n.patient_id = :patient_id
                       ORDER BY n.note_date DESC LIMIT 20";
        $notes_stmt = $db->prepare($notes_query);
        $notes_stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $notes_stmt->execute();
        $ehr_data['clinical_notes'] = $notes_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $ehr_data['clinical_notes'] = [];
    }
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">View Patients</h1>
    <p class="text-gray-600 dark:text-gray-400">Search and view patient information, EHR summary, and medical history</p>
</div>

<?php if ($view_mode === 'list' || !$patient_id): ?>
    <!-- Patient List / Search -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
        <div class="px-4 py-5 sm:p-6">
            <!-- Patient Category Tabs -->
            <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
                <div class="flex flex-wrap gap-2 pb-4">
                    <!-- Patient Type Filters -->
                    <div class="flex items-center gap-2 mr-6">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Type:</span>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['patient_type' => ''])); ?>" 
                           class="px-3 py-1.5 text-sm font-medium rounded-full transition-colors <?php echo empty($patient_type_filter) ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'; ?>">
                            All
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['patient_type' => 'outpatient'])); ?>" 
                           class="px-3 py-1.5 text-sm font-medium rounded-full transition-colors flex items-center gap-1.5 <?php echo $patient_type_filter === 'outpatient' ? 'bg-blue-600 text-white' : 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/30'; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            Outpatient
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['patient_type' => 'inpatient'])); ?>" 
                           class="px-3 py-1.5 text-sm font-medium rounded-full transition-colors flex items-center gap-1.5 <?php echo $patient_type_filter === 'inpatient' ? 'bg-purple-600 text-white' : 'bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-300 hover:bg-purple-100 dark:hover:bg-purple-900/30'; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4v16"></path><path d="M2 8h18a2 2 0 0 1 2 2v10"></path><path d="M2 17h20"></path><path d="M6 8v9"></path></svg>
                            Inpatient
                        </a>
                    </div>
                    
                    <!-- Admission Status Filters -->
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Status:</span>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['admission_status' => ''])); ?>" 
                           class="px-3 py-1.5 text-sm font-medium rounded-full transition-colors <?php echo empty($admission_status_filter) ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'; ?>">
                            All
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['admission_status' => 'active'])); ?>" 
                           class="px-3 py-1.5 text-sm font-medium rounded-full transition-colors flex items-center gap-1.5 <?php echo $admission_status_filter === 'active' ? 'bg-green-600 text-white' : 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300 hover:bg-green-100 dark:hover:bg-green-900/30'; ?>">
                            <span class="w-2 h-2 rounded-full bg-current"></span>
                            Active
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['admission_status' => 'admitted'])); ?>" 
                           class="px-3 py-1.5 text-sm font-medium rounded-full transition-colors flex items-center gap-1.5 <?php echo $admission_status_filter === 'admitted' ? 'bg-orange-600 text-white' : 'bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-300 hover:bg-orange-100 dark:hover:bg-orange-900/30'; ?>">
                            <span class="w-2 h-2 rounded-full bg-current"></span>
                            Admitted
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['admission_status' => 'discharged'])); ?>" 
                           class="px-3 py-1.5 text-sm font-medium rounded-full transition-colors flex items-center gap-1.5 <?php echo $admission_status_filter === 'discharged' ? 'bg-gray-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600'; ?>">
                            <span class="w-2 h-2 rounded-full bg-current"></span>
                            Discharged
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Search and Filter Controls -->
            <div class="mb-6 flex items-center justify-end gap-0">
                <!-- Search Icon (transforms to search bar) -->
                <div class="relative">
                    <div id="searchIconContainer" class="flex items-center <?php echo !empty($search_term) ? 'hidden' : ''; ?>">
                        <button type="button" onclick="toggleSearchBar()" 
                                class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-l-md transition-colors border border-r-0 border-gray-300 dark:border-gray-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                        </button>
                    </div>
                    <div id="searchBarContainer" class="<?php echo !empty($search_term) ? '' : 'hidden'; ?>">
                        <div class="relative">
                            <input type="text" 
                                   id="searchInput" 
                                   value="<?php echo htmlspecialchars($search_term); ?>" 
                                   placeholder="Search by full name..."
                                   autocomplete="off"
                                   class="w-64 border border-gray-300 dark:border-gray-600 rounded-l-md shadow-sm py-2 pl-10 pr-4 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <path d="m21 21-4.35-4.35"></path>
                                </svg>
                            </div>
                            <button type="button" onclick="closeSearchBar()" 
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Filter by Initial (A-Z) -->
                <div class="relative">
                    <button type="button" onclick="toggleFilterMenu()" 
                            class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-r-md transition-colors border border-l-0 border-gray-300 dark:border-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                        </svg>
                    </button>
                    <div id="filterMenu" class="hidden absolute right-0 mt-2 w-64 bg-white dark:bg-gray-800 rounded-md shadow-lg z-10 border border-gray-200 dark:border-gray-700 p-4">
                        <div class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Filter by First Name Initial</div>
                        <div class="grid grid-cols-6 gap-2">
                            <a href="?initial=" 
                               class="px-2 py-1 text-center text-sm rounded <?php echo empty($initial_filter) ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'; ?>">
                                All
                            </a>
                            <?php foreach (range('A', 'Z') as $letter): ?>
                                <a href="?initial=<?php echo $letter; ?>" 
                                   class="px-2 py-1 text-center text-sm rounded <?php echo $initial_filter === $letter ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'; ?>">
                                    <?php echo $letter; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($search_term) || !empty($initial_filter)): ?>
                    <a href="view_patients.php" 
                       class="ml-2 px-3 py-2 text-sm bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                        Clear
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Patient Cards -->
            <div class="mb-4">
                <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo count($patients); ?> patient(s) found</p>
            </div>
            
            <?php if (empty($patients)): ?>
                <div class="text-center py-12">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 dark:text-gray-600 mx-auto mb-4">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No patients found</h3>
                    <p class="text-gray-500 dark:text-gray-400">Try adjusting your search criteria</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php foreach ($patients as $p): ?>
                        <?php
                        // Get profile picture path
                        $profile_picture = $p['profile_picture'] ?? null;
                        $profile_path = null;
                        $cache_buster = '';
                        
                        if (!empty($profile_picture)) {
                            // Check if it's a relative path or absolute
                            if (strpos($profile_picture, 'http') === 0) {
                                $profile_path = $profile_picture;
                            } else {
                                // Build full path
                                $full_path = __DIR__ . '/../../' . ltrim($profile_picture, '/');
                                if (file_exists($full_path)) {
                                    $profile_path = BASE_URL . '/' . ltrim($profile_picture, '/');
                                    // Add cache buster using file modification time for real-time updates
                                    $cache_buster = '?t=' . filemtime($full_path);
                                }
                            }
                        }
                        
                        // Generate initials as fallback
                        $first_initial = strtoupper(substr($p['first_name'] ?? '', 0, 1));
                        $last_initial = strtoupper(substr($p['last_name'] ?? '', 0, 1));
                        $initials = $first_initial . $last_initial;
                        
                        // Build full name
                        $full_name = trim(($p['first_name'] ?? '') . ' ' . ($p['middle_name'] ?? '') . ' ' . ($p['last_name'] ?? '') . ' ' . ($p['suffix'] ?? ''));
                        
                        // Format contact number for display
                        $contact_display = $p['contact_number'] ?? '-';
                        if ($contact_display !== '-' && strlen($contact_display) >= 11) {
                            $contact_display = substr($contact_display, 0, 4) . ' ' . substr($contact_display, 4, 3) . ' ' . substr($contact_display, 7);
                        }
                        ?>
                        
                        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl hover:shadow-2xl transition-all duration-300 border-2 border-gray-200 dark:border-gray-700 overflow-visible group relative backdrop-blur-sm bg-opacity-95 dark:bg-opacity-95 hover:border-primary-300 dark:hover:border-primary-600 hover:-translate-y-1 hover:ring-2 hover:ring-primary-200 dark:hover:ring-primary-800 hover:ring-opacity-50">
                            <!-- Card Header with Medical Theme -->
                            <div class="relative h-3 bg-gradient-to-r from-blue-500 via-blue-400 to-teal-500 rounded-t-2xl shadow-inner">
                                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent animate-pulse"></div>
                            </div>
                            
                            <!-- Card Body -->
                            <div class="p-6 relative bg-gradient-to-br from-white via-white to-gray-50 dark:from-gray-800 dark:via-gray-800 dark:to-gray-900 rounded-b-2xl">
                                <!-- Subtle Pattern Overlay -->
                                <div class="absolute inset-0 rounded-b-2xl opacity-5 dark:opacity-10 pointer-events-none" style="background-image: radial-gradient(circle at 2px 2px, currentColor 1px, transparent 0); background-size: 24px 24px;"></div>
                                <!-- Ellipsis Menu Button (Admin Only) - Upper Right of Card Body -->
                                <?php 
                                // Get current user role - check multiple possible session keys
                                $current_user_role = '';
                                if (isset($_SESSION['role_name'])) {
                                    $current_user_role = strtolower(trim($_SESSION['role_name']));
                                } elseif (isset($_SESSION['user_role'])) {
                                    $current_user_role = strtolower(trim($_SESSION['user_role']));
                                } elseif (isset($_SESSION['role'])) {
                                    $current_user_role = strtolower(trim($_SESSION['role']));
                                }
                                $is_admin = ($current_user_role === 'admin');
                                
                                // Get patient ID - check multiple possible fields
                                $patient_card_id = 0;
                                if (isset($p['id']) && !empty($p['id'])) {
                                    $patient_card_id = (int)$p['id'];
                                } elseif (isset($p['user_id']) && !empty($p['user_id'])) {
                                    $patient_card_id = (int)$p['user_id'];
                                }
                                
                                // Debug: Output patient ID for troubleshooting (remove in production)
                                // Uncomment the line below to see what patient ID is being used
                                // echo "<!-- Patient ID: " . $patient_card_id . ", Role: " . $current_user_role . ", Is Admin: " . ($is_admin ? 'Yes' : 'No') . " -->";
                                
                                // Show menu for admin users (always show for admins, even if patient ID is 0)
                                if ($is_admin): 
                                ?>
                                <div class="absolute top-2 right-2 z-20">
                                    <button type="button" 
                                            onclick="togglePatientMenu(<?php echo $patient_card_id; ?>)" 
                                            class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="1.5"></circle>
                                            <circle cx="12" cy="5" r="1.5"></circle>
                                            <circle cx="12" cy="19" r="1.5"></circle>
                                        </svg>
                                    </button>
                                    
                                    <!-- Dropdown Menu -->
                                    <div id="patientMenu<?php echo $patient_card_id; ?>" class="hidden absolute right-0 mt-1 w-48 bg-white dark:bg-gray-800 rounded-md shadow-lg z-20 border border-gray-200 dark:border-gray-700 py-1">
                                        <a href="?id=<?php echo $patient_card_id; ?>&view=profile&tab=update" 
                                           class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                            Update
                                        </a>
                                        <a href="?id=<?php echo $patient_card_id; ?>&view=profile&tab=verify" 
                                           class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                            </svg>
                                            Verify Identity & Insurance
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <!-- Profile Section -->
                                <div class="flex flex-col items-center mb-5 relative z-10">
                                    <div class="relative mb-4">
                                        <?php if ($profile_path): ?>
                                            <img src="<?php echo htmlspecialchars($profile_path . $cache_buster); ?>" 
                                                 alt="<?php echo htmlspecialchars($full_name); ?>"
                                                 class="h-24 w-24 rounded-full object-cover border-4 border-blue-200 dark:border-blue-700 shadow-lg ring-2 ring-blue-100 dark:ring-blue-800"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="h-24 w-24 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 dark:from-blue-600 dark:to-blue-800 flex items-center justify-center hidden border-4 border-blue-200 dark:border-blue-700 shadow-lg ring-2 ring-blue-100 dark:ring-blue-800">
                                                <span class="text-white font-bold text-2xl">
                                                    <?php echo htmlspecialchars($initials); ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <div class="h-24 w-24 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 dark:from-blue-600 dark:to-blue-800 flex items-center justify-center border-4 border-blue-200 dark:border-blue-700 shadow-lg ring-2 ring-blue-100 dark:ring-blue-800">
                                                <span class="text-white font-bold text-2xl">
                                                    <?php echo htmlspecialchars($initials); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Medical Badge -->
                                        <div class="absolute -bottom-1 -right-1 h-7 w-7 rounded-full bg-gradient-to-br from-teal-400 to-teal-600 border-3 border-white dark:border-gray-800 flex items-center justify-center shadow-md">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                            </svg>
                                        </div>
                                    </div>
                                    
                                    <h4 class="text-lg font-bold text-gray-900 dark:text-white text-center mb-1.5 leading-tight">
                                        <?php echo htmlspecialchars($full_name); ?>
                                    </h4>
                                    <div class="flex items-center justify-center gap-2 mb-2">
                                        <span class="px-2.5 py-1 text-xs font-mono font-semibold rounded-md bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-600">
                                            <?php echo htmlspecialchars($p['hospital_id'] ?? $p['patient_id'] ?? 'N/A'); ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Patient Type & Status Badges -->
                                    <div class="flex items-center justify-center gap-2 flex-wrap">
                                        <?php 
                                        $patient_type = $p['patient_type'] ?? 'outpatient';
                                        $admission_status = $p['admission_status'] ?? 'active';
                                        ?>
                                        <!-- Patient Type Badge -->
                                        <?php if ($patient_type === 'inpatient'): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4v16"></path><path d="M2 8h18a2 2 0 0 1 2 2v10"></path><path d="M2 17h20"></path><path d="M6 8v9"></path></svg>
                                            Inpatient
                                        </span>
                                        <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                                            Outpatient
                                        </span>
                                        <?php endif; ?>
                                        
                                        <!-- Admission Status Badge -->
                                        <?php if ($admission_status === 'admitted'): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">
                                            <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span>
                                            Admitted
                                        </span>
                                        <?php elseif ($admission_status === 'discharged'): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                            Discharged
                                        </span>
                                        <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                            Active
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Divider -->
                                <div class="border-t border-gray-200 dark:border-gray-700 my-4 relative z-10"></div>
                                
                                <!-- Patient Details -->
                                <div class="space-y-3.5 mb-5 relative z-10">
                                    <!-- Email -->
                                    <div class="flex items-start group/item">
                                        <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center mr-3 group-hover/item:bg-blue-100 dark:group-hover/item:bg-blue-900/30 transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-600 dark:text-blue-400">
                                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                                <polyline points="22,6 12,13 2,6"></polyline>
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Email</p>
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate" title="<?php echo htmlspecialchars($p['email'] ?? 'N/A'); ?>">
                                                <?php echo htmlspecialchars($p['email'] ?? 'N/A'); ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <!-- Contact -->
                                    <div class="flex items-start group/item">
                                        <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-green-50 dark:bg-green-900/20 flex items-center justify-center mr-3 group-hover/item:bg-green-100 dark:group-hover/item:bg-green-900/30 transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-green-600 dark:text-green-400">
                                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Contact</p>
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($contact_display); ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <!-- Age and Gender Row -->
                                    <div class="flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-700 gap-3">
                                        <div class="flex items-center group/item flex-1">
                                            <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-purple-50 dark:bg-purple-900/20 flex items-center justify-center mr-3 group-hover/item:bg-purple-100 dark:group-hover/item:bg-purple-900/30 transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-purple-600 dark:text-purple-400">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <polyline points="12 6 12 12 16 14"></polyline>
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Age</p>
                                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                                    <?php 
                                                    $display_age = $p['age'] ?? null;
                                                    if (!$display_age && !empty($p['birth_date'])) {
                                                        try {
                                                            $birth = new DateTime($p['birth_date']);
                                                            $display_age = (new DateTime())->diff($birth)->y;
                                                        } catch (Exception $e) { $display_age = null; }
                                                    }
                                                    echo htmlspecialchars($display_age ?? 'N/A'); 
                                                    echo $display_age ? ' years' : ''; 
                                                    ?>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($p['gender'])): ?>
                                        <div class="flex items-center group/item flex-1">
                                            <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-pink-50 dark:bg-pink-900/20 flex items-center justify-center mr-3 group-hover/item:bg-pink-100 dark:group-hover/item:bg-pink-900/30 transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-pink-600 dark:text-pink-400">
                                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                                    <circle cx="12" cy="7" r="4"></circle>
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Gender</p>
                                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($p['gender']); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Additional Info Row (Birth Date & Blood Type) -->
                                    <?php if (!empty($p['birth_date']) || !empty($p['blood_type'])): ?>
                                    <div class="flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-700 gap-3">
                                        <?php if (!empty($p['birth_date'])): ?>
                                        <div class="flex items-center group/item flex-1">
                                            <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-orange-50 dark:bg-orange-900/20 flex items-center justify-center mr-3 group-hover/item:bg-orange-100 dark:group-hover/item:bg-orange-900/30 transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-orange-600 dark:text-orange-400">
                                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Birth Date</p>
                                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                                    <?php echo date('M d, Y', strtotime($p['birth_date'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($p['blood_type'])): ?>
                                        <div class="flex items-center group/item flex-1">
                                            <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-red-50 dark:bg-red-900/20 flex items-center justify-center mr-3 group-hover/item:bg-red-100 dark:group-hover/item:bg-red-900/30 transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-red-600 dark:text-red-400">
                                                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Blood Type</p>
                                                <p class="text-sm font-semibold text-red-600 dark:text-red-400">
                                                    <?php echo htmlspecialchars($p['blood_type']); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Action Button -->
                                <button onclick="openPatientModal(<?php echo htmlspecialchars(json_encode($p)); ?>)" 
                                        class="w-full mt-5 px-4 py-3 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white text-sm font-semibold rounded-lg transition-all duration-200 flex items-center justify-center shadow-md hover:shadow-lg transform hover:-translate-y-0.5 relative z-10">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    View Profile
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($view_mode === 'profile' && $patient): ?>
    <!-- Patient Profile -->
    <?php 
    $user_role = strtolower(trim($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? $_SESSION['role'] ?? ''));
    $is_admin = ($user_role === 'admin');
    ?>
    
    <!-- Tabs -->
    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <a href="?id=<?php echo $patient_id; ?>&view=profile&tab=info" 
               class="<?php echo $active_tab === 'info' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'; ?> py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                Patient Information
            </a>
            <?php if ($is_admin): ?>
            <a href="?id=<?php echo $patient_id; ?>&view=profile&tab=update" 
               class="<?php echo $active_tab === 'update' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'; ?> py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                Update Patient Info
            </a>
            <a href="?id=<?php echo $patient_id; ?>&view=profile&tab=verify" 
               class="<?php echo $active_tab === 'verify' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'; ?> py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                Verify Identity & Insurance
            </a>
            <?php endif; ?>
        </nav>
    </div>

    <!-- Tab Content -->
    <?php if ($active_tab === 'info'): ?>
        <!-- Patient Information Tab -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <!-- Basic Information -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Patient Information</h3>
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Patient ID</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['patient_id'] ?? 'N/A'); ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Full Name</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . ($patient['middle_name'] ? $patient['middle_name'] . ' ' : '') . $patient['last_name'] . ($patient['suffix'] ? ' ' . $patient['suffix'] : '')); ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['email'] ?? '-'); ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Contact Number</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['contact_number'] ?? '-'); ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Birth Date</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo $patient['birth_date'] ? date('M d, Y', strtotime($patient['birth_date'])) : '-'; ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Age</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['age'] ?? '-'); ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Gender</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['gender'] ?? '-'); ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Civil Status</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['civil_status'] ?? '-'); ?></dd>
                        </div>
                        <?php if (!empty($patient['nationality'])): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Nationality</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['nationality']); ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($patient['birth_place'])): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Birth Place</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['birth_place']); ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($patient['blood_type'])): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Blood Type</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white font-semibold text-red-600 dark:text-red-400"><?php echo htmlspecialchars($patient['blood_type']); ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($patient['occupation'])): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Occupation</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['occupation'] . ($patient['occupation_other'] ? ' - ' . $patient['occupation_other'] : '')); ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                    <?php if (!empty($patient['known_allergies'])): ?>
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <dt class="text-sm font-medium text-red-700 dark:text-red-300">Known Allergies</dt>
                        <dd class="mt-1 text-sm text-red-600 dark:text-red-400 font-medium"><?php echo htmlspecialchars($patient['known_allergies']); ?></dd>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($patient['pre_existing_conditions'])): ?>
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <dt class="text-sm font-medium text-gray-700 dark:text-gray-300">Pre-existing Conditions</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['pre_existing_conditions']); ?></dd>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($patient['current_medications'])): ?>
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <dt class="text-sm font-medium text-gray-700 dark:text-gray-300">Current Medications</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['current_medications']); ?></dd>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Address Information -->
                <?php if (!empty($patient['house_number']) || !empty($patient['barangay']) || !empty($patient['city_municipality'])): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Address Information</h3>
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <?php if (!empty($patient['house_number'])): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">House No. & Street</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['house_number']); ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($patient['barangay'])): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Barangay</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['barangay']); ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($patient['city_municipality'])): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">City/Municipality</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['city_municipality']); ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($patient['province'])): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Province</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['province']); ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($patient['region'])): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Region</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['region']); ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($patient['zip_code'])): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">ZIP Code</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['zip_code']); ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                </div>
                <?php endif; ?>
                
                <!-- Contact Information -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Contact Information</h3>
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <?php if (!empty($patient['emergency_contact_name'])): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Emergency Contact Name</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['emergency_contact_name']); ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($patient['emergency_contact_number'])): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Emergency Contact Number</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['emergency_contact_number']); ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($patient['emergency_contact_relationship'])): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Relationship</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['emergency_contact_relationship']); ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                </div>
                
                <!-- Insurance Information -->
                <?php if (!empty($patient_insurance)): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Insurance Information</h3>
                    <div class="space-y-4">
                        <?php foreach ($patient_insurance as $insurance): ?>
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($insurance['provider_name'] ?? 'N/A'); ?></p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($insurance['insurance_number'] ?? ''); ?></p>
                                    </div>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $insurance['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo ucfirst($insurance['status'] ?? 'pending'); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="space-y-6">
                <!-- Actions -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Actions</h3>
                    <div class="space-y-2">
                        <a href="?id=<?php echo $patient_id; ?>&view=ehr" class="block w-full px-4 py-2 text-center bg-primary-600 text-white rounded-md hover:bg-primary-700">
                            View EHR Summary
                        </a>
                        <a href="?id=<?php echo $patient_id; ?>&view=history" class="block w-full px-4 py-2 text-center bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                            View Medical History
                        </a>
                        <a href="?id=<?php echo $patient_id; ?>&export=1" class="block w-full px-4 py-2 text-center bg-green-600 text-white rounded-md hover:bg-green-700">
                            Export Patient Data
                        </a>
                        <a href="view_patients.php" class="block w-full px-4 py-2 text-center bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                            Back to List
                        </a>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($active_tab === 'update'): ?>
        <?php if (!$is_admin): ?>
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                <p class="text-red-800 dark:text-red-200">You do not have permission to access this page.</p>
                <a href="?id=<?php echo $patient_id; ?>&view=profile&tab=info" class="mt-2 inline-block text-red-600 dark:text-red-400 hover:underline">Go back to Patient Information</a>
            </div>
        <?php else: ?>
        <!-- Update Patient Info Tab (Admin Only) -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-6">Update Patient Information</h3>
            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" value="update_patient">
                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                
                <!-- Personal Information -->
                <div>
                    <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-4">Personal Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">First Name <span class="text-red-500">*</span></label>
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($patient['first_name'] ?? ''); ?>" required
                                   class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Name <span class="text-red-500">*</span></label>
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($patient['last_name'] ?? ''); ?>" required
                                   class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Middle Name</label>
                            <input type="text" name="middle_name" value="<?php echo htmlspecialchars($patient['middle_name'] ?? ''); ?>"
                                   class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Suffix</label>
                            <select name="suffix" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                                <option value="">None</option>
                                <option value="Jr." <?php echo ($patient['suffix'] ?? '') === 'Jr.' ? 'selected' : ''; ?>>Jr.</option>
                                <option value="Sr." <?php echo ($patient['suffix'] ?? '') === 'Sr.' ? 'selected' : ''; ?>>Sr.</option>
                                <option value="II" <?php echo ($patient['suffix'] ?? '') === 'II' ? 'selected' : ''; ?>>II</option>
                                <option value="III" <?php echo ($patient['suffix'] ?? '') === 'III' ? 'selected' : ''; ?>>III</option>
                                <option value="IV" <?php echo ($patient['suffix'] ?? '') === 'IV' ? 'selected' : ''; ?>>IV</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Birth Date <span class="text-red-500">*</span></label>
                            <input type="date" name="birth_date" value="<?php echo htmlspecialchars($patient['birth_date'] ?? ''); ?>" required
                                   class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gender <span class="text-red-500">*</span></label>
                            <select name="gender" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                                <option value="">Select gender</option>
                                <option value="Male" <?php echo ($patient['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($patient['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Rather Not Say" <?php echo ($patient['gender'] ?? '') === 'Rather Not Say' ? 'selected' : ''; ?>>Rather Not Say</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Civil Status</label>
                            <select name="civil_status" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                                <option value="">Select status</option>
                                <option value="Single" <?php echo ($patient['civil_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
                                <option value="Married" <?php echo ($patient['civil_status'] ?? '') === 'Married' ? 'selected' : ''; ?>>Married</option>
                                <option value="Widowed" <?php echo ($patient['civil_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                <option value="Separated" <?php echo ($patient['civil_status'] ?? '') === 'Separated' ? 'selected' : ''; ?>>Separated</option>
                                <option value="Divorced" <?php echo ($patient['civil_status'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nationality</label>
                            <input type="text" name="nationality" value="<?php echo htmlspecialchars($patient['nationality'] ?? 'Filipino'); ?>"
                                   class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Birth Place</label>
                            <input type="text" name="birth_place" value="<?php echo htmlspecialchars($patient['birth_place'] ?? ''); ?>"
                                   class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Blood Type</label>
                            <select name="blood_type" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                                <option value="">Select blood type</option>
                                <option value="A+" <?php echo ($patient['blood_type'] ?? '') === 'A+' ? 'selected' : ''; ?>>A+</option>
                                <option value="A-" <?php echo ($patient['blood_type'] ?? '') === 'A-' ? 'selected' : ''; ?>>A-</option>
                                <option value="B+" <?php echo ($patient['blood_type'] ?? '') === 'B+' ? 'selected' : ''; ?>>B+</option>
                                <option value="B-" <?php echo ($patient['blood_type'] ?? '') === 'B-' ? 'selected' : ''; ?>>B-</option>
                                <option value="AB+" <?php echo ($patient['blood_type'] ?? '') === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                <option value="AB-" <?php echo ($patient['blood_type'] ?? '') === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                <option value="O+" <?php echo ($patient['blood_type'] ?? '') === 'O+' ? 'selected' : ''; ?>>O+</option>
                                <option value="O-" <?php echo ($patient['blood_type'] ?? '') === 'O-' ? 'selected' : ''; ?>>O-</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Occupation</label>
                            <input type="text" name="occupation" value="<?php echo htmlspecialchars($patient['occupation'] ?? ''); ?>"
                                   class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Known Allergies</label>
                        <textarea name="known_allergies" rows="2" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white"><?php echo htmlspecialchars($patient['known_allergies'] ?? ''); ?></textarea>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Pre-existing Conditions</label>
                        <textarea name="pre_existing_conditions" rows="2" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white"><?php echo htmlspecialchars($patient['pre_existing_conditions'] ?? ''); ?></textarea>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Current Medications</label>
                        <textarea name="current_medications" rows="2" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white"><?php echo htmlspecialchars($patient['current_medications'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Address Information -->
                <div>
                    <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-4">Address Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">House No. & Street</label>
                            <input type="text" name="house_number" value="<?php echo htmlspecialchars($patient['house_number'] ?? ''); ?>"
                                   class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Region</label>
                            <input type="text" name="region" value="<?php echo htmlspecialchars($patient['region'] ?? ''); ?>"
                                   class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Province</label>
                            <input type="text" name="province" value="<?php echo htmlspecialchars($patient['province'] ?? ''); ?>"
                                   class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">City/Municipality</label>
                            <input type="text" name="city_municipality" value="<?php echo htmlspecialchars($patient['city_municipality'] ?? ''); ?>"
                                   class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Barangay</label>
                            <input type="text" name="barangay" value="<?php echo htmlspecialchars($patient['barangay'] ?? ''); ?>"
                                   class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">ZIP Code</label>
                            <input type="text" name="zip_code" value="<?php echo htmlspecialchars($patient['zip_code'] ?? ''); ?>"
                                   class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                </div>
                
                <!-- Contact Information -->
                <div>
                    <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-4">Contact Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contact Number</label>
                            <input type="tel" name="contact_number" value="<?php echo htmlspecialchars($patient['contact_number'] ?? ''); ?>"
                                   class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Emergency Contact Name</label>
                            <input type="text" name="emergency_contact_name" value="<?php echo htmlspecialchars($patient['emergency_contact_name'] ?? ''); ?>"
                                   class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Emergency Contact Number</label>
                            <input type="tel" name="emergency_contact_number" value="<?php echo htmlspecialchars($patient['emergency_contact_number'] ?? ''); ?>"
                                   class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Relationship</label>
                            <select name="emergency_contact_relationship" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                                <option value="">Select relationship</option>
                                <option value="Mother" <?php echo ($patient['emergency_contact_relationship'] ?? '') === 'Mother' ? 'selected' : ''; ?>>Mother</option>
                                <option value="Father" <?php echo ($patient['emergency_contact_relationship'] ?? '') === 'Father' ? 'selected' : ''; ?>>Father</option>
                                <option value="Brother" <?php echo ($patient['emergency_contact_relationship'] ?? '') === 'Brother' ? 'selected' : ''; ?>>Brother</option>
                                <option value="Sister" <?php echo ($patient['emergency_contact_relationship'] ?? '') === 'Sister' ? 'selected' : ''; ?>>Sister</option>
                                <option value="Guardian" <?php echo ($patient['emergency_contact_relationship'] ?? '') === 'Guardian' ? 'selected' : ''; ?>>Guardian</option>
                                <option value="Child" <?php echo ($patient['emergency_contact_relationship'] ?? '') === 'Child' ? 'selected' : ''; ?>>Child</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Government ID -->
                <div>
                    <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-4">Government ID</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">ID Type</label>
                            <select name="government_id_type" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                                <option value="">Select ID type</option>
                                <option value="PhilHealth ID" <?php echo ($patient['government_id_type'] ?? '') === 'PhilHealth ID' ? 'selected' : ''; ?>>PhilHealth ID</option>
                                <option value="SSS ID" <?php echo ($patient['government_id_type'] ?? '') === 'SSS ID' ? 'selected' : ''; ?>>SSS ID</option>
                                <option value="GSIS ID" <?php echo ($patient['government_id_type'] ?? '') === 'GSIS ID' ? 'selected' : ''; ?>>GSIS ID</option>
                                <option value="TIN" <?php echo ($patient['government_id_type'] ?? '') === 'TIN' ? 'selected' : ''; ?>>TIN</option>
                                <option value="Passport ID" <?php echo ($patient['government_id_type'] ?? '') === 'Passport ID' ? 'selected' : ''; ?>>Passport ID</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">ID Number</label>
                            <input type="text" name="government_id_number" value="<?php echo htmlspecialchars($patient['government_id_number'] ?? ''); ?>"
                                   class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <a href="?id=<?php echo $patient_id; ?>&view=profile&tab=info" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                        Cancel
                    </a>
                    <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">
                        Update Patient
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

    <?php elseif ($active_tab === 'verify'): ?>
        <?php if (!$is_admin): ?>
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                <p class="text-red-800 dark:text-red-200">You do not have permission to access this page.</p>
                <a href="?id=<?php echo $patient_id; ?>&view=profile&tab=info" class="mt-2 inline-block text-red-600 dark:text-red-400 hover:underline">Go back to Patient Information</a>
            </div>
        <?php else: ?>
        <!-- Verify Identity & Insurance Tab (Admin Only) -->
        <div class="space-y-6">
            <!-- Identity Verification -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Identity Verification</h3>
                <?php if ($identity_verification): ?>
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 mb-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">ID Type</label>
                                <p class="mt-1 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($identity_verification['id_type']); ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">ID Number</label>
                                <p class="mt-1 text-sm text-gray-900 dark:text-white font-mono"><?php echo htmlspecialchars($identity_verification['id_number']); ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Verification Status</label>
                                <p class="mt-1">
                                    <?php if ($identity_verification['verified']): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            Verified
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                            Pending Verification
                                        </span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php if ($identity_verification['verified'] && $identity_verification['verified_at']): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Verified At</label>
                                <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                    <?php echo date('M d, Y H:i', strtotime($identity_verification['verified_at'])); ?>
                                    <?php if ($identity_verification['verified_by_fname']): ?>
                                        by <?php echo htmlspecialchars($identity_verification['verified_by_fname'] . ' ' . $identity_verification['verified_by_lname']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!$identity_verification['verified']): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="verify_identity">
                            <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                Verify Identity
                            </button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-gray-500 dark:text-gray-400 mb-4">No identity verification record found. Please update patient information with government ID details first.</p>
                <?php endif; ?>
            </div>
            
            <!-- Insurance Verification -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Insurance Verification</h3>
                <?php if (!empty($patient_insurance)): ?>
                    <div class="space-y-4">
                        <?php foreach ($patient_insurance as $insurance): ?>
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($insurance['provider_name'] ?? 'N/A'); ?></p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($insurance['insurance_number'] ?? ''); ?></p>
                                    </div>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php 
                                        $ins_status = $insurance['status'] ?? 'pending_verification';
                                        echo $ins_status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                            ($ins_status === 'pending_verification' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                                            'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200');
                                        ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $ins_status)); ?>
                                    </span>
                                </div>
                                <?php if ($insurance['verified_at']): ?>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Verified: <?php echo date('M d, Y H:i', strtotime($insurance['verified_at'])); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($ins_status === 'pending_verification' || $ins_status === 'inactive'): ?>
                                    <form method="POST" class="mt-3 inline">
                                        <input type="hidden" name="action" value="verify_insurance">
                                        <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                                        <input type="hidden" name="insurance_id" value="<?php echo $insurance['id']; ?>">
                                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm">
                                            Verify Insurance
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 dark:text-gray-400">No insurance records found for this patient.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

<?php elseif ($view_mode === 'ehr' && $patient): ?>
    <!-- EHR Summary -->
    <div class="space-y-6">
        <!-- Header -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">EHR Summary</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">
                        <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?> 
                        (<?php echo htmlspecialchars($patient['patient_id'] ?? 'N/A'); ?>)
                    </p>
                </div>
                <a href="?id=<?php echo $patient_id; ?>&view=profile" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                    Back to Profile
                </a>
            </div>
        </div>

        <!-- Patient Summary Card -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                Patient Summary
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Age</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['age'] ?? 'N/A'); ?> years</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Gender</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['gender'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Blood Type</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['blood_type'] ?? 'Not recorded'); ?></p>
                </div>
            </div>
            <?php if (!empty($patient['known_allergies'])): ?>
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Known Allergies</p>
                    <p class="text-sm text-red-600 dark:text-red-400 font-medium"><?php echo htmlspecialchars($patient['known_allergies']); ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($patient['pre_existing_conditions'])): ?>
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Pre-existing Conditions</p>
                    <p class="text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['pre_existing_conditions']); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Latest Vital Signs -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
                Latest Vital Signs
            </h3>
            <?php if (!empty($ehr_data['vital_signs'])): ?>
                <?php $latest_vitals = $ehr_data['vital_signs'][0]; ?>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Temperature</p>
                        <p class="text-xl font-bold text-blue-600 dark:text-blue-400">
                            <?php echo $latest_vitals['temperature'] ? number_format($latest_vitals['temperature'], 1) . '°C' : 'N/A'; ?>
                        </p>
                    </div>
                    <div class="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Blood Pressure</p>
                        <p class="text-xl font-bold text-red-600 dark:text-red-400">
                            <?php echo ($latest_vitals['blood_pressure_systolic'] && $latest_vitals['blood_pressure_diastolic']) 
                                ? $latest_vitals['blood_pressure_systolic'] . '/' . $latest_vitals['blood_pressure_diastolic'] . ' mmHg' 
                                : 'N/A'; ?>
                        </p>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Heart Rate</p>
                        <p class="text-xl font-bold text-green-600 dark:text-green-400">
                            <?php echo $latest_vitals['heart_rate'] ? $latest_vitals['heart_rate'] . ' bpm' : 'N/A'; ?>
                        </p>
                    </div>
                    <div class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg">
                        <p class="text-xs text-gray-500 dark:text-gray-400">SpO2</p>
                        <p class="text-xl font-bold text-purple-600 dark:text-purple-400">
                            <?php echo $latest_vitals['oxygen_saturation'] ? number_format($latest_vitals['oxygen_saturation'], 1) . '%' : 'N/A'; ?>
                        </p>
                    </div>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Recorded: <?php echo date('M d, Y H:i', strtotime($latest_vitals['recorded_at'])); ?>
                    <?php if ($latest_vitals['recorded_fname']): ?>
                        by <?php echo htmlspecialchars($latest_vitals['recorded_fname'] . ' ' . $latest_vitals['recorded_lname']); ?>
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <p class="text-gray-500 dark:text-gray-400">No vital signs recorded yet.</p>
            <?php endif; ?>
        </div>

        <!-- Allergies -->
        <?php if (!empty($ehr_data['allergies'])): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 text-red-600">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                Allergies
            </h3>
            <div class="space-y-3">
                <?php foreach ($ehr_data['allergies'] as $allergy): ?>
                    <div class="border border-red-200 dark:border-red-800 rounded-lg p-4 bg-red-50 dark:bg-red-900/10">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <p class="font-semibold text-red-900 dark:text-red-200"><?php echo htmlspecialchars($allergy['allergen']); ?></p>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    Type: <?php echo ucfirst(str_replace('_', ' ', $allergy['allergy_type'])); ?> | 
                                    Severity: <span class="font-semibold text-red-600 dark:text-red-400">
                                        <?php echo ucfirst(str_replace('_', ' ', $allergy['severity'])); ?>
                                    </span>
                                </p>
                                <?php if ($allergy['reaction']): ?>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 mt-2">Reaction: <?php echo htmlspecialchars($allergy['reaction']); ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                <?php echo ucfirst($allergy['status']); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Active Diagnoses -->
        <?php if (!empty($ehr_data['diagnoses'])): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                Diagnoses
            </h3>
            <div class="space-y-3">
                <?php foreach ($ehr_data['diagnoses'] as $diagnosis): ?>
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <p class="font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($diagnosis['diagnosis_description']); ?></p>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    <?php if ($diagnosis['icd10_code']): ?>
                                        ICD-10: <span class="font-mono"><?php echo htmlspecialchars($diagnosis['icd10_code']); ?></span> |
                                    <?php endif; ?>
                                    Date: <?php echo date('M d, Y', strtotime($diagnosis['diagnosis_date'])); ?>
                                    <?php if ($diagnosis['doctor_fname']): ?>
                                        | By: <?php echo htmlspecialchars($diagnosis['doctor_fname'] . ' ' . $diagnosis['doctor_lname']); ?>
                                    <?php endif; ?>
                                </p>
                                <?php if ($diagnosis['notes']): ?>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 mt-2"><?php echo htmlspecialchars($diagnosis['notes']); ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                <?php 
                                $status = $diagnosis['status'];
                                echo $status === 'active' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 
                                    ($status === 'resolved' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                    'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200');
                                ?>">
                                <?php echo ucfirst($status); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Current Medications -->
        <?php if (!empty($ehr_data['medications'])): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                    <rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect>
                    <line x1="3" x2="21" y1="9" y2="9"></line>
                    <line x1="9" x2="9" y1="21" y2="9"></line>
                </svg>
                Medications
            </h3>
            <div class="space-y-3">
                <?php foreach ($ehr_data['medications'] as $med): ?>
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <p class="font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($med['medication_name']); ?></p>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    <?php if ($med['dosage']): ?>
                                        Dosage: <?php echo htmlspecialchars($med['dosage']); ?> |
                                    <?php endif; ?>
                                    <?php if ($med['frequency']): ?>
                                        Frequency: <?php echo htmlspecialchars($med['frequency']); ?> |
                                    <?php endif; ?>
                                    Route: <?php echo ucfirst($med['route']); ?>
                                </p>
                                <?php if ($med['instructions']): ?>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 mt-2">
                                        <span class="font-medium">Instructions:</span> <?php echo htmlspecialchars($med['instructions']); ?>
                                    </p>
                                <?php endif; ?>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                    Prescribed: <?php echo date('M d, Y', strtotime($med['prescription_date'])); ?>
                                    <?php if ($med['doctor_fname']): ?>
                                        by <?php echo htmlspecialchars($med['doctor_fname'] . ' ' . $med['doctor_lname']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                <?php 
                                $status = $med['status'];
                                echo $status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                    ($status === 'completed' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 
                                    'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200');
                                ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Lab Results -->
        <?php if (!empty($ehr_data['lab_results'])): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                    <path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0-6 0z"></path>
                    <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 0 1-2.827 0l-4.244-4.243a8 8 0 1 1 11.314 0z"></path>
                </svg>
                Lab Results
            </h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Test Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Result</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Reference Range</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($ehr_data['lab_results'] as $lab): ?>
                            <tr>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($lab['test_name']); ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($lab['result_value'] ?? 'Pending'); ?>
                                    <?php if ($lab['unit']): ?>
                                        <span class="text-gray-500"><?php echo htmlspecialchars($lab['unit']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo htmlspecialchars($lab['reference_range'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo date('M d, Y', strtotime($lab['order_date'])); ?>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php 
                                        $abnormal = $lab['abnormal_flag'] ?? 'normal';
                                        echo $abnormal === 'normal' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                            ($abnormal === 'critical' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 
                                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200');
                                        ?>">
                                        <?php echo ucfirst($abnormal); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Procedures -->
        <?php if (!empty($ehr_data['procedures'])): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
                Procedures
            </h3>
            <div class="space-y-3">
                <?php foreach ($ehr_data['procedures'] as $procedure): ?>
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <p class="font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($procedure['procedure_name']); ?></p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Date: <?php echo date('M d, Y H:i', strtotime($procedure['procedure_date'])); ?> |
                            Type: <?php echo ucfirst(str_replace('_', ' ', $procedure['procedure_type'])); ?>
                            <?php if ($procedure['doctor_fname']): ?>
                                | Performed by: <?php echo htmlspecialchars($procedure['doctor_fname'] . ' ' . $procedure['doctor_lname']); ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($procedure['description']): ?>
                            <p class="text-sm text-gray-700 dark:text-gray-300 mt-2"><?php echo htmlspecialchars($procedure['description']); ?></p>
                        <?php endif; ?>
                        <?php if ($procedure['outcome']): ?>
                            <p class="text-sm text-gray-700 dark:text-gray-300 mt-2">
                                <span class="font-medium">Outcome:</span> <?php echo htmlspecialchars($procedure['outcome']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Immunizations -->
        <?php if (!empty($ehr_data['immunizations'])): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                Immunizations
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($ehr_data['immunizations'] as $immunization): ?>
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <p class="font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($immunization['vaccine_name']); ?></p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Date: <?php echo date('M d, Y', strtotime($immunization['administration_date'])); ?>
                            <?php if ($immunization['dose_number'] > 1): ?>
                                | Dose: <?php echo $immunization['dose_number']; ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($immunization['next_dose_date']): ?>
                            <p class="text-sm text-blue-600 dark:text-blue-400 mt-2">
                                Next dose: <?php echo date('M d, Y', strtotime($immunization['next_dose_date'])); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Clinical Notes -->
        <?php if (!empty($ehr_data['clinical_notes'])): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                Clinical Notes
            </h3>
            <div class="space-y-4">
                <?php foreach ($ehr_data['clinical_notes'] as $note): ?>
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">
                                    <?php echo ucfirst(str_replace('_', ' ', $note['note_type'])); ?> Note
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo date('M d, Y H:i', strtotime($note['note_date'])); ?>
                                    <?php if ($note['author_fname']): ?>
                                        by <?php echo htmlspecialchars($note['author_fname'] . ' ' . $note['author_lname']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <?php if ($note['chief_complaint']): ?>
                            <div class="mb-2">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Chief Complaint:</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($note['chief_complaint']); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if ($note['subjective']): ?>
                            <div class="mb-2">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Subjective:</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo nl2br(htmlspecialchars($note['subjective'])); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if ($note['objective']): ?>
                            <div class="mb-2">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Objective:</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo nl2br(htmlspecialchars($note['objective'])); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if ($note['assessment']): ?>
                            <div class="mb-2">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Assessment:</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo nl2br(htmlspecialchars($note['assessment'])); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if ($note['plan']): ?>
                            <div class="mb-2">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Plan:</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo nl2br(htmlspecialchars($note['plan'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Empty State -->
        <?php if (empty($ehr_data['vital_signs']) && empty($ehr_data['diagnoses']) && empty($ehr_data['medications']) && 
                  empty($ehr_data['lab_results']) && empty($ehr_data['procedures']) && empty($ehr_data['allergies']) && 
                  empty($ehr_data['immunizations']) && empty($ehr_data['clinical_notes'])): ?>
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-12 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 dark:text-gray-600 mx-auto mb-4">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No EHR Records Found</h3>
                <p class="text-gray-500 dark:text-gray-400">No health records have been entered for this patient yet.</p>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($view_mode === 'history' && $patient): ?>
    <!-- Medical History -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Medical History - <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h2>
            <a href="?id=<?php echo $patient_id; ?>&view=profile" class="text-primary-600 hover:text-primary-800">Back to Profile</a>
        </div>
        
        <!-- Appointments History -->
        <?php if (!empty($medical_history['appointments'])): ?>
        <div class="mb-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Appointments</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Doctor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($medical_history['appointments'] as $apt): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    <?php echo date('M d, Y H:i', strtotime($apt['appointment_date'] . ' ' . $apt['appointment_time'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars(($apt['doctor_fname'] ?? '') . ' ' . ($apt['doctor_lname'] ?? '')); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <?php echo ucfirst($apt['status'] ?? 'unknown'); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Teleconsultations History -->
        <?php if (!empty($medical_history['teleconsultations'])): ?>
        <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Teleconsultations</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Doctor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($medical_history['teleconsultations'] as $tc): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    <?php echo date('M d, Y H:i', strtotime($tc['consultation_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars(($tc['doctor_fname'] ?? '') . ' ' . ($tc['doctor_lname'] ?? '')); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                        <?php echo ucfirst($tc['status'] ?? 'unknown'); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (empty($medical_history['appointments']) && empty($medical_history['teleconsultations'])): ?>
            <div class="text-center py-12 text-gray-500">
                <p>No medical history found for this patient.</p>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Patient Details Modal -->
<div id="patientModal" class="hidden fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
    <!-- Background overlay -->
    <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75" onclick="closePatientModal()"></div>

    <!-- Modal panel -->
    <div class="relative bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="flex items-center justify-end mb-4">
                <button onclick="closePatientModal()" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            
            <div id="modalPatientContent" class="space-y-6">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
        
        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 border-t border-gray-200 dark:border-gray-600">
            <div class="space-y-2">
                <a id="modalEhrBtn" href="#" class="block w-full px-4 py-2 text-center bg-primary-600 text-white rounded-md hover:bg-primary-700 text-sm font-medium">
                    View EHR Summary
                </a>
                <a id="modalHistoryBtn" href="#" class="block w-full px-4 py-2 text-center bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500 text-sm font-medium">
                    View Medical History
                </a>
                <a id="modalExportBtn" href="#" class="block w-full px-4 py-2 text-center bg-green-600 text-white rounded-md hover:bg-green-700 text-sm font-medium">
                    Export Patient Data
                </a>
                <button onclick="closePatientModal()" class="w-full px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500 text-sm font-medium">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPatient = null;

function openPatientModal(patient) {
    currentPatient = patient;
    const modal = document.getElementById('patientModal');
    const modalContent = document.getElementById('modalPatientContent');
    
    // Build full name
    const fullName = [
        patient.first_name || '',
        patient.middle_name || '',
        patient.last_name || '',
        patient.suffix || ''
    ].filter(Boolean).join(' ');
    
    // Build profile picture HTML
    let profilePictureHtml = '';
    if (patient.profile_picture) {
        const profilePath = '<?php echo BASE_URL; ?>/' + patient.profile_picture;
        profilePictureHtml = `
            <img src="${profilePath}?t=${new Date().getTime()}" 
                 alt="${fullName}"
                 class="w-24 h-24 rounded-full object-cover border-4 border-blue-200 dark:border-blue-700 mx-auto"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <div class="w-24 h-24 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center hidden mx-auto">
                <span class="text-blue-600 dark:text-blue-300 font-medium text-2xl">
                    ${(patient.first_name?.[0] || '').toUpperCase()}${(patient.last_name?.[0] || '').toUpperCase()}
                </span>
            </div>
        `;
    } else {
        const initials = ((patient.first_name?.[0] || '') + (patient.last_name?.[0] || '')).toUpperCase();
        profilePictureHtml = `
            <div class="w-24 h-24 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center mx-auto">
                <span class="text-blue-600 dark:text-blue-300 font-medium text-2xl">${initials}</span>
            </div>
        `;
    }
    
    // Format contact number
    const formatContact = (contact) => {
        if (!contact || contact === '-') return 'N/A';
        const cleaned = contact.replace(/\D/g, '');
        if (cleaned.length >= 11) {
            return cleaned.slice(0, 4) + ' ' + cleaned.slice(4, 7) + ' ' + cleaned.slice(7);
        }
        return contact;
    };
    
    // Format date
    const formatDate = (dateStr) => {
        if (!dateStr || dateStr === '-') return 'N/A';
        try {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        } catch {
            return dateStr;
        }
    };
    
    // Calculate age from birth_date if age field is missing
    let patientAge = patient.age || null;
    if (!patientAge && patient.birth_date) {
        try {
            const birth = new Date(patient.birth_date);
            const today = new Date();
            patientAge = today.getFullYear() - birth.getFullYear();
            const m = today.getMonth() - birth.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) patientAge--;
        } catch(e) { patientAge = null; }
    }

    // Build address (support both patients & users table column names)
    const addressParts = [];
    if (patient.house_number || patient.house_no_street) addressParts.push(patient.house_number || patient.house_no_street);
    if (patient.barangay) addressParts.push(patient.barangay);
    if (patient.city_municipality || patient.city_code) addressParts.push(patient.city_municipality || patient.city_code);
    if (patient.province || patient.province_code) addressParts.push(patient.province || patient.province_code);
    if (patient.region || patient.region_code) addressParts.push(patient.region || patient.region_code);
    if (patient.zip_code) addressParts.push(patient.zip_code);
    const fullAddress = addressParts.length > 0 ? addressParts.join(', ') : 'N/A';
    
    // Patient ID (support both hospital_id and patient_id)
    const patientDisplayId = patient.hospital_id || patient.patient_id || 'N/A';
    
    modalContent.innerHTML = `
        <div class="text-center mb-6">
            ${profilePictureHtml}
            <h4 class="mt-4 text-xl font-semibold text-gray-900 dark:text-white">
                ${fullName}
            </h4>
            <p class="text-sm text-gray-500 dark:text-gray-400 font-mono">${patientDisplayId}</p>
            <div class="flex justify-center space-x-2 mt-2">
                ${patientAge ? `<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Age: ${patientAge} years</span>` : ''}
                ${patient.gender ? `<span class="px-2 py-1 text-xs font-semibold rounded-full bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200">${patient.gender}</span>` : ''}
            </div>
        </div>
        
        <!-- Personal Information -->
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <h5 class="text-md font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                Personal Information
            </h5>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Patient ID</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white font-mono">${patientDisplayId}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Full Name</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white">${fullName || 'N/A'}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Birth Date</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white">${formatDate(patient.birth_date)}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Age</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white">${patientAge ? patientAge + ' years' : 'N/A'}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gender</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white">${patient.gender || 'N/A'}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Civil Status</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white">${patient.civil_status || 'N/A'}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nationality</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white">${patient.nationality || 'N/A'}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Birth Place</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white">${patient.birth_place || 'N/A'}</p>
                </div>
                ${patient.blood_type ? `
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Blood Type</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white font-semibold text-red-600 dark:text-red-400">${patient.blood_type}</p>
                </div>
                ` : ''}
                ${patient.occupation ? `
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Occupation</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white">${patient.occupation}${patient.occupation_other ? ' - ' + patient.occupation_other : ''}</p>
                </div>
                ` : ''}
            </div>
            ${patient.known_allergies ? `
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <label class="block text-sm font-medium text-red-700 dark:text-red-300">Known Allergies</label>
                <p class="mt-1 text-sm text-red-600 dark:text-red-400 font-medium">${patient.known_allergies}</p>
            </div>
            ` : ''}
            ${patient.pre_existing_conditions ? `
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Pre-existing Conditions</label>
                <p class="mt-1 text-sm text-gray-900 dark:text-white">${patient.pre_existing_conditions}</p>
            </div>
            ` : ''}
            ${patient.current_medications ? `
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Current Medications</label>
                <p class="mt-1 text-sm text-gray-900 dark:text-white">${patient.current_medications}</p>
            </div>
            ` : ''}
        </div>
        
        <!-- Address Information -->
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <h5 class="text-md font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                    <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
                Address Information
            </h5>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Complete Address</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white">${fullAddress}</p>
                </div>
                ${patient.house_number ? `
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">House No. & Street</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white">${patient.house_number}</p>
                </div>
                ` : ''}
                ${patient.barangay ? `
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Barangay</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white">${patient.barangay}</p>
                </div>
                ` : ''}
                ${patient.city_municipality ? `
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">City/Municipality</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white">${patient.city_municipality}</p>
                </div>
                ` : ''}
                ${patient.province ? `
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Province</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white">${patient.province}</p>
                </div>
                ` : ''}
                ${patient.region ? `
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Region</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white">${patient.region}</p>
                </div>
                ` : ''}
                ${patient.zip_code ? `
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">ZIP Code</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white">${patient.zip_code}</p>
                </div>
                ` : ''}
            </div>
        </div>
        
        <!-- Contact Information -->
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <h5 class="text-md font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                </svg>
                Contact Information
            </h5>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white">${patient.email || 'N/A'}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contact Number</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white">${formatContact(patient.contact_number)}</p>
                </div>
                ${patient.emergency_contact_name ? `
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Emergency Contact Name</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white">${patient.emergency_contact_name}</p>
                </div>
                ` : ''}
                ${patient.emergency_contact_number ? `
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Emergency Contact Number</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white">${formatContact(patient.emergency_contact_number)}</p>
                </div>
                ` : ''}
                ${patient.emergency_contact_relationship ? `
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Relationship</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white">${patient.emergency_contact_relationship}</p>
                </div>
                ` : ''}
            </div>
        </div>
        
        <!-- Government ID Information -->
        ${patient.government_id_type || patient.government_id_number ? `
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <h5 class="text-md font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
                    <rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                Government ID
            </h5>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                ${patient.government_id_type ? `
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">ID Type</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white">${patient.government_id_type}</p>
                </div>
                ` : ''}
                ${patient.government_id_number ? `
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">ID Number</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white font-mono">${patient.government_id_number}</p>
                </div>
                ` : ''}
            </div>
        </div>
        ` : ''}
    `;
    
    // Set action button URLs
    if (patient.id) {
        const ehrBtn = document.getElementById('modalEhrBtn');
        const historyBtn = document.getElementById('modalHistoryBtn');
        const exportBtn = document.getElementById('modalExportBtn');
        
        if (ehrBtn) ehrBtn.href = '?id=' + patient.id + '&view=ehr';
        if (historyBtn) historyBtn.href = '?id=' + patient.id + '&view=history';
        if (exportBtn) exportBtn.href = '?id=' + patient.id + '&export=1';
    }
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closePatientModal() {
    const modal = document.getElementById('patientModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
    currentPatient = null;
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePatientModal();
    }
});

// Patient Menu Functions
function togglePatientMenu(patientId) {
    const menu = document.getElementById('patientMenu' + patientId);
    const allMenus = document.querySelectorAll('[id^="patientMenu"]');
    
    // Close all other menus
    allMenus.forEach(function(m) {
        if (m.id !== menu.id) {
            m.classList.add('hidden');
        }
    });
    
    // Toggle current menu
    menu.classList.toggle('hidden');
}

// Close patient menus when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('[onclick^="togglePatientMenu"]') && !event.target.closest('[id^="patientMenu"]')) {
        const allMenus = document.querySelectorAll('[id^="patientMenu"]');
        allMenus.forEach(function(menu) {
            menu.classList.add('hidden');
        });
    }
});

// Search and Filter Functions
let searchTimeout = null;

function toggleSearchBar() {
    const searchIconContainer = document.getElementById('searchIconContainer');
    const searchBarContainer = document.getElementById('searchBarContainer');
    const searchInput = document.getElementById('searchInput');
    
    if (searchBarContainer.classList.contains('hidden')) {
        searchIconContainer.classList.add('hidden');
        searchBarContainer.classList.remove('hidden');
        searchInput.focus();
    } else {
        closeSearchBar();
    }
}

function closeSearchBar() {
    const searchIconContainer = document.getElementById('searchIconContainer');
    const searchBarContainer = document.getElementById('searchBarContainer');
    const searchInput = document.getElementById('searchInput');
    
    searchBarContainer.classList.add('hidden');
    searchIconContainer.classList.remove('hidden');
    searchInput.value = '';
    
    // Clear search and reload
    if (window.location.search.includes('search=')) {
        const url = new URL(window.location);
        url.searchParams.delete('search');
        window.location.href = url.toString();
    }
}

function toggleFilterMenu() {
    const filterMenu = document.getElementById('filterMenu');
    filterMenu.classList.toggle('hidden');
}

// Close filter menu when clicking outside
document.addEventListener('click', function(event) {
    const filterMenu = document.getElementById('filterMenu');
    const filterButton = event.target.closest('[onclick="toggleFilterMenu()"]');
    
    if (!filterMenu.contains(event.target) && !filterButton) {
        filterMenu.classList.add('hidden');
    }
});

// Real-time search
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.trim();
            
            // Clear previous timeout
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            // Debounce search - wait 300ms after user stops typing
            searchTimeout = setTimeout(function() {
                const url = new URL(window.location);
                
                if (searchTerm.length > 0) {
                    url.searchParams.set('search', searchTerm);
                    // Remove initial filter when searching
                    url.searchParams.delete('initial');
                } else {
                    url.searchParams.delete('search');
                }
                
                // Update URL and reload
                window.location.href = url.toString();
            }, 300);
        });
        
        // Handle Enter key to search immediately
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (searchTimeout) {
                    clearTimeout(searchTimeout);
                }
                const searchTerm = e.target.value.trim();
                const url = new URL(window.location);
                
                if (searchTerm.length > 0) {
                    url.searchParams.set('search', searchTerm);
                    url.searchParams.delete('initial');
                } else {
                    url.searchParams.delete('search');
                }
                
                window.location.href = url.toString();
            }
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
