<?php
/**
 * Digital Consent Forms
 * Part of SPRS - Smart Patient Registration System
 */
require_once '../../config/config.php';
requireAuth();
checkRole(['admin', 'receptionist', 'doctor', 'nurse']);

$page_title = "Digital Consent Forms";

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
        $consent_form_id = sanitizeInput($_POST['consent_form_id']);
        $signed_by_patient = isset($_POST['signed_by_patient']) ? 1 : 0;
        $signed_by_guardian = isset($_POST['signed_by_guardian']) ? 1 : 0;
        $guardian_name = sanitizeInput($_POST['guardian_name'] ?? '');
        $guardian_relationship = sanitizeInput($_POST['guardian_relationship'] ?? '');
        
        // Check if consent already exists
        $check_query = "SELECT id FROM patient_consents WHERE patient_id = :patient_id AND consent_form_id = :consent_form_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $check_stmt->bindParam(':consent_form_id', $consent_form_id, PDO::PARAM_INT);
        $check_stmt->execute();
        $existing = $check_stmt->fetch();
        
        if ($existing) {
            $_SESSION['error'] = "This consent form has already been signed by this patient.";
        } else {
            $query = "INSERT INTO patient_consents (
                patient_id, consent_form_id, signed_by_patient, signed_by_guardian,
                guardian_name, guardian_relationship, ip_address, signed_at
            ) VALUES (
                :patient_id, :consent_form_id, :signed_by_patient, :signed_by_guardian,
                :guardian_name, :guardian_relationship, :ip_address, NOW()
            )";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
            $stmt->bindParam(':consent_form_id', $consent_form_id, PDO::PARAM_INT);
            $stmt->bindParam(':signed_by_patient', $signed_by_patient, PDO::PARAM_INT);
            $stmt->bindParam(':signed_by_guardian', $signed_by_guardian, PDO::PARAM_INT);
            $stmt->bindParam(':guardian_name', $guardian_name);
            $stmt->bindParam(':guardian_relationship', $guardian_relationship);
            $stmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Consent form recorded successfully!";
                header("Location: consent_forms.php?patient_id=" . $patient_id);
                exit;
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Get available consent forms
try {
    $forms_query = "SELECT * FROM consent_forms WHERE is_active = 1 ORDER BY form_type, form_name";
    $forms_stmt = $db->prepare($forms_query);
    $forms_stmt->execute();
    $consent_forms = $forms_stmt->fetchAll();
} catch (PDOException $e) {
    $consent_forms = [];
}

// Get existing consents
try {
    $consents_query = "SELECT pc.*, cf.form_name, cf.form_type 
                      FROM patient_consents pc
                      INNER JOIN consent_forms cf ON pc.consent_form_id = cf.id
                      WHERE pc.patient_id = :patient_id
                      ORDER BY pc.signed_at DESC";
    $consents_stmt = $db->prepare($consents_query);
    $consents_stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
    $consents_stmt->execute();
    $existing_consents = $consents_stmt->fetchAll();
} catch (PDOException $e) {
    $existing_consents = [];
}

// Create array of already signed form IDs
$signed_form_ids = array_column($existing_consents, 'consent_form_id');

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Digital Consent Forms</h1>
    <p class="text-gray-600">Manage consent forms for <?php echo $patient['first_name'] . ' ' . $patient['last_name']; ?> (<?php echo $patient['hospital_id']; ?>)</p>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <!-- Consent Forms List -->
    <div class="lg:col-span-2">
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Available Consent Forms</h3>
                
                <?php if (count($consent_forms) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($consent_forms as $form): ?>
                            <div class="border rounded-lg p-4 <?php echo in_array($form['id'], $signed_form_ids) ? 'bg-green-50 border-green-200' : 'bg-white border-gray-200'; ?>">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-900"><?php echo $form['form_name']; ?></h4>
                                        <p class="text-xs text-gray-500 mt-1">Type: <?php echo ucfirst(str_replace('_', ' ', $form['form_type'])); ?></p>
                                    </div>
                                    <?php if (in_array($form['id'], $signed_form_ids)): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Signed
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="text-sm text-gray-600 mb-4">
                                    <p><?php echo $form['form_content']; ?></p>
                                </div>
                                
                                <?php if (!in_array($form['id'], $signed_form_ids)): ?>
                                    <form method="POST" class="mt-4">
                                        <input type="hidden" name="consent_form_id" value="<?php echo $form['id']; ?>">
                                        
                                        <div class="space-y-3">
                                            <label class="inline-flex items-center">
                                                <input type="checkbox" name="signed_by_patient" value="1" class="text-primary-600 focus:ring-primary-500">
                                                <span class="ml-2 text-sm text-gray-700">Signed by Patient</span>
                                            </label>
                                            
                                            <label class="inline-flex items-center">
                                                <input type="checkbox" name="signed_by_guardian" value="1" class="text-primary-600 focus:ring-primary-500" id="guardian_check_<?php echo $form['id']; ?>" onchange="toggleGuardianFields(<?php echo $form['id']; ?>)">
                                                <span class="ml-2 text-sm text-gray-700">Signed by Guardian</span>
                                            </label>
                                            
                                            <div id="guardian_fields_<?php echo $form['id']; ?>" class="hidden grid grid-cols-2 gap-3">
                                                <div>
                                                    <label for="guardian_name_<?php echo $form['id']; ?>" class="block text-xs font-medium text-gray-700">Guardian Name</label>
                                                    <input type="text" name="guardian_name" id="guardian_name_<?php echo $form['id']; ?>"
                                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-1 px-2 text-sm">
                                                </div>
                                                <div>
                                                    <label for="guardian_relationship_<?php echo $form['id']; ?>" class="block text-xs font-medium text-gray-700">Relationship</label>
                                                    <input type="text" name="guardian_relationship" id="guardian_relationship_<?php echo $form['id']; ?>"
                                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-1 px-2 text-sm">
                                                </div>
                                            </div>
                                            
                                            <button type="submit"
                                                class="w-full px-3 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700">
                                                Record Consent
                                            </button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M10 18h4"></path><path d="M12 16v4"></path></svg>
                        <p class="text-gray-500">No consent forms available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Signed Consents -->
    <div class="lg:col-span-1">
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    Signed Consents
                </h3>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <?php if (count($existing_consents) > 0): ?>
                    <div class="space-y-3">
                        <?php foreach ($existing_consents as $consent): ?>
                            <div class="p-3 bg-green-50 rounded-lg border border-green-200">
                                <p class="text-sm font-medium text-gray-900"><?php echo $consent['form_name']; ?></p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?php echo formatDate($consent['signed_at'], 'M j, Y g:i A'); ?>
                                </p>
                                <div class="mt-2 text-xs text-gray-600">
                                    <?php if ($consent['signed_by_patient']): ?>
                                        <span class="inline-block px-2 py-0.5 bg-blue-100 text-blue-800 rounded">Patient</span>
                                    <?php endif; ?>
                                    <?php if ($consent['signed_by_guardian']): ?>
                                        <span class="inline-block px-2 py-0.5 bg-purple-100 text-purple-800 rounded ml-1">
                                            Guardian: <?php echo $consent['guardian_name']; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M9 15l2 2 4-4"></path></svg>
                        <p class="text-gray-500">No signed consents</p>
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
</div>

<script>
function toggleGuardianFields(formId) {
    const checkbox = document.getElementById('guardian_check_' + formId);
    const fields = document.getElementById('guardian_fields_' + formId);
    if (checkbox.checked) {
        fields.classList.remove('hidden');
    } else {
        fields.classList.add('hidden');
    }
}
</script>

<?php include '../../includes/footer.php'; ?>



