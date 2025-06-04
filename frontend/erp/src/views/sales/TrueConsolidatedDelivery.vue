<!-- src/views/sales/TrueConsolidatedDelivery.vue -->
<template>
  <div class="consolidated-delivery">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-content">
        <h1 class="page-title">
          <i class="fas fa-layer-group"></i>
          Create Consolidated Delivery
        </h1>
        <p class="page-subtitle">Combine multiple sales orders into ONE single delivery</p>
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
          <div class="step-label">Select Customer & SOs</div>
        </div>
        <div class="step-connector"></div>
        <div class="step" :class="{ active: currentStep === 2, completed: currentStep > 2 }">
          <div class="step-number">2</div>
          <div class="step-label">Consolidate Items</div>
        </div>
        <div class="step-connector"></div>
        <div class="step" :class="{ active: currentStep === 3 }">
          <div class="step-number">3</div>
          <div class="step-label">Single Delivery</div>
        </div>
      </div>

      <!-- Step 1: Select Customer & Sales Orders -->
      <div v-if="currentStep === 1" class="step-content">
        <div class="section-card">
          <div class="section-header">
            <h2><i class="fas fa-users"></i> Select Customer First</h2>
            <p>Choose a customer, then select their outstanding sales orders to consolidate</p>
          </div>

          <!-- Customer Selection -->
          <div class="customer-selection">
            <h3>Choose Customer</h3>
            <div class="customer-grid">
              <div 
                v-for="customer in customersWithOutstanding" 
                :key="customer.customer_id"
                class="customer-card"
                :class="{ selected: selectedCustomerId === customer.customer_id }"
                @click="selectCustomer(customer.customer_id)"
              >
                <div class="customer-info">
                  <h4>{{ customer.name }}</h4>
                  <p>{{ customer.customer_code }}</p>
                  <div class="customer-stats">
                    <span class="stat">
                      <i class="fas fa-file-alt"></i>
                      {{ customer.outstanding_sos }} Outstanding SOs
                    </span>
                    <span class="stat">
                      <i class="fas fa-boxes"></i>
                      {{ customer.total_outstanding_items }} Items
                    </span>
                  </div>
                </div>
                <div class="selection-indicator">
                  <i class="fas fa-check"></i>
                </div>
              </div>
            </div>
          </div>

          <!-- Sales Orders for Selected Customer -->
          <div v-if="selectedCustomerId" class="so-selection">
            <h3>Select Sales Orders to Consolidate</h3>
            <div class="consolidation-preview">
              <div class="preview-stats">
                <div class="stat-item">
                  <span class="stat-value">{{ selectedSOs.length }}</span>
                  <span class="stat-label">Selected SOs</span>
                </div>
                <div class="stat-item">
                  <span class="stat-value">{{ getTotalOutstandingItems() }}</span>
                  <span class="stat-label">Total Items</span>
                </div>
                <div class="stat-item">
                  <span class="stat-value">{{ getEstimatedSavings() }}min</span>
                  <span class="stat-label">Time Saved</span>
                </div>
              </div>
            </div>

            <div class="so-list">
              <div v-for="so in customerSalesOrders" :key="so.so_id" class="so-card">
                <div class="so-content">
                  <div class="so-checkbox">
                    <input 
                      :id="`so-${so.so_id}`"
                      v-model="selectedSOs"
                      :value="so.so_id"
                      type="checkbox"
                    >
                    <label :for="`so-${so.so_id}`" class="checkbox-label"></label>
                  </div>
                  <div class="so-details">
                    <h4>{{ so.so_number }}</h4>
                    <div class="so-meta">
                      <span class="so-date">
                        <i class="fas fa-calendar"></i>
                        {{ formatDate(so.so_date) }}
                      </span>
                      <span class="so-status" :class="`status-${so.status.toLowerCase()}`">
                        {{ so.status }}
                      </span>
                    </div>
                    <div class="so-summary">
                      <span class="summary-item">
                        <i class="fas fa-boxes"></i>
                        {{ so.outstanding_items_count }} items
                      </span>
                      <span class="summary-item">
                        <i class="fas fa-weight"></i>
                        {{ so.outstanding_quantity }} units
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Step Navigation -->
          <div class="step-actions">
            <button 
              @click="loadConsolidatedItems" 
              :disabled="selectedSOs.length < 2"
              class="btn btn-primary"
            >
              Next: Consolidate Items ({{ selectedSOs.length }} SOs)
              <i class="fas fa-arrow-right"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Step 2: Consolidate Items -->
      <div v-if="currentStep === 2" class="step-content">
        <div class="section-card">
          <div class="section-header">
            <h2><i class="fas fa-layer-group"></i> Consolidated Items View</h2>
            <p>All items from selected SOs will be combined into ONE delivery</p>
          </div>

          <!-- Consolidation Summary -->
          <div class="consolidation-summary">
            <div class="summary-header">
              <h3>Consolidation Summary</h3>
              <div class="benefit-indicator">
                <span class="benefit-text">
                  <i class="fas fa-check-circle"></i>
                  Consolidating {{ selectedSOs.length }} Sales Orders
                </span>
                <span class="savings-text">
                  Estimated savings: {{ getEstimatedSavings() }} minutes
                </span>
              </div>
            </div>
            
            <div class="so-tags">
              <span v-for="so in selectedSODetails" :key="so.so_id" class="so-tag">
                {{ so.so_number }}
              </span>
            </div>
          </div>

          <!-- Consolidated Items Table -->
          <div class="consolidated-items">
            <h3>Items to be Delivered (Consolidated View)</h3>
            <div class="items-table">
              <table>
                <thead>
                  <tr>
                    <th width="50"></th>
                    <th>Item</th>
                    <th>From SOs</th>
                    <th>Total Outstanding</th>
                    <th>Deliver Qty</th>
                    <th>Warehouse</th>
                    <th>Available Stock</th>
                    <th>Batch</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in consolidatedItems" :key="item.consolidated_id" class="item-row">
                    <td>
                      <input 
                        :id="`item-${item.consolidated_id}`"
                        v-model="selectedItems"
                        :value="item.consolidated_id"
                        type="checkbox"
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
                      <div class="so-sources">
                        <span 
                          v-for="source in item.sources" 
                          :key="source.so_id"
                          class="so-source-tag"
                          :title="`${source.so_number}: ${source.quantity} ${item.uom_name}`"
                        >
                          {{ source.so_number }} ({{ source.quantity }})
                        </span>
                      </div>
                    </td>
                    <td>
                      <span class="total-qty">{{ item.total_outstanding }} {{ item.uom_name }}</span>
                    </td>
                    <td>
                      <input 
                        v-if="selectedItems.includes(item.consolidated_id)"
                        v-model.number="deliveryQuantities[item.consolidated_id]"
                        type="number"
                        :max="item.total_outstanding"
                        min="0.01"
                        step="0.01"
                        class="qty-input"
                        @input="validateConsolidatedQuantity(item)"
                      >
                      <span v-else class="text-muted">-</span>
                    </td>
                    <td>
                      <select 
                        v-if="selectedItems.includes(item.consolidated_id)"
                        v-model="selectedWarehouses[item.consolidated_id]"
                        class="warehouse-select"
                      >
                        <option value="">Select Warehouse</option>
                        <option 
                          v-for="warehouse in item.available_warehouses" 
                          :key="warehouse.warehouse_id" 
                          :value="warehouse.warehouse_id"
                        >
                          {{ warehouse.warehouse_name }} ({{ warehouse.available_quantity }})
                        </option>
                      </select>
                      <span v-else class="text-muted">-</span>
                    </td>
                    <td>
                      <span v-if="selectedWarehouses[item.consolidated_id]" class="stock-info">
                        {{ getAvailableStockForItem(item, selectedWarehouses[item.consolidated_id]) }}
                      </span>
                      <span v-else class="text-muted">-</span>
                    </td>
                    <td>
                      <input 
                        v-if="selectedItems.includes(item.consolidated_id)"
                        v-model="batchNumbers[item.consolidated_id]"
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

          <!-- Step Navigation -->
          <div class="step-actions">
            <button @click="currentStep = 1" class="btn btn-outline">
              <i class="fas fa-arrow-left"></i>
              Back
            </button>
            <button 
              @click="currentStep = 3"
              :disabled="!hasValidConsolidatedSelections"
              class="btn btn-primary"
            >
              Next: Create Single Delivery
              <i class="fas fa-arrow-right"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Step 3: Create Single Delivery -->
      <div v-if="currentStep === 3" class="step-content">
        <div class="section-card">
          <div class="section-header">
            <h2><i class="fas fa-truck"></i> Single Consolidated Delivery</h2>
            <p>Create ONE delivery record that combines all selected items from multiple SOs</p>
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
                  required
                >
              </div>

              <div class="form-group">
                <label for="delivery_date" class="required">Delivery Date</label>
                <input 
                  id="delivery_date"
                  v-model="deliveryForm.delivery_date"
                  type="date"
                  class="form-control"
                  required
                >
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

          <!-- Consolidated Delivery Preview -->
          <div class="delivery-preview">
            <h3>Consolidated Delivery Preview</h3>
            <div class="preview-content">
              
              <!-- Key Stats -->
              <div class="preview-stats">
                <div class="stat-card primary">
                  <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                  </div>
                  <div class="stat-content">
                    <div class="stat-value">{{ selectedSODetails.length }}</div>
                    <div class="stat-label">Sales Orders</div>
                  </div>
                </div>

                <div class="stat-card success">
                  <div class="stat-icon">
                    <i class="fas fa-boxes"></i>
                  </div>
                  <div class="stat-content">
                    <div class="stat-value">{{ getSelectedItemsCount() }}</div>
                    <div class="stat-label">Unique Items</div>
                  </div>
                </div>

                <div class="stat-card info">
                  <div class="stat-icon">
                    <i class="fas fa-weight"></i>
                  </div>
                  <div class="stat-content">
                    <div class="stat-value">{{ getTotalDeliveryQuantity() }}</div>
                    <div class="stat-label">Total Quantity</div>
                  </div>
                </div>

                <div class="stat-card warning">
                  <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                  </div>
                  <div class="stat-content">
                    <div class="stat-value">{{ getEstimatedSavings() }}min</div>
                    <div class="stat-label">Time Saved</div>
                  </div>
                </div>
              </div>

              <!-- Consolidation Benefits -->
              <div class="benefits-section">
                <h4><i class="fas fa-chart-line"></i> Consolidation Benefits</h4>
                <div class="benefits-grid">
                  <div class="benefit-item">
                    <i class="fas fa-truck"></i>
                    <span>Single delivery trip instead of {{ selectedSODetails.length }}</span>
                  </div>
                  <div class="benefit-item">
                    <i class="fas fa-dollar-sign"></i>
                    <span>Estimated cost saving: ${{ getEstimatedCostSaving() }}</span>
                  </div>
                  <div class="benefit-item">
                    <i class="fas fa-leaf"></i>
                    <span>Reduced environmental impact</span>
                  </div>
                  <div class="benefit-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>{{ Math.round(getEfficiencyImprovement()) }}% efficiency improvement</span>
                  </div>
                </div>
              </div>

              <!-- Items Summary -->
              <div class="consolidated-summary-table">
                <h4>Items in This Consolidated Delivery</h4>
                <table>
                  <thead>
                    <tr>
                      <th>Item</th>
                      <th>Source SOs</th>
                      <th>Quantity</th>
                      <th>Warehouse</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in getConsolidatedSummary()" :key="item.consolidated_id">
                      <td>
                        <div class="item-info">
                          <strong>{{ item.item_name }}</strong>
                          <small>{{ item.item_code }}</small>
                        </div>
                      </td>
                      <td>
                        <div class="source-sos">
                          <span 
                            v-for="so in item.source_sos" 
                            :key="so.so_id"
                            class="so-mini-tag"
                          >
                            {{ so.so_number }}
                          </span>
                        </div>
                      </td>
                      <td>{{ item.quantity }} {{ item.uom_name }}</td>
                      <td>{{ item.warehouse_name }}</td>
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
              @click="createConsolidatedDelivery"
              :disabled="isCreating || !isFormValid"
              class="btn btn-success btn-lg"
            >
              <i v-if="isCreating" class="fas fa-spinner fa-spin"></i>
              <i v-else class="fas fa-check"></i>
              {{ isCreating ? 'Creating...' : 'Create Consolidated Delivery' }}
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
          <h2>Consolidated Delivery Created!</h2>
        </div>
        <div class="modal-body">
          <p>Successfully created <strong>ONE delivery</strong> combining {{ consolidationResult.total_sos }} sales orders!</p>
          <div class="success-details">
            <div class="detail-row">
              <strong>Delivery Number:</strong> {{ consolidationResult.delivery_number }}
            </div>
            <div class="detail-row">
              <strong>Sales Orders:</strong> {{ consolidationResult.so_numbers.join(', ') }}
            </div>
            <div class="detail-row">
              <strong>Total Items:</strong> {{ consolidationResult.total_items }}
            </div>
            <div class="detail-row">
              <strong>Time Saved:</strong> {{ consolidationResult.time_saved }} minutes
            </div>
          </div>
        </div>
        <div class="modal-actions">
          <button @click="viewDelivery" class="btn btn-primary">
            View Delivery
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
  name: 'TrueConsolidatedDelivery',
  data() {
    return {
      currentStep: 1,
      isLoading: true,
      isCreating: false,
      
      // Customer & SO Selection
      customersWithOutstanding: [],
      selectedCustomerId: null,
      customerSalesOrders: [],
      selectedSOs: [],
      selectedSODetails: [],
      
      // Consolidated Items
      consolidatedItems: [],
      selectedItems: [],
      deliveryQuantities: {},
      selectedWarehouses: {},
      batchNumbers: {},
      
      // Form
      deliveryForm: {
        delivery_number: '',
        delivery_date: new Date().toISOString().split('T')[0],
        shipping_method: '',
        tracking_number: ''
      },
      
      // UI State
      showSuccessModal: false,
      consolidationResult: {}
    };
  },
  
  computed: {
    hasValidConsolidatedSelections() {
      return this.selectedItems.length > 0 && 
             this.selectedItems.every(id => 
               this.deliveryQuantities[id] > 0 && 
               this.selectedWarehouses[id]
             );
    },
    
    isFormValid() {
      return this.deliveryForm.delivery_number && 
             this.deliveryForm.delivery_date && 
             this.hasValidConsolidatedSelections;
    }
  },
  
  async mounted() {
    await this.loadCustomersWithOutstanding();
  },
  
  methods: {
    async loadCustomersWithOutstanding() {
      try {
        this.isLoading = true;
        const response = await axios.get('/deliveries/customers-with-outstanding');
        this.customersWithOutstanding = response.data.data;
      } catch (error) {
        this.$toast.error('Failed to load customers');
        console.error('Error:', error);
      } finally {
        this.isLoading = false;
      }
    },
    
    async selectCustomer(customerId) {
      this.selectedCustomerId = customerId;
      this.selectedSOs = [];
      
      try {
        const response = await axios.get(`/deliveries/customer-outstanding-sos/${customerId}`);
        this.customerSalesOrders = response.data.data;
      } catch (error) {
        this.$toast.error('Failed to load sales orders');
        console.error('Error:', error);
      }
    },
    
    async loadConsolidatedItems() {
      if (this.selectedSOs.length < 2) {
        this.$toast.warning('Please select at least 2 sales orders to consolidate');
        return;
      }
      
      try {
        this.isLoading = true;
        
        // Load detailed data for selected SOs
        this.selectedSODetails = [];
        for (const soId of this.selectedSOs) {
          const response = await axios.get(`/deliveries/outstanding-items/${soId}`);
          this.selectedSODetails.push(response.data.data);
        }
        
        // Consolidate items from all SOs
        this.consolidateItems();
        this.generateDeliveryNumber();
        this.currentStep = 2;
        
      } catch (error) {
        this.$toast.error('Failed to load items');
        console.error('Error:', error);
      } finally {
        this.isLoading = false;
      }
    },
    
    consolidateItems() {
      const itemMap = new Map();
      
      // Group items by item_id
      this.selectedSODetails.forEach(so => {
        so.outstanding_items.forEach(item => {
          const key = item.item_id;
          
          if (!itemMap.has(key)) {
            itemMap.set(key, {
              consolidated_id: `${key}_consolidated`,
              item_id: key,
              item_name: item.item_name,
              item_code: item.item_code,
              uom_name: item.uom_name,
              total_outstanding: 0,
              sources: [],
              available_warehouses: new Map(),
              so_line_ids: []
            });
          }
          
          const consolidated = itemMap.get(key);
          consolidated.total_outstanding += item.outstanding_quantity;
          consolidated.sources.push({
            so_id: so.so_id,
            so_number: so.so_number,
            so_line_id: item.so_line_id,
            quantity: item.outstanding_quantity
          });
          consolidated.so_line_ids.push(item.so_line_id);
          
          // Consolidate warehouse stock
          item.warehouse_stocks.forEach(stock => {
            const whKey = stock.warehouse_id;
            if (!consolidated.available_warehouses.has(whKey)) {
              consolidated.available_warehouses.set(whKey, {
                warehouse_id: stock.warehouse_id,
                warehouse_name: stock.warehouse_name,
                available_quantity: 0
              });
            }
            consolidated.available_warehouses.get(whKey).available_quantity += stock.available_quantity;
          });
        });
      });
      
      // Convert to array
      this.consolidatedItems = Array.from(itemMap.values()).map(item => ({
        ...item,
        available_warehouses: Array.from(item.available_warehouses.values())
      }));
    },
    
    onItemSelectionChange(item) {
      if (this.selectedItems.includes(item.consolidated_id)) {
        this.$set(this.deliveryQuantities, item.consolidated_id, item.total_outstanding);
        
        if (item.available_warehouses.length === 1) {
          this.$set(this.selectedWarehouses, item.consolidated_id, item.available_warehouses[0].warehouse_id);
        }
      } else {
        this.$delete(this.deliveryQuantities, item.consolidated_id);
        this.$delete(this.selectedWarehouses, item.consolidated_id);
        this.$delete(this.batchNumbers, item.consolidated_id);
      }
    },
    
    validateConsolidatedQuantity(item) {
      const qty = this.deliveryQuantities[item.consolidated_id];
      if (qty > item.total_outstanding) {
        this.$set(this.deliveryQuantities, item.consolidated_id, item.total_outstanding);
      }
    },
    
    getAvailableStockForItem(item, warehouseId) {
      const warehouse = item.available_warehouses.find(w => w.warehouse_id === warehouseId);
      return warehouse ? warehouse.available_quantity : 0;
    },
    
    getTotalOutstandingItems() {
      return this.selectedSOs.reduce((total, soId) => {
        const so = this.customerSalesOrders.find(s => s.so_id === soId);
        return total + (so ? so.outstanding_items_count : 0);
      }, 0);
    },
    
    getEstimatedSavings() {
      return Math.max(0, (this.selectedSOs.length - 1) * 15);
    },
    
    getEstimatedCostSaving() {
      return Math.max(0, (this.selectedSOs.length - 1) * 10);
    },
    
    getEfficiencyImprovement() {
      return Math.min(50, (this.selectedSOs.length - 1) * 12.5);
    },
    
    getSelectedItemsCount() {
      return this.selectedItems.length;
    },
    
    getTotalDeliveryQuantity() {
      return Object.values(this.deliveryQuantities).reduce((sum, qty) => sum + (qty || 0), 0);
    },
    
    getConsolidatedSummary() {
      return this.selectedItems.map(id => {
        const item = this.consolidatedItems.find(i => i.consolidated_id === id);
        const warehouse = item.available_warehouses.find(w => w.warehouse_id === this.selectedWarehouses[id]);
        
        return {
          consolidated_id: id,
          item_name: item.item_name,
          item_code: item.item_code,
          quantity: this.deliveryQuantities[id],
          uom_name: item.uom_name,
          warehouse_name: warehouse ? warehouse.warehouse_name : '',
          source_sos: item.sources.map(s => ({ so_id: s.so_id, so_number: s.so_number }))
        };
      });
    },
    
    generateDeliveryNumber() {
      const today = new Date();
      const year = today.getFullYear();
      const month = String(today.getMonth() + 1).padStart(2, '0');
      const day = String(today.getDate()).padStart(2, '0');
      const timestamp = Date.now().toString().slice(-4);
      
      this.deliveryForm.delivery_number = `CONS-${year}${month}${day}-${timestamp}`;
    },
    
    async createConsolidatedDelivery() {
      try {
        this.isCreating = true;
        
        // Prepare consolidated delivery data
        const deliveryLines = [];
        
        this.selectedItems.forEach(consolidatedId => {
          const item = this.consolidatedItems.find(i => i.consolidated_id === consolidatedId);
          const deliveryQty = this.deliveryQuantities[consolidatedId];
          const warehouseId = this.selectedWarehouses[consolidatedId];
          const batchNumber = this.batchNumbers[consolidatedId];
          
          // Distribute quantity across source SO lines proportionally
          const totalSourceQty = item.sources.reduce((sum, s) => sum + s.quantity, 0);
          
          item.sources.forEach(source => {
            const proportionalQty = (source.quantity / totalSourceQty) * deliveryQty;
            
            deliveryLines.push({
              so_line_id: source.so_line_id,
              delivered_quantity: proportionalQty,
              warehouse_id: warehouseId,
              batch_number: batchNumber
            });
          });
        });
        
        const payload = {
          delivery_number: this.deliveryForm.delivery_number,
          delivery_date: this.deliveryForm.delivery_date,
          shipping_method: this.deliveryForm.shipping_method,
          tracking_number: this.deliveryForm.tracking_number,
          customer_id: this.selectedCustomerId,
          is_consolidated: true,
          consolidated_so_ids: this.selectedSOs,
          delivery_lines: deliveryLines
        };
        
        const response = await axios.post('/deliveries/create-consolidated', payload);
        
        this.consolidationResult = {
          delivery_id: response.data.delivery.delivery_id,
          delivery_number: response.data.delivery.delivery_number,
          total_sos: this.selectedSOs.length,
          so_numbers: this.selectedSODetails.map(so => so.so_number),
          total_items: this.selectedItems.length,
          time_saved: this.getEstimatedSavings()
        };
        
        this.showSuccessModal = true;
        
      } catch (error) {
        this.$toast.error(error.response?.data?.message || 'Failed to create consolidated delivery');
        console.error('Error:', error);
      } finally {
        this.isCreating = false;
      }
    },
    
    closeSuccessModal() {
      this.showSuccessModal = false;
    },
    
    viewDelivery() {
      this.$router.push(`/sales/deliveries/${this.consolidationResult.delivery_id}`);
    },
    
    createAnother() {
      this.showSuccessModal = false;
      this.resetForm();
    },
    
    resetForm() {
      this.currentStep = 1;
      this.selectedCustomerId = null;
      this.selectedSOs = [];
      this.selectedSODetails = [];
      this.consolidatedItems = [];
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
    },
    
    formatDate(date) {
      return new Date(date).toLocaleDateString();
    }
  }
};
</script>

<style scoped>
/* Main Container */
.consolidated-delivery {
  max-width: 1200px;
  margin: 0 auto;
  padding: 1rem;
}

/* Page Header */
.page-header {
  text-align: center;
  margin-bottom: 2rem;
}

.page-title {
  color: var(--primary-color);
  font-size: 2.5rem;
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.page-subtitle {
  color: var(--text-muted);
  font-size: 1.2rem;
  font-weight: 500;
}

/* Step Indicator */
.step-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 2rem;
  padding: 1.5rem;
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.step {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.step-number {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background: #e9ecef;
  color: #6c757d;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  font-size: 1.2rem;
  margin-bottom: 0.5rem;
  transition: all 0.3s ease;
}

.step.active .step-number {
  background: var(--primary-color);
  color: white;
  transform: scale(1.1);
}

.step.completed .step-number {
  background: var(--success-color);
  color: white;
}

.step-label {
  font-size: 0.9rem;
  color: var(--text-muted);
  font-weight: 500;
  max-width: 120px;
}

.step.active .step-label {
  color: var(--primary-color);
  font-weight: 600;
}

.step-connector {
  width: 80px;
  height: 3px;
  background: #e9ecef;
  margin: 0 1rem;
  border-radius: 2px;
}

/* Section Card */
.section-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
  overflow: hidden;
  margin-bottom: 2rem;
}

.section-header {
  padding: 2rem;
  border-bottom: 1px solid #e9ecef;
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.section-header h2 {
  color: var(--primary-color);
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 1.5rem;
}

.section-header p {
  color: var(--text-muted);
  margin: 0;
  font-size: 1.1rem;
}

/* Customer Selection */
.customer-selection {
  padding: 2rem;
}

.customer-selection h3 {
  margin-bottom: 1.5rem;
  color: var(--text-primary);
  font-size: 1.3rem;
}

.customer-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}

.customer-card {
  border: 2px solid #e9ecef;
  border-radius: 12px;
  padding: 1.5rem;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  background: #f8f9fa;
}

.customer-card:hover {
  border-color: var(--primary-color);
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.customer-card.selected {
  border-color: var(--primary-color);
  background: linear-gradient(135deg, rgba(37, 99, 235, 0.1) 0%, rgba(37, 99, 235, 0.05) 100%);
}

.customer-info h4 {
  color: var(--text-primary);
  margin-bottom: 0.5rem;
  font-size: 1.2rem;
}

.customer-info p {
  color: var(--text-muted);
  margin-bottom: 1rem;
}

.customer-stats {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.customer-stats .stat {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--text-secondary);
  font-size: 0.9rem;
}

.selection-indicator {
  position: absolute;
  top: 1rem;
  right: 1rem;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: var(--success-color);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transform: scale(0);
  transition: all 0.3s ease;
}

.customer-card.selected .selection-indicator {
  opacity: 1;
  transform: scale(1);
}

/* SO Selection */
.so-selection {
  padding: 2rem;
  border-top: 1px solid #e9ecef;
  background: white;
}

.so-selection h3 {
  margin-bottom: 1.5rem;
  color: var(--text-primary);
  font-size: 1.3rem;
}

.consolidation-preview {
  background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}

.preview-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 1rem;
}

.stat-item {
  text-align: center;
}

.stat-value {
  font-size: 2rem;
  font-weight: bold;
  color: var(--primary-color);
  display: block;
}

.stat-label {
  font-size: 0.9rem;
  color: var(--text-muted);
}

/* SO List */
.so-list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 1rem;
}

.so-card {
  border: 2px solid #e9ecef;
  border-radius: 8px;
  overflow: hidden;
  transition: all 0.3s ease;
}

.so-card:hover {
  border-color: var(--primary-color);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.so-content {
  padding: 1rem;
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}

.so-checkbox input[type="checkbox"] {
  width: 20px;
  height: 20px;
  cursor: pointer;
}

.so-details {
  flex: 1;
}

.so-details h4 {
  color: var(--primary-color);
  margin-bottom: 0.5rem;
  font-size: 1.1rem;
}

.so-meta {
  display: flex;
  gap: 1rem;
  margin-bottom: 1rem;
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

.so-summary {
  display: flex;
  gap: 1rem;
}

.summary-item {
  color: var(--text-secondary);
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

/* Consolidation Summary */
.consolidation-summary {
  padding: 2rem;
  background: linear-gradient(135deg, #e8f5e8 0%, #ffffff 100%);
  border-bottom: 1px solid #e9ecef;
}

.summary-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.summary-header h3 {
  color: var(--success-color);
  margin: 0;
}

.benefit-indicator {
  text-align: right;
}

.benefit-text {
  display: block;
  color: var(--success-color);
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.savings-text {
  display: block;
  color: var(--text-muted);
  font-size: 0.9rem;
}

.so-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.so-tag {
  background: var(--success-color);
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.9rem;
  font-weight: 500;
}

/* Consolidated Items */
.consolidated-items {
  padding: 2rem;
}

.consolidated-items h3 {
  margin-bottom: 1.5rem;
  color: var(--text-primary);
}

.items-table {
  overflow-x: auto;
  border-radius: 8px;
  border: 1px solid #e9ecef;
}

.items-table table {
  width: 100%;
  border-collapse: collapse;
}

.items-table th {
  background: var(--primary-color);
  color: white;
  padding: 1rem;
  text-align: left;
  font-weight: 600;
}

.items-table td {
  padding: 1rem;
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

.so-sources {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}

.so-source-tag {
  background: #e3f2fd;
  color: #1976d2;
  padding: 0.25rem 0.5rem;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 500;
}

.total-qty {
  font-weight: bold;
  color: var(--primary-color);
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

/* Delivery Preview */
.delivery-preview {
  padding: 2rem;
  border-top: 1px solid #e9ecef;
  background: #f8f9fa;
}

.delivery-preview h3 {
  margin-bottom: 1.5rem;
  color: var(--text-primary);
}

.preview-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: white;
  padding: 1.5rem;
  border-radius: 12px;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stat-icon {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  color: white;
}

.stat-card.primary .stat-icon {
  background: var(--primary-color);
}

.stat-card.success .stat-icon {
  background: var(--success-color);
}

.stat-card.info .stat-icon {
  background: #17a2b8;
}

.stat-card.warning .stat-icon {
  background: var(--warning-color);
}

.stat-content .stat-value {
  font-size: 1.8rem;
  font-weight: bold;
  color: var(--text-primary);
}

.stat-content .stat-label {
  color: var(--text-muted);
  font-size: 0.9rem;
}

/* Benefits Section */
.benefits-section {
  margin-bottom: 2rem;
}

.benefits-section h4 {
  margin-bottom: 1rem;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.benefits-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
}

.benefit-item {
  background: white;
  padding: 1rem;
  border-radius: 8px;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.benefit-item i {
  color: var(--success-color);
  font-size: 1.2rem;
}

/* Consolidated Summary Table */
.consolidated-summary-table {
  background: white;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.consolidated-summary-table h4 {
  padding: 1rem 1.5rem;
  margin: 0;
  background: var(--primary-color);
  color: white;
}

.consolidated-summary-table table {
  width: 100%;
  border-collapse: collapse;
}

.consolidated-summary-table th {
  background: #f8f9fa;
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: var(--text-primary);
}

.consolidated-summary-table td {
  padding: 1rem;
  border-bottom: 1px solid #e9ecef;
}

.source-sos {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}

.so-mini-tag {
  background: #e9ecef;
  padding: 0.25rem 0.5rem;
  border-radius: 10px;
  font-size: 0.8rem;
  color: var(--text-secondary);
}

/* Form */
.delivery-form {
  padding: 2rem;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
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

/* Step Actions */
.step-actions {
  padding: 2rem;
  border-top: 1px solid #e9ecef;
  display: flex;
  justify-content: space-between;
  gap: 1rem;
}

/* Buttons */
.btn {
  padding: 0.875rem 2rem;
  border: none;
  border-radius: 8px;
  font-size: 0.95rem;
  font-weight: 600;
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
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.btn-success {
  background: var(--success-color);
  color: white;
}

.btn-success:hover:not(:disabled) {
  background: #218838;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
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

.btn-lg {
  padding: 1rem 2.5rem;
  font-size: 1.1rem;
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
  background: linear-gradient(135deg, #e8f5e8 0%, #ffffff 100%);
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

.success-details {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 1rem;
  margin-top: 1rem;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.detail-row:last-child {
  margin-bottom: 0;
}

.modal-actions {
  padding: 1.5rem;
  border-top: 1px solid #e9ecef;
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
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

/* Responsive */
@media (max-width: 768px) {
  .consolidated-delivery {
    padding: 0.5rem;
  }
  
  .page-title {
    font-size: 1.8rem;
    flex-direction: column;
    gap: 0.5rem;
  }
  
  .step-indicator {
    flex-direction: column;
    gap: 1rem;
  }
  
  .step-connector {
    width: 2px;
    height: 30px;
  }
  
  .customer-grid {
    grid-template-columns: 1fr;
  }
  
  .so-list {
    grid-template-columns: 1fr;
  }
  
  .preview-stats {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .benefits-grid {
    grid-template-columns: 1fr;
  }
  
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .step-actions {
    flex-direction: column;
  }
}

.text-muted {
  color: var(--text-muted);
}
</style>