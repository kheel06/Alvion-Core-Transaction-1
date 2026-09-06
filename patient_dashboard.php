<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/patient/helpers.php';

ensurePatientAccess();

$page_title = 'Patient Dashboard';
$patient = getCurrentPatientRecord($db);
$patientId = $patient['id'] ?? null;

$upcomingAppointments = [];
$recentPrescriptions = [];
$billingSummary = ['balance' => 0, 'last_statement' => null, 'coverage' => null];

if ($patientId) {
    try {
        $apptQuery = "SELECT a.*, u.first_name AS doctor_fname, u.last_name AS doctor_lname
                      FROM appointments a
                      LEFT JOIN users u ON a.doctor_id = u.id
                      WHERE a.patient_id = :pid
                        AND a.appointment_date >= CURDATE()
                      ORDER BY a.appointment_date ASC, a.appointment_time ASC
                      LIMIT 3";
        $apptStmt = $db->prepare($apptQuery);
        $apptStmt->bindParam(':pid', $patientId, PDO::PARAM_INT);
        $apptStmt->execute();
        $upcomingAppointments = $apptStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $upcomingAppointments = [];
    }

    try {
        $rxQuery = "SELECT ep.*, u.first_name AS doctor_fname, u.last_name AS doctor_lname
                    FROM e_prescriptions ep
                    LEFT JOIN users u ON ep.doctor_id = u.id
                    WHERE ep.patient_id = :pid
                    ORDER BY ep.prescription_date DESC, ep.created_at DESC
                    LIMIT 3";
        $rxStmt = $db->prepare($rxQuery);
        $rxStmt->bindParam(':pid', $patientId, PDO::PARAM_INT);
        $rxStmt->execute();
        $recentPrescriptions = $rxStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $recentPrescriptions = [];
    }

    try {
        $billingQuery = "SELECT 
                            COALESCE(SUM(balance_amount), 0) AS total_balance,
                            MAX(updated_at) AS last_statement
                         FROM billing
                         WHERE patient_id = :pid";
        $billingStmt = $db->prepare($billingQuery);
        $billingStmt->bindParam(':pid', $patientId, PDO::PARAM_INT);
        $billingStmt->execute();
        $billingSummary['balance'] = (float) $billingStmt->fetchColumn();

        $billingStmt->execute();
        $billingRow = $billingStmt->fetch(PDO::FETCH_ASSOC);
        if ($billingRow) {
            $billingSummary['balance'] = (float) $billingRow['total_balance'];
            $billingSummary['last_statement'] = $billingRow['last_statement'];
        }
    } catch (PDOException $e) {
        $billingSummary['balance'] = 0;
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Welcome back, <?php echo htmlspecialchars($patient['first_name'] ?? ($_SESSION['first_name'] ?? 'Patient')); ?>!</h1>
        <p class="text-gray-600 dark:text-gray-400">Manage your care experience from one place.</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Upcoming Appointments -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Upcoming Appointments</h3>
            <a href="patient/my_appointments.php" class="text-sm text-primary-600 dark:text-primary-400 hover:underline">View all</a>
        </div>
        <?php if (!empty($upcomingAppointments)): ?>
            <div class="space-y-4 max-h-72 overflow-y-auto pr-1">
                <?php foreach ($upcomingAppointments as $appointment): ?>
                    <div class="p-3 rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/40">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            <?php echo htmlspecialchars($appointment['appointment_type'] ?? 'Consultation'); ?>
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-300">
                            <?php echo formatDateTimeDisplay($appointment['appointment_date'], $appointment['appointment_time']); ?>
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-300">
                            With Dr. <?php echo htmlspecialchars(($appointment['doctor_fname'] ?? '') . ' ' . ($appointment['doctor_lname'] ?? '')); ?>
                        </p>
                        <span class="inline-flex mt-2 px-2 py-0.5 rounded-full text-xs font-medium <?php echo ($appointment['status'] ?? 'scheduled') === 'confirmed' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $appointment['status'] ?? 'scheduled')); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-sm text-gray-500 dark:text-gray-400">No upcoming appointments yet.</p>
        <?php endif; ?>
    </div>

    <!-- Recent Prescriptions -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Prescriptions</h3>
            <a href="patient/e_prescriptions.php" class="text-sm text-primary-600 dark:text-primary-400 hover:underline">View all</a>
        </div>
        <?php if (!empty($recentPrescriptions)): ?>
            <div class="space-y-4">
                <?php foreach ($recentPrescriptions as $rx): ?>
                    <div class="p-3 rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/40">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">RX #<?php echo htmlspecialchars($rx['prescription_number']); ?></p>
                        <p class="text-xs text-gray-500 dark:text-gray-300">
                            <?php echo formatDate($rx['prescription_date'] ?? $rx['created_at']); ?> &bull; Dr. <?php echo htmlspecialchars(($rx['doctor_fname'] ?? '') . ' ' . ($rx['doctor_lname'] ?? '')); ?>
                        </p>
                        <span class="inline-flex mt-2 px-2 py-0.5 rounded-full text-xs font-medium <?php echo $rx['status'] === 'filled' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'; ?>">
                            <?php echo ucfirst($rx['status']); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-sm text-gray-500 dark:text-gray-400">No prescriptions recorded yet.</p>
        <?php endif; ?>
    </div>

    <!-- Billing Summary -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Billing Summary</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">Current outstanding balance</p>
        <p class="text-3xl font-bold text-primary-600 dark:text-primary-400 mb-4">
            ₱<?php echo number_format($billingSummary['balance'], 2); ?>
        </p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
            <?php if ($billingSummary['last_statement']): ?>
                Last statement updated on <?php echo formatDate($billingSummary['last_statement']); ?>
            <?php else: ?>
                No billing statements yet.
            <?php endif; ?>
        </p>
        <a href="patient/view_soa.php" class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 transition">View Statements</a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Quick Actions -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Self-Service Actions</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <a href="patient/book_appointment.php" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-primary-500 hover:text-primary-600 transition">
                <p class="text-sm font-semibold">Book Appointment</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Choose doctor, date, telehealth preference</p>
            </a>
            <a href="patient/telehealth_sessions.php" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-primary-500 hover:text-primary-600 transition">
                <p class="text-sm font-semibold">Join Telehealth</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Review upcoming virtual consults</p>
            </a>
            <a href="patient/e_prescriptions.php" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-primary-500 hover:text-primary-600 transition">
                <p class="text-sm font-semibold">View E-Prescriptions</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Download or show QR at pharmacy</p>
            </a>
            <a href="patient/lab_results.php" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-primary-500 hover:text-primary-600 transition">
                <p class="text-sm font-semibold">Check Lab Results</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">View PDF reports and impressions</p>
            </a>
        </div>
    </div>

    <!-- Contact & Profile -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">My Information</h3>
        <?php if ($patient): ?>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="font-medium text-gray-700 dark:text-gray-300">Hospital ID</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['hospital_id']); ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-700 dark:text-gray-300">Contact</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['contact_number'] ?? 'Not set'); ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-700 dark:text-gray-300">Email</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['email'] ?? ($_SESSION['email'] ?? '')); ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-700 dark:text-gray-300">Emergency Contact</dt>
                    <dd class="text-gray-900 dark:text-white">
                        <?php
                        if (!empty($patient['emergency_contact_name'])) {
                            echo htmlspecialchars($patient['emergency_contact_name']) . ' • ' . htmlspecialchars($patient['emergency_contact_number'] ?? '');
                        } else {
                            echo 'Not set';
                        }
                        ?>
                    </dd>
                </div>
            </dl>
        <?php else: ?>
            <p class="text-sm text-gray-500 dark:text-gray-400">We could not locate your patient profile. Please contact support.</p>
        <?php endif; ?>
        <a href="patient/profile.php" class="inline-flex items-center mt-4 px-4 py-2 rounded-md text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">Update my details</a>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

