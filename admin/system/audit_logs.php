<?php
/**
 * Employee Audit Logs
 * Track login/logout and all actions by employees from department_accounts only
 * Excludes patients and regular users
 */
require_once '../../config/config.php';
requireAuth();
checkRole(['admin']);

$page_title = "Audit Logs";

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$employee_id = $_GET['employee_id'] ?? 'all';
$action_filter = $_GET['action'] ?? 'all';
$module_filter = $_GET['module'] ?? 'all';
$role_filter = $_GET['role'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query - join audit_logs with department_accounts
// Show ONLY employee logs from department_accounts (exclude patients and regular users)
// Filter by checking if there's a match with department_accounts or if source_table in JSON is 'department_accounts'
$query = "SELECT al.*, 
          al.module as module,
          COALESCE(al.user_id, 'Unknown') as employee_id, 
          COALESCE(da.employee_fname, u.first_name, 'Unknown') as first_name, 
          COALESCE(da.employee_lname, u.last_name, '') as last_name, 
          COALESCE(da.employee_email, u.email, '') as email,
          COALESCE(da.role_name, r.role_name) as role_name,
          da.last_login,
          da.is_active
          FROM audit_logs al
          LEFT JOIN department_accounts da ON CAST(al.user_id AS CHAR) = da.employee_id
          LEFT JOIN users u ON al.user_id = u.id
          LEFT JOIN roles r ON u.role_id = r.id
          WHERE DATE(al.created_at) BETWEEN :start_date AND :end_date
          AND al.user_id IS NOT NULL";

$params = [':start_date' => $start_date, ':end_date' => $end_date];

if ($employee_id !== 'all') {
    $query .= " AND al.user_id = :employee_id";
    $params[':employee_id'] = $employee_id;
}

if ($action_filter !== 'all') {
    $query .= " AND al.action = :action";
    $params[':action'] = $action_filter;
}

if ($module_filter !== 'all') {
    $query .= " AND al.module = :module";
    $params[':module'] = $module_filter;
}

if ($role_filter !== 'all') {
    $query .= " AND COALESCE(da.role_name, r.role_name) = :role";
    $params[':role'] = $role_filter;
}

if ($search) {
    $query .= " AND (
        al.action LIKE :search 
        OR al.module LIKE :search 
        OR al.ip_address LIKE :search 
        OR da.employee_fname LIKE :search 
        OR da.employee_lname LIKE :search 
        OR da.employee_email LIKE :search
        OR u.first_name LIKE :search
        OR u.last_name LIKE :search
        OR (al.new_values IS NOT NULL AND al.new_values LIKE :search)
    )";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY al.created_at DESC LIMIT 500";

// Debug: Check if there's any employee data in audit_logs for the date range
try {
    $debug_query = "SELECT COUNT(*) as total 
                    FROM audit_logs al
                    WHERE DATE(al.created_at) BETWEEN :start_date AND :end_date
                    AND al.user_id IS NOT NULL";
    $debug_stmt = $db->prepare($debug_query);
    $debug_stmt->bindParam(':start_date', $start_date);
    $debug_stmt->bindParam(':end_date', $end_date);
    $debug_stmt->execute();
    $debug_result = $debug_stmt->fetch();
    // Uncomment below to see total count in page source for debugging
    // error_log("Employee audit logs total count: " . ($debug_result['total'] ?? 0));
} catch (PDOException $e) {
    error_log("Debug query error: " . $e->getMessage());
}

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get employees for filter - get all active employees from department_accounts
try {
    $employees_query = "SELECT DISTINCT da.employee_id, da.employee_fname, da.employee_lname, da.employee_email 
                        FROM department_accounts da 
                        WHERE da.is_active = 1
                        ORDER BY da.employee_fname, da.employee_lname";
    $employees_stmt = $db->prepare($employees_query);
    $employees_stmt->execute();
    $employees = $employees_stmt->fetchAll();
} catch (PDOException $e) {
    $employees = [];
}

// Get unique actions and modules from audit_logs - only for employees
try {
    $actions_query = "SELECT DISTINCT al.action 
                      FROM audit_logs al
                      WHERE al.user_id IS NOT NULL
                      ORDER BY al.action";
    $actions_stmt = $db->prepare($actions_query);
    $actions_stmt->execute();
    $available_actions = array_column($actions_stmt->fetchAll(), 'action');
} catch (PDOException $e) {
    $available_actions = [];
}

try {
    $modules_query = "SELECT DISTINCT al.module 
                      FROM audit_logs al
                      WHERE al.user_id IS NOT NULL
                      ORDER BY al.module";
    $modules_stmt = $db->prepare($modules_query);
    $modules_stmt->execute();
    $available_modules = array_column($modules_stmt->fetchAll(), 'module');
} catch (PDOException $e) {
    $available_modules = [];
}

// Get unique roles from department_accounts - only for employees with audit logs
try {
    $roles_query = "SELECT DISTINCT COALESCE(da.role_name, r.role_name) as role_name
                    FROM audit_logs al 
                    LEFT JOIN department_accounts da ON CAST(al.user_id AS CHAR) = da.employee_id
                    LEFT JOIN users u ON al.user_id = u.id
                    LEFT JOIN roles r ON u.role_id = r.id
                    WHERE al.user_id IS NOT NULL
                    AND COALESCE(da.role_name, r.role_name) IS NOT NULL
                    AND COALESCE(da.role_name, r.role_name) != ''
                    ORDER BY role_name";
    $roles_stmt = $db->prepare($roles_query);
    $roles_stmt->execute();
    $available_roles = array_column($roles_stmt->fetchAll(), 'role_name');
} catch (PDOException $e) {
    $available_roles = [];
}

// Stats for top-level cards (excluding create/update/delete per request)
$stats_query = "SELECT 
    COUNT(*) as total,
    COUNT(DISTINCT al.user_id) as unique_users,
    SUM(CASE WHEN al.action = 'login' THEN 1 ELSE 0 END) as logins,
    SUM(CASE WHEN al.action = 'logout' THEN 1 ELSE 0 END) as logouts
    FROM audit_logs al
    WHERE DATE(al.created_at) BETWEEN :start_date AND :end_date
    AND al.user_id IS NOT NULL";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':start_date', $start_date);
$stats_stmt->bindParam(':end_date', $end_date);
$stats_stmt->execute();
$stats = $stats_stmt->fetch();

include '../../includes/header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Employee Audit Logs</h1>
            <p class="text-gray-600 dark:text-gray-400">Track login/logout and all actions by employees from department_accounts</p>
        </div>
        <?php if (count($logs) > 0): ?>
            <div class="flex space-x-2">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" 
                   class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    Export CSV
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 gap-6 sm:grid-cols-4 mb-6">
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">Total Actions</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $stats['total'] ?? 0; ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">Unique Users</p>
        <p class="text-2xl font-bold text-blue-600"><?php echo $stats['unique_users'] ?? 0; ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">Logins</p>
        <p class="text-2xl font-bold text-green-600"><?php echo $stats['logins'] ?? 0; ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">Logouts</p>
        <p class="text-2xl font-bold text-orange-600"><?php echo $stats['logouts'] ?? 0; ?></p>
    </div>
</div>

<!-- Audit Logs Table -->
<div class="bg-white dark:bg-gray-800 shadow rounded-lg">
    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Activity Log</h3>
    </div>
    <div class="px-4 py-5 sm:p-6">
        <?php if (count($logs) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Timestamp</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Employee</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Action</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Module</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Details</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">IP Address</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo date('M d, Y', strtotime($log['created_at'])); ?>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        <?php echo date('h:i:s A', strtotime($log['created_at'])); ?>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php 
                                        $full_name = trim(($log['first_name'] ?? 'Unknown') . ' ' . ($log['last_name'] ?? ''));
                                        echo htmlspecialchars($full_name ?: 'Unknown User');
                                        ?>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        ID: <?php echo htmlspecialchars($log['employee_id'] ?? 'N/A'); ?>
                                        <?php if (!empty($log['role_name'])): ?>
                                            <span class="ml-1 text-blue-600 dark:text-blue-400">(<?php echo htmlspecialchars($log['role_name']); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($log['email'])): ?>
                                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                        <?php echo htmlspecialchars($log['email']); ?>
                                    </div>
                                    <?php else: ?>
                                        <p class="text-xs italic text-gray-400 dark:text-gray-500">No details provided.</p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                        $action = $log['action'] ?? 'other';
                                        if ($action === 'login') {
                                            echo 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                                        } elseif ($action === 'logout') {
                                            echo 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';
                                        } elseif ($action === 'create') {
                                            echo 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
                                        } elseif ($action === 'update') {
                                            echo 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
                                        } elseif ($action === 'delete') {
                                            echo 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
                                        } else {
                                            echo 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
                                        }
                                        ?>">
                                        <?php echo ucfirst(htmlspecialchars($action)); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    <?php echo htmlspecialchars($log['module'] ?? 'N/A'); ?>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">
                                    <?php
                                    $details_text = '';
                                    if (!empty($log['new_values'])) {
                                        $decoded = json_decode($log['new_values'], true);
                                        if (json_last_error() === JSON_ERROR_NONE && !empty($decoded['details'])) {
                                            $details_text = trim((string)$decoded['details']);
                                        }
                                    }
                                    if ($details_text === '') {
                                        $details_text = sprintf(
                                            '%s %s',
                                            ucfirst($log['action'] ?? 'Performed'),
                                            $log['module'] ?? 'module action'
                                        );
                                    }
                                    ?>
                                    <p class="text-sm text-gray-800 dark:text-gray-200">
                                        <?php echo htmlspecialchars($details_text); ?>
                                    </p>

                                    <?php if (!empty($log['record_id'])): ?>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">Record ID: <?php echo htmlspecialchars($log['record_id']); ?></span>
                                    <?php endif; ?>
                                    <?php if ((!empty($log['old_values'])) || (!empty($log['new_values']))): ?>
                                        <details class="mt-1">
                                            <summary class="text-xs text-blue-600 dark:text-blue-400 cursor-pointer">View Changes</summary>
                                            <div class="mt-2 text-xs">
                                                <?php if (!empty($log['old_values'])): ?>
                                                    <div class="text-red-600 dark:text-red-400">Old: <?php echo htmlspecialchars(substr($log['old_values'], 0, 100)); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($log['new_values'])): ?>
                                                    <div class="text-green-600 dark:text-green-400">New: <?php echo htmlspecialchars(substr($log['new_values'], 0, 100)); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </details>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    <?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-8">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 dark:text-gray-600 mx-auto mb-2">
                    <rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect>
                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                    <path d="M12 11h4"></path>
                    <path d="M12 16h4"></path>
                    <path d="M8 11h.01"></path>
                    <path d="M8 16h.01"></path>
                </svg>
                <p class="text-gray-500 dark:text-gray-400">No audit logs found for the selected period</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
