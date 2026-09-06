<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

ensurePatientAccess();

$page_title = 'Medical History';
$patient = getCurrentPatientRecord($db);
$patientId = $patient['id'] ?? null;

$diagnoses = $medications = $procedures = $immunizations = [];

if ($patientId) {
    try {
        $diagQuery = "SELECT ed.*, u.first_name AS doctor_fname, u.last_name AS doctor_lname
                      FROM ehr_diagnoses ed
                      LEFT JOIN users u ON ed.diagnosed_by = u.id
                      WHERE ed.patient_id = :pid
                      ORDER BY ed.diagnosis_date DESC, ed.created_at DESC
                      LIMIT 20";
        $stmt = $db->prepare($diagQuery);
        $stmt->bindParam(':pid', $patientId, PDO::PARAM_INT);
        $stmt->execute();
        $diagnoses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $diagnoses = [];
    }

    try {
        $medQuery = "SELECT em.*, u.first_name AS provider_fname, u.last_name AS provider_lname
                     FROM ehr_medications em
                     LEFT JOIN users u ON em.prescribed_by = u.id
                     WHERE em.patient_id = :pid
                     ORDER BY em.start_date DESC, em.created_at DESC
                     LIMIT 20";
        $stmt = $db->prepare($medQuery);
        $stmt->bindParam(':pid', $patientId, PDO::PARAM_INT);
        $stmt->execute();
        $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $medications = [];
    }

    try {
        $procQuery = "SELECT ep.*, u.first_name AS clinician_fname, u.last_name AS clinician_lname
                      FROM ehr_procedures ep
                      LEFT JOIN users u ON ep.performed_by = u.id
                      WHERE ep.patient_id = :pid
                      ORDER BY ep.procedure_date DESC
                      LIMIT 15";
        $stmt = $db->prepare($procQuery);
        $stmt->bindParam(':pid', $patientId, PDO::PARAM_INT);
        $stmt->execute();
        $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $procedures = [];
    }

    try {
        $immQuery = "SELECT ei.*
                     FROM ehr_immunizations ei
                     WHERE ei.patient_id = :pid
                     ORDER BY ei.immunization_date DESC
                     LIMIT 15";
        $stmt = $db->prepare($immQuery);
        $stmt->bindParam(':pid', $patientId, PDO::PARAM_INT);
        $stmt->execute();
        $immunizations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $immunizations = [];
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Electronic Health Record Summary</h1>
    <p class="text-gray-600 dark:text-gray-400">Read-only view of your diagnoses, medications, and procedures.</p>
</div>

<?php if (!$patient): ?>
    <div class="rounded-md bg-rose-50 border border-rose-200 p-4 text-sm text-rose-700">
        We could not locate your patient chart. Please contact the admissions desk for assistance.
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Diagnoses -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Diagnoses</h2>
            <?php if (!empty($diagnoses)): ?>
                <div class="space-y-4 max-h-[28rem] overflow-y-auto pr-1">
                    <?php foreach ($diagnoses as $diag): ?>
                        <div class="p-3 border border-gray-100 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-700/40">
                            <p class="font-semibold text-sm text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($diag['diagnosis'] ?? 'Diagnosis'); ?>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-300">
                                <?php echo formatDate($diag['diagnosis_date'] ?? $diag['created_at']); ?> &bull;
                                Dr. <?php echo htmlspecialchars(($diag['doctor_fname'] ?? '') . ' ' . ($diag['doctor_lname'] ?? '')); ?>
                            </p>
                            <?php if (!empty($diag['notes'])): ?>
                                <p class="text-xs text-gray-600 dark:text-gray-300 mt-1"><?php echo nl2br(htmlspecialchars($diag['notes'])); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500 dark:text-gray-400">No diagnoses recorded.</p>
            <?php endif; ?>
        </div>

        <!-- Medications -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Active Medications</h2>
            <?php if (!empty($medications)): ?>
                <div class="space-y-4 max-h-[28rem] overflow-y-auto pr-1">
                    <?php foreach ($medications as $med): ?>
                        <div class="p-3 border border-gray-100 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-700/40">
                            <p class="font-semibold text-sm text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($med['medication_name'] ?? 'Medication'); ?>
                                <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">
                                    <?php echo htmlspecialchars($med['dosage'] ?? ''); ?>
                                </span>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-300">
                                <?php echo formatDate($med['start_date'] ?? $med['created_at']); ?>
                                <?php if (!empty($med['end_date'])): ?>
                                    – <?php echo formatDate($med['end_date']); ?>
                                <?php endif; ?>
                                &bull; Prescribed by <?php echo htmlspecialchars(($med['provider_fname'] ?? '') . ' ' . ($med['provider_lname'] ?? '')); ?>
                            </p>
                            <?php if (!empty($med['instructions'])): ?>
                                <p class="text-xs text-gray-600 dark:text-gray-300 mt-1"><?php echo nl2br(htmlspecialchars($med['instructions'])); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500 dark:text-gray-400">No medications on file.</p>
            <?php endif; ?>
        </div>

        <!-- Procedures -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Procedures</h2>
            <?php if (!empty($procedures)): ?>
                <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
                    <?php foreach ($procedures as $proc): ?>
                        <div class="p-3 border border-gray-100 dark:border-gray-700 rounded-lg">
                            <p class="font-semibold text-sm text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($proc['procedure_name'] ?? 'Procedure'); ?>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-300">
                                <?php echo formatDate($proc['procedure_date'] ?? $proc['created_at']); ?> &bull;
                                <?php echo htmlspecialchars($proc['location'] ?? 'Main Facility'); ?>
                            </p>
                            <?php if (!empty($proc['notes'])): ?>
                                <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                    <?php echo nl2br(htmlspecialchars($proc['notes'])); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500 dark:text-gray-400">No procedures recorded.</p>
            <?php endif; ?>
        </div>

        <!-- Immunizations -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Immunizations</h2>
            <?php if (!empty($immunizations)): ?>
                <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
                    <?php foreach ($immunizations as $imm): ?>
                        <div class="flex items-center justify-between border border-gray-100 dark:border-gray-700 rounded-lg p-3">
                            <div>
                                <p class="font-semibold text-sm text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($imm['vaccine_name'] ?? 'Vaccine'); ?>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-300">
                                    <?php echo formatDate($imm['immunization_date'] ?? $imm['created_at']); ?>
                                </p>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-300">
                                <?php echo htmlspecialchars($imm['dose_number'] ?? 'Dose'); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500 dark:text-gray-400">No immunization records yet.</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

