<?php

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

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/email_helper.php';
require_once __DIR__ . '/../includes/audit_log_helper.php';

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

    if (isset($_POST['resend_otp'])) {
        handleResendOtpRequest($config['redirect']);
    }

    if (isset($_POST['verify_code'])) {
        handleOtpVerificationSubmission($db, $config['redirect']);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['verify_code']) && !isset($_POST['resend_otp'])) {
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
    
    // Determine source table and IDs
    $sourceTable = $pending_user['source_table'] ?? 'users';
    $userId = null;
    $departmentAccountId = null;
    
    if ($sourceTable === 'users') {
        $userId = $pending_user['id'] ?? null;
    } else {
        $departmentAccountId = $pending_user['source_identifier_value'] ?? $pending_user['employee_id'] ?? null;
    }

    // Save OTP to database
    if (!empty($user_email)) {
        saveOtpToDatabase($db, $verification_code, $user_email, $sourceTable, $userId, $departmentAccountId, 'login');
        
        $email_result = sendVerificationCodeEmail($user_email, $user_name, $verification_code);
        if (!$email_result['success']) {
            error_log("Failed to send verification email: " . $email_result['message']);
            $_SESSION['error'] = "Failed to send verification code. Please contact support.";
            $_SESSION['show_verification_modal'] = true;
        } else {
            $_SESSION['verification_email'] = $user_email;
        }
    } else {
        $_SESSION['error'] = "No email address found for your account. Please contact support.";
        $_SESSION['show_verification_modal'] = true;
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
    $sourceTable = $pending_user['source_table'] ?? 'users';
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

    logDepartmentAccountAuditEvent(
        $db,
        $pending_user,
        'login',
        sprintf('Employee %s logged in', $pending_user['employee_id'] ?? 'employee')
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

$loginControllerState = initializeLoginController($db, [
    'context' => 'standard',
    'redirect' => 'login.php',
    'identifier_field' => 'username',
    'empty_identifier_message' => "Please enter both username/email and password.",
    'invalid_credentials_message' => "Invalid username or password.",
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
    'source_table' => 'users',
    'source_identifier_field' => 'id',
    'fetch_user' => function(PDO $db, string $identifier) {
        $query = "SELECT * FROM users WHERE username = :identifier OR email = :identifier";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':identifier', $identifier);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    },
]);

$resendCountdownShouldStart = $loginControllerState['resendCountdownShouldStart'];
$resendCountdownStartTime = $loginControllerState['resendCountdownStartTime'];
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>Login - <?php echo SITE_NAME; ?></title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/toast.js"></script>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>/assets/img/alvion-emblem-removebg.png">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif']
                    },
                    colors: {
                        alvion: {
                            50: '#effcfb',
                            100: '#d7f7f4',
                            200: '#b0eee9',
                            500: '#0f9d94',
                            600: '#07877f',
                            700: '#08716c',
                            900: '#124f4d'
                        }
                    },
                    boxShadow: {
                        panel: '0 24px 70px rgba(15, 118, 110, 0.14)',
                        soft: '0 10px 30px rgba(15, 118, 110, 0.10)'
                    }
                }
            }
        };
    </script>

    <style>
        :root {
            --alvion: #07877f;
            --alvion-dark: #066b65;
            --ink: #102a2a;
            --muted: #667b7b;
            --line: #dce8e7;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
        }

        body {
            margin: 0;
            color: var(--ink);
            background:
                radial-gradient(circle at 8% 18%, rgba(15, 157, 148, .10), transparent 28%),
                radial-gradient(circle at 92% 82%, rgba(84, 110, 122, .09), transparent 30%),
                linear-gradient(135deg, #f2fbfa 0%, #f8fbfc 52%, #eef5f7 100%);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .login-shell {
            min-height: 100svh;
            padding: 24px;
        }

        .login-card {
            width: min(1120px, 100%);
            min-height: min(700px, calc(100svh - 48px));
            border: 1px solid rgba(255, 255, 255, .85);
            box-shadow: 0 28px 80px rgba(15, 118, 110, .14);
        }

        .hero-panel {
            position: relative;
            overflow: hidden;
            isolation: isolate;
            min-height: 100%;
            background: #0f7772;
        }

        .hero-image {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(180deg, rgba(7, 96, 91, .03) 0%, rgba(5, 77, 74, .10) 45%, rgba(4, 68, 65, .72) 100%),
                url('<?php echo BASE_URL; ?>/assets/img/login-doc.png');
            background-size: cover;
            background-position: center;
            transform: scale(1.015);
        }

        .hero-panel::after {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -1;
            background: linear-gradient(135deg, rgba(4, 83, 79, .05), rgba(5, 90, 85, .35));
        }

        .hero-content {
            position: relative;
            z-index: 2;
            min-height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 38px;
        }

        .hero-bottom {
            max-width: 560px;
            padding-top: 170px;
        }

        .brand-mark {
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(255,255,255,.28);
            background: rgba(255,255,255,.14);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 14px;
        }

        .form-panel {
            display: flex;
            align-items: center;
            background: rgba(255,255,255,.96);
        }

        .form-inner {
            width: min(430px, 100%);
            margin: 0 auto;
        }

        .field {
            position: relative;
        }

        .field-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c8a89;
            pointer-events: none;
            transition: color .2s ease;
        }

        .field:focus-within .field-icon {
            color: var(--alvion);
        }

        .login-input {
            width: 100%;
            height: 54px;
            border: 1px solid var(--line);
            border-radius: 15px;
            background: #fbfdfd;
            color: var(--ink);
            padding: 0 48px 0 48px;
            outline: none;
            transition: border-color .2s ease, box-shadow .2s ease, background .2s ease, transform .2s ease;
        }

        .login-input::placeholder {
            color: #9aabab;
        }

        .login-input:hover {
            background: #fff;
            border-color: #c8d9d8;
        }

        .login-input:focus {
            background: #fff;
            border-color: var(--alvion);
            box-shadow: 0 0 0 4px rgba(7, 135, 127, .10);
        }

        .primary-btn {
            position: relative;
            height: 54px;
            border-radius: 15px;
            background: linear-gradient(135deg, #07968d, #078077);
            box-shadow: 0 12px 24px rgba(7, 135, 127, .20);
            transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
        }

        .primary-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 15px 28px rgba(7, 135, 127, .26);
            filter: brightness(1.02);
        }

        .primary-btn:active {
            transform: translateY(0);
        }

        .secondary-btn {
            height: 54px;
            border: 1px solid #b8d9d7;
            border-radius: 15px;
            color: #087b75;
            background: #fff;
            transition: background .2s ease, border-color .2s ease, transform .2s ease;
        }

        .secondary-btn:hover {
            background: #effaf9;
            border-color: #83c5c1;
            transform: translateY(-1px);
        }

        .legal-box {
            border: 1px solid #d7e8f6;
            background: #f4f9ff;
            border-radius: 14px;
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #9aa9a9;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .12em;
        }

        .divider::before,
        .divider::after {
            content: "";
            height: 1px;
            flex: 1;
            background: #e5ecec;
        }

        .otp-backdrop {
            background: rgba(7, 28, 28, .58);
            backdrop-filter: blur(7px);
            -webkit-backdrop-filter: blur(7px);
        }

        .otp-modal {
            width: min(460px, calc(100vw - 32px));
            border: 1px solid rgba(255,255,255,.75);
            box-shadow: 0 30px 100px rgba(0,0,0,.24);
        }

        .code-input {
            width: 54px;
            height: 60px;
            border: 1.5px solid #d7e3e2;
            border-radius: 14px;
            background: #fbfdfd;
            color: var(--ink);
            outline: none;
            transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
        }

        .code-input:focus {
            border-color: var(--alvion);
            box-shadow: 0 0 0 4px rgba(7,135,127,.10);
            transform: translateY(-1px);
        }

        .auto-dismiss {
            transition: opacity .3s ease, transform .3s ease;
        }

        @media (max-width: 767px) {
            .login-shell {
                padding: 14px;
            }

            .login-card {
                min-height: auto;
            }

            .hero-panel {
                min-height: 285px;
            }

            .hero-content {
                padding: 26px;
            }

            .hero-bottom {
                padding-top: 80px;
            }

            .hero-title {
                font-size: 2rem;
            }

            .form-panel {
                padding: 32px 22px;
            }

            .code-input {
                width: 46px;
                height: 54px;
            }
        }

        @media (max-width: 390px) {
            .login-shell {
                padding: 8px;
            }

            .form-panel {
                padding: 28px 16px;
            }

            .code-input {
                width: 41px;
                height: 50px;
            }
        }

        @media (max-height: 760px) and (min-width: 768px) {
            .login-shell {
                padding: 16px;
            }

            .login-card {
                min-height: calc(100svh - 32px);
            }

            .hero-content {
                padding: 30px;
            }

            .hero-bottom {
                padding-top: 100px;
            }

            .form-panel {
                padding-top: 26px;
                padding-bottom: 26px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
            }
        }
    </style>
</head>

<body>
    <div id="toast-container" class="fixed top-4 right-4 z-[100] flex flex-col gap-3 pointer-events-none"></div>

    <main class="login-shell flex items-center justify-center">
        <section class="login-card grid grid-cols-1 md:grid-cols-5 overflow-hidden rounded-[28px] bg-white">

            <!-- Brand / Visual panel -->
            <div class="hero-panel md:col-span-3 text-white">
                <div class="hero-image" aria-hidden="true"></div>

                <div class="hero-content">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="brand-mark">
                                <img
                                    src="<?php echo BASE_URL; ?>/assets/img/alvion-emblem-removebg.png"
                                    alt=""
                                    class="h-7 w-7 object-contain"
                                >
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[.24em] text-white/70">
                                    <?php echo SITE_SHORT_NAME; ?>
                                </p>
                                <p class="text-sm font-medium text-white/90">Healthcare Management</p>
                            </div>
                        </div>
                    </div>

                    <div class="hero-bottom">
                        <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-medium text-white/90 backdrop-blur-md">
                            <span class="h-1.5 w-1.5 rounded-full bg-[#a7f3d0] shadow-[0_0_10px_rgba(167,243,208,.8)]"></span>
                            Trusted healthcare platform
                        </div>

                        <h1 class="hero-title text-4xl font-semibold leading-[1.08] tracking-tight md:text-[3.15rem]">
                            Centers of
                            <span class="block text-[#b8f3ed]">Excellence.</span>
                        </h1>

                        <p class="mt-5 max-w-xl text-sm leading-6 text-white/80 md:text-[15px]">
                            Delivering compassionate, world-class healthcare for every Filipino —
                            supported by dedicated professionals and a secure digital experience.
                        </p>

                        <div class="mt-7 flex items-center gap-3 text-xs text-white/65">
                            <span class="h-px w-10 bg-white/35"></span>
                            Secure access to your Alvion dashboard
                        </div>
                    </div>

                    <p class="pt-8 text-xs text-white/55">
                        &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.
                    </p>
                </div>
            </div>

            <!-- Login panel -->
            <div class="form-panel md:col-span-2 px-6 py-10 sm:px-10 lg:px-12">
                <div class="form-inner">

                    <a href="../landing.php"
                       class="mb-7 inline-flex items-center gap-2 text-xs font-semibold text-slate-500 transition hover:text-[#07877f]"
                       aria-label="Back to home">
                        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m12 19-7-7 7-7"></path>
                            <path d="M19 12H5"></path>
                        </svg>
                        Back to Home
                    </a>

                    <div>
                        <img
                            src="<?php echo BASE_URL; ?>/assets/img/alvion-logo-removebg.png"
                            alt="<?php echo SITE_NAME; ?>"
                            class="h-11 w-auto object-contain object-left sm:h-12"
                        >

                        <div class="mt-7">
                            <p class="text-[11px] font-bold uppercase tracking-[.2em] text-[#07877f]">
                                Welcome back
                            </p>
                            <h2 class="mt-2 text-[2rem] font-semibold tracking-tight text-slate-900">
                                Sign in to your account
                            </h2>
                            <p class="mt-2 text-sm leading-5 text-slate-500">
                                Access your Alvion dashboard securely.
                            </p>
                        </div>
                    </div>

                    <form id="loginForm" class="mt-7 space-y-5" method="POST">

                        <div>
                            <label for="username" class="mb-2 block text-xs font-semibold text-slate-700">
                                Username or Email
                            </label>

                            <div class="field">
                                <span class="field-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M20 21a8 8 0 0 0-16 0"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                </span>

                                <input
                                    id="username"
                                    name="username"
                                    type="text"
                                    required
                                    autocomplete="username"
                                    class="login-input"
                                    placeholder="Enter username or email"
                                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                >
                            </div>
                        </div>

                        <div>
                            <label for="password" class="mb-2 block text-xs font-semibold text-slate-700">
                                Password
                            </label>

                            <div class="field">
                                <span class="field-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect width="18" height="11" x="3" y="11" rx="2"></rect>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                    </svg>
                                </span>

                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    required
                                    autocomplete="current-password"
                                    class="login-input pr-12"
                                    placeholder="Enter your password"
                                >

                                <button
                                    type="button"
                                    id="togglePassword"
                                    class="absolute right-0 top-0 flex h-full w-12 items-center justify-center text-slate-400 transition hover:text-[#07877f]"
                                    aria-label="Show password"
                                >
                                    <svg id="passwordEyeIcon" xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-4 text-xs">
                            <label class="inline-flex cursor-pointer items-center gap-2 text-slate-600">
                                <input
                                    id="remember_me"
                                    name="remember_me"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-[#07877f] focus:ring-[#07877f]"
                                >
                                <span>Remember me</span>
                            </label>

                            <a href="forgot-password.php"
                               class="font-semibold text-slate-500 transition hover:text-[#07877f]">
                                Forgot password?
                            </a>
                        </div>

                        <div class="legal-box px-4 py-3.5">
                            <p class="text-[11px] leading-5 text-slate-600">
                                By logging in, you agree to our
                                <a href="../legal/terms-and-conditions.php" target="_blank" rel="noopener noreferrer"
                                   class="font-semibold text-[#07877f] underline decoration-[#07877f]/30 underline-offset-2 hover:decoration-[#07877f]">
                                    Terms and Conditions
                                </a>
                                and
                                <a href="../legal/privacy-policy.php" target="_blank" rel="noopener noreferrer"
                                   class="font-semibold text-[#07877f] underline decoration-[#07877f]/30 underline-offset-2 hover:decoration-[#07877f]">
                                    Privacy Policy
                                </a>.
                            </p>
                        </div>

                        <button
                            type="submit"
                            id="loginButton"
                            class="primary-btn w-full text-sm font-bold text-white"
                        >
                            Login
                        </button>

                        <div class="divider">or</div>

                        <a href="employee-login.php"
                           class="secondary-btn flex w-full items-center justify-center text-sm font-bold">
                            Sign in as employee
                        </a>
                    </form>

                    <p class="mt-7 text-center text-xs text-slate-500">
                        Don't have an account?
                        <a href="register.php" class="font-bold text-[#07877f] hover:text-[#066b65]">
                            Create Account
                        </a>
                    </p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Verification Code Modal -->
    <div
        id="verificationModal"
        class="otp-backdrop fixed inset-0 z-50 hidden items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="verificationTitle"
    >
        <div class="otp-modal relative rounded-[24px] bg-white p-6 sm:p-8">
            <button
                type="button"
                id="closeVerificationModal"
                class="absolute right-4 top-4 grid h-9 w-9 place-items-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                aria-label="Close verification dialog"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>

            <div class="text-center">
                <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-[#07877f]/10 text-[#07877f]">
                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                    </svg>
                </div>

                <p class="mt-5 text-[11px] font-bold uppercase tracking-[.18em] text-[#07877f]">
                    Security verification
                </p>

                <h3 id="verificationTitle" class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">
                    Enter your code
                </h3>

                <p class="mx-auto mt-2 max-w-sm text-sm leading-5 text-slate-500">
                    We've sent a 6-digit verification code to
                    <span class="font-semibold text-slate-700">
                        <?php
                        if (isset($_SESSION['verification_email']) && !empty($_SESSION['verification_email'])) {
                            echo htmlspecialchars(maskEmail($_SESSION['verification_email']));
                        } else {
                            echo 'your email';
                        }
                        ?>
                    </span>.
                </p>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="auto-dismiss mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-left text-xs leading-5 text-red-700" data-auto-dismiss="true" role="alert">
                        <?php
                        $error_msg = $_SESSION['error'];
                        echo htmlspecialchars($error_msg);
                        unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="auto-dismiss mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-left text-xs leading-5 text-emerald-700" data-auto-dismiss="true" role="status">
                        <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="verificationForm" class="mt-7">
                    <div class="flex justify-center gap-2 sm:gap-3">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <input
                                type="text"
                                name="code<?php echo $i; ?>"
                                class="code-input text-center text-xl font-bold"
                                maxlength="1"
                                pattern="[0-9]"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                aria-label="Verification digit <?php echo $i; ?>"
                                required
                            >
                        <?php endfor; ?>
                    </div>

                    <input type="hidden" name="verification_code" id="fullCode">
                    <input type="hidden" name="verify_code" value="1">

                    <button
                        type="submit"
                        id="verifyButton"
                        class="primary-btn mt-7 w-full text-sm font-bold text-white"
                    >
                        Verify Code
                    </button>
                </form>

                <div class="mt-5 border-t border-slate-100 pt-5">
                    <p class="text-xs text-slate-500">
                        Code expires in 10 minutes.
                    </p>

                    <form method="POST" id="resendOtpForm" class="mt-2">
                        <input type="hidden" name="resend_otp" value="1">
                        <button
                            type="submit"
                            id="resendOtpBtn"
                            class="text-xs font-bold text-[#07877f] transition hover:text-[#066b65] disabled:cursor-not-allowed disabled:text-slate-400"
                        >
                            Resend Code
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const passwordField = document.getElementById('password');
            const togglePasswordBtn = document.getElementById('togglePassword');
            const passwordEyeIcon = document.getElementById('passwordEyeIcon');

            if (togglePasswordBtn && passwordField && passwordEyeIcon) {
                togglePasswordBtn.addEventListener('click', function () {
                    const isPassword = passwordField.type === 'password';
                    passwordField.type = isPassword ? 'text' : 'password';
                    togglePasswordBtn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');

                    passwordEyeIcon.innerHTML = isPassword
                        ? '<path d="M3.5 3.5 20.5 20.5"></path><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"></path><path d="M9.9 5.1A10.8 10.8 0 0 1 12 5c7 0 10 7 10 7a18.3 18.3 0 0 1-3.1 3.9"></path><path d="M6.2 6.2A18.4 18.4 0 0 0 2 12s3 7 10 7a10.8 10.8 0 0 0 2.1-.2"></path>'
                        : '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle>';
                });
            }

            const modal = document.getElementById('verificationModal');
            const closeModalBtn = document.getElementById('closeVerificationModal');

            function openVerificationModal() {
                if (!modal) return;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.classList.add('overflow-hidden');
                setTimeout(setupCodeInputs, 80);
            }

            function closeVerificationModal() {
                if (!modal) return;
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            }

            closeModalBtn?.addEventListener('click', closeVerificationModal);

            modal?.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeVerificationModal();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                    closeVerificationModal();
                }
            });

            let codeInputsSetup = false;

            function setupCodeInputs() {
                if (codeInputsSetup) return;
                codeInputsSetup = true;

                const codeInputs = document.querySelectorAll('.code-input');

                <?php if (isset($_SESSION['error']) && isset($_SESSION['show_verification_modal'])): ?>
                codeInputs.forEach(input => input.value = '');
                <?php endif; ?>

                codeInputs.forEach((input, index) => {
                    input.addEventListener('input', function (event) {
                        const value = event.target.value.replace(/\D/g, '').slice(-1);
                        event.target.value = value;

                        if (value && index < codeInputs.length - 1) {
                            codeInputs[index + 1].focus();
                            codeInputs[index + 1].select();
                        }
                    });

                    input.addEventListener('keydown', function (event) {
                        if (event.key === 'Backspace' && !input.value && index > 0) {
                            codeInputs[index - 1].focus();
                        }

                        if (event.key === 'ArrowLeft' && index > 0) {
                            codeInputs[index - 1].focus();
                        }

                        if (event.key === 'ArrowRight' && index < codeInputs.length - 1) {
                            codeInputs[index + 1].focus();
                        }
                    });

                    input.addEventListener('paste', function (event) {
                        const pasted = (event.clipboardData || window.clipboardData)
                            .getData('text')
                            .replace(/\D/g, '')
                            .slice(0, 6);

                        if (!pasted) return;

                        event.preventDefault();

                        [...pasted].forEach((digit, i) => {
                            if (codeInputs[i]) codeInputs[i].value = digit;
                        });

                        const focusIndex = Math.min(pasted.length, codeInputs.length - 1);
                        codeInputs[focusIndex].focus();
                    });
                });

                if (codeInputs.length) {
                    codeInputs[0].focus();
                }
            }

            <?php if (isset($_SESSION['show_verification_modal']) && $_SESSION['show_verification_modal']): ?>
                openVerificationModal();

                <?php
                $error_message = $_SESSION['error'] ?? '';
                $is_otp_error =
                    stripos($error_message, 'OTP') !== false ||
                    stripos($error_message, 'verification') !== false ||
                    stripos($error_message, 'code') !== false;

                if (!isset($_SESSION['error']) || !$is_otp_error) {
                    unset($_SESSION['show_verification_modal']);
                }
                ?>
            <?php endif; ?>

            const verificationForm = document.getElementById('verificationForm');
            const verifyButton = document.getElementById('verifyButton');

            function setButtonLoading(button, text) {
                if (!button || button.dataset.loading === 'true') return;

                button.dataset.loading = 'true';
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');

                button.innerHTML = `
                    <span class="inline-flex items-center justify-center gap-2">
                        <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                        </svg>
                        <span>${text}</span>
                    </span>
                `;
            }

            verificationForm?.addEventListener('submit', function (event) {
                const codeInputs = document.querySelectorAll('.code-input');
                const fullCode = Array.from(codeInputs).map(input => input.value || '').join('');

                if (fullCode.length !== 6) {
                    event.preventDefault();

                    if (typeof showErrorAlert === 'function') {
                        showErrorAlert('Verification Code', 'Please enter all 6 digits of the verification code.');
                    } else {
                        alert('Please enter all 6 digits of the verification code.');
                    }

                    return false;
                }

                document.getElementById('fullCode').value = fullCode;
                setButtonLoading(verifyButton, 'Verifying...');
            });

            const loginForm = document.getElementById('loginForm');
            const loginButton = document.getElementById('loginButton');

            loginForm?.addEventListener('submit', function () {
                setButtonLoading(loginButton, 'Authenticating...');
            });

            const resendBtn = document.getElementById('resendOtpBtn');
            const resendForm = document.getElementById('resendOtpForm');
            const cooldown = 60;
            let countdownInterval = null;

            function startCountdown(seconds) {
                if (!resendBtn) return;

                let remaining = Math.ceil(seconds);
                resendBtn.disabled = true;
                resendBtn.textContent = `Resend in ${remaining}s`;

                if (countdownInterval) clearInterval(countdownInterval);

                countdownInterval = setInterval(function () {
                    remaining--;

                    if (remaining > 0) {
                        resendBtn.textContent = `Resend in ${remaining}s`;
                    } else {
                        clearInterval(countdownInterval);
                        countdownInterval = null;
                        resendBtn.disabled = false;
                        resendBtn.textContent = 'Resend Code';
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
                }
            }

            resendForm?.addEventListener('submit', function () {
                startCountdown(cooldown);
            });

            <?php if (isset($_SESSION['success']) && !isset($_SESSION['show_verification_modal'])): ?>
                if (typeof showToast === 'function') {
                    showToast('success', <?php echo json_encode($_SESSION['success']); ?>);
                }
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error']) && !isset($_SESSION['show_verification_modal'])): ?>
                if (typeof showToast === 'function') {
                    showToast('error', <?php echo json_encode($_SESSION['error']); ?>);
                }
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            document.querySelectorAll('[data-auto-dismiss="true"]').forEach(function (element) {
                setTimeout(function () {
                    element.style.opacity = '0';
                    element.style.transform = 'translateY(-4px)';
                    setTimeout(() => element.remove(), 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>