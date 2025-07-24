/**
 * Admin Control Panel JavaScript - Tripple Exchange
 * Comprehensive admin functionality for platform management
 */

// Initialize the control panel
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardStats();
    checkImpersonationStatus();
    setupEventListeners();
});

// Setup event listeners
function setupEventListeners() {
    // User form submission
    document.getElementById('userForm').addEventListener('submit', handleUserCreation);
    
    // Balance form submission
    document.getElementById('balanceForm').addEventListener('submit', handleBalanceAdjustment);
    
    // Impersonation form submission
    document.getElementById('impersonationForm').addEventListener('submit', handleImpersonation);
    
    // Close modals when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal(modal.id);
            }
        });
    });
}

// Load dashboard statistics
async function loadDashboardStats() {
    try {
        const response = await fetch('api/admin-dashboard-stats.php');
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('totalUsers').textContent = data.stats.total_users || 0;
            document.getElementById('activeUsers').textContent = data.stats.active_users || 0;
            document.getElementById('totalBalance').textContent = '$' + (parseFloat(data.stats.total_balance || 0).toLocaleString());
            document.getElementById('pendingWithdrawals').textContent = data.stats.pending_withdrawals || 0;
        }
    } catch (error) {
        console.error('Error loading dashboard stats:', error);
        showAlert('Error loading dashboard statistics', 'danger');
    }
}

// Check if admin is currently impersonating a user
function checkImpersonationStatus() {
    fetch('api/admin-impersonation-status.php')
        .then(response => response.json())
        .then(data => {
            if (data.impersonating) {
                document.getElementById('impersonationBanner').classList.add('active');
                document.getElementById('impersonatedUser').textContent = data.username;
            }
        })
        .catch(error => console.error('Error checking impersonation status:', error));
}

// Handle user creation
async function handleUserCreation(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    try {
        showLoading();
        const response = await fetch('api/admin-create-user.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('User created successfully!', 'success');
            closeModal('userModal');
            e.target.reset();
            loadDashboardStats();
        } else {
            showAlert(result.message || 'Error creating user', 'danger');
        }
    } catch (error) {
        showAlert('Network error occurred', 'danger');
    } finally {
        hideLoading();
    }
}

// Handle balance adjustment
async function handleBalanceAdjustment(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    try {
        showLoading();
        const response = await fetch('api/admin-adjust-balance.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert(`Balance ${formData.get('action')}ed successfully!`, 'success');
            closeModal('balanceModal');
            e.target.reset();
            loadDashboardStats();
        } else {
            showAlert(result.message || 'Error adjusting balance', 'danger');
        }
    } catch (error) {
        showAlert('Network error occurred', 'danger');
    } finally {
        hideLoading();
    }
}

// Handle user impersonation
async function handleImpersonation(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    try {
        showLoading();
        const response = await fetch('api/admin-impersonate-user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_identifier: formData.get('user_identifier'),
                reason: formData.get('reason')
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Redirect to frontend as the impersonated user
            window.location.href = '../dashboard.html';
        } else {
            showAlert(result.message || 'Error starting impersonation', 'danger');
        }
    } catch (error) {
        showAlert('Network error occurred', 'danger');
    } finally {
        hideLoading();
    }
}

// Navigation functions
function openUserModal() {
    document.getElementById('userModal').classList.add('active');
}

function openBalanceModal() {
    document.getElementById('balanceModal').classList.add('active');
}

function openDebitModal() {
    openBalanceModal();
    // Pre-select debit action
    document.querySelector('select[name="action"]').value = 'debit';
}

function openImpersonationModal() {
    document.getElementById('impersonationModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Navigation to existing admin pages
function viewAllUsers() {
    window.location.href = 'admin-users.html';
}

function bulkUserActions() {
    window.location.href = 'admin-users.html#bulk-actions';
}

function viewBalanceHistory() {
    window.location.href = 'admin-users.html#balance-history';
}

function viewPendingDeposits() {
    window.location.href = 'admin-deposits.html';
}

function viewPendingWithdrawals() {
    window.location.href = 'admin-withdrawals.html';
}

function processTransactions() {
    window.location.href = 'admin-withdrawals.html#process';
}

function editPlatformSettings() {
    window.location.href = 'admin-get-settings.html';
}

function manageFees() {
    showAlert('Fee management feature coming soon!', 'warning');
}

function securitySettings() {
    window.location.href = 'admin-logs.html';
}

function viewActiveImpersonations() {
    showAlert('Active impersonations feature coming soon!', 'warning');
}

function viewSystemLogs() {
    window.location.href = 'admin-logs.html';
}

function viewSecurityAlerts() {
    window.location.href = 'admin-logs.html#security';
}

function systemHealth() {
    showAlert('System health monitoring feature coming soon!', 'warning');
}

function viewFrontend() {
    window.open('../index.html', '_blank');
}

function refreshStats() {
    loadDashboardStats();
    showAlert('Statistics refreshed!', 'success');
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = 'admin-logout.php';
    }
}

// End impersonation
async function endImpersonation() {
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
}

// Utility functions
function showAlert(message, type = 'success') {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert-notification');
    existingAlerts.forEach(alert => alert.remove());
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-notification`;
    alertDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        min-width: 300px;
        max-width: 500px;
        animation: slideIn 0.3s ease;
    `;
    
    alertDiv.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'exclamation-triangle'}"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: inherit; cursor: pointer; font-size: 1.2rem;">&times;</button>
        </div>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentElement) {
            alertDiv.remove();
        }
    }, 5000);
}

function showLoading() {
    const loadingDiv = document.createElement('div');
    loadingDiv.id = 'loading-indicator';
    loadingDiv.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10001;
    `;
    
    loadingDiv.innerHTML = `
        <div style="background: var(--card); padding: 2rem; border-radius: 1rem; text-align: center;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary); margin-bottom: 1rem;"></i>
            <div>Processing...</div>
        </div>
    `;
    
    document.body.appendChild(loadingDiv);
}

function hideLoading() {
    const loadingDiv = document.getElementById('loading-indicator');
    if (loadingDiv) {
        loadingDiv.remove();
    }
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .alert-notification {
        animation: slideIn 0.3s ease;
    }
`;
document.head.appendChild(style);

// Auto-refresh stats every 30 seconds
setInterval(loadDashboardStats, 30000);

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Escape key to close modals
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.active').forEach(modal => {
            closeModal(modal.id);
        });
    }
    
    // Ctrl+R to refresh stats
    if (e.ctrlKey && e.key === 'r') {
        e.preventDefault();
        refreshStats();
    }
});

// Export functions for global access
window.adminControlPanel = {
    openUserModal,
    openBalanceModal,
    openImpersonationModal,
    closeModal,
    refreshStats,
    showAlert,
    endImpersonation
};
