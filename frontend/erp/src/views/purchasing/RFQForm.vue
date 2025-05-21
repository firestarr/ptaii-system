<!-- src/views/purchasing/RFQForm.vue -->
<template>
    <div class="rfq-form-container">
      <div class="page-header">
        <h1>{{ isEditing ? 'Edit Request for Quotation' : 'Create Request for Quotation' }}</h1>
        <div class="header-actions">
          <button @click="$router.go(-1)" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back
          </button>
        </div>
      </div>
  
      <div v-if="loading" class="loading-indicator">
        <i class="fas fa-spinner fa-spin"></i> Loading...
      </div>
  
      <form v-else @submit.prevent="saveRFQ" class="rfq-form">
        <div class="form-section">
          <h2 class="section-title">RFQ Information</h2>
          
          <div class="form-row">
            <div class="form-group">
              <label for="rfq_date">RFQ Date <span class="required">*</span></label>
              <input 
                type="date" 
                id="rfq_date" 
                v-model="rfq.rfq_date" 
                required
                class="form-control"
              />
            </div>
            
            <div class="form-group">
              <label for="validity_date">Validity Date</label>
              <input 
                type="date" 
                id="validity_date" 
                v-model="rfq.validity_date" 
                class="form-control"
              />
            </div>
          </div>
          
          <div class="form-group" v-if="isEditing">
            <label for="rfq_number">RFQ Number</label>
            <input 
              type="text" 
              id="rfq_number" 
              v-model="rfq.rfq_number" 
              class="form-control"
              disabled
            />
          </div>
          
          <div class="form-group" v-if="isEditing">
            <label for="status">Status</label>
            <input 
              type="text" 
              id="status" 
              v-model="rfq.status" 
              class="form-control"
              disabled
            />
          </div>
        </div>
  
        <div class="form-section">
          <div class="section-header">
            <h2 class="section-title">RFQ Lines</h2>
            <button type="button" @click="addLine" class="btn btn-outline-primary">
              <i class="fas fa-plus"></i> Add Item
            </button>
          </div>
          
          <div v-if="rfq.lines.length === 0" class="empty-lines">
            <p>No items added yet. Click "Add Item" to start adding items to this RFQ.</p>
          </div>
          
          <div v-else class="rfq-lines-table-container">
            <table class="rfq-lines-table">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Quantity</th>
                  <th>UOM</th>
                  <th>Required Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(line, index) in rfq.lines" :key="index">
                  <td>
                    <div class="custom-dropdown">
                      <input
                        type="text"
                        class="form-control"
                        v-model="line.itemSearch"
                        placeholder="Search for an item..."
                        @focus="showDropdown(index)"
                        @input="showDropdown(index)"
                        @blur="hideDropdown(index)"
                        required
                      />
                      <div v-show="line.showDropdown" class="custom-dropdown-menu" :style="{ minWidth: '300px', maxWidth: '450px' }">
                        <div v-for="item in filteredItems(line.itemSearch)" :key="item.item_id" 
                          @mousedown="selectItem(line, item)" class="dropdown-item">
                          {{ item.item_code }} - {{ item.name }}
                        </div>
                        <div v-if="filteredItems(line.itemSearch).length === 0" class="dropdown-item text-muted">No items found</div>
                      </div>
                      <small v-if="line.item" class="item-description">
                        {{ line.item.description }}
                      </small>
                    </div>
                  </td>
                  <td>
                    <input 
                      type="number" 
                      v-model.number="line.quantity" 
                      class="form-control" 
                      min="0.01" 
                      step="0.01"
                      required
                    />
                  </td>
                  <td>
                    <select 
                      v-model="line.uom_id" 
                      class="form-control"
                      required
                    >
                      <option value="" disabled>Select UOM</option>
                      <option 
                        v-for="uom in uoms" 
                        :key="uom.uom_id" 
                        :value="uom.uom_id"
                      >
                        {{ uom.name }} ({{ uom.symbol }})
                      </option>
                    </select>
                  </td>
                  <td>
                    <input 
                      type="date" 
                      v-model="line.required_date" 
                      class="form-control"
                    />
                  </td>
                  <td>
                    <button 
                      type="button" 
                      @click="removeLine(index)" 
                      class="btn-icon btn-danger"
                    >
                      <i class="fas fa-trash"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
  
        <div class="form-section">
          <h2 class="section-title">Notes</h2>
          <div class="form-group">
            <textarea 
              v-model="rfq.notes" 
              class="form-control" 
              rows="3" 
              placeholder="Enter any additional notes or requirements..."
            ></textarea>
          </div>
        </div>
  
        <div class="form-actions">
          <button type="button" @click="$router.go(-1)" class="btn btn-secondary">
            Cancel
          </button>
          <button type="submit" class="btn btn-primary" :disabled="isSaving">
            <i v-if="isSaving" class="fas fa-spinner fa-spin"></i>
            {{ isSaving ? 'Saving...' : (isEditing ? 'Update RFQ' : 'Create RFQ') }}
          </button>
        </div>
      </form>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'RFQForm',
    props: {
      id: {
        type: [Number, String],
        required: false
      }
    },
    data() {
      return {
        rfq: {
          rfq_date: new Date().toISOString().substr(0, 10),
          validity_date: '',
          lines: [],
          notes: ''
        },
        items: [],
        uoms: [],
        loading: true,
        isSaving: false,
        showItemModal: false,
        itemSearch: '',
        currentLineIndex: null
      }
    },
    computed: {
      isEditing() {
        return !!this.id;
      },
      filteredItems() {
        return (search) => {
          if (!search) {
            return this.items;
          }
          
          const searchLower = search.toLowerCase();
          return this.items.filter(item => 
            item.item_code.toLowerCase().includes(searchLower) || 
            item.name.toLowerCase().includes(searchLower) ||
            (item.description && item.description.toLowerCase().includes(searchLower))
          );
        }
      }
    },
    async mounted() {
      try {
        // Load initial data
        await Promise.all([
          this.loadItems(),
          this.loadUOMs()
        ]);
        
        if (this.isEditing) {
          await this.loadRFQ();
        } else {
          // Add empty line for new RFQ
          this.addLine();
        }
      } catch (error) {
        console.error('Error initializing form:', error);
        this.$toast.error('Failed to initialize form. Please try again.');
      } finally {
        this.loading = false;
      }
    },
    methods: {
      // Methods for searchable dropdown
      showDropdown(index) {
        if (!this.rfq.lines[index].showDropdown) {
          this.rfq.lines[index].showDropdown = true;
        }
      },
      hideDropdown(index) {
        setTimeout(() => {
          this.rfq.lines[index].showDropdown = false;
        }, 200);
      },
      selectItem(line, item) {
        line.item_id = item.item_id;
        line.itemSearch = `${item.item_code} - ${item.name}`;
        line.item = item;
        line.showDropdown = false;
        
        // Auto-fill UOM based on item's default UOM
        if (item.unitOfMeasure && item.unitOfMeasure.uom_id) {
          line.uom_id = item.unitOfMeasure.uom_id;
        } else if (item.uom_id) {
          // Direct UOM ID reference
          line.uom_id = item.uom_id;
        } else if (item.default_uom_id) {
          // Try another possible property name
          line.uom_id = item.default_uom_id;
        }
        
        // For debugging - remove in production
        console.log('Selected item:', item);
        console.log('UOM set to:', line.uom_id);
      },

      async loadItems() {
        try {
          const response = await axios.get('/items', {
            params: { is_purchasable: true }
          });
          
          if (response.data.data) {
            this.items = response.data.data;
          }
        } catch (error) {
          console.error('Error loading items:', error);
          throw error;
        }
      },
      async loadUOMs() {
        try {
          const response = await axios.get('/uoms');
          
          if (response.data.data) {
            this.uoms = response.data.data;
          }
        } catch (error) {
          console.error('Error loading UOMs:', error);
          throw error;
        }
      },
      async loadRFQ() {
        try {
          const response = await axios.get(`/request-for-quotations/${this.id}`);
          
          if (response.data.status === 'success' && response.data.data) {
            const rfqData = response.data.data;
            
            this.rfq = {
              rfq_id: rfqData.rfq_id,
              rfq_number: rfqData.rfq_number,
              rfq_date: this.formatDateForInput(rfqData.rfq_date),
              validity_date: this.formatDateForInput(rfqData.validity_date),
              status: rfqData.status,
              notes: rfqData.notes || '',
              lines: rfqData.lines.map(line => {
                // Find matching item to get its name
                const matchedItem = this.items.find(item => item.item_id === line.item_id);
                const itemDisplay = matchedItem ? 
                  `${matchedItem.item_code} - ${matchedItem.name}` : '';
                
                return {
                  line_id: line.line_id,
                  item_id: line.item_id,
                  itemSearch: itemDisplay,  // Add itemSearch property for display
                  quantity: line.quantity,
                  uom_id: line.uom_id,
                  required_date: this.formatDateForInput(line.required_date),
                  item: matchedItem,
                  showDropdown: false  // Add dropdown visibility property
                };
              })
            };
          } else {
            throw new Error(response.data.message || 'Failed to load RFQ data');
          }
        } catch (error) {
          console.error('Error loading RFQ:', error);
          this.$toast.error('Failed to load RFQ data. Please try again.');
          this.$router.push('/purchasing/rfqs');
        }
      },
      formatDateForInput(dateString) {
        if (!dateString) return '';
        
        const date = new Date(dateString);
        return date.toISOString().substr(0, 10);
      },
      addLine() {
        this.rfq.lines.push({
          item_id: '',
          itemSearch: '',
          quantity: 1,
          uom_id: '',
          required_date: '',
          item: null,
          showDropdown: false
        });
      },
      removeLine(index) {
        this.rfq.lines.splice(index, 1);
      },
      async saveRFQ() {
        // Validate form
        if (!this.validateForm()) return;
        
        this.isSaving = true;
        
        try {
          // Prepare data for API
          const data = {
            rfq_date: this.rfq.rfq_date,
            validity_date: this.rfq.validity_date,
            notes: this.rfq.notes,
            lines: this.rfq.lines.map(line => ({
              item_id: line.item_id,
              quantity: line.quantity,
              uom_id: line.uom_id,
              required_date: line.required_date
            }))
          };
          
          let response;
          
          if (this.isEditing) {
            response = await axios.put(`/request-for-quotations/${this.id}`, data);
          } else {
            response = await axios.post('/request-for-quotations', data);
          }
          
          if (response.data.status === 'success') {
            this.$toast.success(
              this.isEditing 
                ? 'Request For Quotation updated successfully' 
                : 'Request For Quotation created successfully'
            );
            
            // Navigate to the detail page or list page
            if (this.isEditing) {
              this.$router.go(-1);
            } else {
              this.$router.push(`/purchasing/rfqs/${response.data.data.rfq_id}`);
            }
          } else {
            throw new Error(response.data.message || 'Failed to save RFQ');
          }
        } catch (error) {
          console.error('Error saving RFQ:', error);
          
          if (error.response && error.response.data && error.response.data.message) {
            this.$toast.error('Failed to save RFQ: ' + error.response.data.message);
          } else {
            this.$toast.error('Failed to save RFQ. Please try again.');
          }
        } finally {
          this.isSaving = false;
        }
      },
      validateForm() {
        // Check if RFQ date is provided
        if (!this.rfq.rfq_date) {
          this.$toast.error('RFQ Date is required');
          return false;
        }
        
        // Check if at least one line is added
        if (this.rfq.lines.length === 0) {
          this.$toast.error('Please add at least one item');
          return false;
        }
        
        // Check if all lines have required fields
        for (let i = 0; i < this.rfq.lines.length; i++) {
          const line = this.rfq.lines[i];
          
          if (!line.item_id) {
            this.$toast.error(`Please select an item for line ${i + 1}`);
            return false;
          }
          
          if (!line.quantity || line.quantity <= 0) {
            this.$toast.error(`Please enter a valid quantity for line ${i + 1}`);
            return false;
          }
          
          if (!line.uom_id) {
            this.$toast.error(`Please select a unit of measure for line ${i + 1}`);
            return false;
          }
        }
        
        return true;
      }
    }
  }
  </script>
  
  <style scoped>
  .rfq-form-container {
    padding: 1rem;
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }
  
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
  }
  
  .page-header h1 {
    margin: 0;
    font-size: 1.5rem;
  }
  
  .header-actions {
    display: flex;
    gap: 0.5rem;
  }
  
  .loading-indicator {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 3rem 0;
    color: var(--gray-500);
    font-size: 0.875rem;
  }
  
  .loading-indicator i {
    margin-right: 0.5rem;
    animation: spin 1s linear infinite;
  }
  
  .rfq-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
  }
  
  .form-section {
    border: 1px solid var(--gray-200);
    border-radius: 0.5rem;
    padding: 1.5rem;
  }
  
  .section-title {
    font-size: 1.125rem;
    margin: 0 0 1rem 0;
    color: var(--gray-800);
  }
  
  .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
  }
  
  .form-row {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
  }
  
  .form-group {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    margin-bottom: 1rem;
    flex: 1;
  }
  
  .form-group:last-child {
    margin-bottom: 0;
  }
  
  label {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--gray-700);
  }
  
  .required {
    color: #dc2626;
  }
  
  .form-control {
    padding: 0.625rem;
    border: 1px solid var(--gray-200);
    border-radius: 0.375rem;
    font-size: 0.875rem;
    transition: border-color 0.2s, box-shadow 0.2s;
  }
  
  .form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
  }
  
  .form-control:disabled {
    background-color: var(--gray-100);
    cursor: not-allowed;
  }
  
  .empty-lines {
    padding: 2rem 0;
    text-align: center;
    color: var(--gray-500);
    background-color: var(--gray-50);
    border-radius: 0.375rem;
    border: 1px dashed var(--gray-300);
  }
  
  .rfq-lines-table-container {
    overflow-x: auto;
  }
  
  .rfq-lines-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
  }
  
  .rfq-lines-table th {
    text-align: left;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-200);
    background-color: var(--gray-50);
    font-weight: 500;
    color: var(--gray-600);
  }
  
  .rfq-lines-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-100);
    color: var(--gray-800);
    vertical-align: middle;
  }
  
  /* Custom Dropdown Styles - Added for searchable dropdown */
  .custom-dropdown {
    position: relative;
    width: 100%;
  }
  
  .custom-dropdown-menu {
    position: fixed; /* Use fixed instead of absolute */
    z-index: 9999;
    display: block;
    width: 100%;
    max-height: 250px;
    overflow-y: auto;
    padding: 0.5rem 0;
    margin: 0.125rem 0 0;
    background-color: #fff;
    border: 1px solid rgba(0, 0, 0, 0.15);
    border-radius: 0.25rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.175);
  }
  
  .dropdown-item {
    display: block;
    width: 100%;
    padding: 0.5rem 1rem;
    clear: both;
    font-weight: 400;
    color: #212529;
    text-align: left;
    white-space: nowrap;
    background-color: transparent;
    border: 0;
    cursor: pointer;
  }
  
  .dropdown-item:hover {
    color: #16181b;
    text-decoration: none;
    background-color: #f8f9fa;
  }
  
  .item-selection {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }
  
  .item-description {
    font-size: 0.75rem;
    color: var(--gray-500);
  }
  
  .btn {
    padding: 0.625rem 1rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
  }
  
  .btn-primary {
    background-color: var(--primary-color);
    color: white;
    border: none;
  }
  
  .btn-primary:hover {
    background-color: var(--primary-dark);
  }
  
  .btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
  }
  
  .btn-secondary {
    background-color: white;
    color: var(--gray-700);
    border: 1px solid var(--gray-300);
  }
  
  .btn-secondary:hover {
    background-color: var(--gray-50);
  }
  
  .btn-outline {
    background-color: white;
    color: var(--gray-700);
    border: 1px solid var(--gray-300);
  }
  
  .btn-outline:hover {
    background-color: var(--gray-50);
  }
  
  .btn-outline-primary {
    background-color: white;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
  }
  
  .btn-outline-primary:hover {
    background-color: rgba(37, 99, 235, 0.05);
  }
  
  .btn-icon {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    width: 2rem;
    height: 2rem;
    border-radius: 0.375rem;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
  }
  
  .btn-danger {
    color: white;
    background-color: #ef4444;
  }
  
  .btn-danger:hover {
    background-color: #dc2626;
  }
  
  .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1rem;
  }
  
  /* Make dropdown appear above other elements */
  .table-responsive {
    overflow: visible !important;
  }
  
  .text-muted {
    color: #6c757d;
  }
  
  @media (max-width: 768px) {
    .form-row {
      flex-direction: column;
    }
  }
  </style>