<?php
/**
 * ER Patient Transfer/Discharge
 * Part of EERTS - Emergency and ER Triage System
 * Philippines Hospital Context
 */
require_once '../../config/config.php';
requireAuth();
checkRole(['admin', 'nurse', 'doctor']);

$page_title = "ER Transfer & Discharge";

// Handle transfer/discharge
if ($_POST) {
    try {
        $triage_id = (int)sanitizeInput($_POST['triage_id']);
        $action = sanitizeInput($_POST['action']); // 'transfer', 'discharge', 'admit'
        
        if ($action === 'transfer') {
            $transfer_to = sanitizeInput($_POST['transfer_to']);
            $transfer_notes = sanitizeInput($_POST['transfer_notes'] ?? '');
            
            $query = "UPDATE er_triage 
                     SET status = 'transferred', transferred_to = :transfer_to, updated_at = NOW()
                     WHERE id = :triage_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':transfer_to', $transfer_to);
            $stmt->bindParam(':triage_id', $triage_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $_SESSION['success'] = "Patient transferred successfully.";
        } elseif ($action === 'discharge') {
            $discharge_notes = sanitizeInput($_POST['discharge_notes'] ?? '');
            
            $query = "UPDATE er_triage 
                     SET status = 'discharged', discharged_at = NOW(), updated_at = NOW()
                     WHERE id = :triage_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':triage_id', $triage_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $_SESSION['success'] = "Patient discharged successfully.";
        } elseif ($action === 'admit') {
            // Create admission record
            $admission_number = generateAdmissionNumber();
            $bed_id = (int)sanitizeInput($_POST['bed_id']);
            $diagnosis = sanitizeInput($_POST['diagnosis'] ?? '');
            
            // Get patient_id from triage
            $triage_query = "SELECT patient_id FROM er_triage WHERE id = :triage_id";
            $triage_stmt = $db->prepare($triage_query);
            $triage_stmt->bindParam(':triage_id', $triage_id, PDO::PARAM_INT);
            $triage_stmt->execute();
            $triage_data = $triage_stmt->fetch();
            
            if ($triage_data) {
                $admission_query = "INSERT INTO admissions (
                    admission_number, patient_id, bed_id, admitting_doctor_id,
                    admission_date, admission_type, diagnosis, status
                ) VALUES (
                    :admission_number, :patient_id, :bed_id, :doctor_id,
                    NOW(), 'emergency', :diagnosis, 'admitted'
                )";
                
                $admission_stmt = $db->prepare($admission_query);
                $admission_stmt->bindParam(':admission_number', $admission_number);
                $admission_stmt->bindParam(':patient_id', $triage_data['patient_id'], PDO::PARAM_INT);
                $admission_stmt->bindParam(':bed_id', $bed_id, PDO::PARAM_INT);
                $admission_stmt->bindParam(':doctor_id', $_SESSION['user_id'], PDO::PARAM_INT);
                $admission_stmt->bindParam(':diagnosis', $diagnosis);
                $admission_stmt->execute();
                
                // Update bed status
                $bed_update = $db->prepare("UPDATE beds SET status = 'occupied', current_patient_id = :patient_id WHERE id = :bed_id");
                $bed_update->bindParam(':patient_id', $triage_data['patient_id'], PDO::PARAM_INT);
                $bed_update->bindParam(':bed_id', $bed_id, PDO::PARAM_INT);
                $bed_update->execute();
                
                // Update triage status
                $triage_update = $db->prepare("UPDATE er_triage SET status = 'admitted', updated_at = NOW() WHERE id = :triage_id");
                $triage_update->bindParam(':triage_id', $triage_id, PDO::PARAM_INT);
                $triage_update->execute();
                
                $_SESSION['success'] = "Patient admitted successfully! Admission #: " . $admission_number;
            }
        }
        
        header("Location: transfer.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Get active ER triage cases
try {
    $triage_query = "SELECT et.*, p.first_name, p.last_name, p.hospital_id,
                    u.first_name as nurse_fname, u.last_name as nurse_lname
                    FROM er_triage et
                    INNER JOIN patients p ON et.patient_id = p.id
                    LEFT JOIN users u ON et.triage_nurse_id = u.id
                    WHERE et.status IN ('waiting', 'in_progress')
                    ORDER BY et.priority_score ASC, et.created_at ASC";
    $triage_stmt = $db->prepare($triage_query);
    $triage_stmt->execute();
    $triage_cases = $triage_stmt->fetchAll();
} catch (PDOException $e) {
    $triage_cases = [];
}

// Get available beds for admission
try {
    $beds_query = "SELECT b.*, w.ward_name, w.ward_code
                   FROM beds b
                   INNER JOIN wards w ON b.ward_id = w.id
                   WHERE b.status = 'available'
                   ORDER BY w.ward_name, b.bed_number
                   LIMIT 50";
    $beds_stmt = $db->prepare($beds_query);
    $beds_stmt->execute();
    $available_beds = $beds_stmt->fetchAll();
} catch (PDOException $e) {
    $available_beds = [];
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">ER Transfer & Discharge</h1>
    <p class="text-gray-600">Manage patient disposition from Emergency Room</p>
</div>

<div class="bg-white shadow rounded-lg">
    <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
        <h3 class="text-lg leading-6 font-medium text-gray-900">Active ER Cases</h3>
    </div>
    <div class="px-4 py-5 sm:p-6">
        <?php if (count($triage_cases) > 0): ?>
            <div class="space-y-4">
                <?php foreach ($triage_cases as $case): ?>
                    <div class="border rounded-lg p-4 
                        <?php echo $case['triage_level'] == 'resuscitation' ? 'bg-red-50 border-red-200' : ''; ?>
                        <?php echo $case['triage_level'] == 'emergency' ? 'bg-orange-50 border-orange-200' : ''; ?>
                        <?php echo $case['triage_level'] == 'urgent' ? 'bg-yellow-50 border-yellow-200' : ''; ?>
                        <?php echo $case['triage_level'] == 'semi_urgent' ? 'bg-blue-50 border-blue-200' : ''; ?>
                        <?php echo $case['triage_level'] == 'non_urgent' ? 'bg-green-50 border-green-200' : ''; ?>">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex-1">
                                <h4 class="text-sm font-medium text-gray-900">
                                    <?php echo $case['first_name'] . ' ' . $case['last_name']; ?>
                                    (<?php echo $case['hospital_id']; ?>)
                                </h4>
                                <p class="text-xs text-gray-500 mt-1"><?php echo $case['chief_complaint']; ?></p>
                                <div class="mt-2 flex items-center space-x-4 text-xs text-gray-600">
                                    <span>Triage: <?php echo ucfirst(str_replace('_', ' ', $case['triage_level'])); ?></span>
                                    <span>Status: <?php echo ucfirst(str_replace('_', ' ', $case['status'])); ?></span>
                                    <?php if ($case['doctor_fname']): ?>
                                        <span>Dr. <?php echo $case['doctor_fname'] . ' ' . $case['doctor_lname']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                <?php echo getTriageColor($case['triage_level']) == 'red' ? 'bg-red-100 text-red-800' : ''; ?>
                                <?php echo getTriageColor($case['triage_level']) == 'orange' ? 'bg-orange-100 text-orange-800' : ''; ?>
                                <?php echo getTriageColor($case['triage_level']) == 'yellow' ? 'bg-yellow-100 text-yellow-800' : ''; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $case['triage_level'])); ?>
                            </span>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <!-- Discharge -->
                            <details class="group">
                                <summary class="cursor-pointer px-3 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 text-center">
                                    Discharge
                                </summary>
                                <div class="mt-3 p-4 bg-white border border-gray-200 rounded-lg">
                                    <form method="POST" class="space-y-3">
                                        <input type="hidden" name="action" value="discharge">
                                        <input type="hidden" name="triage_id" value="<?php echo $case['id']; ?>">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">Discharge Notes</label>
                                            <textarea name="discharge_notes" rows="3"
                                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm"
                                                placeholder="Discharge instructions..."></textarea>
                                        </div>
                                        <button type="submit"
                                            class="w-full px-3 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700">
                                            Confirm Discharge
                                        </button>
                                    </form>
                                </div>
                            </details>
                            
                            <!-- Transfer -->
                            <details class="group">
                                <summary class="cursor-pointer px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 text-center">
                                    Transfer
                                </summary>
                                <div class="mt-3 p-4 bg-white border border-gray-200 rounded-lg">
                                    <form method="POST" class="space-y-3">
                                        <input type="hidden" name="action" value="transfer">
                                        <input type="hidden" name="triage_id" value="<?php echo $case['id']; ?>">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">Transfer To</label>
                                            <input type="text" name="transfer_to" required
                                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm"
                                                placeholder="e.g., Ward 3, ICU, Another Hospital">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">Transfer Notes</label>
                                            <textarea name="transfer_notes" rows="2"
                                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm"
                                                placeholder="Reason for transfer..."></textarea>
                                        </div>
                                        <button type="submit"
                                            class="w-full px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                                            Confirm Transfer
                                        </button>
                                    </form>
                                </div>
                            </details>
                            
                            <!-- Admit -->
                            <details class="group">
                                <summary class="cursor-pointer px-3 py-2 bg-purple-600 text-white text-sm font-medium rounded-md hover:bg-purple-700 text-center">
                                    Admit
                                </summary>
                                <div class="mt-3 p-4 bg-white border border-gray-200 rounded-lg">
                                    <form method="POST" class="space-y-3">
                                        <input type="hidden" name="action" value="admit">
                                        <input type="hidden" name="triage_id" value="<?php echo $case['id']; ?>">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">Select Bed *</label>
                                            <select name="bed_id" required
                                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">
                                                <option value="">Select Bed</option>
                                                <?php foreach ($available_beds as $bed): ?>
                                                    <option value="<?php echo $bed['id']; ?>">
                                                        <?php echo $bed['ward_name']; ?> - Bed <?php echo $bed['bed_number']; ?>
                                                        (₱<?php echo number_format($bed['daily_rate'], 2); ?>/day)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">Diagnosis</label>
                                            <textarea name="diagnosis" rows="2"
                                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm"
                                                placeholder="Primary diagnosis..."></textarea>
                                        </div>
                                        <button type="submit"
                                            class="w-full px-3 py-2 bg-purple-600 text-white text-sm font-medium rounded-md hover:bg-purple-700">
                                            Confirm Admission
                                        </button>
                                    </form>
                                </div>
                            </details>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-8">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
                <p class="text-gray-500">No active ER cases</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>



