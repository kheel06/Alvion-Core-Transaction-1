<?php
/**
 * Insurance Provider Management
 * Admin only - Manage insurance providers
 */
require_once '../../config/config.php';
requireAuth();
checkRole(['admin']);
requirePermission('insurance.create');

$page_title = "Insurance Provider Management";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = sanitizeInput($_POST['action'] ?? '');
        $user_id = $_SESSION['user_id'];
        
        if ($action === 'create') {
            $provider_name = sanitizeInput($_POST['provider_name']);
            $provider_type = sanitizeInput($_POST['provider_type']);
            $contact_person = sanitizeInput($_POST['contact_person'] ?? '');
            $contact_email = sanitizeInput($_POST['contact_email'] ?? '');
            $contact_phone = sanitizeInput($_POST['contact_phone'] ?? '');
            $address = sanitizeInput($_POST['address'] ?? '');
            $coverage_details = sanitizeInput($_POST['coverage_details'] ?? '');
            $reimbursement_rate = floatval($_POST['reimbursement_rate'] ?? 0);
            $status = sanitizeInput($_POST['status'] ?? 'pending_approval');
            
            $query = "INSERT INTO insurance_providers 
                     (provider_name, provider_type, contact_person, contact_email, 
                      contact_phone, address, coverage_details, reimbursement_rate, 
                      status, created_by, approved_by, approved_at)
                     VALUES 
                     (:provider_name, :provider_type, :contact_person, :contact_email,
                      :contact_phone, :address, :coverage_details, :reimbursement_rate,
                      :status, :created_by, :approved_by, :approved_at)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':provider_name', $provider_name);
            $stmt->bindParam(':provider_type', $provider_type);
            $stmt->bindParam(':contact_person', $contact_person);
            $stmt->bindParam(':contact_email', $contact_email);
            $stmt->bindParam(':contact_phone', $contact_phone);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':coverage_details', $coverage_details);
            $stmt->bindParam(':reimbursement_rate', $reimbursement_rate);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':created_by', $user_id);
            
            if ($status === 'active') {
                $stmt->bindParam(':approved_by', $user_id);
                $approved_at = date('Y-m-d H:i:s');
                $stmt->bindParam(':approved_at', $approved_at);
            } else {
                $stmt->bindValue(':approved_by', null);
                $stmt->bindValue(':approved_at', null);
            }
            
            if ($stmt->execute()) {
                $provider_id = $db->lastInsertId();
                logAction('insurance_provider_created', 'insurance', $provider_id, null, [
                    'provider_name' => $provider_name,
                    'provider_type' => $provider_type
                ]);
                $_SESSION['success'] = "Insurance provider created successfully!";
                header("Location: manage_providers.php");
                exit;
            }
        } elseif ($action === 'approve') {
            $provider_id = intval($_POST['provider_id']);
            
            $query = "UPDATE insurance_providers 
                     SET status = 'active', approved_by = :approved_by, approved_at = NOW()
                     WHERE id = :provider_id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':approved_by', $user_id);
            $stmt->bindParam(':provider_id', $provider_id);
            
            if ($stmt->execute()) {
                logAction('insurance_provider_approved', 'insurance', $provider_id, null, ['status' => 'active']);
                $_SESSION['success'] = "Insurance provider approved!";
                header("Location: manage_providers.php");
                exit;
            }
        } elseif ($action === 'update') {
            $provider_id = intval($_POST['provider_id']);
            $provider_name = sanitizeInput($_POST['provider_name']);
            $provider_type = sanitizeInput($_POST['provider_type']);
            $contact_person = sanitizeInput($_POST['contact_person'] ?? '');
            $contact_email = sanitizeInput($_POST['contact_email'] ?? '');
            $contact_phone = sanitizeInput($_POST['contact_phone'] ?? '');
            $address = sanitizeInput($_POST['address'] ?? '');
            $coverage_details = sanitizeInput($_POST['coverage_details'] ?? '');
            $reimbursement_rate = floatval($_POST['reimbursement_rate'] ?? 0);
            $status = sanitizeInput($_POST['status'] ?? 'active');
            
            // Get old values for audit
            $old_query = "SELECT * FROM insurance_providers WHERE id = :id";
            $old_stmt = $db->prepare($old_query);
            $old_stmt->bindParam(':id', $provider_id);
            $old_stmt->execute();
            $old_data = $old_stmt->fetch(PDO::FETCH_ASSOC);
            
            $query = "UPDATE insurance_providers 
                     SET provider_name = :provider_name, provider_type = :provider_type,
                         contact_person = :contact_person, contact_email = :contact_email,
                         contact_phone = :contact_phone, address = :address,
                         coverage_details = :coverage_details, reimbursement_rate = :reimbursement_rate,
                         status = :status
                     WHERE id = :provider_id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':provider_name', $provider_name);
            $stmt->bindParam(':provider_type', $provider_type);
            $stmt->bindParam(':contact_person', $contact_person);
            $stmt->bindParam(':contact_email', $contact_email);
            $stmt->bindParam(':contact_phone', $contact_phone);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':coverage_details', $coverage_details);
            $stmt->bindParam(':reimbursement_rate', $reimbursement_rate);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':provider_id', $provider_id);
            
            if ($stmt->execute()) {
                logAction('insurance_provider_updated', 'insurance', $provider_id, $old_data, [
                    'provider_name' => $provider_name,
                    'status' => $status
                ]);
                $_SESSION['success'] = "Insurance provider updated successfully!";
                header("Location: manage_providers.php");
                exit;
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Get all insurance providers
try {
    $query = "SELECT ip.*, 
              u1.first_name as created_by_name, u1.last_name as created_by_last,
              u2.first_name as approved_by_name, u2.last_name as approved_by_last
              FROM insurance_providers ip
              LEFT JOIN users u1 ON ip.created_by = u1.id
              LEFT JOIN users u2 ON ip.approved_by = u2.id
              ORDER BY ip.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $providers = [];
    $_SESSION['error'] = "Error loading providers: " . $e->getMessage();
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Insurance Provider Management</h1>
            <p class="text-gray-600">Manage insurance providers, HMO, and PhilHealth</p>
        </div>
        <button type="button" onclick="openCreateModal()" 
                class="btn btn-primary">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Add Provider
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

<div class="bg-white shadow rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provider</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reimbursement</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($providers)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No insurance providers found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($providers as $provider): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($provider['provider_name']); ?></div>
                                <?php if ($provider['contact_person']): ?>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($provider['contact_person']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $provider['provider_type'] === 'HMO' ? 'bg-blue-100 text-blue-800' : 
                                               ($provider['provider_type'] === 'PhilHealth' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'); ?>">
                                    <?php echo htmlspecialchars($provider['provider_type']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php if ($provider['contact_email']): ?>
                                    <div><?php echo htmlspecialchars($provider['contact_email']); ?></div>
                                <?php endif; ?>
                                <?php if ($provider['contact_phone']): ?>
                                    <div><?php echo htmlspecialchars($provider['contact_phone']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo number_format($provider['reimbursement_rate'], 2); ?>%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $provider['status'] === 'active' ? 'bg-green-100 text-green-800' : 
                                               ($provider['status'] === 'pending_approval' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $provider['status'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($provider)); ?>)" 
                                        class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                <?php if ($provider['status'] === 'pending_approval'): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="provider_id" value="<?php echo $provider['id']; ?>">
                                        <button type="submit" class="text-green-600 hover:text-green-900">Approve</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="providerModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4">
    <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-5">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4" id="modalTitle">Add Insurance Provider</h3>
            <form method="POST" id="providerForm">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="provider_id" id="providerId">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Provider Name *</label>
                        <input type="text" name="provider_name" id="providerName" required
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Provider Type *</label>
                        <select name="provider_type" id="providerType" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                            <option value="">Select Type</option>
                            <option value="HMO">HMO</option>
                            <option value="Insurance">Insurance</option>
                            <option value="PhilHealth">PhilHealth</option>
                            <option value="Government">Government</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Contact Person</label>
                        <input type="text" name="contact_person" id="contactPerson"
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Contact Email</label>
                        <input type="email" name="contact_email" id="contactEmail"
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Contact Phone</label>
                        <input type="text" name="contact_phone" id="contactPhone"
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Address</label>
                        <textarea name="address" id="address" rows="2"
                                  class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Reimbursement Rate (%)</label>
                        <input type="number" name="reimbursement_rate" id="reimbursementRate" step="0.01" min="0" max="100"
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" id="status"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                            <option value="pending_approval">Pending Approval</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal()" 
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Add Insurance Provider';
    document.getElementById('formAction').value = 'create';
    document.getElementById('providerForm').reset();
    document.getElementById('providerId').value = '';
    document.getElementById('providerModal').classList.remove('hidden');
}

function openEditModal(provider) {
    document.getElementById('modalTitle').textContent = 'Edit Insurance Provider';
    document.getElementById('formAction').value = 'update';
    document.getElementById('providerId').value = provider.id;
    document.getElementById('providerName').value = provider.provider_name;
    document.getElementById('providerType').value = provider.provider_type;
    document.getElementById('contactPerson').value = provider.contact_person || '';
    document.getElementById('contactEmail').value = provider.contact_email || '';
    document.getElementById('contactPhone').value = provider.contact_phone || '';
    document.getElementById('address').value = provider.address || '';
    document.getElementById('reimbursementRate').value = provider.reimbursement_rate || 0;
    document.getElementById('status').value = provider.status;
    document.getElementById('providerModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('providerModal').classList.add('hidden');
}
</script>

<?php include '../../includes/footer.php'; ?>

