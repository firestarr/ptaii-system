<!-- src/views/sales/ForecastVolatilityDashboard.vue -->
<template>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <h1>📊 Forecast Volatility Monitor</h1>
            <p>Monitor items with significant forecast changes across all customers</p>
        </div>
        
        <!-- Filters -->
        <div class="filters-section">
            <div class="filter-row">
                <select v-model="filters.customer_id" class="filter-input">
                    <option value="">All Customers</option>
                    <option v-for="customer in customers" :key="customer.customer_id" :value="customer.customer_id">
                        {{ customer.name }}
                    </option>
                </select>
                
                <input type="month" v-model="filters.start_period" class="filter-input" placeholder="Start Period">
                <input type="month" v-model="filters.end_period" class="filter-input" placeholder="End Period">
                <input type="number" v-model="filters.volatility_threshold" class="filter-input" placeholder="Threshold %">
                
                <button @click="refreshData" class="btn-refresh" :disabled="loading">
                    {{ loading ? '⏳ Loading...' : '🔄 Refresh Data' }}
                </button>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="card high-risk">
                <h3>{{ summaryStats.high_risk_items }}</h3>
                <p>High Risk Items</p>
            </div>
            <div class="card medium-risk">
                <h3>{{ summaryStats.medium_risk_items }}</h3>
                <p>Medium Risk Items</p>
            </div>
            <div class="card total">
                <h3>{{ summaryStats.total_volatile_items }}</h3>
                <p>Total Volatile Items</p>
            </div>
            <div class="card avg">
                <h3>{{ summaryStats.avg_volatility_overall.toFixed(1) }}%</h3>
                <p>Average Volatility</p>
            </div>
        </div>
        
        <!-- Loading State -->
        <div v-if="loading" class="loading">
            <div class="spinner"></div>
            <p>Loading volatility data...</p>
        </div>
        
        <!-- Empty State -->
        <div v-else-if="volatileItems.length === 0" class="empty-state">
            <p>No volatile items found for the selected criteria</p>
            <button @click="refreshData" class="btn-refresh">Try Different Filters</button>
        </div>
        
        <!-- Items List -->
        <div v-else class="items-container">
            <div 
                v-for="item in volatileItems" 
                :key="`${item.item_id}-${item.customer_id}`"
                :class="['item-card', `risk-${item.risk_level.toLowerCase()}`]"
                @click="showDetail(item.item_id, item.customer_id)"
            >
                <div class="item-header">
                    <div class="item-info">
                        <h3>{{ item.item_code }} - {{ item.item_name }}</h3>
                        <p>Customer: {{ item.customer_name }}</p>
                    </div>
                    <div :class="['risk-badge', item.risk_level.toLowerCase()]">
                        {{ item.risk_level }}
                    </div>
                </div>
                
                <div class="volatility-summary">
                    <div class="stat">
                        <span class="label">Volatile Periods</span>
                        <span class="value">{{ item.volatile_periods.length }}</span>
                    </div>
                    <div class="stat">
                        <span class="label">Avg Volatility</span>
                        <span class="value">{{ item.avg_volatility.toFixed(1) }}%</span>
                    </div>
                    <div class="stat">
                        <span class="label">Max Increase</span>
                        <span class="value increase">+{{ item.max_increase.toFixed(1) }}%</span>
                    </div>
                    <div class="stat">
                        <span class="label">Max Decrease</span>
                        <span class="value decrease">{{ item.max_decrease.toFixed(1) }}%</span>
                    </div>
                </div>
                
                <div v-if="item.volatile_periods.length > 0" class="volatile-periods">
                    <h4>⚠️ High Volatility Detected</h4>
                    <ul>
                        <li 
                            v-for="period in item.volatile_periods.slice(0, 4)" 
                            :key="period.period"
                            :class="period.trend.toLowerCase()"
                        >
                            • {{ formatPeriod(period.period) }}: {{ period.change_percentage > 0 ? '+' : '' }}{{ period.change_percentage }}% change 
                            ({{ period.first_quantity }} → {{ period.last_quantity }})
                        </li>
                        <li v-if="item.volatile_periods.length > 4" class="more-periods">
                            ... and {{ item.volatile_periods.length - 4 }} more periods
                        </li>
                    </ul>
                </div>
                
                <div class="action-footer">
                    <button @click.stop="showDetail(item.item_id, item.customer_id)" class="btn-detail">
                        📈 View Detailed Trend
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'ForecastVolatilityDashboard',
    data() {
        return {
            loading: false,
            volatileItems: [],
            customers: [],
            summaryStats: {
                total_volatile_items: 0,
                high_risk_items: 0,
                medium_risk_items: 0,
                avg_volatility_overall: 0
            },
            filters: {
                customer_id: '',
                start_period: '2024-01',
                end_period: '2024-12',
                volatility_threshold: 20,
                limit: 50
            }
        };
    },
    mounted() {
        this.loadCustomers();
        this.refreshData();
    },
    methods: {
async loadCustomers() {
    try {
        const response = await axios.get('/customers');
        this.customers = response.data.data || response.data;
    } catch (error) {
        console.error('Error loading customers:', error);
    }
},
        
        async refreshData() {
            this.loading = true;
            try {
                const params = {
                    start_period: this.filters.start_period + '-01',
                    end_period: this.filters.end_period + '-01',
                    volatility_threshold: this.filters.volatility_threshold,
                    limit: this.filters.limit
                };
                
                if (this.filters.customer_id) {
                    params.customer_id = this.filters.customer_id;
                }
                
                const response = await axios.get('/forecasts/volatility-summary', { params });
                
                this.volatileItems = response.data.data || [];
                this.summaryStats = response.data.summary || this.summaryStats;
                
            } catch (error) {
                console.error('Error loading volatility data:', error);
                this.$toast?.error?.('Failed to load volatility data');
            } finally {
                this.loading = false;
            }
        },
        
        showDetail(itemId, customerId) {
            this.$router.push({
                name: 'ForecastTrendAnalysis',
                query: {
                    item_id: itemId,
                    customer_id: customerId,
                    start_period: this.filters.start_period,
                    end_period: this.filters.end_period
                }
            });
        },
        
        formatPeriod(period) {
            const [year, month] = period.split('-');
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return `${months[parseInt(month) - 1]} ${year}`;
        }
    }
};
</script>

<style scoped>
.dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0;
}

.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.dashboard-header h1 {
    font-size: 32px;
    font-weight: 600;
    margin-bottom: 8px;
}

.dashboard-header p {
    font-size: 16px;
    opacity: 0.9;
}

.filters-section {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.filter-row {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-input {
    padding: 10px 12px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 14px;
    min-width: 150px;
}

.btn-refresh {
    background: #007bff;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.3s ease;
}

.btn-refresh:hover:not(:disabled) {
    background: #0056b3;
}

.btn-refresh:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.card {
    background: white;
    padding: 24px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    border-left: 4px solid #ccc;
    transition: transform 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
}

.card.high-risk {
    border-left-color: #dc3545;
}

.card.medium-risk {
    border-left-color: #ffc107;
}

.card.total {
    border-left-color: #007bff;
}

.card.avg {
    border-left-color: #6f42c1;
}

.card h3 {
    font-size: 36px;
    font-weight: 700;
    margin-bottom: 8px;
}

.card.high-risk h3 { color: #dc3545; }
.card.medium-risk h3 { color: #ffc107; }
.card.total h3 { color: #007bff; }
.card.avg h3 { color: #6f42c1; }

.card p {
    font-size: 14px;
    color: #6c757d;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.items-container {
    display: grid;
    gap: 20px;
}

.item-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    cursor: pointer;
    transition: all 0.3s ease;
    border-left: 4px solid #ccc;
}

.item-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 12px rgba(0,0,0,0.15);
}

.item-card.risk-high {
    border-left-color: #dc3545;
    background: linear-gradient(135deg, #fff 0%, #fff5f5 100%);
}

.item-card.risk-medium {
    border-left-color: #ffc107;
    background: linear-gradient(135deg, #fff 0%, #fffbf0 100%);
}

.item-card.risk-low {
    border-left-color: #28a745;
    background: linear-gradient(135deg, #fff 0%, #f8fff8 100%);
}

.item-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.item-info h3 {
    font-size: 18px;
    font-weight: 600;
    color: #212529;
    margin-bottom: 4px;
}

.item-info p {
    font-size: 14px;
    color: #6c757d;
}

.risk-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.risk-badge.high {
    background: #dc3545;
    color: white;
}

.risk-badge.medium {
    background: #ffc107;
    color: #212529;
}

.risk-badge.low {
    background: #28a745;
    color: white;
}

.volatility-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
    padding: 16px;
    background: rgba(248, 249, 250, 0.5);
    border-radius: 8px;
}

.stat {
    display: flex;
    flex-direction: column;
    text-align: center;
}

.stat .label {
    font-size: 12px;
    color: #6c757d;
    font-weight: 500;
    margin-bottom: 4px;
}

.stat .value {
    font-size: 16px;
    font-weight: 600;
    color: #212529;
}

.stat .value.increase {
    color: #28a745;
}

.stat .value.decrease {
    color: #dc3545;
}

.volatile-periods {
    margin-bottom: 20px;
}

.volatile-periods h4 {
    font-size: 14px;
    font-weight: 600;
    color: #856404;
    margin-bottom: 8px;
}

.volatile-periods ul {
    list-style: none;
    margin: 0;
    padding: 0;
}

.volatile-periods li {
    padding: 4px 0;
    font-size: 13px;
    color: #856404;
}

.volatile-periods li.increase {
    color: #155724;
}

.volatile-periods li.decrease {
    color: #721c24;
}

.volatile-periods .more-periods {
    font-style: italic;
    color: #6c757d;
}

.action-footer {
    text-align: center;
    padding-top: 16px;
    border-top: 1px solid #e9ecef;
}

.btn-detail {
    background: #007bff;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.3s ease;
}

.btn-detail:hover {
    background: #0056b3;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-state p {
    font-size: 18px;
    margin-bottom: 20px;
}

.loading {
    text-align: center;
    padding: 60px 20px;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@media (max-width: 768px) {
    .dashboard-header {
        padding: 20px;
    }
    
    .dashboard-header h1 {
        font-size: 24px;
    }
    
    .filter-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .volatility-summary {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .summary-cards {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .summary-cards {
        grid-template-columns: 1fr;
    }
    
    .volatility-summary {
        grid-template-columns: 1fr;
    }
}
</style>