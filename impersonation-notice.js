/* Add this to any user page head section */
<script>
// Check if admin is impersonating and show notice
document.addEventListener('DOMContentLoaded', function() {
    checkImpersonationStatus();
});

async function checkImpersonationStatus() {
    try {
        const response = await fetch('check-session.php');
        const data = await response.json();
        
        if (data.impersonating) {
            showImpersonationNotice();
        }
    } catch (error) {
        console.error('Error checking impersonation status:', error);
    }
}

function showImpersonationNotice() {
    // Create impersonation notice bar
    const notice = document.createElement('div');
    notice.id = 'impersonation-notice';
    notice.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        background: linear-gradient(135deg, #ff6b35, #f7931e);
        color: white;
        padding: 12px 20px;
        text-align: center;
        z-index: 9999;
        font-weight: 600;
        box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        font-family: 'Inter', sans-serif;
    `;
    
    notice.innerHTML = `
        <div style="display: flex; align-items: center; justify-content: center; gap: 15px;">
            <i class="fas fa-user-secret" style="font-size: 1.2rem;"></i>
            <span>Admin Impersonation Mode Active</span>
            <button onclick="stopImpersonation()" style="
                background: rgba(255,255,255,0.2);
                border: 1px solid rgba(255,255,255,0.3);
                color: white;
                padding: 6px 12px;
                border-radius: 6px;
                cursor: pointer;
                font-weight: 600;
                transition: all 0.3s ease;
            " onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                <i class="fas fa-sign-out-alt"></i> Exit Impersonation
            </button>
        </div>
    `;
    
    document.body.insertBefore(notice, document.body.firstChild);
    
    // Adjust body padding to account for notice
    document.body.style.paddingTop = '60px';
}

async function stopImpersonation() {
    if (confirm('End impersonation and return to admin panel?')) {
        try {
            const response = await fetch('admin-stop-impersonation.php', {
                method: 'POST'
            });
            const data = await response.json();
            
            if (data.success) {
                window.location.href = data.redirect;
            } else {
                alert('Failed to stop impersonation: ' + data.error);
            }
        } catch (error) {
            console.error('Error stopping impersonation:', error);
            alert('Failed to stop impersonation');
        }
    }
}
</script>