<!-- src/views/sales/SalesInvoiceForm.vue -->
<template>
    <div class="page-container">
      <div class="page-header">
        <h1>{{ isEditing ? 'Edit Invoice' : 'Create New Invoice' }}</h1>
        <div class="header-actions">
          <!-- <button type="button" class="btn btn-secondary" @click="goBack">
            <i class="fas fa-arrow-left"></i> Back
          </button> -->
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
                  <div v-if="errors.invoice_number?.length" class="error-message">
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
                  <div v-if="errors.invoice_date?.length" class="error-message">
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
                  <div v-if="errors.due_date?.length" class="error-message">
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
                  <div v-if="errors.customer_id?.length" class="error-message">
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
                  <div v-if="errors.status?.length" class="error-message">
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
                  <div v-if="errors.currency_code?.length" class="error-message">
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
                    <tr v-for="(line, index) in invoice.lines" :key="`line-${line.line_id || index}`">
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
  
  // Add error capture
  errorCaptured(err, vm, info) {
    console.error('Vue Error Captured:', {
      error: err,
      component: vm,
      info: info,
      stack: err.stack
    });
    
    this.showMessage('An error occurred. Please check the console for details.', 'error');
    return false;
  },

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
      try {
        const today = new Date();
        today.setDate(today.getDate() + 30);
        return today.toISOString().split('T')[0];
      } catch (error) {
        console.error('Error calculating default due date:', error);
        return new Date().toISOString().split('T')[0];
      }
    }
  },

  async mounted() {
    try {
      console.log('SalesInvoiceForm mounted with id:', this.id);
      this.isEditing = !!this.id;
      
      await this.loadCustomers();
      await this.loadItems();

      if (this.isEditing) {
        await this.loadInvoice();
      } else {
        this.invoice.due_date = this.defaultDueDate;
        this.generateInvoiceNumber();
      }

      this.loading = false;
    } catch (error) {
      console.error('Error in mounted hook:', error);
      this.showMessage('Failed to initialize form: ' + error.message, 'error');
      this.loading = false;
    }
  },

  methods: {
    // Safe message display with fallbacks
    showMessage(message, type = 'info') {
      try {
        // Try multiple toast implementations
        if (this.$toast && typeof this.$toast.success === 'function') {
          // Vue Toast Next or similar
          switch(type) {
            case 'success':
              this.$toast.success(message);
              break;
            case 'error':
              this.$toast.error(message);
              break;
            case 'warning':
              this.$toast.warning(message);
              break;
            default:
              this.$toast.info(message);
          }
        } else if (this.$notify && typeof this.$notify === 'function') {
          // Vue Notification
          this.$notify({
            type: type,
            title: type.charAt(0).toUpperCase() + type.slice(1),
            text: message
          });
        } else if (this.$message && typeof this.$message === 'function') {
          // Element UI or similar
          this.$message({
            message: message,
            type: type === 'error' ? 'error' : 'success'
          });
        } else {
          // Fallback to console and alert
          console.log(`${type.toUpperCase()}: ${message}`);
          
          if (type === 'error') {
            alert('Error: ' + message);
          } else if (type === 'success') {
            // Only show success in console for less interruption
            console.log('Success: ' + message);
          }
        }
      } catch (error) {
        console.error('Error showing message:', error);
        console.log(`${type.toUpperCase()}: ${message}`);
        
        if (type === 'error') {
          alert('Error: ' + message);
        }
      }
    },

    async loadCustomers() {
      try {
        console.log('Loading customers...');
        const response = await axios.get('/customers');
        this.customers = response.data.data || response.data || [];
        console.log('Customers loaded:', this.customers.length);
      } catch (error) {
        console.error('Error loading customers:', error);
        this.showMessage('Failed to load customers: ' + (error.response?.data?.message || error.message), 'error');
        this.customers = [];
      }
    },

    async loadItems() {
      try {
        console.log('Loading items...');
        const response = await axios.get('/items', {
          params: { sellable: true }
        });
        this.items = response.data.data || response.data || [];
        console.log('Items loaded:', this.items.length);
      } catch (error) {
        console.error('Error loading items:', error);
        this.showMessage('Failed to load items: ' + (error.response?.data?.message || error.message), 'error');
        this.items = [];
      }
    },

    async loadInvoice() {
      try {
        console.log('Loading invoice with ID:', this.id);
        
        if (!this.id) {
          throw new Error('No invoice ID provided');
        }

        const response = await axios.get(`/invoices/${this.id}`);
        const invoice = response?.data?.data;

        if (!invoice) {
          throw new Error('Invoice data not found in response');
        }

        console.log('Raw invoice data:', invoice);

        // Safely format dates
        const invoiceDate = this.formatDate(invoice.invoice_date);
        const dueDate = this.formatDate(invoice.due_date);

        // Set invoice data with validation
        this.invoice = {
          invoice_id: invoice.invoice_id,
          invoice_number: invoice.invoice_number || '',
          invoice_date: invoiceDate,
          due_date: dueDate,
          customer_id: invoice.customer_id || '',
          status: invoice.status || 'Draft',
          currency_code: invoice.currency_code || 'IDR',
          reference: invoice.reference_number || invoice.reference || '',
          payment_terms: invoice.payment_terms || 
                        (invoice.customer?.payment_term ? `Net ${invoice.customer.payment_term}` : '') || '',
          total_amount: this.safeParseFloat(invoice.total_amount),
          tax_amount: this.safeParseFloat(invoice.tax_amount),
          do_id: invoice.do_id || null,
          lines: []
        };

        // Process invoice lines safely with UOM fix
        await this.processInvoiceLines(invoice);

        // Calculate totals
        this.calculateTotals();

        console.log('Invoice loaded successfully:', {
          invoice_id: this.invoice.invoice_id,
          lines_count: this.invoice.lines.length,
          total_amount: this.invoice.total_amount
        });

      } catch (error) {
        console.error('Error loading invoice:', error);
        this.showMessage('Failed to load invoice: ' + (error.response?.data?.message || error.message), 'error');
        
        if (error.response?.data?.errors) {
          this.errors = error.response.data.errors;
        }
      }
    },

    async processInvoiceLines(invoice) {
      try {
        // Try different possible field names for invoice lines
        let invoiceLines = [];
        
        const possibleFields = [
          'sales_invoice_lines',
          'salesInvoiceLines', 
          'lines',
          'invoice_lines'
        ];

        for (const field of possibleFields) {
          if (invoice[field] && Array.isArray(invoice[field])) {
            invoiceLines = invoice[field];
            console.log(`Found invoice lines in field '${field}':`, invoiceLines.length);
            break;
          }
        }

        if (invoiceLines.length > 0) {
          // Process each line and fix missing UOM
          this.invoice.lines = await Promise.all(invoiceLines.map(async (line, index) => {
            console.log(`Processing line ${index + 1}:`, line);
            
            let uomId = line.uom_id;
            
            // If UOM is missing, try to get it from the item
            if (!uomId && line.item_id) {
              console.log(`Line ${index + 1}: UOM missing, trying to get from item ${line.item_id}`);
              uomId = await this.getItemUOM(line.item_id);
            }
            
            const processedLine = {
              line_id: line.line_id || null,
              item_id: line.item_id || null,
              description: line.description || (line.item?.name) || '',
              quantity: this.safeParseFloat(line.quantity),
              unit_price: this.safeParseFloat(line.unit_price),
              discount: this.safeParseFloat(line.discount),
              tax: this.safeParseFloat(line.tax),
              subtotal: this.safeParseFloat(line.subtotal),
              total: this.safeParseFloat(line.total),
              uom_id: uomId, // Use the fixed UOM ID
              do_line_id: line.do_line_id || null,
              so_line_id: line.so_line_id || null
            };

            // Log result for debugging
            if (!processedLine.uom_id) {
              console.error(`❌ Line ${index + 1} still missing UOM after processing:`, processedLine);
            } else {
              console.log(`✅ Line ${index + 1} UOM fixed:`, processedLine.uom_id);
            }
            
            return processedLine;
          }));
          
          console.log('Successfully processed invoice lines:', this.invoice.lines.length);
        } else {
          console.log('No invoice lines found');
          this.invoice.lines = [];
        }

      } catch (error) {
        console.error('Error processing invoice lines:', error);
        this.invoice.lines = [];
        throw error;
      }
    },

    // New method to get UOM from item
    async getItemUOM(itemId) {
      try {
        console.log(`Getting UOM for item ${itemId}...`);
        
        // First, check if item is already in our items array
        const existingItem = this.items.find(item => item.item_id === itemId);
        if (existingItem && existingItem.uom_id) {
          console.log(`Found UOM in existing items: ${existingItem.uom_id}`);
          return existingItem.uom_id;
        }

        // If not found or no UOM, fetch from API
        const response = await axios.get(`/items/${itemId}`);
        const item = response.data.data || response.data;
        
        if (item && item.uom_id) {
          console.log(`Fetched UOM from API: ${item.uom_id}`);
          return item.uom_id;
        }

        // If still no UOM, try to get default UOM for the item
        if (item && (item.default_uom_id || item.base_uom_id)) {
          const defaultUOM = item.default_uom_id || item.base_uom_id;
          console.log(`Using default UOM: ${defaultUOM}`);
          return defaultUOM;
        }

        // Last resort: use a system default UOM (you may need to adjust this)
        console.warn(`No UOM found for item ${itemId}, using default UOM ID 1`);
        return 1; // Change this to your system's default UOM ID

      } catch (error) {
        console.error(`Error getting UOM for item ${itemId}:`, error);
        return 1; // Fallback to default UOM ID
      }
    },

    formatDate(dateString) {
      try {
        if (!dateString) return '';
        return dateString.split('T')[0];
      } catch (error) {
        console.error('Error formatting date:', dateString, error);
        return '';
      }
    },

    safeParseFloat(value) {
      try {
        const parsed = parseFloat(value || 0);
        return isNaN(parsed) ? 0 : parsed;
      } catch (error) {
        console.error('Error parsing float:', value, error);
        return 0;
      }
    },

    generateInvoiceNumber() {
      try {
        const date = new Date();
        const year = date.getFullYear().toString().substr(-2);
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const day = date.getDate().toString().padStart(2, '0');
        const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
        
        this.invoice.invoice_number = `INV-${year}${month}${day}-${random}`;
        console.log('Generated invoice number:', this.invoice.invoice_number);
      } catch (error) {
        console.error('Error generating invoice number:', error);
        this.invoice.invoice_number = 'INV-ERROR';
      }
    },

    async handleCustomerChange() {
      try {
        console.log('Customer changed to:', this.invoice.customer_id);
        
        if (this.invoice.customer_id && !this.isEditing) {
          const selectedCustomer = this.customers.find(c => c.customer_id === this.invoice.customer_id);

          if (selectedCustomer) {
            console.log('Selected customer:', selectedCustomer);
            
            if (selectedCustomer.preferred_currency) {
              this.invoice.currency_code = selectedCustomer.preferred_currency;
            }

            if (selectedCustomer.payment_term) {
              const paymentTermDays = selectedCustomer.payment_term;
              this.invoice.payment_terms = `Net ${paymentTermDays}`;

              const today = new Date(this.invoice.invoice_date || new Date());
              today.setDate(today.getDate() + paymentTermDays);
              this.invoice.due_date = today.toISOString().split('T')[0];
            }
          }
        }

        // Reset deliveries
        this.deliveries = [];
        this.selectedDeliveries = [];

        if (this.createFromDelivery && this.invoice.customer_id) {
          await this.loadDeliveries();
        }

      } catch (error) {
        console.error('Error handling customer change:', error);
        this.showMessage('Error updating customer information: ' + error.message, 'error');
      }
    },

    async loadDeliveries() {
      try {
        if (!this.invoice.customer_id) return;

        console.log('Loading deliveries for customer:', this.invoice.customer_id);
        this.loadingDeliveries = true;
        this.deliveries = [];
        this.selectedDeliveries = [];

        const response = await axios.get('/invoices/getDeliveriesForInvoicing', {
          params: { customer_id: this.invoice.customer_id }
        });

        if (response?.data?.data && Array.isArray(response.data.data)) {
          this.deliveries = response.data.data;
          console.log('Deliveries loaded:', this.deliveries.length);
        } else {
          console.warn('Invalid deliveries response:', response);
          this.deliveries = [];
        }

      } catch (error) {
        console.error('Error loading deliveries:', error);
        this.showMessage('Failed to load deliveries: ' + (error.response?.data?.message || error.message), 'error');
        this.deliveries = [];
      } finally {
        this.loadingDeliveries = false;
      }
    },

    async handleDeliverySelection() {
      try {
        console.log('Delivery selection changed:', this.selectedDeliveries);
        
        if (this.selectedDeliveries.length > 0) {
          this.isLoadingItems = true;

          const response = await axios.get('/invoices/getDeliveryLinesByItem', {
            params: {
              customer_id: this.invoice.customer_id,
              delivery_ids: this.selectedDeliveries
            }
          });

          let items = [];
          if (response?.data?.data && Array.isArray(response.data.data)) {
            items = response.data.data;
          }

          // Process delivery items with UOM fix
          this.invoice.lines = await Promise.all(items.map(async (item) => {
            let uomId = item.uom_id;
            
            // If UOM is missing, try to get it from the item
            if (!uomId && item.item_id) {
              uomId = await this.getItemUOM(item.item_id);
            }

            return {
              item_id: item.item_id || null,
              description: item.item_name || '',
              quantity: item.total_quantity || 0,
              unit_price: item.unit_price || 0,
              discount: 0,
              tax: 0,
              uom_id: uomId,
              do_line_id: (item.delivery_lines?.[0]?.do_line_id) || null,
              subtotal: 0,
              total: 0
            };
          }));

          this.invoice.lines.forEach((line, index) => {
            this.calculateLineTotals(index);
          });

          this.calculateTotals();

        } else {
          this.invoice.lines = [];
          this.calculateTotals();
        }

      } catch (error) {
        console.error('Error handling delivery selection:', error);
        this.showMessage('Error loading delivery items: ' + (error.response?.data?.message || error.message), 'error');
      } finally {
        this.isLoadingItems = false;
      }
    },

    toggleDeliveryMode() {
      try {
        console.log('Toggle delivery mode:', this.createFromDelivery);
        
        if (this.createFromDelivery) {
          this.invoice.lines = [];
          this.calculateTotals();

          if (this.invoice.customer_id) {
            this.loadDeliveries();
          }
        } else {
          this.selectedDeliveries = [];
        }
      } catch (error) {
        console.error('Error toggling delivery mode:', error);
        this.showMessage('Error switching delivery mode: ' + error.message, 'error');
      }
    },

    addItemRow() {
      try {
        console.log('Adding new item row');
        
        const newLine = {
          line_id: null,
          item_id: '',
          description: '',
          quantity: 1,
          unit_price: 0,
          discount: 0,
          tax: 0,
          subtotal: 0,
          total: 0,
          uom_id: null,
          do_line_id: null,
          so_line_id: null
        };

        this.invoice.lines.push(newLine);
        console.log('New line added. Total lines:', this.invoice.lines.length);
        
      } catch (error) {
        console.error('Error adding item row:', error);
        this.showMessage('Error adding new item: ' + error.message, 'error');
      }
    },

    removeItemRow(index) {
      try {
        console.log('Removing item row at index:', index);
        
        if (index >= 0 && index < this.invoice.lines.length) {
          this.invoice.lines.splice(index, 1);
          this.calculateTotals();
          console.log('Item removed. Remaining lines:', this.invoice.lines.length);
        }
        
      } catch (error) {
        console.error('Error removing item row:', error);
        this.showMessage('Error removing item: ' + error.message, 'error');
      }
    },

    async updateLineItem(index) {
      try {
        console.log('Updating line item at index:', index);
        
        const line = this.invoice.lines[index];
        if (!line || !line.item_id) {
          console.log('No item selected for line:', index);
          return;
        }

        const selectedItem = this.items.find(item => item.item_id === line.item_id);
        console.log('Selected item:', selectedItem);

        if (selectedItem) {
          line.description = selectedItem.name || '';
          line.unit_price = this.safeParseFloat(selectedItem.sale_price);
          
          // Enhanced UOM setting with fallbacks
          line.uom_id = selectedItem.uom_id || 
                       selectedItem.default_uom_id || 
                       selectedItem.base_uom_id || 
                       null;

          // If still no UOM, try to fetch from API
          if (!line.uom_id) {
            console.log('No UOM in selected item, fetching from API...');
            line.uom_id = await this.getItemUOM(line.item_id);
          }

          if (!line.uom_id) {
            console.error('Unable to determine UOM for item:', selectedItem);
            this.showMessage(`Item ${selectedItem.name} tidak memiliki Unit of Measure yang valid. Silakan set UOM untuk item ini di master data.`, 'warning');
            
            // Optionally, clear the item selection
            line.item_id = '';
            line.description = '';
            line.unit_price = 0;
            return;
          }

          console.log(`✅ UOM set for line ${index + 1}:`, line.uom_id);
          this.calculateLineTotals(index);
          
        } else {
          console.error('Item not found:', line.item_id);
          this.showMessage('Selected item not found', 'error');
        }
        
      } catch (error) {
        console.error('Error updating line item:', error);
        this.showMessage('Error updating item: ' + error.message, 'error');
      }
    },

    calculateLineTotals(index) {
      try {
        const line = this.invoice.lines[index];
        if (!line) return;

        line.unit_price = this.safeParseFloat(line.unit_price);
        line.discount = this.safeParseFloat(line.discount);
        line.tax = this.safeParseFloat(line.tax);
        line.quantity = this.safeParseFloat(line.quantity);

        line.subtotal = Number((line.quantity * line.unit_price).toFixed(5));
        const subtotalNum = this.safeParseFloat(line.subtotal);
        const discountNum = this.safeParseFloat(line.discount);
        const taxNum = this.safeParseFloat(line.tax);
        
        line.total = Number((subtotalNum - discountNum + taxNum).toFixed(5));

        this.calculateTotals();
        
      } catch (error) {
        console.error('Error calculating line totals:', error);
      }
    },

    calculateTotals() {
      try {
        this.subtotal = 0;
        this.totalDiscount = 0;
        this.invoice.tax_amount = 0;
        this.invoice.total_amount = 0;

        this.invoice.lines.forEach(line => {
          this.subtotal += this.safeParseFloat(line.subtotal);
          this.totalDiscount += this.safeParseFloat(line.discount);
          this.invoice.tax_amount += this.safeParseFloat(line.tax);
        });

        this.invoice.total_amount = this.safeParseFloat(
          (this.subtotal - this.totalDiscount + this.invoice.tax_amount).toFixed(5)
        );
        
      } catch (error) {
        console.error('Error calculating totals:', error);
      }
    },

    // Method to fix all lines with missing UOM
    async fixAllMissingUOMs() {
      console.log('Fixing all missing UOMs...');
      
      for (let i = 0; i < this.invoice.lines.length; i++) {
        const line = this.invoice.lines[i];
        
        if (!line.uom_id && line.item_id) {
          console.log(`Fixing UOM for line ${i + 1}, item ${line.item_id}`);
          line.uom_id = await this.getItemUOM(line.item_id);
          
          if (line.uom_id) {
            console.log(`✅ Fixed UOM for line ${i + 1}: ${line.uom_id}`);
          } else {
            console.error(`❌ Could not fix UOM for line ${i + 1}`);
          }
        }
      }
    },

    async saveInvoice() {
      try {
        console.log('=== SAVE INVOICE START ===');
        
        // First, try to fix any missing UOMs
        await this.fixAllMissingUOMs();

        // Basic validation
        if (this.invoice.lines.length === 0) {
          this.showMessage('Please add at least one item to the invoice', 'error');
          return;
        }

        // Detailed validation
        for (let i = 0; i < this.invoice.lines.length; i++) {
          const line = this.invoice.lines[i];
          console.log(`Validating line ${i + 1}:`, line);

          if (!line.item_id) {
            this.showMessage(`Baris ke-${i + 1}: Silakan pilih item`, 'error');
            return;
          }

          if (!line.uom_id) {
            console.error(`❌ Line ${i + 1} missing UOM after fix attempts:`, line);
            this.showMessage(`Baris ke-${i + 1} belum memiliki Unit of Measure (UOM). Silakan pilih item yang valid.`, 'error');
            return;
          }

          if (!line.quantity || line.quantity <= 0) {
            this.showMessage(`Baris ke-${i + 1}: Quantity harus lebih dari 0`, 'error');
            return;
          }

          if (line.unit_price < 0) {
            this.showMessage(`Baris ke-${i + 1}: Unit price tidak valid`, 'error');
            return;
          }
        }

        console.log('✅ All validations passed');
        this.saving = true;
        this.errors = {};

        let response;
        const payload = this.preparePayload();
        console.log('📤 Prepared payload:', JSON.stringify(payload, null, 2));

        if (this.isEditing) {
          console.log('📝 Updating existing invoice...');
          response = await axios.put(`/invoices/${this.id}`, payload);
          this.showMessage('Invoice updated successfully', 'success');
        } else if (this.createFromDelivery && this.selectedDeliveries.length > 0) {
          console.log('🚚 Creating invoice from deliveries...');
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
          this.showMessage('Invoice created successfully', 'success');
        } else {
          console.log('📄 Creating new invoice...');
          response = await axios.post('/invoices', payload);
          this.showMessage('Invoice created successfully', 'success');
        }

        console.log('✅ API call successful');

        // Navigate to detail page
        const newId = response?.data?.data?.invoice_id;
        if (newId) {
          this.$router.push(`/sales/invoices/${newId}`);
        } else {
          this.$router.push('/sales/invoices');
        }

      } catch (error) {
        console.error('❌ Error saving invoice:', error);
        
        if (error.response?.data?.errors) {
          this.errors = error.response.data.errors;
          this.showMessage('Please correct the validation errors', 'error');
        } else {
          const errorMessage = error.response?.data?.message || 
                             error.response?.data?.error || 
                             error.message || 
                             'Failed to save invoice';
          this.showMessage(errorMessage, 'error');
        }
      } finally {
        this.saving = false;
      }
    },

    preparePayload() {
      try {
        console.log('🔧 Preparing payload...');
        
        const lines = this.invoice.lines.map((line, index) => {
          console.log(`Processing line ${index + 1} for payload:`, line);
          
          const preparedLine = {
            line_id: line.line_id,
            item_id: line.item_id,
            quantity: line.quantity,
            unit_price: line.unit_price,
            discount: line.discount || 0,
            tax: line.tax || 0,
            uom_id: line.uom_id,
            do_line_id: line.do_line_id,
            so_line_id: line.so_line_id
          };
          
          console.log(`Prepared line ${index + 1}:`, preparedLine);
          
          // Validate critical fields
          if (!preparedLine.item_id) {
            throw new Error(`Line ${index + 1}: Missing item_id in preparePayload`);
          }
          if (!preparedLine.uom_id) {
            throw new Error(`Line ${index + 1}: Missing uom_id in preparePayload`);
          }
          
          return preparedLine;
        });

        const payload = {
          invoice_number: this.invoice.invoice_number,
          invoice_date: this.invoice.invoice_date,
          due_date: this.invoice.due_date,
          customer_id: this.invoice.customer_id,
          status: this.invoice.status,
          currency_code: this.invoice.currency_code,
          reference: this.invoice.reference || null,
          payment_terms: this.invoice.payment_terms || null,
          do_id: this.invoice.do_id,
          lines: lines
        };

        console.log('✅ Payload prepared successfully');
        return payload;
        
      } catch (error) {
        console.error('❌ Error in preparePayload:', error);
        throw error;
      }
    },

    goBack() {
      try {
        this.$router.go(-1);
      } catch (error) {
        console.error('Error going back:', error);
        this.$router.push('/sales/invoices');
      }
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
