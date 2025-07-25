/**
 * Real Data Manager - Tripple Exchange
 * Manages real user data integration across the platform
 */

class RealDataManager {
    constructor() {
        this.cache = new Map();
        this.cacheTimeout = 5 * 60 * 1000; // 5 minutes
        this.endpoints = {
            user: '/api/user.php',
            balance: '/api/balance.php',
            notifications: '/get-notifications.php',
            assets: '/get-user-assets.php'
        };
    }

    /**
     * Generic API call with caching
     */
    async apiCall(endpoint, options = {}) {
        const cacheKey = `${endpoint}_${JSON.stringify(options)}`;
        const cached = this.cache.get(cacheKey);
        
        if (cached && Date.now() - cached.timestamp < this.cacheTimeout) {
            return cached.data;
        }

        try {
            const response = await fetch(endpoint, {
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                },
                ...options
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'API call failed');
            }

            // Cache successful responses
            this.cache.set(cacheKey, {
                data,
                timestamp: Date.now()
            });

            return data;
        } catch (error) {
            console.error(`API call failed for ${endpoint}:`, error);
            throw error;
        }
    }

    /**
     * Get user profile data
     */
    async getUserData() {
        return this.apiCall(this.endpoints.user);
    }

    /**
     * Get user balances
     */
    async getBalances() {
        return this.apiCall(this.endpoints.balance);
    }

    /**
     * Get user notifications
     */
    async getNotifications() {
        return this.apiCall(this.endpoints.notifications);
    }

    /**
     * Get user assets
     */
    async getAssets() {
        return this.apiCall(this.endpoints.assets);
    }

    /**
     * Update user profile
     */
    async updateProfile(data) {
        const response = await this.apiCall(this.endpoints.user, {
            method: 'POST',
            body: JSON.stringify(data)
        });
        
        // Clear user data cache
        this.clearCache('user');
        return response;
    }

    /**
     * Mark notification as read
     */
    async markNotificationRead(notificationId) {
        const response = await fetch('/mark-notification-read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notification_id: notificationId }),
            credentials: 'same-origin'
        });
        
        if (response.ok) {
            this.clearCache('notifications');
        }
        
        return response.json();
    }

    /**
     * Clear cache for specific endpoint
     */
    clearCache(type = null) {
        if (type) {
            const keysToDelete = [];
            for (const key of this.cache.keys()) {
                if (key.includes(type)) {
                    keysToDelete.push(key);
                }
            }
            keysToDelete.forEach(key => this.cache.delete(key));
        } else {
            this.cache.clear();
        }
    }

    /**
     * Initialize real data on page load
     */
    async initializePageData() {
        const pageName = this.getCurrentPageName();
        
        try {
            switch (pageName) {
                case 'dashboard':
                    await this.initializeDashboard();
                    break;
                case 'profile':
                    await this.initializeProfile();
                    break;
                case 'wallet':
                    await this.initializeWallet();
                    break;
                case 'notifications':
                    await this.initializeNotifications();
                    break;
                default:
                    // Initialize common data for all pages
                    await this.initializeCommonData();
            }
        } catch (error) {
            console.error('Failed to initialize page data:', error);
            this.showError('Failed to load user data. Please refresh the page.');
        }
    }

    /**
     * Initialize dashboard with real data
     */
    async initializeDashboard() {
        const [userData, balanceData, notificationsData] = await Promise.all([
            this.getUserData(),
            this.getBalances(),
            this.getNotifications()
        ]);

        this.updateDashboardUI(userData.data, balanceData.data, notificationsData);
    }

    /**
     * Initialize profile page with real data
     */
    async initializeProfile() {
        const userData = await this.getUserData();
        this.updateProfileUI(userData.data);
    }

    /**
     * Initialize wallet page with real data
     */
    async initializeWallet() {
        const [balanceData, assetsData] = await Promise.all([
            this.getBalances(),
            this.getAssets()
        ]);

        this.updateWalletUI(balanceData.data, assetsData);
    }

    /**
     * Initialize notifications page with real data
     */
    async initializeNotifications() {
        const notificationsData = await this.getNotifications();
        this.updateNotificationsUI(notificationsData);
    }

    /**
     * Initialize common data (for all pages)
     */
    async initializeCommonData() {
        try {
            const [userData, notificationsData] = await Promise.all([
                this.getUserData(),
                this.getNotifications()
            ]);

            // Update user info in header/navigation
            this.updateUserInfo(userData.data.user);
            
            // Update notification badge
            this.updateNotificationBadge(notificationsData.unread_count || 0);
            
        } catch (error) {
            console.warn('Failed to load common data:', error);
        }
    }

    /**
     * Update dashboard UI with real data
     */
    updateDashboardUI(userData, balanceData, notificationsData) {
        // Update user welcome message
        const welcomeElement = document.querySelector('[data-user-name]');
        if (welcomeElement && userData.user) {
            welcomeElement.textContent = userData.user.first_name || userData.user.username;
        }

        // Update balance information
        if (balanceData.balances) {
            this.updateBalanceDisplay(balanceData.balances, balanceData.total_value);
        }

        // Update recent activity
        if (userData.recent_transactions) {
            this.updateRecentActivity(userData.recent_transactions);
        }

        // Update notification count
        const notificationCount = notificationsData.unread_count || 0;
        this.updateNotificationBadge(notificationCount);
    }

    /**
     * Update profile UI with real data
     */
    updateProfileUI(userData) {
        const user = userData.user;
        const activity = userData.activity || {};
        
        // Handle combined full name field
        const fullName = user.first_name && user.last_name ? 
            `${user.first_name} ${user.last_name}` : 
            (user.first_name || user.last_name || user.username);
        
        const fields = {
            'full_name': fullName,
            'first_name': user.first_name,
            'last_name': user.last_name,
            'username': user.username,
            'email': user.email,
            'phone': user.phone,
            'country': user.country,
            'wallet_address': user.wallet_address,
            'kyc_status': user.kyc_status,
            'account_score': user.account_score !== undefined ? user.account_score + '%' : '0%',
            'created_at': user.created_at ? new Date(user.created_at).toLocaleDateString() : 'Unknown',
            'total_deposits': activity.total_deposits ? `$${parseFloat(activity.total_deposits).toFixed(2)}` : '$0.00',
            'total_withdrawals': activity.total_withdrawals ? `$${parseFloat(activity.total_withdrawals).toFixed(2)}` : '$0.00',
            'total_transactions': activity.total_transactions || '0'
        };
        
        Object.entries(fields).forEach(([field, value]) => {
            const element = document.querySelector(`[data-field="${field}"]`) || 
                           document.getElementById(field) ||
                           document.querySelector(`input[name="${field}"]`);
            
            if (element && value !== undefined && value !== null) {
                if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA' || element.tagName === 'SELECT') {
                    element.value = value;
                } else {
                    element.textContent = value;
                }
            }
        });

        // Update KYC status with proper styling
        const kycElement = document.querySelector('[data-field="kyc_status"]');
        if (kycElement && user.kyc_status) {
            const status = user.kyc_status.charAt(0).toUpperCase() + user.kyc_status.slice(1);
            kycElement.textContent = status;
            kycElement.className = `info-value status-${user.kyc_status}`;
        }

        // Update activity summary
        if (userData.recent_transactions) {
            this.updateRecentActivity(userData.recent_transactions);
        }
    }

    /**
     * Update wallet UI with real data
     */
    updateWalletUI(balanceData, assetsData) {
        // Update total portfolio value
        const totalElement = document.querySelector('[data-total-value]');
        if (totalElement && balanceData.total_value !== undefined) {
            totalElement.textContent = `$${parseFloat(balanceData.total_value).toFixed(2)}`;
        }

        // Update asset list
        this.updateAssetList(assetsData.assets || balanceData.balances || []);
    }

    /**
     * Update notifications UI with real data
     */
    updateNotificationsUI(notificationsData) {
        const container = document.querySelector('[data-notifications-container]') || 
                         document.querySelector('.notifications-list');
        
        if (!container) return;

        container.innerHTML = '';

        if (!notificationsData.notifications || notificationsData.notifications.length === 0) {
            container.innerHTML = '<div class="no-notifications">No notifications yet</div>';
            return;
        }

        notificationsData.notifications.forEach(notification => {
            const element = this.createNotificationElement(notification);
            container.appendChild(element);
        });
    }

    /**
     * Update balance display elements
     */
    updateBalanceDisplay(balances, totalValue) {
        // Update total value
        const totalElements = document.querySelectorAll('[data-total-balance]');
        totalElements.forEach(el => {
            el.textContent = `$${parseFloat(totalValue || 0).toFixed(2)}`;
        });

        // Update individual asset balances
        balances.forEach(balance => {
            const element = document.querySelector(`[data-balance="${balance.asset_symbol}"]`);
            if (element) {
                element.textContent = parseFloat(balance.balance).toFixed(8);
            }
        });
    }

    /**
     * Update recent activity list
     */
    updateRecentActivity(transactions) {
        const container = document.querySelector('[data-recent-activity]');
        if (!container) return;

        container.innerHTML = '';

        if (!transactions || transactions.length === 0) {
            container.innerHTML = '<div class="no-activity">No recent activity</div>';
            return;
        }

        transactions.forEach(tx => {
            const element = this.createTransactionElement(tx);
            container.appendChild(element);
        });
    }

    /**
     * Update user info in header/navigation
     */
    updateUserInfo(user) {
        const userNameElements = document.querySelectorAll('[data-user-display-name]');
        userNameElements.forEach(el => {
            el.textContent = user.first_name || user.username;
        });

        const userEmailElements = document.querySelectorAll('[data-user-email]');
        userEmailElements.forEach(el => {
            el.textContent = user.email;
        });
    }

    /**
     * Update notification badge count
     */
    updateNotificationBadge(count) {
        const badges = document.querySelectorAll('[data-notification-badge]');
        badges.forEach(badge => {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        });
    }

    /**
     * Create notification element
     */
    createNotificationElement(notification) {
        const div = document.createElement('div');
        div.className = `notification ${notification.type} ${notification.is_read ? 'read' : 'unread'}`;
        div.innerHTML = `
            <div class="notification-content">
                <h4>${notification.title}</h4>
                <p>${notification.message}</p>
                <small>${new Date(notification.time).toLocaleString()}</small>
            </div>
            ${!notification.is_read ? '<button class="mark-read" onclick="markAsRead(' + notification.id + ')">Mark as read</button>' : ''}
        `;
        return div;
    }

    /**
     * Create transaction element
     */
    createTransactionElement(transaction) {
        const div = document.createElement('div');
        div.className = `transaction ${transaction.transaction_type}`;
        div.innerHTML = `
            <div class="transaction-info">
                <span class="asset">${transaction.asset_symbol}</span>
                <span class="amount">${transaction.amount}</span>
                <span class="type">${transaction.transaction_type}</span>
                <span class="time">${new Date(transaction.created_at).toLocaleString()}</span>
            </div>
            ${transaction.description ? `<p class="description">${transaction.description}</p>` : ''}
        `;
        return div;
    }

    /**
     * Update asset list display
     */
    updateAssetList(assets) {
        const container = document.querySelector('[data-assets-list]') || 
                         document.querySelector('.assets-container');
        
        if (!container) return;

        container.innerHTML = '';

        if (!assets || assets.length === 0) {
            container.innerHTML = '<div class="no-assets">No assets found</div>';
            return;
        }

        assets.forEach(asset => {
            const element = this.createAssetElement(asset);
            container.appendChild(element);
        });
    }

    /**
     * Create asset element
     */
    createAssetElement(asset) {
        const div = document.createElement('div');
        div.className = 'asset-item';
        div.innerHTML = `
            <div class="asset-info">
                <img src="${asset.icon || '/assets/icons/' + asset.symbol.toLowerCase() + '.png'}" alt="${asset.symbol}" class="asset-icon">
                <div class="asset-details">
                    <h4>${asset.symbol}</h4>
                    <p>${asset.name || asset.symbol}</p>
                </div>
            </div>
            <div class="asset-balance">
                <span class="balance">${parseFloat(asset.balance || 0).toFixed(8)}</span>
                <span class="value">$${parseFloat(asset.value || 0).toFixed(2)}</span>
            </div>
        `;
        return div;
    }

    /**
     * Get current page name from URL
     */
    getCurrentPageName() {
        const path = window.location.pathname;
        const page = path.split('/').pop().split('.')[0];
        return page || 'index';
    }

    /**
     * Show error message to user
     */
    showError(message) {
        // Create or update error display
        let errorDiv = document.querySelector('.data-error');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'data-error';
            errorDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #ff4444;
                color: white;
                padding: 12px 16px;
                border-radius: 4px;
                z-index: 10000;
                max-width: 300px;
            `;
            document.body.appendChild(errorDiv);
        }
        
        errorDiv.textContent = message;
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.parentNode.removeChild(errorDiv);
            }
        }, 5000);
    }
}

// Global instance
window.realDataManager = new RealDataManager();

// Initialize on DOM content loaded
document.addEventListener('DOMContentLoaded', () => {
    window.realDataManager.initializePageData();
});

// Utility functions for backward compatibility
window.markAsRead = async function(notificationId) {
    try {
        await window.realDataManager.markNotificationRead(notificationId);
        // Refresh notifications display
        const notificationsData = await window.realDataManager.getNotifications();
        window.realDataManager.updateNotificationsUI(notificationsData);
    } catch (error) {
        console.error('Failed to mark notification as read:', error);
    }
};

// Export for modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = RealDataManager;
}