<?php
require_once '../../config/config.php';
requireAuth();
// Admin and appointment coordinators can manage doctor schedules
checkRole(['admin', 'appointment_coordinator']);

// Check if database connection exists
if (!$db) {
    $_SESSION['error'] = "Database connection failed. Please ensure MySQL is running and configured correctly.";
    header("Location: ../../index.php");
    exit();
}

$page_title = "Doctor Schedules";
$week_days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// PRC to Doctor Name mapping (based on doctors_insert.sql)
$prc_to_name = [
    'PRC-123456' => ['first_name' => 'Antonio', 'last_name' => 'Reyes'],
    'PRC-234567' => ['first_name' => 'Maria Isabel', 'last_name' => 'Santos'],
    'PRC-345678' => ['first_name' => 'Rafael', 'last_name' => 'Dela Cruz'],
    'PRC-456789' => ['first_name' => 'Liza', 'last_name' => 'Marquez'],
    'PRC-567890' => ['first_name' => 'Jerome', 'last_name' => 'Bautista'],
    'PRC-678901' => ['first_name' => 'Aileen', 'last_name' => 'Navarro']
];

// PRC to Image mapping
$prc_to_image = [
    'PRC-123456' => 'assets/img/doctors/Dr. Antonio Reyes.png',
    'PRC-234567' => 'assets/img/doctors/Dr. Maria Isabel Santos.png',
    'PRC-345678' => 'assets/img/doctors/Dr. Rafael Dela Cruz.png',
    'PRC-456789' => 'assets/img/doctors/Dr. Liza Marquez.png',
    'PRC-567890' => 'assets/img/doctors/Dr. Jerome Bautista.png',
    'PRC-678901' => 'assets/img/doctors/Dr. Aileen Navarro.png'
];

// Fetch all doctors from doctors table
$doctors = [];
try {
    // Check if optional columns exist in doctors table
    $has_first_name = false;
    $has_last_name = false;
    $has_profile_image = false;
$has_email = false;
    
    try {
        $check_cols = $db->query("SHOW COLUMNS FROM doctors");
        $columns = $check_cols->fetchAll(PDO::FETCH_COLUMN);
        $normalized_columns = array_map('strtolower', $columns);

        $has_first_name = in_array('first_name', $normalized_columns, true);
        $has_last_name = in_array('last_name', $normalized_columns, true);
        $has_profile_image = in_array('profile_image', $normalized_columns, true);
        $has_email = in_array('email', $normalized_columns, true);
    } catch (PDOException $e) {
        // Columns check failed, continue without them
    }
    
    // Build query for doctors table
    $doctors_query = "SELECT 
                        d.id,
                        d.prc_number,
                        d.specialty,
                        d.services,
                        d.years_experience,
                        d.consultation_fee,
                        d.status,
                        d.description,
                        d.education,
                        d.certifications,
                        d.created_at,
                        d.updated_at";
    
    if ($has_first_name) {
        $doctors_query .= ", d.first_name";
    } else {
        $doctors_query .= ", NULL AS first_name";
    }
    
    if ($has_last_name) {
        $doctors_query .= ", d.last_name";
    } else {
        $doctors_query .= ", NULL AS last_name";
    }
    
    if ($has_profile_image) {
        $doctors_query .= ", d.profile_image";
    } else {
        $doctors_query .= ", NULL AS profile_image";
    }
    
    if ($has_email) {
        $doctors_query .= ", d.email";
    } else {
        $doctors_query .= ", NULL AS email";
    }
    
    $doctors_query .= " FROM doctors d
                      WHERE d.status = 1
                      ORDER BY d.specialty, d.id";
    
    $doctors_stmt = $db->prepare($doctors_query);
    $doctors_stmt->execute();
    $doctors_raw = $doctors_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process doctors and enhance with names/images
    foreach ($doctors_raw as $doctor) {
        // Get name from table, PRC mapping, or default
        if (empty($doctor['first_name']) && isset($prc_to_name[$doctor['prc_number']])) {
            $doctor['first_name'] = $prc_to_name[$doctor['prc_number']]['first_name'];
            $doctor['last_name'] = $prc_to_name[$doctor['prc_number']]['last_name'];
        } elseif (empty($doctor['first_name'])) {
            $doctor['first_name'] = 'Dr.';
            $doctor['last_name'] = '';
        }
        
        // Get image from table, PRC mapping, or default
        if (empty($doctor['profile_image']) && isset($prc_to_image[$doctor['prc_number']])) {
            $doctor['profile_picture'] = $prc_to_image[$doctor['prc_number']];
        } elseif (!empty($doctor['profile_image'])) {
            $doctor['profile_picture'] = $doctor['profile_image'];
        } else {
            $doctor['profile_picture'] = '';
        }
        
        // Map specialty to specialization for consistency
        $doctor['specialization'] = $doctor['specialty'];
        
        // Use email from doctors table if available, otherwise try to get from users table
        if (empty($doctor['email'])) {
            // Try to get additional info from users table if email not in doctors table
            try {
                $user_query = "SELECT u.email, u.username, u.profile_picture, u.last_login
                              FROM users u
                              INNER JOIN roles r ON u.role_id = r.id
                              WHERE r.role_name = 'doctor' 
                              AND u.status = 'active'
                              AND (u.email LIKE CONCAT('%', :prc, '%') 
                                   OR CONCAT(u.first_name, ' ', u.last_name) LIKE CONCAT('%', :first, '%', :last, '%'))
                              LIMIT 1";
                $user_stmt = $db->prepare($user_query);
                $user_stmt->bindValue(':prc', $doctor['prc_number']);
                $user_stmt->bindValue(':first', $doctor['first_name']);
                $user_stmt->bindValue(':last', $doctor['last_name']);
                $user_stmt->execute();
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && !empty($user['email'])) {
                    $doctor['email'] = $user['email'];
                }
                
                if ($user) {
                    $doctor['username'] = $user['username'] ?? '';
                    $doctor['last_login'] = $user['last_login'] ?? null;
                    if (!empty($user['profile_picture'])) {
                        $doctor['profile_picture'] = $user['profile_picture'];
                    }
                } else {
                    $doctor['username'] = '';
                    $doctor['last_login'] = null;
                }
            } catch (PDOException $e) {
                // Keep email from doctors table or empty if not available
                $doctor['username'] = '';
                $doctor['last_login'] = null;
            }
        } else {
            // Email exists in doctors table, initialize other fields
            $doctor['username'] = '';
            $doctor['last_login'] = null;
        }
        
        // Ensure email is set (default to empty string if not available)
        if (!isset($doctor['email'])) {
            $doctor['email'] = '';
        }
        
        $doctors[] = $doctor;
    }
} catch (PDOException $exception) {
    error_log("Error fetching doctors from doctors table: " . $exception->getMessage());
    $doctors = [];
}

// Fetch rooms
try {
    $rooms = getClinicRooms($db);
} catch (PDOException $exception) {
    $rooms = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            $doctor_id = (int) sanitizeInput($_POST['doctor_id']);
            $day_of_week = (int) sanitizeInput($_POST['day_of_week']);
            $start_time = sanitizeInput($_POST['start_time']);
            $end_time = sanitizeInput($_POST['end_time']);
            $slot_duration = (int) sanitizeInput($_POST['slot_duration']);
            $max_daily = (int) sanitizeInput($_POST['max_daily']);
            $room_id = !empty($_POST['room_id']) ? (int) sanitizeInput($_POST['room_id']) : null;
            $notes = sanitizeInput($_POST['notes'] ?? '');

            if (strtotime($end_time) <= strtotime($start_time)) {
                throw new Exception("End time must be later than start time.");
            }

            // Prevent duplicate schedules
            $dup_stmt = $db->prepare("SELECT COUNT(*) AS total FROM doctor_schedules WHERE doctor_id = :doctor_id AND day_of_week = :day_of_week");
            $dup_stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
            $dup_stmt->bindParam(':day_of_week', $day_of_week, PDO::PARAM_INT);
            $dup_stmt->execute();
            $dup = $dup_stmt->fetch();
            if ($dup['total'] > 0) {
                throw new Exception("Doctor already has a schedule for the selected day.");
            }

            $insert = $db->prepare("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration_minutes, max_daily_appointments, room_id, notes, created_by)
                                    VALUES (:doctor_id, :day_of_week, :start_time, :end_time, :slot_duration, :max_daily, :room_id, :notes, :created_by)");
            $insert->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
            $insert->bindParam(':day_of_week', $day_of_week, PDO::PARAM_INT);
            $insert->bindParam(':start_time', $start_time);
            $insert->bindParam(':end_time', $end_time);
            $insert->bindParam(':slot_duration', $slot_duration, PDO::PARAM_INT);
            $insert->bindParam(':max_daily', $max_daily, PDO::PARAM_INT);
            $insert->bindValue(':room_id', $room_id, $room_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $insert->bindParam(':notes', $notes);
            $insert->bindParam(':created_by', $_SESSION['user_id'], PDO::PARAM_INT);
            $insert->execute();

            $_SESSION['success'] = "Schedule created successfully.";
        } elseif ($action === 'delete') {
            $schedule_id = (int) sanitizeInput($_POST['schedule_id']);
            $delete = $db->prepare("DELETE FROM doctor_schedules WHERE id = :id");
            $delete->bindParam(':id', $schedule_id, PDO::PARAM_INT);
            $delete->execute();
            $_SESSION['success'] = "Schedule removed.";
        }
    } catch (Exception $exception) {
        $_SESSION['error'] = $exception->getMessage();
    }

    header("Location: manage_schedules.php");
    exit();
}

// Fetch all schedules from doctor_schedules table (doctor_id references doctors.id)
// Room columns optional so query works when clinic_rooms table is missing
try {
    $schedules_query = "SELECT 
                            ds.*,
                            d.id AS doctor_id,
                            d.prc_number,
                            d.specialty,
                            creator.first_name AS created_by_fname,
                            creator.last_name AS created_by_lname
                        FROM doctor_schedules ds
                        LEFT JOIN doctors d ON ds.doctor_id = d.id
                        LEFT JOIN users creator ON ds.created_by = creator.id
                        WHERE d.status = 1
                        ORDER BY d.specialty, ds.day_of_week, ds.start_time";
    $schedules_stmt = $db->prepare($schedules_query);
    $schedules_stmt->execute();
    $schedules = $schedules_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize schedules by doctor for better display
    $doctors_with_schedules = [];
    foreach ($doctors as $doctor) {
        $doctor['schedules'] = [];
        $doctors_with_schedules[$doctor['id']] = $doctor;
    }
    
    foreach ($schedules as $schedule) {
        $doctor_id = $schedule['doctor_id'];
        if (isset($doctors_with_schedules[$doctor_id])) {
            $doctors_with_schedules[$doctor_id]['schedules'][] = $schedule;
        } else {
            // Doctor not in active list but has schedules - create entry
            if (!isset($doctors_with_schedules[$doctor_id])) {
                $doctor_name = isset($prc_to_name[$schedule['prc_number']]) 
                    ? $prc_to_name[$schedule['prc_number']] 
                    : ['first_name' => 'Dr.', 'last_name' => ''];
                
                $doctor_image = isset($prc_to_image[$schedule['prc_number']]) 
                    ? $prc_to_image[$schedule['prc_number']] 
                    : '';
                
                $doctors_with_schedules[$doctor_id] = [
                    'id' => $doctor_id,
                    'prc_number' => $schedule['prc_number'] ?? '',
                    'first_name' => $doctor_name['first_name'],
                    'last_name' => $doctor_name['last_name'],
                    'specialization' => $schedule['specialty'] ?? '',
                    'specialty' => $schedule['specialty'] ?? '',
                    'profile_picture' => $doctor_image,
                    'email' => '',
                    'username' => '',
                    'status' => 1,
                    'last_login' => null,
                    'schedules' => []
                ];
            }
            $doctors_with_schedules[$doctor_id]['schedules'][] = $schedule;
        }
    }
} catch (PDOException $exception) {
    $schedules = [];
    $doctors_with_schedules = [];
    error_log("Error fetching schedules: " . $exception->getMessage());
}

include '../../includes/header.php';
?>

<div class="mb-6 flex items-center justify-between">
    <div>
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Doctor Schedules</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">Manage doctor schedules and clinic hours</p>
    </div>
    <button onclick="document.getElementById('addScheduleModal').classList.remove('hidden')" 
            class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors flex items-center space-x-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        <span>Add Schedule</span>
    </button>
</div>

<!-- Doctor Cards Grid -->
<?php if (count($doctors) > 0): ?>
    <?php 
    // Use doctors_with_schedules if available, otherwise use doctors array
    $display_doctors = isset($doctors_with_schedules) && !empty($doctors_with_schedules) ? $doctors_with_schedules : array_map(function($doc) {
        $doc['schedules'] = [];
        return $doc;
    }, $doctors);
    ?>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($display_doctors as $doctor): 
            // Get doctor image path
            $doctor_image = '';
            if (!empty($doctor['profile_picture'])) {
                $doctor_image = BASE_URL . '/' . $doctor['profile_picture'];
                // Check if file exists
                if (!file_exists(__DIR__ . '/../../' . $doctor['profile_picture'])) {
                    $doctor_image = '';
                }
            }
            
            // Try to find default doctor image based on name
            if (empty($doctor_image)) {
                $doctor_name = strtolower($doctor['first_name'] . ' ' . $doctor['last_name']);
                $default_images = [
                    'antonio reyes' => BASE_URL . '/assets/img/doctors/Dr. Antonio Reyes.png',
                    'maria isabel santos' => BASE_URL . '/assets/img/doctors/Dr. Maria Isabel Santos.png',
                    'rafael dela cruz' => BASE_URL . '/assets/img/doctors/Dr. Rafael Dela Cruz.png',
                    'liza marquez' => BASE_URL . '/assets/img/doctors/Dr. Liza Marquez.png',
                    'jerome bautista' => BASE_URL . '/assets/img/doctors/Dr. Jerome Bautista.png',
                    'aileen navarro' => BASE_URL . '/assets/img/doctors/Dr. Aileen Navarro.png',
                ];
                
                foreach ($default_images as $name => $path) {
                    if (strpos($doctor_name, $name) !== false) {
                        $doctor_image = $path;
                        break;
                    }
                }
            }
            
            // Generate initials if no image
            $initials = '';
            if (empty($doctor_image)) {
                $first_initial = strtoupper(substr($doctor['first_name'] ?? '', 0, 1));
                $last_initial = strtoupper(substr($doctor['last_name'] ?? '', 0, 1));
                $initials = $first_initial . $last_initial;
            }
            
            $schedule_count = count($doctor['schedules'] ?? []);
        ?>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-200 dark:border-gray-700">
                <!-- Doctor Header with Image -->
                <div class="bg-gradient-to-r from-primary-500 to-primary-600 p-6 text-center">
                    <div class="flex justify-center mb-4">
                        <?php if (!empty($doctor_image)): ?>
                            <img src="<?php echo htmlspecialchars($doctor_image); ?>" 
                                 alt="Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>" 
                                 class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-lg">
                        <?php else: ?>
                            <div class="w-24 h-24 rounded-full bg-white flex items-center justify-center border-4 border-white shadow-lg">
                                <span class="text-3xl font-bold text-primary-600"><?php echo htmlspecialchars($initials); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <h3 class="text-xl font-bold text-white">
                        Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                    </h3>
                    <p class="text-primary-100 text-sm mt-1">
                        <?php echo htmlspecialchars($doctor['specialization'] ?? 'General Practitioner'); ?>
                    </p>
                </div>
                
                <!-- Doctor Info -->
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                    <div class="space-y-3">
                        <!-- Email Display -->
                        <div class="flex items-center p-2.5 rounded-lg bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 shadow-sm">
                            <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center mr-3">
                                <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Email</p>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate" title="<?php echo htmlspecialchars($doctor['email'] ?? 'N/A'); ?>">
                                    <?php echo !empty($doctor['email']) ? htmlspecialchars($doctor['email']) : '<span class="text-gray-400 dark:text-gray-500 italic">N/A</span>'; ?>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Status and Schedule Count -->
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo ($doctor['status'] == 1 || $doctor['status'] === 'active') ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'; ?>">
                                <?php echo ($doctor['status'] == 1 || $doctor['status'] === 'active') ? 'Active' : 'Inactive'; ?>
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                                <?php echo $schedule_count; ?> schedule<?php echo $schedule_count !== 1 ? 's' : ''; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Schedules & Appointments Section -->
                <div class="p-4 space-y-5">
                    <div>
                        <?php if (!empty($doctor['schedules'])): ?>
                            <div class="space-y-2 max-h-64 overflow-y-auto">
                                <?php foreach ($doctor['schedules'] as $schedule): ?>
                                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 border border-gray-200 dark:border-gray-600">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center space-x-2 mb-1">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                        <?php echo $week_days[$schedule['day_of_week']] ?? 'Unknown'; ?>
                                                    </span>
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                                        <?php echo formatTime($schedule['start_time']); ?> - <?php echo formatTime($schedule['end_time']); ?>
                                                    </span>
                                                </div>
                                                <?php if (!empty($schedule['room_name'])): ?>
                                                    <p class="text-xs text-gray-600 dark:text-gray-300">
                                                        <span class="font-medium">Room:</span> <?php echo htmlspecialchars($schedule['room_department'] ?? ''); ?> • <?php echo htmlspecialchars($schedule['room_name']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <div class="flex items-center space-x-3 mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    <span>Slot: <?php echo $schedule['slot_duration_minutes']; ?> mins</span>
                                                    <span>Max: <?php echo $schedule['max_daily_appointments']; ?>/day</span>
                                                </div>
                                                <?php if (!empty($schedule['notes'])): ?>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 italic">
                                                        <?php echo htmlspecialchars(substr($schedule['notes'], 0, 60)) . (strlen($schedule['notes']) > 60 ? '...' : ''); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <form method="POST" class="ml-2" onsubmit="event.preventDefault(); showConfirmAlert('Remove Schedule', 'Are you sure you want to remove this schedule?').then(confirmed => { if(confirmed) this.submit(); }); return false;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                                <button type="submit" class="text-red-500 hover:text-red-700 transition-colors" title="Delete Schedule">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-6">
                                <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <p class="text-sm text-gray-500 dark:text-gray-400">No schedules assigned</p>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="text-center py-16 bg-white dark:bg-gray-800 rounded-lg shadow">
        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 dark:text-gray-600 mx-auto mb-4">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
        <p class="text-gray-500 dark:text-gray-400 text-lg">No doctors found</p>
        <p class="text-gray-400 dark:text-gray-500 text-sm mt-2">Please add doctors to the system first</p>
    </div>
<?php endif; ?>

<!-- Add Schedule Modal -->
<div id="addScheduleModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Add New Schedule</h3>
            <button onclick="document.getElementById('addScheduleModal').classList.add('hidden')" 
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
            </div>
        <form method="POST" class="p-6 space-y-4">
                    <input type="hidden" name="action" value="create">
                    <div>
                <label for="doctor_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Doctor *</label>
                        <select id="doctor_id" name="doctor_id" required 
                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Doctor</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>">
                            Dr. <?php echo $doctor['first_name'] . ' ' . $doctor['last_name']; ?>
                            <?php if (!empty($doctor['specialization'])): ?>
                                - <?php echo htmlspecialchars($doctor['specialization']); ?>
                            <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                <label for="day_of_week" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Day of Week *</label>
                        <select id="day_of_week" name="day_of_week" required 
                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Day</option>
                            <?php foreach ($week_days as $index => $label): ?>
                                <option value="<?php echo $index; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
            <div class="grid grid-cols-2 gap-4">
                        <div>
                    <label for="start_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Time *</label>
                            <input type="time" id="start_time" name="start_time" required
                        class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                    <label for="end_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Time *</label>
                            <input type="time" id="end_time" name="end_time" required
                        class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
            <div class="grid grid-cols-2 gap-4">
                        <div>
                    <label for="slot_duration" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Slot (mins)</label>
                            <input type="number" id="slot_duration" name="slot_duration" min="10" step="5" value="30"
                        class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                    <label for="max_daily" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Max per Day</label>
                            <input type="number" id="max_daily" name="max_daily" min="1" value="24"
                        class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                    <div>
                <label for="room_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Default Room</label>
                        <select id="room_id" name="room_id"
                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">None</option>
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?php echo $room['id']; ?>">
                                    <?php echo $room['department'] . ' • ' . $room['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                        <textarea id="notes" name="notes" rows="3"
                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                            placeholder="Optional instructions or reminders"></textarea>
                    </div>
            <div class="flex justify-end space-x-3 pt-4">
                <button type="button" onclick="document.getElementById('addScheduleModal').classList.add('hidden')"
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                    Cancel
                </button>
                        <button type="submit"
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            Save Schedule
                        </button>
                    </div>
                </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

