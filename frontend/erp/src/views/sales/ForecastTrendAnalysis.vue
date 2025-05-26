<!-- src/views/sales/ForecastTrendAnalysis.vue -->
<template>
    <div class="dashboard-container">
        <!-- Header Section -->
        <div class="header-section">
            <h1 class="header-title">Forecast Trend Analysis</h1>
            <p class="header-subtitle">Analyze forecast changes across different issue dates</p>
            
            <div class="item-info" v-if="itemInfo">
                <div class="info-item">
                    <span class="info-label">Item Code</span>
                    <span class="info-value">{{ itemInfo.item_code }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Item Name</span>
                    <span class="info-value">{{ itemInfo.item_name }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Customer</span>
                    <span class="info-value">{{ itemInfo.customer_name }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Period Range</span>
                    <span class="info-value">{{ formatPeriodRange() }}</span>
                </div>
            </div>
        </div>
        
        <!-- Filters Section -->
        <div class="filters-section">
            <div class="filter-row">
                <div class="filter-group">
                    <label class="filter-label">Item</label>
                    <select v-model="filters.item_id" class="filter-input" @change="loadItemData">
                        <option v-for="item in items" :key="item.item_id" :value="item.item_id">
                            {{ item.item_code }} - {{ item.name }}
                        </option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Customer</label>
                    <select v-model="filters.customer_id" class="filter-input" @change="loadTrendData">
                        <option v-for="customer in customers" :key="customer.customer_id" :value="customer.customer_id">
                            {{ customer.name }}
                        </option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Start Period</label>
                    <input type="month" v-model="filters.start_period" class="filter-input" @change="loadTrendData">
                </div>
                <div class="filter-group">
                    <label class="filter-label">End Period</label>
                    <input type="month" v-model="filters.end_period" class="filter-input" @change="loadTrendData">
                </div>
                <button @click="loadTrendData" class="btn-analyze" :disabled="loading">
                    {{ loading ? '⏳ Loading...' : '🔍 Analyze Trend' }}
                </button>
            </div>
        </div>
        
        <!-- Loading State -->
        <div v-if="loading" class="loading-overlay">
            <div class="spinner"></div>
            <p>Loading trend analysis...</p>
        </div>
        
        <!-- Main Content -->
        <div v-else class="main-content">
            <div class="export-actions">
                <button @click="exportToExcel" class="btn-export">📊 Export to Excel</button>
                <button @click="exportToCSV" class="btn-export">📋 Export to CSV</button>
            </div>
            
            <!-- Volatile Periods Alert -->
            <div v-if="statistics.volatile_periods && statistics.volatile_periods.length > 0" class="volatile-alert">
                <div class="alert-title">⚠️ High Volatility Detected</div>
                <ul class="volatile-list">
                    <li v-for="period in statistics.volatile_periods" :key="period.period" class="volatile-item">
                        • {{ formatPeriod(period.period) }}: {{ period.change > 0 ? '+' : '' }}{{ period.change.toFixed(1) }}% change
                    </li>
                </ul>
            </div>
            
            <!-- Trend Table -->
            <div v-if="trendData.length > 0" class="trend-table-container">
                <table class="trend-table">
                    <thead>
                        <tr>
                            <th rowspan="2">Period</th>
                            <th :colspan="issueDates.length">Forecast Issue Dates</th>
                            <th rowspan="2">Total Change %</th>
                        </tr>
                        <tr>
                            <th v-for="issueDate in issueDates" :key="issueDate">
                                {{ formatDate(issueDate) }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="period in trendData" :key="period.period">
                            <td class="period-cell">{{ formatPeriod(period.period) }}</td>
                            <td 
                                v-for="issueDate in issueDates" 
                                :key="issueDate"
                                :class="getCellClass(period.forecasts[issueDate])"
                            >
                                <template v-if="period.forecasts[issueDate] && period.forecasts[issueDate].quantity !== null">
                                    {{ period.forecasts[issueDate].quantity }}
                                    <span 
                                        v-if="period.forecasts[issueDate].percentage_change !== null" 
                                        class="change-indicator"
                                    >
                                        {{ period.forecasts[issueDate].percentage_change > 0 ? '+' : '' }}{{ period.forecasts[issueDate].percentage_change.toFixed(1) }}%
                                    </span>
                                </template>
                                <template v-else>
                                    -
                                </template>
                            </td>
                            <td :class="getPeriodChangeClass(period)">
                                {{ getPeriodTotalChange(period) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Statistics Grid -->
            <div v-if="statistics" class="statistics-grid">
                <div class="stat-card">
                    <div class="stat-title">Total Forecasts</div>
                    <div class="stat-value">{{ statistics.total_forecasts || 0 }}</div>
                    <div class="stat-subtitle">Across all periods</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-title">Average Quantity</div>
                    <div class="stat-value">{{ Math.round(statistics.average_quantity || 0) }}</div>
                    <div class="stat-subtitle">Per forecast</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-title">Max Increase</div>
                    <div class="stat-value" style="color: #28a745;">
                        +{{ (statistics.max_percentage_increase || 0).toFixed(1) }}%
                    </div>
                    <div class="stat-subtitle">Single period</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-title">Max Decrease</div>
                    <div class="stat-value" style="color: #dc3545;">
                        {{ (statistics.max_percentage_decrease || 0).toFixed(1) }}%
                    </div>
                    <div class="stat-subtitle">Single period</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-title">Volatile Periods</div>
                    <div class="stat-value" style="color: #ffc107;">{{ (statistics.volatile_periods || []).length }}</div>
                    <div class="stat-subtitle">Changes > 20%</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-title">Avg Change</div>
                    <div class="stat-value">{{ (statistics.average_percentage_change || 0).toFixed(1) }}%</div>
                    <div class="stat-subtitle">Overall trend</div>
                </div>
            </div>
            
            <!-- Empty State -->
            <div v-if="trendData.length === 0 && !loading" class="empty-state">
                <p>No trend data found for the selected criteria</p>
                <button @click="loadTrendData" class="btn-analyze">Try Different Filters</button>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'ForecastTrendAnalysis',
    data() {
        return {
            loading: false,
            trendData: [],
            issueDates: [],
            periodSummary: {},
            statistics: {},
            itemInfo: null,
            items: [],
            customers: [],
            filters: {
                item_id: null,
                customer_id: null,
                start_period: '2024-01',
                end_period: '2024-12',
                show_percentage_change: true
            }
        };
    },
    mounted() {
        // Get query parameters
        const query = this.$route.query;
        if (query.item_id) this.filters.item_id = parseInt(query.item_id);
        if (query.customer_id) this.filters.customer_id = parseInt(query.customer_id);
        if (query.start_period) this.filters.start_period = query.start_period;
        if (query.end_period) this.filters.end_period = query.end_period;
        
        this.loadInitialData();
    },
    methods: {
        async loadInitialData() {
            await Promise.all([
                this.loadItems(),
                this.loadCustomers()
            ]);
            
            if (this.filters.item_id && this.filters.customer_id) {
                this.loadTrendData();
            }
        },
        
        async loadItems() {
            try {
                const response = await axios.get('/items', {
                    params: { per_page: 1000 }
                });
                this.items = response.data.data || response.data;
            } catch (error) {
                console.error('Error loading items:', error);
            }
        },
        
        async loadCustomers() {
            try {
                const response = await axios.get('/sales/customers');
                this.customers = response.data.data || response.data;
            } catch (error) {
                console.error('Error loading customers:', error);
            }
        },
        
        async loadItemData() {
            if (this.filters.item_id && this.filters.customer_id) {
                this.loadTrendData();
            }
        },
        
        async loadTrendData() {
            if (!this.filters.item_id || !this.filters.customer_id) {
                return;
            }
            
            this.loading = true;
            try {
                const params = {
                    item_id: this.filters.item_id,
                    customer_id: this.filters.customer_id,
                    start_period: this.filters.start_period + '-01',
                    end_period: this.filters.end_period + '-01',
                    show_percentage_change: this.filters.show_percentage_change
                };
                
                const response = await axios.get('/forecasts/trend', { params });
                const data = response.data.data;
                
                this.trendData = data.trend_data || [];
                this.issueDates = data.issue_dates || [];
                this.periodSummary = data.period_summary || {};
                this.statistics = data.statistics || {};
                this.itemInfo = data.item_info || null;
                
            } catch (error) {
                console.error('Error loading trend data:', error);
                this.$toast?.error?.('Failed to load trend analysis');
            } finally {
                this.loading = false;
            }
        },
        
        getCellClass(forecast) {
            if (!forecast || forecast.quantity === null) {
                return 'no-data';
            }
            
            const classes = ['quantity-cell'];
            
            if (forecast.percentage_change !== null) {
                if (forecast.percentage_change > 0) {
                    classes.push('change-positive');
                } else if (forecast.percentage_change < 0) {
                    classes.push('change-negative');
                } else {
                    classes.push('change-neutral');
                }
                
                if (Math.abs(forecast.percentage_change) >= 50) {
                    classes.push('change-high');
                }
            }
            
            return classes.join(' ');
        },
        
        getPeriodChangeClass(period) {
            const totalChange = this.getPeriodTotalChangeValue(period);
            const classes = [];
            
            if (totalChange > 0) {
                classes.push('change-positive');
            } else if (totalChange < 0) {
                classes.push('change-negative');
            } else {
                classes.push('change-neutral');
            }
            
            if (Math.abs(totalChange) >= 50) {
                classes.push('change-high');
            }
            
            return classes.join(' ');
        },
        
        getPeriodTotalChange(period) {
            const totalChange = this.getPeriodTotalChangeValue(period);
            return totalChange !== null ? `${totalChange > 0 ? '+' : ''}${totalChange.toFixed(1)}%` : '0.0%';
        },
        
        getPeriodTotalChangeValue(period) {
            const forecasts = Object.values(period.forecasts).filter(f => f && f.quantity !== null);
            if (forecasts.length < 2) return 0;
            
            const firstQuantity = forecasts[0].quantity;
            const lastQuantity = forecasts[forecasts.length - 1].quantity;
            
            if (firstQuantity > 0) {
                return ((lastQuantity - firstQuantity) / firstQuantity) * 100;
            }
            return 0;
        },
        
        formatPeriod(period) {
            const [year, month] = period.split('-');
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return `${months[parseInt(month) - 1]} ${year}`;
        },
        
        formatDate(dateStr) {
            const date = new Date(dateStr);
            const month = date.toLocaleString('default', { month: 'short' });
            const day = date.getDate();
            return `${month} ${day}`;
        },
        
        formatPeriodRange() {
            if (!this.filters.start_period || !this.filters.end_period) return '';
            return `${this.formatPeriod(this.filters.start_period)} - ${this.formatPeriod(this.filters.end_period)}`;
        },
        
        exportToExcel() {
            // Implementation for Excel export
            this.$toast?.info?.('Excel export feature will be implemented');
        },
        
        exportToCSV() {
            // Implementation for CSV export
            const csvContent = this.generateCSVContent();
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `forecast_trend_${this.itemInfo?.item_code}_${Date.now()}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },
        
        generateCSVContent() {
            const headers = ['Period', ...this.issueDates.map(date => this.formatDate(date)), 'Total Change %'];
            const rows = [headers.join(',')];
            
            this.trendData.forEach(period => {
                const row = [
                    this.formatPeriod(period.period),
                    ...this.issueDates.map(date => {
                        const forecast = period.forecasts[date];
                        return forecast && forecast.quantity !== null ? forecast.quantity : '-';
                    }),
                    this.getPeriodTotalChange(period)
                ];
                rows.push(row.join(','));
            });
            
            return rows.join('\n');
        }
    }
};
</script>

<style scoped>
.dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 24px;
}

.header-title {
    font-size: 28px;
    font-weight: 600;
    margin-bottom: 8px;
}

.header-subtitle {
    font-size: 16px;
    opacity: 0.9;
}

.item-info {
    display: flex;
    gap: 30px;
    margin-top: 16px;
    flex-wrap: wrap;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-label {
    font-size: 12px;
    opacity: 0.8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-size: 16px;
    font-weight: 500;
    margin-top: 4px;
}

.filters-section {
    padding: 20px 24px;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}

.filter-row {
    display: flex;
    gap: 20px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.filter-label {
    font-size: 12px;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
}

.filter-input {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 14px;
    min-width: 120px;
}

.btn-analyze {
    background: #007bff;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    margin-top: 18px;
    transition: background 0.3s ease;
}

.btn-analyze:hover:not(:disabled) {
    background: #0056b3;
}

.btn-analyze:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.main-content {
    padding: 24px;
}

.export-actions {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    justify-content: flex-end;
}

.btn-export {
    background: #28a745;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 12px;
    cursor: pointer;
    transition: background 0.3s ease;
}

.btn-export:hover {
    background: #218838;
}

.volatile-alert {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 8px;
    padding: 16px;
    margin: 20px 0;
}

.alert-title {
    font-weight: 600;
    color: #856404;
    margin-bottom: 8px;
}

.volatile-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.volatile-item {
    padding: 4px 0;
    color: #856404;
}

.trend-table-container {
    overflow-x: auto;
    margin-bottom: 30px;
}

.trend-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
    min-width: 800px;
}

.trend-table th {
    background: #f8f9fa;
    padding: 12px 8px;
    text-align: center;
    font-weight: 600;
    border: 1px solid #dee2e6;
    position: sticky;
    top: 0;
    z-index: 10;
}

.trend-table td {
    padding: 10px 8px;
    text-align: center;
    border: 1px solid #dee2e6;
    position: relative;
}

.period-cell {
    font-weight: 600;
    background: #e9ecef;
    text-align: left;
    padding-left: 16px;
    min-width: 120px;
}

.quantity-cell {
    font-weight: 500;
}

.change-positive {
    background-color: #d1e7dd;
    color: #0f5132;
}

.change-negative {
    background-color: #f8d7da;
    color: #842029;
}

.change-neutral {
    background-color: #f8f9fa;
    color: #6c757d;
}

.change-high {
    font-weight: 600;
    border: 2px solid currentColor;
}

.no-data {
    background-color: #f8f9fa;
    color: #adb5bd;
    font-style: italic;
}

.change-indicator {
    font-size: 11px;
    display: block;
    margin-top: 2px;
}

.statistics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.stat-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-title {
    font-size: 14px;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
}

.stat-value {
    font-size: 24px;
    font-weight: 600;
    color: #212529;
}

.stat-subtitle {
    font-size: 12px;
    color: #6c757d;
    margin-top: 4px;
}

.loading-overlay {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 60px 20px;
    background: rgba(255, 255, 255, 0.8);
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
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

@media (max-width: 768px) {
    .header-section {
        padding: 20px;
    }
    
    .header-title {
        font-size: 24px;
    }
    
    .item-info {
        gap: 20px;
    }
    
    .filter-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .trend-table {
        font-size: 12px;
    }
    
    .statistics-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .statistics-grid {
        grid-template-columns: 1fr;
    }
    
    .item-info {
        flex-direction: column;
        gap: 12px;
    }
    
    .export-actions {
        flex-direction: column;
    }
}
</style>