<!-- src/views/sales/EnhancedDeliveryDetail.vue -->
<template>
  <div class="enhanced-delivery-detail">
    <!-- Loading State -->
    <div v-if="isLoading" class="loading-container">
      <div class="loading-spinner"></div>
      <p>Loading delivery details...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-container">
      <div class="error-icon">
        <i class="fas fa-exclamation-triangle"></i>
      </div>
      <h3>Error Loading Delivery</h3>
      <p>{{ error }}</p>
      <button @click="loadDeliveryDetails" class="btn btn-primary">
        <i class="fas fa-redo"></i>
        Try Again
      </button>
    </div>

    <!-- Main Content -->
    <div v-else-if="delivery" class="delivery-content">
      <!-- Page Header -->
      <div class="page-header">
        <div class="header-left">
          <div class="breadcrumb">
            <router-link to="/sales/deliveries" class="breadcrumb-link">
              <i class="fas fa-truck"></i>
              Deliveries
            </router-link>
            <i class="fas fa-chevron-right"></i>
            <span class="breadcrumb-current">{{ delivery.delivery_number }}</span>
          </div>
          <h1 class="page-title">
            {{ delivery.delivery_number }}
            <span class="delivery-type-badge" v-if="isMultiSODelivery">
              <i class="fas fa-layer-group"></i>
              Multi-SO Delivery
            </span>
          </h1>
          <p class="page-subtitle">
            Delivery created on {{ formatDate(delivery.delivery_date) }}
            <span v-if="isMultiSODelivery"> • Consolidated from {{ uniqueSalesOrders.length }} Sales Orders</span>
          </p>
        </div>
        <div class="header-actions">
          <button 
            v-if="canComplete" 
            @click="completeDelivery" 
            class="btn btn-success"
            :disabled="isUpdating"
          >
            <i class="fas fa-check"></i>
            Complete Delivery
          </button>
          <router-link 
            v-if="canEdit" 
            :to="`/sales/deliveries/${delivery.delivery_id}/edit`" 
            class="btn btn-primary"
          >
            <i class="fas fa-edit"></i>
            Edit
          </router-link>
          <button @click="printDelivery" class="btn btn-outline">
            <i class="fas fa-print"></i>
            Print
          </button>
        </div>
      </div>

      <!-- Status Banner -->
      <div class="status-banner" :class="`status-${delivery.status.toLowerCase()}`">
        <div class="status-content">
          <div class="status-indicator">
            <i :class="getStatusIcon(delivery.status)"></i>
          </div>
          <div class="status-text">
            <h3>{{ delivery.status }}</h3>
            <p>{{ getStatusDescription(delivery.status) }}</p>
          </div>
        </div>
        <div v-if="delivery.tracking_number" class="tracking-info">
          <span class="tracking-label">Tracking Number:</span>
          <span class="tracking-number">{{ delivery.tracking_number }}</span>
        </div>
      </div>

      <!-- Delivery Overview -->
      <div class="overview-section">
        <div class="overview-grid">
          <div class="overview-card">
            <div class="card-icon customer">
              <i class="fas fa-user"></i>
            </div>
            <div class="card-content">
              <h4>Customer</h4>
              <p>{{ delivery.customer.name }}</p>
              <small>{{ delivery.customer.customer_code }}</small>
            </div>
          </div>

          <div class="overview-card">
            <div class="card-icon date">
              <i class="fas fa-calendar"></i>
            </div>
            <div class="card-content">
              <h4>Delivery Date</h4>
              <p>{{ formatDate(delivery.delivery_date) }}</p>
              <small>{{ formatTime(delivery.delivery_date) }}</small>
            </div>
          </div>

          <div class="overview-card">
            <div class="card-icon shipping">
              <i class="fas fa-shipping-fast"></i>
            </div>
            <div class="card-content">
              <h4>Shipping Method</h4>
              <p>{{ delivery.shipping_method || 'Not specified' }}</p>
              <small v-if="delivery.tracking_number">{{ delivery.tracking_number }}</small>
            </div>
          </div>

          <div class="overview-card">
            <div class="card-icon items">
              <i class="fas fa-boxes"></i>
            </div>
            <div class="card-content">
              <h4>Total Items</h4>
              <p>{{ totalItems }}</p>
              <small>{{ totalQuantity }} units</small>
            </div>
          </div>
        </div>
      </div>

      <!-- Multi-SO Summary (only for multi-SO deliveries) -->
      <div v-if="isMultiSODelivery" class="multi-so-summary">
        <div class="section-card">
          <div class="section-header">
            <h3><i class="fas fa-layer-group"></i> Sales Orders Summary</h3>
            <span class="so-count-badge">{{ uniqueSalesOrders.length }} Sales Orders</span>
          </div>
          <div class="so-summary-grid">
            <div v-for="so in uniqueSalesOrders" :key="so.so_id" class="so-summary-card">
              <div class="so-header">
                <div class="so-info">
                  <h4>{{ so.so_number }}</h4>
                  <p class="so-date">{{ formatDate(so.so_date) }}</p>
                </div>
                <div class="so-stats">
                  <span class="stat-item">
                    <span class="stat-value">{{ getSOItemCount(so.so_id) }}</span>
                    <span class="stat-label">Items</span>
                  </span>
                  <span class="stat-item">
                    <span class="stat-value">{{ getSOQuantity(so.so_id) }}</span>
                    <span class="stat-label">Qty</span>
                  </span>
                </div>
              </div>
              <div class="so-progress">
                <div class="progress-label">
                  <span>Delivery Progress</span>
                  <span>{{ getSOProgress(so.so_id) }}%</span>
                </div>
                <div class="progress-bar">
                  <div 
                    class="progress-fill" 
                    :style="{ width: getSOProgress(so.so_id) + '%' }"
                  ></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Delivery Items -->
      <div class="items-section">
        <div class="section-card">
          <div class="section-header">
            <h3><i class="fas fa-list"></i> Delivery Items</h3>
            <div class="section-filters">
              <select v-if="isMultiSODelivery" v-model="selectedSOFilter" class="filter-select">
                <option value="">All Sales Orders</option>
                <option v-for="so in uniqueSalesOrders" :key="so.so_id" :value="so.so_id">
                  {{ so.so_number }}
                </option>
              </select>
            </div>
          </div>
          
          <div class="items-table-container">
            <table class="items-table">
              <thead>
                <tr>
                  <th v-if="isMultiSODelivery">Sales Order</th>
                  <th>Item</th>
                  <th>Delivered Qty</th>
                  <th>Warehouse</th>
                  <th>Batch</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="line in filteredDeliveryLines" :key="line.line_id" class="item-row">
                  <td v-if="isMultiSODelivery" class="so-cell">
                    <div class="so-tag">
                      {{ getSalesOrderNumber(line.so_line_id) }}
                    </div>
                  </td>
                  <td class="item-cell">
                    <div class="item-info">
                      <div class="item-main">
                        <strong>{{ line.item.name }}</strong>
                        <span class="item-code">{{ line.item.item_code }}</span>
                      </div>
                      <div class="item-meta">
                        <span class="item-category">{{ line.item.category || 'Uncategorized' }}</span>
                      </div>
                    </div>
                  </td>
                  <td class="quantity-cell">
                    <div class="quantity-info">
                    <span class="quantity-value">{{ formatQuantity(line.deliveredQuantity) }}</span>
                    <span class="quantity-unit">{{ line.item.uomName || 'pcs' }}</span>
                    </div>
                  </td>
                  <td class="warehouse-cell">
                    <div class="warehouse-info">
                      <i class="fas fa-warehouse"></i>
                      {{ line.warehouse.name }}
                    </div>
                  </td>
                  <td class="batch-cell">
                    <span v-if="line.batch_number" class="batch-number">{{ line.batch_number }}</span>
                    <span v-else class="no-batch">-</span>
                  </td>
                  <td class="status-cell">
                    <span class="item-status" :class="`status-${delivery.status.toLowerCase()}`">
                      {{ delivery.status }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Delivery Timeline -->
      <div class="timeline-section">
        <div class="section-card">
          <div class="section-header">
            <h3><i class="fas fa-history"></i> Delivery Timeline</h3>
          </div>
          <div class="timeline-content">
            <div class="timeline">
              <div v-for="event in deliveryTimeline" :key="event.id" class="timeline-item">
                <div class="timeline-marker" :class="event.type">
                  <i :class="event.icon"></i>
                </div>
                <div class="timeline-content-item">
                  <div class="timeline-header">
                    <h4>{{ event.title }}</h4>
                    <span class="timeline-date">{{ formatDateTime(event.timestamp) }}</span>
                  </div>
                  <p class="timeline-description">{{ event.description }}</p>
                  <div v-if="event.user" class="timeline-user">
                    <i class="fas fa-user"></i>
                    {{ event.user }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Related Documents -->
      <div class="documents-section">
        <div class="section-card">
          <div class="section-header">
            <h3><i class="fas fa-file-alt"></i> Related Documents</h3>
          </div>
          <div class="documents-grid">
            <template v-if="uniqueSalesOrders.length > 0 || (delivery.invoices && delivery.invoices.length > 0)">
              <div v-for="so in uniqueSalesOrders" :key="so.so_id" class="document-card">
                <div class="document-icon">
                  <i class="fas fa-file-signature"></i>
                </div>
                <div class="document-info">
                  <h4>{{ so.so_number }}</h4>
                  <p>Sales Order</p>
                  <span class="document-date">{{ formatDate(so.so_date) }}</span>
                </div>
                <div class="document-actions">
                  <router-link :to="`/sales/orders/${so.so_id}`" class="btn btn-sm btn-outline">
                    <i class="fas fa-eye"></i>
                    View
                  </router-link>
                </div>
              </div>

              <div v-if="delivery.invoices && delivery.invoices.length > 0" class="document-card">
                <div class="document-icon invoice">
                  <i class="fas fa-file-invoice"></i>
                </div>
                <div class="document-info">
                  <h4>Invoice Created</h4>
                  <p>{{ delivery.invoices.length }} invoice(s)</p>
                  <span class="document-date">{{ formatDate(delivery.invoices[0].created_at) }}</span>
                </div>
                <div class="document-actions">
                  <router-link :to="`/sales/invoices/${delivery.invoices[0].id}`" class="btn btn-sm btn-outline">
                    <i class="fas fa-eye"></i>
                    View
                  </router-link>
                </div>
              </div>
            </template>
            <template v-else>
              <p class="no-related-documents">No related sales orders or invoices found.</p>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- Complete Delivery Modal -->
    <div v-if="showCompleteModal" class="modal-overlay" @click="closeCompleteModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>Complete Delivery</h3>
          <button @click="closeCompleteModal" class="modal-close">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to complete this delivery?</p>
          <div class="completion-details">
            <div class="detail-item">
              <strong>Delivery Number:</strong> {{ delivery.delivery_number }}
            </div>
            <div class="detail-item">
              <strong>Total Items:</strong> {{ totalItems }}
            </div>
            <div class="detail-item">
              <strong>Customer:</strong> {{ delivery.customer.name }}
            </div>
          </div>
          <div class="warning-note">
            <i class="fas fa-info-circle"></i>
            Once completed, this action cannot be undone and stock will be updated.
          </div>
        </div>
        <div class="modal-actions">
          <button @click="closeCompleteModal" class="btn btn-outline">Cancel</button>
          <button @click="confirmComplete" class="btn btn-success" :disabled="isUpdating">
            <i v-if="isUpdating" class="fas fa-spinner fa-spin"></i>
            <i v-else class="fas fa-check"></i>
            {{ isUpdating ? 'Completing...' : 'Complete Delivery' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'EnhancedDeliveryDetail',
  props: {
    id: {
      type: [String, Number],
      required: true
    }
  },
  
  data() {
    return {
      delivery: null,
      isLoading: true,
      isUpdating: false,
      error: null,
      selectedSOFilter: '',
      showCompleteModal: false,
      deliveryTimeline: []
    };
  },
  
  computed: {
    isMultiSODelivery() {
      if (!this.delivery || !this.delivery.deliveryLines) return false;
      
      // Method 1: Check dari consolidated flag
      if (this.delivery.isConsolidated) return true;
      
      // Method 2: Check dari unique SO IDs di delivery lines
      const uniqueSOIds = new Set(
        this.delivery.deliveryLines
          .map(line => line.salesOrderLine?.soId)
          .filter(Boolean)
      );
      return uniqueSOIds.size > 1;
    },
    
    uniqueSalesOrders() {
      if (!this.delivery || !this.delivery.deliveryLines) return [];
      
      const soMap = new Map();
      
      this.delivery.deliveryLines.forEach(line => {
        if (line.salesOrderLine) {
          const soId = line.salesOrderLine.soId;
          if (!soMap.has(soId)) {
            // Create SO object from the line data
            soMap.set(soId, {
              so_id: soId,
              soId: soId,  // camelCase version
              so_number: this.getSONumberFromNotes(soId) || `SO-${soId}`,
              so_date: new Date().toISOString() // Fallback date
            });
          }
        }
      });
      
      return Array.from(soMap.values());
    },
    
    consolidatedSOIds() {
      if (!this.delivery) return [];
      
      // Parse consolidated_so_ids if it's a string
      let soIds = this.delivery.consolidatedSoIds;
      
      if (typeof soIds === 'string') {
        try {
          soIds = JSON.parse(soIds);
        } catch (e) {
          console.warn('Failed to parse consolidated_so_ids:', soIds);
          return [];
        }
      }
      
      return Array.isArray(soIds) ? soIds : [];
    },
    
    filteredDeliveryLines() {
      if (!this.delivery || !this.delivery.deliveryLines) return [];
      
      if (!this.selectedSOFilter) {
        return this.delivery.deliveryLines;
      }
      
      return this.delivery.deliveryLines.filter(line => 
        line.salesOrderLine?.soId == this.selectedSOFilter
      );
    },
    
    totalItems() {
      return this.delivery?.deliveryLines?.length || 0;
    },
    
    totalQuantity() {
      if (!this.delivery || !this.delivery.deliveryLines) return 0;
      return this.delivery.deliveryLines.reduce(
        (sum, line) => sum + (parseFloat(line.deliveredQuantity) || 0), 0
      );
    },
    
    canEdit() {
      return this.delivery?.status === 'Pending';
    },
    
    canComplete() {
      return this.delivery?.status === 'Pending';
    }
  },
  
  async mounted() {
    await this.loadDeliveryDetails();
  },
  
  methods: {
    async loadDeliveryDetails() {
      try {
        this.isLoading = true;
        this.error = null;
        
        console.log('Loading delivery details for ID:', this.id);
        
        const response = await axios.get(`/deliveries/${this.id}`);
        
        console.log('Raw API Response:', response.data);
        
        // Convert snake_case to camelCase
        this.delivery = this.toCamelCase(response.data.data);
        
        console.log('Converted delivery data:', this.delivery);
        console.log('Is consolidated:', this.isMultiSODelivery);
        console.log('Unique SOs:', this.uniqueSalesOrders);
        
        // Generate timeline
        this.generateTimeline();
        
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to load delivery details';
        console.error('Error loading delivery:', error);
      } finally {
        this.isLoading = false;
      }
    },
    
    // Improved camelCase conversion
    toCamelCase(obj) {
      if (Array.isArray(obj)) {
        return obj.map(v => this.toCamelCase(v));
      } else if (obj !== null && obj !== undefined && obj.constructor === Object) {
        return Object.keys(obj).reduce((result, key) => {
          const camelKey = key.replace(/_([a-z])/g, g => g[1].toUpperCase());
          result[camelKey] = this.toCamelCase(obj[key]);
          return result;
        }, {});
      }
      return obj;
    },
    
    // Extract SO number from notes
    getSONumberFromNotes(soId) {
      if (!this.delivery?.notes) return null;
      
      // Parse SO numbers from notes like "SO250421-058, SO250513-116"
      const matches = this.delivery.notes.match(/SO[\d-]+/g);
      if (matches && matches.length >= soId) {
        return matches[soId - 1]; // Assuming SO IDs are sequential
      }
      
      return null;
    },
    
    // Get SO info from delivery lines
    getSalesOrderNumber(soLineId) {
      const line = this.delivery.deliveryLines.find(l => l.soLineId === soLineId);
      const soId = line?.salesOrderLine?.soId;
      return this.getSONumberFromNotes(soId) || `SO-${soId}` || 'Unknown';
    },
    
    getSOItemCount(soId) {
      return this.delivery.deliveryLines.filter(
        line => line.salesOrderLine?.soId === soId
      ).length;
    },
    
    getSOQuantity(soId) {
      return this.delivery.deliveryLines
        .filter(line => line.salesOrderLine?.soId === soId)
        .reduce((sum, line) => sum + (parseFloat(line.deliveredQuantity) || 0), 0);
    },
    
    getSOProgress() {
      // For now, return 100% if delivery is completed, 75% otherwise
      return this.delivery.status === 'Completed' ? 100 : 75;
    },
    
    generateTimeline() {
      this.deliveryTimeline = [
        {
          id: 1,
          type: 'created',
          icon: 'fas fa-plus-circle',
          title: 'Delivery Created',
          description: `${this.isMultiSODelivery ? 'Consolidated d' : 'D'}elivery ${this.delivery.deliveryNumber} was created`,
          timestamp: this.delivery.createdAt || this.delivery.deliveryDate,
          user: 'System'
        }
      ];
      
      if (this.delivery.status === 'Completed') {
        this.deliveryTimeline.push({
          id: 2,
          type: 'completed',
          icon: 'fas fa-check-circle',
          title: 'Delivery Completed',
          description: 'All items have been delivered and stock updated',
          timestamp: this.delivery.updatedAt || this.delivery.deliveryDate,
          user: 'System'
        });
      }
    },
    
    getStatusIcon(status) {
      const icons = {
        'Pending': 'fas fa-clock',
        'In Transit': 'fas fa-truck',
        'Completed': 'fas fa-check-circle',
        'Cancelled': 'fas fa-times-circle'
      };
      return icons[status] || 'fas fa-question-circle';
    },
    
    getStatusDescription(status) {
      const descriptions = {
        'Pending': 'Delivery is being prepared and waiting to be shipped',
        'In Transit': 'Items are on the way to the customer',
        'Completed': 'All items have been successfully delivered',
        'Cancelled': 'This delivery has been cancelled'
      };
      return descriptions[status] || 'Status unknown';
    },
    
    formatDate(date) {
      return new Date(date).toLocaleDateString();
    },
    
    formatTime(date) {
      return new Date(date).toLocaleTimeString();
    },
    
    formatDateTime(date) {
      return new Date(date).toLocaleString();
    },
    
    formatQuantity(qty) {
      return parseFloat(qty).toLocaleString();
    },
    
    // Rest of the methods remain the same...
    completeDelivery() {
      this.showCompleteModal = true;
    },
    
    closeCompleteModal() {
      this.showCompleteModal = false;
    },
    
    async confirmComplete() {
      try {
        this.isUpdating = true;
        
        await axios.post(`/sales/deliveries/${this.id}/complete`);
        
        this.$toast.success('Delivery completed successfully');
        this.showCompleteModal = false;
        await this.loadDeliveryDetails();
        
      } catch (error) {
        this.$toast.error(error.response?.data?.message || 'Failed to complete delivery');
        console.error('Error completing delivery:', error);
      } finally {
        this.isUpdating = false;
      }
    },
    
    printDelivery() {
      window.print();
    }
  }
};
</script>

<style scoped>
.enhanced-delivery-detail {
  max-width: 1200px;
  margin: 0 auto;
  padding: 1rem;
}

/* Loading & Error States */
.loading-container, .error-container {
  text-align: center;
  padding: 3rem 0;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #f3f3f3;
  border-top: 4px solid var(--primary-color);
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.error-icon {
  font-size: 3rem;
  color: var(--danger-color);
  margin-bottom: 1rem;
}

/* Page Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
  color: var(--text-muted);
  font-size: 0.9rem;
}

.breadcrumb-link {
  color: var(--primary-color);
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.breadcrumb-link:hover {
  text-decoration: underline;
}

.breadcrumb-current {
  font-weight: 500;
}

.page-title {
  font-size: 2rem;
  color: var(--text-primary);
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.delivery-type-badge {
  background: var(--primary-color);
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.page-subtitle {
  color: var(--text-muted);
  font-size: 1rem;
}

.header-actions {
  display: flex;
  gap: 1rem;
  align-items: center;
}

/* Status Banner */
.status-banner {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 2rem;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  border-left: 5px solid;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.status-banner.status-pending {
  border-color: var(--warning-color);
  background: linear-gradient(135deg, #fff8e1 0%, #ffffff 100%);
}

.status-banner.status-completed {
  border-color: var(--success-color);
  background: linear-gradient(135deg, #e8f5e8 0%, #ffffff 100%);
}

.status-content {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.status-indicator {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  color: white;
}

.status-pending .status-indicator {
  background: var(--warning-color);
}

.status-completed .status-indicator {
  background: var(--success-color);
}

.status-text h3 {
  margin: 0 0 0.25rem 0;
  color: var(--text-primary);
}

.status-text p {
  margin: 0;
  color: var(--text-muted);
}

.tracking-info {
  text-align: right;
}

.tracking-label {
  display: block;
  color: var(--text-muted);
  font-size: 0.9rem;
  margin-bottom: 0.25rem;
}

.tracking-number {
  font-size: 1.1rem;
  font-weight: bold;
  color: var(--primary-color);
}

/* Overview Section */
.overview-section {
  margin-bottom: 2rem;
}

.overview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
}

.overview-card {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  display: flex;
  align-items: center;
  gap: 1rem;
}

.card-icon {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  color: white;
  flex-shrink: 0;
}

.card-icon.customer {
  background: linear-gradient(135deg, #007bff, #0056b3);
}

.card-icon.date {
  background: linear-gradient(135deg, #28a745, #1e7e34);
}

.card-icon.shipping {
  background: linear-gradient(135deg, #17a2b8, #138496);
}

.card-icon.items {
  background: linear-gradient(135deg, #ffc107, #e0a800);
}

.card-content h4 {
  margin: 0 0 0.25rem 0;
  color: var(--text-primary);
  font-size: 0.9rem;
  font-weight: 500;
}

.card-content p {
  margin: 0 0 0.25rem 0;
  color: var(--text-primary);
  font-size: 1.1rem;
  font-weight: 600;
}

.card-content small {
  color: var(--text-muted);
  font-size: 0.8rem;
}

/* Multi-SO Summary */
.multi-so-summary {
  margin-bottom: 2rem;
}

.section-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  overflow: hidden;
}

.section-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e9ecef;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.section-header h3 {
  margin: 0;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.so-count-badge {
  background: var(--primary-color);
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.9rem;
  font-weight: 500;
}

.so-summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
  padding: 1.5rem;
}

.so-summary-card {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 1rem;
  border: 1px solid #e9ecef;
}

.so-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.so-info h4 {
  margin: 0 0 0.25rem 0;
  color: var(--primary-color);
}

.so-date {
  color: var(--text-muted);
  font-size: 0.9rem;
  margin: 0;
}

.so-stats {
  display: flex;
  gap: 1rem;
}

.stat-item {
  text-align: center;
  display: flex;
  flex-direction: column;
}

.stat-value {
  font-size: 1.2rem;
  font-weight: bold;
  color: var(--text-primary);
}

.stat-label {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.so-progress {
  margin-top: 1rem;
}

.progress-label {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.5rem;
  font-size: 0.9rem;
  color: var(--text-secondary);
}

.progress-bar {
  height: 8px;
  background: #e9ecef;
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: var(--success-color);
  transition: width 0.3s ease;
}

/* Items Section */
.items-section {
  margin-bottom: 2rem;
}

.section-filters {
  display: flex;
  gap: 1rem;
}

.filter-select {
  padding: 0.5rem;
  border: 1px solid #ddd;
  border-radius: 6px;
  background: white;
  min-width: 150px;
}

.items-table-container {
  overflow-x: auto;
}

.items-table {
  width: 100%;
  border-collapse: collapse;
}

.items-table th {
  background: #f8f9fa;
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: var(--text-primary);
  border-bottom: 2px solid #e9ecef;
}

.items-table td {
  padding: 1rem;
  border-bottom: 1px solid #e9ecef;
  vertical-align: middle;
}

.item-row:hover {
  background: #f8f9fa;
}

.so-cell .so-tag {
  background: var(--primary-color);
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 500;
}

.item-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.item-main {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.item-main strong {
  color: var(--text-primary);
}

.item-code {
  background: #e9ecef;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.8rem;
  color: var(--text-muted);
}

.item-meta {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.quantity-info {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.quantity-value {
  font-size: 1.1rem;
  font-weight: bold;
  color: var(--text-primary);
}

.quantity-unit {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.warehouse-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--text-primary);
}

.batch-number {
  background: #e3f2fd;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.8rem;
  color: #1976d2;
}

.no-batch {
  color: var(--text-muted);
}

.item-status {
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 500;
}

.item-status.status-pending {
  background: #fff3cd;
  color: #856404;
}

.item-status.status-completed {
  background: #d4edda;
  color: #155724;
}

/* Timeline Section */
.timeline-section {
  margin-bottom: 2rem;
}

.timeline {
  position: relative;
  padding-left: 2rem;
}

.timeline::before {
  content: '';
  position: absolute;
  left: 1rem;
  top: 0;
  bottom: 0;
  width: 2px;
  background: #e9ecef;
}

.timeline-item {
  position: relative;
  margin-bottom: 2rem;
}

.timeline-marker {
  position: absolute;
  left: -2rem;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 0.9rem;
}

.timeline-marker.created {
  background: var(--primary-color);
}

.timeline-marker.completed {
  background: var(--success-color);
}

.timeline-content-item {
  background: white;
  border-radius: 8px;
  padding: 1rem;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  margin-left: 1rem;
}

.timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.timeline-header h4 {
  margin: 0;
  color: var(--text-primary);
}

.timeline-date {
  color: var(--text-muted);
  font-size: 0.9rem;
}

.timeline-description {
  color: var(--text-secondary);
  margin: 0 0 0.5rem 0;
}

.timeline-user {
  color: var(--text-muted);
  font-size: 0.8rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

/* Documents Section */
.documents-section {
  margin-bottom: 2rem;
}

.documents-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
  padding: 1.5rem;
}

.document-card {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 1rem;
  border: 1px solid #e9ecef;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.document-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--primary-color);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.document-icon.invoice {
  background: var(--success-color);
}

.document-info {
  flex: 1;
}

.document-info h4 {
  margin: 0 0 0.25rem 0;
  color: var(--text-primary);
}

.document-info p {
  margin: 0 0 0.25rem 0;
  color: var(--text-secondary);
  font-size: 0.9rem;
}

.document-date {
  color: var(--text-muted);
  font-size: 0.8rem;
}

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 12px;
  max-width: 500px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.modal-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e9ecef;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  color: var(--text-primary);
}

.modal-close {
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  font-size: 1.2rem;
  padding: 0.5rem;
  border-radius: 50%;
}

.modal-close:hover {
  background: #f8f9fa;
}

.modal-body {
  padding: 1.5rem;
}

.completion-details {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 1rem;
  margin: 1rem 0;
}

.detail-item {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.detail-item:last-child {
  margin-bottom: 0;
}

.warning-note {
  background: #fff3cd;
  border: 1px solid #ffeaa7;
  border-radius: 8px;
  padding: 1rem;
  color: #856404;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: 1rem;
}

.modal-actions {
  padding: 1.5rem;
  border-top: 1px solid #e9ecef;
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
}

/* Buttons */
.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  background: var(--primary-color);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: var(--primary-dark);
  transform: translateY(-1px);
}

.btn-success {
  background: var(--success-color);
  color: white;
}

.btn-success:hover:not(:disabled) {
  background: #218838;
  transform: translateY(-1px);
}

.btn-outline {
  background: transparent;
  color: var(--primary-color);
  border: 2px solid var(--primary-color);
}

.btn-outline:hover:not(:disabled) {
  background: var(--primary-color);
  color: white;
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.8rem;
}

/* Responsive */
@media (max-width: 768px) {
  .enhanced-delivery-detail {
    padding: 0.5rem;
  }
  
  .page-header {
    flex-direction: column;
    gap: 1rem;
    align-items: flex-start;
  }
  
  .header-actions {
    width: 100%;
    justify-content: flex-start;
  }
  
  .overview-grid {
    grid-template-columns: 1fr;
  }
  
  .so-summary-grid {
    grid-template-columns: 1fr;
  }
  
  .documents-grid {
    grid-template-columns: 1fr;
  }
  
  .page-title {
    font-size: 1.5rem;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
  
  .status-banner {
    flex-direction: column;
    gap: 1rem;
    text-align: center;
  }
  
  .timeline {
    padding-left: 1rem;
  }
  
  .timeline-marker {
    left: -1rem;
  }
  
  .timeline-content-item {
    margin-left: 0.5rem;
  }
}
</style>