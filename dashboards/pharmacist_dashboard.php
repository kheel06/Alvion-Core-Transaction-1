<?php
/**
 * Pharmacist Dashboard
 * Prescription processing overview
 */
require_once '../config/config.php';
requireAuth();
checkRole(['pharmacist']);

$page_title = "Pharmacist Dashboard";

try {
    // Pharmacist statistics
    $stats_query = "SELECT 
        (SELECT COUNT(*) FROM e_prescriptions WHERE status = 'pending') as pending_prescriptions,
        (SELECT COUNT(*) FROM e_prescriptions WHERE status = 'filled' AND DATE(filled_at) = CURDATE()) as filled_today,
        (SELECT COUNT(*) FROM e_prescriptions WHERE status = 'partially_filled') as partially_filled,
        (SELECT COUNT(*) FROM e_prescriptions WHERE DATE(created_at) = CURDATE()) as prescriptions_today";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch();

    // Pending prescriptions
    $pending_prescriptions = $db->query("SELECT ep.*, p.first_name, p.last_name, p.hospital_id, u.first_name as doctor_fname, u.last_name as doctor_lname
        FROM e_prescriptions ep
        INNER JOIN patients p ON ep.patient_id = p.id
        INNER JOIN users u ON ep.doctor_id = u.id
        WHERE ep.status = 'pending'
        ORDER BY ep.prescription_date ASC, ep.created_at ASC
        LIMIT 10")->fetchAll();

} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching dashboard data: " . $e->getMessage();
    $stats = [];
    $pending_prescriptions = [];
}

include '../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Pharmacist Dashboard</h1>
    <p class="text-gray-600 dark:text-gray-400">Welcome, <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?> - Prescription processing overview</p>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Pending</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['pending_prescriptions'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Filled Today</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['filled_today'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M5 22h14"></path><path d="M5 2h14"></path><path d="M17 22v-4.172a2 2 0 0 0-.586-1.414l-4.828-4.828A2 2 0 0 1 11 11.172V6"></path><path d="M7 2v4.172a2 2 0 0 0 .586 1.414l4.828 4.828A2 2 0 0 1 13 12.828V22"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Partially Filled</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['partially_filled'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Today's Total</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['prescriptions_today'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pending Prescriptions -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Pending Prescriptions</h3>
    </div>
    <div class="px-4 py-5 sm:p-6">
        <?php if (count($pending_prescriptions) > 0): ?>
            <div class="space-y-4">
                <?php foreach ($pending_prescriptions as $rx): ?>
                    <div class="p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    <?php echo $rx['first_name'] . ' ' . $rx['last_name']; ?>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo $rx['hospital_id']; ?></p>
                                <p class="text-xs text-gray-600 mt-1">Prescription #<?php echo $rx['prescription_number']; ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Dr. <?php echo $rx['doctor_fname'] . ' ' . $rx['doctor_lname']; ?></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo formatDate($rx['prescription_date']); ?></p>
                            </div>
                        </div>
                        <?php 
                        $medications = json_decode($rx['medications'], true);
                        if (is_array($medications) && count($medications) > 0): 
                        ?>
                            <div class="mt-3 p-3 bg-white rounded">
                                <p class="text-xs font-medium text-gray-700 mb-2">Medications:</p>
                                <ul class="space-y-1">
                                    <?php foreach ($medications as $med): ?>
                                        <li class="text-xs text-gray-600">
                                            • <?php echo $med['name']; ?> - <?php echo $med['dosage']; ?> <?php echo $med['frequency']; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <div class="mt-3">
                            <a href="../modules/telehealth/e_prescriptions.php" 
                               class="text-sm text-primary-600 hover:text-primary-700">
                                Process Prescription →
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-8">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
                <p class="text-gray-500">No pending prescriptions</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>



