<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

ensurePatientAccess();

$page_title = 'E-Prescriptions';
$patientId = getCurrentPatientId($db);
$prescriptions = [];

if ($patientId) {
    try {
        $query = "SELECT ep.*, u.first_name AS doctor_fname, u.last_name AS doctor_lname
                  FROM e_prescriptions ep
                  LEFT JOIN users u ON ep.doctor_id = u.id
                  WHERE ep.patient_id = :pid
                  ORDER BY ep.prescription_date DESC, ep.created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':pid', $patientId, PDO::PARAM_INT);
        $stmt->execute();
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $prescriptions = [];
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Electronic Prescriptions</h1>
    <p class="text-gray-600 dark:text-gray-400">Download, print, or show the QR code at partner pharmacies.</p>
</div>

<?php if (!$patientId): ?>
    <p class="text-sm text-gray-500 dark:text-gray-400">We could not locate your patient record.</p>
<?php else: ?>
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">My Prescriptions</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (!empty($prescriptions)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Prescription #</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Doctor</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($prescriptions as $rx): ?>
                                <tr>
                                    <td class="px-4 py-3 text-gray-900 dark:text-white font-medium">
                                        <?php echo htmlspecialchars($rx['prescription_number']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        Dr. <?php echo htmlspecialchars(($rx['doctor_fname'] ?? '') . ' ' . ($rx['doctor_lname'] ?? '')); ?>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                        <?php echo formatDate($rx['prescription_date'] ?? $rx['created_at']); ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?php echo $rx['status'] === 'filled' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'; ?>">
                                            <?php echo ucfirst($rx['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 flex flex-wrap gap-2">
                                        <button type="button"
                                                class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium text-primary-600 bg-primary-50 hover:bg-primary-100"
                                                onclick="window.print()">
                                            Print
                                        </button>
                                        <button type="button"
                                                class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium text-gray-600 bg-gray-100 hover:bg-gray-200"
                                                onclick="alert('Show this QR code at pharmacy: <?php echo htmlspecialchars($rx['prescription_number']); ?>');">
                                            Show QR
                                        </button>
                                        <?php if (!empty($rx['medications'])): ?>
                                            <button type="button"
                                                    class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium text-teal-600 bg-teal-50 hover:bg-teal-100"
                                                    onclick="toggleDetails('<?php echo $rx['id']; ?>')">
                                                View Details
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if (!empty($rx['medications'])): ?>
                                    <tr id="details-<?php echo $rx['id']; ?>" class="hidden">
                                        <td colspan="5" class="px-4 py-3 bg-gray-50 dark:bg-gray-700">
                                            <p class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-2">Medications</p>
                                            <?php
                                            $medications = json_decode($rx['medications'], true) ?: [];
                                            if (!empty($medications)): ?>
                                                <ul class="text-xs text-gray-600 dark:text-gray-300 space-y-1">
                                                    <?php foreach ($medications as $med): ?>
                                                        <li>• <?php echo htmlspecialchars($med['name'] ?? 'Medication'); ?> — <?php echo htmlspecialchars($med['dosage'] ?? ''); ?> (<?php echo htmlspecialchars($med['frequency'] ?? ''); ?>)</li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <p class="text-xs text-gray-500">No medications available.</p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500 dark:text-gray-400">No prescriptions recorded.</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
function toggleDetails(id) {
    const row = document.getElementById('details-' + id);
    if (row) {
        row.classList.toggle('hidden');
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

