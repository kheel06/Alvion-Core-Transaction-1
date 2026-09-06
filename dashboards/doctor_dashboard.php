<?php
/**
 * Doctor Dashboard
 * Patient care and consultation overview
 */
require_once '../config/config.php';
requireAuth();
checkRole(['doctor']);

$page_title = "Doctor Dashboard";
$doctor_id = $_SESSION['user_id'];

try {
    // Doctor-specific statistics
    $stats_query = "SELECT 
        (SELECT COUNT(*) FROM appointments WHERE doctor_id = :doctor_id AND DATE(appointment_date) = CURDATE()) as today_appointments,
        (SELECT COUNT(*) FROM appointments WHERE doctor_id = :doctor_id AND status = 'scheduled' AND appointment_date >= CURDATE()) as upcoming_appointments,
        (SELECT COUNT(*) FROM teleconsultations WHERE doctor_id = :doctor_id AND DATE(consultation_date) = CURDATE()) as teleconsult_today,
        (SELECT COUNT(*) FROM e_prescriptions WHERE doctor_id = :doctor_id AND status = 'pending') as pending_prescriptions,
        (SELECT COUNT(*) FROM e_lab_orders WHERE doctor_id = :doctor_id AND status = 'pending') as pending_lab_orders,
        (SELECT COUNT(*) FROM er_triage WHERE assigned_doctor_id = :doctor_id AND status = 'in_progress') as er_patients";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch();

    // Today's appointments
    $today_appointments = $db->prepare("SELECT a.*, p.first_name, p.last_name, p.hospital_id, p.blood_type
        FROM appointments a
        INNER JOIN patients p ON a.patient_id = p.id
        WHERE a.doctor_id = :doctor_id AND a.appointment_date = CURDATE()
        ORDER BY a.appointment_time ASC");
    $today_appointments->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
    $today_appointments->execute();
    $today_appointments = $today_appointments->fetchAll();

    // Pending prescriptions
    $pending_prescriptions = $db->prepare("SELECT ep.*, p.first_name, p.last_name, p.hospital_id
        FROM e_prescriptions ep
        INNER JOIN patients p ON ep.patient_id = p.id
        WHERE ep.doctor_id = :doctor_id AND ep.status = 'pending'
        ORDER BY ep.prescription_date DESC
        LIMIT 5");
    $pending_prescriptions->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
    $pending_prescriptions->execute();
    $pending_prescriptions = $pending_prescriptions->fetchAll();

    // Pending lab orders
    $pending_labs = $db->prepare("SELECT elo.*, p.first_name, p.last_name, p.hospital_id
        FROM e_lab_orders elo
        INNER JOIN patients p ON elo.patient_id = p.id
        WHERE elo.doctor_id = :doctor_id AND elo.status = 'pending'
        ORDER BY elo.order_date DESC
        LIMIT 5");
    $pending_labs->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
    $pending_labs->execute();
    $pending_labs = $pending_labs->fetchAll();

} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching dashboard data: " . $e->getMessage();
    $stats = [];
    $today_appointments = [];
    $pending_prescriptions = [];
    $pending_labs = [];
}

include '../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Doctor Dashboard</h1>
    <p class="text-gray-600 dark:text-gray-400">Welcome, Dr. <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?> - Your schedule and patient care overview</p>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white">
                            <path d="M8 2v4"></path>
                            <path d="M16 2v4"></path>
                            <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                            <path d="M3 10h18"></path>
                            <path d="m9 16 2 2 4-4"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Today's Appointments</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['today_appointments'] ?? 0; ?></dd>
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white">
                            <path d="M8 2v4"></path>
                            <path d="M16 2v4"></path>
                            <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                            <path d="M3 10h18"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Upcoming</dt>
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
                    <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white">
                            <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
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

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-indigo-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white">
                            <path d="M10 2v2"></path>
                            <path d="M14 2v2"></path>
                            <path d="M10.5 2h3"></path>
                            <path d="M7 12a5 5 0 0 0 10 0Z"></path>
                            <path d="M7 12H4a2 2 0 0 0-2 2v1a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-1a2 2 0 0 0-2-2h-3"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Pending Lab Orders</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['pending_lab_orders'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Today's Schedule -->
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-8">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Today's Appointments</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($today_appointments) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($today_appointments as $appt): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    <?php echo $appt['first_name'] . ' ' . $appt['last_name']; ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?php echo $appt['hospital_id']; ?> • 
                                    <?php echo formatTime($appt['appointment_time']); ?> • 
                                    <?php echo ucfirst($appt['appointment_type']); ?>
                                </p>
                                <?php if ($appt['reason']): ?>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1"><?php echo substr($appt['reason'], 0, 50); ?>...</p>
                                <?php endif; ?>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                <?php echo $appt['status'] == 'scheduled' ? 'bg-blue-100 text-blue-800' : ''; ?>
                                <?php echo $appt['status'] == 'in_progress' ? 'bg-yellow-100 text-yellow-800' : ''; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $appt['status'])); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2">
                        <path d="M8 2v4"></path>
                        <path d="M16 2v4"></path>
                        <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                        <path d="M3 10h18"></path>
                        <path d="m14 14-4-4"></path>
                        <path d="m10 14 4-4"></path>
                    </svg>
                    <p class="text-gray-500">No appointments scheduled for today</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Pending Prescriptions</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($pending_prescriptions) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($pending_prescriptions as $rx): ?>
                        <div class="p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                            <p class="text-sm font-medium text-gray-900">
                                <?php echo $rx['first_name'] . ' ' . $rx['last_name']; ?>
                            </p>
                            <p class="text-xs text-gray-500"><?php echo $rx['hospital_id']; ?></p>
                            <p class="text-xs text-gray-600 mt-1">Prescription #<?php echo $rx['prescription_number']; ?></p>
                            <p class="text-xs text-gray-500"><?php echo formatDate($rx['prescription_date']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="m9 12 2 2 4-4"></path>
                    </svg>
                    <p class="text-gray-500">All prescriptions processed</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Pending Lab Orders -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Pending Lab Orders</h3>
    </div>
    <div class="px-4 py-5 sm:p-6">
        <?php if (count($pending_labs) > 0): ?>
            <div class="space-y-3">
                <?php foreach ($pending_labs as $lab): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div>
                            <p class="text-sm font-medium text-gray-900">
                                <?php echo $lab['first_name'] . ' ' . $lab['last_name']; ?>
                            </p>
                            <p class="text-xs text-gray-500">
                                Order #<?php echo $lab['order_number']; ?> • 
                                <?php echo formatDate($lab['order_date']); ?>
                            </p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                            <?php echo $lab['priority'] == 'stat' ? 'bg-red-100 text-red-800' : ''; ?>
                            <?php echo $lab['priority'] == 'urgent' ? 'bg-orange-100 text-orange-800' : ''; ?>
                            <?php echo $lab['priority'] == 'routine' ? 'bg-blue-100 text-blue-800' : ''; ?>">
                            <?php echo strtoupper($lab['priority']); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="m9 12 2 2 4-4"></path>
                </svg>
                <p class="text-gray-500">All lab orders processed</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>



