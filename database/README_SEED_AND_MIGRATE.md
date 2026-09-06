# Hospital Core 1 – Seed Data & Migration (Philippines)

This folder contains migration and seed scripts so **admin role pages** and the rest of the system have real, Philippines-focused data.

## Run order

Use one of the options below.

### Option A: Align to base schema then seed (recommended)

Use this so **all seeds run successfully**: align the DB to the base schema, then run migrations and seeds in order.

1. **Base database** (pick one):
   - **Fresh:** create DB and load base schema:
     ```bash
     mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS \`hospital-core1-system\`;"
     mysql -u root -p hospital-core1-system < hospital-core1-system.sql
     ```
   - **Existing:** skip this and go to step 2 (alignment will add missing tables/columns).

2. **Admin schema** (creates payments, insurance, settings, clinic_hours, view):
   ```bash
   mysql -u root -p hospital-core1-system < 01_migrate_admin_schema.sql
   ```

3. **Align to base schema** (adds `ph_regions`/`ph_provinces`/`ph_cities`, `patients.hospital_id`, `appointments.appointment_number` if missing):
   ```bash
   mysql -u root -p hospital-core1-system < 02_align_to_base_schema.sql
   ```

4. **Seed data** (order matters):
   ```bash
   mysql -u root -p hospital-core1-system < seed_admin_data.sql
   mysql -u root -p hospital-core1-system < seed_admin_data_part2.sql
   mysql -u root -p hospital-core1-system < seed_admin_data_part3.sql
   mysql -u root -p hospital-core1-system < seed_ph_locations.sql
   ```

5. **Patient portal data** (EHR + e-prescriptions; run after EHR tables exist):
   - Create EHR tables and point `patient_id` to `patients.id`, then seed:
   ```bash
   mysql -u root -p hospital-core1-system < ehr.sql
   mysql -u root -p hospital-core1-system < 03_ehr_reference_patients.sql
   mysql -u root -p hospital-core1-system < seed_patient_portal_data.sql
   ```

6. **Optional – payments** (only if your `payments` table has `billing_id`):
   ```bash
   mysql -u root -p hospital-core1-system < seed_payments_billing_schema.sql
   ```

7. **Admin dashboard “last 7 days”** (so Appointments, Revenue, New patients, and charts show real numbers):
   ```bash
   mysql -u root -p hospital-core1-system < seed_dashboard_last7days.sql
   ```
   Run after seed_admin_data (1–3) and seed_payments_billing_schema. Uses `CURDATE()` so data always falls in the last 7 days.

### Option B: Existing database (already matches base schema)

If the database already exists and has the base schema:

1. Migrate admin tables and view:
   ```bash
   mysql -u root -p hospital-core1-system < 01_migrate_admin_schema.sql
   ```

2. Seed data (run each once; safe to re-run with ON DUPLICATE/INSERT IGNORE where used):
   ```bash
   mysql -u root -p hospital-core1-system < seed_admin_data.sql
   mysql -u root -p hospital-core1-system < seed_admin_data_part2.sql
   mysql -u root -p hospital-core1-system < seed_admin_data_part3.sql
   mysql -u root -p hospital-core1-system < seed_ph_locations.sql
   ```

### Run all migrations and seeds (PowerShell)

From the `database` folder, run every migration and seed in order:

```powershell
cd database
.\run_all_migrations.ps1
```

This runs: 01_migrate, 02_align, migrate_appointments_reschedule, clinic_rooms, general_modules, er_triage, teleconsultations, ehr, 03_ehr, seed_admin_data (1–3), seed_ph_locations, doctors_insert, seed_doctor_schedules, seed_patient_portal_data, seed_payments_billing_schema, seed_dashboard_last7days, seed_reschedule_appointments, seed_consent_forms, seed_patient_queue, seed_appointments_report, seed_reports_all, seed_er_transfer_active, migrate_er_report_patients, seed_insurance_setup. Running this ensures all admin pages (Dashboard, Patient Registration/View, Insurance, Consent Forms, Patient Queue, Schedule/Walk-in/Reschedule/Doctor Schedules, Telehealth, ER, Inpatient, Billing, Reports) have data.

If your database was not created from `hospital-core1-system.sql`, some seeds may fail (e.g. missing columns like `patients.birth_date`, `appointments.reason`). Run `02_align_to_base_schema.sql` first to add many missing columns; for full compatibility, load the base schema once: `mysql -u root -p hospital-core1-system < hospital-core1-system.sql` (then re-run migrations/seeds as needed).

### Option C: One-liner (Windows PowerShell)

From the `database` folder, after base DB and 01_migrate are applied:

```powershell
cd database
Get-Content 02_align_to_base_schema.sql, seed_admin_data.sql, seed_admin_data_part2.sql, seed_admin_data_part3.sql, seed_ph_locations.sql | mysql -u root -p hospital-core1-system
```

---

## What gets created/updated

| Script | Purpose |
|--------|--------|
| **01_migrate_admin_schema.sql** | Creates `insurance_providers`, `payments`, `insurance_claims`, `system_settings`, `clinic_hours`; creates view `patient_billing` from `billing`. |
| **02_align_to_base_schema.sql** | Adds `ph_regions`, `ph_provinces`, `ph_cities` (with base data); adds `patients.hospital_id` and `appointments.appointment_number` if missing so seeds run. |
| **seed_admin_data.sql** | Users (doctors, nurses, receptionists, finance), patients (30 Filipino names, PH addresses, PhilHealth IDs), wards, beds. |
| **seed_admin_data_part2.sql** | Appointments, admissions, teleconsultations, ER triage. |
| **seed_admin_data_part3.sql** | Billing, Philippine insurance providers (PhilHealth, Maxicare, Intellicare, etc.), insurance claims, system settings (Alvion, PHP, Asia/Manila), clinic hours (Mon–Fri 8–5, Sat 8–12). |
| **seed_payments_billing_schema.sql** | Optional. Run only if your `payments` table has `billing_id` (e.g. after fresh 01_migrate). If you use `patient_bills` + `payments.patient_bill_id`, skip. |
| **seed_ph_locations.sql** | PH regions, provinces, and cities for address fields. |
| **ehr.sql** | Creates EHR tables (ehr_diagnoses, ehr_medications, ehr_lab_results, ehr_procedures, ehr_immunizations, etc.). |
| **03_ehr_reference_patients.sql** | Points EHR `patient_id` FKs to `patients(id)` so patient portal can show data. Run once after ehr.sql. |
| **seed_patient_portal_data.sql** | Creates `e_prescriptions` table (if missing); seeds EHR + e-prescriptions for patient_id 1 (Jose Rizal). |
| **seed_dashboard_last7days.sql** | Adds appointments, payments, ER, and backdates patients so **Admin Dashboard** shows 7-day and "today" metrics (revenue/appointments/ER today, teleconsultations, pending prescriptions/lab orders, occupied beds). Creates `e_lab_orders` if missing. Run after part3 and seed_payments_billing_schema. |
| **seed_consent_forms.sql** | Seeds Consent Forms (Templates + Signed Forms). Run after `general_modules.sql` and seed_admin_data. |
| **seed_patient_queue.sql** | Seeds **Patient Queue** (Waiting + In Consultation) for OPD, ER, and Clinic. Run after `general_modules.sql` and seed_admin_data. |
| **seed_doctor_schedules.sql** | Creates `doctor_schedules` table (if missing) and seeds 2 schedules per doctor for **Doctor Schedules** page. Run after `doctors_insert.sql`. |
| **seed_appointments_report.sql** | Seeds appointments (Jan–Feb 2026) so **Appointments Report** shows real data. Run after seed_admin_data. |
| **seed_reports_all.sql** | Seeds **Billing Report** (current-month bills) and **ER Report** (er_triage in last 30 days). Run after seed_admin_data, er_triage.sql, and billing table. |
| **seed_er_transfer_active.sql** | Seeds active ER cases (waiting/in_progress) for **ER Transfer & Discharge** page. Run when no active cases exist. |
| **seed_insurance_setup.sql** | Seeds **Insurance Setup** (Providers, Policies, Coverage Rules) for all 3 tabs. Run after **general_modules.sql**. |
| **wards_and_beds_setup.sql** | Creates wards and beds; run before **Bed Occupancy Report** and **Patient Admission**. |
| **admissions.sql** | Creates admissions table and sample admissions; run after wards_and_beds and seed_admin_data for **Bed Occupancy** trend and **Patient Admission**. |

---

## Reports (real data)

- **Appointments Report** – Run **seed_appointments_report.sql** (and fix doctors dropdown uses roles; already done in code). Default date range or Jan 13–Feb 12 shows data.
- **Reschedule & Cancel** – Run **migrate_appointments_reschedule.sql** (adds cancelled_at, cancellation_reason, updated_by to appointments), then **seed_reschedule_appointments.sql** so the page shows upcoming appointments (today and tomorrow, status scheduled/confirmed/in_progress). Requires at least one patient and one doctor user.
- **Billing Report** – Run **seed_reports_all.sql** so current-month bills exist; also **seed_admin_data_part3.sql** has more billing rows.
- **ER Patient Statistics Report** – Run **er_triage.sql** (table + sample), then **seed_reports_all.sql** and/or **seed_er_transfer_active.sql** for cases in last 30 days. If the report shows summary counts but "No ER cases" in the list, run **migrate_er_report_patients.sql** to create placeholder patient rows for any `er_triage.patient_id` not in `patients` (the report code also uses LEFT JOIN so cases show even without this migration).
- **Bed Occupancy Report** – Run **wards_and_beds_setup.sql** then **admissions.sql** so wards, beds, and admissions exist; report shows totals and trend.

---

## Logins for real data (admin and patient)

After running the seeds above:

| Role   | Login / identifier      | Password      | Notes |
|--------|-------------------------|---------------|--------|
| **Admin**  | `admin`                 | `Hospital@2026` | Full access; dashboard, users, billing, ER, teleconsult, reports, etc. show real data. |
| **Patient**| `khel` (or base user 2) | `Hospital@2026` | **seed_patient_portal_data.sql** sets user 2’s password to this so you can test. This user sees **patient_id 1** (Jose Rizal). My Appointments, Telehealth, SOA, Medical History, Lab Results, E-Prescriptions show data after **ehr.sql**, **03_ehr_reference_patients.sql**, and **seed_patient_portal_data.sql**. |

---

## Admin pages that use this data

- **Admin Dashboard** – stats from patients, appointments, billing, payments, ER, beds, teleconsultations, audit-style metrics.
- **Manage Users / Roles** – users and roles from seed.
- **System Settings** – site name, currency (PHP), timezone (Asia/Manila).
- **Clinic Hours** – general clinic hours.
- **Audit Logs** – depends on your audit table schema (user_id vs employee_id); dashboard already handles both.
- **Insurance (admin)** – insurance providers and claims.
- **Billing / Payments** – billing records and payments; payments page uses view `patient_billing` (from `billing`).

## Patient portal pages that use this data

- **My Appointments** – appointments for the logged-in patient (patient_id from `patients.created_by` or email match).
- **Telehealth sessions** – teleconsultations for that patient.
- **Medical History** – diagnoses, medications, procedures, immunizations from `ehr_*` (after ehr.sql + 03 + seed_patient_portal_data).
- **Lab Results** – `ehr_lab_results` for that patient.
- **E-Prescriptions** – `e_prescriptions` for that patient.
- **View SOA** – billing/statement of account for that patient.

## Insurance Setup (admin)

- **Insurance Providers**, **Insurance Policies**, and **Coverage Rules** tabs show data after you run **general_modules.sql** (creates `insurance_providers`, `insurance_policies`, `coverage_rules`) and **seed_insurance_setup.sql** (adds 6 providers, 9 policies, 21 coverage rules). If `insurance_providers` is already filled (e.g. from seed_admin_data_part3), the seed only adds policies and rules.

## Consent Forms (admin)

- **Templates** and **Signed Forms** show data after you run `general_modules.sql` (creates the tables) and **seed_consent_forms.sql** (adds 5 templates and 5 signed forms for the patient user).

## Patient Queue (admin)

- **Waiting Queue** and **In Consultation** show data after you run `general_modules.sql` and **seed_patient_queue.sql** (adds OPD, ER, and Clinic queue entries for the patient user).

## Doctor Schedules (admin)

- **Doctor Schedules** shows doctors from the `doctors` table and their weekly slots. Run **doctors_insert.sql** (creates `doctors` and 6 doctors), then **seed_doctor_schedules.sql** (creates `doctor_schedules` and assigns 2 schedules per doctor) so each doctor shows e.g. "2 schedules" and the schedule cards have real data.

---

## Notes

- **Passwords** in seed users: use the same bcrypt hash as in the seed (e.g. `Hospital@2026` as in comments) or change after first login.
- **Database user** in `config/database.php` must have access to `hospital-core1-system` (create tables, insert, create view).
- If you use **audit_logs** with `employee_id` instead of `user_id`, the admin dashboard and recent activity logic already support both; no extra migration needed for that.
