<?php
/**
 * Lab Technician Dashboard
 * Lab order processing overview
 */
require_once '../config/config.php';
requireAuth();
checkRole(['lab_technician']);

$page_title = "Lab Technician Dashboard";

try {
    // Lab technician statistics
    $stats_query = "SELECT 
        (SELECT COUNT(*) FROM e_lab_orders WHERE status = 'pending') as pending_orders,
        (SELECT COUNT(*) FROM e_lab_orders WHERE status = 'completed' AND DATE(completed_at) = CURDATE()) as completed_today,
        (SELECT COUNT(*) FROM e_lab_orders WHERE status = 'in_progress') as in_progress,
        (SELECT COUNT(*) FROM e_lab_orders WHERE DATE(order_date) = CURDATE()) as orders_today";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch();

    // Pending lab orders (prioritized)
    $pending_orders = $db->query("SELECT elo.*, p.first_name, p.last_name, p.hospital_id, u.first_name as doctor_fname, u.last_name as doctor_lname
        FROM e_lab_orders elo
        INNER JOIN patients p ON elo.patient_id = p.id
        INNER JOIN users u ON elo.doctor_id = u.id
        WHERE elo.status = 'pending'
        ORDER BY 
            CASE elo.priority 
                WHEN 'stat' THEN 1 
                WHEN 'urgent' THEN 2 
                ELSE 3 
            END,
            elo.order_date ASC, elo.created_at ASC
        LIMIT 10")->fetchAll();

} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching dashboard data: " . $e->getMessage();
    $stats = [];
    $pending_orders = [];
}

include '../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Lab Technician Dashboard</h1>
    <p class="text-gray-600 dark:text-gray-400">Welcome, <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?> - Lab order processing overview</p>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M10 2v2"></path><path d="M14 2v2"></path><path d="M10.5 2h3"></path><path d="M7 12a5 5 0 0 0 10 0Z"></path><path d="M7 12H4a2 2 0 0 0-2 2v1a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-1a2 2 0 0 0-2-2h-3"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Pending Orders</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['pending_orders'] ?? 0; ?></dd>
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
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Completed Today</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['completed_today'] ?? 0; ?></dd>
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white animate-spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">In Progress</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['in_progress'] ?? 0; ?></dd>
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
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['orders_today'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pending Lab Orders -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Pending Lab Orders (Prioritized)</h3>
    </div>
    <div class="px-4 py-5 sm:p-6">
        <?php if (count($pending_orders) > 0): ?>
            <div class="space-y-4">
                <?php foreach ($pending_orders as $order): ?>
                    <div class="p-4 rounded-lg border 
                        <?php echo $order['priority'] == 'stat' ? 'bg-red-50 border-red-200' : ''; ?>
                        <?php echo $order['priority'] == 'urgent' ? 'bg-orange-50 border-orange-200' : ''; ?>
                        <?php echo $order['priority'] == 'routine' ? 'bg-yellow-50 border-yellow-200' : ''; ?>">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    <?php echo $order['first_name'] . ' ' . $order['last_name']; ?>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo $order['hospital_id']; ?></p>
                                <p class="text-xs text-gray-600 mt-1">Order #<?php echo $order['order_number']; ?></p>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    <?php echo $order['priority'] == 'stat' ? 'bg-red-100 text-red-800' : ''; ?>
                                    <?php echo $order['priority'] == 'urgent' ? 'bg-orange-100 text-orange-800' : ''; ?>
                                    <?php echo $order['priority'] == 'routine' ? 'bg-blue-100 text-blue-800' : ''; ?>">
                                    <?php echo strtoupper($order['priority']); ?>
                                </span>
                                <p class="text-xs text-gray-500 mt-1">Dr. <?php echo $order['doctor_fname'] . ' ' . $order['doctor_lname']; ?></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo formatDate($order['order_date']); ?></p>
                            </div>
                        </div>
                        <?php 
                        $lab_tests = json_decode($order['lab_tests'], true);
                        if (is_array($lab_tests) && count($lab_tests) > 0): 
                        ?>
                            <div class="mt-3 p-3 bg-white rounded">
                                <p class="text-xs font-medium text-gray-700 mb-2">Lab Tests:</p>
                                <ul class="space-y-1">
                                    <?php foreach ($lab_tests as $test): ?>
                                        <li class="text-xs text-gray-600">
                                            • <?php echo $test['test_name']; ?>
                                            <?php if (!empty($test['specimen_type'])): ?>
                                                (<?php echo $test['specimen_type']; ?>)
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <div class="mt-3">
                            <a href="../modules/telehealth/e_labs.php" 
                               class="text-sm text-primary-600 hover:text-primary-700">
                                Process Lab Order →
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-8">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
                <p class="text-gray-500">No pending lab orders</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>



