<?php
require_once '../../config/config.php';
require_once 'helpers.php';
requireAuth();
checkRole(['admin', 'receptionist', 'billing_staff']);

$page_title = "Insurance Setup";
$active_tab = $_GET['tab'] ?? 'providers';

// Handle Add Provider
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_provider') {
    $provider_name = sanitizeInput($_POST['provider_name'] ?? '');
    $provider_type = sanitizeInput($_POST['provider_type'] ?? '');
    $provider_code = sanitizeInput($_POST['provider_code'] ?? '');
    $contact_person = sanitizeInput($_POST['contact_person'] ?? '');
    $contact_number = sanitizeInput($_POST['contact_number'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    
    if (empty($provider_name) || empty($provider_type)) {
        $_SESSION['error'] = "Provider name and type are required.";
    } else {
        try {
            $query = "INSERT INTO insurance_providers (provider_name, provider_type, provider_code, contact_person, contact_number, email, address) 
                     VALUES (:provider_name, :provider_type, :provider_code, :contact_person, :contact_number, :email, :address)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':provider_name', $provider_name);
            $stmt->bindParam(':provider_type', $provider_type);
            $stmt->bindParam(':provider_code', $provider_code);
            $stmt->bindParam(':contact_person', $contact_person);
            $stmt->bindParam(':contact_number', $contact_number);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':address', $address);
            $stmt->execute();
            
            $_SESSION['success'] = "Insurance provider added successfully.";
            header("Location: insurance_setup.php?tab=providers");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error adding provider: " . $e->getMessage();
        }
    }
}

// Handle Update Policy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_policy') {
    $policy_id = (int)$_POST['policy_id'];
    $coverage_type = sanitizeInput($_POST['coverage_type'] ?? '');
    $coverage_percentage = sanitizeInput($_POST['coverage_percentage'] ?? 0);
    $max_coverage_amount = sanitizeInput($_POST['max_coverage_amount'] ?? null);
    
    try {
        $query = "UPDATE insurance_policies SET coverage_type = :coverage_type, coverage_percentage = :coverage_percentage, 
                 max_coverage_amount = :max_coverage_amount WHERE id = :policy_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':policy_id', $policy_id, PDO::PARAM_INT);
        $stmt->bindParam(':coverage_type', $coverage_type);
        $stmt->bindParam(':coverage_percentage', $coverage_percentage);
        $stmt->bindParam(':max_coverage_amount', $max_coverage_amount);
        $stmt->execute();
        
        $_SESSION['success'] = "Policy updated successfully.";
        header("Location: insurance_setup.php?tab=policies");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating policy: " . $e->getMessage();
    }
}

// Handle Add Coverage Rule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_coverage_rule') {
    $policy_id = (int)$_POST['policy_id'];
    $service_type = sanitizeInput($_POST['service_type'] ?? '');
    $coverage_percentage = sanitizeInput($_POST['coverage_percentage'] ?? 0);
    $max_amount = sanitizeInput($_POST['max_amount'] ?? null);
    $requires_pre_approval = isset($_POST['requires_pre_approval']) ? 1 : 0;
    
    try {
        $query = "INSERT INTO coverage_rules (policy_id, service_type, coverage_percentage, max_amount, requires_pre_approval) 
                 VALUES (:policy_id, :service_type, :coverage_percentage, :max_amount, :requires_pre_approval)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':policy_id', $policy_id, PDO::PARAM_INT);
        $stmt->bindParam(':service_type', $service_type);
        $stmt->bindParam(':coverage_percentage', $coverage_percentage);
        $stmt->bindParam(':max_amount', $max_amount);
        $stmt->bindParam(':requires_pre_approval', $requires_pre_approval, PDO::PARAM_INT);
        $stmt->execute();
        
        $_SESSION['success'] = "Coverage rule added successfully.";
        header("Location: insurance_setup.php?tab=rules");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error adding coverage rule: " . $e->getMessage();
    }
}

// Get providers
try {
    $providers_query = "SELECT * FROM insurance_providers ORDER BY provider_name";
    $providers_stmt = $db->prepare($providers_query);
    $providers_stmt->execute();
    $providers = $providers_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $providers = [];
}

// Get policies
try {
    $policies_query = "SELECT ip.*, prov.provider_name, prov.provider_type 
                       FROM insurance_policies ip
                       LEFT JOIN insurance_providers prov ON ip.provider_id = prov.id
                       ORDER BY ip.created_at DESC";
    $policies_stmt = $db->prepare($policies_query);
    $policies_stmt->execute();
    $policies = $policies_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $policies = [];
}

$edit_policy = null;
if ($active_tab === 'policies' && !empty($_GET['edit'])) {
    $edit_id = (int) $_GET['edit'];
    foreach ($policies as $p) {
        if ((int)$p['id'] === $edit_id) {
            $edit_policy = $p;
            break;
        }
    }
}

// Get coverage rules
try {
    $rules_query = "SELECT cr.*, ip.policy_name, prov.provider_name 
                   FROM coverage_rules cr
                   LEFT JOIN insurance_policies ip ON cr.policy_id = ip.id
                   LEFT JOIN insurance_providers prov ON ip.provider_id = prov.id
                   ORDER BY cr.created_at DESC";
    $rules_stmt = $db->prepare($rules_query);
    $rules_stmt->execute();
    $coverage_rules = $rules_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $coverage_rules = [];
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Insurance Setup</h1>
    <p class="text-gray-600 dark:text-gray-400">Manage insurance providers, policies, and coverage rules</p>
</div>

<!-- Tabs -->
<div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="flex -mb-px">
            <a href="?tab=providers" class="<?php echo $active_tab === 'providers' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> py-4 px-6 border-b-2 font-medium text-sm">
                Insurance Providers
            </a>
            <a href="?tab=policies" class="<?php echo $active_tab === 'policies' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> py-4 px-6 border-b-2 font-medium text-sm">
                Insurance Policies
            </a>
            <a href="?tab=rules" class="<?php echo $active_tab === 'rules' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> py-4 px-6 border-b-2 font-medium text-sm">
                Coverage Rules
            </a>
        </nav>
    </div>
</div>

<?php if ($active_tab === 'providers'): ?>
    <!-- Insurance Providers Tab -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
        <div class="px-4 py-5 sm:p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Insurance Providers</h3>
                <button onclick="document.getElementById('addProviderModal').classList.remove('hidden')" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">
                    Add Provider
                </button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Provider Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php if (empty($providers)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">No providers found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($providers as $provider): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($provider['provider_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($provider['provider_type']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($provider['provider_code'] ?? '-'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($provider['contact_number'] ?? '-'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $provider['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo ucfirst($provider['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Add Provider Modal -->
    <div id="addProviderModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Add Insurance Provider</h3>
                <button onclick="document.getElementById('addProviderModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-500">×</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_provider">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Provider Name *</label>
                        <input type="text" name="provider_name" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Provider Type *</label>
                        <select name="provider_type" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Type</option>
                            <option value="PhilHealth">PhilHealth</option>
                            <option value="HMO">HMO</option>
                            <option value="Private">Private</option>
                            <option value="Government">Government</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Provider Code</label>
                        <input type="text" name="provider_code" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contact Number</label>
                        <input type="tel" name="contact_number" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input type="email" name="email" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="document.getElementById('addProviderModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">
                            Add Provider
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($active_tab === 'policies'): ?>
    <!-- Insurance Policies Tab -->
    <?php if ($edit_policy): ?>
    <!-- Edit Policy Modal -->
    <div id="editPolicyModal" class="fixed inset-0 z-50 overflow-y-auto" role="dialog">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/50" onclick="window.location.href='?tab=policies'"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Edit Policy: <?php echo htmlspecialchars($edit_policy['policy_name']); ?></h3>
                <form method="post" action="">
                    <input type="hidden" name="action" value="update_policy">
                    <input type="hidden" name="policy_id" value="<?php echo (int)$edit_policy['id']; ?>">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Coverage Type</label>
                            <input type="text" name="coverage_type" value="<?php echo htmlspecialchars($edit_policy['coverage_type'] ?? ''); ?>" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Coverage %</label>
                            <input type="number" name="coverage_percentage" min="0" max="100" value="<?php echo htmlspecialchars($edit_policy['coverage_percentage'] ?? '0'); ?>" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Max Coverage Amount (optional)</label>
                            <input type="number" name="max_coverage_amount" min="0" step="0.01" value="<?php echo htmlspecialchars($edit_policy['max_coverage_amount'] ?? ''); ?>" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 dark:bg-gray-700 dark:text-white" placeholder="Leave empty for no limit">
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <a href="?tab=policies" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-200">Cancel</a>
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">Update Policy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Insurance Policies</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Policy Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Provider</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Coverage Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Coverage %</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php if (empty($policies)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">No policies found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($policies as $policy): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($policy['policy_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($policy['provider_name'] ?? '-'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($policy['coverage_type'] ?? '-'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($policy['coverage_percentage'] ?? '0'); ?>%
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $policy['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo ucfirst($policy['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="?tab=policies&edit=<?php echo $policy['id']; ?>" class="text-primary-600 hover:text-primary-900">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($active_tab === 'rules'): ?>
    <!-- Coverage Rules Tab -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Coverage Rules</h3>
                <button onclick="document.getElementById('addRuleModal').classList.remove('hidden')" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">
                    Add Coverage Rule
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Service Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Policy</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Coverage %</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Max Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Pre-Approval</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php if (empty($coverage_rules)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">No coverage rules found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($coverage_rules as $rule): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($rule['service_type']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($rule['policy_name'] ?? '-'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($rule['coverage_percentage'] ?? '0'); ?>%
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo $rule['max_amount'] ? '₱' . number_format($rule['max_amount'], 2) : '-'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php echo $rule['requires_pre_approval'] ? 'Yes' : 'No'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Add Coverage Rule Modal -->
    <div id="addRuleModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Add Coverage Rule</h3>
                <button onclick="document.getElementById('addRuleModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-500">×</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_coverage_rule">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Policy *</label>
                        <select name="policy_id" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Policy</option>
                            <?php foreach ($policies as $policy): ?>
                                <option value="<?php echo $policy['id']; ?>"><?php echo htmlspecialchars($policy['policy_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Service Type *</label>
                        <input type="text" name="service_type" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Coverage Percentage</label>
                        <input type="number" name="coverage_percentage" min="0" max="100" step="0.01" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Max Amount</label>
                        <input type="number" name="max_amount" step="0.01" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="requires_pre_approval" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Requires Pre-Approval</span>
                        </label>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="document.getElementById('addRuleModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">
                            Add Rule
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
