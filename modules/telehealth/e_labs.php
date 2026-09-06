<?php
/**
 * E-Lab Orders Module
 * Part of TOCS - Telehealth and Outpatient Care System
 */
require_once '../../config/config.php';
requireAuth();
checkRole(['admin', 'doctor', 'lab_technician']);

$page_title = "E-Lab Orders";

// Handle lab order creation (doctor)
if ($_POST && isset($_POST['create_lab_order'])) {
    try {
        $order_number = 'LAB' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $patient_id = (int)sanitizeInput($_POST['patient_id']);
        $doctor_id = $_SESSION['user_id'];
        $consultation_id = !empty($_POST['consultation_id']) ? (int)sanitizeInput($_POST['consultation_id']) : null;
        $appointment_id = !empty($_POST['appointment_id']) ? (int)sanitizeInput($_POST['appointment_id']) : null;
        $order_date = date('Y-m-d');
        $priority = sanitizeInput($_POST['priority'] ?? 'routine');
        
        // Parse lab tests from JSON or array
        $lab_tests = [];
        if (isset($_POST['lab_tests']) && is_array($_POST['lab_tests'])) {
            foreach ($_POST['lab_tests'] as $test) {
                $lab_tests[] = [
                    'test_name' => sanitizeInput($test['test_name']),
                    'test_code' => sanitizeInput($test['test_code'] ?? ''),
                    'specimen_type' => sanitizeInput($test['specimen_type'] ?? ''),
                    'notes' => sanitizeInput($test['notes'] ?? '')
                ];
            }
        }
        
        $query = "INSERT INTO e_lab_orders (
            order_number, patient_id, doctor_id, consultation_id, appointment_id,
            order_date, lab_tests, priority, status
        ) VALUES (
            :order_number, :patient_id, :doctor_id, :consultation_id, :appointment_id,
            :order_date, :lab_tests, :priority, 'pending'
        )";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':order_number', $order_number);
        $stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
        $stmt->bindValue(':consultation_id', $consultation_id, $consultation_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':appointment_id', $appointment_id, $appointment_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindParam(':order_date', $order_date);
        $stmt->bindParam(':lab_tests', json_encode($lab_tests));
        $stmt->bindParam(':priority', $priority);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Lab order created successfully! Order #: " . $order_number;
            header("Location: e_labs.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error creating lab order: " . $e->getMessage();
    }
}

// Handle lab order processing (lab technician)
if ($_POST && isset($_POST['process_lab_order'])) {
    try {
        $order_id = (int)sanitizeInput($_POST['order_id']);
        $status = sanitizeInput($_POST['status']);
        $results = sanitizeInput($_POST['results'] ?? '');
        
        $query = "UPDATE e_lab_orders 
                 SET status = :status, results = :results, completed_at = NOW(), completed_by = :completed_by 
                 WHERE id = :order_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':results', $results);
        $stmt->bindParam(':completed_by', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Lab order status updated successfully!";
            header("Location: e_labs.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating lab order: " . $e->getMessage();
    }
}

// Get lab orders based on role
$role_name = $_SESSION['role_name'] ?? '';

try {
    if ($role_name == 'lab_technician') {
        // Lab technician sees pending orders
        $orders_query = "SELECT elo.*, p.first_name, p.last_name, p.hospital_id,
                        u.first_name as doctor_fname, u.last_name as doctor_lname
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
                            elo.order_date DESC, elo.created_at DESC";
    } else {
        // Doctor sees all orders they created
        $orders_query = "SELECT elo.*, p.first_name, p.last_name, p.hospital_id,
                        u.first_name as doctor_fname, u.last_name as doctor_lname
                        FROM e_lab_orders elo
                        INNER JOIN patients p ON elo.patient_id = p.id
                        INNER JOIN users u ON elo.doctor_id = u.id
                        WHERE elo.doctor_id = :doctor_id
                        ORDER BY elo.order_date DESC, elo.created_at DESC";
    }
    
    $orders_stmt = $db->prepare($orders_query);
    if ($role_name != 'lab_technician') {
        $orders_stmt->bindParam(':doctor_id', $_SESSION['user_id'], PDO::PARAM_INT);
    }
    $orders_stmt->execute();
    $lab_orders = $orders_stmt->fetchAll();
} catch (PDOException $e) {
    $lab_orders = [];
}

// Get patients for dropdown (doctor only)
$patients = [];
if ($role_name == 'doctor' || $role_name == 'admin') {
    try {
        $patients_query = "SELECT id, hospital_id, first_name, last_name FROM patients WHERE status = 'active' ORDER BY first_name, last_name";
        $patients_stmt = $db->prepare($patients_query);
        $patients_stmt->execute();
        $patients = $patients_stmt->fetchAll();
    } catch (PDOException $e) {
        $patients = [];
    }
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">E-Lab Orders</h1>
    <p class="text-gray-600"><?php echo $role_name == 'lab_technician' ? 'Process pending lab orders' : 'Manage electronic lab orders'; ?></p>
</div>

<?php if ($role_name == 'doctor' || $role_name == 'admin'): ?>
<!-- Create Lab Order Form -->
<div class="mb-6 bg-white shadow rounded-lg">
    <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Create New Lab Order</h3>
        <form method="POST" id="labOrderForm">
            <input type="hidden" name="create_lab_order" value="1">
            
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <label for="patient_id" class="block text-sm font-medium text-gray-700">Patient *</label>
                    <select name="patient_id" id="patient_id" required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Select Patient</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>">
                                <?php echo $patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['hospital_id'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="priority" class="block text-sm font-medium text-gray-700">Priority *</label>
                    <select name="priority" id="priority" required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        <option value="routine">Routine</option>
                        <option value="urgent">Urgent</option>
                        <option value="stat">STAT (Immediate)</option>
                    </select>
                </div>
            </div>
            
            <div class="mt-6">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="text-sm font-medium text-gray-900">Lab Tests</h4>
                    <button type="button" onclick="addLabTest()" 
                        class="text-sm text-primary-600 hover:text-primary-700">
                        + Add Test
                    </button>
                </div>
                <div id="labTestsContainer" class="space-y-4">
                    <!-- Lab tests will be added here dynamically -->
                </div>
            </div>
            
            <div class="mt-6 flex justify-end">
                <button type="submit"
                    class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700">
                    Create Lab Order
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Lab Orders List -->
<div class="bg-white shadow rounded-lg">
    <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">
            <?php echo $role_name == 'lab_technician' ? 'Pending Lab Orders' : 'Lab Orders'; ?>
        </h3>
        
        <?php if (count($lab_orders) > 0): ?>
            <div class="space-y-4">
                <?php foreach ($lab_orders as $order): ?>
                    <div class="border rounded-lg p-4">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    Lab Order #<?php echo $order['order_number']; ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    Patient: <?php echo $order['first_name'] . ' ' . $order['last_name']; ?> 
                                    (<?php echo $order['hospital_id']; ?>)
                                </p>
                                <p class="text-xs text-gray-500">
                                    Doctor: Dr. <?php echo $order['doctor_fname'] . ' ' . $order['doctor_lname']; ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    Date: <?php echo formatDate($order['order_date']); ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    <?php echo $order['priority'] == 'stat' ? 'bg-red-100 text-red-800' : ''; ?>
                                    <?php echo $order['priority'] == 'urgent' ? 'bg-orange-100 text-orange-800' : ''; ?>
                                    <?php echo $order['priority'] == 'routine' ? 'bg-blue-100 text-blue-800' : ''; ?>">
                                    <?php echo strtoupper($order['priority']); ?>
                                </span>
                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    <?php echo $order['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : ''; ?>
                                    <?php echo $order['status'] == 'in_progress' ? 'bg-blue-100 text-blue-800' : ''; ?>
                                    <?php echo $order['status'] == 'completed' ? 'bg-green-100 text-green-800' : ''; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h5 class="text-sm font-medium text-gray-700 mb-2">Lab Tests:</h5>
                            <?php 
                            $lab_tests = json_decode($order['lab_tests'], true);
                            if (is_array($lab_tests)): 
                            ?>
                                <ul class="list-disc list-inside space-y-1 text-sm text-gray-600">
                                    <?php foreach ($lab_tests as $test): ?>
                                        <li>
                                            <strong><?php echo $test['test_name']; ?></strong>
                                            <?php if (!empty($test['test_code'])): ?>
                                                (<?php echo $test['test_code']; ?>)
                                            <?php endif; ?>
                                            <?php if (!empty($test['specimen_type'])): ?>
                                                - Specimen: <?php echo $test['specimen_type']; ?>
                                            <?php endif; ?>
                                            <?php if (!empty($test['notes'])): ?>
                                                <br><span class="text-xs text-gray-500"><?php echo $test['notes']; ?></span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            
                            <?php if (!empty($order['results'])): ?>
                                <div class="mt-3 p-3 bg-gray-50 rounded">
                                    <p class="text-xs font-medium text-gray-700 mb-1">Results:</p>
                                    <p class="text-sm text-gray-600"><?php echo nl2br($order['results']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($role_name == 'lab_technician' && $order['status'] == 'pending'): ?>
                            <div class="mt-4 pt-4 border-t">
                                <form method="POST" class="space-y-3">
                                    <input type="hidden" name="process_lab_order" value="1">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    
                                    <div>
                                        <label for="status_<?php echo $order['id']; ?>" class="block text-sm font-medium text-gray-700">Status</label>
                                        <select name="status" id="status_<?php echo $order['id']; ?>" required
                                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                                            <option value="in_progress">In Progress</option>
                                            <option value="completed">Completed</option>
                                            <option value="cancelled">Cancel</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label for="results_<?php echo $order['id']; ?>" class="block text-sm font-medium text-gray-700">Results</label>
                                        <textarea name="results" id="results_<?php echo $order['id']; ?>" rows="4"
                                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                                            placeholder="Enter lab test results..."></textarea>
                                    </div>
                                    
                                    <button type="submit"
                                        class="w-full px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700">
                                        Update Order
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-8">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 mx-auto mb-2"><path d="M10 2v2"></path><path d="M14 2v2"></path><path d="M10.5 2h3"></path><path d="M7 12a5 5 0 0 0 10 0Z"></path><path d="M7 12H4a2 2 0 0 0-2 2v1a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-1a2 2 0 0 0-2-2h-3"></path></svg>
                <p class="text-gray-500">No lab orders found</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($role_name == 'doctor' || $role_name == 'admin'): ?>
<script>
let labTestCount = 0;

function addLabTest() {
    labTestCount++;
    const container = document.getElementById('labTestsContainer');
    const div = document.createElement('div');
    div.className = 'border rounded-lg p-4 bg-gray-50';
    div.innerHTML = `
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-medium text-gray-700">Test Name *</label>
                <input type="text" name="lab_tests[${labTestCount}][test_name]" required
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm"
                    placeholder="e.g., Complete Blood Count">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700">Test Code</label>
                <input type="text" name="lab_tests[${labTestCount}][test_code]"
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm"
                    placeholder="e.g., CBC">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700">Specimen Type</label>
                <input type="text" name="lab_tests[${labTestCount}][specimen_type]"
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm"
                    placeholder="e.g., Blood, Urine">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700">Notes</label>
                <input type="text" name="lab_tests[${labTestCount}][notes]"
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm"
                    placeholder="Special instructions">
            </div>
        </div>
        <button type="button" onclick="this.parentElement.remove()" 
            class="mt-2 text-xs text-red-600 hover:text-red-700">
            Remove
        </button>
    `;
    container.appendChild(div);
}

// Add first lab test field on load
document.addEventListener('DOMContentLoaded', function() {
    addLabTest();
});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>



