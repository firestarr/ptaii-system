<!-- src/views/purchasing/PurchaseOrderTrack.vue -->
<template>
  <div class="po-track-container">
    <div class="page-header">
      <h1>Purchase Order Tracking</h1>
      <div class="action-buttons">
        <router-link :to="`/purchasing/orders/${purchaseOrder.po_id}`" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Back to PO Details
        </router-link>
        <router-link to="/purchasing/orders" class="btn btn-secondary ml-2">
          <i class="fas fa-list"></i> All Purchase Orders
        </router-link>
      </div>
    </div>

    <div v-if="isLoading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="sr-only">Loading...</span>
      </div>
      <p class="mt-2">Loading purchase order data...</p>
    </div>

    <div v-else class="tracking-content">
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Purchase Order Information</h2>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <table class="table table-sm table-borderless detail-table">
                <tbody>
                  <tr>
                    <th>PO Number:</th>
                    <td>{{ purchaseOrder.po_number }}</td>
                  </tr>
                  <tr>
                    <th>PO Date:</th>
                    <td>{{ formatDate(purchaseOrder.po_date) }}</td>
                  </tr>
                  <tr>
                    <th>Expected Delivery:</th>
                    <td>{{ formatDate(purchaseOrder.expected_delivery) || 'Not specified' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="col-md-6">
              <table class="table table-sm table-borderless detail-table">
                <tbody>
                  <tr>
                    <th>Vendor:</th>
                    <td>{{ purchaseOrder.vendor ? purchaseOrder.vendor.name : 'Unknown' }}</td>
                  </tr>
                  <tr>
                    <th>Status:</th>
                    <td>
                      <span class="badge" :class="getStatusBadgeClass(purchaseOrder.status)">
                        {{ purchaseOrder.status }}
                      </span>
                    </td>
                  </tr>
                  <tr>
                    <th>Amount:</th>
                    <td>{{ formatCurrency(purchaseOrder.total_amount) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="card mt-4">
        <div class="card-header">
          <h2 class="card-title">Status Timeline</h2>
        </div>
        <div class="card-body">
          <div class="timeline">
            <div v-for="status in statuses" :key="status.status" class="timeline-step">
              <div :class="['timeline-marker', isStatusPassed(status.status) ? 'completed' : '']">
                <i :class="status.icon"></i>
              </div>
              <div class="timeline-content">
                <h3 class="timeline-title">{{ status.label }}</h3>
                <p v-if="isStatusPassed(status.status) && status.date" class="timeline-date">
                  {{ formatDate(status.date) }}
                </p>
                <p v-if="isCurrentStatus(status.status)" class="timeline-description">
                  {{ status.description }}
                </p>
                <div v-if="isCurrentStatus(status.status) && status.actions" class="timeline-actions">
                  <button 
                    v-for="action in status.actions" 
                    :key="action.action"
                    class="btn btn-sm"
                    :class="action.class"
                    @click="performAction(action.action)"
                  >
                    <i :class="action.icon"></i> {{ action.label }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row mt-4">
        <div class="col-md-7">
          <div class="card">
            <div class="card-header">
              <h2 class="card-title">Item Status</h2>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-bordered">
                  <thead>
                    <tr>
                      <th style="width: 40%">Item</th>
                      <th style="width: 15%">Ordered</th>
                      <th style="width: 15%">Received</th>
                      <th style="width: 15%">Outstanding</th>
                      <th style="width: 15%">Progress</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in itemStatus" :key="item.line_id">
                      <td>{{ item.item_name }}</td>
                      <td>{{ formatNumber(item.ordered_quantity) }}</td>
                      <td>{{ formatNumber(item.received_quantity) }}</td>
                      <td>{{ formatNumber(item.outstanding_quantity) }}</td>
                      <td>
                        <div class="progress">
                          <div 
                            class="progress-bar" 
                            :class="getProgressBarClass(item.percentage)"
                            :style="{ width: `${item.percentage}%` }"
                          >
                            {{ item.percentage }}%
                          </div>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-5">
          <div class="card">
            <div class="card-header">
              <h2 class="card-title">Receipts</h2>
            </div>
            <div class="card-body">
              <div v-if="purchaseOrder.goodsReceipts && purchaseOrder.goodsReceipts.length > 0">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Receipt #</th>
                      <th>Date</th>
                      <th>Status</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="receipt in purchaseOrder.goodsReceipts" :key="receipt.receipt_id">
                      <td>{{ receipt.receipt_number }}</td>
                      <td>{{ formatDate(receipt.receipt_date) }}</td>
                      <td>
                        <span :class="getStatusBadgeClass(receipt.status)">
                          {{ receipt.status }}
                        </span>
                      </td>
                      <td>
                        <router-link :to="`/purchasing/goods-receipts/${receipt.receipt_id}`" class="btn btn-sm btn-info">
                          <i class="fas fa-eye"></i>
                        </router-link>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div v-else class="text-center py-3">
                <i class="fas fa-inbox fa-2x text-muted"></i>
                <p class="mt-2">No receipts created yet</p>
              </div>
            </div>
          </div>

          <div class="card mt-4">
            <div class="card-header">
              <h2 class="card-title">Invoices</h2>
            </div>
            <div class="card-body">
              <div v-if="invoices.length > 0">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Invoice #</th>
                      <th>Date</th>
                      <th>Amount</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="invoice in invoices" :key="invoice.invoice_id">
                      <td>{{ invoice.invoice_number }}</td>
                      <td>{{ formatDate(invoice.invoice_date) }}</td>
                      <td>{{ formatCurrency(invoice.total_amount) }}</td>
                      <td>
                        <span :class="getStatusBadgeClass(invoice.status)">
                          {{ invoice.status }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div v-else class="text-center py-3">
                <i class="fas fa-file-invoice fa-2x text-muted"></i>
                <p class="mt-2">No invoices created yet</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Status Update Confirmation Modal -->
      <div class="modal fade" id="statusModal" tabindex="-1" role="dialog" aria-hidden="true" v-if="showStatusModal">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Update Purchase Order Status</h5>
              <button type="button" class="close" @click="showStatusModal = false">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              Are you sure you want to change the status of this purchase order to <strong>{{ newStatus }}</strong>?
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="showStatusModal = false">Cancel</button>
              <button type="button" class="btn btn-primary" @click="confirmStatusUpdate()">Update Status</button>
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
  name: 'PurchaseOrderTrack',
  data() {
    return {
      isLoading: true,
      purchaseOrder: {},
      itemStatus: [],
      invoices: [],
      statuses: [
        {
          status: 'draft',
          label: 'Draft',
          date: null,
          icon: 'fas fa-pencil-alt',
          description: 'The purchase order is in draft mode and can be edited.',
          actions: [
            { action: 'submit', label: 'Submit', icon: 'fas fa-paper-plane', class: 'btn-primary' }
          ]
        },
        {
          status: 'submitted',
          label: 'Submitted',
          date: null,
          icon: 'fas fa-paper-plane',
          description: 'The purchase order has been submitted for approval.',
          actions: [
            { action: 'approve', label: 'Approve', icon: 'fas fa-check', class: 'btn-success' },
            { action: 'cancel', label: 'Cancel', icon: 'fas fa-times', class: 'btn-danger' }
          ]
        },
        {
          status: 'approved',
          label: 'Approved',
          date: null,
          icon: 'fas fa-check',
          description: 'The purchase order has been approved and is ready to be sent to the vendor.',
          actions: [
            { action: 'sent', label: 'Mark as Sent', icon: 'fas fa-envelope', class: 'btn-info' },
            { action: 'cancel', label: 'Cancel', icon: 'fas fa-times', class: 'btn-danger' }
          ]
        },
        {
          status: 'sent',
          label: 'Sent to Vendor',
          date: null,
          icon: 'fas fa-envelope',
          description: 'The purchase order has been sent to the vendor and is awaiting delivery.',
          actions: [
            { action: 'createReceipt', label: 'Create Receipt', icon: 'fas fa-truck-loading', class: 'btn-primary' },
            { action: 'cancel', label: 'Cancel', icon: 'fas fa-times', class: 'btn-danger' }
          ]
        },
        {
          status: 'partial',
          label: 'Partially Received',
          date: null,
          icon: 'fas fa-truck-loading',
          description: 'Some items have been received, but not all items have been delivered yet.',
          actions: [
            { action: 'createReceipt', label: 'Create Receipt', icon: 'fas fa-truck-loading', class: 'btn-primary' },
            { action: 'cancel', label: 'Cancel', icon: 'fas fa-times', class: 'btn-danger' }
          ]
        },
        {
          status: 'received',
          label: 'Fully Received',
          date: null,
          icon: 'fas fa-check-circle',
          description: 'All items have been received from the vendor.',
          actions: [
            { action: 'complete', label: 'Complete', icon: 'fas fa-flag-checkered', class: 'btn-success' }
          ]
        },
        {
          status: 'completed',
          label: 'Completed',
          date: null,
          icon: 'fas fa-flag-checkered',
          description: 'The purchase order has been fully completed.',
          actions: []
        },
        {
          status: 'canceled',
          label: 'Canceled',
          date: null,
          icon: 'fas fa-ban',
          description: 'The purchase order has been canceled.',
          actions: []
        }
      ],
      showStatusModal: false,
      newStatus: ''
    };
  },
  created() {
    const poId = this.$route.params.id;
    if (poId) {
      this.loadPurchaseOrder(poId);
    } else {
      this.$router.push('/purchasing/orders');
    }
  },
  methods: {
    async loadPurchaseOrder(poId) {
      this.isLoading = true;
      try {
        const response = await axios.get(`/api/purchase-orders/${poId}`);
        
        if (response.data.status === 'success') {
          this.purchaseOrder = response.data.data;
          
          // Load outstanding items
          await this.loadOutstandingItems(poId);
          
          // Load vendor invoices for this PO
          await this.loadInvoices(poId);
          
          // Set dates in the status timeline
          this.updateStatusTimeline();
        }
      } catch (error) {
        console.error('Error loading purchase order:', error);
        alert('Failed to load purchase order data');
      } finally {
        this.isLoading = false;
      }
    },
    async loadOutstandingItems(poId) {
      try {
        const response = await axios.get(`/api/purchase-orders/${poId}/outstanding`);
        
        if (response.data.status === 'success') {
          // Process items to calculate percentage
          const outstandingLines = response.data.data.outstanding_lines || [];
          this.itemStatus = [];
          
          // Calculate received and outstanding for all lines
          if (this.purchaseOrder.lines) {
            this.purchaseOrder.lines.forEach(line => {
              let receivedQuantity = 0;
              
              // Calculate received quantity for this item
              if (this.purchaseOrder.goodsReceipts) {
                this.purchaseOrder.goodsReceipts.forEach(receipt => {
                  if (receipt.lines) {
                    receipt.lines.forEach(receiptLine => {
                      if (receiptLine.po_line_id === line.line_id) {
                        receivedQuantity += parseFloat(receiptLine.received_quantity);
                      }
                    });
                  }
                });
              }
              
              const outstandingQuantity = parseFloat(line.quantity) - receivedQuantity;
              const percentage = line.quantity > 0 ? Math.round((receivedQuantity / line.quantity) * 100) : 0;
              
              this.itemStatus.push({
                line_id: line.line_id,
                item_name: line.item ? line.item.name : 'Unknown Item',
                ordered_quantity: line.quantity,
                received_quantity: receivedQuantity,
                outstanding_quantity: outstandingQuantity,
                percentage: percentage
              });
            });
          }
        }
      } catch (error) {
        console.error('Error loading outstanding items:', error);
      }
    },
    async loadInvoices(poId) {
      try {
        const response = await axios.get(`/api/vendor-invoices?po_id=${poId}`);
        
        if (response.data.status === 'success') {
          this.invoices = response.data.data.data || [];
        }
      } catch (error) {
        console.error('Error loading invoices:', error);
      }
    },
    updateStatusTimeline() {
      // Set dates in the status timeline based on the PO's history
      // This is just a placeholder - in a real application, you would have
      // actual timestamps for each status change stored in the database
      
      const statusOrder = ['draft', 'submitted', 'approved', 'sent', 'partial', 'received', 'completed', 'canceled'];
      const currentIndex = statusOrder.indexOf(this.purchaseOrder.status);
      
      // For simplicity, let's just set dates for all past statuses
      for (let i = 0; i < statusOrder.length; i++) {
        const status = statusOrder[i];
        const statusObj = this.statuses.find(s => s.status === status);
        
        if (i <= currentIndex && statusObj) {
          // Generate a fake date for demonstration purposes
          // In a real app, these would come from the database
          const daysAgo = (currentIndex - i) * 2; // 2 days between statuses
          const date = new Date();
          date.setDate(date.getDate() - daysAgo);
          statusObj.date = date.toISOString();
        }
      }
    },
    isStatusPassed(status) {
      const statusOrder = ['draft', 'submitted', 'approved', 'sent', 'partial', 'received', 'completed', 'canceled'];
      const currentIndex = statusOrder.indexOf(this.purchaseOrder.status);
      const statusIndex = statusOrder.indexOf(status);
      
      // Special case for canceled status
      if (this.purchaseOrder.status === 'canceled') {
        return status === 'canceled';
      }
      
      // Special case for partial
      if (status === 'partial' && this.purchaseOrder.status === 'received') {
        return true;
      }
      
      return statusIndex <= currentIndex;
    },
    isCurrentStatus(status) {
      return this.purchaseOrder.status === status;
    },
    performAction(action) {
      switch (action) {
        case 'submit':
          this.updateStatus('submitted');
          break;
        case 'approve':
          this.updateStatus('approved');
          break;
        case 'sent':
          this.updateStatus('sent');
          break;
        case 'createReceipt':
          this.createGoodsReceipt();
          break;
        case 'complete':
          this.updateStatus('completed');
          break;
        case 'cancel':
          this.updateStatus('canceled');
          break;
      }
    },
    updateStatus(status) {
      this.newStatus = status;
      this.showStatusModal = true;
    },
    async confirmStatusUpdate() {
      try {
        const response = await axios.patch(
          `/api/purchase-orders/${this.purchaseOrder.po_id}/status`,
          { status: this.newStatus }
        );
        
        if (response.data.status === 'success') {
          // Update local data
          this.purchaseOrder.status = this.newStatus;
          
          // Show success message
          alert(`Purchase order status updated to ${this.newStatus}`);
          
          // Reload the purchase order to get the latest data
          this.loadPurchaseOrder(this.purchaseOrder.po_id);
        }
      } catch (error) {
        console.error('Error updating status:', error);
        
        // Show error message
        if (error.response && error.response.data && error.response.data.message) {
          alert(`Error: ${error.response.data.message}`);
        } else {
          alert('An error occurred while updating the purchase order status');
        }
      } finally {
        this.showStatusModal = false;
      }
    },
    createGoodsReceipt() {
      this.$router.push(`/purchasing/goods-receipts/create?po_id=${this.purchaseOrder.po_id}`);
    },
    formatDate(dateString) {
      if (!dateString) return '-';
      const date = new Date(dateString);
      return date.toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    },
    formatCurrency(amount) {
      if (amount === null || amount === undefined) return '-';
      return new Intl.NumberFormat('id-ID', {
        style: 'decimal',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(amount);
    },
    formatNumber(number) {
      if (number === null || number === undefined) return '-';
      return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
      }).format(number);
    },
    getStatusBadgeClass(status) {
      const statusClasses = {
        'draft': 'badge-secondary',
        'submitted': 'badge-info',
        'approved': 'badge-primary',
        'sent': 'badge-warning',
        'partial': 'badge-info',
        'received': 'badge-success',
        'completed': 'badge-success',
        'canceled': 'badge-danger'
      };
      
      return `badge ${statusClasses[status] || 'badge-secondary'}`;
    },
    getProgressBarClass(percentage) {
      if (percentage === 100) {
        return 'bg-success';
      } else if (percentage > 50) {
        return 'bg-info';
      } else if (percentage > 0) {
        return 'bg-warning';
      } else {
        return 'bg-danger';
      }
    }
  }
};
</script>

<style scoped>
.po-track-container {
  margin-bottom: 2rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.card-title {
  margin-bottom: 0;
  font-size: 1.25rem;
}

.detail-table th {
  width: 40%;
  font-weight: 600;
}

.badge {
  padding: 0.5em 0.75em;
  text-transform: capitalize;
}

/* Timeline styles */
.timeline {
  position: relative;
  padding-left: 50px;
}

.timeline-step {
  position: relative;
  padding-bottom: 2.5rem;
}

.timeline-step:last-child {
  padding-bottom: 0;
}

.timeline::before {
  content: '';
  position: absolute;
  top: 0;
  bottom: 0;
  left: 20px;
  width: 2px;
  background-color: #e2e8f0;
}

.timeline-marker {
  position: absolute;
  left: -50px;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background-color: #e2e8f0;
  color: #64748b;
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1;
}

.timeline-marker.completed {
  background-color: #10b981;
  color: white;
}

.timeline-title {
  font-size: 1.125rem;
  margin-bottom: 0.25rem;
  font-weight: 600;
}

.timeline-date {
  font-size: 0.875rem;
  color: #64748b;
  margin-bottom: 0.5rem;
}

.timeline-description {
  color: #1e293b;
  margin-bottom: 0.5rem;
}

.timeline-actions {
  display: flex;
  gap: 0.5rem;
}

.progress {
  height: 1.25rem;
}

.progress-bar {
  line-height: 1.25rem;
  font-size: 0.75rem;
  font-weight: 600;
}

/* Modal styles */
.modal {
  display: block;
  background-color: rgba(0, 0, 0, 0.5);
}
</style>