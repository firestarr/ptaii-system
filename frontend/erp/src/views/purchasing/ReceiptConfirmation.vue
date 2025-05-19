<!-- src/views/purchasing/ReceiptConfirmation.vue -->
<template>
    <div class="receipt-confirmation">
      <div class="card">
        <div class="card-header">
          <h2>Confirm Goods Receipt</h2>
          <div class="actions">
            <router-link :to="`/purchasing/goods-receipts/${receiptId}`" class="btn btn-secondary">
              <i class="fas fa-arrow-left"></i> Back to Details
            </router-link>
          </div>
        </div>
  
        <div class="card-body">
          <!-- Loading indicator -->
          <div v-if="loading" class="loading-container">
            <i class="fas fa-spinner fa-spin"></i> Loading receipt details...
          </div>
  
          <!-- Error state -->
          <div v-else-if="error" class="error-container">
            <i class="fas fa-exclamation-circle"></i>
            <h3>Error Loading Receipt</h3>
            <p>{{ error }}</p>
            <button @click="fetchReceipt" class="btn btn-primary">
              <i class="fas fa-sync"></i> Try Again
            </button>
          </div>
  
          <!-- Not pending state -->
          <div v-else-if="receipt && receipt.status !== 'pending'" class="not-pending">
            <i class="fas fa-info-circle"></i>
            <h3>Receipt Already Confirmed</h3>
            <p>This goods receipt has already been confirmed and can't be confirmed again.</p>
            <router-link :to="`/purchasing/goods-receipts/${receiptId}`" class="btn btn-primary">
              View Details
            </router-link>
          </div>
  
          <!-- Confirmation view -->
          <div v-else-if="receipt">
            <div class="confirmation-header">
              <div class="alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                  <p><strong>Important:</strong> Confirming this receipt will:</p>
                  <ul>
                    <li>Increase inventory levels for the received items</li>
                    <li>Update the status of related purchase orders</li>
                    <li>Make the receipt permanent and non-editable</li>
                  </ul>
                  <p>Please review all information before confirming.</p>
                </div>
              </div>
            </div>
  
            <!-- Receipt summary -->
            <div class="receipt-summary">
              <div class="info-card">
                <h3>Receipt Information</h3>
                <div class="info-grid">
                  <div class="info-item">
                    <span class="label">Receipt Number:</span>
                    <span class="value">{{ receipt.receipt_number }}</span>
                  </div>
                  <div class="info-item">
                    <span class="label">Receipt Date:</span>
                    <span class="value">{{ formatDate(receipt.receipt_date) }}</span>
                  </div>
                  <div class="info-item">
                    <span class="label">Vendor:</span>
                    <span class="value">{{ receipt.vendor.name }}</span>
                  </div>
                </div>
              </div>
            </div>
  
            <!-- Item summary -->
            <div class="receipt-lines-section">
              <h3>Receipt Items</h3>
              
              <div class="table-responsive">
                <table class="items-table">
                  <thead>
                    <tr>
                      <th>PO Number</th>
                      <th>Item Code</th>
                      <th>Item Name</th>
                      <th>Received Qty</th>
                      <th>Warehouse</th>
                      <th>Batch Number</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="line in receiptLines" :key="line.line_id">
                      <td>{{ line.po_number }}</td>
                      <td>{{ line.item_code }}</td>
                      <td>{{ line.item_name }}</td>
                      <td>
                        <span class="highlight">{{ line.received_quantity }}</span>
                      </td>
                      <td>{{ line.warehouse_name }}</td>
                      <td>{{ line.batch_number || 'N/A' }}</td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr>
                      <td colspan="3" class="total-label">Total Items:</td>
                      <td colspan="3" class="total-value">{{ receipt.lines ? receipt.lines.length : receiptLines.length }}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
  
            <!-- PO Summary -->
            <div class="po-summary-section">
              <h3>Related Purchase Orders</h3>
              
              <div class="po-status-summary">
                <div v-for="po in poSummary" :key="po.po_id" class="po-status-item">
                  <span class="po-number">{{ po.po_number }}</span>
                  <div class="status-transition">
                    <span :class="'status-badge ' + po.status">{{ po.status }}</span>
                    <i class="fas fa-long-arrow-alt-right"></i>
                    <span :class="'status-badge ' + getNewPOStatus(po)">{{ getNewPOStatus(po) }}</span>
                  </div>
                </div>
              </div>
            </div>
  
            <!-- Confirmation buttons -->
            <div class="confirmation-actions">
              <button type="button" class="btn btn-secondary" @click="cancel">
                Cancel
              </button>
              <button 
                type="button" 
                class="btn btn-success" 
                @click="confirmReceipt"
                :disabled="confirming"
              >
                <i v-if="confirming" class="fas fa-spinner fa-spin"></i>
                <span v-else><i class="fas fa-check-circle"></i> Confirm Receipt</span>
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
    name: 'ReceiptConfirmation',
    props: {
      receiptId: {
        type: [Number, String],
        required: true
      }
    },
    data() {
      return {
        receipt: null,
        receiptLines: [],
        poSummary: [],
        loading: true,
        confirming: false,
        error: null
      };
    },
    created() {
      this.fetchReceipt();
    },
    methods: {
      fetchReceipt() {
        this.loading = true;
        this.error = null;
        
        axios.get(`/goods-receipts/${this.receiptId}`)
          .then(response => {
            const data = response.data.data;
            this.receipt = data.receipt;
            this.receiptLines = data.lines;
            this.poSummary = data.po_summary;
          })
          .catch(error => {
            console.error('Error fetching receipt details:', error);
            this.error = error.response?.data?.message || 'Failed to load receipt details';
          })
          .finally(() => {
            this.loading = false;
          });
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
      getNewPOStatus(po) {
        // If already fully received, status won't change
        if (po.status === 'received') {
          return 'received';
        }
        
        // Calculate what the new status will be after confirmation
        const ordered = po.total_ordered;
        const received = po.total_received;
        
        // Calculate how much will be received in this receipt for this PO
        const currentlyReceiving = this.receiptLines
          .filter(line => line.po_id === po.po_id)
          .reduce((sum, line) => sum + line.received_quantity, 0);
        
        const totalReceived = received + currentlyReceiving;
        
        // Determine new status
        if (totalReceived >= ordered) {
          return 'received';
        } else if (totalReceived > 0) {
          return 'partial';
        }
        
        return po.status;
      },
      confirmReceipt() {
        this.confirming = true;
        
        axios.post(`/goods-receipts/${this.receiptId}/confirm`)
          .then(() => {
            this.$toast.success('Goods receipt confirmed successfully');
            this.$router.push(`/purchasing/goods-receipts/${this.receiptId}`);
          })
          .catch(error => {
            console.error('Error confirming receipt:', error);
            this.$toast.error('Failed to confirm goods receipt: ' + (error.response?.data?.message || 'Unknown error'));
          })
          .finally(() => {
            this.confirming = false;
          });
      },
      cancel() {
        this.$router.push(`/purchasing/goods-receipts/${this.receiptId}`);
      }
    }
  };
  </script>
  
  <style scoped>
  .receipt-confirmation {
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
  
  .btn-secondary {
    background-color: var(--gray-200);
    color: var(--gray-700);
    border-color: var(--gray-300);
  }
  
  .btn-secondary:hover:not(:disabled) {
    background-color: var(--gray-300);
  }
  
  .btn-success {
    background-color: #059669;
    color: white;
    border-color: #059669;
  }
  
  .btn-success:hover:not(:disabled) {
    background-color: #047857;
  }
  
  .btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
  }
  
  .card-body {
    padding: 1.5rem;
  }
  
  .loading-container,
  .error-container,
  .not-pending {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 0;
    text-align: center;
  }
  
  .loading-container i,
  .error-container i,
  .not-pending i {
    font-size: 2rem;
    margin-bottom: 1rem;
    color: var(--gray-500);
  }
  
  .error-container i {
    color: #dc2626;
  }
  
  .not-pending i {
    color: #0ea5e9;
  }
  
  .error-container h3,
  .not-pending h3 {
    margin-top: 0;
    margin-bottom: 0.5rem;
    font-size: 1.25rem;
  }
  
  .error-container p,
  .not-pending p {
    margin-bottom: 1.5rem;
  color: var(--gray-600);
  max-width: 500px;
}

.confirmation-header {
  margin-bottom: 2rem;
}

.alert-info {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  background-color: #dbeafe;
  border: 1px solid #93c5fd;
  border-radius: 0.5rem;
  color: #1e40af;
}

.alert-info i {
  font-size: 1.5rem;
}

.alert-info p {
  margin: 0 0 0.5rem 0;
}

.alert-info ul {
  margin: 0 0 0.5rem 0;
  padding-left: 1.5rem;
}

.receipt-summary {
  margin-bottom: 2rem;
  display: flex;
  flex-wrap: wrap;
  gap: 1.5rem;
}

.info-card {
  padding: 1.5rem;
  border: 1px solid var(--gray-200);
  border-radius: 0.5rem;
  background-color: var(--gray-50);
  flex: 1;
  min-width: 300px;
}

.info-card h3 {
  margin-top: 0;
  margin-bottom: 1rem;
  font-size: 1.125rem;
  color: var(--gray-800);
}

.info-grid {
  display: grid;
  gap: 0.75rem;
}

.info-item {
  display: flex;
  flex-direction: column;
}

.info-item .label {
  font-size: 0.75rem;
  color: var(--gray-500);
  margin-bottom: 0.25rem;
}

.info-item .value {
  font-size: 0.875rem;
  color: var(--gray-800);
  font-weight: 500;
}

.receipt-lines-section {
  margin-bottom: 2rem;
}

.receipt-lines-section h3 {
  margin-top: 0;
  margin-bottom: 1rem;
  font-size: 1.125rem;
  color: var(--gray-800);
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

.items-table tfoot td {
  border-top: 2px solid var(--gray-300);
  font-weight: 600;
}

.total-label {
  text-align: right;
}

.total-value {
  font-weight: 700;
}

.highlight {
  font-weight: 600;
  color: var(--primary-color);
}

.po-summary-section {
  margin-bottom: 2rem;
}

.po-summary-section h3 {
  margin-top: 0;
  margin-bottom: 1rem;
  font-size: 1.125rem;
  color: var(--gray-800);
}

.po-status-summary {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
}

.po-status-item {
  flex: 1;
  min-width: 250px;
  padding: 1rem;
  border: 1px solid var(--gray-200);
  border-radius: 0.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.po-number {
  font-weight: 600;
  font-size: 0.875rem;
  color: var(--gray-800);
}

.status-transition {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.status-transition i {
  color: var(--gray-400);
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: capitalize;
}

.status-badge.pending {
  background-color: #fef3c7;
  color: #92400e;
}

.status-badge.confirmed {
  background-color: #d1fae5;
  color: #065f46;
}

.status-badge.sent {
  background-color: #dbeafe;
  color: #1e40af;
}

.status-badge.partial {
  background-color: #fef3c7;
  color: #92400e;
}

.status-badge.received {
  background-color: #d1fae5;
  color: #065f46;
}

.confirmation-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 2rem;
}

@media (max-width: 768px) {
  .card-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }
  
  .actions {
    width: 100%;
  }
  
  .actions .btn {
    flex: 1;
    justify-content: center;
  }
  
  .alert-info {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .confirmation-actions {
    flex-direction: column;
  }
  
  .confirmation-actions button {
    width: 100%;
  }
}
</style>