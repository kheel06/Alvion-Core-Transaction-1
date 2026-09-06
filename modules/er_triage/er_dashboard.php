<?php
require_once '../../config/config.php';
requireAuth();
checkRole(['admin', 'nurse', 'doctor']);

$page_title = "ER Dashboard";

try {
    // Detect patients table columns (some schemas omit first_name/last_name)
    $patients_has_first_name = false;
    $patients_has_last_name = false;
    $patients_has_hospital_id = false;
    try {
        $cols = $db->query("SHOW COLUMNS FROM patients");
        $patient_columns = $cols ? $cols->fetchAll(PDO::FETCH_COLUMN) : [];
        $patients_has_first_name = in_array('first_name', $patient_columns);
        $patients_has_last_name = in_array('last_name', $patient_columns);
        $patients_has_hospital_id = in_array('hospital_id', $patient_columns);
    } catch (PDOException $e) {
        // patients table may not exist
    }

    $p_select = 'et.*, ';
    if ($patients_has_first_name) {
        $p_select .= 'p.first_name, ';
    } else {
        $p_select .= 'NULL AS first_name, ';
    }
    if ($patients_has_last_name) {
        $p_select .= 'p.last_name, ';
    } else {
        $p_select .= 'NULL AS last_name, ';
    }
    if ($patients_has_hospital_id) {
        $p_select .= 'p.hospital_id, ';
    } else {
        $p_select .= 'NULL AS hospital_id, ';
    }

    // Detect users table name columns (for nurse display)
    $users_has_first_name = false;
    $users_has_last_name = false;
    try {
        $ucols = $db->query("SHOW COLUMNS FROM users");
        $user_columns = $ucols ? $ucols->fetchAll(PDO::FETCH_COLUMN) : [];
        $users_has_first_name = in_array('first_name', $user_columns);
        $users_has_last_name = in_array('last_name', $user_columns);
    } catch (PDOException $e) {
        // users table may not exist
    }
    if ($users_has_first_name && $users_has_last_name) {
        $u_select = 'u.first_name as nurse_fname, u.last_name as nurse_lname';
    } else {
        $u_select = 'NULL AS nurse_fname, NULL AS nurse_lname';
    }

    // Get current ER stats
    $er_stats_query = "SELECT 
        COUNT(*) as total_triage_today,
        COUNT(CASE WHEN status = 'waiting' THEN 1 END) as waiting,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
        COUNT(CASE WHEN status = 'admitted' THEN 1 END) as admitted,
        COUNT(CASE WHEN status = 'discharged' THEN 1 END) as discharged,
        COUNT(CASE WHEN triage_level = 'resuscitation' THEN 1 END) as resuscitation,
        COUNT(CASE WHEN triage_level = 'emergency' THEN 1 END) as emergency,
        COUNT(CASE WHEN triage_level = 'urgent' THEN 1 END) as urgent,
        COUNT(CASE WHEN triage_level = 'semi_urgent' THEN 1 END) as semi_urgent,
        COUNT(CASE WHEN triage_level = 'non_urgent' THEN 1 END) as non_urgent
        FROM er_triage 
        WHERE DATE(created_at) = CURDATE()";
    $er_stats_stmt = $db->prepare($er_stats_query);
    $er_stats_stmt->execute();
    $er_stats = $er_stats_stmt->fetch();

    // Get current triage queue (patient name columns built dynamically)
    $triage_queue_query = "SELECT " . $p_select . "
                          " . $u_select . "
                          FROM er_triage et
                          LEFT JOIN patients p ON et.patient_id = p.id
                          LEFT JOIN users u ON et.triage_nurse_id = u.id
                          WHERE et.status = 'waiting'
                          ORDER BY et.priority_score ASC, et.created_at ASC
                          LIMIT 10";
    $triage_queue_stmt = $db->prepare($triage_queue_query);
    $triage_queue_stmt->execute();
    $triage_queue = $triage_queue_stmt->fetchAll();

    // Get recent ER activity
    $recent_activity_query = "SELECT " . $p_select . "
                             " . $u_select . "
                             FROM er_triage et
                             LEFT JOIN patients p ON et.patient_id = p.id
                             LEFT JOIN users u ON et.triage_nurse_id = u.id
                             WHERE DATE(et.created_at) = CURDATE()
                             ORDER BY et.created_at DESC
                             LIMIT 10";
    $recent_activity_stmt = $db->prepare($recent_activity_query);
    $recent_activity_stmt->execute();
    $recent_activity = $recent_activity_stmt->fetchAll();

    // Get ER bed status
    $er_beds_query = "SELECT COUNT(*) as total_er_beds,
                      COUNT(CASE WHEN status = 'occupied' THEN 1 END) as occupied_er_beds
                      FROM beds b
                      LEFT JOIN wards w ON b.ward_id = w.id
                      WHERE w.ward_type = 'emergency'";
    $er_beds_stmt = $db->prepare($er_beds_query);
    $er_beds_stmt->execute();
    $er_beds = $er_beds_stmt->fetch();

} catch (PDOException $exception) {
    $_SESSION['error'] = "Error fetching ER dashboard data: " . $exception->getMessage();
    $er_stats = [];
    $triage_queue = [];
    $recent_activity = [];
    $er_beds = [];
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Emergency Room Dashboard</h1>
    <p class="text-gray-600 dark:text-gray-400">Real-time overview of emergency room operations</p>
</div>

<!-- ER Statistics -->
<div class="grid grid-cols-2 gap-6 mb-8 sm:grid-cols-3 lg:grid-cols-5">
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M10 2v6"></path><path d="M14 2v6"></path><path d="M18 8H2"></path><path d="M20 8v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8"></path><path d="M4 12h16"></path><path d="M8 18h8"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Today</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $er_stats['total_triage_today'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Waiting</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $er_stats['waiting'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M2 4v16"></path><path d="M2 8h18a2 2 0 0 1 2 2v10"></path><path d="M2 17h20"></path><path d="M6 8V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"></path><circle cx="13" cy="13" r="1"></circle><circle cx="11" cy="13" r="1"></circle></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">In Progress</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $er_stats['in_progress'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"></path><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Admitted</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $er_stats['admitted'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-gray-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Discharged</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $er_stats['discharged'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <!-- Current Triage Queue -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                Current Triage Queue
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                <?php echo count($triage_queue); ?> patients waiting for triage
            </p>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($triage_queue) > 0): ?>
                <div class="space-y-4">
                    <?php foreach ($triage_queue as $triage): ?>
                        <div class="flex items-center justify-between p-3 border rounded-lg 
                            <?php echo $triage['triage_level'] == 'resuscitation' ? 'border-red-300 bg-red-50' : ''; ?>
                            <?php echo $triage['triage_level'] == 'emergency' ? 'border-orange-300 bg-orange-50' : ''; ?>
                            <?php echo $triage['triage_level'] == 'urgent' ? 'border-yellow-300 bg-yellow-50' : ''; ?>
                            <?php echo $triage['triage_level'] == 'semi_urgent' ? 'border-blue-300 bg-blue-50' : ''; ?>
                            <?php echo $triage['triage_level'] == 'non_urgent' ? 'border-green-300 bg-green-50' : ''; ?>">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center border">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-600"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                                    </div>
                                </div>
                                <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    <?php
                                    $name = trim(($triage['first_name'] ?? '') . ' ' . ($triage['last_name'] ?? ''));
                                    echo $name !== '' ? htmlspecialchars($name) : (isset($triage['hospital_id']) && $triage['hospital_id'] !== '' ? htmlspecialchars($triage['hospital_id']) : 'Patient #' . (int)($triage['patient_id'] ?? 0));
                                    ?>
                                </p>
                                    <?php if (!empty($triage['hospital_id'])): ?>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($triage['hospital_id']); ?>
                                    </p>
                                    <?php endif; ?>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                        <?php echo $triage['chief_complaint']; ?>
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    <?php echo getTriageColor($triage['triage_level']) == 'red' ? 'bg-red-100 text-red-800' : ''; ?>
                                    <?php echo getTriageColor($triage['triage_level']) == 'orange' ? 'bg-orange-100 text-orange-800' : ''; ?>
                                    <?php echo getTriageColor($triage['triage_level']) == 'yellow' ? 'bg-yellow-100 text-yellow-800' : ''; ?>
                                    <?php echo getTriageColor($triage['triage_level']) == 'blue' ? 'bg-blue-100 text-blue-800' : ''; ?>
                                    <?php echo getTriageColor($triage['triage_level']) == 'green' ? 'bg-green-100 text-green-800' : ''; ?>">
                                    <?php echo ucfirst($triage['triage_level']); ?>
                                </span>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?php echo formatTime($triage['created_at']); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 11v2a4 4 0 0 1-4 4h-1.5"></path><path d="M16 8h.01"></path></svg>
                    <p class="text-gray-500 dark:text-gray-400">No patients in triage queue</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent ER Activity -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                Recent ER Activity
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Latest emergency room cases
            </p>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($recent_activity) > 0): ?>
                <div class="space-y-4">
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    <?php
                                    $name = trim(($activity['first_name'] ?? '') . ' ' . ($activity['last_name'] ?? ''));
                                    echo $name !== '' ? htmlspecialchars($name) : (isset($activity['hospital_id']) && $activity['hospital_id'] !== '' ? htmlspecialchars($activity['hospital_id']) : 'Patient #' . (int)($activity['patient_id'] ?? 0));
                                    ?>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                    <?php echo $activity['chief_complaint']; ?>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Triage by: <?php $nurse = trim(($activity['nurse_fname'] ?? '') . ' ' . ($activity['nurse_lname'] ?? '')); echo $nurse !== '' ? htmlspecialchars($nurse) : 'N/A'; ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    <?php echo $activity['status'] == 'waiting' ? 'bg-yellow-100 text-yellow-800' : ''; ?>
                                    <?php echo $activity['status'] == 'in_progress' ? 'bg-blue-100 text-blue-800' : ''; ?>
                                    <?php echo $activity['status'] == 'admitted' ? 'bg-green-100 text-green-800' : ''; ?>
                                    <?php echo $activity['status'] == 'discharged' ? 'bg-gray-100 text-gray-800' : ''; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $activity['status'])); ?>
                                </span>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    <?php echo formatTime($activity['created_at']); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 dark:text-gray-600 mx-auto mb-2"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><path d="M12 11h4"></path><path d="M12 16h4"></path><path d="M8 11h.01"></path><path d="M8 16h.01"></path></svg>
                    <p class="text-gray-500 dark:text-gray-400">No recent ER activity</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Triage Priority Distribution -->
<div class="mt-6 bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
            Triage Priority Distribution (Today)
        </h3>
    </div>
    <div class="px-4 py-5 sm:p-6">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
            <div class="text-center p-4 bg-red-50 rounded-lg">
                <div class="text-2xl font-bold text-red-600"><?php echo $er_stats['resuscitation'] ?? 0; ?></div>
                <div class="text-sm font-medium text-red-800">Resuscitation</div>
            </div>
            <div class="text-center p-4 bg-orange-50 rounded-lg">
                <div class="text-2xl font-bold text-orange-600"><?php echo $er_stats['emergency'] ?? 0; ?></div>
                <div class="text-sm font-medium text-orange-800">Emergency</div>
            </div>
            <div class="text-center p-4 bg-yellow-50 rounded-lg">
                <div class="text-2xl font-bold text-yellow-600"><?php echo $er_stats['urgent'] ?? 0; ?></div>
                <div class="text-sm font-medium text-yellow-800">Urgent</div>
            </div>
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <div class="text-2xl font-bold text-blue-600"><?php echo $er_stats['semi_urgent'] ?? 0; ?></div>
                <div class="text-sm font-medium text-blue-800">Semi-urgent</div>
            </div>
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <div class="text-2xl font-bold text-green-600"><?php echo $er_stats['non_urgent'] ?? 0; ?></div>
                <div class="text-sm font-medium text-green-800">Non-urgent</div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <a href="triage.php" class="flex flex-col items-center p-4 bg-primary-50 dark:bg-primary-900/20 rounded-lg hover:bg-primary-100 dark:hover:bg-primary-900/40 transition-colors">
        <div class="w-12 h-12 bg-primary-500 rounded-full flex items-center justify-center mb-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white text-xl"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
        </div>
        <span class="text-sm font-medium text-gray-900 dark:text-white">New Triage</span>
    </a>

    <a href="../inpatient/admission.php" class="flex flex-col items-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/40 transition-colors">
        <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center mb-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white text-xl"><path d="M2 4v16"></path><path d="M2 8h18a2 2 0 0 1 2 2v10"></path><path d="M2 17h20"></path><path d="M6 8V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"></path><circle cx="13" cy="13" r="1"></circle><circle cx="11" cy="13" r="1"></circle></svg>
        </div>
        <span class="text-sm font-medium text-gray-900 dark:text-white">Admit Patient</span>
    </a>

    <a href="../registration/queue.php" class="flex flex-col items-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-colors">
        <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center mb-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white text-xl"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
        </div>
        <span class="text-sm font-medium text-gray-900 dark:text-white">View Queues</span>
    </a>

    <a href="../reports/er_report.php" class="flex flex-col items-center p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg hover:bg-purple-100 dark:hover:bg-purple-900/40 transition-colors">
        <div class="w-12 h-12 bg-purple-500 rounded-full flex items-center justify-center mb-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white text-xl"><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line></svg>
        </div>
        <span class="text-sm font-medium text-gray-900 dark:text-white">ER Reports</span>
    </a>
</div>

<script>
// Auto-refresh dashboard every 30 seconds
setTimeout(function() {
    window.location.reload();
}, 30000);
</script>

<?php include '../../includes/footer.php'; ?>