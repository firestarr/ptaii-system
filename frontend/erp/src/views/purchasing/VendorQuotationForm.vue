<!-- src/views/purchasing/VendorQuotationForm.vue -->
<template>
  <div class="quotation-form-container">
    <div class="page-header">
      <h1>{{ isEditMode ? 'Edit Vendor Quotation' : 'Create Vendor Quotation' }}</h1>
      <router-link to="/purchasing/quotations" class="btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to List
      </router-link>
    </div>

    <div v-if="loading" class="loading-container">
      <i class="fas fa-spinner fa-spin"></i> Loading...
    </div>

    <form v-else @submit.prevent="saveQuotation" class="quotation-form">
      <div class="form-card">
        <h2 class="card-title">Quotation Information</h2>
        
        <div class="form-row">
          <div class="form-group">
            <label for="rfq_id">Request for Quotation</label>
            <select 
              id="rfq_id" 
              v-model="form.rfq_id" 
              required 
              :disabled="isEditMode"
              @change="loadRfqDetails"
            >
              <option value="" disabled>Select a Request for Quotation</option>
              <option v-for="rfq in rfqOptions" :key="rfq.rfq_id" :value="rfq.rfq_id">
                {{ rfq.rfq_number }}
              </option>
            </select>
            <div v-if="errors.rfq_id" class="error-message">{{ errors.rfq_id[0] }}</div>
          </div>
          
          <div class="form-group">
            <label for="vendor_id">Vendor</label>
            <select 
              id="vendor_id" 
              v-model="form.vendor_id" 
              required
              :disabled="isEditMode"
            >
              <option value="" disabled>Select a Vendor</option>
              <option v-for="vendor in vendorOptions" :key="vendor.vendor_id" :value="vendor.vendor_id">
                {{ vendor.name }}
              </option>
            </select>
            <div v-if="errors.vendor_id" class="error-message">{{ errors.vendor_id[0] }}</div>
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label for="quotation_date">Quotation Date</label>
            <input 
              type="date" 
              id="quotation_date" 
              v-model="form.quotation_date" 
              required
            />
            <div v-if="errors.quotation_date" class="error-message">{{ errors.quotation_date[0] }}</div>
          </div>
          
          <div class="form-group">
            <label for="validity_date">Validity Date</label>
            <input 
              type="date" 
              id="validity_date" 
              v-model="form.validity_date"
              :min="form.quotation_date"
            />
            <div v-if="errors.validity_date" class="error-message">{{ errors.validity_date[0] }}</div>
          </div>
        </div>
        
        <div class="form-row" v-if="isEditMode">
          <div class="form-group">
            <label for="status">Status</label>
            <div class="readonly-field">{{ capitalizeFirstLetter(form.status) }}</div>
          </div>
        </div>
      </div>
      
      <div class="form-card">
        <div class="card-header">
          <h2 class="card-title">Quotation Lines</h2>
          <div v-if="rfqDetails" class="info-text">
            Based on RFQ: {{ rfqDetails.rfq_number }}
          </div>
        </div>
        
        <div v-if="!form.lines.length" class="empty-lines">
          <p>No items added yet. Select an RFQ to load requested items.</p>
        </div>
        
        <div v-else class="table-responsive">
          <table class="lines-table">
            <thead>
              <tr>
                <th>Item</th>
                <th>Description</th>
                <th>Quantity</th>
                <th>UOM</th>
                <th>Unit Price</th>
                <th>Delivery Date</th>
                <th>Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(line, index) in form.lines" :key="index">
                <td>
                  <select 
                    v-model="line.item_id" 
                    required
                    :disabled="true"
                  >
                    <option v-for="item in itemOptions" :key="item.item_id" :value="item.item_id">
                      {{ item.item_code }}
                    </option>
                  </select>
                </td>
                <td>
                  <div class="readonly-field">{{ getItemName(line.item_id) }}</div>
                </td>
                <td>
                  <input 
                    type="number" 
                    v-model.number="line.quantity" 
                    required 
                    min="0.01"
                    step="0.01"
                    :disabled="true"
                  />
                </td>
                <td>
                  <select 
                    v-model="line.uom_id" 
                    required
                    :disabled="true"
                  >
                    <option v-for="uom in uomOptions" :key="uom.uom_id" :value="uom.uom_id">
                      {{ uom.symbol }}
                    </option>
                  </select>
                </td>
                <td>
                  <input 
                    type="number" 
                    v-model.number="line.unit_price" 
                    required 
                    min="0"
                    step="0.01"
                    @input="calculateSubtotal(index)"
                  />
                </td>
                <td>
                  <input 
                    type="date" 
                    v-model="line.delivery_date"
                    :min="form.quotation_date"
                  />
                </td>
                <td class="subtotal-cell">
                  {{ formatCurrency(calculateLineSubtotal(line)) }}
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="6" class="text-right"><strong>Total</strong></td>
                <td class="subtotal-cell"><strong>{{ formatCurrency(calculateTotal()) }}</strong></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
      
      <div class="form-actions">
        <router-link to="/purchasing/quotations" class="btn btn-secondary">
          Cancel
        </router-link>
        <button type="submit" class="btn btn-primary" :disabled="submitting">
          <i class="fas fa-spinner fa-spin" v-if="submitting"></i>
          {{ isEditMode ? 'Update Quotation' : 'Create Quotation' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'VendorQuotationForm',
  props: {
    id: {
      type: [Number, String],
      required: false
    }
  },
  data() {
    return {
      localId: this.id || null,
      isEditMode: false,
      loading: true,
      submitting: false,
      form: {
        rfq_id: '',
        vendor_id: '',
        quotation_date: '',
        validity_date: '',
        status: '',
        lines: []
      },
      rfqOptions: [],
      vendorOptions: [],
      itemOptions: [],
      uomOptions: [],
      rfqDetails: null,
      errors: {}
    };
  },
  computed: {
    // Check if we're in edit mode based on whether ID is provided in the route
    formMode() {
      return this.isEditMode ? 'edit' : 'create';
    }
  },
  methods: {
    // Initialize form based on mode
    initializeForm() {
      this.loading = true;
      this.fetchDropdownOptions();
      
      if (this.localId) {
        this.isEditMode = true;
        this.fetchQuotationDetails();
      } else {
        this.isEditMode = false;
        // Set default quotation date to today
        this.form.quotation_date = new Date().toISOString().split('T')[0];
        this.loading = false;
      }
    },
    
    // Fetch options for dropdowns
    fetchDropdownOptions() {
      Promise.all([
        axios.get('/request-for-quotations?status=sent'),
        axios.get('/vendors?is_active=1'),
        axios.get('/items'),
        axios.get('/unit-of-measures')
      ])
      .then(([rfqResponse, vendorResponse, itemsResponse, uomResponse]) => {
        this.rfqOptions = rfqResponse.data.data.data;
        this.vendorOptions = vendorResponse.data.data.data;
        this.itemOptions = itemsResponse.data.data.data;
        this.uomOptions = uomResponse.data.data;
      })
      .catch(error => {
        console.error('Error fetching options:', error);
        this.$toasted.error('Failed to load form options');
      });
    },
    
    // Fetch quotation details for edit mode
    fetchQuotationDetails() {
      axios.get(`/vendor-quotations/${this.localId}`)
        .then(response => {
          if (response.data.status === 'success') {
            const quotation = response.data.data;
            
            // Format dates for the form inputs
            const formattedQuotation = {
              ...quotation,
              quotation_date: quotation.quotation_date ? quotation.quotation_date.split('T')[0] : '',
              validity_date: quotation.validity_date ? quotation.validity_date.split('T')[0] : ''
            };
            
            this.form = formattedQuotation;
            
            // Load RFQ details for reference
            this.loadRfqDetails();
          } else {
            this.$toasted.error('Failed to load quotation details');
            this.$router.push('/purchasing/quotations');
          }
        })
        .catch(error => {
          console.error('Error fetching quotation details:', error);
          this.$toasted.error('Error loading quotation');
          this.$router.push('/purchasing/quotations');
        })
        .finally(() => {
          this.loading = false;
        });
    },
    
    // Load RFQ details when RFQ is selected
    loadRfqDetails() {
      if (!this.form.rfq_id) return;
      
      axios.get(`/request-for-quotations/${this.form.rfq_id}`)
        .then(response => {
          if (response.data.status === 'success') {
            this.rfqDetails = response.data.data;
            
            // Only initialize lines when creating a new quotation
            if (!this.isEditMode) {
              // Initialize quotation lines from RFQ items
              this.form.lines = this.rfqDetails.lines.map(rfqLine => ({
                item_id: rfqLine.item_id,
                quantity: rfqLine.quantity,
                uom_id: rfqLine.uom_id,
                unit_price: 0, // Initialize with zero, will be filled by user
                delivery_date: rfqLine.required_date || ''
              }));
            }
          }
        })
        .catch(error => {
          console.error('Error fetching RFQ details:', error);
          this.$toasted.error('Failed to load RFQ details');
        });
    },
    
    // Calculate subtotal for a line
    calculateLineSubtotal(line) {
      return line.quantity * line.unit_price;
    },
    
    // Calculate total for all lines
    calculateTotal() {
      return this.form.lines.reduce((sum, line) => sum + this.calculateLineSubtotal(line), 0);
    },
    
    // Update subtotal on price change
    calculateSubtotal() {
      // This method is just a trigger for reactive updates - the actual calculation happens in calculateLineSubtotal
    },
    
    // Get item name from its ID
    getItemName(itemId) {
      const item = this.itemOptions.find(item => item.item_id === itemId);
      return item ? item.name : '';
    },
    
    // Format currency for display
    formatCurrency(value) {
      return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
      }).format(value);
    },
    
    // Capitalize first letter of a string
    capitalizeFirstLetter(string) {
      if (!string) return '';
      return string.charAt(0).toUpperCase() + string.slice(1);
    },
    
    // Submit the form
    saveQuotation() {
      this.submitting = true;
      this.errors = {};
      
      // Create a deep copy of the form data to avoid modifying the original
      const formData = JSON.parse(JSON.stringify(this.form));
      
      // Use appropriate HTTP method based on mode
      const method = this.isEditMode ? 'put' : 'post';
      const url = this.isEditMode ? `/vendor-quotations/${this.localId}` : '/vendor-quotations';
      
      axios[method](url, formData)
        .then(response => {
          if (response.data.status === 'success') {
            this.$toasted.success(this.isEditMode ? 'Quotation updated successfully' : 'Quotation created successfully');
            this.$router.push(`/purchasing/quotations/${response.data.data.quotation_id}`);
          } else {
            this.$toasted.error(response.data.message || 'Failed to save quotation');
          }
        })
        .catch(error => {
          console.error('API Error:', error);
          
          if (error.response && error.response.data && error.response.data.errors) {
            this.errors = error.response.data.errors;
            this.$toasted.error('Please correct the errors in the form');
          } else {
            this.$toasted.error('An error occurred while saving');
          }
        })
        .finally(() => {
          this.submitting = false;
        });
    }
  },
  mounted() {
    this.initializeForm();
  },
  watch: {
    // Watch for route changes
    '$route'(to, from) {
      if (to.params.id !== from.params.id) {
        this.localId = to.params.id;
        this.initializeForm();
      }
    }
  }
};
</script>

<style scoped>
.quotation-form-container {
  max-width: 1200px;
  margin: 0 auto;
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
  color: var(--gray-800);
}

.btn-secondary {
  display: inline-flex;
  align-items: center;
  padding: 0.5rem 1rem;
  background-color: var(--gray-100);
  color: var(--gray-700);
  border: 1px solid var(--gray-200);
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.2s;
}

.btn-secondary:hover {
  background-color: var(--gray-200);
  text-decoration: none;
}

.btn-secondary i {
  margin-right: 0.5rem;
}

.loading-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 200px;
  color: var(--gray-500);
}

.loading-container i {
  margin-right: 0.5rem;
  animation: spin 1s linear infinite;
}

.quotation-form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.form-card {
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 1.5rem;
}

.card-title {
  font-size: 1.25rem;
  margin-top: 0;
  margin-bottom: 1.5rem;
  color: var(--gray-800);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.info-text {
  color: var(--gray-500);
  font-size: 0.875rem;
}

.form-row {
  display: flex;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.form-group {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.form-group label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--gray-700);
  margin-bottom: 0.375rem;
}

.form-group input, 
.form-group select {
  padding: 0.625rem;
  border: 1px solid var(--gray-200);
  border-radius: 0.375rem;
  font-size: 0.875rem;
  color: var(--gray-800);
  background-color: white;
}

.form-group input:focus, 
.form-group select:focus {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 1px var(--primary-color);
  outline: none;
}

.form-group input:disabled, 
.form-group select:disabled {
  background-color: var(--gray-100);
  cursor: not-allowed;
}

.readonly-field {
  padding: 0.625rem;
  border: 1px solid var(--gray-200);
  border-radius: 0.375rem;
  font-size: 0.875rem;
  color: var(--gray-700);
  background-color: var(--gray-50);
}

.error-message {
  color: #ef4444;
  font-size: 0.75rem;
  margin-top: 0.25rem;
}

.empty-lines {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 2rem;
  background-color: var(--gray-50);
  border: 1px dashed var(--gray-300);
  border-radius: 0.375rem;
  color: var(--gray-500);
  text-align: center;
}

.table-responsive {
  overflow-x: auto;
}

.lines-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.lines-table th {
  text-align: left;
  padding: 0.75rem;
  border-bottom: 1px solid var(--gray-200);
  font-weight: 500;
  color: var(--gray-600);
}

.lines-table td {
  padding: 0.5rem 0.75rem;
  border-bottom: 1px solid var(--gray-100);
}

.lines-table tfoot td {
  border-top: 1px solid var(--gray-200);
  background-color: var(--gray-50);
  padding: 0.75rem;
}

.text-right {
  text-align: right;
}

.subtotal-cell {
  text-align: right;
  font-weight: 500;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 1rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.625rem 1.25rem;
  font-size: 0.875rem;
  font-weight: 500;
  border-radius: 0.375rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary {
  background-color: var(--primary-color);
  color: white;
  border: none;
}

.btn-primary:hover:not(:disabled) {
  background-color: var(--primary-dark);
}

.btn-primary:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.btn-primary i {
  margin-right: 0.5rem;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@media (max-width: 768px) {
  .form-row {
    flex-direction: column;
    gap: 1rem;
  }
  
  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }
}
</style>