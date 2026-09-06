<?php
// Helper functions for General Modules

// Ensure profile_picture column exists
if (!function_exists('ensureUsersProfilePictureColumn')) {
    function ensureUsersProfilePictureColumn(PDO $db): void {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
            if ($stmt && $stmt->rowCount() === 0) {
                $db->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL AFTER last_name");
            }
        } catch (PDOException $e) {
            error_log("Failed to ensure users.profile_picture column: " . $e->getMessage());
        }
    }
}

// Ensure patient_id column exists
if (!function_exists('ensureUsersPatientIdColumn')) {
    function ensureUsersPatientIdColumn(PDO $db): void {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'patient_id'");
            if ($stmt && $stmt->rowCount() === 0) {
                $db->exec("ALTER TABLE users ADD COLUMN patient_id VARCHAR(20) NULL AFTER id");
                try {
                    $db->exec("ALTER TABLE users ADD UNIQUE KEY `patient_id` (`patient_id`)");
                } catch (PDOException $e) {
                    error_log("Note: patient_id index may already exist: " . $e->getMessage());
                }
            }
        } catch (PDOException $e) {
            error_log("Failed to ensure users.patient_id column: " . $e->getMessage());
        }
    }
}

// Function to generate patient ID in format: PAT-YYYYMMDD-NNNN
if (!function_exists('generatePatientId')) {
    function generatePatientId(PDO $db): string {
        $prefix = 'PAT';
        $date = date('Ymd');
        $datePart = $date;
        
        $query = "SELECT patient_id FROM users 
                  WHERE patient_id LIKE :pattern 
                  ORDER BY patient_id DESC 
                  LIMIT 1";
        $pattern = $prefix . '-' . $datePart . '-%';
        $stmt = $db->prepare($query);
        $stmt->bindParam(':pattern', $pattern);
        $stmt->execute();
        
        $lastId = $stmt->fetchColumn();
        $nextNumber = 1;
        
        if ($lastId) {
            $parts = explode('-', $lastId);
            if (count($parts) === 3 && isset($parts[2])) {
                $lastNumber = (int)$parts[2];
                $nextNumber = $lastNumber + 1;
            }
        }
        
        $autoIncrement = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        return $prefix . '-' . $datePart . '-' . $autoIncrement;
    }
}

// Log registration activity
function logRegistrationActivity(PDO $db, int $patient_id, string $action, ?string $notes = null, ?int $performed_by = null): bool {
    try {
        // Ensure registration_logs table exists
        $db->exec("CREATE TABLE IF NOT EXISTS registration_logs (
            id int(11) NOT NULL AUTO_INCREMENT,
            patient_id int(11) NOT NULL,
            action enum('registered','updated','verified','identity_verified','insurance_verified') NOT NULL,
            performed_by int(11) DEFAULT NULL,
            notes text DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (id),
            KEY patient_id (patient_id),
            KEY performed_by (performed_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $query = "INSERT INTO registration_logs (patient_id, action, performed_by, notes, ip_address) 
                  VALUES (:patient_id, :action, :performed_by, :notes, :ip_address)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':performed_by', $performed_by, PDO::PARAM_INT);
        $stmt->bindParam(':notes', $notes);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt->bindParam(':ip_address', $ip_address);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Failed to log registration activity: " . $e->getMessage());
        return false;
    }
}

// Get patient role ID
function getPatientRoleId(PDO $db): int {
    try {
        $query = "SELECT id FROM roles WHERE role_name = 'patient' LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        return $role ? (int)$role['id'] : 4; // Default to 4 if not found
    } catch (PDOException $e) {
        error_log("Failed to get patient role ID: " . $e->getMessage());
        return 4;
    }
}

// Get patient by ID — checks patients table first, falls back to users table
function getPatientFromUsers(PDO $db, int $patient_id) {
    try {
        // Try patients table first (primary patient records)
        $query = "SELECT p.*, NULL as role_name, NULL as suffix
                  FROM patients p
                  WHERE p.id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $patient_id, PDO::PARAM_INT);
        $stmt->execute();
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($patient) return $patient;

        // Fallback to users table (patient user accounts)
        $query = "SELECT u.*, r.role_name 
                  FROM users u 
                  LEFT JOIN roles r ON u.role_id = r.id 
                  WHERE u.id = :id AND r.role_name = 'patient'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $patient_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to get patient: " . $e->getMessage());
        return false;
    }
}

// Search patients from patients table (with fallback to users table)
function searchPatients(PDO $db, string $search_term = '', string $initial_filter = '', int $limit = 50, int $offset = 0, string $patient_type = '', string $admission_status = '') {
    try {
        // Query patients table (primary patient records)
        $query = "SELECT p.*, NULL as role_name, NULL as suffix
                  FROM patients p
                  WHERE p.status = 'active'";
        
        $params = [];
        if (!empty($search_term)) {
            $query .= " AND (CONCAT(p.first_name, ' ', COALESCE(p.middle_name, ''), ' ', p.last_name) LIKE :search 
                      OR p.first_name LIKE :search 
                      OR p.last_name LIKE :search 
                      OR p.hospital_id LIKE :search 
                      OR p.email LIKE :search 
                      OR p.contact_number LIKE :search)";
            $params[':search'] = "%$search_term%";
        }
        
        if (!empty($initial_filter) && strlen($initial_filter) === 1) {
            $query .= " AND UPPER(SUBSTRING(p.first_name, 1, 1)) = :initial";
            $params[':initial'] = strtoupper($initial_filter);
        }
        
        // Filter by patient type (outpatient/inpatient)
        if (!empty($patient_type) && in_array($patient_type, ['outpatient', 'inpatient'])) {
            $query .= " AND p.patient_type = :patient_type";
            $params[':patient_type'] = $patient_type;
        }
        
        // Filter by admission status (active/admitted/discharged)
        if (!empty($admission_status) && in_array($admission_status, ['active', 'admitted', 'discharged'])) {
            $query .= " AND p.admission_status = :admission_status";
            $params[':admission_status'] = $admission_status;
        }
        
        $query .= " ORDER BY p.first_name ASC, p.last_name ASC LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If patients table has results, return them
        if (!empty($results)) {
            return $results;
        }
        
        // Fallback: query users table for patient role accounts
        $query = "SELECT u.*, r.role_name 
                  FROM users u 
                  LEFT JOIN roles r ON u.role_id = r.id 
                  WHERE r.role_name = 'patient'";
        
        $params = [];
        if (!empty($search_term)) {
            $query .= " AND (CONCAT(u.first_name, ' ', COALESCE(u.middle_name, ''), ' ', u.last_name) LIKE :search 
                      OR u.first_name LIKE :search 
                      OR u.last_name LIKE :search 
                      OR u.email LIKE :search 
                      OR u.contact_number LIKE :search)";
            $params[':search'] = "%$search_term%";
        }
        
        if (!empty($initial_filter) && strlen($initial_filter) === 1) {
            $query .= " AND UPPER(SUBSTRING(u.first_name, 1, 1)) = :initial";
            $params[':initial'] = strtoupper($initial_filter);
        }
        
        $query .= " ORDER BY u.first_name ASC, u.last_name ASC LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to search patients: " . $e->getMessage());
        return [];
    }
}

