<?php
require_once '../../config/config.php';
requireAuth();
checkRole(['admin', 'receptionist', 'nurse', 'doctor']);

$page_title = "Patient Queue";
$is_admin = ($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? '') === 'admin';

// Handle admin reassignment
if ($is_admin && $_POST && isset($_POST['action']) && $_POST['action'] === 'reassign') {
    try {
        $appointment_id = (int) sanitizeInput($_POST['appointment_id']);
        $new_doctor_id = (int) sanitizeInput($_POST['new_doctor_id']);
        
        $reassign_query = "UPDATE appointments SET doctor_id = :doctor_id, updated_by = :updated_by, updated_at = NOW() WHERE id = :appointment_id";
        $reassign_stmt = $db->prepare($reassign_query);
        $reassign_stmt->bindParam(':doctor_id', $new_doctor_id, PDO::PARAM_INT);
        $reassign_stmt->bindParam(':appointment_id', $appointment_id, PDO::PARAM_INT);
        $reassign_stmt->bindParam(':updated_by', $_SESSION['user_id'], PDO::PARAM_INT);
        $reassign_stmt->execute();
        
        // Log the action
        logAction('appointment_reassign', 'appointments', $appointment_id, null, ['new_doctor_id' => $new_doctor_id]);
        
        $_SESSION['success'] = "Appointment reassigned successfully.";
        header("Location: queue.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error reassigning appointment: " . $e->getMessage();
    }
}

try {
    // Get current patient queue (appointments for today)
    $queue_query = "SELECT a.*, p.first_name, p.last_name, p.hospital_id, 
                   u.first_name as doctor_fname, u.last_name as doctor_lname
                   FROM appointments a 
                   LEFT JOIN patients p ON a.patient_id = p.id 
                   LEFT JOIN users u ON a.doctor_id = u.id
                   WHERE a.appointment_date = CURDATE() 
                   AND a.status IN ('scheduled', 'confirmed', 'in_progress')
                   ORDER BY a.priority_level ASC, a.appointment_time ASC";
    $queue_stmt = $db->prepare($queue_query);
    $queue_stmt->execute();
    $queue = $queue_stmt->fetchAll();

    // Get ER triage queue
    $er_queue_query = "SELECT et.*, p.first_name, p.last_name, p.hospital_id,
                      u.first_name as nurse_fname, u.last_name as nurse_lname
                      FROM er_triage et
                      LEFT JOIN patients p ON et.patient_id = p.id
                      LEFT JOIN users u ON et.triage_nurse_id = u.id
                      WHERE et.status = 'waiting'
                      ORDER BY et.priority_score ASC, et.created_at ASC";
    $er_queue_stmt = $db->prepare($er_queue_query);
    $er_queue_stmt->execute();
    $er_queue = $er_queue_stmt->fetchAll();

    // Get all doctors for admin reassignment
    $doctors = [];
    if ($is_admin) {
        $doctors_query = "SELECT id, first_name, last_name, specialization FROM users WHERE role = 'doctor' AND status = 'active' ORDER BY first_name, last_name";
        $doctors_stmt = $db->prepare($doctors_query);
        $doctors_stmt->execute();
        $doctors = $doctors_stmt->fetchAll();
    }

} catch (PDOException $exception) {
    $_SESSION['error'] = "Error fetching queue: " . $exception->getMessage();
    $queue = [];
    $er_queue = [];
    $doctors = [];
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Patient Queue</h1>
    <p class="text-gray-600">Current patient queues for today</p>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <!-- Regular Appointments Queue -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Appointment Queue
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        <?php echo count($queue); ?> patients waiting
                    </p>
                </div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                    Today
                </span>
            </div>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($queue) > 0): ?>
                <div class="space-y-4">
                    <?php foreach ($queue as $appointment): ?>
                        <div class="flex items-center justify-between p-4 border rounded-lg">
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 bg-primary-100 rounded-full flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary-600"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">
                                        <?php echo $appointment['first_name'] . ' ' . $appointment['last_name']; ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo $appointment['hospital_id']; ?> • 
                                        <?php echo formatTime($appointment['appointment_time']); ?>
                                    </p>
                                    <p class="text-xs text-gray-600">
                                        Dr. <?php echo $appointment['doctor_fname'] . ' ' . $appointment['doctor_lname']; ?>
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    <?php echo $appointment['status'] == 'scheduled' ? 'bg-blue-100 text-blue-800' : ''; ?>
                                    <?php echo $appointment['status'] == 'confirmed' ? 'bg-green-100 text-green-800' : ''; ?>
                                    <?php echo $appointment['status'] == 'in_progress' ? 'bg-yellow-100 text-yellow-800' : ''; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                </span>
                                <p class="text-xs text-gray-500 mt-1">
                                    Priority: <?php echo ucfirst($appointment['priority_level']); ?>
                                </p>
                                <?php if ($is_admin): ?>
                                    <button onclick="openReassignModal(<?php echo $appointment['id']; ?>, <?php echo $appointment['doctor_id']; ?>)" 
                                            class="mt-2 text-xs text-primary-600 hover:text-primary-800 font-medium">
                                        Reassign Doctor
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    <p class="text-gray-500">No appointments in queue for today</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ER Triage Queue -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        ER Triage Queue
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        <?php echo count($er_queue); ?> patients waiting
                    </p>
                </div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                    Emergency
                </span>
            </div>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($er_queue) > 0): ?>
                <div class="space-y-4">
                    <?php foreach ($er_queue as $triage): ?>
                        <div class="p-4 border rounded-lg 
                            <?php echo $triage['triage_level'] == 'resuscitation' ? 'border-red-300 bg-red-50' : ''; ?>
                            <?php echo $triage['triage_level'] == 'emergency' ? 'border-orange-300 bg-orange-50' : ''; ?>
                            <?php echo $triage['triage_level'] == 'urgent' ? 'border-yellow-300 bg-yellow-50' : ''; ?>
                            <?php echo $triage['triage_level'] == 'semi_urgent' ? 'border-blue-300 bg-blue-50' : ''; ?>
                            <?php echo $triage['triage_level'] == 'non_urgent' ? 'border-green-300 bg-green-50' : ''; ?>">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3">
                                        <div class="flex-shrink-0">
                                            <div class="w-8 h-8 bg-white rounded-full flex items-center justify-center border">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-600 text-sm"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">
                                                <?php echo $triage['first_name'] . ' ' . $triage['last_name']; ?>
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                <?php echo $triage['hospital_id']; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-600 mt-2">
                                        <?php echo $triage['chief_complaint']; ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        <?php echo getTriageColor($triage['triage_level']) == 'red' ? 'bg-red-100 text-red-800' : ''; ?>
                                        <?php echo getTriageColor($triage['triage_level']) == 'orange' ? 'bg-orange-100 text-orange-800' : ''; ?>
                                        <?php echo getTriageColor($triage['triage_level']) == 'yellow' ? 'bg-yellow-100 text-yellow-800' : ''; ?>
                                        <?php echo getTriageColor($triage['triage_level']) == 'blue' ? 'bg-blue-100 text-blue-800' : ''; ?>
                                        <?php echo getTriageColor($triage['triage_level']) == 'green' ? 'bg-green-100 text-green-800' : ''; ?>">
                                        <?php echo ucfirst($triage['triage_level']); ?>
                                    </span>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?php echo formatTime($triage['created_at']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M10 2v6"></path><path d="M14 2v6"></path><path d="M18 8H2"></path><path d="M20 8v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8"></path><path d="M4 12h16"></path><path d="M8 18h8"></path></svg>
                    <p class="text-gray-500">No patients in ER triage queue</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Queue Management Actions -->
<div class="mt-6 bg-white shadow rounded-lg">
    <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
            Queue Management
        </h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <a href="../appointments/schedule.php" 
               class="flex flex-col items-center p-4 bg-primary-50 rounded-lg hover:bg-primary-100 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary-600 text-2xl mb-2 mx-auto"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="M12 6v6"></path><path d="M9 9h6"></path></svg>
                <span class="text-sm font-medium text-gray-900">Schedule Appointment</span>
            </a>

            <a href="../er_triage/triage.php" 
               class="flex flex-col items-center p-4 bg-red-50 rounded-lg hover:bg-red-100 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-red-600 text-2xl mb-2 mx-auto"><path d="M10 2v6"></path><path d="M14 2v6"></path><path d="M18 8H2"></path><path d="M20 8v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8"></path><path d="M4 12h16"></path><path d="M8 18h8"></path></svg>
                <span class="text-sm font-medium text-gray-900">ER Triage</span>
            </a>

            <a href="../registration/register.php" 
               class="flex flex-col items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-green-600 text-2xl mb-2 mx-auto"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" y1="8" x2="19" y2="14"></line><line x1="22" y1="11" x2="16" y2="11"></line></svg>
                <span class="text-sm font-medium text-gray-900">Register Patient</span>
            </a>
        </div>
    </div>
</div>

<!-- Reassign Doctor Modal (Admin Only) -->
<?php if ($is_admin): ?>
<div id="reassignModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4">
    <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-5">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Reassign Doctor</h3>
            <form method="POST" action="queue.php">
                <input type="hidden" name="action" value="reassign">
                <input type="hidden" name="appointment_id" id="reassign_appointment_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select New Doctor</label>
                    <select name="new_doctor_id" id="reassign_doctor_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option value="">-- Select Doctor --</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['id']; ?>">
                                Dr. <?php echo $doctor['first_name'] . ' ' . $doctor['last_name']; ?>
                                <?php if ($doctor['specialization']): ?>
                                    - <?php echo $doctor['specialization']; ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeReassignModal()"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">
                        Reassign
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openReassignModal(appointmentId, currentDoctorId) {
    document.getElementById('reassign_appointment_id').value = appointmentId;
    document.getElementById('reassign_doctor_id').value = currentDoctorId;
    document.getElementById('reassignModal').classList.remove('hidden');
}

function closeReassignModal() {
    document.getElementById('reassignModal').classList.add('hidden');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('reassignModal');
    if (event.target == modal) {
        closeReassignModal();
    }
}
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>