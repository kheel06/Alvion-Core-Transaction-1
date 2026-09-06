<?php
require_once '../../config/config.php';
require_once 'helpers.php';
requireAuth();
checkRole(['admin', 'receptionist', 'doctor', 'nurse']);

$page_title = "Consent Forms";
$active_tab = $_GET['tab'] ?? 'templates';

// Handle Upload Template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_template') {
    $template_name = sanitizeInput($_POST['template_name'] ?? '');
    $template_content = $_POST['template_content'] ?? '';
    $version = sanitizeInput($_POST['version'] ?? '1.0');
    
    if (empty($template_name) || empty($template_content)) {
        $_SESSION['error'] = "Template name and content are required.";
    } else {
        try {
            $query = "INSERT INTO consent_form_templates (template_name, form_type, template_content, version, created_by) 
                     VALUES (:template_name, 'general', :template_content, :version, :created_by)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':template_name', $template_name);
            $stmt->bindParam(':template_content', $template_content);
            $stmt->bindParam(':version', $version);
            $stmt->bindParam(':created_by', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            
            $_SESSION['success'] = "Consent form template uploaded successfully.";
            header("Location: consent_forms.php?tab=templates");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error uploading template: " . $e->getMessage();
        }
    }
}

// Handle Update Template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_template') {
    $template_id = (int)$_POST['template_id'];
    $template_name = sanitizeInput($_POST['template_name'] ?? '');
    $template_content = $_POST['template_content'] ?? '';
    $version = sanitizeInput($_POST['version'] ?? '1.0');
    
    try {
        $query = "UPDATE consent_form_templates SET template_name = :template_name, template_content = :template_content, 
                 version = :version, updated_at = NOW() WHERE id = :template_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':template_id', $template_id, PDO::PARAM_INT);
        $stmt->bindParam(':template_name', $template_name);
        $stmt->bindParam(':template_content', $template_content);
        $stmt->bindParam(':version', $version);
        $stmt->execute();
        
        $_SESSION['success'] = "Template updated successfully.";
        header("Location: consent_forms.php?tab=templates");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating template: " . $e->getMessage();
    }
}

// Get templates
try {
    $templates_query = "SELECT cft.*, u.first_name, u.last_name 
                       FROM consent_form_templates cft
                       LEFT JOIN users u ON cft.created_by = u.id
                       ORDER BY cft.created_at DESC";
    $templates_stmt = $db->prepare($templates_query);
    $templates_stmt->execute();
    $templates = $templates_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $templates = [];
}

// Get signed forms (schema: signed_at, signed_by; patient_id = users.id)
try {
    $signed_query = "SELECT scf.*, cft.template_name,
                    (scf.status = 'revoked') as is_revoked,
                    u.first_name as patient_fname, u.last_name as patient_lname,
                    u2.first_name as signed_by_fname, u2.last_name as signed_by_lname
                    FROM signed_consent_forms scf
                    LEFT JOIN consent_form_templates cft ON scf.template_id = cft.id
                    LEFT JOIN users u ON scf.patient_id = u.id
                    LEFT JOIN users u2 ON scf.signed_by = u2.id
                    ORDER BY scf.signed_at DESC
                    LIMIT 100";
    $signed_stmt = $db->prepare($signed_query);
    $signed_stmt->execute();
    $signed_forms = $signed_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $signed_forms = [];
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Consent Forms</h1>
    <p class="text-gray-600 dark:text-gray-400">Upload and manage digital consent form templates and track signed forms</p>
</div>

<!-- Tabs -->
<div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="flex -mb-px">
            <a href="?tab=templates" class="<?php echo $active_tab === 'templates' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> py-4 px-6 border-b-2 font-medium text-sm">
                Templates
            </a>
            <a href="?tab=signed" class="<?php echo $active_tab === 'signed' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> py-4 px-6 border-b-2 font-medium text-sm">
                Signed Forms
            </a>
        </nav>
    </div>
</div>

<?php if ($active_tab === 'templates'): ?>
    <!-- Templates Tab -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
        <div class="px-4 py-5 sm:p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Consent Form Templates</h3>
                <button onclick="document.getElementById('uploadTemplateModal').classList.remove('hidden')" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">
                    Upload Template
                </button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Template Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Version</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Created By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Created At</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php if (empty($templates)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">No templates found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($templates as $template): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($template['template_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($template['version']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars(($template['first_name'] ?? '') . ' ' . ($template['last_name'] ?? '')); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo date('M d, Y', strtotime($template['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="?tab=templates&edit=<?php echo $template['id']; ?>" class="text-primary-600 hover:text-primary-900">Edit</a>
                                        <a href="?tab=templates&view=<?php echo $template['id']; ?>" class="ml-4 text-blue-600 hover:text-blue-900">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Upload Template Modal -->
    <div id="uploadTemplateModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Upload Consent Form Template</h3>
                <button onclick="document.getElementById('uploadTemplateModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-500">×</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="upload_template">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Template Name *</label>
                        <input type="text" name="template_name" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Version</label>
                        <input type="text" name="version" value="1.0" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Template Content *</label>
                        <textarea name="template_content" rows="15" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"></textarea>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="document.getElementById('uploadTemplateModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">
                            Upload Template
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($active_tab === 'signed'): ?>
    <!-- Signed Forms Tab -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Signed Consent Forms</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Patient</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Template</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Signed Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Signed By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php if (empty($signed_forms)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">No signed forms found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($signed_forms as $form): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars(($form['patient_fname'] ?? '') . ' ' . ($form['patient_lname'] ?? '')); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($form['template_name'] ?? '-'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo date('M d, Y H:i', strtotime($form['signed_at'] ?? $form['signed_date'] ?? 'now')); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars(($form['signed_by_fname'] ?? '') . ' ' . ($form['signed_by_lname'] ?? '')); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $form['is_revoked'] ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                            <?php echo $form['is_revoked'] ? 'Revoked' : 'Active'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="?tab=signed&view=<?php echo $form['id']; ?>" class="text-primary-600 hover:text-primary-900">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
