<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

ensurePatientAccess();

$page_title = 'Book Appointment';
$patient = getCurrentPatientRecord($db);
$patientId = $patient['id'] ?? null;
$doctors = [];
$errors = [];
$success = null;

try {
    $docQuery = "SELECT id, first_name, last_name, specialty
                 FROM doctors
                 WHERE status = 1
                 ORDER BY specialty, last_name";
    $docStmt = $db->prepare($docQuery);
    $docStmt->execute();
    $doctors = $docStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $doctors = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id = (int) ($_POST['doctor_id'] ?? 0);
    $appointment_date = sanitizeInput($_POST['appointment_date'] ?? '');
    $appointment_time = sanitizeInput($_POST['appointment_time'] ?? '');
    $visit_type = sanitizeInput($_POST['visit_type'] ?? 'in_person_consultation');
    $walk_in_intent = !empty($_POST['walk_in_intent']) ? 'yes' : 'no';
    $reason = trim($_POST['reason'] ?? '');

    if ($doctor_id <= 0) {
        $errors[] = 'Please choose a doctor.';
    }
    if (empty($appointment_date)) {
        $errors[] = 'Please select a preferred date.';
    }
    if (empty($appointment_time)) {
        $errors[] = 'Please select a time.';
    }
    if (empty($reason)) {
        $errors[] = 'Please describe your reason for visit.';
    }

    if (empty($patient)) {
        $errors[] = 'We could not locate your patient record.';
    }

    if (empty($errors)) {
        try {
            $insert = "INSERT INTO appointments_public (
                        doctor_id,
                        full_name,
                        contact_number,
                        email,
                        date_of_birth,
                        visit_type,
                        appointment_date,
                        appointment_time,
                        appointment_slot,
                        reason,
                        booking_channel,
                        walkin_intent,
                        status,
                        created_at,
                        updated_at
                    ) VALUES (
                        :doctor_id,
                        :full_name,
                        :contact,
                        :email,
                        :dob,
                        :visit_type,
                        :appointment_date,
                        :appointment_time,
                        :appointment_slot,
                        :reason,
                        'patient_portal',
                        :walk_in,
                        'pending',
                        NOW(),
                        NOW()
                    )";
            $stmt = $db->prepare($insert);
            $fullName = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));
            $stmt->execute([
                ':doctor_id' => $doctor_id,
                ':full_name' => $fullName,
                ':contact' => $patient['contact_number'] ?? '',
                ':email' => $patient['email'] ?? ($_SESSION['email'] ?? ''),
                ':dob' => $patient['birth_date'] ?? null,
                ':visit_type' => $visit_type,
                ':appointment_date' => $appointment_date,
                ':appointment_time' => $appointment_time,
                ':appointment_slot' => $appointment_time,
                ':reason' => $reason,
                ':walk_in' => $walk_in_intent
            ]);

            $success = 'Your appointment request has been submitted. Our coordinator will confirm via email or SMS.';
        } catch (PDOException $e) {
            $errors[] = 'Unable to book appointment: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Book an Appointment</h1>
    <p class="text-gray-600 dark:text-gray-400">Choose your preferred doctor, visit type, and schedule.</p>
</div>

<?php if (!empty($errors)): ?>
    <div class="mb-4 rounded-md bg-rose-50 border border-rose-200 p-4 text-sm text-rose-700">
        <ul class="list-disc list-inside space-y-1">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php elseif ($success): ?>
    <div class="mb-4 rounded-md bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
    <form method="POST" class="space-y-5">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Doctor *</label>
            <select name="doctor_id" required
                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                <option value="">Select doctor</option>
                <?php foreach ($doctors as $doc): ?>
                    <option value="<?php echo $doc['id']; ?>" <?php echo (isset($doctor_id) && $doctor_id == $doc['id']) ? 'selected' : ''; ?>>
                        Dr. <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name'] . ' – ' . $doc['specialty']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Preferred Date *</label>
                <input type="date" name="appointment_date" min="<?php echo date('Y-m-d'); ?>" required
                       value="<?php echo htmlspecialchars($appointment_date ?? ''); ?>"
                       class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-primary-500 focus:border-primary-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Preferred Time *</label>
                <input type="time" name="appointment_time" required
                       value="<?php echo htmlspecialchars($appointment_time ?? ''); ?>"
                       class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-primary-500 focus:border-primary-500">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Visit Type *</label>
                <select name="visit_type" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                    <option value="in_person_consultation" <?php echo (($visit_type ?? '') === 'in_person_consultation') ? 'selected' : ''; ?>>In-Person Consultation</option>
                    <option value="teleconsultation_video" <?php echo (($visit_type ?? '') === 'teleconsultation_video') ? 'selected' : ''; ?>>Teleconsult (Video)</option>
                    <option value="walkin_express" <?php echo (($visit_type ?? '') === 'walkin_express') ? 'selected' : ''; ?>>Walk-In Express</option>
                </select>
            </div>
            <div class="flex items-center mt-6">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="walk_in_intent" class="text-primary-600 focus:ring-primary-500" <?php echo !empty($walk_in_intent) && $walk_in_intent === 'yes' ? 'checked' : ''; ?>>
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">I intend to walk in on the selected day.</span>
                </label>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reason *</label>
            <textarea name="reason" rows="4" required
                      class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                      placeholder="Briefly describe your symptoms or concern..."><?php echo htmlspecialchars($reason ?? ''); ?></textarea>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-5 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 transition">
                Submit Request
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

