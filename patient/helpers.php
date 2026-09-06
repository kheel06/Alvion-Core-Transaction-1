<?php
/**
 * Shared helper functions for patient-facing pages.
 */

if (!function_exists('ensurePatientAccess')) {
    function ensurePatientAccess(array $allowed_roles = ['patient'])
    {
        requireAuth();
        checkRole($allowed_roles);
    }
}

if (!function_exists('getCurrentPatientRecord')) {
    function getCurrentPatientRecord(PDO $db): ?array
    {
        static $cachedPatient = null;

        if ($cachedPatient !== null) {
            return $cachedPatient;
        }

        $userId = $_SESSION['user_id'] ?? null;
        $email = $_SESSION['email'] ?? null;

        if (!$db) {
            return null;
        }

        $patient = null;

        try {
            if ($userId) {
                $stmt = $db->prepare("SELECT * FROM patients WHERE created_by = :user_id LIMIT 1");
                $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $stmt->execute();
                $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$patient && $email) {
                $stmt = $db->prepare("SELECT * FROM patients WHERE email = :email LIMIT 1");
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            error_log('Failed to fetch patient record: ' . $e->getMessage());
        }

        $cachedPatient = $patient ?: null;
        return $cachedPatient;
    }
}

if (!function_exists('getCurrentPatientId')) {
    function getCurrentPatientId(PDO $db): ?int
    {
        $patient = getCurrentPatientRecord($db);
        return $patient['id'] ?? null;
    }
}

if (!function_exists('formatDateTimeDisplay')) {
    function formatDateTimeDisplay(?string $date, ?string $time = null): string
    {
        if (!$date) {
            return 'TBA';
        }

        $formattedDate = formatDate($date, 'M d, Y');

        if ($time) {
            return $formattedDate . ' @ ' . date('g:i A', strtotime($time));
        }

        return $formattedDate;
    }
}

