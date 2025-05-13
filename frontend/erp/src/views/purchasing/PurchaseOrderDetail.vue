<!-- src/views/purchasing/PurchaseOrderDetail.vue -->
<template>
  <div class="po-detail-container">
    <div class="page-header">
      <h1>Purchase Order Details</h1>
      <div class="action-buttons">
        <router-link to="/purchasing/orders" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Back to List
        </router-link>
        <div class="btn-group ml-2">
          <button 
            v-if="purchaseOrder.status === 'draft'"
            @click="updateStatus('submitted')" 
            class="btn btn-primary"
          >
            <i class="fas fa-paper-plane"></i> Submit
          </button>
          <button 
            v-if="purchaseOrder.status === 'submitted'"
            @click="updateStatus('approved')" 
            class="btn btn-success"
          >
            <i class="fas fa-check"></i> Approve
          </button>
          <button 
            v-if="purchaseOrder.status === 'approved'"
            @click="updateStatus('sent')" 
            class="btn btn-info"
          >
            <i class="fas fa-envelope"></i> Mark as Sent
          </button>
          <button 
            v-if="['sent', 'partial'].includes(purchaseOrder.status)"
            @click="createGoodsReceipt()" 
            class="btn btn-primary"
          >
            <i class="fas fa-truck-loading"></i> Create Receipt
          </button>
        </div>
        <div class="btn-group ml-2">
          <router-link 
            v-if="purchaseOrder.status === 'draft'"
            :to="`/purchasing/orders/${purchaseOrder.po_id}/edit`" 
            class="btn btn-warning"
          >
            <i class="fas fa-edit"></i> Edit
          </router-link>
          <button 
            @click="printPurchaseOrder()" 
            class="btn btn-secondary"
          >
            <i class="fas fa-print"></i> Print
          </button>
          <button 
            @click="openCurrencyModal" 
            v-if="purchaseOrder.status === 'draft'"
            class="btn btn-info"
          >
            <i class="fas fa-exchange-alt"></i> Convert Currency
          </button>
        </div>
      </div>
    </div>

    <div v-if="isLoading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="sr-only">Loading...</span>
      </div>
      <p class="mt-2">Loading purchase order data...</p>
    </div>

    <div v-else class="po-detail-content">
      <div class="row">
        <div class="col-md-8">
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
                      <tr>
                        <th>Payment Terms:</th>
                        <td>{{ purchaseOrder.payment_terms || 'Not specified' }}</td>
                      </tr>
                      <tr>
                        <th>Delivery Terms:</th>
                        <td>{{ purchaseOrder.delivery_terms || 'Not specified' }}</td>
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
                        <th>Vendor Contact:</th>
                        <td>{{ purchaseOrder.vendor ? purchaseOrder.vendor.contact_person : 'Unknown' }}</td>
                      </tr>
                      <tr>
                        <th>Vendor Email:</th>
                        <td>{{ purchaseOrder.vendor ? purchaseOrder.vendor.email : 'Unknown' }}</td>
                      </tr>
                      <tr>
                        <th>Vendor Phone:</th>
                        <td>{{ purchaseOrder.vendor ? purchaseOrder.vendor.phone : 'Unknown' }}</td>
                      </tr>
                      <tr>
                        <th>Currency:</th>
                        <td>{{ purchaseOrder.currency_code || 'USD' }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h2 class="card-title">Purchase Order Items</h2>
              <span class="badge" :class="getStatusBadgeClass(purchaseOrder.status)">
                {{ purchaseOrder.status }}
              </span>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-bordered">
                  <thead>
                    <tr>
                      <th style="width: 5%">#</th>
                      <th style="width: 40%">Item</th>
                      <th style="width: 15%">Quantity</th>
                      <th style="width: 15%">Unit Price</th>
                      <th style="width: 25%">Subtotal</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(line, index) in purchaseOrder.lines" :key="line.line_id">
                      <td>{{ index + 1 }}</td>
                      <td>
                        {{ line.item ? line.item.name : 'Unknown Item' }}
                        <small class="text-muted d-block">
                          {{ line.item ? line.item.item_code : '' }}
                        </small>
                      </td>
                      <td>
                        {{ formatNumber(line.quantity) }}
                        {{ line.unitOfMeasure ? line.unitOfMeasure.name : '' }}
                      </td>
                      <td>{{ formatCurrency(line.unit_price) }}</td>
                      <td>{{ formatCurrency(line.subtotal) }}</td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr>
                      <td colspan="4" class="text-right font-weight-bold">Subtotal:</td>
                      <td>{{ formatCurrency(calculateSubtotal()) }}</td>
                    </tr>
                    <tr>
                      <td colspan="4" class="text-right font-weight-bold">Tax:</td>
                      <td>{{ formatCurrency(purchaseOrder.tax_amount) }}</td>
                    </tr>
                    <tr>
                      <td colspan="4" class="text-right font-weight-bold">Total:</td>
                      <td class="font-weight-bold">{{ formatCurrency(purchaseOrder.total_amount) }}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
          </div>
        </div>
        
        <div class="col-md-4">
          <div class="card">
            <div class="card-header">
              <h2 class="card-title">Status Information</h2>
            </div>
            <div class="card-body">
              <div class="status-timeline">
                <div 
                  v-for="status in statusTimeline" 
                  :key="status.name"
                  class="timeline-item"
                  :class="{ 'active': isStatusActive(status.name), 'completed': isStatusCompleted(status.name) }"
                >
                  <div class="timeline-marker"></div>
                  <div class="timeline-content">
                    <h3 class="timeline-title">{{ status.label }}</h3>
                    <p v-if="isStatusCompleted(status.name) && status.timestamp" class="timeline-date">
                      {{ formatDate(status.timestamp) }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="card mt-4">
            <div class="card-header">
              <h2 class="card-title">Receipt Information</h2>
            </div>
            <div class="card-body">
              <div v-if="purchaseOrder.goodsReceipts && purchaseOrder.goodsReceipts.length > 0">
                <table class="table table-sm">
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
              <h2 class="card-title">Outstanding Items</h2>
            </div>
            <div class="card-body">
              <div v-if="outstandingItems.length > 0">
                <table class="table table-sm">
                  <thead>
                    <tr>
                      <th>Item</th>
                      <th>Ordered</th>
                      <th>Received</th>
                      <th>Outstanding</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in outstandingItems" :key="item.line_id">
                      <td>{{ item.item_name }}</td>
                      <td>{{ formatNumber(item.ordered_quantity) }}</td>
                      <td>{{ formatNumber(item.received_quantity) }}</td>
                      <td class="font-weight-bold">{{ formatNumber(item.outstanding_quantity) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div v-else class="text-center py-3">
                <i class="fas fa-check-circle fa-2x text-success"></i>
                <p class="mt-2">All items have been received</p>
              </div>
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

    <!-- Currency Conversion Modal -->
    <div class="modal fade" id="currencyModal" tabindex="-1" role="dialog" aria-hidden="true" v-if="showCurrencyModal">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Convert Currency</h5>
            <button type="button" class="close" @click="showCurrencyModal = false">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p>
              Current currency: <strong>{{ purchaseOrder.currency_code || 'USD' }}</strong>
            </p>
            <div class="form-group">
              <label>New Currency</label>
              <select v-model="newCurrency" class="form-control">
                <option value="USD">USD - US Dollar</option>
                <option value="EUR">EUR - Euro</option>
                <option value="GBP">GBP - British Pound</option>
                <option value="IDR">IDR - Indonesian Rupiah</option>
                <option value="JPY">JPY - Japanese Yen</option>
                <option value="CNY">CNY - Chinese Yuan</option>
                <option value="SGD">SGD - Singapore Dollar</option>
              </select>
            </div>
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="useExchangeRateDate" v-model="useExchangeRateDate">
              <label class="form-check-label" for="useExchangeRateDate">
                Use exchange rate from PO date ({{ formatDate(purchaseOrder.po_date) }})
              </label>
            </div>
            <div class="alert alert-info mt-3">
              <i class="fas fa-info-circle"></i>
              Converting currency will recalculate all amounts based on the current exchange rate. This cannot be undone.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showCurrencyModal = false">Cancel</button>
            <button type="button" class="btn btn-primary" @click="convertCurrency()">Convert Currency</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'PurchaseOrderDetail',
  data() {
    return {
      isLoading: true,
      purchaseOrder: {},
      outstandingItems: [],
      showStatusModal: false,
      showCurrencyModal: false,
      newStatus: '',
      newCurrency: 'USD',
      useExchangeRateDate: true,
      statusTimeline: [
        { name: 'draft', label: 'Draft', timestamp: null },
        { name: 'submitted', label: 'Submitted', timestamp: null },
        { name: 'approved', label: 'Approved', timestamp: null },
        { name: 'sent', label: 'Sent to Vendor', timestamp: null },
        { name: 'partial', label: 'Partially Received', timestamp: null },
        { name: 'received', label: 'Fully Received', timestamp: null },
        { name: 'completed', label: 'Completed', timestamp: null }
      ]
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
          
          // Set default currency for conversion
          this.newCurrency = this.purchaseOrder.currency_code || 'USD';
          
          // Load outstanding items
          this.loadOutstandingItems(poId);
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
          this.outstandingItems = response.data.data.outstanding_lines || [];
        }
      } catch (error) {
        console.error('Error loading outstanding items:', error);
      }
    },
    calculateSubtotal() {
      if (!this.purchaseOrder.lines) return 0;
      return this.purchaseOrder.lines.reduce((sum, line) => sum + (line.subtotal || 0), 0);
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
    isStatusActive(status) {
      return this.purchaseOrder.status === status;
    },
    isStatusCompleted(status) {
      const statusOrder = ['draft', 'submitted', 'approved', 'sent', 'partial', 'received', 'completed'];
      const currentIndex = statusOrder.indexOf(this.purchaseOrder.status);
      const statusIndex = statusOrder.indexOf(status);
      
      return statusIndex <= currentIndex;
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
    openCurrencyModal() {
      this.newCurrency = this.purchaseOrder.currency_code || 'USD';
      this.useExchangeRateDate = true;
      this.showCurrencyModal = true;
    },
    async convertCurrency() {
      // Don't convert if selected currency is the same as current
      if (this.newCurrency === this.purchaseOrder.currency_code) {
        alert('Selected currency is the same as current currency');
        this.showCurrencyModal = false;
        return;
      }
      
      try {
        const response = await axios.post(
          `/api/purchase-orders/${this.purchaseOrder.po_id}/convert-currency`,
          {
            currency_code: this.newCurrency,
            use_exchange_rate_date: this.useExchangeRateDate
          }
        );
        
        if (response.data.status === 'success') {
          // Update local data
          this.purchaseOrder = response.data.data;
          
          // Show success message
          alert(`Purchase order currency converted to ${this.newCurrency}`);
        }
      } catch (error) {
        console.error('Error converting currency:', error);
        
        // Show error message
        if (error.response && error.response.data && error.response.data.message) {
          alert(`Error: ${error.response.data.message}`);
        } else {
          alert('An error occurred while converting the currency');
        }
      } finally {
        this.showCurrencyModal = false;
      }
    },
    createGoodsReceipt() {
      this.$router.push(`/purchasing/goods-receipts/create?po_id=${this.purchaseOrder.po_id}`);
    },
    printPurchaseOrder() {
      // Implementation for printing PO
      window.print();
    }
  }
};
</script>

<style scoped>
.po-detail-container {
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
.status-timeline {
  position: relative;
  padding-left: 1.5rem;
}

.status-timeline:before {
  content: '';
  position: absolute;
  top: 0;
  bottom: 0;
  left: 0.6rem;
  width: 2px;
  background-color: #e2e8f0;
}

.timeline-item {
  position: relative;
  padding-bottom: 1.5rem;
}

.timeline-marker {
  position: absolute;
  top: 0.25rem;
  left: -1.5rem;
  width: 1rem;
  height: 1rem;
  border-radius: 50%;
  background-color: #e2e8f0;
  border: 2px solid #fff;
  z-index: 1;
}

.timeline-item.active .timeline-marker {
  background-color: #2563eb;
}

.timeline-item.completed .timeline-marker {
  background-color: #10b981;
}

.timeline-title {
  font-size: 1rem;
  margin-bottom: 0.25rem;
  color: #64748b;
}

.timeline-item.active .timeline-title,
.timeline-item.completed .timeline-title {
  color: #1e293b;
  font-weight: 600;
}

.timeline-date {
  font-size: 0.875rem;
  color: #94a3b8;
  margin: 0;
}

/* Modal styles */
.modal {
  display: block;
  background-color: rgba(0, 0, 0, 0.5);
}

/* Print styles */
@media print {
  .action-buttons, 
  .btn, 
  .page-header {
    display: none !important;
  }
  
  .card {
    border: none !important;
    box-shadow: none !important;
  }
  
  .card-header {
    background-color: transparent !important;
    border-bottom: 1px solid #000 !important;
  }
}
</style>