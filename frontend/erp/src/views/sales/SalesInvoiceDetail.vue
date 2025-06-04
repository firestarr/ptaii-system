<!-- src/views/sales/SalesInvoiceDetail.vue -->
<template>
    <div class="page-container">
      <div class="page-header">
        <h1>Invoice Details</h1>
        <div class="header-actions">
          <router-link :to="`/sales/invoices`" class="btn btn-secondary">
            <i class="fas fa-list"></i> Back to List
          </router-link>
          <router-link :to="`/sales/invoices/${invoice.invoice_id}/print`" class="btn btn-secondary">
            <i class="fas fa-print"></i> Print
          </router-link>
          <router-link v-if="canEdit" :to="`/sales/invoices/${invoice.invoice_id}/edit`" class="btn btn-warning">
            <i class="fas fa-edit"></i> Edit
          </router-link>
          <button v-if="canDelete" @click="openDeleteModal" class="btn btn-danger">
            <i class="fas fa-trash"></i> Delete
          </button>
        </div>
      </div>

      <!-- Loading Indicator -->
      <div v-if="loading" class="loading-indicator">
        <i class="fas fa-spinner fa-spin"></i> Loading invoice...
      </div>

      <!-- Invoice Content -->
      <div v-else class="invoice-container">
        <!-- Status Banner -->
        <div class="status-banner" :class="getStatusClass(invoice.status)">
          <span class="status-text">{{ invoice.status }}</span>
          <div v-if="isOverdue(invoice.due_date, invoice.status)" class="overdue-tag">
            <i class="fas fa-exclamation-triangle"></i> OVERDUE
          </div>
        </div>

        <!-- Basic Information Card -->
        <div class="card">
          <div class="card-header">
            <h2>Invoice Information</h2>
            <div v-if="!isPaid && !isCancelled" class="header-actions">
              <button @click="openPaymentModal" class="btn btn-primary btn-sm">
                <i class="fas fa-money-bill-wave"></i> Record Payment
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="info-section">
              <div class="info-column">
                <div class="info-row">
                  <div class="info-label">Invoice Number:</div>
                  <div class="info-value">{{ invoice.invoice_number }}</div>
                </div>
                <div class="info-row">
                  <div class="info-label">Customer:</div>
                  <div class="info-value">
                    <router-link v-if="invoice.customer" :to="`/sales/customers/${invoice.customer.customer_id}`">
                      {{ invoice.customer.name }}
                    </router-link>
                    <span v-else>N/A</span>
                  </div>
                </div>
                <div class="info-row">
                  <div class="info-label">Invoice Date:</div>
                  <div class="info-value">{{ formatDate(invoice.invoice_date) }}</div>
                </div>
                <div class="info-row">
                  <div class="info-label">Due Date:</div>
                  <div class="info-value" :class="{ 'text-danger': isOverdue(invoice.due_date, invoice.status) }">
                    {{ formatDate(invoice.due_date) }}
                  </div>
                </div>
              </div>
              <div class="info-column">
                <div class="info-row">
                  <div class="info-label">Total Amount:</div>
                  <div class="info-value">{{ formatCurrency(invoice.total_amount, invoice.currency_code) }}</div>
                </div>
                <div class="info-row">
                  <div class="info-label">Currency:</div>
                  <div class="info-value">{{ invoice.currency_code }}</div>
                </div>
                <div class="info-row">
                  <div class="info-label">Payment Terms:</div>
                  <div class="info-value">{{ getPaymentTerms() }}</div>
                </div>
                <div class="info-row">
                  <div class="info-label">Reference:</div>
                  <div class="info-value">{{ getReference() }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Related Delivery Order -->
        <div v-if="invoice.delivery" class="card mt-4">
          <div class="card-header">
            <h2>Related Delivery</h2>
          </div>
          <div class="card-body">
            <div class="related-item">
              <div class="related-label">Delivery Number:</div>
              <div class="related-value">
                <router-link :to="`/sales/deliveries/${invoice.delivery.delivery_id}`">
                  {{ invoice.delivery.delivery_number }}
                </router-link>
              </div>
            </div>
            <div class="related-item">
              <div class="related-label">Delivery Date:</div>
              <div class="related-value">{{ formatDate(invoice.delivery.delivery_date) }}</div>
            </div>
            <div class="related-item">
              <div class="related-label">Shipping Method:</div>
              <div class="related-value">{{ invoice.delivery.shipping_method || 'Not specified' }}</div>
            </div>
            <div class="related-item">
              <div class="related-label">Tracking Number:</div>
              <div class="related-value">{{ invoice.delivery.tracking_number || 'Not specified' }}</div>
            </div>
          </div>
        </div>

        <!-- Invoice Items Card -->
        <div class="card mt-4">
          <div class="card-header">
            <h2>Invoice Items</h2>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Discount</th>
                    <th>Tax</th>
                    <th>Subtotal</th>
                    <th>Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="line in invoiceLines" :key="line.line_id">
                    <td>
                      <div v-if="line.item" class="item-details">
                        <strong>{{ line.item.item_code }}</strong>
                        <div>{{ line.item.name }}</div>
                      </div>
                      <div v-else>Unknown Item</div>
                    </td>
                    <td>{{ line.quantity }}</td>
                    <td>{{ formatCurrency(line.unit_price, invoice.currency_code) }}</td>
                    <td>{{ formatCurrency(line.discount || 0, invoice.currency_code) }}</td>
                    <td>{{ formatCurrency(line.tax || 0, invoice.currency_code) }}</td>
                    <td>{{ formatCurrency(line.subtotal, invoice.currency_code) }}</td>
                    <td>{{ formatCurrency(line.total, invoice.currency_code) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="5" class="text-right"><strong>Subtotal:</strong></td>
                    <td colspan="2">{{ formatCurrency(calculateSubtotal(), invoice.currency_code) }}</td>
                  </tr>
                  <tr>
                    <td colspan="5" class="text-right"><strong>Total Discount:</strong></td>
                    <td colspan="2">{{ formatCurrency(calculateTotalDiscount(), invoice.currency_code) }}</td>
                  </tr>
                  <tr>
                    <td colspan="5" class="text-right"><strong>Total Tax:</strong></td>
                    <td colspan="2">{{ formatCurrency(invoice.tax_amount || 0, invoice.currency_code) }}</td>
                  </tr>
                  <tr class="total-row">
                    <td colspan="5" class="text-right"><strong>Grand Total:</strong></td>
                    <td colspan="2">{{ formatCurrency(invoice.total_amount, invoice.currency_code) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>

        <!-- Payment Information Card -->
        <div class="card mt-4">
          <div class="card-header">
            <h2>Payment Information</h2>
          </div>
          <div class="card-body">
            <div v-if="loading" class="loading-text">
              <i class="fas fa-spinner fa-spin"></i> Loading payment information...
            </div>
            <div v-else-if="!receivable" class="empty-message">
              No payment information available.
            </div>
            <div v-else>
              <div class="payment-summary">
                <div class="payment-info-row">
                  <div class="payment-label">Total Amount:</div>
                  <div class="payment-value">{{ formatCurrency(receivable.amount, invoice.currency_code) }}</div>
                </div>
                <div class="payment-info-row">
                  <div class="payment-label">Amount Paid:</div>
                  <div class="payment-value">{{ formatCurrency(receivable.paid_amount || 0, invoice.currency_code) }}</div>
                </div>
                <div class="payment-info-row">
                  <div class="payment-label">Balance Due:</div>
                  <div class="payment-value" :class="{ 'text-danger': receivable.balance > 0 }">
                    {{ formatCurrency(receivable.balance || 0, invoice.currency_code) }}
                  </div>
                </div>
                <div class="payment-info-row">
                  <div class="payment-label">Status:</div>
                  <div class="payment-value">
                    <span class="status-badge" :class="getPaymentStatusClass(receivable.status)">
                      {{ receivable.status }}
                    </span>
                  </div>
                </div>
              </div>

              <div v-if="receivable.receivable_payments && receivable.receivable_payments.length > 0" class="mt-4">
                <h3>Payment History</h3>
                <div class="table-responsive">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Reference</th>
                        <th>Currency</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="payment in receivable.receivable_payments" :key="payment.payment_id">
                        <td>{{ formatDate(payment.payment_date) }}</td>
                        <td>{{ formatCurrency(payment.amount, payment.payment_currency) }}</td>
                        <td>{{ payment.payment_method }}</td>
                        <td>{{ payment.reference_number || 'N/A' }}</td>
                        <td>{{ payment.payment_currency }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div v-else class="empty-message mt-4">
                No payments recorded yet.
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Delete Confirmation Modal -->
      <div class="modal" v-if="showDeleteModal">
        <div class="modal-backdrop" @click="showDeleteModal = false"></div>
        <div class="modal-content modal-sm">
          <div class="modal-header">
            <h2>Confirm Delete</h2>
            <button class="close-btn" @click="showDeleteModal = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to delete invoice <strong>{{ invoice.invoice_number }}</strong>?</p>
            <p>This action cannot be undone.</p>

            <div class="form-actions">
              <button type="button" class="btn btn-secondary" @click="showDeleteModal = false">
                Cancel
              </button>
              <button
                type="button"
                class="btn btn-danger"
                @click="deleteInvoice"
                :disabled="deleting"
              >
                <i class="fas fa-spinner fa-spin" v-if="deleting"></i>
                Delete
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Payment Modal -->
      <div class="modal" v-if="showPaymentModal">
        <div class="modal-backdrop" @click="showPaymentModal = false"></div>
        <div class="modal-content">
          <div class="modal-header">
            <h2>Record Payment</h2>
            <button class="close-btn" @click="showPaymentModal = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="recordPayment">
              <div class="form-group">
                <label for="paymentAmount">Amount <span class="required">*</span></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text">{{ paymentData.payment_currency }}</span>
                  </div>
                  <input
                    type="number"
                    id="paymentAmount"
                    v-model.number="paymentData.amount"
                    class="form-control"
                    min="0.01"
                    step="0.01"
                    required
                  >
                </div>
                <div v-if="paymentErrors.amount" class="error-message">
                  {{ paymentErrors.amount }}
                </div>
              </div>

              <div class="form-group">
                <label for="paymentDate">Payment Date <span class="required">*</span></label>
                <input
                  type="date"
                  id="paymentDate"
                  v-model="paymentData.payment_date"
                  class="form-control"
                  required
                >
                <div v-if="paymentErrors.payment_date" class="error-message">
                  {{ paymentErrors.payment_date }}
                </div>
              </div>

              <div class="form-group">
                <label for="paymentMethod">Payment Method <span class="required">*</span></label>
                <select
                  id="paymentMethod"
                  v-model="paymentData.payment_method"
                  class="form-control"
                  required
                >
                  <option value="">Select Payment Method</option>
                  <option value="Cash">Cash</option>
                  <option value="Bank Transfer">Bank Transfer</option>
                  <option value="Credit Card">Credit Card</option>
                  <option value="Debit Card">Debit Card</option>
                  <option value="Check">Check</option>
                  <option value="Online Payment">Online Payment</option>
                  <option value="Other">Other</option>
                </select>
                <div v-if="paymentErrors.payment_method" class="error-message">
                  {{ paymentErrors.payment_method }}
                </div>
              </div>

              <div class="form-group">
                <label for="paymentCurrency">Currency</label>
                <select
                  id="paymentCurrency"
                  v-model="paymentData.payment_currency"
                  class="form-control"
                >
                  <option :value="invoice.currency_code">{{ invoice.currency_code }} (Invoice Currency)</option>
                  <option value="IDR">IDR - Indonesian Rupiah</option>
                  <option value="USD">USD - US Dollar</option>
                  <option value="EUR">EUR - Euro</option>
                  <option value="SGD">SGD - Singapore Dollar</option>
                  <option value="MYR">MYR - Malaysian Ringgit</option>
                </select>
              </div>

              <div class="form-group">
                <label for="reference">Reference Number</label>
                <input
                  type="text"
                  id="reference"
                  v-model="paymentData.reference"
                  class="form-control"
                  placeholder="e.g., Transaction ID, Check Number"
                >
              </div>

              <div class="form-actions">
                <button type="button" class="btn btn-secondary" @click="showPaymentModal = false">
                  Cancel
                </button>
                <button
                  type="submit"
                  class="btn btn-primary"
                  :disabled="recording"
                >
                  <i class="fas fa-spinner fa-spin" v-if="recording"></i>
                  Record Payment
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </template>
<script>
import axios from 'axios';

export default {
  name: 'SalesInvoiceDetail',
  props: {
    id: {
      type: [Number, String],
      required: true
    }
  },
  data() {
    return {
      invoice: {},
      receivable: null,
      loading: true,
      showDeleteModal: false,
      deleting: false,
      showPaymentModal: false,
      recording: false,
      paymentData: {
        amount: 0,
        payment_date: new Date().toISOString().split('T')[0],
        payment_method: '',
        payment_currency: '',
        reference: ''
      },
      paymentErrors: {}
    };
  },
  computed: {
    invoiceLines() {
      return this.invoice.sales_invoice_lines || [];
    },
    isPaid() {
      return this.invoice.status === 'Paid';
    },
    isCancelled() {
      return this.invoice.status === 'Cancelled';
    },
    canEdit() {
      return !this.isPaid && !this.isCancelled;
    },
    canDelete() {
      return !this.isPaid && !(this.invoice.sales_returns && this.invoice.sales_returns.length > 0);
    }
  },
  mounted() {
    this.fetchInvoice();
  },
  methods: {
    async fetchInvoice() {
      this.loading = true;
      try {
        const response = await axios.get(`/invoices/${this.id}`);
        this.invoice = response.data.data;

        // Set payment currency to invoice currency by default
        this.paymentData.payment_currency = this.invoice.currency_code;

        // Set default payment amount to balance due
        if (this.invoice.customer_receivables && this.invoice.customer_receivables.length > 0) {
          this.receivable = this.invoice.customer_receivables[0];
          this.paymentData.amount = this.receivable.balance || 0;
        }
      } catch (error) {
        console.error('Error fetching invoice:', error);
        this.$toast.error('Failed to load invoice details');
      } finally {
        this.loading = false;
      }
    },
    getPaymentTerms() {
      // Check multiple possible sources for payment terms
      if (this.invoice.payment_terms) {
        return this.invoice.payment_terms;
      }
      if (this.invoice.customer && this.invoice.customer.payment_term) {
        return `${this.invoice.customer.payment_term} days`;
      }
      return 'Not specified';
    },
    getReference() {
      // Check multiple possible sources for reference
      return this.invoice.reference || this.invoice.reference_number || 'Not specified';
    },
    getStatusClass(status) {
      switch (status) {
        case 'Draft':
          return 'status-draft';
        case 'Sent':
          return 'status-sent';
        case 'Partially Paid':
          return 'status-partial';
        case 'Paid':
          return 'status-paid';
        case 'Overdue':
          return 'status-overdue';
        case 'Cancelled':
          return 'status-cancelled';
        default:
          return '';
      }
    },
    getPaymentStatusClass(status) {
      switch (status) {
        case 'Open':
          return 'status-draft';
        case 'Partial':
          return 'status-partial';
        case 'Closed':
          return 'status-paid';
        default:
          return '';
      }
    },
    isOverdue(dueDate, status) {
      if (!dueDate || status === 'Paid' || status === 'Cancelled') return false;
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const due = new Date(dueDate);
      return due < today;
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
    formatCurrency(amount, currencyCode = 'IDR') {
      if (amount === undefined || amount === null) return 'N/A';
      return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: currencyCode || 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
      }).format(amount);
    },
    calculateSubtotal() {
      return this.invoiceLines.reduce((total, line) => total + (line.subtotal || 0), 0);
    },
    calculateTotalDiscount() {
      return this.invoiceLines.reduce((total, line) => total + (line.discount || 0), 0);
    },
    openDeleteModal() {
      this.showDeleteModal = true;
    },
    async deleteInvoice() {
      this.deleting = true;

      try {
        await axios.delete(`/invoices/${this.id}`);
        this.$toast.success(`Invoice ${this.invoice.invoice_number} deleted successfully`);
        this.showDeleteModal = false;
        this.$router.push('/sales/invoices');
      } catch (error) {
        console.error('Error deleting invoice:', error);
        if (error.response && error.response.data.message) {
          this.$toast.error(error.response.data.message);
        } else {
          this.$toast.error('Failed to delete invoice. Please try again.');
        }
      } finally {
        this.deleting = false;
      }
    },
    openPaymentModal() {
      this.paymentErrors = {};
      this.showPaymentModal = true;
    },
    async recordPayment() {
      this.paymentErrors = {};

      // Basic validation
      if (!this.paymentData.amount || this.paymentData.amount <= 0) {
        this.paymentErrors.amount = 'Amount must be greater than zero';
        return;
      }

      if (!this.paymentData.payment_date) {
        this.paymentErrors.payment_date = 'Payment date is required';
        return;
      }

      if (!this.paymentData.payment_method) {
        this.paymentErrors.payment_method = 'Payment method is required';
        return;
      }

      this.recording = true;

      try {
        await axios.post(`/invoices/${this.id}/recordPayment`, {
          amount: this.paymentData.amount,
          payment_date: this.paymentData.payment_date,
          payment_method: this.paymentData.payment_method,
          reference: this.paymentData.reference,
          payment_currency: this.paymentData.payment_currency
        });

        this.$toast.success('Payment recorded successfully');
        this.showPaymentModal = false;

        // Refresh invoice data to show updated payment info
        this.fetchInvoice();
      } catch (error) {
        console.error('Error recording payment:', error);
        if (error.response && error.response.data.errors) {
          const errors = error.response.data.errors;

          // Map API validation errors to form fields
          for (const field in errors) {
            this.paymentErrors[field.replace('amount', 'amount')] = errors[field][0];
          }

          this.$toast.error('Please correct the errors in the form');
        } else if (error.response && error.response.data.message) {
          this.$toast.error(error.response.data.message);
        } else {
          this.$toast.error('Failed to record payment. Please try again.');
        }
      } finally {
        this.recording = false;
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

  .header-actions {
    display: flex;
    gap: 0.5rem;
  }

  .invoice-container {
    max-width: 100%;
  }

  .status-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    color: white;
  }

  .status-draft {
    background-color: var(--gray-500);
  }

  .status-sent {
    background-color: #3b82f6;
  }

  .status-partial {
    background-color: #f59e0b;
  }

  .status-paid {
    background-color: #10b981;
  }

  .status-overdue {
    background-color: #ef4444;
  }

  .status-cancelled {
    background-color: var(--gray-700);
  }

  .status-text {
    font-size: 1.125rem;
    font-weight: 600;
  }

  .overdue-tag {
    display: flex;
    align-items: center;
    background-color: rgba(255, 255, 255, 0.2);
    padding: 0.375rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.875rem;
    font-weight: 600;
  }

  .overdue-tag i {
    margin-right: 0.5rem;
  }

  .card {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    margin-bottom: 1.5rem;
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

  .info-section {
    display: flex;
    flex-wrap: wrap;
    margin: -0.75rem;
  }

  .info-column {
    flex: 1;
    min-width: 250px;
    padding: 0.75rem;
  }

  .info-row {
    display: flex;
    margin-bottom: 1rem;
  }

  .info-label {
    width: 40%;
    font-weight: 500;
    color: var(--gray-600);
  }

  .info-value {
    width: 60%;
    color: var(--gray-900);
  }

  .related-item {
    display: flex;
    margin-bottom: 0.75rem;
  }

  .related-label {
    width: 30%;
    font-weight: 500;
    color: var(--gray-600);
  }

  .related-value {
    width: 70%;
    color: var(--gray-900);
  }

  .item-details {
    display: flex;
    flex-direction: column;
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

  .total-row td {
    font-weight: 600;
    font-size: 1rem;
    background-color: #f0f9ff;
  }

  .text-right {
    text-align: right;
  }

  .text-danger {
    color: #ef4444;
  }

  .mt-4 {
    margin-top: 1.5rem;
  }

  .payment-summary {
    border: 1px solid var(--gray-200);
    border-radius: 0.5rem;
    padding: 1.5rem;
    background-color: var(--gray-50);
  }

  .payment-info-row {
    display: flex;
    margin-bottom: 1rem;
  }

  .payment-info-row:last-child {
    margin-bottom: 0;
  }

  .payment-label {
    width: 40%;
    font-weight: 500;
    color: var(--gray-600);
  }

  .payment-value {
    width: 60%;
    color: var(--gray-900);
    font-weight: 600;
  }

  .status-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    text-align: center;
    white-space: nowrap;
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

  h3 {
    font-size: 1rem;
    font-weight: 600;
    margin-top: 0;
    margin-bottom: 1rem;
    color: var(--gray-700);
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

  .btn-secondary {
    color: var(--gray-700);
    background-color: var(--gray-100);
    border-color: var(--gray-300);
  }

  .btn-secondary:hover {
    background-color: var(--gray-200);
    border-color: var(--gray-400);
  }

  .btn-warning {
    color: #92400e;
    background-color: #fef3c7;
    border-color: #fde68a;
  }

  .btn-warning:hover {
    background-color: #fde68a;
    color: #78350f;
  }

  .btn-danger {
    color: white;
    background-color: #ef4444;
    border-color: #ef4444;
  }

  .btn-danger:hover {
    background-color: #dc2626;
    border-color: #dc2626;
  }

  .btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
  }

  .btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
  }

  .btn i {
    margin-right: 0.5rem;
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
    max-width: 500px;
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
    border-bottom: 1px solid #e2e8f0;
  }

  .modal-header h2 {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
    color: #1e293b;
  }

  .close-btn {
    background: none;
    border: none;
    color: #64748b;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.25rem;
    border-radius: 0.25rem;
  }

  .close-btn:hover {
    background-color: #f1f5f9;
    color: #0f172a;
  }

  .modal-body {
    padding: 1.5rem;
  }

  .modal-body p {
    margin-top: 0;
    margin-bottom: 1rem;
    color: #1e293b;
  }

  .form-group {
    margin-bottom: 1.5rem;
  }

  .form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--gray-700);
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

  .required {
    color: #ef4444;
  }

  .error-message {
    color: #ef4444;
    font-size: 0.75rem;
    margin-top: 0.25rem;
  }

  .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
  }

  @media (max-width: 768px) {
    .info-section,
    .info-row,
    .related-item,
    .payment-info-row {
      flex-direction: column;
    }

    .info-label,
    .info-value,
    .related-label,
    .related-value,
    .payment-label,
    .payment-value {
      width: 100%;
    }

    .info-label,
    .related-label,
    .payment-label {
      margin-bottom: 0.25rem;
    }

    .info-value,
    .related-value,
    .payment-value {
      margin-bottom: 0.75rem;
    }
  }
  </style>
