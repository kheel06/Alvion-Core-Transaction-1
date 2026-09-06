<?php if (isset($_SESSION['user_id'])): ?>
            </main>
        </div>
    </div>
<?php else: ?>
    </main>
<?php endif; ?>

<!-- Logout Loading Overlay -->
<div id="logoutLoadingOverlay" class="hidden fixed inset-0 bg-gray-900/60 dark:bg-gray-900/70 backdrop-blur-sm z-[9998] flex items-center justify-center transition-opacity duration-200 ease-out opacity-0" aria-hidden="true">
    <div data-logout-dialog class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl px-8 py-10 flex flex-col items-center text-center max-w-sm w-full mx-4 transform transition-all duration-200 ease-out scale-95 opacity-0">
        <div class="h-16 w-16 rounded-full bg-primary-100 dark:bg-primary-600/20 flex items-center justify-center mb-5">
            <svg class="animate-spin h-8 w-8 text-primary-600 dark:text-primary-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" role="img" aria-label="Loading">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
            </svg>
        </div>
        <p class="text-lg font-semibold text-gray-900 dark:text-white">Logging you out</p>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Please wait while we securely end your session.</p>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed right-4 flex flex-col items-end justify-start space-y-3 pointer-events-none z-50" style="max-width: 24rem;"></div>

<!-- Alert Modal (Flowbite) -->
<div id="alertModal" tabindex="-1" aria-hidden="true" class="hidden fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="document.getElementById('alertModal').classList.add('hidden')"></div>
    <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex flex-col items-center text-center">
            <div id="alertModalIcon" class="mx-auto flex items-center justify-center h-12 w-12 rounded-full mb-4">
                <!-- Icon will be inserted here -->
            </div>
            <h3 id="alertModalTitle" class="text-lg font-semibold mb-2">
                <!-- Title will be inserted here -->
            </h3>
            <p id="alertModalMessage" class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                <!-- Message will be inserted here -->
            </p>
            <div id="alertModalFooter" class="flex gap-3 w-full justify-center">
                <button id="alertModalOkBtn" type="button" class="px-4 py-2 text-sm font-medium text-white rounded-lg focus:ring-4 focus:outline-none">
                    OK
                </button>
                <button id="alertModalCancelBtn" type="button" class="hidden px-4 py-2 text-sm font-medium text-gray-500 bg-white dark:bg-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 focus:ring-4 focus:outline-none focus:ring-gray-200 dark:focus:ring-gray-700">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Flowbite JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/1.8.1/flowbite.min.js"></script>

<!-- Custom JS -->
<script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/toast.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/alert-modal.js"></script>
<script>
// Full Page Loader Management
(function() {
    'use strict';
    
    const pageLoader = document.getElementById('pageLoader');
    const loaderText = document.getElementById('loaderText');
    const loaderProgress = document.getElementById('loaderProgress');
    
    if (!pageLoader) return;
    
    let progressInterval = null;
    let currentProgress = 0;
    const maxProgress = 90; // Don't go to 100% until page is fully loaded
    
    // Simulate progress
    function simulateProgress() {
        if (currentProgress < maxProgress) {
            // Increment progress with decreasing speed (easing effect)
            const increment = Math.max(0.5, (maxProgress - currentProgress) * 0.1);
            currentProgress = Math.min(currentProgress + increment, maxProgress);
            
            if (loaderProgress) {
                loaderProgress.style.width = currentProgress + '%';
            }
        }
    }
    
    // Start progress simulation
    function startProgress() {
        currentProgress = 0;
        if (loaderProgress) {
            loaderProgress.style.width = '0%';
        }
        
        // Update progress every 50ms for smooth animation
        progressInterval = setInterval(simulateProgress, 50);
    }
    
    // Complete progress
    function completeProgress() {
        if (progressInterval) {
            clearInterval(progressInterval);
            progressInterval = null;
        }
        
        if (loaderProgress) {
            loaderProgress.style.width = '100%';
        }
    }
    
    // Hide loader with fade out
    function hideLoader() {
        completeProgress();
        
        // Re-enable body scrolling
        document.body.style.overflow = '';
        document.documentElement.style.overflow = '';
        
        // Wait a bit for progress to complete, then fade out
        setTimeout(() => {
            if (pageLoader) {
                pageLoader.style.opacity = '0';
                setTimeout(() => {
                    if (pageLoader) {
                        pageLoader.style.display = 'none';
                        // Remove from DOM after animation
                        setTimeout(() => {
                            if (pageLoader && pageLoader.parentNode) {
                                pageLoader.parentNode.removeChild(pageLoader);
                            }
                        }, 300);
                    }
                }, 300);
            }
        }, 200);
    }
    
    // Prevent body scrolling while loader is active
    document.body.style.overflow = 'hidden';
    document.documentElement.style.overflow = 'hidden';
    
    // Show loader immediately (it's already visible in HTML)
    // Start progress simulation
    startProgress();
    
    // Update loader text based on page state
    function updateLoaderText(text) {
        if (loaderText) {
            loaderText.textContent = text;
        }
    }
    
    // Handle different loading states
    if (document.readyState === 'loading') {
        updateLoaderText('Loading page...');
    } else if (document.readyState === 'interactive') {
        updateLoaderText('Loading content...');
        currentProgress = 50; // Already partially loaded
    } else {
        updateLoaderText('Finalizing...');
        currentProgress = 75; // Almost done
    }
    
    // Hide loader when DOM is ready
    if (document.readyState === 'complete') {
        updateLoaderText('Almost done...');
        setTimeout(hideLoader, 300);
    } else {
        // Wait for page to fully load
        window.addEventListener('load', function() {
            updateLoaderText('Almost done...');
            setTimeout(hideLoader, 300);
        });
        
        // Also handle pageshow event (for back/forward cache)
        window.addEventListener('pageshow', function(event) {
            // If page was loaded from cache, hide loader immediately
            if (event.persisted) {
                hideLoader();
            } else {
                updateLoaderText('Almost done...');
                setTimeout(hideLoader, 300);
            }
        });
    }
    
    // Fallback: Hide loader after maximum wait time (5 seconds)
    setTimeout(function() {
        if (pageLoader && pageLoader.style.display !== 'none') {
            hideLoader();
        }
    }, 5000);
    
    // Update progress on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function() {
        updateLoaderText('Loading content...');
        currentProgress = Math.max(currentProgress, 60);
    });
    
    // Show loader on navigation (for same-origin links)
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a[href]');
        if (!link) return;
        
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
        if (link.target && link.target !== '_self') return;
        if (link.origin !== window.location.origin && !href.startsWith('/')) return;
        
        // Don't show loader for logout links (they have their own loader)
        if (link.hasAttribute('data-logout-trigger')) return;
        
        // Show loader immediately if it exists and is hidden
        const currentLoader = document.getElementById('pageLoader');
        if (currentLoader && currentLoader.style.display === 'none') {
            currentLoader.style.display = 'flex';
            currentLoader.style.opacity = '1';
            document.body.style.overflow = 'hidden';
            document.documentElement.style.overflow = 'hidden';
            startProgress();
            updateLoaderText('Loading page...');
        }
        // If loader was removed from DOM, the new page will have its own loader
    }, true); // Use capture phase to catch early
})();

document.addEventListener('DOMContentLoaded', function() {
    const logoutLinks = document.querySelectorAll('[data-logout-trigger="true"]');
    const overlay = document.getElementById('logoutLoadingOverlay');
    const overlayDialog = overlay ? overlay.querySelector('[data-logout-dialog]') : null;
    const toastContainer = document.getElementById('toast-container');
    
    // Position toast container below header
    function positionToastContainer() {
        if (toastContainer) {
            const header = document.querySelector('header');
            if (header) {
                const headerRect = header.getBoundingClientRect();
                const headerHeight = headerRect.height;
                toastContainer.style.top = (headerHeight + 8) + 'px'; // 8px spacing below header
            } else {
                // Fallback if header not found (public pages)
                toastContainer.style.top = '1rem';
            }
        }
    }
    
    // Set initial position
    positionToastContainer();
    
    // Update position on window resize (in case header height changes)
    window.addEventListener('resize', positionToastContainer);

    function showLogoutOverlay() {
        if (!overlay || overlay.dataset.visible === 'true') return;
        overlay.dataset.visible = 'true';
        overlay.classList.remove('hidden');
        overlay.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(() => {
            overlay.classList.remove('opacity-0');
            if (overlayDialog) {
                overlayDialog.classList.remove('opacity-0');
                overlayDialog.classList.remove('scale-95');
            }
        });
    }

    logoutLinks.forEach(link => {
        link.addEventListener('click', function(event) {
            const targetUrl = this.getAttribute('href');
            if (!targetUrl) {
                return;
            }
            event.preventDefault();
            showLogoutOverlay();
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 450);
        });
    });

});
</script>

<?php
// Show toast messages if any (using toast.js functions)
$toastMessages = [];
if (isset($_SESSION['success'])) {
    $toastMessages[] = ['type' => 'success', 'message' => $_SESSION['success']];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $toastMessages[] = ['type' => 'error', 'message' => $_SESSION['error']];
    unset($_SESSION['error']);
}
if (isset($_SESSION['warning'])) {
    $toastMessages[] = ['type' => 'warning', 'message' => $_SESSION['warning']];
    unset($_SESSION['warning']);
}
if (isset($_SESSION['info'])) {
    $toastMessages[] = ['type' => 'info', 'message' => $_SESSION['info']];
    unset($_SESSION['info']);
}

if (!empty($toastMessages)) {
    echo "<script>";
    echo "document.addEventListener('DOMContentLoaded', function() {";
    foreach ($toastMessages as $toast) {
        $escapedMessage = addslashes($toast['message']);
        $type = $toast['type'];
        // Use the appropriate toast function based on type
        if ($type === 'success') {
            echo "if (typeof showSuccess === 'function') { showSuccess('{$escapedMessage}'); } else if (typeof showToast === 'function') { showToast('success', '{$escapedMessage}'); }";
        } elseif ($type === 'error') {
            echo "if (typeof showError === 'function') { showError('{$escapedMessage}'); } else if (typeof showToast === 'function') { showToast('error', '{$escapedMessage}'); }";
        } elseif ($type === 'warning') {
            echo "if (typeof showWarning === 'function') { showWarning('{$escapedMessage}'); } else if (typeof showToast === 'function') { showToast('warning', '{$escapedMessage}'); }";
        } elseif ($type === 'info') {
            echo "if (typeof showInfo === 'function') { showInfo('{$escapedMessage}'); } else if (typeof showToast === 'function') { showToast('info', '{$escapedMessage}'); }";
        } else {
            echo "if (typeof showToast === 'function') { showToast('{$type}', '{$escapedMessage}'); }";
        }
    }
    echo "});";
    echo "</script>";
}
?>

<!-- Session Timeout Modal -->
<?php 
// Check if user is logged in AND is an employee
$role_name = $_SESSION['role_name'] ?? $_SESSION['user_role'] ?? 'patient';
$normalized_role = strtolower(str_replace(' ', '_', $role_name));
$employee_roles = ['admin', 'doctor', 'staff', 'receptionist', 'finance_staff', 'finance staff'];
$is_employee_role = in_array($normalized_role, $employee_roles, true);

if (isset($_SESSION['user_id']) && $is_employee_role): 
?>
<div id="sessionTimeoutModal" tabindex="-1" aria-hidden="true" class="hidden fixed inset-0 z-[9999] overflow-y-auto flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm transition-opacity duration-300">
    <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full p-6 text-center transform transition-all scale-100">
        <div class="mb-4 mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-yellow-100 dark:bg-yellow-900/30 text-yellow-500">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        </div>
        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Session Timeout</h3>
        <p class="text-gray-500 dark:text-gray-400 mb-6">
            Your session will expire in <span id="sessionTimeoutCountdown" class="font-bold text-red-500">15</span> seconds due to inactivity.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <button onclick="sessionMonitor.logout()" class="w-full sm:w-auto px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-4 focus:outline-none focus:ring-gray-200 dark:bg-gray-700 dark:text-white dark:border-gray-600 dark:hover:bg-gray-600 dark:focus:ring-gray-700">
                Log Out
            </button>
            <button onclick="sessionMonitor.stayLoggedIn()" class="w-full sm:w-auto px-5 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 focus:ring-4 focus:outline-none focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
                Stay Logged In
            </button>
        </div>
    </div>
</div>
<!-- Session Monitor Script -->
<script src="<?php echo BASE_URL; ?>/assets/js/session-monitor.js"></script>
<?php endif; ?>

</body>
</html>