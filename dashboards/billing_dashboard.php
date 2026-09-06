<?php
/**
 * Billing Staff Dashboard
 * Insurance and payment overview
 */
require_once '../config/config.php';
requireAuth();
checkRole(['billing_staff']);

$page_title = "Billing Dashboard";

try {
    // Billing statistics
    $stats_query = "SELECT 
        (SELECT COUNT(*) FROM payments WHERE DATE(payment_date) = CURDATE()) as payments_today,
        (SELECT SUM(amount) FROM payments WHERE DATE(payment_date) = CURDATE() AND status = 'completed') as revenue_today,
        (SELECT COUNT(*) FROM patient_insurance WHERE is_active = 1) as active_insurance,
        (SELECT COUNT(*) FROM payments WHERE status = 'pending') as pending_payments,
        (SELECT COUNT(*) FROM payments WHERE DATE(payment_date) = CURDATE() AND status = 'failed') as failed_payments";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch();

    // Recent payments
    $recent_payments = $db->query("SELECT p.*, pt.first_name, pt.last_name, pt.hospital_id, pm.method_name
        FROM payments p
        INNER JOIN patients pt ON p.patient_id = pt.id
        INNER JOIN payment_methods pm ON p.payment_method_id = pm.id
        ORDER BY p.payment_date DESC
        LIMIT 10")->fetchAll();

    // Pending payments
    $pending_payments = $db->query("SELECT p.*, pt.first_name, pt.last_name, pt.hospital_id, pm.method_name
        FROM payments p
        INNER JOIN patients pt ON p.patient_id = pt.id
        INNER JOIN payment_methods pm ON p.payment_method_id = pm.id
        WHERE p.status = 'pending'
        ORDER BY p.payment_date ASC
        LIMIT 10")->fetchAll();

} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching dashboard data: " . $e->getMessage();
    $stats = [];
    $recent_payments = [];
    $pending_payments = [];
}

include '../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Billing Dashboard</h1>
    <p class="text-gray-600 dark:text-gray-400">Welcome, <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?> - Insurance and payment management</p>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v12"></path><path d="M15 9a3 3 0 1 0-6 0"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Revenue Today</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white">₱<?php echo number_format($stats['revenue_today'] ?? 0, 2); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="bg-gray-50 dark:bg-gray-700 px-5 py-3">
            <div class="text-sm">
                <span class="text-green-600 font-medium"><?php echo $stats['payments_today'] ?? 0; ?> payments</span>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Pending Payments</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['pending_payments'] ?? 0; ?></dd>
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Active Insurance</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['active_insurance'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Failed Payments</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['failed_payments'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payments -->
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Pending Payments</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($pending_payments) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($pending_payments as $payment): ?>
                        <div class="p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo $payment['first_name'] . ' ' . $payment['last_name']; ?>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo $payment['hospital_id']; ?></p>
                                    <p class="text-xs text-gray-600 mt-1">₱<?php echo number_format($payment['amount'], 2); ?></p>
                                </div>
                                <span class="text-xs text-gray-500"><?php echo formatDate($payment['payment_date']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
                    <p class="text-gray-500">No pending payments</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Recent Payments</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($recent_payments) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($recent_payments as $payment): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    <?php echo $payment['first_name'] . ' ' . $payment['last_name']; ?>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo $payment['hospital_id']; ?></p>
                                <p class="text-xs text-gray-600 mt-1">₱<?php echo number_format($payment['amount'], 2); ?> via <?php echo $payment['method_name']; ?></p>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                <?php echo $payment['status'] == 'completed' ? 'bg-green-100 text-green-800' : ''; ?>
                                <?php echo $payment['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : ''; ?>">
                                <?php echo ucfirst($payment['status']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"></path><path d="M14 8H8"></path><path d="M16 12H8"></path><path d="M13 16H8"></path></svg>
                    <p class="text-gray-500">No recent payments</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>



