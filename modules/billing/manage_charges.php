<?php
/**
 * Billing Charges Management
 * Finance Staff - Add and manage billing charges
 */
require_once '../../config/config.php';
requireAuth();
checkRole(['finance staff', 'admin']);
requirePermission('charges.add');

$page_title = "Manage Billing Charges";

$billing_id = isset($_GET['billing_id']) ? intval($_GET['billing_id']) : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = sanitizeInput($_POST['action'] ?? '');
        $user_id = $_SESSION['user_id'];
        
        if ($action === 'add_charge') {
            $billing_id = intval($_POST['billing_id']);
            $charge_type = sanitizeInput($_POST['charge_type']);
            $charge_description = sanitizeInput($_POST['charge_description']);
            $quantity = floatval($_POST['quantity'] ?? 1);
            $unit_price = floatval($_POST['unit_price']);
            $discount_amount = floatval($_POST['discount_amount'] ?? 0);
            
            $total_amount = $quantity * $unit_price;
            $final_amount = $total_amount - $discount_amount;
            
            $query = "INSERT INTO billing_charges 
                     (billing_id, charge_type, charge_description, quantity,
                      unit_price, total_amount, discount_amount, final_amount, created_by)
                     VALUES 
                     (:billing_id, :charge_type, :charge_description, :quantity,
                      :unit_price, :total_amount, :discount_amount, :final_amount, :created_by)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':billing_id', $billing_id);
            $stmt->bindParam(':charge_type', $charge_type);
            $stmt->bindParam(':charge_description', $charge_description);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':unit_price', $unit_price);
            $stmt->bindParam(':total_amount', $total_amount);
            $stmt->bindParam(':discount_amount', $discount_amount);
            $stmt->bindParam(':final_amount', $final_amount);
            $stmt->bindParam(':created_by', $user_id);
            
            if ($stmt->execute()) {
                $charge_id = $db->lastInsertId();
                
                // Update billing total
                $update_billing = "UPDATE billing SET 
                                  total_amount = (SELECT SUM(final_amount) FROM billing_charges WHERE billing_id = :billing_id),
                                  balance_amount = total_amount - paid_amount
                                  WHERE id = :billing_id";
                $update_stmt = $db->prepare($update_billing);
                $update_stmt->bindParam(':billing_id', $billing_id);
                $update_stmt->execute();
                
                logAction('billing_charge_added', 'billing', $charge_id, null, [
                    'billing_id' => $billing_id,
                    'charge_type' => $charge_type,
                    'final_amount' => $final_amount
                ]);
                
                $_SESSION['success'] = "Charge added successfully!";
                header("Location: manage_charges.php?billing_id=" . $billing_id);
                exit;
            }
        } elseif ($action === 'delete_charge') {
            $charge_id = intval($_POST['charge_id']);
            $billing_id = intval($_POST['billing_id']);
            
            // Get charge for audit
            $old_query = "SELECT * FROM billing_charges WHERE id = :id";
            $old_stmt = $db->prepare($old_query);
            $old_stmt->bindParam(':id', $charge_id);
            $old_stmt->execute();
            $old_data = $old_stmt->fetch(PDO::FETCH_ASSOC);
            
            $delete_query = "DELETE FROM billing_charges WHERE id = :charge_id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':charge_id', $charge_id);
            
            if ($delete_stmt->execute()) {
                // Update billing total
                $update_billing = "UPDATE billing SET 
                                  total_amount = COALESCE((SELECT SUM(final_amount) FROM billing_charges WHERE billing_id = :billing_id), 0),
                                  balance_amount = total_amount - paid_amount
                                  WHERE id = :billing_id";
                $update_stmt = $db->prepare($update_billing);
                $update_stmt->bindParam(':billing_id', $billing_id);
                $update_stmt->execute();
                
                logAction('billing_charge_deleted', 'billing', $charge_id, $old_data, null);
                
                $_SESSION['success'] = "Charge deleted successfully!";
                header("Location: manage_charges.php?billing_id=" . $billing_id);
                exit;
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Get billing information
$billing = null;
if ($billing_id) {
    try {
        $query = "SELECT b.*, p.first_name, p.last_name, p.hospital_id
                  FROM billing b
                  LEFT JOIN patients p ON b.patient_id = p.id
                  WHERE b.id = :billing_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':billing_id', $billing_id);
        $stmt->execute();
        $billing = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error loading billing: " . $e->getMessage();
    }
}

// Get charges for this billing
$charges = [];
if ($billing_id) {
    try {
        $query = "SELECT bc.*, u.first_name as created_by_name, u.last_name as created_by_last
                  FROM billing_charges bc
                  LEFT JOIN users u ON bc.created_by = u.id
                  WHERE bc.billing_id = :billing_id
                  ORDER BY bc.created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':billing_id', $billing_id);
        $stmt->execute();
        $charges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table might not exist yet
    }
}

// Get services catalog
try {
    $services_query = "SELECT * FROM services_catalog WHERE is_active = 1 ORDER BY service_name";
    $services_stmt = $db->prepare($services_query);
    $services_stmt->execute();
    $services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $services = [];
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Manage Billing Charges</h1>
            <?php if ($billing): ?>
                <p class="text-gray-600">Bill #<?php echo htmlspecialchars($billing['bill_number']); ?> - 
                <?php echo htmlspecialchars($billing['first_name'] . ' ' . $billing['last_name']); ?></p>
            <?php endif; ?>
        </div>
        <?php if ($billing_id): ?>
            <button type="button" onclick="openAddChargeModal()" class="btn btn-primary">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add Charge
            </button>
        <?php endif; ?>
    </div>
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

<?php if (!$billing_id): ?>
    <div class="bg-white shadow rounded-lg p-6">
        <p class="text-gray-600 mb-4">Please select a billing record to manage charges.</p>
        <a href="payments.php" class="btn btn-primary">View Billing Records</a>
    </div>
<?php elseif (!$billing): ?>
    <div class="bg-white shadow rounded-lg p-6">
        <p class="text-red-600">Billing record not found.</p>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Charges List -->
        <div class="lg:col-span-2">
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Charges</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($charges)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">No charges added yet</td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $total_charges = 0;
                                foreach ($charges as $charge): 
                                    $total_charges += $charge['final_amount'];
                                ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo ucfirst(str_replace('_', ' ', $charge['charge_type'])); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <?php echo htmlspecialchars($charge['charge_description']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo number_format($charge['quantity'], 2); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            ₱<?php echo number_format($charge['unit_price'], 2); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            ₱<?php echo number_format($charge['final_amount'], 2); ?>
                                            <?php if ($charge['discount_amount'] > 0): ?>
                                                <br><span class="text-xs text-green-600">Discount: ₱<?php echo number_format($charge['discount_amount'], 2); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <form method="POST" class="inline delete-charge-form" data-charge-id="<?php echo $charge['id']; ?>" data-billing-id="<?php echo $billing_id; ?>">
                                                <input type="hidden" name="action" value="delete_charge">
                                                <input type="hidden" name="charge_id" value="<?php echo $charge['id']; ?>">
                                                <input type="hidden" name="billing_id" value="<?php echo $billing_id; ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="bg-gray-50 font-semibold">
                                    <td colspan="4" class="px-6 py-4 text-right text-sm text-gray-700">Total Charges:</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        ₱<?php echo number_format($total_charges, 2); ?>
                                    </td>
                                    <td></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Billing Summary -->
        <div>
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Billing Summary</h2>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total Charges:</span>
                        <span class="font-medium">₱<?php echo number_format($billing['total_amount'], 2); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Paid Amount:</span>
                        <span class="font-medium text-green-600">₱<?php echo number_format($billing['paid_amount'], 2); ?></span>
                    </div>
                    <div class="flex justify-between border-t pt-3">
                        <span class="text-gray-900 font-semibold">Balance:</span>
                        <span class="font-bold text-red-600">₱<?php echo number_format($billing['balance_amount'], 2); ?></span>
                    </div>
                    <div class="mt-4 pt-4 border-t">
                        <span class="text-sm text-gray-500">Status: </span>
                        <span class="text-sm font-medium 
                            <?php echo $billing['payment_status'] === 'paid' ? 'text-green-600' : 
                                       ($billing['payment_status'] === 'partial' ? 'text-yellow-600' : 'text-red-600'); ?>">
                            <?php echo ucfirst($billing['payment_status']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Add Charge Modal -->
<div id="addChargeModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4">
    <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-5">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Add Charge</h3>
            <form method="POST" id="chargeForm">
                <input type="hidden" name="action" value="add_charge">
                <input type="hidden" name="billing_id" value="<?php echo $billing_id; ?>">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Charge Type *</label>
                        <select name="charge_type" id="chargeType" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                            <option value="">Select Type</option>
                            <option value="room">Room</option>
                            <option value="lab">Laboratory</option>
                            <option value="doctor_fee">Doctor Fee</option>
                            <option value="procedure">Procedure</option>
                            <option value="medication">Medication</option>
                            <option value="service">Service</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Description *</label>
                        <input type="text" name="charge_description" id="chargeDescription" required
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Quantity</label>
                            <input type="number" name="quantity" id="quantity" step="0.01" min="0.01" value="1"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
                                   onchange="calculateTotal()">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Unit Price *</label>
                            <input type="number" name="unit_price" id="unitPrice" step="0.01" min="0" required
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
                                   onchange="calculateTotal()">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Discount Amount</label>
                        <input type="number" name="discount_amount" id="discountAmount" step="0.01" min="0" value="0"
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
                               onchange="calculateTotal()">
                    </div>
                    
                    <div class="bg-gray-50 p-3 rounded">
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-700">Total Amount:</span>
                            <span class="text-sm font-bold" id="totalAmount">₱0.00</span>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeAddChargeModal()" 
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700">
                        Add Charge
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddChargeModal() {
    document.getElementById('addChargeModal').classList.remove('hidden');
    document.getElementById('chargeForm').reset();
    document.getElementById('quantity').value = 1;
    document.getElementById('discountAmount').value = 0;
    calculateTotal();
}

function closeAddChargeModal() {
    document.getElementById('addChargeModal').classList.add('hidden');
}

function calculateTotal() {
    const quantity = parseFloat(document.getElementById('quantity').value) || 0;
    const unitPrice = parseFloat(document.getElementById('unitPrice').value) || 0;
    const discount = parseFloat(document.getElementById('discountAmount').value) || 0;
    
    const total = (quantity * unitPrice) - discount;
    document.getElementById('totalAmount').textContent = '₱' + total.toFixed(2);
}

// Handle delete charge confirmations
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.delete-charge-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            showConfirmAlert('Delete Charge', 'Are you sure you want to delete this charge?')
                .then((confirmed) => {
                    if (confirmed) {
                        this.submit();
                    }
                });
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>

