<!-- src/views/manufacturing/ProductionOrderDetail.vue -->
<template>
    <div class="production-order-detail">
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
        <h1>Production Order Details</h1>
        <div class="actions">
          <router-link to="/manufacturing/production-orders" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
          </router-link>
          
          <!-- Status-based Action Buttons -->
          <template v-if="productionOrder">
            <!-- Draft Status Actions -->
            <template v-if="productionOrder.status === 'Draft'">
              <router-link 
                :to="`/manufacturing/production-orders/${productionId}/edit`" 
                class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit
              </router-link>
              <button 
                @click="confirmStartProduction" 
                class="btn btn-success">
                <i class="fas fa-play"></i> Start Production
              </button>
              <button 
                @click="confirmDelete" 
                class="btn btn-danger">
                <i class="fas fa-trash"></i> Delete
              </button>
            </template>
            
            <!-- In Progress Status Actions -->
            <template v-if="productionOrder.status === 'In Progress'">
              <router-link 
                :to="`/manufacturing/production-orders/${productionId}/complete`" 
                class="btn btn-success">
                <i class="fas fa-check"></i> Complete Production
              </router-link>
              <button 
                @click="confirmCancelProduction" 
                class="btn btn-warning">
                <i class="fas fa-times"></i> Cancel Production
              </button>
            </template>
            
            <!-- Completed Status Actions -->
            <template v-if="productionOrder.status === 'Completed'">
              <button 
                @click="printProductionOrder" 
                class="btn btn-info">
                <i class="fas fa-print"></i> Print
              </button>
            </template>
            
            <!-- Cancelled Status Actions -->
            <template v-if="productionOrder.status === 'Cancelled'">
              <button 
                @click="confirmReactivate" 
                class="btn btn-primary">
                <i class="fas fa-undo"></i> Reactivate
              </button>
            </template>
          </template>
        </div>
      </div>
  
      <div v-if="loading" class="loading-container">
        <i class="fas fa-spinner fa-spin"></i>
        <span>Loading production order details...</span>
      </div>
  
      <div v-else-if="!productionOrder" class="error-container">
        <i class="fas fa-exclamation-triangle"></i>
        <h3>Production Order Not Found</h3>
        <p>The requested production order could not be found.</p>
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
                <div class="detail-value">
                  <router-link :to="`/manufacturing/work-orders/${productionOrder.wo_id}`">
                    {{ workOrder?.wo_number || 'N/A' }}
                  </router-link>
                </div>
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
                <div class="detail-label">Actual Quantity</div>
                <div class="detail-value">{{ productionOrder.actual_quantity || '0' }}</div>
              </div>
            </div>
          </div>
        </div>
  
        <div class="card detail-card" v-if="workOrder">
          <div class="card-header">
            <h2>Work Order Information</h2>
          </div>
          <div class="card-body">
            <div class="detail-grid">
              <div class="detail-item">
                <div class="detail-label">Work Order #</div>
                <div class="detail-value">{{ workOrder.wo_number }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Work Order Date</div>
                <div class="detail-value">{{ formatDate(workOrder.wo_date) }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">BOM</div>
                <div class="detail-value">
                  <router-link v-if="workOrder.bom_id" :to="`/manufacturing/boms/${workOrder.bom_id}`">
                    {{ workOrder.bom?.bom_code || 'N/A' }}
                  </router-link>
                  <span v-else>N/A</span>
                </div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Routing</div>
                <div class="detail-value">
                  <router-link v-if="workOrder.routing_id" :to="`/manufacturing/routings/${workOrder.routing_id}`">
                    {{ workOrder.routing?.routing_code || 'N/A' }}
                  </router-link>
                  <span v-else>N/A</span>
                </div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Planned Start</div>
                <div class="detail-value">{{ formatDate(workOrder.planned_start_date) }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Planned End</div>
                <div class="detail-value">{{ formatDate(workOrder.planned_end_date) }}</div>
              </div>
            </div>
          </div>
        </div>
  
        <div class="card detail-card" v-if="consumptions.length > 0">
          <div class="card-header">
            <h2>Material Consumption</h2>
            <div class="header-actions" v-if="productionOrder.status === 'In Progress'">
              <router-link 
                :to="`/manufacturing/production-orders/${productionId}/consumption/add`" 
                class="btn btn-sm btn-primary">
                <i class="fas fa-plus"></i> Add Material
              </router-link>
            </div>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Warehouse</th>
                    <th>Planned Quantity</th>
                    <th>Actual Quantity</th>
                    <th>Variance</th>
                    <th v-if="productionOrder.status === 'In Progress'">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="consumption in consumptions" :key="consumption.consumption_id">
                    <td>
                      <div class="item-name">{{ consumption.item?.name || 'Unknown Item' }}</div>
                      <div class="item-code">{{ consumption.item?.item_code || '' }}</div>
                    </td>
                    <td>{{ consumption.warehouse?.name || 'N/A' }}</td>
                    <td>{{ consumption.planned_quantity }}</td>
                    <td>{{ consumption.actual_quantity || '0' }}</td>
                    <td>
                      <div class="variance" :class="getVarianceClass(consumption)">
                        {{ getVariance(consumption) }}
                      </div>
                    </td>
                    <td v-if="productionOrder.status === 'In Progress'">
                      <router-link 
                        :to="`/manufacturing/production-orders/${productionId}/consumption/${consumption.consumption_id}/edit`" 
                        class="btn btn-sm btn-primary">
                        <i class="fas fa-edit"></i>
                      </router-link>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
  
        <div class="card detail-card" v-else>
          <div class="card-header">
            <h2>Material Consumption</h2>
            <div class="header-actions" v-if="productionOrder.status === 'In Progress'">
              <router-link 
                :to="`/manufacturing/production-orders/${productionId}/consumption/add`" 
                class="btn btn-sm btn-primary">
                <i class="fas fa-plus"></i> Add Material
              </router-link>
            </div>
          </div>
          <div class="card-body">
            <div class="empty-state">
              <i class="fas fa-box-open"></i>
              <p>No material consumption records found.</p>
              <p v-if="productionOrder.status === 'In Progress'">
                Add materials to track consumption during production.
              </p>
            </div>
          </div>
        </div>
  
        <div v-if="productionOrder.status === 'Completed'" class="card detail-card">
          <div class="card-header">
            <h2>Production Summary</h2>
          </div>
          <div class="card-body">
            <div class="summary-stats">
              <div class="stat-item">
                <div class="stat-label">Efficiency</div>
                <div class="stat-value">{{ calculateEfficiency() }}%</div>
              </div>
              <div class="stat-item">
                <div class="stat-label">Material Utilization</div>
                <div class="stat-value">{{ calculateMaterialUtilization() }}%</div>
              </div>
              <div class="stat-item">
                <div class="stat-label">Completion Date</div>
                <div class="stat-value">{{ formatDate(productionOrder.updated_at) }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
  
      <!-- Start Production Confirmation Modal -->
      <ConfirmationModal
        v-if="showStartModal"
        title="Start Production"
        :message="`Are you sure you want to start production for <strong>${productionOrder?.production_number}</strong>?<br><br>This will change the status to 'In Progress' and you will be able to record material consumption and production activities.`"
        confirm-button-text="Start Production"
        confirm-button-class="btn btn-success"
        @confirm="startProduction"
        @close="cancelStart"
      />
  
      <!-- Cancel Production Confirmation Modal -->
      <ConfirmationModal
        v-if="showCancelModal"
        title="Cancel Production"
        :message="`Are you sure you want to cancel production for <strong>${productionOrder?.production_number}</strong>?<br><br>This will change the status to 'Cancelled' and stop all production activities.`"
        confirm-button-text="Cancel Production"
        confirm-button-class="btn btn-warning"
        @confirm="cancelProduction"
        @close="cancelCancelAction"
      />
  
      <!-- Delete Confirmation Modal -->
      <ConfirmationModal
        v-if="showDeleteModal"
        title="Delete Production Order"
        :message="`Are you sure you want to delete production order <strong>${productionOrder?.production_number}</strong>? This action cannot be undone.`"
        confirm-button-text="Delete"
        confirm-button-class="btn btn-danger"
        @confirm="deleteProductionOrder"
        @close="cancelDelete"
      />
  
      <!-- Reactivate Confirmation Modal -->
      <ConfirmationModal
        v-if="showReactivateModal"
        title="Reactivate Production Order"
        :message="`Are you sure you want to reactivate production order <strong>${productionOrder?.production_number}</strong>?<br><br>This will change the status back to 'Draft'.`"
        confirm-button-text="Reactivate"
        confirm-button-class="btn btn-primary"
        @confirm="reactivateProduction"
        @close="cancelReactivate"
      />
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'ProductionOrderDetail',
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
        loading: true,
        showStartModal: false,
        showCancelModal: false,
        showDeleteModal: false,
        showReactivateModal: false,
        // Toast system
        toasts: [],
        toastIdCounter: 0
      };
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

      // Helper methods for toast
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
          const response = await axios.get(`/production-orders/${this.productionId}`);
          this.productionOrder = response.data.data || response.data;
          
          // Get consumptions
          if (this.productionOrder.production_consumptions) {
            this.consumptions = this.productionOrder.production_consumptions;
          }
          
          // Fetch work order details if we have a work order ID
          if (this.productionOrder.wo_id) {
            await this.fetchWorkOrder(this.productionOrder.wo_id);
          }
        } catch (error) {
          console.error('Error fetching production order:', error);
          this.showError('Failed to load production order');
        } finally {
          this.loading = false;
        }
      },
      
      async fetchWorkOrder(workOrderId) {
        try {
          const response = await axios.get(`/work-orders/${workOrderId}`);
          this.workOrder = response.data.data || response.data;
        } catch (error) {
          console.error('Error fetching work order:', error);
          this.showError('Failed to load work order details');
        }
      },
      
      // Status Transition Methods
      confirmStartProduction() {
        this.showStartModal = true;
      },
      
      async startProduction() {
        try {
          await axios.patch(`/production-orders/${this.productionId}/status`, {
            status: 'In Progress'
          });
          
          this.showSuccess('Production started successfully');
          this.fetchProductionOrder(); // Refresh data
        } catch (error) {
          console.error('Error starting production:', error);
          this.showError('Failed to start production');
        } finally {
          this.showStartModal = false;
        }
      },
      
      cancelStart() {
        this.showStartModal = false;
      },
      
      confirmCancelProduction() {
        this.showCancelModal = true;
      },
      
      async cancelProduction() {
        try {
          await axios.patch(`/production-orders/${this.productionId}/status`, {
            status: 'Cancelled'
          });
          
          this.showSuccess('Production cancelled successfully');
          this.fetchProductionOrder(); // Refresh data
        } catch (error) {
          console.error('Error cancelling production:', error);
          this.showError('Failed to cancel production');
        } finally {
          this.showCancelModal = false;
        }
      },
      
      cancelCancelAction() {
        this.showCancelModal = false;
      },
      
      confirmReactivate() {
        this.showReactivateModal = true;
      },
      
      async reactivateProduction() {
        try {
          await axios.patch(`/production-orders/${this.productionId}/status`, {
            status: 'Draft'
          });
          
          this.showSuccess('Production order reactivated successfully');
          this.fetchProductionOrder(); // Refresh data
        } catch (error) {
          console.error('Error reactivating production:', error);
          this.showError('Failed to reactivate production order');
        } finally {
          this.showReactivateModal = false;
        }
      },
      
      cancelReactivate() {
        this.showReactivateModal = false;
      },
      
      // Existing Methods
      formatDate(date) {
        if (!date) return 'N/A';
        return new Date(date).toLocaleDateString();
      },
      
      getStatusClass(status) {
        switch (status) {
          case 'Draft': return 'status-draft';
          case 'In Progress': return 'status-in-progress';
          case 'Completed': return 'status-completed';
          case 'Cancelled': return 'status-cancelled';
          default: return '';
        }
      },
      
      getVariance(consumption) {
        const planned = parseFloat(consumption.planned_quantity) || 0;
        const actual = parseFloat(consumption.actual_quantity) || 0;
        const variance = planned - actual;
        
        if (variance === 0) return '0';
        
        return variance > 0 
          ? `+${variance.toFixed(2)}` 
          : variance.toFixed(2);
      },
      
      getVarianceClass(consumption) {
        const planned = parseFloat(consumption.planned_quantity) || 0;
        const actual = parseFloat(consumption.actual_quantity) || 0;
        const variance = planned - actual;
        
        if (variance === 0) return 'no-variance';
        if (Math.abs(variance) / planned <= 0.05) return 'low-variance';
        if (variance > 0) return 'positive-variance';
        return 'negative-variance';
      },
      
      calculateEfficiency() {
        if (!this.productionOrder || !this.productionOrder.planned_quantity || this.productionOrder.planned_quantity <= 0) {
          return '0';
        }
        
        const efficiency = (this.productionOrder.actual_quantity / this.productionOrder.planned_quantity) * 100;
        return efficiency.toFixed(2);
      },
      
      calculateMaterialUtilization() {
        if (!this.consumptions || this.consumptions.length === 0) {
          return '0';
        }
        
        let plannedTotal = 0;
        let actualTotal = 0;
        
        this.consumptions.forEach(consumption => {
          plannedTotal += parseFloat(consumption.planned_quantity) || 0;
          actualTotal += parseFloat(consumption.actual_quantity) || 0;
        });
        
        if (plannedTotal <= 0) {
          return '0';
        }
        
        const utilization = (actualTotal / plannedTotal) * 100;
        return utilization.toFixed(2);
      },
      
      printProductionOrder() {
        // Implement print functionality
        window.print();
      },
      
      confirmDelete() {
        this.showDeleteModal = true;
      },
      
      async deleteProductionOrder() {
        try {
          await axios.delete(`/production-orders/${this.productionId}`);
          this.showSuccess('Production order deleted successfully');
          this.$router.push('/manufacturing/production-orders');
        } catch (error) {
          console.error('Error deleting production order:', error);
          this.showError('Failed to delete production order');
        } finally {
          this.showDeleteModal = false;
        }
      },
      
      cancelDelete() {
        this.showDeleteModal = false;
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

  /* Existing Styles */
  .production-order-detail {
    padding: 1rem;
  }
  
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
  }
  
  .actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
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
  
  .detail-card {
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
  
  .table {
    width: 100%;
    border-collapse: collapse;
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
  
  .variance {
    font-weight: 500;
  }
  
  .no-variance {
    color: var(--gray-600);
  }
  
  .low-variance {
    color: var(--warning-color);
  }
  
  .positive-variance {
    color: var(--success-color);
  }
  
  .negative-variance {
    color: var(--danger-color);
  }
  
  .header-actions {
    display: flex;
    gap: 0.5rem;
  }
  
  .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 2rem;
    color: var(--gray-500);
    text-align: center;
  }
  
  .empty-state i {
    font-size: 2rem;
    margin-bottom: 1rem;
  }
  
  .summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.5rem;
  }
  
  .stat-item {
    text-align: center;
    padding: 1rem;
    background-color: var(--gray-50);
    border-radius: 0.5rem;
  }
  
  .stat-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--gray-600);
    margin-bottom: 0.5rem;
  }
  
  .stat-value {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--primary-color);
  }
  
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
  }
  
  .btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
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
  
  .btn-warning {
    background-color: var(--warning-color);
    color: white;
  }
  
  .btn-warning:hover {
    background-color: #b45309;
  }
  
  .btn-danger {
    background-color: var(--danger-color);
    color: white;
  }
  
  .btn-danger:hover {
    background-color: #b91c1c;
  }
  
  .btn-info {
    background-color: #3b82f6;
    color: white;
  }
  
  .btn-info:hover {
    background-color: #2563eb;
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
    }
    
    .detail-grid {
      grid-template-columns: 1fr;
    }
    
    .table-responsive {
      overflow-x: auto;
    }
    
    .summary-stats {
      grid-template-columns: 1fr;
    }
  }
  </style>