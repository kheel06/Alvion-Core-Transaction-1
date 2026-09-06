<?php
/**
 * E-Prescriptions Module
 * Part of TOCS - Telehealth and Outpatient Care System
 */
require_once '../../config/config.php';
requireAuth();
checkRole(['admin', 'doctor', 'pharmacist']);

$page_title = "E-Prescriptions";

// Handle prescription creation (doctor)
if ($_POST && isset($_POST['create_prescription'])) {
    try {
        $prescription_number = 'RX' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $patient_id = (int)sanitizeInput($_POST['patient_id']);
        $doctor_id = $_SESSION['user_id'];
        $consultation_id = !empty($_POST['consultation_id']) ? (int)sanitizeInput($_POST['consultation_id']) : null;
        $appointment_id = !empty($_POST['appointment_id']) ? (int)sanitizeInput($_POST['appointment_id']) : null;
        $prescription_date = date('Y-m-d');
        
        // Parse medications from JSON or array
        $medications = [];
        if (isset($_POST['medications']) && is_array($_POST['medications'])) {
            foreach ($_POST['medications'] as $med) {
                $medications[] = [
                    'name' => sanitizeInput($med['name']),
                    'dosage' => sanitizeInput($med['dosage']),
                    'frequency' => sanitizeInput($med['frequency']),
                    'duration' => sanitizeInput($med['duration']),
                    'quantity' => sanitizeInput($med['quantity']),
                    'instructions' => sanitizeInput($med['instructions'] ?? '')
                ];
            }
        }
        
        $instructions = sanitizeInput($_POST['instructions'] ?? '');
        
        $query = "INSERT INTO e_prescriptions (
            prescription_number, patient_id, doctor_id, consultation_id, appointment_id,
            prescription_date, medications, instructions, status
        ) VALUES (
            :prescription_number, :patient_id, :doctor_id, :consultation_id, :appointment_id,
            :prescription_date, :medications, :instructions, 'pending'
        )";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':prescription_number', $prescription_number);
        $stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
        $stmt->bindValue(':consultation_id', $consultation_id, $consultation_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':appointment_id', $appointment_id, $appointment_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindParam(':prescription_date', $prescription_date);
        $stmt->bindParam(':medications', json_encode($medications));
        $stmt->bindParam(':instructions', $instructions);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Prescription created successfully! Prescription #: " . $prescription_number;
            header("Location: e_prescriptions.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error creating prescription: " . $e->getMessage();
    }
}

// Handle prescription filling (pharmacist)
if ($_POST && isset($_POST['fill_prescription'])) {
    try {
        $prescription_id = (int)sanitizeInput($_POST['prescription_id']);
        $status = sanitizeInput($_POST['status']);
        
        $query = "UPDATE e_prescriptions 
                 SET status = :status, filled_at = NOW(), filled_by = :filled_by 
                 WHERE id = :prescription_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':filled_by', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->bindParam(':prescription_id', $prescription_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Prescription status updated successfully!";
            header("Location: e_prescriptions.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating prescription: " . $e->getMessage();
    }
}

// Get prescriptions based on role
$role_name = $_SESSION['role_name'] ?? '';

try {
    if ($role_name == 'pharmacist') {
        // Pharmacist sees pending prescriptions
        $prescriptions_query = "SELECT ep.*, p.first_name, p.last_name, p.hospital_id,
                               u.first_name as doctor_fname, u.last_name as doctor_lname
                               FROM e_prescriptions ep
                               INNER JOIN patients p ON ep.patient_id = p.id
                               INNER JOIN users u ON ep.doctor_id = u.id
                               WHERE ep.status = 'pending'
                               ORDER BY ep.prescription_date DESC, ep.created_at DESC";
        $prescriptions_stmt = $db->prepare($prescriptions_query);
        $prescriptions_stmt->execute();
    } elseif ($role_name == 'admin') {
        // Admin sees all prescriptions
        $prescriptions_query = "SELECT ep.*, p.first_name, p.last_name, p.hospital_id,
                               u.first_name as doctor_fname, u.last_name as doctor_lname
                               FROM e_prescriptions ep
                               INNER JOIN patients p ON ep.patient_id = p.id
                               INNER JOIN users u ON ep.doctor_id = u.id
                               ORDER BY ep.prescription_date DESC, ep.created_at DESC";
        $prescriptions_stmt = $db->prepare($prescriptions_query);
        $prescriptions_stmt->execute();
    } else {
        // Doctor sees prescriptions they created
        $prescriptions_query = "SELECT ep.*, p.first_name, p.last_name, p.hospital_id,
                               u.first_name as doctor_fname, u.last_name as doctor_lname
                               FROM e_prescriptions ep
                               INNER JOIN patients p ON ep.patient_id = p.id
                               INNER JOIN users u ON ep.doctor_id = u.id
                               WHERE ep.doctor_id = :doctor_id
                               ORDER BY ep.prescription_date DESC, ep.created_at DESC";
        $prescriptions_stmt = $db->prepare($prescriptions_query);
        $prescriptions_stmt->bindParam(':doctor_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $prescriptions_stmt->execute();
    }
    $prescriptions = $prescriptions_stmt->fetchAll();
} catch (PDOException $e) {
    $prescriptions = [];
}

// Get patients for dropdown (doctor only)
$patients = [];
if ($role_name == 'doctor' || $role_name == 'admin') {
    try {
        $patients_query = "SELECT id, hospital_id, first_name, last_name FROM patients WHERE status = 'active' ORDER BY first_name, last_name";
        $patients_stmt = $db->prepare($patients_query);
        $patients_stmt->execute();
        $patients = $patients_stmt->fetchAll();
    } catch (PDOException $e) {
        $patients = [];
    }
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">E-Prescriptions</h1>
    <p class="text-gray-600"><?php echo $role_name == 'pharmacist' ? 'Process pending prescriptions' : 'Manage electronic prescriptions'; ?></p>
</div>

<?php if ($role_name == 'doctor' || $role_name == 'admin'): ?>
<!-- Create Prescription Form -->
<div class="mb-6 bg-white shadow rounded-lg">
    <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Create New Prescription</h3>
        <form method="POST" id="prescriptionForm">
            <input type="hidden" name="create_prescription" value="1">
            
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
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
            </div>
            
            <div class="mt-6">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="text-sm font-medium text-gray-900">Medications</h4>
                    <button type="button" onclick="addMedication()" 
                        class="text-sm text-primary-600 hover:text-primary-700">
                        + Add Medication
                    </button>
                </div>
                <div id="medicationsContainer" class="space-y-4">
                    <!-- Medications will be added here dynamically -->
                </div>
            </div>
            
            <div class="mt-6">
                <label for="instructions" class="block text-sm font-medium text-gray-700">General Instructions</label>
                <textarea name="instructions" id="instructions" rows="3"
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                    placeholder="Additional instructions for the patient..."></textarea>
            </div>
            
            <div class="mt-6 flex justify-end">
                <button type="submit"
                    class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700">
                    Create Prescription
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Prescriptions List -->
<div class="bg-white shadow rounded-lg">
    <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">
            <?php echo $role_name == 'pharmacist' ? 'Pending Prescriptions' : 'Prescriptions'; ?>
        </h3>
        
        <?php if (count($prescriptions) > 0): ?>
            <div class="space-y-4">
                <?php foreach ($prescriptions as $prescription): ?>
                    <div class="border rounded-lg p-4">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    Prescription #<?php echo $prescription['prescription_number']; ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    Patient: <?php echo $prescription['first_name'] . ' ' . $prescription['last_name']; ?> 
                                    (<?php echo $prescription['hospital_id']; ?>)
                                </p>
                                <p class="text-xs text-gray-500">
                                    Doctor: Dr. <?php echo $prescription['doctor_fname'] . ' ' . $prescription['doctor_lname']; ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    Date: <?php echo formatDate($prescription['prescription_date']); ?>
                                </p>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                <?php echo $prescription['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : ''; ?>
                                <?php echo $prescription['status'] == 'filled' ? 'bg-green-100 text-green-800' : ''; ?>
                                <?php echo $prescription['status'] == 'partially_filled' ? 'bg-blue-100 text-blue-800' : ''; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $prescription['status'])); ?>
                            </span>
                        </div>
                        
                        <div class="mt-4">
                            <h5 class="text-sm font-medium text-gray-700 mb-2">Medications:</h5>
                            <?php 
                            $medications = json_decode($prescription['medications'], true);
                            if (is_array($medications)): 
                            ?>
                                <ul class="list-disc list-inside space-y-1 text-sm text-gray-600">
                                    <?php foreach ($medications as $med): ?>
                                        <li>
                                            <strong><?php echo $med['name']; ?></strong> - 
                                            <?php echo $med['dosage']; ?> 
                                            <?php echo $med['frequency']; ?> 
                                            for <?php echo $med['duration']; ?>
                                            (Qty: <?php echo $med['quantity']; ?>)
                                            <?php if (!empty($med['instructions'])): ?>
                                                <br><span class="text-xs text-gray-500"><?php echo $med['instructions']; ?></span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            
                            <?php if (!empty($prescription['instructions'])): ?>
                                <div class="mt-3 p-2 bg-gray-50 rounded">
                                    <p class="text-xs font-medium text-gray-700">General Instructions:</p>
                                    <p class="text-xs text-gray-600"><?php echo $prescription['instructions']; ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($role_name == 'pharmacist' && $prescription['status'] == 'pending'): ?>
                            <div class="mt-4 pt-4 border-t">
                                <form method="POST" class="flex items-center space-x-3">
                                    <input type="hidden" name="fill_prescription" value="1">
                                    <input type="hidden" name="prescription_id" value="<?php echo $prescription['id']; ?>">
                                    <select name="status" required
                                        class="block border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                        <option value="filled">Mark as Filled</option>
                                        <option value="partially_filled">Partially Filled</option>
                                        <option value="cancelled">Cancel</option>
                                    </select>
                                    <button type="submit"
                                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700">
                                        Update Status
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-8">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                <p class="text-gray-500">No prescriptions found</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($role_name == 'doctor' || $role_name == 'admin'): ?>
<script>
let medicationCount = 0;

function addMedication() {
    medicationCount++;
    const container = document.getElementById('medicationsContainer');
    const div = document.createElement('div');
    div.className = 'border rounded-lg p-4 bg-gray-50';
    div.innerHTML = `
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="block text-xs font-medium text-gray-700">Medication Name *</label>
                <input type="text" name="medications[${medicationCount}][name]" required
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700">Dosage *</label>
                <input type="text" name="medications[${medicationCount}][dosage]" required
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm"
                    placeholder="e.g., 500mg">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700">Frequency *</label>
                <input type="text" name="medications[${medicationCount}][frequency]" required
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm"
                    placeholder="e.g., 2x daily">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700">Duration *</label>
                <input type="text" name="medications[${medicationCount}][duration]" required
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm"
                    placeholder="e.g., 7 days">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700">Quantity *</label>
                <input type="text" name="medications[${medicationCount}][quantity]" required
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm"
                    placeholder="e.g., 14 tablets">
            </div>
            <div class="sm:col-span-2 lg:col-span-1">
                <label class="block text-xs font-medium text-gray-700">Special Instructions</label>
                <input type="text" name="medications[${medicationCount}][instructions]"
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm"
                    placeholder="e.g., Take with food">
            </div>
        </div>
        <button type="button" onclick="this.parentElement.remove()" 
            class="mt-2 text-xs text-red-600 hover:text-red-700">
            Remove
        </button>
    `;
    container.appendChild(div);
}

// Add first medication field on load
document.addEventListener('DOMContentLoaded', function() {
    addMedication();
});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
