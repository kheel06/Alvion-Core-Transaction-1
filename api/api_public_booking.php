<?php
declare(strict_types=1);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Please use POST.'
    ]);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/email_helper.php';

if (!$db) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection unavailable.'
    ]);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

if (!is_array($payload)) {
    $payload = $_POST;
}

$respond = static function (int $statusCode, array $body): void {
    http_response_code($statusCode);
    echo json_encode($body);
    exit;
};

$requiredFields = [
    'doctor_id',
    'full_name',
    'contact_number',
    'email',
    'date_of_birth',
    'appointment_date',
    'appointment_time',
    'appointment_slot',
    'visit_type'
];

$errors = [];
foreach ($requiredFields as $field) {
    if (empty($payload[$field])) {
        $errors[$field] = 'This field is required.';
    }
}

$doctorId = isset($payload['doctor_id']) ? (int) $payload['doctor_id'] : 0;
if ($doctorId <= 0) {
    $errors['doctor_id'] = 'Please select a doctor.';
}

$email = filter_var($payload['email'] ?? '', FILTER_VALIDATE_EMAIL);
if (!$email) {
    $errors['email'] = 'Please provide a valid email address.';
}

$visitType = $payload['visit_type'] ?? '';
$allowedVisitTypes = [
    'in_person_consultation',
    'teleconsultation_video',
    'walkin_express'
];
if (!in_array($visitType, $allowedVisitTypes, true)) {
    $errors['visit_type'] = 'Invalid appointment type.';
}

$dateValidators = [
    'date_of_birth' => $payload['date_of_birth'] ?? '',
    'appointment_date' => $payload['appointment_date'] ?? '',
];

foreach ($dateValidators as $field => $value) {
    if (!empty($value)) {
        $date = DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            $errors[$field] = 'Invalid date format. Use YYYY-MM-DD.';
        }
    }
}

if (!empty($errors)) {
    $respond(422, [
        'success' => false,
        'message' => 'Please correct the highlighted fields.',
        'errors' => $errors
    ]);
}

$fullName = sanitizeInput($payload['full_name'] ?? '');
$firstName = sanitizeInput($payload['first_name'] ?? '');
$lastName = sanitizeInput($payload['last_name'] ?? '');

if (!$firstName && !$lastName && $fullName) {
    $parts = preg_split('/\s+/', $fullName, -1, PREG_SPLIT_NO_EMPTY);
    $firstName = $parts[0] ?? '';
    $lastName = implode(' ', array_slice($parts, 1));
}

$contactNumber = preg_replace('/\s+/', '', $payload['contact_number'] ?? '');
$contactNumber = sanitizeInput($contactNumber);

$appointmentTime = normalizeTime($payload['appointment_time'] ?? '');
$appointmentSlot = sanitizeInput($payload['appointment_slot'] ?? '');
$hmoProvider = sanitizeInput($payload['hmo_provider'] ?? '');
$telehealthPlatform = ($visitType === 'teleconsultation_video')
    ? sanitizeInput($payload['telehealth_platform'] ?? '')
    : null;
$reason = trim($payload['reason'] ?? '');
$bookingChannel = sanitizeInput($payload['booking_channel'] ?? 'online');

try {
    $doctorStmt = $db->prepare("
        SELECT id, COALESCE(first_name, '') AS first_name, COALESCE(last_name, '') AS last_name, specialty
        FROM doctors
        WHERE id = :id
        LIMIT 1
    ");
    $doctorStmt->bindParam(':id', $doctorId, PDO::PARAM_INT);
    $doctorStmt->execute();
    $doctor = $doctorStmt->fetch(PDO::FETCH_ASSOC);

    if (!$doctor) {
        $respond(404, [
            'success' => false,
            'message' => 'Selected doctor could not be found. Please refresh and try again.'
        ]);
    }

    $db->beginTransaction();

    $insertStmt = $db->prepare("
        INSERT INTO appointments_public
            (doctor_id, full_name, first_name, last_name, contact_number, email, date_of_birth, hmo_provider,
             visit_type, telehealth_platform, appointment_date, appointment_slot, appointment_time, reason,
             booking_channel, status, created_at, updated_at)
        VALUES
            (:doctor_id, :full_name, :first_name, :last_name, :contact_number, :email, :date_of_birth, :hmo_provider,
             :visit_type, :telehealth_platform, :appointment_date, :appointment_slot, :appointment_time, :reason,
             :booking_channel, :status, NOW(), NOW())
    ");

    $status = 'pending';

    $insertStmt->execute([
        ':doctor_id' => $doctorId,
        ':full_name' => $fullName,
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':contact_number' => $contactNumber,
        ':email' => $email,
        ':date_of_birth' => $payload['date_of_birth'],
        ':hmo_provider' => $hmoProvider ?: null,
        ':visit_type' => $visitType,
        ':telehealth_platform' => $telehealthPlatform,
        ':appointment_date' => $payload['appointment_date'],
        ':appointment_slot' => $appointmentSlot ?: $appointmentTime,
        ':appointment_time' => $appointmentTime,
        ':reason' => $reason ?: null,
        ':booking_channel' => $bookingChannel ?: 'online',
        ':status' => $status
    ]);

    $appointmentId = (int) $db->lastInsertId();
    $db->commit();

    $visitTypeLabels = [
        'in_person_consultation' => 'In-Person Consultation',
        'teleconsultation_video' => 'Teleconsultation (Video)',
        'walkin_express' => 'Walk-In (Express)'
    ];

    $doctorName = trim(($doctor['first_name'] ?? '') . ' ' . ($doctor['last_name'] ?? ''));
    if (!$doctorName) {
        $doctorName = 'Alvion Doctor';
    }

    $emailDetails = [
        'doctor_name' => $doctorName,
        'appointment_date' => $payload['appointment_date'],
        'appointment_time' => $appointmentTime,
        'visit_type_label' => $visitTypeLabels[$visitType] ?? ucfirst(str_replace('_', ' ', $visitType)),
        'telehealth_platform' => $telehealthPlatform
    ];

    $emailResult = sendAppointmentConfirmationEmail($email, $fullName, $emailDetails);

    $respond(200, [
        'success' => true,
        'message' => 'Appointment request submitted! Please check your email for confirmation details.',
        'data' => [
            'appointment_id' => $appointmentId,
            'email_sent' => $emailResult['success'],
            'email_message' => $emailResult['message'] ?? ''
        ]
    ]);
} catch (PDOException $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Public booking failed: ' . $exception->getMessage());
    $respond(500, [
        'success' => false,
        'message' => 'Unable to save your appointment at the moment. Please try again shortly.'
    ]);
}

