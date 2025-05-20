<!-- src/views/sales/ForecastAccuracyAnalysis.vue -->
<template>
  <div class="page-container">
    <div class="page-header">
      <h2>Forecast Accuracy Analysis</h2>
      <p class="text-muted">
        Analyze the accuracy of forecasts compared to actual sales
      </p>
    </div>

    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0">Analysis Parameters</h5>
      </div>
      <div class="card-body">
        <form @submit.prevent="analyzeForecastAccuracy">
          <div class="row">
            <div class="col-md-6 col-lg-3">
              <div class="mb-3">
                <label class="form-label">Start Period</label>
                <input
                  type="month"
                  class="form-control"
                  v-model="analysisParams.start_period"
                  required
                />
              </div>
            </div>
            <div class="col-md-6 col-lg-3">
              <div class="mb-3">
                <label class="form-label">End Period</label>
                <input
                  type="month"
                  class="form-control"
                  v-model="analysisParams.end_period"
                  required
                />
              </div>
            </div>
            <div class="col-md-6 col-lg-3">
              <div class="mb-3">
                <label class="form-label">Customer (Optional)</label>
                <select class="form-select" v-model="analysisParams.customer_id">
                  <option value="">All Customers</option>
                  <option
                    v-for="customer in customers"
                    :key="customer.customer_id"
                    :value="customer.customer_id"
                  >
                    {{ customer.name }}
                  </option>
                </select>
              </div>
            </div>
            <div class="col-md-6 col-lg-3">
              <div class="mb-3">
                <label class="form-label">Item (Optional)</label>
                <select class="form-select" v-model="analysisParams.item_id">
                  <option value="">All Items</option>
                  <option
                    v-for="item in items"
                    :key="item.item_id"
                    :value="item.item_id"
                  >
                    {{ item.item_code }} - {{ item.name }}
                  </option>
                </select>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 col-lg-3">
              <div class="mb-3">
                <label class="form-label">Forecast Source (Optional)</label>
                <select
                  class="form-select"
                  v-model="analysisParams.forecast_source"
                >
                  <option value="">All Sources</option>
                  <option value="Customer">Customer</option>
                  <option value="System-Trend">System-Trend</option>
                  <option value="System-Weighted">System-Weighted</option>
                  <option value="System-Average">System-Average</option>
                  <option value="System-Manual">System-Manual</option>
                </select>
              </div>
            </div>
            <div class="col-md-6 col-lg-3">
              <div class="mb-3">
                <label class="form-label">Issue Date Start (Optional)</label>
                <input
                  type="date"
                  class="form-control"
                  v-model="analysisParams.issue_date_start"
                />
              </div>
            </div>
            <div class="col-md-6 col-lg-3">
              <div class="mb-3">
                <label class="form-label">Issue Date End (Optional)</label>
                <input
                  type="date"
                  class="form-control"
                  v-model="analysisParams.issue_date_end"
                />
              </div>
            </div>
            <div class="col-md-6 col-lg-3 d-flex align-items-end">
              <div class="mb-3 w-100">
                <button
                  type="submit"
                  class="btn btn-primary w-100"
                  :disabled="analyzing"
                >
                  <i
                    v-if="analyzing"
                    class="fas fa-spinner fa-spin me-2"
                  ></i>
                  <i v-else class="fas fa-search me-2"></i>
                  Analyze Accuracy
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>

    <div v-if="analyzing" class="text-center py-5">
      <i class="fas fa-spinner fa-spin fa-2x"></i>
      <p class="mt-2">Analyzing forecast accuracy...</p>
    </div>

    <template v-else-if="accuracyData">
      <!-- Summary Cards -->
      <div class="row mb-4">
        <div class="col-md-6 col-lg-3">
          <div class="card h-100">
            <div class="card-body">
              <h6 class="card-subtitle mb-2 text-muted">Total Forecasts</h6>
              <h2 class="card-title mb-0">{{ accuracyData.total_forecasts }}</h2>
            </div>
          </div>
        </div>
        <div class="col-md-6 col-lg-3">
          <div class="card h-100">
            <div class="card-body">
              <h6 class="card-subtitle mb-2 text-muted">MAPE</h6>
              <h2 class="card-title mb-0">
                {{ accuracyData.mean_absolute_percentage_error }}%
              </h2>
              <p class="card-text text-muted">
                Mean Absolute Percentage Error
              </p>
            </div>
          </div>
        </div>
        <div class="col-md-6 col-lg-3">
          <div class="card h-100">
            <div class="card-body">
              <h6 class="card-subtitle mb-2 text-muted">Bias</h6>
              <h2 
                class="card-title mb-0"
                :class="{
                  'text-success': accuracyData.bias_percentage > -5 && accuracyData.bias_percentage < 5,
                  'text-warning': (accuracyData.bias_percentage <= -5 && accuracyData.bias_percentage > -15) || (accuracyData.bias_percentage >= 5 && accuracyData.bias_percentage < 15),
                  'text-danger': accuracyData.bias_percentage <= -15 || accuracyData.bias_percentage >= 15
                }"
              >
                {{ accuracyData.bias_percentage }}%
              </h2>
              <p class="card-text text-muted">
                {{ biasInterpretation(accuracyData.bias_percentage) }}
              </p>
            </div>
          </div>
        </div>
        <div class="col-md-6 col-lg-3">
          <div class="card h-100">
            <div class="card-body">
              <h6 class="card-subtitle mb-2 text-muted">Mean Abs. Deviation</h6>
              <h2 class="card-title mb-0">{{ accuracyData.mean_absolute_deviation }}</h2>
              <p class="card-text text-muted">
                Average absolute error in units
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Accuracy by Source -->
      <div v-if="hasSourceMetrics" class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Accuracy by Forecast Source</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-bordered table-hover">
              <thead>
                <tr>
                  <th>Source</th>
                  <th class="text-end">Count</th>
                  <th class="text-end">MAPE (%)</th>
                  <th class="text-end">Bias (%)</th>
                  <th class="text-end">MAD (units)</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(metrics, source) in accuracyData.by_source" :key="source">
                  <td>
                    <span class="badge" :class="getSourceBadgeClass(source)">{{ source }}</span>
                  </td>
                  <td class="text-end">{{ metrics.count }}</td>
                  <td class="text-end">{{ metrics.mape }}</td>
                  <td 
                    class="text-end"
                    :class="{
                      'text-success': metrics.bias > -5 && metrics.bias < 5,
                      'text-warning': (metrics.bias <= -5 && metrics.bias > -15) || (metrics.bias >= 5 && metrics.bias < 15),
                      'text-danger': metrics.bias <= -15 || metrics.bias >= 15
                    }"
                  >
                    {{ metrics.bias }}
                  </td>
                  <td class="text-end">{{ metrics.mad }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Accuracy by Issue Date -->
      <div v-if="hasIssueDateMetrics" class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Accuracy by Issue Date</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-bordered table-hover">
              <thead>
                <tr>
                  <th>Issue Date</th>
                  <th class="text-end">Count</th>
                  <th class="text-end">MAPE (%)</th>
                  <th class="text-end">Bias (%)</th>
                  <th class="text-end">MAD (units)</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(metrics, date) in accuracyData.by_issue_date" :key="date">
                  <td>{{ formatDate(date) }}</td>
                  <td class="text-end">{{ metrics.count }}</td>
                  <td class="text-end">{{ metrics.mape }}</td>
                  <td 
                    class="text-end"
                    :class="{
                      'text-success': metrics.bias > -5 && metrics.bias < 5,
                      'text-warning': (metrics.bias <= -5 && metrics.bias > -15) || (metrics.bias >= 5 && metrics.bias < 15),
                      'text-danger': metrics.bias <= -15 || metrics.bias >= 15
                    }"
                  >
                    {{ metrics.bias }}
                  </td>
                  <td class="text-end">{{ metrics.mad }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Individual Forecast Data -->
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Individual Forecast Details</h5>
          <div class="input-group" style="max-width: 300px;">
            <input
              type="text"
              class="form-control"
              placeholder="Filter forecasts..."
              v-model="forecastFilter"
            />
            <span class="input-group-text">
              <i class="fas fa-search"></i>
            </span>
          </div>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm">
              <thead>
                <tr>
                  <th>Customer</th>
                  <th>Item</th>
                  <th>Period</th>
                  <th class="text-end">Forecast</th>
                  <th class="text-end">Actual</th>
                  <th class="text-end">Variance</th>
                  <th class="text-end">% Error</th>
                  <th>Source</th>
                  <th>Confidence</th>
                  <th>Issue Date</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="forecast in filteredForecasts" :key="forecast.forecast_id">
                  <td>{{ forecast.customer?.name || 'N/A' }}</td>
                  <td>
                    <div>{{ forecast.item?.item_code || 'N/A' }}</div>
                    <small class="text-muted">{{ forecast.item?.name }}</small>
                  </td>
                  <td>{{ formatDate(forecast.forecast_period) }}</td>
                  <td class="text-end">{{ forecast.forecast_quantity }}</td>
                  <td class="text-end">{{ forecast.actual_quantity }}</td>
                  <td 
                    class="text-end"
                    :class="{
                      'text-success': forecast.variance >= 0,
                      'text-danger': forecast.variance < 0
                    }"
                  >
                    {{ forecast.variance }}
                  </td>
                  <td class="text-end">
                    {{ calculateErrorPercentage(forecast.forecast_quantity, forecast.actual_quantity) }}%
                  </td>
                  <td>
                    <span class="badge" :class="getSourceBadgeClass(forecast.forecast_source)">
                      {{ forecast.forecast_source }}
                    </span>
                  </td>
                  <td>
                    <div class="progress" style="height: 6px;">
                      <div 
                        class="progress-bar" 
                        :class="getConfidenceLevelClass(forecast.confidence_level)"
                        :style="`width: ${forecast.confidence_level * 100}%`"
                      ></div>
                    </div>
                    <small>{{ (forecast.confidence_level * 100).toFixed(0) }}%</small>
                  </td>
                  <td>{{ formatDate(forecast.forecast_issue_date) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>

    <div v-else-if="analysisError" class="alert alert-danger" role="alert">
      <i class="fas fa-exclamation-circle me-2"></i>
      {{ analysisError }}
    </div>

    <div v-else class="text-center py-5">
      <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
      <h4>No analysis data available</h4>
      <p class="text-muted">
        Select analysis parameters and click "Analyze Accuracy" to get started
      </p>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'ForecastAccuracyAnalysis',
  data() {
    return {
      // Data for dropdowns
      customers: [],
      items: [],
      
      // Analysis parameters
      analysisParams: {
        start_period: this.getLastYearMonth(6),
        end_period: this.getLastYearMonth(1),
        customer_id: '',
        item_id: '',
        forecast_source: '',
        issue_date_start: '',
        issue_date_end: ''
      },
      
      // Analysis state
      analyzing: false,
      analysisError: null,
      accuracyData: null,
      forecastFilter: ''
    };
  },
  computed: {
    hasSourceMetrics() {
      return this.accuracyData && this.accuracyData.by_source && Object.keys(this.accuracyData.by_source).length > 0;
    },
    hasIssueDateMetrics() {
      return this.accuracyData && this.accuracyData.by_issue_date && Object.keys(this.accuracyData.by_issue_date).length > 0;
    },
    filteredForecasts() {
      if (!this.accuracyData || !this.accuracyData.forecasts) {
        return [];
      }
      
      if (!this.forecastFilter) {
        return this.accuracyData.forecasts;
      }
      
      const filter = this.forecastFilter.toLowerCase();
      return this.accuracyData.forecasts.filter(forecast => {
        const customerName = forecast.customer?.name?.toLowerCase() || '';
        const itemCode = forecast.item?.item_code?.toLowerCase() || '';
        const itemName = forecast.item?.name?.toLowerCase() || '';
        const source = forecast.forecast_source?.toLowerCase() || '';
        
        return customerName.includes(filter) || 
               itemCode.includes(filter) || 
               itemName.includes(filter) || 
               source.includes(filter);
      });
    }
  },
  created() {
    this.fetchCustomers();
    this.fetchItems();
  },
  methods: {
    // Utility methods
    getLastYearMonth(monthsBack) {
      const date = new Date();
      // Go back 1 year and then to specified months back
      date.setFullYear(date.getFullYear() - 1);
      date.setMonth(date.getMonth() - monthsBack);
      return date.toISOString().substring(0, 7);
    },
    formatDate(dateString) {
      if (!dateString) return 'N/A';
      
      const date = new Date(dateString);
      return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    },
    biasInterpretation(bias) {
      if (bias > 15) {
        return 'Significantly under-forecasted';
      } else if (bias > 5) {
        return 'Under-forecasted';
      } else if (bias > -5) {
        return 'Balanced forecast';
      } else if (bias > -15) {
        return 'Over-forecasted';
      } else {
        return 'Significantly over-forecasted';
      }
    },
    getSourceBadgeClass(source) {
      if (!source) return 'bg-secondary';
      
      if (source === 'Customer') {
        return 'bg-primary';
      } else if (source.startsWith('System-Trend')) {
        return 'bg-info';
      } else if (source.startsWith('System-Weighted')) {
        return 'bg-success';
      } else if (source.startsWith('System-Average')) {
        return 'bg-warning';
      } else if (source.startsWith('System-Manual')) {
        return 'bg-secondary';
      } else {
        return 'bg-light text-dark';
      }
    },
    getConfidenceLevelClass(level) {
      if (level >= 0.8) {
        return 'bg-success';
      } else if (level >= 0.6) {
        return 'bg-info';
      } else if (level >= 0.4) {
        return 'bg-warning';
      } else {
        return 'bg-danger';
      }
    },
    calculateErrorPercentage(forecast, actual) {
      if (!actual || actual === 0) {
        return 'N/A';
      }
      
      const percentage = Math.abs((forecast - actual) / actual) * 100;
      return percentage.toFixed(2);
    },
    
    // Data fetching methods
    async fetchCustomers() {
      try {
        const response = await axios.get('/customers');
        this.customers = response.data.data || [];
      } catch (error) {
        console.error('Error fetching customers:', error);
      }
    },
    async fetchItems() {
      try {
        const response = await axios.get('/items');
        this.items = response.data.data || [];
      } catch (error) {
        console.error('Error fetching items:', error);
      }
    },
    
    // Analysis methods
    async analyzeForecastAccuracy() {
      if (!this.analysisParams.start_period || !this.analysisParams.end_period) {
        this.analysisError = 'Start and end periods are required';
        return;
      }
      
      // Validate end period is after start period
      if (this.analysisParams.end_period <= this.analysisParams.start_period) {
        this.analysisError = 'End period must be after start period';
        return;
      }
      
      this.analyzing = true;
      this.analysisError = null;
      
      try {
        // Prepare query parameters
        const params = {
          start_period: `${this.analysisParams.start_period}-01`,
          end_period: `${this.analysisParams.end_period}-01`,
        };
        
        // Add optional parameters if they exist
        if (this.analysisParams.customer_id) {
          params.customer_id = this.analysisParams.customer_id;
        }
        
        if (this.analysisParams.item_id) {
          params.item_id = this.analysisParams.item_id;
        }
        
        if (this.analysisParams.forecast_source) {
          params.forecast_source = this.analysisParams.forecast_source;
        }
        
        if (this.analysisParams.issue_date_start) {
          params.issue_date_start = this.analysisParams.issue_date_start;
        }
        
        if (this.analysisParams.issue_date_end) {
          params.issue_date_end = this.analysisParams.issue_date_end;
        }
        
        const response = await axios.get('/forecasts/accuracy', { params });
        
        if (response.data.data) {
          this.accuracyData = response.data.data;
        } else {
          this.accuracyData = null;
          this.analysisError = 'No forecast data available for the selected criteria';
        }
      } catch (error) {
        console.error('Error analyzing forecast accuracy:', error);
        this.analysisError = error.response?.data?.message || 'An error occurred while analyzing forecast accuracy';
        this.accuracyData = null;
      } finally {
        this.analyzing = false;
      }
    }
  }
};
</script>

<style scoped>
/* Styling yang disempurnakan untuk ForecastAccuracyAnalysis.vue */

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

/* Form styling */
form .row {
  margin-bottom: 0.5rem;
}

.form-label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: #495057;
  font-size: 0.9rem;
}

.form-control,
.form-select {
  border-radius: 5px;
  padding: 0.5rem 0.75rem;
  border: 1px solid #ced4da;
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
  width: 100%;
  height: calc(1.5em + 0.75rem + 2px);
  font-size: 0.9rem;
}

.form-control:focus,
.form-select:focus {
  border-color: #80bdff;
  box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* Proper spacing for form groups */
.mb-3 {
  margin-bottom: 1.25rem !important;
}

/* Button styling */
.btn {
  border-radius: 5px;
  font-weight: 500;
  padding: 0.5rem 1rem;
  transition: all 0.2s ease;
}

.btn-primary {
  background-color: #007bff;
  border-color: #007bff;
}

.btn-primary:hover {
  background-color: #0069d9;
  border-color: #0062cc;
}

.btn-primary:focus {
  box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* Summary cards styling */
.card .card-subtitle {
  font-size: 0.85rem;
  color: #6c757d;
}

.card .card-title {
  font-size: 1.75rem;
  margin-top: 0.5rem;
  margin-bottom: 0.5rem;
  font-weight: 600;
}

.card .card-text {
  font-size: 0.85rem;
  margin-top: 0.5rem;
}

/* Table styling */
.table-responsive {
  border-radius: 6px;
  overflow: hidden;
}

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

.table-sm thead th {
  padding: 0.6rem 0.85rem;
}

.table-sm tbody td {
  padding: 0.6rem 0.85rem;
}

.table-hover tbody tr:hover {
  background-color: rgba(0, 0, 0, 0.04);
}

/* Badge styling */
.badge {
  padding: 0.4em 0.65em;
  font-weight: 500;
  font-size: 0.75rem;
  border-radius: 4px;
}

/* Progress bar styling */
.progress {
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 0.25rem;
}

/* Filter input group */
.input-group {
  border-radius: 5px;
  overflow: hidden;
}

.input-group .form-control {
  border-right: none;
}

.input-group-text {
  background-color: #fff;
  border-left: none;
  color: #6c757d;
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

/* Alert styling */
.alert {
  border-radius: 6px;
  padding: 1rem 1.25rem;
  border: none;
}

.alert-danger {
  background-color: rgba(220, 53, 69, 0.1);
  color: #721c24;
}

/* Responsive adjustments */
@media (max-width: 991px) {
  .mb-3 {
    margin-bottom: 1rem !important;
  }
  
  .col-lg-3 {
    margin-bottom: 0.5rem;
  }
}

@media (max-width: 768px) {
  .page-container {
    padding: 1rem;
  }
  
  .card-body {
    padding: 1.25rem;
  }
  
  .table thead th,
  .table tbody td {
    padding: 0.6rem;
  }
  
  .btn {
    padding: 0.4rem 0.75rem;
  }
  
  .text-center.py-5 {
    padding-top: 2rem !important;
    padding-bottom: 2rem !important;
  }
}
</style>