<?php
/**
 * Follow-up Scheduling
 * Part of TOCS - Telehealth and Outpatient Care System
 * Philippines Hospital Context
 */
require_once '../../config/config.php';
requireAuth();
checkRole(['admin', 'doctor']);

$page_title = "Follow-up Scheduling";

// Handle follow-up creation
if ($_POST) {
    try {
        $original_consultation_id = !empty($_POST['original_consultation_id']) ? (int)sanitizeInput($_POST['original_consultation_id']) : null;
        $original_appointment_id = !empty($_POST['original_appointment_id']) ? (int)sanitizeInput($_POST['original_appointment_id']) : null;
        $patient_id = (int)sanitizeInput($_POST['patient_id']);
        $doctor_id = $_SESSION['user_id'];
        $followup_date = sanitizeInput($_POST['followup_date']);
        $followup_time = sanitizeInput($_POST['followup_time']);
        $reason = sanitizeInput($_POST['reason'] ?? '');
        
        // Validate date is in the future
        if (strtotime($followup_date) < strtotime(date('Y-m-d'))) {
            throw new Exception("Follow-up date must be in the future.");
        }
        
        $query = "INSERT INTO followup_appointments (
            original_consultation_id, original_appointment_id, patient_id, doctor_id,
            followup_date, followup_time, reason, status
        ) VALUES (
            :original_consultation_id, :original_appointment_id, :patient_id, :doctor_id,
            :followup_date, :followup_time, :reason, 'scheduled'
        )";
        
        $stmt = $db->prepare($query);
        $stmt->bindValue(':original_consultation_id', $original_consultation_id, $original_consultation_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':original_appointment_id', $original_appointment_id, $original_appointment_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
        $stmt->bindParam(':followup_date', $followup_date);
        $stmt->bindParam(':followup_time', $followup_time);
        $stmt->bindParam(':reason', $reason);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Follow-up appointment scheduled successfully!";
            header("Location: followup.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error scheduling follow-up: " . $e->getMessage();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Get patients
try {
    $patients_query = "SELECT id, hospital_id, first_name, last_name FROM patients WHERE status = 'active' ORDER BY first_name, last_name";
    $patients_stmt = $db->prepare($patients_query);
    $patients_stmt->execute();
    $patients = $patients_stmt->fetchAll();
} catch (PDOException $e) {
    $patients = [];
}

// Get recent consultations for this doctor
try {
    $consultations_query = "SELECT tc.*, p.first_name, p.last_name, p.hospital_id
                           FROM teleconsultations tc
                           INNER JOIN patients p ON tc.patient_id = p.id
                           WHERE tc.doctor_id = :doctor_id AND tc.status = 'completed'
                           ORDER BY tc.ended_at DESC
                           LIMIT 20";
    $consultations_stmt = $db->prepare($consultations_query);
    $consultations_stmt->bindParam(':doctor_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $consultations_stmt->execute();
    $recent_consultations = $consultations_stmt->fetchAll();
} catch (PDOException $e) {
    $recent_consultations = [];
}

// Get recent appointments for this doctor
try {
    $appointments_query = "SELECT a.*, p.first_name, p.last_name, p.hospital_id
                          FROM appointments a
                          INNER JOIN patients p ON a.patient_id = p.id
                          WHERE a.doctor_id = :doctor_id AND a.status = 'completed'
                          ORDER BY a.appointment_date DESC
                          LIMIT 20";
    $appointments_stmt = $db->prepare($appointments_query);
    $appointments_stmt->bindParam(':doctor_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $appointments_stmt->execute();
    $recent_appointments = $appointments_stmt->fetchAll();
} catch (PDOException $e) {
    $recent_appointments = [];
}

// Get scheduled follow-ups
try {
    $followups_query = "SELECT fa.*, p.first_name, p.last_name, p.hospital_id
                       FROM followup_appointments fa
                       INNER JOIN patients p ON fa.patient_id = p.id
                       WHERE fa.doctor_id = :doctor_id AND fa.status = 'scheduled'
                       ORDER BY fa.followup_date ASC, fa.followup_time ASC";
    $followups_stmt = $db->prepare($followups_query);
    $followups_stmt->bindParam(':doctor_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $followups_stmt->execute();
    $scheduled_followups = $followups_stmt->fetchAll();
} catch (PDOException $e) {
    $scheduled_followups = [];
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Follow-up Scheduling</h1>
    <p class="text-gray-600">Schedule follow-up appointments for patients after consultations</p>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <!-- Follow-up Form -->
    <div class="lg:col-span-2">
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Schedule Follow-up</h3>
                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="patient_id" class="block text-sm font-medium text-gray-700">Patient *</label>
                            <select name="patient_id" id="patient_id" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                <option value="">Select Patient</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo $patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['hospital_id'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="followup_date" class="block text-sm font-medium text-gray-700">Follow-up Date *</label>
                            <input type="date" name="followup_date" id="followup_date" required
                                min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        </div>

                        <div>
                            <label for="followup_time" class="block text-sm font-medium text-gray-700">Follow-up Time *</label>
                            <input type="time" name="followup_time" id="followup_time" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        </div>

                        <div class="sm:col-span-2">
                            <label for="reason" class="block text-sm font-medium text-gray-700">Reason for Follow-up</label>
                            <textarea name="reason" id="reason" rows="3"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                                placeholder="Reason for scheduling this follow-up appointment..."></textarea>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Link to Previous Visit (Optional)</label>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="original_consultation_id" class="block text-xs font-medium text-gray-600">From Teleconsultation</label>
                                    <select name="original_consultation_id" id="original_consultation_id"
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                        <option value="">None</option>
                                        <?php foreach ($recent_consultations as $consult): ?>
                                            <option value="<?php echo $consult['id']; ?>">
                                                <?php echo $consult['first_name'] . ' ' . $consult['last_name']; ?> - 
                                                <?php echo formatDate($consult['consultation_date']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label for="original_appointment_id" class="block text-xs font-medium text-gray-600">From Appointment</label>
                                    <select name="original_appointment_id" id="original_appointment_id"
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                        <option value="">None</option>
                                        <?php foreach ($recent_appointments as $appt): ?>
                                            <option value="<?php echo $appt['id']; ?>">
                                                <?php echo $appt['first_name'] . ' ' . $appt['last_name']; ?> - 
                                                <?php echo formatDate($appt['appointment_date']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700">
                            Schedule Follow-up
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scheduled Follow-ups -->
    <div class="lg:col-span-1">
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Scheduled Follow-ups</h3>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <?php if (count($scheduled_followups) > 0): ?>
                    <div class="space-y-3">
                        <?php foreach ($scheduled_followups as $followup): ?>
                            <div class="p-3 bg-blue-50 rounded-lg border border-blue-200">
                                <p class="text-sm font-medium text-gray-900">
                                    <?php echo $followup['first_name'] . ' ' . $followup['last_name']; ?>
                                </p>
                                <p class="text-xs text-gray-500"><?php echo $followup['hospital_id']; ?></p>
                                <p class="text-xs text-gray-600 mt-1">
                                    <?php echo formatDate($followup['followup_date']); ?> at <?php echo formatTime($followup['followup_time']); ?>
                                </p>
                                <?php if ($followup['reason']): ?>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo substr($followup['reason'], 0, 50); ?>...</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="m9 16 2 2 4-4"></path></svg>
                        <p class="text-gray-500">No scheduled follow-ups</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>



