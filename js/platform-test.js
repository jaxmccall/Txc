/**
 * Platform Integration Test - Tripple Exchange
 * Tests all integrated systems and data flows
 */

class PlatformTester {
    constructor() {
        this.results = [];
        this.testContainer = null;
    }

    async runAllTests() {
        this.createTestUI();
        
        await this.testSession();
        await this.testRealDataManager();
        await this.testBalanceAPI();
        await this.testUserAPI();
        await this.testNotifications();
        await this.testAuthentication();
        
        this.displayResults();
    }

    createTestUI() {
        // Create test results container
        this.testContainer = document.createElement('div');
        this.testContainer.id = 'platform-test-results';
        this.testContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            width: 400px;
            max-height: 80vh;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 20px;
            z-index: 10000;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
            color: #f8fafc;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        `;
        
        this.testContainer.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0; color: #4cc9f0;">Platform Integration Test</h3>
                <button onclick="this.parentElement.parentElement.remove()" style="
                    background: #f72585; color: white; border: none; 
                    border-radius: 4px; padding: 4px 8px; cursor: pointer;
                ">✕</button>
            </div>
            <div id="test-results-content">
                <p style="color: #94a3b8;">Running tests...</p>
            </div>
        `;
        
        document.body.appendChild(this.testContainer);
    }

    log(message, status = 'info') {
        const colors = {
            'pass': '#38b000',
            'fail': '#f72585', 
            'warn': '#ffaa00',
            'info': '#4cc9f0'
        };
        
        this.results.push({
            message,
            status,
            color: colors[status] || colors.info,
            timestamp: new Date().toLocaleTimeString()
        });
        
        this.updateDisplay();
    }

    updateDisplay() {
        if (!this.testContainer) return;
        
        const content = document.getElementById('test-results-content');
        if (!content) return;
        
        content.innerHTML = this.results.map(result => 
            `<div style="color: ${result.color}; margin-bottom: 5px;">
                [${result.timestamp}] ${result.message}
            </div>`
        ).join('');
        
        // Auto-scroll to bottom
        this.testContainer.scrollTop = this.testContainer.scrollHeight;
    }

    async testSession() {
        try {
            this.log('Testing session management...', 'info');
            
            // Test if session data is available
            if (typeof Storage !== 'undefined') {
                this.log('✓ Browser storage available', 'pass');
            } else {
                this.log('✗ Browser storage not available', 'fail');
            }
            
            // Test session storage
            sessionStorage.setItem('test_key', 'test_value');
            const value = sessionStorage.getItem('test_key');
            if (value === 'test_value') {
                this.log('✓ Session storage working', 'pass');
                sessionStorage.removeItem('test_key');
            } else {
                this.log('✗ Session storage failed', 'fail');
            }
            
        } catch (error) {
            this.log(`✗ Session test error: ${error.message}`, 'fail');
        }
    }

    async testRealDataManager() {
        try {
            this.log('Testing real data manager...', 'info');
            
            if (window.realDataManager) {
                this.log('✓ Real data manager loaded', 'pass');
                
                // Test cache functionality
                window.realDataManager.cache.set('test', { data: 'test', timestamp: Date.now() });
                if (window.realDataManager.cache.has('test')) {
                    this.log('✓ Data manager cache working', 'pass');
                    window.realDataManager.cache.delete('test');
                } else {
                    this.log('✗ Data manager cache failed', 'fail');
                }
                
            } else {
                this.log('✗ Real data manager not loaded', 'fail');
            }
            
        } catch (error) {
            this.log(`✗ Real data manager error: ${error.message}`, 'fail');
        }
    }

    async testBalanceAPI() {
        try {
            this.log('Testing balance API...', 'info');
            
            const response = await fetch('/api/balance.php', {
                method: 'GET',
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.log('✓ Balance API responding', 'pass');
                    this.log(`  - Found ${data.data.balances ? data.data.balances.length : 0} balances`, 'info');
                } else {
                    this.log(`✗ Balance API error: ${data.error}`, 'fail');
                }
            } else {
                this.log(`✗ Balance API HTTP ${response.status}`, 'fail');
            }
            
        } catch (error) {
            this.log(`✗ Balance API error: ${error.message}`, 'fail');
        }
    }

    async testUserAPI() {
        try {
            this.log('Testing user API...', 'info');
            
            const response = await fetch('/api/user.php', {
                method: 'GET',
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.log('✓ User API responding', 'pass');
                    if (data.data.user) {
                        this.log(`  - User: ${data.data.user.username || 'Unknown'}`, 'info');
                    }
                } else {
                    this.log(`✗ User API error: ${data.error}`, 'fail');
                }
            } else {
                this.log(`✗ User API HTTP ${response.status}`, 'fail');
            }
            
        } catch (error) {
            this.log(`✗ User API error: ${error.message}`, 'fail');
        }
    }

    async testNotifications() {
        try {
            this.log('Testing notifications API...', 'info');
            
            const response = await fetch('/get-notifications.php', {
                method: 'GET',
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.log('✓ Notifications API responding', 'pass');
                    this.log(`  - Found ${data.notifications ? data.notifications.length : 0} notifications`, 'info');
                } else {
                    this.log(`✗ Notifications API error: ${data.error}`, 'fail');
                }
            } else {
                this.log(`✗ Notifications API HTTP ${response.status}`, 'fail');
            }
            
        } catch (error) {
            this.log(`✗ Notifications API error: ${error.message}`, 'fail');
        }
    }

    async testAuthentication() {
        try {
            this.log('Testing authentication...', 'info');
            
            const response = await fetch('/check-session.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.authenticated) {
                    this.log('✓ User authenticated', 'pass');
                    this.log(`  - User ID: ${data.user_id || 'Unknown'}`, 'info');
                } else {
                    this.log('! User not authenticated', 'warn');
                    this.log(`  - Reason: ${data.reason || 'Unknown'}`, 'info');
                }
            } else {
                this.log(`✗ Auth check HTTP ${response.status}`, 'fail');
            }
            
        } catch (error) {
            this.log(`✗ Authentication error: ${error.message}`, 'fail');
        }
    }

    displayResults() {
        const passed = this.results.filter(r => r.status === 'pass').length;
        const failed = this.results.filter(r => r.status === 'fail').length;
        const warned = this.results.filter(r => r.status === 'warn').length;
        
        this.log('', 'info'); // Empty line
        this.log(`Test Summary: ${passed} passed, ${failed} failed, ${warned} warnings`, 'info');
        
        if (failed === 0) {
            this.log('🎉 All critical tests passed!', 'pass');
        } else {
            this.log('⚠️ Some tests failed - check configuration', 'warn');
        }
    }
}

// Auto-run tests when script is loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => new PlatformTester().runAllTests(), 1000);
    });
} else {
    setTimeout(() => new PlatformTester().runAllTests(), 1000);
}

// Expose for manual testing
window.PlatformTester = PlatformTester;