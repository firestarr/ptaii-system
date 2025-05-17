<!-- src/views/purchasing/GoodsReceiptDetail.vue -->
<template>
    <div class="goods-receipt-detail">
      <div class="card">
        <div class="card-header">
          <h2>Goods Receipt Details</h2>
          <div class="actions">
            <router-link to="/purchasing/goods-receipts" class="btn btn-secondary">
              <i class="fas fa-arrow-left"></i> Back to List
            </router-link>
            <router-link
              v-if="receipt && receipt.status === 'pending'"
              :to="`/purchasing/goods-receipts/${id}/edit`"
              class="btn btn-primary"
            >
              <i class="fas fa-edit"></i> Edit
            </router-link>
            <router-link
              v-if="receipt && receipt.status === 'pending'"
              :to="`/purchasing/goods-receipts/${id}/confirm`"
              class="btn btn-success"
            >
              <i class="fas fa-check-circle"></i> Confirm
            </router-link>
          </div>
        </div>
  
        <div class="card-body">
          <!-- Loading indicator -->
          <div v-if="loading" class="loading-container">
            <i class="fas fa-spinner fa-spin"></i> Loading receipt details...
          </div>
  
          <!-- Receipt Data -->
          <div v-else>
            <div v-if="receipt && Object.keys(receipt).length > 0">
              <!-- Receipt Header -->
              <div class="receipt-header">
                <div class="receipt-info">
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
                        <span class="label">Status:</span>
                        <span class="value">
                          <span :class="'status-badge ' + receipt.status">
                            {{ receipt.status.charAt(0).toUpperCase() + receipt.status.slice(1) }}
                          </span>
                        </span>
                      </div>
                    </div>
                  </div>
    
                  <div class="info-card">
                    <h3>Vendor Information</h3>
                    <div class="info-grid">
                      <div class="info-item">
                        <span class="label">Vendor:</span>
                        <span class="value">{{ receipt.vendor?.name || 'N/A' }}</span>
                      </div>
                      <div class="info-item">
                        <span class="label">Contact Person:</span>
                        <span class="value">{{ receipt.vendor?.contact_person || 'N/A' }}</span>
                      </div>
                      <div class="info-item">
                        <span class="label">Email:</span>
                        <span class="value">{{ receipt.vendor?.email || 'N/A' }}</span>
                      </div>
                      <div class="info-item">
                        <span class="label">Phone:</span>
                        <span class="value">{{ receipt.vendor?.phone || 'N/A' }}</span>
                      </div>
                    </div>
                  </div>
                </div>
    
                <!-- PO Summary Cards -->
                <div class="po-summary-section">
                  <h3>Related Purchase Orders</h3>
                  <div class="po-cards">
                    <div v-for="po in poSummary" :key="po.po_id" class="po-card">
                      <div class="po-header">
                        <div class="po-title">
                          <h4>{{ po.po_number }}</h4>
                          <span :class="'status-badge ' + po.status">{{ po.status }}</span>
                        </div>
                        <router-link :to="`/purchasing/orders/${po.po_id}`" class="btn-icon view" title="View PO">
                          <i class="fas fa-external-link-alt"></i>
                        </router-link>
                      </div>
                      <div class="po-body">
                        <div class="po-info-item">
                          <span class="label">PO Date:</span>
                          <span class="value">{{ formatDate(po.po_date) }}</span>
                        </div>
                        <div class="po-info-item">
                          <span class="label">Ordered Qty:</span>
                          <span class="value">{{ po.total_ordered }}</span>
                        </div>
                        <div class="po-info-item">
                          <span class="label">Received Qty:</span>
                          <span class="value">{{ po.total_received }}</span>
                        </div>
                        <div class="po-info-item">
                          <span class="label">Outstanding:</span>
                          <span class="value">{{ po.total_outstanding }}</span>
                        </div>
                      </div>
                      <div class="po-footer">
                        <div class="progress-bar">
                          <div class="progress-fill" :style="{ width: po.progress_percentage + '%' }"></div>
                        </div>
                        <div class="progress-text">{{ po.progress_percentage }}% received</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
    
              <!-- Receipt Lines -->
              <div class="receipt-lines-section">
                <h3>Receipt Items</h3>
                
                <div class="table-responsive">
                  <table class="items-table">
                    <thead>
                      <tr>
                        <th>PO Number</th>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Ordered Qty</th>
                        <th>Previously Received</th>
                        <th>Received Qty</th>
                        <th>Outstanding</th>
                        <th>Warehouse</th>
                        <th>Batch Number</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="line in receiptLines" :key="line.line_id">
                        <td>{{ line.po_number }}</td>
                        <td>{{ line.item_code }}</td>
                        <td>{{ line.item_name }}</td>
                        <td>{{ line.ordered_quantity }}</td>
                        <td>{{ line.previously_received }}</td>
                        <td>
                          <span class="highlight">{{ line.received_quantity }}</span>
                        </td>
                        <td>{{ line.outstanding_quantity }}</td>
                        <td>{{ line.warehouse_name }}</td>
                        <td>{{ line.batch_number || 'N/A' }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
    
              <!-- Timeline Section (for confirmed receipts) -->
              <div v-if="receipt.status === 'confirmed'" class="timeline-section">
                <h3>Receipt Timeline</h3>
                
                <div class="timeline">
                  <div class="timeline-item">
                    <div class="timeline-icon created">
                      <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="timeline-content">
                      <h4>Receipt Created</h4>
                      <div class="timeline-info">
                        <p>Receipt number {{ receipt.receipt_number }} was created</p>
                        <span class="timeline-date">{{ formatDate(receipt.created_at || receipt.receipt_date) }}</span>
                      </div>
                    </div>
                  </div>
                  
                  <div class="timeline-item">
                    <div class="timeline-icon confirmed">
                      <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="timeline-content">
                      <h4>Receipt Confirmed</h4>
                      <div class="timeline-info">
                        <p>Items were confirmed received and added to inventory</p>
                        <span class="timeline-date">{{ formatDate(receipt.updated_at || receipt.receipt_date) }}</span>
                      </div>
                    </div>
                  </div>
                </div>
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
    name: 'GoodsReceiptDetail',
    props: {
      id: {
        type: [Number, String],
        required: true
      }
    },
    data() {
      return {
        receipt: {},
        receiptLines: [],
        poSummary: [],
        loading: true
      };
    },
    created() {
      this.fetchReceipt();
    },
    methods: {
      fetchReceipt() {
        this.loading = true;
        
        axios.get(`/goods-receipts/${this.id}`)
          .then(response => {
            const data = response.data.data;
            this.receipt = data.receipt;
            this.receiptLines = data.lines;
            this.poSummary = data.po_summary;
          })
          .catch(error => {
            console.error('Error fetching receipt details:', error);
            const message = error?.response?.data?.error || 'Failed to load receipt details';
            this.$toast.error(message);
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
      }
    }
  };
  </script>
  
  <style scoped>
  .goods-receipt-detail {
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
  
  .btn-primary:hover {
    background-color: var(--primary-dark);
  }
  
  .btn-secondary {
    background-color: var(--gray-200);
    color: var(--gray-700);
    border-color: var(--gray-300);
  }
  
  .btn-secondary:hover {
    background-color: var(--gray-300);
  }
  
  .btn-success {
    background-color: #059669;
    color: white;
    border-color: #059669;
  }
  
  .btn-success:hover {
    background-color: #047857;
  }
  
  .card-body {
    padding: 1.5rem;
  }
  
  .loading-container {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 3rem 0;
    font-size: 1rem;
    color: var(--gray-500);
  }
  
  .loading-container i {
    margin-right: 0.5rem;
  }
  
  .receipt-header {
    margin-bottom: 2rem;
  }
  
  .receipt-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
  }
  
  .info-card {
    padding: 1.5rem;
    border: 1px solid var(--gray-200);
    border-radius: 0.5rem;
    background-color: var(--gray-50);
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
  
  .po-summary-section {
    margin-top: 2rem;
  }
  
  .po-summary-section h3 {
    margin-top: 0;
    margin-bottom: 1rem;
    font-size: 1.125rem;
    color: var(--gray-800);
  }
  
  .po-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
  }
  
  .po-card {
    padding: 1rem;
    border: 1px solid var(--gray-200);
    border-radius: 0.5rem;
    background-color: white;
  }
  
  .po-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
  }
  
  .po-title {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }
  
  .po-title h4 {
    margin: 0;
    font-size: 0.875rem;
    color: var(--gray-800);
  }
  
  .btn-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 0.375rem;
    color: var(--gray-700);
    background-color: var(--gray-100);
    border: 1px solid var(--gray-200);
    text-decoration: none;
    transition: all 0.2s;
  }
  
  .btn-icon:hover {
    background-color: var(--gray-200);
  }
  
  .btn-icon.view {
    color: var(--primary-color);
    background-color: var(--gray-100);
    border-color: var(--primary-color);
  }
  
  .btn-icon.view:hover {
    background-color: #dbeafe;
  }
  
  .po-body {
    margin-bottom: 0.75rem;
  }
  
  .po-info-item {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    margin-bottom: 0.25rem;
  }
  
  .po-info-item .label {
    color: var(--gray-500);
  }
  
  .po-info-item .value {
    color: var(--gray-800);
    font-weight: 500;
  }
  
  .po-footer {
    margin-top: 0.5rem;
  }
  
  .progress-bar {
    height: 0.5rem;
    background-color: var(--gray-200);
    border-radius: 0.25rem;
    overflow: hidden;
    margin-bottom: 0.25rem;
  }
  
  .progress-fill {
    height: 100%;
    background-color: #10b981;
    border-radius: 0.25rem;
  }
  
  .progress-text {
    font-size: 0.75rem;
    color: var(--gray-600);
    text-align: right;
  }
  
  .receipt-lines-section {
    margin-top: 2rem;
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
  
  .highlight {
    font-weight: 600;
    color: var(--primary-color);
  }
  
  .timeline-section {
    margin-top: 2rem;
  }
  
  .timeline-section h3 {
    margin-top: 0;
    margin-bottom: 1rem;
    font-size: 1.125rem;
    color: var(--gray-800);
  }
  
  .timeline {
    position: relative;
    padding-left: 2rem;
    margin-left: 1rem;
  }
  
  .timeline:before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background-color: var(--gray-200);
  }
  
  .timeline-item {
    position: relative;
    padding-bottom: 1.5rem;
  }
  
  .timeline-item:last-child {
    padding-bottom: 0;
  }
  
  .timeline-icon {
    position: absolute;
    left: -1.5rem;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.75rem;
  }
  
  .timeline-icon.created {
    background-color: var(--primary-color);
  }
  
  .timeline-icon.confirmed {
    background-color: #10b981;
  }
  
  .timeline-content {
    position: relative;
    padding: 0.5rem;
  }
  
  .timeline-content h4 {
    margin: 0 0 0.5rem 0;
    font-size: 0.875rem;
    color: var(--gray-800);
  }
  
  .timeline-info {
    font-size: 0.75rem;
    color: var(--gray-600);
  }
  
  .timeline-info p {
    margin: 0 0 0.25rem 0;
  }
  
  .timeline-date {
    font-size: 0.75rem;
    color: var(--gray-500);
  }
  
  @media (max-width: 768px) {
    .card-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }
    
    .actions {
      width: 100%;
      flex-wrap: wrap;
    }
    
    .actions .btn {
      flex: 1;
      justify-content: center;
    }
    
    .receipt-info {
      grid-template-columns: 1fr;
    }
    
    .po-cards {
      grid-template-columns: 1fr;
    }
  }
  </style>