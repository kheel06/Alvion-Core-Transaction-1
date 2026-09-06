<?php
require_once '../../config/config.php';
requireAuth();
checkRole(['admin', 'doctor', 'nurse']);

$page_title = "Teleconsultation";

// Handle teleconsultation booking (consultation_date = single datetime column)
if ($_POST) {
    try {
        $consultation_number = 'TEL' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $patient_id = (int) sanitizeInput($_POST['patient_id']);
        $doctor_id = (int) sanitizeInput($_POST['doctor_id']);
        $date_part = sanitizeInput($_POST['consultation_date']);
        $time_part = sanitizeInput($_POST['consultation_time']);
        $consultation_datetime = $date_part && $time_part ? ($date_part . ' ' . $time_part) : $date_part;
        $reason = sanitizeInput($_POST['reason'] ?? '');
        $symptoms = sanitizeInput($_POST['symptoms'] ?? '');
        $consultation_fee = (float) (sanitizeInput($_POST['consultation_fee'] ?? 0) ?: 500);

        $query = "INSERT INTO teleconsultations (
            consultation_number, patient_id, doctor_id, consultation_date, consultation_fee
        ) VALUES (
            :consultation_number, :patient_id, :doctor_id, :consultation_date, :consultation_fee
        )";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':consultation_number', $consultation_number);
        $stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
        $stmt->bindParam(':consultation_date', $consultation_datetime);
        $stmt->bindParam(':consultation_fee', $consultation_fee, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $new_id = (int) $db->lastInsertId();
            if ($reason || $symptoms) {
                try {
                    $up = $db->prepare("UPDATE teleconsultations SET consultation_notes = :notes WHERE id = :id");
                    $up->bindValue(':notes', trim($reason . "\n\nSymptoms: " . $symptoms));
                    $up->bindParam(':id', $new_id, PDO::PARAM_INT);
                    $up->execute();
                } catch (PDOException $e) { /* optional columns */ }
            }
            $_SESSION['success'] = "Teleconsultation scheduled successfully! Consultation #: " . $consultation_number;
            header("Location: teleconsult.php");
            exit();
        }
    } catch (PDOException $exception) {
        $_SESSION['error'] = "Booking error: " . $exception->getMessage();
    }
}

// Get doctors (role_id -> roles) and patients for dropdowns
try {
    $doctors_query = "SELECT u.id, u.first_name, u.last_name 
                      FROM users u 
                      INNER JOIN roles r ON u.role_id = r.id 
                      WHERE r.role_name = 'doctor' AND (u.status = 'active' OR u.status IS NULL)
                      ORDER BY u.last_name, u.first_name";
    $doctors_stmt = $db->prepare($doctors_query);
    $doctors_stmt->execute();
    $doctors = $doctors_stmt->fetchAll();

    $patients_query = "SELECT id, hospital_id, first_name, last_name FROM patients WHERE status = 'active' ORDER BY first_name, last_name";
    $patients_stmt = $db->prepare($patients_query);
    $patients_stmt->execute();
    $patients = $patients_stmt->fetchAll();
} catch (PDOException $exception) {
    $doctors = [];
    $patients = [];
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Teleconsultation Booking</h1>
    <p class="text-gray-600 dark:text-gray-400">Schedule virtual consultations with patients</p>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <!-- Booking Form -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="px-4 py-5 sm:p-6">
            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 gap-6">
                    <div>
                        <label for="patient_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Patient *</label>
                        <select name="patient_id" id="patient_id" required 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>">
                                    <?php echo $patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['hospital_id'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="doctor_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Doctor *</label>
                        <select name="doctor_id" id="doctor_id" required 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                            <option value="">Select Doctor</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>">
                                    Dr. <?php echo htmlspecialchars(trim(($doctor['first_name'] ?? '') . ' ' . ($doctor['last_name'] ?? '')) . (!empty($doctor['specialization']) ? ' - ' . $doctor['specialization'] : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label for="consultation_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Date *</label>
                            <input type="date" name="consultation_date" id="consultation_date" required 
                                min="<?php echo date('Y-m-d'); ?>"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        </div>

                        <div>
                            <label for="consultation_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Time *</label>
                            <input type="time" name="consultation_time" id="consultation_time" required 
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                        </div>
                    </div>

                    <div>
                        <label for="consultation_fee" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Consultation Fee (₱)</label>
                        <input type="number" name="consultation_fee" id="consultation_fee" step="0.01" 
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                            placeholder="0.00" value="500.00">
                    </div>

                    <div>
                        <label for="reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reason for Consultation *</label>
                        <textarea name="reason" id="reason" rows="4" required 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                            placeholder="Describe the reason for the teleconsultation..."></textarea>
                    </div>

                    <div>
                        <label for="symptoms" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Symptoms</label>
                        <textarea name="symptoms" id="symptoms" rows="3" 
                            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                            placeholder="List any symptoms the patient is experiencing..."></textarea>
                    </div>

                    <!-- AI Telehealth Support -->
                    <div class="rounded-lg border-2 border-dashed border-emerald-200 dark:border-emerald-700 bg-emerald-50/50 dark:bg-emerald-900/20 p-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" /></svg>
                            AI Telehealth Support
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Generate a brief clinical summary and suggested follow-up questions for the consultation.</p>
                        <button type="button" id="ai-telehealth-btn" 
                            class="inline-flex items-center px-3 py-2 border border-emerald-300 dark:border-emerald-600 rounded-md text-sm font-medium text-emerald-700 dark:text-emerald-300 bg-white dark:bg-gray-700 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                            <span id="ai-telehealth-btn-text">Generate summary &amp; questions</span>
                            <span id="ai-telehealth-spinner" class="hidden ml-2 h-4 w-4 border-2 border-emerald-600 border-t-transparent rounded-full animate-spin"></span>
                        </button>
                        <div id="ai-telehealth-result" class="hidden mt-3 p-3 rounded-md bg-white border border-emerald-200 space-y-2">
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase">Summary</p>
                                <p class="text-sm text-gray-900 mt-0.5" id="ai-summary"></p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase">Suggested questions to ask</p>
                                <ul class="list-disc list-inside text-sm text-gray-700 mt-0.5" id="ai-questions"></ul>
                            </div>
                            <p class="text-xs text-gray-400" id="ai-telehealth-source"></p>
                        </div>
                        <p id="ai-telehealth-error" class="hidden mt-2 text-sm text-red-600"></p>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" 
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        Schedule Teleconsultation
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Upcoming Teleconsultations -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                Upcoming Teleconsultations
            </h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php
            try {
                $upcoming_query = "SELECT tc.*, p.first_name, p.last_name, p.hospital_id, 
                                 u.first_name as doctor_fname, u.last_name as doctor_lname
                                 FROM teleconsultations tc
                                 LEFT JOIN patients p ON tc.patient_id = p.id
                                 LEFT JOIN users u ON tc.doctor_id = u.id
                                 WHERE tc.consultation_date >= CURDATE() 
                                 AND tc.status IN ('scheduled', 'ongoing')
                                 ORDER BY tc.consultation_date ASC
                                 LIMIT 5";
                $upcoming_stmt = $db->prepare($upcoming_query);
                $upcoming_stmt->execute();
                $upcoming_consultations = $upcoming_stmt->fetchAll();
            } catch (PDOException $exception) {
                $upcoming_consultations = [];
            }
            ?>

            <?php if (count($upcoming_consultations) > 0): ?>
                <div class="space-y-4">
                    <?php foreach ($upcoming_consultations as $consult): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                    <?php echo $consult['first_name'] . ' ' . $consult['last_name']; ?>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                    <?php echo date('M d, Y', strtotime($consult['consultation_date'])) . ' at ' . date('g:i A', strtotime($consult['consultation_date'])); ?>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Dr. <?php echo $consult['doctor_fname'] . ' ' . $consult['doctor_lname']; ?>
                                </p>
                            </div>
                            <div class="ml-3 flex-shrink-0 flex items-center gap-2">
                                <button type="button" class="js-start-video inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium text-white bg-primary-600 hover:bg-primary-700" data-consultation-id="<?php echo (int)($consult['id'] ?? 0); ?>">
                                    Start / Join call
                                </button>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200">
                                    <?php echo $consult['status'] === 'ongoing' ? 'Ongoing' : 'Scheduled'; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300 dark:text-gray-600 mx-auto mb-2"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.999"></path><rect x="2" y="6" width="14" height="12" rx="2"></rect><line x1="22" y1="22" x2="2" y2="2"></line></svg>
                    <p class="text-gray-500 dark:text-gray-400">No upcoming teleconsultations</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function() {
    var baseUrl = '<?php echo defined("BASE_URL") ? rtrim(BASE_URL, "/") : ""; ?>';
    var btn = document.getElementById('ai-telehealth-btn');
    var btnText = document.getElementById('ai-telehealth-btn-text');
    var spinner = document.getElementById('ai-telehealth-spinner');
    var result = document.getElementById('ai-telehealth-result');
    var errorEl = document.getElementById('ai-telehealth-error');
    var summaryEl = document.getElementById('ai-summary');
    var questionsEl = document.getElementById('ai-questions');
    var sourceEl = document.getElementById('ai-telehealth-source');

    function showLoading(show) {
        if (spinner) spinner.classList.toggle('hidden', !show);
        if (btn) btn.disabled = show;
        if (btnText) btnText.textContent = show ? 'Generating...' : 'Generate summary & questions';
    }
    function showError(msg) {
        if (errorEl) { errorEl.textContent = msg || ''; errorEl.classList.toggle('hidden', !msg); }
        if (result) result.classList.add('hidden');
    }
    function showResult(data) {
        if (errorEl) errorEl.classList.add('hidden');
        if (summaryEl) summaryEl.textContent = data.summary || '';
        if (questionsEl) {
            var html = '';
            (data.suggested_questions || []).forEach(function(q) { html += '<li>' + (q || '').replace(/</g, '&lt;') + '</li>'; });
            questionsEl.innerHTML = html || '<li>None</li>';
        }
        if (sourceEl) sourceEl.textContent = (data.source || '') === 'ai' ? 'Powered by AI' : 'Rule-based suggestion';
        if (result) result.classList.remove('hidden');
    }

    if (btn) {
        btn.addEventListener('click', function() {
            var reason = document.getElementById('reason').value.trim();
            if (!reason) {
                showError('Please enter reason for consultation first.');
                return;
            }
            showLoading(true);
            showError('');
            fetch(baseUrl + '/api/ai/telehealth_summary.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({
                    reason: reason,
                    symptoms: document.getElementById('symptoms').value
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                showLoading(false);
                if (data.success) showResult(data);
                else showError(data.message || 'Request failed.');
            })
            .catch(function() {
                showLoading(false);
                showError('Network error. Try again.');
            });
        });
    }
})();
</script>

<!-- Video Consultation Interface (Jitsi Meet) -->
<div id="video-consultation-section" class="mt-6 bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700 hidden">
    <div class="px-4 py-5 sm:p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Video Consultation</h3>
            <button type="button" id="video-end-btn" class="inline-flex items-center px-3 py-1.5 border border-red-300 dark:border-red-600 rounded-md text-sm font-medium text-red-700 dark:text-red-300 bg-white dark:bg-gray-700 hover:bg-red-50 dark:hover:bg-red-900/20">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mr-1.5"><rect x="6" y="6" width="12" height="12" rx="2"></rect></svg>End consultation
            </button>
        </div>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Share this room name with the other party so they can join: <strong id="video-room-display" class="text-gray-900 dark:text-white"></strong></p>
        <div class="rounded-lg overflow-hidden bg-black" style="min-height: 400px;">
            <iframe id="jitsi-iframe" allow="camera; microphone; fullscreen; display-capture" class="w-full" style="height: 500px; border: 0;"></iframe>
        </div>
    </div>
</div>
<!-- Join by room name (when no consultation selected) -->
<div class="mt-6 bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
    <div class="px-4 py-5 sm:p-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-2">Video Consultation</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Start or join a video call from an upcoming consultation above, or enter a room name to join directly.</p>
        <div class="flex flex-wrap items-end gap-2">
            <div class="flex-1 min-w-[200px]">
                <label for="video-room-input" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Room name</label>
                <input type="text" id="video-room-input" placeholder="e.g. AlvionRoom-1" class="block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
            </div>
            <button type="button" id="video-join-btn" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mr-2"><polygon points="6 3 20 12 6 21 6 3"></polygon></svg>Join call
            </button>
        </div>
    </div>
</div>
<script>
(function() {
    var baseUrl = '<?php echo defined("BASE_URL") ? rtrim(BASE_URL, "/") : ""; ?>';
    var section = document.getElementById('video-consultation-section');
    var iframe = document.getElementById('jitsi-iframe');
    var roomDisplay = document.getElementById('video-room-display');
    var roomInput = document.getElementById('video-room-input');
    var joinBtn = document.getElementById('video-join-btn');
    var endBtn = document.getElementById('video-end-btn');

    function roomNameFromId(id) { return 'AlvionRoom-' + (id || ''); }

    var currentConsultationId = null;

    function startVideo(roomName, consultationId) {
        roomName = (roomName || '').trim().replace(/\s+/g, '') || roomNameFromId(consultationId);
        if (!roomName) return;
        currentConsultationId = consultationId || null;
        roomDisplay.textContent = roomName;
        if (roomInput) roomInput.value = roomName;
        iframe.src = 'https://meet.jit.si/' + encodeURIComponent(roomName);
        if (section) section.classList.remove('hidden');
        if (consultationId) {
            fetch(baseUrl + '/api/telehealth/video_start.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ consultation_id: consultationId, video_link: 'https://meet.jit.si/' + roomName })
            }).catch(function() {});
        }
    }

    function endVideo() {
        if (currentConsultationId) {
            fetch(baseUrl + '/api/telehealth/video_end.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ consultation_id: currentConsultationId })
            }).catch(function() {});
            currentConsultationId = null;
        }
        iframe.src = '';
        if (section) section.classList.add('hidden');
    }

    document.querySelectorAll('.js-start-video').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = parseInt(btn.getAttribute('data-consultation-id'), 10);
            startVideo(roomNameFromId(id), id);
        });
    });
    if (joinBtn && roomInput) {
        joinBtn.addEventListener('click', function() {
            startVideo(roomInput.value, null);
        });
    }
    if (endBtn) endBtn.addEventListener('click', endVideo);
})();
</script>

<?php include '../../includes/footer.php'; ?>