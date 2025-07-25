/**
 * User Authentication Manager
 * Handles session checking and redirects for regular users
 */
class AuthManager {
    constructor() {
        this.init();
    }

    async init() {
        // Check authentication status on page load
        await this.checkAuth();
    }

    async checkAuth() {
        try {
            const response = await fetch('check-session.php');
            const data = await response.json();
            
            if (!data.authenticated) {
                // User is not authenticated, redirect to login
                this.redirectToLogin();
                return false;
            }
            
            // User is authenticated, update last activity
            this.updateLastActivity();
            return true;
            
        } catch (error) {
            console.error('Auth check failed:', error);
            // On error, redirect to login to be safe
            this.redirectToLogin();
            return false;
        }
    }

    redirectToLogin() {
        // Avoid infinite redirect loops
        if (!window.location.pathname.includes('login.html')) {
            window.location.href = 'login.html';
        }
    }

    updateLastActivity() {
        // Update session activity timestamp
        // This could be expanded to send periodic heartbeats
        localStorage.setItem('lastActivity', Date.now());
    }

    async logout() {
        try {
            const response = await fetch('logout.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Clear local data
                localStorage.clear();
                sessionStorage.clear();
                
                // Redirect to login
                window.location.href = 'login.html';
            } else {
                console.error('Logout failed:', data.message);
                // Force redirect anyway
                window.location.href = 'login.html';
            }
            
        } catch (error) {
            console.error('Logout error:', error);
            // Force redirect on error
            window.location.href = 'login.html';
        }
    }
}

// Initialize auth manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize on non-login pages
    if (!window.location.pathname.includes('login.html') && 
        !window.location.pathname.includes('signup.html') &&
        !window.location.pathname.includes('index.html')) {
        window.authManager = new AuthManager();
    }
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AuthManager;
}