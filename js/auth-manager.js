// Auth Manager - handles user authentication and session management
class AuthManager {
    constructor() {
        this.user = null;
        this.isAuthenticated = false;
        this.init();
    }

    async init() {
        await this.checkAuthStatus();
        this.setupSessionMonitoring();
    }

    async checkAuthStatus() {
        try {
            const response = await fetch('check-session.php');
            const data = await response.json();
            
            if (data.authenticated) {
                this.isAuthenticated = true;
                this.user = {
                    id: data.user_id,
                    username: data.username,
                    email: data.email || `${data.username}@trippleexchange.com`
                };
                this.updateUserInterface();
            } else {
                this.redirectToLogin();
            }
        } catch (error) {
            console.error('Auth check failed:', error);
            this.redirectToLogin();
        }
    }

    updateUserInterface() {
        if (!this.user) return;

        // Update user name displays
        const nameElements = document.querySelectorAll('[data-user-name]');
        nameElements.forEach(el => {
            el.textContent = this.user.username;
        });

        // Update email displays
        const emailElements = document.querySelectorAll('[data-user-email]');
        emailElements.forEach(el => {
            el.textContent = this.user.email;
        });

        // Update user avatar initials
        const avatarElements = document.querySelectorAll('[data-user-avatar]');
        avatarElements.forEach(el => {
            const initials = this.user.username.substring(0, 2).toUpperCase();
            el.textContent = initials;
        });

        // Update account ID display
        const accountIdElements = document.querySelectorAll('[data-account-id]');
        accountIdElements.forEach(el => {
            el.textContent = `CTP-${this.user.id.toString().padStart(4, '0')}-${Math.floor(Math.random() * 9999).toString().padStart(4, '0')}`;
        });
    }

    async getUserProfile() {
        if (!this.isAuthenticated) return null;
        
        try {
            const response = await fetch('get-user-profile.php');
            const data = await response.json();
            
            if (data.success) {
                return data.profile;
            }
        } catch (error) {
            console.error('Failed to fetch user profile:', error);
        }
        
        return null;
    }

    setupSessionMonitoring() {
        // Check session every 5 minutes
        setInterval(() => {
            this.checkAuthStatus();
        }, 5 * 60 * 1000);
    }

    redirectToLogin() {
        window.location.href = 'login.html';
    }

    async logout() {
        try {
            const response = await fetch('logout.php', { method: 'POST' });
            const data = await response.json();
            
            if (data.success) {
                this.redirectToLogin();
            }
        } catch (error) {
            console.error('Logout failed:', error);
            // Force redirect even if logout request fails
            this.redirectToLogin();
        }
    }
}

// Create global auth manager instance
window.authManager = new AuthManager();