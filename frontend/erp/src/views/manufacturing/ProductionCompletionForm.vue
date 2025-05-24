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
        <p>The requested production order could not be found or is not in a valid state for completion.</p>
        <router-link to="/manufacturing/production-orders" class="btn btn-primary">
          Back to Production Orders
        </router-link>
      </div>
  
      <div v-else class="detail-content">
        <div class="card detail-card">
          <div class="card-header">
            <h2>Production Order Information</h2>
            <div class="status-badge" :class="getStatusClass(productionOrder.status)">
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
                <div class="detail-value">{{ workOrder?.product?.name || 'N/A' }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Planned Quantity</div>
                <div class="detail-value">{{ productionOrder.planned_quantity }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Current Status</div>
                <div class="detail-value">{{ productionOrder.status }}</div>
              </div>
            </div>
          </div>
        </div>
  
        <form @submit.prevent="completeProduction" class="card completion-form">
          <div class="card-header">
            <h2>Production Completion</h2>
          </div>
          <div class="card-body">
            <div class="alert alert-info">
              <i class="fas fa-info-circle"></i>
              <div>
                <strong>Important:</strong> Completing a production order will:
                <ul>
                  <li>Update inventory for the produced item</li>
                  <li>Consume materials from inventory</li>
                  <li>Record actual consumption and production quantities</li>
                  <li>Change the status to "Completed"</li>
                </ul>
                This action cannot be undone.
              </div>
            </div>
  
            <div class="form-row">
              <div class="form-group full-width">
                <label for="actual_quantity">Actual Produced Quantity</label>
                <input 
                  type="number" 
                  id="actual_quantity" 
                  v-model="form.actual_quantity"
                  :class="{ 'error': errors.actual_quantity }"
                  min="0" 
                  step="0.01" 
                  required
                >
                <div v-if="errors.actual_quantity" class="error-message">
                  {{ errors.actual_quantity }}
                </div>
                <div class="input-hint">
                  Enter the actual quantity of {{ workOrder?.product?.name || 'product' }} produced
                </div>
              </div>
            </div>
  
            <div class="form-section">
              <h3>Material Consumption</h3>
              <p class="section-description">
                Verify and update the actual quantities of materials consumed during production.
              </p>
              
              <div v-if="consumptions.length === 0" class="empty-state">
                <i class="fas fa-box-open"></i>
                <p>No material consumption records found. Add materials before completing the production order.</p>
                <router-link 
                  :to="`/manufacturing/production-orders/${productionId}/consumption/add`" 
                  class="btn btn-primary">
                  <i class="fas fa-plus"></i> Add Material
                </router-link>
              </div>
              
              <div v-else class="table-responsive">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Item</th>
                      <th>Warehouse</th>
                      <th>Planned Quantity</th>
                      <th>Actual Quantity</th>
                      <th>Available Stock</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(consumption, index) in consumptions" :key="consumption.consumption_id">
                      <td>
                        <div class="item-name">{{ consumption.item?.name || 'Unknown Item' }}</div>
                        <div class="item-code">{{ consumption.item?.item_code || '' }}</div>
                      </td>
                      <td>{{ consumption.warehouse?.name || 'N/A' }}</td>
                      <td>{{ consumption.planned_quantity }}</td>
                      <td>
                        <input 
                          type="number" 
                          v-model="form.consumptions[index].actual_quantity" 
                          min="0" 
                          step="0.01"
                          :class="{ 'error': getConsumptionError(index) }"
                        >
                        <div v-if="getConsumptionError(index)" class="error-message">
                          {{ getConsumptionError(index) }}
                        </div>
                      </td>
                      <td>
                        <div 
                          class="stock-amount" 
                          :class="{ 'stock-warning': isStockInsufficient(consumption, index) }"
                        >
                          {{ getAvailableStock(consumption) }}
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
  
            <div class="form-row">
              <div class="form-group full-width">
                <label for="completion_notes">Completion Notes</label>
                <textarea 
                  id="completion_notes" 
                  v-model="form.notes"
                  rows="3"
                  placeholder="Enter any notes or observations about this production run"
                ></textarea>
              </div>
            </div>
          </div>
          
          <div class="card-footer">
            <button type="button" class="btn btn-secondary" @click="cancel">Cancel</button>
            <button 
              type="submit" 
              class="btn btn-success" 
              :disabled="saving || hasValidationErrors || consumptions.length === 0"
            >
              <i v-if="saving" class="fas fa-spinner fa-spin"></i>
              {{ saving ? 'Completing...' : 'Complete Production Order' }}
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
      consumptions: [],
      itemStocks: {},
      form: {
        actual_quantity: 0,
        consumptions: [],
        notes: '',
        status: 'Completed'
      },
      loading: true,
      saving: false,
      errors: {},
      consumptionErrors: [],
      // Toast system
      toasts: [],
      toastIdCounter: 0
    };
  },
  computed: {
    hasValidationErrors() {
      return (
        Object.keys(this.errors).length > 0 ||
        this.consumptionErrors.some(err => err !== null)
      );
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
    showError(msg) {
      this.showToast(msg, 'error');
    },
    
    showSuccess(msg) {
      this.showToast(msg, 'success');
    },

    showWarning(msg) {
      this.showToast(msg, 'warning');
    },

    showInfo(msg) {
      this.showToast(msg, 'info');
    },

    async fetchProductionOrder() {
      this.loading = true;
      try {
        const resp = await axios.get(`/production-orders/${this.productionId}`);
        this.productionOrder = resp.data.data || resp.data;

        if (this.productionOrder.status !== 'In Progress') {
          this.showError(
            'This production order cannot be completed because it is not in progress'
          );
          this.productionOrder = null;
          return;
        }

        this.form.actual_quantity = this.productionOrder.planned_quantity;

        if (this.productionOrder.production_consumptions) {
          this.consumptions = this.productionOrder.production_consumptions;
          this.form.consumptions = this.consumptions.map(c => ({
            consumption_id: c.consumption_id,
            actual_quantity: c.actual_quantity || c.planned_quantity
          }));
          this.consumptionErrors = this.consumptions.map(() => null);
          await this.fetchItemStocks();
        }

        if (this.productionOrder.wo_id) {
          await this.fetchWorkOrder(this.productionOrder.wo_id);
        }
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

    async fetchItemStocks() {
      try {
        const requests = this.consumptions.map(c =>
          axios.get(`/item-stocks/item/${c.item_id}`, {
            params: { warehouse_id: c.warehouse_id }
          })
        );
        const responses = await Promise.all(requests);

        this.consumptions.forEach((c, i) => {
          const key = `${c.item_id}-${c.warehouse_id}`;
          this.itemStocks[key] = responses[i].data.data || responses[i].data;
        });

        this.validateConsumptions();
      } catch (err) {
        console.error('Error fetching item stocks:', err);
        this.showError('Failed to load stock information');
      }
    },

    getAvailableStock(c) {
      const key = `${c.item_id}-${c.warehouse_id}`;
      const stock = this.itemStocks[key];
      if (!stock) return 'Unknown';
      const ws = (stock.warehouse_stocks || []).find(
        w => w.warehouse_id === c.warehouse_id
      );
      return ws ? ws.available_quantity || 0 : 0;
    },

    isStockInsufficient(c, idx) {
      const key = `${c.item_id}-${c.warehouse_id}`;
      const stock = this.itemStocks[key];
      if (!stock) return false;
      const ws = (stock.warehouse_stocks || []).find(
        w => w.warehouse_id === c.warehouse_id
      );
      if (!ws) return false;
      const avail = parseFloat(ws.available_quantity) || 0;
      const actual = parseFloat(this.form.consumptions[idx].actual_quantity) || 0;
      return actual > avail;
    },

    validateConsumptions() {
      if (!this.consumptions || !this.form.consumptions) {
        this.consumptionErrors = [];
        return;
      }
      this.consumptionErrors = this.consumptions.map((c, i) => {
        const entry = this.form.consumptions[i];
        if (!entry) return 'Invalid consumption data';
        const qty = parseFloat(entry.actual_quantity) || 0;
        if (qty < 0) return 'Actual quantity cannot be negative';
        if (this.isStockInsufficient(c, i)) return 'Insufficient stock available';
        return null;
      });
    },

    getConsumptionError(idx) {
      return this.consumptionErrors[idx] || null;
    },

    formatDate(d) {
      return d ? new Date(d).toLocaleDateString() : 'N/A';
    },

    getStatusClass(status) {
      switch (status) {
        case 'Draft':
          return 'status-draft';
        case 'In Progress':
          return 'status-in-progress';
        case 'Completed':
          return 'status-completed';
        case 'Cancelled':
          return 'status-cancelled';
        default:
          return '';
      }
    },

    async completeProduction() {
      try {
        this.errors = {};
        this.validateConsumptions();

        if (this.hasValidationErrors) {
          this.showError('Please correct the errors before completing the production order');
          return;
        }
        if (this.consumptions.length === 0) {
          this.showError('Cannot complete production order without any material consumption');
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

        // Stock-specific errors
        if (Array.isArray(errorData.errors?.stock)) {
          errorData.errors.stock.forEach(msg => this.showError(msg));
          return;
        }

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
    'form.consumptions': {
      handler() {
        this.validateConsumptions();
      },
      deep: true
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

/* Existing styles remain the same */
.production-completion-form {
  padding: 1rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.form-group {
  flex: 1;
  min-width: 250px;
}

.full-width {
  width: 100%;
}

.form-group label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  margin-bottom: 0.25rem;
  color: var(--gray-700);
}

.form-group input,
.form-group textarea,
.form-group select {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid var(--gray-300);
  border-radius: 0.375rem;
  font-size: 0.875rem;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
  outline: none;
}

.form-group .input-hint {
  font-size: 0.75rem;
  color: var(--gray-500);
  margin-top: 0.25rem;
}

.error {
  border-color: var(--danger-color) !important;
}

.error-message {
  color: var(--danger-color);
  font-size: 0.75rem;
  margin-top: 0.25rem;
}

.form-section {
  margin-top: 2rem;
}

.form-section h3 {
  font-size: 1rem;
  font-weight: 600;
  margin-bottom: 0.75rem;
  color: var(--gray-700);
}

.section-description {
  font-size: 0.875rem;
  color: var(--gray-600);
  margin-bottom: 1rem;
}

.alert {
  display: flex;
  align-items: flex-start;
  padding: 1rem;
  border-radius: 0.375rem;
  margin-bottom: 1.5rem;
}

.alert i {
  margin-right: 0.75rem;
  font-size: 1.25rem;
  margin-top: 0.125rem;
}

.alert-info {
  background-color: #dbeafe;
  color: #1e40af;
}

.table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 1.5rem;
}

.table th,
.table td {
  padding: 0.75rem;
  border-bottom: 1px solid var(--gray-200);
  text-align: left;
}

.table th {
  font-weight: 500;
  color: var(--gray-600);
}

.item-name {
  font-weight: 500;
}

.item-code {
  font-size: 0.75rem;
  color: var(--gray-500);
}

.stock-amount {
  font-weight: 500;
}

.stock-warning {
  color: var(--danger-color);
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 2rem;
  text-align: center;
  color: var(--gray-500);
}

.empty-state i {
  font-size: 2rem;
  margin-bottom: 1rem;
}

.empty-state p {
  margin-bottom: 1rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.2s, border-color 0.2s;
  border: none;
  gap: 0.5rem;
}

.btn-primary {
  background-color: var(--primary-color);
  color: white;
}

.btn-primary:hover {
  background-color: var(--primary-dark);
}

.btn-secondary {
  background-color: var(--gray-200);
  color: var(--gray-800);
}

.btn-secondary:hover {
  background-color: var(--gray-300);
}

.btn-success {
  background-color: var(--success-color);
  color: white;
}

.btn-success:hover {
  background-color: #047857;
}

.btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
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
    justify-content: flex-start;
    flex-wrap: wrap;
  }
  
  .form-row {
    flex-direction: column;
    gap: 1rem;
  }
  
  .table-responsive {
    overflow-x: auto;
  }
  
  .detail-grid {
    grid-template-columns: 1fr;
  }
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
  color: var(--gray-300);
}

.error-container i {
  color: var(--danger-color);
}

.detail-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.completion-form {
  margin-top: 1rem;
}

.card {
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  background-color: var(--gray-50);
  border-bottom: 1px solid var(--gray-200);
}

.card-header h2 {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 600;
}

.card-body {
  padding: 1.5rem;
}

.card-footer {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding: 1rem 1.5rem;
  background-color: var(--gray-50);
  border-top: 1px solid var(--gray-200);
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1rem;
}

.detail-item {
  margin-bottom: 0.5rem;
}

.detail-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--gray-500);
  margin-bottom: 0.25rem;
}

.detail-value {
  font-size: 1rem;
  color: var(--gray-800);
}

.status-badge {
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
}

.status-draft {
  background-color: var(--gray-200);
  color: var(--gray-700);
}

.status-in-progress {
  background-color: #bfdbfe;
  color: #1e40af;
}

.status-completed {
  background-color: #bbf7d0;
  color: #166534;
}

.status-cancelled {
  background-color: #fecaca;
  color: #b91c1c;
}

.form-row {
  display: flex;
  flex-wrap: wrap;
  gap: 1.5rem;
}
</style>