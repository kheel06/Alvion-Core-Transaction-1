<?php
/**
 * Receptionist Dashboard
 * Registration and scheduling overview
 */
require_once '../config/config.php';
requireAuth();
checkRole(['receptionist']);

$page_title = "Receptionist Dashboard";

try {
    // Receptionist statistics
    $stats_query = "SELECT 
        (SELECT COUNT(*) FROM patients WHERE DATE(created_at) = CURDATE()) as registered_today,
        (SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE()) as appointments_today,
        (SELECT COUNT(*) FROM appointments WHERE booking_channel = 'clerk' AND DATE(created_at) = CURDATE()) as clerk_bookings,
        (SELECT COUNT(*) FROM er_triage WHERE DATE(created_at) = CURDATE()) as er_registrations";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch();

    // Today's registrations
    $today_registrations = $db->query("SELECT * FROM patients WHERE DATE(created_at) = CURDATE() ORDER BY created_at DESC LIMIT 10")->fetchAll();

    // Today's appointments
    $today_appointments = $db->query("SELECT a.*, p.first_name, p.last_name, p.hospital_id, u.first_name as doctor_fname, u.last_name as doctor_lname
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.appointment_date = CURDATE()
        ORDER BY a.appointment_time ASC
        LIMIT 10")->fetchAll();

    // Queue status
    $queue_status = $db->query("SELECT 
        COUNT(*) as total_in_queue,
        COUNT(CASE WHEN a.status = 'scheduled' THEN 1 END) as scheduled,
        COUNT(CASE WHEN a.status = 'confirmed' THEN 1 END) as confirmed,
        COUNT(CASE WHEN a.status = 'in_progress' THEN 1 END) as in_progress
        FROM appointments a
        WHERE a.appointment_date = CURDATE() AND a.status IN ('scheduled', 'confirmed', 'in_progress')")->fetch();

} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching dashboard data: " . $e->getMessage();
    $stats = [];
    $today_registrations = [];
    $today_appointments = [];
    $queue_status = [];
}

include '../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Receptionist Dashboard</h1>
    <p class="text-gray-600 dark:text-gray-400">Welcome, <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?> - Registration and scheduling overview</p>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" y1="8" x2="19" y2="14"></line><line x1="22" y1="11" x2="16" y2="11"></line></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Registered Today</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['registered_today'] ?? 0; ?></dd>
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="m9 16 2 2 4-4"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Appointments Today</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['appointments_today'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M11 12H2a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h9Z"></path><path d="M16 6h4a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2h-4"></path><path d="M11 12V8a2 2 0 0 1 2-2h2V4a2 2 0 0 0-2-2h-4v10Z"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Clerk Bookings</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['clerk_bookings'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M10 2v6"></path><path d="M14 2v6"></path><path d="M18 8H2"></path><path d="M20 8v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8"></path><path d="M4 12h16"></path><path d="M8 18h8"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">ER Registrations</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['er_registrations'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Queue Status -->
<div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Today's Queue Status</h3>
    </div>
    <div class="px-4 py-5 sm:p-6">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $queue_status['total_in_queue'] ?? 0; ?></p>
                <p class="text-sm text-gray-500">Total in Queue</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold text-blue-600"><?php echo $queue_status['scheduled'] ?? 0; ?></p>
                <p class="text-sm text-gray-500">Scheduled</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold text-green-600"><?php echo $queue_status['confirmed'] ?? 0; ?></p>
                <p class="text-sm text-gray-500">Confirmed</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold text-yellow-600"><?php echo $queue_status['in_progress'] ?? 0; ?></p>
                <p class="text-sm text-gray-500">In Progress</p>
            </div>
        </div>
    </div>
</div>

<!-- Today's Activity -->
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Today's Registrations</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($today_registrations) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($today_registrations as $patient): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    <?php echo $patient['first_name'] . ' ' . $patient['last_name']; ?>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo $patient['hospital_id']; ?></p>
                            </div>
                            <span class="text-xs text-gray-500"><?php echo formatTime($patient['created_at']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="17" y1="8" x2="23" y2="14"></line><line x1="23" y1="8" x2="17" y2="14"></line></svg>
                    <p class="text-gray-500">No registrations today</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

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
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    <?php echo $appt['hospital_id']; ?> • 
                                    <?php echo formatTime($appt['appointment_time']); ?>
                                </p>
                                <?php if ($appt['doctor_fname']): ?>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">Dr. <?php echo $appt['doctor_fname'] . ' ' . $appt['doctor_lname']; ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                <?php echo ucfirst(str_replace('_', ' ', $appt['status'])); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="m14 14-4-4"></path><path d="m10 14 4-4"></path></svg>
                    <p class="text-gray-500">No appointments today</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>



