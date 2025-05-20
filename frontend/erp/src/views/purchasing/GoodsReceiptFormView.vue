<!-- src/views/purchasing/GoodsReceiptFormView.vue -->
<template>
    <div class="goods-receipt-form">
      <div class="card">
        <div class="card-header">
          <h2>{{ isEdit ? 'Edit Goods Receipt' : 'Create Goods Receipt' }}</h2>
          <div class="actions">
            <router-link to="/purchasing/goods-receipts" class="btn btn-secondary">
              <i class="fas fa-arrow-left"></i> Back to List
            </router-link>
          </div>
        </div>
  
        <div class="card-body">
          <!-- Loading indicator -->
          <div v-if="loading" class="loading-container">
            <i class="fas fa-spinner fa-spin"></i> Loading...
          </div>
  
          <div v-else>
            <form @submit.prevent="saveReceipt" class="receipt-form">
              <!-- Receipt Information -->
              <div class="form-section">
                <h3>Receipt Information</h3>
                
                <div class="form-grid">
                  <div class="form-group">
                    <label for="receipt_date">Receipt Date <span class="required">*</span></label>
                    <input
                      type="date"
                      id="receipt_date"
                      v-model="receipt.receipt_date"
                      required
                      :max="maxDate"
                    />
                    <div v-if="validationErrors.receipt_date" class="error-message">
                      {{ validationErrors.receipt_date[0] }}
                    </div>
                  </div>
                  
                  <div class="form-group">
                    <label for="vendor_id">Vendor <span class="required">*</span></label>
                    <select
                      id="vendor_id"
                      v-model="receipt.vendor_id"
                      required
                      :disabled="itemsLoaded || isEdit"
                      @change="vendorSelected"
                    >
                      <option value="">Select Vendor</option>
                    <option v-for="vendor in vendors.filter(v => v)" :key="vendor.vendor_id" :value="vendor.vendor_id">
                      {{ vendor.name }}
                    </option>
                    </select>
                    <div v-if="validationErrors.vendor_id" class="error-message">
                      {{ validationErrors.vendor_id[0] }}
                    </div>
                  </div>
                </div>
              </div>
  
              <!-- PO Selection -->
              <div v-if="receipt.vendor_id && !itemsLoaded" class="form-section">
                <h3>Select Purchase Orders</h3>
                <p class="info-text">Select purchase orders to receive items from:</p>
                
                <div v-if="loadingPOs" class="loading-container">
                  <i class="fas fa-spinner fa-spin"></i> Loading purchase orders...
                </div>
                
                <div v-else-if="purchaseOrders.length === 0" class="empty-state">
                  <i class="fas fa-file-invoice"></i>
                  <p>No outstanding purchase orders found for this vendor.</p>
                </div>
                
                <div v-else class="po-selection">
                  <div v-for="po in purchaseOrders.filter(po => po)" :key="po.po_id" class="po-card">
                    <div class="checkbox-wrapper">
                      <input type="checkbox" :id="'po-' + po.po_id" v-model="selectedPOs" :value="po.po_id">
                      <label :for="'po-' + po.po_id">
                        <div class="po-header">
                          <h4>{{ po.po_number }}</h4>
                          <span class="status-badge" :class="po.status">{{ po.status }}</span>
                        </div>
                        <div class="po-details">
                          <div><strong>Date:</strong> {{ formatDate(po.po_date) }}</div>
                          <div><strong>Total Items:</strong> {{ po.lines ? po.lines.length : 0 }}</div>
                          <div><strong>Expected Delivery:</strong> {{ formatDate(po.expected_delivery) || 'Not specified' }}</div>
                        </div>
                      </label>
                    </div>
                  </div>
                  
                  <div class="actions-wrapper">
                    <button type="button" class="btn btn-primary" @click="loadItemsFromPOs" :disabled="selectedPOs.length === 0">
                      <i class="fas fa-check"></i> Select Items from POs
                    </button>
                  </div>
                </div>
              </div>
  
              <!-- Item Selection -->
              <div v-if="itemsLoaded" class="form-section">
                <h3>Receipt Items</h3>
                
                <div v-if="availableItems.length === 0" class="empty-state">
                  <i class="fas fa-boxes"></i>
                  <p>No outstanding items to receive.</p>
                </div>
                
                <div v-else>
                  <div class="table-responsive">
                    <table class="items-table">
                      <thead>
                        <tr>
                          <th>PO Number</th>
                          <th>Item Code</th>
                          <th>Item Name</th>
                          <th>Ordered</th>
                          <th>Previously Received</th>
                          <th>Outstanding</th>
                          <th>Quantity to Receive <span class="required">*</span></th>
                          <th>Warehouse <span class="required">*</span></th>
                          <th>Batch Number</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(line, index) in (receipt && receipt.lines) || []" :key="index">
                          <td>{{ line.po_number }}</td>
                          <td>{{ line.item_code }}</td>
                          <td>{{ line.item_name }}</td>
                          <td>{{ line.ordered_quantity }}</td>
                          <td>{{ line.received_quantity || 0 }}</td>
                          <td>{{ line.outstanding_quantity }}</td>
                          <td>
                            <input
                              type="number"
                              v-model.number="line.received_quantity"
                              :min="0.01"
                              :max="line.outstanding_quantity"
                              step="0.01"
                              required
                            />
                            <div v-if="validationErrors[`lines.${index}.received_quantity`]" class="error-message">
                              {{ validationErrors[`lines.${index}.received_quantity`][0] }}
                            </div>
                          </td>
                          <td>
                            <select v-model="line.warehouse_id" required>
                              <option value="">Select Warehouse</option>
                              <option v-for="warehouse in warehouses" :key="warehouse.warehouse_id" :value="warehouse.warehouse_id">
                                {{ warehouse.name }}
                              </option>
                            </select>
                            <div v-if="validationErrors[`lines.${index}.warehouse_id`]" class="error-message">
                              {{ validationErrors[`lines.${index}.warehouse_id`][0] }}
                            </div>
                          </td>
                          <td>
                            <input
                              type="text"
                              v-model="line.batch_number"
                              placeholder="Optional"
                            />
                          </td>
                          <td>
                            <button type="button" class="btn-icon delete" @click="removeLine(index)" title="Remove">
                              <i class="fas fa-trash"></i>
                            </button>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  
                  <div v-if="hasMoreItems" class="add-items-row">
                    <button type="button" class="btn btn-secondary" @click="showAvailableItemsModal = true">
                      <i class="fas fa-plus"></i> Add More Items
                    </button>
                  </div>
                </div>
              </div>
  
              <!-- Submit Buttons -->
              <div v-if="itemsLoaded" class="form-actions">
                <button type="button" class="btn btn-secondary" @click="cancel">
                  Cancel
                </button>
                <button type="submit" class="btn btn-primary" :disabled="saving || !(receipt && receipt.lines && receipt.lines.length > 0)">
                  <i v-if="saving" class="fas fa-spinner fa-spin"></i>
                  <span v-else>{{ isEdit ? 'Update' : 'Create' }} Goods Receipt</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
  
      <!-- Available Items Modal -->
      <div v-if="showAvailableItemsModal" class="modal">
        <div class="modal-backdrop" @click="showAvailableItemsModal = false"></div>
        <div class="modal-content">
          <div class="modal-header">
            <h2>Add Items to Receipt</h2>
            <button class="close-btn" @click="showAvailableItemsModal = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <div v-if="unusedItems.length === 0" class="empty-state">
              <i class="fas fa-box-open"></i>
              <p>All available items have been added to the receipt.</p>
            </div>
            
            <div v-else>
              <div class="search-box">
                <input
                  type="text"
                  v-model="itemSearch"
                  placeholder="Search items..."
                />
                <i class="fas fa-search"></i>
              </div>
              
              <div class="table-responsive">
                <table class="items-table">
                  <thead>
                    <tr>
                      <th>PO Number</th>
                      <th>Item Code</th>
                      <th>Item Name</th>
                      <th>Outstanding</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in filteredUnusedItems" :key="item.po_line_id">
                      <td>{{ item.po_number }}</td>
                      <td>{{ item.item_code }}</td>
                      <td>{{ item.item_name }}</td>
                      <td>{{ item.outstanding_quantity }}</td>
                      <td>
                        <button type="button" class="btn-icon add" @click="addItemToReceipt(item)" title="Add to Receipt">
                          <i class="fas fa-plus"></i>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            
            <div class="form-actions">
              <button type="button" class="btn btn-primary" @click="showAvailableItemsModal = false">
                Done
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'GoodsReceiptFormView',
    props: {
      id: {
        type: [Number, String],
        required: false
      }
    },
    data() {
      return {
        receipt: {
          receipt_date: new Date().toISOString().split('T')[0],
          vendor_id: '',
          lines: []
        },
        vendors: [],
        warehouses: [],
        purchaseOrders: [],
        availableItems: [],
        selectedPOs: [],
        loading: true,
        loadingPOs: false,
        itemsLoaded: false,
        saving: false,
        showAvailableItemsModal: false,
        itemSearch: '',
        validationErrors: {}
      };
    },
    computed: {
      isEdit() {
        return !!this.id;
      },
      maxDate() {
        return new Date().toISOString().split('T')[0];
      },
      hasMoreItems() {
        return this.availableItems.some(item => 
          !this.receipt.lines.some(line => line.po_line_id === item.po_line_id)
        );
      },
      unusedItems() {
        return this.availableItems.filter(item => 
          !this.receipt.lines.some(line => line.po_line_id === item.po_line_id)
        );
      },
      filteredUnusedItems() {
        if (!this.itemSearch) return this.unusedItems;
        
        const search = this.itemSearch.toLowerCase();
        return this.unusedItems.filter(item => 
          item.item_code.toLowerCase().includes(search) ||
          item.item_name.toLowerCase().includes(search) ||
          item.po_number.toLowerCase().includes(search)
        );
      }
    },
    created() {
      this.fetchVendors();
      this.fetchWarehouses();
      
      if (this.isEdit) {
        this.fetchReceipt();
      } else {
        this.loading = false;
      }
    },
    methods: {
      fetchVendors() {
        axios.get('/vendors')
          .then(response => {
            // Correctly assign vendors from response.data.data (array)
            const vendorsData = response.data.data;
            if (Array.isArray(vendorsData)) {
              this.vendors = vendorsData.filter(vendor => vendor != null);
            } else {
              this.vendors = [];
              console.warn('Vendors API response data.data is not an array:', vendorsData);
            }
          })
          .catch(error => {
            console.error('Error fetching vendors:', error);
            this.$toast.error('Failed to load vendors');
          });
      },
      fetchWarehouses() {
        axios.get('/warehouses')
          .then(response => {
            this.warehouses = response.data.data;
          })
          .catch(error => {
            console.error('Error fetching warehouses:', error);
            this.$toast.error('Failed to load warehouses');
          });
      },
      fetchReceipt() {
        axios.get(`/goods-receipts/${this.id}`)
          .then(response => {
            const data = response.data.data;
            
            // Update receipt properties individually to maintain reactivity
            this.receipt.receipt_date = data.receipt.receipt_date;
            this.receipt.vendor_id = data.receipt.vendor_id;
            this.receipt.lines = data.lines.map(line => ({
              po_id: line.po_id,
              po_number: line.po_number,
              po_line_id: line.po_line_id,
              item_id: line.item_id,
              item_code: line.item_code,
              item_name: line.item_name,
              ordered_quantity: line.ordered_quantity,
              received_quantity: line.received_quantity,
              outstanding_quantity: line.outstanding_quantity + line.received_quantity,
              warehouse_id: line.warehouse_id,
              batch_number: line.batch_number
            }));
            
            // Mark as loaded
            this.itemsLoaded = true;
            this.loading = false;
          })
          .catch(error => {
            console.error('Error fetching receipt:', error);
            this.$toast.error('Failed to load receipt data');
            this.loading = false;
          });
      },
      vendorSelected() {
        if (!this.receipt.vendor_id) return;
        
        this.loadingPOs = true;
        this.purchaseOrders = [];
        this.selectedPOs = [];
        
        // Fetch outstanding POs for this vendor
        axios.get('/purchase-orders', {
          params: {
            vendor_id: this.receipt.vendor_id,
            status: ['sent', 'partial'],
            outstanding: true
          }
        })
          .then(response => {
            const poData = response.data.data;
            if (poData && poData.data && Array.isArray(poData.data)) {
              this.purchaseOrders = poData.data;
            } else {
              this.purchaseOrders = [];
              console.warn('Purchase Orders API response data.data.data is not an array:', poData);
            }
          })
          .catch(error => {
            console.error('Error fetching purchase orders:', error);
            this.$toast.error('Failed to load purchase orders');
          })
          .finally(() => {
            this.loadingPOs = false;
          });
      },
async loadItemsFromPOs() {
        if (this.selectedPOs.length === 0) return;
        
        this.loading = true;
        
        try {
          const response = await axios.get('/goods-receipts/available-items', {
            params: {
              po_ids: this.selectedPOs
            }
          });
          
          const data = response.data.data;
          this.availableItems = data.po_lines || [];
          
          // Add all items to receipt by default
          this.receipt.lines = this.availableItems.map(item => ({
            po_id: item.po_id,
            po_number: item.po_number,
            po_line_id: item.po_line_id,
            item_id: item.item_id,
            item_code: item.item_code,
            item_name: item.item_name,
            ordered_quantity: item.ordered_quantity,
            received_quantity: item.outstanding_quantity,
            outstanding_quantity: item.outstanding_quantity,
            warehouse_id: this.warehouses.length > 0 ? this.warehouses[0].warehouse_id : '',
            batch_number: ''
          }));
          
          this.itemsLoaded = true;
        } catch (error) {
          console.error('Error fetching available items:', error);
          this.$toast.error('Failed to load available items');
        } finally {
          this.loading = false;
        }
      },
      addItemToReceipt(item) {
        this.receipt.lines.push({
          po_id: item.po_id,
          po_number: item.po_number,
          po_line_id: item.po_line_id,
          item_id: item.item_id,
          item_code: item.item_code,
          item_name: item.item_name,
          ordered_quantity: item.ordered_quantity,
          received_quantity: item.outstanding_quantity,
          outstanding_quantity: item.outstanding_quantity,
          warehouse_id: this.warehouses.length > 0 ? this.warehouses[0].warehouse_id : '',
          batch_number: ''
        });
        
        this.showAvailableItemsModal = false;
      },
      removeLine(index) {
        this.receipt.lines.splice(index, 1);
      },
      saveReceipt() {
        // Reset validation errors
        this.validationErrors = {};
        
        // Check if at least one line is added
        if (this.receipt.lines.length === 0) {
          this.$toast.error('Please add at least one item to the receipt');
          return;
        }
        
        // Prepare data for submission
        const formData = {
          receipt_date: this.receipt.receipt_date,
          vendor_id: this.receipt.vendor_id,
          lines: this.receipt.lines.map(line => ({
            po_line_id: line.po_line_id,
            item_id: line.item_id,
            received_quantity: line.received_quantity,
            warehouse_id: line.warehouse_id,
            batch_number: line.batch_number || null
          }))
        };
        
        this.saving = true;
        
        const request = this.isEdit
          ? axios.put(`/goods-receipts/${this.id}`, formData)
          : axios.post('/goods-receipts', formData);
        
        request
          .then(response => {
            console.log('Save receipt response:', response);
            if (response && response.data && response.data.data && response.data.data.receipt_id) {
              if (this.$toast && typeof this.$toast.success === 'function') {
                this.$toast.success(`Goods receipt ${this.isEdit ? 'updated' : 'created'} successfully`);
              }
              this.$router.push(`/purchasing/goods-receipts/${response.data.data.receipt_id}`);
            } else {
              if (this.$toast && typeof this.$toast.error === 'function') {
                this.$toast.error('Unexpected response from server when saving receipt');
              }
              console.error('Unexpected response structure:', response);
            }
          })
          .catch(error => {
            console.error('Error saving receipt:', error);
            
            if (error.response && error.response.status === 422) {
              this.validationErrors = error.response.data.errors || {};
              if (this.$toast && typeof this.$toast.error === 'function') {
                this.$toast.error('Please correct the errors in the form');
              }
            } else {
              if (this.$toast && typeof this.$toast.error === 'function') {
                this.$toast.error(`Failed to ${this.isEdit ? 'update' : 'create'} goods receipt: ${error.response?.data?.message || 'Unknown error'}`);
              }
            }
          })
          .finally(() => {
            this.saving = false;
          });
      },
      cancel() {
        if (this.isEdit) {
          this.$router.push(`/purchasing/goods-receipts/${this.id}`);
        } else {
          this.$router.push('/purchasing/goods-receipts');
        }
      },
      formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', { 
          year: 'numeric', 
          month: 'short', 
          day: 'numeric' 
        });
      }
    }
  };
  </script>
  
  <style scoped>
  .goods-receipt-form {
    max-width: 100%;
  }
  
  .card {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
  }
  
  .card-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  
  .card-header h2 {
    margin: 0;
    font-size: 1.5rem;
  }
  
  .actions {
    display: flex;
    gap: 0.5rem;
  }
  
  .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    border: 1px solid transparent;
  }
  
  .btn-primary {
    background-color: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
  }
  
  .btn-primary:hover:not(:disabled) {
    background-color: var(--primary-dark);
  }
  
  .btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
  }
  
  .btn-secondary {
    background-color: var(--gray-200);
    color: var(--gray-700);
    border-color: var(--gray-300);
  }
  
  .btn-secondary:hover {
    background-color: var(--gray-300);
  }
  
  .card-body {
    padding: 1.5rem;
  }
  
  .loading-container {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 3rem 0;
    font-size: 1rem;
    color: var(--gray-500);
  }
  
  .loading-container i {
    margin-right: 0.5rem;
  }
  
  .form-section {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--gray-200);
  }
  
  .form-section h3 {
    margin-top: 0;
    margin-bottom: 1rem;
    font-size: 1.25rem;
    color: var(--gray-800);
  }
  
  .form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
  }
  
  .form-group {
    margin-bottom: 1rem;
  }
  
  .form-group label {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
  }
  
  .form-group input,
  .form-group select {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid var(--gray-300);
    border-radius: 0.375rem;
    font-size: 0.875rem;
  }
  
  .required {
    color: #dc2626;
  }
  
  .info-text {
    font-size: 0.875rem;
    color: var(--gray-600);
    margin-bottom: 1rem;
  }
  
  .empty-state {
    text-align: center;
    padding: 2rem 0;
    color: var(--gray-500);
  }
  
  .empty-state i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
  }
  
  .po-selection {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
  }
  
  .po-card {
    border: 1px solid var(--gray-300);
    border-radius: 0.5rem;
    overflow: hidden;
  }
  
  .checkbox-wrapper {
    display: flex;
  }
  
  .checkbox-wrapper input[type="checkbox"] {
    margin: 1rem;
  }
  
  .checkbox-wrapper label {
    flex: 1;
    padding: 1rem 1rem 1rem 0;
    cursor: pointer;
  }
  
  .po-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
  }
  
  .po-header h4 {
    margin: 0;
    font-size: 1rem;
  }
  
  .status-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: capitalize;
  }
  
  .status-badge.sent {
    background-color: #dbeafe;
    color: #1e40af;
  }
  
  .status-badge.partial {
    background-color: #fef3c7;
    color: #92400e;
  }
  
  .status-badge.pending {
    background-color: #fef3c7;
    color: #92400e;
  }
  
  .status-badge.confirmed {
    background-color: #d1fae5;
    color: #065f46;
  }
  
  .po-details {
    font-size: 0.875rem;
    color: var(--gray-600);
  }
  
  .po-details div {
    margin-bottom: 0.25rem;
  }
  
  .actions-wrapper {
    display: flex;
    justify-content: flex-end;
    margin-top: 1rem;
  }
  
  .table-responsive {
    overflow-x: auto;
    margin-bottom: 1rem;
  }
  
  .items-table {
    width: 100%;
    border-collapse: collapse;
  }
  
  .items-table th {
    background-color: var(--gray-50);
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    border-bottom: 1px solid var(--gray-200);
    white-space: nowrap;
  }
  
  .items-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-800);
  }
  
  .items-table input,
  .items-table select {
    width: 100%;
    padding: 0.375rem 0.5rem;
    border: 1px solid var(--gray-300);
    border-radius: 0.25rem;
    font-size: 0.875rem;
  }
  
  .error-message {
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: #dc2626;
  }
  
  .add-items-row {
    display: flex;
    justify-content: flex-end;
    margin-top: 1rem;
  }
  
  .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
  }
  
  .btn-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 0.375rem;
    color: var(--gray-700);
    background-color: var(--gray-100);
    border: 1px solid var(--gray-200);
    text-decoration: none;
    transition: all 0.2s;
    cursor: pointer;
  }
  
  .btn-icon:hover {
    background-color: var(--gray-200);
  }
  
  .btn-icon.delete {
    color: #dc2626;
    background-color: #fee2e2;
    border-color: #dc2626;
  }
  
  .btn-icon.delete:hover {
    background-color: #fecaca;
  }
  
  .btn-icon.add {
    color: #059669;
    background-color: #d1fae5;
    border-color: #059669;
  }
  
  .btn-icon.add:hover {
    background-color: #a7f3d0;
  }
  
  /* Modal styles */
  .modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 50;
    display: flex;
    justify-content: center;
    align-items: center;
  }
  
  .modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 50;
  }
  
  .modal-content {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    width: 90%;
    max-width: 900px;
    z-index: 60;
    overflow: hidden;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
  }
  
  .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--gray-200);
  }
  
  .modal-header h2 {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
  }
  
  .close-btn {
    background: none;
    border: none;
    color: var(--gray-500);
    cursor: pointer;
    font-size: 1rem;
  }
  
  .modal-body {
    padding: 1.5rem;
    overflow-y: auto;
  }
  
  .search-box {
    position: relative;
    margin-bottom: 1rem;
  }
  
  .search-box input {
    width: 100%;
    padding: 0.5rem 2rem 0.5rem 0.75rem;
    border: 1px solid var(--gray-300);
    border-radius: 0.375rem;
    font-size: 0.875rem;
  }
  
  .search-box i {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-500);
  }
  
  @media (max-width: 768px) {
    .po-selection {
      grid-template-columns: 1fr;
    }
    
    .card-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }
    
    .actions {
      width: 100%;
    }
    
    .btn {
      flex: 1;
      justify-content: center;
    }
    
    .form-actions {
      flex-direction: column;
    }
    
    .form-actions button {
      width: 100%;
    }
  }
  </style>