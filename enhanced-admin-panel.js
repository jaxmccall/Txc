// Enhanced Admin Panel JavaScript - Tripple Exchange
class EnhancedAdminPanel {
    constructor() {
        this.currentUser = null;
        this.stats = {};
        this.impersonationActive = false;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadStats();
        this.checkImpersonationStatus();
        this.initializeAnimations();
    }

    setupEventListeners() {
        // Modal controls
        document.querySelectorAll('[data-modal]').forEach(trigger => {
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                const modalId = trigger.getAttribute('data-modal');
                this.openModal(modalId);
            });
        });

        document.querySelectorAll('.close-btn, .modal').forEach(element => {
            element.addEventListener('click', (e) => {
                if (e.target === element) {
                    this.closeModal(e.target.closest('.modal'));
                }
            });
        });

        // Form submissions
        document.getElementById('createUserForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleCreateUser(e.target);
        });

        document.getElementById('adjustBalanceForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleAdjustBalance(e.target);
        });

        document.getElementById('accountScoreForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleAccountScore(e.target);
        });

        document.getElementById('walletAssignForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleWalletAssignment(e.target);
        });

        document.getElementById('impersonateForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleImpersonation(e.target);
        });

        // Quick actions
        document.getElementById('refreshStats')?.addEventListener('click', () => {
            this.loadStats();
        });

        document.getElementById('viewFrontend')?.addEventListener('click', () => {
            window.open('../dashboard.html', '_blank');
        });

        document.getElementById('logoutBtn')?.addEventListener('click', () => {
            this.handleLogout();
        });

        // Account score preset buttons
        document.querySelectorAll('.score-preset').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const score = e.target.getAttribute('data-score');
                document.getElementById('accountScore').value = score;
            });
        });

        // Balance operation type change
        document.getElementById('balanceOperation')?.addEventListener('change', (e) => {
            const amountGroup = document.querySelector('.amount-group');
            const label = amountGroup.querySelector('label');
            const input = amountGroup.querySelector('input');
            
            switch(e.target.value) {
                case 'credit':
                    label.textContent = 'Amount to Add';
                    input.placeholder = 'Enter amount to credit';
                    break;
                case 'debit':
                    label.textContent = 'Amount to Deduct';
                    input.placeholder = 'Enter amount to debit';
                    break;
                case 'set':
                    label.textContent = 'New Balance';
                    input.placeholder = 'Enter new balance';
                    break;
            }
        });

        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', () => {
            document.querySelector('.sidebar').classList.toggle('open');
        });

        // Stop impersonation
        document.getElementById('stopImpersonation')?.addEventListener('click', () => {
            this.stopImpersonation();
        });
    }

    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            modal.querySelector('.modal-content').classList.add('animate-slide-up');
            document.body.style.overflow = 'hidden';
        }
    }

    closeModal(modal) {
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    async loadStats() {
        try {
            this.showLoading('refreshStats');
            
            // Simulate API call - replace with actual endpoint
            const response = await fetch('api/admin-stats.php');
            const data = await response.json();
            
            if (data.success) {
                this.updateStatsDisplay(data.stats);
            } else {
                this.showAlert('Failed to load statistics', 'danger');
            }
        } catch (error) {
            console.error('Error loading stats:', error);
            this.showAlert('Error loading statistics', 'danger');
        } finally {
            this.hideLoading('refreshStats');
        }
    }

    updateStatsDisplay(stats) {
        // Update stat cards with animation
        const statCards = {
            'totalUsers': stats.total_users || 0,
            'activeUsers': stats.active_users || 0,
            'totalBalance': stats.total_balance || 0,
            'pendingTransactions': stats.pending_transactions || 0
        };

        Object.entries(statCards).forEach(([key, value]) => {
            const element = document.getElementById(key);
            if (element) {
                this.animateCounter(element, parseInt(value));
            }
        });

        // Update change indicators
        this.updateChangeIndicators(stats);
    }

    animateCounter(element, targetValue) {
        const startValue = parseInt(element.textContent) || 0;
        const duration = 1000;
        const startTime = performance.now();

        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            const currentValue = Math.floor(startValue + (targetValue - startValue) * progress);
            element.textContent = currentValue.toLocaleString();

            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };

        requestAnimationFrame(animate);
    }

    updateChangeIndicators(stats) {
        const indicators = {
            'userChange': stats.user_change || 0,
            'balanceChange': stats.balance_change || 0,
            'transactionChange': stats.transaction_change || 0
        };

        Object.entries(indicators).forEach(([key, value]) => {
            const element = document.getElementById(key);
            if (element) {
                const isPositive = value >= 0;
                element.className = `stat-change ${isPositive ? 'positive' : 'negative'}`;
                element.innerHTML = `
                    <i class="fas fa-arrow-${isPositive ? 'up' : 'down'}"></i>
                    ${Math.abs(value)}%
                `;
            }
        });
    }

    async handleCreateUser(form) {
        try {
            this.showLoading('createUserSubmit');
            
            const formData = new FormData(form);
            const response = await fetch('api/admin-create-user.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showAlert('User created successfully!', 'success');
                form.reset();
                this.closeModal(document.getElementById('createUserModal'));
                this.loadStats(); // Refresh stats
            } else {
                this.showAlert(data.message || 'Failed to create user', 'danger');
            }
        } catch (error) {
            console.error('Error creating user:', error);
            this.showAlert('Error creating user', 'danger');
        } finally {
            this.hideLoading('createUserSubmit');
        }
    }

    async handleAdjustBalance(form) {
        try {
            this.showLoading('adjustBalanceSubmit');
            
            const formData = new FormData(form);
            const response = await fetch('api/admin-adjust-balance.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showAlert(`Balance ${formData.get('operation')}ed successfully!`, 'success');
                form.reset();
                this.closeModal(document.getElementById('adjustBalanceModal'));
                this.loadStats();
            } else {
                this.showAlert(data.message || 'Failed to adjust balance', 'danger');
            }
        } catch (error) {
            console.error('Error adjusting balance:', error);
            this.showAlert('Error adjusting balance', 'danger');
        } finally {
            this.hideLoading('adjustBalanceSubmit');
        }
    }

    async handleAccountScore(form) {
        try {
            this.showLoading('accountScoreSubmit');
            
            const formData = new FormData(form);
            const response = await fetch('api/admin-account-score.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showAlert('Account score updated successfully!', 'success');
                form.reset();
                this.closeModal(document.getElementById('accountScoreModal'));
            } else {
                this.showAlert(data.message || 'Failed to update account score', 'danger');
            }
        } catch (error) {
            console.error('Error updating account score:', error);
            this.showAlert('Error updating account score', 'danger');
        } finally {
            this.hideLoading('accountScoreSubmit');
        }
    }

    async handleWalletAssignment(form) {
        try {
            this.showLoading('walletAssignSubmit');
            
            const formData = new FormData(form);
            const response = await fetch('api/admin-wallet-assign.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showAlert('Wallet assigned successfully!', 'success');
                form.reset();
                this.closeModal(document.getElementById('walletAssignModal'));
            } else {
                this.showAlert(data.message || 'Failed to assign wallet', 'danger');
            }
        } catch (error) {
            console.error('Error assigning wallet:', error);
            this.showAlert('Error assigning wallet', 'danger');
        } finally {
            this.hideLoading('walletAssignSubmit');
        }
    }

    async handleImpersonation(form) {
        try {
            this.showLoading('impersonateSubmit');
            
            const formData = new FormData(form);
            const response = await fetch('api/admin-impersonate-user.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showAlert('Impersonation started successfully!', 'success');
                form.reset();
                this.closeModal(document.getElementById('impersonateModal'));
                
                // Show impersonation banner
                this.showImpersonationBanner(data.user);
                
                // Redirect to frontend after short delay
                setTimeout(() => {
                    window.location.href = data.redirect || '../dashboard.html';
                }, 2000);
            } else {
                this.showAlert(data.message || 'Failed to start impersonation', 'danger');
            }
        } catch (error) {
            console.error('Error starting impersonation:', error);
            this.showAlert('Error starting impersonation', 'danger');
        } finally {
            this.hideLoading('impersonateSubmit');
        }
    }

    async checkImpersonationStatus() {
        try {
            const response = await fetch('api/admin-impersonation-status.php');
            const data = await response.json();
            
            if (data.success && data.impersonating) {
                this.showImpersonationBanner(data.user);
            }
        } catch (error) {
            console.error('Error checking impersonation status:', error);
        }
    }

    showImpersonationBanner(user) {
        const banner = document.getElementById('impersonationBanner');
        if (banner) {
            banner.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <i class="fas fa-user-secret"></i>
                        Currently impersonating: <strong>${user.username}</strong> (${user.email})
                    </div>
                    <button id="stopImpersonation" class="btn btn-outline" style="padding: 0.5rem 1rem;">
                        <i class="fas fa-times"></i> Stop Impersonation
                    </button>
                </div>
            `;
            banner.classList.add('active');
            this.impersonationActive = true;
        }
    }

    async stopImpersonation() {
        try {
            const response = await fetch('api/admin-stop-impersonation.php', {
                method: 'POST'
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showAlert('Impersonation stopped', 'info');
                document.getElementById('impersonationBanner').classList.remove('active');
                this.impersonationActive = false;
                
                // Reload page to refresh admin session
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                this.showAlert('Failed to stop impersonation', 'danger');
            }
        } catch (error) {
            console.error('Error stopping impersonation:', error);
            this.showAlert('Error stopping impersonation', 'danger');
        }
    }

    async handleLogout() {
        if (confirm('Are you sure you want to logout?')) {
            try {
                const response = await fetch('../logout.php', {
                    method: 'POST'
                });
                
                if (response.ok) {
                    window.location.href = '../login.html';
                } else {
                    this.showAlert('Logout failed', 'danger');
                }
            } catch (error) {
                console.error('Error during logout:', error);
                this.showAlert('Error during logout', 'danger');
            }
        }
    }

    showAlert(message, type = 'info') {
        // Remove existing alerts
        document.querySelectorAll('.alert').forEach(alert => alert.remove());
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} animate-fade-in`;
        
        const icon = this.getAlertIcon(type);
        alertDiv.innerHTML = `
            <i class="${icon}"></i>
            ${message}
        `;
        
        // Insert after header
        const header = document.querySelector('.admin-header');
        header.insertAdjacentElement('afterend', alertDiv);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            alertDiv.style.opacity = '0';
            setTimeout(() => alertDiv.remove(), 300);
        }, 5000);
    }

    getAlertIcon(type) {
        const icons = {
            'success': 'fas fa-check-circle',
            'danger': 'fas fa-exclamation-triangle',
            'warning': 'fas fa-exclamation-circle',
            'info': 'fas fa-info-circle'
        };
        return icons[type] || icons.info;
    }

    showLoading(buttonId) {
        const button = document.getElementById(buttonId);
        if (button) {
            button.disabled = true;
            const originalText = button.innerHTML;
            button.innerHTML = '<span class="loading"></span> Processing...';
            button.setAttribute('data-original-text', originalText);
        }
    }

    hideLoading(buttonId) {
        const button = document.getElementById(buttonId);
        if (button) {
            button.disabled = false;
            const originalText = button.getAttribute('data-original-text');
            if (originalText) {
                button.innerHTML = originalText;
                button.removeAttribute('data-original-text');
            }
        }
    }

    initializeAnimations() {
        // Animate cards on load
        const cards = document.querySelectorAll('.stat-card, .control-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.6s ease-out';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });

        // Add hover effects to action items
        document.querySelectorAll('.action-item').forEach(item => {
            item.addEventListener('mouseenter', () => {
                item.style.transform = 'translateX(10px)';
            });
            
            item.addEventListener('mouseleave', () => {
                item.style.transform = 'translateX(0)';
            });
        });

        // Add ripple effect to buttons
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', (e) => {
                const ripple = document.createElement('span');
                const rect = button.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
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
                
                button.style.position = 'relative';
                button.style.overflow = 'hidden';
                button.appendChild(ripple);
                
                setTimeout(() => ripple.remove(), 600);
            });
        });
    }

    // Utility methods
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    }

    formatDate(date) {
        return new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(new Date(date));
    }

    validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    validateUsername(username) {
        const re = /^[a-zA-Z0-9_]{3,20}$/;
        return re.test(username);
    }
}

// Add ripple animation CSS
const rippleCSS = `
@keyframes ripple {
    to {
        transform: scale(4);
        opacity: 0;
    }
}
`;

const style = document.createElement('style');
style.textContent = rippleCSS;
document.head.appendChild(style);

// Initialize the admin panel when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.adminPanel = new EnhancedAdminPanel();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = EnhancedAdminPanel;
}
