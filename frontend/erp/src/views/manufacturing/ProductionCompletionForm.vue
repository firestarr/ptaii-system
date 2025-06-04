<!-- src/views/manufacturing/ProductionCompletionForm.vue -->
<template>
    <div class="production-completion-form">
      <!-- Toast Notifications -->
      <div class="toast-container">
        <div 
          v-for="toast in toasts" 
          :key="toast.id"
          :class="['toast', `toast-${toast.type}`]"
          @click="removeToast(toast.id)"
        >
          <i :class="getToastIcon(toast.type)"></i>
          <span>{{ toast.message }}</span>
          <button class="toast-close" @click.stop="removeToast(toast.id)">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>

      <div class="page-header">
        <h1>Complete Production Order</h1>
        <div class="actions">
          <router-link :to="`/manufacturing/production-orders/${productionId}`" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Production Order
          </router-link>
        </div>
      </div>
  
      <div v-if="loading" class="loading-container">
        <i class="fas fa-spinner fa-spin"></i>
        <span>Loading production order...</span>
      </div>
  
      <div v-else-if="!productionOrder" class="error-container">
        <i class="fas fa-exclamation-triangle"></i>
        <h3>Production Order Not Found</h3>
        <p>The requested production order could not be found or is not ready for completion.</p>
        <router-link to="/manufacturing/production-orders" class="btn btn-primary">
          Back to Production Orders
        </router-link>
      </div>

      <div v-else-if="productionOrder.status !== 'In Progress'" class="error-container">
        <i class="fas fa-info-circle"></i>
        <h3>Production Order Not Ready</h3>
        <p>This production order is in <strong>{{ productionOrder.status }}</strong> status.</p>
        <p>Only production orders in "In Progress" status can be completed.</p>
        <div class="status-help">
          <div v-if="productionOrder.status === 'Draft'" class="help-item">
            <strong>Next Step:</strong> Issue materials first, then start production.
          </div>
          <div v-else-if="productionOrder.status === 'Materials Issued'" class="help-item">
            <strong>Next Step:</strong> Start production first.
          </div>
          <div v-else-if="productionOrder.status === 'Completed'" class="help-item">
            <strong>Status:</strong> This production order is already completed.
          </div>
          <div v-else-if="productionOrder.status === 'Cancelled'" class="help-item">
            <strong>Status:</strong> This production order has been cancelled.
          </div>
        </div>
        <router-link :to="`/manufacturing/production-orders/${productionId}`" class="btn btn-primary">
          Go to Production Order
        </router-link>
      </div>
  
      <div v-else class="detail-content">
        <div class="card detail-card">
          <div class="card-header">
            <h2>Production Order Information</h2>
            <div class="status-badge status-in-progress">
              {{ productionOrder.status }}
            </div>
          </div>
          <div class="card-body">
            <div class="detail-grid">
              <div class="detail-item">
                <div class="detail-label">Production #</div>
                <div class="detail-value">{{ productionOrder.production_number }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Production Date</div>
                <div class="detail-value">{{ formatDate(productionOrder.production_date) }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Work Order</div>
                <div class="detail-value">{{ workOrder?.wo_number || 'N/A' }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Product</div>
                <div class="detail-value">
                  <div class="product-info">
                    <div class="product-name">{{ workOrder?.item?.name || 'N/A' }}</div>
                    <div class="product-code">{{ workOrder?.item?.item_code || '' }}</div>
                  </div>
                </div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Planned Quantity</div>
                <div class="detail-value">{{ productionOrder.planned_quantity }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Current Status</div>
                <div class="detail-value">Ready for completion</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Materials Summary Card -->
        <div class="card detail-card" v-if="materialSummary">
          <div class="card-header">
            <h2>Materials Already Issued</h2>
          </div>
          <div class="card-body">
            <div class="alert alert-info">
              <i class="fas fa-info-circle"></i>
              <div>
                <strong>Materials Status:</strong> All required materials have been issued to production.
                Total value: <strong>${{ materialSummary.total_actual_value?.toFixed(2) || '0.00' }}</strong>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table material-summary-table">
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Quantity Issued</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="material in materialSummary.material_details" :key="material.consumption_id">
                    <td>
                      <div class="item-info">
                        <div class="item-name">{{ material.item_name }}</div>
                        <div class="item-code">{{ material.item_code }}</div>
                      </div>
                    </td>
                    <td>{{ material.actual_quantity }}</td>
                    <td>
                      <span class="status-badge status-issued">
                        {{ material.status }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Completion Form -->
        <form @submit.prevent="completeProduction" class="card completion-form">
          <div class="card-header">
            <h2>Production Completion</h2>
          </div>
          <div class="card-body">
            <div class="alert alert-warning">
              <i class="fas fa-exclamation-triangle"></i>
              <div>
                <strong>Important:</strong> Completing production will:
                <ul>
                  <li>Move finished goods from WIP to Finished Goods warehouse</li>
                  <li>Record the actual production quantity</li>
                  <li>Change status to "Completed"</li>
                  <li>Update work order progress</li>
                </ul>
                This action cannot be undone.
              </div>
            </div>
  
            <div class="form-section">
              <h3>Production Results</h3>
              
              <div class="form-row">
                <div class="form-group">
                  <label for="actual_quantity">Actual Quantity Produced *</label>
                  <input 
                    type="number" 
                    id="actual_quantity" 
                    v-model="form.actual_quantity"
                    :class="{ 'error': errors.actual_quantity }"
                    min="0.01" 
                    step="0.01" 
                    required
                    @input="validateActualQuantity"
                  >
                  <div v-if="errors.actual_quantity" class="error-message">
                    {{ errors.actual_quantity }}
                  </div>
                  <div class="input-hint">
                    Planned: {{ productionOrder.planned_quantity }} | 
                    Efficiency: {{ calculateEfficiency() }}%
                  </div>
                </div>

                <div class="form-group">
                  <label for="quality_notes">Quality Notes</label>
                  <textarea 
                    id="quality_notes" 
                    v-model="form.quality_notes"
                    rows="4"
                    placeholder="Enter quality observations, defects noted, or production remarks"
                  ></textarea>
                  <div class="input-hint">
                    Optional: Record any quality observations or production notes
                  </div>
                </div>
              </div>

              <!-- Production Metrics Display -->
              <div class="metrics-section" v-if="form.actual_quantity > 0">
                <h4>Production Metrics Preview</h4>
                <div class="metrics-grid">
                  <div class="metric-item">
                    <div class="metric-label">Production Efficiency</div>
                    <div class="metric-value" :class="getEfficiencyClass()">
                      {{ calculateEfficiency() }}%
                    </div>
                  </div>
                  <div class="metric-item">
                    <div class="metric-label">Quantity Variance</div>
                    <div class="metric-value" :class="getVarianceClass()">
                      {{ getQuantityVariance() }}
                    </div>
                  </div>
                  <div class="metric-item">
                    <div class="metric-label">Materials Used</div>
                    <div class="metric-value">
                      ${{ materialSummary?.total_actual_value?.toFixed(2) || '0.00' }}
                    </div>
                  </div>
                  <div class="metric-item">
                    <div class="metric-label">Unit Cost</div>
                    <div class="metric-value">
                      ${{ calculateUnitCost() }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="card-footer">
            <button type="button" class="btn btn-secondary" @click="cancel">Cancel</button>
            <button 
              type="submit" 
              class="btn btn-success" 
              :disabled="saving || hasValidationErrors || !isActualQuantityValid"
            >
              <i v-if="saving" class="fas fa-spinner fa-spin"></i>
              {{ saving ? 'Completing Production...' : 'Complete Production Order' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </template>
  
  <script>
import axios from 'axios';

export default {
  name: 'ProductionCompletionForm',
  props: {
    productionId: {
      type: [Number, String],
      required: true
    }
  },
  data() {
    return {
      productionOrder: null,
      workOrder: null,
      materialSummary: null,
      form: {
        actual_quantity: 0,
        quality_notes: ''
      },
      loading: true,
      saving: false,
      errors: {},
      // Toast system
      toasts: [],
      toastIdCounter: 0
    };
  },
  computed: {
    hasValidationErrors() {
      return Object.keys(this.errors).length > 0;
    },
    
    isActualQuantityValid() {
      const actualQty = parseFloat(this.form.actual_quantity) || 0;
      return actualQty > 0;
    }
  },
  created() {
    this.fetchProductionOrder();
  },
  methods: {
    // Toast Methods
    showToast(message, type = 'info', duration = 5000) {
      const toast = {
        id: this.toastIdCounter++,
        message,
        type,
        duration
      };
      
      this.toasts.push(toast);
      
      // Auto remove after duration
      setTimeout(() => {
        this.removeToast(toast.id);
      }, duration);
    },

    removeToast(id) {
      this.toasts = this.toasts.filter(toast => toast.id !== id);
    },

    getToastIcon(type) {
      switch (type) {
        case 'success':
          return 'fas fa-check-circle';
        case 'error':
          return 'fas fa-exclamation-circle';
        case 'warning':
          return 'fas fa-exclamation-triangle';
        default:
          return 'fas fa-info-circle';
      }
    },

    // Helper untuk toast
    showError(msg) { this.showToast(msg, 'error'); },
    showSuccess(msg) { this.showToast(msg, 'success'); },
    showWarning(msg) { this.showToast(msg, 'warning'); },
    showInfo(msg) { this.showToast(msg, 'info'); },

    validateActualQuantity() {
      const actualQty = parseFloat(this.form.actual_quantity) || 0;
      
      if (actualQty <= 0) {
        this.errors.actual_quantity = 'Actual quantity must be greater than 0';
      } else {
        delete this.errors.actual_quantity;
      }
    },

    async fetchProductionOrder() {
      this.loading = true;
      try {
        const resp = await axios.get(`/production-orders/${this.productionId}`);
        this.productionOrder = resp.data.data || resp.data;

        // Initialize form with planned quantity
        this.form.actual_quantity = this.productionOrder.planned_quantity;
        this.validateActualQuantity();

        // Fetch work order details
        if (this.productionOrder.wo_id) {
          await this.fetchWorkOrder(this.productionOrder.wo_id);
        }

        // Fetch material summary
        await this.fetchMaterialSummary();

      } catch (err) {
        console.error('Error fetching production order:', err);
        this.showError('Failed to load production order');
        this.productionOrder = null;
      } finally {
        this.loading = false;
      }
    },

    async fetchWorkOrder(workOrderId) {
      try {
        const resp = await axios.get(`/work-orders/${workOrderId}`);
        this.workOrder = resp.data.data || resp.data;
      } catch (err) {
        console.error('Error fetching work order:', err);
        this.showError('Failed to load work order details');
      }
    },

    async fetchMaterialSummary() {
      try {
        const resp = await axios.get(`/production-orders/${this.productionId}/material-status`);
        this.materialSummary = resp.data.data || resp.data;
      } catch (err) {
        console.error('Error fetching material summary:', err);
        // Don't show error as it's not critical
      }
    },

    formatDate(d) {
      return d ? new Date(d).toLocaleDateString() : 'N/A';
    },

    calculateEfficiency() {
      const planned = parseFloat(this.productionOrder?.planned_quantity) || 0;
      const actual = parseFloat(this.form.actual_quantity) || 0;
      
      if (planned <= 0) return '0.00';
      
      const efficiency = (actual / planned) * 100;
      return efficiency.toFixed(2);
    },

    getQuantityVariance() {
      const planned = parseFloat(this.productionOrder?.planned_quantity) || 0;
      const actual = parseFloat(this.form.actual_quantity) || 0;
      const variance = actual - planned;
      
      if (variance === 0) return '0';
      return variance > 0 ? `+${variance.toFixed(2)}` : variance.toFixed(2);
    },

    calculateUnitCost() {
      const actual = parseFloat(this.form.actual_quantity) || 0;
      const totalCost = parseFloat(this.materialSummary?.total_actual_value) || 0;
      
      if (actual <= 0) return '0.00';
      
      const unitCost = totalCost / actual;
      return unitCost.toFixed(2);
    },

    getEfficiencyClass() {
      const efficiency = parseFloat(this.calculateEfficiency());
      if (efficiency >= 95) return 'metric-excellent';
      if (efficiency >= 85) return 'metric-good';
      if (efficiency >= 75) return 'metric-fair';
      return 'metric-poor';
    },

    getVarianceClass() {
      const planned = parseFloat(this.productionOrder?.planned_quantity) || 0;
      const actual = parseFloat(this.form.actual_quantity) || 0;
      const variance = actual - planned;
      
      if (Math.abs(variance) / planned <= 0.05) return 'metric-good'; // Within 5%
      if (variance > 0) return 'metric-excellent'; // Over production
      return 'metric-poor'; // Under production
    },

    async completeProduction() {
      try {
        this.errors = {};
        this.validateActualQuantity();

        // Validasi actual quantity tidak boleh 0
        if (!this.isActualQuantityValid) {
          this.showError('Actual quantity must be greater than 0');
          return;
        }

        if (this.hasValidationErrors) {
          this.showError('Please correct the errors before completing the production order');
          return;
        }

        this.saving = true;
        await axios.post(`/production-orders/${this.productionId}/complete`, this.form);
        this.showSuccess('Production order completed successfully');
        
        // Redirect setelah 1 detik agar user bisa lihat success message
        setTimeout(() => {
          this.$router.push(`/manufacturing/production-orders/${this.productionId}`);
        }, 1000);
        
      } catch (err) {
        console.error('Error completing production order:', err);

        const errorData = err.response?.data || {};

        // Field validation errors
        if (errorData.errors && typeof errorData.errors === 'object') {
          this.errors = errorData.errors;
          this.showError('Please correct the errors before completing');
          return;
        }

        // General message
        if (typeof errorData.message === 'string') {
          this.showError(errorData.message);
          return;
        }

        // Fallback
        this.showError('Failed to complete production order');
      } finally {
        this.saving = false;
      }
    },

    cancel() {
      this.$router.push(`/manufacturing/production-orders/${this.productionId}`);
    }
  },

  watch: {    
    'form.actual_quantity'() {
      this.validateActualQuantity();
    }
  }
};
</script>
  
<style scoped>
/* Toast Styles */
.toast-container {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-width: 400px;
}

.toast {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  cursor: pointer;
  animation: toastSlideIn 0.3s ease-out;
  position: relative;
  word-wrap: break-word;
}

.toast i {
  margin-right: 12px;
  font-size: 18px;
  flex-shrink: 0;
}

.toast span {
  flex-grow: 1;
  font-weight: 500;
}

.toast-close {
  background: none;
  border: none;
  margin-left: 12px;
  cursor: pointer;
  opacity: 0.7;
  padding: 4px;
  border-radius: 4px;
  transition: opacity 0.2s;
}

.toast-close:hover {
  opacity: 1;
  background-color: rgba(0, 0, 0, 0.1);
}

.toast-success {
  background-color: #10b981;
  color: white;
}

.toast-error {
  background-color: #ef4444;
  color: white;
}

.toast-warning {
  background-color: #f59e0b;
  color: white;
}

.toast-info {
  background-color: #3b82f6;
  color: white;
}

@keyframes toastSlideIn {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

/* Main styles */
.production-completion-form {
  padding: 1rem;
  max-width: 1000px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.page-header h1 {
  margin: 0;
  color: #2c3e50;
}

.actions {
  display: flex;
  gap: 0.5rem;
}

.loading-container,
.error-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  text-align: center;
}

.loading-container i,
.error-container i {
  font-size: 3rem;
  margin-bottom: 1rem;
  color: #bdc3c7;
}

.error-container i {
  color: #e74c3c;
}

.status-help {
  margin: 1rem 0;
  padding: 1rem;
  background: #f8f9fa;
  border-radius: 6px;
  text-align: left;
}

.help-item {
  margin-bottom: 0.5rem;
}

.detail-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  background: #f8f9fa;
  border-bottom: 1px solid #e9ecef;
}

.card-header h2 {
  margin: 0;
  font-size: 1.25rem;
  color: #2c3e50;
}

.card-body {
  padding: 1.5rem;
}

.card-footer {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding: 1rem 1.5rem;
  background: #f8f9fa;
  border-top: 1px solid #e9ecef;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 1rem;
}

.detail-item {
  margin-bottom: 0.5rem;
}

.detail-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: #6c757d;
  margin-bottom: 0.25rem;
}

.detail-value {
  font-size: 1rem;
  color: #2c3e50;
  font-weight: 500;
}

.product-info {
  display: flex;
  flex-direction: column;
}

.product-name {
  font-weight: 600;
  color: #2c3e50;
}

.product-code {
  font-size: 0.875rem;
  color: #6c757d;
}

.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.status-in-progress {
  background: #e3f2fd;
  color: #1976d2;
}

.status-issued {
  background: #e8f5e8;
  color: #2e7d32;
}

.alert {
  display: flex;
  align-items: flex-start;
  padding: 1rem;
  border-radius: 6px;
  margin-bottom: 1.5rem;
}

.alert i {
  margin-right: 0.75rem;
  font-size: 1.25rem;
  margin-top: 0.125rem;
  flex-shrink: 0;
}

.alert-info {
  background: #e3f2fd;
  color: #1976d2;
  border: 1px solid #bbdefb;
}

.alert-warning {
  background: #fff3e0;
  color: #f57c00;
  border: 1px solid #ffcc02;
}

.alert ul {
  margin: 0.5rem 0 0 0;
  padding-left: 1.5rem;
}

.form-section {
  margin-bottom: 2rem;
}

.form-section h3 {
  font-size: 1.125rem;
  color: #2c3e50;
  margin-bottom: 1rem;
  border-bottom: 2px solid #e9ecef;
  padding-bottom: 0.5rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 2rem;
  margin-bottom: 1.5rem;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  font-size: 0.875rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #2c3e50;
}

.form-group input,
.form-group textarea {
  padding: 0.75rem;
  border: 2px solid #e9ecef;
  border-radius: 6px;
  font-size: 0.875rem;
  transition: border-color 0.2s;
}

.form-group input:focus,
.form-group textarea:focus {
  border-color: #3498db;
  outline: none;
  box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.form-group .error {
  border-color: #e74c3c;
}

.error-message {
  color: #e74c3c;
  font-size: 0.75rem;
  margin-top: 0.25rem;
  font-weight: 500;
}

.input-hint {
  font-size: 0.75rem;
  color: #6c757d;
  margin-top: 0.25rem;
}

.table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 1rem;
}

.table th,
.table td {
  padding: 0.75rem;
  text-align: left;
  border-bottom: 1px solid #e9ecef;
}

.table th {
  background: #f8f9fa;
  font-weight: 600;
  color: #495057;
  font-size: 0.875rem;
}

.material-summary-table {
  font-size: 0.875rem;
}

.item-info {
  display: flex;
  flex-direction: column;
}

.item-name {
  font-weight: 500;
  color: #2c3e50;
}

.item-code {
  font-size: 0.75rem;
  color: #6c757d;
}

.metrics-section {
  margin-top: 2rem;
  padding: 1.5rem;
  background: #f8f9fa;
  border-radius: 8px;
  border: 1px solid #e9ecef;
}

.metrics-section h4 {
  margin: 0 0 1rem 0;
  color: #2c3e50;
  font-size: 1rem;
}

.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.metric-item {
  text-align: center;
  padding: 1rem;
  background: white;
  border-radius: 6px;
  border: 1px solid #e9ecef;
}

.metric-label {
  font-size: 0.75rem;
  color: #6c757d;
  font-weight: 500;
  margin-bottom: 0.5rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.metric-value {
  font-size: 1.5rem;
  font-weight: 700;
}

.metric-excellent {
  color: #27ae60;
}

.metric-good {
  color: #2980b9;
}

.metric-fair {
  color: #f39c12;
}

.metric-poor {
  color: #e74c3c;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.75rem 1.5rem;
  border-radius: 6px;
  font-weight: 500;
  text-decoration: none;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
  gap: 0.5rem;
  font-size: 0.875rem;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.btn-primary {
  background: #3498db;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #2980b9;
}

.btn-secondary {
  background: #95a5a6;
  color: white;
}

.btn-secondary:hover:not(:disabled) {
  background: #7f8c8d;
}

.btn-success {
  background: #27ae60;
  color: white;
}

.btn-success:hover:not(:disabled) {
  background: #229954;
}

@media (max-width: 768px) {
  .toast-container {
    top: 10px;
    right: 10px;
    left: 10px;
    max-width: none;
  }

  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }

  .actions {
    width: 100%;
  }

  .detail-grid {
    grid-template-columns: 1fr;
  }

  .form-row {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .metrics-grid {
    grid-template-columns: 1fr;
  }

  .table {
    font-size: 0.75rem;
  }

  .card-footer {
    flex-direction: column;
  }

  .btn {
    width: 100%;
    justify-content: center;
  }
}
</style>