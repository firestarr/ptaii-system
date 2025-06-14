<!-- src/views/purchasing/VendorInvoiceForm.vue -->
<template>
    <div class="vendor-invoice-form">
      <div class="page-header">
        <h1>{{ isEditing ? 'Edit Vendor Invoice' : 'Create Vendor Invoice' }}</h1>
        <div class="actions">
          <router-link to="/purchasing/vendor-invoices" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to List
          </router-link>
        </div>
      </div>
  
      <div v-if="loading" class="loading">  
        <i class="fas fa-spinner fa-spin"></i> Loading data...
      </div>
  
      <div v-else class="form-container">
        <form @submit.prevent="saveInvoice">
          <div class="form-header-card">
            <div class="card-header">
              <h2>Invoice Information</h2>
            </div>
            <div class="card-body">
              <div class="form-row">
                <div class="form-group">
                  <label for="invoice_number">Invoice Number</label>
                  <input
                    type="text"
                    id="invoice_number"
                    v-model="form.invoice_number"
                    required
                    :disabled="isEditing"
                    placeholder="Enter invoice number"
                  />
                  <span v-if="errors.invoice_number" class="error-text">{{ errors.invoice_number[0] }}</span>
                </div>
  
                <div class="form-group">
                  <label for="invoice_date">Invoice Date</label>
                  <input
                    type="date"
                    id="invoice_date"
                    v-model="form.invoice_date"
                    required
                  />
                  <span v-if="errors.invoice_date" class="error-text">{{ errors.invoice_date[0] }}</span>
                </div>
  
                <div class="form-group">
                  <label for="due_date">Due Date</label>
                  <input
                    type="date"
                    id="due_date"
                    v-model="form.due_date"
                  />
                  <span v-if="errors.due_date" class="error-text">{{ errors.due_date[0] }}</span>
                </div>
              </div>
  
              <div class="form-row">
                <div class="form-group">
                  <label for="vendor_id">Vendor</label>
                  <select
                    id="vendor_id"
                    v-model="selectedVendorId"
                    required
                    :disabled="isEditing"
                    @change="vendorChanged"
                  >
                    <option value="" disabled>Select a vendor</option>
                    <option v-for="vendor in vendors" :key="vendor.vendor_id" :value="vendor.vendor_id">
                      {{ vendor.name }}
                    </option>
                  </select>
                  <span v-if="errors.vendor_id" class="error-text">{{ errors.vendor_id[0] }}</span>
                </div>
  
                <div class="form-group">
                  <label for="currency_code">Currency</label>
                  <select
                    id="currency_code"
                    v-model="form.currency_code"
                    required
                    @change="currencyChanged"
                  >
                    <option value="USD">USD - US Dollar</option>
                    <option value="EUR">EUR - Euro</option>
                    <option value="IDR">IDR - Indonesian Rupiah</option>
                    <option value="JPY">JPY - Japanese Yen</option>
                  </select>
                  <span v-if="errors.currency_code" class="error-text">{{ errors.currency_code[0] }}</span>
                </div>
  
                <div class="form-group">
                  <label for="exchange_rate">Exchange Rate</label>
                  <div class="input-with-button">
                    <input
                      type="number"
                      id="exchange_rate"
                      v-model="form.exchange_rate"
                      step="0.0001"
                      min="0.0001"
                      required
                    />
                    <button type="button" class="btn btn-secondary" @click="fetchExchangeRate">
                      <i class="fas fa-sync-alt"></i>
                    </button>
                  </div>
                  <small class="helper-text">Base currency: USD</small>
                  <span v-if="errors.exchange_rate" class="error-text">{{ errors.exchange_rate[0] }}</span>
                </div>
              </div>
            </div>
          </div>
  
          <div class="card mt-4">
            <div class="card-header">
              <h2>Select Receipts</h2>
              <button v-if="!isEditing" type="button" class="btn btn-primary" @click="searchReceipts">
                <i class="fas fa-search"></i> Find Uninvoiced Receipts
              </button>
            </div>
            
            <div v-if="loadingReceipts" class="loading-overlay">
              <i class="fas fa-spinner fa-spin"></i> Loading receipts...
            </div>
  
            <div v-else-if="availableReceipts.length === 0" class="empty-state">
              <i class="fas fa-file-invoice"></i>
              <h3>No uninvoiced receipts found</h3>
              <p>No uninvoiced receipts are available for this vendor.</p>
            </div>
  
            <div v-else class="card-body">
              <div class="receipts-list">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th style="width: 50px">
                        <input 
                          type="checkbox" 
                          :checked="allReceiptsSelected" 
                          @change="toggleAllReceipts" 
                        />
                      </th>
                      <th>Receipt #</th>
                      <th>Receipt Date</th>
                      <th>PO Numbers</th>
                      <th>Items</th>
                      <th>Uninvoiced Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="receipt in availableReceipts" :key="receipt.receipt_id">
                      <td>
                        <input 
                          type="checkbox" 
                          v-model="selectedReceipts"
                          :value="receipt.receipt_id" 
                        />
                      </td>
                      <td>{{ receipt.receipt_number }}</td>
                      <td>{{ formatDate(receipt.receipt_date) }}</td>
                      <td>{{ receipt.po_numbers }}</td>
                      <td>{{ receipt.uninvoiced_count }}</td>
                      <td>
                        <div v-for="(totals, currency) in receipt.totals_by_currency" :key="currency">
                          {{ formatCurrency(totals.total, currency) }}
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
  
              <div v-if="selectedReceipts.length > 0" class="receipt-details mt-4">
                <h3>Selected Receipt Details</h3>
                
                <div v-for="receipt in getSelectedReceipts()" :key="receipt.receipt_id" class="receipt-detail-card">
                  <div class="receipt-header">
                    <h4>{{ receipt.receipt_number }} ({{ formatDate(receipt.receipt_date) }})</h4>
                  </div>
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Item</th>
                        <th>PO Number</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Subtotal</th>
                        <th>Tax</th>
                        <th>Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="line in receipt.uninvoiced_lines" :key="line.line_id">
                        <td>{{ line.item_code }} - {{ line.item_name }}</td>
                        <td>{{ line.po_number || 'N/A' }}</td>
                        <td>{{ line.quantity }}</td>
                        <td>{{ formatCurrency(line.unit_price, line.currency_code) }}</td>
                        <td>{{ formatCurrency(line.subtotal, line.currency_code) }}</td>
                        <td>{{ formatCurrency(line.tax, line.currency_code) }}</td>
                        <td>{{ formatCurrency(line.total, line.currency_code) }}</td>
                      </tr>
                    </tbody>
                    <tfoot>
                      <tr>
                        <td colspan="4" class="text-right"><strong>Total:</strong></td>
                        <td>
                          <div v-for="(totals, currency) in receipt.totals_by_currency" :key="currency">
                            {{ formatCurrency(totals.subtotal, currency) }}
                          </div>
                        </td>
                        <td>
                          <div v-for="(totals, currency) in receipt.totals_by_currency" :key="currency">
                            {{ formatCurrency(totals.tax, currency) }}
                          </div>
                        </td>
                        <td>
                          <div v-for="(totals, currency) in receipt.totals_by_currency" :key="currency">
                            {{ formatCurrency(totals.total, currency) }}
                          </div>
                        </td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>
            </div>
          </div>
  
          <div class="card mt-4">
            <div class="card-header">
              <h2>Additional Options</h2>
            </div>
            <div class="card-body">
              <div class="form-group">
                <div class="checkbox-group">
                  <input type="checkbox" id="create_journal_entry" v-model="form.create_journal_entry" />
                  <label for="create_journal_entry">Create Journal Entry</label>
                </div>
              </div>
  
              <div v-if="form.create_journal_entry" class="journal-entry-options">
                <div class="form-row">
                  <div class="form-group">
                    <label for="ap_account_id">Accounts Payable Account</label>
                    <select id="ap_account_id" v-model="form.ap_account_id" required>
                      <option value="" disabled>Select account</option>
                      <option value="AP001">AP001 - Accounts Payable</option>
                      <option value="AP002">AP002 - International Payables</option>
                    </select>
                  </div>
  
                  <div class="form-group">
                    <label for="expense_account_id">Expense Account</label>
                    <select id="expense_account_id" v-model="form.expense_account_id" required>
                      <option value="" disabled>Select account</option>
                      <option value="EXP001">EXP001 - Purchase Expense</option>
                      <option value="EXP002">EXP002 - Inventory Expense</option>
                    </select>
                  </div>
  
                  <div class="form-group">
                    <label for="tax_account_id">Tax Account</label>
                    <select id="tax_account_id" v-model="form.tax_account_id" required>
                      <option value="" disabled>Select account</option>
                      <option value="TAX001">TAX001 - Input VAT</option>
                      <option value="TAX002">TAX002 - Sales Tax</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>
          </div>
  
          <div class="form-actions mt-4">
            <button type="button" class="btn btn-secondary" @click="$router.push('/purchasing/vendor-invoices')">
              Cancel
            </button>
            <button type="submit" class="btn btn-primary" :disabled="saving || selectedReceipts.length === 0">
              <i v-if="saving" class="fas fa-spinner fa-spin"></i>
              {{ isEditing ? 'Update Invoice' : 'Create Invoice' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'VendorInvoiceForm',
    data() {
      return {
        isEditing: false,
        loading: true,
        saving: false,
        loadingReceipts: false,
        vendors: [],
        availableReceipts: [],
        selectedVendorId: '',
        selectedReceipts: [],
        form: {
          invoice_number: '',
          invoice_date: new Date().toISOString().split('T')[0],
          due_date: '',
          receipt_ids: [],
          currency_code: 'USD',
          exchange_rate: 1,
          create_journal_entry: false,
          ap_account_id: 'AP001',
          expense_account_id: 'EXP001',
          tax_account_id: 'TAX001'
        },
        errors: {}
      };
    },
    computed: {
      allReceiptsSelected() {
        return this.availableReceipts.length > 0 && 
               this.selectedReceipts.length === this.availableReceipts.length;
      }
    },
    async created() {
  const invoiceId = this.$route.params.id;
  this.isEditing = !!invoiceId;
  
  try {
    // Load vendors and filter out null/undefined values
    const vendorsResponse = await axios.get('/vendors');
    console.log('Full vendors response:', vendorsResponse.data); // Debug log
    
    // Handle the nested response structure berdasarkan struktur API Anda
    let vendorsData = [];
    
    // Cek struktur response yang benar
    if (vendorsResponse.data && vendorsResponse.data.data) {
      if (Array.isArray(vendorsResponse.data.data.data)) {
        // Structure: { data: { data: [...], current_page: 1, ... } }
        vendorsData = vendorsResponse.data.data.data;
        console.log('Using nested structure: response.data.data.data');
      } else if (Array.isArray(vendorsResponse.data.data)) {
        // Structure: { data: [...] } 
        vendorsData = vendorsResponse.data.data;
        console.log('Using flat structure: response.data.data');
      }
    } else if (Array.isArray(vendorsResponse.data)) {
      // Direct array response: response.data
      vendorsData = vendorsResponse.data;
      console.log('Using direct array: response.data');
    }
    
    console.log('Extracted vendors data:', vendorsData); // Debug log
    console.log('Vendors data length:', vendorsData.length); // Debug log
    
    // Filter dan assign ke this.vendors
    this.vendors = vendorsData.filter(vendor => vendor != null && vendor.vendor_id);
    console.log('Filtered vendors:', this.vendors); // Debug log
    console.log('Final vendors count:', this.vendors.length); // Debug log
    
    if (this.isEditing) {
      // Load invoice data if editing
      const invoiceResponse = await axios.get(`/vendor-invoices/${invoiceId}`);
      const invoice = invoiceResponse.data.data.invoice;
      
      this.form = {
        invoice_number: invoice.invoice_number,
        invoice_date: invoice.invoice_date,
        due_date: invoice.due_date || '',
        receipt_ids: invoice.goodsReceipts.map(receipt => receipt.receipt_id),
        currency_code: invoice.currency_code || 'USD',
        exchange_rate: invoice.exchange_rate || 1,
        create_journal_entry: false, // Default for editing
        ap_account_id: 'AP001',
        expense_account_id: 'EXP001',
        tax_account_id: 'TAX001'
      };
      
      this.selectedVendorId = invoice.vendor_id;
      this.selectedReceipts = [...this.form.receipt_ids];
    }
  } catch (error) {
    console.error('Error loading initial data:', error);
    console.error('Error details:', error.response?.data); // Additional debug info
    // Set empty arrays as fallback
    this.vendors = [];
  } finally {
    this.loading = false;
  }
},
    methods: {
      async vendorChanged() {
        if (!this.selectedVendorId) return;
        
        // Clear previous receipts and selection
        this.availableReceipts = [];
        this.selectedReceipts = [];
        
        // Set due date based on vendor payment terms
        const vendor = this.vendors.find(v => v.vendor_id === this.selectedVendorId);
        if (vendor && vendor.payment_term) {
          const invoiceDate = new Date(this.form.invoice_date);
          const dueDate = new Date(invoiceDate);
          dueDate.setDate(dueDate.getDate() + (vendor.payment_term || 30));
          this.form.due_date = dueDate.toISOString().split('T')[0];
        }
        
        // Set preferred currency if available
        if (vendor && vendor.preferred_currency) {
          this.form.currency_code = vendor.preferred_currency;
          await this.fetchExchangeRate();
        }
        
        // Load uninvoiced receipts for this vendor
        await this.searchReceipts();
      },
      async currencyChanged() {
        await this.fetchExchangeRate();
      },
      async fetchExchangeRate() {
        if (this.form.currency_code === 'USD') {
          this.form.exchange_rate = 1;
          return;
        }
        
        try {
          const response = await axios.get('/currency-rates/current-rate', {
            params: {
              currency_code: this.form.currency_code,
              date: this.form.invoice_date
            }
          });
          
          if (response.data.status === 'success') {
            this.form.exchange_rate = response.data.data.rate;
          }
        } catch (error) {
          console.error('Error fetching exchange rate:', error);
        }
      },
      async searchReceipts() {
        if (!this.selectedVendorId) {
          alert('Please select a vendor first');
          return;
        }
        
        this.loadingReceipts = true;
        
        try {
          const response = await axios.get('/vendor-invoices/uninvoiced-receipts', {
            params: { vendor_id: this.selectedVendorId }
          });
          
          if (response.data.status === 'success') {
            this.availableReceipts = response.data.data;
          }
        } catch (error) {
          console.error('Error fetching uninvoiced receipts:', error);
        } finally {
          this.loadingReceipts = false;
        }
      },
      toggleAllReceipts() {
        if (this.allReceiptsSelected) {
          this.selectedReceipts = [];
        } else {
          this.selectedReceipts = this.availableReceipts.map(receipt => receipt.receipt_id);
        }
      },
      getSelectedReceipts() {
        return this.availableReceipts.filter(receipt => 
          this.selectedReceipts.includes(receipt.receipt_id)
        );
      },
      async saveInvoice() {
        if (this.selectedReceipts.length === 0) {
          alert('Please select at least one receipt');
          return;
        }
        
        this.saving = true;
        this.errors = {};
        
        // Update receipt_ids in form data
        this.form.receipt_ids = [...this.selectedReceipts];
        this.form.vendor_id = this.selectedVendorId;
        
        try {
          let response;
          const formData = { ...this.form };
          
          if (this.isEditing) {
            const invoiceId = this.$route.params.id;
            response = await axios.put(`/vendor-invoices/${invoiceId}`, formData);
          } else {
            response = await axios.post('/vendor-invoices', formData);
          }
          
          if (response.data.status === 'success') {
            // Redirect to invoice detail page
            this.$router.push(`/purchasing/vendor-invoices/${response.data.data.invoice_id}`);
          }
        } catch (error) {
          console.error('Error saving invoice:', error);
          
          if (error.response && error.response.data && error.response.data.errors) {
            this.errors = error.response.data.errors;
          } else {
            alert(error.response?.data?.message || 'Error saving invoice');
          }
        } finally {
          this.saving = false;
        }
      },
      formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString();
      },
      formatCurrency(amount, currency) {
        if (amount === null || amount === undefined) return 'N/A';
        
        return new Intl.NumberFormat('en-US', {
          style: 'currency',
          currency: currency || 'USD',
          minimumFractionDigits: 2
        }).format(amount);
      }
    }
  };
  </script>
  
  <style scoped>
  .vendor-invoice-form {
    padding: 1rem;
  }
  
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
  }
  
  .form-container {
    max-width: 1200px;
  }
  
  .form-header-card,
  .card {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 1.5rem;
    overflow: hidden;
  }
  
  .card-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  
  .card-header h2 {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
    color: var(--gray-800);
  }
  
  .card-body {
    padding: 1.5rem;
  }
  
  .form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
  }
  
  .form-group {
    flex: 1;
    min-width: 200px;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }
  
  .form-group label {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--gray-700);
  }
  
  .form-group input,
  .form-group select {
    padding: 0.625rem;
    border: 1px solid var(--gray-300);
    border-radius: 0.375rem;
    font-size: 0.875rem;
  }
  
  .form-group input:focus,
  .form-group select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
  }
  
  .form-group input:disabled,
  .form-group select:disabled {
    background-color: var(--gray-100);
    cursor: not-allowed;
  }
  
  .input-with-button {
    display: flex;
    gap: 0.5rem;
  }
  
  .input-with-button input {
    flex: 1;
  }
  
  .helper-text {
    font-size: 0.75rem;
    color: var(--gray-500);
  }
  
  .checkbox-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  
  .checkbox-group input[type="checkbox"] {
    width: 1rem;
    height: 1rem;
  }
  
  .journal-entry-options {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--gray-200);
  }
  
  .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
  }
  
  .btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
  
  .btn-primary {
    background-color: var(--primary-color);
    color: white;
    border: none;
  }
  
  .btn-primary:hover:not(:disabled) {
    background-color: var(--primary-dark);
  }
  
  .btn-secondary {
    background-color: var(--gray-200);
    color: var(--gray-700);
    border: none;
  }
  
  .btn-secondary:hover:not(:disabled) {
    background-color: var(--gray-300);
  }
  
  .btn-outline {
    background-color: white;
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
  }
  
  .btn-outline:hover {
    background-color: var(--gray-100);
  }
  
  .loading,
  .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 0;
    text-align: center;
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 1.5rem;
  }
  
  .loading i,
  .empty-state i {
    font-size: 2.5rem;
    color: var(--gray-300);
    margin-bottom: 1rem;
  }
  
  .empty-state h3 {
    font-size: 1.125rem;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
  }
  
  .empty-state p {
    color: var(--gray-500);
    max-width: 24rem;
  }
  
  .loading-overlay {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 2rem;
    background-color: rgba(255, 255, 255, 0.8);
  }
  
  .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
  }
  
  .mt-4 {
    margin-top: 1.5rem;
  }
  
  .data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
  }
  
  .data-table th {
    text-align: left;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-200);
    background-color: var(--gray-50);
    font-weight: 500;
    color: var(--gray-600);
  }
  
  .data-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-100);
    color: var(--gray-800);
  }
  
  .data-table tbody tr:hover {
    background-color: var(--gray-50);
  }
  
  .data-table tfoot td {
    border-top: 1px solid var(--gray-200);
    font-weight: 500;
  }
  
  .text-right {
    text-align: right;
  }
  
  .receipt-details {
    border-top: 1px solid var(--gray-200);
    padding-top: 1.5rem;
  }
  
  .receipt-details h3 {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: var(--gray-800);
  }
  
  .receipt-detail-card {
    margin-bottom: 1.5rem;
    border: 1px solid var(--gray-200);
    border-radius: 0.375rem;
    overflow: hidden;
  }
  
  .receipt-header {
    padding: 0.75rem 1rem;
    background-color: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
  }
  
  .receipt-header h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
  }
  
  .error-text {
    color: var(--danger-color);
    font-size: 0.75rem;
    margin-top: 0.25rem;
  }
  
  @media (max-width: 768px) {
    .page-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }
    
    .form-group {
      width: 100%;
    }
    
    .form-actions {
      flex-direction: column;
    }
    
    .btn {
      width: 100%;
    }
  }
  </style>