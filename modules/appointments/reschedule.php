<?php
require_once '../../config/config.php';
requireAuth();
checkRole(['admin', 'receptionist', 'appointment_coordinator', 'doctor']);

$page_title = "Manage Appointments";
$week_days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

try {
    $rooms = getClinicRooms($db);
} catch (PDOException $exception) {
    $rooms = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');
    $appointment_id = (int) sanitizeInput($_POST['appointment_id'] ?? 0);

    if ($appointment_id <= 0) {
        $_SESSION['error'] = "Invalid appointment selected.";
        header("Location: reschedule.php");
        exit();
    }

    try {
        if ($action === 'reschedule') {
            $new_date = sanitizeInput($_POST['new_date']);
            $new_time_input = sanitizeInput($_POST['new_time']);
            $new_time = normalizeTime($new_time_input);
            $room_id = !empty($_POST['room_id']) ? (int) sanitizeInput($_POST['room_id']) : null;
            $notification_channel = sanitizeInput($_POST['notification_channel'] ?? 'email');
            $notification_recipient = sanitizeInput($_POST['notification_recipient'] ?? '');
            $notification_message = sanitizeInput($_POST['notification_message'] ?? '');
            $should_notify = $notification_channel !== 'none' && !empty($notification_recipient);

            $appointment_stmt = $db->prepare("SELECT doctor_id FROM appointments WHERE id = :id");
            $appointment_stmt->bindParam(':id', $appointment_id, PDO::PARAM_INT);
            $appointment_stmt->execute();
            $appointment = $appointment_stmt->fetch();

            if (!$appointment) {
                throw new Exception("Appointment not found.");
            }

            [$is_valid, $schedule_meta] = validateAppointmentSlot($db, (int) $appointment['doctor_id'], $room_id ?? 0, $new_date, $new_time, $appointment_id);
            if (!$is_valid) {
                throw new Exception($schedule_meta);
            }

            if (!$room_id && is_array($schedule_meta) && !empty($schedule_meta['room_id'])) {
                $room_id = (int) $schedule_meta['room_id'];
            }

            $update = $db->prepare("UPDATE appointments 
                                    SET appointment_date = :date, appointment_time = :time, room_id = :room_id,
                                        notification_status = :notification_status, notification_channel = :notification_channel,
                                        updated_by = :updated_by, updated_at = NOW(), status = 'scheduled'
                                    WHERE id = :id");
            $update->bindParam(':date', $new_date);
            $update->bindParam(':time', $new_time);
            $update->bindValue(':room_id', $room_id, $room_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $update->bindValue(':notification_status', $should_notify ? 'pending' : 'skipped');
            $update->bindParam(':notification_channel', $notification_channel);
            $update->bindParam(':updated_by', $_SESSION['user_id'], PDO::PARAM_INT);
            $update->bindParam(':id', $appointment_id, PDO::PARAM_INT);
            $update->execute();

            if ($should_notify) {
                $message = !empty($notification_message)
                    ? $notification_message
                    : "Your appointment has been moved to " . formatDate($new_date) . " at " . date('g:i A', strtotime($new_time)) . ". Please confirm your availability.";
                recordAppointmentNotification($db, $appointment_id, $notification_channel, $notification_recipient, $message);
            }

            $_SESSION['success'] = "Appointment rescheduled successfully.";
        } elseif ($action === 'cancel') {
            $reason = sanitizeInput($_POST['cancellation_reason'] ?? '');
            $update = $db->prepare("UPDATE appointments 
                                    SET status = 'cancelled', cancelled_at = NOW(), cancellation_reason = :reason,
                                        updated_by = :updated_by, updated_at = NOW()
                                    WHERE id = :id");
            $update->bindParam(':reason', $reason);
            $update->bindParam(':updated_by', $_SESSION['user_id'], PDO::PARAM_INT);
            $update->bindParam(':id', $appointment_id, PDO::PARAM_INT);
            $update->execute();

            if (!empty($_POST['notify_cancel_channel']) && $_POST['notify_cancel_channel'] !== 'none') {
                $channel = sanitizeInput($_POST['notify_cancel_channel']);
                $recipient = sanitizeInput($_POST['notify_cancel_recipient']);
                if (!empty($recipient)) {
                    $message = !empty($_POST['notify_cancel_message'])
                        ? sanitizeInput($_POST['notify_cancel_message'])
                        : "Your appointment has been cancelled. Reason: {$reason}";
                    recordAppointmentNotification($db, $appointment_id, $channel, $recipient, $message);
                }
            }

            $_SESSION['success'] = "Appointment cancelled.";
        }
    } catch (Exception $exception) {
        $_SESSION['error'] = $exception->getMessage();
    }

    header("Location: reschedule.php");
    exit();
}

// Get all appointments that can be rescheduled or cancelled (today or future, not completed/cancelled)
// Resolve patient from patients table OR users with role patient; doctor from users
try {
    $appointments_query = "SELECT a.*,
                                  COALESCE(p.first_name, up.first_name, 'Unknown') AS patient_fname,
                                  COALESCE(p.last_name, up.last_name, 'Patient') AS patient_lname,
                                  COALESCE(p.hospital_id, up.patient_id, CONCAT('ID:', a.patient_id)) AS hospital_id,
                                  u.first_name AS doctor_fname,
                                  u.last_name AS doctor_lname
                           FROM appointments a
                           LEFT JOIN patients p ON a.patient_id = p.id
                           LEFT JOIN users up ON a.patient_id = up.id AND up.role_id IN (SELECT id FROM roles WHERE role_name = 'patient')
                           LEFT JOIN users u ON a.doctor_id = u.id
                           WHERE a.status IN ('scheduled', 'confirmed', 'in_progress')
                           AND a.appointment_date >= CURDATE() - INTERVAL 1 DAY
                           ORDER BY a.appointment_date ASC, a.appointment_time ASC";
    $appointments_stmt = $db->prepare($appointments_query);
    $appointments_stmt->execute();
    $appointments = $appointments_stmt->fetchAll();
} catch (PDOException $exception) {
    $appointments = [];
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Reschedule & Cancel</h1>
    <p class="text-gray-600 dark:text-gray-400 mt-1">Manage cancellations. Assign new appointment times.</p>
</div>

<!-- Appointments Table -->
<div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
    <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Manage Appointments</h3>
        <?php if (count($appointments) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Appointment #</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Patient</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Doctor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date & Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Room</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($appointments as $appointment): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($appointment['appointment_number']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars(($appointment['patient_fname'] ?? '') . ' ' . ($appointment['patient_lname'] ?? '')); ?>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($appointment['hospital_id'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-white">
                                        Dr. <?php echo htmlspecialchars(($appointment['doctor_fname'] ?? '') . ' ' . ($appointment['doctor_lname'] ?? 'N/A')); ?>
                                    </div>
                                    <?php if (!empty($appointment['specialization'])): ?>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo htmlspecialchars($appointment['specialization']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?><br>
                                    <span class="text-gray-500 dark:text-gray-400"><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></span><br>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 capitalize"><?php echo str_replace('_', ' ', $appointment['appointment_type']); ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    <?php echo $appointment['room_name'] ? htmlspecialchars($appointment['room_department'] . ' • ' . $appointment['room_name']) : 'Unassigned'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php 
                                        $status = $appointment['status'] ?? 'scheduled';
                                        echo $status === 'completed' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                            ($status === 'cancelled' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 
                                            ($status === 'in_progress' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                                            ($status === 'confirmed' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 
                                            'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200')));
                                        ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <div class="flex space-x-2">
                                        <button onclick="openRescheduleModal(<?php echo htmlspecialchars(json_encode($appointment)); ?>)" 
                                            class="px-3 py-1 bg-primary-600 text-white text-xs font-semibold rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500">
                                            Reschedule
                                        </button>
                                        <button onclick="openCancelModal(<?php echo htmlspecialchars(json_encode($appointment)); ?>)" 
                                            class="px-3 py-1 bg-red-600 text-white text-xs font-semibold rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                                            Cancel
                                        </button>
                                    </div>
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
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No appointments to manage</h3>
                <p class="text-gray-500 dark:text-gray-400">There are no upcoming appointments that can be rescheduled or cancelled.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reschedule Modal -->
<div id="rescheduleModal" class="hidden fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
    <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75" onclick="closeRescheduleModal()"></div>
    <div class="relative bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Reschedule Appointment</h3>
                <button onclick="closeRescheduleModal()" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="reschedule">
                <input type="hidden" name="appointment_id" id="reschedule_appointment_id">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">New Date *</label>
                        <input type="date" name="new_date" id="reschedule_new_date" min="<?php echo date('Y-m-d'); ?>" required
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">New Time *</label>
                        <input type="time" name="new_time" id="reschedule_new_time" required
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Room Assignment</label>
                    <select name="room_id" id="reschedule_room_id"
                        class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        <option value="">Keep current</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?php echo $room['id']; ?>">
                                <?php echo htmlspecialchars($room['department'] . ' • ' . $room['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Notification Preferences</h4>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notify via</label>
                            <select name="notification_channel" id="reschedule_notification_channel"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                <option value="email">Email</option>
                                <option value="sms">SMS</option>
                                <option value="none">Do not notify</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Recipient</label>
                            <input type="text" name="notification_recipient" id="reschedule_notification_recipient"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                                placeholder="Email or mobile">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Message</label>
                        <textarea name="notification_message" id="reschedule_notification_message" rows="3"
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                            placeholder="Optional message to patient"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button type="button" onclick="closeRescheduleModal()" 
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        Confirm Reschedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cancel Modal -->
<div id="cancelModal" class="hidden fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
    <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75" onclick="closeCancelModal()"></div>
    <div class="relative bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Cancel Appointment</h3>
                <button onclick="closeCancelModal()" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <form method="POST" class="space-y-4" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="appointment_id" id="cancel_appointment_id">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cancellation Reason *</label>
                    <textarea name="cancellation_reason" id="cancel_reason" rows="3" required
                        class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-red-500 focus:border-red-500 dark:bg-gray-700 dark:text-white"
                        placeholder="Provide reason for cancellation"></textarea>
                </div>
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Notification Preferences</h4>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notify via</label>
                            <select name="notify_cancel_channel" id="cancel_notification_channel"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-red-500 focus:border-red-500 dark:bg-gray-700 dark:text-white">
                                <option value="email">Email</option>
                                <option value="sms">SMS</option>
                                <option value="none" selected>Do not notify</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Recipient</label>
                            <input type="text" name="notify_cancel_recipient" id="cancel_notification_recipient"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-red-500 focus:border-red-500 dark:bg-gray-700 dark:text-white"
                                placeholder="Email or mobile">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Message</label>
                        <textarea name="notify_cancel_message" id="cancel_notification_message" rows="3"
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-red-500 focus:border-red-500 dark:bg-gray-700 dark:text-white"
                            placeholder="Optional cancellation notice"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button type="button" onclick="closeCancelModal()" 
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        Confirm Cancellation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openRescheduleModal(appointment) {
    document.getElementById('reschedule_appointment_id').value = appointment.id;
    document.getElementById('reschedule_new_date').value = appointment.appointment_date;
    document.getElementById('reschedule_new_time').value = appointment.appointment_time;
    if (appointment.room_id) {
        document.getElementById('reschedule_room_id').value = appointment.room_id;
    }
    document.getElementById('rescheduleModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeRescheduleModal() {
    document.getElementById('rescheduleModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function openCancelModal(appointment) {
    document.getElementById('cancel_appointment_id').value = appointment.id;
    document.getElementById('cancelModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRescheduleModal();
        closeCancelModal();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>

