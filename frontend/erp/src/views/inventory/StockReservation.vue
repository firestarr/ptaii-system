<!-- src/views/inventory/StockReservation.vue -->
<template>
    <div class="stock-reservation">
      <div class="page-header">
        <div class="header-left">
          <router-link to="/item-stocks" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Stock List
          </router-link>
          <h1>Stock Reservation</h1>
        </div>
      </div>
  
      <div class="content-container">
        <div class="reservation-form-card">
          <div class="card-header">
            <h2>Reserve Stock for Future Use</h2>
          </div>
          <div class="card-body">
            <form @submit.prevent="submitForm">
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
  
                <div class="form-group">
                  <label for="actionSelect">Action <span class="required">*</span></label>
                  <div class="select-wrapper">
                    <select 
                      id="actionSelect" 
                      v-model="form.action" 
                      :disabled="loading"
                      required
                    >
                      <option value="reserve">Reserve Stock</option>
                      <option value="release">Release Reserved Stock</option>
                    </select>
                  </div>
                </div>
  
                <div class="form-group span-full" v-if="form.warehouseId && selectedItem">
                  <div class="stock-info-box">
                    <div class="stock-info">
                      <div class="info-label">Current Stock:</div>
                      <div class="info-value">{{ currentStock }} {{ selectedItem.uom?.symbol || '' }}</div>
                    </div>
                    <div class="stock-info">
                      <div class="info-label">Reserved Stock:</div>
                      <div class="info-value">{{ reservedStock }} {{ selectedItem.uom?.symbol || '' }}</div>
                    </div>
                    <div class="stock-info">
                      <div class="info-label">Available Stock:</div>
                      <div class="info-value" :class="{'info-warning': availableStock < form.quantity && form.action === 'reserve'}">
                        {{ availableStock }} {{ selectedItem.uom?.symbol || '' }}
                      </div>
                    </div>
                  </div>
                </div>
  
                <div class="form-group">
                  <label for="quantityInput">Quantity <span class="required">*</span></label>
                  <input 
                    type="number" 
                    id="quantityInput" 
                    v-model.number="form.quantity" 
                    min="0.01" 
                    step="0.01" 
                    :disabled="loading || !form.warehouseId"
                    required
                    :max="form.action === 'reserve' ? availableStock : reservedStock"
                  />
                  <div class="input-hint" v-if="selectedItem">
                    {{ selectedItem.uom?.symbol || 'units' }}
                  </div>
                  <div class="error-message" v-if="quantityError">
                    {{ quantityError }}
                  </div>
                </div>
  
                <div class="form-group">
                  <label for="referenceTypeSelect">Reference Type <span class="required">*</span></label>
                  <div class="select-wrapper">
                    <select 
                      id="referenceTypeSelect" 
                      v-model="form.referenceType" 
                      :disabled="loading"
                      required
                    >
                      <option value="">Select reference type</option>
                      <option value="sales_order">Sales Order</option>
                      <option value="production_order">Production Order</option>
                      <option value="quality_control">Quality Control</option>
                      <option value="customer_demo">Customer Demo</option>
                      <option value="maintenance">Maintenance</option>
                      <option value="other">Other</option>
                    </select>
                  </div>
                </div>
  
                <div class="form-group">
                  <label for="referenceIdInput">Reference ID <span class="required">*</span></label>
                  <input 
                    type="text" 
                    id="referenceIdInput" 
                    v-model="form.referenceId" 
                    placeholder="Enter reference ID" 
                    :disabled="loading"
                    required
                  />
                  <div class="input-hint">
                    Order number, request ID, etc.
                  </div>
                </div>
  
                <div class="form-group span-full">
                  <label for="notesInput">Notes</label>
                  <textarea 
                    id="notesInput" 
                    v-model="form.notes" 
                    rows="3" 
                    placeholder="Additional notes for this reservation" 
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
                  <span v-else>{{ form.action === 'reserve' ? 'Reserve Stock' : 'Release Reserved Stock' }}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
  
        <div class="reservation-preview-card" v-if="form.itemId && form.warehouseId && form.quantity > 0">
          <div class="card-header">
            <h2>{{ form.action === 'reserve' ? 'Reservation' : 'Release' }} Preview</h2>
          </div>
          <div class="card-body">
            <div class="preview-container">
              <div class="item-summary">
                <div class="item-image">
                  <i class="fas fa-box"></i>
                </div>
                <div class="item-details">
                  <div class="item-name">{{ selectedItem?.item_code }} - {{ selectedItem?.name }}</div>
                  <div class="warehouse-name">{{ warehouseName }}</div>
                </div>
              </div>
  
              <div class="reservation-calculation">
                <div class="calculation-row">
                  <div class="calculation-label">Current Stock:</div>
                  <div class="calculation-value">{{ currentStock }} {{ selectedItem?.uom?.symbol || '' }}</div>
                </div>
                <div class="calculation-row">
                  <div class="calculation-label">Currently Reserved:</div>
                  <div class="calculation-value">{{ reservedStock }} {{ selectedItem?.uom?.symbol || '' }}</div>
                </div>
                <div class="calculation-row">
                  <div class="calculation-label">Available Stock:</div>
                  <div class="calculation-value">{{ availableStock }} {{ selectedItem?.uom?.symbol || '' }}</div>
                </div>
                <div class="calculation-divider"></div>
                <div class="calculation-row highlight">
                  <div class="calculation-label">{{ form.action === 'reserve' ? 'Quantity to Reserve:' : 'Quantity to Release:' }}</div>
                  <div 
                    class="calculation-value"
                    :class="{'value-positive': form.action === 'release', 'value-negative': form.action === 'reserve'}"
                  >
                    {{ form.action === 'reserve' ? '-' : '+' }}{{ form.quantity }} {{ selectedItem?.uom?.symbol || '' }}
                  </div>
                </div>
                <div class="calculation-divider"></div>
                <div class="calculation-row result">
                  <div class="calculation-label">{{ form.action === 'reserve' ? 'New Available Stock:' : 'New Available Stock:' }}</div>
                  <div class="calculation-value">{{ calculateNewAvailable() }} {{ selectedItem?.uom?.symbol || '' }}</div>
                </div>
                <div class="calculation-row result">
                  <div class="calculation-label">{{ form.action === 'reserve' ? 'New Reserved Stock:' : 'New Reserved Stock:' }}</div>
                  <div class="calculation-value">{{ calculateNewReserved() }} {{ selectedItem?.uom?.symbol || '' }}</div>
                </div>
              </div>
  
              <div class="reference-details">
                <div class="detail-row">
                  <div class="detail-label">Reference Type:</div>
                  <div class="detail-value">{{ formatReferenceType(form.referenceType) }}</div>
                </div>
                <div class="detail-row">
                  <div class="detail-label">Reference ID:</div>
                  <div class="detail-value">{{ form.referenceId }}</div>
                </div>
                <div class="detail-row" v-if="form.notes">
                  <div class="detail-label">Notes:</div>
                  <div class="detail-value">{{ form.notes }}</div>
                </div>
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
            <h2>{{ form.action === 'reserve' ? 'Reservation' : 'Release' }} Successful</h2>
            <button class="close-btn" @click="showSuccessModal = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <div class="success-message">
              <i class="fas fa-check-circle"></i>
              <p>Stock {{ form.action === 'reserve' ? 'reservation' : 'release' }} completed successfully!</p>
            </div>
            <div class="reservation-details">
              <div class="reservation-detail">
                <div class="detail-label">Item:</div>
                <div class="detail-value">{{ selectedItem?.item_code }} - {{ selectedItem?.name }}</div>
              </div>
              <div class="reservation-detail">
                <div class="detail-label">Warehouse:</div>
                <div class="detail-value">{{ warehouseName }}</div>
              </div>
              <div class="reservation-detail">
                <div class="detail-label">Quantity:</div>
                <div class="detail-value">{{ form.quantity }} {{ selectedItem?.uom?.symbol || '' }}</div>
              </div>
              <div class="reservation-detail">
                <div class="detail-label">Action:</div>
                <div class="detail-value">{{ form.action === 'reserve' ? 'Reserved' : 'Released' }}</div>
              </div>
              <div class="reservation-detail">
                <div class="detail-label">Reference:</div>
                <div class="detail-value">{{ formatReferenceType(form.referenceType) }} #{{ form.referenceId }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="showSuccessModal = false">Close</button>
            <button class="btn btn-primary" @click="newReservation">New {{ form.action === 'reserve' ? 'Reservation' : 'Release' }}</button>
          </div>
        </div>
      </div>
  
      <!-- Error Modal -->
      <div class="modal" v-if="showErrorModal">
        <div class="modal-backdrop" @click="showErrorModal = false"></div>
        <div class="modal-content">
          <div class="modal-header">
            <h2>{{ form.action === 'reserve' ? 'Reservation' : 'Release' }} Failed</h2>
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
    name: 'StockReservation',
    data() {
      return {
        form: {
          itemId: '',
          warehouseId: '',
          quantity: 0,
          action: 'reserve',
          referenceType: '',
          referenceId: '',
          notes: ''
        },
        items: [],
        warehouses: [],
        selectedItem: null,
        currentStock: 0,
        reservedStock: 0,
        availableStock: 0,
        loading: false,
        quantityError: '',
        showSuccessModal: false,
        showErrorModal: false,
        errorMessage: ''
      };
    },
    computed: {
      isFormValid() {
        if (!this.form.itemId || !this.form.warehouseId || this.form.quantity <= 0 || 
            !this.form.referenceType || !this.form.referenceId) {
          return false;
        }
        
        if (this.form.action === 'reserve' && this.form.quantity > this.availableStock) {
          return false;
        }
        
        if (this.form.action === 'release' && this.form.quantity > this.reservedStock) {
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
          this.reservedStock = 0;
          this.availableStock = 0;
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
          this.reservedStock = 0;
          this.availableStock = 0;
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
            
            if (warehouseStock) {
              this.currentStock = warehouseStock.quantity || 0;
              this.reservedStock = warehouseStock.reserved_quantity || 0;
              this.availableStock = warehouseStock.available_quantity || 0;
            } else {
              this.currentStock = 0;
              this.reservedStock = 0;
              this.availableStock = 0;
            }
          }
        } catch (err) {
          console.error('Error fetching warehouse stock:', err);
          this.errorMessage = 'Failed to load warehouse stock data. Please try again later.';
          this.showErrorModal = true;
        } finally {
          this.loading = false;
        }
      },
      calculateNewAvailable() {
        if (this.form.action === 'reserve') {
          return Math.max(0, this.availableStock - this.form.quantity).toFixed(2);
        } else {
          return (this.availableStock + this.form.quantity).toFixed(2);
        }
      },
      calculateNewReserved() {
        if (this.form.action === 'reserve') {
          return (this.reservedStock + this.form.quantity).toFixed(2);
        } else {
          return Math.max(0, this.reservedStock - this.form.quantity).toFixed(2);
        }
      },
      formatReferenceType(type) {
        switch(type) {
          case 'sales_order': return 'Sales Order';
          case 'production_order': return 'Production Order';
          case 'quality_control': return 'Quality Control';
          case 'customer_demo': return 'Customer Demo';
          case 'maintenance': return 'Maintenance';
          case 'other': return 'Other';
          default: return type;
        }
      },
      validateForm() {
        this.quantityError = '';
        
        if (!this.form.quantity || this.form.quantity <= 0) {
          this.quantityError = 'Quantity must be greater than zero';
          return false;
        }
        
        if (this.form.action === 'reserve' && this.form.quantity > this.availableStock) {
          this.quantityError = `Quantity exceeds available stock (${this.availableStock})`;
          return false;
        }
        
        if (this.form.action === 'release' && this.form.quantity > this.reservedStock) {
          this.quantityError = `Quantity exceeds reserved stock (${this.reservedStock})`;
          return false;
        }
        
        if (!this.form.referenceType) {
          this.errorMessage = 'Please select a reference type';
          this.showErrorModal = true;
          return false;
        }
        
        if (!this.form.referenceId.trim()) {
          this.errorMessage = 'Please enter a reference ID';
          this.showErrorModal = true;
          return false;
        }
        
        return true;
      },
      async submitForm() {
        if (!this.validateForm()) return;
        
        this.loading = true;
        
        try {
          let endpoint = '/item-stocks/reserve';
          if (this.form.action === 'release') {
            endpoint = '/item-stocks/release-reservation';
          }
          
          const response = await axios.post(endpoint, {
            item_id: this.form.itemId,
            warehouse_id: this.form.warehouseId,
            quantity: this.form.quantity,
            reference_type: this.form.referenceType,
            reference_id: this.form.referenceId,
            notes: this.form.notes || undefined
          });
          
          if (response.data && response.data.message) {
            // Show success modal
            this.showSuccessModal = true;
            
            // Update the stock information
            this.onWarehouseChange();
          }
        } catch (err) {
          console.error('Error submitting form:', err);
          this.errorMessage = err.response?.data?.message || `Failed to ${this.form.action} stock. Please try again later.`;
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
          action: 'reserve',
          referenceType: '',
          referenceId: '',
          notes: ''
        };
        this.selectedItem = null;
        this.currentStock = 0;
        this.reservedStock = 0;
        this.availableStock = 0;
        this.quantityError = '';
      },
      newReservation() {
        this.resetForm();
        this.showSuccessModal = false;
      }
    },
    watch: {
      'form.action'() {
        // Reset quantity error when action changes
        this.quantityError = '';
      },
      'form.quantity'() {
        // Validate quantity when it changes
        if (this.form.action === 'reserve' && this.form.quantity > this.availableStock) {
          this.quantityError = `Quantity exceeds available stock (${this.availableStock})`;
        } else if (this.form.action === 'release' && this.form.quantity > this.reservedStock) {
          this.quantityError = `Quantity exceeds reserved stock (${this.reservedStock})`;
        } else {
          this.quantityError = '';
        }
      }
    }
  };
  </script>
  
  <style scoped>
  .stock-reservation {
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
  
  .reservation-form-card, .reservation-preview-card {
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
    justify-content: space-between;
    background-color: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: 0.375rem;
    padding: 1rem;
    margin-top: 0.5rem;
  }
  
  .stock-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
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
  
  .info-warning {
    color: var(--warning-color);
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
  
  .item-summary {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--gray-200);
  }
  
  .item-image {
    width: 3rem;
    height: 3rem;
    background-color: var(--gray-100);
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-500);
    font-size: 1.5rem;
  }
  
  .item-details {
    flex: 1;
    min-width: 0;
  }
  
  .item-name {
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  
  .warehouse-name {
    font-size: 0.875rem;
    color: var(--gray-600);
  }
  
  .reservation-calculation {
    background-color: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: 0.5rem;
    padding: 1rem;
  }
  
  .calculation-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    font-size: 0.875rem;
  }
  
  .calculation-row.highlight {
    color: var(--primary-color);
    font-weight: 500;
  }
  
  .calculation-row.result {
    color: var(--gray-800);
    font-weight: 600;
  }
  
  .calculation-label {
    color: inherit;
  }
  
  .calculation-value {
    color: inherit;
  }
  
  .calculation-value.value-positive {
    color: var(--success-color);
  }
  
  .calculation-value.value-negative {
    color: var(--danger-color);
  }
  
  .calculation-divider {
    height: 1px;
    background-color: var(--gray-200);
    margin: 0.5rem 0;
  }
  
  .reference-details {
    padding-top: 1rem;
    border-top: 1px solid var(--gray-200);
  }
  
  .detail-row {
    display: flex;
    margin-bottom: 0.75rem;
    font-size: 0.875rem;
  }
  
  .detail-label {
    width: 7rem;
    font-weight: 500;
    color: var(--gray-700);
  }
  
  .detail-value {
    flex: 1;
    color: var(--gray-800);
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
  
  .reservation-details {
    margin-top: 1.5rem;
    border-top: 1px solid var(--gray-200);
    padding-top: 1.5rem;
  }
  
  .reservation-detail {
    display: flex;
    margin-bottom: 0.5rem;
  }
  
  .reservation-detail .detail-label {
    width: 7rem;
    font-weight: 500;
    color: var(--gray-700);
  }
  
  .reservation-detail .detail-value {
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
    
    .stock-info-box {
      flex-direction: column;
      gap: 1rem;
    }
  }
  </style>