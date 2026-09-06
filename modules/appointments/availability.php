<?php
/**
 * Returns available time slots for a doctor on a given date.
 * Used by the landing page booking modal. doctor_id = doctors.id (not users.id).
 * Slots are derived from doctor_schedules and exclude already-booked times in appointments_public.
 */
declare(strict_types=1);

header('Content-Type: application/json');

$doctor_id = isset($_GET['doctor_id']) ? (int) $_GET['doctor_id'] : 0;
$date = isset($_GET['date']) ? trim($_GET['date']) : '';
// appointment_type is optional; reserved for future use (e.g. different slot lengths)

if ($doctor_id <= 0 || $date === '') {
    http_response_code(400);
    echo json_encode(['error' => 'doctor_id and date are required']);
    exit;
}

$dt = DateTime::createFromFormat('Y-m-d', $date);
if (!$dt || $dt->format('Y-m-d') !== $date) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date. Use YYYY-MM-DD.']);
    exit;
}

$today = (new DateTime())->setTime(0, 0, 0);
if ($dt < $today) {
    echo json_encode([]);
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!$db) {
    http_response_code(500);
    echo json_encode(['error' => 'Database unavailable']);
    exit;
}

try {
    $schedule = getDoctorScheduleForDate($db, $doctor_id, $date);
} catch (Throwable $e) {
    error_log('availability.php getDoctorScheduleForDate: ' . $e->getMessage());
    echo json_encode([]);
    exit;
}

if (!$schedule) {
    echo json_encode([]);
    exit;
}

$start = $schedule['start_time'] ?? null;
$end = $schedule['end_time'] ?? null;
$duration = (int) ($schedule['slot_duration_minutes'] ?? 30);
if (!$start || !$end || $duration <= 0) {
    echo json_encode([]);
    exit;
}

$start = normalizeTime($start);
$end = normalizeTime($end);
if (strtotime($end) <= strtotime($start)) {
    echo json_encode([]);
    exit;
}

// Load booked times for this doctor/date from appointments_public (if table exists)
$booked = [];
try {
    $cols = $db->query("SHOW COLUMNS FROM appointments_public");
    $columns = $cols ? $cols->fetchAll(PDO::FETCH_COLUMN) : [];
} catch (PDOException $e) {
    $columns = [];
}

if (in_array('doctor_id', $columns) && in_array('appointment_date', $columns) && in_array('appointment_time', $columns)) {
    $statusCol = in_array('status', $columns);
    $sql = "SELECT appointment_time FROM appointments_public 
            WHERE doctor_id = :doctor_id AND appointment_date = :appointment_date";
    if ($statusCol) {
        $sql .= " AND (status IS NULL OR status NOT IN ('cancelled', 'rejected'))";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute([':doctor_id' => $doctor_id, ':appointment_date' => $date]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $t = $row['appointment_time'] ?? null;
        if ($t) {
            $booked[] = normalizeTime(is_string($t) ? $t : date('H:i:s', strtotime($t)));
        }
    }
}

$slots = [];
$current = strtotime($start);
$endTs = strtotime($end);

while ($current < $endTs) {
    $timeStr = date('H:i:s', $current);
    $currentNormalized = normalizeTime($timeStr);
    if (!in_array($currentNormalized, $booked, true)) {
        $slots[] = [
            'value' => $timeStr,
            'label' => date('g:i A', $current)
        ];
    }
    $current += $duration * 60;
}

echo json_encode($slots);
