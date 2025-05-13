<!-- src/views/purchasing/PurchaseRequisitionDetail.vue -->
<template>
    <div class="purchase-requisition-detail">
      <div class="page-header">
        <h1>Purchase Requisition Detail</h1>
        <div class="action-buttons">
          <router-link v-if="pr.status === 'draft'" :to="`/purchasing/requisitions/${pr.pr_id}/edit`" class="btn btn-primary mr-2">
            <i class="fas fa-edit"></i> Edit
          </router-link>
          
          <button v-if="pr.status === 'draft'" @click="submitPR" class="btn btn-success mr-2" :disabled="loading">
            <i class="fas fa-paper-plane"></i> Submit for Approval
          </button>
          
          <router-link v-if="pr.status === 'pending'" :to="`/purchasing/requisitions/${pr.pr_id}/approve`" class="btn btn-warning mr-2">
            <i class="fas fa-check-circle"></i> Review & Approve
          </router-link>
          
          <router-link v-if="pr.status === 'approved'" :to="`/purchasing/requisitions/${pr.pr_id}/convert`" class="btn btn-info mr-2">
            <i class="fas fa-exchange-alt"></i> Convert to RFQ
          </router-link>
          
          <button v-if="['draft', 'pending'].includes(pr.status)" @click="showCancelConfirmation = true" class="btn btn-danger mr-2">
            <i class="fas fa-times"></i> Cancel PR
          </button>
          
          <button @click="printPR" class="btn btn-secondary">
            <i class="fas fa-print"></i> Print
          </button>
        </div>
      </div>
  
      <div v-if="loading" class="text-center my-4">
        <div class="spinner-border text-primary" role="status">
          <span class="sr-only">Loading...</span>
        </div>
      </div>
  
      <div v-else>
        <div class="row">
          <div class="col-md-8">
            <div class="card mb-4">
              <div class="card-header">
                <h5 class="card-title mb-0">Requisition Information</h5>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-6">
                    <div class="detail-item">
                      <div class="detail-label">PR Number</div>
                      <div class="detail-value">{{ pr.pr_number }}</div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="detail-item">
                      <div class="detail-label">Status</div>
                      <div class="detail-value">
                        <span :class="getStatusBadgeClass(pr.status)">{{ capitalizeStatus(pr.status) }}</span>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div class="row mt-3">
                  <div class="col-md-6">
                    <div class="detail-item">
                      <div class="detail-label">PR Date</div>
                      <div class="detail-value">{{ formatDate(pr.pr_date) }}</div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="detail-item">
                      <div class="detail-label">Requester</div>
                      <div class="detail-value">{{ pr.requester ? pr.requester.name : 'N/A' }}</div>
                    </div>
                  </div>
                </div>
                
                <div class="row mt-3">
                  <div class="col-md-12">
                    <div class="detail-item">
                      <div class="detail-label">Notes</div>
                      <div class="detail-value notes-value">{{ pr.notes || 'No notes provided.' }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="card">
              <div class="card-header">
                <h5 class="card-title mb-0">Requisition Lines</h5>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-striped mb-0">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Required Date</th>
                        <th>Notes</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(line, index) in pr.lines" :key="index">
                        <td>{{ index + 1 }}</td>
                        <td>
                          <div class="item-info">
                            <span class="item-code">{{ line.item ? line.item.item_code : 'N/A' }}</span>
                            <span class="item-name">{{ line.item ? line.item.name : 'Unknown Item' }}</span>
                          </div>
                        </td>
                        <td>{{ formatNumber(line.quantity) }}</td>
                        <td>{{ line.unitOfMeasure ? line.unitOfMeasure.name : 'N/A' }}</td>
                        <td>{{ formatDate(line.required_date) }}</td>
                        <td>{{ line.notes || '-' }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-md-4">
            <div class="card mb-4">
              <div class="card-header">
                <h5 class="card-title mb-0">Status Timeline</h5>
              </div>
              <div class="card-body p-0">
                <ul class="timeline">
                  <li class="timeline-item">
                    <div class="timeline-marker done">
                      <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="timeline-content">
                      <h3 class="timeline-title">Created</h3>
                      <p class="timeline-date">{{ formatDateTime(pr.created_at) }}</p>
                    </div>
                  </li>
                  
                  <li class="timeline-item">
                    <div class="timeline-marker" :class="{ 'done': pr.status !== 'draft', 'current': pr.status === 'pending' }">
                      <i class="fas fa-paper-plane"></i>
                    </div>
                    <div class="timeline-content">
                      <h3 class="timeline-title">Submitted</h3>
                      <p class="timeline-date" v-if="pr.status !== 'draft'">{{ formatDateTime(pr.submitted_at) }}</p>
                    </div>
                  </li>
                  
                  <li class="timeline-item">
                    <div class="timeline-marker" :class="{ 'done': ['approved', 'rejected'].includes(pr.status), 'current': pr.status === 'approved' }">
                      <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="timeline-content">
                      <h3 class="timeline-title">
                        <span v-if="pr.status === 'approved'">Approved</span>
                        <span v-else-if="pr.status === 'rejected'">Rejected</span>
                        <span v-else>Approval</span>
                      </h3>
                      <p class="timeline-date" v-if="['approved', 'rejected'].includes(pr.status)">{{ formatDateTime(pr.approved_at) }}</p>
                    </div>
                  </li>
                  
                  <li class="timeline-item">
                    <div class="timeline-marker" :class="{ 'done': pr.converted_to_rfq, 'current': pr.status === 'approved' && !pr.converted_to_rfq }">
                      <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div class="timeline-content">
                      <h3 class="timeline-title">Converted to RFQ</h3>
                      <p class="timeline-date" v-if="pr.converted_to_rfq">{{ formatDateTime(pr.converted_at) }}</p>
                      <p v-if="pr.rfq_number" class="timeline-info">
                        RFQ: 
                        <router-link :to="`/purchasing/rfqs/${pr.rfq_id}`" class="rfq-link">
                          {{ pr.rfq_number }}
                        </router-link>
                      </p>
                    </div>
                  </li>
                </ul>
              </div>
            </div>
            
            <div class="card">
              <div class="card-header">
                <h5 class="card-title mb-0">Related Documents</h5>
              </div>
              <div class="card-body">
                <div v-if="relatedDocuments.length === 0" class="text-center my-3">
                  <p class="text-muted">No related documents found.</p>
                </div>
                <ul v-else class="related-docs-list">
                  <li v-for="(doc, index) in relatedDocuments" :key="index" class="related-doc-item">
                    <div class="doc-icon">
                      <i :class="getDocumentIcon(doc.type)"></i>
                    </div>
                    <div class="doc-info">
                      <router-link :to="doc.link" class="doc-number">{{ doc.number }}</router-link>
                      <span class="doc-date">{{ formatDate(doc.date) }}</span>
                    </div>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
  
      <!-- Cancel Confirmation Modal -->
      <div v-if="showCancelConfirmation" class="modal-backdrop" @click="showCancelConfirmation = false"></div>
      <div v-if="showCancelConfirmation" class="modal cancel-modal">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Cancel Purchase Requisition</h5>
            <button type="button" class="close" @click="showCancelConfirmation = false">
              <span>&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to cancel this purchase requisition?</p>
            <p>This action cannot be undone.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showCancelConfirmation = false">Close</button>
            <button type="button" class="btn btn-danger" @click="cancelPR" :disabled="processing">
              <span v-if="processing" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
              Confirm Cancel
            </button>
          </div>
        </div>
      </div>
      
      <!-- Alert Modal -->
      <div v-if="alert.show" class="alert-modal">
        <div class="alert" :class="`alert-${alert.type}`">
          {{ alert.message }}
          <button type="button" class="close" @click="alert.show = false">
            <span>&times;</span>
          </button>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'PurchaseRequisitionDetail',
    props: {
      id: {
        type: [String, Number],
        required: true
      }
    },
    data() {
      return {
        pr: {
          pr_id: null,
          pr_number: '',
          pr_date: '',
          requester_id: null,
          requester: null,
          status: '',
          notes: '',
          lines: [],
          created_at: null,
          submitted_at: null,
          approved_at: null,
          converted_at: null,
          converted_to_rfq: false,
          rfq_id: null,
          rfq_number: null
        },
        relatedDocuments: [],
        loading: true,
        processing: false,
        showCancelConfirmation: false,
        alert: {
          show: false,
          type: 'success',
          message: ''
        }
      };
    },
    async created() {
      await this.fetchPRData();
    },
    methods: {
      async fetchPRData() {
        try {
          this.loading = true;
          const response = await axios.get(`/purchase-requisitions/${this.id}`);
          
          // Update PR data
          this.pr = response.data.data;
          
          // For demo purposes, we'll add some extra timeline data that might come from the API
          // In a real implementation, these would come from your backend
          this.pr.submitted_at = this.pr.status !== 'draft' ? new Date(new Date(this.pr.created_at).getTime() + 3600000) : null;
          this.pr.approved_at = ['approved', 'rejected'].includes(this.pr.status) ? new Date(new Date(this.pr.created_at).getTime() + 7200000) : null;
          
          // Check if this PR has been converted to RFQ
          this.checkRFQConversion();
          
          // Fetch related documents
          this.fetchRelatedDocuments();
        } catch (error) {
          console.error('Error fetching PR data:', error);
          this.showAlert('danger', 'Failed to load purchase requisition data. Please try again.');
        } finally {
          this.loading = false;
        }
      },
      
      async checkRFQConversion() {
        // In a real app, this information would come from the backend
        // For demo, we'll just simulate it for approved PRs
        if (this.pr.status === 'approved') {
          // Random chance for demo purposes
          const hasRFQ = Math.random() > 0.5;
          
          if (hasRFQ) {
            this.pr.converted_to_rfq = true;
            this.pr.converted_at = new Date(new Date(this.pr.approved_at).getTime() + 3600000);
            this.pr.rfq_id = 123;
            this.pr.rfq_number = 'RFQ20250101001';
          }
        }
      },
      
      async fetchRelatedDocuments() {
        // In a real app, this would be fetched from the backend
        // For demo, we'll create some dummy related documents
        this.relatedDocuments = [];
        
        if (this.pr.converted_to_rfq) {
          this.relatedDocuments.push({
            type: 'rfq',
            number: this.pr.rfq_number,
            date: this.pr.converted_at,
            link: `/purchasing/rfqs/${this.pr.rfq_id}`
          });
          
          // Add a sample PO for demo
          this.relatedDocuments.push({
            type: 'po',
            number: 'PO20250115001',
            date: new Date(new Date(this.pr.converted_at).getTime() + 86400000 * 2),
            link: `/purchasing/orders/456`
          });
        }
      },
      
      async submitPR() {
        if (this.processing) return;
        
        this.processing = true;
        try {
          const response = await axios.patch(`/purchase-requisitions/${this.id}/status`, {
            status: 'pending'
          });
          
          // Update PR data
          this.pr.status = response.data.data.status;
          this.pr.submitted_at = new Date();
          
          this.showAlert('success', 'Purchase Requisition submitted for approval successfully.');
        } catch (error) {
          console.error('Error submitting PR:', error);
          this.showAlert('danger', 'Failed to submit purchase requisition. Please try again.');
        } finally {
          this.processing = false;
        }
      },
      
      async cancelPR() {
        if (this.processing) return;
        
        this.processing = true;
        try {
          const response = await axios.patch(`/purchase-requisitions/${this.id}/status`, {
            status: 'canceled'
          });
          
          // Update PR data
          this.pr.status = response.data.data.status;
          
          // Hide modal
          this.showCancelConfirmation = false;
          
          this.showAlert('success', 'Purchase Requisition canceled successfully.');
        } catch (error) {
          console.error('Error canceling PR:', error);
          this.showAlert('danger', 'Failed to cancel purchase requisition. Please try again.');
        } finally {
          this.processing = false;
        }
      },
      
      printPR() {
        window.print();
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
      
      formatDateTime(dateString) {
        if (!dateString) return 'N/A';
        
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', {
          year: 'numeric',
          month: 'short',
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        });
      },
      
      formatNumber(value) {
        if (value === null || value === undefined) return 'N/A';
        
        return new Intl.NumberFormat('id-ID', {
          minimumFractionDigits: 0,
          maximumFractionDigits: 2
        }).format(value);
      },
      
      getStatusBadgeClass(status) {
        const statusClasses = {
          draft: 'badge badge-secondary',
          pending: 'badge badge-warning',
          approved: 'badge badge-success',
          rejected: 'badge badge-danger',
          canceled: 'badge badge-dark'
        };
        
        return statusClasses[status] || 'badge badge-secondary';
      },
      
      capitalizeStatus(status) {
        if (!status) return '';
        return status.charAt(0).toUpperCase() + status.slice(1);
      },
      
      getDocumentIcon(docType) {
        const iconClasses = {
          rfq: 'fas fa-file-invoice-dollar',
          po: 'fas fa-clipboard-list',
          gr: 'fas fa-truck-loading',
          invoice: 'fas fa-file-invoice'
        };
        
        return iconClasses[docType] || 'fas fa-file';
      },
      
      showAlert(type, message) {
        this.alert = {
          show: true,
          type,
          message
        };
        
        // Hide alert after 5 seconds
        setTimeout(() => {
          this.alert.show = false;
        }, 5000);
      }
    }
  };
  </script>
  
  <style scoped>
  .purchase-requisition-detail {
    margin-bottom: 2rem;
  }
  
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
  }
  
  .action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }
  
  .card {
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: none;
    border-radius: 0.375rem;
    margin-bottom: 1.5rem;
  }
  
  .card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    padding: 0.75rem 1rem;
  }
  
  .card-title {
    color: #495057;
    font-size: 1.1rem;
    font-weight: 600;
  }
  
  .detail-item {
    margin-bottom: 0.75rem;
  }
  
  .detail-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #6c757d;
    font-weight: 600;
    margin-bottom: 0.25rem;
  }
  
  .detail-value {
    font-size: 1rem;
    color: #212529;
  }
  
  .notes-value {
    white-space: pre-line;
    min-height: 1.5rem;
  }
  
  .table th {
    background-color: #f8f9fa;
    color: #495057;
    font-weight: 600;
    border-top: none;
  }
  
  .badge {
    font-size: 0.75rem;
    font-weight: 500;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    text-transform: capitalize;
  }
  
  .item-info {
    display: flex;
    flex-direction: column;
  }
  
  .item-code {
    font-size: 0.875rem;
    font-weight: 600;
    color: #495057;
  }
  
  .item-name {
    font-size: 0.75rem;
    color: #6c757d;
  }
  
  /* Timeline styles */
  .timeline {
    position: relative;
    padding: 1rem;
    margin: 0;
    list-style: none;
  }
  
  .timeline:before {
    content: '';
    position: absolute;
    top: 0;
    left: 1.75rem;
    height: 100%;
    width: 2px;
    background-color: #e9ecef;
  }
  
  .timeline-item {
    position: relative;
    padding-left: 2.5rem;
    padding-bottom: 1.5rem;
  }
  
  .timeline-item:last-child {
    padding-bottom: 0;
  }
  
  .timeline-marker {
    position: absolute;
    left: 0;
    width: 1.5rem;
    height: 1.5rem;
    background-color: #f8f9fa;
    border-radius: 50%;
    border: 2px solid #e9ecef;
    top: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #adb5bd;
    font-size: 0.75rem;
  }
  
  .timeline-marker.done {
    background-color: #28a745;
    border-color: #28a745;
    color: white;
  }
  
  .timeline-marker.current {
    background-color: #ffc107;
    border-color: #ffc107;
    color: white;
  }
  
  .timeline-content {
    padding-top: 0.1rem;
  }
  
  .timeline-title {
    font-size: 0.875rem;
    font-weight: 600;
    margin: 0 0 0.25rem;
    color: #495057;
  }
  
  .timeline-date {
    font-size: 0.75rem;
    color: #6c757d;
    margin: 0;
  }
  
  .timeline-info {
    font-size: 0.75rem;
    color: #6c757d;
    margin: 0.25rem 0 0;
  }
  
  .rfq-link {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
  }
  
  .rfq-link:hover {
    text-decoration: underline;
  }
  
  /* Related Documents styles */
  .related-docs-list {
    list-style: none;
    padding: 0;
    margin: 0;
  }
  
  .related-doc-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #e9ecef;
  }
  
  .related-doc-item:last-child {
    border-bottom: none;
  }
  
  .doc-icon {
    width: 2rem;
    height: 2rem;
    background-color: #e9ecef;
    border-radius: 0.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #495057;
    margin-right: 0.75rem;
  }
  
  .doc-info {
    display: flex;
    flex-direction: column;
  }
  
  .doc-number {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--primary-color);
    text-decoration: none;
  }
  
  .doc-number:hover {
    text-decoration: underline;
  }
  
  .doc-date {
    font-size: 0.75rem;
    color: #6c757d;
  }
  
  /* Modal Styles */
  .modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
  }
  
  .modal {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 1001;
    display: block;
    width: 100%;
    max-width: 500px;
  }
  
  .modal-content {
    background-color: white;
    border-radius: 0.375rem;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  }
  
  .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid #dee2e6;
  }
  
  .modal-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
  }
  
  .close {
    background-color: transparent;
    border: none;
    font-size: 1.5rem;
    font-weight: 700;
    color: #000;
    opacity: 0.5;
    cursor: pointer;
  }
  
  .close:hover {
    opacity: 0.75;
  }
  
  .modal-body {
    padding: 1rem;
  }
  
  .modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    padding: 1rem;
    border-top: 1px solid #dee2e6;
  }
  
  .alert-modal {
    position: fixed;
    top: 20px;
    right: 20px;
    width: 380px;
    z-index: 1050;
  }
  
  .alert {
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border-left-width: 4px;
  }
  
  @media print {
    .action-buttons,
    .alert-modal,
    .modal-backdrop,
    .modal {
      display: none !important;
    }
    
    .card {
      break-inside: avoid;
      box-shadow: none;
      border: 1px solid #dee2e6;
    }
  }
  
  @media (max-width: 768px) {
    .page-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }
    
    .action-buttons {
      width: 100%;
      justify-content: flex-start;
    }
    
    .alert-modal {
      width: calc(100% - 40px);
      top: 10px;
      right: 20px;
    }
  }
  </style>