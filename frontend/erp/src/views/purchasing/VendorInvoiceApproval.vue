<!-- src/views/purchasing/VendorInvoiceApproval.vue -->
<template>
  <div class="vendor-invoice-approval">
    <div class="page-header">
      <h1>Approve Vendor Invoice</h1>
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

    <div v-else-if="invoice.status !== 'pending'" class="alert-card">
      <div class="alert-icon">
        <i class="fas fa-exclamation-circle"></i>
      </div>
      <div class="alert-content">
        <h3>Cannot Approve Invoice</h3>
        <p>This invoice cannot be approved because its current status is <strong>{{ capitalizeFirst(invoice.status) }}</strong>.</p>
        <p>Only invoices with "Pending" status can be approved.</p>
        <router-link :to="`/purchasing/vendor-invoices/${invoice.invoice_id}`" class="btn btn-primary mt-2">
          Return to Invoice Details
        </router-link>
      </div>
    </div>

    <div v-else class="approval-container">
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
              <div class="meta-subtitle">{{ getDueStatus(invoice.due_date) }}</div>
            </div>
            <div class="meta-group">
              <label>Currency</label>
              <div class="meta-value">{{ invoice.currency_code }}</div>
              <div class="meta-subtitle" v-if="invoice.currency_code !== 'USD'">
                Exchange Rate: {{ invoice.exchange_rate }}
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

      <div class="card receipt-verification mt-4">
        <div class="card-header">
          <h2>Receipt Verification</h2>
        </div>
        <div class="card-body">
          <div class="receipt-status">
            <div class="status-icon">
              <i class="fas fa-check-circle"></i>
            </div>
            <div class="status-details">
              <h3>All Items Verified</h3>
              <p>All invoice items have been verified against corresponding goods receipts.</p>
            </div>
          </div>

          <div class="receipts-table">
            <h4>Related Receipts</h4>
            <table class="data-table">
              <thead>
                <tr>
                  <th>Receipt #</th>
                  <th>Receipt Date</th>
                  <th>Status</th>
                  <th>Items</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="receipt in relatedReceipts" :key="receipt.receipt_id">
                  <td>{{ receipt.receipt_number }}</td>
                  <td>{{ formatDate(receipt.receipt_date) }}</td>
                  <td>
                    <span class="status-badge status-success">Confirmed</span>
                  </td>
                  <td>{{ receipt.total_items }}</td>
                  <td>
                    <router-link :to="`/purchasing/goods-receipts/${receipt.receipt_id}`" class="btn-icon" title="View Receipt">
                      <i class="fas fa-eye"></i>
                    </router-link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card accounting-setup mt-4">
        <div class="card-header">
          <h2>Accounting Setup</h2>
        </div>
        <div class="card-body">
          <div class="form-group">
            <div class="checkbox-group">
              <input type="checkbox" id="create_journal_entry" v-model="createJournalEntry" />
              <label for="create_journal_entry">Create Journal Entry</label>
            </div>
          </div>

          <div v-if="createJournalEntry" class="journal-entry-options">
            <div class="form-row">
              <div class="form-group">
                <label for="ap_account_id">Accounts Payable Account</label>
                <select id="ap_account_id" v-model="journalEntry.ap_account_id" required>
                  <option value="" disabled>Select account</option>
                  <option value="AP001">AP001 - Accounts Payable</option>
                  <option value="AP002">AP002 - International Payables</option>
                </select>
              </div>

              <div class="form-group">
                <label for="expense_account_id">Expense Account</label>
                <select id="expense_account_id" v-model="journalEntry.expense_account_id" required>
                  <option value="" disabled>Select account</option>
                  <option value="EXP001">EXP001 - Purchase Expense</option>
                  <option value="EXP002">EXP002 - Inventory Expense</option>
                </select>
              </div>

              <div class="form-group">
                <label for="tax_account_id">Tax Account</label>
                <select id="tax_account_id" v-model="journalEntry.tax_account_id" required>
                  <option value="" disabled>Select account</option>
                  <option value="TAX001">TAX001 - Input VAT</option>
                  <option value="TAX002">TAX002 - Sales Tax</option>
                </select>
              </div>
            </div>

            <div class="journal-preview">
              <h4>Journal Entry Preview</h4>
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Account</th>
                    <th>Description</th>
                    <th>Debit (USD)</th>
                    <th>Credit (USD)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>{{ getAccountName(journalEntry.expense_account_id) }}</td>
                    <td>Expense from {{ invoice.vendor?.name }}</td>
                    <td>{{ formatCurrency(invoice.base_currency_total - invoice.base_currency_tax, 'USD') }}</td>
                    <td>-</td>
                  </tr>
                  <tr v-if="invoice.tax_amount > 0">
                    <td>{{ getAccountName(journalEntry.tax_account_id) }}</td>
                    <td>Tax from {{ invoice.vendor?.name }}</td>
                    <td>{{ formatCurrency(invoice.base_currency_tax, 'USD') }}</td>
                    <td>-</td>
                  </tr>
                  <tr>
                    <td>{{ getAccountName(journalEntry.ap_account_id) }}</td>
                    <td>Payable to {{ invoice.vendor?.name }}</td>
                    <td>-</td>
                    <td>{{ formatCurrency(invoice.base_currency_total, 'USD') }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="2" class="text-right"><strong>Total:</strong></td>
                    <td>{{ formatCurrency(invoice.base_currency_total, 'USD') }}</td>
                    <td>{{ formatCurrency(invoice.base_currency_total, 'USD') }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="card approval-comments mt-4">
        <div class="card-header">
          <h2>Approval Comments</h2>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label for="approval_comments">Comments (Optional)</label>
            <textarea 
              id="approval_comments" 
              v-model="approvalComments" 
              rows="3" 
              placeholder="Add any comments regarding this approval..."
            ></textarea>
          </div>
        </div>
      </div>

      <div class="form-actions mt-4">
        <button type="button" class="btn btn-secondary" @click="cancel">
          Cancel
        </button>
        <button 
          type="button" 
          class="btn btn-danger" 
          @click="rejectInvoice"
          :disabled="approving || rejecting"
        >
          <i v-if="rejecting" class="fas fa-spinner fa-spin"></i>
          Reject
        </button>
        <button 
          type="button" 
          class="btn btn-primary" 
          @click="approveInvoice"
          :disabled="approving || rejecting"
        >
          <i v-if="approving" class="fas fa-spinner fa-spin"></i>
          Approve
        </button>
      </div>

      <!-- Confirm Approval Modal -->
      <div v-if="showApproveModal" class="modal">
        <div class="modal-backdrop" @click="showApproveModal = false"></div>
        <div class="modal-content modal-sm">
          <div class="modal-header">
            <h2>Confirm Approval</h2>
            <button class="close-btn" @click="showApproveModal = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to approve invoice <strong>{{ invoice.invoice_number }}</strong>?</p>
            <p>This will convert it to a payable and allow payment processing.</p>
            
            <div class="form-actions">
              <button type="button" class="btn btn-secondary" @click="showApproveModal = false">
                Cancel
              </button>
              <button type="button" class="btn btn-primary" @click="confirmApprove">
                <i v-if="approving" class="fas fa-spinner fa-spin"></i>
                Confirm Approval
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Confirm Rejection Modal -->
      <div v-if="showRejectModal" class="modal">
        <div class="modal-backdrop" @click="showRejectModal = false"></div>
        <div class="modal-content modal-sm">
          <div class="modal-header">
            <h2>Confirm Rejection</h2>
            <button class="close-btn" @click="showRejectModal = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to reject invoice <strong>{{ invoice.invoice_number }}</strong>?</p>
            <p class="text-warning">
              <i class="fas fa-exclamation-triangle"></i> 
              This will mark the invoice as cancelled and release related receipt lines.
            </p>
            
            <div class="form-group">
              <label for="rejection_reason">Rejection Reason</label>
              <textarea 
                id="rejection_reason" 
                v-model="rejectionReason" 
                rows="3" 
                placeholder="Please provide a reason for rejection..."
                required
              ></textarea>
            </div>
            
            <div class="form-actions">
              <button type="button" class="btn btn-secondary" @click="showRejectModal = false">
                Cancel
              </button>
              <button 
                type="button" 
                class="btn btn-danger" 
                @click="confirmReject"
                :disabled="!rejectionReason.trim()"
              >
                <i v-if="rejecting" class="fas fa-spinner fa-spin"></i>
                Confirm Rejection
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
  name: 'VendorInvoiceApproval',
  data() {
    return {
      loading: true,
      approving: false,
      rejecting: false,
      invoice: null,
      relatedReceipts: [],
      createJournalEntry: true,
      journalEntry: {
        ap_account_id: 'AP001',
        expense_account_id: 'EXP001',
        tax_account_id: 'TAX001'
      },
      approvalComments: '',
      rejectionReason: '',
      showApproveModal: false,
      showRejectModal: false
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
          this.relatedReceipts = response.data.data.receipt_details || [];
          
          // Set counts on receipts
          this.relatedReceipts.forEach(receipt => {
            receipt.total_items = receipt.lines?.length || 0;
          });
        }
      } catch (error) {
        console.error('Error loading invoice:', error);
      } finally {
        this.loading = false;
      }
    },
    approveInvoice() {
      this.showApproveModal = true;
    },
    async confirmApprove() {
      this.approving = true;
      
      try {
        const data = {
          status: 'approved',
          comments: this.approvalComments
        };
        
        if (this.createJournalEntry) {
          data.create_journal_entry = true;
          data.ap_account_id = this.journalEntry.ap_account_id;
          data.expense_account_id = this.journalEntry.expense_account_id;
          data.tax_account_id = this.journalEntry.tax_account_id;
        }
        
        await axios.patch(`/vendor-invoices/${this.invoice.invoice_id}/status`, data);
        
        this.showApproveModal = false;
        
        // Redirect to invoice detail
        this.$router.push(`/purchasing/vendor-invoices/${this.invoice.invoice_id}`);
      } catch (error) {
        console.error('Error approving invoice:', error);
        alert(error.response?.data?.message || 'Error approving invoice');
      } finally {
        this.approving = false;
      }
    },
    rejectInvoice() {
      this.showRejectModal = true;
    },
    async confirmReject() {
      if (!this.rejectionReason.trim()) {
        alert('Please provide a reason for rejection');
        return;
      }
      
      this.rejecting = true;
      
      try {
        await axios.patch(`/vendor-invoices/${this.invoice.invoice_id}/status`, {
          status: 'cancelled',
          comments: this.rejectionReason
        });
        
        this.showRejectModal = false;
        
        // Redirect to invoice detail
        this.$router.push(`/purchasing/vendor-invoices/${this.invoice.invoice_id}`);
      } catch (error) {
        console.error('Error rejecting invoice:', error);
        alert(error.response?.data?.message || 'Error rejecting invoice');
      } finally {
        this.rejecting = false;
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
    getAccountName(accountId) {
      const accounts = {
        'AP001': 'AP001 - Accounts Payable',
        'AP002': 'AP002 - International Payables',
        'EXP001': 'EXP001 - Purchase Expense',
        'EXP002': 'EXP002 - Inventory Expense',
        'TAX001': 'TAX001 - Input VAT',
        'TAX002': 'TAX002 - Sales Tax'
      };
      
      return accounts[accountId] || accountId;
    }
  }
};
</script>

<style scoped>
.vendor-invoice-approval {
  padding: 1rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.approval-container {
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

.receipt-status {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background-color: var(--gray-50);
  border-radius: 0.375rem;
  margin-bottom: 1.5rem;
}

.status-icon {
  font-size: 2rem;
  color: #10b981;
}

.status-details h3 {
  margin: 0 0 0.25rem 0;
  font-size: 1.125rem;
  font-weight: 600;
}

.status-details p {
  margin: 0;
  color: var(--gray-600);
}

.receipts-table {
  margin-top: 1.5rem;
}

.receipts-table h4 {
  font-size: 1rem;
  font-weight: 600;
  margin-bottom: 0.75rem;
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

.form-group {
  margin-bottom: 1rem;
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

.journal-preview {
  margin-top: 1.5rem;
  border-top: 1px solid var(--gray-200);
  padding-top: 1.5rem;
}

.journal-preview h4 {
  font-size: 1rem;
  font-weight: 600;
  margin-bottom: 0.75rem;
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 500;
}

.status-success {
  background-color: #d1fae5;
  color: #065f46;
}

.btn-icon {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: 0.375rem;
  color: var(--gray-500);
  background: none;
  border: 1px solid var(--gray-200);
  cursor: pointer;
  transition: all 0.2s;
}

.btn-icon:hover {
  background-color: var(--gray-100);
  color: var(--gray-800);
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

.btn-danger {
  background-color: var(--danger-color);
  color: white;
  border: none;
}

.btn-danger:hover:not(:disabled) {
  background-color: #b91c1c;
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

.text-warning {
  color: var(--warning-color);
  display: flex;
  align-items: center;
  gap: 0.5rem;
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