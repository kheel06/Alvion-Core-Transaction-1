<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

ensurePatientAccess();

$page_title = 'Lab Results';
$patientId = getCurrentPatientId($db);
$labResults = [];

if ($patientId) {
    try {
        $query = "SELECT lr.*, u.first_name AS ordered_by_fname, u.last_name AS ordered_by_lname
                  FROM ehr_lab_results lr
                  LEFT JOIN users u ON lr.ordered_by = u.id
                  WHERE lr.patient_id = :pid
                  ORDER BY lr.result_date DESC, lr.created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':pid', $patientId, PDO::PARAM_INT);
        $stmt->execute();
        $labResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $labResults = [];
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Laboratory Results</h1>
    <p class="text-gray-600 dark:text-gray-400">Download lab PDFs and review impressions from your care team.</p>
</div>

<?php if (!$patientId): ?>
    <p class="text-sm text-gray-500 dark:text-gray-400">We could not locate your patient record.</p>
<?php else: ?>
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Results</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (!empty($labResults)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Test</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Doctor</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Impression</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($labResults as $lab): ?>
                                <tr>
                                    <td class="px-4 py-3 text-gray-900 dark:text-white">
                                        <?php echo formatDate($lab['result_date'] ?? $lab['created_at']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        <?php echo htmlspecialchars($lab['test_name'] ?? 'Laboratory Test'); ?>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                        <?php echo htmlspecialchars(trim(($lab['ordered_by_fname'] ?? '') . ' ' . ($lab['ordered_by_lname'] ?? ''))); ?>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                        <?php echo !empty($lab['impression']) ? htmlspecialchars($lab['impression']) : '—'; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if (!empty($lab['result_file'])): ?>
                                            <a href="<?php echo BASE_URL . '/' . htmlspecialchars($lab['result_file']); ?>" target="_blank"
                                               class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium text-primary-600 bg-primary-50 hover:bg-primary-100">
                                                Download PDF
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400">Pending upload</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500 dark:text-gray-400">No lab results available.</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

