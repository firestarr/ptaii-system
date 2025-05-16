<!-- src/views/purchasing/VendorInvoiceDetail.vue -->
<template>
  <div class="vendor-invoice-detail">
    <div class="page-header">
      <h1>Vendor Invoice Details</h1>
      <div class="actions">
        <router-link to="/purchasing/vendor-invoices" class="btn btn-outline">
          <i class="fas fa-arrow-left"></i> Back to List
        </router-link>
        <div class="action-dropdown" ref="actionMenu">
          <button class="btn btn-secondary" @click="toggleActionMenu">
            <i class="fas fa-ellipsis-v"></i> Actions
          </button>
          <div v-if="showActionMenu" class="dropdown-menu">
            <router-link v-if="invoice?.status === 'pending'" 
              :to="`/purchasing/vendor-invoices/${invoice?.invoice_id}/edit`" 
              class="dropdown-item">
              <i class="fas fa-edit"></i> Edit Invoice
            </router-link>
            <router-link v-if="invoice?.status === 'pending'" 
              :to="`/purchasing/vendor-invoices/${invoice?.invoice_id}/approve`" 
              class="dropdown-item">
              <i class="fas fa-check-circle"></i> Approve Invoice
            </router-link>
            <router-link v-if="invoice?.status === 'approved'" 
              :to="`/purchasing/vendor-invoices/${invoice?.invoice_id}/payment`" 
              class="dropdown-item">
              <i class="fas fa-money-bill-wave"></i> Process Payment
            </router-link>
            <button v-if="invoice?.status !== 'paid'" 
              @click="confirmStatusChange('cancelled')" 
              class="dropdown-item">
              <i class="fas fa-ban"></i> Cancel Invoice
            </button>
            <button v-if="invoice?.status === 'pending' || invoice?.status === 'cancelled'" 
              @click="confirmDelete" 
              class="dropdown-item">
              <i class="fas fa-trash"></i> Delete Invoice
            </button>
            <div class="dropdown-divider"></div>
            <button @click="printInvoice" class="dropdown-item">
              <i class="fas fa-print"></i> Print Invoice
            </button>
          </div>
        </div>
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

    <div v-else class="invoice-container">
      <div class="invoice-header-card">
        <div class="status-bar" :class="getStatusClass(invoice.status)">
          <span class="status-label">{{ capitalizeFirst(invoice.status) }}</span>
          <div class="status-actions" v-if="invoice.status === 'pending'">
            <button @click="confirmStatusChange('approved')" class="btn btn-sm btn-light">
              <i class="fas fa-check"></i> Approve
            </button>
          </div>
          <div class="status-actions" v-else-if="invoice.status === 'approved'">
            <button @click="$router.push(`/purchasing/vendor-invoices/${invoice.invoice_id}/payment`)" class="btn btn-sm btn-light">
              <i class="fas fa-money-bill-wave"></i> Process Payment
            </button>
          </div>
        </div>

        <div class="card-body">
          <div class="invoice-header">
            <div class="invoice-title">
              <h2>Invoice: {{ invoice.invoice_number }}</h2>
              <span class="invoice-date">Date: {{ formatDate(invoice.invoice_date) }}</span>
            </div>
            <div class="invoice-amount">
              <div class="amount-value">{{ formatCurrency(invoice.total_amount, invoice.currency_code) }}</div>
              <div v-if="invoice.currency_code !== 'USD'" class="amount-converted">
                {{ formatCurrency(invoice.base_currency_total, 'USD') }} (USD)
              </div>
            </div>
          </div>

          <div class="invoice-info-grid">
            <div class="info-group">
              <label>Vendor</label>
              <div>{{ invoice.vendor?.name }}</div>
              <div class="text-secondary">{{ invoice.vendor?.vendor_code }}</div>
            </div>

            <div class="info-group">
              <label>Due Date</label>
              <div>{{ formatDate(invoice.due_date) }}</div>
              <div class="text-secondary">{{ getDueStatus(invoice.due_date) }}</div>
            </div>

            <div class="info-group">
              <label>Currency</label>
              <div>{{ invoice.currency_code }}</div>
              <div class="text-secondary" v-if="invoice.currency_code !== 'USD'">
                Rate: {{ invoice.exchange_rate }} (to USD)
              </div>
            </div>

            <div class="info-group">
              <label>Amounts</label>
              <div>Subtotal: {{ formatCurrency(invoice.total_amount - invoice.tax_amount, invoice.currency_code) }}</div>
              <div>Tax: {{ formatCurrency(invoice.tax_amount, invoice.currency_code) }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="tab-container">
        <div class="tab-header">
          <button 
            @click="currentTab = 'lines'" 
            :class="{ active: currentTab === 'lines' }" 
            class="tab-button">
            Invoice Lines
          </button>
          <button 
            @click="currentTab = 'receipts'" 
            :class="{ active: currentTab === 'receipts' }" 
            class="tab-button">
            Related Receipts
          </button>
          <button 
            @click="currentTab = 'journal'" 
            :class="{ active: currentTab === 'journal' }" 
            class="tab-button">
            Journal Entry
          </button>
          <button 
            @click="currentTab = 'payments'" 
            :class="{ active: currentTab === 'payments' }" 
            class="tab-button">
            Payments
          </button>
        </div>

        <div class="tab-content">
          <!-- Invoice Lines Tab -->
          <div v-if="currentTab === 'lines'" class="tab-pane">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>PO Number</th>
                  <th>Quantity</th>
                  <th>Unit Price</th>
                  <th>Subtotal</th>
                  <th>Tax</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="line in invoice.lines" :key="line.line_id">
                  <td>
                    <div>{{ line.item?.item_code || 'N/A' }}</div>
                    <div class="text-secondary">{{ line.item?.name || 'N/A' }}</div>
                  </td>
                  <td>
                    <div v-if="line.purchaseOrderLine">
                      {{ line.purchaseOrderLine.purchaseOrder?.po_number || 'N/A' }}
                    </div>
                    <div v-else>N/A</div>
                  </td>
                  <td>{{ line.quantity }}</td>
                  <td>{{ formatCurrency(line.unit_price, invoice.currency_code) }}</td>
                  <td>{{ formatCurrency(line.subtotal, invoice.currency_code) }}</td>
                  <td>{{ formatCurrency(line.tax, invoice.currency_code) }}</td>
                  <td>{{ formatCurrency(line.total, invoice.currency_code) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="4" class="text-right"><strong>Total:</strong></td>
                  <td>{{ formatCurrency(invoice.total_amount - invoice.tax_amount, invoice.currency_code) }}</td>
                  <td>{{ formatCurrency(invoice.tax_amount, invoice.currency_code) }}</td>
                  <td>{{ formatCurrency(invoice.total_amount, invoice.currency_code) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>

          <!-- Related Receipts Tab -->
          <div v-if="currentTab === 'receipts'" class="tab-pane">
            <div v-if="!receiptDetails || receiptDetails.length === 0" class="empty-tab-state">
              <i class="fas fa-truck-loading"></i>
              <h3>No Receipt Details</h3>
              <p>There are no receipt details available for this invoice.</p>
            </div>
            <div v-else>
              <div v-for="receipt in receiptDetails" :key="receipt.receipt_id" class="receipt-detail-card">
                <div class="receipt-header">
                  <h4>{{ receipt.receipt_number }} ({{ formatDate(receipt.receipt_date) }})</h4>
                  <router-link :to="`/purchasing/goods-receipts/${receipt.receipt_id}`" class="btn-link">
                    <i class="fas fa-external-link-alt"></i> View Receipt
                  </router-link>
                </div>
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Item</th>
                      <th>PO Number</th>
                      <th>Received Qty</th>
                      <th>Invoiced Qty</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="line in receipt.lines" :key="line.line_id">
                      <td>
                        <div>{{ line.item?.item_code || 'N/A' }}</div>
                        <div class="text-secondary">{{ line.item?.name || 'N/A' }}</div>
                      </td>
                      <td>
                        <div v-if="line.purchaseOrderLine">
                          {{ line.purchaseOrderLine.purchaseOrder?.po_number || 'N/A' }}
                        </div>
                        <div v-else>N/A</div>
                      </td>
                      <td>{{ line.received_quantity }}</td>
                      <td>{{ line.received_quantity }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Journal Entry Tab -->
          <div v-if="currentTab === 'journal'" class="tab-pane">
            <div class="empty-tab-state">
              <i class="fas fa-book"></i>
              <h3>No Journal Entry</h3>
              <p>No journal entry has been created for this invoice.</p>
              <button class="btn btn-primary">
                <i class="fas fa-plus"></i> Create Journal Entry
              </button>
            </div>

            <!-- Journal entry details would go here once implemented -->
          </div>

          <!-- Payments Tab -->
          <div v-if="currentTab === 'payments'" class="tab-pane">
            <div class="empty-tab-state">
              <i class="fas fa-money-bill-wave"></i>
              <h3>No Payments</h3>
              <p>This invoice has not been paid yet.</p>
              <button v-if="invoice.status === 'approved'" 
                @click="$router.push(`/purchasing/vendor-invoices/${invoice.invoice_id}/payment`)" 
                class="btn btn-primary">
                <i class="fas fa-plus"></i> Process Payment
              </button>
            </div>

            <!-- Payment details would go here once implemented -->
          </div>
        </div>
      </div>
    </div>

    <!-- Status Change Confirmation Modal -->
    <div v-if="showStatusModal" class="modal">
      <div class="modal-backdrop" @click="showStatusModal = false"></div>
      <div class="modal-content modal-sm">
        <div class="modal-header">
          <h2>Confirm Status Change</h2>
          <button class="close-btn" @click="showStatusModal = false">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to change the status to <strong>{{ capitalizeFirst(newStatus) }}</strong>?</p>
          <p v-if="newStatus === 'cancelled'" class="text-warning">
            <i class="fas fa-exclamation-triangle"></i> 
            This will release all allocations and free up receipt lines for other invoices.
          </p>
          <div class="form-actions">
            <button type="button" class="btn btn-secondary" @click="showStatusModal = false">
              Cancel
            </button>
            <button type="button" 
              :class="newStatus === 'cancelled' ? 'btn btn-danger' : 'btn btn-primary'" 
              @click="updateStatus">
              Confirm
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="modal">
      <div class="modal-backdrop" @click="showDeleteModal = false"></div>
      <div class="modal-content modal-sm">
        <div class="modal-header">
          <h2>Confirm Deletion</h2>
          <button class="close-btn" @click="showDeleteModal = false">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete invoice <strong>{{ invoice?.invoice_number }}</strong>?</p>
          <p class="text-warning">
            <i class="fas fa-exclamation-triangle"></i> 
            This action cannot be undone.
          </p>
          <div class="form-actions">
            <button type="button" class="btn btn-secondary" @click="showDeleteModal = false">
              Cancel
            </button>
            <button type="button" class="btn btn-danger" @click="deleteInvoice">
              Delete
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
  name: 'VendorInvoiceDetail',
  data() {
    return {
      loading: true,
      invoice: null,
      receiptDetails: [],
      currentTab: 'lines',
      showActionMenu: false,
      showStatusModal: false,
      showDeleteModal: false,
      newStatus: ''
    };
  },
  created() {
    this.loadInvoice();
    
    // Close action menu when clicking outside
    document.addEventListener('click', this.closeActionMenu);
  },
  beforeUnmount() {
    document.removeEventListener('click', this.closeActionMenu);
  },
  methods: {
    async loadInvoice() {
      try {
        const invoiceId = this.$route.params.id;
        const response = await axios.get(`/vendor-invoices/${invoiceId}`);
        
        if (response.data.status === 'success') {
          this.invoice = response.data.data.invoice;
          this.receiptDetails = response.data.data.receipt_details || [];
        }
      } catch (error) {
        console.error('Error loading invoice:', error);
      } finally {
        this.loading = false;
      }
    },
    toggleActionMenu() {
      this.showActionMenu = !this.showActionMenu;
    },
    closeActionMenu(event) {
      if (this.$refs.actionMenu && !this.$refs.actionMenu.contains(event.target)) {
        this.showActionMenu = false;
      }
    },
    confirmStatusChange(status) {
      this.newStatus = status;
      this.showStatusModal = true;
      this.showActionMenu = false;
    },
    async updateStatus() {
      try {
        const response = await axios.patch(`/vendor-invoices/${this.invoice.invoice_id}/status`, {
          status: this.newStatus
        });
        
        if (response.data.status === 'success') {
          this.showStatusModal = false;
          await this.loadInvoice();
        }
      } catch (error) {
        console.error('Error updating status:', error);
        alert(error.response?.data?.message || 'Error updating status');
      }
    },
    confirmDelete() {
      this.showDeleteModal = true;
      this.showActionMenu = false;
    },
    async deleteInvoice() {
      try {
        await axios.delete(`/vendor-invoices/${this.invoice.invoice_id}`);
        this.showDeleteModal = false;
        this.$router.push('/purchasing/vendor-invoices');
      } catch (error) {
        console.error('Error deleting invoice:', error);
        alert(error.response?.data?.message || 'Error deleting invoice');
      }
    },
    printInvoice() {
      window.print();
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
    getStatusClass(status) {
      const statusClasses = {
        pending: 'status-pending',
        approved: 'status-approved',
        paid: 'status-paid',
        cancelled: 'status-cancelled'
      };
      
      return statusClasses[status] || '';
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
    }
  }
};
</script>

<style scoped>
.vendor-invoice-detail {
  padding: 1rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.invoice-container {
  max-width: 1200px;
}

.invoice-header-card {
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  margin-bottom: 1.5rem;
  overflow: hidden;
}

.status-bar {
  padding: 0.75rem 1.5rem;
  color: white;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.status-pending {
  background-color: #f59e0b;
}

.status-approved {
  background-color: #10b981;
}

.status-paid {
  background-color: #3b82f6;
}

.status-cancelled {
  background-color: #ef4444;
}

.status-label {
  font-weight: 600;
  font-size: 1rem;
}

.card-body {
  padding: 1.5rem;
}

.invoice-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
}

.invoice-title h2 {
  margin: 0 0 0.5rem 0;
  font-size: 1.5rem;
  font-weight: 600;
}

.invoice-date {
  color: var(--gray-500);
  font-size: 0.875rem;
}

.invoice-amount {
  text-align: right;
}

.amount-value {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--gray-800);
}

.amount-converted {
  font-size: 0.875rem;
  color: var(--gray-500);
  margin-top: 0.25rem;
}

.invoice-info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
}

.info-group {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.info-group label {
  font-size: 0.75rem;
  color: var(--gray-500);
  font-weight: 500;
  text-transform: uppercase;
}

.text-secondary {
  color: var(--gray-500);
  font-size: 0.875rem;
}

.tab-container {
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.tab-header {
  display: flex;
  border-bottom: 1px solid var(--gray-200);
  overflow-x: auto;
}

.tab-button {
  padding: 1rem 1.5rem;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--gray-600);
  cursor: pointer;
  white-space: nowrap;
}

.tab-button:hover {
  color: var(--primary-color);
}

.tab-button.active {
  color: var(--primary-color);
  border-bottom-color: var(--primary-color);
}

.tab-content {
  padding: 1.5rem;
}

.tab-pane {
  min-height: 300px;
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

.empty-tab-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 0;
  text-align: center;
}

.empty-tab-state i {
  font-size: 2.5rem;
  color: var(--gray-300);
  margin-bottom: 1rem;
}

.empty-tab-state h3 {
  font-size: 1.125rem;
  color: var(--gray-700);
  margin-bottom: 0.5rem;
}

.empty-tab-state p {
  color: var(--gray-500);
  max-width: 24rem;
  margin-bottom: 1rem;
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
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.receipt-header h4 {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
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

.btn-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
}

.btn-primary {
  background-color: var(--primary-color);
  color: white;
  border: none;
}

.btn-primary:hover {
  background-color: var(--primary-dark);
}

.btn-secondary {
  background-color: var(--gray-200);
  color: var(--gray-700);
  border: none;
}

.btn-secondary:hover {
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

.btn-light {
  background-color: rgba(255, 255, 255, 0.2);
  color: white;
  border: 1px solid rgba(255, 255, 255, 0.3);
}

.btn-light:hover {
  background-color: rgba(255, 255, 255, 0.3);
}

.btn-danger {
  background-color: var(--danger-color);
  color: white;
  border: none;
}

.btn-danger:hover {
  background-color: #b91c1c;
}

.btn-link {
  color: var(--primary-color);
  text-decoration: none;
  font-size: 0.875rem;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}

.btn-link:hover {
  text-decoration: underline;
}

.action-dropdown {
  position: relative;
}

.dropdown-menu {
  position: absolute;
  top: 100%;
  right: 0;
  margin-top: 0.5rem;
  background-color: white;
  border-radius: 0.375rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  min-width: 12rem;
  z-index: 20;
}

.dropdown-item {
  padding: 0.75rem 1rem;
  display: flex;
  align-items: center;
  text-decoration: none;
  color: var(--gray-700);
  font-size: 0.875rem;
  transition: background-color 0.2s;
  cursor: pointer;
  border: none;
  background: none;
  width: 100%;
  text-align: left;
}

.dropdown-item i {
  width: 1.25rem;
  margin-right: 0.75rem;
}

.dropdown-item:hover {
  background-color: var(--gray-100);
}

.dropdown-divider {
  height: 1px;
  background-color: var(--gray-200);
  margin: 0.25rem 0;
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

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 1.5rem;
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
  
  .invoice-header {
    flex-direction: column;
    gap: 1rem;
  }
  
  .invoice-amount {
    text-align: left;
  }
  
  .tab-header {
    overflow-x: auto;
  }
  
  .form-actions {
    flex-direction: column;
  }
  
  .btn {
    width: 100%;
  }
}

@media print {
  .page-header, 
  .tab-header, 
  .actions, 
  .status-actions {
    display: none !important;
  }
  
  .vendor-invoice-detail {
    padding: 0;
  }
  
  .invoice-header-card,
  .tab-container {
    box-shadow: none;
    margin-bottom: 2rem;
  }
  
  .tab-content {
    display: block !important;
  }
  
  .tab-pane {
    display: block !important;
    page-break-after: always;
  }
}
</style>