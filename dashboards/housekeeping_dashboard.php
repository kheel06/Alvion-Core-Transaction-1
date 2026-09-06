<?php
/**
 * Housekeeping Dashboard
 * Bed cleaning and maintenance overview
 */
require_once '../config/config.php';
requireAuth();
checkRole(['housekeeping']);

$page_title = "Housekeeping Dashboard";

try {
    // Housekeeping statistics
    $stats_query = "SELECT 
        (SELECT COUNT(*) FROM beds WHERE cleaning_status = 'dirty') as dirty_beds,
        (SELECT COUNT(*) FROM beds WHERE cleaning_status = 'cleaning') as cleaning_beds,
        (SELECT COUNT(*) FROM beds WHERE cleaning_status = 'clean') as clean_beds,
        (SELECT COUNT(*) FROM housekeeping_tasks WHERE status = 'pending') as pending_tasks,
        (SELECT COUNT(*) FROM housekeeping_tasks WHERE status = 'in_progress') as in_progress_tasks,
        (SELECT COUNT(*) FROM housekeeping_tasks WHERE DATE(created_at) = CURDATE() AND status = 'completed') as completed_today";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch();

    // Pending tasks
    $pending_tasks = $db->query("SELECT ht.*, b.bed_number, w.ward_name, w.ward_code
        FROM housekeeping_tasks ht
        INNER JOIN beds b ON ht.bed_id = b.id
        INNER JOIN wards w ON b.ward_id = w.id
        WHERE ht.status = 'pending'
        ORDER BY ht.scheduled_at ASC, ht.created_at ASC
        LIMIT 10")->fetchAll();

    // Beds needing cleaning
    $dirty_beds = $db->query("SELECT b.*, w.ward_name, w.ward_code
        FROM beds b
        INNER JOIN wards w ON b.ward_id = w.id
        WHERE b.cleaning_status = 'dirty'
        ORDER BY b.last_cleaned_at ASC
        LIMIT 10")->fetchAll();

} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching dashboard data: " . $e->getMessage();
    $stats = [];
    $pending_tasks = [];
    $dirty_beds = [];
}

include '../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Housekeeping Dashboard</h1>
    <p class="text-gray-600 dark:text-gray-400">Welcome, <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?> - Bed cleaning and maintenance overview</p>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="m12 2 3 9h6l-4.5 6 3 9-6-3-6 3 3-9L3 11h6z"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Dirty Beds</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['dirty_beds'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white animate-spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Cleaning</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['cleaning_beds'] ?? 0; ?></dd>
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
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Clean Beds</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['clean_beds'] ?? 0; ?></dd>
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
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
</div>

<!-- Tasks and Beds -->
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Pending Tasks</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($pending_tasks) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($pending_tasks as $task): ?>
                        <div class="p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo ucfirst($task['task_type']); ?> - Bed <?php echo $task['bed_number']; ?>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo $task['ward_name']; ?> (<?php echo $task['ward_code']; ?>)</p>
                                    <?php if ($task['scheduled_at']): ?>
                                        <p class="text-xs text-gray-600 mt-1">Scheduled: <?php echo formatDate($task['scheduled_at'], 'M j, Y g:i A'); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
                    <p class="text-gray-500">No pending tasks</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Beds Needing Cleaning</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($dirty_beds) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($dirty_beds as $bed): ?>
                        <div class="p-3 bg-red-50 rounded-lg border border-red-200">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        Bed <?php echo $bed['bed_number']; ?>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo $bed['ward_name']; ?> (<?php echo $bed['ward_code']; ?>)</p>
                                    <?php if ($bed['last_cleaned_at']): ?>
                                        <p class="text-xs text-gray-600 mt-1">Last cleaned: <?php echo formatDate($bed['last_cleaned_at']); ?></p>
                                    <?php else: ?>
                                        <p class="text-xs text-gray-600 mt-1">Never cleaned</p>
                                    <?php endif; ?>
                                </div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    Dirty
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
                    <p class="text-gray-500">All beds are clean</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>



