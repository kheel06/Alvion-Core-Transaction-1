<?php
/**
 * Nurse Dashboard
 * Triage and patient monitoring overview
 */
require_once '../config/config.php';
requireAuth();
checkRole(['nurse']);

$page_title = "Nurse Dashboard";
$nurse_id = $_SESSION['user_id'];

try {
    // Nurse-specific statistics
    $stats_query = "SELECT 
        (SELECT COUNT(*) FROM er_triage WHERE triage_nurse_id = :nurse_id AND DATE(created_at) = CURDATE()) as triage_today,
        (SELECT COUNT(*) FROM er_triage WHERE status = 'waiting') as er_waiting,
        (SELECT COUNT(*) FROM er_triage WHERE status = 'in_progress') as er_in_progress,
        (SELECT COUNT(*) FROM beds WHERE status = 'occupied') as occupied_beds,
        (SELECT COUNT(*) FROM admissions WHERE status = 'admitted') as active_admissions";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->bindParam(':nurse_id', $nurse_id, PDO::PARAM_INT);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch();

    // ER Triage Queue
    $triage_queue = $db->query("SELECT et.*, p.first_name, p.last_name, p.hospital_id
        FROM er_triage et
        INNER JOIN patients p ON et.patient_id = p.id
        WHERE et.status = 'waiting'
        ORDER BY et.priority_score ASC, et.created_at ASC
        LIMIT 10")->fetchAll();

    // Recent triage assessments
    $recent_triage = $db->prepare("SELECT et.*, p.first_name, p.last_name, p.hospital_id
        FROM er_triage et
        INNER JOIN patients p ON et.patient_id = p.id
        WHERE et.triage_nurse_id = :nurse_id
        ORDER BY et.created_at DESC
        LIMIT 5");
    $recent_triage->bindParam(':nurse_id', $nurse_id, PDO::PARAM_INT);
    $recent_triage->execute();
    $recent_triage = $recent_triage->fetchAll();

} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching dashboard data: " . $e->getMessage();
    $stats = [];
    $triage_queue = [];
    $recent_triage = [];
}

include '../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Nurse Dashboard</h1>
    <p class="text-gray-600 dark:text-gray-400">Welcome, <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?> - Triage and patient care overview</p>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M10 2v6"></path><path d="M14 2v6"></path><path d="M18 8H2"></path><path d="M20 8v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8"></path><path d="M4 12h16"></path><path d="M8 18h8"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Triage Today</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['triage_today'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-orange-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Waiting</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['er_waiting'] ?? 0; ?></dd>
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 11v2a4 4 0 0 1-4 4h-1.5"></path><path d="M16 8h.01"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">In Progress</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['er_in_progress'] ?? 0; ?></dd>
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M2 4v16"></path><path d="M2 8h18a2 2 0 0 1 2 2v10"></path><path d="M2 17h20"></path><path d="M6 8V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Occupied Beds</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['occupied_beds'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ER Triage Queue -->
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-8">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">ER Triage Queue</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($triage_queue) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($triage_queue as $triage): ?>
                        <div class="p-3 rounded-lg border 
                            <?php echo $triage['triage_level'] == 'resuscitation' ? 'bg-red-50 border-red-200' : ''; ?>
                            <?php echo $triage['triage_level'] == 'emergency' ? 'bg-orange-50 border-orange-200' : ''; ?>
                            <?php echo $triage['triage_level'] == 'urgent' ? 'bg-yellow-50 border-yellow-200' : ''; ?>
                            <?php echo $triage['triage_level'] == 'semi_urgent' ? 'bg-blue-50 border-blue-200' : ''; ?>
                            <?php echo $triage['triage_level'] == 'non_urgent' ? 'bg-green-50 border-green-200' : ''; ?>">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo $triage['first_name'] . ' ' . $triage['last_name']; ?>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo $triage['hospital_id']; ?></p>
                                    <p class="text-xs text-gray-600 mt-1"><?php echo substr($triage['chief_complaint'], 0, 50); ?>...</p>
                                </div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    <?php echo getTriageColor($triage['triage_level']) == 'red' ? 'bg-red-100 text-red-800' : ''; ?>
                                    <?php echo getTriageColor($triage['triage_level']) == 'orange' ? 'bg-orange-100 text-orange-800' : ''; ?>
                                    <?php echo getTriageColor($triage['triage_level']) == 'yellow' ? 'bg-yellow-100 text-yellow-800' : ''; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $triage['triage_level'])); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
                    <p class="text-gray-500">No patients in triage queue</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Recent Triage Assessments</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($recent_triage) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($recent_triage as $triage): ?>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo $triage['first_name'] . ' ' . $triage['last_name']; ?>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo $triage['hospital_id']; ?></p>
                            <p class="text-xs text-gray-600 mt-1"><?php echo substr($triage['chief_complaint'], 0, 40); ?>...</p>
                            <p class="text-xs text-gray-500 mt-1"><?php echo formatDate($triage['created_at']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><path d="M12 11h4"></path><path d="M12 16h4"></path><path d="M8 11h.01"></path><path d="M8 16h.01"></path></svg>
                    <p class="text-gray-500">No recent assessments</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>



