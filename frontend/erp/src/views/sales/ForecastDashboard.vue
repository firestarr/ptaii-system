<!-- src/views/sales/ForecastDashboard.vue -->
<template>
  <div class="page-container">
    <div class="page-header">
      <h2>Sales Forecast Dashboard</h2>
      <p class="text-muted">Overview of forecast metrics and performance</p>
    </div>

    <!-- Filters Row -->
    <div class="card mb-4">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <div class="form-group">
              <label class="form-label">Period Range</label>
              <select v-model="filters.periodRange" class="form-select" @change="applyFilters">
                <option value="3">3 Months</option>
                <option value="6">6 Months</option>
                <option value="12">12 Months</option>
              </select>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label class="form-label">Customer</label>
              <select v-model="filters.customerId" class="form-select" @change="applyFilters">
                <option value="">All Customers</option>
                <option v-for="customer in customers" :key="customer.customer_id" :value="customer.customer_id">
                  {{ customer.name }}
                </option>
              </select>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label class="form-label">Forecast Source</label>
              <select v-model="filters.forecastSource" class="form-select" @change="applyFilters">
                <option value="">All Sources</option>
                <option value="Customer">Customer</option>
                <option value="System-Trend">System-Trend</option>
                <option value="System-Weighted">System-Weighted</option>
                <option value="System-Average">System-Average</option>
                <option value="System-Manual">System-Manual</option>
              </select>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label class="form-label">Date Type</label>
              <select v-model="filters.dateType" class="form-select" @change="applyFilters">
                <option value="forecast_period">Forecast Period</option>
                <option value="forecast_issue_date">Issue Date</option>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="loading" class="text-center py-5">
      <i class="fas fa-spinner fa-spin fa-2x"></i>
      <p class="mt-2">Loading dashboard data...</p>
    </div>

    <template v-else>
      <!-- Summary Cards Row -->
      <div class="row mb-4">
        <div class="col-md-3">
          <div class="card h-100">
            <div class="card-body">
              <h6 class="card-subtitle mb-2 text-muted">Total Forecasts</h6>
              <h2 class="card-title">{{ dashboardData.totalForecasts || 0 }}</h2>
              <div class="progress mt-2" style="height: 5px;">
                <div class="progress-bar bg-primary" style="width: 100%"></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card h-100">
            <div class="card-body">
              <h6 class="card-subtitle mb-2 text-muted">Average Accuracy</h6>
              <h2 class="card-title">{{ dashboardData.averageAccuracy || 0 }}%</h2>
              <div
                class="progress mt-2"
                style="height: 5px;"
                v-if="dashboardData.averageAccuracy"
              >
                <div
                  class="progress-bar"
                  :class="getAccuracyBarClass(dashboardData.averageAccuracy)"
                  :style="`width: ${Math.min(dashboardData.averageAccuracy, 100)}%`"
                ></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card h-100">
            <div class="card-body">
              <h6 class="card-subtitle mb-2 text-muted">Forecasted Units</h6>
              <h2 class="card-title">{{ formatNumber(dashboardData.totalForecastQuantity) }}</h2>
              <p
                v-if="dashboardData.forecastVsLastPeriod"
                class="mt-2"
                :class="{
                  'text-success': dashboardData.forecastVsLastPeriod > 0,
                  'text-danger': dashboardData.forecastVsLastPeriod < 0,
                }"
              >
                <i
                  class="fas"
                  :class="{
                    'fa-arrow-up': dashboardData.forecastVsLastPeriod > 0,
                    'fa-arrow-down': dashboardData.forecastVsLastPeriod < 0,
                    'fa-arrows-left-right': dashboardData.forecastVsLastPeriod === 0,
                  }"
                ></i>
                {{ Math.abs(dashboardData.forecastVsLastPeriod) }}% vs previous
              </p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card h-100">
            <div class="card-body">
              <h6 class="card-subtitle mb-2 text-muted">Actual Units</h6>
              <h2 class="card-title">{{ formatNumber(dashboardData.totalActualQuantity) }}</h2>
              <p
                v-if="dashboardData.actualVsForecast"
                class="mt-2"
                :class="{
                  'text-success': dashboardData.actualVsForecast > 0,
                  'text-danger': dashboardData.actualVsForecast < 0,
                }"
              >
                <i
                  class="fas"
                  :class="{
                    'fa-arrow-up': dashboardData.actualVsForecast > 0,
                    'fa-arrow-down': dashboardData.actualVsForecast < 0,
                    'fa-arrows-left-right': dashboardData.actualVsForecast === 0,
                  }"
                ></i>
                {{ Math.abs(dashboardData.actualVsForecast) }}% vs forecast
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Forecast vs Actual Chart -->
      <div class="row mb-4">
        <div class="col-md-8">
          <div class="card h-100">
            <div class="card-header">
              <h5 class="mb-0">Forecast vs Actual</h5>
            </div>
            <div class="card-body">
              <div v-if="!dashboardData.timeSeriesData || dashboardData.timeSeriesData.length === 0" class="text-center py-5">
                <p class="text-muted">No data available for the selected filters</p>
              </div>
              <div v-else class="forecast-chart-container">
                <canvas ref="forecastVsActualChart"></canvas>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header">
              <h5 class="mb-0">Forecast by Source</h5>
            </div>
            <div class="card-body">
              <div v-if="!dashboardData.sourceBreakdown || dashboardData.sourceBreakdown.length === 0" class="text-center py-5">
                <p class="text-muted">No data available for the selected filters</p>
              </div>
              <div v-else class="source-chart-container">
                <canvas ref="forecastSourceChart"></canvas>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Top Items and Top Customers -->
      <div class="row mb-4">
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h5 class="mb-0">Top 10 Forecasted Items</h5>
              <div class="btn-group btn-group-sm">
                <button
                  @click="sortTopItems('quantity')"
                  class="btn"
                  :class="itemsSortBy === 'quantity' ? 'btn-primary' : 'btn-outline-primary'"
                >
                  Quantity
                </button>
                <button
                  @click="sortTopItems('accuracy')"
                  class="btn"
                  :class="itemsSortBy === 'accuracy' ? 'btn-primary' : 'btn-outline-primary'"
                >
                  Accuracy
                </button>
              </div>
            </div>
            <div class="card-body p-0">
              <div v-if="!dashboardData.topItems || dashboardData.topItems.length === 0" class="text-center py-5">
                <p class="text-muted">No data available for the selected filters</p>
              </div>
              <div v-else class="table-responsive">
                <table class="table table-hover table-borderless mb-0">
                  <thead>
                    <tr>
                      <th>Item</th>
                      <th class="text-end">Forecast Qty</th>
                      <th class="text-end">Actual Qty</th>
                      <th class="text-end">Accuracy</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in sortedTopItems.slice(0, 10)" :key="item.item_id">
                      <td>
                        <div class="d-flex flex-column">
                          <span>{{ item.item_code }}</span>
                          <small class="text-muted">{{ item.item_name }}</small>
                        </div>
                      </td>
                      <td class="text-end">{{ formatNumber(item.forecast_quantity) }}</td>
                      <td class="text-end">{{ formatNumber(item.actual_quantity) }}</td>
                      <td class="text-end">
                        <span 
                          :class="{
                            'text-success': item.accuracy >= 90,
                            'text-warning': item.accuracy >= 70 && item.accuracy < 90,
                            'text-danger': item.accuracy < 70
                          }"
                        >
                          {{ item.accuracy }}%
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h5 class="mb-0">Top 10 Customers</h5>
              <div class="btn-group btn-group-sm">
                <button
                  @click="sortTopCustomers('quantity')"
                  class="btn"
                  :class="customersSortBy === 'quantity' ? 'btn-primary' : 'btn-outline-primary'"
                >
                  Quantity
                </button>
                <button
                  @click="sortTopCustomers('accuracy')"
                  class="btn"
                  :class="customersSortBy === 'accuracy' ? 'btn-primary' : 'btn-outline-primary'"
                >
                  Accuracy
                </button>
              </div>
            </div>
            <div class="card-body p-0">
              <div v-if="!dashboardData.topCustomers || dashboardData.topCustomers.length === 0" class="text-center py-5">
                <p class="text-muted">No data available for the selected filters</p>
              </div>
              <div v-else class="table-responsive">
                <table class="table table-hover table-borderless mb-0">
                  <thead>
                    <tr>
                      <th>Customer</th>
                      <th class="text-end">Forecast Qty</th>
                      <th class="text-end">Actual Qty</th>
                      <th class="text-end">Accuracy</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="customer in sortedTopCustomers.slice(0, 10)" :key="customer.customer_id">
                      <td>{{ customer.customer_name }}</td>
                      <td class="text-end">{{ formatNumber(customer.forecast_quantity) }}</td>
                      <td class="text-end">{{ formatNumber(customer.actual_quantity) }}</td>
                      <td class="text-end">
                        <span 
                          :class="{
                            'text-success': customer.accuracy >= 90,
                            'text-warning': customer.accuracy >= 70 && customer.accuracy < 90,
                            'text-danger': customer.accuracy < 70
                          }"
                        >
                          {{ customer.accuracy }}%
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Forecast Accuracy History -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Forecast Accuracy Trend</h5>
        </div>
        <div class="card-body">
          <div v-if="!dashboardData.accuracyTrend || dashboardData.accuracyTrend.length === 0" class="text-center py-5">
            <p class="text-muted">No trend data available for the selected filters</p>
          </div>
          <div v-else class="accuracy-chart-container">
            <canvas ref="accuracyTrendChart"></canvas>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import axios from 'axios';
import Chart from 'chart.js/auto';

export default {
  name: 'ForecastDashboard',
  data() {
    return {
      loading: true,
      customers: [],
      filters: {
        periodRange: '6',
        customerId: '',
        forecastSource: '',
        dateType: 'forecast_period'
      },
      dashboardData: {
        totalForecasts: 0,
        averageAccuracy: 0,
        totalForecastQuantity: 0,
        totalActualQuantity: 0,
        forecastVsLastPeriod: 0,
        actualVsForecast: 0,
        timeSeriesData: [],
        sourceBreakdown: [],
        topItems: [],
        topCustomers: [],
        accuracyTrend: []
      },
      itemsSortBy: 'quantity',
      customersSortBy: 'quantity',
      charts: {
        forecastVsActual: null,
        forecastSource: null,
        accuracyTrend: null
      }
    };
  },
  computed: {
    sortedTopItems() {
      if (!this.dashboardData.topItems) return [];
      
      const items = [...this.dashboardData.topItems];
      
      if (this.itemsSortBy === 'quantity') {
        return items.sort((a, b) => b.forecast_quantity - a.forecast_quantity);
      } else {
        return items.sort((a, b) => b.accuracy - a.accuracy);
      }
    },
    sortedTopCustomers() {
      if (!this.dashboardData.topCustomers) return [];
      
      const customers = [...this.dashboardData.topCustomers];
      
      if (this.customersSortBy === 'quantity') {
        return customers.sort((a, b) => b.forecast_quantity - a.forecast_quantity);
      } else {
        return customers.sort((a, b) => b.accuracy - a.accuracy);
      }
    }
  },
  created() {
    this.fetchCustomers();
    this.fetchDashboardData();
  },
  methods: {
    formatNumber(num) {
      if (num === null || num === undefined) return '0';
      return new Intl.NumberFormat().format(Math.round(num));
    },
    getAccuracyBarClass(accuracy) {
      if (accuracy >= 90) {
        return 'bg-success';
      } else if (accuracy >= 70) {
        return 'bg-warning';
      } else {
        return 'bg-danger';
      }
    },
    async fetchCustomers() {
      try {
        const response = await axios.get('/customers');
        this.customers = response.data.data || [];
      } catch (error) {
        console.error('Error fetching customers:', error);
      }
    },
    async fetchDashboardData() {
      this.loading = true;
      
      try {
        // In a real application, this would be a dedicated dashboard API endpoint
        // For this example, we'll combine data from several endpoints
        
        const months = parseInt(this.filters.periodRange);
        const endDate = new Date();
        const startDate = new Date();
        startDate.setMonth(startDate.getMonth() - months);
        
        const startPeriod = `${startDate.getFullYear()}-${String(startDate.getMonth() + 1).padStart(2, '0')}-01`;
        const endPeriod = `${endDate.getFullYear()}-${String(endDate.getMonth() + 1).padStart(2, '0')}-01`;
        
        // Prepare common params for accuracy API
        const params = {
          start_period: startPeriod,
          end_period: endPeriod
        };
        
        if (this.filters.customerId) {
          params.customer_id = this.filters.customerId;
        }
        
        if (this.filters.forecastSource) {
          params.forecast_source = this.filters.forecastSource;
        }
        
        // Get accuracy data
        const accuracyResponse = await axios.get('/forecasts/accuracy', { params });
        const accuracyData = accuracyResponse.data.data;
        
        // Get consolidated forecast data
        const consolidatedParams = {
          start_month: startPeriod
        };
        
        if (this.filters.customerId) {
          consolidatedParams.customer_id = this.filters.customerId;
        }
        
        const consolidatedResponse = await axios.get('/forecasts/consolidated', { params: consolidatedParams });
        const consolidatedData = consolidatedResponse.data.data;
        
        // Process and transform the data for the dashboard
        this.processDashboardData(accuracyData, consolidatedData);
      } catch (error) {
        console.error('Error fetching dashboard data:', error);
      } finally {
        this.loading = false;
      }
    },
    processDashboardData(accuracyData, consolidatedData) {
      // Process accuracy data
      const dashboardData = {
        totalForecasts: accuracyData?.total_forecasts || 0,
        averageAccuracy: accuracyData ? 100 - accuracyData.mean_absolute_percentage_error : 0,
        totalForecastQuantity: accuracyData?.total_forecast_quantity || 0,
        totalActualQuantity: accuracyData?.total_actual_quantity || 0
      };
      
      // Calculate percentage differences
      if (dashboardData.totalForecastQuantity && dashboardData.totalActualQuantity) {
        dashboardData.actualVsForecast = Math.round(((dashboardData.totalActualQuantity - dashboardData.totalForecastQuantity) / dashboardData.totalForecastQuantity) * 100);
      }
      
      // Dummy value for forecast vs last period (would come from real API)
      dashboardData.forecastVsLastPeriod = 5;
      
      // Process time series data from forecasts
      const timeSeriesData = [];
      const forecastsBySource = {};
      
      // Extract time series data from consolidated view
      if (consolidatedData && consolidatedData.length > 0) {
        // Get list of all months from the first customer's items
        const months = [];
        if (consolidatedData[0].items && consolidatedData[0].items.length > 0) {
          const firstItem = consolidatedData[0].items[0];
          firstItem.periods.forEach(period => {
            months.push(period.period);
          });
        }
        
        // Create time series data
        months.sort().forEach(month => {
          const forecastTotal = consolidatedData.reduce((total, customer) => {
            return total + customer.items.reduce((itemTotal, item) => {
              const period = item.periods.find(p => p.period === month);
              return itemTotal + (period ? period.forecast_quantity : 0);
            }, 0);
          }, 0);
          
          const actualTotal = consolidatedData.reduce((total, customer) => {
            return total + customer.items.reduce((itemTotal, item) => {
              const period = item.periods.find(p => p.period === month);
              return itemTotal + (period && period.actual_quantity ? period.actual_quantity : 0);
            }, 0);
          }, 0);
          
          timeSeriesData.push({
            period: month,
            forecast: forecastTotal,
            actual: actualTotal
          });
          
          // Collect data by source
          consolidatedData.forEach(customer => {
            customer.items.forEach(item => {
              const period = item.periods.find(p => p.period === month);
              if (period && period.source) {
                if (!forecastsBySource[period.source]) {
                  forecastsBySource[period.source] = 0;
                }
                forecastsBySource[period.source] += period.forecast_quantity;
              }
            });
          });
        });
        
        dashboardData.timeSeriesData = timeSeriesData;
        
        // Convert source breakdown to array
        dashboardData.sourceBreakdown = Object.entries(forecastsBySource).map(([source, quantity]) => ({
          source,
          quantity
        }));
      }
      
      // Process top items and customers
      if (consolidatedData && consolidatedData.length > 0) {
        const itemsMap = new Map();
        const customersMap = new Map();
        
        // Collect data for each item and customer
        consolidatedData.forEach(customer => {
          let customerForecastTotal = 0;
          let customerActualTotal = 0;
          
          customer.items.forEach(item => {
            let itemForecastTotal = 0;
            let itemActualTotal = 0;
            
            item.periods.forEach(period => {
              itemForecastTotal += period.forecast_quantity || 0;
              itemActualTotal += period.actual_quantity || 0;
            });
            
            // Add to items map
            itemsMap.set(item.item_id, {
              item_id: item.item_id,
              item_code: item.item_code,
              item_name: item.item_name,
              forecast_quantity: itemForecastTotal,
              actual_quantity: itemActualTotal,
              accuracy: itemActualTotal > 0 ? 
                Math.round(100 - Math.min(Math.abs((itemForecastTotal - itemActualTotal) / itemActualTotal * 100), 100)) : 0
            });
            
            customerForecastTotal += itemForecastTotal;
            customerActualTotal += itemActualTotal;
          });
          
          // Add to customers map
          customersMap.set(customer.customer_id, {
            customer_id: customer.customer_id,
            customer_name: customer.customer_name,
            forecast_quantity: customerForecastTotal,
            actual_quantity: customerActualTotal,
            accuracy: customerActualTotal > 0 ? 
              Math.round(100 - Math.min(Math.abs((customerForecastTotal - customerActualTotal) / customerActualTotal * 100), 100)) : 0
          });
        });
        
        dashboardData.topItems = Array.from(itemsMap.values());
        dashboardData.topCustomers = Array.from(customersMap.values());
      }
      
      // Generate dummy accuracy trend data (would come from real API)
      const accuracyTrend = [];
      const months = parseInt(this.filters.periodRange);
      const today = new Date();
      
      for (let i = months - 1; i >= 0; i--) {
        const date = new Date();
        date.setMonth(today.getMonth() - i);
        
        const monthName = date.toLocaleString('default', { month: 'short' });
        const year = date.getFullYear();
        
        accuracyTrend.push({
          period: `${monthName} ${year}`,
          accuracy: Math.round(70 + Math.random() * 20) // Random accuracy between 70-90%
        });
      }
      
      dashboardData.accuracyTrend = accuracyTrend;
      
      this.dashboardData = dashboardData;
      
      // Render charts after data is processed
      this.$nextTick(() => {
        this.renderCharts();
      });
    },
    renderCharts() {
      // Destroy existing charts
      if (this.charts.forecastVsActual) {
        this.charts.forecastVsActual.destroy();
      }
      
      if (this.charts.forecastSource) {
        this.charts.forecastSource.destroy();
      }
      
      if (this.charts.accuracyTrend) {
        this.charts.accuracyTrend.destroy();
      }
      
      // Render Forecast vs Actual Chart
      if (this.$refs.forecastVsActualChart && this.dashboardData.timeSeriesData && this.dashboardData.timeSeriesData.length > 0) {
        const ctx = this.$refs.forecastVsActualChart.getContext('2d');
        
        const labels = this.dashboardData.timeSeriesData.map(item => {
          const [year, month] = item.period.split('-');
          return `${month}/${year.substring(2)}`;
        });
        
        this.charts.forecastVsActual = new Chart(ctx, {
          type: 'bar',
          data: {
            labels: labels,
            datasets: [
              {
                label: 'Forecast',
                data: this.dashboardData.timeSeriesData.map(item => item.forecast),
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
              },
              {
                label: 'Actual',
                data: this.dashboardData.timeSeriesData.map(item => item.actual),
                backgroundColor: 'rgba(255, 99, 132, 0.5)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
              }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              y: {
                beginAtZero: true,
                title: {
                  display: true,
                  text: 'Quantity'
                }
              },
              x: {
                title: {
                  display: true,
                  text: 'Period'
                }
              }
            }
          }
        });
      }
      
      // Render Forecast Source Chart
      if (this.$refs.forecastSourceChart && this.dashboardData.sourceBreakdown && this.dashboardData.sourceBreakdown.length > 0) {
        const ctx = this.$refs.forecastSourceChart.getContext('2d');
        
        const sourceColors = {
          'Customer': 'rgba(54, 162, 235, 0.8)',
          'System-Trend': 'rgba(75, 192, 192, 0.8)',
          'System-Weighted': 'rgba(255, 159, 64, 0.8)',
          'System-Average': 'rgba(255, 205, 86, 0.8)',
          'System-Manual': 'rgba(201, 203, 207, 0.8)'
        };
        
        const defaultColor = 'rgba(153, 102, 255, 0.8)';
        
        this.charts.forecastSource = new Chart(ctx, {
          type: 'doughnut',
          data: {
            labels: this.dashboardData.sourceBreakdown.map(item => item.source),
            datasets: [
              {
                data: this.dashboardData.sourceBreakdown.map(item => item.quantity),
                backgroundColor: this.dashboardData.sourceBreakdown.map(item => sourceColors[item.source] || defaultColor),
                borderWidth: 1
              }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                position: 'right'
              }
            }
          }
        });
      }
      
      // Render Accuracy Trend Chart
      if (this.$refs.accuracyTrendChart && this.dashboardData.accuracyTrend && this.dashboardData.accuracyTrend.length > 0) {
        const ctx = this.$refs.accuracyTrendChart.getContext('2d');
        
        this.charts.accuracyTrend = new Chart(ctx, {
          type: 'line',
          data: {
            labels: this.dashboardData.accuracyTrend.map(item => item.period),
            datasets: [
              {
                label: 'Forecast Accuracy',
                data: this.dashboardData.accuracyTrend.map(item => item.accuracy),
                fill: false,
                borderColor: 'rgba(75, 192, 192, 1)',
                tension: 0.4,
                pointBackgroundColor: 'rgba(75, 192, 192, 1)',
                pointRadius: 4
              }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              y: {
                min: 0,
                max: 100,
                title: {
                  display: true,
                  text: 'Accuracy (%)'
                }
              },
              x: {
                title: {
                  display: true,
                  text: 'Period'
                }
              }
            }
          }
        });
      }
    },
    applyFilters() {
      this.fetchDashboardData();
    },
    sortTopItems(sortBy) {
      this.itemsSortBy = sortBy;
    },
    sortTopCustomers(sortBy) {
      this.customersSortBy = sortBy;
    }
  }
};
</script>

<style scoped>
/* Styling yang disempurnakan untuk ForecastDashboard.vue */

.page-container {
  padding: 2rem;
  max-width: 1600px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 2.5rem;
  border-bottom: 1px solid #eaeaea;
  padding-bottom: 1.5rem;
}

.page-header h2 {
  font-size: 1.8rem;
  margin-bottom: 0.5rem;
  color: #333;
}

.page-header .text-muted {
  font-size: 1rem;
}

/* Card styling */
.card {
  border-radius: 8px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
  border: none;
  margin-bottom: 1.5rem;
}

.card-body {
  padding: 1.5rem;
}

.card-header {
  background-color: #f8f9fa;
  border-bottom: 1px solid #eef0f2;
  padding: 1rem 1.5rem;
}

.card-header h5 {
  font-weight: 600;
  color: #333;
}

/* Filter controls dengan layout yang konsisten */
.card .row.g-3 {
  display: flex;
  flex-wrap: wrap;
  margin-right: -10px;
  margin-left: -10px;
  row-gap: normal !important;
}

/* Styling untuk column */
.card .row.g-3 .col-md-3 {
  padding-left: 10px;
  padding-right: 10px;
  margin-bottom: 0;
  width: 25%;
  flex: 0 0 25%;
  max-width: 25%;
}

.form-group {
  margin-bottom: 0;
}

.form-label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: #495057;
  font-size: 0.9rem;
}

.form-select,
.form-control {
  border-radius: 5px;
  padding: 0.5rem 0.75rem;
  border: 1px solid #ced4da;
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
  width: 100%;
  height: calc(1.5em + 0.75rem + 2px);
  font-size: 0.9rem;
}

.form-select:focus,
.form-control:focus {
  border-color: #80bdff;
  box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* Summary cards styling */
.row .col-md-3 .card {
  transition: transform 0.2s;
}

.row .col-md-3 .card:hover {
  transform: translateY(-3px);
}

.row .col-md-3 .card .card-body {
  padding: 1.25rem;
}

.row .col-md-3 .card .card-subtitle {
  font-size: 0.85rem;
  color: #6c757d;
}

.row .col-md-3 .card .card-title {
  font-size: 1.75rem;
  margin-top: 0.5rem;
  margin-bottom: 0.5rem;
  font-weight: 600;
}

/* Chart containers */
.forecast-chart-container,
.source-chart-container,
.accuracy-chart-container {
  position: relative;
  height: 300px;
  margin-top: 0.5rem;
}

/* Table styling */
.table {
  margin-bottom: 0;
}

.table thead th {
  background-color: #f8f9fa;
  color: #495057;
  font-weight: 600;
  padding: 0.85rem;
  border-bottom-width: 1px;
  font-size: 0.9rem;
}

.table tbody td {
  padding: 0.85rem;
  vertical-align: middle;
  font-size: 0.9rem;
}

.table tbody tr:hover {
  background-color: rgba(0, 0, 0, 0.02);
}

.table-hover tbody tr:hover {
  background-color: rgba(0, 0, 0, 0.04);
}

.table-borderless td,
.table-borderless th {
  border: none;
}

/* Loading and empty state */
.text-center.py-5 {
  padding-top: 3rem !important;
  padding-bottom: 3rem !important;
}

.text-center.py-5 i {
  color: #6c757d;
  margin-bottom: 1rem;
}

.text-center.py-5 p {
  color: #6c757d;
  margin-top: 0.5rem;
}

/* Button group styling */
.btn-group-sm .btn {
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
  border-radius: 0.2rem;
}

.btn-outline-primary {
  color: #007bff;
  border-color: #007bff;
}

.btn-outline-primary:hover {
  color: #fff;
  background-color: #007bff;
  border-color: #007bff;
}

.btn-primary {
  color: #fff;
  background-color: #007bff;
  border-color: #007bff;
}

/* Responsive adjustments */
@media (max-width: 991px) {
  .card .row.g-3 .col-md-3 {
    flex: 0 0 50%;
    max-width: 50%;
    margin-bottom: 1rem;
  }
  
  .row .col-md-3,
  .row .col-md-4,
  .row .col-md-6,
  .row .col-md-8 {
    margin-bottom: 1.5rem;
  }
}

@media (max-width: 768px) {
  .page-container {
    padding: 1rem;
  }
  
  .card .row.g-3 .col-md-3 {
    flex: 0 0 100%;
    max-width: 100%;
    margin-bottom: 1rem;
  }
  
  .card-body {
    padding: 1.25rem;
  }
  
  .table thead th,
  .table tbody td {
    padding: 0.6rem;
  }
  
  .forecast-chart-container,
  .source-chart-container,
  .accuracy-chart-container {
    height: 250px;
  }
}
</style>