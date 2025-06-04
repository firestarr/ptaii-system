<!-- ConsolidatedDeliveryAdvancedEdit.vue - Complete Version -->
<template>
  <div class="consolidated-delivery-edit">
    <!-- Loading State -->
    <div v-if="isLoading" class="loading-container">
      <div class="loading-spinner"></div>
      <p>Loading delivery data...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="hasError" class="error-container">
      <div class="error-icon">
        <i class="fas fa-exclamation-triangle"></i>
      </div>
      <h2>Error Loading Delivery</h2>
      <p>{{ errorMessage }}</p>
      <div class="error-actions">
        <button @click="loadDeliveryData" class="btn btn-primary">
          <i class="fas fa-sync"></i>
          Retry
        </button>
        <button @click="goBack" class="btn btn-outline">
          <i class="fas fa-arrow-left"></i>
          Go Back
        </button>
      </div>
    </div>

    <!-- Main Content -->
    <div v-else class="content-container">
      <!-- Page Header -->
      <div class="page-header">
        <div class="header-content">
          <h1 class="page-title">
            <i class="fas fa-edit"></i>
            Edit Consolidated Delivery
          </h1>
          <div class="delivery-info">
            <span class="delivery-number">{{ deliveryData.delivery_number }}</span>
            <span class="status-badge" :class="`status-${deliveryData.status?.toLowerCase()}`">
              {{ deliveryData.status }}
            </span>
          </div>
        </div>
      </div>

      <!-- Consolidation Summary (Read-only) -->
      <div class="consolidation-summary-card">
        <div class="card-header">
          <h2><i class="fas fa-layer-group"></i> Consolidation Details</h2>
          <span class="readonly-badge">Read-only</span>
        </div>
        <div class="card-content">
          <div class="consolidation-stats">
            <div class="stat-item">
              <div class="stat-value">{{ consolidatedSOs.length }}</div>
              <div class="stat-label">Sales Orders</div>
            </div>
            <div class="stat-item">
              <div class="stat-value">{{ deliveryLines.length }}</div>
              <div class="stat-label">Items</div>
            </div>
            <div class="stat-item">
              <div class="stat-value">{{ getTotalQuantity() }}</div>
              <div class="stat-label">Total Qty</div>
            </div>
          </div>
          <div class="so-tags">
            <span v-for="so in consolidatedSOs" :key="so.so_id" class="so-tag">
              {{ so.so_number }}
            </span>
          </div>
        </div>
      </div>

      <!-- Edit Form -->
      <div class="edit-form-card">
        <!-- Header Information -->
        <div class="form-section">
          <div class="section-header">
            <h3><i class="fas fa-truck"></i> Delivery Information</h3>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label for="delivery_date" class="required">Delivery Date</label>
              <input 
                id="delivery_date"
                v-model="form.delivery_date"
                type="date"
                class="form-control"
                :disabled="!canEditHeader"
              >
            </div>

            <div class="form-group">
              <label for="shipping_method">Shipping Method</label>
              <select 
                id="shipping_method"
                v-model="form.shipping_method"
                class="form-control"
                :disabled="!canEditHeader"
              >
                <option value="">Select Method</option>
                <option value="courier">Courier</option>
                <option value="truck">Truck</option>
                <option value="pickup">Customer Pickup</option>
                <option value="air_freight">Air Freight</option>
                <option value="sea_freight">Sea Freight</option>
              </select>
            </div>

            <div class="form-group">
              <label for="tracking_number">Tracking Number</label>
              <input 
                id="tracking_number"
                v-model="form.tracking_number"
                type="text"
                class="form-control"
                placeholder="Enter tracking number"
                :disabled="!canEditHeader"
              >
            </div>

            <div class="form-group">
              <label for="status" class="required">Status</label>
              <select 
                id="status"
                v-model="form.status"
                class="form-control"
              >
                <option value="Pending">Pending</option>
                <option value="In Transit">In Transit</option>
                <option value="Completed">Completed</option>
                <option value="Cancelled">Cancelled</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Line Management -->
        <div class="form-section">
          <div class="section-header">
            <h3><i class="fas fa-list"></i> Delivery Items Management</h3>
            <div class="section-actions">
              <button 
                v-if="canManageLines"
                @click="showAddItemModal = true"
                class="btn btn-success"
                :disabled="availableItems.length === 0"
              >
                <i class="fas fa-plus"></i>
                Add Items ({{ availableItems.length }} available)
              </button>
              <button 
                v-if="hasChanges"
                @click="saveAllChanges"
                class="btn btn-primary"
                :disabled="isSaving"
              >
                <i v-if="isSaving" class="fas fa-spinner fa-spin"></i>
                <i v-else class="fas fa-save"></i>
                Save All Changes
              </button>
            </div>
          </div>

          <!-- Delivery Lines Table -->
          <div class="lines-table-container">
            <table class="lines-table">
              <thead>
                <tr>
                  <th width="40"></th>
                  <th>Item</th>
                  <th>Source SO</th>
                  <th>Ordered Qty</th>
                  <th>Delivered Qty</th>
                  <th>Warehouse</th>
                  <th>Batch</th>
                  <th>Available Stock</th>
                  <th v-if="canManageLines" width="120">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr 
                  v-for="(line, index) in deliveryLines" 
                  :key="line.line_id"
                  class="line-row"
                  :class="{ 
                    'editing': editingLineId === line.line_id,
                    'changed': hasLineChanged(line),
                    'new': line.isNew
                  }"
                >
                  <td>
                    <div class="line-number">{{ index + 1 }}</div>
                  </td>
                  <td>
                    <div class="item-info">
                      <strong>{{ line.item?.name || line.item_name }}</strong>
                      <small>{{ line.item?.item_code || line.item_code }}</small>
                    </div>
                  </td>
                  <td>
                    <span class="so-badge">
                      {{ getSONumber(line) }}
                    </span>
                  </td>
                  <td>
                    <span class="ordered-qty">{{ getOrderedQuantity(line) }}</span>
                  </td>
                  <td>
                    <div v-if="editingLineId === line.line_id" class="edit-quantity">
                      <input 
                        v-model.number="line.delivered_quantity"
                        type="number"
                        :max="getMaxAllowedQuantity(line)"
                        min="0.01"
                        step="0.01"
                        class="qty-input"
                        @keyup.enter="saveLineEdit(line)"
                        @keyup.escape="cancelLineEdit()"
                      >
                      <div class="qty-constraints">
                        Max: {{ getMaxAllowedQuantity(line) }}
                      </div>
                    </div>
                    <div v-else class="quantity-display">
                      <span class="qty-value">{{ line.delivered_quantity }}</span>
                      <span class="qty-unit">{{ getUOMName(line) }}</span>
                    </div>
                  </td>
                  <td>
                    <div v-if="editingLineId === line.line_id" class="edit-warehouse">
                      <select 
                        v-model="line.warehouse_id"
                        class="warehouse-select"
                        @change="onWarehouseChange(line)"
                      >
                        <option value="">Select Warehouse</option>
                        <option 
                          v-for="warehouse in getAvailableWarehouses(line)" 
                          :key="warehouse.warehouse_id"
                          :value="warehouse.warehouse_id"
                        >
                          {{ warehouse.warehouse_name || warehouse.name }} 
                          (Available: {{ warehouse.available_quantity || 0 }})
                        </option>
                      </select>
                    </div>
                    <div v-else class="warehouse-display">
                      <span class="warehouse-name">{{ getWarehouseName(line) }}</span>
                      <small class="warehouse-code">{{ getWarehouseCode(line) }}</small>
                    </div>
                  </td>
                  <td>
                    <div v-if="editingLineId === line.line_id">
                      <input 
                        v-model="line.batch_number"
                        type="text"
                        class="batch-input"
                        placeholder="Optional"
                      >
                    </div>
                    <span v-else>{{ line.batch_number || '-' }}</span>
                  </td>
                  <td>
                    <span class="stock-info" :class="getStockStatusClass(line)">
                      {{ getAvailableStock(line) }}
                    </span>
                  </td>
                  <td v-if="canManageLines">
                    <div class="line-actions">
                      <div v-if="editingLineId === line.line_id" class="edit-actions">
                        <button 
                          @click="saveLineEdit(line)"
                          class="btn btn-sm btn-success"
                          :disabled="!isLineValid(line)"
                        >
                          <i class="fas fa-check"></i>
                        </button>
                        <button 
                          @click="cancelLineEdit()"
                          class="btn btn-sm btn-outline"
                        >
                          <i class="fas fa-times"></i>
                        </button>
                      </div>
                      <div v-else class="view-actions">
                        <button 
                          @click="startEditLine(line)"
                          class="btn btn-sm btn-outline"
                          title="Edit"
                        >
                          <i class="fas fa-edit"></i>
                        </button>
                        <button 
                          @click="removeLine(line)"
                          class="btn btn-sm btn-danger"
                          title="Remove"
                        >
                          <i class="fas fa-trash"></i>
                        </button>
                      </div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>

            <div v-if="deliveryLines.length === 0" class="empty-lines">
              <div class="empty-icon">
                <i class="fas fa-inbox"></i>
              </div>
              <p>No delivery items</p>
              <button 
                v-if="canManageLines"
                @click="showAddItemModal = true"
                class="btn btn-primary"
              >
                Add Items
              </button>
            </div>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
          <button @click="goBack" class="btn btn-outline btn-lg">
            <i class="fas fa-arrow-left"></i>
            Cancel
          </button>
          <button 
            @click="updateDelivery"
            :disabled="isUpdating || !hasValidChanges"
            class="btn btn-primary btn-lg"
          >
            <i v-if="isUpdating" class="fas fa-spinner fa-spin"></i>
            <i v-else class="fas fa-save"></i>
            {{ isUpdating ? 'Updating...' : 'Update Delivery' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Add Items Modal -->
    <div v-if="showAddItemModal" class="modal-overlay" @click="closeAddItemModal">
      <div class="modal-content add-item-modal" @click.stop>
        <div class="modal-header">
          <h2><i class="fas fa-plus-circle"></i> Add Items to Delivery</h2>
          <button @click="closeAddItemModal" class="modal-close">
            <i class="fas fa-times"></i>
          </button>
        </div>

        <div class="modal-body">
          <div class="available-items-info">
            <p>Select items from the consolidated sales orders that haven't been fully delivered yet:</p>
          </div>

          <!-- Available Items List -->
          <div class="available-items-list">
            <div 
              v-for="item in availableItems" 
              :key="`${item.so_line_id}`"
              class="available-item"
              :class="{ selected: selectedNewItems.includes(item.so_line_id) }"
              @click="toggleItemSelection(item)"
            >
              <div class="item-checkbox">
                <input 
                  :id="`item-${item.so_line_id}`"
                  v-model="selectedNewItems"
                  :value="item.so_line_id"
                  type="checkbox"
                >
              </div>
              <div class="item-details">
                <div class="item-header">
                  <strong>{{ item.item_name }}</strong>
                  <span class="item-code">{{ item.item_code }}</span>
                </div>
                <div class="item-meta">
                  <span class="so-info">
                    <i class="fas fa-file-alt"></i>
                    {{ item.so_number }}
                  </span>
                  <span class="qty-info">
                    <i class="fas fa-boxes"></i>
                    {{ item.outstanding_quantity }} {{ item.uom_name }} available
                  </span>
                </div>
                <div class="warehouse-options">
                  <label>Available in warehouses:</label>
                  <div class="warehouse-list">
                    <span 
                      v-for="stock in item.warehouse_stocks" 
                      :key="stock.warehouse_id"
                      class="warehouse-option"
                    >
                      {{ stock.warehouse_name }} ({{ stock.available_quantity }})
                    </span>
                  </div>
                </div>
              </div>
              <div class="selection-indicator">
                <i class="fas fa-check"></i>
              </div>
            </div>
          </div>

          <div v-if="availableItems.length === 0" class="no-available-items">
            <div class="empty-icon">
              <i class="fas fa-check-circle"></i>
            </div>
            <p>All items from consolidated sales orders have been added to delivery.</p>
          </div>
        </div>

        <div class="modal-actions">
          <button 
            @click="closeAddItemModal" 
            class="btn btn-outline"
          >
            Cancel
          </button>
          <button 
            @click="addSelectedItems"
            :disabled="selectedNewItems.length === 0 || isAddingItems"
            class="btn btn-success"
          >
            <i v-if="isAddingItems" class="fas fa-spinner fa-spin"></i>
            <i v-else class="fas fa-plus"></i>
            Add {{ selectedNewItems.length }} Item{{ selectedNewItems.length !== 1 ? 's' : '' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Confirmation Modal -->
    <div v-if="showConfirmModal" class="modal-overlay">
      <div class="modal-content confirm-modal">
        <div class="modal-header">
          <h3>{{ confirmModal.title }}</h3>
        </div>
        <div class="modal-body">
          <p>{{ confirmModal.message }}</p>
        </div>
        <div class="modal-actions">
          <button @click="cancelConfirm" class="btn btn-outline">
            Cancel
          </button>
          <button 
            @click="executeConfirm" 
            :class="`btn ${confirmModal.type === 'danger' ? 'btn-danger' : 'btn-primary'}`"
          >
            {{ confirmModal.confirmText }}
          </button>
        </div>
      </div>
    </div>

    <!-- Toast Notifications Container -->
    <div class="toast-container">
      <div 
        v-for="toast in toasts" 
        :key="toast.id"
        :class="`toast toast-${toast.type}`"
        @click="removeToast(toast.id)"
      >
        <div class="toast-content">
          <i :class="`fas ${getToastIcon(toast.type)}`"></i>
          <span>{{ toast.message }}</span>
        </div>
        <button class="toast-close">
          <i class="fas fa-times"></i>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'ConsolidatedDeliveryAdvancedEdit',
  props: {
    deliveryId: {
      type: [String, Number],
      required: true
    }
  },
  
  data() {
    return {
      isLoading: true,
      isUpdating: false,
      isSaving: false,
      isAddingItems: false,
      hasError: false,
      errorMessage: '',
      
      // Data
      deliveryData: {},
      originalDeliveryData: {},
      consolidatedSOs: [],
      deliveryLines: [],
      availableItems: [],
      allWarehouses: [],
      
      // Form
      form: {
        delivery_date: '',
        shipping_method: '',
        tracking_number: '',
        status: ''
      },
      originalForm: {},
      
      // Editing state
      editingLineId: null,
      editingLineBackup: null,
      changedLines: new Set(),
      
      // Modal state
      showAddItemModal: false,
      selectedNewItems: [],
      
      // Confirmation
      showConfirmModal: false,
      confirmModal: {
        title: '',
        message: '',
        confirmText: '',
        type: 'primary',
        action: null
      },

      // Toast notifications
      toasts: [],
      toastCounter: 0
    };
  },
  
  computed: {
    canEditHeader() {
      return this.deliveryData && this.deliveryData.status !== 'Completed';
    },
    
    canManageLines() {
      return this.deliveryData && this.deliveryData.status === 'Pending';
    },
    
    hasChanges() {
      return this.hasFormChanges || this.changedLines.size > 0;
    },
    
    hasFormChanges() {
      return JSON.stringify(this.form) !== JSON.stringify(this.originalForm);
    },
    
    hasValidChanges() {
      return this.hasChanges && this.isFormValid && this.areAllLinesValid;
    },
    
    isFormValid() {
      return this.form.delivery_date && this.form.status;
    },
    
    areAllLinesValid() {
      return this.deliveryLines.every(line => this.isLineValid(line));
    }
  },
  
  async mounted() {
    window.addEventListener('unhandledrejection', this.handleUnhandledRejection);
    await this.loadAllWarehouses();
    await this.loadDeliveryData();
  },

  beforeUnmount() {
    window.removeEventListener('unhandledrejection', this.handleUnhandledRejection);
  },
  
  methods: {
    handleUnhandledRejection(event) {
      console.error('Unhandled promise rejection:', event.reason);
      this.showToast('An unexpected error occurred', 'error');
      event.preventDefault();
    },

    // Toast notification methods
    showToast(message, type = 'info', duration = 5000) {
      const toast = {
        id: ++this.toastCounter,
        message,
        type,
        duration
      };
      
      this.toasts.push(toast);
      
      setTimeout(() => {
        this.removeToast(toast.id);
      }, duration);
    },

    removeToast(id) {
      const index = this.toasts.findIndex(t => t.id === id);
      if (index > -1) {
        this.toasts.splice(index, 1);
      }
    },

    getToastIcon(type) {
      const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
      };
      return icons[type] || icons.info;
    },

    // Load all warehouses for dropdown
    async loadAllWarehouses() {
      try {
        const response = await axios.get('/warehouses');
        this.allWarehouses = response.data.data || response.data || [];
        
      } catch (error) {
        console.warn('Failed to load warehouses:', error);
        this.allWarehouses = [];
      }
    },

    // ENHANCED: Load delivery data with stock information
    async loadDeliveryData() {
      try {
        this.isLoading = true;
        this.hasError = false;
        
        
        
        if (!this.deliveryId) {
          throw new Error('No delivery ID provided');
        }
        
        // Load delivery dengan consolidated info
        const response = await axios.get(`/deliveries/${this.deliveryId}`);
        
        
        this.deliveryData = response.data.data || response.data;
        
        if (!this.deliveryData || !this.deliveryData.delivery_number) {
          throw new Error('Invalid delivery data received');
        }
        
        this.originalDeliveryData = JSON.parse(JSON.stringify(this.deliveryData));
        
        // Set form data dengan fallback values
this.form = {
  delivery_date: this.deliveryData.delivery_date ? this.deliveryData.delivery_date.split('T')[0] : new Date().toISOString().split('T')[0],
  shipping_method: this.deliveryData.shipping_method || '',
  tracking_number: this.deliveryData.tracking_number || '',
  status: this.deliveryData.status || 'Pending'
};
        this.originalForm = JSON.parse(JSON.stringify(this.form));
        
        // Process delivery lines with proper stock data
        this.deliveryLines = this.safeGetDeliveryLines();
        this.consolidatedSOs = this.safeGetConsolidatedSOs();
        
        
        
        // NEW: Fetch additional stock info for items that don't have it
        const itemsNeedingStock = this.deliveryLines.filter(line => 
          !line.stock_info && line.item_id && line.warehouse_id
        );
        
        if (itemsNeedingStock.length > 0) {
          
          await this.fetchStockInfo(itemsNeedingStock);
        }
        
        // Load available items
        try {
          await this.loadAvailableItems();
        } catch (availableItemsError) {
          console.warn('Failed to load available items:', availableItemsError);
          this.availableItems = [];
        }
        
      } catch (error) {
        console.error('Error loading delivery data:', error);
        this.hasError = true;
        this.errorMessage = error.response?.data?.message || error.message || 'Failed to load delivery data';
        
        this.showToast(this.errorMessage, 'error');
        
        if (error.response?.status === 404) {
          setTimeout(() => {
            this.$router.go(-1);
          }, 2000);
        }
      } finally {
        this.isLoading = false;
      }
    },

    // ENHANCED: Better delivery lines processing with stock info
    safeGetDeliveryLines() {
      try {
        const lines = this.deliveryData.delivery_lines || 
                     this.deliveryData.deliveryLines || 
                     this.deliveryData.lines || [];
        
        
        
        // Process each line with better error handling and stock data
        return lines.map((line) => {
          
          
          const processedLine = {
            line_id: line.line_id,
            so_line_id: line.so_line_id,
            item_id: line.item_id,
            item_name: line.item?.name || line.item_name || 'Unknown Item',
            item_code: line.item?.item_code || line.item_code || '',
            delivered_quantity: parseFloat(line.delivered_quantity) || 0,
            warehouse_id: line.warehouse_id,
            batch_number: line.batch_number || '',
            
            // Better warehouse data handling
            warehouse: line.warehouse ? {
              warehouse_id: line.warehouse.warehouse_id,
              name: line.warehouse.name,
              code: line.warehouse.code || ''
            } : null,
            
            // Item data
            item: line.item || {},
            
            // Sales order line data
            sales_order_line: line.sales_order_line || line.salesOrderLine || {},
            
            // NEW: Stock information from backend
            stock_info: line.stock_info || null,
            available_warehouses: line.available_warehouses || [],
            
            // Flags
            isNew: false,
            _originalData: { ...line } // Keep original for reference
          };
          
          // Cache stock info if available
          if (line.stock_info && line.item_id && line.warehouse_id) {
            this.cacheStockInfo(line.item_id, line.warehouse_id, line.stock_info);
          }
          
          
          return processedLine;
        });
      } catch (error) {
        console.error('Error processing delivery lines:', error);
        return [];
      }
    },

    // NEW: Cache stock information
    cacheStockInfo(itemId, warehouseId, stockInfo) {
      try {
        if (!this.stockCache) {
          this.stockCache = {};
        }
        const cacheKey = `${itemId}_${warehouseId}`;
        this.stockCache[cacheKey] = stockInfo;
      } catch (error) {
        console.warn('Error caching stock info:', error);
      }
    },

    // NEW: Fetch real-time stock information
    async fetchStockInfo(items) {
      try {
        if (!items || items.length === 0) return;
        
        const payload = {
          items: items.map(item => ({
            item_id: item.item_id,
            warehouse_id: item.warehouse_id
          }))
        };
        
        const response = await axios.post('/deliveries/stock-info', payload);
        
        if (response.data && response.data.data) {
          // Update stock cache
          response.data.data.forEach(item => {
            if (item.stock_info) {
              this.cacheStockInfo(item.item_id, item.warehouse_id, item.stock_info);
            }
          });
          
          // Force reactivity update
          this.$forceUpdate();
        }
      } catch (error) {
        console.warn('Failed to fetch stock info:', error);
      }
    },

    safeGetConsolidatedSOs() {
      try {
        return this.deliveryData.consolidated_sales_orders || 
               this.deliveryData.consolidatedSalesOrders || 
               this.deliveryData.consolidated_sos || [];
      } catch (error) {
        console.warn('Error processing consolidated SOs:', error);
        return [];
      }
    },

    async loadAvailableItems() {
      try {
        const soIds = (this.consolidatedSOs || [])
          .map(so => so.so_id)
          .filter(id => id && !isNaN(id));
        
        if (soIds.length === 0) {
          
          this.availableItems = [];
          return;
        }
        
        
        
        // Use the new API endpoint
        const response = await axios.post('/deliveries/outstanding-items-for-sos', {
          so_ids: soIds
        });
        
        
        
        // Filter out items that are already in delivery
        const existingSOLineIds = (this.deliveryLines || []).map(line => line.so_line_id);
        this.availableItems = (response.data.data || []).filter(item => 
          item && !existingSOLineIds.includes(item.so_line_id)
        );
        
        
        
      } catch (error) {
        console.error('Failed to load available items:', error);
        this.availableItems = [];
      }
    },

    // Warehouse name getter with better fallback
    getWarehouseName(line) {
      try {
        // Priority 1: Direct warehouse object
        if (line.warehouse && line.warehouse.name) {
          return line.warehouse.name;
        }
        
        // Priority 2: Find in all warehouses by ID
        if (line.warehouse_id && this.allWarehouses.length > 0) {
          const warehouse = this.allWarehouses.find(w => w.warehouse_id === line.warehouse_id);
          if (warehouse) {
            return warehouse.name;
          }
        }
        
        // Priority 3: From original item data (for new items)
        if (line._originalItem && line.warehouse_id) {
          const warehouse = line._originalItem.warehouse_stocks?.find(w => w.warehouse_id === line.warehouse_id);
          if (warehouse) {
            return warehouse.warehouse_name;
          }
        }
        
        return 'Unknown Warehouse';
      } catch (error) {
        console.warn('Error getting warehouse name for line:', line, error);
        return 'Error Loading';
      }
    },

    // Get warehouse code
    getWarehouseCode(line) {
      try {
        if (line.warehouse && line.warehouse.code) {
          return line.warehouse.code;
        }
        
        if (line.warehouse_id && this.allWarehouses.length > 0) {
          const warehouse = this.allWarehouses.find(w => w.warehouse_id === line.warehouse_id);
          if (warehouse) {
            return warehouse.code || '';
          }
        }
        
        return '';
      } catch (error) {
        console.warn('Error getting warehouse code:', error);
        return '';
      }
    },

    // ENHANCED: Better available warehouses handling
    getAvailableWarehouses(line) {
      try {
        // Priority 1: From original item data (for new items from outstanding)
        if (line._originalItem && line._originalItem.warehouse_stocks) {
          return line._originalItem.warehouse_stocks.map(stock => ({
            warehouse_id: stock.warehouse_id,
            warehouse_name: stock.warehouse_name,
            name: stock.warehouse_name,
            available_quantity: stock.available_quantity,
            total_quantity: stock.total_quantity
          }));
        }
        
        // Priority 2: From backend provided available warehouses
        if (line.available_warehouses && Array.isArray(line.available_warehouses)) {
          return line.available_warehouses.map(warehouse => ({
            warehouse_id: warehouse.warehouse_id,
            warehouse_name: warehouse.warehouse_name,
            name: warehouse.warehouse_name,
            available_quantity: warehouse.available_quantity,
            total_quantity: warehouse.total_quantity
          }));
        }
        
        // Priority 3: From processed original data
        if (line._originalData && line._originalData.available_warehouses) {
          return line._originalData.available_warehouses.map(warehouse => ({
            warehouse_id: warehouse.warehouse_id,
            warehouse_name: warehouse.warehouse_name,
            name: warehouse.warehouse_name,
            available_quantity: warehouse.available_quantity,
            total_quantity: warehouse.total_quantity
          }));
        }
        
        // Priority 4: All warehouses (fallback) - fetch stock data real-time
        if (this.allWarehouses.length > 0 && line.item_id) {
          return this.allWarehouses.map(warehouse => ({
            warehouse_id: warehouse.warehouse_id,
            warehouse_name: warehouse.name,
            name: warehouse.name,
            available_quantity: this.getWarehouseStockForItem(line.item_id, warehouse.warehouse_id),
            total_quantity: 'N/A'
          }));
        }
        
        return this.allWarehouses || [];
      } catch (error) {
        console.warn('Error getting available warehouses:', error);
        return this.allWarehouses || [];
      }
    },

    // NEW: Get warehouse stock for specific item
    getWarehouseStockForItem(itemId, warehouseId) {
      try {
        if (this.stockCache) {
          const cacheKey = `${itemId}_${warehouseId}`;
          return this.stockCache[cacheKey]?.available_quantity || 0;
        }
        return 'N/A';
      } catch (error) {
        return 'N/A';
      }
    },

    // Handle warehouse change during editing
    onWarehouseChange(line) {
      try {
        
        
        // Update the line's warehouse object if we have warehouse data
        const warehouse = this.allWarehouses.find(w => w.warehouse_id === line.warehouse_id);
        if (warehouse) {
          line.warehouse = {
            warehouse_id: warehouse.warehouse_id,
            name: warehouse.name,
            code: warehouse.code || ''
          };
        }
      } catch (error) {
        console.warn('Error handling warehouse change:', error);
      }
    },

    // Helper methods
    getSONumber(line) {
      try {
        if (line._originalItem) {
          return line._originalItem.so_number || 'N/A';
        }
        
        if (line.sales_order_line && line.sales_order_line.sales_order) {
          return line.sales_order_line.sales_order.so_number;
        }
        
        return 'N/A';
      } catch (error) {
        console.warn('Error getting SO number:', error);
        return 'N/A';
      }
    },

    getOrderedQuantity(line) {
      try {
        if (line._originalItem) {
          return parseFloat(line._originalItem.ordered_quantity) || 0;
        }
        return parseFloat(line.sales_order_line?.quantity) || 0;
      } catch (error) {
        console.warn('Error getting ordered quantity:', error);
        return 0;
      }
    },

    getMaxAllowedQuantity(line) {
      try {
        if (line._originalItem) {
          return parseFloat(line._originalItem.outstanding_quantity) || 0;
        }
        return parseFloat(line.delivered_quantity) || 0;
      } catch (error) {
        console.warn('Error getting max allowed quantity:', error);
        return parseFloat(line.delivered_quantity) || 0;
      }
    },

    getUOMName(line) {
      try {
        if (line._originalItem) {
          return line._originalItem.uom_name || '';
        }
        return line.item?.unit_of_measure?.name || 
               line.item?.unitOfMeasure?.name || '';
      } catch (error) {
        console.warn('Error getting UOM name:', error);
        return '';
      }
    },

    getAvailableStock(line) {
      try {
        // Priority 1: For new items from outstanding (has _originalItem)
        if (line._originalItem && line.warehouse_id) {
          const warehouse = line._originalItem.warehouse_stocks?.find(w => w.warehouse_id === line.warehouse_id);
          return warehouse?.available_quantity || 0;
        }
        
        // Priority 2: For existing delivery lines (has stock_info from backend)
        if (line.stock_info) {
          return line.stock_info.available_quantity || 0;
        }
        
        // Priority 3: Try to get from processed line data
        if (line._originalData && line._originalData.stock_info) {
          return line._originalData.stock_info.available_quantity || 0;
        }
        
        // Priority 4: Real-time fetch if we have the data
        if (line.item_id && line.warehouse_id && this.stockCache) {
          const cacheKey = `${line.item_id}_${line.warehouse_id}`;
          if (this.stockCache[cacheKey]) {
            return this.stockCache[cacheKey].available_quantity || 0;
          }
        }
        
        return 'Loading...';
      } catch (error) {
        console.warn('Error getting available stock:', error);
        return 'Error';
      }
    },

    getStockStatusClass(line) {
      try {
        const available = this.getAvailableStock(line);
        if (available === 'N/A') return '';
        
        const quantity = parseFloat(line.delivered_quantity) || 0;
        
        if (available >= quantity) return 'stock-ok';
        if (available > 0) return 'stock-low';
        return 'stock-none';
      } catch (error) {
        console.warn('Error getting stock status class:', error);
        return '';
      }
    },

    // Line editing methods
    startEditLine(line) {
      try {
        if (this.editingLineId) {
          this.cancelLineEdit();
        }
        
        this.editingLineId = line.line_id;
        this.editingLineBackup = JSON.parse(JSON.stringify(line));
        
      } catch (error) {
        console.error('Error starting line edit:', error);
        this.showToast('Failed to start editing line', 'error');
      }
    },
    
    async saveLineEdit(line) {
      try {
        if (!this.isLineValid(line)) {
          this.showToast('Please check the quantity and warehouse selection', 'error');
          return;
        }
        
        // Mark as changed if values differ from original
        if (this.hasLineChanged(line)) {
          this.changedLines.add(line.line_id);
        } else {
          this.changedLines.delete(line.line_id);
        }
        
        this.editingLineId = null;
        this.editingLineBackup = null;
        
        
        this.showToast('Line changes saved locally. Click "Save All Changes" to persist.', 'success');
        
      } catch (error) {
        console.error('Error saving line edit:', error);
        this.showToast('Failed to save line changes', 'error');
      }
    },
    
    cancelLineEdit() {
      try {
        if (this.editingLineBackup) {
          const index = this.deliveryLines.findIndex(l => l.line_id === this.editingLineId);
          if (index !== -1) {
            this.deliveryLines.splice(index, 1, { ...this.editingLineBackup });
          }
        }
        
        this.editingLineId = null;
        this.editingLineBackup = null;
        
      } catch (error) {
        console.error('Error canceling line edit:', error);
      }
    },

    removeLine(line) {
      try {
        this.confirmModal = {
          title: 'Remove Delivery Item',
          message: `Are you sure you want to remove "${line.item_name}" from this delivery?`,
          confirmText: 'Remove',
          type: 'danger',
          action: () => this.executeRemoveLine(line)
        };
        this.showConfirmModal = true;
      } catch (error) {
        console.error('Error setting up remove confirmation:', error);
        this.showToast('Failed to setup remove confirmation', 'error');
      }
    },
    
    executeRemoveLine(line) {
      try {
        const index = this.deliveryLines.findIndex(l => l.line_id === line.line_id);
        if (index !== -1) {
          this.deliveryLines.splice(index, 1);
          this.changedLines.add(`removed_${line.line_id}`);
          
          // Add back to available items
          this.loadAvailableItems();
          
          this.showToast('Item removed from delivery', 'success');
        }
      } catch (error) {
        console.error('Error removing line:', error);
        this.showToast('Failed to remove item', 'error');
      }
    },

    // Add Items Methods
    toggleItemSelection(item) {
      try {
        if (!item || !item.so_line_id) return;
        
        const index = this.selectedNewItems.indexOf(item.so_line_id);
        if (index > -1) {
          this.selectedNewItems.splice(index, 1);
        } else {
          this.selectedNewItems.push(item.so_line_id);
        }
      } catch (error) {
        console.error('Error toggling item selection:', error);
      }
    },
    
    async addSelectedItems() {
      if (this.selectedNewItems.length === 0) return;
      
      try {
        this.isAddingItems = true;
        
        // Get selected items details
        const itemsToAdd = this.availableItems.filter(item => 
          item && this.selectedNewItems.includes(item.so_line_id)
        );
        
        if (itemsToAdd.length === 0) {
          throw new Error('No valid items selected');
        }
        
        // Add to delivery lines
        itemsToAdd.forEach(item => {
          const newLine = {
            line_id: `new_${Date.now()}_${item.so_line_id}`,
            so_line_id: item.so_line_id,
            item_id: item.item_id,
            item_name: item.item_name || 'Unknown Item',
            item_code: item.item_code || '',
            delivered_quantity: parseFloat(item.outstanding_quantity) || 0,
            warehouse_id: (item.warehouse_stocks && item.warehouse_stocks[0]) ? item.warehouse_stocks[0].warehouse_id : null,
            batch_number: '',
            isNew: true,
            _originalItem: item
          };
          
          this.deliveryLines.push(newLine);
          this.changedLines.add(newLine.line_id);
        });
        
        // Update available items
        await this.loadAvailableItems();
        
        this.closeAddItemModal();
        this.showToast(`${itemsToAdd.length} item(s) added to delivery`, 'success');
        
      } catch (error) {
        console.error('Error adding items:', error);
        this.showToast(error.message || 'Failed to add items', 'error');
      } finally {
        this.isAddingItems = false;
      }
    },
    
    closeAddItemModal() {
      this.showAddItemModal = false;
      this.selectedNewItems = [];
    },

    // Save All Changes
    async saveAllChanges() {
      try {
        this.isSaving = true;
        
        // Prepare changed lines data
        const changedLinesData = this.deliveryLines
          .filter(line => this.changedLines.has(line.line_id))
          .map(line => ({
            line_id: line.isNew ? null : line.line_id,
            so_line_id: line.so_line_id,
            delivered_quantity: parseFloat(line.delivered_quantity) || 0,
            warehouse_id: line.warehouse_id,
            batch_number: line.batch_number || null,
            is_new: line.isNew || false
          }));
        
        // Get removed lines
        const removedLines = Array.from(this.changedLines)
          .filter(id => typeof id === 'string' && id.startsWith('removed_'))
          .map(id => id.replace('removed_', ''));
        
        const payload = {
          delivery_lines: changedLinesData,
          removed_lines: removedLines
        };
        
        
        
        await axios.put(`/deliveries/consolidated/${this.deliveryId}/lines`, payload);
        
        this.changedLines.clear();
        this.showToast('All changes saved successfully', 'success');
        
        // Reload data
        await this.loadDeliveryData();
        
      } catch (error) {
        console.error('Error saving changes:', error);
        this.showToast(error.response?.data?.message || 'Failed to save changes', 'error');
      } finally {
        this.isSaving = false;
      }
    },

    // Update Delivery
    async updateDelivery() {
      try {
        this.isUpdating = true;
        
        // Save line changes first if any
        if (this.changedLines.size > 0) {
          await this.saveAllChanges();
        }
        
        // Update header if changed
        if (this.hasFormChanges) {
          await axios.put(`/deliveries/${this.deliveryId}`, this.form);
        }
        
        this.showToast('Delivery updated successfully', 'success');
        
        // If status changed to Completed
        if (this.form.status === 'Completed' && this.originalForm.status !== 'Completed') {
          this.showToast('Delivery completed and stock updated', 'success');
        }
        
        // Refresh data
        await this.loadDeliveryData();
        
      } catch (error) {
        console.error('Error updating delivery:', error);
        this.showToast(error.response?.data?.message || 'Failed to update delivery', 'error');
      } finally {
        this.isUpdating = false;
      }
    },

    // Validation methods
    hasLineChanged(line) {
      try {
        const original = this.originalDeliveryData.delivery_lines?.find(l => l.line_id === line.line_id);
        if (!original) return line.isNew;
        
        return (
          parseFloat(original.delivered_quantity) !== parseFloat(line.delivered_quantity) ||
          original.warehouse_id !== line.warehouse_id ||
          (original.batch_number || '') !== (line.batch_number || '')
        );
      } catch (error) {
        console.warn('Error checking line changes:', error);
        return false;
      }
    },
    
    isLineValid(line) {
      try {
        const quantity = parseFloat(line.delivered_quantity) || 0;
        const maxQuantity = this.getMaxAllowedQuantity(line);
        
        return (
          quantity > 0 &&
          quantity <= maxQuantity &&
          line.warehouse_id
        );
      } catch (error) {
        console.warn('Error validating line:', error);
        return false;
      }
    },

    // Utility methods
    getTotalQuantity() {
      try {
        return this.deliveryLines.reduce((sum, line) => {
          return sum + (parseFloat(line.delivered_quantity) || 0);
        }, 0);
      } catch (error) {
        console.warn('Error calculating total quantity:', error);
        return 0;
      }
    },

    // Confirmation methods
    cancelConfirm() {
      this.showConfirmModal = false;
      this.confirmModal = {};
    },
    
    executeConfirm() {
      try {
        if (this.confirmModal.action) {
          this.confirmModal.action();
        }
        this.showConfirmModal = false;
        this.confirmModal = {};
      } catch (error) {
        console.error('Error executing confirmation:', error);
        this.showToast('Failed to execute action', 'error');
      }
    },

    goBack() {
      try {
        if (this.hasChanges) {
          this.confirmModal = {
            title: 'Unsaved Changes',
            message: 'You have unsaved changes. Are you sure you want to leave?',
            confirmText: 'Leave',
            type: 'danger',
            action: () => this.$router.go(-1)
          };
          this.showConfirmModal = true;
        } else {
          this.$router.go(-1);
        }
      } catch (error) {
        console.error('Error going back:', error);
        this.$router.go(-1);
      }
    }
  }
};
</script>

<style scoped>
/* Main Container */
.consolidated-delivery-edit {
  max-width: 1400px;
  margin: 0 auto;
  padding: 1rem;
  min-height: 100vh;
  font-family: 'Inter', sans-serif;
}

/* Loading State */
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

/* Error State */
.error-container {
  text-align: center;
  padding: 3rem;
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.error-icon {
  font-size: 4rem;
  color: var(--danger-color);
  margin-bottom: 1rem;
}

.error-actions {
  display: flex;
  gap: 1rem;
  justify-content: center;
  margin-top: 2rem;
}

/* Page Header */
.page-header {
  margin-bottom: 2rem;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.page-title {
  color: var(--primary-color);
  font-size: 2rem;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.delivery-info {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.delivery-number {
  font-size: 1.2rem;
  font-weight: 600;
  color: var(--text-primary);
}

.status-badge {
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.9rem;
  font-weight: 600;
  text-transform: uppercase;
}

.status-pending {
  background: #fff3cd;
  color: #856404;
}

.status-in-transit, .status-in.transit {
  background: #cff4fc;
  color: #055160;
}

.status-completed {
  background: #d1e7dd;
  color: #0f5132;
}

.status-cancelled {
  background: #f8d7da;
  color: #721c24;
}

/* Consolidation Summary Card */
.consolidation-summary-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  margin-bottom: 2rem;
  overflow: hidden;
}

.card-header {
  padding: 1.5rem;
  background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
  border-bottom: 1px solid #e9ecef;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header h2 {
  margin: 0;
  color: var(--primary-color);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.readonly-badge {
  background: #6c757d;
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 500;
}

.card-content {
  padding: 1.5rem;
}

.consolidation-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 1rem;
  margin-bottom: 1rem;
}

.stat-item {
  text-align: center;
  padding: 0.75rem;
  background: #f8f9fa;
  border-radius: 8px;
}

.stat-value {
  font-size: 1.5rem;
  font-weight: bold;
  color: var(--primary-color);
}

.stat-label {
  font-size: 0.9rem;
  color: var(--text-muted);
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

/* Edit Form Card */
.edit-form-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  overflow: hidden;
}

.form-section {
  border-bottom: 1px solid #e9ecef;
}

.form-section:last-child {
  border-bottom: none;
}

.section-header {
  padding: 1.5rem;
  background: #f8f9fa;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.section-header h3 {
  margin: 0;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.section-actions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

/* Form Elements */
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  padding: 1.5rem;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: var(--text-primary);
}

.form-group label.required::after {
  content: ' *';
  color: red;
}

.form-control {
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

.form-control:disabled {
  background-color: #f8f9fa;
  opacity: 0.7;
}

/* Lines Table */
.lines-table-container {
  padding: 1.5rem;
}

.lines-table {
  width: 100%;
  border-collapse: collapse;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.lines-table th {
  background: var(--primary-color);
  color: white;
  padding: 1rem;
  text-align: left;
  font-weight: 600;
}

.lines-table td {
  padding: 1rem;
  border-bottom: 1px solid #e9ecef;
  vertical-align: middle;
}

.line-row {
  transition: all 0.3s ease;
}

.line-row:hover {
  background-color: #f8f9fa;
}

.line-row.editing {
  background-color: #fff3cd;
  box-shadow: inset 0 0 0 2px #ffc107;
}

.line-row.changed {
  background-color: #e7f3ff;
  border-left: 4px solid var(--primary-color);
}

.line-row.new {
  background-color: #d1e7dd;
  border-left: 4px solid var(--success-color);
}

.line-number {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: var(--primary-color);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  font-size: 0.9rem;
}

.item-info strong {
  display: block;
  color: var(--text-primary);
  margin-bottom: 0.25rem;
}

.item-info small {
  color: var(--text-muted);
}

.so-badge {
  background: #e3f2fd;
  color: #1976d2;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 500;
}

.ordered-qty {
  font-weight: 500;
  color: var(--text-secondary);
}

.quantity-display {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.qty-value {
  font-weight: bold;
  color: var(--primary-color);
  font-size: 1.1rem;
}

.qty-unit {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.edit-quantity {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.qty-input {
  width: 100px;
  padding: 0.5rem;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.qty-constraints {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.edit-warehouse {
  min-width: 150px;
}

.warehouse-select {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.warehouse-display {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.warehouse-name {
  font-weight: 500;
  color: var(--text-primary);
}

.warehouse-code {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.batch-input {
  width: 100px;
  padding: 0.5rem;
  border: 1px solid #ddd;
  border-radius: 4px;
}

/* Stock Status */
.stock-info {
  font-weight: 500;
}

.stock-info.stock-ok {
  color: var(--success-color);
}

.stock-info.stock-low {
  color: var(--warning-color);
}

.stock-info.stock-none {
  color: var(--danger-color);
}

/* Line Actions */
.line-actions {
  display: flex;
  gap: 0.25rem;
}

.edit-actions, .view-actions {
  display: flex;
  gap: 0.25rem;
}

/* Empty State */
.empty-lines {
  text-align: center;
  padding: 3rem;
  color: var(--text-muted);
}

.empty-icon {
  font-size: 4rem;
  margin-bottom: 1rem;
  opacity: 0.3;
}

/* Buttons */
.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 8px;
  font-size: 0.9rem;
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

.btn-sm {
  padding: 0.5rem 0.75rem;
  font-size: 0.8rem;
}

.btn-lg {
  padding: 1rem 2rem;
  font-size: 1rem;
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

.btn-danger {
  background: var(--danger-color);
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background: #c82333;
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

/* Form Actions */
.form-actions {
  padding: 2rem;
  background: #f8f9fa;
  display: flex;
  justify-content: space-between;
  gap: 1rem;
}

/* Modals */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 12px;
  max-width: 90vw;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.add-item-modal {
  width: 800px;
}

.confirm-modal {
  width: 400px;
}

.modal-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e9ecef;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h2, .modal-header h3 {
  margin: 0;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.modal-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  color: var(--text-muted);
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 4px;
}

.modal-close:hover {
  background: #f8f9fa;
  color: var(--text-primary);
}

.modal-body {
  padding: 1.5rem;
}

.modal-actions {
  padding: 1rem 1.5rem;
  border-top: 1px solid #e9ecef;
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
}

/* Available Items */
.available-items-info {
  margin-bottom: 1rem;
  padding: 1rem;
  background: #e3f2fd;
  border-radius: 8px;
  color: #1976d2;
}

.available-items-list {
  max-height: 400px;
  overflow-y: auto;
  border: 1px solid #e9ecef;
  border-radius: 8px;
}

.available-item {
  padding: 1rem;
  border-bottom: 1px solid #e9ecef;
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
}

.available-item:hover {
  background: #f8f9fa;
}

.available-item.selected {
  background: #e3f2fd;
  border-color: var(--primary-color);
}

.available-item:last-child {
  border-bottom: none;
}

.item-checkbox input {
  width: 18px;
  height: 18px;
}

.item-details {
  flex: 1;
}

.item-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 0.5rem;
}

.item-header strong {
  color: var(--text-primary);
}

.item-code {
  color: var(--text-muted);
  font-size: 0.9rem;
}

.item-meta {
  display: flex;
  gap: 1rem;
  margin-bottom: 0.5rem;
  flex-wrap: wrap;
}

.so-info, .qty-info {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.9rem;
  color: var(--text-secondary);
}

.warehouse-options {
  margin-top: 0.5rem;
}

.warehouse-options label {
  font-size: 0.8rem;
  color: var(--text-muted);
  margin-bottom: 0.25rem;
  display: block;
}

.warehouse-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.warehouse-option {
  background: #f8f9fa;
  padding: 0.25rem 0.5rem;
  border-radius: 12px;
  font-size: 0.8rem;
  color: var(--text-secondary);
}

.selection-indicator {
  position: absolute;
  top: 1rem;
  right: 1rem;
  width: 24px;
  height: 24px;
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

.available-item.selected .selection-indicator {
  opacity: 1;
  transform: scale(1);
}

.no-available-items {
  text-align: center;
  padding: 2rem;
  color: var(--text-muted);
}

/* Toast Notifications */
.toast-container {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 2000;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.toast {
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  padding: 1rem;
  min-width: 300px;
  max-width: 400px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  animation: slideIn 0.3s ease-out;
  cursor: pointer;
}

.toast-success {
  border-left: 4px solid var(--success-color);
}

.toast-error {
  border-left: 4px solid var(--danger-color);
}

.toast-warning {
  border-left: 4px solid var(--warning-color);
}

.toast-info {
  border-left: 4px solid var(--primary-color);
}

.toast-content {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex: 1;
}

.toast-close {
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 0.25rem;
}

@keyframes slideIn {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

/* CSS Variables */
:root {
  --primary-color: #2563eb;
  --primary-dark: #1d4ed8;
  --success-color: #059669;
  --warning-color: #d97706;
  --danger-color: #dc2626;
  --text-primary: #1f2937;
  --text-secondary: #6b7280;
  --text-muted: #9ca3af;
}

/* Responsive */
@media (max-width: 768px) {
  .consolidated-delivery-edit {
    padding: 0.5rem;
  }
  
  .header-content {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .page-title {
    font-size: 1.5rem;
  }
  
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .consolidation-stats {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .section-header {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .form-actions {
    flex-direction: column;
  }
  
  .add-item-modal {
    width: 95vw;
  }
  
  .lines-table-container {
    overflow-x: auto;
  }
  
  .item-meta {
    flex-direction: column;
    gap: 0.25rem;
  }

  .toast-container {
    top: 10px;
    right: 10px;
    left: 10px;
  }

  .toast {
    min-width: auto;
    max-width: none;
  }
}
</style>