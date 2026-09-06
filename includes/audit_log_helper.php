<?php
/**
 * Audit Log Helper Functions
 */

if (!function_exists('logDepartmentAccountAuditEvent')) {
    function logDepartmentAccountAuditEvent(PDO $db, array $user, string $action, string $details = '') {
        try {
            $employee_id = $user['employee_id'] ?? null;
            $ip_address = getClientIpAddress();
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            // For login/logout actions, use 'authentication' as table_name
            $table_name = 'authentication';
            $record_id = null; // No specific record for login/logout
            
            // Prepare the new_values JSON
            $new_values = json_encode([
                'details' => $details,
                'employee_id' => $employee_id,
                'ip_address' => $ip_address,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            $query = "INSERT INTO audit_logs 
                     (employee_id, action, table_name, record_id, ip_address, user_agent, new_values, created_at)
                     VALUES (:employee_id, :action, :table_name, :record_id, :ip_address, :user_agent, :new_values, NOW())";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':employee_id', $employee_id);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':table_name', $table_name);
            $stmt->bindParam(':record_id', $record_id);
            $stmt->bindParam(':ip_address', $ip_address);
            $stmt->bindParam(':user_agent', $user_agent);
            $stmt->bindParam(':new_values', $new_values);
            
            $result = $stmt->execute();
            
            if (!$result) {
                error_log("Failed to insert audit log: " . implode(", ", $stmt->errorInfo()));
            }
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("Audit log error: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getClientIpAddress')) {
    function getClientIpAddress(): ?string {
        $headerKeys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP'
        ];

        $loopbackIp = null;

        foreach ($headerKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ipList = explode(',', $_SERVER[$key]);
                foreach ($ipList as $candidate) {
                    $ip = trim($candidate);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
                            return $ip;
                        }
                        $loopbackIp = $loopbackIp ?? $ip;
                    }
                }
            }
        }

        $directIp = $_SERVER['REMOTE_ADDR'] ?? null;
        if ($directIp && filter_var($directIp, FILTER_VALIDATE_IP)) {
            if (!in_array($directIp, ['127.0.0.1', '::1'], true)) {
                return $directIp;
            }
            $loopbackIp = $loopbackIp ?? $directIp;
        }

        $fallbackCandidates = [
            $_SERVER['SERVER_ADDR'] ?? null,
            gethostbyname(gethostname())
        ];

        foreach ($fallbackCandidates as $candidate) {
            if ($candidate && filter_var($candidate, FILTER_VALIDATE_IP) && !in_array($candidate, ['127.0.0.1', '::1'], true)) {
                return $candidate;
            }
        }

        return $loopbackIp;
    }
}
?>