<?php
/**
 * Insurance & Billing Setup
 * Part of SPRS - Smart Patient Registration System
 */
require_once '../../config/config.php';
requireAuth();
checkRole(['admin', 'receptionist', 'billing_staff']);

$page_title = "Insurance & Billing Setup";

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if (!$patient_id) {
    $_SESSION['error'] = "Patient ID is required.";
    header("Location: register.php");
    exit;
}

// Get patient info
try {
    $patient_query = "SELECT id, hospital_id, first_name, last_name FROM patients WHERE id = :id";
    $patient_stmt = $db->prepare($patient_query);
    $patient_stmt->bindParam(':id', $patient_id, PDO::PARAM_INT);
    $patient_stmt->execute();
    $patient = $patient_stmt->fetch();
    
    if (!$patient) {
        $_SESSION['error'] = "Patient not found.";
        header("Location: register.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching patient: " . $e->getMessage();
    header("Location: register.php");
    exit;
}

// Handle form submission
if ($_POST) {
    try {
        $insurance_provider_id = sanitizeInput($_POST['insurance_provider_id']);
        $policy_number = sanitizeInput($_POST['policy_number']);
        $policy_holder_name = sanitizeInput($_POST['policy_holder_name']);
        $relationship = sanitizeInput($_POST['relationship_to_patient']);
        $coverage_type = sanitizeInput($_POST['coverage_type']);
        $effective_date = sanitizeInput($_POST['effective_date']);
        $expiry_date = sanitizeInput($_POST['expiry_date']);
        $is_primary = isset($_POST['is_primary']) ? 1 : 0;
        
        // If setting as primary, unset other primary insurances
        if ($is_primary) {
            $unset_query = "UPDATE patient_insurance SET is_primary = 0 WHERE patient_id = :patient_id";
            $unset_stmt = $db->prepare($unset_query);
            $unset_stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
            $unset_stmt->execute();
        }
        
        $query = "INSERT INTO patient_insurance (
            patient_id, insurance_provider_id, policy_number, policy_holder_name,
            relationship_to_patient, coverage_type, effective_date, expiry_date, is_primary
        ) VALUES (
            :patient_id, :insurance_provider_id, :policy_number, :policy_holder_name,
            :relationship, :coverage_type, :effective_date, :expiry_date, :is_primary
        )";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $stmt->bindParam(':insurance_provider_id', $insurance_provider_id, PDO::PARAM_INT);
        $stmt->bindParam(':policy_number', $policy_number);
        $stmt->bindParam(':policy_holder_name', $policy_holder_name);
        $stmt->bindParam(':relationship', $relationship);
        $stmt->bindParam(':coverage_type', $coverage_type);
        $stmt->bindParam(':effective_date', $effective_date);
        $stmt->bindParam(':expiry_date', $expiry_date);
        $stmt->bindParam(':is_primary', $is_primary, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Insurance information added successfully!";
            header("Location: insurance_setup.php?patient_id=" . $patient_id);
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Get insurance providers
try {
    $providers_query = "SELECT * FROM insurance_providers WHERE is_active = 1 ORDER BY provider_name";
    $providers_stmt = $db->prepare($providers_query);
    $providers_stmt->execute();
    $providers = $providers_stmt->fetchAll();
} catch (PDOException $e) {
    $providers = [];
}

// Get existing insurance records
try {
    $insurance_query = "SELECT pi.*, ip.provider_name, ip.provider_type 
                       FROM patient_insurance pi
                       INNER JOIN insurance_providers ip ON pi.insurance_provider_id = ip.id
                       WHERE pi.patient_id = :patient_id AND pi.is_active = 1
                       ORDER BY pi.is_primary DESC, pi.created_at DESC";
    $insurance_stmt = $db->prepare($insurance_query);
    $insurance_stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
    $insurance_stmt->execute();
    $insurance_records = $insurance_stmt->fetchAll();
} catch (PDOException $e) {
    $insurance_records = [];
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Insurance & Billing Setup</h1>
    <p class="text-gray-600">Manage insurance information for <?php echo $patient['first_name'] . ' ' . $patient['last_name']; ?> (<?php echo $patient['hospital_id']; ?>)</p>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <!-- Insurance Form -->
    <div class="lg:col-span-2">
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add Insurance</h3>
                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="insurance_provider_id" class="block text-sm font-medium text-gray-700">Insurance Provider *</label>
                            <select name="insurance_provider_id" id="insurance_provider_id" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                <option value="">Select Provider</option>
                                <?php foreach ($providers as $provider): ?>
                                    <option value="<?php echo $provider['id']; ?>">
                                        <?php echo $provider['provider_name']; ?> (<?php echo ucfirst($provider['provider_type']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="policy_number" class="block text-sm font-medium text-gray-700">Policy Number *</label>
                            <input type="text" name="policy_number" id="policy_number" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        </div>

                        <div>
                            <label for="policy_holder_name" class="block text-sm font-medium text-gray-700">Policy Holder Name</label>
                            <input type="text" name="policy_holder_name" id="policy_holder_name"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        </div>

                        <div>
                            <label for="relationship_to_patient" class="block text-sm font-medium text-gray-700">Relationship to Patient</label>
                            <select name="relationship_to_patient" id="relationship_to_patient"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                <option value="self">Self</option>
                                <option value="spouse">Spouse</option>
                                <option value="parent">Parent</option>
                                <option value="child">Child</option>
                                <option value="sibling">Sibling</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label for="coverage_type" class="block text-sm font-medium text-gray-700">Coverage Type</label>
                            <input type="text" name="coverage_type" id="coverage_type"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                                placeholder="e.g., Inpatient, Outpatient, Emergency">
                        </div>

                        <div>
                            <label for="effective_date" class="block text-sm font-medium text-gray-700">Effective Date</label>
                            <input type="date" name="effective_date" id="effective_date"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        </div>

                        <div>
                            <label for="expiry_date" class="block text-sm font-medium text-gray-700">Expiry Date</label>
                            <input type="date" name="expiry_date" id="expiry_date"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="is_primary" value="1" class="text-primary-600 focus:ring-primary-500">
                                <span class="ml-2 text-sm text-gray-700">Set as Primary Insurance</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            Add Insurance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Existing Insurance Records -->
    <div class="lg:col-span-1">
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    Insurance Records
                </h3>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <?php if (count($insurance_records) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($insurance_records as $insurance): ?>
                            <div class="p-3 bg-gray-50 rounded-lg <?php echo $insurance['is_primary'] ? 'border-2 border-primary-300' : ''; ?>">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">
                                            <?php echo $insurance['provider_name']; ?>
                                            <?php if ($insurance['is_primary']): ?>
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary-100 text-primary-800">Primary</span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-xs text-gray-500"><?php echo $insurance['policy_number']; ?></p>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-600">
                                    <p>Type: <?php echo ucfirst($insurance['provider_type']); ?></p>
                                    <?php if ($insurance['expiry_date']): ?>
                                        <p>Expires: <?php echo formatDate($insurance['expiry_date']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path></svg>
                        <p class="text-gray-500">No insurance records</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="mt-6 flex justify-end space-x-3">
    <a href="view_patient.php?id=<?php echo $patient_id; ?>" 
       class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
        View Patient
    </a>
    <a href="register.php" 
       class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700">
        Register New Patient
    </a>
</div>

<?php include '../../includes/footer.php'; ?>



