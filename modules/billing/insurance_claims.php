<?php
/**
 * Insurance Claims Processing
 * Finance Staff - Process insurance claims, HMO LOA, reimbursements
 */
require_once '../../config/config.php';
requireAuth();
checkRole(['finance staff', 'admin']);
requirePermission('insurance.process_claims');

$page_title = "Insurance Claims Processing";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = sanitizeInput($_POST['action'] ?? '');
        $user_id = $_SESSION['user_id'];
        
        if ($action === 'create_claim') {
            $billing_id = intval($_POST['billing_id']);
            $insurance_provider_id = intval($_POST['insurance_provider_id']);
            $claim_type = sanitizeInput($_POST['claim_type']);
            
            // Get billing information
            $billing_query = "SELECT * FROM billing WHERE id = :billing_id";
            $billing_stmt = $db->prepare($billing_query);
            $billing_stmt->bindParam(':billing_id', $billing_id);
            $billing_stmt->execute();
            $billing = $billing_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$billing) {
                throw new Exception("Billing record not found");
            }
            
            // Generate claim number
            $claim_number = 'CLM-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $query = "INSERT INTO insurance_claims 
                     (claim_number, billing_id, insurance_provider_id, patient_id,
                      claim_type, claim_amount, status, processed_by, submitted_at)
                     VALUES 
                     (:claim_number, :billing_id, :insurance_provider_id, :patient_id,
                      :claim_type, :claim_amount, :status, :processed_by, NOW())";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':claim_number', $claim_number);
            $stmt->bindParam(':billing_id', $billing_id);
            $stmt->bindParam(':insurance_provider_id', $insurance_provider_id);
            $stmt->bindParam(':patient_id', $billing['patient_id']);
            $stmt->bindParam(':claim_type', $claim_type);
            $stmt->bindParam(':claim_amount', $billing['total_amount']);
            $stmt->bindValue(':status', 'submitted');
            $stmt->bindParam(':processed_by', $user_id);
            
            if ($stmt->execute()) {
                $claim_id = $db->lastInsertId();
                logAction('insurance_claim_created', 'insurance_claims', $claim_id, null, [
                    'claim_number' => $claim_number,
                    'claim_type' => $claim_type
                ]);
                $_SESSION['success'] = "Insurance claim created successfully! Claim #: " . $claim_number;
                header("Location: insurance_claims.php");
                exit;
            }
        } elseif ($action === 'update_status') {
            $claim_id = intval($_POST['claim_id']);
            $status = sanitizeInput($_POST['status']);
            $approved_amount = !empty($_POST['approved_amount']) ? floatval($_POST['approved_amount']) : null;
            $rejection_reason = sanitizeInput($_POST['rejection_reason'] ?? '');
            
            // Get old values
            $old_query = "SELECT * FROM insurance_claims WHERE id = :id";
            $old_stmt = $db->prepare($old_query);
            $old_stmt->bindParam(':id', $claim_id);
            $old_stmt->execute();
            $old_data = $old_stmt->fetch(PDO::FETCH_ASSOC);
            
            $query = "UPDATE insurance_claims 
                     SET status = :status, approved_amount = :approved_amount,
                         rejection_reason = :rejection_reason, processed_by = :processed_by";
            
            if ($status === 'approved') {
                $query .= ", approved_at = NOW()";
            }
            
            $query .= " WHERE id = :claim_id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':approved_amount', $approved_amount);
            $stmt->bindParam(':rejection_reason', $rejection_reason);
            $stmt->bindParam(':processed_by', $user_id);
            $stmt->bindParam(':claim_id', $claim_id);
            
            if ($stmt->execute()) {
                logAction('insurance_claim_updated', 'insurance_claims', $claim_id, $old_data, [
                    'status' => $status,
                    'approved_amount' => $approved_amount
                ]);
                $_SESSION['success'] = "Claim status updated successfully!";
                header("Location: insurance_claims.php");
                exit;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$provider_filter = $_GET['provider'] ?? 'all';

// Get insurance claims
try {
    $query = "SELECT ic.*, 
              b.bill_number, b.total_amount as billing_total,
              p.first_name, p.last_name, p.hospital_id,
              ip.provider_name, ip.provider_type,
              u.first_name as processed_by_name, u.last_name as processed_by_last
              FROM insurance_claims ic
              LEFT JOIN billing b ON ic.billing_id = b.id
              LEFT JOIN patients p ON ic.patient_id = p.id
              LEFT JOIN insurance_providers ip ON ic.insurance_provider_id = ip.id
              LEFT JOIN users u ON ic.processed_by = u.id
              WHERE 1=1";
    
    $params = [];
    
    if ($status_filter !== 'all') {
        $query .= " AND ic.status = :status";
        $params[':status'] = $status_filter;
    }
    
    if ($provider_filter !== 'all') {
        $query .= " AND ic.insurance_provider_id = :provider";
        $params[':provider'] = $provider_filter;
    }
    
    $query .= " ORDER BY ic.created_at DESC LIMIT 100";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $claims = [];
    $_SESSION['error'] = "Error loading claims: " . $e->getMessage();
}

// Get insurance providers
try {
    $providers_query = "SELECT * FROM insurance_providers WHERE status = 'active' ORDER BY provider_name";
    $providers_stmt = $db->prepare($providers_query);
    $providers_stmt->execute();
    $providers = $providers_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $providers = [];
}

// Get pending bills for new claims
try {
    $bills_query = "SELECT b.*, p.first_name, p.last_name, p.hospital_id
                   FROM billing b
                   LEFT JOIN patients p ON b.patient_id = p.id
                   WHERE b.payment_status != 'paid'
                   AND NOT EXISTS (
                       SELECT 1 FROM insurance_claims ic WHERE ic.billing_id = b.id
                   )
                   ORDER BY b.created_at DESC
                   LIMIT 50";
    $bills_stmt = $db->prepare($bills_query);
    $bills_stmt->execute();
    $pending_bills = $bills_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pending_bills = [];
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Insurance Claims Processing</h1>
            <p class="text-gray-600">Process HMO LOA, reimbursements, and direct billing</p>
        </div>
        <button type="button" onclick="openCreateModal()" class="btn btn-primary">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Create New Claim
        </button>
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

<!-- Filters -->
<div class="bg-white shadow rounded-lg p-4 mb-6">
    <form method="GET" class="flex gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Status</label>
            <select name="status" class="mt-1 block border border-gray-300 rounded-md shadow-sm py-2 px-3">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="submitted" <?php echo $status_filter === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Provider</label>
            <select name="provider" class="mt-1 block border border-gray-300 rounded-md shadow-sm py-2 px-3">
                <option value="all" <?php echo $provider_filter === 'all' ? 'selected' : ''; ?>>All Providers</option>
                <?php foreach ($providers as $provider): ?>
                    <option value="<?php echo $provider['id']; ?>" <?php echo $provider_filter == $provider['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($provider['provider_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="btn btn-secondary">Filter</button>
        </div>
    </form>
</div>

<!-- Claims Table -->
<div class="bg-white shadow rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Claim #</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patient</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Provider</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($claims)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">No claims found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($claims as $claim): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($claim['claim_number']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']); ?><br>
                                <span class="text-xs"><?php echo htmlspecialchars($claim['hospital_id']); ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($claim['provider_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo str_replace('_', ' ', $claim['claim_type']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                ₱<?php echo number_format($claim['claim_amount'], 2); ?>
                                <?php if ($claim['approved_amount']): ?>
                                    <br><span class="text-xs text-green-600">Approved: ₱<?php echo number_format($claim['approved_amount'], 2); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php 
                                    $status_colors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'submitted' => 'bg-blue-100 text-blue-800',
                                        'approved' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                        'paid' => 'bg-gray-100 text-gray-800'
                                    ];
                                    echo $status_colors[$claim['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>">
                                    <?php echo ucfirst($claim['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="openStatusModal(<?php echo htmlspecialchars(json_encode($claim)); ?>)" 
                                        class="text-indigo-600 hover:text-indigo-900">Update Status</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Claim Modal -->
<div id="createModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4">
    <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-5">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Create Insurance Claim</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_claim">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Bill *</label>
                        <select name="billing_id" id="billingId" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                            <option value="">Select Bill</option>
                            <?php foreach ($pending_bills as $bill): ?>
                                <option value="<?php echo $bill['id']; ?>" 
                                        data-amount="<?php echo $bill['total_amount']; ?>"
                                        data-patient="<?php echo htmlspecialchars($bill['first_name'] . ' ' . $bill['last_name']); ?>">
                                    <?php echo $bill['bill_number']; ?> - 
                                    <?php echo htmlspecialchars($bill['first_name'] . ' ' . $bill['last_name']); ?> - 
                                    ₱<?php echo number_format($bill['total_amount'], 2); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Insurance Provider *</label>
                        <select name="insurance_provider_id" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                            <option value="">Select Provider</option>
                            <?php foreach ($providers as $provider): ?>
                                <option value="<?php echo $provider['id']; ?>">
                                    <?php echo htmlspecialchars($provider['provider_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Claim Type *</label>
                        <select name="claim_type" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                            <option value="">Select Type</option>
                            <option value="HMO_LOA">HMO Letter of Authorization</option>
                            <option value="Reimbursement">Reimbursement</option>
                            <option value="Direct_Billing">Direct Billing</option>
                        </select>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeCreateModal()" 
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700">
                        Create Claim
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div id="statusModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4">
    <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-5">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Update Claim Status</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="claim_id" id="claimId">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status *</label>
                        <select name="status" id="claimStatus" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
                                onchange="toggleApprovedAmount()">
                            <option value="pending">Pending</option>
                            <option value="submitted">Submitted</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                    
                    <div id="approvedAmountDiv" class="hidden">
                        <label class="block text-sm font-medium text-gray-700">Approved Amount</label>
                        <input type="number" name="approved_amount" id="approvedAmount" step="0.01" min="0"
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                    </div>
                    
                    <div id="rejectionReasonDiv" class="hidden">
                        <label class="block text-sm font-medium text-gray-700">Rejection Reason</label>
                        <textarea name="rejection_reason" id="rejectionReason" rows="3"
                                  class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"></textarea>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeStatusModal()" 
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700">
                        Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('createModal').classList.remove('hidden');
}

function closeCreateModal() {
    document.getElementById('createModal').classList.add('hidden');
}

function openStatusModal(claim) {
    document.getElementById('claimId').value = claim.id;
    document.getElementById('claimStatus').value = claim.status;
    document.getElementById('approvedAmount').value = claim.approved_amount || '';
    document.getElementById('rejectionReason').value = claim.rejection_reason || '';
    toggleApprovedAmount();
    document.getElementById('statusModal').classList.remove('hidden');
}

function closeStatusModal() {
    document.getElementById('statusModal').classList.add('hidden');
}

function toggleApprovedAmount() {
    const status = document.getElementById('claimStatus').value;
    const approvedDiv = document.getElementById('approvedAmountDiv');
    const rejectionDiv = document.getElementById('rejectionReasonDiv');
    
    if (status === 'approved') {
        approvedDiv.classList.remove('hidden');
        rejectionDiv.classList.add('hidden');
    } else if (status === 'rejected') {
        approvedDiv.classList.add('hidden');
        rejectionDiv.classList.remove('hidden');
    } else {
        approvedDiv.classList.add('hidden');
        rejectionDiv.classList.add('hidden');
    }
}
</script>

<?php include '../../includes/footer.php'; ?>

