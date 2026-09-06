<?php
require_once '../../config/config.php';
require_once 'helpers.php';
requireAuth();
checkRole(['admin', 'receptionist', 'nurse', 'doctor']);

$page_title = "Patient Queue";
$is_admin = ($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? '') === 'admin';
$queue_type = $_GET['type'] ?? 'OPD';

// Handle Reassign Patient (schema: doctor_id)
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reassign') {
    $queue_id = (int)$_POST['queue_id'];
    $new_doctor_id = (int)$_POST['new_doctor_id'];
    
    try {
        $query = "UPDATE patient_queue SET doctor_id = :new_doctor_id WHERE id = :queue_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':queue_id', $queue_id, PDO::PARAM_INT);
        $stmt->bindParam(':new_doctor_id', $new_doctor_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $_SESSION['success'] = "Patient reassigned successfully.";
        header("Location: patient_queue.php?type=" . $queue_type);
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error reassigning patient: " . $e->getMessage();
    }
}

// Get queue (schema: clinic_type, doctor_id, priority, status waiting|in_progress, check_in_time|created_at)
try {
    $queue_query = "SELECT pq.*, pq.doctor_id as assigned_doctor_id, pq.clinic_type as queue_type,
                    COALESCE(pq.check_in_time, pq.created_at) as queued_at, pq.priority as priority_level,
                    COALESCE(p.first_name, u.first_name) as patient_fname, 
                    COALESCE(p.last_name, u.last_name) as patient_lname,
                    COALESCE(p.hospital_id, p.id) as patient_display_id,
                    u2.first_name as doctor_fname, u2.last_name as doctor_lname
                    FROM patient_queue pq
                    LEFT JOIN patients p ON pq.patient_id = p.id
                    LEFT JOIN users u ON pq.patient_id = u.id
                    LEFT JOIN users u2 ON pq.doctor_id = u2.id
                    WHERE pq.clinic_type = :queue_type AND pq.status IN ('waiting', 'in_progress')
                    ORDER BY FIELD(pq.priority, 'emergency', 'urgent', 'normal'), COALESCE(pq.check_in_time, pq.created_at) ASC";
    $queue_stmt = $db->prepare($queue_query);
    $queue_stmt->bindParam(':queue_type', $queue_type);
    $queue_stmt->execute();
    $queue = $queue_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $queue = [];
}

// Get doctors for reassignment
$doctors = [];
if ($is_admin) {
    try {
        $doctors_query = "SELECT u.id, u.first_name, u.last_name
                         FROM users u
                         INNER JOIN roles r ON u.role_id = r.id
                         WHERE r.role_name = 'doctor' AND (u.status = 'active' OR u.status IS NULL)
                         ORDER BY u.last_name, u.first_name";
        $doctors_stmt = $db->prepare($doctors_query);
        $doctors_stmt->execute();
        $doctors = $doctors_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $doctors = [];
    }
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Patient Queue</h1>
    <p class="text-gray-600 dark:text-gray-400">View real-time patient queue in OPD/clinics and manage patient assignments</p>
</div>

<!-- Queue Type Filter -->
<div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
    <div class="px-4 py-5 sm:p-6">
        <div class="flex space-x-4">
            <a href="?type=OPD" class="<?php echo $queue_type === 'OPD' ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-700'; ?> px-4 py-2 rounded-md hover:bg-primary-700">
                OPD
            </a>
            <a href="?type=ER" class="<?php echo $queue_type === 'ER' ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-700'; ?> px-4 py-2 rounded-md hover:bg-primary-700">
                ER
            </a>
            <a href="?type=Clinic" class="<?php echo $queue_type === 'Clinic' ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-700'; ?> px-4 py-2 rounded-md hover:bg-primary-700">
                Clinic
            </a>
        </div>
    </div>
</div>

<!-- Queue Display -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Waiting Queue -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Waiting Queue</h3>
            <div class="space-y-3">
                <?php 
                $waiting = array_filter($queue, function($q) { return $q['status'] === 'waiting'; });
                if (empty($waiting)): ?>
                    <p class="text-gray-500 text-center py-8">No patients waiting</p>
                <?php else: ?>
                    <?php foreach ($waiting as $item): ?>
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($item['patient_fname'] . ' ' . $item['patient_lname']); ?>
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        ID: <?php echo htmlspecialchars($item['patient_display_id'] ?? $item['patient_id'] ?? 'N/A'); ?>
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Doctor: <?php echo htmlspecialchars(($item['doctor_fname'] ?? '') . ' ' . ($item['doctor_lname'] ?? 'Unassigned')); ?>
                                    </p>
                                    <p class="text-xs text-gray-400 mt-1">
                                        Queued: <?php echo date('H:i', strtotime($item['queued_at'])); ?>
                                    </p>
                                </div>
                                <div class="flex flex-col items-end">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 mb-2">
                                        <?php echo ucfirst($item['priority_level']); ?>
                                    </span>
                                    <?php if ($is_admin): ?>
                                        <button onclick="openReassignModal(<?php echo $item['id']; ?>, <?php echo $item['assigned_doctor_id'] ?? 0; ?>)" 
                                                class="text-xs text-primary-600 hover:text-primary-800">
                                            Reassign
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- In Consultation -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">In Consultation</h3>
            <div class="space-y-3">
                <?php 
                $in_consultation = array_filter($queue, function($q) { return ($q['status'] ?? '') === 'in_progress'; });
                if (empty($in_consultation)): ?>
                    <p class="text-gray-500 text-center py-8">No patients in consultation</p>
                <?php else: ?>
                    <?php foreach ($in_consultation as $item): ?>
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($item['patient_fname'] . ' ' . $item['patient_lname']); ?>
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        ID: <?php echo htmlspecialchars($item['patient_display_id'] ?? $item['patient_id'] ?? 'N/A'); ?>
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Doctor: <?php echo htmlspecialchars(($item['doctor_fname'] ?? '') . ' ' . ($item['doctor_lname'] ?? 'Unassigned')); ?>
                                    </p>
                                    <p class="text-xs text-gray-400 mt-1">
                                        Started: <?php echo !empty($item['called_at']) ? date('H:i', strtotime($item['called_at'])) : '-'; ?>
                                    </p>
                                </div>
                                <div class="flex flex-col items-end">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 mb-2">
                                        In Consultation
                                    </span>
                                    <?php if ($is_admin): ?>
                                        <button onclick="openReassignModal(<?php echo $item['id']; ?>, <?php echo $item['assigned_doctor_id'] ?? 0; ?>)" 
                                                class="text-xs text-primary-600 hover:text-primary-800">
                                            Reassign
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Reassign Modal -->
<?php if ($is_admin): ?>
<div id="reassignModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Reassign Patient</h3>
            <button onclick="document.getElementById('reassignModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-500">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="reassign">
            <input type="hidden" name="queue_id" id="reassign_queue_id">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Select Doctor *</label>
                    <select name="new_doctor_id" id="reassign_doctor_id" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['id']; ?>">
                                <?php echo htmlspecialchars(trim(($doctor['first_name'] ?? '') . ' ' . ($doctor['last_name'] ?? ''))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('reassignModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">
                        Reassign
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openReassignModal(queueId, currentDoctorId) {
    document.getElementById('reassign_queue_id').value = queueId;
    document.getElementById('reassign_doctor_id').value = currentDoctorId || '';
    document.getElementById('reassignModal').classList.remove('hidden');
}
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
