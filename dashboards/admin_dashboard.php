<?php
/**
 * Admin Dashboard
 * Full system overview for administrators
 */
require_once '../config/config.php';
requireAuth();
checkRole(['admin']);

$page_title = "Admin Dashboard";

// Default system health status so it's always available even if stats fail
$system_health = [
    'database' => 'healthy',
    'users_online' => 0, // Can be enhanced with session tracking
    'last_backup' => 'N/A'
];

try {
    // Check which tables exist
    $check_tables = $db->query("SHOW TABLES");
    $existing_tables = $check_tables->fetchAll(PDO::FETCH_COLUMN);
    $has_teleconsultations = in_array('teleconsultations', $existing_tables);
    $has_er_triage = in_array('er_triage', $existing_tables);
    $has_e_prescriptions = in_array('e_prescriptions', $existing_tables);
    $has_e_lab_orders = in_array('e_lab_orders', $existing_tables);
    $has_payments = in_array('payments', $existing_tables);
    $has_audit_logs = in_array('audit_logs', $existing_tables);
    $has_beds = in_array('beds', $existing_tables);
    
    // Check if patients table has created_at column
    $has_patients_created_at = false;
    if (in_array('patients', $existing_tables)) {
        try {
            $check_patient_cols = $db->query("SHOW COLUMNS FROM patients LIKE 'created_at'");
            $has_patients_created_at = $check_patient_cols->rowCount() > 0;
        } catch (PDOException $e) {
            $has_patients_created_at = false;
        }
    }
    
    // Check if appointments table has created_at column
    $has_appointments_created_at = false;
    if (in_array('appointments', $existing_tables)) {
        try {
            $check_appt_cols = $db->query("SHOW COLUMNS FROM appointments LIKE 'created_at'");
            $has_appointments_created_at = $check_appt_cols->rowCount() > 0;
        } catch (PDOException $e) {
            $has_appointments_created_at = false;
        }
    }
    
    // Check if audit_logs table has created_at, user_id, and employee_id columns
    $has_audit_logs_created_at = false;
    $has_audit_logs_user_id = false;
    $has_audit_logs_employee_id = false;
    if ($has_audit_logs) {
        try {
            $check_logs_cols = $db->query("SHOW COLUMNS FROM audit_logs");
            $audit_log_columns = $check_logs_cols->fetchAll(PDO::FETCH_COLUMN);
            $has_audit_logs_created_at = in_array('created_at', $audit_log_columns);
            $has_audit_logs_user_id = in_array('user_id', $audit_log_columns);
            $has_audit_logs_employee_id = in_array('employee_id', $audit_log_columns);
        } catch (PDOException $e) {
            $has_audit_logs_created_at = false;
            $has_audit_logs_user_id = false;
            $has_audit_logs_employee_id = false;
        }
    }
    
    // Check if tables have status columns
    $has_appointments_status = false;
    $has_users_status = false;
    $has_beds_status = false;
    $has_payments_status = false;
    $has_billing_payment_status = false;
    $has_insurance_claims_status = false;
    $has_er_triage_status = false;
    $has_er_triage_created_at = false;
    $has_e_prescriptions_status = false;
    $has_e_lab_orders_status = false;
    
    if ($has_er_triage) {
        try {
            $check_er_cols = $db->query("SHOW COLUMNS FROM er_triage");
            $er_cols = $check_er_cols->fetchAll(PDO::FETCH_COLUMN);
            $has_er_triage_status = in_array('status', $er_cols);
            $has_er_triage_created_at = in_array('created_at', $er_cols);
        } catch (PDOException $e) {
            $has_er_triage_status = false;
            $has_er_triage_created_at = false;
        }
    }
    
    if ($has_e_prescriptions) {
        try {
            $check_pres_cols = $db->query("SHOW COLUMNS FROM e_prescriptions LIKE 'status'");
            $has_e_prescriptions_status = $check_pres_cols->rowCount() > 0;
        } catch (PDOException $e) {
            $has_e_prescriptions_status = false;
        }
    }
    
    if ($has_e_lab_orders) {
        try {
            $check_lab_cols = $db->query("SHOW COLUMNS FROM e_lab_orders LIKE 'status'");
            $has_e_lab_orders_status = $check_lab_cols->rowCount() > 0;
        } catch (PDOException $e) {
            $has_e_lab_orders_status = false;
        }
    }
    
    if (in_array('appointments', $existing_tables)) {
        try {
            $check_appt_cols = $db->query("SHOW COLUMNS FROM appointments LIKE 'status'");
            $has_appointments_status = $check_appt_cols->rowCount() > 0;
        } catch (PDOException $e) {
            $has_appointments_status = false;
        }
    }
    
    if (in_array('users', $existing_tables)) {
        try {
            $check_users_cols = $db->query("SHOW COLUMNS FROM users LIKE 'status'");
            $has_users_status = $check_users_cols->rowCount() > 0;
        } catch (PDOException $e) {
            $has_users_status = false;
        }
    }
    
    if ($has_beds) {
        try {
            $check_beds_cols = $db->query("SHOW COLUMNS FROM beds LIKE 'status'");
            $has_beds_status = $check_beds_cols->rowCount() > 0;
        } catch (PDOException $e) {
            $has_beds_status = false;
        }
    }
    
    if ($has_payments) {
        try {
            $check_payments_cols = $db->query("SHOW COLUMNS FROM payments LIKE 'status'");
            $has_payments_status = $check_payments_cols->rowCount() > 0;
        } catch (PDOException $e) {
            $has_payments_status = false;
        }
    }
    
    if (in_array('billing', $existing_tables)) {
        try {
            $check_billing_cols = $db->query("SHOW COLUMNS FROM billing LIKE 'payment_status'");
            $has_billing_payment_status = $check_billing_cols->rowCount() > 0;
        } catch (PDOException $e) {
            $has_billing_payment_status = false;
        }
    }
    
    if (in_array('insurance_claims', $existing_tables)) {
        try {
            $check_claims_cols = $db->query("SHOW COLUMNS FROM insurance_claims LIKE 'status'");
            $has_insurance_claims_status = $check_claims_cols->rowCount() > 0;
        } catch (PDOException $e) {
            $has_insurance_claims_status = false;
        }
    }
    
    // Build query dynamically based on existing tables
    $teleconsult_select = $has_teleconsultations 
        ? "(SELECT COUNT(*) FROM teleconsultations WHERE DATE(consultation_date) = CURDATE()) as teleconsult_today,"
        : "0 as teleconsult_today,";
    
    $er_select = $has_er_triage
        ? "(SELECT COUNT(*) FROM er_triage" . ($has_er_triage_created_at ? " WHERE DATE(created_at) = CURDATE()" : "") . ") as er_cases_today,
        " . ($has_er_triage_status 
            ? "(SELECT COUNT(*) FROM er_triage WHERE status IN ('waiting', 'in_progress')) as er_active,
            (SELECT COUNT(*) FROM er_triage WHERE status = 'waiting') as er_waiting,
            (SELECT COUNT(*) FROM er_triage WHERE status = 'in_progress') as er_in_progress,"
            : "(SELECT COUNT(*) FROM er_triage) as er_active,
            (SELECT COUNT(*) FROM er_triage) as er_waiting,
            (SELECT COUNT(*) FROM er_triage) as er_in_progress,") . "
        (SELECT COUNT(*) FROM er_triage WHERE triage_level = 'resuscitation') as er_resuscitation,
        (SELECT COUNT(*) FROM er_triage WHERE triage_level = 'emergency') as er_emergency,"
        : "0 as er_cases_today, 0 as er_active, 0 as er_waiting, 0 as er_in_progress, 0 as er_resuscitation, 0 as er_emergency,";
    
    $prescriptions_select = $has_e_prescriptions && $has_e_prescriptions_status
        ? "(SELECT COUNT(*) FROM e_prescriptions WHERE status = 'pending') as pending_prescriptions,"
        : "0 as pending_prescriptions,";
    
    $lab_orders_select = $has_e_lab_orders && $has_e_lab_orders_status
        ? "(SELECT COUNT(*) FROM e_lab_orders WHERE status = 'pending') as pending_lab_orders,"
        : "0 as pending_lab_orders,";
    
    $payments_select = $has_payments
        ? "(SELECT COUNT(*) FROM payments" . ($has_payments_status ? " WHERE DATE(payment_date) = CURDATE()" : "") . ") as payments_today,
        " . ($has_payments_status 
            ? "(SELECT SUM(amount) FROM payments WHERE DATE(payment_date) = CURDATE() AND status = 'completed') as revenue_today,
            (SELECT SUM(amount) FROM payments WHERE status = 'completed' AND MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())) as revenue_month,"
            : "0 as revenue_today, 0 as revenue_month,")
        : "0 as payments_today, 0 as revenue_today, 0 as revenue_month,";
    
    // Use user_id or employee_id depending on which column exists in audit_logs
    $active_users_subquery = "0";
    if ($has_audit_logs && $has_audit_logs_created_at) {
        if ($has_audit_logs_user_id) {
            $active_users_subquery = "(SELECT COUNT(DISTINCT user_id) FROM audit_logs WHERE DATE(created_at) = CURDATE())";
        } elseif ($has_audit_logs_employee_id) {
            $active_users_subquery = "(SELECT COUNT(DISTINCT employee_id) FROM audit_logs WHERE DATE(created_at) = CURDATE() AND employee_id IS NOT NULL)";
        }
    }
    $logs_select = $has_audit_logs
        ? "(SELECT COUNT(*) FROM audit_logs" . ($has_audit_logs_created_at ? " WHERE DATE(created_at) = CURDATE()" : "") . ") as logs_today,
        " . $active_users_subquery . " as active_users_today"
        : "0 as logs_today, 0 as active_users_today";
    
    $beds_select = $has_beds && $has_beds_status
        ? "(SELECT COUNT(*) FROM beds WHERE status = 'occupied') as occupied_beds,
        (SELECT COUNT(*) FROM beds WHERE status = 'available') as available_beds,
        (SELECT COUNT(*) FROM beds) as total_beds,"
        : ($has_beds 
            ? "(SELECT COUNT(*) FROM beds) as occupied_beds,
            (SELECT COUNT(*) FROM beds) as available_beds,
            (SELECT COUNT(*) FROM beds) as total_beds,"
            : "0 as occupied_beds, 0 as available_beds, 0 as total_beds,");
    
    // System-wide statistics - Admin Dashboard with full metrics
    $total_patients_select = "(SELECT COUNT(*) FROM patients) as total_patients,";
    $outpatients_select = "(SELECT COUNT(*) FROM patients WHERE patient_type = 'outpatient') as outpatients,";
    $inpatients_select = "(SELECT COUNT(*) FROM patients WHERE patient_type = 'inpatient') as inpatients,";
    
    $today_patients_select = $has_patients_created_at
        ? "(SELECT COUNT(*) FROM patients WHERE DATE(created_at) = CURDATE()) as today_patients,"
        : "0 as today_patients,";
    
    // Build appointments queries based on status column existence
    $appointments_base = "(SELECT COUNT(*) FROM appointments";
    $appointments_today = $appointments_base . " WHERE DATE(appointment_date) = CURDATE()) as today_appointments,";
    
    $appointments_ongoing = $has_appointments_status
        ? "(SELECT COUNT(*) FROM appointments WHERE status IN ('scheduled', 'confirmed', 'in_progress')) as ongoing_appointments,"
        : "(SELECT COUNT(*) FROM appointments) as ongoing_appointments,";
    
    $appointments_scheduled = $has_appointments_status
        ? "(SELECT COUNT(*) FROM appointments WHERE status = 'scheduled') as scheduled_appointments,"
        : "0 as scheduled_appointments,";
    
    $appointments_confirmed = $has_appointments_status
        ? "(SELECT COUNT(*) FROM appointments WHERE status = 'confirmed') as confirmed_appointments,"
        : "0 as confirmed_appointments,";
    
    $appointments_in_progress = $has_appointments_status
        ? "(SELECT COUNT(*) FROM appointments WHERE status = 'in_progress') as in_progress_appointments,"
        : "0 as in_progress_appointments,";
    
    $users_active = $has_users_status
        ? "(SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,"
        : "(SELECT COUNT(*) FROM users) as active_users,";
    
    $stats_query = "SELECT 
        " . $total_patients_select . "
        " . $outpatients_select . "
        " . $inpatients_select . "
        " . $today_patients_select . "
        " . $appointments_today . "
        " . $appointments_ongoing . "
        " . $appointments_scheduled . "
        " . $appointments_confirmed . "
        " . $appointments_in_progress . "
        " . $er_select . "
        " . $beds_select . "
        " . $teleconsult_select . "
        " . $prescriptions_select . "
        " . $lab_orders_select . "
        " . $users_active . "
        " . $payments_select . "
        " . $logs_select;
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch();

    // Financial summary
    $financial_revenue = $has_payments && $has_payments_status
        ? "(SELECT SUM(amount) FROM payments WHERE status = 'completed') as total_revenue,"
        : "0 as total_revenue,";
    
    $financial_pending = $has_payments && $has_payments_status
        ? "(SELECT SUM(amount) FROM payments WHERE status = 'pending') as pending_payments,"
        : "0 as pending_payments,";
    
    $financial_unpaid = in_array('billing', $existing_tables) && $has_billing_payment_status
        ? "(SELECT SUM(balance_amount) FROM billing WHERE payment_status IN ('pending', 'partial', 'overdue')) as unpaid_bills,"
        : "0 as unpaid_bills,";
    
    $financial_claims = in_array('insurance_claims', $existing_tables) && $has_insurance_claims_status
        ? "(SELECT COUNT(*) FROM insurance_claims WHERE status = 'pending') as pending_claims"
        : "0 as pending_claims";
    
    $financial_query = "SELECT 
        " . $financial_revenue . "
        " . $financial_pending . "
        " . $financial_unpaid . "
        " . $financial_claims;
    $financial_stmt = $db->prepare($financial_query);
    $financial_stmt->execute();
    $financial = $financial_stmt->fetch();

    // System health - Enhanced with database size calculation
    try {
        $db_size_query = "SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'db_size_mb'
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()";
        $db_size_stmt = $db->prepare($db_size_query);
        $db_size_stmt->execute();
        $db_size_result = $db_size_stmt->fetch();
        $db_size_mb = $db_size_result['db_size_mb'] ?? 0;
        $database_size = $db_size_mb > 0 ? number_format($db_size_mb, 2) . ' MB' : 'N/A';
    } catch (Exception $e) {
        $database_size = 'N/A';
    }

    // Get total log entries safely
    try {
        $total_logs_result = $db->query("SELECT COUNT(*) as total FROM audit_logs")->fetch();
        $total_logs = $total_logs_result['total'] ?? 0;
    } catch (Exception $e) {
        $total_logs = 0;
    }

    $storage_info = [
        'database_size' => $database_size,
        'log_entries' => $stats['logs_today'] ?? 0,
        'user_activity' => $stats['active_users_today'] ?? 0,
        'total_logs' => $total_logs
    ];

    // Check if patients table has first_name and last_name columns
    $has_patients_first_name = false;
    $has_patients_last_name = false;
    $has_patients_hospital_id = false;
    if (in_array('patients', $existing_tables)) {
        try {
            $check_patient_cols = $db->query("SHOW COLUMNS FROM patients");
            $patient_columns = $check_patient_cols->fetchAll(PDO::FETCH_COLUMN);
            $has_patients_first_name = in_array('first_name', $patient_columns);
            $has_patients_last_name = in_array('last_name', $patient_columns);
            $has_patients_hospital_id = in_array('hospital_id', $patient_columns);
        } catch (PDOException $e) {
            // Columns check failed
        }
    }
    
    // Recent activity
    $recent_patients = [];
    if (in_array('patients', $existing_tables)) {
        try {
            $recent_patients_order = $has_patients_created_at ? "ORDER BY created_at DESC" : "ORDER BY id DESC";
            $recent_patients = $db->query("SELECT * FROM patients " . $recent_patients_order . " LIMIT 5")->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching recent patients: " . $e->getMessage());
            $recent_patients = [];
        }
    }
    
    // Recent appointments - build query dynamically based on available columns
    $recent_appointments = [];
    if (in_array('appointments', $existing_tables)) {
        try {
            $patient_select = "";
            if ($has_patients_first_name && $has_patients_last_name) {
                $patient_select = "p.first_name, p.last_name, ";
            } else {
                $patient_select = "NULL as first_name, NULL as last_name, ";
            }
            
            if ($has_patients_hospital_id) {
                $patient_select .= "p.hospital_id, ";
            } else {
                $patient_select .= "NULL as hospital_id, ";
            }
            
            $appointments_query = "SELECT a.*, " . $patient_select . "u.first_name as doctor_fname, u.last_name as doctor_lname 
                FROM appointments a 
                LEFT JOIN patients p ON a.patient_id = p.id 
                LEFT JOIN users u ON a.doctor_id = u.id
                " . ($has_appointments_created_at ? "ORDER BY a.created_at DESC" : "ORDER BY a.id DESC") . " LIMIT 5";
            $recent_appointments = $db->query($appointments_query)->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching recent appointments: " . $e->getMessage());
            $recent_appointments = [];
        }
    }
    
    // Recent user activity from audit logs
    $recent_activity = [];
    if ($has_audit_logs) {
        try {
            if ($has_audit_logs_employee_id && in_array('department_accounts', $existing_tables)) {
                // Schema uses employee_id - join with department_accounts
                $activity_query = "SELECT al.*, 
                    COALESCE(da.employee_fname, 'Unknown') as first_name, 
                    COALESCE(da.employee_lname, '') as last_name, 
                    COALESCE(da.role_name, 'N/A') as role_name,
                    COALESCE(al.table_name, 'System') as module
                    FROM audit_logs al 
                    LEFT JOIN department_accounts da ON al.employee_id = da.employee_id
                    " . ($has_audit_logs_created_at ? "ORDER BY al.created_at DESC" : "ORDER BY al.id DESC") . " LIMIT 10";
                $recent_activity = $db->query($activity_query)->fetchAll();
            } elseif ($has_audit_logs_user_id && in_array('users', $existing_tables)) {
                // Schema uses user_id - join with users
                $has_users_role_name = false;
                try {
                    $check_user_cols = $db->query("SHOW COLUMNS FROM users");
                    $user_columns = $check_user_cols->fetchAll(PDO::FETCH_COLUMN);
                    $has_users_role_name = in_array('role_name', $user_columns);
                } catch (PDOException $e) {}
                $role_select = $has_users_role_name ? "u.role_name" : "COALESCE(r.role_name, 'N/A') as role_name";
                $role_join = $has_users_role_name ? "" : "LEFT JOIN roles r ON u.role_id = r.id";
                $activity_query = "SELECT al.*, u.first_name, u.last_name, " . $role_select . " 
                    FROM audit_logs al 
                    LEFT JOIN users u ON al.user_id = u.id 
                    " . $role_join . "
                    " . ($has_audit_logs_created_at ? "ORDER BY al.created_at DESC" : "ORDER BY al.id DESC") . " LIMIT 10";
                $recent_activity = $db->query($activity_query)->fetchAll();
            }
        } catch (PDOException $e) {
            error_log("Error fetching recent activity: " . $e->getMessage());
            $recent_activity = [];
        }
    }

    // --- Trend data for graphs (last 7 days and last 30 days) ---
    $trend_days = 7;
    $trend_appointments = [];
    $trend_patients = [];
    $trend_revenue = [];
    $trend_er = [];
    $insights = ['appointments_this_week' => 0, 'appointments_last_week' => 0, 'revenue_this_week' => 0, 'revenue_last_week' => 0, 'patients_this_week' => 0, 'patients_last_week' => 0];

    for ($i = $trend_days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $trend_appointments[$d] = 0;
        $trend_patients[$d] = 0;
        $trend_revenue[$d] = 0;
        $trend_er[$d] = 0;
    }

    if (in_array('appointments', $existing_tables)) {
        try {
            $stmt = $db->prepare("SELECT DATE(appointment_date) as d, COUNT(*) as c FROM appointments WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY DATE(appointment_date)");
            $stmt->execute([$trend_days]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (isset($trend_appointments[$row['d']])) {
                    $trend_appointments[$row['d']] = (int) $row['c'];
                }
            }
            $stmt_this = $db->prepare("SELECT COUNT(*) as c FROM appointments WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
            $stmt_this->execute();
            $insights['appointments_this_week'] = (int) $stmt_this->fetch(PDO::FETCH_ASSOC)['c'];
            $stmt_last = $db->prepare("SELECT COUNT(*) as c FROM appointments WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND appointment_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
            $stmt_last->execute();
            $insights['appointments_last_week'] = (int) $stmt_last->fetch(PDO::FETCH_ASSOC)['c'];
        } catch (PDOException $e) {
            // ignore
        }
    }

    if (in_array('patients', $existing_tables) && $has_patients_created_at) {
        try {
            $stmt = $db->prepare("SELECT DATE(created_at) as d, COUNT(*) as c FROM patients WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY DATE(created_at)");
            $stmt->execute([$trend_days]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (isset($trend_patients[$row['d']])) {
                    $trend_patients[$row['d']] = (int) $row['c'];
                }
            }
            $stmt_this = $db->prepare("SELECT COUNT(*) as c FROM patients WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
            $stmt_this->execute();
            $insights['patients_this_week'] = (int) $stmt_this->fetch(PDO::FETCH_ASSOC)['c'];
            $stmt_last = $db->prepare("SELECT COUNT(*) as c FROM patients WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
            $stmt_last->execute();
            $insights['patients_last_week'] = (int) $stmt_last->fetch(PDO::FETCH_ASSOC)['c'];
        } catch (PDOException $e) {
            // ignore
        }
    }

    if ($has_payments) {
        try {
            $date_col = 'payment_date';
            $stmt = $db->prepare("SELECT DATE($date_col) as d, COALESCE(SUM(amount), 0) as c FROM payments WHERE $date_col >= DATE_SUB(CURDATE(), INTERVAL ? DAY) " . ($has_payments_status ? " AND status = 'completed'" : "") . " GROUP BY DATE($date_col)");
            $stmt->execute([$trend_days]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (isset($trend_revenue[$row['d']])) {
                    $trend_revenue[$row['d']] = (float) $row['c'];
                }
            }
            $stmt_this = $db->prepare("SELECT COALESCE(SUM(amount), 0) as c FROM payments WHERE $date_col >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) " . ($has_payments_status ? " AND status = 'completed'" : ""));
            $stmt_this->execute();
            $insights['revenue_this_week'] = (float) $stmt_this->fetch(PDO::FETCH_ASSOC)['c'];
            $stmt_last = $db->prepare("SELECT COALESCE(SUM(amount), 0) as c FROM payments WHERE $date_col >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND $date_col < DATE_SUB(CURDATE(), INTERVAL 7 DAY) " . ($has_payments_status ? " AND status = 'completed'" : ""));
            $stmt_last->execute();
            $insights['revenue_last_week'] = (float) $stmt_last->fetch(PDO::FETCH_ASSOC)['c'];
        } catch (PDOException $e) {
            // ignore
        }
    }

    if ($has_er_triage && $has_er_triage_created_at) {
        try {
            $stmt = $db->prepare("SELECT DATE(created_at) as d, COUNT(*) as c FROM er_triage WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY DATE(created_at)");
            $stmt->execute([$trend_days]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (isset($trend_er[$row['d']])) {
                    $trend_er[$row['d']] = (int) $row['c'];
                }
            }
        } catch (PDOException $e) {
            // ignore
        }
    }

    $chart_labels = array_keys($trend_appointments);
    $chart_appointments = array_values($trend_appointments);
    $chart_patients = array_values($trend_patients);
    $chart_revenue = array_values($trend_revenue);
    $chart_er = array_values($trend_er);
    // Short labels for axis (e.g. Feb 12)
    $chart_labels_short = array_map(function ($d) {
        return date('M j', strtotime($d));
    }, $chart_labels);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching dashboard data: " . $e->getMessage();
    $stats = [];
    $financial = [];
    $recent_patients = [];
    $recent_appointments = [];
    $recent_activity = [];
    $storage_info = ['database_size' => 'N/A', 'log_entries' => 0, 'user_activity' => 0, 'total_logs' => 0];
    $chart_labels_short = [];
    $chart_appointments = [];
    $chart_patients = [];
    $chart_revenue = [];
    $chart_er = [];
    $insights = ['appointments_this_week' => 0, 'appointments_last_week' => 0, 'revenue_this_week' => 0, 'revenue_last_week' => 0, 'patients_this_week' => 0, 'patients_last_week' => 0];
}

include '../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">📊 Admin Dashboard</h1>
    <p class="text-gray-600 dark:text-gray-400">Comprehensive system overview and management - Alvion Hospital</p>
</div>

<!-- Key Statistics -->
<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Patients</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo number_format($stats['total_patients'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="bg-gray-50 dark:bg-gray-700 px-5 py-3">
            <div class="text-sm space-y-1">
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Outpatient:</span>
                    <span class="text-blue-600 font-medium"><?php echo $stats['outpatients'] ?? 0; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Inpatient:</span>
                    <span class="text-purple-600 font-medium"><?php echo $stats['inpatients'] ?? 0; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">New Today:</span>
                    <span class="text-green-600 font-medium">+<?php echo $stats['today_patients'] ?? 0; ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="m9 16 2 2 4-4"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Ongoing Appointments</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['ongoing_appointments'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="bg-gray-50 dark:bg-gray-700 px-5 py-3">
            <div class="text-sm space-y-1">
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Today:</span>
                    <span class="text-blue-600 font-medium"><?php echo $stats['today_appointments'] ?? 0; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Scheduled:</span>
                    <span class="text-green-600 font-medium"><?php echo $stats['scheduled_appointments'] ?? 0; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Confirmed:</span>
                    <span class="text-teal-600 font-medium"><?php echo $stats['confirmed_appointments'] ?? 0; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">In Progress:</span>
                    <span class="text-purple-600 font-medium"><?php echo $stats['in_progress_appointments'] ?? 0; ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M10 2v6"></path><path d="M14 2v6"></path><path d="M18 8H2"></path><path d="M20 8v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8"></path><path d="M4 12h16"></path><path d="M8 18h8"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">ER Activity</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['er_active'] ?? 0; ?> Active</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="bg-gray-50 dark:bg-gray-700 px-5 py-3">
            <div class="text-sm space-y-1">
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Cases Today:</span>
                    <span class="text-red-600 font-medium"><?php echo $stats['er_cases_today'] ?? 0; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Waiting:</span>
                    <span class="text-orange-600 font-medium"><?php echo $stats['er_waiting'] ?? 0; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">In Progress:</span>
                    <span class="text-blue-600 font-medium"><?php echo $stats['er_in_progress'] ?? 0; ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v12"></path><path d="M15 9a3 3 0 1 0-6 0"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Revenue Today</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white">₱<?php echo number_format($stats['revenue_today'] ?? 0, 2); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="bg-gray-50 dark:bg-gray-700 px-5 py-3">
            <div class="text-sm">
                <span class="text-green-600 font-medium"><?php echo $stats['payments_today'] ?? 0; ?> payments</span>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Statistics -->
<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-teal-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.999"></path><rect x="2" y="6" width="14" height="12" rx="2"></rect></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Teleconsultations</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['teleconsult_today'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Pending Prescriptions</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['pending_prescriptions'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-indigo-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M10 2v2"></path><path d="M14 2v2"></path><path d="M10.5 2h3"></path><path d="M7 12a5 5 0 0 0 10 0Z"></path><path d="M7 12H4a2 2 0 0 0-2 2v1a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-1a2 2 0 0 0-2-2h-3"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Pending Lab Orders</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['pending_lab_orders'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-pink-500 rounded-md flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 11v2a4 4 0 0 1-4 4h-1.5"></path><path d="M16 8h.01"></path></svg>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Active Users</dt>
                        <dd class="text-lg font-medium text-gray-900 dark:text-white"><?php echo $stats['active_users'] ?? 0; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bed Occupancy & ER Activity -->
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-8">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Bed Occupancy</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <div class="mb-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Beds</span>
                    <span class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo $stats['total_beds'] ?? 0; ?></span>
                </div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Occupied</span>
                    <span class="text-lg font-medium text-red-600 dark:text-red-400"><?php echo $stats['occupied_beds'] ?? 0; ?></span>
                </div>
                <div class="flex items-center justify-between mb-4">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Available</span>
                    <span class="text-lg font-medium text-green-600 dark:text-green-400"><?php echo $stats['available_beds'] ?? 0; ?></span>
                </div>
            </div>
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 mb-2">
                <?php
                    $occupied_beds = $stats['occupied_beds'] ?? 0;
                    $available_beds = $stats['available_beds'] ?? 0;
                    $total_beds = $stats['total_beds'] ?? ($occupied_beds + $available_beds);
                    $occupied_percent = $total_beds > 0 ? ($occupied_beds / $total_beds) * 100 : 0;
                    $available_percent = $total_beds > 0 ? ($available_beds / $total_beds) * 100 : 0;
                ?>
                <div class="bg-red-500 h-3 rounded-full" style="width: <?php echo $occupied_percent; ?>%"></div>
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400 text-center">
                <?php echo number_format($occupied_percent, 1); ?>% Occupied
            </div>
        </div>
    </div>

    <!-- ER Activity Section -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">ER Activity</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-red-50 dark:bg-red-900/20 p-3 rounded-lg">
                        <div class="text-xs text-gray-600 dark:text-gray-400 mb-1">Cases Today</div>
                        <div class="text-2xl font-bold text-red-600 dark:text-red-400"><?php echo $stats['er_cases_today'] ?? 0; ?></div>
                    </div>
                    <div class="bg-orange-50 dark:bg-orange-900/20 p-3 rounded-lg">
                        <div class="text-xs text-gray-600 dark:text-gray-400 mb-1">Active Cases</div>
                        <div class="text-2xl font-bold text-orange-600 dark:text-orange-400"><?php echo $stats['er_active'] ?? 0; ?></div>
                    </div>
                </div>
                <div class="space-y-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Waiting</span>
                        <span class="text-sm font-semibold text-orange-600 dark:text-orange-400"><?php echo $stats['er_waiting'] ?? 0; ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">In Progress</span>
                        <span class="text-sm font-semibold text-blue-600 dark:text-blue-400"><?php echo $stats['er_in_progress'] ?? 0; ?></span>
                    </div>
                    <?php if (($stats['er_resuscitation'] ?? 0) > 0 || ($stats['er_emergency'] ?? 0) > 0): ?>
                    <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-medium text-red-700 dark:text-red-400">Critical Cases</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-600 dark:text-gray-400">Resuscitation:</span>
                            <span class="font-semibold text-red-600 dark:text-red-400"><?php echo $stats['er_resuscitation'] ?? 0; ?></span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-600 dark:text-gray-400">Emergency:</span>
                            <span class="font-semibold text-orange-600 dark:text-orange-400"><?php echo $stats['er_emergency'] ?? 0; ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Analytics & Trends for Decision Making -->
<div class="mb-8">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">📈 Analytics & Trends</h2>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 mb-6">
        <!-- Decision insights -->
        <div class="lg:col-span-1 bg-white dark:bg-gray-800 shadow rounded-lg border-l-4 border-primary-500">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-base font-medium text-gray-900 dark:text-white">Decision Insights</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Week-over-week comparison</p>
            </div>
            <div class="px-4 py-4 sm:p-6 space-y-4">
                <?php
                $app_this = $insights['appointments_this_week'] ?? 0;
                $app_last = $insights['appointments_last_week'] ?? 0;
                $app_diff = $app_last > 0 ? round((($app_this - $app_last) / $app_last) * 100, 1) : ($app_this > 0 ? 100 : 0);
                $rev_this = $insights['revenue_this_week'] ?? 0;
                $rev_last = $insights['revenue_last_week'] ?? 0;
                $rev_diff = $rev_last > 0 ? round((($rev_this - $rev_last) / $rev_last) * 100, 1) : ($rev_this > 0 ? 100 : 0);
                $pat_this = $insights['patients_this_week'] ?? 0;
                $pat_last = $insights['patients_last_week'] ?? 0;
                $pat_diff = $pat_last > 0 ? round((($pat_this - $pat_last) / $pat_last) * 100, 1) : ($pat_this > 0 ? 100 : 0);
                ?>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Appointments (7 days)</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo number_format($app_this); ?> <span class="text-sm font-normal <?php echo $app_diff >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'; ?>">(<?php echo $app_diff >= 0 ? '+' : ''; ?><?php echo $app_diff; ?>% vs prev. week)</span></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Revenue (7 days)</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">₱<?php echo number_format($rev_this, 2); ?> <span class="text-sm font-normal <?php echo $rev_diff >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'; ?>">(<?php echo $rev_diff >= 0 ? '+' : ''; ?><?php echo $rev_diff; ?>% vs prev. week)</span></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">New patients (7 days)</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo number_format($pat_this); ?> <span class="text-sm font-normal <?php echo $pat_diff >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'; ?>">(<?php echo $pat_diff >= 0 ? '+' : ''; ?><?php echo $pat_diff; ?>% vs prev. week)</span></p>
                </div>
            </div>
        </div>
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-base font-medium text-gray-900 dark:text-white">Appointments (last 7 days)</h3>
            </div>
            <div class="px-4 py-4 sm:p-6 h-64">
                <canvas id="chartAppointments" aria-label="Appointments trend" role="img"></canvas>
            </div>
        </div>
    </div>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-base font-medium text-gray-900 dark:text-white">Revenue trend (last 7 days)</h3>
            </div>
            <div class="px-4 py-4 sm:p-6 h-64">
                <canvas id="chartRevenue" aria-label="Revenue trend" role="img"></canvas>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-base font-medium text-gray-900 dark:text-white">Patient registrations & ER cases (last 7 days)</h3>
            </div>
            <div class="px-4 py-4 sm:p-6 h-64">
                <canvas id="chartPatientsEr" aria-label="Patients and ER trend" role="img"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Financial Summary & System Health -->
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-8">
    <!-- Financial Summary -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Financial Summary</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Revenue</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">₱<?php echo number_format($financial['total_revenue'] ?? 0, 2); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Revenue Today</p>
                    <p class="text-lg font-semibold text-green-600 dark:text-green-400">₱<?php echo number_format($stats['revenue_today'] ?? 0, 2); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Revenue This Month</p>
                    <p class="text-lg font-semibold text-blue-600 dark:text-blue-400">₱<?php echo number_format($stats['revenue_month'] ?? 0, 2); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Pending Payments</p>
                    <p class="text-lg font-semibold text-yellow-600 dark:text-yellow-400">₱<?php echo number_format($financial['pending_payments'] ?? 0, 2); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Unpaid Bills</p>
                    <p class="text-lg font-semibold text-red-600 dark:text-red-400">₱<?php echo number_format($financial['unpaid_bills'] ?? 0, 2); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Pending Insurance Claims</p>
                    <p class="text-lg font-semibold text-orange-600 dark:text-orange-400"><?php echo $financial['pending_claims'] ?? 0; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- System Health -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">System Health</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <div class="space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-green-500 mr-2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Database Status</span>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                        Healthy
                    </span>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Active Users Today</span>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo $storage_info['user_activity']; ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Log Entries Today</span>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo number_format($storage_info['log_entries']); ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Total Log Entries</span>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo number_format($storage_info['total_logs']); ?></span>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Database Storage</span>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo $storage_info['database_size']; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Recent Patients</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($recent_patients) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($recent_patients as $patient): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    <?php 
                                    $first_name = $patient['first_name'] ?? '';
                                    $last_name = $patient['last_name'] ?? '';
                                    echo !empty($first_name) || !empty($last_name) ? trim($first_name . ' ' . $last_name) : 'Unknown Patient';
                                    ?>
                                </p>
                                <?php if (isset($patient['hospital_id'])): ?>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($patient['hospital_id']); ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400"><?php echo isset($patient['created_at']) ? formatDate($patient['created_at']) : 'N/A'; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">No recent patients</p>
            <?php endif; ?>
        </div>
    </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Recent Appointments</h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <?php if (count($recent_appointments) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($recent_appointments as $appt): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    <?php 
                                    $first_name = $appt['first_name'] ?? '';
                                    $last_name = $appt['last_name'] ?? '';
                                    echo !empty($first_name) || !empty($last_name) ? trim($first_name . ' ' . $last_name) : 'Unknown Patient';
                                    ?>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    <?php echo formatDate($appt['appointment_date'] ?? date('Y-m-d')); ?> at <?php echo formatTime($appt['appointment_time'] ?? '00:00:00'); ?>
                                    <?php if (!empty($appt['doctor_fname'])): ?>
                                        <br>Dr. <?php echo htmlspecialchars($appt['doctor_fname'] . ' ' . ($appt['doctor_lname'] ?? '')); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <span class="text-xs px-2 py-1 rounded-full <?php 
                                $status = $appt['status'] ?? 'unknown';
                                echo $status === 'completed' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                    ($status === 'scheduled' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'); 
                            ?>"><?php echo ucfirst($status); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 dark:text-gray-400 text-center py-4">No recent appointments</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- User Activity Logs -->
<?php if (count($recent_activity) > 0): ?>
<div class="bg-white dark:bg-gray-800 shadow rounded-lg mt-6">
    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Recent User Activity</h3>
    </div>
    <div class="px-4 py-5 sm:p-6">
        <div class="space-y-2">
            <?php foreach (array_slice($recent_activity, 0, 5) as $activity): ?>
                <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-700 rounded">
                    <div class="flex-1">
                        <p class="text-sm text-gray-900 dark:text-white">
                            <span class="font-medium"><?php echo $activity['first_name'] . ' ' . $activity['last_name']; ?></span>
                            <span class="text-gray-500 dark:text-gray-400"> (<?php echo ucfirst($activity['role_name'] ?? 'N/A'); ?>)</span>
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo $activity['action'] ?? 'N/A'; ?> - <?php echo $activity['module'] ?? 'System'; ?></p>
                    </div>
                    <span class="text-xs text-gray-500 dark:text-gray-400"><?php echo isset($activity['created_at']) ? formatDate($activity['created_at']) : 'N/A'; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Chart.js for Analytics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function() {
    var labels = <?php echo json_encode($chart_labels_short ?? []); ?>;
    var dataAppointments = <?php echo json_encode($chart_appointments ?? []); ?>;
    var dataRevenue = <?php echo json_encode($chart_revenue ?? []); ?>;
    var dataPatients = <?php echo json_encode($chart_patients ?? []); ?>;
    var dataEr = <?php echo json_encode($chart_er ?? []); ?>;

    var grid = { color: 'rgba(156, 163, 175, 0.3)' };
    var textColor = document.documentElement.classList.contains('dark') ? '#e5e7eb' : '#374151';

    if (labels.length && document.getElementById('chartAppointments')) {
        new Chart(document.getElementById('chartAppointments'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{ label: 'Appointments', data: dataAppointments, backgroundColor: 'rgba(34, 197, 94, 0.6)', borderColor: 'rgb(34, 197, 94)', borderWidth: 1 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: grid, ticks: { color: textColor } },
                    x: { grid: { display: false }, ticks: { color: textColor } }
                }
            }
        });
    }
    if (labels.length && document.getElementById('chartRevenue')) {
        new Chart(document.getElementById('chartRevenue'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{ label: 'Revenue (₱)', data: dataRevenue, borderColor: 'rgb(59, 130, 246)', backgroundColor: 'rgba(59, 130, 246, 0.1)', fill: true, tension: 0.3 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: grid, ticks: { color: textColor, callback: function(v) { return '₱' + Number(v).toLocaleString(); } } },
                    x: { grid: { display: false }, ticks: { color: textColor } }
                }
            }
        });
    }
    if (labels.length && document.getElementById('chartPatientsEr')) {
        new Chart(document.getElementById('chartPatientsEr'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'New patients', data: dataPatients, backgroundColor: 'rgba(99, 102, 241, 0.6)', borderColor: 'rgb(99, 102, 241)', borderWidth: 1 },
                    { label: 'ER cases', data: dataEr, backgroundColor: 'rgba(239, 68, 68, 0.6)', borderColor: 'rgb(239, 68, 68)', borderWidth: 1 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: textColor } } },
                scales: {
                    y: { beginAtZero: true, grid: grid, ticks: { color: textColor } },
                    x: { grid: { display: false }, ticks: { color: textColor } }
                }
            }
        });
    }
})();
</script>

<?php include '../includes/footer.php'; ?>



