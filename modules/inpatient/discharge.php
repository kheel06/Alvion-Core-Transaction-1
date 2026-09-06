<?php
/**
 * Patient Discharge
 * Part of IBMS - Inpatient and Bed Management System
 * Philippines Hospital Context
 */
require_once '../../config/config.php';
requireAuth();
checkRole(['admin', 'doctor', 'nurse']);

$page_title = "Patient Discharge";

// Handle discharge
if ($_POST) {
    try {
        $admission_id = (int)sanitizeInput($_POST['admission_id']);
        $discharge_summary = sanitizeInput($_POST['discharge_summary'] ?? '');
        
        // Get admission details
        $admission_query = "SELECT ad.*, b.id as bed_id FROM admissions ad
                          INNER JOIN beds b ON ad.bed_id = b.id
                          WHERE ad.id = :admission_id AND ad.status = 'admitted'";
        $admission_stmt = $db->prepare($admission_query);
        $admission_stmt->bindParam(':admission_id', $admission_id, PDO::PARAM_INT);
        $admission_stmt->execute();
        $admission = $admission_stmt->fetch();
        
        if (!$admission) {
            throw new Exception("Admission not found or already discharged.");
        }
        
        // Update admission
        $discharge_query = "UPDATE admissions 
                           SET status = 'discharged', discharge_date = NOW(), discharge_summary = :summary
                           WHERE id = :admission_id";
        $discharge_stmt = $db->prepare($discharge_query);
        $discharge_stmt->bindParam(':summary', $discharge_summary);
        $discharge_stmt->bindParam(':admission_id', $admission_id, PDO::PARAM_INT);
        $discharge_stmt->execute();
        
        // Update bed status
        $bed_update = $db->prepare("UPDATE beds SET status = 'available', current_patient_id = NULL, cleaning_status = 'dirty' WHERE id = :bed_id");
        $bed_update->bindParam(':bed_id', $admission['bed_id'], PDO::PARAM_INT);
        $bed_update->execute();
        
        // Update ward occupancy
        $ward_update = $db->prepare("UPDATE wards w
                                    INNER JOIN beds b ON w.id = b.ward_id
                                    SET w.current_occupancy = (
                                        SELECT COUNT(*) FROM beds WHERE ward_id = w.id AND status = 'occupied'
                                    )
                                    WHERE b.id = :bed_id");
        $ward_update->bindParam(':bed_id', $admission['bed_id'], PDO::PARAM_INT);
        $ward_update->execute();
        
        // Create housekeeping task for bed cleaning
        $housekeeping_query = "INSERT INTO housekeeping_tasks (bed_id, task_type, status, scheduled_at)
                              VALUES (:bed_id, 'cleaning', 'pending', NOW())";
        $housekeeping_stmt = $db->prepare($housekeeping_query);
        $housekeeping_stmt->bindParam(':bed_id', $admission['bed_id'], PDO::PARAM_INT);
        $housekeeping_stmt->execute();
        
        $_SESSION['success'] = "Patient discharged successfully! Bed marked for cleaning.";
        header("Location: discharge.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error processing discharge: " . $e->getMessage();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Get active admissions
try {
    $admissions_query = "SELECT ad.*, p.first_name, p.last_name, p.hospital_id, 
                       w.ward_name, b.bed_number, b.bed_type,
                       u.first_name as doctor_fname, u.last_name as doctor_lname
                       FROM admissions ad
                       INNER JOIN patients p ON ad.patient_id = p.id
                       INNER JOIN beds b ON ad.bed_id = b.id
                       INNER JOIN wards w ON b.ward_id = w.id
                       LEFT JOIN users u ON ad.admitting_doctor_id = u.id
                       WHERE ad.status = 'admitted'
                       ORDER BY ad.admission_date ASC";
    $admissions_stmt = $db->prepare($admissions_query);
    $admissions_stmt->execute();
    $active_admissions = $admissions_stmt->fetchAll();
} catch (PDOException $e) {
    $active_admissions = [];
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Patient Discharge</h1>
    <p class="text-gray-600">Discharge patients and prepare beds for cleaning</p>
</div>

<div class="bg-white shadow rounded-lg">
    <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
        <h3 class="text-lg leading-6 font-medium text-gray-900">Active Admissions</h3>
        <p class="mt-1 text-sm text-gray-500"><?php echo count($active_admissions); ?> patients currently admitted</p>
    </div>
    <div class="px-4 py-5 sm:p-6">
        <?php if (count($active_admissions) > 0): ?>
            <div class="space-y-4">
                <?php foreach ($active_admissions as $adm): ?>
                    <div class="border rounded-lg p-4 bg-gray-50">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex-1">
                                <h4 class="text-sm font-medium text-gray-900">
                                    <?php echo $adm['first_name'] . ' ' . $adm['last_name']; ?>
                                    (<?php echo $adm['hospital_id']; ?>)
                                </h4>
                                <p class="text-xs text-gray-500 mt-1">
                                    Admission #<?php echo $adm['admission_number']; ?> • 
                                    <?php echo formatDate($adm['admission_date']); ?>
                                </p>
                                <div class="mt-2 flex items-center space-x-4 text-xs text-gray-600">
                                    <span><?php echo $adm['ward_name']; ?> - Bed <?php echo $adm['bed_number']; ?></span>
                                    <span><?php echo ucfirst($adm['bed_type']); ?></span>
                                    <?php if ($adm['doctor_fname']): ?>
                                        <span>Dr. <?php echo $adm['doctor_fname'] . ' ' . $adm['doctor_lname']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($adm['diagnosis']): ?>
                                    <p class="text-xs text-gray-600 mt-2">Diagnosis: <?php echo $adm['diagnosis']; ?></p>
                                <?php endif; ?>
                                
                                <?php
                                // Calculate length of stay
                                $admission_date = new DateTime($adm['admission_date']);
                                $today = new DateTime();
                                $los = $admission_date->diff($today);
                                ?>
                                <p class="text-xs text-gray-500 mt-2">
                                    Length of Stay: <?php echo $los->days; ?> day(s)
                                </p>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                Admitted
                            </span>
                        </div>
                        
                        <!-- Discharge Form -->
                        <details class="group">
                            <summary class="cursor-pointer px-3 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 text-center">
                                Discharge Patient
                            </summary>
                            <div class="mt-3 p-4 bg-white border border-gray-200 rounded-lg">
                                <form method="POST" class="space-y-3">
                                    <input type="hidden" name="admission_id" value="<?php echo $adm['id']; ?>">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Discharge Summary *</label>
                                        <textarea name="discharge_summary" rows="4" required
                                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm"
                                            placeholder="Enter discharge summary, instructions, and follow-up recommendations..."></textarea>
                                    </div>
                                    <div class="bg-yellow-50 border border-yellow-200 rounded p-3">
                                        <p class="text-xs text-yellow-800">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1 inline"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                                            Bed will be marked for cleaning after discharge.
                                        </p>
                                    </div>
                                    <button type="submit" class="discharge-btn" data-action="discharge"
                                        class="w-full px-3 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700">
                                        Confirm Discharge
                                    </button>
                                </form>
                            </div>
                        </details>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-8">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
                <p class="text-gray-500">No active admissions</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.discharge-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('form');
            showConfirmAlert('Discharge Patient', 'Are you sure you want to discharge this patient?')
                .then((confirmed) => {
                    if (confirmed && form) {
                        form.submit();
                    }
                });
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>