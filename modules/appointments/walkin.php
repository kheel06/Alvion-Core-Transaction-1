<?php
require_once '../../config/config.php';
requireAuth();
checkRole(['admin', 'receptionist', 'appointment_coordinator', 'doctor']);

$page_title = "Walk-in Patients";

// Handle walk-in appointment
if ($_POST) {
    try {
        // First, check if patient exists or create new
        $patient_id = sanitizeInput($_POST['patient_id']);
        
        if (empty($patient_id) && !empty($_POST['new_patient'])) {
            // Create new patient for walk-in
            $hospital_id = generateHospitalId();
            $query = "INSERT INTO patients (hospital_id, first_name, last_name, contact_number, created_by) 
                      VALUES (:hospital_id, :first_name, :last_name, :contact_number, :created_by)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':hospital_id', $hospital_id);
            $stmt->bindParam(':first_name', sanitizeInput($_POST['first_name']));
            $stmt->bindParam(':last_name', sanitizeInput($_POST['last_name']));
            $stmt->bindParam(':contact_number', sanitizeInput($_POST['contact_number']));
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            $stmt->execute();
            
            $patient_id = $db->lastInsertId();
        }

        // Create walk-in appointment
        $appointment_number = generateAppointmentNumber();
        $doctor_id = (int) sanitizeInput($_POST['doctor_id']);
        $slot = findNextAvailableSlot($db, $doctor_id, date('Y-m-d'));

        if (!$slot) {
            throw new Exception("No available slots for the selected doctor in the next few days.");
        }

        $appointment_date = $slot['date'];
        $appointment_time = $slot['time'];
        $room_id = $slot['room_id'];

        [$is_valid, $schedule_meta] = validateAppointmentSlot($db, $doctor_id, $room_id ?? 0, $appointment_date, $appointment_time);
        if (!$is_valid) {
            throw new Exception($schedule_meta);
        }

        if (!$room_id && is_array($schedule_meta) && !empty($schedule_meta['room_id'])) {
            $room_id = (int) $schedule_meta['room_id'];
        }

        $query = "INSERT INTO appointments (
            appointment_number, patient_id, doctor_id, appointment_type, 
            appointment_date, appointment_time, reason, symptoms, priority_level, 
            is_walkin, room_id, booking_channel, notification_status, notification_channel, created_by
        ) VALUES (
            :appointment_number, :patient_id, :doctor_id, :appointment_type,
            :appointment_date, :appointment_time, :reason, :symptoms, :priority_level,
            TRUE, :room_id, :booking_channel, 'skipped', 'none', :created_by
        )";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':appointment_number', $appointment_number);
        $stmt->bindParam(':patient_id', $patient_id);
        $stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
        $stmt->bindParam(':appointment_type', sanitizeInput($_POST['appointment_type']));
        $stmt->bindParam(':appointment_date', $appointment_date);
        $stmt->bindParam(':appointment_time', $appointment_time);
        $stmt->bindParam(':reason', sanitizeInput($_POST['reason']));
        $stmt->bindParam(':symptoms', sanitizeInput($_POST['symptoms']));
        $stmt->bindParam(':priority_level', sanitizeInput($_POST['priority_level']));
        $stmt->bindValue(':room_id', $room_id, $room_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':booking_channel', 'walkin');
        $stmt->bindParam(':created_by', $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $appointment_id = (int) $db->lastInsertId();
            upsertAppointmentLink($db, $appointment_id);

            $_SESSION['success'] = "Walk-in patient registered successfully! Appointment #: " . $appointment_number .
                " on " . formatDate($appointment_date) . " at " . date('g:i A', strtotime($appointment_time)) . ".";
            header("Location: walkin.php");
            exit();
        }
    } catch (PDOException $exception) {
        $_SESSION['error'] = "Error processing walk-in: " . $exception->getMessage();
    } catch (Exception $exception) {
        $_SESSION['error'] = $exception->getMessage();
    }
}

// Get available doctors (users.role_id -> roles.role_name = 'doctor')
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

// Get recent patients for quick selection
try {
    $patients_query = "SELECT id, hospital_id, first_name, last_name FROM patients WHERE status = 'active' ORDER BY created_at DESC LIMIT 10";
    $patients_stmt = $db->prepare($patients_query);
    $patients_stmt->execute();
    $patients = $patients_stmt->fetchAll();
} catch (PDOException $exception) {
    $patients = [];
}

// Get all walk-in appointments
$walkin_appointments = [];
try {
    $walkin_query = "SELECT a.*, p.first_name, p.last_name, p.hospital_id, 
                    u.first_name as doctor_fname, u.last_name as doctor_lname
                    FROM appointments a 
                    LEFT JOIN patients p ON a.patient_id = p.id 
                    LEFT JOIN users u ON a.doctor_id = u.id
                    WHERE a.is_walkin = 1
                    ORDER BY a.appointment_date DESC, a.appointment_time DESC";
    $walkin_stmt = $db->prepare($walkin_query);
    $walkin_stmt->execute();
    $walkin_appointments = $walkin_stmt->fetchAll();
} catch (PDOException $exception) {
    $walkin_appointments = [];
}

include '../../includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Walk-in Patients</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">Add walk-ins into the system. Assign them to available doctors.</p>
    </div>
    <button onclick="openWalkinModal()" 
            class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <line x1="19" y1="8" x2="19" y2="14"></line>
            <line x1="22" y1="11" x2="16" y2="11"></line>
        </svg>
        Register Walk-in
    </button>
</div>

<!-- Walk-in Appointments Table -->
<div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
    <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Walk-in Appointments</h3>
        <?php if (count($walkin_appointments) > 0): ?>
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
                        <?php foreach ($walkin_appointments as $apt): ?>
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
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <line x1="19" y1="8" x2="19" y2="14"></line>
                    <line x1="22" y1="11" x2="16" y2="11"></line>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No walk-in appointments found</h3>
                <p class="text-gray-500 dark:text-gray-400">Click the button above to register a new walk-in patient</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Walk-in Registration Modal -->
<div id="walkinModal" class="hidden fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
    <!-- Background overlay -->
    <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75" onclick="closeWalkinModal()"></div>

    <!-- Modal panel -->
    <div class="relative bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Register Walk-in Patient</h3>
                <button onclick="closeWalkinModal()" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            
            <form method="POST" class="space-y-6">
                <!-- Patient Selection -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Patient Information</h3>
                
                <div class="space-y-4">
                    <!-- Existing Patient -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Existing Patient</label>
                        <select name="patient_id" id="patient_id" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                            onchange="toggleNewPatientFields()">
                            <option value="">-- Or register new patient --</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>">
                                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['hospital_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- New Patient Fields -->
                    <div id="newPatientFields" class="hidden border-t border-gray-200 dark:border-gray-700 pt-4 mt-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">First Name</label>
                                <input type="text" name="first_name" id="first_name" 
                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Name</label>
                                <input type="text" name="last_name" id="last_name" 
                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            </div>

                            <div class="sm:col-span-2">
                                <label for="contact_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contact Number</label>
                                <input type="tel" name="contact_number" id="contact_number" 
                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                        <input type="hidden" name="new_patient" value="1">
                    </div>
                </div>
            </div>

                <!-- Appointment Details -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Appointment Details</h3>
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label for="doctor_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Doctor *</label>
                            <select name="doctor_id" id="doctor_id" required 
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['id']; ?>">
                                        Dr. <?php echo htmlspecialchars(trim(($doctor['first_name'] ?? '') . ' ' . ($doctor['last_name'] ?? '')) . (!empty($doctor['specialization']) ? ' - ' . $doctor['specialization'] : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="appointment_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Appointment Type *</label>
                            <select name="appointment_type" id="appointment_type" required 
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                <option value="consultation">Consultation</option>
                                <option value="emergency">Emergency</option>
                                <option value="checkup">Check-up</option>
                            </select>
                        </div>

                        <div>
                            <label for="priority_level" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Priority Level *</label>
                            <select name="priority_level" id="priority_level" required 
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                                <option value="high">High</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Medical Information -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Medical Information</h3>
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label for="reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reason for Visit *</label>
                            <textarea name="reason" id="reason" rows="3" required 
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" 
                                placeholder="Brief description of the reason for visit..."></textarea>
                        </div>

                        <div>
                            <label for="symptoms" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Symptoms</label>
                            <textarea name="symptoms" id="symptoms" rows="3" 
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" 
                                placeholder="List any symptoms the patient is experiencing..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button type="button" onclick="closeWalkinModal()" 
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="submit" 
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        Register Walk-in
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openWalkinModal() {
    const modal = document.getElementById('walkinModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeWalkinModal() {
    const modal = document.getElementById('walkinModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function toggleNewPatientFields() {
    const patientSelect = document.getElementById('patient_id');
    const newPatientFields = document.getElementById('newPatientFields');
    
    if (patientSelect.value === '') {
        newPatientFields.classList.remove('hidden');
    } else {
        newPatientFields.classList.add('hidden');
    }
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeWalkinModal();
    }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleNewPatientFields();
});
</script>

<?php include '../../includes/footer.php'; ?>