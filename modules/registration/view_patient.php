<?php
/**
 * View Patient - Patient Profile Viewing
 * Part of SPRS - Smart Patient Registration System
 * Philippines Hospital Context
 */
require_once '../../config/config.php';
requireAuth();
checkRole(['admin', 'receptionist', 'doctor', 'nurse', 'billing_staff']);

$page_title = "View Patient";
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$patient_id) {
    $_SESSION['error'] = "Patient ID is required.";
    header("Location: register.php");
    exit;
}

// Get patient information
try {
    $patient_query = "SELECT * FROM patients WHERE id = :id";
    $patient_stmt = $db->prepare($patient_query);
    $patient_stmt->bindParam(':id', $patient_id, PDO::PARAM_INT);
    $patient_stmt->execute();
    $patient = $patient_stmt->fetch();
    
    if (!$patient) {
        $_SESSION['error'] = "Patient not found.";
        header("Location: register.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching patient: " . $e->getMessage();
    header("Location: register.php");
    exit;
}

// Get patient insurance
try {
    $insurance_query = "SELECT pi.*, ip.provider_name, ip.provider_type 
                       FROM patient_insurance pi
                       INNER JOIN insurance_providers ip ON pi.insurance_provider_id = ip.id
                       WHERE pi.patient_id = :patient_id AND pi.is_active = 1
                       ORDER BY pi.is_primary DESC";
    $insurance_stmt = $db->prepare($insurance_query);
    $insurance_stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
    $insurance_stmt->execute();
    $insurance_records = $insurance_stmt->fetchAll();
} catch (PDOException $e) {
    $insurance_records = [];
}

// Get patient consents
try {
    $consents_query = "SELECT pc.*, cf.form_name, cf.form_type 
                      FROM patient_consents pc
                      INNER JOIN consent_forms cf ON pc.consent_form_id = cf.id
                      WHERE pc.patient_id = :patient_id
                      ORDER BY pc.signed_at DESC";
    $consents_stmt = $db->prepare($consents_query);
    $consents_stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
    $consents_stmt->execute();
    $consents = $consents_stmt->fetchAll();
} catch (PDOException $e) {
    $consents = [];
}

// Get recent appointments
try {
    $appointments_query = "SELECT a.*, u.first_name as doctor_fname, u.last_name as doctor_lname, u.specialization
                          FROM appointments a
                          LEFT JOIN users u ON a.doctor_id = u.id
                          WHERE a.patient_id = :patient_id
                          ORDER BY a.appointment_date DESC, a.appointment_time DESC
                          LIMIT 10";
    $appointments_stmt = $db->prepare($appointments_query);
    $appointments_stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
    $appointments_stmt->execute();
    $appointments = $appointments_stmt->fetchAll();
} catch (PDOException $e) {
    $appointments = [];
}

// Get recent admissions
try {
    $admissions_query = "SELECT ad.*, w.ward_name, b.bed_number, u.first_name as doctor_fname, u.last_name as doctor_lname
                        FROM admissions ad
                        LEFT JOIN beds b ON ad.bed_id = b.id
                        LEFT JOIN wards w ON b.ward_id = w.id
                        LEFT JOIN users u ON ad.admitting_doctor_id = u.id
                        WHERE ad.patient_id = :patient_id
                        ORDER BY ad.admission_date DESC
                        LIMIT 5";
    $admissions_stmt = $db->prepare($admissions_query);
    $admissions_stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
    $admissions_stmt->execute();
    $admissions = $admissions_stmt->fetchAll();
} catch (PDOException $e) {
    $admissions = [];
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Patient Profile</h1>
            <p class="text-gray-600"><?php echo $patient['first_name'] . ' ' . $patient['last_name']; ?> (<?php echo $patient['hospital_id']; ?>)</p>
        </div>
        <div class="flex space-x-3">
            <?php if (hasPermission('register_patient')): ?>
            <a href="edit_patient.php?id=<?php echo $patient_id; ?>" 
               class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>Edit Patient
            </a>
            <?php endif; ?>
            <a href="register.php" 
               class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" y1="8" x2="19" y2="14"></line><line x1="22" y1="11" x2="16" y2="11"></line></svg>Register New
            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <!-- Main Patient Information -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Personal Information -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Personal Information</h3>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Full Name</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <?php echo $patient['first_name'] . ' ' . ($patient['middle_name'] ? $patient['middle_name'] . ' ' : '') . $patient['last_name'] . ($patient['suffix'] ? ' ' . $patient['suffix'] : ''); ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Hospital ID</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-mono"><?php echo $patient['hospital_id']; ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Birth Date</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <?php echo formatDate($patient['birth_date']); ?> 
                            (Age: <?php echo calculateAge($patient['birth_date']); ?> years)
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Gender</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo ucfirst($patient['gender']); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Civil Status</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo ucfirst($patient['civil_status'] ?: 'Not specified'); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Nationality</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo $patient['nationality'] ?: 'Filipino'; ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Blood Type</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo $patient['blood_type'] ?: 'Not specified'; ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                <?php echo $patient['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                <?php echo ucfirst($patient['status'] ?: 'active'); ?>
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Address Information -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Address Information</h3>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <dl class="grid grid-cols-1 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Complete Address</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <?php 
                            $address_parts = array_filter([
                                $patient['house_no_street'],
                                $patient['barangay'],
                                $patient['city_code'],
                                $patient['province_code'],
                                $patient['zip_code']
                            ]);
                            echo !empty($address_parts) ? implode(', ', $address_parts) : 'Not provided';
                            ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Region</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo $patient['region_code'] ?: 'Not specified'; ?></dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Contact Information</h3>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Contact Number</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo $patient['contact_number'] ?: 'Not provided'; ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Email Address</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo $patient['email'] ?: 'Not provided'; ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Emergency Contact</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <?php echo $patient['emergency_contact_name'] ?: 'Not provided'; ?>
                            <?php if ($patient['emergency_contact_relationship']): ?>
                                (<?php echo $patient['emergency_contact_relationship']; ?>)
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Emergency Contact Number</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo $patient['emergency_contact_number'] ?: 'Not provided'; ?></dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Government IDs -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Government Identification</h3>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">PhilHealth ID</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo $patient['philhealth_id'] ?: 'Not provided'; ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">SSS ID</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo $patient['sss_id'] ?: 'Not provided'; ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">GSIS ID</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo $patient['gsis_id'] ?: 'Not provided'; ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">TIN</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo $patient['tin'] ?: 'Not provided'; ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Passport Number</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo $patient['passport_number'] ?: 'Not provided'; ?></dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Medical Information -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Medical Information</h3>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <dl class="grid grid-cols-1 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Known Allergies</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo $patient['known_allergies'] ?: 'None recorded'; ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Pre-existing Conditions</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo $patient['pre_existing_conditions'] ?: 'None recorded'; ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Current Medications</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo $patient['current_medications'] ?: 'None recorded'; ?></dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    <!-- Sidebar Information -->
    <div class="lg:col-span-1 space-y-6">
        <!-- Insurance Information -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Insurance</h3>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <?php if (count($insurance_records) > 0): ?>
                    <div class="space-y-3">
                        <?php foreach ($insurance_records as $insurance): ?>
                            <div class="p-3 bg-gray-50 rounded-lg <?php echo $insurance['is_primary'] ? 'border-2 border-primary-300' : ''; ?>">
                                <p class="text-sm font-medium text-gray-900">
                                    <?php echo $insurance['provider_name']; ?>
                                    <?php if ($insurance['is_primary']): ?>
                                        <span class="ml-2 text-xs text-primary-600">(Primary)</span>
                                    <?php endif; ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-1"><?php echo $insurance['policy_number']; ?></p>
                                <p class="text-xs text-gray-600 mt-1">Type: <?php echo ucfirst($insurance['provider_type']); ?></p>
                                <?php if ($insurance['expiry_date']): ?>
                                    <p class="text-xs text-gray-500 mt-1">Expires: <?php echo formatDate($insurance['expiry_date']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-gray-500">No insurance records</p>
                <?php endif; ?>
                <?php if (hasPermission('view_insurance')): ?>
                <a href="insurance_setup.php?patient_id=<?php echo $patient_id; ?>" 
                   class="mt-4 block text-sm text-primary-600 hover:text-primary-700">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1 inline"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>Add Insurance
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Consent Forms -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Consent Forms</h3>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <?php if (count($consents) > 0): ?>
                    <div class="space-y-2">
                        <?php foreach ($consents as $consent): ?>
                            <div class="p-2 bg-green-50 rounded border border-green-200">
                                <p class="text-xs font-medium text-gray-900"><?php echo $consent['form_name']; ?></p>
                                <p class="text-xs text-gray-500"><?php echo formatDate($consent['signed_at']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-gray-500">No consent forms signed</p>
                <?php endif; ?>
                <?php if (hasPermission('register_patient')): ?>
                <a href="consent_forms.php?patient_id=<?php echo $patient_id; ?>" 
                   class="mt-4 block text-sm text-primary-600 hover:text-primary-700">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1 inline"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M10 18h4"></path><path d="M12 16v4"></path></svg>Manage Consents
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Quick Actions</h3>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <div class="space-y-2">
                    <?php if (hasPermission('schedule_appointment')): ?>
                    <a href="../appointments/schedule.php?patient_id=<?php echo $patient_id; ?>" 
                       class="block w-full px-3 py-2 text-sm text-center border border-gray-300 rounded-md hover:bg-gray-50">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="M12 6v6"></path><path d="M9 9h6"></path></svg>Schedule Appointment
                    </a>
                    <?php endif; ?>
                    <?php if (hasPermission('view_insurance')): ?>
                    <a href="insurance_setup.php?patient_id=<?php echo $patient_id; ?>" 
                       class="block w-full px-3 py-2 text-sm text-center border border-gray-300 rounded-md hover:bg-gray-50">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path></svg>Insurance Setup
                    </a>
                    <?php endif; ?>
                    <?php if (hasPermission('view_ehr')): ?>
                    <a href="#" 
                       class="block w-full px-3 py-2 text-sm text-center border border-gray-300 rounded-md hover:bg-gray-50">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 inline"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>View Health Records
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <!-- Recent Appointments -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Recent Appointments</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($appointments) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($appointments as $appt): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    <?php echo formatDate($appt['appointment_date']); ?> at <?php echo formatTime($appt['appointment_time']); ?>
                                </p>
                                <?php if ($appt['doctor_fname']): ?>
                                    <p class="text-xs text-gray-500">
                                        Dr. <?php echo $appt['doctor_fname'] . ' ' . $appt['doctor_lname']; ?>
                                        <?php if ($appt['specialization']): ?>
                                            - <?php echo $appt['specialization']; ?>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <p class="text-xs text-gray-600 mt-1"><?php echo ucfirst($appt['appointment_type']); ?></p>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                <?php echo getStatusBadge($appt['status']) == 'blue' ? 'bg-blue-100 text-blue-800' : ''; ?>
                                <?php echo getStatusBadge($appt['status']) == 'green' ? 'bg-green-100 text-green-800' : ''; ?>
                                <?php echo getStatusBadge($appt['status']) == 'yellow' ? 'bg-yellow-100 text-yellow-800' : ''; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $appt['status'])); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500 text-center py-4">No appointments found</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Admissions -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Recent Admissions</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($admissions) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($admissions as $adm): ?>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="text-sm font-medium text-gray-900">
                                Admission #<?php echo $adm['admission_number']; ?>
                            </p>
                            <p class="text-xs text-gray-500">
                                <?php echo formatDate($adm['admission_date']); ?>
                            </p>
                            <?php if ($adm['ward_name']): ?>
                                <p class="text-xs text-gray-600 mt-1">
                                    <?php echo $adm['ward_name']; ?> - Bed <?php echo $adm['bed_number']; ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($adm['doctor_fname']): ?>
                                <p class="text-xs text-gray-600">
                                    Dr. <?php echo $adm['doctor_fname'] . ' ' . $adm['doctor_lname']; ?>
                                </p>
                            <?php endif; ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-2
                                <?php echo $adm['status'] == 'admitted' ? 'bg-blue-100 text-blue-800' : ''; ?>
                                <?php echo $adm['status'] == 'discharged' ? 'bg-green-100 text-green-800' : ''; ?>">
                                <?php echo ucfirst($adm['status']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500 text-center py-4">No admissions found</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>



