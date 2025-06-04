<!-- src/views/sales/CreateDeliveryFromMultipleSO.vue -->
<template>
  <div class="create-delivery-multiple-so">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-content">
        <h1 class="page-title">
          <i class="fas fa-truck"></i>
          Create Delivery from Multiple Sales Orders
        </h1>
        <p class="page-subtitle">Select items from multiple sales orders to create delivery</p>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="loading-container">
      <div class="loading-spinner"></div>
      <p>Loading outstanding sales orders...</p>
    </div>

    <!-- Main Content -->
    <div v-else class="content-container">
      <!-- Step Indicator -->
      <div class="step-indicator">
        <div class="step" :class="{ active: currentStep === 1, completed: currentStep > 1 }">
          <div class="step-number">1</div>
          <div class="step-label">Select Sales Orders</div>
        </div>
        <div class="step-connector"></div>
        <div class="step" :class="{ active: currentStep === 2, completed: currentStep > 2 }">
          <div class="step-number">2</div>
          <div class="step-label">Select Items</div>
        </div>
        <div class="step-connector"></div>
        <div class="step" :class="{ active: currentStep === 3 }">
          <div class="step-number">3</div>
          <div class="step-label">Delivery Details</div>
        </div>
      </div>

      <!-- Step 1: Select Sales Orders -->
      <div v-if="currentStep === 1" class="step-content">
        <div class="section-card">
          <div class="section-header">
            <h2><i class="fas fa-list"></i> Outstanding Sales Orders</h2>
            <p>Select sales orders that have items ready for delivery</p>
          </div>

          <!-- Search and Filter -->
          <div class="filters-section">
            <div class="search-box">
              <i class="fas fa-search"></i>
              <input 
                v-model="soSearchQuery" 
                type="text" 
                placeholder="Search by SO number or customer name..."
                class="search-input"
              >
            </div>
            <div class="filter-controls">
              <select v-model="selectedCustomerFilter" class="filter-select">
                <option value="">All Customers</option>
                <option v-for="customer in uniqueCustomers" :key="customer.id" :value="customer.id">
                  {{ customer.name }}
                </option>
              </select>
            </div>
          </div>

          <!-- Sales Orders List -->
          <div class="so-list">
            <div v-if="filteredSalesOrders.length === 0" class="empty-state">
              <i class="fas fa-inbox"></i>
              <h3>No Outstanding Sales Orders</h3>
              <p>All sales orders have been fully delivered or there are no pending orders.</p>
            </div>

            <div v-for="so in filteredSalesOrders" :key="so.so_id" class="so-card">
              <div class="so-header">
                <div class="so-info">
                  <div class="checkbox-container">
                    <input 
                      :id="`so-${so.so_id}`"
                      v-model="selectedSOs"
                      :value="so.so_id"
                      type="checkbox"
                      class="so-checkbox"
                    >
                    <label :for="`so-${so.so_id}`" class="checkbox-label"></label>
                  </div>
                  <div class="so-details">
                    <h3 class="so-number">{{ so.so_number }}</h3>
                    <p class="customer-name">{{ so.customer_name }}</p>
                    <div class="so-meta">
                      <span class="so-date">
                        <i class="fas fa-calendar"></i>
                        {{ formatDate(so.so_date) }}
                      </span>
                      <span class="so-status" :class="`status-${so.status.toLowerCase()}`">
                        {{ so.status }}
                      </span>
                    </div>
                  </div>
                </div>
                <div class="so-summary">
                  <div class="outstanding-qty">
                    <span class="qty-label">Outstanding Qty</span>
                    <span class="qty-value">{{ so.outstanding_quantity }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Step Navigation -->
          <div class="step-actions">
            <button 
              @click="loadSelectedSOItems" 
              :disabled="selectedSOs.length === 0"
              class="btn btn-primary"
            >
              Next: Select Items
              <i class="fas fa-arrow-right"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Step 2: Select Items -->
      <div v-if="currentStep === 2" class="step-content">
        <div class="section-card">
          <div class="section-header">
            <h2><i class="fas fa-boxes"></i> Select Items for Delivery</h2>
            <p>Choose items and quantities to include in this delivery</p>
          </div>

          <!-- Selected SO Summary -->
          <div class="selected-so-summary">
            <h3>Selected Sales Orders ({{ selectedSODetails.length }})</h3>
            <div class="so-tags">
              <span v-for="so in selectedSODetails" :key="so.so_id" class="so-tag">
                {{ so.so_number }} - {{ so.customer_name }}
              </span>
            </div>
          </div>

          <!-- Items Selection -->
          <div v="selectedSODetails.length > 0" class="items-section">
            <div v-for="so in selectedSODetails" :key="so.so_id" class="so-items-group">
              <div class="so-group-header">
                <h4>{{ so.so_number }} - {{ so.customer_name }}</h4>
                <div class="group-actions">
                  <button 
                    @click="selectAllItems(so.so_id)" 
                    class="btn btn-sm btn-outline"
                  >
                    Select All
                  </button>
                  <button 
                    @click="deselectAllItems(so.so_id)" 
                    class="btn btn-sm btn-outline"
                  >
                    Deselect All
                  </button>
                </div>
              </div>

              <div class="items-table">
                <table>
                  <thead>
                    <tr>
                      <th width="40"></th>
                      <th>Item</th>
                      <th>Outstanding Qty</th>
                      <th>Delivery Qty</th>
                      <th>Warehouse</th>
                      <th>Available Stock</th>
                      <th>Batch Number</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in so.outstanding_items" :key="item.so_line_id" class="item-row">
                      <td>
                        <input 
                          :id="`item-${item.so_line_id}`"
                          v-model="selectedItems"
                          :value="item.so_line_id"
                          type="checkbox"
                          class="item-checkbox"
                          @change="onItemSelectionChange(item)"
                        >
                      </td>
                      <td>
                        <div class="item-info">
                          <strong>{{ item.item_name }}</strong>
                          <small>{{ item.item_code }}</small>
                        </div>
                      </td>
                      <td>
                        <span class="qty-badge">{{ item.outstanding_quantity }} {{ item.uom_name }}</span>
                      </td>
                      <td>
                        <input 
                          v-if="selectedItems.includes(item.so_line_id)"
                          v-model.number="deliveryQuantities[item.so_line_id]"
                          type="number"
                          :max="item.outstanding_quantity"
                          min="0.01"
                          step="0.01"
                          class="qty-input"
                          @input="validateQuantity(item)"
                        >
                        <span v-else class="text-muted">-</span>
                      </td>
                      <td>
                        <select 
                          v-if="selectedItems.includes(item.so_line_id)"
                          v-model="selectedWarehouses[item.so_line_id]"
                          class="warehouse-select"
                          @change="updateAvailableStock(item)"
                        >
                          <option value="">Select Warehouse</option>
                          <option 
                            v-for="stock in item.warehouse_stocks.filter(s => s.available_quantity > 0)" 
                            :key="stock.warehouse_id" 
                            :value="stock.warehouse_id"
                          >
                            {{ stock.warehouse_name }} ({{ stock.available_quantity }})
                          </option>
                        </select>
                        <span v-else class="text-muted">-</span>
                      </td>
                      <td>
                        <span v-if="selectedWarehouses[item.so_line_id]" class="stock-info">
                          {{ getAvailableStock(item, selectedWarehouses[item.so_line_id]) }}
                        </span>
                        <span v-else class="text-muted">-</span>
                      </td>
                      <td>
                        <input 
                          v-if="selectedItems.includes(item.so_line_id)"
                          v-model="batchNumbers[item.so_line_id]"
                          type="text"
                          placeholder="Optional"
                          class="batch-input"
                        >
                        <span v-else class="text-muted">-</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Step Navigation -->
          <div class="step-actions">
            <button @click="currentStep = 1" class="btn btn-outline">
              <i class="fas fa-arrow-left"></i>
              Back
            </button>
            <button 
              @click="proceedToDeliveryDetails"
              :disabled="!hasValidSelections"
              class="btn btn-primary"
            >
              Next: Delivery Details
              <i class="fas fa-arrow-right"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Step 3: Delivery Details -->
      <div v-if="currentStep === 3" class="step-content">
        <div class="section-card">
          <div class="section-header">
            <h2><i class="fas fa-file-alt"></i> Delivery Details</h2>
            <p>Enter delivery information and review selected items</p>
          </div>

          <!-- Delivery Form -->
          <div class="delivery-form">
            <div class="form-grid">
              <div class="form-group">
                <label for="delivery_number" class="required">Delivery Number</label>
                <input 
                  id="delivery_number"
                  v-model="deliveryForm.delivery_number"
                  type="text"
                  class="form-control"
                  :class="{ 'is-invalid': errors.delivery_number }"
                  required
                >
                <div v-if="errors.delivery_number" class="invalid-feedback">
                  {{ errors.delivery_number[0] }}
                </div>
              </div>

              <div class="form-group">
                <label for="delivery_date" class="required">Delivery Date</label>
                <input 
                  id="delivery_date"
                  v-model="deliveryForm.delivery_date"
                  type="date"
                  class="form-control"
                  :class="{ 'is-invalid': errors.delivery_date }"
                  required
                >
                <div v-if="errors.delivery_date" class="invalid-feedback">
                  {{ errors.delivery_date[0] }}
                </div>
              </div>

              <div class="form-group">
                <label for="shipping_method">Shipping Method</label>
                <select 
                  id="shipping_method"
                  v-model="deliveryForm.shipping_method"
                  class="form-control"
                >
                  <option value="">Select Method</option>
                  <option value="courier">Courier</option>
                  <option value="truck">Truck</option>
                  <option value="pickup">Customer Pickup</option>
                  <option value="other">Other</option>
                </select>
              </div>

              <div class="form-group">
                <label for="tracking_number">Tracking Number</label>
                <input 
                  id="tracking_number"
                  v-model="deliveryForm.tracking_number"
                  type="text"
                  class="form-control"
                  placeholder="Optional"
                >
              </div>
            </div>
          </div>

          <!-- Delivery Summary -->
          <div class="delivery-summary">
            <h3>Delivery Summary</h3>
            <div class="summary-content">
              <div class="summary-stats">
                <div class="stat-card">
                  <div class="stat-value">{{ selectedSODetails.length }}</div>
                  <div class="stat-label">Sales Orders</div>
                </div>
                <div class="stat-card">
                  <div class="stat-value">{{ getTotalItems() }}</div>
                  <div class="stat-label">Items</div>
                </div>
                <div class="stat-card">
                  <div class="stat-value">{{ getTotalQuantity() }}</div>
                  <div class="stat-label">Total Quantity</div>
                </div>
              </div>

              <!-- Items Summary Table -->
              <div class="items-summary-table">
                <table>
                  <thead>
                    <tr>
                      <th>SO Number</th>
                      <th>Item</th>
                      <th>Quantity</th>
                      <th>Warehouse</th>
                      <th>Batch</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in getSelectedItemsSummary()" :key="item.so_line_id">
                      <td>{{ item.so_number }}</td>
                      <td>
                        <div class="item-info">
                          <strong>{{ item.item_name }}</strong>
                          <small>{{ item.item_code }}</small>
                        </div>
                      </td>
                      <td>{{ item.quantity }} {{ item.uom_name }}</td>
                      <td>{{ item.warehouse_name }}</td>
                      <td>{{ item.batch_number || '-' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Step Navigation -->
          <div class="step-actions">
            <button @click="currentStep = 2" class="btn btn-outline">
              <i class="fas fa-arrow-left"></i>
              Back
            </button>
            <button 
              @click="createDelivery"
              :disabled="isCreating || !isFormValid"
              class="btn btn-success"
            >
              <i v-if="isCreating" class="fas fa-spinner fa-spin"></i>
              <i v-else class="fas fa-check"></i>
              {{ isCreating ? 'Creating...' : 'Create Delivery' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Success Modal -->
    <div v-if="showSuccessModal" class="modal-overlay" @click="closeSuccessModal">
      <div class="modal-content success-modal" @click.stop>
        <div class="modal-header">
          <i class="fas fa-check-circle success-icon"></i>
          <h2>Delivery Created Successfully!</h2>
        </div>
        <div class="modal-body">
          <p>{{ successMessage }}</p>
          <div v-if="createdDeliveries.length > 0" class="created-deliveries">
            <h4>Created Deliveries:</h4>
            <ul>
              <li v-for="delivery in createdDeliveries" :key="delivery.delivery_id">
                <strong>{{ delivery.delivery_number }}</strong>
              </li>
            </ul>
          </div>
        </div>
        <div class="modal-actions">
          <button @click="goToDeliveryList" class="btn btn-primary">
            View Deliveries
          </button>
          <button @click="createAnother" class="btn btn-outline">
            Create Another
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'CreateDeliveryFromMultipleSO',
  data() {
    return {
      // Step management
      currentStep: 1,
      isLoading: true,
      isCreating: false,
      
      // Data
      salesOrders: [],
      selectedSOs: [],
      selectedSODetails: [],
      selectedItems: [],
      deliveryQuantities: {},
      selectedWarehouses: {},
      batchNumbers: {},
      
      // Filters
      soSearchQuery: '',
      selectedCustomerFilter: '',
      
      // Form
      deliveryForm: {
        delivery_number: '',
        delivery_date: new Date().toISOString().split('T')[0],
        shipping_method: '',
        tracking_number: ''
      },
      
      // UI State
      errors: {},
      showSuccessModal: false,
      successMessage: '',
      createdDeliveries: []
    };
  },
  
  computed: {
    uniqueCustomers() {
      const customers = this.salesOrders.map(so => ({
        id: so.customer_id,
        name: so.customer_name
      }));
      
      return customers.filter((customer, index, self) => 
        index === self.findIndex(c => c.id === customer.id)
      );
    },
    
    filteredSalesOrders() {
      let filtered = this.salesOrders;
      
      if (this.soSearchQuery) {
        const query = this.soSearchQuery.toLowerCase();
        filtered = filtered.filter(so => 
          so.so_number.toLowerCase().includes(query) ||
          so.customer_name.toLowerCase().includes(query)
        );
      }
      
      if (this.selectedCustomerFilter) {
        filtered = filtered.filter(so => 
          so.customer_id === this.selectedCustomerFilter
        );
      }
      
      return filtered;
    },
    
    hasValidSelections() {
      return this.selectedItems.length > 0 && 
             this.selectedItems.every(lineId => 
               this.deliveryQuantities[lineId] > 0 && 
               this.selectedWarehouses[lineId]
             );
    },
    
    isFormValid() {
      return this.deliveryForm.delivery_number && 
             this.deliveryForm.delivery_date && 
             this.hasValidSelections;
    }
  },
  
  async mounted() {
    await this.loadOutstandingSalesOrders();
  },
  
  methods: {
    async loadOutstandingSalesOrders() {
      try {
        this.isLoading = true;
        const response = await axios.get('/deliveries/outstanding-so');
        this.salesOrders = response.data.data;
      } catch (error) {
        this.$toast.error('Failed to load outstanding sales orders');
        console.error('Error loading sales orders:', error);
      } finally {
        this.isLoading = false;
      }
    },
    
    async loadSelectedSOItems() {
      try {
        this.isLoading = true;
        this.selectedSODetails = [];
        
        for (const soId of this.selectedSOs) {
          const response = await axios.get(`/deliveries/outstanding-items/${soId}`);
          this.selectedSODetails.push(response.data.data);
        }
        
        this.currentStep = 2;
      } catch (error) {
        this.$toast.error('Failed to load sales order items');
        console.error('Error loading SO items:', error);
      } finally {
        this.isLoading = false;
      }
    },
    
    onItemSelectionChange(item) {
      if (this.selectedItems.includes(item.so_line_id)) {
        // Set default quantity to outstanding quantity
        this.$set(this.deliveryQuantities, item.so_line_id, item.outstanding_quantity);
        
        // Set default warehouse if only one option
        if (item.warehouse_stocks.length === 1) {
          this.$set(this.selectedWarehouses, item.so_line_id, item.warehouse_stocks[0].warehouse_id);
        }
      } else {
        // Remove from selections
        this.$delete(this.deliveryQuantities, item.so_line_id);
        this.$delete(this.selectedWarehouses, item.so_line_id);
        this.$delete(this.batchNumbers, item.so_line_id);
      }
    },
    
    selectAllItems(soId) {
      const so = this.selectedSODetails.find(s => s.so_id === soId);
      if (so) {
        so.outstanding_items.forEach(item => {
          if (!this.selectedItems.includes(item.so_line_id)) {
            this.selectedItems.push(item.so_line_id);
            this.onItemSelectionChange(item);
          }
        });
      }
    },
    
    deselectAllItems(soId) {
      const so = this.selectedSODetails.find(s => s.so_id === soId);
      if (so) {
        so.outstanding_items.forEach(item => {
          const index = this.selectedItems.indexOf(item.so_line_id);
          if (index > -1) {
            this.selectedItems.splice(index, 1);
            this.$delete(this.deliveryQuantities, item.so_line_id);
            this.$delete(this.selectedWarehouses, item.so_line_id);
            this.$delete(this.batchNumbers, item.so_line_id);
          }
        });
      }
    },
    
    validateQuantity(item) {
      const qty = this.deliveryQuantities[item.so_line_id];
      if (qty > item.outstanding_quantity) {
        this.$set(this.deliveryQuantities, item.so_line_id, item.outstanding_quantity);
      }
    },
    
    updateAvailableStock() {
      // This method can be used to update UI when warehouse selection changes
    },
    
    getAvailableStock(item, warehouseId) {
      const stock = item.warehouse_stocks.find(s => s.warehouse_id === warehouseId);
      return stock ? stock.available_quantity : 0;
    },
    
    proceedToDeliveryDetails() {
      this.currentStep = 3;
      this.generateDeliveryNumber();
    },
    
    generateDeliveryNumber() {
      const today = new Date();
      const year = today.getFullYear();
      const month = String(today.getMonth() + 1).padStart(2, '0');
      const day = String(today.getDate()).padStart(2, '0');
      const timestamp = Date.now().toString().slice(-4);
      
      this.deliveryForm.delivery_number = `DEL-${year}${month}${day}-${timestamp}`;
    },
    
    getTotalItems() {
      return this.selectedItems.length;
    },
    
    getTotalQuantity() {
      return Object.values(this.deliveryQuantities).reduce((sum, qty) => sum + (qty || 0), 0);
    },
    
    getSelectedItemsSummary() {
      const summary = [];
      
      this.selectedSODetails.forEach(so => {
        so.outstanding_items.forEach(item => {
          if (this.selectedItems.includes(item.so_line_id)) {
            const warehouse = item.warehouse_stocks.find(s => 
              s.warehouse_id === this.selectedWarehouses[item.so_line_id]
            );
            
            summary.push({
              so_line_id: item.so_line_id,
              so_number: so.so_number,
              item_name: item.item_name,
              item_code: item.item_code,
              quantity: this.deliveryQuantities[item.so_line_id],
              uom_name: item.uom_name,
              warehouse_name: warehouse ? warehouse.warehouse_name : '',
              batch_number: this.batchNumbers[item.so_line_id] || ''
            });
          }
        });
      });
      
      return summary;
    },
    
    async createDelivery() {
      try {
        this.isCreating = true;
        this.errors = {};
        
        // Prepare items data
        const items = this.selectedItems.map(lineId => ({
          so_line_id: lineId,
          delivered_quantity: this.deliveryQuantities[lineId],
          warehouse_id: this.selectedWarehouses[lineId],
          batch_number: this.batchNumbers[lineId] || null
        }));
        
        const payload = {
          ...this.deliveryForm,
          items: items
        };
        
        const response = await axios.post('/deliveries/from-outstanding', payload);
        
        this.createdDeliveries = response.data.data;
        this.successMessage = response.data.message;
        this.showSuccessModal = true;
        
      } catch (error) {
        if (error.response && error.response.status === 422) {
          this.errors = error.response.data.errors || {};
        }
        this.$toast.error(error.response?.data?.message || 'Failed to create delivery');
        console.error('Error creating delivery:', error);
      } finally {
        this.isCreating = false;
      }
    },
    
    closeSuccessModal() {
      this.showSuccessModal = false;
    },
    
    goToDeliveryList() {
      this.$router.push('/sales/deliveries');
    },
    
    createAnother() {
      this.showSuccessModal = false;
      this.resetForm();
    },
    
    resetForm() {
      this.currentStep = 1;
      this.selectedSOs = [];
      this.selectedSODetails = [];
      this.selectedItems = [];
      this.deliveryQuantities = {};
      this.selectedWarehouses = {};
      this.batchNumbers = {};
      this.deliveryForm = {
        delivery_number: '',
        delivery_date: new Date().toISOString().split('T')[0],
        shipping_method: '',
        tracking_number: ''
      };
      this.errors = {};
      this.loadOutstandingSalesOrders();
    },
    
    formatDate(date) {
      return new Date(date).toLocaleDateString();
    }
  }
};
</script>

<style scoped>
.create-delivery-multiple-so {
  max-width: 1200px;
  margin: 0 auto;
  padding: 1rem;
}

/* Page Header */
.page-header {
  margin-bottom: 2rem;
}

.header-content {
  text-align: center;
}

.page-title {
  color: var(--primary-color);
  font-size: 2rem;
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.page-subtitle {
  color: var(--text-muted);
  font-size: 1.1rem;
}

/* Loading */
.loading-container {
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

/* Step Indicator */
.step-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 2rem;
  padding: 1rem;
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.step {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.step-number {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #e9ecef;
  color: #6c757d;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  margin-bottom: 0.5rem;
  transition: all 0.3s ease;
}

.step.active .step-number {
  background: var(--primary-color);
  color: white;
}

.step.completed .step-number {
  background: var(--success-color);
  color: white;
}

.step-label {
  font-size: 0.9rem;
  color: var(--text-muted);
  font-weight: 500;
}

.step.active .step-label {
  color: var(--primary-color);
}

.step-connector {
  width: 60px;
  height: 2px;
  background: #e9ecef;
  margin: 0 1rem;
}

/* Section Card */
.section-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  overflow: hidden;
}

.section-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e9ecef;
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.section-header h2 {
  color: var(--primary-color);
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.section-header p {
  color: var(--text-muted);
  margin: 0;
}

/* Filters */
.filters-section {
  padding: 1.5rem;
  border-bottom: 1px solid #e9ecef;
  display: flex;
  gap: 1rem;
  align-items: center;
  flex-wrap: wrap;
}

.search-box {
  position: relative;
  flex: 1;
  min-width: 250px;
}

.search-box i {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
}

.search-input {
  width: 100%;
  padding: 0.75rem 1rem 0.75rem 2.5rem;
  border: 2px solid #e9ecef;
  border-radius: 8px;
  font-size: 0.9rem;
  transition: border-color 0.3s ease;
}

.search-input:focus {
  outline: none;
  border-color: var(--primary-color);
}

.filter-controls {
  display: flex;
  gap: 1rem;
}

.filter-select {
  padding: 0.75rem;
  border: 2px solid #e9ecef;
  border-radius: 8px;
  background: white;
  min-width: 150px;
}

/* SO List */
.so-list {
  padding: 1.5rem;
}

.empty-state {
  text-align: center;
  padding: 3rem;
  color: var(--text-muted);
}

.empty-state i {
  font-size: 3rem;
  margin-bottom: 1rem;
  opacity: 0.5;
}

.so-card {
  border: 2px solid #e9ecef;
  border-radius: 8px;
  margin-bottom: 1rem;
  transition: all 0.3s ease;
  overflow: hidden;
}

.so-card:hover {
  border-color: var(--primary-color);
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.so-header {
  padding: 1rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.so-info {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.checkbox-container {
  position: relative;
}

.so-checkbox {
  width: 20px;
  height: 20px;
  cursor: pointer;
}

.so-details h3 {
  margin: 0 0 0.25rem 0;
  color: var(--primary-color);
}

.customer-name {
  color: var(--text-secondary);
  margin: 0 0 0.5rem 0;
  font-weight: 500;
}

.so-meta {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.so-date {
  color: var(--text-muted);
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.so-status {
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 500;
}

.status-confirmed {
  background: #d1ecf1;
  color: #0c5460;
}

.status-delivering {
  background: #fff3cd;
  color: #856404;
}

.so-summary {
  text-align: right;
}

.outstanding-qty {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.qty-label {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.qty-value {
  font-size: 1.5rem;
  font-weight: bold;
  color: var(--primary-color);
}

/* Selected SO Summary */
.selected-so-summary {
  padding: 1.5rem;
  border-bottom: 1px solid #e9ecef;
  background: #f8f9fa;
}

.selected-so-summary h3 {
  margin-bottom: 1rem;
  color: var(--primary-color);
}

.so-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.so-tag {
  background: var(--primary-color);
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.9rem;
  font-weight: 500;
}

/* Items Section */
.items-section {
  padding: 1.5rem;
}

.so-items-group {
  margin-bottom: 2rem;
}

.so-group-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 2px solid #e9ecef;
}

.so-group-header h4 {
  color: var(--text-primary);
  margin: 0;
}

.group-actions {
  display: flex;
  gap: 0.5rem;
}

/* Items Table */
.items-table {
  overflow-x: auto;
}

.items-table table {
  width: 100%;
  border-collapse: collapse;
}

.items-table th {
  background: #f8f9fa;
  padding: 0.75rem;
  text-align: left;
  font-weight: 600;
  color: var(--text-primary);
  border-bottom: 2px solid #e9ecef;
}

.items-table td {
  padding: 0.75rem;
  border-bottom: 1px solid #e9ecef;
  vertical-align: middle;
}

.item-row:hover {
  background: #f8f9fa;
}

.item-info strong {
  display: block;
  color: var(--text-primary);
}

.item-info small {
  color: var(--text-muted);
}

.qty-badge {
  background: #e9ecef;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.9rem;
  font-weight: 500;
}

.qty-input, .warehouse-select, .batch-input {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 0.9rem;
}

.qty-input:focus, .warehouse-select:focus, .batch-input:focus {
  outline: none;
  border-color: var(--primary-color);
}

.stock-info {
  color: var(--success-color);
  font-weight: 500;
}

/* Delivery Form */
.delivery-form {
  padding: 1.5rem;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: var(--text-primary);
}

.form-group label.required::after {
  content: ' *';
  color: red;
}

.form-control {
  width: 100%;
  padding: 0.75rem;
  border: 2px solid #e9ecef;
  border-radius: 8px;
  font-size: 0.9rem;
  transition: border-color 0.3s ease;
}

.form-control:focus {
  outline: none;
  border-color: var(--primary-color);
}

.form-control.is-invalid {
  border-color: #dc3545;
}

.invalid-feedback {
  color: #dc3545;
  font-size: 0.8rem;
  margin-top: 0.25rem;
}

/* Delivery Summary */
.delivery-summary {
  padding: 1.5rem;
  border-top: 1px solid #e9ecef;
  background: #f8f9fa;
}

.delivery-summary h3 {
  margin-bottom: 1rem;
  color: var(--primary-color);
}

.summary-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.stat-card {
  background: white;
  padding: 1rem;
  border-radius: 8px;
  text-align: center;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stat-value {
  font-size: 2rem;
  font-weight: bold;
  color: var(--primary-color);
}

.stat-label {
  color: var(--text-muted);
  font-size: 0.9rem;
}

.items-summary-table {
  background: white;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.items-summary-table table {
  width: 100%;
  border-collapse: collapse;
}

.items-summary-table th {
  background: var(--primary-color);
  color: white;
  padding: 0.75rem;
  text-align: left;
  font-weight: 500;
}

.items-summary-table td {
  padding: 0.75rem;
  border-bottom: 1px solid #e9ecef;
}

/* Step Actions */
.step-actions {
  padding: 1.5rem;
  border-top: 1px solid #e9ecef;
  display: flex;
  justify-content: space-between;
  gap: 1rem;
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

.btn-outline:hover {
  background: var(--primary-color);
  color: white;
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.8rem;
}

/* Success Modal */
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

.success-modal .modal-header {
  padding: 2rem;
  text-align: center;
  border-bottom: 1px solid #e9ecef;
}

.success-icon {
  font-size: 4rem;
  color: var(--success-color);
  margin-bottom: 1rem;
}

.success-modal h2 {
  color: var(--success-color);
  margin: 0;
}

.modal-body {
  padding: 1.5rem;
}

.created-deliveries {
  margin-top: 1rem;
  padding: 1rem;
  background: #f8f9fa;
  border-radius: 8px;
}

.created-deliveries h4 {
  margin-bottom: 0.5rem;
  color: var(--primary-color);
}

.created-deliveries ul {
  margin: 0;
  padding-left: 1rem;
}

.modal-actions {
  padding: 1.5rem;
  border-top: 1px solid #e9ecef;
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
}

/* Responsive */
@media (max-width: 768px) {
  .create-delivery-multiple-so {
    padding: 0.5rem;
  }
  
  .page-title {
    font-size: 1.5rem;
  }
  
  .step-indicator {
    flex-direction: column;
    gap: 1rem;
  }
  
  .step-connector {
    width: 2px;
    height: 30px;
  }
  
  .filters-section {
    flex-direction: column;
    align-items: stretch;
  }
  
  .so-header {
    flex-direction: column;
    gap: 1rem;
    align-items: flex-start;
  }
  
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .summary-stats {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .step-actions {
    flex-direction: column;
  }
  
  .modal-actions {
    flex-direction: column;
  }
}

.text-muted {
  color: var(--text-muted);
}
</style>