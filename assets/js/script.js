/**
 * SportsMeet Manager - Custom JavaScript
 *
 * This file contains custom JavaScript functionality for the SportsMeet Manager application.
 *
 * @author SportsMeet Team
 * @version 1.0
 * @since 2025-08-04
 *
 * Current User: joealjohn
 * Current Time: 2025-08-04 16:14:32 UTC
 */

// Global Application Object
const SportsMeetApp = {
    // Configuration
    config: {
        currentUser: 'joealjohn',
        currentTime: new Date('2025-08-04T16:14:32Z'),
        apiBase: window.location.origin,
        debug: true,
        autoRefreshInterval: 60000, // 1 minute
        notificationCheckInterval: 30000, // 30 seconds
        timeUpdateInterval: 1000 // 1 second
    },

    // Application state
    state: {
        isOnline: navigator.onLine,
        lastActivity: Date.now(),
        notifications: [],
        activeModals: [],
        timeZone: 'UTC'
    },

    // Initialize the application
    init: function() {
        console.log('%c🏆 SportsMeet Manager Initialized', 'font-size: 16px; color: #667eea; font-weight: bold;');
        console.log(`%cCurrent User: ${this.config.currentUser}`, 'color: #6c757d;');
        console.log(`%cCurrent Time: ${this.config.currentTime.toISOString()}`, 'color: #6c757d;');

        this.setupEventListeners();
        this.initializeComponents();
        this.startPeriodicUpdates();
        this.checkUserActivity();

    },

    // Setup global event listeners
    setupEventListeners: function() {
        // Online/Offline status
        window.addEventListener('online', () => {
            this.state.isOnline = true;
            this.showNotification('Connection restored', 'success');
        });

        window.addEventListener('offline', () => {
            this.state.isOnline = false;
            this.showNotification('Connection lost', 'warning');
        });

        // User activity tracking
        ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, () => {
                this.state.lastActivity = Date.now();
            }, { passive: true });
        });

        // Form submission handlers
        document.addEventListener('submit', this.handleFormSubmit.bind(this));

        // Button click handlers
        document.addEventListener('click', this.handleButtonClick.bind(this));

        // Page visibility change
        document.addEventListener('visibilitychange', this.handleVisibilityChange.bind(this));

        // Before page unload
        window.addEventListener('beforeunload', this.handleBeforeUnload.bind(this));
    },

    // Initialize components
    initializeComponents: function() {
        this.initializeDateTimePickers();
        this.initializeTooltips();
        this.initializeModals();
        this.initializeCharts();
        this.initializeNotifications();
        this.setupBackToTop();
        this.setupSearchFunctionality();
        this.setupFilterTabs();
        this.setupAutoSave();
    },

    // Time and date management
    timeManager: {
        // Get current application time
        getCurrentTime: function() {
            const now = new Date();
            const timeDiff = now.getTime() - SportsMeetApp.config.currentTime.getTime();
            return new Date(new Date('2025-08-04T16:14:32Z').getTime() + timeDiff);
        },

        // Format time for display
        formatTime: function(date, format = 'full') {
            const options = {
                full: {
                    year: 'numeric',
                    month: 'short',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    timeZone: 'UTC'
                },
                short: {
                    month: 'short',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                },
                time: {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                }
            };

            return date.toLocaleDateString('en-US', options[format] || options.full);
        },

        // Update all time displays
        updateTimeDisplays: function() {
            const currentTime = this.getCurrentTime();

            // Update navbar time
            const navbarTime = document.getElementById('currentTime');
            if (navbarTime) {
                navbarTime.innerHTML = `<i class="fas fa-clock me-1"></i>${this.formatTime(currentTime)}`;
            }

            // Update footer time
            const footerTime = document.getElementById('serverTime');
            if (footerTime) {
                footerTime.textContent = currentTime.toISOString().slice(0, 19).replace('T', ' ') + ' UTC';
            }

            // Update any countdown timers
            document.querySelectorAll('[data-countdown]').forEach(element => {
                this.updateCountdown(element);
            });

            // Update relative times
            document.querySelectorAll('[data-relative-time]').forEach(element => {
                this.updateRelativeTime(element);
            });
        },

        // Update countdown timer
        updateCountdown: function(element) {
            const targetTime = new Date(element.dataset.countdown);
            const currentTime = this.getCurrentTime();
            const diff = targetTime - currentTime;

            if (diff <= 0) {
                element.textContent = 'Event has started';
                element.classList.add('text-danger');
                return;
            }

            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);

            if (days > 0) {
                element.textContent = `${days}d ${hours}h ${minutes}m`;
            } else if (hours > 0) {
                element.textContent = `${hours}h ${minutes}m ${seconds}s`;
            } else {
                element.textContent = `${minutes}m ${seconds}s`;
                element.classList.add('text-warning');
            }
        },

        // Update relative time display
        updateRelativeTime: function(element) {
            const targetTime = new Date(element.dataset.relativeTime);
            const currentTime = this.getCurrentTime();
            const diff = Math.abs(currentTime - targetTime);
            const isPast = currentTime > targetTime;

            const minutes = Math.floor(diff / (1000 * 60));
            const hours = Math.floor(diff / (1000 * 60 * 60));
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));

            let text = '';
            if (days > 0) {
                text = `${days} day${days > 1 ? 's' : ''}`;
            } else if (hours > 0) {
                text = `${hours} hour${hours > 1 ? 's' : ''}`;
            } else if (minutes > 0) {
                text = `${minutes} minute${minutes > 1 ? 's' : ''}`;
            } else {
                text = 'Just now';
            }

            element.textContent = isPast ? `${text} ago` : `in ${text}`;
        }
    },

    // Notification system
    notificationManager: {
        // Show toast notification
        show: function(message, type = 'info', duration = 5000) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = `
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 300px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                border: none;
                border-radius: 10px;
            `;

            const icons = {
                success: 'check-circle',
                error: 'exclamation-circle',
                warning: 'exclamation-triangle',
                info: 'info-circle'
            };

            notification.innerHTML = `
                <i class="fas fa-${icons[type]} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.body.appendChild(notification);

            // Auto remove after duration
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, duration);

            // Add to state
            SportsMeetApp.state.notifications.push({
                id: Date.now(),
                message,
                type,
                timestamp: new Date()
            });
        },

        // Check for new notifications (would normally be an API call)
        checkForNew: function() {
            // Simulate notification check for user joealjohn
            if (Math.random() < 0.1) { // 10% chance
                const messages = [
                    'New event available in your preferred sport!',
                    'Reminder: Your event starts in 2 hours',
                    'Event capacity is filling up fast',
                    'Your event registration was confirmed'
                ];

                const randomMessage = messages[Math.floor(Math.random() * messages.length)];
                this.show(randomMessage, 'info');
            }
        }
    },

    // Event handlers
    handleFormSubmit: function(event) {
        const form = event.target;

        // Add loading state to submit buttons
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn && !submitBtn.disabled) {
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';

            // Restore button state after form submission
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }, 3000);
        }

        // Log form submission
        console.log(`Form submitted: ${form.id || form.className}`);

        // Track activity
        this.logActivity('form_submit', { form: form.id || 'unnamed' });
    },

    handleButtonClick: function(event) {
        const button = event.target.closest('button, .btn');
        if (!button) return;

        // Add ripple effect
        this.addRippleEffect(button, event);

        // Handle special button types
        if (button.classList.contains('btn-join-event')) {
            this.handleEventJoin(button);
        } else if (button.classList.contains('btn-leave-event')) {
            this.handleEventLeave(button);
        }
    },

    handleVisibilityChange: function() {
        if (document.hidden) {
            console.log('Page hidden - pausing updates');
        } else {
            console.log('Page visible - resuming updates');
            this.timeManager.updateTimeDisplays();
        }
    },

    handleBeforeUnload: function(event) {
        // Save any unsaved data
        this.saveUserPreferences();

        // Log session end
        this.logActivity('session_end');
    },

    // Event-specific handlers
    handleEventJoin: function(button) {
        const eventId = button.dataset.eventId;
        const eventTitle = button.dataset.eventTitle;

        if (confirm(`Are you sure you want to join "${eventTitle}"?`)) {
            // Would normally make an API call here
            this.notificationManager.show(`Successfully joined "${eventTitle}"!`, 'success');
            button.classList.replace('btn-primary', 'btn-success');
            button.innerHTML = '<i class="fas fa-check me-1"></i>Joined';
            button.disabled = true;

            this.logActivity('event_join', { eventId, eventTitle });
        }
    },

    handleEventLeave: function(button) {
        const eventId = button.dataset.eventId;
        const eventTitle = button.dataset.eventTitle;

        if (confirm(`Are you sure you want to leave "${eventTitle}"? This action cannot be undone.`)) {
            // Would normally make an API call here
            this.notificationManager.show(`Left "${eventTitle}" successfully.`, 'info');
            button.closest('.event-card').style.opacity = '0.5';

            this.logActivity('event_leave', { eventId, eventTitle });
        }
    },

    // UI enhancements
    addRippleEffect: function(element, event) {
        const ripple = document.createElement('span');
        const rect = element.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = event.clientX - rect.left - size / 2;
        const y = event.clientY - rect.top - size / 2;

        ripple.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            left: ${x}px;
            top: ${y}px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
        `;

        // Add ripple animation keyframes if not exists
        if (!document.getElementById('ripple-styles')) {
            const style = document.createElement('style');
            style.id = 'ripple-styles';
            style.textContent = `
                @keyframes ripple {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        }

        element.style.position = 'relative';
        element.style.overflow = 'hidden';
        element.appendChild(ripple);

        setTimeout(() => ripple.remove(), 600);
    },

    // Component initializers
    initializeDateTimePickers: function() {
        document.querySelectorAll('input[type="date"]').forEach(input => {
            // Set minimum date to today
            const today = this.timeManager.getCurrentTime().toISOString().split('T')[0];
            if (!input.hasAttribute('min')) {
                input.min = today;
            }
        });
    },

    initializeTooltips: function() {
        // Initialize Bootstrap tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    },

    initializeModals: function() {
        // Auto-focus first input in modals
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('shown.bs.modal', function() {
                const firstInput = modal.querySelector('input, textarea, select');
                if (firstInput) firstInput.focus();
            });
        });
    },

    initializeCharts: function() {
        // Initialize any charts (placeholder for future Chart.js integration)
        const chartElements = document.querySelectorAll('[data-chart]');
        chartElements.forEach(element => {
            console.log('Chart element found:', element.dataset.chart);
        });
    },

    initializeNotifications: function() {
        // Mark notifications as read when dropdown is opened
        const notificationDropdown = document.getElementById('notificationDropdown');
        if (notificationDropdown) {
            notificationDropdown.addEventListener('click', () => {
                // Would normally make an API call to mark notifications as read
                console.log('Marking notifications as read for user:', this.config.currentUser);
            });
        }
    },

    setupBackToTop: function() {
        const backToTopBtn = document.getElementById('backToTop');
        if (!backToTopBtn) return;

        let scrolling = false;

        window.addEventListener('scroll', () => {
            if (!scrolling) {
                requestAnimationFrame(() => {
                    if (window.pageYOffset > 300) {
                        backToTopBtn.style.display = 'flex';
                    } else {
                        backToTopBtn.style.display = 'none';
                    }
                    scrolling = false;
                });
                scrolling = true;
            }
        });

        backToTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    },

    setupSearchFunctionality: function() {
        const searchInputs = document.querySelectorAll('input[type="search"], input[name="search"]');

        searchInputs.forEach(input => {
            let searchTimeout;

            input.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.performSearch(e.target.value);
                }, 300); // Debounce search
            });
        });
    },

    setupFilterTabs: function() {
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                // Add loading animation
                const originalContent = tab.innerHTML;
                tab.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Loading...';

                // Restore content after navigation (simulated)
                setTimeout(() => {
                    tab.innerHTML = originalContent;
                }, 500);
            });
        });
    },

    setupAutoSave: function() {
        // Auto-save form data to localStorage
        const forms = document.querySelectorAll('form[data-autosave]');

        forms.forEach(form => {
            const formId = form.id || `form_${Date.now()}`;

            // Load saved data
            const savedData = localStorage.getItem(`autosave_${formId}`);
            if (savedData) {
                try {
                    const data = JSON.parse(savedData);
                    Object.keys(data).forEach(name => {
                        const field = form.querySelector(`[name="${name}"]`);
                        if (field && field.type !== 'password') {
                            field.value = data[name];
                        }
                    });
                } catch (e) {
                    console.error('Error loading autosave data:', e);
                }
            }

            // Save data on input
            form.addEventListener('input', () => {
                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());
                localStorage.setItem(`autosave_${formId}`, JSON.stringify(data));
            });

            // Clear saved data on successful submission
            form.addEventListener('submit', () => {
                localStorage.removeItem(`autosave_${formId}`);
            });
        });
    },

    // Utility functions
    performSearch: function(query) {
        console.log(`Searching for: ${query}`);
        // Would normally perform AJAX search here
    },

    checkUserActivity: function() {
        setInterval(() => {
            const timeSinceActivity = Date.now() - this.state.lastActivity;

            // Show idle warning after 15 minutes
            if (timeSinceActivity > 15 * 60 * 1000 && !this.state.idleWarningShown) {
                this.notificationManager.show('You have been idle for 15 minutes', 'warning');
                this.state.idleWarningShown = true;
            }

            // Auto-logout after 30 minutes (if implemented)
            if (timeSinceActivity > 30 * 60 * 1000) {
                console.log('User idle for 30 minutes - would auto-logout here');
            }
        }, 60000); // Check every minute
    },

    startPeriodicUpdates: function() {
        // Update time displays
        setInterval(() => {
            if (!document.hidden) {
                this.timeManager.updateTimeDisplays();
            }
        }, this.config.timeUpdateInterval);

        // Check for notifications
        setInterval(() => {
            if (!document.hidden && this.state.isOnline) {
                this.notificationManager.checkForNew();
            }
        }, this.config.notificationCheckInterval);

        // Refresh data
        setInterval(() => {
            if (!document.hidden && this.state.isOnline) {
                this.refreshPageData();
            }
        }, this.config.autoRefreshInterval);
    },

    refreshPageData: function() {
        // Refresh dynamic content (would normally make API calls)
        console.log('Refreshing page data...');

        // Update participant counts
        document.querySelectorAll('[data-refresh="participant-count"]').forEach(element => {
            // Simulate count update
            const currentCount = parseInt(element.textContent) || 0;
            const change = Math.floor(Math.random() * 3) - 1; // -1, 0, or 1
            const newCount = Math.max(0, currentCount + change);

            if (newCount !== currentCount) {
                element.textContent = newCount;
                element.style.animation = 'pulse 0.5s';
            }
        });
    },

    logActivity: function(action, data = {}) {
        const activity = {
            user: this.config.currentUser,
            action: action,
            timestamp: this.timeManager.getCurrentTime().toISOString(),
            data: data,
            page: window.location.pathname
        };

        console.log('Activity logged:', activity);

        // Would normally send to server
        // fetch('/api/log-activity', { method: 'POST', body: JSON.stringify(activity) });
    },

    saveUserPreferences: function() {
        const preferences = {
            user: this.config.currentUser,
            theme: document.body.classList.contains('dark-theme') ? 'dark' : 'light',
            lastVisited: this.timeManager.getCurrentTime().toISOString()
        };

        localStorage.setItem('userPreferences', JSON.stringify(preferences));
    },

    // Public API methods
    showNotification: function(message, type = 'info', duration = 5000) {
        this.notificationManager.show(message, type, duration);
    },

    getCurrentUser: function() {
        return this.config.currentUser;
    },

    getCurrentTime: function() {
        return this.timeManager.getCurrentTime();
    }
};

// Page-specific functionality
const PageHandlers = {
    // Dashboard page
    dashboard: function() {
        console.log('Dashboard page loaded for user:', SportsMeetApp.config.currentUser);

        // Animate statistics cards
        document.querySelectorAll('.stats-card').forEach((card, index) => {
            setTimeout(() => {
                card.style.animation = 'fadeInUp 0.6s ease forwards';
            }, index * 100);
        });

        // Update dashboard data every 2 minutes
        setInterval(() => {
            SportsMeetApp.refreshPageData();
        }, 120000);
    },

    // Events page
    events: function() {
        console.log('Events page loaded');

        // Auto-submit filter form when selects change
        document.querySelectorAll('select[name="sport"], select[name="venue"], select[name="status"]').forEach(select => {
            select.addEventListener('change', () => {
                select.closest('form').submit();
            });
        });

        // Smooth scroll to event if hash present
        if (window.location.hash) {
            setTimeout(() => {
                const element = document.querySelector(window.location.hash);
                if (element) {
                    element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    element.style.animation = 'pulse 2s';
                }
            }, 100);
        }
    },

    // My Events page
    myEvents: function() {
        console.log('My Events page loaded');

        // Highlight today's events
        document.querySelectorAll('.event-timeline-card.today').forEach(card => {
            setInterval(() => {
                card.style.boxShadow = '0 15px 35px rgba(240, 147, 251, 0.15)';
                setTimeout(() => {
                    card.style.boxShadow = '0 5px 20px rgba(0,0,0,0.08)';
                }, 1000);
            }, 3000);
        });
    },

    // Authentication pages
    auth: function() {
        console.log('Authentication page loaded');

        // Real-time password validation
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');

        if (passwordInput && confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', function() {
                if (this.value && passwordInput.value !== this.value) {
                    this.style.borderColor = '#dc3545';
                } else {
                    this.style.borderColor = '#e9ecef';
                }
            });
        }
    },

    // Admin pages
    admin: function() {
        console.log('Admin page loaded for user:', SportsMeetApp.config.currentUser);

        // Enhanced admin functionality would go here
        if (SportsMeetApp.config.currentUser === 'admin') {
            console.log('Admin privileges active');
        }
    }
};

// Initialize application when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize main application
    SportsMeetApp.init();

    // Initialize page-specific handlers
    const currentPage = window.location.pathname.split('/').pop().split('.')[0];
    const pageType = window.location.pathname.includes('/admin/') ? 'admin' :
        window.location.pathname.includes('/auth/') ? 'auth' :
            currentPage;

    if (PageHandlers[pageType]) {
        PageHandlers[pageType]();
    } else if (PageHandlers[currentPage]) {
        PageHandlers[currentPage]();
    }

    // Global CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .loading-skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    `;
    document.head.appendChild(style);
});

// Export for global access
window.SportsMeetApp = SportsMeetApp;

// Service Worker registration (for future PWA features)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js')
            .then(function(registration) {
                console.log('SW registered: ', registration);
            })
            .catch(function(registrationError) {
                console.log('SW registration failed: ', registrationError);
            });
    });
}

// Error handling
window.addEventListener('error', function(event) {
    console.error('JavaScript error:', event.error);
    SportsMeetApp.logActivity('javascript_error', {
        message: event.error.message,
        filename: event.filename,
        lineno: event.lineno
    });
});

// Unhandled promise rejection handling
window.addEventListener('unhandledrejection', function(event) {
    console.error('Unhandled promise rejection:', event.reason);
    SportsMeetApp.logActivity('promise_rejection', {
        reason: event.reason
    });
});