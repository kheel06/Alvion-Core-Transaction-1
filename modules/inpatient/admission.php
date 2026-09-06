<?php
/**
 * Patient Admission
 * Part of IBMS - Inpatient and Bed Management System
 * Philippines Hospital Context
 */
require_once '../../config/config.php';
requireAuth();
checkRole(['admin', 'doctor', 'nurse']);

$page_title = "Patient Admission";

// Handle admission
if ($_POST) {
    try {
        $admission_number = generateAdmissionNumber();
        $patient_id = (int)sanitizeInput($_POST['patient_id']);
        $bed_id = (int)sanitizeInput($_POST['bed_id']);
        $admitting_doctor_id = $_SESSION['user_id'];
        $admission_type = sanitizeInput($_POST['admission_type']);
        $diagnosis = sanitizeInput($_POST['diagnosis'] ?? '');
        
        // Check if bed is available
        $bed_check = $db->prepare("SELECT status, current_patient_id FROM beds WHERE id = :bed_id");
        $bed_check->bindParam(':bed_id', $bed_id, PDO::PARAM_INT);
        $bed_check->execute();
        $bed = $bed_check->fetch();
        
        if (!$bed) {
            throw new Exception("Bed not found.");
        }
        
        if ($bed['status'] !== 'available') {
            throw new Exception("Selected bed is not available.");
        }
        
        // Create admission
        $admission_query = "INSERT INTO admissions (
            admission_number, patient_id, bed_id, admitting_doctor_id,
            admission_date, admission_type, diagnosis, status
        ) VALUES (
            :admission_number, :patient_id, :bed_id, :doctor_id,
            NOW(), :admission_type, :diagnosis, 'admitted'
        )";
        
        $admission_stmt = $db->prepare($admission_query);
        $admission_stmt->bindParam(':admission_number', $admission_number);
        $admission_stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $admission_stmt->bindParam(':bed_id', $bed_id, PDO::PARAM_INT);
        $admission_stmt->bindParam(':doctor_id', $admitting_doctor_id, PDO::PARAM_INT);
        $admission_stmt->bindParam(':admission_type', $admission_type);
        $admission_stmt->bindParam(':diagnosis', $diagnosis);
        $admission_stmt->execute();
        
        // Update bed status
        $bed_update = $db->prepare("UPDATE beds SET status = 'occupied', current_patient_id = :patient_id WHERE id = :bed_id");
        $bed_update->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $bed_update->bindParam(':bed_id', $bed_id, PDO::PARAM_INT);
        $bed_update->execute();
        
        // Update ward occupancy
        $ward_update = $db->prepare("UPDATE wards w
                                    INNER JOIN beds b ON w.id = b.ward_id
                                    SET w.current_occupancy = (
                                        SELECT COUNT(*) FROM beds WHERE ward_id = w.id AND status = 'occupied'
                                    )
                                    WHERE b.id = :bed_id");
        $ward_update->bindParam(':bed_id', $bed_id, PDO::PARAM_INT);
        $ward_update->execute();
        
        $_SESSION['success'] = "Patient admitted successfully! Admission #: " . $admission_number;
        header("Location: admission.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error processing admission: " . $e->getMessage();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Get patients (include active or null status so dropdown always has data when patients exist)
try {
    $patients_query = "SELECT id, hospital_id, first_name, last_name FROM patients WHERE (status = 'active' OR status IS NULL) ORDER BY first_name, last_name";
    $patients_stmt = $db->prepare($patients_query);
    $patients_stmt->execute();
    $patients = $patients_stmt->fetchAll();
} catch (PDOException $e) {
    $patients = [];
}

// Get available beds with ward information
try {
    $beds_query = "SELECT b.*, w.ward_name, w.ward_code, w.ward_type, w.department_id
                   FROM beds b
                   INNER JOIN wards w ON b.ward_id = w.id
                   WHERE b.status = 'available'
                   ORDER BY w.ward_name, b.bed_number";
    $beds_stmt = $db->prepare($beds_query);
    $beds_stmt->execute();
    $available_beds = $beds_stmt->fetchAll();
} catch (PDOException $e) {
    $available_beds = [];
}

// Get recent admissions
try {
    $recent_query = "SELECT ad.*, p.first_name, p.last_name, p.hospital_id, w.ward_name, b.bed_number
                    FROM admissions ad
                    INNER JOIN patients p ON ad.patient_id = p.id
                    INNER JOIN beds b ON ad.bed_id = b.id
                    INNER JOIN wards w ON b.ward_id = w.id
                    WHERE ad.status = 'admitted'
                    ORDER BY ad.admission_date DESC
                    LIMIT 10";
    $recent_stmt = $db->prepare($recent_query);
    $recent_stmt->execute();
    $recent_admissions = $recent_stmt->fetchAll();
} catch (PDOException $e) {
    $recent_admissions = [];
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Patient Admission</h1>
    <p class="text-gray-600">Admit patients to inpatient wards</p>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <!-- Admission Form -->
    <div class="lg:col-span-2">
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">New Admission</h3>
                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="patient_id" class="block text-sm font-medium text-gray-700">Patient *</label>
                            <select name="patient_id" id="patient_id" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                <option value="">Select Patient</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo $patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['hospital_id'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="admission_type" class="block text-sm font-medium text-gray-700">Admission Type *</label>
                            <select name="admission_type" id="admission_type" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                <option value="emergency">Emergency</option>
                                <option value="scheduled">Elective</option>
                                <option value="transfer">Transfer</option>
                            </select>
                        </div>

                        <div>
                            <label for="bed_id" class="block text-sm font-medium text-gray-700">Assign Bed *</label>
                            <select name="bed_id" id="bed_id" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                <option value="">Select Bed</option>
                                <?php 
                                $current_ward = '';
                                foreach ($available_beds as $bed): 
                                    if ($current_ward !== $bed['ward_name']):
                                        if ($current_ward !== ''):
                                            echo '</optgroup>';
                                        endif;
                                        echo '<optgroup label="' . $bed['ward_name'] . ' (' . $bed['ward_code'] . ')">';
                                        $current_ward = $bed['ward_name'];
                                    endif;
                                ?>
                                    <option value="<?php echo $bed['id']; ?>">
                                        Bed <?php echo $bed['bed_number']; ?> - 
                                        <?php echo ucfirst($bed['bed_type']); ?> - 
                                        ₱<?php echo number_format($bed['daily_rate'], 2); ?>/day
                                    </option>
                                <?php endforeach; ?>
                                <?php if ($current_ward !== ''): ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <label for="diagnosis" class="block text-sm font-medium text-gray-700">Primary Diagnosis</label>
                            <textarea name="diagnosis" id="diagnosis" rows="3"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                                placeholder="Enter primary diagnosis and reason for admission..."></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700">
                            Admit Patient
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Recent Admissions -->
    <div class="lg:col-span-1">
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Recent Admissions</h3>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <?php if (count($recent_admissions) > 0): ?>
                    <div class="space-y-3">
                        <?php foreach ($recent_admissions as $adm): ?>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-sm font-medium text-gray-900">
                                    <?php echo $adm['first_name'] . ' ' . $adm['last_name']; ?>
                                </p>
                                <p class="text-xs text-gray-500"><?php echo $adm['hospital_id']; ?></p>
                                <p class="text-xs text-gray-600 mt-1">
                                    <?php echo $adm['ward_name']; ?> - Bed <?php echo $adm['bed_number']; ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?php echo formatDate($adm['admission_date']); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M2 4v16"></path><path d="M2 8h18a2 2 0 0 1 2 2v10"></path><path d="M2 17h20"></path><path d="M6 8V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"></path></svg>
                        <p class="text-gray-500">No recent admissions</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>



