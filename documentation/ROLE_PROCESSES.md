# Hospital Core System - Role-Based Processes & Permissions

This document outlines the core responsibilities, data access permissions, and processes for each role in the Hospital Core System.

---

## 1. ADMIN / HOSPITAL ADMINISTRATOR

### Core Responsibilities
- Manages the entire system configuration
- Oversees operations, departments, and compliance
- Handles user accounts, roles, and audit logs

### Admin Can Access / Modify the Following Data

#### Read Access (All Records)
- ✅ All patient records (read-only for clinical info)
- ✅ All appointments & schedules
- ✅ All triage and ER logs
- ✅ All inpatient admissions, transfers, discharges
- ✅ All billing data, SOAs, insurance claims
- ✅ All user accounts and permissions
- ✅ All system settings, logs, backups, maintenance

#### Write/Modify Access
- ✅ User accounts (create, edit, deactivate)
- ✅ Role permissions and assignments
- ✅ System settings and configuration
- ✅ Clinic hours and room availability
- ✅ Service catalogs and pricing
- ✅ Insurance provider setup and approval
- ✅ Audit logs (view only, cannot delete)

### Admin Processes

#### User Management
1. **Create/Edit Users**
   - Create new user accounts (doctor, staff, receptionist, finance)
   - Assign roles and permissions
   - Edit user profiles and access levels
   - Deactivate/reactivate user accounts
   - Reset user passwords

#### System Configuration
2. **Configure Clinic Operations**
   - Set clinic hours and operating schedules
   - Manage room availability and assignments
   - Configure services and procedures catalog
   - Set consultation fees and service pricing
   - Manage department settings

#### Insurance Management
3. **Insurance Provider Setup**
   - Add new insurance providers
   - Configure insurance plans and coverage
   - Approve insurance provider setup
   - Manage HMO agreements and contracts
   - Set reimbursement rates

#### Audit & Compliance
4. **Audit Logs Management**
   - View all system actions and changes
   - Monitor user activities
   - Track data modifications
   - Generate audit reports
   - Export logs for compliance

#### Reporting
5. **Generate Reports**
   - Patient statistics and demographics
   - Appointment reports
   - Billing and financial reports
   - ER and triage reports
   - Bed occupancy reports
   - User activity reports
   - System performance reports

#### Maintenance
6. **System Maintenance**
   - Database backups and restoration
   - System updates and patches
   - Performance monitoring
   - Error log management
   - Data archiving

---

## 2. DOCTOR

### Core Responsibilities
- Provides patient care
- Handles consultations (OPD + Telehealth)
- Issues prescriptions, labs, medical orders
- Updates patient EHR

### Doctor Can Access / Modify the Following Data

#### Patient Medical Records
- ✅ Patient medical history (full access)
- ✅ Triage notes & ER cases assigned to them
- ✅ Their own appointment list and schedule
- ✅ Their teleconsultation sessions
- ✅ EHR notes, progress notes, diagnoses
- ✅ E-prescriptions & lab requests
- ✅ Inpatient orders (admission, transfer, discharge)

#### Read-Only Access
- ✅ Patient demographics
- ✅ Previous consultation notes
- ✅ Lab results and diagnostic reports
- ✅ Medication history

### Doctor Processes

#### Schedule Management
1. **View Today's Schedule / Queue**
   - View daily appointment schedule
   - Check patient queue
   - See walk-in patients assigned
   - Review teleconsultation schedule
   - Manage availability status

#### Patient Consultations
2. **Perform Consultations (OPD/Telehealth)**
   - Access patient medical history
   - Conduct in-person consultations
   - Conduct telehealth video consultations
   - Review triage notes and vitals
   - Document chief complaint and history

#### Clinical Documentation
3. **Encode Diagnosis & Treatment**
   - Document diagnosis (ICD-10 codes)
   - Record treatment plans
   - Add progress notes
   - Update patient EHR
   - Document follow-up requirements

#### Prescription Management
4. **Issue E-Prescriptions**
   - Create electronic prescriptions
   - Select medications and dosages
   - Set prescription duration
   - Add special instructions
   - Send to pharmacy system
   - Print prescription for patient

#### Laboratory Orders
5. **Issue E-Labs**
   - Request laboratory tests
   - Select test panels
   - Add clinical notes for lab
   - Set priority (routine/urgent/stat)
   - Send to laboratory system
   - View lab results when available

#### Inpatient Management
6. **Approve or Request Inpatient Admission**
   - Review admission requests
   - Approve inpatient admissions
   - Request bed assignments
   - Set admission orders
   - Document admission diagnosis

7. **Update Discharge Notes**
   - Prepare discharge summary
   - Document final diagnosis
   - List discharge medications
   - Provide follow-up instructions
   - Set discharge date and time
   - Complete discharge process

#### Patient Care Coordination
8. **Transfer Management**
   - Request patient transfers
   - Approve transfer requests
   - Document transfer reasons
   - Update transfer orders

---

## 3. STAFF (Nurse, Clerk, Ward Staff)

### Core Responsibilities
- Assists in patient registration
- Updates basic patient info
- Handles queueing
- Performs triage assessments
- Manages bed transfers & cleaning status

### Staff Can Access / Modify the Following Data

#### Patient Information
- ✅ Patient demographics (edit access)
- ✅ Basic patient information
- ✅ Walk-in registrations
- ✅ Initial ER documentation

#### Clinical Data
- ✅ Triage level & vitals (input/update)
- ✅ Room/bed assignments
- ✅ Inpatient monitoring logs
- ✅ Queue numbers and status

#### Read-Only Access
- ✅ Patient appointment history
- ✅ Basic medical history (view only)

### Staff Processes

#### Patient Registration
1. **Register Walk-in Patients**
   - Collect patient demographics
   - Verify identification
   - Create new patient record
   - Update existing patient information
   - Collect insurance information
   - Assign temporary patient ID

#### Triage Assessment
2. **Perform Triage**
   - Assess patient condition
   - Assign triage level (1-5)
   - Document chief complaint
   - Record initial observations
   - Set priority for doctor assignment

#### Vital Signs
3. **Encode Vitals**
   - Record blood pressure
   - Record temperature
   - Record pulse rate
   - Record respiratory rate
   - Record oxygen saturation
   - Record pain scale
   - Update vital signs in system

#### Queue Management
4. **Add Patient to Emergency or Outpatient Queue**
   - Assign queue number
   - Set queue priority
   - Route to appropriate department
   - Notify doctor of new patient
   - Update queue status
   - Manage queue flow

#### Bed Management
5. **Assign Bed When Doctor Approves Admission**
   - View available beds
   - Assign bed to patient
   - Update bed status to "Occupied"
   - Document bed assignment
   - Notify ward staff

6. **Mark Bed as "Cleaning / Ready / Occupied"**
   - Update bed cleaning status
   - Mark bed as ready for new patient
   - Update bed occupancy status
   - Maintain bed inventory
   - Coordinate with housekeeping

#### Patient Monitoring
7. **Inpatient Monitoring**
   - Record routine vitals
   - Update monitoring logs
   - Document patient observations
   - Report changes to doctor
   - Maintain shift reports

---

## 4. PATIENT (Portal User)

### Core Responsibilities
- Books appointments (in-person, telehealth, walk-in intent)
- Views prescriptions, lab results
- Views billing & SOA

### Patient Can Access

#### Personal Information
- ✅ Their own profile & EHR preview
- ✅ Personal demographics (edit own info)
- ✅ Contact information

#### Medical Records (View Only)
- ✅ Their appointment history
- ✅ Telehealth sessions (past and scheduled)
- ✅ Lab results (when released by doctor)
- ✅ Prescriptions (current and past)
- ✅ Medical history summary
- ✅ Diagnosis records

#### Financial Information
- ✅ Billing statements (SOA)
- ✅ Payment history
- ✅ Outstanding balances
- ✅ Insurance claims status

### Patient Processes

#### Account Management
1. **Register Personal Account**
   - Create patient portal account
   - Verify email address
   - Set up password
   - Complete profile information
   - Link to existing patient record (if applicable)

#### Appointment Booking
2. **Choose Doctor and Schedule**
   - Browse available doctors
   - View doctor profiles and specialties
   - Check doctor availability
   - Select preferred date and time
   - Choose appointment type

3. **Choose Appointment Type**
   - **In-person**: Schedule clinic visit
   - **Walk-in**: Reserve slot for walk-in
   - **Telehealth video**: Schedule video consultation
   - Receive appointment confirmation
   - Get appointment reminders

#### Consent & Forms
4. **Fill Consent Forms**
   - Complete medical consent forms
   - Sign treatment consent
   - Acknowledge privacy policy
   - Complete health questionnaire
   - Submit pre-appointment forms

#### Payment Processing
5. **Pay Online via Payment Gateway**
   - View outstanding bills
   - Select payment method
   - Process online payment
   - Receive payment confirmation
   - Download payment receipt

#### Medical Records Access
6. **View Medical Information**
   - View lab results (when available)
   - View prescriptions
   - Download medical records
   - View appointment summaries
   - Access telehealth session recordings (if available)

---

## 5. RECEPTIONIST (Front Desk)

### Core Responsibilities
- Handles registration, queueing, and appointment confirmation
- Manages walk-in patients
- Coordinates between patients and doctors

### Receptionist Can Access / Modify Data

#### Patient Information
- ✅ Patient registration info (full access)
- ✅ Patient demographics
- ✅ Contact information
- ✅ Insurance information

#### Appointment Management
- ✅ Appointments & schedules (view and modify)
- ✅ Queue numbers (assign and manage)
- ✅ Appointment status (confirm, reschedule, cancel)

#### Financial Information (Limited)
- ✅ Front-line billing estimation (optional)
- ✅ Basic charge information
- ✅ Insurance verification status

### Receptionist Processes

#### Patient Registration
1. **Register New or Returning Patient**
   - Collect patient information
   - Verify identification documents
   - Create new patient record
   - Update existing patient information
   - Link to previous records
   - Complete registration forms

#### Insurance Verification
2. **Verify ID & Insurance**
   - Verify patient identification
   - Check insurance eligibility
   - Verify insurance coverage
   - Confirm insurance provider
   - Document insurance information
   - Update insurance status

#### Queue Management
3. **Assign Queue Number**
   - Generate queue number
   - Assign to appropriate department
   - Set queue priority
   - Display queue status
   - Manage queue flow
   - Notify patients when called

#### Appointment Management
4. **Book, Reschedule, or Cancel Appointments**
   - Schedule new appointments
   - Check doctor availability
   - Reschedule existing appointments
   - Cancel appointments
   - Send appointment confirmations
   - Send appointment reminders
   - Update appointment status

#### Walk-in Management
5. **Process Walk-in & Route to Doctor**
   - Register walk-in patients
   - Perform initial triage (basic)
   - Assign to appropriate doctor
   - Add to walk-in queue
   - Notify doctor of walk-in patient
   - Coordinate patient flow

#### Patient Coordination
6. **Coordinate Between Patients and Doctors**
   - Communicate patient arrival
   - Relay messages between parties
   - Update appointment status
   - Handle patient inquiries
   - Manage waiting room
   - Facilitate patient flow

---

## 6. FINANCE STAFF

### Core Responsibilities
- Handles billing, charges, SOA generation
- Manages insurance claims and HMO processing
- Accepts payments (cash, card, online)

### Finance Staff Can Access / Modify Data

#### Billing Information
- ✅ Charges, procedures, medications
- ✅ Room charges and service fees
- ✅ Patient SOA (Statement of Account)
- ✅ Billing adjustments and corrections

#### Insurance Management
- ✅ Insurance coverage verification notes
- ✅ HMO LOA (Letter of Authorization)
- ✅ Insurance claim submissions
- ✅ Reimbursement processing

#### Payment Records
- ✅ Payment records (all payment methods)
- ✅ Payment history
- ✅ Outstanding balances
- ✅ Payment receipts and invoices

### Finance Processes

#### Billing Generation
1. **Generate Billing**
   - Create patient bills
   - Generate SOA (Statement of Account)
   - Calculate total charges
   - Apply insurance coverage
   - Generate itemized bills
   - Send bills to patients

#### Charge Management
2. **Add Room Charges, Lab Charges, Doctor Fees**
   - Add room charges (daily rates)
   - Add laboratory charges
   - Add doctor consultation fees
   - Add procedure charges
   - Add medication charges
   - Add service fees
   - Apply discounts (if authorized)

#### Insurance Processing
3. **Process Insurance (HMO LOA, Reimbursement)**
   - Verify insurance eligibility
   - Process HMO LOA requests
   - Submit insurance claims
   - Track claim status
   - Process reimbursements
   - Handle insurance denials
   - Coordinate with insurance providers

#### Payment Processing
4. **Accept and Log Payments**
   - Process cash payments
   - Process card payments
   - Process online payments
   - Record payment transactions
   - Issue payment receipts
   - Update account balances
   - Reconcile payments

#### Financial Reporting
5. **Generate Billing Reports**
   - Daily revenue reports
   - Outstanding balances report
   - Payment collection reports
   - Insurance claims report
   - Service utilization reports
   - Financial summaries

#### Account Management
6. **Account Reconciliation**
   - Reconcile daily payments
   - Match payments to bills
   - Handle payment discrepancies
   - Process refunds (if authorized)
   - Update account status

---

## Access Control Summary

| Role | Patient Records | Appointments | Billing | System Config | User Management |
|------|----------------|--------------|---------|---------------|-----------------|
| **Admin** | Read All | Full Access | Full Access | Full Access | Full Access |
| **Doctor** | Own Patients | Own Schedule | View Only | None | None |
| **Staff** | Basic Info | Queue Only | None | None | None |
| **Patient** | Own Only | Own Only | Own Only | None | Own Profile |
| **Receptionist** | Registration | Full Access | Estimate Only | None | None |
| **Finance** | Billing Info | View Only | Full Access | None | None |

---

## Data Modification Permissions

| Data Type | Admin | Doctor | Staff | Patient | Receptionist | Finance |
|-----------|-------|--------|-------|---------|--------------|---------|
| Patient Demographics | ✅ | View | ✅ | Own Only | ✅ | View |
| Medical Records | View | ✅ | View | View Own | View | None |
| Appointments | ✅ | Own | Queue | Own | ✅ | View |
| Billing | ✅ | View | None | View Own | Estimate | ✅ |
| Prescriptions | View | ✅ | View | View Own | View | None |
| Lab Results | View | ✅ | View | View Own | View | None |
| System Settings | ✅ | None | None | None | None | None |
| User Accounts | ✅ | None | None | Own | None | None |

---

## Notes

- All roles have access to their own profile management
- Audit logs track all data modifications
- Sensitive medical information is protected by HIPAA-equivalent privacy rules
- Role-based access control (RBAC) is enforced at the application level
- All data access is logged for compliance and security

---

**Document Version:** 1.0  
**Last Updated:** 2025  
**Maintained By:** System Administrator

