<!-- src/views/purchasing/VendorQuotationDetail.vue -->
<template>
    <div class="quotation-detail-container">
      <div class="page-header">
        <div class="header-left">
          <h1>Vendor Quotation Detail</h1>
          <span 
            class="status-badge" 
            :class="statusClass"
          >
            {{ capitalizeFirstLetter(quotation.status) }}
          </span>
        </div>
        <div class="header-actions">
          <router-link to="/purchasing/quotations" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
          </router-link>
          <div class="action-buttons">
            <router-link 
              v-if="canEdit" 
            :to="`/purchasing/quotations/${localId}/edit`" 
              class="btn btn-primary"
            >
              <i class="fas fa-edit"></i> Edit
            </router-link>
            <router-link 
              v-if="canCreatePO" 
            :to="`/purchasing/quotations/${localId}/create-po`" 
              class="btn btn-success"
            >
              <i class="fas fa-file-invoice"></i> Create PO
            </router-link>
            <button 
              v-if="canApprove" 
              @click="changeStatus('accepted')" 
              class="btn btn-success"
              :disabled="processingAction"
            >
              <i class="fas fa-check"></i> Accept
            </button>
            <button 
              v-if="canReject" 
              @click="changeStatus('rejected')" 
              class="btn btn-danger"
              :disabled="processingAction"
            >
              <i class="fas fa-times"></i> Reject
            </button>
            <button 
              v-if="canDelete" 
              @click="confirmDelete" 
              class="btn btn-danger"
              :disabled="processingAction"
            >
              <i class="fas fa-trash"></i> Delete
            </button>
          </div>
        </div>
      </div>
  
      <div v-if="loading" class="loading-container">
        <i class="fas fa-spinner fa-spin"></i> Loading quotation details...
      </div>
  
      <div v-else class="content-wrapper">
        <div class="content-row">
          <div class="detail-card">
            <h2 class="card-title">Quotation Information</h2>
            <div class="detail-grid">
              <div class="detail-item">
                <div class="detail-label">Quotation ID</div>
                <div class="detail-value">{{ quotation.quotation_id }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Quotation Date</div>
                <div class="detail-value">{{ formatDate(quotation.quotation_date) }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Valid Until</div>
                <div class="detail-value">{{ formatDate(quotation.validity_date) }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Days Remaining</div>
                <div class="detail-value">
                  <span :class="validityClass">{{ daysRemaining }}</span>
                </div>
              </div>
            </div>
          </div>
  
          <div class="detail-card">
            <h2 class="card-title">Vendor Information</h2>
            <div class="detail-grid" v-if="quotation.vendor">
              <div class="detail-item">
                <div class="detail-label">Vendor</div>
                <div class="detail-value">{{ quotation.vendor.name }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Contact Person</div>
                <div class="detail-value">{{ quotation.vendor.contact_person || 'N/A' }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Email</div>
                <div class="detail-value">{{ quotation.vendor.email || 'N/A' }}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Phone</div>
                <div class="detail-value">{{ quotation.vendor.phone || 'N/A' }}</div>
              </div>
            </div>
            <div v-else class="empty-vendor">
              Vendor information not available
            </div>
          </div>
        </div>
  
        <div class="detail-card rfq-card">
          <h2 class="card-title">Request for Quotation</h2>
          <div class="detail-grid" v-if="quotation.requestForQuotation">
            <div class="detail-item">
              <div class="detail-label">RFQ Number</div>
              <div class="detail-value">{{ quotation.requestForQuotation.rfq_number }}</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">RFQ Date</div>
              <div class="detail-value">{{ formatDate(quotation.requestForQuotation.rfq_date) }}</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">RFQ Status</div>
              <div class="detail-value">
                <span class="status-badge" :class="rfqStatusClass">
                  {{ capitalizeFirstLetter(quotation.requestForQuotation.status) }}
                </span>
              </div>
            </div>
            <div class="detail-item">
              <div class="detail-label">View RFQ</div>
              <div class="detail-value">
                <router-link :to="`/purchasing/rfqs/${quotation.rfq_id}`" class="link-button">
                  <i class="fas fa-external-link-alt"></i> Open RFQ
                </router-link>
              </div>
            </div>
          </div>
          <div v-else class="empty-rfq">
            RFQ information not available
          </div>
        </div>
  
        <div class="detail-card">
          <h2 class="card-title">Quotation Lines</h2>
          <div class="table-responsive">
            <table class="lines-table">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Description</th>
                  <th>Quantity</th>
                  <th>UOM</th>
                  <th>Unit Price</th>
                  <th>Delivery Date</th>
                  <th>Subtotal</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="line in quotation.lines" :key="line.line_id">
                  <td>{{ line.item ? line.item.item_code : 'N/A' }}</td>
                  <td>{{ line.item ? line.item.name : 'N/A' }}</td>
                  <td>{{ formatNumber(line.quantity) }}</td>
                  <td>{{ line.unitOfMeasure ? line.unitOfMeasure.symbol : 'N/A' }}</td>
                  <td>{{ formatCurrency(line.unit_price) }}</td>
                  <td>{{ formatDate(line.delivery_date) }}</td>
                  <td class="subtotal-cell">{{ formatCurrency(calculateSubtotal(line)) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="6" class="text-right"><strong>Total</strong></td>
                  <td class="subtotal-cell"><strong>{{ formatCurrency(calculateTotal()) }}</strong></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
  
      <!-- Confirmation Modal -->
      <div v-if="showConfirmModal" class="modal">
        <div class="modal-backdrop" @click="showConfirmModal = false"></div>
        <div class="modal-content modal-sm">
          <div class="modal-header">
            <h2>{{ modalTitle }}</h2>
            <button class="close-btn" @click="showConfirmModal = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <p>{{ modalMessage }}</p>
            
            <div class="form-actions">
              <button type="button" class="btn btn-secondary" @click="showConfirmModal = false">
                Cancel
              </button>
              <button
                type="button"
                :class="`btn ${modalAction === 'delete' ? 'btn-danger' : 'btn-success'}`"
                @click="confirmAction"
              >
                {{ modalAction === 'delete' ? 'Delete' : 'Confirm' }}
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
    name: 'VendorQuotationDetail',
    props: {
      id: {
        type: [Number, String],
        required: true
      }
    },
    data() {
      return {
        localId: this.id,
        quotation: {},
        loading: true,
        processingAction: false,
        showConfirmModal: false,
        modalTitle: '',
        modalMessage: '',
        modalAction: '',
        pendingStatus: ''
      };
    },
    computed: {
      // Check permissions based on quotation status
      canEdit() {
        return this.quotation.status === 'received';
      },
      canDelete() {
        return this.quotation.status === 'received';
      },
      canApprove() {
        return this.quotation.status === 'received';
      },
      canReject() {
        return this.quotation.status === 'received' || this.quotation.status === 'accepted';
      },
      canCreatePO() {
        return this.quotation.status === 'accepted';
      },
      
      // CSS classes for status badges
      statusClass() {
        switch (this.quotation.status) {
          case 'received': return 'status-info';
          case 'accepted': return 'status-success';
          case 'rejected': return 'status-danger';
          default: return '';
        }
      },
      
      rfqStatusClass() {
        if (!this.quotation.requestForQuotation) return '';
        
        switch (this.quotation.requestForQuotation.status) {
          case 'draft': return 'status-warn';
          case 'sent': return 'status-info';
          case 'closed': return 'status-success';
          case 'cancelled': return 'status-danger';
          default: return '';
        }
      },
      
      // Calculate days remaining for validity
      daysRemaining() {
        if (!this.quotation.validity_date) return 'No expiry date';
        
        const today = new Date();
        const validityDate = new Date(this.quotation.validity_date);
        
        const differenceInTime = validityDate.getTime() - today.getTime();
        const differenceInDays = Math.ceil(differenceInTime / (1000 * 3600 * 24));
        
        if (differenceInDays < 0) {
          return 'Expired';
        } else if (differenceInDays === 0) {
          return 'Expires today';
        } else {
          return `${differenceInDays} day${differenceInDays !== 1 ? 's' : ''} remaining`;
        }
      },
      
      // CSS class for validity days
      validityClass() {
        if (!this.quotation.validity_date) return '';
        
        const today = new Date();
        const validityDate = new Date(this.quotation.validity_date);
        
        const differenceInTime = validityDate.getTime() - today.getTime();
        const differenceInDays = Math.ceil(differenceInTime / (1000 * 3600 * 24));
        
        if (differenceInDays < 0) {
          return 'text-danger';
        } else if (differenceInDays <= 5) {
          return 'text-warning';
        } else {
          return 'text-success';
        }
      }
    },
    methods: {
      // Fetch quotation details from API
      fetchQuotationDetails() {
        this.loading = true;
        
        axios.get(`/vendor-quotations/${this.localId}`)
          .then(response => {
            if (response.data.status === 'success') {
              this.quotation = response.data.data;
            } else {
              this.$toasted.error('Failed to load quotation details');
              this.$router.push('/purchasing/quotations');
            }
          })
          .catch(error => {
            console.error('Error fetching quotation details:', error);
            this.$toasted.error('Error loading quotation');
            this.$router.push('/purchasing/quotations');
          })
          .finally(() => {
            this.loading = false;
          });
      },
      
      // Change quotation status
      changeStatus(newStatus) {
        this.pendingStatus = newStatus;
        this.modalTitle = newStatus === 'accepted' ? 'Accept Quotation' : 'Reject Quotation';
        this.modalMessage = newStatus === 'accepted' 
          ? 'Are you sure you want to accept this quotation? This action will mark the quotation as approved and make it available for creating purchase orders.'
          : 'Are you sure you want to reject this quotation? This action cannot be undone.';
        this.modalAction = 'status';
        this.showConfirmModal = true;
      },
      
      // Process status change
      processStatusChange() {
        this.processingAction = true;
        
        axios.patch(`/vendor-quotations/${this.id}/status`, { status: this.pendingStatus })
          .then(response => {
            if (response.data.status === 'success') {
              this.quotation.status = this.pendingStatus;
              this.$toasted.success(`Quotation ${this.pendingStatus} successfully`);
            } else {
              this.$toasted.error(`Failed to update status: ${response.data.message}`);
            }
          })
          .catch(error => {
            console.error('API Error:', error);
            this.$toasted.error('An error occurred while updating status');
          })
          .finally(() => {
            this.processingAction = false;
            this.showConfirmModal = false;
          });
      },
      
      // Confirm delete
      confirmDelete() {
        this.modalTitle = 'Delete Quotation';
        this.modalMessage = 'Are you sure you want to delete this quotation? This action cannot be undone.';
        this.modalAction = 'delete';
        this.showConfirmModal = true;
      },
      
      // Process delete
      processDelete() {
        this.processingAction = true;
        
        axios.delete(`/vendor-quotations/${this.id}`)
          .then(response => {
            if (response.data.status === 'success') {
              this.$toasted.success('Quotation deleted successfully');
              this.$router.push('/purchasing/quotations');
            } else {
              this.$toasted.error(`Failed to delete: ${response.data.message}`);
            }
          })
          .catch(error => {
            console.error('API Error:', error);
            this.$toasted.error('An error occurred while deleting');
          })
          .finally(() => {
            this.processingAction = false;
            this.showConfirmModal = false;
          });
      },
      
      // Confirm modal action
      confirmAction() {
        if (this.modalAction === 'delete') {
          this.processDelete();
        } else if (this.modalAction === 'status') {
          this.processStatusChange();
        }
      },
      
      // Format date for display
      formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
      },
      
      // Format number for display
      formatNumber(value) {
        if (value === undefined || value === null) return 'N/A';
        return new Intl.NumberFormat('id-ID').format(value);
      },
      
      // Format currency for display
      formatCurrency(value) {
        if (value === undefined || value === null) return 'N/A';
        return new Intl.NumberFormat('id-ID', {
          style: 'currency',
          currency: 'IDR',
          minimumFractionDigits: 0,
          maximumFractionDigits: 0
        }).format(value);
      },
      
      // Calculate subtotal for a line
      calculateSubtotal(line) {
        return line.quantity * line.unit_price;
      },
      
      // Calculate total for all lines
      calculateTotal() {
        if (!this.quotation.lines) return 0;
        return this.quotation.lines.reduce((sum, line) => sum + this.calculateSubtotal(line), 0);
      },
      
      // Capitalize first letter of a string
      capitalizeFirstLetter(string) {
        if (!string) return '';
        return string.charAt(0).toUpperCase() + string.slice(1);
      }
    },
    mounted() {
      this.fetchQuotationDetails();
    },
    watch: {
      // Watch for route changes to reload data if ID changes
      '$route'(to, from) {
        if (to.params.id !== from.params.id) {
          this.localId = to.params.id;
          this.fetchQuotationDetails();
        }
      }
    }
  };
  </script>
  
  <style scoped>
  .quotation-detail-container {
    max-width: 1200px;
    margin: 0 auto;
  }
  
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
  }
  
  .header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
  }
  
  .page-header h1 {
    margin: 0;
    font-size: 1.5rem;
    color: var(--gray-800);
  }
  
  .header-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 1rem;
  }
  
  .action-buttons {
    display: flex;
    gap: 0.5rem;
  }
  
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    border-radius: 0.375rem;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    border: none;
  }
  
  .btn i {
    margin-right: 0.5rem;
  }
  
  .btn-primary {
    background-color: var(--primary-color);
    color: white;
  }
  
  .btn-primary:hover:not(:disabled) {
    background-color: var(--primary-dark);
  }
  
  .btn-secondary {
    background-color: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
  }
  
  .btn-secondary:hover {
    background-color: var(--gray-200);
  }
  
  .btn-success {
    background-color: #059669;
    color: white;
  }
  
  .btn-success:hover:not(:disabled) {
    background-color: #047857;
  }
  
  .btn-danger {
    background-color: #ef4444;
    color: white;
  }
  
  .btn-danger:hover:not(:disabled) {
    background-color: #b91c1c;
  }
  
  .btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
  }
  
  .status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
  }
  
  .status-info {
    background-color: #e0f2fe;
    color: #0369a1;
  }
  
  .status-success {
    background-color: #dcfce7;
    color: #166534;
  }
  
  .status-danger {
    background-color: #fee2e2;
    color: #b91c1c;
  }
  
  .status-warn {
    background-color: #fff7ed;
    color: #9a3412;
  }
  
  .loading-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 300px;
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    color: var(--gray-500);
  }
  
  .loading-container i {
    margin-right: 0.5rem;
    animation: spin 1s linear infinite;
  }
  
  .content-wrapper {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
  }
  
  .content-row {
    display: flex;
    gap: 1.5rem;
  }
  
  .detail-card {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
    flex: 1;
  }
  
  .card-title {
    font-size: 1.125rem;
    font-weight: 600;
    margin-top: 0;
    margin-bottom: 1.5rem;
    color: var(--gray-800);
  }
  
  .detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
  }
  
  .detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
  }
  
  .detail-label {
    font-size: 0.75rem;
    color: var(--gray-500);
    font-weight: 500;
  }
  
  .detail-value {
    font-size: 0.875rem;
    color: var(--gray-800);
    font-weight: 400;
  }
  
  .rfq-card {
    margin-top: 0;
  }
  
  .empty-vendor,
  .empty-rfq {
    color: var(--gray-500);
    font-style: italic;
    padding: 1rem;
    background-color: var(--gray-50);
    border-radius: 0.375rem;
    text-align: center;
  }
  
  .link-button {
    display: inline-flex;
    align-items: center;
    background-color: var(--gray-100);
    padding: 0.375rem 0.75rem;
    border-radius: 0.375rem;
    color: var(--primary-color);
    font-size: 0.875rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
  }
  
  .link-button:hover {
    background-color: var(--gray-200);
    text-decoration: none;
  }
  
  .link-button i {
    margin-right: 0.5rem;
  }
  
  .table-responsive {
    overflow-x: auto;
  }
  
  .lines-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
  }
  
  .lines-table th {
    text-align: left;
    padding: 0.75rem;
    border-bottom: 1px solid var(--gray-200);
    font-weight: 500;
    color: var(--gray-600);
  }
  
  .lines-table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--gray-100);
  }
  
  .lines-table tfoot td {
    border-top: 1px solid var(--gray-200);
    background-color: var(--gray-50);
    padding: 0.75rem;
  }
  
  .text-right {
    text-align: right;
  }
  
  .subtotal-cell {
    text-align: right;
    font-weight: 500;
  }
  
  .text-danger {
    color: #b91c1c;
  }
  
  .text-warning {
    color: #d97706;
  }
  
  .text-success {
    color: #059669;
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
  
  .modal-body {
    padding: 1.5rem;
  }
  
  .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
  }
  
  @keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
  }
  
  @media (max-width: 768px) {
    .page-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }
    
    .header-actions {
      align-items: flex-start;
      width: 100%;
    }
    
    .action-buttons {
      flex-wrap: wrap;
    }
    
    .content-row {
      flex-direction: column;
    }
    
    .detail-grid {
      grid-template-columns: 1fr;
    }
  }
  </style>