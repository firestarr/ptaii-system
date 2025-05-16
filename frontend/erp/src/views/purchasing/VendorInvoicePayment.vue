<!-- src/views/purchasing/VendorInvoicePayment.vue -->
<template>
    <div class="vendor-invoice-payment">
      <div class="page-header">
        <h1>Process Invoice Payment</h1>
        <div class="actions">
          <router-link :to="`/purchasing/vendor-invoices/${$route.params.id}`" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Details
          </router-link>
        </div>
      </div>
  
      <div v-if="loading" class="loading">
        <i class="fas fa-spinner fa-spin"></i> Loading invoice details...
      </div>
  
      <div v-else-if="!invoice" class="empty-state">
        <i class="fas fa-file-invoice"></i>
        <h3>Invoice not found</h3>
        <p>The requested invoice could not be found or has been deleted.</p>
      </div>
  
      <div v-else-if="invoice.status !== 'approved'" class="alert-card">
        <div class="alert-icon">
          <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="alert-content">
          <h3>Cannot Process Payment</h3>
          <p>This invoice cannot be paid because its current status is <strong>{{ capitalizeFirst(invoice.status) }}</strong>.</p>
          <p>Only invoices with "Approved" status can be processed for payment.</p>
          <router-link :to="`/purchasing/vendor-invoices/${invoice.invoice_id}`" class="btn btn-primary mt-2">
            Return to Invoice Details
          </router-link>
        </div>
      </div>
  
      <div v-else class="payment-container">
        <div class="card invoice-summary">
          <div class="card-header">
            <h2>Invoice Summary</h2>
          </div>
          <div class="card-body">
            <div class="invoice-meta">
              <div class="meta-group">
                <label>Invoice Number</label>
                <div class="meta-value">{{ invoice.invoice_number }}</div>
              </div>
              <div class="meta-group">
                <label>Vendor</label>
                <div class="meta-value">{{ invoice.vendor?.name }}</div>
                <div class="meta-subtitle">{{ invoice.vendor?.vendor_code }}</div>
              </div>
              <div class="meta-group">
                <label>Invoice Date</label>
                <div class="meta-value">{{ formatDate(invoice.invoice_date) }}</div>
              </div>
              <div class="meta-group">
                <label>Due Date</label>
                <div class="meta-value">{{ formatDate(invoice.due_date) }}</div>
                <div class="meta-subtitle" :class="isOverdue(invoice.due_date) ? 'text-danger' : ''">
                  {{ getDueStatus(invoice.due_date) }}
                </div>
              </div>
              <div class="meta-group">
                <label>Status</label>
                <div class="meta-value">
                  <span class="status-badge status-approved">{{ capitalizeFirst(invoice.status) }}</span>
                </div>
              </div>
              <div class="meta-group">
                <label>Total Amount</label>
                <div class="meta-value">{{ formatCurrency(invoice.total_amount, invoice.currency_code) }}</div>
                <div class="meta-subtitle" v-if="invoice.currency_code !== 'USD'">
                  {{ formatCurrency(invoice.base_currency_total, 'USD') }} (USD)
                </div>
              </div>
            </div>
          </div>
        </div>
  
        <div class="card payment-details mt-4">
          <div class="card-header">
            <h2>Payment Details</h2>
          </div>
          <div class="card-body">
            <form @submit.prevent="processPayment">
              <div class="form-row">
                <div class="form-group">
                  <label for="payment_date">Payment Date</label>
                  <input 
                    type="date" 
                    id="payment_date" 
                    v-model="paymentForm.payment_date" 
                    required
                  />
                  <span v-if="errors.payment_date" class="error-text">{{ errors.payment_date[0] }}</span>
                </div>
  
                <div class="form-group">
                  <label for="payment_method">Payment Method</label>
                  <select 
                    id="payment_method" 
                    v-model="paymentForm.payment_method" 
                    required
                    @change="paymentMethodChanged"
                  >
                    <option value="" disabled>Select payment method</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="check">Check</option>
                    <option value="credit_card">Credit Card</option>
                    <option value="cash">Cash</option>
                  </select>
                  <span v-if="errors.payment_method" class="error-text">{{ errors.payment_method[0] }}</span>
                </div>
  
                <div class="form-group">
                  <label for="payment_currency">Payment Currency</label>
                  <select 
                    id="payment_currency" 
                    v-model="paymentForm.payment_currency" 
                    required
                    @change="paymentCurrencyChanged"
                  >
                    <option :value="invoice.currency_code">{{ invoice.currency_code }} (Invoice Currency)</option>
                    <option v-if="invoice.currency_code !== 'USD'" value="USD">USD (Base Currency)</option>
                    <option v-if="invoice.currency_code !== 'EUR'" value="EUR">EUR (Euro)</option>
                    <option v-if="invoice.currency_code !== 'JPY'" value="JPY">JPY (Japanese Yen)</option>
                    <option v-if="invoice.currency_code !== 'IDR'" value="IDR">IDR (Indonesian Rupiah)</option>
                  </select>
                  <span v-if="errors.payment_currency" class="error-text">{{ errors.payment_currency[0] }}</span>
                </div>
              </div>
  
              <div class="form-row">
                <div class="form-group">
                  <label for="payment_amount">Payment Amount</label>
                  <div class="input-with-prefix">
                    <span class="input-prefix">{{ paymentForm.payment_currency }}</span>
                    <input 
                      type="number" 
                      id="payment_amount" 
                      v-model="paymentForm.payment_amount" 
                      min="0" 
                      step="0.01" 
                      required
                      @input="calculateBaseAmount"
                    />
                  </div>
                  <span v-if="errors.payment_amount" class="error-text">{{ errors.payment_amount[0] }}</span>
                </div>
  
                <div class="form-group">
                  <label for="exchange_rate">Exchange Rate</label>
                  <div class="input-with-button">
                    <input 
                      type="number" 
                      id="exchange_rate" 
                      v-model="paymentForm.exchange_rate" 
                      min="0.0001" 
                      step="0.0001" 
                      required
                      :disabled="paymentForm.payment_currency === 'USD'"
                      @input="calculateBaseAmount"
                    />
                    <button 
                      type="button" 
                      class="btn btn-secondary" 
                      @click="fetchExchangeRate"
                      :disabled="paymentForm.payment_currency === 'USD'"
                    >
                      <i class="fas fa-sync-alt"></i>
                    </button>
                  </div>
                  <span class="helper-text">
                    {{ paymentForm.payment_currency }} to USD
                  </span>
                  <span v-if="errors.exchange_rate" class="error-text">{{ errors.exchange_rate[0] }}</span>
                </div>
  
                <div class="form-group">
                  <label>Base Amount (USD)</label>
                  <div class="input-with-prefix readonly">
                    <span class="input-prefix">USD</span>
                    <input 
                      type="number" 
                      :value="baseAmount" 
                      readonly
                    />
                  </div>
                </div>
              </div>
  
              <div v-if="paymentForm.payment_method === 'bank_transfer'" class="payment-method-fields">
                <div class="form-row">
                  <div class="form-group">
                    <label for="bank_account">Bank Account</label>
                    <select 
                      id="bank_account" 
                      v-model="paymentForm.bank_account" 
                      required
                    >
                      <option value="" disabled>Select bank account</option>
                      <option value="BANK001">BANK001 - Operating Account</option>
                      <option value="BANK002">BANK002 - Payables Account</option>
                    </select>
                    <span v-if="errors.bank_account" class="error-text">{{ errors.bank_account[0] }}</span>
                  </div>
  
                  <div class="form-group">
                    <label for="reference_number">Reference Number</label>
                    <input 
                      type="text" 
                      id="reference_number" 
                      v-model="paymentForm.reference_number" 
                      placeholder="Transfer reference number"
                    />
                    <span v-if="errors.reference_number" class="error-text">{{ errors.reference_number[0] }}</span>
                  </div>
                </div>
              </div>
  
              <div v-if="paymentForm.payment_method === 'check'" class="payment-method-fields">
                <div class="form-row">
                  <div class="form-group">
                    <label for="check_number">Check Number</label>
                    <input 
                      type="text" 
                      id="check_number" 
                      v-model="paymentForm.check_number" 
                      required
                      placeholder="Enter check number"
                    />
                    <span v-if="errors.check_number" class="error-text">{{ errors.check_number[0] }}</span>
                  </div>
  
                  <div class="form-group">
                    <label for="bank_account">Bank Account</label>
                    <select 
                      id="bank_account" 
                      v-model="paymentForm.bank_account" 
                      required
                    >
                      <option value="" disabled>Select bank account</option>
                      <option value="BANK001">BANK001 - Operating Account</option>
                      <option value="BANK002">BANK002 - Payables Account</option>
                    </select>
                    <span v-if="errors.bank_account" class="error-text">{{ errors.bank_account[0] }}</span>
                  </div>
                </div>
              </div>
  
              <div v-if="paymentForm.payment_method === 'credit_card'" class="payment-method-fields">
                <div class="form-row">
                  <div class="form-group">
                    <label for="card_type">Card Type</label>
                    <select 
                      id="card_type" 
                      v-model="paymentForm.card_type" 
                      required
                    >
                      <option value="" disabled>Select card type</option>
                      <option value="visa">Visa</option>
                      <option value="mastercard">Mastercard</option>
                      <option value="amex">American Express</option>
                    </select>
                    <span v-if="errors.card_type" class="error-text">{{ errors.card_type[0] }}</span>
                  </div>
  
                  <div class="form-group">
                    <label for="card_last_four">Last 4 Digits</label>
                    <input 
                      type="text" 
                      id="card_last_four" 
                      v-model="paymentForm.card_last_four" 
                      required
                      maxlength="4"
                      pattern="[0-9]{4}"
                      placeholder="Last 4 digits of card"
                    />
                    <span v-if="errors.card_last_four" class="error-text">{{ errors.card_last_four[0] }}</span>
                  </div>
  
                  <div class="form-group">
                    <label for="authorization_code">Authorization Code</label>
                    <input 
                      type="text" 
                      id="authorization_code" 
                      v-model="paymentForm.authorization_code" 
                      placeholder="Authorization code"
                    />
                    <span v-if="errors.authorization_code" class="error-text">{{ errors.authorization_code[0] }}</span>
                  </div>
                </div>
              </div>
  
              <div class="form-group">
                <label for="payment_notes">Payment Notes</label>
                <textarea 
                  id="payment_notes" 
                  v-model="paymentForm.payment_notes" 
                  rows="3" 
                  placeholder="Add any notes regarding this payment..."
                ></textarea>
                <span v-if="errors.payment_notes" class="error-text">{{ errors.payment_notes[0] }}</span>
              </div>
  
              <div class="form-group">
                <div class="checkbox-group">
                  <input type="checkbox" id="create_journal_entry" v-model="paymentForm.create_journal_entry" />
                  <label for="create_journal_entry">Create Journal Entry</label>
                </div>
              </div>
  
              <div v-if="paymentForm.create_journal_entry" class="journal-entry-options">
                <div class="form-row">
                  <div class="form-group">
                    <label for="ap_account_id">Accounts Payable Account</label>
                    <select id="ap_account_id" v-model="paymentForm.ap_account_id" required>
                      <option value="" disabled>Select account</option>
                      <option value="AP001">AP001 - Accounts Payable</option>
                      <option value="AP002">AP002 - International Payables</option>
                    </select>
                    <span v-if="errors.ap_account_id" class="error-text">{{ errors.ap_account_id[0] }}</span>
                  </div>
  
                  <div class="form-group">
                    <label for="cash_account_id">Cash/Bank Account</label>
                    <select id="cash_account_id" v-model="paymentForm.cash_account_id" required>
                      <option value="" disabled>Select account</option>
                      <option value="CASH001">CASH001 - Operating Cash</option>
                      <option value="BANK001">BANK001 - Operating Account</option>
                      <option value="BANK002">BANK002 - Payables Account</option>
                      <option value="CC001">CC001 - Corporate Credit Card</option>
                    </select>
                    <span v-if="errors.cash_account_id" class="error-text">{{ errors.cash_account_id[0] }}</span>
                  </div>
                </div>
              </div>
  
              <div class="form-actions mt-4">
                <button type="button" class="btn btn-secondary" @click="cancel">
                  Cancel
                </button>
                <button 
                  type="submit" 
                  class="btn btn-primary" 
                  :disabled="processing"
                >
                  <i v-if="processing" class="fas fa-spinner fa-spin"></i>
                  Process Payment
                </button>
              </div>
            </form>
          </div>
        </div>
  
        <!-- Confirm Payment Modal -->
        <div v-if="showConfirmModal" class="modal">
          <div class="modal-backdrop" @click="showConfirmModal = false"></div>
          <div class="modal-content modal-sm">
            <div class="modal-header">
              <h2>Confirm Payment</h2>
              <button class="close-btn" @click="showConfirmModal = false">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <div class="modal-body">
              <p>Are you sure you want to process payment of <strong>{{ formatCurrency(paymentForm.payment_amount, paymentForm.payment_currency) }}</strong> for invoice <strong>{{ invoice.invoice_number }}</strong>?</p>
              
              <table class="payment-summary-table">
                <tbody>
                  <tr>
                    <th>Payment Method:</th>
                    <td>{{ getPaymentMethodLabel(paymentForm.payment_method) }}</td>
                  </tr>
                  <tr v-if="paymentForm.payment_currency !== 'USD'">
                    <th>Exchange Rate:</th>
                    <td>{{ paymentForm.exchange_rate }} ({{ paymentForm.payment_currency }} to USD)</td>
                  </tr>
                  <tr v-if="paymentForm.payment_currency !== 'USD'">
                    <th>USD Amount:</th>
                    <td>{{ formatCurrency(baseAmount, 'USD') }}</td>
                  </tr>
                  <tr v-if="paymentForm.payment_method === 'bank_transfer'">
                    <th>Bank Account:</th>
                    <td>{{ paymentForm.bank_account }}</td>
                  </tr>
                  <tr v-if="paymentForm.payment_method === 'check'">
                    <th>Check Number:</th>
                    <td>{{ paymentForm.check_number }}</td>
                  </tr>
                  <tr v-if="paymentForm.payment_method === 'credit_card'">
                    <th>Card:</th>
                    <td>{{ getCardTypeLabel(paymentForm.card_type) }} ending in {{ paymentForm.card_last_four }}</td>
                  </tr>
                </tbody>
              </table>
              
              <div class="form-actions">
                <button type="button" class="btn btn-secondary" @click="showConfirmModal = false">
                  Cancel
                </button>
                <button type="button" class="btn btn-primary" @click="confirmPayment">
                  <i v-if="processing" class="fas fa-spinner fa-spin"></i>
                  Confirm Payment
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'VendorInvoicePayment',
    data() {
      return {
        loading: true,
        processing: false,
        invoice: null,
        paymentForm: {
          payment_date: new Date().toISOString().split('T')[0],
          payment_method: '',
          payment_currency: '',
          payment_amount: 0,
          exchange_rate: 1,
          bank_account: '',
          reference_number: '',
          check_number: '',
          card_type: '',
          card_last_four: '',
          authorization_code: '',
          payment_notes: '',
          create_journal_entry: true,
          ap_account_id: 'AP001',
          cash_account_id: 'BANK001'
        },
        baseAmount: 0,
        errors: {},
        showConfirmModal: false
      };
    },
    created() {
      this.loadInvoice();
    },
    methods: {
      async loadInvoice() {
        try {
          const invoiceId = this.$route.params.id;
          const response = await axios.get(`/vendor-invoices/${invoiceId}`);
          
          if (response.data.status === 'success') {
            this.invoice = response.data.data.invoice;
            
            // Initialize form values
            this.paymentForm.payment_currency = this.invoice.currency_code;
            this.paymentForm.payment_amount = this.invoice.total_amount;
            this.paymentForm.exchange_rate = this.invoice.exchange_rate || 1;
            this.calculateBaseAmount();
          }
        } catch (error) {
          console.error('Error loading invoice:', error);
        } finally {
          this.loading = false;
        }
      },
      paymentMethodChanged() {
        // Reset payment method specific fields
        this.paymentForm.bank_account = '';
        this.paymentForm.reference_number = '';
        this.paymentForm.check_number = '';
        this.paymentForm.card_type = '';
        this.paymentForm.card_last_four = '';
        this.paymentForm.authorization_code = '';
        
        // Set default cash account based on payment method
        if (this.paymentForm.payment_method === 'bank_transfer' || this.paymentForm.payment_method === 'check') {
          this.paymentForm.cash_account_id = 'BANK001';
        } else if (this.paymentForm.payment_method === 'credit_card') {
          this.paymentForm.cash_account_id = 'CC001';
        } else {
          this.paymentForm.cash_account_id = 'CASH001';
        }
      },
      async paymentCurrencyChanged() {
        // If currency changed to USD, set exchange rate to 1
        if (this.paymentForm.payment_currency === 'USD') {
          this.paymentForm.exchange_rate = 1;
        } else {
          await this.fetchExchangeRate();
        }
        
        this.calculateBaseAmount();
      },
      async fetchExchangeRate() {
        if (this.paymentForm.payment_currency === 'USD') {
          this.paymentForm.exchange_rate = 1;
          return;
        }
        
        try {
          const response = await axios.get('/currency-rates/current-rate', {
            params: {
              currency_code: this.paymentForm.payment_currency,
              date: this.paymentForm.payment_date
            }
          });
          
          if (response.data.status === 'success') {
            this.paymentForm.exchange_rate = response.data.data.rate;
            this.calculateBaseAmount();
          }
        } catch (error) {
          console.error('Error fetching exchange rate:', error);
        }
      },
      calculateBaseAmount() {
        if (this.paymentForm.payment_currency === 'USD') {
          this.baseAmount = parseFloat(this.paymentForm.payment_amount) || 0;
        } else {
          this.baseAmount = (parseFloat(this.paymentForm.payment_amount) || 0) / (parseFloat(this.paymentForm.exchange_rate) || 1);
        }
        
        // Round to 2 decimal places
        this.baseAmount = Math.round(this.baseAmount * 100) / 100;
      },
      processPayment() {
        this.errors = {};
        
        // Validate form
        const errors = {};
        
        if (!this.paymentForm.payment_method) {
          errors.payment_method = ['Payment method is required'];
        }
        
        if (!this.paymentForm.payment_currency) {
          errors.payment_currency = ['Payment currency is required'];
        }
        
        if (!this.paymentForm.payment_amount || this.paymentForm.payment_amount <= 0) {
          errors.payment_amount = ['Payment amount must be greater than zero'];
        }
        
        if (this.paymentForm.payment_currency !== 'USD' && 
            (!this.paymentForm.exchange_rate || this.paymentForm.exchange_rate <= 0)) {
          errors.exchange_rate = ['Exchange rate must be greater than zero'];
        }
        
        // Payment method specific validations
        if (this.paymentForm.payment_method === 'bank_transfer') {
          if (!this.paymentForm.bank_account) {
            errors.bank_account = ['Bank account is required'];
          }
        } else if (this.paymentForm.payment_method === 'check') {
          if (!this.paymentForm.check_number) {
            errors.check_number = ['Check number is required'];
          }
          if (!this.paymentForm.bank_account) {
            errors.bank_account = ['Bank account is required'];
          }
        } else if (this.paymentForm.payment_method === 'credit_card') {
          if (!this.paymentForm.card_type) {
            errors.card_type = ['Card type is required'];
          }
          if (!this.paymentForm.card_last_four || !/^\d{4}$/.test(this.paymentForm.card_last_four)) {
            errors.card_last_four = ['Last 4 digits of card are required (numeric only)'];
          }
        }
        
        // Journal entry validations
        if (this.paymentForm.create_journal_entry) {
          if (!this.paymentForm.ap_account_id) {
            errors.ap_account_id = ['Accounts payable account is required'];
          }
          if (!this.paymentForm.cash_account_id) {
            errors.cash_account_id = ['Cash/bank account is required'];
          }
        }
        
        if (Object.keys(errors).length > 0) {
          this.errors = errors;
          return;
        }
        
        // Show confirmation modal
        this.showConfirmModal = true;
      },
      async confirmPayment() {
        this.processing = true;
        
        try {
          const paymentData = {
            invoice_id: this.invoice.invoice_id,
            payment_date: this.paymentForm.payment_date,
            payment_method: this.paymentForm.payment_method,
            payment_currency: this.paymentForm.payment_currency,
            payment_amount: this.paymentForm.payment_amount,
            exchange_rate: this.paymentForm.exchange_rate,
            base_amount: this.baseAmount,
            notes: this.paymentForm.payment_notes,
            create_journal_entry: this.paymentForm.create_journal_entry
          };
          
          // Add payment method specific data
          if (this.paymentForm.payment_method === 'bank_transfer') {
            paymentData.bank_account = this.paymentForm.bank_account;
            paymentData.reference_number = this.paymentForm.reference_number;
          } else if (this.paymentForm.payment_method === 'check') {
            paymentData.bank_account = this.paymentForm.bank_account;
            paymentData.check_number = this.paymentForm.check_number;
          } else if (this.paymentForm.payment_method === 'credit_card') {
            paymentData.card_type = this.paymentForm.card_type;
            paymentData.card_last_four = this.paymentForm.card_last_four;
            paymentData.authorization_code = this.paymentForm.authorization_code;
          }
          
          // Add journal entry data
          if (this.paymentForm.create_journal_entry) {
            paymentData.ap_account_id = this.paymentForm.ap_account_id;
            paymentData.cash_account_id = this.paymentForm.cash_account_id;
          }
          
          await axios.post(`/vendor-invoices/${this.invoice.invoice_id}/pay`, paymentData);
          
          this.showConfirmModal = false;
          
          // Redirect to invoice detail
          this.$router.push(`/purchasing/vendor-invoices/${this.invoice.invoice_id}`);
        } catch (error) {
          console.error('Error processing payment:', error);
          
          if (error.response && error.response.data && error.response.data.errors) {
            this.errors = error.response.data.errors;
          } else {
            alert(error.response?.data?.message || 'Error processing payment');
          }
          
          this.showConfirmModal = false;
        } finally {
          this.processing = false;
        }
      },
      cancel() {
        this.$router.push(`/purchasing/vendor-invoices/${this.invoice.invoice_id}`);
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
      },
      capitalizeFirst(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
      },
      getDueStatus(dueDate) {
        if (!dueDate) return 'No due date';
        
        const today = new Date();
        const due = new Date(dueDate);
        
        if (today > due) {
          const days = Math.floor((today - due) / (1000 * 60 * 60 * 24));
          return `Overdue by ${days} day${days === 1 ? '' : 's'}`;
        } else {
          const days = Math.floor((due - today) / (1000 * 60 * 60 * 24));
          return `Due in ${days} day${days === 1 ? '' : 's'}`;
        }
      },
      isOverdue(dueDate) {
        if (!dueDate) return false;
        
        const today = new Date();
        const due = new Date(dueDate);
        
        return today > due;
      },
      getPaymentMethodLabel(method) {
        const methods = {
          bank_transfer: 'Bank Transfer',
          check: 'Check',
          credit_card: 'Credit Card',
          cash: 'Cash'
        };
        
        return methods[method] || method;
      },
      getCardTypeLabel(type) {
        const types = {
          visa: 'Visa',
          mastercard: 'Mastercard',
          amex: 'American Express'
        };
        
        return types[type] || type;
      }
    }
  };
  </script>
  
  <style scoped>
  .vendor-invoice-payment {
    padding: 1rem;
  }
  
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
  }
  
  .payment-container {
    max-width: 1200px;
  }
  
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
  
  .invoice-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.5rem;
  }
  
  .meta-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }
  
  .meta-group label {
    font-size: 0.75rem;
    color: var(--gray-500);
    font-weight: 500;
    text-transform: uppercase;
  }
  
  .meta-value {
    font-weight: 500;
    color: var(--gray-800);
  }
  
  .meta-subtitle {
    font-size: 0.875rem;
    color: var(--gray-500);
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
  .form-group select,
  .form-group textarea {
    padding: 0.625rem;
    border: 1px solid var(--gray-300);
    border-radius: 0.375rem;
    font-size: 0.875rem;
  }
  
  .form-group input:focus,
  .form-group select:focus,
  .form-group textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
  }
  
  .input-with-prefix,
  .input-with-button {
    display: flex;
    align-items: center;
  }
  
  .input-prefix {
    background-color: var(--gray-100);
    padding: 0.625rem;
    border: 1px solid var(--gray-300);
    border-right: none;
    border-top-left-radius: 0.375rem;
    border-bottom-left-radius: 0.375rem;
    color: var(--gray-600);
    font-size: 0.875rem;
  }
  
  .input-with-prefix input {
    flex: 1;
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
  }
  
  .readonly .input-prefix,
  .readonly input {
    background-color: var(--gray-50);
    color: var(--gray-600);
  }
  
  .readonly input {
    border-color: var(--gray-200);
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
  
  .payment-method-fields {
    border-top: 1px solid var(--gray-200);
    padding-top: 1.5rem;
    margin-top: 1.5rem;
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
  
  .status-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
  }
  
  .status-approved {
    background-color: #d1fae5;
    color: #065f46;
  }
  
  .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
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
  
  .alert-card {
    display: flex;
    gap: 1.5rem;
    padding: 1.5rem;
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 1.5rem;
    border-left: 4px solid var(--warning-color);
  }
  
  .alert-icon {
    font-size: 2rem;
    color: var(--warning-color);
  }
  
  .alert-content h3 {
    font-size: 1.125rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
  }
  
  .alert-content p {
    margin: 0 0 0.5rem 0;
    color: var(--gray-600);
  }
  
  .text-danger {
    color: var(--danger-color);
  }
  
  .error-text {
    color: var(--danger-color);
    font-size: 0.75rem;
    margin-top: 0.25rem;
  }
  
  .mt-2 {
    margin-top: 0.75rem;
  }
  
  .mt-4 {
    margin-top: 1.5rem;
  }
  
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
    width: 100%;
    z-index: 60;
    overflow: hidden;
  }
  
  .modal-sm {
    max-width: 400px;
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
    color: var(--gray-800);
  }
  
  .close-btn {
    background: none;
    border: none;
    color: var(--gray-500);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.25rem;
    border-radius: 0.25rem;
  }
  
  .close-btn:hover {
    background-color: var(--gray-100);
    color: var(--gray-800);
  }
  
  .modal-body {
    padding: 1.5rem;
  }
  
  .payment-summary-table {
    width: 100%;
    margin: 1rem 0;
    border-collapse: collapse;
  }
  
  .payment-summary-table th {
    text-align: left;
    padding: 0.5rem;
    font-weight: 500;
    color: var(--gray-600);
    width: 40%;
  }
  
  .payment-summary-table td {
    padding: 0.5rem;
    color: var(--gray-800);
  }
  
  .payment-summary-table tr:nth-child(odd) {
    background-color: var(--gray-50);
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
    
    .alert-card {
      flex-direction: column;
      gap: 1rem;
    }
  }
  </style>