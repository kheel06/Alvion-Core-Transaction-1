<?php
/**
 * Insurance Management
 * Part of Billing System
 * Philippines Hospital Context
 */
require_once '../../config/config.php';
requireAuth();
checkRole(['admin', 'billing_staff']);

$page_title = "Insurance Management";

// Handle insurance operations
if ($_POST) {
    try {
        $action = sanitizeInput($_POST['action']);
        
        if ($action === 'add') {
            $patient_id = (int)sanitizeInput($_POST['patient_id']);
            $insurance_provider_id = (int)sanitizeInput($_POST['insurance_provider_id']);
            $policy_number = sanitizeInput($_POST['policy_number']);
            $group_number = sanitizeInput($_POST['group_number'] ?? '');
            $coverage_type = sanitizeInput($_POST['coverage_type']);
            $valid_from = sanitizeInput($_POST['valid_from']);
            $valid_to = sanitizeInput($_POST['valid_to'] ?? null);
            $is_primary = isset($_POST['is_primary']) ? 1 : 0;
            
            // If this is primary, unset other primary insurances for this patient
            if ($is_primary) {
                $unset_primary = $db->prepare("UPDATE patient_insurance SET is_primary = 0 WHERE patient_id = :patient_id");
                $unset_primary->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
                $unset_primary->execute();
            }
            
            $insert_query = "INSERT INTO patient_insurance (
                patient_id, insurance_provider_id, policy_number, group_number,
                coverage_type, valid_from, valid_to, is_primary, is_active
            ) VALUES (
                :patient_id, :insurance_provider_id, :policy_number, :group_number,
                :coverage_type, :valid_from, :valid_to, :is_primary, 1
            )";
            
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
            $insert_stmt->bindParam(':insurance_provider_id', $insurance_provider_id, PDO::PARAM_INT);
            $insert_stmt->bindParam(':policy_number', $policy_number);
            $insert_stmt->bindParam(':group_number', $group_number);
            $insert_stmt->bindParam(':coverage_type', $coverage_type);
            $insert_stmt->bindParam(':valid_from', $valid_from);
            $insert_stmt->bindValue(':valid_to', $valid_to ?: null, $valid_to ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $insert_stmt->bindParam(':is_primary', $is_primary, PDO::PARAM_INT);
            $insert_stmt->execute();
            
            $_SESSION['success'] = "Insurance added successfully.";
        } elseif ($action === 'update') {
            $insurance_id = (int)sanitizeInput($_POST['insurance_id']);
            $policy_number = sanitizeInput($_POST['policy_number']);
            $group_number = sanitizeInput($_POST['group_number'] ?? '');
            $coverage_type = sanitizeInput($_POST['coverage_type']);
            $valid_from = sanitizeInput($_POST['valid_from']);
            $valid_to = sanitizeInput($_POST['valid_to'] ?? null);
            $is_primary = isset($_POST['is_primary']) ? 1 : 0;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // Get patient_id from insurance record
            $get_patient = $db->prepare("SELECT patient_id FROM patient_insurance WHERE id = :insurance_id");
            $get_patient->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);
            $get_patient->execute();
            $insurance_data = $get_patient->fetch();
            
            if ($insurance_data && $is_primary) {
                $unset_primary = $db->prepare("UPDATE patient_insurance SET is_primary = 0 WHERE patient_id = :patient_id AND id != :insurance_id");
                $unset_primary->bindParam(':patient_id', $insurance_data['patient_id'], PDO::PARAM_INT);
                $unset_primary->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);
                $unset_primary->execute();
            }
            
            $update_query = "UPDATE patient_insurance SET
                policy_number = :policy_number, group_number = :group_number,
                coverage_type = :coverage_type, valid_from = :valid_from, valid_to = :valid_to,
                is_primary = :is_primary, is_active = :is_active, updated_at = NOW()
                WHERE id = :insurance_id";
            
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':policy_number', $policy_number);
            $update_stmt->bindParam(':group_number', $group_number);
            $update_stmt->bindParam(':coverage_type', $coverage_type);
            $update_stmt->bindParam(':valid_from', $valid_from);
            $update_stmt->bindValue(':valid_to', $valid_to ?: null, $valid_to ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $update_stmt->bindParam(':is_primary', $is_primary, PDO::PARAM_INT);
            $update_stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
            $update_stmt->bindParam(':insurance_id', $insurance_id, PDO::PARAM_INT);
            $update_stmt->execute();
            
            $_SESSION['success'] = "Insurance updated successfully.";
        }
        
        header("Location: insurance.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Get patients
try {
    $patients_query = "SELECT id, hospital_id, first_name, last_name FROM patients WHERE status = 'active' ORDER BY first_name, last_name";
    $patients_stmt = $db->prepare($patients_query);
    $patients_stmt->execute();
    $patients = $patients_stmt->fetchAll();
} catch (PDOException $e) {
    $patients = [];
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

// Get all insurance records
try {
    $insurance_query = "SELECT pi.*, p.first_name, p.last_name, p.hospital_id,
                       ip.provider_name, ip.provider_type
                       FROM patient_insurance pi
                       INNER JOIN patients p ON pi.patient_id = p.id
                       INNER JOIN insurance_providers ip ON pi.insurance_provider_id = ip.id
                       ORDER BY pi.created_at DESC
                       LIMIT 50";
    $insurance_stmt = $db->prepare($insurance_query);
    $insurance_stmt->execute();
    $insurance_records = $insurance_stmt->fetchAll();
} catch (PDOException $e) {
    $insurance_records = [];
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Insurance Management</h1>
            <p class="text-gray-600">Manage patient insurance coverage</p>
        </div>
        <button onclick="document.getElementById('addInsuranceForm').classList.toggle('hidden')"
            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>Add Insurance
        </button>
    </div>
</div>

<!-- Add Insurance Form -->
<div id="addInsuranceForm" class="hidden mb-6 bg-white shadow rounded-lg">
    <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Add New Insurance</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add">
            
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
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
                    <label for="insurance_provider_id" class="block text-sm font-medium text-gray-700">Insurance Provider *</label>
                    <select name="insurance_provider_id" id="insurance_provider_id" required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Select Provider</option>
                        <?php foreach ($providers as $provider): ?>
                            <option value="<?php echo $provider['id']; ?>">
                                <?php echo $provider['provider_name']; ?> - <?php echo ucfirst($provider['provider_type']); ?>
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
                    <label for="group_number" class="block text-sm font-medium text-gray-700">Group Number</label>
                    <input type="text" name="group_number" id="group_number"
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                </div>

                <div>
                    <label for="coverage_type" class="block text-sm font-medium text-gray-700">Coverage Type *</label>
                    <select name="coverage_type" id="coverage_type" required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        <option value="full">Full Coverage</option>
                        <option value="partial">Partial Coverage</option>
                        <option value="co_pay">Co-pay</option>
                    </select>
                </div>

                <div>
                    <label for="valid_from" class="block text-sm font-medium text-gray-700">Valid From *</label>
                    <input type="date" name="valid_from" id="valid_from" required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                </div>

                <div>
                    <label for="valid_to" class="block text-sm font-medium text-gray-700">Valid To</label>
                    <input type="date" name="valid_to" id="valid_to"
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="is_primary" id="is_primary" value="1"
                        class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                    <label for="is_primary" class="ml-2 block text-sm text-gray-700">
                        Set as Primary Insurance
                    </label>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit"
                    class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700">
                    Add Insurance
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Insurance Records -->
<div class="bg-white shadow rounded-lg">
    <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
        <h3 class="text-lg leading-6 font-medium text-gray-900">Insurance Records</h3>
    </div>
    <div class="px-4 py-5 sm:p-6">
        <?php if (count($insurance_records) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provider</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Policy Number</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Coverage</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Validity</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($insurance_records as $insurance): ?>
                            <tr>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo $insurance['first_name'] . ' ' . $insurance['last_name']; ?>
                                    </div>
                                    <div class="text-xs text-gray-500"><?php echo $insurance['hospital_id']; ?></div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?php echo $insurance['provider_name']; ?>
                                    <div class="text-xs text-gray-500"><?php echo ucfirst($insurance['provider_type']); ?></div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?php echo $insurance['policy_number']; ?>
                                    <?php if ($insurance['group_number']): ?>
                                        <div class="text-xs text-gray-500">Group: <?php echo $insurance['group_number']; ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?php echo ucfirst(str_replace('_', ' ', $insurance['coverage_type'])); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <div><?php echo formatDate($insurance['valid_from']); ?></div>
                                    <?php if ($insurance['valid_to']): ?>
                                        <div class="text-xs text-gray-500">to <?php echo formatDate($insurance['valid_to']); ?></div>
                                    <?php else: ?>
                                        <div class="text-xs text-gray-500">No expiry</div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <?php if ($insurance['is_primary']): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800">
                                            Primary
                                        </span>
                                    <?php endif; ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        <?php echo $insurance['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo $insurance['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="editInsurance(<?php echo htmlspecialchars(json_encode($insurance)); ?>)"
                                        class="text-primary-600 hover:text-primary-900">
                                        Edit
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-8">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path></svg>
                <p class="text-gray-500">No insurance records</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function editInsurance(insurance) {
    // Populate form and show edit form
    document.getElementById('addInsuranceForm').classList.remove('hidden');
    document.querySelector('#addInsuranceForm input[name="action"]').value = 'update';
    document.querySelector('#addInsuranceForm input[name="insurance_id"]')?.remove();
    
    const form = document.querySelector('#addInsuranceForm form');
    const insuranceIdInput = document.createElement('input');
    insuranceIdInput.type = 'hidden';
    insuranceIdInput.name = 'insurance_id';
    insuranceIdInput.value = insurance.id;
    form.appendChild(insuranceIdInput);
    
    // Populate fields
    document.getElementById('patient_id').value = insurance.patient_id;
    document.getElementById('insurance_provider_id').value = insurance.insurance_provider_id;
    document.getElementById('policy_number').value = insurance.policy_number;
    document.getElementById('group_number').value = insurance.group_number || '';
    document.getElementById('coverage_type').value = insurance.coverage_type;
    document.getElementById('valid_from').value = insurance.valid_from;
    document.getElementById('valid_to').value = insurance.valid_to || '';
    document.getElementById('is_primary').checked = insurance.is_primary == 1;
    
    // Scroll to form
    document.getElementById('addInsuranceForm').scrollIntoView({ behavior: 'smooth' });
}
</script>

<?php include '../../includes/footer.php'; ?>



