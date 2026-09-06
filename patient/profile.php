<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

ensurePatientAccess();

$page_title = 'My Profile';
$patient = getCurrentPatientRecord($db);
$patientId = $patient['id'] ?? null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $patientId) {
    $contact_number = sanitizeInput($_POST['contact_number'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $address = sanitizeInput($_POST['house_no_street'] ?? '');
    $barangay = sanitizeInput($_POST['barangay'] ?? '');
    $city = sanitizeInput($_POST['city_code'] ?? '');
    $province = sanitizeInput($_POST['province_code'] ?? '');
    $zip = sanitizeInput($_POST['zip_code'] ?? '');
    $emergency_name = sanitizeInput($_POST['emergency_contact_name'] ?? '');
    $emergency_number = sanitizeInput($_POST['emergency_contact_number'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    if (empty($errors)) {
        try {
            $query = "UPDATE patients SET
                        contact_number = :contact_number,
                        email = :email,
                        house_no_street = :address,
                        barangay = :barangay,
                        city_code = :city,
                        province_code = :province,
                        zip_code = :zip,
                        emergency_contact_name = :em_name,
                        emergency_contact_number = :em_number,
                        updated_at = NOW()
                      WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':contact_number', $contact_number);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':barangay', $barangay);
            $stmt->bindParam(':city', $city);
            $stmt->bindParam(':province', $province);
            $stmt->bindParam(':zip', $zip);
            $stmt->bindParam(':em_name', $emergency_name);
            $stmt->bindParam(':em_number', $emergency_number);
            $stmt->bindParam(':id', $patientId, PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['email'] = $email;
            $_SESSION['success'] = 'Profile updated successfully.';
            $patient = getCurrentPatientRecord($db); // refresh cache
            header('Location: profile.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Failed to update profile: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">My Information</h1>
    <p class="text-gray-600 dark:text-gray-400">Keep your contact details up to date for appointment reminders.</p>
</div>

<?php if (!empty($errors)): ?>
    <div class="mb-4 rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-1">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php elseif (!empty($_SESSION['success'])): ?>
    <div class="mb-4 rounded-md bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">
        <?php
        echo htmlspecialchars($_SESSION['success']);
        unset($_SESSION['success']);
        ?>
    </div>
<?php endif; ?>

<?php if ($patient): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Profile Summary</h2>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Hospital ID</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo htmlspecialchars($patient['hospital_id']); ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Full Name</dt>
                    <dd class="text-gray-900 dark:text-white">
                        <?php echo htmlspecialchars(trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''))); ?>
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Birthdate</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo formatDate($patient['birth_date']); ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Known Allergies</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo !empty($patient['known_allergies']) ? nl2br(htmlspecialchars($patient['known_allergies'])) : 'None recorded'; ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Pre-existing Conditions</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo !empty($patient['pre_existing_conditions']) ? nl2br(htmlspecialchars($patient['pre_existing_conditions'])) : 'None recorded'; ?></dd>
                </div>
            </dl>
        </div>

        <div class="lg:col-span-2 bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Update Contact Details</h2>
            <form method="POST" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contact Number</label>
                        <input type="text" name="contact_number" value="<?php echo htmlspecialchars($patient['contact_number'] ?? ''); ?>"
                               class="mt-1 w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($patient['email'] ?? ($_SESSION['email'] ?? '')); ?>"
                               class="mt-1 w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-primary-500 focus:border-primary-500" required>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Street Address</label>
                    <input type="text" name="house_no_street" value="<?php echo htmlspecialchars($patient['house_no_street'] ?? ''); ?>"
                           class="mt-1 w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Barangay</label>
                        <input type="text" name="barangay" value="<?php echo htmlspecialchars($patient['barangay'] ?? ''); ?>"
                               class="mt-1 w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">City</label>
                        <input type="text" name="city_code" value="<?php echo htmlspecialchars($patient['city_code'] ?? ''); ?>"
                               class="mt-1 w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Province</label>
                        <input type="text" name="province_code" value="<?php echo htmlspecialchars($patient['province_code'] ?? ''); ?>"
                               class="mt-1 w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">ZIP Code</label>
                        <input type="text" name="zip_code" value="<?php echo htmlspecialchars($patient['zip_code'] ?? ''); ?>"
                               class="mt-1 w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Emergency Contact Name</label>
                        <input type="text" name="emergency_contact_name" value="<?php echo htmlspecialchars($patient['emergency_contact_name'] ?? ''); ?>"
                               class="mt-1 w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Emergency Contact Number</label>
                        <input type="text" name="emergency_contact_number" value="<?php echo htmlspecialchars($patient['emergency_contact_number'] ?? ''); ?>"
                               class="mt-1 w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-gray-100 dark:border-gray-700">
                    <button type="submit" class="px-5 py-2 rounded-md bg-primary-600 text-white text-sm font-medium hover:bg-primary-700 transition">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <p class="text-sm text-gray-500 dark:text-gray-400">We could not locate your patient profile. Please contact support.</p>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>


