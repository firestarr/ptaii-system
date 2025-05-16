<!-- src/views/inventory/StockTransfer.vue -->
<template>
    <div class="stock-transfer">
      <div class="page-header">
        <div class="header-left">
          <router-link to="/item-stocks" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Stock List
          </router-link>
          <h1>Stock Transfer</h1>
        </div>
      </div>
  
      <div class="content-container">
        <div class="transfer-form-card">
          <div class="card-header">
            <h2>Transfer Stock Between Warehouses</h2>
          </div>
          <div class="card-body">
            <form @submit.prevent="submitTransfer">
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
                  <label for="fromWarehouseSelect">From Warehouse <span class="required">*</span></label>
                  <div class="select-wrapper">
                    <select 
                      id="fromWarehouseSelect" 
                      v-model="form.fromWarehouseId" 
                      @change="onFromWarehouseChange" 
                      :disabled="loading || !form.itemId"
                      required
                    >
                      <option value="">Select source warehouse</option>
                      <option v-for="warehouse in sourceWarehouses" :key="warehouse.warehouse_id" :value="warehouse.warehouse_id">
                        {{ warehouse.name }}
                      </option>
                    </select>
                  </div>
                </div>
  
                <div class="form-group">
                  <label for="toWarehouseSelect">To Warehouse <span class="required">*</span></label>
                  <div class="select-wrapper">
                    <select 
                      id="toWarehouseSelect" 
                      v-model="form.toWarehouseId" 
                      :disabled="loading || !form.fromWarehouseId"
                      required
                    >
                      <option value="">Select destination warehouse</option>
                      <option 
                        v-for="warehouse in destinationWarehouses" 
                        :key="warehouse.warehouse_id" 
                        :value="warehouse.warehouse_id"
                        :disabled="warehouse.warehouse_id === form.fromWarehouseId"
                      >
                        {{ warehouse.name }}
                      </option>
                    </select>
                  </div>
                </div>
  
                <div class="form-group span-full" v-if="form.fromWarehouseId && selectedItem">
                  <div class="stock-info-box">
                    <div class="stock-info">
                      <div class="info-label">Current Stock in Source Warehouse:</div>
                      <div class="info-value">{{ currentStock }} {{ selectedItem.uom?.symbol || '' }}</div>
                    </div>
                    <div class="stock-info">
                      <div class="info-label">Available Stock:</div>
                      <div class="info-value" :class="{'info-warning': availableStock < form.quantity}">
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
                    :disabled="loading || !form.fromWarehouseId || !form.toWarehouseId"
                    required
                    :max="availableStock"
                  />
                  <div class="input-hint" v-if="selectedItem">
                    {{ selectedItem.uom?.symbol || 'units' }}
                  </div>
                  <div class="error-message" v-if="quantityError">
                    {{ quantityError }}
                  </div>
                </div>
  
                <div class="form-group">
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
                    placeholder="Additional notes for this transfer" 
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
                  <span v-else>Transfer Stock</span>
                </button>
              </div>
            </form>
          </div>
        </div>
  
        <div class="transfer-preview-card" v-if="form.itemId && form.fromWarehouseId && form.toWarehouseId && form.quantity > 0">
          <div class="card-header">
            <h2>Transfer Preview</h2>
          </div>
          <div class="card-body">
            <div class="preview-container">
              <div class="warehouse-box source">
                <div class="warehouse-title">Source</div>
                <div class="warehouse-name">{{ fromWarehouseName }}</div>
                <div class="warehouse-stock">
                  <div class="stock-before">{{ currentStock }} {{ selectedItem?.uom?.symbol || '' }}</div>
                  <div class="stock-arrow negative">
                    <i class="fas fa-arrow-down"></i> {{ form.quantity }} {{ selectedItem?.uom?.symbol || '' }}
                  </div>
                  <div class="stock-after">{{ calculateSourceAfter() }} {{ selectedItem?.uom?.symbol || '' }}</div>
                </div>
              </div>
  
              <div class="transfer-arrow">
                <i class="fas fa-arrow-right"></i>
              </div>
  
              <div class="warehouse-box destination">
                <div class="warehouse-title">Destination</div>
                <div class="warehouse-name">{{ toWarehouseName }}</div>
                <div class="warehouse-stock">
                  <div class="stock-before">{{ destinationStock }} {{ selectedItem?.uom?.symbol || '' }}</div>
                  <div class="stock-arrow positive">
                    <i class="fas fa-arrow-up"></i> {{ form.quantity }} {{ selectedItem?.uom?.symbol || '' }}
                  </div>
                  <div class="stock-after">{{ calculateDestinationAfter() }} {{ selectedItem?.uom?.symbol || '' }}</div>
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
            <h2>Transfer Successful</h2>
            <button class="close-btn" @click="showSuccessModal = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <div class="success-message">
              <i class="fas fa-check-circle"></i>
              <p>Stock transfer completed successfully!</p>
            </div>
            <div class="transfer-details">
              <div class="transfer-detail">
                <div class="detail-label">Item:</div>
                <div class="detail-value">{{ selectedItem?.item_code }} - {{ selectedItem?.name }}</div>
              </div>
              <div class="transfer-detail">
                <div class="detail-label">Quantity:</div>
                <div class="detail-value">{{ form.quantity }} {{ selectedItem?.uom?.symbol || '' }}</div>
              </div>
              <div class="transfer-detail">
                <div class="detail-label">From Warehouse:</div>
                <div class="detail-value">{{ fromWarehouseName }}</div>
              </div>
              <div class="transfer-detail">
                <div class="detail-label">To Warehouse:</div>
                <div class="detail-value">{{ toWarehouseName }}</div>
              </div>
              <div class="transfer-detail" v-if="form.referenceNumber">
                <div class="detail-label">Reference:</div>
                <div class="detail-value">{{ form.referenceNumber }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="showSuccessModal = false">Close</button>
            <button class="btn btn-primary" @click="newTransfer">New Transfer</button>
          </div>
        </div>
      </div>
  
      <!-- Error Modal -->
      <div class="modal" v-if="showErrorModal">
        <div class="modal-backdrop" @click="showErrorModal = false"></div>
        <div class="modal-content">
          <div class="modal-header">
            <h2>Transfer Failed</h2>
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
    name: 'StockTransfer',
    data() {
      return {
        form: {
          itemId: '',
          fromWarehouseId: '',
          toWarehouseId: '',
          quantity: 0,
          referenceNumber: '',
          notes: ''
        },
        items: [],
        warehouses: [],
        selectedItem: null,
        currentStock: 0,
        availableStock: 0,
        destinationStock: 0,
        sourceWarehouses: [],
        destinationWarehouses: [],
        loading: false,
        quantityError: '',
        showSuccessModal: false,
        showErrorModal: false,
        errorMessage: ''
      };
    },
    computed: {
      isFormValid() {
        return (
          this.form.itemId && 
          this.form.fromWarehouseId && 
          this.form.toWarehouseId && 
          this.form.quantity > 0 && 
          this.form.quantity <= this.availableStock &&
          this.form.fromWarehouseId !== this.form.toWarehouseId
        );
      },
      fromWarehouseName() {
        const warehouse = this.warehouses.find(w => w.warehouse_id == this.form.fromWarehouseId);
        return warehouse ? warehouse.name : '';
      },
      toWarehouseName() {
        const warehouse = this.warehouses.find(w => w.warehouse_id == this.form.toWarehouseId);
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
            this.destinationWarehouses = [...this.warehouses];
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
          this.form.fromWarehouseId = parseInt(warehouse, 10) || '';
          this.$nextTick(() => {
            this.onFromWarehouseChange();
          });
        }
      },
      async onItemChange() {
        if (!this.form.itemId) {
          this.selectedItem = null;
          this.sourceWarehouses = [];
          this.form.fromWarehouseId = '';
          this.form.toWarehouseId = '';
          this.currentStock = 0;
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
          
          // Get the item's stock in all warehouses
          const stockResponse = await axios.get(`/item-stocks/item/${this.form.itemId}`);
          if (stockResponse.data && stockResponse.data.data) {
            const itemStock = stockResponse.data.data;
            
            // Filter warehouses that have stock of this item
            this.sourceWarehouses = this.warehouses.filter(warehouse => {
              const stockInWarehouse = itemStock.warehouse_stocks.find(
                stock => stock.warehouse_id == warehouse.warehouse_id && stock.quantity > 0
              );
              return stockInWarehouse !== undefined;
            });
            
            // If a warehouse was pre-selected from query params, check its stock
            if (this.form.fromWarehouseId) {
              this.onFromWarehouseChange();
            }
          }
        } catch (err) {
          console.error('Error fetching item data:', err);
          this.errorMessage = 'Failed to load item data. Please try again later.';
          this.showErrorModal = true;
        } finally {
          this.loading = false;
        }
      },
      async onFromWarehouseChange() {
        if (!this.form.itemId || !this.form.fromWarehouseId) {
          this.currentStock = 0;
          this.availableStock = 0;
          return;
        }
        
        this.loading = true;
        
        try {
          // Get the item's stock in the selected warehouse
          const stockResponse = await axios.get(`/item-stocks/item/${this.form.itemId}`);
          if (stockResponse.data && stockResponse.data.data) {
            const itemStock = stockResponse.data.data;
            
            // Find stock in source warehouse
            const sourceStock = itemStock.warehouse_stocks.find(
              stock => stock.warehouse_id == this.form.fromWarehouseId
            );
            
            if (sourceStock) {
              this.currentStock = sourceStock.quantity || 0;
              this.availableStock = sourceStock.available_quantity || this.currentStock;
            } else {
              this.currentStock = 0;
              this.availableStock = 0;
            }
            
            // Find stock in destination warehouse (if already selected)
            if (this.form.toWarehouseId) {
              const destStock = itemStock.warehouse_stocks.find(
                stock => stock.warehouse_id == this.form.toWarehouseId
              );
              
              this.destinationStock = destStock ? destStock.quantity : 0;
            }
            
            // Filter out source warehouse from destination options
            this.destinationWarehouses = this.warehouses.filter(
              warehouse => warehouse.warehouse_id != this.form.fromWarehouseId
            );
          }
        } catch (err) {
          console.error('Error fetching warehouse stock:', err);
          this.errorMessage = 'Failed to load warehouse stock data. Please try again later.';
          this.showErrorModal = true;
        } finally {
          this.loading = false;
        }
      },
      async onToWarehouseChange() {
        if (!this.form.itemId || !this.form.toWarehouseId) {
          this.destinationStock = 0;
          return;
        }
        
        this.loading = true;
        
        try {
          // Get the item's stock in the destination warehouse
          const stockResponse = await axios.get(`/item-stocks/item/${this.form.itemId}`);
          if (stockResponse.data && stockResponse.data.data) {
            const itemStock = stockResponse.data.data;
            
            // Find stock in destination warehouse
            const destStock = itemStock.warehouse_stocks.find(
              stock => stock.warehouse_id == this.form.toWarehouseId
            );
            
            this.destinationStock = destStock ? destStock.quantity : 0;
          }
        } catch (err) {
          console.error('Error fetching destination stock:', err);
        } finally {
          this.loading = false;
        }
      },
      calculateSourceAfter() {
        return Math.max(0, this.currentStock - this.form.quantity).toFixed(2);
      },
      calculateDestinationAfter() {
        return (this.destinationStock + this.form.quantity).toFixed(2);
      },
      validateForm() {
        this.quantityError = '';
        
        if (!this.form.quantity || this.form.quantity <= 0) {
          this.quantityError = 'Quantity must be greater than zero';
          return false;
        }
        
        if (this.form.quantity > this.availableStock) {
          this.quantityError = `Quantity exceeds available stock (${this.availableStock})`;
          return false;
        }
        
        if (this.form.fromWarehouseId === this.form.toWarehouseId) {
          this.errorMessage = 'Source and destination warehouses must be different';
          this.showErrorModal = true;
          return false;
        }
        
        return true;
      },
      async submitTransfer() {
        if (!this.validateForm()) return;
        
        this.loading = true;
        
        try {
          const response = await axios.post('/item-stocks/transfer', {
            item_id: this.form.itemId,
            from_warehouse_id: this.form.fromWarehouseId,
            to_warehouse_id: this.form.toWarehouseId,
            quantity: this.form.quantity,
            reference_number: this.form.referenceNumber || undefined,
            notes: this.form.notes || undefined
          });
          
          if (response.data && response.data.message) {
            // Show success modal
            this.showSuccessModal = true;
          }
        } catch (err) {
          console.error('Error submitting transfer:', err);
          this.errorMessage = err.response?.data?.message || 'Failed to submit stock transfer. Please try again later.';
          this.showErrorModal = true;
        } finally {
          this.loading = false;
        }
      },
      resetForm() {
        this.form = {
          itemId: '',
          fromWarehouseId: '',
          toWarehouseId: '',
          quantity: 0,
          referenceNumber: '',
          notes: ''
        };
        this.selectedItem = null;
        this.currentStock = 0;
        this.availableStock = 0;
        this.destinationStock = 0;
        this.quantityError = '';
      },
      newTransfer() {
        this.resetForm();
        this.showSuccessModal = false;
      }
    },
    watch: {
      'form.toWarehouseId'() {
        this.onToWarehouseChange();
      },
      'form.quantity'() {
        // Validate quantity when it changes
        if (this.form.quantity > this.availableStock) {
          this.quantityError = `Quantity exceeds available stock (${this.availableStock})`;
        } else {
          this.quantityError = '';
        }
      }
    }
  };
  </script>
  
  <style scoped>
  .stock-transfer {
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
  
  .transfer-form-card, .transfer-preview-card {
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
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
  }
  
  .warehouse-box {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    background-color: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: 0.5rem;
    padding: 1rem;
    min-width: 0;
  }
  
  .warehouse-title {
    font-size: 0.75rem;
    color: var(--gray-500);
    font-weight: 500;
    text-transform: uppercase;
  }
  
  .warehouse-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--gray-800);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  
  .warehouse-stock {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-top: 0.5rem;
  }
  
  .stock-before {
    font-size: 0.875rem;
    color: var(--gray-600);
  }
  
  .stock-arrow {
    font-size: 0.875rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.25rem;
  }
  
  .stock-arrow.positive {
    color: var(--success-color);
  }
  
  .stock-arrow.negative {
    color: var(--danger-color);
  }
  
  .stock-after {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--gray-800);
  }
  
  .transfer-arrow {
    font-size: 1.5rem;
    color: var(--gray-400);
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
  
  .success-message, .error-message {
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
  
  .transfer-details {
    margin-top: 1.5rem;
    border-top: 1px solid var(--gray-200);
    padding-top: 1.5rem;
  }
  
  .transfer-detail {
    display: flex;
    margin-bottom: 0.5rem;
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
    
    .preview-container {
      flex-direction: column;
    }
    
    .transfer-arrow {
      transform: rotate(90deg);
      margin: 1rem 0;
    }
  }
  </style>