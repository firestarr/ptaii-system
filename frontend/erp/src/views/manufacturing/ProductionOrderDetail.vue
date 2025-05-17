<!-- src/views/manufacturing/ProductionOrderDetail.vue -->
<template>
    <div class="production-order-detail">
      <div class="page-header">
        <h1>Production Order Details</h1>
        <div class="actions">
          <router-link to="/manufacturing/production-orders" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
          </router-link>
          <router-link v-if="productionOrder && productionOrder.status === 'Draft'" 
            :to="`/manufacturing/production-orders/${productionId}/edit`" 
            class="btn btn-primary">
            <i class="fas fa-edit"></i> Edit
          </router-link>
          <router-link v-if="productionOrder && productionOrder.status === 'In Progress'" 
            :to="`/manufacturing/production-orders/${productionId}/complete`" 
            class="btn btn-success">
            <i class="fas fa-check"></i> Complete
          </router-link>
          <button v-if="productionOrder && productionOrder.status === 'Draft'" 
            @click="confirmDelete" 
            class="btn btn-danger">
            <i class="fas fa-trash"></i> Delete
          </button>
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
  
      <!-- Confirmation Modal -->
      <ConfirmationModal
        v-if="showDeleteModal"
        title="Delete Production Order"
        :message="`Are you sure you want to delete production order <strong>${productionOrder?.production_number}</strong>? This action cannot be undone.`"
        confirm-button-text="Delete"
        confirm-button-class="btn btn-danger"
        @confirm="deleteProductionOrder"
        @close="cancelDelete"
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
        showDeleteModal: false
      };
    },
    created() {
      this.fetchProductionOrder();
    },
    methods: {
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
          this.$toast.error('Failed to load production order');
        } finally {
          this.loading = false;
        }
      },
      
      issueMaterial(material) {
        // Navigate to add consumption form with prefill props
        this.$router.push({
          name: 'AddProductionConsumption',
          params: { productionId: this.productionId },
          query: { prefillItemId: material.item_id, prefillPlannedQuantity: material.planned_quantity || 0 }
        });
      },
      
      async fetchWorkOrder(workOrderId) {
        try {
          const response = await axios.get(`/work-orders/${workOrderId}`);
          this.workOrder = response.data.data || response.data;
        } catch (error) {
          console.error('Error fetching work order:', error);
          this.$toast.error('Failed to load work order details');
        }
      },
      
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
      
      confirmDelete() {
        this.showDeleteModal = true;
      },
      
      async deleteProductionOrder() {
        try {
          await axios.delete(`/production-orders/${this.productionId}`);
          this.$toast.success('Production order deleted successfully');
          this.$router.push('/manufacturing/production-orders');
        } catch (error) {
          console.error('Error deleting production order:', error);
          this.$toast.error('Failed to delete production order');
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
  
  @media (max-width: 768px) {
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
    
    .detail-grid {
      grid-template-columns: 1fr;
    }
    
    .table-responsive {
      overflow-x: auto;
    }
  }
  </style>