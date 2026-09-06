<?php
/**
 * Vital Signs Recording
 * Staff - Record patient vital signs
 */
require_once '../../config/config.php';
requireAuth();
checkRole(['staff', 'nurse']);
requirePermission('vitals.create');

$page_title = "Vital Signs Recording";

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$triage_id = isset($_GET['triage_id']) ? intval($_GET['triage_id']) : null;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $patient_id = intval($_POST['patient_id']);
        $triage_id = !empty($_POST['triage_id']) ? intval($_POST['triage_id']) : null;
        $appointment_id = !empty($_POST['appointment_id']) ? intval($_POST['appointment_id']) : null;
        $recorded_by = $_SESSION['user_id'];
        
        $bp_systolic = !empty($_POST['bp_systolic']) ? intval($_POST['bp_systolic']) : null;
        $bp_diastolic = !empty($_POST['bp_diastolic']) ? intval($_POST['bp_diastolic']) : null;
        $temperature = !empty($_POST['temperature']) ? floatval($_POST['temperature']) : null;
        $pulse_rate = !empty($_POST['pulse_rate']) ? intval($_POST['pulse_rate']) : null;
        $respiratory_rate = !empty($_POST['respiratory_rate']) ? intval($_POST['respiratory_rate']) : null;
        $oxygen_saturation = !empty($_POST['oxygen_saturation']) ? floatval($_POST['oxygen_saturation']) : null;
        $pain_scale = !empty($_POST['pain_scale']) ? intval($_POST['pain_scale']) : null;
        $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
        $height = !empty($_POST['height']) ? floatval($_POST['height']) : null;
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        // Calculate BMI if weight and height provided
        $bmi = null;
        if ($weight && $height && $height > 0) {
            $height_m = $height / 100; // Convert cm to meters
            $bmi = round($weight / ($height_m * $height_m), 2);
        }
        
        $recorded_at = date('Y-m-d H:i:s');
        
        $query = "INSERT INTO vital_signs 
                 (patient_id, triage_id, appointment_id, recorded_by,
                  blood_pressure_systolic, blood_pressure_diastolic, temperature,
                  pulse_rate, respiratory_rate, oxygen_saturation, pain_scale,
                  weight, height, bmi, notes, recorded_at)
                 VALUES 
                 (:patient_id, :triage_id, :appointment_id, :recorded_by,
                  :bp_systolic, :bp_diastolic, :temperature,
                  :pulse_rate, :respiratory_rate, :oxygen_saturation, :pain_scale,
                  :weight, :height, :bmi, :notes, :recorded_at)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':patient_id', $patient_id);
        $stmt->bindParam(':triage_id', $triage_id);
        $stmt->bindParam(':appointment_id', $appointment_id);
        $stmt->bindParam(':recorded_by', $recorded_by);
        $stmt->bindParam(':bp_systolic', $bp_systolic);
        $stmt->bindParam(':bp_diastolic', $bp_diastolic);
        $stmt->bindParam(':temperature', $temperature);
        $stmt->bindParam(':pulse_rate', $pulse_rate);
        $stmt->bindParam(':respiratory_rate', $respiratory_rate);
        $stmt->bindParam(':oxygen_saturation', $oxygen_saturation);
        $stmt->bindParam(':pain_scale', $pain_scale);
        $stmt->bindParam(':weight', $weight);
        $stmt->bindParam(':height', $height);
        $stmt->bindParam(':bmi', $bmi);
        $stmt->bindParam(':notes', $notes);
        $stmt->bindParam(':recorded_at', $recorded_at);
        
        if ($stmt->execute()) {
            $vital_id = $db->lastInsertId();
            logAction('vital_signs_recorded', 'vitals', $vital_id, null, [
                'patient_id' => $patient_id,
                'recorded_at' => $recorded_at
            ]);
            
            $_SESSION['success'] = "Vital signs recorded successfully!";
            
            // Redirect based on source
            if ($triage_id) {
                header("Location: ../er_triage/triage.php?id=" . $triage_id);
            } elseif ($appointment_id) {
                header("Location: ../appointments/schedule.php?id=" . $appointment_id);
            } else {
                header("Location: vital_signs.php?patient_id=" . $patient_id);
            }
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Get patient information
$patient = null;
if ($patient_id) {
    try {
        $query = "SELECT * FROM patients WHERE id = :patient_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':patient_id', $patient_id);
        $stmt->execute();
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error loading patient: " . $e->getMessage();
    }
}

// Get recent vital signs for this patient
$recent_vitals = [];
if ($patient_id) {
    try {
        $query = "SELECT vs.*, u.first_name, u.last_name
                  FROM vital_signs vs
                  LEFT JOIN users u ON vs.recorded_by = u.id
                  WHERE vs.patient_id = :patient_id
                  ORDER BY vs.recorded_at DESC
                  LIMIT 10";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':patient_id', $patient_id);
        $stmt->execute();
        $recent_vitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Ignore if table doesn't exist yet
    }
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Record Vital Signs</h1>
    <?php if ($patient): ?>
        <p class="text-gray-600">Patient: <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?> 
        (<?php echo htmlspecialchars($patient['hospital_id']); ?>)</p>
    <?php endif; ?>
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

<?php if (!$patient_id): ?>
    <div class="bg-white shadow rounded-lg p-6">
        <p class="text-gray-600">Please select a patient first.</p>
        <a href="register.php" class="btn btn-primary mt-4">Register Patient</a>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Vital Signs Form -->
        <div class="lg:col-span-2">
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Record New Vital Signs</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <?php if ($triage_id): ?>
                        <input type="hidden" name="triage_id" value="<?php echo $triage_id; ?>">
                    <?php endif; ?>
                    <?php if ($appointment_id): ?>
                        <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Blood Pressure (Systolic)</label>
                            <input type="number" name="bp_systolic" min="0" max="300"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Blood Pressure (Diastolic)</label>
                            <input type="number" name="bp_diastolic" min="0" max="200"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Temperature (°C)</label>
                            <input type="number" name="temperature" step="0.1" min="30" max="45"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Pulse Rate (BPM)</label>
                            <input type="number" name="pulse_rate" min="0" max="250"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Respiratory Rate (per min)</label>
                            <input type="number" name="respiratory_rate" min="0" max="60"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Oxygen Saturation (%)</label>
                            <input type="number" name="oxygen_saturation" step="0.1" min="0" max="100"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Pain Scale (0-10)</label>
                            <input type="number" name="pain_scale" min="0" max="10"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Weight (kg)</label>
                            <input type="number" name="weight" step="0.1" min="0"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Height (cm)</label>
                        <input type="number" name="height" step="0.1" min="0"
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
                               onchange="calculateBMI()">
                    </div>
                    
                    <div id="bmiDisplay" class="hidden">
                        <label class="block text-sm font-medium text-gray-700">BMI (Calculated)</label>
                        <input type="text" id="bmiValue" readonly
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 bg-gray-50">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea name="notes" rows="3"
                                  class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <a href="view_patient.php?id=<?php echo $patient_id; ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Record Vital Signs</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Recent Vital Signs -->
        <div>
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Recent Vital Signs</h2>
                <?php if (empty($recent_vitals)): ?>
                    <p class="text-gray-500 text-sm">No previous vital signs recorded</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach (array_slice($recent_vitals, 0, 5) as $vital): ?>
                            <div class="border-b pb-3">
                                <div class="text-sm text-gray-600">
                                    <div class="font-medium text-gray-900">
                                        <?php echo date('M d, Y H:i', strtotime($vital['recorded_at'])); ?>
                                    </div>
                                    <?php if ($vital['blood_pressure_systolic']): ?>
                                        <div>BP: <?php echo $vital['blood_pressure_systolic']; ?>/<?php echo $vital['blood_pressure_diastolic']; ?></div>
                                    <?php endif; ?>
                                    <?php if ($vital['temperature']): ?>
                                        <div>Temp: <?php echo $vital['temperature']; ?>°C</div>
                                    <?php endif; ?>
                                    <?php if ($vital['pulse_rate']): ?>
                                        <div>Pulse: <?php echo $vital['pulse_rate']; ?> BPM</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function calculateBMI() {
    const weight = parseFloat(document.querySelector('input[name="weight"]').value);
    const height = parseFloat(document.querySelector('input[name="height"]').value);
    
    if (weight && height && height > 0) {
        const height_m = height / 100;
        const bmi = (weight / (height_m * height_m)).toFixed(2);
        document.getElementById('bmiValue').value = bmi;
        document.getElementById('bmiDisplay').classList.remove('hidden');
    } else {
        document.getElementById('bmiDisplay').classList.add('hidden');
    }
}
</script>

<?php include '../../includes/footer.php'; ?>

