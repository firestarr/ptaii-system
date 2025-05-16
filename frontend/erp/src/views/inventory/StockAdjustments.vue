<!-- src/views/inventory/StockAdjustment.vue -->
<template>
  <div class="stock-adjustment">
    <div class="page-header">
      <div class="header-left">
        <router-link to="/item-stocks" class="back-link">
          <i class="fas fa-arrow-left"></i> Back to Stock List
        </router-link>
        <h1>Stock Adjustment</h1>
      </div>
    </div>

    <div class="content-container">
      <div class="adjustment-form-card">
        <div class="card-header">
          <h2>Adjust Stock Quantity</h2>
        </div>
        <div class="card-body">
          <form @submit.prevent="submitAdjustment">
            <div class="form-grid">
              <div class="form-group span-full">
                <label for="itemSelect">Item <span class="required">*</span></label>
                <div class="select-wrapper">
                  <select 
                    id="itemSelect" 
                    v-model="form.itemId" 
                    @change="onItemChange" 
                    :disabled="loading"
                    required
                  >
                    <option value="">Select an item</option>
                    <option v-for="item in items" :key="item.item_id" :value="item.item_id">
                      {{ item.item_code }} - {{ item.name }}
                    </option>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label for="warehouseSelect">Warehouse <span class="required">*</span></label>
                <div class="select-wrapper">
                  <select 
                    id="warehouseSelect" 
                    v-model="form.warehouseId" 
                    @change="onWarehouseChange" 
                    :disabled="loading || !form.itemId"
                    required
                  >
                    <option value="">Select warehouse</option>
                    <option v-for="warehouse in warehouses" :key="warehouse.warehouse_id" :value="warehouse.warehouse_id">
                      {{ warehouse.name }}
                    </option>
                  </select>
                </div>
              </div>

              <div class="form-group" v-if="form.warehouseId && selectedItem">
                <div class="stock-info-box">
                  <div class="info-label">Current Stock:</div>
                  <div class="info-value">{{ currentStock }} {{ selectedItem.uom?.symbol || '' }}</div>
                </div>
              </div>

              <div class="form-group">
                <label for="adjustmentTypeSelect">Adjustment Type <span class="required">*</span></label>
                <div class="select-wrapper">
                  <select 
                    id="adjustmentTypeSelect" 
                    v-model="form.adjustmentType" 
                    :disabled="loading"
                    required
                  >
                    <option value="absolute">Set to Specific Value</option>
                    <option value="increase">Increase Quantity</option>
                    <option value="decrease">Decrease Quantity</option>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label for="quantityInput">
                  {{ form.adjustmentType === 'absolute' ? 'New Quantity' : 'Adjustment Amount' }} 
                  <span class="required">*</span>
                </label>
                <input 
                  type="number" 
                  id="quantityInput" 
                  v-model.number="form.quantity" 
                  min="0" 
                  step="0.01" 
                  :disabled="loading || !form.warehouseId"
                  required
                />
                <div class="input-hint" v-if="selectedItem">
                  {{ selectedItem.uom?.symbol || 'units' }}
                </div>
                <div class="error-message" v-if="quantityError">
                  {{ quantityError }}
                </div>
              </div>

              <div class="form-group span-full">
                <label for="reasonSelect">Reason <span class="required">*</span></label>
                <div class="select-wrapper">
                  <select 
                    id="reasonSelect" 
                    v-model="form.reason" 
                    :disabled="loading"
                    required
                  >
                    <option value="">Select a reason</option>
                    <option value="inventory_count">Inventory Count</option>
                    <option value="damaged">Damaged/Expired Goods</option>
                    <option value="lost">Lost/Stolen</option>
                    <option value="system_error">System Error</option>
                    <option value="returned">Returned Items</option>
                    <option value="production">Production Excess/Shortage</option>
                    <option value="other">Other</option>
                  </select>
                </div>
              </div>

              <div v-if="form.reason === 'other'" class="form-group span-full">
                <label for="otherReasonInput">Specify Reason <span class="required">*</span></label>
                <input 
                  type="text" 
                  id="otherReasonInput" 
                  v-model="form.otherReason" 
                  placeholder="Specify other reason for adjustment" 
                  :disabled="loading"
                  required
                />
              </div>

              <div class="form-group span-full">
                <label for="referenceInput">Reference Number</label>
                <input 
                  type="text" 
                  id="referenceInput" 
                  v-model="form.referenceNumber" 
                  placeholder="Optional reference number" 
                  :disabled="loading"
                  maxlength="50"
                />
              </div>

              <div class="form-group span-full">
                <label for="notesInput">Notes</label>
                <textarea 
                  id="notesInput" 
                  v-model="form.notes" 
                  rows="3" 
                  placeholder="Additional notes for this adjustment" 
                  :disabled="loading"
                ></textarea>
              </div>
            </div>

            <div class="form-actions">
              <button 
                type="button" 
                class="btn btn-secondary" 
                @click="resetForm" 
                :disabled="loading"
              >
                Reset
              </button>
              <button 
                type="submit" 
                class="btn btn-primary" 
                :disabled="loading || !isFormValid"
              >
                <i v-if="loading" class="fas fa-spinner fa-spin"></i>
                <span v-else>Submit Adjustment</span>
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="adjustment-preview-card" v-if="form.itemId && form.warehouseId && form.quantity >= 0">
        <div class="card-header">
          <h2>Adjustment Preview</h2>
        </div>
        <div class="card-body">
          <div class="preview-container">
            <div class="stock-box">
              <div class="warehouse-title">{{ warehouseName }}</div>
              <div class="item-name">{{ selectedItem?.item_code }} - {{ selectedItem?.name }}</div>
              <div class="stock-visualization">
                <div class="stock-level">
                  <div class="stock-label">Current Stock:</div>
                  <div class="stock-value">{{ currentStock }} {{ selectedItem?.uom?.symbol || '' }}</div>
                </div>
                
                <div class="adjustment-arrow" v-if="form.adjustmentType !== 'absolute'">
                  <i 
                    :class="form.adjustmentType === 'increase' ? 'fas fa-arrow-up' : 'fas fa-arrow-down'"
                    :style="{color: form.adjustmentType === 'increase' ? 'var(--success-color)' : 'var(--danger-color)'}"
                  ></i>
                  <div 
                    class="adjustment-value"
                    :style="{color: form.adjustmentType === 'increase' ? 'var(--success-color)' : 'var(--danger-color)'}"
                  >
                    {{ form.adjustmentType === 'increase' ? '+' : '-' }}{{ form.quantity }} {{ selectedItem?.uom?.symbol || '' }}
                  </div>
                </div>
                
                <div class="stock-level result">
                  <div class="stock-label">{{ form.adjustmentType === 'absolute' ? 'New Stock:' : 'Result:' }}</div>
                  <div class="stock-value">{{ calculateResultStock() }} {{ selectedItem?.uom?.symbol || '' }}</div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="adjustment-details">
            <div class="detail-item">
              <div class="detail-label">Type:</div>
              <div class="detail-value">{{ formatAdjustmentType(form.adjustmentType) }}</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Reason:</div>
              <div class="detail-value">{{ formatReason() }}</div>
            </div>
            <div class="detail-item" v-if="form.referenceNumber">
              <div class="detail-label">Reference:</div>
              <div class="detail-value">{{ form.referenceNumber }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Success Modal -->
    <div class="modal" v-if="showSuccessModal">
      <div class="modal-backdrop" @click="showSuccessModal = false"></div>
      <div class="modal-content">
        <div class="modal-header">
          <h2>Adjustment Successful</h2>
          <button class="close-btn" @click="showSuccessModal = false">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="success-message">
            <i class="fas fa-check-circle"></i>
            <p>Stock adjustment completed successfully!</p>
          </div>
          <div class="adjustment-details">
            <div class="adjustment-detail">
              <div class="detail-label">Item:</div>
              <div class="detail-value">{{ selectedItem?.item_code }} - {{ selectedItem?.name }}</div>
            </div>
            <div class="adjustment-detail">
              <div class="detail-label">Warehouse:</div>
              <div class="detail-value">{{ warehouseName }}</div>
            </div>
            <div class="adjustment-detail">
              <div class="detail-label">Type:</div>
              <div class="detail-value">{{ formatAdjustmentType(form.adjustmentType) }}</div>
            </div>
            <div class="adjustment-detail">
              <div class="detail-label">Change:</div>
              <div class="detail-value">
                {{ form.adjustmentType === 'absolute' ? 'Set to ' : (form.adjustmentType === 'increase' ? '+' : '-') }}
                {{ form.quantity }} {{ selectedItem?.uom?.symbol || '' }}
              </div>
            </div>
            <div class="adjustment-detail">
              <div class="detail-label">New Stock:</div>
              <div class="detail-value">{{ calculateResultStock() }} {{ selectedItem?.uom?.symbol || '' }}</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showSuccessModal = false">Close</button>
          <button class="btn btn-primary" @click="newAdjustment">New Adjustment</button>
        </div>
      </div>
    </div>

    <!-- Error Modal -->
    <div class="modal" v-if="showErrorModal">
      <div class="modal-backdrop" @click="showErrorModal = false"></div>
      <div class="modal-content">
        <div class="modal-header">
          <h2>Adjustment Failed</h2>
          <button class="close-btn" @click="showErrorModal = false">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <p>{{ errorMessage }}</p>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary" @click="showErrorModal = false">Try Again</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'StockAdjustment',
  data() {
    return {
      form: {
        itemId: '',
        warehouseId: '',
        quantity: 0,
        adjustmentType: 'absolute',
        reason: '',
        otherReason: '',
        referenceNumber: '',
        notes: ''
      },
      items: [],
      warehouses: [],
      selectedItem: null,
      currentStock: 0,
      loading: false,
      quantityError: '',
      showSuccessModal: false,
      showErrorModal: false,
      errorMessage: ''
    };
  },
  computed: {
    isFormValid() {
      if (!this.form.itemId || !this.form.warehouseId || this.form.quantity < 0 || !this.form.reason) {
        return false;
      }
      
      if (this.form.reason === 'other' && !this.form.otherReason) {
        return false;
      }
      
      if (this.form.adjustmentType === 'decrease' && this.form.quantity > this.currentStock) {
        return false;
      }
      
      return true;
    },
    warehouseName() {
      const warehouse = this.warehouses.find(w => w.warehouse_id == this.form.warehouseId);
      return warehouse ? warehouse.name : '';
    }
  },
  created() {
    this.fetchData();
    this.initFromQuery();
  },
  methods: {
    async fetchData() {
      this.loading = true;
      
      try {
        // Fetch all items
        const itemsResponse = await axios.get('/items');
        if (itemsResponse.data && itemsResponse.data.data) {
          this.items = itemsResponse.data.data;
        }
        
        // Fetch all warehouses
        const warehousesResponse = await axios.get('/warehouses');
        if (warehousesResponse.data && warehousesResponse.data.data) {
          this.warehouses = warehousesResponse.data.data;
        }
      } catch (err) {
        console.error('Error fetching data:', err);
        this.errorMessage = 'Failed to load item and warehouse data. Please try again later.';
        this.showErrorModal = true;
      } finally {
        this.loading = false;
      }
    },
    initFromQuery() {
      // If item and/or warehouse passed in query parameters, initialize the form
      const { item, warehouse } = this.$route.query;
      
      if (item) {
        this.form.itemId = parseInt(item, 10) || '';
        this.$nextTick(() => {
          this.onItemChange();
        });
      }
      
      if (warehouse) {
        this.form.warehouseId = parseInt(warehouse, 10) || '';
        this.$nextTick(() => {
          this.onWarehouseChange();
        });
      }
    },
    async onItemChange() {
      if (!this.form.itemId) {
        this.selectedItem = null;
        this.currentStock = 0;
        return;
      }
      
      this.loading = true;
      
      try {
        // Get the selected item details
        const itemResponse = await axios.get(`/items/${this.form.itemId}`);
        if (itemResponse.data && itemResponse.data.data) {
          this.selectedItem = itemResponse.data.data;
        }
        
        // If warehouse is already selected, get current stock
        if (this.form.warehouseId) {
          this.onWarehouseChange();
        }
      } catch (err) {
        console.error('Error fetching item data:', err);
        this.errorMessage = 'Failed to load item data. Please try again later.';
        this.showErrorModal = true;
      } finally {
        this.loading = false;
      }
    },
    async onWarehouseChange() {
      if (!this.form.itemId || !this.form.warehouseId) {
        this.currentStock = 0;
        return;
      }
      
      this.loading = true;
      
      try {
        // Get the item's stock in the selected warehouse
        const stockResponse = await axios.get(`/item-stocks/item/${this.form.itemId}`);
        if (stockResponse.data && stockResponse.data.data) {
          const itemStock = stockResponse.data.data;
          
          // Find stock in the selected warehouse
          const warehouseStock = itemStock.warehouse_stocks.find(
            stock => stock.warehouse_id == this.form.warehouseId
          );
          
          this.currentStock = warehouseStock ? warehouseStock.quantity : 0;
        }
      } catch (err) {
        console.error('Error fetching warehouse stock:', err);
        this.errorMessage = 'Failed to load warehouse stock data. Please try again later.';
        this.showErrorModal = true;
      } finally {
        this.loading = false;
      }
    },
    calculateResultStock() {
      if (this.form.adjustmentType === 'absolute') {
        return parseFloat(this.form.quantity) || 0;
      } else if (this.form.adjustmentType === 'increase') {
        return parseFloat((this.currentStock + parseFloat(this.form.quantity || 0)).toFixed(2));
      } else if (this.form.adjustmentType === 'decrease') {
        return parseFloat(Math.max(0, this.currentStock - parseFloat(this.form.quantity || 0)).toFixed(2));
      }
      return 0;
    },
    formatAdjustmentType(type) {
      switch(type) {
        case 'absolute': return 'Set to Specific Value';
        case 'increase': return 'Increase Quantity';
        case 'decrease': return 'Decrease Quantity';
        default: return type;
      }
    },
    formatReason() {
      if (this.form.reason === 'other' && this.form.otherReason) {
        return this.form.otherReason;
      }
      
      switch(this.form.reason) {
        case 'inventory_count': return 'Inventory Count';
        case 'damaged': return 'Damaged/Expired Goods';
        case 'lost': return 'Lost/Stolen';
        case 'system_error': return 'System Error';
        case 'returned': return 'Returned Items';
        case 'production': return 'Production Excess/Shortage';
        default: return this.form.reason;
      }
    },
    validateForm() {
      this.quantityError = '';
      
      if (isNaN(this.form.quantity) || this.form.quantity < 0) {
        this.quantityError = 'Quantity must be greater than or equal to zero';
        return false;
      }
      
      if (this.form.adjustmentType === 'decrease' && this.form.quantity > this.currentStock) {
        this.quantityError = `Quantity to decrease exceeds current stock (${this.currentStock})`;
        return false;
      }
      
      if (this.form.reason === 'other' && !this.form.otherReason.trim()) {
        this.errorMessage = 'Please specify a reason for the adjustment';
        this.showErrorModal = true;
        return false;
      }
      
      return true;
    },
    async submitAdjustment() {
      if (!this.validateForm()) return;
      
      this.loading = true;
      
      try {
        // Determine the actual new quantity based on the adjustment type
        let newQuantity = this.form.quantity;
        if (this.form.adjustmentType === 'increase') {
          newQuantity = this.currentStock + parseFloat(this.form.quantity);
        } else if (this.form.adjustmentType === 'decrease') {
          newQuantity = Math.max(0, this.currentStock - parseFloat(this.form.quantity));
        }
        
        // Reason with proper formatting
        const reason = this.form.reason === 'other' ? this.form.otherReason : this.form.reason;
        
        const response = await axios.post('/item-stocks/adjust', {
          item_id: this.form.itemId,
          warehouse_id: this.form.warehouseId,
          new_quantity: newQuantity,
          reason: reason,
          reference_number: this.form.referenceNumber || undefined,
          notes: this.form.notes || undefined
        });
        
        if (response.data && response.data.message) {
          // Show success modal
          this.showSuccessModal = true;
        }
      } catch (err) {
        console.error('Error submitting adjustment:', err);
        this.errorMessage = err.response?.data?.message || 'Failed to submit stock adjustment. Please try again later.';
        this.showErrorModal = true;
      } finally {
        this.loading = false;
      }
    },
    resetForm() {
      this.form = {
        itemId: '',
        warehouseId: '',
        quantity: 0,
        adjustmentType: 'absolute',
        reason: '',
        otherReason: '',
        referenceNumber: '',
        notes: ''
      };
      this.selectedItem = null;
      this.currentStock = 0;
      this.quantityError = '';
    },
    newAdjustment() {
      this.resetForm();
      this.showSuccessModal = false;
    }
  },
  watch: {
    'form.adjustmentType'() {
      // Reset quantity error when adjustment type changes
      this.quantityError = '';
    },
    'form.quantity'() {
      // Validate quantity when it changes
      if (this.form.adjustmentType === 'decrease' && this.form.quantity > this.currentStock) {
        this.quantityError = `Quantity to decrease exceeds current stock (${this.currentStock})`;
      } else {
        this.quantityError = '';
      }
    }
  }
};
</script>

<style scoped>
.stock-adjustment {
  margin-bottom: 2rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
}

.header-left {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--primary-color);
  text-decoration: none;
  font-size: 0.875rem;
}

.back-link:hover {
  text-decoration: underline;
}

.page-header h1 {
  margin: 0;
  font-size: 1.75rem;
}

.content-container {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.5rem;
}

@media (min-width: 1024px) {
  .content-container {
    grid-template-columns: 3fr 2fr;
  }
}

.adjustment-form-card, .adjustment-preview-card {
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
  font-size: 1.25rem;
  color: var(--gray-800);
}

.card-body {
  padding: 1.5rem;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.form-group.span-full {
  grid-column: span 2;
}

.form-group label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--gray-700);
}

.required {
  color: var(--danger-color);
}

.select-wrapper {
  position: relative;
}

.select-wrapper select {
  width: 100%;
  padding: 0.625rem 2rem 0.625rem 0.75rem;
  border: 1px solid var(--gray-200);
  border-radius: 0.375rem;
  font-size: 0.875rem;
  appearance: none;
  background-color: white;
  background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236B7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
  background-position: right 0.5rem center;
  background-repeat: no-repeat;
  background-size: 1.5em 1.5em;
}

.select-wrapper select:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.select-wrapper select:disabled {
  background-color: var(--gray-100);
  cursor: not-allowed;
}

.form-group input[type="number"],
.form-group input[type="text"] {
  padding: 0.625rem 0.75rem;
  border: 1px solid var(--gray-200);
  border-radius: 0.375rem;
  font-size: 0.875rem;
}

.form-group input:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-group input:disabled {
  background-color: var(--gray-100);
  cursor: not-allowed;
}

.form-group textarea {
  padding: 0.625rem 0.75rem;
  border: 1px solid var(--gray-200);
  border-radius: 0.375rem;
  font-size: 0.875rem;
  resize: vertical;
}

.form-group textarea:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-group textarea:disabled {
  background-color: var(--gray-100);
  cursor: not-allowed;
}

.input-hint {
  font-size: 0.75rem;
  color: var(--gray-500);
  margin-top: 0.25rem;
}

.error-message {
  font-size: 0.75rem;
  color: var(--danger-color);
  margin-top: 0.25rem;
}

.stock-info-box {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  background-color: var(--gray-50);
  border: 1px solid var(--gray-200);
  border-radius: 0.375rem;
  padding: 1rem;
}

.info-label {
  font-size: 0.75rem;
  color: var(--gray-500);
  font-weight: 500;
}

.info-value {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--gray-800);
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 1.5rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.625rem 1rem;
  border-radius: 0.375rem;
  font-weight: 500;
  font-size: 0.875rem;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
}

.btn-primary {
  background-color: var(--primary-color);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background-color: var(--primary-dark);
}

.btn-secondary {
  background-color: var(--gray-100);
  color: var(--gray-800);
  border: 1px solid var(--gray-200);
}

.btn-secondary:hover:not(:disabled) {
  background-color: var(--gray-200);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.preview-container {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.stock-box {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  background-color: var(--gray-50);
  border: 1px solid var(--gray-200);
  border-radius: 0.5rem;
  padding: 1.5rem;
}

.warehouse-title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--gray-700);
}

.item-name {
  font-size: 0.875rem;
  color: var(--gray-600);
  margin-bottom: 0.5rem;
}

.stock-visualization {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 1rem 0;
}

.stock-level {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.stock-label {
  font-size: 0.875rem;
  color: var(--gray-600);
}

.stock-value {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--gray-800);
}

.stock-level.result .stock-value {
  font-weight: 600;
  font-size: 1rem;
  color: var(--primary-color);
}

.adjustment-arrow {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-left: auto;
  font-size: 0.875rem;
}

.adjustment-value {
  font-weight: 500;
}

.adjustment-details {
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--gray-200);
}

.detail-item {
  display: flex;
  margin-bottom: 0.75rem;
}

.detail-label {
  width: 5rem;
  font-weight: 500;
  color: var(--gray-700);
  font-size: 0.875rem;
}

.detail-value {
  flex: 1;
  color: var(--gray-800);
  font-size: 0.875rem;
}

.modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
}

.modal-backdrop {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
  position: relative;
  background-color: white;
  border-radius: 0.5rem;
  max-width: 28rem;
  width: 100%;
  z-index: 60;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--gray-200);
}

.modal-header h2 {
  margin: 0;
  font-size: 1.25rem;
  color: var(--gray-800);
}

.close-btn {
  background: none;
  border: none;
  color: var(--gray-500);
  cursor: pointer;
  padding: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.close-btn:hover {
  color: var(--gray-800);
}

.modal-body {
  padding: 1.5rem;
}

.success-message, .modal-body .error-message {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  margin-bottom: 1.5rem;
}

.success-message i, .modal-body .error-message i {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.success-message i {
  color: var(--success-color);
}

.modal-body .error-message i {
  color: var(--danger-color);
}

.success-message p, .modal-body .error-message p {
  font-size: 1rem;
  color: var(--gray-800);
  margin: 0;
}

.adjustment-details {
  margin-top: 1.5rem;
  border-top: 1px solid var(--gray-200);
  padding-top: 1.5rem;
}

.adjustment-detail {
  display: flex;
  margin-bottom: 0.5rem;
}

.adjustment-detail .detail-label {
  width: 7rem;
  font-weight: 500;
  color: var(--gray-700);
}

.adjustment-detail .detail-value {
  flex: 1;
  color: var(--gray-800);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--gray-200);
}

@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .form-group.span-full {
    grid-column: span 1;
  }
}
</style>