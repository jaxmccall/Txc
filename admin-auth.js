// Universal Admin Authentication and Navigation System
class AdminAuth {
    constructor() {
        this.init();
    }

    async init() {
        // Skip authentication check on login page
        if (window.location.pathname.includes('admin-login.html')) {
            return;
        }

        await this.checkAuthentication();
        this.setupNavigation();
        this.setupLogout();
    }

    async checkAuthentication() {
        try {
            const response = await fetch('api/admin-session-check.php');
            const data = await response.json();

            if (!data.success || !data.authenticated) {
                // Redirect to login page
                window.location.href = 'admin-login.html';
                return;
            }

            // Store admin info globally
            window.adminInfo = data.admin;
            this.updateAdminInfo(data.admin);

        } catch (error) {
            console.error('Authentication check failed:', error);
            window.location.href = 'admin-login.html';
        }
    }

    updateAdminInfo(admin) {
        // Update admin username display if element exists
        const adminUsernameElements = document.querySelectorAll('.admin-username');
        adminUsernameElements.forEach(el => {
            el.textContent = admin.username || 'Admin';
        });

        // Update admin email display if element exists
        const adminEmailElements = document.querySelectorAll('.admin-email');
        adminEmailElements.forEach(el => {
            el.textContent = admin.email || '';
        });
    }

    setupNavigation() {
        // Add navigation links to all admin pages
        this.addAdminNavigation();
        this.highlightCurrentPage();
    }

    addAdminNavigation() {
        // Check if navigation already exists
        if (document.querySelector('.admin-nav-injected')) {
            return;
        }

        // Create navigation HTML
        const navHTML = `
            <div class="admin-nav-injected" style="position: fixed; top: 0; left: 0; right: 0; background: linear-gradient(135deg, #1a1f2e 0%, #252b3d 100%); z-index: 9999; padding: 0.75rem 1rem; box-shadow: 0 2px 10px rgba(0,0,0,0.3); border-bottom: 2px solid #0096FF;">
                <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto;">
                    <div style="display: flex; align-items: center; gap: 2rem;">
                        <div style="color: #0096FF; font-weight: 800; font-size: 1.2rem;">
                            <i class="fas fa-shield-alt"></i> TRIPPLE ADMIN
                        </div>
                        <nav style="display: flex; gap: 1.5rem;">
                            <a href="enhanced-admin-panel.html" class="admin-nav-link" style="color: #f8fafc; text-decoration: none; padding: 0.5rem 1rem; border-radius: 0.5rem; transition: all 0.3s; font-weight: 500;">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                            <a href="admin-users.html" class="admin-nav-link" style="color: #f8fafc; text-decoration: none; padding: 0.5rem 1rem; border-radius: 0.5rem; transition: all 0.3s; font-weight: 500;">
                                <i class="fas fa-users"></i> Users
                            </a>
                            <a href="create-test-user.html" class="admin-nav-link" style="color: #f8fafc; text-decoration: none; padding: 0.5rem 1rem; border-radius: 0.5rem; transition: all 0.3s; font-weight: 500;">
                                <i class="fas fa-user-plus"></i> Test User
                            </a>
                            <a href="../dashboard.html" target="_blank" class="admin-nav-link" style="color: #4cc9f0; text-decoration: none; padding: 0.5rem 1rem; border-radius: 0.5rem; transition: all 0.3s; font-weight: 500;">
                                <i class="fas fa-external-link-alt"></i> Frontend
                            </a>
                        </nav>
                    </div>
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <span class="admin-username" style="color: #94a3b8; font-size: 0.9rem;"></span>
                        <button id="adminLogoutBtn" style="background: linear-gradient(135deg, #f72585 0%, #f87171 100%); color: white; border: none; padding: 0.5rem 1rem; border-radius: 0.5rem; cursor: pointer; font-weight: 600; transition: all 0.3s;">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Insert navigation at the beginning of body
        document.body.insertAdjacentHTML('afterbegin', navHTML);

        // Add margin to main content to account for fixed nav
        document.body.style.paddingTop = '70px';

        // Add hover effects
        const navLinks = document.querySelectorAll('.admin-nav-link');
        navLinks.forEach(link => {
            link.addEventListener('mouseenter', () => {
                link.style.background = 'rgba(0, 150, 255, 0.2)';
                link.style.color = '#0096FF';
            });
            link.addEventListener('mouseleave', () => {
                if (!link.classList.contains('active')) {
                    link.style.background = 'transparent';
                    link.style.color = '#f8fafc';
                }
            });
        });
    }

    highlightCurrentPage() {
        const currentPage = window.location.pathname.split('/').pop();
        const navLinks = document.querySelectorAll('.admin-nav-link');
        
        navLinks.forEach(link => {
            const linkPage = link.getAttribute('href');
            if (linkPage === currentPage || 
                (currentPage === 'admin-control-panel.html' && linkPage === 'enhanced-admin-panel.html')) {
                link.classList.add('active');
                link.style.background = 'rgba(0, 150, 255, 0.3)';
                link.style.color = '#0096FF';
            }
        });
    }

    setupLogout() {
        // Handle logout button click
        document.addEventListener('click', async (e) => {
            if (e.target.id === 'adminLogoutBtn' || e.target.closest('#adminLogoutBtn')) {
                e.preventDefault();
                await this.logout();
            }
        });

        // Also handle any existing logout buttons on the page
        const existingLogoutBtns = document.querySelectorAll('[onclick*="logout"], .logout-btn, #logoutBtn');
        existingLogoutBtns.forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                await this.logout();
            });
        });
    }

    async logout() {
        if (!confirm('Are you sure you want to logout?')) {
            return;
        }

        try {
            const response = await fetch('api/admin-logout.php', {
                method: 'POST'
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Show success message
                this.showNotification('Logged out successfully', 'success');
                
                // Redirect to login page after short delay
                setTimeout(() => {
                    window.location.href = data.redirect || 'admin-login.html';
                }, 1000);
            } else {
                this.showNotification('Logout failed', 'error');
            }
        } catch (error) {
            console.error('Logout error:', error);
            this.showNotification('Logout error', 'error');
        }
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            background: ${type === 'success' ? '#38b000' : type === 'error' ? '#f72585' : '#0096FF'};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            z-index: 10000;
            font-weight: 600;
            animation: slideIn 0.3s ease-out;
        `;
        
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
            ${message}
        `;

        document.body.appendChild(notification);

        // Remove after 3 seconds
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease-in forwards';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

// Initialize admin authentication when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.adminAuth = new AdminAuth();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AdminAuth;
}
