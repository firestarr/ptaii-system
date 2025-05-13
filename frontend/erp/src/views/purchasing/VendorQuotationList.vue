<!-- src/views/purchasing/VendorQuotationList.vue -->
<template>
    <div class="quotation-list-container">
      <div class="page-header">
        <h1>Vendor Quotations</h1>
        <router-link to="/purchasing/quotations/create" class="btn btn-primary">
          <i class="fas fa-plus"></i> New Quotation
        </router-link>
      </div>
  
      <div class="filters-container">
        <div class="search-box">
          <i class="fas fa-search search-icon"></i>
          <input 
            type="text" 
            v-model="searchQuery" 
            placeholder="Search by vendor or RFQ number" 
            @input="applyFilters"
          />
          <button v-if="searchQuery" @click="clearSearch" class="clear-search">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <div class="filter-group">
          <label>Status</label>
          <select v-model="filters.status" @change="applyFilters">
            <option value="">All Statuses</option>
            <option value="received">Received</option>
            <option value="accepted">Accepted</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
        
        <div class="filter-group">
          <label>Date Range</label>
          <div class="date-inputs">
            <input type="date" v-model="filters.dateFrom" @change="applyFilters" />
            <span>to</span>
            <input type="date" v-model="filters.dateTo" @change="applyFilters" />
          </div>
        </div>
      </div>
  
      <div v-if="loading" class="loading-state">
        <i class="fas fa-spinner fa-spin"></i> Loading quotations...
      </div>
  
      <div v-else-if="quotations.length === 0" class="empty-state">
        <i class="fas fa-file-invoice-dollar empty-icon"></i>
        <h3>No quotations found</h3>
        <p>No vendor quotations match your current filters. Try adjusting your search criteria.</p>
      </div>
  
      <div v-else class="table-responsive">
        <table class="quotations-table">
          <thead>
            <tr>
              <th @click="sortBy('quotation_id')">
                ID
                <i v-if="sortField === 'quotation_id'" 
                   :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th @click="sortBy('vendor.name')">
                Vendor
                <i v-if="sortField === 'vendor.name'" 
                   :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th @click="sortBy('rfq_id')">
                RFQ Number
                <i v-if="sortField === 'rfq_id'" 
                   :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th @click="sortBy('quotation_date')">
                Quotation Date
                <i v-if="sortField === 'quotation_date'" 
                   :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th @click="sortBy('validity_date')">
                Valid Until
                <i v-if="sortField === 'validity_date'" 
                   :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th @click="sortBy('status')">
                Status
                <i v-if="sortField === 'status'" 
                   :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="quotation in quotations" :key="quotation.quotation_id">
              <td>{{ quotation.quotation_id }}</td>
              <td>{{ quotation.vendor ? quotation.vendor.name : 'N/A' }}</td>
              <td>{{ quotation.requestForQuotation ? quotation.requestForQuotation.rfq_number : 'N/A' }}</td>
              <td>{{ formatDate(quotation.quotation_date) }}</td>
              <td>{{ formatDate(quotation.validity_date) }}</td>
              <td>
                <span class="status-badge" :class="statusClass(quotation.status)">
                  {{ capitalizeFirstLetter(quotation.status) }}
                </span>
              </td>
              <td class="actions-cell">
                <router-link :to="`/purchasing/quotations/${quotation.quotation_id}`" class="btn-icon" title="View">
                  <i class="fas fa-eye"></i>
                </router-link>
                <router-link v-if="quotation.status === 'received'" 
                  :to="`/purchasing/quotations/${quotation.quotation_id}/edit`" 
                  class="btn-icon" 
                  title="Edit">
                  <i class="fas fa-edit"></i>
                </router-link>
                <button v-if="quotation.status === 'received'" 
                  @click="changeStatus(quotation.quotation_id, 'accepted')" 
                  class="btn-icon" 
                  title="Accept">
                  <i class="fas fa-check"></i>
                </button>
                <button v-if="quotation.status === 'received'" 
                  @click="changeStatus(quotation.quotation_id, 'rejected')" 
                  class="btn-icon" 
                  title="Reject">
                  <i class="fas fa-times"></i>
                </button>
                <router-link v-if="quotation.status === 'accepted'" 
                  :to="`/purchasing/quotations/${quotation.quotation_id}/create-po`" 
                  class="btn-icon" 
                  title="Create PO">
                  <i class="fas fa-file-invoice"></i>
                </router-link>
                <button v-if="quotation.status === 'received'" 
                  @click="confirmDelete(quotation.quotation_id)" 
                  class="btn-icon danger" 
                  title="Delete">
                  <i class="fas fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
  
      <div class="pagination">
        <div class="pagination-info">
          Showing {{ pagination.from || 0 }} to {{ pagination.to || 0 }} of {{ pagination.total || 0 }} quotations
        </div>
        <div class="pagination-controls">
          <button class="pagination-btn" :disabled="!pagination.prev_page_url" @click="goToPage(pagination.current_page - 1)">
            <i class="fas fa-chevron-left"></i>
          </button>
          
          <template v-for="page in displayedPages" :key="page">
            <button v-if="page !== '...'" 
              class="pagination-btn" 
              :class="{ active: page === pagination.current_page }"
              @click="goToPage(page)">
              {{ page }}
            </button>
            <span v-else class="pagination-ellipsis">...</span>
          </template>
          
          <button class="pagination-btn" :disabled="!pagination.next_page_url" @click="goToPage(pagination.current_page + 1)">
            <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
  
      <!-- Confirmation Modal -->
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
            <p>Are you sure you want to delete this quotation? This action cannot be undone.</p>
            
            <div class="form-actions">
              <button type="button" class="btn btn-secondary" @click="showDeleteModal = false">
                Cancel
              </button>
              <button type="button" class="btn btn-danger" @click="deleteQuotation">
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
    name: 'VendorQuotationList',
    data() {
      return {
        quotations: [],
        loading: true,
        searchQuery: '',
        filters: {
          status: '',
          dateFrom: '',
          dateTo: ''
        },
        pagination: {
          current_page: 1,
          from: null,
          to: null,
          total: 0,
          per_page: 15,
          last_page: 1,
          prev_page_url: null,
          next_page_url: null
        },
        sortField: 'quotation_date',
        sortDirection: 'desc',
        showDeleteModal: false,
        quotationToDelete: null
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
    methods: {
      fetchQuotations() {
        this.loading = true;
        
        // Construct query parameters
        let params = {
          page: this.pagination.current_page,
          sort_field: this.sortField,
          sort_direction: this.sortDirection
        };
        
        // Add search query if present
        if (this.searchQuery) {
          params.search = this.searchQuery;
        }
        
        // Add filters if present
        if (this.filters.status) {
          params.status = this.filters.status;
        }
        
        if (this.filters.dateFrom) {
          params.date_from = this.filters.dateFrom;
        }
        
        if (this.filters.dateTo) {
          params.date_to = this.filters.dateTo;
        }
        
        axios.get('/vendor-quotations', { params })
          .then(response => {
            if (response.data.status === 'success') {
              this.quotations = response.data.data.data;
              
              // Update pagination
              this.pagination = {
                current_page: response.data.data.current_page,
                from: response.data.data.from,
                to: response.data.data.to,
                total: response.data.data.total,
                per_page: response.data.data.per_page,
                last_page: response.data.data.last_page,
                prev_page_url: response.data.data.prev_page_url,
                next_page_url: response.data.data.next_page_url
              };
            } else {
              console.error('Failed to fetch quotations');
            }
          })
          .catch(error => {
            console.error('API Error:', error);
          })
          .finally(() => {
            this.loading = false;
          });
      },
      
      applyFilters() {
        this.pagination.current_page = 1;
        this.fetchQuotations();
      },
      
      clearSearch() {
        this.searchQuery = '';
        this.applyFilters();
      },
      
      sortBy(field) {
        if (this.sortField === field) {
          // Toggle sort direction
          this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
          // Set new sort field with default descending order
          this.sortField = field;
          this.sortDirection = 'desc';
        }
        
        this.fetchQuotations();
      },
      
      goToPage(page) {
        if (page >= 1 && page <= this.pagination.last_page) {
          this.pagination.current_page = page;
          this.fetchQuotations();
        }
      },
      
      changeStatus(id, newStatus) {
        axios.patch(`/vendor-quotations/${id}/status`, { status: newStatus })
          .then(response => {
            if (response.data.status === 'success') {
              // Update the local data to reflect the change
              const index = this.quotations.findIndex(q => q.quotation_id === id);
              if (index !== -1) {
                this.quotations[index].status = newStatus;
              }
              
              // Show success notification
              this.$toasted.success(`Quotation ${newStatus} successfully.`);
            } else {
              this.$toasted.error(`Failed to update status: ${response.data.message}`);
            }
          })
          .catch(error => {
            console.error('API Error:', error);
            this.$toasted.error('An error occurred while updating status');
          });
      },
      
      confirmDelete(id) {
        this.quotationToDelete = id;
        this.showDeleteModal = true;
      },
      
      deleteQuotation() {
        if (!this.quotationToDelete) return;
        
        axios.delete(`/vendor-quotations/${this.quotationToDelete}`)
          .then(response => {
            if (response.data.status === 'success') {
              // Remove from local data
              this.quotations = this.quotations.filter(q => q.quotation_id !== this.quotationToDelete);
              
              // Show success notification
              this.$toasted.success('Quotation deleted successfully.');
              
              // Close modal
              this.showDeleteModal = false;
              this.quotationToDelete = null;
              
              // Refresh data if needed
              if (this.quotations.length === 0 && this.pagination.current_page > 1) {
                this.pagination.current_page--;
                this.fetchQuotations();
              }
            } else {
              this.$toasted.error(`Failed to delete: ${response.data.message}`);
            }
          })
          .catch(error => {
            console.error('API Error:', error);
            this.$toasted.error('An error occurred while deleting');
          });
      },
      
      formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
      },
      
      statusClass(status) {
        switch (status) {
          case 'received': return 'status-info';
          case 'accepted': return 'status-success';
          case 'rejected': return 'status-danger';
          default: return '';
        }
      },
      
      capitalizeFirstLetter(string) {
        if (!string) return '';
        return string.charAt(0).toUpperCase() + string.slice(1);
      }
    },
    mounted() {
      this.fetchQuotations();
    }
  };
  </script>
  
  <style scoped>
  .quotation-list-container {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
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
  
  .btn {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    border-radius: 0.375rem;
    cursor: pointer;
    transition: all 0.2s;
  }
  
  .btn-primary {
    background-color: var(--primary-color);
    color: white;
    border: none;
    text-decoration: none;
  }
  
  .btn-primary:hover {
    background-color: var(--primary-dark);
    text-decoration: none;
  }
  
  .btn-primary i {
    margin-right: 0.5rem;
  }
  
  .filters-container {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
  }
  
  .search-box {
    position: relative;
    flex-grow: 1;
    max-width: 300px;
  }
  
  .search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-500);
  }
  
  .search-box input {
    width: 100%;
    padding: 0.625rem 2.25rem;
    border: 1px solid var(--gray-200);
    border-radius: 0.375rem;
    font-size: 0.875rem;
  }
  
  .clear-search {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--gray-500);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.25rem;
    border-radius: 9999px;
  }
  
  .filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }
  
  .filter-group label {
    font-size: 0.75rem;
    color: var(--gray-500);
    font-weight: 500;
  }
  
  .filter-group select,
  .filter-group input {
    padding: 0.5rem;
    border: 1px solid var(--gray-200);
    border-radius: 0.375rem;
    font-size: 0.875rem;
    min-width: 8rem;
  }
  
  .date-inputs {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  
  .date-inputs span {
    color: var(--gray-500);
    font-size: 0.875rem;
  }
  
  .loading-state {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 3rem 0;
    color: var(--gray-500);
  }
  
  .loading-state i {
    margin-right: 0.5rem;
    animation: spin 1s linear infinite;
  }
  
  .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 0;
    text-align: center;
  }
  
  .empty-icon {
    font-size: 3rem;
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
  
  .table-responsive {
    overflow-x: auto;
  }
  
  .quotations-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
  }
  
  .quotations-table th {
    text-align: left;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-200);
    background-color: var(--gray-50);
    font-weight: 500;
    color: var(--gray-600);
    cursor: pointer;
  }
  
  .quotations-table th i {
    margin-left: 0.5rem;
  }
  
  .quotations-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-100);
    color: var(--gray-800);
  }
  
  .quotations-table tr:hover td {
    background-color: var(--gray-50);
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
  
  .status-warning {
    background-color: #fff7ed;
    color: #9a3412;
  }
  
  .actions-cell {
    display: flex;
    gap: 0.5rem;
  }
  
  .btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border: 1px solid var(--gray-200);
    border-radius: 0.375rem;
    cursor: pointer;
    color: var(--gray-600);
    transition: all 0.2s;
    text-decoration: none;
  }
  
  .btn-icon:hover {
    background-color: var(--gray-100);
    color: var(--gray-800);
  }
  
  .btn-icon.danger {
    color: #b91c1c;
  }
  
  .btn-icon.danger:hover {
    background-color: #fee2e2;
  }
  
  .pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    margin-top: 1rem;
    border-top: 1px solid var(--gray-200);
  }
  
  .pagination-info {
    color: var(--gray-500);
    font-size: 0.875rem;
  }
  
  .pagination-controls {
    display: flex;
    gap: 0.25rem;
  }
  
  .pagination-btn {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 2rem;
    height: 2rem;
    border-radius: 0.375rem;
    background: none;
    border: 1px solid var(--gray-200);
    color: var(--gray-500);
    cursor: pointer;
  }
  
  .pagination-btn:hover:not(:disabled) {
    background-color: var(--gray-100);
    color: var(--gray-800);
  }
  
  .pagination-btn.active {
    background-color: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
  }
  
  .pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
  
  .pagination-ellipsis {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 2rem;
    height: 2rem;
    color: var(--gray-500);
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
  
  .btn-secondary {
    background-color: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
  }
  
  .btn-secondary:hover {
    background-color: var(--gray-200);
  }
  
  .btn-danger {
    background-color: #ef4444;
    color: white;
    border: none;
  }
  
  .btn-danger:hover {
    background-color: #b91c1c;
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
  
    .filters-container {
      flex-direction: column;
    }
  
    .search-box {
      max-width: none;
    }
  
    .pagination {
      flex-direction: column;
      gap: 1rem;
    }
  }
  </style>