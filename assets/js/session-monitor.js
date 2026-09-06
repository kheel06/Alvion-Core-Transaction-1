/**
 * Session Monitor
 * Handles idle detection and session timeout warning
 */
(function() {
    'use strict';

    // Get BASE_URL from meta tag or default to current origin
    const BASE_URL = (function() {
        const metaTag = document.querySelector('meta[name="base-url"]');
        if (metaTag) return metaTag.getAttribute('content');
        // Fallback: try to get from existing global or use origin
        if (typeof window.BASE_URL !== 'undefined') return window.BASE_URL;
        // Use current origin as fallback
        return window.location.origin;
    })();

    // Configuration
    const TIMEOUT_DURATION = 15 * 60 * 1000; // 15 minutes in milliseconds
    const WARNING_DURATION = 60 * 1000; // Warning shown 60 seconds before timeout
    const WARNING_TIME = TIMEOUT_DURATION - WARNING_DURATION; // When to show warning
    
    // State
    let idleTimer;
    let warningTimer;
    let countdownInterval;
    let isWarningShown = false;
    
    // DOM Elements
    const modalId = 'sessionTimeoutModal';
    let modalElement;
    let countdownElement;
    
    // Initialize
    function init() {
        // Only run if user is logged in (check for logout link or similar indicator)
        // We can check if the footer includes the logout modal or specific logged-in elements
        // But simpler: just run, if modal doesn't exist (added via footer), it won't do anything visible
        
        // Setup event listeners for user activity
        const activityEvents = ['mousemove', 'mousedown', 'keypress', 'touchmove', 'scroll'];
        activityEvents.forEach(event => {
            document.addEventListener(event, resetIdleTimer, { passive: true });
        });
        
        // Start the timer
        startIdleTimer();
    }
    
    function startIdleTimer() {
        // Clear existing timers
        clearTimeout(idleTimer);
        clearTimeout(warningTimer);
        clearInterval(countdownInterval);
        
        // Set warning timer
        warningTimer = setTimeout(showWarning, WARNING_TIME);
        
        // Set final timeout timer
        idleTimer = setTimeout(logoutUser, TIMEOUT_DURATION);
    }
    
    function resetIdleTimer() {
        if (!isWarningShown) {
            startIdleTimer();
        }
    }
    
    function showWarning() {
        isWarningShown = true;
        modalElement = document.getElementById(modalId);
        if (!modalElement) return; // Should be in footer
        
        // Show modal (remove hidden class)
        modalElement.classList.remove('hidden');
        modalElement.setAttribute('aria-hidden', 'false');
        
        // Setup countdown
        let secondsLeft = WARNING_DURATION / 1000;
        countdownElement = document.getElementById('sessionTimeoutCountdown');
        if (countdownElement) {
            countdownElement.textContent = secondsLeft;
            
            countdownInterval = setInterval(() => {
                secondsLeft--;
                countdownElement.textContent = secondsLeft;
                
                if (secondsLeft <= 0) {
                    clearInterval(countdownInterval);
                    logoutUser();
                }
            }, 1000);
        }
    }
    
    function hideWarning() {
        isWarningShown = false;
        if (modalElement) {
            modalElement.classList.add('hidden');
            modalElement.setAttribute('aria-hidden', 'true');
        }
        clearInterval(countdownInterval);
        startIdleTimer();
    }
    
    function logoutUser() {
        window.location.href = BASE_URL + '/auth/logout.php?timeout=1';
    }
    
    // Public methods for button clicks
    window.sessionMonitor = {
        stayLoggedIn: function() {
            // Ping server to keep session alive
            fetch(BASE_URL + '/api/keep_alive.php')
                .then(response => {
                    if (response.ok) {
                        hideWarning();
                    } else {
                        // If ping fails, might be already logged out or network issue
                        // Just hide warning and restart local timer for now
                        hideWarning();
                    }
                })
                .catch(error => {
                    console.error('Session keep-alive failed:', error);
                    hideWarning();
                });
        },
        logout: logoutUser
    };
    
    // Start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
