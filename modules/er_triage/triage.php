<?php
require_once '../../config/config.php';
requireAuth();
checkRole(['admin', 'nurse', 'doctor']);

$page_title = "ER Triage";

// Handle triage form submission
if ($_POST) {
    try {
        $patient_id = sanitizeInput($_POST['patient_id']);
        $chief_complaint = sanitizeInput($_POST['chief_complaint']);
        $triage_level = sanitizeInput($_POST['triage_level']);
        
        // Vital signs
        $vital_signs = [
            'blood_pressure' => sanitizeInput($_POST['blood_pressure']),
            'heart_rate' => sanitizeInput($_POST['heart_rate']),
            'respiratory_rate' => sanitizeInput($_POST['respiratory_rate']),
            'temperature' => sanitizeInput($_POST['temperature']),
            'oxygen_saturation' => sanitizeInput($_POST['oxygen_saturation']),
            'pain_level' => sanitizeInput($_POST['pain_level'])
        ];
        
        $initial_assessment = sanitizeInput($_POST['initial_assessment']);
        
        // Calculate priority score based on triage level
        $priority_scores = [
            'resuscitation' => 1,
            'emergency' => 2,
            'urgent' => 3,
            'semi_urgent' => 4,
            'non_urgent' => 5
        ];
        $priority_score = $priority_scores[$triage_level];

        $query = "INSERT INTO er_triage (
            patient_id, triage_nurse_id, chief_complaint, triage_level, 
            vital_signs, initial_assessment, priority_score
        ) VALUES (
            :patient_id, :triage_nurse_id, :chief_complaint, :triage_level,
            :vital_signs, :initial_assessment, :priority_score
        )";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':patient_id', $patient_id);
        $stmt->bindParam(':triage_nurse_id', $_SESSION['user_id']);
        $stmt->bindParam(':chief_complaint', $chief_complaint);
        $stmt->bindParam(':triage_level', $triage_level);
        $stmt->bindParam(':vital_signs', json_encode($vital_signs));
        $stmt->bindParam(':initial_assessment', $initial_assessment);
        $stmt->bindParam(':priority_score', $priority_score);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Patient triaged successfully with priority: " . ucfirst($triage_level);
            header("Location: triage.php");
            exit();
        }
    } catch (PDOException $exception) {
        $_SESSION['error'] = "Triage error: " . $exception->getMessage();
    }
}

// Get patients for dropdown
try {
    $patients_query = "SELECT id, hospital_id, first_name, last_name FROM patients WHERE status = 'active' ORDER BY first_name, last_name";
    $patients_stmt = $db->prepare($patients_query);
    $patients_stmt->execute();
    $patients = $patients_stmt->fetchAll();
} catch (PDOException $exception) {
    $patients = [];
}

// Get current triage queue
try {
    $triage_queue_query = "SELECT et.*, p.first_name, p.last_name, p.hospital_id,
                          u.first_name as nurse_fname, u.last_name as nurse_lname
                          FROM er_triage et
                          LEFT JOIN patients p ON et.patient_id = p.id
                          LEFT JOIN users u ON et.triage_nurse_id = u.id
                          WHERE et.status = 'waiting'
                          ORDER BY et.priority_score ASC, et.created_at ASC";
    $triage_queue_stmt = $db->prepare($triage_queue_query);
    $triage_queue_stmt->execute();
    $triage_queue = $triage_queue_stmt->fetchAll();
} catch (PDOException $exception) {
    $triage_queue = [];
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Emergency Room Triage</h1>
    <p class="text-gray-600 dark:text-gray-400">Assess and prioritize emergency patients</p>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <!-- Triage Form -->
    <div class="lg:col-span-2">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <form method="POST" class="space-y-6">
                    <!-- Patient Information -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Patient Information</h3>
                        <div class="grid grid-cols-1 gap-6">
                            <div>
                                <label for="patient_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Patient *</label>
                                <select name="patient_id" id="patient_id" required 
                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">Select Patient</option>
                                    <?php foreach ($patients as $patient): ?>
                                        <option value="<?php echo $patient['id']; ?>">
                                            <?php echo $patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['hospital_id'] . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Chief Complaint -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Chief Complaint</h3>
                        <div class="grid grid-cols-1 gap-6">
                            <div>
                                <label for="chief_complaint" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Chief Complaint *</label>
                                <textarea name="chief_complaint" id="chief_complaint" rows="3" required 
                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" 
                                    placeholder="Primary reason for visit..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Vital Signs -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Vital Signs</h3>
                        <div class="grid grid-cols-2 gap-6 sm:grid-cols-3">
                            <div>
                                <label for="blood_pressure" class="block text-sm font-medium text-gray-700 dark:text-gray-300">BP (mmHg)</label>
                                <input type="text" name="blood_pressure" id="blood_pressure" 
                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                                    placeholder="120/80">
                            </div>

                            <div>
                                <label for="heart_rate" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Heart Rate</label>
                                <input type="number" name="heart_rate" id="heart_rate" 
                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                                    placeholder="72">
                            </div>

                            <div>
                                <label for="respiratory_rate" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Resp. Rate</label>
                                <input type="number" name="respiratory_rate" id="respiratory_rate" 
                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                                    placeholder="16">
                            </div>

                            <div>
                                <label for="temperature" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Temp (°C)</label>
                                <input type="number" step="0.1" name="temperature" id="temperature" 
                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                                    placeholder="36.6">
                            </div>

                            <div>
                                <label for="oxygen_saturation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">O2 Sat (%)</label>
                                <input type="number" name="oxygen_saturation" id="oxygen_saturation" 
                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                                    placeholder="98">
                            </div>

                            <div>
                                <label for="pain_level" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Pain (0-10)</label>
                                <input type="number" min="0" max="10" name="pain_level" id="pain_level" 
                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                                    placeholder="0">
                            </div>
                        </div>
                    </div>

                    <!-- AI-Powered Triage Suggestion -->
                    <div class="rounded-lg border-2 border-dashed border-indigo-200 dark:border-indigo-800 bg-indigo-50/50 dark:bg-indigo-900/20 p-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" /></svg>
                            AI-Powered Triage Suggestion
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Get a suggested triage level based on chief complaint and vital signs. Clinical judgment always overrides the suggestion.</p>
                        <button type="button" id="ai-triage-btn" 
                            class="inline-flex items-center px-3 py-2 border border-indigo-300 dark:border-indigo-600 rounded-md text-sm font-medium text-indigo-700 dark:text-indigo-300 bg-white dark:bg-gray-800 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <span id="ai-triage-btn-text">Get AI suggestion</span>
                            <span id="ai-triage-spinner" class="hidden ml-2 h-4 w-4 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></span>
                        </button>
                        <div id="ai-triage-result" class="hidden mt-3 p-3 rounded-md bg-white dark:bg-gray-800 border border-indigo-200 dark:border-indigo-700">
                            <p class="text-sm font-medium text-gray-900 dark:text-white mb-1">Suggested level: <span id="ai-suggested-level" class="capitalize"></span> <span id="ai-source-badge" class="text-xs px-1.5 py-0.5 rounded bg-gray-200 dark:bg-gray-600"></span></p>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2" id="ai-reasoning"></p>
                            <button type="button" id="ai-apply-btn" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Use this level</button>
                        </div>
                        <p id="ai-triage-error" class="hidden mt-2 text-sm text-red-600 dark:text-red-400"></p>
                    </div>

                    <!-- Triage Assessment -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Triage Assessment</h3>
                        <div class="grid grid-cols-1 gap-6">
                            <div>
                                <label for="triage_level" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Triage Level *</label>
                                <select name="triage_level" id="triage_level" required 
                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">Select Priority Level</option>
                                    <option value="resuscitation">Resuscitation (Immediate)</option>
                                    <option value="emergency">Emergency (10 mins)</option>
                                    <option value="urgent">Urgent (30 mins)</option>
                                    <option value="semi_urgent">Semi-urgent (1 hour)</option>
                                    <option value="non_urgent">Non-urgent (2 hours)</option>
                                </select>
                            </div>

                            <div>
                                <label for="initial_assessment" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Initial Assessment</label>
                                <textarea name="initial_assessment" id="initial_assessment" rows="4" 
                                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white" 
                                    placeholder="Initial nursing assessment and observations..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" 
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            Complete Triage
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Triage Queue -->
    <div class="lg:col-span-1">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                    Current Triage Queue
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    <?php echo count($triage_queue); ?> patients waiting
                </p>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <?php if (count($triage_queue) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($triage_queue as $triage): ?>
                            <div class="p-3 border rounded-lg 
                                <?php echo $triage['triage_level'] == 'resuscitation' ? 'border-red-300 bg-red-50' : ''; ?>
                                <?php echo $triage['triage_level'] == 'emergency' ? 'border-orange-300 bg-orange-50' : ''; ?>
                                <?php echo $triage['triage_level'] == 'urgent' ? 'border-yellow-300 bg-yellow-50' : ''; ?>
                                <?php echo $triage['triage_level'] == 'semi_urgent' ? 'border-blue-300 bg-blue-50' : ''; ?>
                                <?php echo $triage['triage_level'] == 'non_urgent' ? 'border-green-300 bg-green-50' : ''; ?>">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            <?php echo $triage['first_name'] . ' ' . $triage['last_name']; ?>
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            <?php echo $triage['hospital_id']; ?>
                                        </p>
                                        <p class="text-xs text-gray-600 mt-1">
                                            <?php echo $triage['chief_complaint']; ?>
                                        </p>
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

        <!-- Triage Guidelines -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg mt-6">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                    Triage Guidelines
                </h3>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                        <span class="text-red-700 font-medium">Resuscitation:</span>
                        <span class="text-gray-600 ml-2">Immediate care needed</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-orange-500 rounded-full mr-2"></div>
                        <span class="text-orange-700 font-medium">Emergency:</span>
                        <span class="text-gray-600 ml-2">Within 10 minutes</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></div>
                        <span class="text-yellow-700 font-medium">Urgent:</span>
                        <span class="text-gray-600 ml-2">Within 30 minutes</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-blue-500 rounded-full mr-2"></div>
                        <span class="text-blue-700 font-medium">Semi-urgent:</span>
                        <span class="text-gray-600 ml-2">Within 1 hour</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                        <span class="text-green-700 font-medium">Non-urgent:</span>
                        <span class="text-gray-600 ml-2">Within 2 hours</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var baseUrl = '<?php echo rtrim(BASE_URL, "/"); ?>';
    var btn = document.getElementById('ai-triage-btn');
    var btnText = document.getElementById('ai-triage-btn-text');
    var spinner = document.getElementById('ai-triage-spinner');
    var result = document.getElementById('ai-triage-result');
    var errorEl = document.getElementById('ai-triage-error');
    var levelEl = document.getElementById('ai-suggested-level');
    var sourceBadge = document.getElementById('ai-source-badge');
    var reasoningEl = document.getElementById('ai-reasoning');
    var applyBtn = document.getElementById('ai-apply-btn');
    var triageSelect = document.getElementById('triage_level');

    function showLoading(show) {
        spinner.classList.toggle('hidden', !show);
        btn.disabled = show;
        btnText.textContent = show ? 'Analyzing...' : 'Get AI suggestion';
    }
    function showError(msg) {
        errorEl.textContent = msg || '';
        errorEl.classList.toggle('hidden', !msg);
        result.classList.add('hidden');
    }
    var lastSuggestedLevel = '';
    function showSuggestion(data) {
        errorEl.classList.add('hidden');
        lastSuggestedLevel = (data.suggested_level || '').trim();
        levelEl.textContent = lastSuggestedLevel.replace(/_/g, ' ');
        sourceBadge.textContent = (data.source || '') === 'ai' ? 'AI' : 'Rule-based';
        reasoningEl.textContent = data.reasoning || '';
        result.classList.remove('hidden');
    }

    if (btn) {
        btn.addEventListener('click', function() {
            var complaint = document.getElementById('chief_complaint').value.trim();
            if (!complaint) {
                showError('Please enter chief complaint first.');
                return;
            }
            showLoading(true);
            showError('');
            fetch(baseUrl + '/api/ai/triage_suggestion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({
                    chief_complaint: complaint,
                    blood_pressure: document.getElementById('blood_pressure').value,
                    heart_rate: document.getElementById('heart_rate').value,
                    respiratory_rate: document.getElementById('respiratory_rate').value,
                    temperature: document.getElementById('temperature').value,
                    oxygen_saturation: document.getElementById('oxygen_saturation').value,
                    pain_level: document.getElementById('pain_level').value
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                showLoading(false);
                if (data.success) showSuggestion(data);
                else showError(data.message || 'Suggestion failed.');
            })
            .catch(function() {
                showLoading(false);
                showError('Network error. Try again.');
            });
        });
    }
    if (applyBtn && triageSelect) {
        applyBtn.addEventListener('click', function() {
            if (lastSuggestedLevel && triageSelect.querySelector('option[value="' + lastSuggestedLevel + '"]')) {
                triageSelect.value = lastSuggestedLevel;
            }
        });
    }
})();
</script>

<?php include '../../includes/footer.php'; ?>