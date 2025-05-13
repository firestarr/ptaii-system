<!-- src/views/purchasing/PurchaseRequisitionList.vue -->
<template>
    <div class="purchase-requisition-list">
      <div class="page-header">
        <h1>Purchase Requisitions</h1>
        <router-link to="/purchasing/requisitions/create" class="btn btn-primary">
          <i class="fas fa-plus"></i> Create New PR
        </router-link>
      </div>
  
      <div class="card filters-card">
        <div class="card-body">
          <div class="row">
            <div class="col-md-3 form-group">
              <label for="status">Status</label>
              <select v-model="filters.status" id="status" class="form-control" @change="fetchPurchaseRequisitions()">
                <option value="">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="canceled">Canceled</option>
              </select>
            </div>
            <div class="col-md-3 form-group">
              <label for="search">Search</label>
              <div class="input-group">
                <input
                  type="text"
                  v-model="filters.search"
                  id="search"
                  class="form-control"
                  placeholder="PR Number or Notes"
                  @keyup.enter="fetchPurchaseRequisitions()"
                />
                <div class="input-group-append">
                  <button class="btn btn-outline-secondary" type="button" @click="fetchPurchaseRequisitions()">
                    <i class="fas fa-search"></i>
                  </button>
                </div>
              </div>
            </div>
            <div class="col-md-3 form-group">
              <label for="dateFrom">Date From</label>
              <input
                type="date"
                v-model="filters.dateFrom"
                id="dateFrom"
                class="form-control"
                @change="fetchPurchaseRequisitions()"
              />
            </div>
            <div class="col-md-3 form-group">
              <label for="dateTo">Date To</label>
              <input
                type="date"
                v-model="filters.dateTo"
                id="dateTo"
                class="form-control"
                @change="fetchPurchaseRequisitions()"
              />
            </div>
          </div>
        </div>
      </div>
  
      <div v-if="loading" class="text-center my-4">
        <div class="spinner-border text-primary" role="status">
          <span class="sr-only">Loading...</span>
        </div>
      </div>
  
      <div v-else-if="purchaseRequisitions.length === 0" class="empty-state my-4">
        <div class="empty-state-container">
          <i class="fas fa-file-alt empty-state-icon"></i>
          <h3>No Purchase Requisitions Found</h3>
          <p>No purchase requisitions match your current filters. Try adjusting your filters or create a new requisition.</p>
          <router-link to="/purchasing/requisitions/create" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create New PR
          </router-link>
        </div>
      </div>
  
      <div v-else class="table-responsive">
        <table class="table table-striped table-hover">
          <thead>
            <tr>
              <th @click="sortBy('pr_number')" class="sortable-column">
                PR Number
                <i v-if="sortField === 'pr_number'" :class="[sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down']"></i>
              </th>
              <th @click="sortBy('pr_date')" class="sortable-column">
                Date
                <i v-if="sortField === 'pr_date'" :class="[sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down']"></i>
              </th>
              <th>Requester</th>
              <th @click="sortBy('status')" class="sortable-column">
                Status
                <i v-if="sortField === 'status'" :class="[sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down']"></i>
              </th>
              <th>Items</th>
              <th>Notes</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="pr in purchaseRequisitions" :key="pr.pr_id">
              <td>
                <router-link :to="`/purchasing/requisitions/${pr.pr_id}`" class="pr-number-link">
                  {{ pr.pr_number }}
                </router-link>
              </td>
              <td>{{ formatDate(pr.pr_date) }}</td>
              <td>{{ pr.requester ? pr.requester.name : 'N/A' }}</td>
              <td>
                <span :class="getStatusBadgeClass(pr.status)">{{ capitalizeStatus(pr.status) }}</span>
              </td>
              <td>{{ pr.lines ? pr.lines.length : 0 }}</td>
              <td class="notes-cell">{{ truncateText(pr.notes, 50) }}</td>
              <td class="text-right">
                <div class="actions-container">
                  <router-link :to="`/purchasing/requisitions/${pr.pr_id}`" class="btn btn-sm btn-info mr-1" title="View">
                    <i class="fas fa-eye"></i>
                  </router-link>
                  
                  <router-link 
                    v-if="pr.status === 'draft'" 
                    :to="`/purchasing/requisitions/${pr.pr_id}/edit`" 
                    class="btn btn-sm btn-primary mr-1" 
                    title="Edit"
                  >
                    <i class="fas fa-edit"></i>
                  </router-link>
                  
                  <router-link 
                    v-if="pr.status === 'pending'" 
                    :to="`/purchasing/requisitions/${pr.pr_id}/approve`" 
                    class="btn btn-sm btn-success mr-1" 
                    title="Approve/Reject"
                  >
                    <i class="fas fa-check-circle"></i>
                  </router-link>
                  
                  <router-link 
                    v-if="pr.status === 'approved'" 
                    :to="`/purchasing/requisitions/${pr.pr_id}/convert`" 
                    class="btn btn-sm btn-warning mr-1" 
                    title="Convert to RFQ"
                  >
                    <i class="fas fa-exchange-alt"></i>
                  </router-link>
                  
                  <button 
                    v-if="['draft', 'pending'].includes(pr.status)" 
                    @click="confirmCancelPR(pr)" 
                    class="btn btn-sm btn-danger" 
                    title="Cancel"
                  >
                    <i class="fas fa-times"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
  
      <div class="pagination-container" v-if="purchaseRequisitions.length > 0">
        <div class="pagination-info">
          Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} entries
        </div>
        <div class="pagination-controls">
          <button 
            class="btn btn-sm btn-outline-secondary" 
            :disabled="pagination.current_page === 1" 
            @click="goToPage(pagination.current_page - 1)"
          >
            <i class="fas fa-chevron-left"></i>
          </button>
          
          <template v-for="page in displayedPages" :key="page">
            <button 
              v-if="page !== '...'" 
              class="btn btn-sm" 
              :class="page === pagination.current_page ? 'btn-primary' : 'btn-outline-secondary'"
              @click="goToPage(page)"
            >
              {{ page }}
            </button>
            <span v-else class="pagination-ellipsis">...</span>
          </template>
          
          <button 
            class="btn btn-sm btn-outline-secondary" 
            :disabled="pagination.current_page === pagination.last_page" 
            @click="goToPage(pagination.current_page + 1)"
          >
            <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
  
      <!-- Confirmation Modal -->
      <div v-if="showCancelModal" class="modal-backdrop" @click="showCancelModal = false"></div>
      <div v-if="showCancelModal" class="modal cancel-modal">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Cancel Purchase Requisition</h5>
            <button type="button" class="close" @click="showCancelModal = false">
              <span>&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to cancel purchase requisition <strong>{{ selectedPR ? selectedPR.pr_number : '' }}</strong>?</p>
            <p>This action cannot be undone.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showCancelModal = false">Close</button>
            <button type="button" class="btn btn-danger" @click="cancelPR">Confirm Cancel</button>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'PurchaseRequisitionList',
    data() {
      return {
        purchaseRequisitions: [],
        loading: true,
        filters: {
          status: '',
          search: '',
          dateFrom: '',
          dateTo: '',
        },
        sortField: 'pr_date',
        sortDirection: 'desc',
        pagination: {
          current_page: 1,
          from: 1,
          to: 10,
          total: 0,
          per_page: 10,
          last_page: 1
        },
        showCancelModal: false,
        selectedPR: null
      };
    },
    computed: {
      displayedPages() {
        const total = this.pagination.last_page;
        const current = this.pagination.current_page;
        const pages = [];
        
        if (total <= 7) {
          // Show all pages if 7 or fewer
          for (let i = 1; i <= total; i++) {
            pages.push(i);
          }
        } else {
          // Always include first page
          pages.push(1);
          
          // Show ellipsis if current page is more than 3
          if (current > 3) {
            pages.push('...');
          }
          
          // Add pages around current page
          const startPage = Math.max(2, current - 1);
          const endPage = Math.min(total - 1, current + 1);
          
          for (let i = startPage; i <= endPage; i++) {
            pages.push(i);
          }
          
          // Show ellipsis if current page is less than total - 2
          if (current < total - 2) {
            pages.push('...');
          }
          
          // Always include last page
          if (total > 1) {
            pages.push(total);
          }
        }
        
        return pages;
      }
    },
    created() {
      this.fetchPurchaseRequisitions();
    },
    methods: {
      async fetchPurchaseRequisitions() {
        this.loading = true;
        try {
          // Build query parameters
          const params = {
            page: this.pagination.current_page,
            per_page: this.pagination.per_page,
            sort_field: this.sortField,
            sort_direction: this.sortDirection
          };
  
          // Add filters if they are set
          if (this.filters.status) params.status = this.filters.status;
          if (this.filters.search) params.search = this.filters.search;
          if (this.filters.dateFrom) params.date_from = this.filters.dateFrom;
          if (this.filters.dateTo) params.date_to = this.filters.dateTo;
  
          const response = await axios.get('/purchase-requisitions', { params });
          
          this.purchaseRequisitions = response.data.data.data; // Access the data array
          
          // Update pagination
          this.pagination = {
            current_page: response.data.data.current_page,
            from: response.data.data.from,
            to: response.data.data.to,
            total: response.data.data.total,
            per_page: response.data.data.per_page,
            last_page: response.data.data.last_page
          };
        } catch (error) {
          console.error('Error fetching purchase requisitions:', error);
          // Show an error message to the user
          this.$root.$emit('showAlert', {
            type: 'danger',
            message: 'Failed to load purchase requisitions. Please try again.'
          });
        } finally {
          this.loading = false;
        }
      },
      
      sortBy(field) {
        // If we click on the same field, reverse the sort direction
        if (this.sortField === field) {
          this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
          this.sortField = field;
          this.sortDirection = 'asc';
        }
        
        // Reset to first page and fetch data
        this.pagination.current_page = 1;
        this.fetchPurchaseRequisitions();
      },
      
      goToPage(page) {
        if (page < 1 || page > this.pagination.last_page) return;
        
        this.pagination.current_page = page;
        this.fetchPurchaseRequisitions();
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
      
      truncateText(text, maxLength) {
        if (!text) return '';
        if (text.length <= maxLength) return text;
        
        return text.substring(0, maxLength) + '...';
      },
      
      confirmCancelPR(pr) {
        this.selectedPR = pr;
        this.showCancelModal = true;
      },
      
      async cancelPR() {
        if (!this.selectedPR) return;
        
        try {
          await axios.patch(`/purchase-requisitions/${this.selectedPR.pr_id}/status`, {
            status: 'canceled'
          });
          
          // Hide the modal
          this.showCancelModal = false;
          
          // Show success message
          this.$root.$emit('showAlert', {
            type: 'success',
            message: `Purchase Requisition ${this.selectedPR.pr_number} has been canceled successfully.`
          });
          
          // Refresh the data
          this.fetchPurchaseRequisitions();
        } catch (error) {
          console.error('Error canceling purchase requisition:', error);
          
          // Show error message
          this.$root.$emit('showAlert', {
            type: 'danger',
            message: 'Failed to cancel the purchase requisition. Please try again.'
          });
        }
      }
    }
  };
  </script>
  
  <style scoped>
  .purchase-requisition-list {
    margin-bottom: 2rem;
  }
  
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
  }
  
  .filters-card {
    margin-bottom: 1.5rem;
    background-color: #f8f9fa;
    border: none;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }
  
  .table-responsive {
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border-radius: 0.25rem;
    overflow: hidden;
  }
  
  .table {
    margin-bottom: 0;
  }
  
  .table th {
    background-color: #f8f9fa;
    color: #495057;
    font-weight: 600;
  }
  
  .sortable-column {
    cursor: pointer;
  }
  
  .sortable-column i {
    margin-left: 0.25rem;
  }
  
  .notes-cell {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  
  .pr-number-link {
    color: var(--primary-color);
    font-weight: 500;
    text-decoration: none;
  }
  
  .pr-number-link:hover {
    text-decoration: underline;
  }
  
  .badge {
    font-size: 0.75rem;
    font-weight: 500;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    text-transform: capitalize;
  }
  
  .actions-container {
    display: flex;
    justify-content: flex-end;
  }
  
  .pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1rem;
  }
  
  .pagination-info {
    color: #6c757d;
    font-size: 0.875rem;
  }
  
  .pagination-controls {
    display: flex;
    gap: 0.25rem;
  }
  
  .pagination-ellipsis {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
  }
  
  .empty-state {
    padding: 3rem 0;
    text-align: center;
  }
  
  .empty-state-container {
    display: inline-block;
    max-width: 500px;
    margin: 0 auto;
  }
  
  .empty-state-icon {
    font-size: 3rem;
    color: #dee2e6;
    margin-bottom: 1rem;
  }
  
  .empty-state h3 {
    font-size: 1.25rem;
    color: #343a40;
    margin-bottom: 0.5rem;
  }
  
  .empty-state p {
    color: #6c757d;
    margin-bottom: 1.5rem;
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
  
  /* Responsive adjustments */
  @media (max-width: 768px) {
    .pagination-container {
      flex-direction: column;
      gap: 1rem;
    }
    
    .pagination-info {
      text-align: center;
    }
    
    .pagination-controls {
      justify-content: center;
    }
    
    .actions-container {
      display: flex;
      flex-wrap: wrap;
      gap: 0.25rem;
    }
  }
  </style>