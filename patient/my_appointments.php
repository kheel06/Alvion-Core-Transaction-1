<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

ensurePatientAccess();

$page_title = 'My Appointments';
$patientId = getCurrentPatientId($db);
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment']) && $patientId) {
    $appointmentId = (int) $_POST['appointment_id'];
    try {
        $cancelQuery = "UPDATE appointments
                        SET status = 'cancelled', updated_at = NOW()
                        WHERE id = :id
                          AND patient_id = :pid
                          AND appointment_date >= CURDATE()
                          AND status NOT IN ('completed', 'cancelled')";
        $stmt = $db->prepare($cancelQuery);
        $stmt->bindParam(':id', $appointmentId, PDO::PARAM_INT);
        $stmt->bindParam(':pid', $patientId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount()) {
            $success = 'Appointment cancelled successfully.';
        } else {
            $errors[] = 'Unable to cancel that appointment.';
        }
    } catch (PDOException $e) {
        $errors[] = 'Error cancelling appointment: ' . $e->getMessage();
    }
}

$upcomingAppointments = $pastAppointments = [];

if ($patientId) {
    try {
        $upcomingQuery = "SELECT a.*, u.first_name AS doctor_fname, u.last_name AS doctor_lname
                          FROM appointments a
                          LEFT JOIN users u ON a.doctor_id = u.id
                          WHERE a.patient_id = :pid
                            AND a.appointment_date >= CURDATE()
                          ORDER BY a.appointment_date ASC, a.appointment_time ASC";
        $stmt = $db->prepare($upcomingQuery);
        $stmt->bindParam(':pid', $patientId, PDO::PARAM_INT);
        $stmt->execute();
        $upcomingAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $historyQuery = "SELECT a.*, u.first_name AS doctor_fname, u.last_name AS doctor_lname
                         FROM appointments a
                         LEFT JOIN users u ON a.doctor_id = u.id
                         WHERE a.patient_id = :pid
                           AND a.appointment_date < CURDATE()
                         ORDER BY a.appointment_date DESC, a.appointment_time DESC
                         LIMIT 20";
        $stmt = $db->prepare($historyQuery);
        $stmt->bindParam(':pid', $patientId, PDO::PARAM_INT);
        $stmt->execute();
        $pastAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errors[] = 'Failed to load appointments: ' . $e->getMessage();
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">My Appointments</h1>
        <p class="text-gray-600 dark:text-gray-400">Review upcoming visits and your appointment history.</p>
    </div>
    <a href="book_appointment.php" class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-primary-600 hover:bg-primary-700">
        Book New Appointment
    </a>
</div>

<?php if (!empty($errors)): ?>
    <div class="mb-4 rounded-md bg-rose-50 border border-rose-200 p-4 text-sm text-rose-700">
        <?php foreach ($errors as $error): ?>
            <p><?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
    </div>
<?php elseif ($success): ?>
    <div class="mb-4 rounded-md bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if (!$patientId): ?>
    <p class="text-sm text-gray-500 dark:text-gray-400">We could not locate your patient record.</p>
<?php else: ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Upcoming -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Upcoming</h2>
            <?php if (!empty($upcomingAppointments)): ?>
                <div class="space-y-4 max-h-[32rem] overflow-y-auto pr-1">
                    <?php foreach ($upcomingAppointments as $appointment): ?>
                        <div class="p-4 border border-gray-100 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-700/40">
                            <div class="flex justify-between">
                                <div>
                                    <p class="font-semibold text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($appointment['appointment_type'] ?? 'Consultation'); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-300">
                                        <?php echo formatDateTimeDisplay($appointment['appointment_date'], $appointment['appointment_time']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-300">
                                        Dr. <?php echo htmlspecialchars(($appointment['doctor_fname'] ?? '') . ' ' . ($appointment['doctor_lname'] ?? '')); ?>
                                    </p>
                                </div>
                                <span class="inline-flex h-fit px-2 py-0.5 rounded-full text-xs font-medium <?php echo $appointment['status'] === 'confirmed' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-300 mt-2">
                                <?php echo htmlspecialchars($appointment['reason'] ?? 'No reason provided.'); ?>
                            </p>
                            <?php if (strtolower($appointment['status']) !== 'cancelled' && strtolower($appointment['status']) !== 'completed'): ?>
                                <form method="POST" class="mt-3">
                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                    <button type="submit" name="cancel_appointment"
                                            class="text-xs text-rose-600 hover:text-rose-700 font-medium"
                                            onclick="return confirm('Cancel this appointment?');">
                                        Cancel appointment
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500 dark:text-gray-400">No upcoming appointments.</p>
            <?php endif; ?>
        </div>

        <!-- History -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent History</h2>
            <?php if (!empty($pastAppointments)): ?>
                <div class="space-y-4 max-h-[32rem] overflow-y-auto pr-1">
                    <?php foreach ($pastAppointments as $appointment): ?>
                        <div class="p-4 border border-gray-100 dark:border-gray-700 rounded-lg">
                            <div class="flex justify-between">
                                <div>
                                    <p class="font-semibold text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($appointment['appointment_type'] ?? 'Consultation'); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-300">
                                        <?php echo formatDateTimeDisplay($appointment['appointment_date'], $appointment['appointment_time']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-300">
                                        Dr. <?php echo htmlspecialchars(($appointment['doctor_fname'] ?? '') . ' ' . ($appointment['doctor_lname'] ?? '')); ?>
                                    </p>
                                </div>
                                <span class="inline-flex h-fit px-2 py-0.5 rounded-full text-xs font-medium <?php echo $appointment['status'] === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700'; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                </span>
                            </div>
                            <?php if (!empty($appointment['symptoms'])): ?>
                                <p class="text-xs text-gray-500 dark:text-gray-300 mt-2"><?php echo nl2br(htmlspecialchars($appointment['symptoms'])); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500 dark:text-gray-400">No past appointments recorded.</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

