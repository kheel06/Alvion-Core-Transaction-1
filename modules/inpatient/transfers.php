<?php
/**
 * Bed Transfers
 * Part of IBMS - Inpatient and Bed Management System
 * Philippines Hospital Context
 */
require_once '../../config/config.php';
requireAuth();
checkRole(['admin', 'nurse', 'doctor']);

$page_title = "Bed Transfers";

// Handle bed transfer
if ($_POST) {
    try {
        $admission_id = (int)sanitizeInput($_POST['admission_id']);
        $from_bed_id = (int)sanitizeInput($_POST['from_bed_id']);
        $to_bed_id = (int)sanitizeInput($_POST['to_bed_id']);
        $transfer_reason = sanitizeInput($_POST['transfer_reason'] ?? '');
        
        // Validate beds
        if ($from_bed_id === $to_bed_id) {
            throw new Exception("Cannot transfer to the same bed.");
        }
        
        // Check if to_bed is available
        $bed_check = $db->prepare("SELECT status FROM beds WHERE id = :bed_id");
        $bed_check->bindParam(':bed_id', $to_bed_id, PDO::PARAM_INT);
        $bed_check->execute();
        $to_bed = $bed_check->fetch();
        
        if (!$to_bed || $to_bed['status'] !== 'available') {
            throw new Exception("Target bed is not available.");
        }
        
        // Get patient_id from admission
        $admission_check = $db->prepare("SELECT patient_id FROM admissions WHERE id = :admission_id");
        $admission_check->bindParam(':admission_id', $admission_id, PDO::PARAM_INT);
        $admission_check->execute();
        $admission = $admission_check->fetch();
        
        if (!$admission) {
            throw new Exception("Admission not found.");
        }
        
        $patient_id = $admission['patient_id'];
        
        // Update admission bed
        $update_admission = $db->prepare("UPDATE admissions SET bed_id = :to_bed_id WHERE id = :admission_id");
        $update_admission->bindParam(':to_bed_id', $to_bed_id, PDO::PARAM_INT);
        $update_admission->bindParam(':admission_id', $admission_id, PDO::PARAM_INT);
        $update_admission->execute();
        
        // Update from bed (make available and mark for cleaning)
        $update_from_bed = $db->prepare("UPDATE beds SET status = 'available', current_patient_id = NULL, cleaning_status = 'dirty' WHERE id = :bed_id");
        $update_from_bed->bindParam(':bed_id', $from_bed_id, PDO::PARAM_INT);
        $update_from_bed->execute();
        
        // Update to bed (make occupied)
        $update_to_bed = $db->prepare("UPDATE beds SET status = 'occupied', current_patient_id = :patient_id WHERE id = :bed_id");
        $update_to_bed->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $update_to_bed->bindParam(':bed_id', $to_bed_id, PDO::PARAM_INT);
        $update_to_bed->execute();
        
        // Record transfer
        $transfer_query = "INSERT INTO bed_transfers (
            admission_id, from_bed_id, to_bed_id, transfer_reason, transferred_by
        ) VALUES (
            :admission_id, :from_bed_id, :to_bed_id, :transfer_reason, :transferred_by
        )";
        $transfer_stmt = $db->prepare($transfer_query);
        $transfer_stmt->bindParam(':admission_id', $admission_id, PDO::PARAM_INT);
        $transfer_stmt->bindParam(':from_bed_id', $from_bed_id, PDO::PARAM_INT);
        $transfer_stmt->bindParam(':to_bed_id', $to_bed_id, PDO::PARAM_INT);
        $transfer_stmt->bindParam(':transfer_reason', $transfer_reason);
        $transfer_stmt->bindParam(':transferred_by', $_SESSION['user_id'], PDO::PARAM_INT);
        $transfer_stmt->execute();
        
        // Update ward occupancies
        $ward_update = $db->prepare("UPDATE wards SET current_occupancy = (
            SELECT COUNT(*) FROM beds WHERE ward_id = wards.id AND status = 'occupied'
        )");
        $ward_update->execute();
        
        // Create housekeeping task for from bed
        $housekeeping_query = "INSERT INTO housekeeping_tasks (bed_id, task_type, status, scheduled_at)
                              VALUES (:bed_id, 'cleaning', 'pending', NOW())";
        $housekeeping_stmt = $db->prepare($housekeeping_query);
        $housekeeping_stmt->bindParam(':bed_id', $from_bed_id, PDO::PARAM_INT);
        $housekeeping_stmt->execute();
        
        $_SESSION['success'] = "Patient transferred successfully! Previous bed marked for cleaning.";
        header("Location: transfers.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error processing transfer: " . $e->getMessage();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Get active admissions with bed information
try {
    $admissions_query = "SELECT ad.*, p.first_name, p.last_name, p.hospital_id,
                        w.ward_name, b.bed_number, b.bed_type, b.id as current_bed_id,
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

// Get available beds
try {
    $beds_query = "SELECT b.*, w.ward_name, w.ward_code
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

// Get transfer history
try {
    $history_query = "SELECT bt.*, p.first_name, p.last_name, p.hospital_id,
                     w1.ward_name as from_ward, b1.bed_number as from_bed,
                     w2.ward_name as to_ward, b2.bed_number as to_bed,
                     u.first_name as user_fname, u.last_name as user_lname
                     FROM bed_transfers bt
                     INNER JOIN admissions ad ON bt.admission_id = ad.id
                     INNER JOIN patients p ON ad.patient_id = p.id
                     INNER JOIN beds b1 ON bt.from_bed_id = b1.id
                     INNER JOIN beds b2 ON bt.to_bed_id = b2.id
                     INNER JOIN wards w1 ON b1.ward_id = w1.id
                     INNER JOIN wards w2 ON b2.ward_id = w2.id
                     INNER JOIN users u ON bt.transferred_by = u.id
                     ORDER BY bt.transfer_date DESC
                     LIMIT 20";
    $history_stmt = $db->prepare($history_query);
    $history_stmt->execute();
    $transfer_history = $history_stmt->fetchAll();
} catch (PDOException $e) {
    $transfer_history = [];
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Bed Transfers</h1>
    <p class="text-gray-600">Transfer patients between beds and wards</p>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <!-- Active Admissions for Transfer -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Active Admissions</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($active_admissions) > 0): ?>
                <div class="space-y-4">
                    <?php foreach ($active_admissions as $adm): ?>
                        <div class="border rounded-lg p-4 bg-gray-50">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900">
                                        <?php echo $adm['first_name'] . ' ' . $adm['last_name']; ?>
                                        (<?php echo $adm['hospital_id']; ?>)
                                    </h4>
                                    <p class="text-xs text-gray-500 mt-1">
                                        Currently in: <?php echo $adm['ward_name']; ?> - Bed <?php echo $adm['bed_number']; ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        Admission #<?php echo $adm['admission_number']; ?>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Transfer Form -->
                            <details class="group">
                                <summary class="cursor-pointer px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 text-center">
                                    Transfer to Another Bed
                                </summary>
                                <div class="mt-3 p-4 bg-white border border-gray-200 rounded-lg">
                                    <form method="POST" class="space-y-3">
                                        <input type="hidden" name="admission_id" value="<?php echo $adm['id']; ?>">
                                        <input type="hidden" name="from_bed_id" value="<?php echo $adm['current_bed_id']; ?>">
                                        
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">Transfer To Bed *</label>
                                            <select name="to_bed_id" required
                                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">
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
                                                        <?php echo ucfirst($bed['bed_type']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                                <?php if ($current_ward !== ''): ?>
                                                    </optgroup>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">Transfer Reason</label>
                                            <textarea name="transfer_reason" rows="2"
                                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm"
                                                placeholder="Reason for transfer..."></textarea>
                                        </div>
                                        
                                        <div class="bg-yellow-50 border border-yellow-200 rounded p-2">
                                            <p class="text-xs text-yellow-800">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1 inline"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
                                                Previous bed will be marked for cleaning.
                                            </p>
                                        </div>
                                        
                                        <button type="submit" class="transfer-btn" data-action="transfer"
                                            class="w-full px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                                            Confirm Transfer
                                        </button>
                                    </form>
                                </div>
                            </details>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M2 4v16"></path><path d="M2 8h18a2 2 0 0 1 2 2v10"></path><path d="M2 17h20"></path><path d="M6 8V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"></path></svg>
                    <p class="text-gray-500">No active admissions</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Transfer History -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Transfer History</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($transfer_history) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($transfer_history as $transfer): ?>
                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                            <p class="text-sm font-medium text-gray-900">
                                <?php echo $transfer['first_name'] . ' ' . $transfer['last_name']; ?>
                            </p>
                            <p class="text-xs text-gray-500"><?php echo $transfer['hospital_id']; ?></p>
                            <p class="text-xs text-gray-600 mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1 inline"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                                <?php echo $transfer['from_ward']; ?> Bed <?php echo $transfer['from_bed']; ?> 
                                → 
                                <?php echo $transfer['to_ward']; ?> Bed <?php echo $transfer['to_bed']; ?>
                            </p>
                            <?php if ($transfer['transfer_reason']): ?>
                                <p class="text-xs text-gray-500 mt-1">Reason: <?php echo $transfer['transfer_reason']; ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500 mt-1">
                                <?php echo formatDate($transfer['transfer_date']); ?> by 
                                <?php echo $transfer['user_fname'] . ' ' . $transfer['user_lname']; ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M3 3v5h5"></path><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"></path><path d="M12 7v5l4 2"></path></svg>
                    <p class="text-gray-500">No transfer history</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.transfer-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('form');
            showConfirmAlert('Transfer Patient', 'Are you sure you want to transfer this patient?')
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



