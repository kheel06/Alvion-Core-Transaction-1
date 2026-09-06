<?php
require_once '../../config/config.php';
requireAuth();
checkRole(['admin', 'nurse', 'doctor']);

$page_title = "Bed Management";

// Handle bed status updates
if ($_POST && isset($_POST['update_bed_status'])) {
    try {
        $bed_id = (int) sanitizeInput($_POST['bed_id']);
        $status = sanitizeInput($_POST['status']);
        $current_patient_id_raw = trim($_POST['current_patient_id'] ?? '');
        $current_patient_id = $current_patient_id_raw !== '' ? (int) $current_patient_id_raw : null;

        $query = "UPDATE beds SET status = :status, current_patient_id = :current_patient_id WHERE id = :bed_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindValue(':current_patient_id', $current_patient_id, $current_patient_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':bed_id', $bed_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Bed status updated successfully!";
            header("Location: bed_management.php");
            exit();
        }
    } catch (PDOException $exception) {
        $_SESSION['error'] = "Bed update error: " . $exception->getMessage();
    }
}

// Get wards and beds (no departments join - table may not exist)
try {
    $wards_query = "SELECT w.* FROM wards w 
                   WHERE (w.status IS NULL OR w.status != 'closed')
                   ORDER BY w.ward_name";
    $wards_stmt = $db->prepare($wards_query);
    $wards_stmt->execute();
    $wards = $wards_stmt->fetchAll();

    // Get beds for each ward
    foreach ($wards as &$ward) {
        $beds_query = "SELECT b.*, p.first_name, p.last_name, p.hospital_id
                      FROM beds b 
                      LEFT JOIN patients p ON b.current_patient_id = p.id
                      WHERE b.ward_id = :ward_id 
                      ORDER BY b.bed_number";
        $beds_stmt = $db->prepare($beds_query);
        $beds_stmt->bindParam(':ward_id', $ward['id']);
        $beds_stmt->execute();
        $ward['beds'] = $beds_stmt->fetchAll();
    }

    // Get available patients for bed assignment
    $patients_query = "SELECT id, hospital_id, first_name, last_name FROM patients WHERE (status = 'active' OR status IS NULL) ORDER BY first_name, last_name";
    $patients_stmt = $db->prepare($patients_query);
    $patients_stmt->execute();
    $patients = $patients_stmt->fetchAll();

} catch (PDOException $exception) {
    $_SESSION['error'] = "Error fetching bed data: " . $exception->getMessage();
    $wards = [];
    $patients = [];
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Bed Management</h1>
    <p class="text-gray-600">Manage hospital bed allocation and status</p>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 gap-6 mb-8 sm:grid-cols-2 lg:grid-cols-4">
    <?php
    $total_beds = 0;
    $occupied_beds = 0;
    $available_beds = 0;
    $maintenance_beds = 0;

    foreach ($wards as $ward) {
        foreach ($ward['beds'] as $bed) {
            $total_beds++;
            if ($bed['status'] == 'occupied') $occupied_beds++;
            if ($bed['status'] == 'available') $available_beds++;
            if ($bed['status'] == 'maintenance') $maintenance_beds++;
        }
    }
    ?>
    
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M2 4v16"></path><path d="M2 8h18a2 2 0 0 1 2 2v10"></path><path d="M2 17h20"></path><path d="M6 8V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Total Beds</dt>
                        <dd class="text-lg font-medium text-gray-900"><?php echo $total_beds; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Available</dt>
                        <dd class="text-lg font-medium text-gray-900"><?php echo $available_beds; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M2 4v16"></path><path d="M2 8h18a2 2 0 0 1 2 2v10"></path><path d="M2 17h20"></path><path d="M6 8V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"></path><circle cx="13" cy="13" r="1"></circle><circle cx="11" cy="13" r="1"></circle></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Occupied</dt>
                        <dd class="text-lg font-medium text-gray-900"><?php echo $occupied_beds; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-orange-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Maintenance</dt>
                        <dd class="text-lg font-medium text-gray-900"><?php echo $maintenance_beds; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Wards and Beds -->
<div class="bg-white shadow rounded-lg">
    <div class="px-4 py-5 sm:p-6">
        <div class="space-y-8">
            <?php foreach ($wards as $ward): ?>
                <div class="border-b border-gray-200 pb-8 last:border-b-0 last:pb-0">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">
                                <?php echo $ward['ward_name']; ?> (<?php echo $ward['ward_code']; ?>)
                            </h3>
                            <p class="text-sm text-gray-500">
                                <?php echo !empty($ward['department_name']) ? $ward['department_name'] . ' • ' : ''; ?>
                                Capacity: <?php echo ($ward['current_occupancy'] ?? 0) . '/' . ($ward['capacity'] ?? 0); ?> •
                                Type: <?php echo ucfirst(str_replace('_', ' ', $ward['ward_type'])); ?>
                            </p>
                        </div>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                            <?php echo $ward['status'] == 'available' ? 'bg-green-100 text-green-800' : ''; ?>
                            <?php echo $ward['status'] == 'full' ? 'bg-red-100 text-red-800' : ''; ?>
                            <?php echo $ward['status'] == 'maintenance' ? 'bg-orange-100 text-orange-800' : ''; ?>">
                            <?php echo ucfirst($ward['status']); ?>
                        </span>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <?php foreach ($ward['beds'] as $bed): ?>
                            <div class="border rounded-lg p-4 
                                <?php echo $bed['status'] == 'available' ? 'border-green-300 bg-green-50' : ''; ?>
                                <?php echo $bed['status'] == 'occupied' ? 'border-red-300 bg-red-50' : ''; ?>
                                <?php echo $bed['status'] == 'maintenance' ? 'border-orange-300 bg-orange-50' : ''; ?>
                                <?php echo $bed['status'] == 'reserved' ? 'border-blue-300 bg-blue-50' : ''; ?>">
                                <div class="flex justify-between items-start mb-2">
                                    <h4 class="text-sm font-medium text-gray-900">
                                        Bed <?php echo $bed['bed_number']; ?>
                                    </h4>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium 
                                        <?php echo $bed['status'] == 'available' ? 'bg-green-100 text-green-800' : ''; ?>
                                        <?php echo $bed['status'] == 'occupied' ? 'bg-red-100 text-red-800' : ''; ?>
                                        <?php echo $bed['status'] == 'maintenance' ? 'bg-orange-100 text-orange-800' : ''; ?>
                                        <?php echo $bed['status'] == 'reserved' ? 'bg-blue-100 text-blue-800' : ''; ?>">
                                        <?php echo ucfirst($bed['status']); ?>
                                    </span>
                                </div>

                                <?php if ($bed['status'] == 'occupied' && $bed['first_name']): ?>
                                    <div class="text-sm text-gray-600 mb-3">
                                        <p class="font-medium"><?php echo $bed['first_name'] . ' ' . $bed['last_name']; ?></p>
                                        <p class="text-xs"><?php echo $bed['hospital_id']; ?></p>
                                    </div>
                                <?php endif; ?>

                                <div class="text-xs text-gray-500 mb-3">
                                    <p>Type: <?php echo ucfirst($bed['bed_type']); ?></p>
                                    <p>Rate: ₱<?php echo number_format($bed['daily_rate'], 2); ?>/day</p>
                                </div>

                                <!-- Bed Action Form -->
                                <form method="POST" class="space-y-2">
                                    <input type="hidden" name="update_bed_status" value="1">
                                    <input type="hidden" name="bed_id" value="<?php echo $bed['id']; ?>">
                                    
                                    <select name="status" class="block w-full text-xs border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500" 
                                        onchange="this.form.submit()">
                                        <option value="available" <?php echo $bed['status'] == 'available' ? 'selected' : ''; ?>>Available</option>
                                        <option value="occupied" <?php echo $bed['status'] == 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                                        <option value="maintenance" <?php echo $bed['status'] == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                        <option value="reserved" <?php echo $bed['status'] == 'reserved' ? 'selected' : ''; ?>>Reserved</option>
                                    </select>

                                    <?php if ($bed['status'] == 'occupied' || (isset($_POST['bed_id']) && $_POST['bed_id'] == $bed['id'] && $_POST['status'] == 'occupied')): ?>
                                        <select name="current_patient_id" class="block w-full text-xs border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500">
                                            <option value="">Select Patient</option>
                                            <?php foreach ($patients as $patient): ?>
                                                <option value="<?php echo $patient['id']; ?>" 
                                                    <?php echo $bed['current_patient_id'] == $patient['id'] ? 'selected' : ''; ?>>
                                                    <?php echo $patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['hospital_id'] . ')'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <input type="hidden" name="current_patient_id" value="">
                                    <?php endif; ?>

                                    <button type="submit" class="w-full bg-white border border-gray-300 rounded-md px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                        Update
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <a href="admission.php" class="flex flex-col items-center p-4 bg-primary-50 rounded-lg hover:bg-primary-100 transition-colors">
        <div class="w-12 h-12 bg-primary-500 rounded-full flex items-center justify-center mb-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white text-xl"><path d="M2 4v16"></path><path d="M2 8h18a2 2 0 0 1 2 2v10"></path><path d="M2 17h20"></path><path d="M6 8V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"></path><circle cx="13" cy="13" r="1"></circle><circle cx="11" cy="13" r="1"></circle></svg>
        </div>
        <span class="text-sm font-medium text-gray-900">Patient Admission</span>
    </a>

    <a href="discharge.php" class="flex flex-col items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
        <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center mb-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white text-xl"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        </div>
        <span class="text-sm font-medium text-gray-900">Patient Discharge</span>
    </a>

    <a href="transfers.php" class="flex flex-col items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
        <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center mb-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white text-xl"><polyline points="8 3 4 7 8 11"></polyline><polyline points="16 21 20 17 16 13"></polyline><line x1="4" y1="7" x2="20" y2="7"></line><line x1="4" y1="17" x2="20" y2="17"></line></svg>
        </div>
        <span class="text-sm font-medium text-gray-900">Bed Transfers</span>
    </a>

    <a href="../reports/bed_occupancy.php" class="flex flex-col items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
        <div class="w-12 h-12 bg-purple-500 rounded-full flex items-center justify-center mb-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white text-xl"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>
        </div>
        <span class="text-sm font-medium text-gray-900">Occupancy Report</span>
    </a>
</div>

<?php include '../../includes/footer.php'; ?>