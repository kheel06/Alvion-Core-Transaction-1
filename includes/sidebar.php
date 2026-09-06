<?php
// Ensure BASE_URL is defined
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

$role_name = $_SESSION['role_name'] ?? $_SESSION['user_role'] ?? 'patient';
$role_id = $_SESSION['role_id'] ?? null;
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar Overlay (Mobile) -->
<div id="sidebarOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden sidebar-transition"></div>

<aside id="sidebar" class="fixed lg:static inset-y-0 left-0 z-50 bg-white dark:bg-gray-800 shadow-xl sidebar-transition transform -translate-x-full lg:translate-x-0 transition-all duration-300">
    <div class="flex flex-col h-full">
        <!-- Logo/Branding -->
        <div class="flex items-center justify-center px-4 py-5 border-b border-gray-200 dark:border-gray-700 transition-all duration-300">
            <div class="flex items-center justify-center sidebar-logo-full w-full">
                <img src="<?php echo BASE_URL; ?>/assets/img/alvion-logo-removebg.png" alt="Alvion" class="h-8 object-contain transition-opacity duration-200">
            </div>
            <div class="flex items-center justify-center sidebar-logo-collapsed hidden w-full">
                <img src="<?php echo BASE_URL; ?>/assets/img/alvion-logo-removebg.png" alt="Alvion" class="h-8 object-contain transition-opacity duration-200">
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-2.5 py-3 space-y-0.5 overflow-y-auto overflow-x-hidden">
            <?php
            // Helper function to check if menu item should be shown
            function showMenuItem($allowed_roles) {
                global $role_name, $role_id;
                if (in_array('*', $allowed_roles)) return true;
                if (in_array($role_name, $allowed_roles)) return true;
                // Check by role_id if provided
                $role_id_map = [
                    'admin' => 1, 'doctor' => 2, 'nurse' => 3, 'receptionist' => 4,
                    'appointment_coordinator' => 5, 'billing_staff' => 6, 'patient' => 7,
                    'pharmacist' => 8, 'lab_technician' => 9, 'housekeeping' => 10
                ];
                foreach ($allowed_roles as $role) {
                    if (isset($role_id_map[$role]) && $role_id == $role_id_map[$role]) return true;
                }
                return false;
            }
            
            // Helper function for active menu item
            function isActive($page) {
                global $current_page;
                return $current_page === $page ? 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white' : '';
            }
            ?>
            
            <!-- Dashboard -->
            <a href="<?php echo BASE_URL; ?>/index.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo $current_page === 'index.php' || strpos($current_page, 'dashboard') !== false ? 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><rect width="7" height="9" x="3" y="3" rx="1"></rect><rect width="7" height="5" x="14" y="3" rx="1"></rect><rect width="7" height="9" x="14" y="12" rx="1"></rect><rect width="7" height="5" x="3" y="16" rx="1"></rect></svg>
                <span class="sidebar-text text-sm">Dashboard</span>
            </a>

            <!-- Patient Management (SPRS) -->
            <?php if (showMenuItem(['admin', 'receptionist', 'doctor', 'nurse', 'billing_staff'])): ?>
            <div class="mb-3 mt-4">
                <h3 class="px-3 py-1.5 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 sidebar-text transition-colors duration-200">GENREAL</h3>
                
                <?php if (showMenuItem(['admin', 'receptionist', 'doctor', 'nurse'])): ?>
                <a href="<?php echo BASE_URL; ?>/modules/general/patient_registration.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('patient_registration.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" y1="8" x2="19" y2="14"></line><line x1="22" y1="11" x2="16" y2="11"></line></svg>
                    <span class="sidebar-text text-sm">Patient Registration</span>
                </a>
                <?php endif; ?>
                
                <?php if (showMenuItem(['admin', 'receptionist', 'doctor', 'nurse', 'billing_staff'])): ?>
                <a href="<?php echo BASE_URL; ?>/modules/general/view_patients.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('view_patients.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                    <span class="sidebar-text text-sm">View Patients</span>
                </a>
                <?php endif; ?>
                
                <?php if (showMenuItem(['admin', 'receptionist', 'billing_staff'])): ?>
                <a href="<?php echo BASE_URL; ?>/modules/general/insurance_setup.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('insurance_setup.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path></svg>
                    <span class="sidebar-text text-sm">Insurance Setup</span>
                </a>
                <?php endif; ?>
                
                <?php if (showMenuItem(['admin', 'receptionist', 'doctor', 'nurse'])): ?>
                <a href="<?php echo BASE_URL; ?>/modules/general/consent_forms.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('consent_forms.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M10 18h4"></path><path d="M12 16v4"></path></svg>
                    <span class="sidebar-text text-sm">Consent Forms</span>
                </a>
                <?php endif; ?>
                
                <?php if (showMenuItem(['admin', 'receptionist', 'nurse', 'doctor'])): ?>
                <a href="<?php echo BASE_URL; ?>/modules/general/patient_queue.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('patient_queue.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                    <span class="sidebar-text text-sm">Patient Queue</span>
                </a>
                <?php endif; ?>
                
                <?php if (showMenuItem(['admin', 'receptionist', 'doctor', 'nurse', 'appointment_coordinator'])): ?>
                <a href="<?php echo BASE_URL; ?>/modules/general/doctor_list.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('doctor_list.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"></path><path d="M8 15v1a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6v-4"></path><circle cx="20" cy="10" r="2"></circle></svg>
                    <span class="sidebar-text text-sm">Doctor List</span>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Appointments (ASS) -->
            <?php if (showMenuItem(['admin', 'receptionist', 'appointment_coordinator', 'doctor', 'billing_staff', 'patient'])): ?>
            <div class="mb-3 mt-4">
                <h3 class="px-3 py-1.5 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 sidebar-text">Appointments</h3>
                
                <?php if (showMenuItem(['admin', 'receptionist', 'appointment_coordinator', 'doctor'])): ?>
                <a href="<?php echo BASE_URL; ?>/modules/appointments/schedule.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('schedule.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="m9 16 2 2 4-4"></path></svg>
                    <span class="sidebar-text text-sm">Schedule Appointment</span>
                </a>
                <?php endif; ?>
                
                <?php if (showMenuItem(['admin', 'receptionist', 'appointment_coordinator'])): ?>
                <a href="<?php echo BASE_URL; ?>/modules/appointments/walkin.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('walkin.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><circle cx="12" cy="5" r="1"></circle><path d="m9 20 3-6 3 6"></path><path d="m6 8 6 2 6-2"></path><path d="M12 10v4"></path></svg>
                    <span class="sidebar-text text-sm">Walk-in Patients</span>
                </a>
                <?php endif; ?>
                
                <?php if (showMenuItem(['admin', 'appointment_coordinator', 'receptionist'])): ?>
                <a href="<?php echo BASE_URL; ?>/modules/appointments/reschedule.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('reschedule.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M21.5 2v6h-6M2.5 22v-6h6"></path><path d="M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"></path></svg>
                    <span class="sidebar-text text-sm">Reschedule & Cancel</span>
                </a>
                <?php endif; ?>
                
                <?php if (showMenuItem(['admin', 'appointment_coordinator'])): ?>
                <a href="<?php echo BASE_URL; ?>/modules/appointments/manage_schedules.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('manage_schedules.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"></path><path d="M8 15v1a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6v-4"></path><circle cx="20" cy="10" r="2"></circle></svg>
                    <span class="sidebar-text text-sm">Doctor Schedules</span>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Telehealth (TOCS) -->
            <?php if (showMenuItem(['admin', 'doctor', 'nurse', 'pharmacist', 'lab_technician', 'billing_staff', 'patient'])): ?>
            <div class="mb-3 mt-4">
                <h3 class="px-3 py-1.5 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 sidebar-text">Telehealth</h3>
                
                <?php if (showMenuItem(['admin', 'doctor', 'nurse', 'patient'])): ?>
                <a href="<?php echo BASE_URL; ?>/modules/telehealth/teleconsult.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('teleconsult.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.999"></path><rect x="2" y="6" width="14" height="12" rx="2"></rect></svg>
                    <span class="sidebar-text text-sm">Teleconsultation</span>
                </a>
                <?php endif; ?>
                
                <?php if (showMenuItem(['admin', 'doctor'])): ?>
                <a href="<?php echo BASE_URL; ?>/modules/telehealth/e_prescriptions.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('e_prescriptions.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    <span class="sidebar-text text-sm">E-Prescriptions</span>
                </a>
                <?php endif; ?>
                
                <?php if (showMenuItem(['admin', 'doctor'])): ?>
                <a href="<?php echo BASE_URL; ?>/modules/telehealth/e_labs.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('e_labs.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M10 2v2"></path><path d="M14 2v2"></path><path d="M10.5 2h3"></path><path d="M7 12a5 5 0 0 0 10 0Z"></path><path d="M7 12H4a2 2 0 0 0-2 2v1a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-1a2 2 0 0 0-2-2h-3"></path></svg>
                    <span class="sidebar-text text-sm">E-Lab Orders</span>
                </a>
                <?php endif; ?>
                

                
                <?php if (showMenuItem(['admin', 'doctor'])): ?>
                <a href="<?php echo BASE_URL; ?>/modules/telehealth/followup.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('followup.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="M12 6v6"></path><path d="M9 9h6"></path></svg>
                    <span class="sidebar-text text-sm">Follow-up Scheduling</span>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Emergency Room (EERTS) -->
            <?php if (showMenuItem(['admin', 'receptionist', 'doctor', 'nurse'])): ?>
            <div class="mb-3 mt-4">
                <h3 class="px-3 py-1.5 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 sidebar-text">Emergency Room</h3>
                
                <?php if (showMenuItem(['admin', 'nurse', 'doctor'])): ?>
                <a href="<?php echo BASE_URL; ?>/modules/er_triage/triage.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('triage.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M10 2v6"></path><path d="M14 2v6"></path><path d="M18 8H2"></path><path d="M20 8v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8"></path><path d="M4 12h16"></path><path d="M8 18h8"></path></svg>
                    <span class="sidebar-text text-sm">Triage Assessment</span>
                </a>
                <?php endif; ?>
                
                <?php if (showMenuItem(['admin', 'nurse', 'doctor'])): ?>
                <a href="<?php echo BASE_URL; ?>/modules/er_triage/er_dashboard.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('er_dashboard.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="m12 14 4-4"></path><path d="M3.34 19a10 10 0 1 1 17.32 0"></path></svg>
                    <span class="sidebar-text text-sm">ER Dashboard</span>
                </a>
                <?php endif; ?>
                
                <?php if (showMenuItem(['admin', 'nurse', 'doctor'])): ?>
                <a href="<?php echo BASE_URL; ?>/modules/er_triage/transfer.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('transfer.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M3 12h18"></path><path d="M12 3v18"></path><path d="M8 8l-4-4 4-4"></path><path d="M16 16l4 4-4 4"></path></svg>
                    <span class="sidebar-text text-sm">Transfer/Discharge</span>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Inpatient (IBMS) -->
            <?php if (showMenuItem(['admin', 'nurse', 'doctor', 'housekeeping', 'billing_staff'])): ?>
            <div class="mb-3 mt-4">
                <h3 class="px-3 py-1.5 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 sidebar-text">Inpatient</h3>
                
                <?php if (showMenuItem(['admin', 'nurse', 'doctor', 'housekeeping'])): ?>
                <a href="<?php echo BASE_URL; ?>/modules/inpatient/bed_management.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('bed_management.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M2 4v16"></path><path d="M2 8h18a2 2 0 0 1 2 2v10"></path><path d="M2 17h20"></path><path d="M6 8V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"></path></svg>
                    <span class="sidebar-text text-sm">Bed Management</span>
                </a>
                <?php endif; ?>
                
                <?php if (showMenuItem(['admin', 'doctor', 'nurse'])): ?>
                <a href="<?php echo BASE_URL; ?>/modules/inpatient/admission.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('admission.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
                    <span class="sidebar-text text-sm">Admissions</span>
                </a>
                <?php endif; ?>
                
                <?php if (showMenuItem(['admin', 'nurse', 'doctor'])): ?>
                <a href="<?php echo BASE_URL; ?>/modules/inpatient/transfers.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('transfers.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><polyline points="8 3 4 7 8 11"></polyline><polyline points="16 21 20 17 16 13"></polyline><line x1="4" y1="7" x2="20" y2="7"></line><line x1="4" y1="17" x2="20" y2="17"></line></svg>
                    <span class="sidebar-text text-sm">Bed Transfers</span>
                </a>
                <?php endif; ?>
                
                <?php if (showMenuItem(['admin', 'doctor', 'nurse'])): ?>
                <a href="<?php echo BASE_URL; ?>/modules/inpatient/discharge.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('discharge.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    <span class="sidebar-text text-sm">Discharge</span>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Billing & Payments -->
            <?php if (showMenuItem(['admin', 'billing_staff'])): ?>
            <div class="mb-3 mt-4">
                <h3 class="px-3 py-1.5 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 sidebar-text">Billing</h3>
                
                <a href="<?php echo BASE_URL; ?>/modules/billing/payments.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('payments.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v12"></path><path d="M15 9a3 3 0 1 0-6 0"></path></svg>
                    <span class="sidebar-text text-sm">Payment Processing</span>
                </a>
                
                <a href="<?php echo BASE_URL; ?>/modules/billing/insurance.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('insurance.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path></svg>
                    <span class="sidebar-text text-sm">Insurance Management</span>
                </a>
            </div>
            <?php endif; ?>

            <!-- Reports -->
            <?php if (showMenuItem(['admin', 'billing_staff', 'appointment_coordinator'])): ?>
            <div class="mb-3 mt-4">
                <h3 class="px-3 py-1.5 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 sidebar-text">Reports</h3>
                
                <?php if (showMenuItem(['admin', 'appointment_coordinator'])): ?>
                <a href="<?php echo BASE_URL; ?>/reports/appointments_report.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('appointments_report.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line></svg>
                    <span class="sidebar-text text-sm">Appointments Report</span>
                </a>
                <?php endif; ?>
                
                <?php if (showMenuItem(['admin', 'billing_staff'])): ?>
                <a href="<?php echo BASE_URL; ?>/reports/billing_report.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('billing_report.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><polyline points="22 6 13.5 15.5 8.5 10.5 2 17"></polyline><polyline points="16 6 22 6 22 12"></polyline></svg>
                    <span class="sidebar-text text-sm">Billing Report</span>
                </a>
                <?php endif; ?>
                
                <?php if (showMenuItem(['admin'])): ?>
                <a href="<?php echo BASE_URL; ?>/reports/bed_occupancy.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('bed_occupancy.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>
                    <span class="sidebar-text text-sm">Bed Occupancy</span>
                </a>
                <?php endif; ?>
                
                <?php if (showMenuItem(['admin', 'nurse', 'doctor'])): ?>
                <a href="<?php echo BASE_URL; ?>/reports/er_report.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('er_report.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M12 22v-4"></path><path d="M2 22h20"></path><path d="M20 22v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8"></path><path d="M12 6V2"></path><path d="M8 6h8"></path><path d="M8 14h8"></path></svg>
                    <span class="sidebar-text text-sm">ER Report</span>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Administration -->
            <?php if (showMenuItem(['admin'])): ?>
            <div class="mb-3 mt-4">
                <h3 class="px-3 py-1.5 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 sidebar-text">ACCOUNT</h3>
                
                <a href="<?php echo BASE_URL; ?>/admin/users/manage_users.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('manage_users.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    <span class="sidebar-text text-sm">User Management</span>
                </a>
                
                <a href="<?php echo BASE_URL; ?>/admin/roles/manage_roles.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('manage_roles.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path></svg>
                    <span class="sidebar-text text-sm">Role Management</span>
                </a>
                
                <a href="<?php echo BASE_URL; ?>/admin/system/system_settings.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('system_settings.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    <span class="sidebar-text text-sm">System Settings</span>
                </a>
                
                <a href="<?php echo BASE_URL; ?>/admin/system/audit_logs.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('audit_logs.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><path d="M12 11h4"></path><path d="M12 16h4"></path><path d="M8 11h.01"></path><path d="M8 16h.01"></path></svg>
                    <span class="sidebar-text text-sm">Audit Logs</span>
                </a>
            </div>
            <?php endif; ?>
        </nav>

        <!-- Sidebar Footer - Empty for spacing -->
        <div class="p-3 border-t border-gray-200 dark:border-gray-700"></div>
    </div>
</aside>