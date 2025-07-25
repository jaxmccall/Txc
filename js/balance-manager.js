// Balance Manager - handles user balance information
class BalanceManager {
    constructor() {
        this.balances = {};
        this.totalBalance = 0;
        this.init();
    }

    async init() {
        await this.loadBalances();
        this.updateBalanceUI();
    }

    async loadBalances() {
        try {
            const response = await fetch('get-user-assets.php');
            const data = await response.json();
            
            if (data.success && data.balances) {
                this.balances = data.balances;
                this.calculateTotalBalance();
            } else {
                // Set default balances if none exist
                this.balances = {
                    'USDT': { balance: 0, locked_balance: 0, symbol: 'USDT' },
                    'BTC': { balance: 0, locked_balance: 0, symbol: 'BTC' },
                    'ETH': { balance: 0, locked_balance: 0, symbol: 'ETH' }
                };
                this.totalBalance = 0;
            }
        } catch (error) {
            console.error('Failed to load balances:', error);
            this.balances = {};
            this.totalBalance = 0;
        }
    }

    calculateTotalBalance() {
        // This would normally fetch current prices and calculate USD value
        // For now, we'll assume USDT = 1 USD and others = 0 for simplicity
        this.totalBalance = Object.values(this.balances).reduce((total, balance) => {
            if (balance.symbol === 'USDT') {
                return total + parseFloat(balance.balance || 0);
            }
            return total;
        }, 0);
    }

    updateBalanceUI() {
        // Update balance displays
        const balanceElements = document.querySelectorAll('[data-balance]');
        balanceElements.forEach(el => {
            const asset = el.getAttribute('data-balance');
            if (this.balances[asset]) {
                el.textContent = this.formatBalance(this.balances[asset].balance, asset);
            }
        });

        // Update total balance displays
        const totalBalanceElements = document.querySelectorAll('[data-total-balance]');
        totalBalanceElements.forEach(el => {
            el.textContent = this.formatCurrency(this.totalBalance);
        });
    }

    formatBalance(amount, symbol) {
        const num = parseFloat(amount || 0);
        if (symbol === 'USDT') {
            return num.toFixed(2);
        }
        return num.toFixed(6);
    }

    formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(parseFloat(amount || 0));
    }

    getBalance(symbol) {
        return this.balances[symbol] ? parseFloat(this.balances[symbol].balance || 0) : 0;
    }

    getAvailableBalance(symbol) {
        if (!this.balances[symbol]) return 0;
        const total = parseFloat(this.balances[symbol].balance || 0);
        const locked = parseFloat(this.balances[symbol].locked_balance || 0);
        return Math.max(0, total - locked);
    }

    hasBalance(symbol, amount) {
        return this.getAvailableBalance(symbol) >= parseFloat(amount || 0);
    }

    getTotalBalance() {
        return this.totalBalance;
    }

    async refreshBalances() {
        await this.loadBalances();
        this.updateBalanceUI();
    }

    // Add balance to a specific asset
    addBalance(symbol, amount) {
        if (!this.balances[symbol]) {
            this.balances[symbol] = { balance: 0, locked_balance: 0, symbol: symbol };
        }
        
        const currentBalance = parseFloat(this.balances[symbol].balance || 0);
        const newBalance = currentBalance + parseFloat(amount);
        this.balances[symbol].balance = newBalance.toString();
        
        this.calculateTotalBalance();
        this.updateBalanceUI();
        
        // In a real app, this would also update the server
        console.log(`Added ${amount} ${symbol} to balance. New balance: ${newBalance}`);
    }

    // Subtract balance from a specific asset
    subtractBalance(symbol, amount) {
        if (!this.balances[symbol]) return false;
        
        const currentBalance = parseFloat(this.balances[symbol].balance || 0);
        const amountToSubtract = parseFloat(amount);
        
        if (currentBalance < amountToSubtract) return false;
        
        const newBalance = currentBalance - amountToSubtract;
        this.balances[symbol].balance = newBalance.toString();
        
        this.calculateTotalBalance();
        this.updateBalanceUI();
        
        console.log(`Subtracted ${amount} ${symbol} from balance. New balance: ${newBalance}`);
        return true;
    }
}

// Create global balance manager instance
window.balanceManager = new BalanceManager();