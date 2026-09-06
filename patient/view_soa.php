<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

ensurePatientAccess();

$page_title = 'Statements of Account';
$patientId = getCurrentPatientId($db);
$statements = [];

if ($patientId) {
    try {
        $query = "SELECT b.*, p.first_name, p.last_name
                  FROM billing b
                  LEFT JOIN patients p ON b.patient_id = p.id
                  WHERE b.patient_id = :pid
                  ORDER BY b.created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':pid', $patientId, PDO::PARAM_INT);
        $stmt->execute();
        $statements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $statements = [];
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Billing & Statements</h1>
    <p class="text-gray-600 dark:text-gray-400">Monitor your SOA, charges, and insurance coverage.</p>
</div>

<?php if (!$patientId): ?>
    <p class="text-sm text-gray-500 dark:text-gray-400">We could not locate your patient record.</p>
<?php else: ?>
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Statements of Account</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (!empty($statements)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">SOA #</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Paid</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Balance</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($statements as $soa): ?>
                                <tr>
                                    <td class="px-4 py-3 text-gray-900 dark:text-white font-medium">
                                        <?php echo htmlspecialchars($soa['billing_number'] ?? ('SOA-' . $soa['id'])); ?>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                        <?php echo formatDate($soa['created_at']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-white">
                                        ₱<?php echo number_format($soa['total_amount'] ?? 0, 2); ?>
                                    </td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-white">
                                        ₱<?php echo number_format($soa['paid_amount'] ?? 0, 2); ?>
                                    </td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-white">
                                        ₱<?php echo number_format($soa['balance_amount'] ?? 0, 2); ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?php echo ($soa['balance_amount'] ?? 0) <= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'; ?>">
                                            <?php echo ($soa['balance_amount'] ?? 0) <= 0 ? 'Settled' : 'Pending'; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <button type="button"
                                                onclick="alert('Please contact billing to request a detailed PDF of SOA <?php echo htmlspecialchars($soa['billing_number'] ?? $soa['id']); ?>.');"
                                                class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium text-primary-600 bg-primary-50 hover:bg-primary-100">
                                            Request PDF
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500 dark:text-gray-400">No statements available.</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

