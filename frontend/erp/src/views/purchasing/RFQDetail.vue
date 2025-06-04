<!-- src/views/purchasing/RFQDetail.vue -->
<template>
    <div class="rfq-detail-container">
      <div class="page-header">
        <h1>Request for Quotation Details</h1>
        <div class="header-actions">
          <router-link :to="`/purchasing/rfqs`" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to List
          </router-link>
  
          <div class="action-dropdown" v-if="rfq">
            <button class="btn btn-outline-primary" @click="toggleActionMenu">
              <i class="fas fa-ellipsis-v"></i> Actions
            </button>
            <div class="dropdown-menu" v-if="showActionMenu">
            <router-link 
                v-if="rfq.status === 'draft'" 
                :to="`/purchasing/rfqs/${id}/edit`" 
                class="dropdown-item"
              >
                <i class="fas fa-edit"></i> Edit RFQ
              </router-link>
              
              <router-link 
                v-if="rfq.status === 'draft'" 
                :to="`/purchasing/rfqs/${id}/send`" 
                class="dropdown-item"
              >
                <i class="fas fa-paper-plane"></i> Send to Vendors
              </router-link>
              
              <router-link 
                v-if="rfq.status === 'sent' && hasQuotations" 
                :to="`/purchasing/rfqs/${id}/compare`" 
                class="dropdown-item"
              >
                <i class="fas fa-balance-scale"></i> Compare Quotations
              </router-link>
              
              <a 
                v-if="rfq.status === 'draft'" 
                href="#" 
                @click.prevent="updateStatus('sent')" 
                class="dropdown-item"
              >
                <i class="fas fa-check-circle"></i> Mark as Sent
              </a>
              
              <a 
                v-if="rfq.status === 'sent'" 
                href="#" 
                @click.prevent="updateStatus('closed')" 
                class="dropdown-item"
              >
                <i class="fas fa-lock"></i> Close RFQ
              </a>
              
              <a 
                v-if="['draft', 'sent'].includes(rfq.status)" 
                href="#" 
                @click.prevent="updateStatus('canceled')" 
                class="dropdown-item"
              >
                <i class="fas fa-ban"></i> Cancel RFQ
              </a>
              
              <a 
                v-if="rfq.status === 'draft'" 
                href="#" 
                @click.prevent="confirmDelete" 
                class="dropdown-item text-danger"
              >
                <i class="fas fa-trash"></i> Delete RFQ
              </a>
            </div>
          </div>
        </div>
      </div>
  
      <div v-if="loading" class="loading-indicator">
        <i class="fas fa-spinner fa-spin"></i> Loading RFQ details...
      </div>
  
      <div v-else-if="!rfq" class="error-state">
        <div class="error-icon">
          <i class="fas fa-exclamation-circle"></i>
        </div>
        <h3>Request for Quotation Not Found</h3>
        <p>The RFQ you are looking for does not exist or has been deleted.</p>
        <router-link to="/purchasing/rfqs" class="btn btn-primary">
          Go Back to RFQ List
        </router-link>
      </div>
  
      <div v-else class="rfq-details">
        <div class="detail-card">
          <div class="card-header">
            <h2 class="card-title">RFQ Information</h2>
            <div class="status-badge" :class="getStatusClass(rfq.status)">
              {{ capitalizeFirstLetter(rfq.status) }}
            </div>
          </div>
  
          <div class="card-body">
            <div class="detail-row">
              <div class="detail-group">
                <label>RFQ Number</label>
                <div class="detail-value">{{ rfq.rfq_number }}</div>
              </div>
              
              <div class="detail-group">
                <label>RFQ Date</label>
                <div class="detail-value">{{ formatDate(rfq.rfq_date) }}</div>
              </div>
            </div>
            
            <div class="detail-row">
              <div class="detail-group">
                <label>Validity Date</label>
                <div class="detail-value">{{ formatDate(rfq.validity_date) || 'N/A' }}</div>
              </div>
              
              <div class="detail-group">
                <label>Created At</label>
                <div class="detail-value">{{ formatDateTime(rfq.created_at) }}</div>
              </div>
            </div>
            
            <div class="detail-row" v-if="rfq.notes">
              <div class="detail-group full-width">
                <label>Notes</label>
                <div class="detail-value">{{ rfq.notes }}</div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="detail-card">
          <div class="card-header">
            <h2 class="card-title">RFQ Lines</h2>
            <div class="items-count">{{ rfq && rfq.lines ? rfq.lines.length : 0 }} Items</div>
          </div>
          
          <div class="card-body">
            <div class="table-responsive">
              <table class="detail-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>UOM</th>
                    <th>Required Date</th>
                  </tr>
                </thead>
                <tbody>
                  <template v-if="rfq && Array.isArray(rfq.lines)">
                    <tr v-for="(line, index) in rfq.lines" :key="line.line_id">
                      <td>{{ index + 1 }}</td>
                      <td>
                        <div class="item-code">{{ line.item.item_code }}</div>
                        <div class="item-name">{{ line.item.name }}</div>
                      </td>
                      <td>{{ line.item.description || 'N/A' }}</td>
                      <td>{{ formatNumber(line.quantity) }}</td>
                      <td>{{ line.unit_of_measure.symbol }}</td>
                      <td>{{ formatDate(line.required_date) || 'N/A' }}</td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        
        <div class="detail-card">
          <div class="card-header">
            <h2 class="card-title">Vendor Quotations</h2>
            <div class="quotations-count">
              {{ rfq.vendor_quotations ? rfq.vendor_quotations.length : 0 }} Quotations
            </div>
          </div>
          
          <div class="card-body">
            <div v-if="!hasQuotations" class="empty-quotations">
              <div class="empty-icon">
                <i class="fas fa-file-invoice-dollar"></i>
              </div>
              <h3>No Quotations Yet</h3>
              <p v-if="rfq.status === 'draft'">
                Send this RFQ to vendors to receive quotations.
              </p>
              <p v-else-if="rfq.status === 'sent'">
                Waiting for vendors to submit their quotations.
              </p>
              <p v-else>
                No quotations were received for this RFQ.
              </p>
              
              <div class="empty-actions" v-if="rfq.status === 'draft'">
                <router-link :to="`/purchasing/rfqs/${id}/send`" class="btn btn-primary">
                  <i class="fas fa-paper-plane"></i> Send to Vendors
                </router-link>
              </div>
            </div>
            
            <div v-else class="quotations-list">
              <div 
                v-for="quotation in rfq.vendor_quotations" 
                :key="quotation.quotation_id" 
                class="quotation-card"
              >
                <div class="quotation-header">
                  <div class="vendor-info">
                    <div class="vendor-name">{{ quotation.vendor.name }}</div>
                    <div class="quotation-date">
                      Quote Date: {{ formatDate(quotation.quotation_date) }}
                    </div>
                  </div>
                  
                  <div class="quotation-validity">
                    Valid until: {{ formatDate(quotation.validity_date) || 'Not specified' }}
                  </div>
                </div>
                
                <div class="quotation-body">
                  <div class="quotation-items">
                    <div class="quotation-item-count">
                      {{ quotation.lines.length }} Items Quoted
                    </div>
                    
                    <router-link 
                      :to="`/purchasing/vendor-quotations/${quotation.quotation_id}`" 
                      class="btn btn-outline-primary btn-sm"
                    >
                      View Quotation
                    </router-link>
                  </div>
                  
                  <div class="quotation-status">
                    <div class="status-badge" :class="getQuotationStatusClass(quotation.status)">
                      {{ capitalizeFirstLetter(quotation.status) }}
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="quotations-actions" v-if="hasQuotations && rfq.status === 'sent'">
                <router-link :to="`/purchasing/rfqs/${id}/compare`" class="btn btn-primary">
                  <i class="fas fa-balance-scale"></i> Compare Quotations
                </router-link>
              </div>
            </div>
          </div>
        </div>
      </div>
  
      <!-- Confirmation Modal for Status Update -->
      <div v-if="showStatusModal" class="modal">
        <div class="modal-backdrop" @click="showStatusModal = false"></div>
        <div class="modal-content modal-sm">
          <div class="modal-header">
            <h2>Update Status</h2>
            <button class="close-btn" @click="showStatusModal = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to change the status of this RFQ to <strong>{{ capitalizeFirstLetter(newStatus) }}</strong>?</p>
            
            <div class="form-actions">
              <button type="button" class="btn btn-secondary" @click="showStatusModal = false">
                Cancel
              </button>
              <button
                type="button"
                class="btn btn-primary"
                @click="confirmUpdateStatus"
              >
                Update Status
              </button>
            </div>
          </div>
        </div>
      </div>
  
      <!-- Confirmation Modal for Delete -->
      <div v-if="showDeleteModal" class="modal">
        <div class="modal-backdrop" @click="showDeleteModal = false"></div>
        <div class="modal-content modal-sm">
          <div class="modal-header">
            <h2>Confirm Delete</h2>
            <button class="close-btn" @click="showDeleteModal = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to delete RFQ <strong>{{ rfq?.rfq_number }}</strong>?</p>
            <p class="text-danger">This action cannot be undone.</p>
            
            <div class="form-actions">
              <button type="button" class="btn btn-secondary" @click="showDeleteModal = false">
                Cancel
              </button>
              <button
                type="button"
                class="btn btn-danger"
                @click="deleteRfq"
              >
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
    name: 'RFQDetail',
    props: {
      id: {
        type: [Number, String],
        required: true
      }
    },
    data() {
      return {
        rfq: null,
        loading: true,
        showActionMenu: false,
        showStatusModal: false,
        showDeleteModal: false,
        newStatus: '',
        isUpdating: false
      }
    },
    computed: {
      hasQuotations() {
        return this.rfq && this.rfq.vendor_quotations && this.rfq.vendor_quotations.length > 0;
      }
    },
    mounted() {
      this.loadRFQ();
      
      // Close action menu when clicking outside
      document.addEventListener('click', this.handleOutsideClick);
    },
    beforeUnmount() {
      document.removeEventListener('click', this.handleOutsideClick);
    },
    methods: {
      async loadRFQ() {
  this.loading = true;
  
  try {
    const response = await axios.get(`/request-for-quotations/${this.id}`);
    
    if (response.data.status === 'success' && response.data.data) {
      this.rfq = response.data.data;
      
      // Ensure lines and vendor_quotations are arrays to prevent undefined errors
      if (!Array.isArray(this.rfq.lines)) {
        this.rfq.lines = [];
      }
      if (!Array.isArray(this.rfq.vendor_quotations)) {
        this.rfq.vendor_quotations = [];
      }
      
      // NEW: Ensure each quotation has a lines array
      this.rfq.vendor_quotations.forEach(quotation => {
        if (!Array.isArray(quotation.lines)) {
          quotation.lines = [];
        }
      });
      
    } else {
      this.rfq = null;
      throw new Error(response.data.message || 'Failed to load RFQ');
    }
  } catch (error) {
    console.error('Error loading RFQ:', error);
    this.rfq = null;
    
    if (error.response && error.response.status === 404) {
      this.$toast.error('Request for Quotation not found');
    } else {
      this.$toast.error('Failed to load RFQ details. Please try again.');
    }
  } finally {
    this.loading = false;
  }
},
      formatDate(dateString) {
        if (!dateString) return null;
        
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', {
          year: 'numeric',
          month: 'long',
          day: 'numeric'
        });
      },
      formatDateTime(dateString) {
        if (!dateString) return null;
        
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', {
          year: 'numeric',
          month: 'long',
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        });
      },
      formatNumber(number) {
        return new Intl.NumberFormat('id-ID').format(number);
      },
      getStatusClass(status) {
        switch (status) {
          case 'draft': return 'status-draft';
          case 'sent': return 'status-sent';
          case 'closed': return 'status-closed';
          case 'canceled': return 'status-canceled';
          default: return '';
        }
      },
      getQuotationStatusClass(status) {
        switch (status) {
          case 'draft': return 'status-draft';
          case 'submitted': return 'status-submitted';
          case 'accepted': return 'status-accepted';
          case 'rejected': return 'status-rejected';
          default: return '';
        }
      },
      capitalizeFirstLetter(string) {
        if (!string) return '';
        return string.charAt(0).toUpperCase() + string.slice(1);
      },
      toggleActionMenu() {
        this.showActionMenu = !this.showActionMenu;
      },
      handleOutsideClick(event) {
        const dropdown = this.$el.querySelector('.action-dropdown');
        if (dropdown && !dropdown.contains(event.target)) {
          this.showActionMenu = false;
        }
      },
      updateStatus(status) {
        this.newStatus = status;
        this.showStatusModal = true;
        this.showActionMenu = false;
      },
      async confirmUpdateStatus() {
        if (!this.newStatus) return;
        
        this.isUpdating = true;
        
        try {
          const response = await axios.patch(`/request-for-quotations/${this.id}/status`, {
            status: this.newStatus
          });
          
          if (response.data.status === 'success') {
            this.$toast.success(`RFQ status updated to ${this.capitalizeFirstLetter(this.newStatus)}`);
            this.loadRFQ();
          } else {
            throw new Error(response.data.message || 'Failed to update status');
          }
        } catch (error) {
          console.error('Error updating status:', error);
          
          if (error.response && error.response.data && error.response.data.message) {
            this.$toast.error('Failed to update status: ' + error.response.data.message);
          } else {
            this.$toast.error('Failed to update RFQ status. Please try again.');
          }
        } finally {
          this.showStatusModal = false;
          this.newStatus = '';
          this.isUpdating = false;
        }
      },
      confirmDelete() {
        this.showDeleteModal = true;
        this.showActionMenu = false;
      },
      async deleteRfq() {
        try {
          const response = await axios.delete(`/request-for-quotations/${this.id}`);
          
          if (response.data.status === 'success') {
            this.$toast.success('RFQ deleted successfully');
            this.$router.push('/purchasing/rfqs');
          } else {
            throw new Error(response.data.message || 'Failed to delete RFQ');
          }
        } catch (error) {
          console.error('Error deleting RFQ:', error);
          
          if (error.response && error.response.data && error.response.data.message) {
            this.$toast.error('Failed to delete RFQ: ' + error.response.data.message);
          } else {
            this.$toast.error('Failed to delete RFQ. Please try again.');
          }
        } finally {
          this.showDeleteModal = false;
        }
      }
    }
  }
  </script>
  
  <style scoped>
  .rfq-detail-container {
    padding: 1rem;
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }
  
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
  }
  
  .page-header h1 {
    margin: 0;
    font-size: 1.5rem;
  }
  
  .header-actions {
    display: flex;
    gap: 0.5rem;
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
    z-index: 10;
    overflow: hidden;
  }
  
  .dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1rem;
    color: var(--gray-700);
    font-size: 0.875rem;
    text-decoration: none;
    transition: background-color 0.2s;
  }
  
  .dropdown-item:hover {
    background-color: var(--gray-50);
  }
  
  .dropdown-item i {
    width: 1rem;
    text-align: center;
  }
  
  .text-danger {
    color: #dc2626;
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
    animation: spin 1s linear infinite;
  }
  
  .error-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 0;
    text-align: center;
  }
  
  .error-icon {
    font-size: 3rem;
    color: #ef4444;
    margin-bottom: 1rem;
  }
  
  .error-state h3 {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
  }
  
  .error-state p {
    color: var(--gray-500);
    margin-bottom: 1.5rem;
  }
  
  .rfq-details {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
  }
  
  .detail-card {
    border: 1px solid var(--gray-200);
    border-radius: 0.5rem;
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
  
  .card-title {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--gray-800);
  }
  
  .status-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: capitalize;
  }
  
  .status-draft {
    background-color: var(--gray-100);
    color: var(--gray-700);
  }
  
  .status-sent {
    background-color: #dbeafe;
    color: #1e40af;
  }
  
  .status-closed {
    background-color: #dcfce7;
    color: #166534;
  }
  
  .status-canceled {
    background-color: #fee2e2;
    color: #991b1b;
  }
  
  .status-submitted {
    background-color: #dbeafe;
    color: #1e40af;
  }
  
  .status-accepted {
    background-color: #dcfce7;
    color: #166534;
  }
  
  .status-rejected {
    background-color: #fee2e2;
    color: #991b1b;
  }
  
  .card-body {
    padding: 1.5rem;
  }
  
  .detail-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
  }
  
  .detail-row:last-child {
    margin-bottom: 0;
  }
  
  .detail-group {
    flex: 1;
    min-width: 200px;
  }
  
  .detail-group.full-width {
    flex-basis: 100%;
  }
  
  .detail-group label {
    display: block;
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-bottom: 0.25rem;
  }
  
  .detail-value {
    font-size: 0.875rem;
    color: var(--gray-800);
  }
  
  .table-responsive {
    overflow-x: auto;
  }
  
  .detail-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
  }
  
  .detail-table th {
    text-align: left;
    padding: 0.75rem;
    border-bottom: 1px solid var(--gray-200);
    font-weight: 500;
    color: var(--gray-600);
  }
  
  .detail-table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--gray-100);
    color: var(--gray-800);
    vertical-align: middle;
  }
  
  .detail-table tr:last-child td {
    border-bottom: none;
  }
  
  .item-code {
    font-weight: 500;
    margin-bottom: 0.25rem;
  }
  
  .item-name {
    font-size: 0.75rem;
    color: var(--gray-500);
  }
  
  .items-count,
  .quotations-count {
    font-size: 0.875rem;
    color: var(--gray-500);
    font-weight: 500;
  }
  
  .empty-quotations {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem 0;
    text-align: center;
  }
  
  .empty-icon {
    font-size: 2.5rem;
    color: var(--gray-300);
    margin-bottom: 1rem;
  }
  
  .empty-quotations h3 {
    font-size: 1.125rem;
    margin-bottom: 0.5rem;
  }
  
  .empty-quotations p {
    color: var(--gray-500);
    max-width: 24rem;
    margin-bottom: 1.5rem;
  }
  
  .empty-actions {
    margin-top: 1rem;
  }
  
  .quotations-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }
  
  .quotation-card {
    border: 1px solid var(--gray-200);
    border-radius: 0.375rem;
    overflow: hidden;
  }
  
  .quotation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background-color: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
  }
  
  .vendor-name {
    font-weight: 500;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
  }
  
  .quotation-date,
  .quotation-validity {
    font-size: 0.75rem;
    color: var(--gray-500);
  }
  
  .quotation-body {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
  }
  
  .quotation-items {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
  }
  
  .quotation-item-count {
    font-size: 0.875rem;
    color: var(--gray-700);
  }
  
  .btn {
    padding: 0.625rem 1rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
  }
  
  .btn-sm {
    padding: 0.375rem 0.75rem;
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
    background-color: white;
    color: var(--gray-700);
    border: 1px solid var(--gray-300);
  }
  
  .btn-secondary:hover {
    background-color: var(--gray-50);
  }
  
  .btn-danger {
    background-color: #ef4444;
    color: white;
    border: none;
  }
  
  .btn-danger:hover {
    background-color: #dc2626;
  }
  
  .btn-outline {
    background-color: white;
    color: var(--gray-700);
    border: 1px solid var(--gray-300);
  }
  
  .btn-outline:hover {
    background-color: var(--gray-50);
  }
  
  .btn-outline-primary {
    background-color: white;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
  }
  
  .btn-outline-primary:hover {
    background-color: rgba(37, 99, 235, 0.05);
  }
  
  .quotations-actions {
    display: flex;
    justify-content: center;
    margin-top: 1.5rem;
  }
  
  /* Modal Styles */
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
    width: 100%;
    justify-content: space-between;
  }
  
  .detail-row {
    flex-direction: column;
    gap: 1rem;
  }
  
  .card-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
  
  .quotation-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
  
  .quotation-body {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }
  
  .quotation-status {
    align-self: flex-start;
  }
}
</style>