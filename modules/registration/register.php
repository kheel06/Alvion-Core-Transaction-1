<?php
require_once '../../config/config.php';
requireAuth();
checkRole(['admin', 'receptionist', 'doctor', 'nurse']);

$page_title = "Patient Registration";

if ($_POST) {
    try {
        // Generate hospital ID
        $hospital_id = generateHospitalId();
        
        // Insert patient data
        $query = "INSERT INTO patients (
            hospital_id, first_name, last_name, middle_name, suffix, birth_date, gender, 
            civil_status, nationality, birth_place, house_no_street, barangay, city_code, 
            province_code, region_code, zip_code, contact_number, email, 
            emergency_contact_name, emergency_contact_number, emergency_contact_relationship,
            philhealth_id, sss_id, gsis_id, tin, passport_number, blood_type, 
            known_allergies, pre_existing_conditions, current_medications,
            occupation, employer_name, employer_address, employer_contact, created_by
        ) VALUES (
            :hospital_id, :first_name, :last_name, :middle_name, :suffix, :birth_date, :gender,
            :civil_status, :nationality, :birth_place, :house_no_street, :barangay, :city_code,
            :province_code, :region_code, :zip_code, :contact_number, :email,
            :emergency_contact_name, :emergency_contact_number, :emergency_contact_relationship,
            :philhealth_id, :sss_id, :gsis_id, :tin, :passport_number, :blood_type,
            :known_allergies, :pre_existing_conditions, :current_medications,
            :occupation, :employer_name, :employer_address, :employer_contact, :created_by
        )";
        
        $stmt = $db->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(':hospital_id', $hospital_id);
        $stmt->bindParam(':first_name', sanitizeInput($_POST['first_name']));
        $stmt->bindParam(':last_name', sanitizeInput($_POST['last_name']));
        $stmt->bindParam(':middle_name', sanitizeInput($_POST['middle_name']));
        $stmt->bindParam(':suffix', sanitizeInput($_POST['suffix']));
        $stmt->bindParam(':birth_date', sanitizeInput($_POST['birth_date']));
        $stmt->bindParam(':gender', sanitizeInput($_POST['gender']));
        $stmt->bindParam(':civil_status', sanitizeInput($_POST['civil_status']));
        $stmt->bindParam(':nationality', sanitizeInput($_POST['nationality']));
        $stmt->bindParam(':birth_place', sanitizeInput($_POST['birth_place']));
        $stmt->bindParam(':house_no_street', sanitizeInput($_POST['house_no_street']));
        $stmt->bindParam(':barangay', sanitizeInput($_POST['barangay']));
        $stmt->bindParam(':city_code', sanitizeInput($_POST['city_code']));
        $stmt->bindParam(':province_code', sanitizeInput($_POST['province_code']));
        $stmt->bindParam(':region_code', sanitizeInput($_POST['region_code']));
        $stmt->bindParam(':zip_code', sanitizeInput($_POST['zip_code']));
        $stmt->bindParam(':contact_number', sanitizeInput($_POST['contact_number']));
        $stmt->bindParam(':email', sanitizeInput($_POST['email']));
        $stmt->bindParam(':emergency_contact_name', sanitizeInput($_POST['emergency_contact_name']));
        $stmt->bindParam(':emergency_contact_number', sanitizeInput($_POST['emergency_contact_number']));
        $stmt->bindParam(':emergency_contact_relationship', sanitizeInput($_POST['emergency_contact_relationship']));
        $stmt->bindParam(':philhealth_id', sanitizeInput($_POST['philhealth_id']));
        $stmt->bindParam(':sss_id', sanitizeInput($_POST['sss_id']));
        $stmt->bindParam(':gsis_id', sanitizeInput($_POST['gsis_id']));
        $stmt->bindParam(':tin', sanitizeInput($_POST['tin']));
        $stmt->bindParam(':passport_number', sanitizeInput($_POST['passport_number']));
        $stmt->bindParam(':blood_type', sanitizeInput($_POST['blood_type']));
        $stmt->bindParam(':known_allergies', sanitizeInput($_POST['known_allergies']));
        $stmt->bindParam(':pre_existing_conditions', sanitizeInput($_POST['pre_existing_conditions']));
        $stmt->bindParam(':current_medications', sanitizeInput($_POST['current_medications']));
        $stmt->bindParam(':occupation', sanitizeInput($_POST['occupation']));
        $stmt->bindParam(':employer_name', sanitizeInput($_POST['employer_name']));
        $stmt->bindParam(':employer_address', sanitizeInput($_POST['employer_address']));
        $stmt->bindParam(':employer_contact', sanitizeInput($_POST['employer_contact']));
        $stmt->bindParam(':created_by', $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $patient_id = $db->lastInsertId();
            $_SESSION['success'] = "Patient registered successfully! Hospital ID: " . $hospital_id;
            header("Location: view_patient.php?id=" . $patient_id);
            exit();
        }
    } catch (PDOException $exception) {
        $_SESSION['error'] = "Registration error: " . $exception->getMessage();
    }
}

// Get Philippine regions for dropdown
try {
    $regions_query = "SELECT * FROM ph_regions ORDER BY region_name";
    $regions_stmt = $db->prepare($regions_query);
    $regions_stmt->execute();
    $regions = $regions_stmt->fetchAll();
} catch (PDOException $exception) {
    $regions = [];
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Patient Registration</h1>
    <p class="text-gray-600 dark:text-gray-400">Register new patient information</p>
</div>

<div class="bg-white dark:bg-gray-800 shadow rounded-lg">
    <div class="px-4 py-5 sm:p-6">
        <form method="POST" class="space-y-8">
            <!-- Personal Information -->
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Personal Information</h3>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">First Name *</label>
                        <input type="text" name="first_name" id="first_name" required 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Name *</label>
                        <input type="text" name="last_name" id="last_name" required 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="middle_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Middle Name</label>
                        <input type="text" name="middle_name" id="middle_name" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="suffix" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Suffix</label>
                        <select name="suffix" id="suffix" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">None</option>
                            <option value="Jr.">Jr.</option>
                            <option value="Sr.">Sr.</option>
                            <option value="II">II</option>
                            <option value="III">III</option>
                            <option value="IV">IV</option>
                        </select>
                    </div>

                    <div>
                        <label for="birth_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Birth Date *</label>
                        <input type="date" name="birth_date" id="birth_date" required 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="gender" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gender *</label>
                        <select name="gender" id="gender" required 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div>
                        <label for="civil_status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Civil Status</label>
                        <select name="civil_status" id="civil_status" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Status</option>
                            <option value="single">Single</option>
                            <option value="married">Married</option>
                            <option value="widowed">Widowed</option>
                            <option value="separated">Separated</option>
                            <option value="divorced">Divorced</option>
                        </select>
                    </div>

                    <div>
                        <label for="nationality" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nationality</label>
                        <input type="text" name="nationality" id="nationality" value="Filipino" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="birth_place" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Birth Place</label>
                        <input type="text" name="birth_place" id="birth_place" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                </div>
            </div>

            <!-- Address Information -->
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Address Information</h3>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="house_no_street" class="block text-sm font-medium text-gray-700 dark:text-gray-300">House No. & Street</label>
                        <input type="text" name="house_no_street" id="house_no_street" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="barangay" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Barangay</label>
                        <input type="text" name="barangay" id="barangay" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="city_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">City/Municipality</label>
                        <input type="text" name="city_code" id="city_code" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="province_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Province</label>
                        <input type="text" name="province_code" id="province_code" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="region_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Region</label>
                        <select name="region_code" id="region_code" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Region</option>
                            <?php foreach ($regions as $region): ?>
                                <option value="<?php echo $region['region_code']; ?>"><?php echo $region['region_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="zip_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">ZIP Code</label>
                        <input type="text" name="zip_code" id="zip_code" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Contact Information</h3>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label for="contact_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contact Number</label>
                        <input type="tel" name="contact_number" id="contact_number" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address</label>
                        <input type="email" name="email" id="email" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="emergency_contact_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Emergency Contact Name</label>
                        <input type="text" name="emergency_contact_name" id="emergency_contact_name" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="emergency_contact_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Emergency Contact Number</label>
                        <input type="tel" name="emergency_contact_number" id="emergency_contact_number" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="emergency_contact_relationship" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Relationship</label>
                        <input type="text" name="emergency_contact_relationship" id="emergency_contact_relationship" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                </div>
            </div>

            <!-- Government IDs -->
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Government Identification</h3>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label for="philhealth_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">PhilHealth ID</label>
                        <input type="text" name="philhealth_id" id="philhealth_id" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="sss_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">SSS ID</label>
                        <input type="text" name="sss_id" id="sss_id" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="gsis_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">GSIS ID</label>
                        <input type="text" name="gsis_id" id="gsis_id" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="tin" class="block text-sm font-medium text-gray-700 dark:text-gray-300">TIN</label>
                        <input type="text" name="tin" id="tin" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="passport_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Passport Number</label>
                        <input type="text" name="passport_number" id="passport_number" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                </div>
            </div>

            <!-- Medical Information -->
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Medical Information</h3>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label for="blood_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Blood Type</label>
                        <select name="blood_type" id="blood_type" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Blood Type</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="known_allergies" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Known Allergies</label>
                        <textarea name="known_allergies" id="known_allergies" rows="3" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" 
                            placeholder="List any known allergies..."></textarea>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="pre_existing_conditions" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Pre-existing Conditions</label>
                        <textarea name="pre_existing_conditions" id="pre_existing_conditions" rows="3" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" 
                            placeholder="List any pre-existing medical conditions..."></textarea>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="current_medications" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Current Medications</label>
                        <textarea name="current_medications" id="current_medications" rows="3" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" 
                            placeholder="List any current medications..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Employment Information -->
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Employment Information</h3>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label for="occupation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Occupation</label>
                        <input type="text" name="occupation" id="occupation" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="employer_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Employer Name</label>
                        <input type="text" name="employer_name" id="employer_name" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="employer_address" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Employer Address</label>
                        <textarea name="employer_address" id="employer_address" rows="2" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"></textarea>
                    </div>

                    <div>
                        <label for="employer_contact" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Employer Contact</label>
                        <input type="tel" name="employer_contact" id="employer_contact" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                </div>
            </div>

            <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200 dark:border-gray-700">
                <button type="reset" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    Reset Form
                </button>
                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    Register Patient
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>