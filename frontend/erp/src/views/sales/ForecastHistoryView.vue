<!-- src/views/sales/ForecastHistoryView.vue -->
<template>
  <div class="page-container">
    <div class="page-header">
      <h2>Forecast History</h2>
      <p class="text-muted">View forecast version history for items and customers</p>
    </div>

    <!-- Search Form -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0">Search Forecast History</h5>
      </div>
      <div class="card-body">
        <form @submit.prevent="searchForecastHistory" class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Customer</label>
            <select
              class="form-select"
              v-model="searchForm.customer_id"
              required
            >
              <option value="">Select Customer</option>
              <option
                v-for="customer in customers"
                :key="customer.customer_id"
                :value="customer.customer_id"
              >
                {{ customer.name }}
              </option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Item</label>
            <select
              class="form-select"
              v-model="searchForm.item_id"
              required
            >
              <option value="">Select Item</option>
              <option
                v-for="item in items"
                :key="item.item_id"
                :value="item.item_id"
              >
                {{ item.item_code }} - {{ item.name }}
              </option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Forecast Period</label>
            <input
              type="month"
              class="form-control"
              v-model="searchForm.forecast_period"
              required
            />
          </div>
          <div class="col-12 d-flex justify-content-end">
            <button
              type="submit"
              class="btn btn-primary me-2"
              :disabled="searching"
            >
              <i
                v-if="searching"
                class="fas fa-spinner fa-spin me-2"
              ></i>
              <i v-else class="fas fa-search me-2"></i>
              Search History
            </button>
            <button
              type="button"
              class="btn btn-outline-secondary"
              @click="resetSearch"
            >
              Reset
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Search Results / Version History -->
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Version History</h5>
        <div v-if="forecastHistory.length > 0" class="d-flex align-items-center">
          <span class="badge bg-primary me-2">{{ forecastHistory.length }} versions</span>
          <div class="dropdown">
            <button
              class="btn btn-sm btn-outline-secondary dropdown-toggle"
              type="button"
              id="exportDropdown"
              data-bs-toggle="dropdown"
              aria-expanded="false"
            >
              <i class="fas fa-download me-1"></i> Export
            </button>
            <ul class="dropdown-menu" aria-labelledby="exportDropdown">
              <li>
                <a class="dropdown-item" href="#" @click.prevent="exportToCsv">
                  <i class="fas fa-file-csv me-2"></i> CSV
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="#" @click.prevent="exportToExcel">
                  <i class="fas fa-file-excel me-2"></i> Excel
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="#" @click.prevent="exportToPdf">
                  <i class="fas fa-file-pdf me-2"></i> PDF
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>
      <div class="card-body">
        <div v-if="searching" class="text-center py-5">
          <i class="fas fa-spinner fa-spin fa-2x"></i>
          <p class="mt-2">Searching forecast history...</p>
        </div>

        <div v-else-if="searchError" class="alert alert-danger" role="alert">
          <i class="fas fa-exclamation-circle me-2"></i>
          {{ searchError }}
        </div>

        <div
          v-else-if="forecastHistory.length === 0 && hasSearched"
          class="text-center py-5"
        >
          <i class="fas fa-history fa-3x text-muted mb-3"></i>
          <h4>No forecast history found</h4>
          <p class="text-muted">
            No forecast versions were found for the selected criteria
          </p>
        </div>

        <div
          v-else-if="forecastHistory.length === 0"
          class="text-center py-5"
        >
          <i class="fas fa-search fa-3x text-muted mb-3"></i>
          <h4>Search for forecast history</h4>
          <p class="text-muted">
            Select a customer, item, and period to view forecast version history
          </p>
        </div>

        <div v-else>
          <!-- Forecast Summary Card -->
          <div v-if="currentForecast" class="forecast-summary-card mb-4">
            <div class="row g-0">
              <div class="col-md-4">
                <div class="card-body border-end h-100">
                  <h5 class="card-title">Customer</h5>
                  <p class="card-text">{{ currentForecast.customer?.name }}</p>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card-body border-end h-100">
                  <h5 class="card-title">Item</h5>
                  <p class="card-text">
                    <strong>{{ currentForecast.item?.item_code }}</strong><br>
                    <span class="text-muted">{{ currentForecast.item?.name }}</span>
                  </p>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card-body h-100">
                  <h5 class="card-title">Period</h5>
                  <p class="card-text">{{ formatDate(currentForecast.forecast_period) }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Version Timeline -->
          <div class="version-timeline mb-4">
            <h6 class="mb-3">Version Timeline</h6>
            <div class="timeline-container">
              <div
                v-for="(forecast, index) in forecastHistory"
                :key="forecast.forecast_id"
                class="timeline-item"
                :class="{ 'active': selectedVersion === index }"
                @click="selectVersion(index)"
              >
                <div class="timeline-point">
                  <i class="fas fa-circle"></i>
                </div>
                <div class="timeline-content">
                  <div class="timeline-date">
                    {{ formatDateTime(forecast.submission_date) }}
                  </div>
                  <div class="timeline-source">
                    <span class="badge" :class="getSourceBadgeClass(forecast.forecast_source)">
                      {{ forecast.forecast_source }}
                    </span>
                  </div>
                  <div class="timeline-info">
                    <small v-if="forecast.is_current_version" class="badge bg-success">Current Version</small>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Version Details -->
          <div v-if="selectedForecast" class="version-details">
            <h6 class="mb-3">Version Details</h6>
            <div class="card bg-light">
              <div class="card-body">
                <div class="row">
                  <div class="col-md-4">
                    <div class="mb-3">
                      <label class="form-label text-muted">Forecast Quantity</label>
                      <div class="form-control-plaintext font-weight-bold">
                        {{ selectedForecast.forecast_quantity }}
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="mb-3">
                      <label class="form-label text-muted">Actual Quantity</label>
                      <div class="form-control-plaintext font-weight-bold">
                        {{ selectedForecast.actual_quantity !== null ? selectedForecast.actual_quantity : 'N/A' }}
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="mb-3">
                      <label class="form-label text-muted">Variance</label>
                      <div 
                        class="form-control-plaintext font-weight-bold"
                        :class="{
                          'text-success': selectedForecast.variance > 0,
                          'text-danger': selectedForecast.variance < 0
                        }"
                      >
                        {{ selectedForecast.variance !== null ? selectedForecast.variance : 'N/A' }}
                        <small v-if="selectedForecast.variance !== null">
                          ({{ calculateVariancePercentage(selectedForecast) }}%)
                        </small>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-4">
                    <div class="mb-3">
                      <label class="form-label text-muted">Source</label>
                      <div class="form-control-plaintext">
                        <span class="badge" :class="getSourceBadgeClass(selectedForecast.forecast_source)">
                          {{ selectedForecast.forecast_source }}
                        </span>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="mb-3">
                      <label class="form-label text-muted">Confidence Level</label>
                      <div class="form-control-plaintext">
                        <div class="progress" style="height: 6px;">
                          <div 
                            class="progress-bar" 
                            :class="getConfidenceLevelClass(selectedForecast.confidence_level)"
                            :style="`width: ${selectedForecast.confidence_level * 100}%`"
                          ></div>
                        </div>
                        <small>{{ (selectedForecast.confidence_level * 100).toFixed(0) }}%</small>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="mb-3">
                      <label class="form-label text-muted">Issue Date</label>
                      <div class="form-control-plaintext">
                        {{ formatDate(selectedForecast.forecast_issue_date) }}
                      </div>
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-12">
                    <div class="mb-0">
                      <label class="form-label text-muted">Version Status</label>
                      <div class="form-control-plaintext">
                        <span v-if="selectedForecast.is_current_version" class="badge bg-success">
                          <i class="fas fa-check-circle me-1"></i> Current Version
                        </span>
                        <span v-else class="badge bg-secondary">
                          <i class="fas fa-history me-1"></i> Historical Version
                        </span>
                        <span v-if="selectedForecast.previous_version_id" class="ms-2 text-muted">
                          Previous version: {{ selectedForecast.previous_version_id }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Version Comparison Chart -->
          <div v-if="forecastHistory.length > 1" class="version-comparison mt-4">
            <h6 class="mb-3">Version Comparison</h6>
            <div class="comparison-chart-container">
              <canvas ref="comparisonChart"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import Chart from 'chart.js/auto';

export default {
  name: 'ForecastHistoryView',
  data() {
    return {
      customers: [],
      items: [],
      searchForm: {
        customer_id: '',
        item_id: '',
        forecast_period: ''
      },
      searching: false,
      searchError: null,
      hasSearched: false,
      forecastHistory: [],
      selectedVersion: 0,
      comparisonChart: null
    };
  },
  computed: {
    currentForecast() {
      if (this.forecastHistory.length === 0) {
        return null;
      }
      
      // Find the current version
      const current = this.forecastHistory.find(f => f.is_current_version);
      return current || this.forecastHistory[0];
    },
    selectedForecast() {
      if (this.forecastHistory.length === 0 || this.selectedVersion < 0 || this.selectedVersion >= this.forecastHistory.length) {
        return null;
      }
      
      return this.forecastHistory[this.selectedVersion];
    }
  },
  watch: {
    forecastHistory() {
      this.$nextTick(() => {
        if (this.forecastHistory.length > 1) {
          this.renderComparisonChart();
        }
      });
    },
    selectedVersion() {
      this.updateComparisonChart();
    }
  },
  created() {
    this.fetchCustomers();
    this.fetchItems();
    
    // Check if there are query parameters for direct access
    const { customer_id, item_id, forecast_period } = this.$route.query;
    if (customer_id && item_id && forecast_period) {
      this.searchForm = {
        customer_id,
        item_id,
        forecast_period: forecast_period.substring(0, 7) // Extract YYYY-MM from date
      };
      this.searchForecastHistory();
    }
  },
  methods: {
    formatDate(dateString) {
      if (!dateString) return 'N/A';
      
      const date = new Date(dateString);
      return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    },
    formatDateTime(dateString) {
      if (!dateString) return 'N/A';
      
      const date = new Date(dateString);
      return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
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
    calculateVariancePercentage(forecast) {
      if (!forecast.actual_quantity || forecast.actual_quantity === 0) {
        return 'N/A';
      }
      
      const percentage = (forecast.variance / forecast.actual_quantity) * 100;
      return percentage.toFixed(2);
    },
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
    async searchForecastHistory() {
      if (!this.searchForm.customer_id || !this.searchForm.item_id || !this.searchForm.forecast_period) {
        this.searchError = 'All fields are required to search forecast history';
        return;
      }
      
      this.searching = true;
      this.searchError = null;
      this.hasSearched = true;
      
      try {
        const params = {
          item_id: this.searchForm.item_id,
          customer_id: this.searchForm.customer_id,
          forecast_period: `${this.searchForm.forecast_period}-01` // Add day to make a valid date
        };
        
        const response = await axios.get('/forecasts/history', { params });
        
        if (response.data.data) {
          // Sort history by submission date (newest first)
          this.forecastHistory = response.data.data.sort((a, b) => {
            return new Date(b.submission_date) - new Date(a.submission_date);
          });
          
          // Select the first (newest) version by default
          this.selectedVersion = 0;
        } else {
          this.forecastHistory = [];
        }
      } catch (error) {
        console.error('Error searching forecast history:', error);
        this.searchError = error.response?.data?.message || 'An error occurred while searching forecast history';
        this.forecastHistory = [];
      } finally {
        this.searching = false;
      }
    },
    resetSearch() {
      this.searchForm = {
        customer_id: '',
        item_id: '',
        forecast_period: ''
      };
      this.searchError = null;
      this.hasSearched = false;
      this.forecastHistory = [];
      this.selectedVersion = 0;
      
      // Clear URL parameters
      this.$router.replace({ query: {} });
      
      // Destroy chart if it exists
      if (this.comparisonChart) {
        this.comparisonChart.destroy();
        this.comparisonChart = null;
      }
    },
    selectVersion(index) {
      this.selectedVersion = index;
    },
    renderComparisonChart() {
      // Destroy existing chart if it exists
      if (this.comparisonChart) {
        this.comparisonChart.destroy();
      }
      
      const ctx = this.$refs.comparisonChart?.getContext('2d');
      if (!ctx) return;
      
      // Prepare data for chart
      const labels = this.forecastHistory.map((forecast, index) => {
        const date = new Date(forecast.submission_date);
        return `V${index + 1} (${date.toLocaleDateString()})`;
      }).reverse(); // Oldest to newest
      
      const forecastData = this.forecastHistory.map(forecast => forecast.forecast_quantity).reverse();
      
      const actualData = this.forecastHistory.map(forecast => forecast.actual_quantity).reverse();
      
      this.comparisonChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [
            {
              label: 'Forecast Quantity',
              data: forecastData,
              borderColor: 'rgba(54, 162, 235, 1)',
              backgroundColor: 'rgba(54, 162, 235, 0.2)',
              fill: false,
              tension: 0.4
            },
            {
              label: 'Actual Quantity',
              data: actualData,
              borderColor: 'rgba(255, 99, 132, 1)',
              backgroundColor: 'rgba(255, 99, 132, 0.2)',
              fill: false,
              tension: 0.4
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            tooltip: {
              mode: 'index',
              intersect: false
            },
            legend: {
              position: 'top'
            }
          },
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
                text: 'Version'
              }
            }
          }
        }
      });
    },
    updateComparisonChart() {
      if (!this.comparisonChart) return;
      
      // Highlight the selected version
      this.comparisonChart.data.datasets.forEach(dataset => {
        dataset.pointBackgroundColor = this.forecastHistory.map((_, index) => {
          // Reverse the index because the chart is oldest to newest
          const reversedIndex = this.forecastHistory.length - 1 - index;
          return reversedIndex === this.selectedVersion ? 
            (dataset.label === 'Forecast Quantity' ? 'rgba(54, 162, 235, 1)' : 'rgba(255, 99, 132, 1)') : 
            'rgba(220, 220, 220, 0.2)';
        });
        
        dataset.pointRadius = this.forecastHistory.map((_, index) => {
          const reversedIndex = this.forecastHistory.length - 1 - index;
          return reversedIndex === this.selectedVersion ? 6 : 3;
        });
      });
      
      this.comparisonChart.update();
    },
    exportToCsv() {
      // In a real application, this would send a request to the server
      // For this example, we'll create a client-side CSV download
      if (this.forecastHistory.length === 0) return;
      
      const headers = [
        'Version',
        'Submission Date',
        'Forecast Quantity',
        'Actual Quantity',
        'Variance',
        'Forecast Source',
        'Confidence Level',
        'Issue Date',
        'Is Current Version'
      ];
      
      const rows = this.forecastHistory.map((forecast, index) => [
        index + 1,
        forecast.submission_date,
        forecast.forecast_quantity,
        forecast.actual_quantity || '',
        forecast.variance || '',
        forecast.forecast_source,
        (forecast.confidence_level * 100).toFixed(0) + '%',
        forecast.forecast_issue_date,
        forecast.is_current_version ? 'Yes' : 'No'
      ]);
      
      // Add header row
      rows.unshift(headers);
      
      // Create CSV content
      const csvContent = rows.map(row => row.join(',')).join('\n');
      
      // Create download link
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.setAttribute('href', url);
      link.setAttribute('download', `forecast_history_${this.searchForm.forecast_period}.csv`);
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    },
    exportToExcel() {
      // In a real application, this would send a request to the server
      alert('Excel export would be implemented in a real application');
    },
    exportToPdf() {
      // In a real application, this would send a request to the server
      alert('PDF export would be implemented in a real application');
    }
  }
};
</script>

<style scoped>
/* Styling yang disempurnakan untuk ForecastHistoryView.vue */

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
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header h5 {
  font-weight: 600;
  color: #333;
  margin-bottom: 0;
}

/* Form styling */
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

/* Search form spacing */
form.row.g-3 {
  --bs-gutter-x: 1.5rem;
  --bs-gutter-y: 1rem;
}

form.row.g-3 .col-md-4 {
  margin-bottom: 0.5rem;
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

.btn-outline-secondary {
  color: #6c757d;
  border-color: #6c757d;
}

.btn-outline-secondary:hover {
  color: #fff;
  background-color: #6c757d;
  border-color: #6c757d;
}

.btn-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
}

/* Badge styling */
.badge {
  padding: 0.4em 0.65em;
  font-weight: 500;
  font-size: 0.75rem;
  border-radius: 4px;
}

/* Alert styling */
.alert {
  border-radius: 6px;
  padding: 1rem 1.25rem;
  margin-bottom: 1.5rem;
  border: none;
}

.alert-danger {
  background-color: rgba(220, 53, 69, 0.1);
  color: #721c24;
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

/* Forecast Summary Card */
.forecast-summary-card {
  background-color: #f8f9fa;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 1px 8px rgba(0, 0, 0, 0.05);
}

.forecast-summary-card .card-body {
  padding: 1.25rem;
}

.forecast-summary-card .card-title {
  font-size: 0.9rem;
  font-weight: 600;
  color: #6c757d;
  margin-bottom: 0.5rem;
}

.forecast-summary-card .card-text {
  font-size: 1.05rem;
  color: #333;
}

.forecast-summary-card .border-end {
  border-right: 1px solid #e9ecef;
}

/* Version section headers */
h6.mb-3 {
  font-size: 1rem;
  font-weight: 600;
  color: #495057;
  margin-bottom: 1rem !important;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid #e9ecef;
}

/* Timeline styling - enhanced */
.version-timeline {
  margin-bottom: 2rem;
}

.timeline-container {
  position: relative;
  padding-left: 1.75rem;
  border-left: 2px solid #e9ecef;
  margin-left: 0.5rem;
}

.timeline-item {
  position: relative;
  padding-bottom: 1.75rem;
  cursor: pointer;
  margin-bottom: 0.5rem;
  transition: all 0.2s;
}

.timeline-item:hover .timeline-point i {
  transform: scale(1.2);
  box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
}

.timeline-item.active .timeline-point i {
  background-color: #2563eb;
  color: white;
  box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.2);
  transform: scale(1.2);
}

.timeline-item.active .timeline-content {
  font-weight: 500;
  transform: translateX(5px);
}

.timeline-point {
  position: absolute;
  left: -1.75rem;
  top: 0;
}

.timeline-point i {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 50%;
  background-color: #f8f9fa;
  border: 2px solid #e9ecef;
  color: #6c757d;
  transition: all 0.2s;
}

.timeline-content {
  padding-left: 0.75rem;
  transition: transform 0.2s;
}

.timeline-date {
  margin-bottom: 0.25rem;
  font-size: 0.875rem;
  color: #495057;
}

.timeline-source {
  margin-bottom: 0.25rem;
}

.timeline-info {
  font-size: 0.75rem;
}

/* Version Details */
.version-details {
  margin-bottom: 2rem;
}

.version-details .card {
  background-color: #f8f9fa;
  border: 1px solid #e9ecef;
  box-shadow: none;
}

.version-details .form-label {
  font-size: 0.8rem;
  margin-bottom: 0.25rem;
}

.form-control-plaintext {
  font-size: 1rem;
  padding: 0.25rem 0;
  color: #333;
}

.form-control-plaintext.font-weight-bold {
  font-weight: 600;
}

/* Progress bar styling */
.progress {
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 0.25rem;
  height: 6px;
}

/* Comparison Chart */
.comparison-chart-container {
  position: relative;
  height: 350px;
  margin-top: 1.5rem;
  padding: 1rem;
  background-color: #fff;
  border-radius: 8px;
  border: 1px solid #e9ecef;
}

/* Dropdown styling */
.dropdown-toggle::after {
  margin-left: 0.5em;
  vertical-align: 0.15em;
}

.dropdown-menu {
  border-radius: 6px;
  border: none;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  padding: 0.5rem 0;
}

.dropdown-item {
  padding: 0.5rem 1rem;
  color: #333;
  font-size: 0.9rem;
  transition: background-color 0.2s;
}

.dropdown-item:hover {
  background-color: #f1f5ff;
}

.dropdown-item i {
  color: #6c757d;
  width: 1.25rem;
  text-align: center;
}

/* Responsive adjustments */
@media (max-width: 991px) {
  .card-header {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .card-header > div {
    margin-top: 0.75rem;
  }
  
  .forecast-summary-card .border-end {
    border-right: none;
    border-bottom: 1px solid #e9ecef;
  }
  
  .forecast-summary-card .card-body {
    padding: 1rem;
  }
}

@media (max-width: 768px) {
  .page-container {
    padding: 1rem;
  }
  
  .card-body {
    padding: 1.25rem;
  }
  
  .btn {
    padding: 0.4rem 0.75rem;
  }
  
  .timeline-container {
    padding-left: 1.5rem;
  }
  
  .timeline-item {
    padding-bottom: 1.5rem;
  }
  
  .timeline-point {
    left: -1.55rem;
  }
  
  .timeline-point i {
    width: 1.25rem;
    height: 1.25rem;
    font-size: 0.75rem;
  }
  
  .comparison-chart-container {
    height: 300px;
  }
}
</style>