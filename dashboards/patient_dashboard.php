<?php
/**
 * Patient Dashboard
 * Self-service patient portal
 */
require_once '../config/config.php';
requireAuth();
checkRole(['patient']);

$page_title = "Patient Portal";
$patientIdentifier = $_SESSION['user_id']; // Handles both numeric IDs and string-based identifiers

if (!function_exists('dashboardTableExists')) {
    function dashboardTableExists(PDO $db, string $table): bool {
        $table = preg_replace('/[^A-Za-z0-9_]/', '', $table);
        if ($table === '') {
            return false;
        }
        try {
            $stmt = $db->prepare("SHOW TABLES LIKE :table_name");
            $stmt->bindParam(':table_name', $table);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Failed to inspect {$table}: " . $e->getMessage());
            return false;
        }
    }
}

try {
    // Get patient record
    $patient_query = "SELECT * FROM patients WHERE id = :patient_numeric OR user_id = :patient_identifier LIMIT 1";
    $patient_stmt = $db->prepare($patient_query);
    
    $patientNumericId = is_numeric($patientIdentifier) ? (int)$patientIdentifier : 0;
    $patient_stmt->bindValue(':patient_numeric', $patientNumericId, PDO::PARAM_INT);
    $patient_stmt->bindValue(':patient_identifier', (string)$patientIdentifier, PDO::PARAM_STR);
    $patient_stmt->execute();
    $patient = $patient_stmt->fetch();
    
    if (!$patient) {
        $patientNotFound = true;
    } else {
        $actual_patient_id = $patient['id'];
    }
    
    if (!empty($patientNotFound)) {
        $actual_patient_id = null;
    }

    $stats = [
        'upcoming_appointments' => 0,
        'completed_appointments' => 0,
        'upcoming_teleconsult' => 0,
        'pending_prescriptions' => 0,
    ];

    $hasAppointments = dashboardTableExists($db, 'appointments');
    $hasTeleconsult = dashboardTableExists($db, 'teleconsultations');
    $hasPrescriptions = dashboardTableExists($db, 'e_prescriptions');

    if ($hasAppointments && $actual_patient_id !== null) {
        $stats_query = "SELECT 
                SUM(CASE WHEN appointment_date >= CURDATE() AND status != 'cancelled' THEN 1 ELSE 0 END) as upcoming_appointments,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_appointments
            FROM appointments
            WHERE patient_id = :patient_id";
        $stats_stmt = $db->prepare($stats_query);
        $stats_stmt->bindParam(':patient_id', $actual_patient_id, PDO::PARAM_INT);
        $stats_stmt->execute();
        $appointment_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        if ($appointment_stats) {
            $stats['upcoming_appointments'] = (int)($appointment_stats['upcoming_appointments'] ?? 0);
            $stats['completed_appointments'] = (int)($appointment_stats['completed_appointments'] ?? 0);
        }

        $upcoming_appointments_stmt = $db->prepare("SELECT a.*, u.first_name as doctor_fname, u.last_name as doctor_lname, u.specialization
            FROM appointments a
            LEFT JOIN users u ON a.doctor_id = u.id
            WHERE a.patient_id = :patient_id AND a.appointment_date >= CURDATE() AND a.status != 'cancelled'
            ORDER BY a.appointment_date ASC, a.appointment_time ASC
            LIMIT 5");
        $upcoming_appointments_stmt->bindParam(':patient_id', $actual_patient_id, PDO::PARAM_INT);
        $upcoming_appointments_stmt->execute();
        $upcoming_appointments = $upcoming_appointments_stmt->fetchAll();
    } else {
        $upcoming_appointments = [];
    }

    if ($hasTeleconsult && $actual_patient_id !== null) {
        $teleconsult_query = "SELECT 
                SUM(CASE WHEN consultation_date >= CURDATE() AND status != 'cancelled' THEN 1 ELSE 0 END) as upcoming_teleconsult
            FROM teleconsultations
            WHERE patient_id = :patient_id";
        $teleconsult_stmt = $db->prepare($teleconsult_query);
        $teleconsult_stmt->bindParam(':patient_id', $actual_patient_id, PDO::PARAM_INT);
        $teleconsult_stmt->execute();
        $teleconsult_stats = $teleconsult_stmt->fetch(PDO::FETCH_ASSOC);
        if ($teleconsult_stats) {
            $stats['upcoming_teleconsult'] = (int)($teleconsult_stats['upcoming_teleconsult'] ?? 0);
        }
    }

    if ($hasPrescriptions && $actual_patient_id !== null) {
        $prescription_query = "SELECT 
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_prescriptions
            FROM e_prescriptions
            WHERE patient_id = :patient_id";
        $prescription_stmt = $db->prepare($prescription_query);
        $prescription_stmt->bindParam(':patient_id', $actual_patient_id, PDO::PARAM_INT);
        $prescription_stmt->execute();
        $prescription_stats = $prescription_stmt->fetch(PDO::FETCH_ASSOC);
        if ($prescription_stats) {
            $stats['pending_prescriptions'] = (int)($prescription_stats['pending_prescriptions'] ?? 0);
        }

        $recent_prescriptions_stmt = $db->prepare("SELECT ep.*, u.first_name as doctor_fname, u.last_name as doctor_lname
            FROM e_prescriptions ep
            LEFT JOIN users u ON ep.doctor_id = u.id
            WHERE ep.patient_id = :patient_id
            ORDER BY ep.prescription_date DESC
            LIMIT 5");
        $recent_prescriptions_stmt->bindParam(':patient_id', $actual_patient_id, PDO::PARAM_INT);
        $recent_prescriptions_stmt->execute();
        $recent_prescriptions = $recent_prescriptions_stmt->fetchAll();
    } else {
        $recent_prescriptions = [];
    }

} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching dashboard data: " . $e->getMessage();
    $patient = [];
    $stats = [];
    $upcoming_appointments = [];
    $recent_prescriptions = [];
}

include '../includes/header.php';
?>

<?php if (!empty($patientNotFound)): ?>
    <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800">
        <p class="font-semibold">We couldn't find your patient profile.</p>
        <p class="text-sm mt-1">Please contact hospital staff to complete your registration so you can view personalized data here.</p>
    </div>
<?php endif; ?>

<?php
$patient_first_name = $patient['first_name'] ?? '';
$patient_last_name = $patient['last_name'] ?? '';
$patient_full_name = trim($patient_first_name . ' ' . $patient_last_name);
$patient_hospital_id = $patient['hospital_id'] ?? 'N/A';
$patient_age = !empty($patient['birth_date']) ? calculateAge($patient['birth_date']) . ' years old' : 'Not available';
$patient_blood_type = !empty($patient['blood_type']) ? $patient['blood_type'] : 'Not specified';
$patient_contact_number = !empty($patient['contact_number']) ? $patient['contact_number'] : 'Not provided';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Patient Portal</h1>
    <p class="text-gray-600 dark:text-gray-400">Welcome, <?php echo htmlspecialchars($patient_full_name ?: 'Patient'); ?> (<?php echo htmlspecialchars($patient_hospital_id); ?>)</p>
</div>

<!-- Patient Info Card -->
<div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Patient Information</h3>
    </div>
    <div class="px-4 py-5 sm:p-6">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-gray-500">Hospital ID</p>
                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($patient_hospital_id); ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Age</p>
                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($patient_age); ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Blood Type</p>
                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($patient_blood_type); ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Contact Number</p>
                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($patient_contact_number); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="m9 16 2 2 4-4"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Upcoming Appointments</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['upcoming_appointments'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Completed</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['completed_appointments'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-teal-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.999"></path><rect x="2" y="6" width="14" height="12" rx="2"></rect></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Upcoming Teleconsult</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['upcoming_teleconsult'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Pending Prescriptions</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['pending_prescriptions'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Appointments and Prescriptions -->
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Upcoming Appointments</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($upcoming_appointments) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($upcoming_appointments as $appt): ?>
                        <div class="p-3 bg-blue-50 rounded-lg border border-blue-200">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo formatDate($appt['appointment_date']); ?> at <?php echo formatTime($appt['appointment_time']); ?>
                            </p>
                            <?php if ($appt['doctor_fname']): ?>
                                <p class="text-xs text-gray-600 mt-1">
                                    Dr. <?php echo $appt['doctor_fname'] . ' ' . $appt['doctor_lname']; ?>
                                    <?php if ($appt['specialization']): ?>
                                        - <?php echo $appt['specialization']; ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500 mt-1"><?php echo ucfirst($appt['appointment_type']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="m14 14-4-4"></path><path d="m10 14 4-4"></path></svg>
                    <p class="text-gray-500">No upcoming appointments</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Recent Prescriptions</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($recent_prescriptions) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($recent_prescriptions as $rx): ?>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                Prescription #<?php echo $rx['prescription_number']; ?>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo formatDate($rx['prescription_date']); ?></p>
                            <p class="text-xs text-gray-600 mt-1">
                                Dr. <?php echo $rx['doctor_fname'] . ' ' . $rx['doctor_lname']; ?>
                            </p>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-2
                                <?php echo $rx['status'] == 'filled' ? 'bg-green-100 text-green-800' : ''; ?>
                                <?php echo $rx['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : ''; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $rx['status'])); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    <p class="text-gray-500">No prescriptions yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>



