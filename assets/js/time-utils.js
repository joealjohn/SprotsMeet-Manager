/**
 * Enhanced Time Utilities for SportsMeet Manager
 * Complete IST timezone conversion and 12-hour format display
 *
 * Current Date: 2025-08-04 15:27:42 IST
 * Current User: joealjohn
 */

class TimeManager {
    constructor() {
        this.timezone = 'Asia/Kolkata';
        this.istOffset = 5.5 * 60 * 60 * 1000; // 5.5 hours in milliseconds
        this.currentUTC = '2025-08-04 09:57:42';
        this.currentUser = 'joealjohn';
    }

    // Get current IST time
    getCurrentIST() {
        const now = new Date();
        return new Date(now.toLocaleString("en-US", {timeZone: this.timezone}));
    }

    // Get current time in multiple formats
    getCurrentTime(format = 'full') {
        const istTime = this.getCurrentIST();

        const options = {
            timeZone: this.timezone,
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        };

        switch (format) {
            case 'time-only':
                return istTime.toLocaleTimeString('en-IN', {
                    timeZone: this.timezone,
                    hour: 'numeric',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                });
            case 'date-only':
                return istTime.toLocaleDateString('en-IN', {
                    timeZone: this.timezone,
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            case 'navbar':
                return istTime.toLocaleString('en-IN', options);
            case 'short':
                return istTime.toLocaleString('en-IN', {
                    timeZone: this.timezone,
                    month: 'short',
                    day: '2-digit',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });
            default:
                return istTime.toLocaleString('en-IN', options);
        }
    }

    // Convert UTC string to IST display
    convertUTCtoIST(utcString, format = 'full') {
        if (!utcString || utcString === '') return '';

        try {
            // Handle different UTC string formats
            let utcDate;
            if (utcString.includes('T')) {
                utcDate = new Date(utcString);
            } else if (utcString.includes(' ')) {
                utcDate = new Date(utcString + ' UTC');
            } else {
                utcDate = new Date(utcString);
            }

            // Convert to IST
            const istDate = new Date(utcDate.toLocaleString("en-US", {timeZone: this.timezone}));

            const options = {
                timeZone: this.timezone,
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };

            switch (format) {
                case 'time-only':
                    return istDate.toLocaleTimeString('en-IN', {
                        timeZone: this.timezone,
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    });
                case 'date-only':
                    return istDate.toLocaleDateString('en-IN', {
                        timeZone: this.timezone,
                        year: 'numeric',
                        month: 'short',
                        day: '2-digit'
                    });
                case 'short':
                    return istDate.toLocaleString('en-IN', {
                        timeZone: this.timezone,
                        month: 'short',
                        day: '2-digit',
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    });
                case 'full-date':
                    return istDate.toLocaleDateString('en-IN', {
                        timeZone: this.timezone,
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                default:
                    return istDate.toLocaleString('en-IN', options);
            }
        } catch (error) {
            console.error('Error converting UTC to IST:', error);
            return utcString;
        }
    }

    // Get time ago in IST context
    getTimeAgo(utcString) {
        if (!utcString || utcString === '') return '';

        try {
            const utcDate = new Date(utcString.includes(' ') ? utcString + ' UTC' : utcString);
            const istDate = new Date(utcDate.toLocaleString("en-US", {timeZone: this.timezone}));
            const now = this.getCurrentIST();
            const diffMs = now.getTime() - istDate.getTime();
            const diffMinutes = Math.floor(diffMs / (1000 * 60));
            const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
            const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

            if (diffMinutes < 1) return 'Just now';
            if (diffMinutes < 60) return `${diffMinutes}m ago`;
            if (diffHours < 24) return `${diffHours}h ago`;
            if (diffDays < 30) return `${diffDays}d ago`;

            return this.convertUTCtoIST(utcString, 'date-only');
        } catch (error) {
            console.error('Error calculating time ago:', error);
            return utcString;
        }
    }

    // Update all time elements on the page
    updateAllTimeElements() {
        // Update navbar time
        const navbarTime = document.getElementById('currentTime');
        if (navbarTime) {
            navbarTime.innerHTML = `<i class="fas fa-clock me-1"></i>${this.getCurrentTime('navbar')} IST`;
        }

        // Update page header times
        const headerTime = document.getElementById('currentDisplayTime');
        if (headerTime) {
            headerTime.textContent = `${this.getCurrentTime('navbar')} IST`;
        }

        // Update current date displays
        document.querySelectorAll('.current-date').forEach(element => {
            element.textContent = this.getCurrentTime('full-date');
        });

        // Update current time displays
        document.querySelectorAll('.current-time-display').forEach(element => {
            element.textContent = `${this.getCurrentTime('navbar')} IST`;
        });

        // Update any other time elements with data-utc attribute
        document.querySelectorAll('[data-utc]').forEach(element => {
            const utcTime = element.getAttribute('data-utc');
            const format = element.getAttribute('data-format') || 'full';

            if (utcTime && utcTime !== '') {
                element.textContent = this.convertUTCtoIST(utcTime, format);
            }
        });

        // Update time-ago elements
        document.querySelectorAll('[data-time-ago]').forEach(element => {
            const utcTime = element.getAttribute('data-time-ago');
            if (utcTime && utcTime !== '') {
                element.textContent = this.getTimeAgo(utcTime);
            }
        });

        // Update any UTC time displays to IST
        document.querySelectorAll('.utc-time').forEach(element => {
            const utcText = element.textContent;
            if (utcText.includes('UTC')) {
                // Extract UTC time and convert
                const utcMatch = utcText.match(/(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/);
                if (utcMatch) {
                    const istTime = this.convertUTCtoIST(utcMatch[1]);
                    element.textContent = utcText.replace(utcMatch[0] + ' UTC', istTime + ' IST');
                    element.classList.remove('utc-time');
                    element.classList.add('ist-time');
                }
            }
        });

        // Update status bar times
        document.querySelectorAll('.status-time').forEach(element => {
            const currentText = element.textContent;
            if (currentText.includes('UTC')) {
                element.textContent = `Current Date: ${this.getCurrentTime('full-date')} at ${this.getCurrentTime('time-only')} IST`;
            }
        });
    }

    // Start auto-update timer
    startAutoUpdate() {
        // Update immediately
        this.updateAllTimeElements();

        // Update every second
        setInterval(() => {
            this.updateAllTimeElements();
        }, 1000);
    }

    // Get IST offset string
    getISTOffset() {
        return '+05:30';
    }

    // Convert IST to UTC for form submissions
    convertISTtoUTC(istString) {
        if (!istString) return '';

        try {
            const istDate = new Date(istString);
            const utcDate = new Date(istDate.toLocaleString("en-US", {timeZone: "UTC"}));
            return utcDate.toISOString().slice(0, 19).replace('T', ' ');
        } catch (error) {
            console.error('Error converting IST to UTC:', error);
            return istString;
        }
    }

    // Get current IST for console logs
    getCurrentISTForLog() {
        return this.getCurrentTime('navbar');
    }
}

// Create global time manager instance
const timeManager = new TimeManager();

// Auto-start when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize time manager
    timeManager.startAutoUpdate();

    // Replace any existing UTC displays
    setTimeout(() => {
        // Find and replace common UTC patterns
        document.querySelectorAll('*').forEach(element => {
            if (element.children.length === 0) { // Text nodes only
                const text = element.textContent;
                if (text && text.includes('UTC') && text.match(/\d{4}-\d{2}-\d{2}/)) {
                    const utcMatch = text.match(/(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\s+UTC/);
                    if (utcMatch) {
                        const istTime = timeManager.convertUTCtoIST(utcMatch[1]);
                        element.textContent = text.replace(utcMatch[0], istTime + ' IST');
                    }
                }
            }
        });
    }, 100);

    // Add IST timezone info to any timezone displays
    document.querySelectorAll('.timezone-info').forEach(element => {
        element.textContent = 'IST (UTC+05:30)';
    });

    console.log('Enhanced Time Manager initialized - Complete IST timezone active');
    console.log('Current UTC:', timeManager.currentUTC);
    console.log('Current IST:', timeManager.getCurrentISTForLog());
    console.log('Current User:', timeManager.currentUser);
});

// Export for use in other scripts
window.timeManager = timeManager;

// Global function to update time displays
window.updateTimeDisplays = function() {
    timeManager.updateAllTimeElements();
};

// Global function to convert UTC to IST
window.convertToIST = function(utcString, format = 'full') {
    return timeManager.convertUTCtoIST(utcString, format);
};