<!-- src/views/sales/SalesInvoiceForm.vue -->
<template>
    <div class="page-container">
      <div class="page-header">
        <h1>{{ isEditing ? 'Edit Invoice' : 'Create New Invoice' }}</h1>
        <div class="header-actions">
          <button type="button" class="btn btn-secondary" @click="goBack">
            <i class="fas fa-arrow-left"></i> Back
          </button>
        </div>
      </div>
      
      <!-- Loading Indicator -->
      <div v-if="loading" class="loading-indicator">
        <i class="fas fa-spinner fa-spin"></i> Loading...
      </div>
      
      <!-- Form Content -->
      <div v-else class="form-container">
        <form @submit.prevent="saveInvoice">
          <!-- Basic Information Card -->
          <div class="card">
            <div class="card-header">
              <h2>Basic Information</h2>
            </div>
            <div class="card-body">
              <div class="form-row">
                <div class="form-group">
                  <label for="invoiceNumber">Invoice Number <span class="required">*</span></label>
                  <input 
                    type="text" 
                    id="invoiceNumber" 
                    v-model="invoice.invoice_number"
                    :disabled="isEditing"
                    required
                    class="form-control"
                  >
                  <div v-if="errors.invoice_number" class="error-message">
                    {{ errors.invoice_number[0] }}
                  </div>
                </div>
                
                <div class="form-group">
                  <label for="invoiceDate">Invoice Date <span class="required">*</span></label>
                  <input 
                    type="date" 
                    id="invoiceDate" 
                    v-model="invoice.invoice_date"
                    required
                    class="form-control"
                  >
                  <div v-if="errors.invoice_date" class="error-message">
                    {{ errors.invoice_date[0] }}
                  </div>
                </div>
                
                <div class="form-group">
                  <label for="dueDate">Due Date <span class="required">*</span></label>
                  <input 
                    type="date" 
                    id="dueDate" 
                    v-model="invoice.due_date"
                    required
                    class="form-control"
                  >
                  <div v-if="errors.due_date" class="error-message">
                    {{ errors.due_date[0] }}
                  </div>
                </div>
              </div>
              
              <div class="form-row">
                <div class="form-group">
                  <label for="customer">Customer <span class="required">*</span></label>
                  <select 
                    id="customer" 
                    v-model="invoice.customer_id"
                    class="form-control"
                    required
                    :disabled="isEditing"
                    @change="handleCustomerChange"
                  >
                    <option value="">Select Customer</option>
                    <option v-for="customer in customers" :key="customer.customer_id" :value="customer.customer_id">
                      {{ customer.name }}
                    </option>
                  </select>
                  <div v-if="errors.customer_id" class="error-message">
                    {{ errors.customer_id[0] }}
                  </div>
                </div>
                
                <div class="form-group">
                  <label for="status">Status <span class="required">*</span></label>
                  <select 
                    id="status" 
                    v-model="invoice.status"
                    class="form-control"
                    required
                  >
                    <option value="Draft">Draft</option>
                    <option value="Sent">Sent</option>
                    <option value="Partially Paid">Partially Paid</option>
                    <option value="Paid">Paid</option>
                    <option value="Cancelled">Cancelled</option>
                  </select>
                  <div v-if="errors.status" class="error-message">
                    {{ errors.status[0] }}
                  </div>
                </div>
                
                <div class="form-group">
                  <label for="currency">Currency</label>
                  <select 
                    id="currency" 
                    v-model="invoice.currency_code"
                    class="form-control"
                    :disabled="isEditing"
                  >
                    <option value="IDR">IDR - Indonesian Rupiah</option>
                    <option value="USD">USD - US Dollar</option>
                    <option value="EUR">EUR - Euro</option>
                    <option value="SGD">SGD - Singapore Dollar</option>
                    <option value="MYR">MYR - Malaysian Ringgit</option>
                  </select>
                  <div v-if="errors.currency_code" class="error-message">
                    {{ errors.currency_code[0] }}
                  </div>
                </div>
              </div>
              
              <div class="form-row">
                <div class="form-group">
                  <label for="reference">Reference Number</label>
                  <input 
                    type="text" 
                    id="reference" 
                    v-model="invoice.reference"
                    class="form-control"
                    placeholder="Optional reference number"
                  >
                </div>
                
                <div class="form-group">
                  <label for="paymentTerms">Payment Terms</label>
                  <select 
                    id="paymentTerms" 
                    v-model="invoice.payment_terms"
                    class="form-control"
                  >
                    <option value="">Select Payment Terms</option>
                    <option value="Net 15">Net 15 (Due in 15 days)</option>
                    <option value="Net 30">Net 30 (Due in 30 days)</option>
                    <option value="Net 45">Net 45 (Due in 45 days)</option>
                    <option value="Net 60">Net 60 (Due in 60 days)</option>
                    <option value="Due on Receipt">Due on Receipt</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Delivery Order Selection (for creating from delivery) -->
          <div v-if="!isEditing" class="card mt-4">
            <div class="card-header">
              <h2>Delivery Order</h2>
            </div>
            <div class="card-body">
              <div class="form-row">
                <div class="form-group col-md-6">
                  <label>Create From Delivery</label>
                  <div class="form-check">
                    <input
                      type="checkbox"
                      id="createFromDelivery"
                      v-model="createFromDelivery"
                      class="form-check-input"
                      @change="toggleDeliveryMode"
                    >
                    <label for="createFromDelivery" class="form-check-label">
                      Create invoice from delivery order
                    </label>
                  </div>
                </div>
              </div>
              
              <div v-if="createFromDelivery">
                <div class="form-row">
                  <div class="form-group col-md-12">
                    <label>Select Delivery Orders <span class="required">*</span></label>
                    <div v-if="loadingDeliveries" class="loading-text">
                      <i class="fas fa-spinner fa-spin"></i> Loading deliveries...
                    </div>
                    <div v-else-if="deliveries.length === 0" class="empty-message">
                      No deliveries available for this customer.
                    </div>
                    <div v-else class="delivery-selection">
                      <div v-for="delivery in deliveries" :key="delivery.delivery_id" class="delivery-item">
                        <div class="form-check">
                          <input
                            type="checkbox"
                            :id="`delivery-${delivery.delivery_id}`"
                            v-model="selectedDeliveries"
                            :value="delivery.delivery_id"
                            class="form-check-input"
                            @change="handleDeliverySelection"
                          >
                          <label :for="`delivery-${delivery.delivery_id}`" class="form-check-label">
                            <strong>{{ delivery.delivery_number }}</strong> - {{ formatDate(delivery.delivery_date) }}
                          </label>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Invoice Items Card -->
          <div class="card mt-4">
            <div class="card-header">
              <h2>Invoice Items</h2>
              <button 
                type="button" 
                class="btn btn-sm btn-primary"
                @click="addItemRow"
                :disabled="createFromDelivery && !isEditing"
              >
                <i class="fas fa-plus"></i> Add Item
              </button>
            </div>
            <div class="card-body">
              <div v-if="isLoadingItems" class="loading-text">
                <i class="fas fa-spinner fa-spin"></i> Loading items...
              </div>
              <div v-else-if="invoice.lines.length === 0" class="empty-message">
                No items added to this invoice yet.
              </div>
              <div v-else class="table-responsive">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Item</th>
                      <th>Description</th>
                      <th>Quantity</th>
                      <th>Unit Price</th>
                      <th>Discount</th>
                      <th>Tax</th>
                      <th>Subtotal</th>
                      <th>Total</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(line, index) in invoice.lines" :key="index">
                      <td>
                        <select 
                          v-model="line.item_id"
                          class="form-control"
                          @change="updateLineItem(index)"
                          :disabled="line.do_line_id || createFromDelivery"
                        >
                          <option value="">Select Item</option>
                          <option v-for="item in items" :key="item.item_id" :value="item.item_id">
                            {{ item.item_code }} - {{ item.name }}
                          </option>
                        </select>
                      </td>
                      <td>
                        <input 
                          type="text" 
                          v-model="line.description" 
                          class="form-control"
                          placeholder="Item description"
                        >
                      </td>
                      <td>
                        <input 
                          type="number" 
                          v-model.number="line.quantity" 
                          class="form-control"
                          min="0.01" 
                          step="0.01"
                          @change="calculateLineTotals(index)"
                          :disabled="line.do_line_id || createFromDelivery"
                        >
                      </td>
                      <td>
                        <div class="input-group">
                          <div class="input-group-prepend">
                            <span class="input-group-text">{{ invoice.currency_code }}</span>
                          </div>
                          <input 
                            type="number" 
                            v-model.number="line.unit_price" 
                            class="form-control"
                            min="0" 
                            step="0.00001"
                            @change="calculateLineTotals(index)"
                          >
                        </div>
                      </td>
                      <td>
                        <div class="input-group">
                          <div class="input-group-prepend">
                            <span class="input-group-text">{{ invoice.currency_code }}</span>
                          </div>
                          <input 
                            type="number" 
                            v-model.number="line.discount" 
                            class="form-control"
                            min="0" 
                            step="0.00001"
                            @change="calculateLineTotals(index)"
                          >
                        </div>
                      </td>
                      <td>
                        <div class="input-group">
                          <div class="input-group-prepend">
                            <span class="input-group-text">{{ invoice.currency_code }}</span>
                          </div>
                          <input 
                            type="number" 
                            v-model.number="line.tax" 
                            class="form-control"
                            min="0" 
                            step="0.00001"
                            @change="calculateLineTotals(index)"
                          >
                        </div>
                      </td>
                      <td>
                        <div class="input-group">
                          <div class="input-group-prepend">
                            <span class="input-group-text">{{ invoice.currency_code }}</span>
                          </div>
                          <input 
                            type="number" 
                            v-model.number="line.subtotal" 
                            class="form-control"
                            readonly
                          >
                        </div>
                      </td>
                      <td>
                        <div class="input-group">
                          <div class="input-group-prepend">
                            <span class="input-group-text">{{ invoice.currency_code }}</span>
                          </div>
                          <input 
                            type="number" 
                            v-model.number="line.total" 
                            class="form-control"
                            readonly
                          >
                        </div>
                      </td>
                      <td>
                        <button 
                          type="button" 
                          class="btn btn-sm btn-danger"
                          @click="removeItemRow(index)"
                          :disabled="line.do_line_id || createFromDelivery"
                        >
                          <i class="fas fa-trash"></i>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr>
                      <td colspan="6" class="text-right"><strong>Subtotal:</strong></td>
                      <td colspan="3">
                        <div class="input-group">
                          <div class="input-group-prepend">
                            <span class="input-group-text">{{ invoice.currency_code }}</span>
                          </div>
                          <input type="number" v-model.number="subtotal" class="form-control" readonly>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td colspan="6" class="text-right"><strong>Total Discount:</strong></td>
                      <td colspan="3">
                        <div class="input-group">
                          <div class="input-group-prepend">
                            <span class="input-group-text">{{ invoice.currency_code }}</span>
                          </div>
                          <input type="number" v-model.number="totalDiscount" class="form-control" readonly>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td colspan="6" class="text-right"><strong>Total Tax:</strong></td>
                      <td colspan="3">
                        <div class="input-group">
                          <div class="input-group-prepend">
                            <span class="input-group-text">{{ invoice.currency_code }}</span>
                          </div>
                          <input type="number" v-model.number="invoice.tax_amount" class="form-control" readonly>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td colspan="6" class="text-right"><strong>Grand Total:</strong></td>
                      <td colspan="3">
                        <div class="input-group">
                          <div class="input-group-prepend">
                            <span class="input-group-text">{{ invoice.currency_code }}</span>
                          </div>
                          <input type="number" v-model.number="invoice.total_amount" class="form-control" readonly>
                        </div>
                      </td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
          </div>
          
          <!-- Form Actions -->
          <div class="form-actions mt-4">
            <button type="button" class="btn btn-secondary" @click="goBack">
              Cancel
            </button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              <i class="fas fa-spinner fa-spin" v-if="saving"></i>
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
    name: 'SalesInvoiceForm',
    props: {
      id: {
        type: [Number, String],
        required: false
      }
    },
    data() {
      return {
        isEditing: false,
        loading: true,
        saving: false,
        customers: [],
        items: [],
        deliveries: [],
        selectedDeliveries: [],
        createFromDelivery: false,
        loadingDeliveries: false,
        isLoadingItems: false,
        invoice: {
          invoice_number: '',
          invoice_date: new Date().toISOString().split('T')[0],
          customer_id: '',
          due_date: '',
          status: 'Draft',
          currency_code: 'IDR',
          reference: '',
          payment_terms: '',
          total_amount: 0,
          tax_amount: 0,
          lines: []
        },
        subtotal: 0,
        totalDiscount: 0,
        errors: {}
      };
    },
    computed: {
      defaultDueDate() {
        const today = new Date();
        // Default to 30 days from now
        today.setDate(today.getDate() + 30);
        return today.toISOString().split('T')[0];
      }
    },
    async mounted() {
      this.isEditing = !!this.id;
      await this.loadCustomers();
      await this.loadItems();
      
      if (this.isEditing) {
        await this.loadInvoice();
      } else {
        // Set default due date
        this.invoice.due_date = this.defaultDueDate;
        // Generate invoice number
        this.generateInvoiceNumber();
      }
      
      this.loading = false;
    },
    methods: {
      async loadCustomers() {
        try {
          const response = await axios.get('/customers');
          this.customers = response.data.data || response.data;
        } catch (error) {
          console.error('Error loading customers:', error);
          this.$toast.error('Failed to load customers');
        }
      },
      
      async loadItems() {
        try {
          const response = await axios.get('/items', {
            params: { sellable: true }
          });
          this.items = response.data.data || response.data;
        } catch (error) {
          console.error('Error loading items:', error);
          this.$toast.error('Failed to load items');
        }
      },
      
      async loadInvoice() {
        try {
          const response = await axios.get(`/invoices/${this.id}`);
          const invoice = response.data.data;
          
          // Format dates
          invoice.invoice_date = invoice.invoice_date ? invoice.invoice_date.split('T')[0] : '';
          invoice.due_date = invoice.due_date ? invoice.due_date.split('T')[0] : '';
          
          // Process lines to ensure they have the necessary properties
          if (invoice.salesInvoiceLines) {
            invoice.lines = invoice.salesInvoiceLines.map(line => ({
              line_id: line.line_id,
              item_id: line.item_id,
              description: line.item ? line.item.name : '',
              quantity: line.quantity,
              unit_price: line.unit_price,
              discount: line.discount || 0,
              tax: line.tax || 0,
              subtotal: line.subtotal,
              total: line.total,
              uom_id: line.uom_id,
              do_line_id: line.do_line_id
            }));
            delete invoice.salesInvoiceLines;
          }
          
          this.invoice = invoice;
          this.calculateTotals();
        } catch (error) {
          console.error('Error loading invoice:', error);
          this.$toast.error('Failed to load invoice');
        }
      },
      
      generateInvoiceNumber() {
        // Generate invoice number based on current date
        const date = new Date();
        const year = date.getFullYear().toString().substr(-2);
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const day = date.getDate().toString().padStart(2, '0');
        const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
        
        this.invoice.invoice_number = `INV-${year}${month}${day}-${random}`;
      },
      
      async handleCustomerChange() {
        if (this.invoice.customer_id && !this.isEditing) {
          // Find selected customer
          const selectedCustomer = this.customers.find(c => c.customer_id === this.invoice.customer_id);
          
if (selectedCustomer) {
  // Set preferred currency if available
  if (selectedCustomer.preferred_currency) {
    this.invoice.currency_code = selectedCustomer.preferred_currency;
  }
  
  // Set payment terms if available
  if (selectedCustomer.payment_term) {
    // Convert payment_term to string to avoid includes error
    this.invoice.payment_terms = String(selectedCustomer.payment_term);
    
    // Adjust due date based on payment terms
    const today = new Date();
    let daysToAdd = 30; // Default
    
    if ((this.invoice.payment_terms || '').includes('15')) {
      daysToAdd = 15;
    } else if ((this.invoice.payment_terms || '').includes('30')) {
      daysToAdd = 30;
    } else if ((this.invoice.payment_terms || '').includes('45')) {
      daysToAdd = 45;
    } else if ((this.invoice.payment_terms || '').includes('60')) {
      daysToAdd = 60;
    } else if ((this.invoice.payment_terms || '').includes('Receipt')) {
      daysToAdd = 0;
    }
    
    today.setDate(today.getDate() + daysToAdd);
    this.invoice.due_date = today.toISOString().split('T')[0];
  }
}
        }
        
        // Reset deliveries and selected deliveries if customer changes
        this.deliveries = [];
        this.selectedDeliveries = [];
        
        // If creating from delivery is enabled, load deliveries for the customer
        if (this.createFromDelivery && this.invoice.customer_id) {
          this.loadDeliveries();
        }
      },
      
async loadDeliveries() {
  if (!this.invoice.customer_id) return;

  console.log('loadDeliveries called with customer_id:', this.invoice.customer_id);

  this.loadingDeliveries = true;
  this.deliveries = [];
  this.selectedDeliveries = [];

  try {
    // Correct API endpoint to get deliveries for invoicing for the customer
    const response = await axios.get('/invoices/getDeliveriesForInvoicing', {
      params: {
        customer_id: this.invoice.customer_id
      }
    });

    console.log('loadDeliveries response:', response);

    if (!response.data || !response.data.data) {
      console.warn('loadDeliveries: response.data or response.data.data is missing', response);
    }

    this.deliveries = response.data.data || [];
  } catch (error) {
    console.error('Error loading deliveries:', error);
    if (error && error.response && error.response.data && error.response.data.message) {
      this.$toast.error(error.response.data.message);
    } else if (error && error.message) {
      this.$toast.error(error.message);
    } else {
      this.$toast.error('Failed to load deliveries');
    }
  } finally {
    this.loadingDeliveries = false;
  }
},
      
async handleDeliverySelection() {
  if (this.selectedDeliveries.length > 0) {
    this.isLoadingItems = true;
    
    try {
      // API endpoint to get delivery lines grouped by item
      const response = await axios.get('/invoices/getDeliveryLinesByItem', {
        params: { 
          customer_id: this.invoice.customer_id,
          delivery_ids: this.selectedDeliveries
        }
      });
      
      // Defensive check and logging for debugging
      if (!response.data || !response.data.data) {
        console.warn('handleDeliverySelection: response.data or response.data.data is missing', response);
      }
      
      const items = response.data.data || [];
      
      // Clear existing lines and add new ones from the deliveries
      this.invoice.lines = items.map(item => ({
        item_id: item.item_id,
        description: item.item_name,
        quantity: item.total_quantity,
        unit_price: item.unit_price,
        discount: 0,
        tax: 0,
        uom_id: item.uom_id,
        do_line_id: (item.delivery_lines && item.delivery_lines.length > 0) ? item.delivery_lines[0].do_line_id : null, // Reference to first delivery line safely
        subtotal: 0, // Will be calculated
        total: 0 // Will be calculated
      }));
      
      // Calculate totals for each line
      this.invoice.lines.forEach((line, index) => {
        this.calculateLineTotals(index);
      });
      
      this.calculateTotals();
    } catch (error) {
      console.error('Error loading delivery items:', error);
      this.$toast.error('Failed to load delivery items');
    } finally {
      this.isLoadingItems = false;
    }
  } else {
    // Clear lines if no deliveries selected
    this.invoice.lines = [];
    this.calculateTotals();
  }
},
      
      toggleDeliveryMode() {
        if (this.createFromDelivery) {
          // Cleared lines when switching to delivery mode
          this.invoice.lines = [];
          this.calculateTotals();
          
          // Load deliveries if customer is selected
          if (this.invoice.customer_id) {
            this.loadDeliveries();
          }
        } else {
          // Clear selected deliveries when switching off delivery mode
          this.selectedDeliveries = [];
        }
      },
      
      addItemRow() {
        this.invoice.lines.push({
          item_id: '',
          description: '',
          quantity: 1,
          unit_price: 0,
          discount: 0,
          tax: 0,
          subtotal: 0,
          total: 0
        });
      },
      
      removeItemRow(index) {
        this.invoice.lines.splice(index, 1);
        this.calculateTotals();
      },
      
      updateLineItem(index) {
        const line = this.invoice.lines[index];
        const selectedItem = this.items.find(item => item.item_id === line.item_id);
        
        if (selectedItem) {
          line.description = selectedItem.name;
          line.unit_price = selectedItem.sale_price || 0;
          line.uom_id = selectedItem.uom_id;
          
          this.calculateLineTotals(index);
        }
      },
      
      calculateLineTotals(index) {
        const line = this.invoice.lines[index];
        
        if (!line) return;

        // Convert unit_price, discount, and tax to numbers with 5 decimals
        line.unit_price = Number(parseFloat(line.unit_price).toFixed(5));
        line.discount = Number(parseFloat(line.discount || 0).toFixed(5));
        line.tax = Number(parseFloat(line.tax || 0).toFixed(5));
        
        line.subtotal = Number((line.quantity * line.unit_price).toFixed(5));
        // Ensure subtotal, discount, and tax are numbers before calculation
        const subtotalNum = Number(line.subtotal) || 0;
        const discountNum = Number(line.discount) || 0;
        const taxNum = Number(line.tax) || 0;
        line.total = Number((subtotalNum - discountNum + taxNum).toFixed(5));
        
        // Recalculate overall totals
        this.calculateTotals();
      },
      
      calculateTotals() {
        this.subtotal = 0;
        this.totalDiscount = 0;
        this.invoice.tax_amount = 0;
        this.invoice.total_amount = 0;
        
        this.invoice.lines.forEach(line => {
          this.subtotal += parseFloat(line.subtotal || 0);
          this.totalDiscount += parseFloat(line.discount || 0);
          this.invoice.tax_amount += parseFloat(line.tax || 0);
        });
        
        this.invoice.total_amount = parseFloat((this.subtotal - this.totalDiscount + this.invoice.tax_amount).toFixed(5));
      },
      
      async saveInvoice() {
        if (this.invoice.lines.length === 0) {
          this.$toast.error('Please add at least one item to the invoice');
          return;
        }
        
        this.saving = true;
        this.errors = {};
        
        try {
          let response;
          const payload = this.preparePayload();
          
          if (this.isEditing) {
            response = await axios.put(`/invoices/${this.id}`, payload);
            this.$toast.success('Invoice updated successfully');
          } else {
            if (this.createFromDelivery && this.selectedDeliveries.length > 0) {
              // Create invoice from deliveries
              const deliveryPayload = {
                invoice_number: this.invoice.invoice_number,
                invoice_date: this.invoice.invoice_date,
                due_date: this.invoice.due_date,
                customer_id: this.invoice.customer_id,
                status: this.invoice.status,
                currency_code: this.invoice.currency_code,
                reference: this.invoice.reference,
                payment_terms: this.invoice.payment_terms,
                delivery_ids: this.selectedDeliveries
              };
              
              response = await axios.post('/invoices/from-deliveries', deliveryPayload);
            } else {
              response = await axios.post('/invoices', payload);
            }
            
            this.$toast.success('Invoice created successfully');
          }
          
          // Navigate to the detail page
          const invoiceId = response.data.data.invoice_id;
          this.$router.push(`/sales/invoices/${invoiceId}`);
        } catch (error) {
          console.error('Error saving invoice:', error);
          
          if (error.response?.data?.errors) {
            this.errors = error.response.data.errors;
            this.$toast.error('Please correct the errors in the form');
          } else if (error.response?.data?.message) {
            this.$toast.error(error.response.data.message);
          } else {
            this.$toast.error('Failed to save invoice. Please try again.');
          }
        } finally {
          this.saving = false;
        }
      },
      
      preparePayload() {
        // Prepare lines data for API
        const lines = this.invoice.lines.map(line => ({
          item_id: line.item_id,
          quantity: line.quantity,
          unit_price: line.unit_price,
          discount: line.discount || 0,
          tax: line.tax || 0,
          uom_id: line.uom_id,
          do_line_id: line.do_line_id
        }));
        
        return {
          invoice_number: this.invoice.invoice_number,
          invoice_date: this.invoice.invoice_date,
          due_date: this.invoice.due_date,
          customer_id: this.invoice.customer_id,
          status: this.invoice.status,
          currency_code: this.invoice.currency_code,
          reference: this.invoice.reference,
          payment_terms: this.invoice.payment_terms,
          do_id: this.invoice.do_id,
          lines: lines
        };
      },
      
      formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', {
          year: 'numeric',
          month: 'short',
          day: 'numeric'
        });
      },
      
      goBack() {
        this.$router.go(-1);
      }
    }
  };
  </script>
  
  <style scoped>
  .page-container {
    padding: 1.5rem;
  }
  
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
  }
  
  .page-header h1 {
    margin: 0;
  }
  
  .loading-indicator {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 3rem 0;
    color: var(--gray-500);
    font-size: 0.875rem;
  }
  
  .form-container {
    max-width: 100%;
  }
  
  .card {
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
    margin: -0.5rem;
    margin-bottom: 1rem;
  }
  
  .form-group {
    flex: 1;
    min-width: 0;
    padding: 0.5rem;
  }
  
  .form-control {
    display: block;
    width: 100%;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    line-height: 1.5;
    color: var(--gray-800);
    background-color: white;
    border: 1px solid var(--gray-300);
    border-radius: 0.375rem;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
  }
  
  .form-control:focus {
    border-color: var(--primary-color);
    outline: 0;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
  }
  
  .form-check {
    display: flex;
    align-items: center;
    margin-bottom: 0.5rem;
  }
  
  .form-check-input {
    margin-right: 0.5rem;
  }
  
  .form-check-label {
    margin-bottom: 0;
  }
  
  .required {
    color: #dc2626;
  }
  
  .error-message {
    color: #dc2626;
    font-size: 0.75rem;
    margin-top: 0.25rem;
  }
  
  .mt-4 {
    margin-top: 1.5rem;
  }
  
  .table-responsive {
    overflow-x: auto;
  }
  
  .data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
  }
  
  .data-table th {
    text-align: left;
    padding: 0.75rem;
    border-bottom: 1px solid var(--gray-200);
    background-color: var(--gray-50);
    font-weight: 500;
    color: var(--gray-600);
  }
  
  .data-table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
  }
  
  .data-table tfoot tr td {
    padding: 0.75rem;
    background-color: var(--gray-50);
  }
  
  .text-right {
    text-align: right;
  }
  
  .input-group {
    display: flex;
    width: 100%;
  }
  
  .input-group-prepend {
    display: flex;
  }
  
  .input-group-text {
    display: flex;
    align-items: center;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    font-weight: 400;
    line-height: 1.5;
    color: var(--gray-700);
    text-align: center;
    white-space: nowrap;
    background-color: var(--gray-100);
    border: 1px solid var(--gray-300);
    border-top-left-radius: 0.375rem;
    border-bottom-left-radius: 0.375rem;
    border-right: none;
  }
  
  .input-group .form-control {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
    flex: 1;
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
    justify-content: center;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1.5;
    text-align: center;
    white-space: nowrap;
    vertical-align: middle;
    cursor: pointer;
    user-select: none;
    border: 1px solid transparent;
    border-radius: 0.375rem;
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
  }
  
  .btn-primary {
    color: white;
    background-color: var(--primary-color);
    border-color: var(--primary-color);
  }
  
  .btn-primary:hover {
    background-color: var(--primary-dark);
    border-color: var(--primary-dark);
  }
  
  .btn-primary:disabled {
    opacity: 0.65;
    cursor: not-allowed;
  }
  
  .btn-secondary {
    color: var(--gray-700);
    background-color: var(--gray-100);
    border-color: var(--gray-300);
  }
  
  .btn-secondary:hover {
    background-color: var(--gray-200);
    border-color: var(--gray-400);
  }
  
  .btn-danger {
    color: white;
    background-color: #dc2626;
    border-color: #dc2626;
  }
  
  .btn-danger:hover {
    background-color: #b91c1c;
    border-color: #b91c1c;
  }
  
  .btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    border-radius: 0.25rem;
  }
  
  .btn i {
    margin-right: 0.5rem;
  }
  
  .btn-sm i {
    margin-right: 0.25rem;
  }
  
  .loading-text {
    display: flex;
    align-items: center;
    color: var(--gray-500);
    font-size: 0.875rem;
    padding: 0.5rem 0;
  }
  
  .loading-text i {
    margin-right: 0.5rem;
  }
  
  .empty-message {
    color: var(--gray-500);
    font-style: italic;
    padding: 1rem 0;
  }
  
  .delivery-selection {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid var(--gray-200);
    border-radius: 0.375rem;
    padding: 0.5rem;
  }
  
  .delivery-item {
    padding: 0.5rem;
    border-bottom: 1px solid var(--gray-100);
  }
  
  .delivery-item:last-child {
    border-bottom: none;
  }
  
  @media (max-width: 768px) {
    .form-row {
      flex-direction: column;
    }
    
    .form-group {
      flex: none;
      width: 100%;
    }
  }
  </style>