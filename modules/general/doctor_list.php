<?php
require_once '../../config/config.php';
requireAuth();
checkRole(['admin', 'receptionist', 'doctor', 'nurse', 'appointment_coordinator']);

$page_title = "Doctor List";

// Filter parameters
$specialty_filter = $_GET['specialty'] ?? '';
$doctor_type_filter = $_GET['doctor_type'] ?? '';
$availability_filter = $_GET['availability'] ?? '';
$search_term = $_GET['search'] ?? '';

// Day names for schedule display
$day_names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
$day_full_names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Specialty categories with display names
$specialty_categories = [
    'surgeon' => ['name' => 'Surgeon', 'color' => 'red'],
    'cardiologist' => ['name' => 'Cardiologist', 'color' => 'pink'],
    'pediatrician' => ['name' => 'Pediatrician', 'color' => 'blue'],
    'neurologist' => ['name' => 'Neurologist', 'color' => 'purple'],
    'orthopedic' => ['name' => 'Orthopedic', 'color' => 'orange'],
    'obgyn' => ['name' => 'OB-GYN', 'color' => 'rose'],
    'internal_medicine' => ['name' => 'Internal Medicine', 'color' => 'teal'],
    'dermatologist' => ['name' => 'Dermatologist', 'color' => 'amber'],
    'oncologist' => ['name' => 'Oncologist', 'color' => 'indigo'],
    'psychiatrist' => ['name' => 'Psychiatrist', 'color' => 'cyan'],
    'radiologist' => ['name' => 'Radiologist', 'color' => 'slate'],
    'anesthesiologist' => ['name' => 'Anesthesiologist', 'color' => 'emerald'],
    'general_practitioner' => ['name' => 'General Practitioner', 'color' => 'green'],
    'other' => ['name' => 'Other', 'color' => 'gray'],
];

// Doctor types
$doctor_types = [
    'attending' => ['name' => 'Attending', 'color' => 'blue'],
    'resident' => ['name' => 'Resident', 'color' => 'yellow'],
    'fellow' => ['name' => 'Fellow', 'color' => 'purple'],
    'consultant' => ['name' => 'Consultant', 'color' => 'green'],
];

// Availability statuses
$availability_statuses = [
    'available' => ['name' => 'Available', 'color' => 'green'],
    'on_call' => ['name' => 'On-Call', 'color' => 'orange'],
    'busy' => ['name' => 'Busy', 'color' => 'red'],
    'off_duty' => ['name' => 'Off Duty', 'color' => 'gray'],
    'on_leave' => ['name' => 'On Leave', 'color' => 'blue'],
];

// PRC to Doctor Name mapping
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

// Fetch doctors
$doctors = [];
try {
    $query = "SELECT d.* FROM doctors d WHERE d.status = 1";
    $params = [];
    
    if (!empty($specialty_filter)) {
        $query .= " AND d.specialty_category = :specialty";
        $params[':specialty'] = $specialty_filter;
    }
    
    if (!empty($doctor_type_filter)) {
        $query .= " AND d.doctor_type = :doctor_type";
        $params[':doctor_type'] = $doctor_type_filter;
    }
    
    if (!empty($availability_filter)) {
        $query .= " AND d.availability_status = :availability";
        $params[':availability'] = $availability_filter;
    }
    
    if (!empty($search_term)) {
        $query .= " AND (d.first_name LIKE :search OR d.last_name LIKE :search OR d.specialty LIKE :search OR d.prc_number LIKE :search)";
        $params[':search'] = "%$search_term%";
    }
    
    $query .= " ORDER BY d.specialty_category, d.last_name, d.first_name";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $doctors_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process doctors and enhance with names/images
    foreach ($doctors_raw as $doctor) {
        // Get name from table or PRC mapping
        if (empty($doctor['first_name']) && isset($prc_to_name[$doctor['prc_number']])) {
            $doctor['first_name'] = $prc_to_name[$doctor['prc_number']]['first_name'];
            $doctor['last_name'] = $prc_to_name[$doctor['prc_number']]['last_name'];
        }
        
        // Get image
        if (empty($doctor['profile_image']) && isset($prc_to_image[$doctor['prc_number']])) {
            $doctor['profile_image'] = $prc_to_image[$doctor['prc_number']];
        }
        
        // Default values for new columns if not set
        $doctor['specialty_category'] = $doctor['specialty_category'] ?? 'general_practitioner';
        $doctor['doctor_type'] = $doctor['doctor_type'] ?? 'attending';
        $doctor['availability_status'] = $doctor['availability_status'] ?? 'available';
        
        // Fetch schedule for this doctor
        try {
            $sched_stmt = $db->prepare("SELECT * FROM doctor_availability WHERE doctor_id = :doctor_id AND is_available = 1 ORDER BY day_of_week, start_time");
            $sched_stmt->bindValue(':doctor_id', $doctor['id'], PDO::PARAM_INT);
            $sched_stmt->execute();
            $doctor['schedule'] = $sched_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Try doctor_schedules table as fallback
            try {
                $sched_stmt = $db->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = :doctor_id ORDER BY day_of_week, start_time");
                $sched_stmt->bindValue(':doctor_id', $doctor['id'], PDO::PARAM_INT);
                $sched_stmt->execute();
                $doctor['schedule'] = $sched_stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e2) {
                $doctor['schedule'] = [];
            }
        }
        
        $doctors[] = $doctor;
    }
} catch (PDOException $e) {
    error_log("Failed to fetch doctors: " . $e->getMessage());
}

// Handle status update (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $user_role = strtolower(trim($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? ''));
    if ($user_role === 'admin') {
        $doctor_id = (int)$_POST['doctor_id'];
        $new_status = $_POST['availability_status'];
        
        if (array_key_exists($new_status, $availability_statuses)) {
            try {
                $update_stmt = $db->prepare("UPDATE doctors SET availability_status = :status WHERE id = :id");
                $update_stmt->bindValue(':status', $new_status);
                $update_stmt->bindValue(':id', $doctor_id, PDO::PARAM_INT);
                $update_stmt->execute();
                $_SESSION['success'] = "Doctor status updated successfully.";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Failed to update status: " . $e->getMessage();
            }
        }
        header("Location: doctor_list.php?" . http_build_query($_GET));
        exit();
    }
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Doctor List</h1>
    <p class="text-gray-600 dark:text-gray-400">View all doctors, their specialties, availability, and schedules</p>
</div>

<div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
    <div class="px-4 py-5 sm:p-6">
        <!-- Filter Tabs -->
        <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex flex-wrap gap-4 pb-4">
                <!-- Specialty Filter -->
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Specialty:</span>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['specialty' => ''])); ?>" 
                       class="px-3 py-1.5 text-sm font-medium rounded-full transition-colors <?php echo empty($specialty_filter) ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'; ?>">
                        All
                    </a>
                    <?php foreach (array_slice($specialty_categories, 0, 6) as $key => $spec): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['specialty' => $key])); ?>" 
                       class="px-3 py-1.5 text-sm font-medium rounded-full transition-colors <?php echo $specialty_filter === $key ? 'bg-'.$spec['color'].'-600 text-white' : 'bg-'.$spec['color'].'-50 dark:bg-'.$spec['color'].'-900/20 text-'.$spec['color'].'-700 dark:text-'.$spec['color'].'-300 hover:bg-'.$spec['color'].'-100 dark:hover:bg-'.$spec['color'].'-900/30'; ?>">
                        <?php echo $spec['name']; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="flex flex-wrap gap-4 pb-4">
                <!-- Doctor Type Filter -->
                <div class="flex items-center gap-2 mr-6">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Type:</span>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['doctor_type' => ''])); ?>" 
                       class="px-3 py-1.5 text-sm font-medium rounded-full transition-colors <?php echo empty($doctor_type_filter) ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'; ?>">
                        All
                    </a>
                    <?php foreach ($doctor_types as $key => $type): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['doctor_type' => $key])); ?>" 
                       class="px-3 py-1.5 text-sm font-medium rounded-full transition-colors <?php echo $doctor_type_filter === $key ? 'bg-'.$type['color'].'-600 text-white' : 'bg-'.$type['color'].'-50 dark:bg-'.$type['color'].'-900/20 text-'.$type['color'].'-700 dark:text-'.$type['color'].'-300 hover:bg-'.$type['color'].'-100 dark:hover:bg-'.$type['color'].'-900/30'; ?>">
                        <?php echo $type['name']; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <!-- Availability Filter -->
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Status:</span>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['availability' => ''])); ?>" 
                       class="px-3 py-1.5 text-sm font-medium rounded-full transition-colors <?php echo empty($availability_filter) ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'; ?>">
                        All
                    </a>
                    <?php foreach ($availability_statuses as $key => $status): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['availability' => $key])); ?>" 
                       class="px-3 py-1.5 text-sm font-medium rounded-full transition-colors flex items-center gap-1.5 <?php echo $availability_filter === $key ? 'bg-'.$status['color'].'-600 text-white' : 'bg-'.$status['color'].'-50 dark:bg-'.$status['color'].'-900/20 text-'.$status['color'].'-700 dark:text-'.$status['color'].'-300 hover:bg-'.$status['color'].'-100 dark:hover:bg-'.$status['color'].'-900/30'; ?>">
                        <span class="w-2 h-2 rounded-full bg-current"></span>
                        <?php echo $status['name']; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Search Bar -->
        <div class="mb-6">
            <form method="GET" class="flex items-center gap-2">
                <?php foreach ($_GET as $key => $value): if ($key !== 'search'): ?>
                <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                <?php endif; endforeach; ?>
                <div class="relative flex-1 max-w-md">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_term); ?>" 
                           placeholder="Search by name, specialty, or PRC number..."
                           class="w-full border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm py-2 pl-10 pr-4 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                    </div>
                </div>
                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                    Search
                </button>
                <?php if (!empty($search_term) || !empty($specialty_filter) || !empty($doctor_type_filter) || !empty($availability_filter)): ?>
                <a href="doctor_list.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500 transition-colors">
                    Clear
                </a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Results Count -->
        <div class="mb-4">
            <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo count($doctors); ?> doctor(s) found</p>
        </div>
        
        <!-- Doctor Cards Grid -->
        <?php if (empty($doctors)): ?>
            <div class="text-center py-12">
                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 dark:text-gray-600 mx-auto mb-4">
                    <path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"></path>
                    <path d="M8 15v1a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6v-4"></path>
                    <circle cx="20" cy="10" r="2"></circle>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No doctors found</h3>
                <p class="text-gray-500 dark:text-gray-400">Try adjusting your search or filter criteria</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($doctors as $doctor): 
                    $full_name = trim(($doctor['first_name'] ?? 'Dr.') . ' ' . ($doctor['last_name'] ?? ''));
                    $initials = strtoupper(substr($doctor['first_name'] ?? 'D', 0, 1) . substr($doctor['last_name'] ?? 'R', 0, 1));
                    $spec_cat = $doctor['specialty_category'] ?? 'general_practitioner';
                    $doc_type = $doctor['doctor_type'] ?? 'attending';
                    $avail_status = $doctor['availability_status'] ?? 'available';
                    
                    $spec_info = $specialty_categories[$spec_cat] ?? $specialty_categories['other'];
                    $type_info = $doctor_types[$doc_type] ?? $doctor_types['attending'];
                    $status_info = $availability_statuses[$avail_status] ?? $availability_statuses['available'];
                    
                    // Check for profile image
                    $profile_image = null;
                    if (!empty($doctor['profile_image'])) {
                        $img_path = __DIR__ . '/../../' . ltrim($doctor['profile_image'], '/');
                        if (file_exists($img_path)) {
                            $profile_image = BASE_URL . '/' . ltrim($doctor['profile_image'], '/');
                        }
                    }
                ?>
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <!-- Header with Specialty Color -->
                    <div class="h-2 bg-gradient-to-r from-<?php echo $spec_info['color']; ?>-500 to-<?php echo $spec_info['color']; ?>-400"></div>
                    
                    <div class="p-6">
                        <!-- Profile Section -->
                        <div class="flex items-start gap-4 mb-4">
                            <div class="relative flex-shrink-0">
                                <?php if ($profile_image): ?>
                                <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="<?php echo htmlspecialchars($full_name); ?>"
                                     class="w-16 h-16 rounded-full object-cover border-2 border-<?php echo $spec_info['color']; ?>-200">
                                <?php else: ?>
                                <div class="w-16 h-16 rounded-full bg-gradient-to-br from-<?php echo $spec_info['color']; ?>-400 to-<?php echo $spec_info['color']; ?>-600 flex items-center justify-center border-2 border-<?php echo $spec_info['color']; ?>-200">
                                    <span class="text-white font-bold text-lg"><?php echo $initials; ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Availability Status Indicator -->
                                <span class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-<?php echo $status_info['color']; ?>-500 border-2 border-white dark:border-gray-800" title="<?php echo $status_info['name']; ?>"></span>
                            </div>
                            
                            <div class="flex-1 min-w-0">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white truncate">Dr. <?php echo htmlspecialchars($full_name); ?></h3>
                                <p class="text-sm text-<?php echo $spec_info['color']; ?>-600 dark:text-<?php echo $spec_info['color']; ?>-400 font-medium"><?php echo htmlspecialchars($doctor['specialty'] ?? $spec_info['name']); ?></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">PRC: <?php echo htmlspecialchars($doctor['prc_number']); ?></p>
                            </div>
                        </div>
                        
                        <!-- Badges -->
                        <div class="flex flex-wrap gap-2 mb-4">
                            <!-- Doctor Type Badge -->
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-<?php echo $type_info['color']; ?>-100 text-<?php echo $type_info['color']; ?>-800 dark:bg-<?php echo $type_info['color']; ?>-900/30 dark:text-<?php echo $type_info['color']; ?>-300">
                                <?php echo $type_info['name']; ?>
                            </span>
                            
                            <!-- Availability Status Badge -->
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-<?php echo $status_info['color']; ?>-100 text-<?php echo $status_info['color']; ?>-800 dark:bg-<?php echo $status_info['color']; ?>-900/30 dark:text-<?php echo $status_info['color']; ?>-300">
                                <span class="w-1.5 h-1.5 rounded-full bg-<?php echo $status_info['color']; ?>-500"></span>
                                <?php echo $status_info['name']; ?>
                            </span>
                            
                            <!-- Years Experience -->
                            <?php if (!empty($doctor['years_experience'])): ?>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                <?php echo $doctor['years_experience']; ?> yrs exp
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Schedule -->
                        <div class="mb-4">
                            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Schedule</h4>
                            <?php if (!empty($doctor['schedule'])): ?>
                            <div class="flex flex-wrap gap-1">
                                <?php 
                                $schedule_days = [];
                                foreach ($doctor['schedule'] as $sched) {
                                    $day = $sched['day_of_week'];
                                    $time = date('g:ia', strtotime($sched['start_time'])) . '-' . date('g:ia', strtotime($sched['end_time']));
                                    if (!isset($schedule_days[$day])) {
                                        $schedule_days[$day] = [];
                                    }
                                    $schedule_days[$day][] = $time;
                                }
                                foreach ($schedule_days as $day => $times): 
                                ?>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded px-2 py-1 text-xs">
                                    <span class="font-semibold text-gray-700 dark:text-gray-300"><?php echo $day_names[$day]; ?></span>
                                    <span class="text-gray-500 dark:text-gray-400"><?php echo implode(', ', $times); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <p class="text-xs text-gray-400 dark:text-gray-500 italic">No schedule set</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Consultation Fee -->
                        <?php if (!empty($doctor['consultation_fee'])): ?>
                        <div class="flex items-center justify-between pt-4 border-t border-gray-100 dark:border-gray-700">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Consultation Fee</span>
                            <span class="text-lg font-bold text-primary-600 dark:text-primary-400">₱<?php echo number_format($doctor['consultation_fee'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Actions -->
                        <div class="mt-4 flex gap-2">
                            <a href="<?php echo BASE_URL; ?>/modules/appointments/schedule.php?doctor_id=<?php echo $doctor['id']; ?>" 
                               class="flex-1 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-lg transition-colors text-center">
                                Book Appointment
                            </a>
                            <a href="<?php echo BASE_URL; ?>/modules/appointments/manage_schedules.php?doctor_id=<?php echo $doctor['id']; ?>" 
                               class="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg transition-colors">
                                View Schedule
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
