/**
 * Admin Notification Checker
 * Periodically checks for new admin panel activities
 * Shows notifications and sends emails to codisticsolutions@gmail.com
 */

class AdminNotificationChecker {
    constructor() {
        this.checkInterval = 5 * 60 * 1000; // 5 minutes
        this.isChecking = false;
        this.lastCheck = null;
        this.notificationBadges = {};
        
        this.init();
    }
    
    init() {
        // Start periodic checking
        this.startPeriodicCheck();
        
        // Check immediately on page load
        this.checkNotifications();
        
        // Add notification badges to navigation
        this.setupNotificationBadges();
        
        // Add status indicator
        this.addStatusIndicator();
        
        console.log('Admin Notification Checker initialized');
    }
    
    startPeriodicCheck() {
        setInterval(() => {
            if (!this.isChecking) {
                this.checkNotifications();
            }
        }, this.checkInterval);
    }
    
    async checkNotifications() {
        if (this.isChecking) return;
        
        this.isChecking = true;
        this.updateStatusIndicator('checking');
        
        try {
            const response = await fetch('check_notifications.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.processNotifications(data);
                this.updateStatusIndicator('success');
                this.lastCheck = new Date();
            } else {
                console.error('Notification check failed:', data.error);
                this.updateStatusIndicator('error');
            }
            
        } catch (error) {
            console.error('Error checking notifications:', error);
            this.updateStatusIndicator('error');
        } finally {
            this.isChecking = false;
        }
    }
    
    processNotifications(data) {
        const notifications = data.notifications || {};
        const totals = data.totals || {};
        
        // Update navigation badges
        this.updateBadge('registration-approvals', totals.pending || 0);
        this.updateBadge('registrations', totals.waitlist_pending || 0);
        
        // Show desktop notifications for new items
        if (notifications.new_registrations > 0) {
            this.showDesktopNotification(
                'New Registrations',
                `${notifications.new_registrations} new registration(s) require approval`,
                'registration-approvals.php'
            );
        }
        
        if (notifications.recent_errors > 0) {
            this.showDesktopNotification(
                'System Errors',
                `${notifications.recent_errors} recent error(s) detected`,
                'dashboard.php'
            );
        }
        
        // Update dashboard stats if on dashboard page
        if (window.location.pathname.includes('dashboard.php')) {
            this.updateDashboardStats(totals);
        }
        
        // Log notification check
        console.log('Notifications checked:', {
            notifications: notifications,
            totals: totals,
            email_sent: data.email_sent,
            time: data.check_time
        });
    }
    
    setupNotificationBadges() {
        // Add badges to navigation items
        const navItems = [
            { selector: 'a[href*="registration-approvals.php"]', key: 'registration-approvals' },
            { selector: 'a[href*="registrations.php"]', key: 'registrations' }
        ];
        
        navItems.forEach(item => {
            const element = document.querySelector(item.selector);
            if (element) {
                const badge = document.createElement('span');
                badge.className = 'notification-badge';
                badge.style.cssText = `
                    display: none;
                    position: absolute;
                    top: -5px;
                    right: -5px;
                    background: #dc3545;
                    color: white;
                    border-radius: 50%;
                    width: 20px;
                    height: 20px;
                    font-size: 12px;
                    text-align: center;
                    line-height: 20px;
                    font-weight: bold;
                `;
                
                // Make parent relative for absolute positioning
                element.style.position = 'relative';
                element.appendChild(badge);
                
                this.notificationBadges[item.key] = badge;
            }
        });
    }
    
    updateBadge(key, count) {
        const badge = this.notificationBadges[key];
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count.toString();
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }
    }
    
    addStatusIndicator() {
        // Add status indicator to the page
        const indicator = document.createElement('div');
        indicator.id = 'notification-status';
        indicator.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #28a745;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            cursor: pointer;
        `;
        indicator.title = 'Notification system status';
        
        indicator.addEventListener('click', () => {
            this.showStatusInfo();
        });
        
        document.body.appendChild(indicator);
        this.statusIndicator = indicator;
    }
    
    updateStatusIndicator(status) {
        if (!this.statusIndicator) return;
        
        const colors = {
            'checking': '#ffc107',
            'success': '#28a745',
            'error': '#dc3545'
        };
        
        this.statusIndicator.style.background = colors[status] || '#6c757d';
        
        const titles = {
            'checking': 'Checking for notifications...',
            'success': `Last check: ${this.lastCheck ? this.lastCheck.toLocaleTimeString() : 'Never'}`,
            'error': 'Error checking notifications'
        };
        
        this.statusIndicator.title = titles[status] || 'Unknown status';
    }
    
    showStatusInfo() {
        const info = `
            Notification System Status
            
            Last Check: ${this.lastCheck ? this.lastCheck.toLocaleString() : 'Never'}
            Check Interval: ${this.checkInterval / 1000 / 60} minutes
            Currently Checking: ${this.isChecking ? 'Yes' : 'No'}
            
            Email notifications are sent to: codisticsolutions@gmail.com
        `;
        
        alert(info);
    }
    
    showDesktopNotification(title, message, url = null) {
        // Check if browser supports notifications
        if (!('Notification' in window)) {
            console.log('Browser does not support notifications');
            return;
        }
        
        // Request permission if needed
        if (Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    this.createNotification(title, message, url);
                }
            });
        } else if (Notification.permission === 'granted') {
            this.createNotification(title, message, url);
        }
    }
    
    createNotification(title, message, url = null) {
        const notification = new Notification(title, {
            body: message,
            icon: '../assets/images/logo.png', // Add your logo path
            badge: '../assets/images/badge.png', // Add your badge path
            tag: 'admin-notification',
            requireInteraction: true
        });
        
        notification.onclick = function() {
            window.focus();
            if (url) {
                window.location.href = url;
            }
            notification.close();
        };
        
        // Auto close after 10 seconds
        setTimeout(() => {
            notification.close();
        }, 10000);
    }
    
    updateDashboardStats(totals) {
        // Update dashboard statistics if elements exist
        const statElements = {
            'pending-registrations': totals.pending,
            'waitlist-registrations': totals.waitlist_pending,
            'approved-registrations': totals.approved
        };
        
        Object.entries(statElements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value || 0;
            }
        });
    }
    
    // Manual check method for testing
    manualCheck() {
        console.log('Manual notification check triggered');
        this.checkNotifications();
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize on admin pages
    if (window.location.pathname.includes('/admin/')) {
        window.adminNotificationChecker = new AdminNotificationChecker();
        
        // Add manual check button for testing (only in development)
        if (window.location.hostname === 'localhost') {
            const testButton = document.createElement('button');
            testButton.textContent = 'Check Notifications';
            testButton.style.cssText = `
                position: fixed;
                bottom: 50px;
                right: 20px;
                padding: 8px 12px;
                background: #007bff;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                z-index: 1000;
                font-size: 12px;
            `;
            testButton.onclick = () => window.adminNotificationChecker.manualCheck();
            document.body.appendChild(testButton);
        }
    }
});

// Export for manual testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AdminNotificationChecker;
}
