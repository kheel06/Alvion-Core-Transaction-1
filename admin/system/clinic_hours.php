<?php
/**
 * Clinic Hours Configuration
 * Admin only - Configure clinic operating hours
 */
require_once '../../config/config.php';
requireAuth();
checkRole(['admin']);
requirePermission('system.clinic_hours');

$page_title = "Clinic Hours Configuration";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = sanitizeInput($_POST['action'] ?? '');
        $user_id = $_SESSION['user_id'];
        
        if ($action === 'update') {
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            
            foreach ($days as $day) {
                $open_time = sanitizeInput($_POST[$day . '_open'] ?? '00:00:00');
                $close_time = sanitizeInput($_POST[$day . '_close'] ?? '00:00:00');
                $is_closed = isset($_POST[$day . '_closed']) ? 1 : 0;
                $department = !empty($_POST['department']) ? sanitizeInput($_POST['department']) : null;
                
                // Check if record exists
                $check_query = "SELECT id FROM clinic_hours 
                               WHERE day_of_week = :day 
                               AND (department = :department OR (department IS NULL AND :department IS NULL))";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':day', $day);
                $check_stmt->bindParam(':department', $department);
                $check_stmt->execute();
                $existing = $check_stmt->fetch();
                
                if ($existing) {
                    $query = "UPDATE clinic_hours 
                             SET open_time = :open_time, close_time = :close_time, 
                                 is_closed = :is_closed, updated_by = :updated_by
                             WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $existing['id']);
                } else {
                    $query = "INSERT INTO clinic_hours 
                             (day_of_week, open_time, close_time, is_closed, department, created_by, updated_by)
                             VALUES 
                             (:day_of_week, :open_time, :close_time, :is_closed, :department, :created_by, :updated_by)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':day_of_week', $day);
                    $stmt->bindParam(':created_by', $user_id);
                }
                
                $stmt->bindParam(':open_time', $open_time);
                $stmt->bindParam(':close_time', $close_time);
                $stmt->bindParam(':is_closed', $is_closed);
                $stmt->bindParam(':department', $department);
                $stmt->bindParam(':updated_by', $user_id);
                $stmt->execute();
            }
            
            logAction('clinic_hours_updated', 'system', null, null, ['department' => $department]);
            $_SESSION['success'] = "Clinic hours updated successfully!";
            header("Location: clinic_hours.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Get current clinic hours
try {
    $query = "SELECT * FROM clinic_hours WHERE department IS NULL ORDER BY 
              FIELD(day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $hours = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert to associative array by day
    $hours_by_day = [];
    foreach ($hours as $hour) {
        $hours_by_day[$hour['day_of_week']] = $hour;
    }
} catch (PDOException $e) {
    $hours_by_day = [];
}

// Get departments for department-specific hours
try {
    $dept_query = "SELECT DISTINCT department FROM appointments WHERE department IS NOT NULL ORDER BY department";
    $dept_stmt = $db->prepare($dept_query);
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $departments = [];
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Clinic Hours Configuration</h1>
    <p class="text-gray-600">Set operating hours for the clinic and departments</p>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">
        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<div class="bg-white shadow rounded-lg">
    <form method="POST" class="p-6">
        <input type="hidden" name="action" value="update">
        
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Department (Leave empty for general clinic hours)</label>
            <select name="department" class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                <option value="">General Clinic Hours</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Day</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Open Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Close Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Closed</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    $days = [
                        'monday' => 'Monday',
                        'tuesday' => 'Tuesday',
                        'wednesday' => 'Wednesday',
                        'thursday' => 'Thursday',
                        'friday' => 'Friday',
                        'saturday' => 'Saturday',
                        'sunday' => 'Sunday'
                    ];
                    
                    foreach ($days as $day_key => $day_name):
                        $hour = $hours_by_day[$day_key] ?? null;
                    ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo $day_name; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="time" 
                                       name="<?php echo $day_key; ?>_open" 
                                       value="<?php echo $hour ? substr($hour['open_time'], 0, 5) : '08:00'; ?>"
                                       class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
                                       <?php echo $hour && $hour['is_closed'] ? 'disabled' : ''; ?>>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="time" 
                                       name="<?php echo $day_key; ?>_close" 
                                       value="<?php echo $hour ? substr($hour['close_time'], 0, 5) : '17:00'; ?>"
                                       class="block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
                                       <?php echo $hour && $hour['is_closed'] ? 'disabled' : ''; ?>>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" 
                                       name="<?php echo $day_key; ?>_closed" 
                                       value="1"
                                       <?php echo $hour && $hour['is_closed'] ? 'checked' : ''; ?>
                                       onchange="toggleDayTimes(this, '<?php echo $day_key; ?>')"
                                       class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-6 flex justify-end">
            <button type="submit" class="btn btn-primary">
                Save Clinic Hours
            </button>
        </div>
    </form>
</div>

<script>
function toggleDayTimes(checkbox, day) {
    const openInput = document.querySelector(`input[name="${day}_open"]`);
    const closeInput = document.querySelector(`input[name="${day}_close"]`);
    
    if (checkbox.checked) {
        openInput.disabled = true;
        closeInput.disabled = true;
        openInput.value = '00:00';
        closeInput.value = '00:00';
    } else {
        openInput.disabled = false;
        closeInput.disabled = false;
        if (openInput.value === '00:00') {
            openInput.value = '08:00';
            closeInput.value = '17:00';
        }
    }
}
</script>

<?php include '../../includes/footer.php'; ?>

