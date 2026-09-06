<?php
require_once '../config/config.php';
require_once '../includes/email_helper.php';
require_once '../includes/audit_log_helper.php';

if (!function_exists('maskEmail')) {
    function maskEmail($email) {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        [$localPart, $domainPart] = explode('@', $email, 2);

        $maskSegment = function ($segment) {
            $length = strlen($segment);
            if ($length <= 2) {
                return substr($segment, 0, 1) . str_repeat('*', max(0, $length - 1));
            }
            return substr($segment, 0, 1) . str_repeat('*', $length - 2) . substr($segment, -1);
        };

        $domainParts = explode('.', $domainPart);
        $domainName = array_shift($domainParts);
        $maskedDomainName = $maskSegment($domainName);
        $maskedDomain = $maskedDomainName . (!empty($domainParts) ? '.' . implode('.', $domainParts) : '');

        return $maskSegment($localPart) . '@' . $maskedDomain;
    }
}

function initializeLoginController(PDO $db, array $config): array {
    $defaults = [
        'context' => 'standard',
        'redirect' => 'login.php',
        'identifier_field' => 'username',
        'empty_identifier_message' => 'Please enter your credentials.',
        'invalid_credentials_message' => 'Invalid credentials.',
        'inactive_error_message' => 'Your account is inactive. Please contact an administrator.',
        'role_mapping' => [
            'admin' => 1,
            'doctor' => 2,
            'nurse' => 3,
            'receptionist' => 4,
            'appointment_coordinator' => 5,
            'billing_staff' => 6,
            'patient' => 7
        ],
        'default_role' => 'patient',
        'default_role_id' => 7,
        'fetch_user' => null,
        'source_table' => 'users',
        'source_identifier_field' => 'id',
    ];

    $config = array_merge($defaults, $config);

    if (!is_callable($config['fetch_user'])) {
        throw new InvalidArgumentException('fetch_user callback is required.');
    }

    if (isset($_SESSION['user_id'])) {
        header("Location: ../index.php");
        exit();
    }

    if (isset($_POST['cancel_verification'])) {
        // Clear all verification-related session data
        unset($_SESSION['pending_user']);
        unset($_SESSION['verification_code']);
        unset($_SESSION['verification_code_expiry']);
        unset($_SESSION['verification_email']);
        unset($_SESSION['otp_last_sent']);
        unset($_SESSION['show_verification_modal']);
        unset($_SESSION['resend_cooldown_active']);
        unset($_SESSION['resend_cooldown_start']);
        unset($_SESSION['otp_fallback_code']);
        unset($_SESSION['otp_fallback_message']);
        header("Location: " . $config['redirect']);
        exit();
    }

    if (isset($_POST['resend_otp'])) {
        handleResendOtpRequest($config['redirect']);
    }

    if (isset($_POST['verify_code'])) {
        handleOtpVerificationSubmission($db, $config['redirect']);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['verify_code']) && !isset($_POST['resend_otp']) && !isset($_POST['cancel_verification'])) {
        processPrimaryLoginAttempt($db, $config);
    }

    $resendCountdownShouldStart = isset($_SESSION['resend_cooldown_active']);
    $resendCountdownStartTime = $_SESSION['resend_cooldown_start'] ?? null;
    if ($resendCountdownShouldStart) {
        unset($_SESSION['resend_cooldown_active']);
    }

    return [
        'resendCountdownShouldStart' => $resendCountdownShouldStart,
        'resendCountdownStartTime' => $resendCountdownStartTime,
    ];
}

function processPrimaryLoginAttempt(PDO $db, array $config): void {
    $identifier = sanitizeInput($_POST[$config['identifier_field']] ?? '');
    $password = isset($_POST['password']) ? trim((string)$_POST['password']) : '';

    if (empty($identifier) || empty($password)) {
        $_SESSION['error'] = $config['empty_identifier_message'];
        return;
    }

    $user = call_user_func($config['fetch_user'], $db, $identifier);
    if (!$user) {
        $_SESSION['error'] = $config['invalid_credentials_message'];
        return;
    }

    $userStatus = $user['status'] ?? null;
    if ($userStatus !== null && $userStatus !== 'active') {
        $_SESSION['error'] = $config['inactive_error_message'];
        return;
    }

    if (!verifyUserPassword($password, $user['password'] ?? null)) {
        $_SESSION['error'] = $config['invalid_credentials_message'];
        return;
    }

    $roleMeta = resolveUserRoleMetadata($db, $user, $config);
    $sourceIdentifierField = $config['source_identifier_field'] ?? 'id';
    $sourceIdentifierValue = $user[$sourceIdentifierField] ?? ($user['id'] ?? null);

    $_SESSION['pending_user'] = [
        'id' => $user['id'] ?? $sourceIdentifierValue,
        'username' => $user['username'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email'] ?? '',
        'role_id' => $roleMeta['role_id'],
        'role_name' => $roleMeta['role_name'],
        'employee_id' => $user['employee_id'] ?? null,
        'login_context' => $config['context'] ?? 'standard',
        'source_table' => $config['source_table'] ?? 'users',
        'source_identifier_field' => $sourceIdentifierField,
        'source_identifier_value' => $sourceIdentifierValue,
    ];

    triggerOtpForPendingUser($user);
}

function resolveUserRoleMetadata(PDO $db, array $user, array $config): array {
    $role_id = $user['role_id'] ?? null;
    $role_name = $user['role'] ?? $config['default_role'];
    $role_lookup_key = strtolower(str_replace(' ', '_', $role_name));

    if (!empty($user['role_id'])) {
        try {
            $role_query = "SELECT role_name FROM roles WHERE id = :role_id";
            $role_stmt = $db->prepare($role_query);
            $role_stmt->bindParam(':role_id', $user['role_id']);
            $role_stmt->execute();
            $role_data = $role_stmt->fetch();
            if ($role_data) {
                $role_name = $role_data['role_name'];
            }
            $role_id = $user['role_id'];
        } catch (PDOException $e) {
            error_log("Roles lookup failed: " . $e->getMessage());
        }
    } else {
        $role_id = $config['role_mapping'][$role_lookup_key] ?? $config['default_role_id'];
    }

    return [
        'role_id' => $role_id,
        'role_name' => $role_name,
    ];
}

if (!function_exists('saveOtpToDatabase')) {
    function saveOtpToDatabase(PDO $db, string $code, string $destination, string $sourceTable, int|string|null $userId = null, int|string|null $departmentAccountId = null, string $purpose = 'login'): ?int {
        try {
            $expiresAt = date('Y-m-d H:i:s', time() + (10 * 60)); // 10 minutes from now
            
            $stmt = $db->prepare("
                INSERT INTO otp_codes (code, user_id, department_account_id, source_table, destination, purpose, expires_at, attempts, created_at)
                VALUES (:code, :user_id, :department_account_id, :source_table, :destination, :purpose, :expires_at, 0, NOW())
            ");
            
            $stmt->bindValue(':code', $code, PDO::PARAM_STR);
            
            // Handle user_id - can be int or null
            if ($userId !== null) {
                $stmt->bindValue(':user_id', is_numeric($userId) ? (int)$userId : $userId, is_numeric($userId) ? PDO::PARAM_INT : PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':user_id', null, PDO::PARAM_NULL);
            }
            
            // Handle department_account_id - can be int, string, or null
            if ($departmentAccountId !== null) {
                $stmt->bindValue(':department_account_id', is_numeric($departmentAccountId) ? (int)$departmentAccountId : $departmentAccountId, is_numeric($departmentAccountId) ? PDO::PARAM_INT : PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':department_account_id', null, PDO::PARAM_NULL);
            }
            
            $stmt->bindValue(':source_table', $sourceTable, PDO::PARAM_STR);
            $stmt->bindValue(':destination', $destination, PDO::PARAM_STR);
            $stmt->bindValue(':purpose', $purpose, PDO::PARAM_STR);
            $stmt->bindValue(':expires_at', $expiresAt, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                return (int)$db->lastInsertId();
            }
        } catch (PDOException $e) {
            error_log("Failed to save OTP to database: " . $e->getMessage());
        }
        return null;
    }
}

if (!function_exists('markOtpAsConsumed')) {
    function markOtpAsConsumed(PDO $db, string $code, string $destination): bool {
        try {
            $stmt = $db->prepare("
                UPDATE otp_codes 
                SET consumed_at = NOW() 
                WHERE code = :code 
                AND destination = :destination 
                AND consumed_at IS NULL 
                AND expires_at > NOW()
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            
            $stmt->bindValue(':code', $code, PDO::PARAM_STR);
            $stmt->bindValue(':destination', $destination, PDO::PARAM_STR);
            
            return $stmt->execute() && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Failed to mark OTP as consumed: " . $e->getMessage());
            return false;
        }
    }
}

function triggerOtpForPendingUser(array $user): void {
    global $db;
    
    $verification_code = generateVerificationCode();
    $code_expiry = time() + (10 * 60);

    $_SESSION['verification_code'] = $verification_code;
    $_SESSION['verification_code_expiry'] = $code_expiry;
    $_SESSION['otp_last_sent'] = time();
    $_SESSION['show_verification_modal'] = true;

    $pending_user = $_SESSION['pending_user'] ?? [];
    $user_email = $pending_user['email'] ?? '';
    $user_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['username'] ?? 'User');
    $fallbackMessage = null;
    
    // Determine source table and IDs
    $sourceTable = $pending_user['source_table'] ?? 'department_accounts';
    $userId = null;
    $departmentAccountId = null;
    
    if ($sourceTable === 'users') {
        $userId = $pending_user['id'] ?? null;
    } else {
        $departmentAccountId = $pending_user['source_identifier_value'] ?? $pending_user['employee_id'] ?? null;
    }

    if (!empty($user_email)) {
        // Save OTP to database
        saveOtpToDatabase($db, $verification_code, $user_email, $sourceTable, $userId, $departmentAccountId, 'login');
        
        $email_result = sendVerificationCodeEmail($user_email, $user_name, $verification_code);
        if (!$email_result['success']) {
            error_log("Failed to send verification email: " . $email_result['message']);
            $_SESSION['error'] = "Failed to send verification code. Please contact support.";
            $_SESSION['show_verification_modal'] = true;
            $fallbackMessage = "Email delivery failed. Use this one-time code to continue.";
        } else {
            $_SESSION['verification_email'] = $user_email;
            unset($_SESSION['otp_fallback_code'], $_SESSION['otp_fallback_message']);
        }
    } else {
        $_SESSION['error'] = "No email address found for your account. Please contact support.";
        $_SESSION['show_verification_modal'] = true;
        $fallbackMessage = "No email on file. Use this one-time code to continue.";
    }

    if ($fallbackMessage !== null) {
        $_SESSION['otp_fallback_code'] = $verification_code;
        $_SESSION['otp_fallback_message'] = $fallbackMessage;
    }
}

function handleResendOtpRequest(string $redirectPath): void {
    global $db;
    
    if (!isset($_SESSION['pending_user'])) {
        $_SESSION['error'] = "Session expired. Please login again.";
        $_SESSION['show_verification_modal'] = true;
        header("Location: {$redirectPath}");
        exit();
    }

    unset($_SESSION['error']);

    $verification_code = generateVerificationCode();
    $code_expiry = time() + (10 * 60);
    $current_time = time();

    $_SESSION['verification_code'] = $verification_code;
    $_SESSION['verification_code_expiry'] = $code_expiry;
    $_SESSION['otp_last_sent'] = $current_time;
    $_SESSION['resend_cooldown_start'] = $current_time;
    $_SESSION['resend_cooldown_active'] = true;

    $pending_user = $_SESSION['pending_user'];
    $user_email = $pending_user['email'] ?? '';
    $user_name = trim(($pending_user['first_name'] ?? '') . ' ' . ($pending_user['last_name'] ?? '')) ?: ($pending_user['username'] ?? 'User');
    
    // Determine source table and IDs
    $sourceTable = $pending_user['source_table'] ?? 'department_accounts';
    $userId = null;
    $departmentAccountId = null;
    
    if ($sourceTable === 'users') {
        $userId = $pending_user['id'] ?? null;
    } else {
        $departmentAccountId = $pending_user['source_identifier_value'] ?? $pending_user['employee_id'] ?? null;
    }

    if (!empty($user_email)) {
        // Save OTP to database
        saveOtpToDatabase($db, $verification_code, $user_email, $sourceTable, $userId, $departmentAccountId, 'login');
        
        $email_result = sendVerificationCodeEmail($user_email, $user_name, $verification_code);
        if (!$email_result['success']) {
            error_log("Failed to resend verification email: " . $email_result['message']);
            $_SESSION['error'] = "Failed to resend verification code. Please try again.";
            $_SESSION['show_verification_modal'] = true;
        } else {
            $_SESSION['success_message'] = "A new verification code has been sent to your email.";
            $_SESSION['show_verification_modal'] = true;
            $_SESSION['verification_email'] = $user_email;
        }
    } else {
        $_SESSION['error'] = "No email address found for your account. Please contact support.";
        $_SESSION['show_verification_modal'] = true;
    }

    header("Location: {$redirectPath}");
    exit();
}

function handleOtpVerificationSubmission(PDO $db, string $redirectPath): void {
    $entered_code = '';
    if (isset($_POST['code1'], $_POST['code2'], $_POST['code3'], $_POST['code4'], $_POST['code5'], $_POST['code6'])) {
        $entered_code = sanitizeInput($_POST['code1'] . $_POST['code2'] . $_POST['code3'] . $_POST['code4'] . $_POST['code5'] . $_POST['code6']);
    } elseif (isset($_POST['verification_code'])) {
        $entered_code = sanitizeInput($_POST['verification_code']);
    }

    $entered_code = trim((string)$entered_code);
    $stored_code = isset($_SESSION['verification_code']) ? trim((string)$_SESSION['verification_code']) : null;
    $code_expiry = $_SESSION['verification_code_expiry'] ?? null;

    if (empty($entered_code)) {
        $_SESSION['error'] = "Please enter the verification code.";
        $_SESSION['show_verification_modal'] = true;
    } elseif (!$stored_code) {
        $_SESSION['error'] = "No verification code found. Please login again.";
        $_SESSION['show_verification_modal'] = true;
        clearPendingVerificationState();
    } elseif ($code_expiry === null || time() > $code_expiry) {
        $_SESSION['error'] = "Verification code has expired. Please login again.";
        $_SESSION['show_verification_modal'] = true;
        clearPendingVerificationState();
    } elseif ($entered_code !== $stored_code) {
        $_SESSION['error'] = "Wrong OTP. Please try again.";
        $_SESSION['show_verification_modal'] = true;
    } else {
        // Mark OTP as consumed in database
        $pending_user = $_SESSION['pending_user'] ?? [];
        $user_email = $pending_user['email'] ?? '';
        if (!empty($user_email)) {
            markOtpAsConsumed($db, $entered_code, $user_email);
        }
        
        finalizeUserLogin($db);
        return;
    }

    header("Location: {$redirectPath}");
    exit();
}

function clearPendingVerificationState(): void {
    unset($_SESSION['pending_user'], $_SESSION['verification_code'], $_SESSION['verification_code_expiry']);
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

function finalizeUserLogin(PDO $db): void {
    $pending_user = $_SESSION['pending_user'];
    $loginContext = $pending_user['login_context'] ?? 'standard';
    $_SESSION['login_context'] = $loginContext;

    $_SESSION['user_id'] = $pending_user['id'];
    $_SESSION['username'] = $pending_user['username'];
    $_SESSION['first_name'] = $pending_user['first_name'];
    $_SESSION['last_name'] = $pending_user['last_name'];
    $_SESSION['role_id'] = $pending_user['role_id'];
    $_SESSION['role_name'] = $pending_user['role_name'];
    $_SESSION['user_role'] = $pending_user['role_name'];

    if (isset($pending_user['employee_id'])) {
        $_SESSION['employee_id'] = $pending_user['employee_id'];
    }

    $sourceTable = $pending_user['source_table'] ?? 'users';
    $sourceField = $pending_user['source_identifier_field'] ?? 'id';
    $sourceValue = $pending_user['source_identifier_value'] ?? $pending_user['id'];

    $_SESSION['source_table'] = $sourceTable;
    $_SESSION['source_identifier_field'] = $sourceField;
    $_SESSION['source_identifier_value'] = $sourceValue;

    updateSourceLastLogin($db, $sourceTable, $sourceField, $sourceValue);

    // Log the login action to audit_logs
    logDepartmentAccountAuditEvent(
        $db,
        $pending_user,
        'login',
        sprintf('Employee %s logged in successfully', $pending_user['employee_id'] ?? 'employee')
    );

    unset($_SESSION['pending_user'], $_SESSION['verification_code'], $_SESSION['verification_code_expiry'], $_SESSION['otp_last_sent']);

    $userFullName = trim(($pending_user['first_name'] ?? '') . ' ' . ($pending_user['last_name'] ?? '')) ?: ($pending_user['username'] ?? 'there');
    $_SESSION['success'] = "Welcome back! 🎉 " . $userFullName . "! You have successfully logged in.";
    header("Location: ../index.php");
    exit();
}

function verifyUserPassword(string $inputPassword, ?string $storedPassword): bool {
    if ($storedPassword === null || $storedPassword === '') {
        return false;
    }

    $storedPassword = trim((string)$storedPassword);
    $isPasswordHash = preg_match('/^\$(2y|2a|2b|argon2i|argon2id|argon2|P)\$/', $storedPassword) === 1;

    if ($isPasswordHash) {
        return password_verify($inputPassword, $storedPassword);
    }

    return hash_equals($storedPassword, $inputPassword);
}

function tableHasColumn(PDO $db, string $table, string $column): bool {
    static $tableColumnCache = [];
    $sanitizedTable = preg_replace('/[^A-Za-z0-9_]/', '', $table);
    $cacheKey = "{$sanitizedTable}.{$column}";

    if (array_key_exists($cacheKey, $tableColumnCache)) {
        return $tableColumnCache[$cacheKey];
    }

    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM `{$sanitizedTable}` LIKE :column_name");
        $stmt->bindParam(':column_name', $column);
        $stmt->execute();
        $tableColumnCache[$cacheKey] = $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Failed to inspect {$sanitizedTable}.{$column}: " . $e->getMessage());
        $tableColumnCache[$cacheKey] = false;
    }

    return $tableColumnCache[$cacheKey];
}

function userTableHasColumn(PDO $db, string $column): bool {
    return tableHasColumn($db, 'users', $column);
}

function updateSourceLastLogin(PDO $db, string $table, string $field, $value): void {
    if ($value === null) {
        return;
    }

    $sanitizedTable = preg_replace('/[^A-Za-z0-9_]/', '', $table);
    $sanitizedField = preg_replace('/[^A-Za-z0-9_]/', '', $field);

    try {
        $query = "UPDATE `{$sanitizedTable}` SET last_login = NOW() WHERE `{$sanitizedField}` = :identifier";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':identifier', $value);
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Failed to update {$sanitizedTable} last_login: " . $e->getMessage());
    }
}

$departmentTable = 'department_accounts';
$departmentTableSafe = preg_replace('/[^A-Za-z0-9_]/', '', $departmentTable);
$departmentIdentifierColumns = [];

if (tableHasColumn($db, $departmentTable, 'employee_id')) {
    $departmentIdentifierColumns[] = 'employee_id';
}
if (tableHasColumn($db, $departmentTable, 'employee_email')) {
    $departmentIdentifierColumns[] = 'employee_email';
}
if (tableHasColumn($db, $departmentTable, 'email')) {
    $departmentIdentifierColumns[] = 'email';
}
if (tableHasColumn($db, $departmentTable, 'username')) {
    $departmentIdentifierColumns[] = 'username';
}

if (empty($departmentIdentifierColumns)) {
    $departmentIdentifierColumns[] = 'id';
}

$departmentRoleMapping = [
    'admin' => 1,
    'doctor' => 2,
    'nurse' => 3,
    'staff' => 3,
    'employee' => 3,
    'receptionist' => 4,
    'appointment_coordinator' => 5,
    'billing_staff' => 6,
    'finance_staff' => 6,
    'finance staff' => 6,
    'patient' => 7,
];

$loginControllerState = initializeLoginController($db, [
    'context' => 'employee',
    'redirect' => 'employee-login.php',
    'identifier_field' => 'employee_email',
    'empty_identifier_message' => "Please enter both email and password.",
    'invalid_credentials_message' => "Invalid email or password.",
    'role_mapping' => $departmentRoleMapping,
    'default_role' => 'employee',
    'default_role_id' => 3,
    'source_table' => $departmentTableSafe,
    'source_identifier_field' => 'employee_email',
    'fetch_user' => function(PDO $db, string $identifier) use ($departmentTableSafe, $departmentIdentifierColumns) {
        $conditions = array_map(fn($column) => "{$column} = :identifier", $departmentIdentifierColumns);
        $whereClause = implode(' OR ', $conditions);
        $query = "SELECT * FROM `{$departmentTableSafe}` WHERE {$whereClause} LIMIT 1";

        try {
            $stmt = $db->prepare($query);
            $stmt->bindParam(':identifier', $identifier);
            $stmt->execute();
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($record) {
                $record['id'] = $record['id'] ?? $record['employee_id'] ?? ($record['staff_id'] ?? null);
                $record['username'] = $record['username']
                    ?? $record['employee_email']
                    ?? $record['email']
                    ?? $record['employee_id']
                    ?? ($record['id'] ?? null);

                $record['email'] = $record['employee_email']
                    ?? $record['email']
                    ?? ($record['work_email'] ?? $record['company_email'] ?? null);

                $record['first_name'] = $record['first_name']
                    ?? $record['employee_fname']
                    ?? $record['fname']
                    ?? '';

                $record['last_name'] = $record['last_name']
                    ?? $record['employee_lname']
                    ?? $record['lname']
                    ?? '';

                if ((empty($record['first_name']) || empty($record['last_name'])) && !empty($record['full_name'])) {
                    $nameParts = preg_split('/\s+/', trim($record['full_name']), 2);
                    $record['first_name'] = $record['first_name'] ?: ($nameParts[0] ?? '');
                    if (count($nameParts) === 2) {
                        $record['last_name'] = $record['last_name'] ?: $nameParts[1];
                    }
                }

                $record['role'] = $record['role']
                    ?? $record['role_name']
                    ?? $record['account_type']
                    ?? 'employee';

                $record['role_name'] = $record['role_name'] ?? $record['role'];

                if (!isset($record['status'])) {
                    if (isset($record['is_active'])) {
                        $record['status'] = ((int)$record['is_active'] === 1) ? 'active' : 'inactive';
                    } elseif (isset($record['account_status'])) {
                        $record['status'] = $record['account_status'];
                    }
                }

                $record['employee_id'] = $record['employee_id'] ?? ($record['staff_id'] ?? $record['id'] ?? null);

                if (isset($record['password'])) {
                    $record['password'] = trim($record['password']);
                }
            }

            return $record;
        } catch (PDOException $e) {
            error_log("Department account lookup failed: " . $e->getMessage());
            return null;
        }
    },
]);

$resendCountdownShouldStart = $loginControllerState['resendCountdownShouldStart'];
$resendCountdownStartTime = $loginControllerState['resendCountdownStartTime'];
?>

<!DOCTYPE html>
<html lang="en" class="h-full bg-gradient-to-br from-[#f9f6f1] to-[#f0f4ff]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Login - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/toast.js"></script>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>/assets/img/alvion-emblem-removebg.png">
    <style>
        .login-card {
            box-shadow: 0 30px 60px rgba(16, 24, 40, 0.12);
        }
        .login-input {
            background-color: rgba(255, 255, 255, 0.9);
        }
        .login-input:focus {
            background-color: #ffffff;
        }
        .auto-dismiss {
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordField = document.getElementById('password');
            const togglePasswordBtn = document.getElementById('togglePassword');
            const passwordEyeIcon = document.getElementById('passwordEyeIcon');
            
            if (togglePasswordBtn && passwordField && passwordEyeIcon) {
                togglePasswordBtn.addEventListener('click', function() {
                    const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordField.setAttribute('type', type);
                    
                    if (type === 'password') {
                        passwordEyeIcon.innerHTML = '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle>';
                    } else {
                        passwordEyeIcon.innerHTML = '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path><line x1="2" y1="2" x2="22" y2="22"></line>';
                    }
                });
            }
            
            <?php if (isset($_SESSION['show_verification_modal']) && $_SESSION['show_verification_modal']): ?>
                const modal = document.getElementById('verificationModal');
                if (modal) {
                    modal.classList.remove('hidden');
                    setTimeout(() => {
                        setupCodeInputs();
                    }, 100);
                }
            <?php 
                $error_message = $_SESSION['error'] ?? '';
                $is_otp_error = stripos($error_message, 'OTP') !== false || 
                                stripos($error_message, 'verification') !== false ||
                                stripos($error_message, 'code') !== false;
                
                if (!isset($_SESSION['error']) || !$is_otp_error) {
                    unset($_SESSION['show_verification_modal']);
                }
            endif; ?>
            
            let codeInputsSetup = false;
            function setupCodeInputs() {
                if (codeInputsSetup) return;
                codeInputsSetup = true;
                
                const codeInputs = document.querySelectorAll('.code-input');
                
                <?php if (isset($_SESSION['error']) && isset($_SESSION['show_verification_modal'])): ?>
                    codeInputs.forEach(input => {
                        input.value = '';
                    });
                <?php endif; ?>
                
                codeInputs.forEach((input, index) => {
                    if (input.dataset.listenerAdded === 'true') return;
                    input.dataset.listenerAdded = 'true';
                    
                    input.addEventListener('input', function(e) {
                        e.target.value = e.target.value.replace(/[^0-9]/g, '').slice(0, 1);
                        const allInputs = document.querySelectorAll('.code-input');
                        if (e.target.value.length === 1 && index < allInputs.length - 1) {
                            allInputs[index + 1].focus();
                        }
                    });
                    
                    input.addEventListener('keydown', function(e) {
                        const allInputs = document.querySelectorAll('.code-input');
                        if (e.key === 'Backspace' && e.target.value === '' && index > 0) {
                            allInputs[index - 1].focus();
                        } else if (e.key === 'Enter') {
                            const fullCode = Array.from(allInputs).map(inp => inp.value).join('');
                            if (fullCode.length === allInputs.length) {
                                document.getElementById('fullCode').value = fullCode;
                                document.getElementById('verificationForm').submit();
                            }
                        }
                    });
                });
                
                if (codeInputs.length > 0) {
                    codeInputs[0].focus();
                }
            }
            
            setupCodeInputs();

            // Show toast notifications from PHP session (only if not in verification modal)
            <?php if (isset($_SESSION['error']) && !isset($_SESSION['show_verification_modal'])): ?>
                showToast('error', <?php echo json_encode($_SESSION['error']); ?>);
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
        });
    </script>
</head>
<body class="h-full">
    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 flex flex-col gap-3 pointer-events-none"></div>
    
    <div class="min-h-full flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-5xl bg-white rounded-3xl overflow-hidden login-card">
            <div class="grid grid-cols-1 md:grid-cols-5">
                <div class="relative md:col-span-3 text-white">
                    <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('<?php echo BASE_URL; ?>/assets/img/alvion-building.png');"></div>
                    <div class="relative h-full flex flex-col justify-end">
                        <div class="bg-gradient-to-br from-[#0f172a]/80 via-[#0f766e]/70 to-[#14b8a6]/70 px-10 py-10">
                            <h1 class="text-4xl font-semibold leading-tight">
                                Welcome Back,<br>
                                <span class="text-[#b2f5ea]">Team Member</span>
                            </h1>
                            <p class="mt-6 text-white/85 leading-relaxed">
                                Securely access your staff dashboard, manage patient data, and stay updated with hospital operations.
                            </p>
                            <p class="mt-6 text-sm text-white/70">
                                &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="md:col-span-2 bg-white px-8 py-10 flex flex-col justify-center">
                    <div>
                        <a href="../landing.php" class="inline-flex items-center text-sm text-gray-500 hover:text-[#0f766e] mb-4 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
                            Back to Home
                        </a>
                        <img src="<?php echo BASE_URL; ?>/assets/img/alvion-logo-removebg.png" alt="Alvion logo" class="h-14 md:h-16 mb-4">
                        <h2 class="text-3xl font-semibold text-[#0f766e]">Employee Access</h2>
                        <p class="mt-3 text-sm text-gray-500">Use your employee email to enter the secure portal.</p>
                    </div>

                    <form id="employeeLoginForm" class="mt-8 space-y-5" method="POST">
                        <div>
                            <label for="employee_email" class="block text-sm font-medium text-gray-600 mb-1">Email Address</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#0f766e]">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
                                </span>
                                <input id="employee_email" name="employee_email" type="email" required autocomplete="email"
                                       class="login-input w-full pl-10 pr-3 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#0f766e] focus:border-transparent transition"
                                       placeholder="Enter your email address" value="<?php echo isset($_POST['employee_email']) ? htmlspecialchars($_POST['employee_email']) : ''; ?>">
                            </div>
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-600 mb-1">Password</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#0f766e]">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                </span>
                                <input id="password" name="password" type="password" required autocomplete="current-password"
                                       class="login-input w-full pl-10 pr-10 py-3 rounded-full border border-gray-200 focus:outline-none focus:ring-2 focus:ring-[#0f766e] focus:border-transparent transition"
                                       placeholder="Enter your account password">
                                <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 flex items-center pr-3 text-[#0f766e] hover:text-[#0b4c44] transition-colors">
                                    <svg id="passwordEyeIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </button>
                            </div>
                        </div>
                        

                        

                        <button type="submit" id="loginButton"
                                class="w-full bg-[#0f766e] hover:bg-[#0b4c44] text-white font-semibold py-3 rounded-full shadow-lg transition">
                            Sign in
                        </button>

                        <a href="login.php"
                           class="w-full inline-flex items-center justify-center mt-4 px-4 py-3 border border-[#0f766e]/40 text-[#0f766e] font-semibold rounded-full hover:bg-[#0f766e]/10 transition">
                            Sign in as patient
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Verification Modal same as login -->
    <div id="verificationModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center">
        <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 p-6">
            <!-- Close Button - Cancel Verification -->
            <form method="POST" id="cancelVerificationForm" class="absolute top-4 right-4">
                <input type="hidden" name="cancel_verification" value="1">
                <button type="submit" id="closeModalBtn"
                        class="text-gray-400 hover:text-gray-600 transition-colors p-1 rounded-full hover:bg-gray-100">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </form>
            
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-[#0f766e]/10 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0f766e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                    </svg>
                </div>
                
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Enter Verification Code</h3>
                <p class="text-sm text-gray-600 mb-2">
                    We've sent a 6-digit verification code to<br>
                    <span class="font-semibold text-[#0f766e]">
                        <?php 
                        if (isset($_SESSION['verification_email']) && !empty($_SESSION['verification_email'])) {
                            echo htmlspecialchars(maskEmail($_SESSION['verification_email']));
                        } else {
                            echo 'your email';
                        }
                        ?>
                    </span>
                </p>
                
                <!-- Session Timeout Countdown -->
                <div id="otpTimerContainer" class="mb-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-gray-100 rounded-full">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-500">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        <span id="otpTimerText" class="text-sm font-medium text-gray-600">Code expires in <span id="otpCountdown" class="text-[#0f766e] font-bold">10:00</span></span>
                    </div>
                </div>
                
                <!-- Expired Message (hidden by default) -->
                <div id="otpExpiredMessage" class="hidden mb-4 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg text-sm">
                    <p class="font-semibold">Verification code has expired!</p>
                    <p>Please click "Resend Code" to get a new one.</p>
                </div>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg text-sm auto-dismiss" data-auto-dismiss="true">
                        <?php 
                        $error_msg = $_SESSION['error'];
                        echo htmlspecialchars($error_msg);
                        unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="mb-4 bg-green-50 border border-green-200 text-green-600 px-4 py-3 rounded-lg text-sm auto-dismiss" data-auto-dismiss="true">
                        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['otp_fallback_code'])): ?>
                    <div class="mb-4 bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-lg text-sm">
                        <p class="font-semibold mb-1">Manual verification code</p>
                        <p><?php echo htmlspecialchars($_SESSION['otp_fallback_message'] ?? 'Use this verification code to continue:'); ?></p>
                        <p class="text-2xl font-bold tracking-[0.5em] mt-2"><?php echo htmlspecialchars($_SESSION['otp_fallback_code']); ?></p>
                    </div>
                    <?php unset($_SESSION['otp_fallback_code'], $_SESSION['otp_fallback_message']); ?>
                <?php endif; ?>
                
                <form method="POST" id="verificationForm" class="space-y-6">
                    <div class="flex justify-center gap-3">
                        <input type="text" name="code1" class="code-input w-14 h-14 text-center text-2xl font-bold border-2 border-gray-300 rounded-lg focus:border-[#0f766e] focus:outline-none focus:ring-2 focus:ring-[#0f766e]/20" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" name="code2" class="code-input w-14 h-14 text-center text-2xl font-bold border-2 border-gray-300 rounded-lg focus:border-[#0f766e] focus:outline-none focus:ring-2 focus:ring-[#0f766e]/20" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" name="code3" class="code-input w-14 h-14 text-center text-2xl font-bold border-2 border-gray-300 rounded-lg focus:border-[#0f766e] focus:outline-none focus:ring-2 focus:ring-[#0f766e]/20" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" name="code4" class="code-input w-14 h-14 text-center text-2xl font-bold border-2 border-gray-300 rounded-lg focus:border-[#0f766e] focus:outline-none focus:ring-2 focus:ring-[#0f766e]/20" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" name="code5" class="code-input w-14 h-14 text-center text-2xl font-bold border-2 border-gray-300 rounded-lg focus:border-[#0f766e] focus:outline-none focus:ring-2 focus:ring-[#0f766e]/20" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" name="code6" class="code-input w-14 h-14 text-center text-2xl font-bold border-2 border-gray-300 rounded-lg focus:border-[#0f766e] focus:outline-none focus:ring-2 focus:ring-[#0f766e]/20" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    </div>
                    <input type="hidden" name="verification_code" id="fullCode">
                    <input type="hidden" name="verify_code" value="1">
                    
                    <button type="submit" id="verifyButton" class="w-full bg-[#0f766e] hover:bg-[#0b4c44] text-white font-semibold py-3 rounded-full shadow-lg transition">
                        Verify Code
                    </button>
                </form>
                
                <div class="text-center mt-4">
                    <p class="text-xs text-gray-500 inline-flex items-center flex-wrap justify-center gap-1">
                        <span class="mr-1">Didn't receive the code?</span>
                        <form method="POST" id="resendOtpForm" class="inline-flex items-center ml-1">
                            <input type="hidden" name="resend_otp" value="1">
                            <button type="submit" id="resendOtpBtn" class="text-sm text-[#0f766e] hover:text-[#0b4c44] font-medium disabled:text-gray-400 disabled:cursor-not-allowed transition-colors">
                                Resend Code
                            </button>
                        </form>
                    </p>
                </div>
                
                <!-- Cancel and go back link -->
                <div class="text-center mt-3 pt-3 border-t border-gray-100">
                    <form method="POST" class="inline">
                        <input type="hidden" name="cancel_verification" value="1">
                        <button type="submit" class="text-xs text-gray-400 hover:text-gray-600 transition-colors">
                            Cancel and sign in with different account
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function setButtonLoading(button, text) {
            if (!button || button.dataset.loading === 'true') return;
            button.dataset.loading = 'true';
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.innerHTML = `
                <span class="inline-flex items-center justify-center gap-2">
                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                    </svg>
                    <span>${text}</span>
                </span>
            `;
        }
        
        function resetButtonState(button, originalText) {
            if (!button) return;
            button.dataset.loading = 'false';
            button.disabled = false;
            button.setAttribute('aria-busy', 'false');
            button.innerHTML = originalText;
        }

        // OTP Expiry Countdown Timer
        (function() {
            const otpExpiry = <?php echo isset($_SESSION['verification_code_expiry']) ? (int)$_SESSION['verification_code_expiry'] : 'null'; ?>;
            const serverNow = <?php echo time(); ?>;
            const countdownEl = document.getElementById('otpCountdown');
            const timerContainer = document.getElementById('otpTimerContainer');
            const expiredMessage = document.getElementById('otpExpiredMessage');
            const verifyButton = document.getElementById('verifyButton');
            const codeInputs = document.querySelectorAll('.code-input');
            
            let otpTimerInterval = null;
            let isExpired = false;
            
            function formatTime(seconds) {
                const mins = Math.floor(seconds / 60);
                const secs = seconds % 60;
                return `${mins}:${secs.toString().padStart(2, '0')}`;
            }
            
            function handleExpired() {
                isExpired = true;
                if (timerContainer) timerContainer.classList.add('hidden');
                if (expiredMessage) expiredMessage.classList.remove('hidden');
                if (verifyButton) {
                    verifyButton.disabled = true;
                    verifyButton.classList.add('opacity-50', 'cursor-not-allowed');
                    verifyButton.classList.remove('hover:bg-[#0b4c44]');
                }
                codeInputs.forEach(input => {
                    input.disabled = true;
                    input.classList.add('bg-gray-100', 'cursor-not-allowed');
                });
            }
            
            function startOtpCountdown() {
                if (!otpExpiry || !countdownEl) return;
                
                const updateTimer = () => {
                    const now = Math.floor(Date.now() / 1000);
                    const remaining = otpExpiry - now;
                    
                    if (remaining <= 0) {
                        clearInterval(otpTimerInterval);
                        handleExpired();
                        return;
                    }
                    
                    countdownEl.textContent = formatTime(remaining);
                    
                    // Change color when less than 2 minutes
                    if (remaining <= 120) {
                        countdownEl.classList.remove('text-[#0f766e]');
                        countdownEl.classList.add('text-orange-500');
                    }
                    // Change color when less than 30 seconds
                    if (remaining <= 30) {
                        countdownEl.classList.remove('text-orange-500');
                        countdownEl.classList.add('text-red-500');
                    }
                };
                
                // Initial update
                updateTimer();
                
                // Update every second
                otpTimerInterval = setInterval(updateTimer, 1000);
            }
            
            // Start countdown if modal is visible
            const modal = document.getElementById('verificationModal');
            if (modal && !modal.classList.contains('hidden') && otpExpiry) {
                startOtpCountdown();
            }
            
            // Also start when modal becomes visible
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        if (!modal.classList.contains('hidden') && otpExpiry && !otpTimerInterval) {
                            startOtpCountdown();
                        }
                    }
                });
            });
            
            if (modal) {
                observer.observe(modal, { attributes: true });
            }
        })();

        // Verification Form Handler
        const verificationForm = document.getElementById('verificationForm');
        const verifyButton = document.getElementById('verifyButton');
        const verifyButtonOriginalText = verifyButton ? verifyButton.innerHTML : '';
        
        if (verificationForm) {
            verificationForm.addEventListener('submit', function(e) {
                const codeInputs = document.querySelectorAll('.code-input');
                const fullCode = Array.from(codeInputs).map(input => input.value || '').join('');
                
                if (fullCode.length !== 6) {
                    e.preventDefault();
                    // Show inline error instead of alert
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'mb-4 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg text-sm';
                    errorDiv.textContent = 'Please enter all 6 digits of the verification code.';
                    const existingError = verificationForm.parentNode.querySelector('.bg-red-50');
                    if (existingError) existingError.remove();
                    verificationForm.parentNode.insertBefore(errorDiv, verificationForm);
                    setTimeout(() => errorDiv.remove(), 5000);
                    return false;
                }
                
                document.getElementById('fullCode').value = fullCode;
                setButtonLoading(verifyButton, 'Verifying...');
            });
        }

        // Login Form Handler
        const loginForm = document.getElementById('employeeLoginForm');
        const loginButton = document.getElementById('loginButton');
        if (loginForm && loginButton) {
            loginForm.addEventListener('submit', function() {
                setButtonLoading(loginButton, 'Authenticating...');
            });
        }
        
        // Resend OTP Cooldown Handler
        (function() {
            const resendBtn = document.getElementById('resendOtpBtn');
            const resendForm = document.getElementById('resendOtpForm');
            const cooldown = 60;
            let remaining = 0;
            let countdownInterval = null;
            const originalText = resendBtn ? resendBtn.textContent.trim() : '';
            
            function setButtonText(text) {
                if (!resendBtn) return;
                resendBtn.textContent = text;
            }
            
            function resetButton() {
                if (!resendBtn) return;
                resendBtn.disabled = false;
                setButtonText(originalText || 'Resend Code');
            }
            
            function startCountdown(seconds) {
                if (!resendBtn) return;
                remaining = seconds;
                resendBtn.disabled = true;
                setButtonText(`Wait ${remaining}s`);
                
                if (countdownInterval) {
                    clearInterval(countdownInterval);
                }
                
                countdownInterval = setInterval(() => {
                    remaining--;
                    if (remaining > 0) {
                        setButtonText(`Wait ${remaining}s`);
                    } else {
                        clearInterval(countdownInterval);
                        countdownInterval = null;
                        resetButton();
                    }
                }, 1000);
            }
            
            const resumeCountdown = <?php echo $resendCountdownShouldStart && $resendCountdownStartTime ? 'true' : 'false'; ?>;
            const serverStartTime = <?php echo $resendCountdownStartTime ? (int)$resendCountdownStartTime : 'null'; ?>;
            const serverNow = <?php echo time(); ?>;
            
            if (resumeCountdown && serverStartTime) {
                const elapsed = serverNow - serverStartTime;
                const remainingTime = Math.max(0, cooldown - elapsed);
                if (remainingTime > 0) {
                    startCountdown(remainingTime);
                } else {
                    resetButton();
                }
            }
            
            if (resendForm) {
                resendForm.addEventListener('submit', function(e) {
                    // Reload page after resend to get new expiry time
                    setButtonLoading(resendBtn, 'Sending...');
                });
            }
        })();
    </script>
</body>
</html>