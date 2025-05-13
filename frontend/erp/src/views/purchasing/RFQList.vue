<!-- src/views/purchasing/RFQList.vue -->
<template>
    <div class="rfq-list-container">
      <div class="page-header">
        <h1>Request for Quotations</h1>
        <router-link to="/purchasing/rfqs/create" class="btn btn-primary">
          <i class="fas fa-plus"></i> Create RFQ
        </router-link>
      </div>
  
      <div class="search-filter-container">
        <div class="search-box">
          <i class="fas fa-search search-icon"></i>
          <input 
            type="text" 
            v-model="filters.search" 
            placeholder="Search RFQ number..." 
            @input="loadData"
          />
          <button v-if="filters.search" @click="clearSearch" class="clear-search">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <div class="filters">
          <div class="filter-group">
            <label>Status</label>
            <select v-model="filters.status" @change="loadData">
              <option value="">All Statuses</option>
              <option value="draft">Draft</option>
              <option value="sent">Sent</option>
              <option value="closed">Closed</option>
              <option value="canceled">Canceled</option>
            </select>
          </div>
          
          <div class="filter-group">
            <label>Date From</label>
            <input type="date" v-model="filters.dateFrom" @change="loadData" />
          </div>
          
          <div class="filter-group">
            <label>Date To</label>
            <input type="date" v-model="filters.dateTo" @change="loadData" />
          </div>
        </div>
      </div>
  
      <div class="rfq-table-container">
        <div v-if="loading" class="loading-indicator">
          <i class="fas fa-spinner fa-spin"></i> Loading...
        </div>
        
        <div v-else-if="rfqs.length === 0" class="empty-state">
          <div class="empty-icon">
            <i class="fas fa-file-invoice-dollar"></i>
          </div>
          <h3>No Request for Quotations Found</h3>
          <p>There are no RFQs matching your search criteria.</p>
        </div>
        
        <table v-else class="rfq-table">
          <thead>
            <tr>
              <th @click="updateSorting('rfq_number')">
                RFQ Number
                <i v-if="sortField === 'rfq_number'" 
                   :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th @click="updateSorting('rfq_date')">
                Date
                <i v-if="sortField === 'rfq_date'" 
                   :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th @click="updateSorting('validity_date')">
                Validity Date
                <i v-if="sortField === 'validity_date'" 
                   :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th @click="updateSorting('status')">
                Status
                <i v-if="sortField === 'status'" 
                   :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th>Items</th>
              <th>Quotations</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="rfq in rfqs" :key="rfq.rfq_id">
              <td>{{ rfq.rfq_number }}</td>
              <td>{{ formatDate(rfq.rfq_date) }}</td>
              <td>{{ formatDate(rfq.validity_date) }}</td>
              <td>
                <span class="status-badge" :class="getStatusClass(rfq.status)">
                  {{ capitalizeFirstLetter(rfq.status) }}
                </span>
              </td>
              <td>{{ rfq.lines ? rfq.lines.length : 0 }} items</td>
              <td>{{ rfq.vendor_quotations ? rfq.vendor_quotations.length : 0 }} quotations</td>
              <td class="actions-cell">
                <div class="action-buttons">
                  <router-link :to="`/purchasing/rfqs/${rfq.rfq_id}`" class="action-btn view-btn">
                    <i class="fas fa-eye"></i>
                  </router-link>
                  
                  <router-link v-if="rfq.status === 'draft'" :to="`/purchasing/rfqs/${rfq.rfq_id}/edit`" class="action-btn edit-btn">
                    <i class="fas fa-edit"></i>
                  </router-link>
                  
                  <router-link v-if="rfq.status === 'draft'" :to="`/purchasing/rfqs/${rfq.rfq_id}/send`" class="action-btn send-btn">
                    <i class="fas fa-paper-plane"></i>
                  </router-link>
                  
                  <router-link v-if="rfq.status === 'sent' && hasQuotations(rfq)" :to="`/purchasing/rfqs/${rfq.rfq_id}/compare`" class="action-btn compare-btn">
                    <i class="fas fa-balance-scale"></i>
                  </router-link>
                  
                  <button v-if="rfq.status === 'draft'" @click="confirmDelete(rfq)" class="action-btn delete-btn">
                    <i class="fas fa-trash"></i>
                  </button>
                  
                  <div class="status-actions">
                    <button v-if="rfq.status === 'draft'" @click="updateStatus(rfq.rfq_id, 'sent')" class="status-btn send-status">
                      Mark as Sent
                    </button>
                    
                    <button v-if="rfq.status === 'sent'" @click="updateStatus(rfq.rfq_id, 'closed')" class="status-btn close-status">
                      Close RFQ
                    </button>
                    
                    <button v-if="['draft', 'sent'].includes(rfq.status)" @click="updateStatus(rfq.rfq_id, 'canceled')" class="status-btn cancel-status">
                      Cancel
                    </button>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        
        <div class="pagination-container" v-if="totalPages > 1">
          <div class="pagination-info">
            Showing {{ from }} to {{ to }} of {{ total }} Request for Quotations
          </div>
          <div class="pagination-controls">
            <button 
              class="pagination-btn" 
              :disabled="currentPage === 1" 
              @click="goToPage(currentPage - 1)"
            >
              <i class="fas fa-chevron-left"></i>
            </button>
            
            <template v-for="page in displayedPages" :key="page">
              <button 
                v-if="page !== '...'" 
                class="pagination-btn" 
                :class="{ active: page === currentPage }"
                @click="goToPage(page)"
              >
                {{ page }}
              </button>
              <span v-else class="pagination-ellipsis">...</span>
            </template>
            
            <button 
              class="pagination-btn" 
              :disabled="currentPage === totalPages" 
              @click="goToPage(currentPage + 1)"
            >
              <i class="fas fa-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
  
      <!-- Confirmation Modal -->
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
            <p>Are you sure you want to delete RFQ <strong>{{ selectedRfq?.rfq_number }}</strong>?</p>
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
  
      <!-- Status Update Modal -->
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
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    name: 'RFQList',
    data() {
      return {
        rfqs: [],
        loading: true,
        filters: {
          search: '',
          status: '',
          dateFrom: '',
          dateTo: ''
        },
        sortField: 'rfq_date',
        sortDirection: 'desc',
        currentPage: 1,
        perPage: 10,
        total: 0,
        from: 0,
        to: 0,
        totalPages: 0,
        showDeleteModal: false,
        showStatusModal: false,
        selectedRfq: null,
        rfqIdToUpdate: null,
        newStatus: ''
      }
    },
    computed: {
      displayedPages() {
        const total = this.totalPages;
        const current = this.currentPage;
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
    mounted() {
      this.loadData();
    },
    methods: {
      async loadData() {
        this.loading = true;
        
        try {
          const params = {
            page: this.currentPage,
            per_page: this.perPage,
            sort_field: this.sortField,
            sort_direction: this.sortDirection
          };
          
          // Add filters to params if they have values
          if (this.filters.search) params.search = this.filters.search;
          if (this.filters.status) params.status = this.filters.status;
          if (this.filters.dateFrom) params.date_from = this.filters.dateFrom;
          if (this.filters.dateTo) params.date_to = this.filters.dateTo;
          
          const response = await axios.get('/request-for-quotations', { params });
          
          if (response.data.status === 'success') {
            this.rfqs = response.data.data.data;
            this.total = response.data.data.total;
            this.from = response.data.data.from;
            this.to = response.data.data.to;
            this.currentPage = response.data.data.current_page;
            this.totalPages = response.data.data.last_page;
          } else {
            console.error('Error loading RFQs:', response.data.message);
            this.$toast.error('Failed to load RFQs: ' + response.data.message);
          }
        } catch (error) {
          console.error('Error loading RFQs:', error);
          this.$toast.error('Failed to load RFQs. Please try again.');
        } finally {
          this.loading = false;
        }
      },
      updateSorting(field) {
        if (this.sortField === field) {
          this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
          this.sortField = field;
          this.sortDirection = 'asc';
        }
        
        this.loadData();
      },
      goToPage(page) {
        if (page !== this.currentPage && page >= 1 && page <= this.totalPages) {
          this.currentPage = page;
          this.loadData();
        }
      },
      clearSearch() {
        this.filters.search = '';
        this.loadData();
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
      getStatusClass(status) {
        switch (status) {
          case 'draft': return 'status-draft';
          case 'sent': return 'status-sent';
          case 'closed': return 'status-closed';
          case 'canceled': return 'status-canceled';
          default: return '';
        }
      },
      capitalizeFirstLetter(string) {
        if (!string) return '';
        return string.charAt(0).toUpperCase() + string.slice(1);
      },
      hasQuotations(rfq) {
        return rfq.vendor_quotations && rfq.vendor_quotations.length > 0;
      },
      confirmDelete(rfq) {
        this.selectedRfq = rfq;
        this.showDeleteModal = true;
      },
      async deleteRfq() {
        if (!this.selectedRfq) return;
        
        try {
          const response = await axios.delete(`/request-for-quotations/${this.selectedRfq.rfq_id}`);
          
          if (response.data.status === 'success') {
            this.$toast.success('RFQ deleted successfully');
            this.loadData();
          } else {
            this.$toast.error('Failed to delete RFQ: ' + response.data.message);
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
          this.selectedRfq = null;
        }
      },
      updateStatus(rfqId, status) {
        this.rfqIdToUpdate = rfqId;
        this.newStatus = status;
        this.showStatusModal = true;
      },
      async confirmUpdateStatus() {
        if (!this.rfqIdToUpdate || !this.newStatus) return;
        
        try {
          const response = await axios.patch(`/request-for-quotations/${this.rfqIdToUpdate}/status`, {
            status: this.newStatus
          });
          
          if (response.data.status === 'success') {
            this.$toast.success(`RFQ status updated to ${this.capitalizeFirstLetter(this.newStatus)}`);
            this.loadData();
          } else {
            this.$toast.error('Failed to update RFQ status: ' + response.data.message);
          }
        } catch (error) {
          console.error('Error updating RFQ status:', error);
          
          if (error.response && error.response.data && error.response.data.message) {
            this.$toast.error('Failed to update status: ' + error.response.data.message);
          } else {
            this.$toast.error('Failed to update RFQ status. Please try again.');
          }
        } finally {
          this.showStatusModal = false;
          this.rfqIdToUpdate = null;
          this.newStatus = '';
        }
      }
    }
  }
  </script>
  
  <style scoped>
  .rfq-list-container {
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
  
  .btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1rem;
    background-color: var(--primary-color);
    color: white;
    border: none;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: background-color 0.2s;
  }
  
  .btn-primary:hover {
    background-color: var(--primary-dark);
  }
  
  .search-filter-container {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
    margin-bottom: 1.5rem;
  }
  
  .search-box {
    position: relative;
    width: 100%;
    max-width: 320px;
    flex-grow: 1;
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
    transition: border-color 0.2s, box-shadow 0.2s;
  }
  
  .search-box input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
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
  
  .clear-search:hover {
    background-color: var(--gray-100);
    color: var(--gray-700);
  }
  
  .filters {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    align-items: center;
  }
  
  /* Filter Components Styling */
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
    background-color: white;
    min-width: 8rem;
  }
  
  .rfq-table-container {
    overflow-x: auto;
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
  
  .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 0;
    text-align: center;
  }
  
  .empty-icon {
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
  
  .rfq-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
  }
  
  .rfq-table th {
    text-align: left;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-200);
    background-color: var(--gray-50);
    font-weight: 500;
    color: var(--gray-600);
    cursor: pointer;
    transition: background-color 0.2s;
    white-space: nowrap;
  }
  
  .rfq-table th:hover {
    background-color: var(--gray-100);
  }
  
  .rfq-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-100);
    color: var(--gray-800);
    vertical-align: middle;
  }
  
  .rfq-table tr:hover td {
    background-color: var(--gray-50);
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
  
  .actions-cell {
    width: 1%;
    white-space: nowrap;
  }
  
  .action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }
  
  .action-btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    width: 2rem;
    height: 2rem;
    border-radius: 0.375rem;
    border: 1px solid var(--gray-200);
    color: var(--gray-500);
    background-color: white;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
  }
  
  .view-btn:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
  }
  
  .edit-btn:hover {
    border-color: #0ea5e9;
    color: #0ea5e9;
  }
  
  .send-btn:hover {
    border-color: #8b5cf6;
    color: #8b5cf6;
  }
  
  .compare-btn:hover {
    border-color: #f59e0b;
    color: #f59e0b;
  }
  
  .delete-btn:hover {
    border-color: #ef4444;
    color: #ef4444;
  }
  
  .status-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.5rem;
  }
  
  .status-btn {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
  }
  
  .send-status {
    background-color: #dbeafe;
    color: #1e40af;
  }
  
  .send-status:hover {
    background-color: #bfdbfe;
  }
  
  .close-status {
    background-color: #dcfce7;
    color: #166534;
  }
  
  .close-status:hover {
    background-color: #bbf7d0;
  }
  
  .cancel-status {
    background-color: #fee2e2;
    color: #991b1b;
  }
  
  .cancel-status:hover {
    background-color: #fecaca;
  }
  
  .pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0;
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
  
  .text-danger {
    color: #dc2626;
  }
  
  .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
  }
  
  .btn-secondary {
    background-color: #e5e7eb;
    color: #1f2937;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    border: none;
    cursor: pointer;
    transition: background-color 0.2s;
  }
  .btn-secondary:hover {
    background-color: #d1d5db;
  }
  .btn-danger {
    background-color: #ef4444;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    border: none;
    cursor: pointer;
    transition: background-color 0.2s;
  }
  .btn-danger:hover {
    background-color: #dc2626;
  }
  </style>