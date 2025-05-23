<template>
  <div class="forecast-detail-container">
    <div class="page-header">
      <div class="header-left">
        <button 
          type="button" 
          class="btn btn-secondary btn-back" 
          @click="$router.go(-1)"
        >
          <i class="fas fa-arrow-left"></i>
          Back
        </button>
        <div class="header-title">
          <h1>Sales Forecast Details</h1>
          <div class="forecast-period" v-if="forecast">
            {{ formatPeriod(forecast.forecast_period) }}
          </div>
        </div>
      </div>
      <div class="header-actions" v-if="forecast">
        <router-link 
          v-if="forecast.is_current_version"
          :to="`/sales/forecasts/${forecast.forecast_id}/edit`"
          class="btn btn-warning"
        >
          <i class="fas fa-edit"></i>
          Edit Forecast
        </router-link>
        <button 
          v-if="forecast.is_current_version"
          @click="confirmDelete"
          class="btn btn-danger"
        >
          <i class="fas fa-trash"></i>
          Delete
        </button>
      </div>
    </div>

    <div v-if="isLoading" class="loading-state">
      <i class="fas fa-spinner fa-spin"></i>
      Loading forecast details...
    </div>

    <div v-else-if="forecast" class="content-grid">
      <!-- Main Forecast Information -->
      <div class="card main-info">
        <div class="card-header">
          <h3>Forecast Information</h3>
          <div class="version-badge" :class="forecast.is_current_version ? 'current' : 'old'">
            {{ forecast.is_current_version ? 'Current Version' : 'Old Version' }}
          </div>
        </div>
        <div class="card-body">
          <div class="info-grid">
            <div class="info-item">
              <label>Customer</label>
              <div class="customer-info">
                <div class="customer-name">{{ forecast.customer?.name }}</div>
                <div class="customer-code">{{ forecast.customer?.customer_code }}</div>
              </div>
            </div>
            
            <div class="info-item">
              <label>Item</label>
              <div class="item-info">
                <div class="item-name">{{ forecast.item?.name }}</div>
                <div class="item-code">{{ forecast.item?.item_code }}</div>
              </div>
            </div>
            
            <div class="info-item">
              <label>Forecast Period</label>
              <div class="forecast-period-display">
                {{ formatPeriod(forecast.forecast_period) }}
              </div>
            </div>
            
            <div class="info-item">
              <label>Forecast Source</label>
              <span class="source-badge" :class="getSourceClass(forecast.forecast_source)">
                {{ forecast.forecast_source }}
              </span>
            </div>
            
            <div class="info-item">
              <label>Confidence Level</label>
              <div class="confidence-display">
                <div class="confidence-bar">
                  <div 
                    class="confidence-fill"
                    :style="{ width: (forecast.confidence_level * 100) + '%' }"
                  ></div>
                </div>
                <span class="confidence-text">
                  {{ formatPercentage(forecast.confidence_level) }}
                </span>
              </div>
            </div>
            
            <div class="info-item">
              <label>Issue Date</label>
              <div>{{ formatDate(forecast.forecast_issue_date) }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Quantity & Variance Information -->
      <div class="card quantities-info">
        <div class="card-header">
          <h3>Quantities & Performance</h3>
        </div>
        <div class="card-body">
          <div class="quantities-grid">
            <div class="quantity-card forecast-qty">
              <div class="quantity-icon">
                <i class="fas fa-chart-line"></i>
              </div>
              <div class="quantity-info">
                <div class="quantity-label">Forecast Quantity</div>
                <div class="quantity-value">{{ formatNumber(forecast.forecast_quantity) }}</div>
              </div>
            </div>
            
            <div class="quantity-card actual-qty">
              <div class="quantity-icon">
                <i class="fas fa-check-circle"></i>
              </div>
              <div class="quantity-info">
                <div class="quantity-label">Actual Quantity</div>
                <div class="quantity-value">
                  {{ forecast.actual_quantity !== null ? formatNumber(forecast.actual_quantity) : 'Not Available' }}
                </div>
              </div>
            </div>
            
            <div class="quantity-card variance-qty" v-if="forecast.variance !== null">
              <div class="quantity-icon" :class="getVarianceIconClass(forecast.variance)">
                <i class="fas" :class="getVarianceIcon(forecast.variance)"></i>
              </div>
              <div class="quantity-info">
                <div class="quantity-label">Variance</div>
                <div class="quantity-value" :class="getVarianceClass(forecast.variance)">
                  {{ formatNumber(forecast.variance) }}
                </div>
                <div class="quantity-percentage" :class="getVarianceClass(forecast.variance)">
                  {{ formatVariancePercentage(forecast.variance, forecast.forecast_quantity) }}
                </div>
              </div>
            </div>
          </div>

          <!-- Accuracy Assessment -->
          <div v-if="forecast.variance !== null" class="accuracy-assessment">
            <h4>Accuracy Assessment</h4>
            <div class="accuracy-info">
              <div class="accuracy-item">
                <label>Absolute Error</label>
                <span>{{ formatNumber(Math.abs(forecast.variance)) }}</span>
              </div>
              <div class="accuracy-item">
                <label>Accuracy Rating</label>
                <span class="accuracy-rating" :class="getAccuracyClass(forecast.variance, forecast.forecast_quantity)">
                  {{ getAccuracyRating(forecast.variance, forecast.forecast_quantity) }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Version History -->
      <div class="card version-history" v-if="versionHistory.length > 1">
        <div class="card-header">
          <h3>Version History</h3>
          <button @click="loadVersionHistory" class="btn btn-sm btn-secondary">
            <i class="fas fa-sync-alt"></i>
            Refresh
          </button>
        </div>
        <div class="card-body">
          <div class="version-timeline">
            <div 
              v-for="(version, index) in versionHistory" 
              :key="version.forecast_id"
              class="version-item"
              :class="{ 'current-version': version.is_current_version }"
            >
              <div class="version-marker">
                <i class="fas fa-circle"></i>
              </div>
              <div class="version-content">
                <div class="version-header">
                  <span class="version-title">
                    Version {{ versionHistory.length - index }}
                    <span v-if="version.is_current_version" class="current-label">(Current)</span>
                  </span>
                  <span class="version-date">{{ formatDateTime(version.submission_date) }}</span>
                </div>
                <div class="version-details">
                  <div class="version-detail">
                    <strong>Forecast:</strong> {{ formatNumber(version.forecast_quantity) }}
                  </div>
                  <div class="version-detail" v-if="version.actual_quantity">
                    <strong>Actual:</strong> {{ formatNumber(version.actual_quantity) }}
                  </div>
                  <div class="version-detail">
                    <strong>Source:</strong> {{ version.forecast_source }}
                  </div>
                  <div class="version-detail">
                    <strong>Confidence:</strong> {{ formatPercentage(version.confidence_level) }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Related Information -->
      <div class="card related-info">
        <div class="card-header">
          <h3>Related Information</h3>
        </div>
        <div class="card-body">
          <div class="related-links">
            <router-link 
              :to="`/sales/customers/${forecast.customer_id}`"
              class="related-link"
            >
              <i class="fas fa-user"></i>
              View Customer Details
            </router-link>
            
            <router-link 
              :to="`/items/${forecast.item_id}`"
              class="related-link"
            >
              <i class="fas fa-cube"></i>
              View Item Details
            </router-link>
            
            <router-link 
              :to="`/sales/forecasts?customer_id=${forecast.customer_id}&item_id=${forecast.item_id}`"
              class="related-link"
            >
              <i class="fas fa-chart-line"></i>
              View All Forecasts for this Item-Customer
            </router-link>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <ConfirmationModal
      v-if="showDeleteModal"
      title="Delete Forecast"
      :message="`Are you sure you want to delete this forecast? This action cannot be undone.`"
      confirm-button-text="Delete"
      confirm-button-class="btn btn-danger"
      @confirm="deleteForecast"
      @close="showDeleteModal = false"
    />
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'SalesForecastDetail',
  props: {
    id: {
      type: [String, Number],
      required: true
    }
  },
  data() {
    return {
      forecast: null,
      versionHistory: [],
      isLoading: false,
      showDeleteModal: false
    };
  },
  async mounted() {
    await this.loadForecast();
    await this.loadVersionHistory();
  },
  methods: {
    async loadForecast() {
      try {
        this.isLoading = true;
        const response = await axios.get(`/forecasts/${this.id}`);
        this.forecast = response.data.data;
      } catch (error) {
        console.error('Error loading forecast:', error);
        this.$toast?.error('Failed to load forecast details');
        this.$router.go(-1);
      } finally {
        this.isLoading = false;
      }
    },

    async loadVersionHistory() {
      if (!this.forecast) return;
      
      try {
        const response = await axios.get('/forecasts/history', {
          params: {
            item_id: this.forecast.item_id,
            customer_id: this.forecast.customer_id,
            forecast_period: this.forecast.forecast_period
          }
        });
        this.versionHistory = response.data.data || [];
      } catch (error) {
        console.error('Error loading version history:', error);
        // Don't show error toast for version history as it's not critical
        this.versionHistory = [this.forecast]; // Fallback to current forecast only
      }
    },

    confirmDelete() {
      this.showDeleteModal = true;
    },

    async deleteForecast() {
      try {
        await axios.delete(`/forecasts/${this.forecast.forecast_id}`);
        this.$toast?.success('Forecast deleted successfully');
        this.showDeleteModal = false;
        this.$router.push('/sales/forecasts');
      } catch (error) {
        console.error('Error deleting forecast:', error);
        this.$toast?.error('Failed to delete forecast');
      }
    },

    formatPeriod(date) {
      if (!date) return '';
      const d = new Date(date);
      return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
    },

    formatDate(date) {
      if (!date) return 'Not set';
      const d = new Date(date);
      return d.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
      });
    },

    formatDateTime(date) {
      if (!date) return 'Not set';
      const d = new Date(date);
      return d.toLocaleString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    },

    formatNumber(value) {
      if (value === null || value === undefined) return '';
      return parseFloat(value).toLocaleString('en-US', { 
        minimumFractionDigits: 0, 
        maximumFractionDigits: 2 
      });
    },

    formatPercentage(value) {
      if (value === null || value === undefined) return '';
      return (parseFloat(value) * 100).toFixed(1) + '%';
    },

    formatVariancePercentage(variance, forecast) {
      if (!variance || !forecast || forecast === 0) return '';
      const percentage = (variance / forecast) * 100;
      return `(${percentage > 0 ? '+' : ''}${percentage.toFixed(1)}%)`;
    },

    getVarianceClass(variance) {
      if (variance > 0) return 'text-success';
      if (variance < 0) return 'text-danger';
      return 'text-muted';
    },

    getVarianceIcon(variance) {
      if (variance > 0) return 'fa-arrow-up';
      if (variance < 0) return 'fa-arrow-down';
      return 'fa-minus';
    },

    getVarianceIconClass(variance) {
      if (variance > 0) return 'icon-success';
      if (variance < 0) return 'icon-danger';
      return 'icon-muted';
    },

    getSourceClass(source) {
      const classes = {
        'Customer': 'source-customer',
        'System-Manual': 'source-manual',
        'System-Average': 'source-system',
        'System-Weighted': 'source-system',
        'System-Trend': 'source-system'
      };
      return classes[source] || 'source-other';
    },

    getAccuracyRating(variance, forecast) {
      if (!variance || !forecast) return 'N/A';
      
      const percentage = Math.abs(variance / forecast) * 100;
      
      if (percentage <= 5) return 'Excellent';
      if (percentage <= 10) return 'Good';
      if (percentage <= 20) return 'Fair';
      if (percentage <= 30) return 'Poor';
      return 'Very Poor';
    },

    getAccuracyClass(variance, forecast) {
      if (!variance || !forecast) return '';
      
      const percentage = Math.abs(variance / forecast) * 100;
      
      if (percentage <= 5) return 'accuracy-excellent';
      if (percentage <= 10) return 'accuracy-good';
      if (percentage <= 20) return 'accuracy-fair';
      if (percentage <= 30) return 'accuracy-poor';
      return 'accuracy-very-poor';
    }
  }
};
</script>

<style scoped>
.forecast-detail-container {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
}

.header-left {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}

.btn-back {
  margin-top: 0.25rem;
}

.header-title h1 {
  margin: 0;
  color: var(--gray-800);
}

.forecast-period {
  color: var(--gray-600);
  font-size: 1.125rem;
  margin-top: 0.25rem;
}

.header-actions {
  display: flex;
  gap: 1rem;
}

.loading-state {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 4rem 0;
  color: var(--gray-500);
  font-size: 1.125rem;
}

.loading-state i {
  margin-right: 0.5rem;
  font-size: 1.5rem;
}

.content-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
}

.card {
  background: white;
  border-radius: 0.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem 2rem;
  background: var(--gray-50);
  border-bottom: 1px solid var(--gray-200);
}

.card-header h3 {
  margin: 0;
  color: var(--gray-800);
}

.card-body {
  padding: 2rem;
}

.main-info {
  grid-column: 1 / -1;
}

.version-badge {
  padding: 0.375rem 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.75rem;
  font-weight: 500;
}

.version-badge.current {
  background-color: #dcfce7;
  color: #166534;
}

.version-badge.old {
  background-color: var(--gray-100);
  color: var(--gray-600);
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
}

.info-item {
  display: flex;
  flex-direction: column;
}

.info-item label {
  font-weight: 500;
  color: var(--gray-600);
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
}

.customer-info,
.item-info {
  display: flex;
  flex-direction: column;
}

.customer-name,
.item-name {
  font-weight: 500;
  color: var(--gray-800);
  font-size: 1rem;
}

.customer-code,
.item-code {
  font-size: 0.875rem;
  color: var(--gray-500);
  margin-top: 0.25rem;
}

.forecast-period-display {
  font-size: 1rem;
  color: var(--gray-800);
  font-weight: 500;
}

.source-badge {
  display: inline-block;
  padding: 0.375rem 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.75rem;
  font-weight: 500;
  width: fit-content;
}

.source-customer {
  background-color: #dbeafe;
  color: #1e40af;
}

.source-manual {
  background-color: #f3e8ff;
  color: #7c3aed;
}

.source-system {
  background-color: #ecfdf5;
  color: #059669;
}

.source-other {
  background-color: var(--gray-100);
  color: var(--gray-600);
}

.confidence-display {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.confidence-bar {
  position: relative;
  width: 80px;
  height: 20px;
  background: var(--gray-200);
  border-radius: 10px;
  overflow: hidden;
}

.confidence-fill {
  height: 100%;
  background: var(--primary-color);
  transition: width 0.3s ease;
}

.confidence-text {
  font-weight: 500;
  color: var(--gray-700);
}

.quantities-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.quantity-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.5rem;
  border-radius: 0.5rem;
  border: 1px solid var(--gray-200);
}

.forecast-qty {
  background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
  border-color: #93c5fd;
}

.actual-qty {
  background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
  border-color: #86efac;
}

.variance-qty {
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  border-color: #fcd34d;
}

.quantity-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.8);
  color: var(--primary-color);
  font-size: 1.5rem;
}

.quantity-icon.icon-success {
  color: var(--success-color);
}

.quantity-icon.icon-danger {
  color: var(--danger-color);
}

.quantity-icon.icon-muted {
  color: var(--gray-500);
}

.quantity-info {
  flex: 1;
}

.quantity-label {
  font-size: 0.875rem;
  color: var(--gray-600);
  margin-bottom: 0.25rem;
}

.quantity-value {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--gray-800);
}

.quantity-percentage {
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.accuracy-assessment {
  padding-top: 1.5rem;
  border-top: 1px solid var(--gray-200);
}

.accuracy-assessment h4 {
  margin: 0 0 1rem 0;
  color: var(--gray-700);
}

.accuracy-info {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.accuracy-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: var(--gray-50);
  border-radius: 0.375rem;
}

.accuracy-item label {
  font-weight: 500;
  color: var(--gray-600);
}

.accuracy-rating {
  font-weight: 500;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
}

.accuracy-excellent {
  background-color: #dcfce7;
  color: #166534;
}

.accuracy-good {
  background-color: #ecfdf5;
  color: #059669;
}

.accuracy-fair {
  background-color: #fef3c7;
  color: #d97706;
}

.accuracy-poor {
  background-color: #fed7d7;
  color: #c53030;
}

.accuracy-very-poor {
  background-color: #fecaca;
  color: #991b1b;
}

.version-timeline {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.version-item {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  border-radius: 0.5rem;
  border: 1px solid var(--gray-200);
}

.version-item.current-version {
  background: var(--gray-50);
  border-color: var(--primary-color);
}

.version-marker {
  display: flex;
  align-items: flex-start;
  padding-top: 0.125rem;
}

.version-marker i {
  color: var(--gray-400);
  font-size: 0.75rem;
}

.current-version .version-marker i {
  color: var(--primary-color);
}

.version-content {
  flex: 1;
}

.version-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.version-title {
  font-weight: 500;
  color: var(--gray-800);
}

.current-label {
  color: var(--primary-color);
  font-size: 0.75rem;
}

.version-date {
  font-size: 0.75rem;
  color: var(--gray-500);
}

.version-details {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
}

.version-detail {
  font-size: 0.875rem;
  color: var(--gray-600);
}

.related-links {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.related-link {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  border: 1px solid var(--gray-200);
  border-radius: 0.375rem;
  color: var(--gray-700);
  text-decoration: none;
  transition: all 0.2s;
}

.related-link:hover {
  background: var(--gray-50);
  border-color: var(--primary-color);
  color: var(--primary-color);
  text-decoration: none;
}

.related-link i {
  color: var(--gray-400);
}

.related-link:hover i {
  color: var(--primary-color);
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border: 1px solid transparent;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.75rem;
}

.btn-primary {
  background-color: var(--primary-color);
  color: white;
  border-color: var(--primary-color);
}

.btn-secondary {
  background-color: white;
  color: var(--gray-700);
  border-color: var(--gray-300);
}

.btn-warning {
  background-color: #f59e0b;
  color: white;
  border-color: #f59e0b;
}

.btn-danger {
  background-color: var(--danger-color);
  color: white;
  border-color: var(--danger-color);
}

.btn:hover {
  opacity: 0.9;
  transform: translateY(-1px);
}

.text-success {
  color: var(--success-color);
}

.text-danger {
  color: var(--danger-color);
}

.text-muted {
  color: var(--gray-500);
}

@media (max-width: 1024px) {
  .content-grid {
    grid-template-columns: 1fr;
  }
  
  .main-info {
    grid-column: 1;
  }
}

@media (max-width: 768px) {
  .forecast-detail-container {
    padding: 1rem;
  }

  .page-header {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  .header-left {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  .header-actions {
    justify-content: flex-end;
  }

  .info-grid {
    grid-template-columns: 1fr;
  }

  .quantities-grid {
    grid-template-columns: 1fr;
  }

  .accuracy-info {
    grid-template-columns: 1fr;
  }

  .version-details {
    flex-direction: column;
    gap: 0.5rem;
  }

  .card-body {
    padding: 1.5rem;
  }
}
</style>