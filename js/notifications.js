// Notifications Manager - handles real-time notifications
class NotificationManager {
    constructor() {
        this.notifications = [];
        this.unreadCount = 0;
        this.init();
    }

    async init() {
        await this.loadNotifications();
        this.setupPolling();
        this.updateNotificationUI();
    }

    async loadNotifications() {
        try {
            const response = await fetch('get-notifications.php');
            const data = await response.json();
            
            if (data.success && data.notifications) {
                this.notifications = data.notifications;
                this.unreadCount = this.notifications.filter(n => !n.is_read).length;
            }
        } catch (error) {
            console.error('Failed to load notifications:', error);
        }
    }

    updateNotificationUI() {
        // Update notification badge
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            badge.textContent = this.unreadCount;
            badge.style.display = this.unreadCount > 0 ? 'inline-block' : 'none';
        }

        // Update notification dropdown if it exists
        this.updateNotificationDropdown();
    }

    updateNotificationDropdown() {
        const dropdown = document.querySelector('.notifications-dropdown');
        if (!dropdown) return;

        dropdown.innerHTML = '';

        if (this.notifications.length === 0) {
            dropdown.innerHTML = '<div class="no-notifications">No notifications</div>';
            return;
        }

        this.notifications.slice(0, 5).forEach(notification => {
            const notificationEl = document.createElement('div');
            notificationEl.className = `notification-item ${notification.is_read ? 'read' : 'unread'}`;
            notificationEl.innerHTML = `
                <div class="notification-icon ${notification.type}">
                    <i class="fas fa-${this.getNotificationIcon(notification.type)}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${notification.title}</div>
                    <div class="notification-message">${notification.message}</div>
                    <div class="notification-time">${this.formatTime(notification.time)}</div>
                </div>
            `;
            
            notificationEl.addEventListener('click', () => {
                this.markAsRead(notification.id);
            });
            
            dropdown.appendChild(notificationEl);
        });

        // Add "View all" link if there are more notifications
        if (this.notifications.length > 5) {
            const viewAllEl = document.createElement('div');
            viewAllEl.className = 'notification-view-all';
            viewAllEl.innerHTML = '<a href="notifications.html">View all notifications</a>';
            dropdown.appendChild(viewAllEl);
        }
    }

    getNotificationIcon(type) {
        const icons = {
            'info': 'info-circle',
            'success': 'check-circle',
            'warning': 'exclamation-triangle',
            'error': 'times-circle'
        };
        return icons[type] || 'bell';
    }

    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;
        
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);
        
        if (minutes < 1) return 'Just now';
        if (minutes < 60) return `${minutes}m ago`;
        if (hours < 24) return `${hours}h ago`;
        if (days < 7) return `${days}d ago`;
        
        return date.toLocaleDateString();
    }

    async markAsRead(notificationId) {
        try {
            const response = await fetch('mark-notification-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ notification_id: notificationId })
            });
            
            const data = await response.json();
            if (data.success) {
                // Update local notification state
                const notification = this.notifications.find(n => n.id === notificationId);
                if (notification && !notification.is_read) {
                    notification.is_read = true;
                    this.unreadCount--;
                    this.updateNotificationUI();
                }
            }
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    }

    async addNotification(notification) {
        this.notifications.unshift(notification);
        if (!notification.is_read) {
            this.unreadCount++;
        }
        this.updateNotificationUI();
    }

    setupPolling() {
        // Poll for new notifications every 30 seconds
        setInterval(async () => {
            await this.loadNotifications();
            this.updateNotificationUI();
        }, 30000);
    }

    showToast(title, message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `notification-toast ${type}`;
        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas fa-${this.getNotificationIcon(type)}"></i>
            </div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close">&times;</button>
        `;

        // Add styles
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            z-index: 10000;
            max-width: 400px;
            animation: slideInRight 0.3s ease-out;
        `;

        document.body.appendChild(toast);

        // Auto remove after 5 seconds
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease-in forwards';
            setTimeout(() => toast.remove(), 300);
        }, 5000);

        // Manual close
        toast.querySelector('.toast-close').addEventListener('click', () => {
            toast.style.animation = 'slideOutRight 0.3s ease-in forwards';
            setTimeout(() => toast.remove(), 300);
        });
    }
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    .notification-toast {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }
    .toast-icon {
        flex-shrink: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .toast-content {
        flex: 1;
    }
    .toast-title {
        font-weight: 600;
        margin-bottom: 4px;
    }
    .toast-message {
        font-size: 0.9em;
        opacity: 0.8;
    }
    .toast-close {
        background: none;
        border: none;
        font-size: 1.2em;
        cursor: pointer;
        opacity: 0.6;
    }
    .toast-close:hover {
        opacity: 1;
    }
`;
document.head.appendChild(style);

// Create global notification manager instance
window.notificationManager = new NotificationManager();