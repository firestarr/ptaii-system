<!-- src/views/sales/UpdateActualsPage.vue -->
<template>
  <div class="page-container">
    <div class="page-header">
      <h2>Update Forecast Actuals</h2>
      <p class="text-muted">
        Update actuals for forecasts based on real sales data
      </p>
    </div>

    <div class="row mb-4">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header">
            <h5 class="mb-0">Bulk Update Options</h5>
          </div>
          <div class="card-body">
            <form @submit.prevent="updateActuals">
              <div class="mb-3">
                <label class="form-label">End Period (up to this date)</label>
                <input
                  type="month"
                  class="form-control"
                  v-model="updateForm.end_period"
                  required
                  :max="currentMonth"
                />
                <div class="form-text">
                  Actuals will be updated for all forecasts up to this period
                </div>
              </div>

              <div class="mb-3 form-check">
                <input
                  type="checkbox"
                  class="form-check-input"
                  id="updateAll"
                  v-model="updateForm.update_all"
                />
                <label class="form-check-label" for="updateAll">
                  Update all forecasts (including those with existing actuals)
                </label>
              </div>

              <div v-if="updateError" class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                {{ updateError }}
              </div>

              <div v-if="updateSuccess" class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                {{ updateSuccess }}
              </div>

              <div class="d-flex">
                <button
                  type="submit"
                  class="btn btn-primary"
                  :disabled="updating"
                >
                  <i
                    v-if="updating"
                    class="fas fa-spinner fa-spin me-2"
                  ></i>
                  <i v-else class="fas fa-sync-alt me-2"></i>
                  Update Actuals
                </button>
                <button
                  type="button"
                  class="btn btn-outline-secondary ms-2"
                  @click="resetForm"
                >
                  Reset
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card">
          <div class="card-header">
            <h5 class="mb-0">About Updating Actuals</h5>
          </div>
          <div class="card-body">
            <h6>How It Works</h6>
            <p>
              The system will update actual quantities for forecasts by calculating the
              total sales for each customer-item pair during the forecast period.
            </p>

            <h6>Data Sources</h6>
            <ul>
              <li>Actual quantities are calculated from completed sales invoices</li>
              <li>Only invoices with "Paid" status are considered</li>
              <li>Returns are automatically subtracted from the actual quantities</li>
            </ul>

            <h6>Version Control</h6>
            <p>
              When actuals are updated, the system creates a new version of the forecast
              with actual quantities and variance calculations. The original forecast
              quantities remain unchanged.
            </p>

            <div class="alert alert-info">
              <i class="fas fa-info-circle me-2"></i>
              It's recommended to update actuals at the end of each month after
              all sales have been finalized.
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Forecasts without Actuals -->
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Forecasts Without Actuals</h5>
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
        <div v-if="loading" class="text-center py-5">
          <i class="fas fa-spinner fa-spin fa-2x"></i>
          <p class="mt-2">Loading forecasts...</p>
        </div>

        <div
          v-else-if="!pendingForecasts || pendingForecasts.length === 0"
          class="text-center py-5"
        >
          <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
          <h4>All forecasts are up to date!</h4>
          <p class="text-muted">
            There are no forecasts with missing actuals for completed periods
          </p>
        </div>

        <div v-else>
          <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm">
              <thead>
                <tr>
                  <th>Customer</th>
                  <th>Item</th>
                  <th>Period</th>
                  <th class="text-end">Forecast Qty</th>
                  <th>Source</th>
                  <th>Issue Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="forecast in filteredForecasts"
                  :key="forecast.forecast_id"
                >
                  <td>{{ forecast.customer?.name || "N/A" }}</td>
                  <td>
                    <div>{{ forecast.item?.item_code || "N/A" }}</div>
                    <small class="text-muted">{{ forecast.item?.name }}</small>
                  </td>
                  <td>{{ formatDate(forecast.forecast_period) }}</td>
                  <td class="text-end">{{ forecast.forecast_quantity }}</td>
                  <td>
                    <span
                      class="badge"
                      :class="getSourceBadgeClass(forecast.forecast_source)"
                    >
                      {{ forecast.forecast_source }}
                    </span>
                  </td>
                  <td>{{ formatDate(forecast.forecast_issue_date) }}</td>
                  <td>
                    <button
                      class="btn btn-sm btn-outline-primary"
                      @click="openManualUpdateModal(forecast)"
                      title="Manually update actuals"
                    >
                      <i class="fas fa-edit"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="d-flex justify-content-end mt-3">
            <button
              class="btn btn-success"
              @click="updateAllPending"
              :disabled="updating"
            >
              <i v-if="updating" class="fas fa-spinner fa-spin me-2"></i>
              <i v-else class="fas fa-sync-alt me-2"></i>
              Update All Pending
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Manual Update Modal -->
    <div v-if="showModal" class="modal-overlay">
      <div class="modal-container">
        <div class="modal-header">
          <h5 class="modal-title">Update Actual Quantity</h5>
          <button class="btn-close" @click="closeModal"></button>
        </div>
        <div class="modal-body">
          <div v-if="selectedForecast" class="manual-update-form">
            <div class="mb-3">
              <div class="forecast-details mb-3">
                <div class="row">
                  <div class="col-md-6">
                    <p class="mb-1">
                      <strong>Customer:</strong> {{ selectedForecast.customer?.name }}
                    </p>
                    <p class="mb-1">
                      <strong>Item:</strong> {{ selectedForecast.item?.item_code }} - {{ selectedForecast.item?.name }}
                    </p>
                    <p class="mb-1">
                      <strong>Period:</strong> {{ formatDate(selectedForecast.forecast_period) }}
                    </p>
                  </div>
                  <div class="col-md-6">
                    <p class="mb-1">
                      <strong>Forecast Quantity:</strong> {{ selectedForecast.forecast_quantity }}
                    </p>
                    <p class="mb-1">
                      <strong>Source:</strong> {{ selectedForecast.forecast_source }}
                    </p>
                    <p class="mb-1">
                      <strong>Issue Date:</strong> {{ formatDate(selectedForecast.forecast_issue_date) }}
                    </p>
                  </div>
                </div>
              </div>

              <label class="form-label">Manual Actual Quantity</label>
              <input
                type="number"
                class="form-control"
                v-model="manualActualQuantity"
                min="0"
                step="0.01"
                required
              />
              <div class="form-text">
                Enter the actual quantity sold for this forecast
              </div>
            </div>

            <div v-if="manualUpdateError" class="alert alert-danger">
              <i class="fas fa-exclamation-circle me-2"></i>
              {{ manualUpdateError }}
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="closeModal">
            Cancel
          </button>
          <button
            class="btn btn-primary"
            @click="submitManualUpdate"
            :disabled="manualUpdating"
          >
            <i v-if="manualUpdating" class="fas fa-spinner fa-spin me-2"></i>
            <i v-else class="fas fa-save me-2"></i>
            Save
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'UpdateActualsPage',
  data() {
    return {
      loading: true,
      updating: false,
      updateForm: {
        end_period: this.getLastMonth(),
        update_all: false
      },
      updateError: null,
      updateSuccess: null,
      pendingForecasts: [],
      forecastFilter: '',
      
      // Modal state
      showModal: false,
      selectedForecast: null,
      manualActualQuantity: 0,
      manualUpdateError: null,
      manualUpdating: false
    };
  },
  computed: {
    currentMonth() {
      const now = new Date();
      return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    },
    filteredForecasts() {
      if (!this.pendingForecasts) {
        return [];
      }
      
      if (!this.forecastFilter) {
        return this.pendingForecasts;
      }
      
      const filter = this.forecastFilter.toLowerCase();
      return this.pendingForecasts.filter(forecast => {
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
    this.fetchPendingForecasts();
  },
  methods: {
    getLastMonth() {
      const date = new Date();
      date.setMonth(date.getMonth() - 1);
      return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
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
    async fetchPendingForecasts() {
      this.loading = true;
      
      try {
        // In a real application, there would be a specific API endpoint for this
        // For this example, we'll simulate it by querying forecasts with missing actuals
        const endPeriod = new Date();
        endPeriod.setMonth(endPeriod.getMonth() - 1); // Last month
        
        const endPeriodStr = `${endPeriod.getFullYear()}-${String(endPeriod.getMonth() + 1).padStart(2, '0')}-01`;
        
        const response = await axios.get('/forecasts', {
          params: {
            end_period: endPeriodStr,
            missing_actuals: true,
            is_current_version: true
          }
        });
        
        this.pendingForecasts = response.data.data || [];
      } catch (error) {
        console.error('Error fetching pending forecasts:', error);
      } finally {
        this.loading = false;
      }
    },
    async updateActuals() {
      if (!this.updateForm.end_period) {
        this.updateError = 'End period is required';
        return;
      }
      
      this.updating = true;
      this.updateError = null;
      this.updateSuccess = null;
      
      try {
        const payload = {
          end_period: `${this.updateForm.end_period}-01`,
          update_all: this.updateForm.update_all
        };
        
        const response = await axios.post('/forecasts/update-actuals', payload);
        
        if (response.data.message) {
          this.updateSuccess = response.data.message;
          
          // Refresh pending forecasts
          await this.fetchPendingForecasts();
        }
      } catch (error) {
        console.error('Error updating actuals:', error);
        this.updateError = error.response?.data?.message || 'An error occurred while updating actuals';
      } finally {
        this.updating = false;
      }
    },
    resetForm() {
      this.updateForm = {
        end_period: this.getLastMonth(),
        update_all: false
      };
      this.updateError = null;
      this.updateSuccess = null;
    },
    async updateAllPending() {
      if (this.pendingForecasts.length === 0) {
        return;
      }
      
      this.updating = true;
      this.updateError = null;
      this.updateSuccess = null;
      
      try {
        // Get the latest period from pending forecasts
        const latestPeriod = this.pendingForecasts.reduce((latest, forecast) => {
          const forecastDate = new Date(forecast.forecast_period);
          const latestDate = new Date(latest);
          return forecastDate > latestDate ? forecast.forecast_period : latest;
        }, '2000-01-01');
        
        const payload = {
          end_period: latestPeriod,
          update_all: false
        };
        
        const response = await axios.post('/forecasts/update-actuals', payload);
        
        if (response.data.message) {
          this.updateSuccess = response.data.message;
          
          // Refresh pending forecasts
          await this.fetchPendingForecasts();
        }
      } catch (error) {
        console.error('Error updating all pending actuals:', error);
        this.updateError = error.response?.data?.message || 'An error occurred while updating actuals';
      } finally {
        this.updating = false;
      }
    },
    openManualUpdateModal(forecast) {
      this.selectedForecast = forecast;
      this.manualActualQuantity = 0;
      this.manualUpdateError = null;
      this.showModal = true;
    },
    closeModal() {
      this.showModal = false;
      this.selectedForecast = null;
      this.manualActualQuantity = 0;
      this.manualUpdateError = null;
    },
    async submitManualUpdate() {
      if (!this.selectedForecast) {
        return;
      }
      
      if (this.manualActualQuantity < 0) {
        this.manualUpdateError = 'Actual quantity cannot be negative';
        return;
      }
      
      this.manualUpdating = true;
      this.manualUpdateError = null;
      
      try {
        // In a real application, there would be a specific API endpoint for this
        // For this example, we'll use the update endpoint with a custom payload
        
        // Calculate variance
        const variance = this.manualActualQuantity - this.selectedForecast.forecast_quantity;
        
        // Create new version with actuals
        const payload = {
          item_id: this.selectedForecast.item_id,
          customer_id: this.selectedForecast.customer_id,
          forecast_period: this.selectedForecast.forecast_period,
          forecast_quantity: this.selectedForecast.forecast_quantity,
          actual_quantity: this.manualActualQuantity,
          variance: variance,
          forecast_source: this.selectedForecast.forecast_source,
          confidence_level: this.selectedForecast.confidence_level,
          forecast_issue_date: this.selectedForecast.forecast_issue_date,
          is_current_version: true,
          previous_version_id: this.selectedForecast.forecast_id
        };
        
        await axios.put(`/forecasts/${this.selectedForecast.forecast_id}`, payload);
        
        // Close modal and refresh list
        this.closeModal();
        this.updateSuccess = 'Actual quantity updated successfully';
        await this.fetchPendingForecasts();
      } catch (error) {
        console.error('Error manually updating actual:', error);
        this.manualUpdateError = error.response?.data?.message || 'An error occurred while updating the actual quantity';
      } finally {
        this.manualUpdating = false;
      }
    }
  }
};
</script>

<style scoped>
/* Styling yang disempurnakan untuk UpdateActualsPage.vue */

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
  height: 100%;
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

.form-text {
  margin-top: 0.375rem;
  font-size: 0.8rem;
  color: #6c757d;
}

.form-check {
  padding-left: 1.75rem;
}

.form-check-input {
  margin-left: -1.75rem;
}

.form-check-label {
  margin-bottom: 0;
  font-size: 0.95rem;
}

/* Proper spacing for form groups */
.mb-3 {
  margin-bottom: 1.5rem !important;
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

.btn-secondary {
  background-color: #6c757d;
  border-color: #6c757d;
}

.btn-secondary:hover {
  background-color: #5a6268;
  border-color: #545b62;
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

.btn-success {
  background-color: #28a745;
  border-color: #28a745;
}

.btn-success:hover {
  background-color: #218838;
  border-color: #1e7e34;
}

.btn-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
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

.alert-success {
  background-color: rgba(40, 167, 69, 0.1);
  color: #155724;
}

.alert-info {
  background-color: rgba(23, 162, 184, 0.1);
  color: #0c5460;
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

/* Modal styling improved */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.65);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1050;
  backdrop-filter: blur(2px);
}

.modal-container {
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
  width: 100%;
  max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;
  animation: modalFadeIn 0.3s ease-out;
}

@keyframes modalFadeIn {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #eef0f2;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-title {
  margin: 0;
  font-weight: 600;
  font-size: 1.25rem;
  color: #333;
}

.btn-close {
  background: transparent;
  border: none;
  font-size: 1.25rem;
  line-height: 1;
  color: #6c757d;
  cursor: pointer;
  padding: 0.5rem;
  margin: -0.5rem;
}

.btn-close:hover {
  color: #343a40;
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  padding: 1.25rem 1.5rem;
  border-top: 1px solid #eef0f2;
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

.forecast-details {
  background-color: #f8f9fa;
  border-radius: 6px;
  padding: 1.25rem;
  margin-bottom: 1.5rem;
  border: 1px solid #eef0f2;
}

.forecast-details p {
  margin-bottom: 0.5rem;
  font-size: 0.95rem;
}

.forecast-details strong {
  font-weight: 600;
  color: #495057;
}

/* Responsive adjustments */
@media (max-width: 991px) {
  .row .col-lg-6 {
    margin-bottom: 1.5rem;
  }
  
  .row .col-lg-6:last-child {
    margin-bottom: 0;
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
  
  .modal-container {
    width: 95%;
    max-height: 80vh;
  }
  
  .modal-header,
  .modal-footer {
    padding: 1rem;
  }
  
  .modal-body {
    padding: 1.25rem;
  }
}
</style>