<?php
/**
 * Appointment Coordinator Dashboard
 * Schedule management overview
 */
require_once '../config/config.php';
requireAuth();
checkRole(['appointment_coordinator']);

$page_title = "Appointment Coordinator Dashboard";

try {
    // Appointment coordinator statistics
    $stats_query = "SELECT 
        (SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE()) as appointments_today,
        (SELECT COUNT(*) FROM appointments WHERE appointment_date >= CURDATE() AND status = 'scheduled') as upcoming_appointments,
        (SELECT COUNT(*) FROM appointments WHERE status = 'cancelled' AND DATE(cancelled_at) = CURDATE()) as cancelled_today,
        (SELECT COUNT(*) FROM doctor_schedules) as active_schedules,
        (SELECT COUNT(*) FROM appointments WHERE booking_channel = 'online' AND DATE(created_at) = CURDATE()) as online_bookings,
        (SELECT COUNT(*) FROM appointments WHERE booking_channel = 'walkin' AND DATE(created_at) = CURDATE()) as walkin_bookings";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch();

    // Today's appointments
    $today_appointments = $db->query("SELECT a.*, p.first_name, p.last_name, p.hospital_id, u.first_name as doctor_fname, u.last_name as doctor_lname
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.appointment_date = CURDATE()
        ORDER BY a.appointment_time ASC
        LIMIT 10")->fetchAll();

    // Upcoming appointments
    $upcoming_appointments = $db->query("SELECT a.*, p.first_name, p.last_name, p.hospital_id, u.first_name as doctor_fname, u.last_name as doctor_lname
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.appointment_date >= CURDATE() AND a.status = 'scheduled'
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 10")->fetchAll();

} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching dashboard data: " . $e->getMessage();
    $stats = [];
    $today_appointments = [];
    $upcoming_appointments = [];
}

include '../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Appointment Coordinator Dashboard</h1>
    <p class="text-gray-600 dark:text-gray-400">Welcome, <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?> - Schedule management overview</p>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Today's Appointments</dt>
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
                    <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="m9 16 2 2 4-4"></path></svg>
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
                    <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="m14 14-4-4"></path><path d="m10 14 4-4"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Cancelled Today</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['cancelled_today'] ?? 0; ?></dd>
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"></path><path d="M8 15v1a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6v-4"></path><circle cx="20" cy="10" r="2"></circle></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Active Schedules</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['active_schedules'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Booking Channels -->
<div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Today's Booking Channels</h3>
    </div>
    <div class="px-4 py-5 sm:p-6">
        <div class="grid grid-cols-2 gap-4">
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <p class="text-2xl font-bold text-blue-600"><?php echo $stats['online_bookings'] ?? 0; ?></p>
                <p class="text-sm text-gray-600">Online Bookings</p>
            </div>
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <p class="text-2xl font-bold text-green-600"><?php echo $stats['walkin_bookings'] ?? 0; ?></p>
                <p class="text-sm text-gray-600">Walk-in Bookings</p>
            </div>
        </div>
    </div>
</div>

<!-- Appointments -->
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
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

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Upcoming Appointments</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($upcoming_appointments) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($upcoming_appointments as $appt): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    <?php echo $appt['first_name'] . ' ' . $appt['last_name']; ?>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    <?php echo formatDate($appt['appointment_date']); ?> at <?php echo formatTime($appt['appointment_time']); ?>
                                </p>
                                <?php if ($appt['doctor_fname']): ?>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">Dr. <?php echo $appt['doctor_fname'] . ' ' . $appt['doctor_lname']; ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="text-xs text-gray-500"><?php echo ucfirst($appt['appointment_type']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="m9 16 2 2 4-4"></path></svg>
                    <p class="text-gray-500">No upcoming appointments</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>



