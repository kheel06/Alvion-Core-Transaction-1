# =====================================================
# Hospital Core 1 System - Full Database Setup
# Drops & recreates DB, loads base schema, runs all
# migrations and seeds so every admin page has real data.
# =====================================================
# Usage: powershell -ExecutionPolicy Bypass -File run_all_migrations.ps1
# Credentials: root / petras123 (same as config/database.php)
# =====================================================

$ErrorActionPreference = "Continue"
$db   = "hospital-core1-system"
$user = "root"
$pass = "petras123"

$scriptDir = $PSScriptRoot
if (-not $scriptDir) { $scriptDir = Get-Location }

# --------------------------------------------------
# Helper: pipe file contents into mysql
# --------------------------------------------------
function Run-SQL {
    param([string]$File, [string]$Label)
    $path = Join-Path $scriptDir $File
    if (-not (Test-Path $path)) {
        Write-Host "  SKIP (not found): $File" -ForegroundColor Yellow
        return
    }
    Write-Host "  Running: $Label ($File)" -ForegroundColor Cyan
    try {
        Get-Content $path -Raw -Encoding UTF8 | & mysql -u $user -p"$pass" $db 2>&1
        if ($LASTEXITCODE -ne 0) {
            Write-Host "    WARNING exit code: $LASTEXITCODE" -ForegroundColor Red
        } else {
            Write-Host "    OK" -ForegroundColor Green
        }
    } catch {
        Write-Host "    ERROR: $_" -ForegroundColor Red
    }
}

# =====================================================
# PHASE 0 – Drop & recreate database
# =====================================================
Write-Host "`n========================================" -ForegroundColor Magenta
Write-Host "PHASE 0: Recreate database" -ForegroundColor Magenta
Write-Host "========================================" -ForegroundColor Magenta

Write-Host "  Dropping database '$db' ..." -ForegroundColor Yellow
echo "DROP DATABASE IF EXISTS ``$db``;" | & mysql -u $user -p"$pass" 2>&1
Write-Host "  Creating database '$db' ..." -ForegroundColor Yellow
echo "CREATE DATABASE ``$db`` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;" | & mysql -u $user -p"$pass" 2>&1
Write-Host "    OK" -ForegroundColor Green

# =====================================================
# PHASE 1 – Base schema (phpMyAdmin export)
# =====================================================
Write-Host "`n========================================" -ForegroundColor Magenta
Write-Host "PHASE 1: Base schema" -ForegroundColor Magenta
Write-Host "========================================" -ForegroundColor Magenta

Run-SQL "hospital-core1-system.sql" "Base schema (tables, indexes, FK)"

# =====================================================
# PHASE 2 – Schema migrations (ALTER / CREATE IF NOT EXISTS)
# =====================================================
Write-Host "`n========================================" -ForegroundColor Magenta
Write-Host "PHASE 2: Schema migrations" -ForegroundColor Magenta
Write-Host "========================================" -ForegroundColor Magenta

$migrations = @(
    @("01_migrate_admin_schema.sql",        "Admin tables (insurance, payments, system settings)"),
    @("fix_schema_gaps.sql",                 "Fix missing columns & tables between migrations"),
    @("02_align_to_base_schema.sql",        "Align to base schema (PH locations, patient cols)"),
    @("migrate_appointments_reschedule.sql", "Appointments reschedule columns"),
    @("clinic_rooms.sql",                    "Clinic rooms table"),
    @("general_modules.sql",                 "General modules (consent, queue, insurance policies)"),
    @("er_triage.sql",                       "ER triage table"),
    @("teleconsultations.sql",               "Teleconsultations table"),
    @("ehr.sql",                             "EHR tables (vitals, diagnoses, meds, labs)"),
    @("admissions.sql",                      "Admissions table"),
    @("billing.sql",                         "Billing table"),
    @("wards_and_beds_setup.sql",            "Wards & beds tables"),
    @("03_ehr_reference_patients.sql",       "Fix EHR FK to reference patients"),
    @("password_reset_tokens.sql",           "Password reset tokens table"),
    @("role_based_schema_updates.sql",       "Role-based schema (billing charges, vital signs, etc)")
)

foreach ($m in $migrations) {
    Run-SQL $m[0] $m[1]
}

# =====================================================
# PHASE 3 – Seed data (INSERT)
# =====================================================
Write-Host "`n========================================" -ForegroundColor Magenta
Write-Host "PHASE 3: Seed data" -ForegroundColor Magenta
Write-Host "========================================" -ForegroundColor Magenta

$seeds = @(
    @("seed_admin_data.sql",              "Core: admin, staff, 30 patients, wards, beds"),
    @("seed_admin_data_part2.sql",        "Appointments, admissions, ER triage, teleconsult"),
    @("seed_admin_data_part3.sql",        "Billing, insurance providers, system settings"),
    @("seed_ph_locations.sql",            "Philippine regions, provinces, cities"),
    @("doctors_insert.sql",              "Doctors table + 6 doctor records"),
    @("insert_permissions.sql",          "All permission definitions"),
    @("seed_doctor_schedules.sql",       "Doctor schedules"),
    @("seed_patient_portal_data.sql",    "Patient portal EHR & e-prescriptions"),
    @("seed_payments_billing_schema.sql","Payment records for billing"),
    @("seed_billing_payments_demo.sql",  "Billing & payments demo (patient_billing view)"),
    @("seed_dashboard_last7days.sql",    "Dashboard last-7-days metrics & today data"),
    @("seed_reschedule_appointments.sql","Upcoming appointments for reschedule page"),
    @("seed_consent_forms.sql",          "Consent form templates & signed forms"),
    @("seed_patient_queue.sql",          "Patient queue (OPD, ER, Clinic)"),
    @("seed_appointments_report.sql",    "Appointments report data (Jan-Feb 2026)"),
    @("seed_reports_all.sql",            "Billing & ER report data"),
    @("seed_er_transfer_active.sql",     "Active ER cases for transfer/discharge"),
    @("migrate_er_report_patients.sql",  "Placeholder patients for ER report"),
    @("seed_insurance_setup.sql",        "Insurance providers, policies, coverage rules")
)

foreach ($s in $seeds) {
    Run-SQL $s[0] $s[1]
}

# =====================================================
# DONE
# =====================================================
Write-Host "`n========================================" -ForegroundColor Green
Write-Host "ALL DONE! Database '$db' is fully set up." -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host "Admin login: admin / Hospital@2026" -ForegroundColor Cyan
Write-Host "DB: $db | User: $user" -ForegroundColor Cyan
Write-Host "`nCheck output above for any warnings." -ForegroundColor Yellow
