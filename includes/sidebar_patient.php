<?php
// Ensure BASE_URL is defined
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

$role_name = $_SESSION['role_name'] ?? $_SESSION['user_role'] ?? 'patient';
$role_id = $_SESSION['role_id'] ?? null;
$current_page = basename($_SERVER['PHP_SELF']);

// Helper function for active menu item
function isActive($page) {
    global $current_page;
    return $current_page === $page ? 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white' : '';
}
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
            <!-- Dashboard -->
            <a href="<?php echo BASE_URL; ?>/index.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo $current_page === 'index.php' || strpos($current_page, 'dashboard') !== false ? 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><rect width="7" height="9" x="3" y="3" rx="1"></rect><rect width="7" height="5" x="14" y="3" rx="1"></rect><rect width="7" height="9" x="14" y="12" rx="1"></rect><rect width="7" height="5" x="3" y="16" rx="1"></rect></svg>
                <span class="sidebar-text text-sm">Dashboard</span>
            </a>

            <!-- My Information -->
            <div class="mb-3 mt-4">
                <h3 class="px-3 py-1.5 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 sidebar-text transition-colors duration-200">MY INFORMATION</h3>
                
                <a href="<?php echo BASE_URL; ?>/patient/profile.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('profile.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    <span class="sidebar-text text-sm">Profile</span>
                </a>
                
                <a href="<?php echo BASE_URL; ?>/patient/medical_history.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('medical_history.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    <span class="sidebar-text text-sm">Medical History</span>
                    <span class="ml-auto text-xs text-gray-400">(read-only)</span>
                </a>
            </div>

            <!-- Appointments -->
            <div class="mb-3 mt-4">
                <h3 class="px-3 py-1.5 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 sidebar-text">APPOINTMENTS</h3>
                
                <a href="<?php echo BASE_URL; ?>/patient/book_appointment.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('book_appointment.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="m9 16 2 2 4-4"></path></svg>
                    <span class="sidebar-text text-sm">Book Appointment</span>
                </a>
                
                <a href="<?php echo BASE_URL; ?>/patient/my_appointments.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('my_appointments.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><circle cx="12" cy="16" r="1"></circle><path d="M8 16h.01"></path><path d="M16 16h.01"></path></svg>
                    <span class="sidebar-text text-sm">My Appointments</span>
                </a>
            </div>

            <!-- Telehealth Sessions -->
            <div class="mb-3 mt-4">
                <h3 class="px-3 py-1.5 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 sidebar-text">TELEHEALTH</h3>
                
                <a href="<?php echo BASE_URL; ?>/patient/telehealth_sessions.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('telehealth_sessions.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.999"></path><rect x="2" y="6" width="14" height="12" rx="2"></rect></svg>
                    <span class="sidebar-text text-sm">Telehealth Sessions</span>
                </a>
            </div>

            <!-- Health Records -->
            <div class="mb-3 mt-4">
                <h3 class="px-3 py-1.5 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 sidebar-text">HEALTH RECORDS</h3>
                
                <a href="<?php echo BASE_URL; ?>/patient/e_prescriptions.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('e_prescriptions.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    <span class="sidebar-text text-sm">E-Prescriptions</span>
                </a>
                
                <a href="<?php echo BASE_URL; ?>/patient/lab_results.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('lab_results.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M10 2v2"></path><path d="M14 2v2"></path><path d="M10.5 2h3"></path><path d="M7 12a5 5 0 0 0 10 0Z"></path><path d="M7 12H4a2 2 0 0 0-2 2v1a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-1a2 2 0 0 0-2-2h-3"></path></svg>
                    <span class="sidebar-text text-sm">Lab Results</span>
                </a>
            </div>

            <!-- Billing -->
            <div class="mb-3 mt-4">
                <h3 class="px-3 py-1.5 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 sidebar-text">BILLING</h3>
                
                <a href="<?php echo BASE_URL; ?>/patient/view_soa.php" class="flex items-center px-3 py-2 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 sidebar-item transition-all duration-200 <?php echo isActive('view_soa.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                    <span class="sidebar-text text-sm">View SOA</span>
                </a>
            </div>
        </nav>

        <!-- Sidebar Footer - Empty for spacing -->
        <div class="p-3 border-t border-gray-200 dark:border-gray-700"></div>
    </div>
</aside>

