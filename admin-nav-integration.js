/**
 * Admin Navigation Integration - Tripple Exchange
 * Links admin control panel with existing admin pages and frontend
 */

// Admin Navigation Integration System
class AdminNavigation {
    constructor() {
        this.currentPage = this.getCurrentPage();
        this.init();
    }
    
    init() {
        this.addControlPanelLink();
        this.addFrontendLinks();
        this.setupImpersonationBanner();
        this.enhanceExistingPages();
    }
    
    getCurrentPage() {
        const path = window.location.pathname;
        if (path.includes('admin-dashboard.html')) return 'dashboard';
        if (path.includes('admin-users.html')) return 'users';
        if (path.includes('admin-deposits.html')) return 'deposits';
        if (path.includes('admin-withdrawals.html')) return 'withdrawals';
        if (path.includes('admin-control-panel.html')) return 'control-panel';
        return 'other';
    }
    
    addControlPanelLink() {
        // Add control panel link to all admin pages
        const sidebar = document.querySelector('.sidebar nav, .nav');
        if (sidebar && !document.querySelector('.control-panel-link')) {
            const controlPanelLink = document.createElement('a');
            controlPanelLink.href = 'admin-control-panel.html';
            controlPanelLink.className = 'nav-link control-panel-link';
            controlPanelLink.innerHTML = `
                <i class="fas fa-shield-alt"></i>
                <span>Control Panel</span>
            `;
            
            // Insert at the top of navigation
            sidebar.insertBefore(controlPanelLink, sidebar.firstChild);
        }
    }
    
    addFrontendLinks() {
        // Add frontend access buttons to admin pages
        const header = document.querySelector('.page-header, .admin-header');
        if (header && !document.querySelector('.frontend-access-btn')) {
            const frontendBtn = document.createElement('button');
            frontendBtn.className = 'btn btn-outline frontend-access-btn';
            frontendBtn.innerHTML = `
                <i class="fas fa-external-link-alt"></i>
                View Frontend
            `;
            frontendBtn.onclick = () => window.open('../index.html', '_blank');
            
            const actionButtons = header.querySelector('.action-buttons, .admin-actions');
            if (actionButtons) {
                actionButtons.appendChild(frontendBtn);
            } else {
                header.appendChild(frontendBtn);
            }
        }
    }
    
    setupImpersonationBanner() {
        // Check for impersonation status and add banner if needed
        fetch('api/admin-impersonation-status.php')
            .then(response => response.json())
            .then(data => {
                if (data.impersonating && !document.querySelector('.impersonation-banner')) {
                    this.createImpersonationBanner(data.username);
                }
            })
            .catch(error => console.error('Error checking impersonation:', error));
    }
    
    createImpersonationBanner(username) {
        const banner = document.createElement('div');
        banner.className = 'impersonation-banner';
        banner.style.cssText = `
            background: linear-gradient(135deg, #ffaa00, #ff8c00);
            color: white;
            padding: 1rem 2rem;
            text-align: center;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        `;
        
        banner.innerHTML = `
            <i class="fas fa-user-secret"></i>
            Currently impersonating user: <strong>${username}</strong>
            <button onclick="endImpersonation()" style="
                margin-left: 1rem;
                background: #f72585;
                color: white;
                border: none;
                padding: 0.5rem 1rem;
                border-radius: 0.5rem;
                cursor: pointer;
                font-weight: 600;
            ">
                <i class="fas fa-sign-out-alt"></i> End Impersonation
            </button>
        `;
        
        document.body.insertBefore(banner, document.body.firstChild);
    }
    
    enhanceExistingPages() {
        // Enhance existing admin pages with additional functionality
        switch (this.currentPage) {
            case 'users':
                this.enhanceUsersPage();
                break;
            case 'deposits':
                this.enhanceDepositsPage();
                break;
            case 'withdrawals':
                this.enhanceWithdrawalsPage();
                break;
            case 'dashboard':
                this.enhanceDashboardPage();
                break;
        }
    }
    
    enhanceUsersPage() {
        // Add quick actions to users page
        const pageHeader = document.querySelector('.page-header');
        if (pageHeader && !document.querySelector('.quick-actions-users')) {
            const quickActions = document.createElement('div');
            quickActions.className = 'quick-actions-users';
            quickActions.style.cssText = `
                display: flex;
                gap: 0.5rem;
                margin-top: 1rem;
            `;
            
            quickActions.innerHTML = `
                <button class="btn btn-primary" onclick="openQuickUserModal()">
                    <i class="fas fa-user-plus"></i> Quick Add User
                </button>
                <button class="btn btn-success" onclick="openQuickBalanceModal()">
                    <i class="fas fa-wallet"></i> Quick Balance
                </button>
                <button class="btn btn-warning" onclick="exportUserData()">
                    <i class="fas fa-download"></i> Export Data
                </button>
            `;
            
            pageHeader.appendChild(quickActions);
        }
    }
    
    enhanceDepositsPage() {
        // Add deposit management tools
        this.addDepositTools();
    }
    
    enhanceWithdrawalsPage() {
        // Add withdrawal management tools
        this.addWithdrawalTools();
    }
    
    enhanceDashboardPage() {
        // Add dashboard enhancements
        this.addDashboardTools();
    }
    
    addDepositTools() {
        const container = document.querySelector('.main-content, .page-content');
        if (container && !document.querySelector('.deposit-tools')) {
            const tools = document.createElement('div');
            tools.className = 'deposit-tools';
            tools.style.cssText = `
                background: var(--card-bg, #1e293b);
                padding: 1rem;
                border-radius: 0.5rem;
                margin-bottom: 1rem;
                border: 1px solid var(--border, #334155);
            `;
            
            tools.innerHTML = `
                <h3 style="margin-bottom: 1rem; color: var(--primary, #0096FF);">
                    <i class="fas fa-tools"></i> Deposit Management Tools
                </h3>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <button class="btn btn-success" onclick="approveAllPending()">
                        <i class="fas fa-check-double"></i> Approve All Pending
                    </button>
                    <button class="btn btn-primary" onclick="refreshDeposits()">
                        <i class="fas fa-sync-alt"></i> Refresh Data
                    </button>
                    <button class="btn btn-warning" onclick="exportDeposits()">
                        <i class="fas fa-file-export"></i> Export Deposits
                    </button>
                </div>
            `;
            
            container.insertBefore(tools, container.firstChild.nextSibling);
        }
    }
    
    addWithdrawalTools() {
        const container = document.querySelector('.main-content, .page-content');
        if (container && !document.querySelector('.withdrawal-tools')) {
            const tools = document.createElement('div');
            tools.className = 'withdrawal-tools';
            tools.style.cssText = `
                background: var(--card-bg, #1e293b);
                padding: 1rem;
                border-radius: 0.5rem;
                margin-bottom: 1rem;
                border: 1px solid var(--border, #334155);
            `;
            
            tools.innerHTML = `
                <h3 style="margin-bottom: 1rem; color: var(--primary, #0096FF);">
                    <i class="fas fa-tools"></i> Withdrawal Management Tools
                </h3>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <button class="btn btn-success" onclick="processAllWithdrawals()">
                        <i class="fas fa-cogs"></i> Process All Pending
                    </button>
                    <button class="btn btn-danger" onclick="reviewSuspiciousWithdrawals()">
                        <i class="fas fa-exclamation-triangle"></i> Review Suspicious
                    </button>
                    <button class="btn btn-primary" onclick="refreshWithdrawals()">
                        <i class="fas fa-sync-alt"></i> Refresh Data
                    </button>
                </div>
            `;
            
            container.insertBefore(tools, container.firstChild.nextSibling);
        }
    }
    
    addDashboardTools() {
        const container = document.querySelector('.main-content, .page-content');
        if (container && !document.querySelector('.dashboard-tools')) {
            const tools = document.createElement('div');
            tools.className = 'dashboard-tools';
            tools.style.cssText = `
                background: var(--card-bg, #1e293b);
                padding: 1rem;
                border-radius: 0.5rem;
                margin-bottom: 1rem;
                border: 1px solid var(--border, #334155);
            `;
            
            tools.innerHTML = `
                <h3 style="margin-bottom: 1rem; color: var(--primary, #0096FF);">
                    <i class="fas fa-tools"></i> Platform Management Tools
                </h3>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <button class="btn btn-primary" onclick="openControlPanel()">
                        <i class="fas fa-shield-alt"></i> Control Panel
                    </button>
                    <button class="btn btn-success" onclick="systemHealthCheck()">
                        <i class="fas fa-heartbeat"></i> System Health
                    </button>
                    <button class="btn btn-warning" onclick="viewSecurityLogs()">
                        <i class="fas fa-shield-alt"></i> Security Logs
                    </button>
                </div>
            `;
            
            container.insertBefore(tools, container.firstChild.nextSibling);
        }
    }
}

// Global functions for enhanced functionality
window.openQuickUserModal = function() {
    window.location.href = 'admin-control-panel.html#create-user';
};

window.openQuickBalanceModal = function() {
    window.location.href = 'admin-control-panel.html#balance-adjustment';
};

window.exportUserData = function() {
    showAlert('Exporting user data...', 'info');
    // Implementation for user data export
};

window.approveAllPending = function() {
    if (confirm('Are you sure you want to approve all pending deposits?')) {
        showAlert('Processing all pending deposits...', 'info');
        // Implementation for bulk approval
    }
};

window.refreshDeposits = function() {
    window.location.reload();
};

window.exportDeposits = function() {
    showAlert('Exporting deposit data...', 'info');
    // Implementation for deposit export
};

window.processAllWithdrawals = function() {
    if (confirm('Are you sure you want to process all pending withdrawals?')) {
        showAlert('Processing all pending withdrawals...', 'info');
        // Implementation for bulk processing
    }
};

window.reviewSuspiciousWithdrawals = function() {
    showAlert('Reviewing suspicious withdrawals...', 'info');
    // Implementation for suspicious review
};

window.refreshWithdrawals = function() {
    window.location.reload();
};

window.openControlPanel = function() {
    window.location.href = 'admin-control-panel.html';
};

window.systemHealthCheck = function() {
    showAlert('Running system health check...', 'info');
    // Implementation for health check
};

window.viewSecurityLogs = function() {
    window.location.href = 'admin-logs.html';
};

window.endImpersonation = async function() {
    try {
        const response = await fetch('api/admin-stop-impersonation.php', {
            method: 'POST'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('Impersonation ended successfully', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showAlert('Error ending impersonation', 'danger');
        }
    } catch (error) {
        showAlert('Network error occurred', 'danger');
    }
};

// Utility function for alerts (if not already defined)
window.showAlert = window.showAlert || function(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#38b000' : type === 'danger' ? '#f72585' : type === 'warning' ? '#ffaa00' : '#0096FF'};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 0.5rem;
        z-index: 10000;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        animation: slideIn 0.3s ease;
    `;
    
    alertDiv.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" style="margin-left: 1rem; background: none; border: none; color: white; cursor: pointer; font-size: 1.2rem;">&times;</button>
        </div>
    `;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv.parentElement) {
            alertDiv.remove();
        }
    }, 5000);
};

// Initialize admin navigation when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    new AdminNavigation();
});

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    .control-panel-link.active {
        background: linear-gradient(90deg, #0096FF 70%, #4cc9f0) !important;
        color: #fff !important;
        box-shadow: 0 2px 10px rgba(0, 150, 255, 0.15) !important;
    }
    
    .frontend-access-btn:hover {
        background: rgba(0, 150, 255, 0.1) !important;
        border-color: #0096FF !important;
    }
    
    .impersonation-banner button:hover {
        background: #d63384 !important;
        transform: translateY(-1px);
    }
`;
document.head.appendChild(style);
