<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

ensurePatientAccess();

$page_title = 'Telehealth Sessions';
$patientId = getCurrentPatientId($db);
$sessions = [];

if ($patientId) {
    try {
        $query = "SELECT t.*, u.first_name AS doctor_fname, u.last_name AS doctor_lname
                  FROM teleconsultations t
                  LEFT JOIN users u ON t.doctor_id = u.id
                  WHERE t.patient_id = :pid
                  ORDER BY t.consultation_date DESC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':pid', $patientId, PDO::PARAM_INT);
        $stmt->execute();
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $sessions = [];
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Telehealth Sessions</h1>
    <p class="text-gray-600 dark:text-gray-400">Join video consults or review past telehealth visits.</p>
</div>

<?php if (!$patientId): ?>
    <p class="text-sm text-gray-500 dark:text-gray-400">We could not locate your patient record.</p>
<?php else: ?>
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Sessions</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (!empty($sessions)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Doctor</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Notes</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($sessions as $session): ?>
                                <tr>
                                    <td class="px-4 py-3 text-gray-900 dark:text-white">
                                        <?php echo formatDateTimeDisplay($session['consultation_date']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        Dr. <?php echo htmlspecialchars(($session['doctor_fname'] ?? '') . ' ' . ($session['doctor_lname'] ?? '')); ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?php echo $session['status'] === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($session['status'] === 'ongoing' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $session['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                        <?php echo !empty($session['consultation_notes']) ? htmlspecialchars($session['consultation_notes']) : '—'; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if ($session['status'] === 'scheduled' || $session['status'] === 'ongoing'): ?>
                                            <a href="<?php echo htmlspecialchars($session['video_link'] ?? '#'); ?>" target="_blank"
                                               class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium text-white bg-primary-600 hover:bg-primary-700 <?php echo empty($session['video_link']) ? 'opacity-40 pointer-events-none' : ''; ?>">
                                                Join Call
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400">No action</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500 dark:text-gray-400">No telehealth sessions found.</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

