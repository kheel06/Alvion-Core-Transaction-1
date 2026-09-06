<?php
require_once '../../config/config.php';
requireAuth();
checkRole(['admin', 'receptionist', 'appointment_coordinator', 'doctor']);

$page_title = "Schedule Appointments";

// Get doctors for dropdown (users.role_id -> roles.role_name = 'doctor')
try {
    $doctors_query = "SELECT u.id, u.first_name, u.last_name 
                      FROM users u 
                      INNER JOIN roles r ON u.role_id = r.id 
                      WHERE r.role_name = 'doctor' AND (u.status = 'active' OR u.status IS NULL)
                      ORDER BY u.last_name, u.first_name";
    $doctors_stmt = $db->prepare($doctors_query);
    $doctors_stmt->execute();
    $doctors = $doctors_stmt->fetchAll();
} catch (PDOException $exception) {
    $doctors = [];
}

// Get patients for dropdown
try {
    $patients_query = "SELECT id, hospital_id, first_name, last_name FROM patients WHERE status = 'active' ORDER BY first_name, last_name";
    $patients_stmt = $db->prepare($patients_query);
    $patients_stmt->execute();
    $patients = $patients_stmt->fetchAll();
} catch (PDOException $exception) {
    $patients = [];
}

// Clinic rooms
try {
    $rooms = getClinicRooms($db);
} catch (PDOException $exception) {
    $rooms = [];
}

if ($_POST) {
    try {
        $appointment_number = generateAppointmentNumber();
        $patient_id = (int) sanitizeInput($_POST['patient_id']);
        $doctor_id = (int) sanitizeInput($_POST['doctor_id']);
        $appointment_type = sanitizeInput($_POST['appointment_type']);
        $appointment_date = sanitizeInput($_POST['appointment_date']);
        $appointment_time_input = sanitizeInput($_POST['appointment_time']);
        $appointment_time = normalizeTime($appointment_time_input);
        $reason = sanitizeInput($_POST['reason']);
        $symptoms = sanitizeInput($_POST['symptoms']);
        $priority_level = sanitizeInput($_POST['priority_level']);
        $room_id = !empty($_POST['room_id']) ? (int) sanitizeInput($_POST['room_id']) : null;
        $booking_channel = sanitizeInput($_POST['booking_channel'] ?? 'clerk');
        $notification_channel = sanitizeInput($_POST['notification_channel'] ?? 'email');
        $notification_recipient = sanitizeInput($_POST['notification_recipient'] ?? '');
        $notification_message = sanitizeInput($_POST['notification_message'] ?? '');
        $should_notify = $notification_channel !== 'none' && !empty($notification_recipient);

        if (!$should_notify) {
            $notification_channel = 'none';
        }

        [$is_valid, $schedule_meta] = validateAppointmentSlot($db, $doctor_id, $room_id ?? 0, $appointment_date, $appointment_time);
        if (!$is_valid) {
            $_SESSION['error'] = $schedule_meta;
            header("Location: schedule.php");
            exit();
        }

        if (!$room_id && is_array($schedule_meta) && !empty($schedule_meta['room_id'])) {
            $room_id = (int) $schedule_meta['room_id'];
        }

        $query = "INSERT INTO appointments (
            appointment_number, patient_id, doctor_id, appointment_type, 
            appointment_date, appointment_time, reason, symptoms, priority_level, 
            room_id, booking_channel, notification_status, notification_channel, created_by
        ) VALUES (
            :appointment_number, :patient_id, :doctor_id, :appointment_type,
            :appointment_date, :appointment_time, :reason, :symptoms, :priority_level,
            :room_id, :booking_channel, :notification_status, :notification_channel, :created_by
        )";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':appointment_number', $appointment_number);
        $stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
        $stmt->bindParam(':appointment_type', $appointment_type);
        $stmt->bindParam(':appointment_date', $appointment_date);
        $stmt->bindParam(':appointment_time', $appointment_time);
        $stmt->bindParam(':reason', $reason);
        $stmt->bindParam(':symptoms', $symptoms);
        $stmt->bindParam(':priority_level', $priority_level);
        $stmt->bindValue(':room_id', $room_id, $room_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindParam(':booking_channel', $booking_channel);
        $stmt->bindValue(':notification_status', $should_notify ? 'pending' : 'skipped');
        $stmt->bindParam(':notification_channel', $notification_channel);
        $stmt->bindParam(':created_by', $_SESSION['user_id'], PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $appointment_id = (int) $db->lastInsertId();

            // Optional link placeholders for EHR & Billing systems
            upsertAppointmentLink($db, $appointment_id);

            // Queue notification if contact details provided
            if ($should_notify) {
                $message = !empty($notification_message)
                    ? $notification_message
                    : "Dear patient, your appointment (#{$appointment_number}) is scheduled on " . formatDate($appointment_date) . " at " . date('g:i A', strtotime($appointment_time)) . ".";
                recordAppointmentNotification($db, $appointment_id, $notification_channel, $notification_recipient, $message);
            }

            $_SESSION['success'] = "Appointment scheduled successfully! Appointment #: " . $appointment_number;
            header("Location: schedule.php");
            exit();
        }
    } catch (PDOException $exception) {
        $_SESSION['error'] = "Scheduling error: " . $exception->getMessage();
    }
}

// Get all appointments for admin (patient from patients table OR users with role patient; doctor from users)
$all_appointments = [];
try {
    $appointments_query = "SELECT a.*,
                          COALESCE(p.first_name, up.first_name) AS first_name,
                          COALESCE(p.last_name, up.last_name) AS last_name,
                          COALESCE(p.hospital_id, up.patient_id, CONCAT('ID:', a.patient_id)) AS hospital_id,
                          u.first_name AS doctor_fname,
                          u.last_name AS doctor_lname
                          FROM appointments a
                          LEFT JOIN patients p ON a.patient_id = p.id
                          LEFT JOIN users up ON a.patient_id = up.id AND up.role_id IN (SELECT id FROM roles WHERE role_name = 'patient')
                          LEFT JOIN users u ON a.doctor_id = u.id
                          ORDER BY a.appointment_date DESC, a.appointment_time DESC";
    $appointments_stmt = $db->prepare($appointments_query);
    $appointments_stmt->execute();
    $all_appointments = $appointments_stmt->fetchAll();
} catch (PDOException $exception) {
    $all_appointments = [];
}

// Get today's appointments for sidebar (same patient/doctor resolution as above)
try {
    $today_appointments_query = "SELECT a.*,
                                COALESCE(p.first_name, up.first_name, 'Unknown') AS first_name,
                                COALESCE(p.last_name, up.last_name, 'Patient') AS last_name,
                                COALESCE(p.hospital_id, up.patient_id, CONCAT('ID:', a.patient_id)) AS hospital_id,
                                u.first_name AS doctor_fname,
                                u.last_name AS doctor_lname
                                FROM appointments a
                                LEFT JOIN patients p ON a.patient_id = p.id
                                LEFT JOIN users up ON a.patient_id = up.id AND up.role_id IN (SELECT id FROM roles WHERE role_name = 'patient')
                                LEFT JOIN users u ON a.doctor_id = u.id
                                WHERE a.appointment_date >= CURDATE()
                                ORDER BY a.appointment_date ASC, a.appointment_time ASC
                                LIMIT 10";
    $today_appointments_stmt = $db->prepare($today_appointments_query);
    $today_appointments_stmt->execute();
    $today_appointments = $today_appointments_stmt->fetchAll();
} catch (PDOException $exception) {
    $today_appointments = [];
}

$public_upcoming_appointments = [];
try {
    $public_query = "SELECT ap.*, d.first_name AS doctor_fname, d.last_name AS doctor_lname
                     FROM appointments_public ap
                     LEFT JOIN doctors d ON ap.doctor_id = d.id
                     WHERE ap.appointment_date >= CURDATE()
                     ORDER BY ap.appointment_date ASC, ap.appointment_time ASC
                     LIMIT 10";
    $public_stmt = $db->prepare($public_query);
    $public_stmt->execute();
    $public_upcoming_appointments = $public_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    $public_upcoming_appointments = [];
}

$upcoming_card_appointments = $today_appointments;
foreach ($public_upcoming_appointments as $public_appt) {
    $upcoming_card_appointments[] = [
        'first_name' => $public_appt['full_name'] ?? 'Online Patient',
        'last_name' => '',
        'appointment_date' => $public_appt['appointment_date'] ?? null,
        'appointment_time' => $public_appt['appointment_time'] ?? ($public_appt['appointment_slot'] ?? null),
        'doctor_fname' => $public_appt['doctor_fname'] ?? '',
        'doctor_lname' => $public_appt['doctor_lname'] ?? '',
        'status' => $public_appt['status'] ?? 'pending',
        'booking_channel' => $public_appt['booking_channel'] ?? 'online',
        'visit_type' => $public_appt['visit_type'] ?? null,
        'source' => 'public'
    ];
}

include '../../includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Schedule Appointment</h1>
        <p class="text-gray-600 dark:text-gray-400">Admin can book appointments for any patient.</p>
    </div>
    <button onclick="openScheduleModal()" 
            class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <line x1="19" y1="8" x2="19" y2="14"></line>
            <line x1="22" y1="11" x2="16" y2="11"></line>
        </svg>
        Schedule New Appointment
    </button>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 gap-6 mb-6 lg:grid-cols-2">
    <!-- Upcoming Appointments -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                Upcoming Appointments
            </h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($upcoming_card_appointments) > 0): ?>
                <div class="space-y-4 max-h-96 overflow-y-auto pr-1">
                    <?php foreach ($upcoming_card_appointments as $appointment): ?>
                        <?php
                            $status = strtolower($appointment['status'] ?? 'scheduled');
                            $statusClassMap = [
                                'scheduled' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                'confirmed' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                'in_progress' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                'completed' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
                                'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                'cancelled' => 'bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200'
                            ];
                            $statusBadgeClass = $statusClassMap[$status] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
                            $channel = $appointment['booking_channel'] ?? '';
                            $patientName = trim(($appointment['first_name'] ?? '') . ' ' . ($appointment['last_name'] ?? ''));
                            $appointmentDateStr = $appointment['appointment_date'] ?? null;
                            $appointmentTimeStr = $appointment['appointment_time'] ?? null;
                            $formattedDate = $appointmentDateStr ? formatDate($appointmentDateStr) : 'Date TBD';
                            $formattedTime = $appointmentTimeStr ? formatTime($appointmentTimeStr) : 'Time TBD';
                        ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                    <?php echo htmlspecialchars($patientName !== '' ? $patientName : 'Unnamed Patient'); ?>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                    <?php echo $formattedDate . ' at ' . $formattedTime; ?>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Dr. <?php echo htmlspecialchars(trim(($appointment['doctor_fname'] ?? '') . ' ' . ($appointment['doctor_lname'] ?? ''))); ?>
                                </p>
                                <?php if (!empty($channel)): ?>
                                    <p class="text-[11px] text-gray-400 dark:text-gray-300">
                                        Channel: <?php echo ucfirst($channel); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="ml-3 flex-shrink-0">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $statusBadgeClass; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 dark:text-gray-600 mx-auto mb-2">
                        <path d="M8 2v4"></path>
                        <path d="M16 2v4"></path>
                        <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                        <path d="M3 10h18"></path>
                        <path d="m14 14-4-4"></path>
                        <path d="m10 14 4-4"></path>
                    </svg>
                    <p class="text-gray-500 dark:text-gray-400">No upcoming appointments</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Today's Summary -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                Today's Summary
            </h3>
            <div class="space-y-3">
                <?php
                $today_stats = [
                    'scheduled' => 0,
                    'confirmed' => 0,
                    'completed' => 0
                ];
                foreach ($today_appointments as $appt) {
                    if (isset($today_stats[$appt['status']])) {
                        $today_stats[$appt['status']]++;
                    }
                }
                ?>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Scheduled</span>
                    <span class="text-sm font-medium text-blue-600 dark:text-blue-400"><?php echo $today_stats['scheduled']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Confirmed</span>
                    <span class="text-sm font-medium text-green-600 dark:text-green-400"><?php echo $today_stats['confirmed']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Completed</span>
                    <span class="text-sm font-medium text-emerald-600 dark:text-emerald-400"><?php echo $today_stats['completed']; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Appointments Table -->
<div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
    <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">All Appointments</h3>
        <?php if (count($all_appointments) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Appointment #</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Patient</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Doctor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date & Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Priority</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($all_appointments as $apt): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($apt['appointment_number']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars(($apt['first_name'] ?? '') . ' ' . ($apt['last_name'] ?? '')); ?>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($apt['hospital_id'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars(($apt['doctor_fname'] ?? '') . ' ' . ($apt['doctor_lname'] ?? 'N/A')); ?>
                                    </div>
                                    <?php if (!empty($apt['doctor_specialization'])): ?>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo htmlspecialchars($apt['doctor_specialization']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    <?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?><br>
                                    <span class="text-gray-500 dark:text-gray-400"><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white capitalize">
                                    <?php echo htmlspecialchars($apt['appointment_type'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php 
                                        $priority = $apt['priority_level'] ?? 'medium';
                                        echo $priority === 'emergency' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 
                                            ($priority === 'high' ? 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' : 
                                            ($priority === 'low' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 
                                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'));
                                        ?>">
                                        <?php echo ucfirst($priority); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php 
                                        $status = $apt['status'] ?? 'scheduled';
                                        echo $status === 'completed' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                            ($status === 'cancelled' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 
                                            ($status === 'in_progress' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                                            ($status === 'confirmed' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 
                                            'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200')));
                                        ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-12">
                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 dark:text-gray-600 mx-auto mb-4">
                    <path d="M8 2v4"></path>
                    <path d="M16 2v4"></path>
                    <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                    <path d="M3 10h18"></path>
                    <path d="m9 16 2 2 4-4"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No appointments found</h3>
                <p class="text-gray-500 dark:text-gray-400">Click the button above to schedule a new appointment</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Schedule Appointment Modal -->
<div id="scheduleModal" class="hidden fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
    <!-- Background overlay -->
    <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75" onclick="closeScheduleModal()"></div>

    <!-- Modal panel -->
    <div class="relative bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Schedule New Appointment</h3>
                <button onclick="closeScheduleModal()" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            
            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="patient_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Patient *</label>
                            <select name="patient_id" id="patient_id" required 
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                <option value="">Select Patient</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo $patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['hospital_id'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="doctor_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Doctor *</label>
                            <select name="doctor_id" id="doctor_id" required 
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['id']; ?>">
                                        Dr. <?php echo htmlspecialchars(trim($doctor['first_name'] . ' ' . $doctor['last_name'])); ?><?php echo !empty($doctor['specialization']) ? ' - ' . htmlspecialchars($doctor['specialization']) : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="room_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Clinic Room</label>
                            <select name="room_id" id="room_id" 
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                <option value="">Assign Automatically</option>
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?php echo $room['id']; ?>">
                                        <?php echo $room['department'] . ' • ' . $room['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="appointment_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Appointment Type *</label>
                            <select name="appointment_type" id="appointment_type" required 
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                <option value="">Select Type</option>
                                <option value="consultation">Consultation</option>
                                <option value="followup">Follow-up</option>
                                <option value="checkup">Check-up</option>
                                <option value="emergency">Emergency</option>
                                <option value="teleconsult">Teleconsultation</option>
                            </select>
                        </div>

                        <div>
                            <label for="appointment_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Date *</label>
                            <input type="date" name="appointment_date" id="appointment_date" required 
                                min="<?php echo date('Y-m-d'); ?>"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>

                        <div>
                            <label for="appointment_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Time *</label>
                            <input type="time" name="appointment_time" id="appointment_time" required 
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Booking Channel</label>
                            <div class="flex items-center space-x-4 mt-1">
                                <label class="inline-flex items-center">
                                    <input type="radio" name="booking_channel" value="online" class="text-primary-600 focus:ring-primary-500">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Online</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="booking_channel" value="clerk" class="text-primary-600 focus:ring-primary-500" checked>
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Front Desk</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label for="priority_level" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Priority Level</label>
                            <select name="priority_level" id="priority_level" 
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                                <option value="high">High</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <label for="reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reason for Visit *</label>
                            <textarea name="reason" id="reason" rows="3" required 
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" 
                                placeholder="Brief description of the reason for appointment..."></textarea>
                        </div>

                        <div class="sm:col-span-2">
                            <label for="symptoms" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Symptoms</label>
                            <textarea name="symptoms" id="symptoms" rows="3" 
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" 
                                placeholder="List any symptoms the patient is experiencing..."></textarea>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Notification Preferences</h3>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <label for="notification_channel" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Channel</label>
                                <select name="notification_channel" id="notification_channel" 
                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                    <option value="email">Email</option>
                                    <option value="sms">SMS</option>
                                    <option value="none">Do not notify</option>
                                </select>
                            </div>
                            <div>
                                <label for="notification_recipient" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Recipient</label>
                                <input type="text" name="notification_recipient" id="notification_recipient" 
                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" 
                                    placeholder="Email or mobile number">
                            </div>
                        </div>
                        <div class="mt-4">
                            <label for="notification_message" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Custom Message</label>
                            <textarea name="notification_message" id="notification_message" rows="3"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                                placeholder="Optional personalized message to include in the notification."></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button type="button" onclick="closeScheduleModal()" 
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit" 
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            Schedule Appointment
                        </button>
                    </div>
                </form>
        </div>
    </div>
</div>

<script>
function openScheduleModal() {
    const modal = document.getElementById('scheduleModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeScheduleModal() {
    const modal = document.getElementById('scheduleModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeScheduleModal();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>