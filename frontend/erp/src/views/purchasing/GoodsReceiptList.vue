<!-- src/views/purchasing/GoodsReceiptList.vue -->
<template>
    <div class="goods-receipt-list">
      <div class="card">
        <div class="card-header">
          <h2>Goods Receipts</h2>
          <div class="actions">
            <router-link to="/purchasing/goods-receipts/create" class="btn btn-primary">
              <i class="fas fa-plus"></i> Create Receipt
            </router-link>
            <router-link to="/purchasing/goods-receipts/dashboard" class="btn btn-info ml-2">
              <i class="fas fa-tachometer-alt"></i> Dashboard
            </router-link>
          </div>
        </div>
  
        <div class="card-body">
          <!-- Search and filters -->
          <div class="filters-container">
            <div class="search-box">
              <input
                type="text"
                v-model="filters.search"
                placeholder="Search receipt number..."
                @input="applyFilters"
              />
              <i class="fas fa-search"></i>
            </div>
  
            <div class="filter-group">
              <label>Status:</label>
              <select v-model="filters.status" @change="applyFilters">
                <option value="">All</option>
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed</option>
              </select>
            </div>
  
            <div class="filter-group">
              <label>Vendor:</label>
              <select v-model="filters.vendor_id" @change="applyFilters">
                <option value="">All</option>
<option v-for="vendor in filteredVendors" :key="vendor.vendor_id" :value="vendor.vendor_id">
  {{ vendor.name }}
</option>
              </select>
            </div>
  
            <div class="filter-group">
              <label>Date From:</label>
              <input type="date" v-model="filters.date_from" @change="applyFilters" />
            </div>
  
            <div class="filter-group">
              <label>Date To:</label>
              <input type="date" v-model="filters.date_to" @change="applyFilters" />
            </div>
          </div>
  
          <!-- Loading indicator -->
          <div v-if="loading" class="loading-container">
            <i class="fas fa-spinner fa-spin"></i> Loading goods receipts...
          </div>
  
          <!-- Empty state -->
          <div v-else-if="receipts.length === 0" class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>No Goods Receipts Found</h3>
            <p>Try adjusting your search or filters, or create a new receipt.</p>
          </div>
  
          <!-- Receipts table -->
          <div v-else class="table-responsive">
            <table class="receipts-table">
              <thead>
                <tr>
                  <th @click="sortBy('receipt_number')">
                    Receipt # 
                    <i v-if="sortColumn === 'receipt_number'" 
                       :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                  </th>
                  <th @click="sortBy('receipt_date')">
                    Date 
                    <i v-if="sortColumn === 'receipt_date'" 
                       :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                  </th>
                  <th @click="sortBy('vendor.name')">
                    Vendor 
                    <i v-if="sortColumn === 'vendor.name'" 
                       :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                  </th>
                  <th>PO Numbers</th>
                  <th @click="sortBy('status')">
                    Status 
                    <i v-if="sortColumn === 'status'" 
                       :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
                  </th>
                  <th>Total Items</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="receipt in receipts" :key="receipt.receipt_id">
                  <td>{{ receipt.receipt_number }}</td>
                  <td>{{ formatDate(receipt.receipt_date) }}</td>
                  <td>{{ receipt.vendor ? receipt.vendor.name : 'N/A' }}</td>
                  <td>{{ receipt.po_numbers }}</td>
                  <td>
                    <span :class="'status-badge ' + receipt.status">
                      {{ receipt.status.charAt(0).toUpperCase() + receipt.status.slice(1) }}
                    </span>
                  </td>
                  <td>{{ receipt.total_items || calculateTotalItems(receipt) }}</td>
                  <td class="actions-cell">
                    <router-link :to="`/purchasing/goods-receipts/${receipt.receipt_id}`" class="btn-icon" title="View Details">
                      <i class="fas fa-eye"></i>
                    </router-link>
                    <router-link v-if="receipt.status === 'pending'" 
                        :to="`/purchasing/goods-receipts/${receipt.receipt_id}/edit`" 
                        class="btn-icon" title="Edit">
                      <i class="fas fa-edit"></i>
                    </router-link>
                    <router-link v-if="receipt.status === 'pending'" 
                        :to="`/purchasing/goods-receipts/${receipt.receipt_id}/confirm`" 
                        class="btn-icon confirm" title="Confirm Receipt">
                      <i class="fas fa-check-circle"></i>
                    </router-link>
                    <button v-if="receipt.status === 'pending'" 
                        @click="confirmDelete(receipt)" 
                        class="btn-icon delete" title="Delete">
                      <i class="fas fa-trash"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
  
          <!-- Pagination -->
          <div class="pagination-container" v-if="totalPages > 1">
            <button 
              class="pagination-btn" 
              :disabled="currentPage === 1" 
              @click="changePage(currentPage - 1)"
            >
              <i class="fas fa-chevron-left"></i>
            </button>
            
            <template v-for="page in displayedPages" :key="page">
              <button 
                v-if="page !== '...'" 
                class="pagination-btn" 
                :class="{ active: page === currentPage }"
                @click="changePage(page)"
              >
                {{ page }}
              </button>
              <span v-else class="pagination-ellipsis">...</span>
            </template>
            
            <button 
              class="pagination-btn" 
              :disabled="currentPage === totalPages" 
              @click="changePage(currentPage + 1)"
            >
              <i class="fas fa-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
  
      <!-- Delete Confirmation Modal -->
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
            <p>Are you sure you want to delete receipt <strong>{{ receiptToDelete?.receipt_number }}</strong>?</p>
            <p class="text-danger">This action cannot be undone.</p>
            
            <div class="form-actions">
              <button type="button" class="btn btn-secondary" @click="showDeleteModal = false">
                Cancel
              </button>
              <button
                type="button"
                class="btn btn-danger"
                @click="deleteReceipt"
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
    name: 'GoodsReceiptList',
data() {
  return {
    receipts: [],
    vendors: [],
    loading: true,
    filters: {
      search: '',
      status: '',
      vendor_id: '',
      date_from: '',
      date_to: ''
    },
    pagination: {
      total: 0,
      per_page: 15,
      current_page: 1,
      last_page: 1
    },
    sortColumn: 'receipt_date',
    sortDirection: 'desc',
    showDeleteModal: false,
    receiptToDelete: null
  };
},
computed: {
  currentPage() {
    return this.pagination.current_page;
  },
  totalPages() {
    return this.pagination.last_page;
  },
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
      
      // Always include last page if more than 1 page
      if (total > 1) {
        pages.push(total);
      }
    }
    
    return pages;
  },
  filteredVendors() {
    return this.vendors.filter(vendor => vendor && vendor.vendor_id);
  }
},
    created() {
      this.fetchReceipts();
      this.fetchVendors();
    },
    methods: {
      fetchReceipts() {
        this.loading = true;
        
        // Build query params
        const params = {
          page: this.pagination.current_page,
          per_page: this.pagination.per_page,
          sort_field: this.sortColumn,
          sort_direction: this.sortDirection
        };
        
        // Add filters if they have values
        if (this.filters.search) params.search = this.filters.search;
        if (this.filters.status) params.status = this.filters.status;
        if (this.filters.vendor_id) params.vendor_id = this.filters.vendor_id;
        if (this.filters.date_from) params.date_from = this.filters.date_from;
        if (this.filters.date_to) params.date_to = this.filters.date_to;
        
        axios.get('/goods-receipts', { params })
          .then(response => {
            const data = response.data.data;
            this.receipts = data.data;
            this.pagination = {
              total: data.total,
              per_page: data.per_page,
              current_page: data.current_page,
              last_page: data.last_page
            };
          })
          .catch(error => {
            console.error('Error fetching receipts:', error);
            this.$toast.error('Failed to load goods receipts');
          })
          .finally(() => {
            this.loading = false;
          });
      },
fetchVendors() {
  axios.get('/vendors')
    .then(response => {
      console.log('Vendors API response data:', response.data.data);
      // Access the actual vendors array inside the response
      const vendorsData = response.data.data;
      if (Array.isArray(vendorsData)) {
        this.vendors = vendorsData.filter(vendor => vendor && vendor.vendor_id);
      } else {
        this.vendors = [];
        console.error('Vendors API response data.data is not an array');
      }
    })
    .catch(error => {
      console.error('Error fetching vendors:', error);
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
      calculateTotalItems(receipt) {
        return receipt.lines ? receipt.lines.length : 0;
      },
      applyFilters() {
        this.pagination.current_page = 1;
        this.fetchReceipts();
      },
      sortBy(column) {
        if (this.sortColumn === column) {
          this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
          this.sortColumn = column;
          this.sortDirection = 'asc';
        }
        this.fetchReceipts();
      },
      changePage(page) {
        if (page === this.pagination.current_page) return;
        this.pagination.current_page = page;
        this.fetchReceipts();
      },
      confirmDelete(receipt) {
        this.receiptToDelete = receipt;
        this.showDeleteModal = true;
      },
      deleteReceipt() {
        if (!this.receiptToDelete) return;
        
        axios.delete(`/goods-receipts/${this.receiptToDelete.receipt_id}`)
          .then(() => {
            this.$toast.success('Goods receipt deleted successfully');
            this.fetchReceipts();
            this.showDeleteModal = false;
            this.receiptToDelete = null;
          })
          .catch(error => {
            console.error('Error deleting receipt:', error);
            this.$toast.error('Failed to delete goods receipt: ' + (error.response?.data?.message || 'Unknown error'));
          });
      }
    }
  };
  </script>
  
  <style scoped>
  .goods-receipt-list {
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
  }
  
  .btn-primary {
    background-color: var(--primary-color);
    color: white;
    border: 1px solid var(--primary-color);
  }
  
  .btn-primary:hover {
    background-color: var(--primary-dark);
  }
  
  .btn-info {
    background-color: #0ea5e9;
    color: white;
    border: 1px solid #0ea5e9;
  }
  
  .btn-info:hover {
    background-color: #0284c7;
  }
  
  .btn-secondary {
    background-color: var(--gray-200);
    color: var(--gray-700);
    border: 1px solid var(--gray-300);
  }
  
  .btn-secondary:hover {
    background-color: var(--gray-300);
  }
  
  .btn-danger {
    background-color: #ef4444;
    color: white;
    border: 1px solid #ef4444;
  }
  
  .btn-danger:hover {
    background-color: #dc2626;
  }
  
  .card-body {
    padding: 1.5rem;
  }
  
  .filters-container {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
    align-items: flex-end;
  }
  
  .search-box {
    position: relative;
    min-width: 250px;
    flex-grow: 1;
  }
  
  .search-box input {
    width: 100%;
    padding: 0.5rem 2rem 0.5rem 0.75rem;
    border: 1px solid var(--gray-300);
    border-radius: 0.375rem;
    font-size: 0.875rem;
  }
  
  .search-box i {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-500);
  }
  
  .filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }
  
  .filter-group label {
    font-size: 0.75rem;
    color: var(--gray-600);
    font-weight: 500;
  }
  
  .filter-group select,
  .filter-group input {
    padding: 0.5rem;
    border: 1px solid var(--gray-300);
    border-radius: 0.375rem;
    font-size: 0.875rem;
    min-width: 150px;
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
  
  .empty-state {
    text-align: center;
    padding: 3rem 0;
    color: var(--gray-500);
  }
  
  .empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
  }
  
  .empty-state h3 {
    margin-bottom: 0.5rem;
    font-size: 1.25rem;
  }
  
  .table-responsive {
    overflow-x: auto;
    margin-bottom: 1rem;
  }
  
  .receipts-table {
    width: 100%;
    border-collapse: collapse;
  }
  
  .receipts-table th {
    background-color: var(--gray-50);
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    border-bottom: 1px solid var(--gray-200);
    cursor: pointer;
    user-select: none;
    white-space: nowrap;
  }
  
  .receipts-table th i {
    margin-left: 0.25rem;
  }
  
  .receipts-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-800);
  }
  
  .receipts-table tr:hover td {
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
  
  .status-badge.pending {
    background-color: #fef3c7;
    color: #92400e;
  }
  
  .status-badge.confirmed {
    background-color: #d1fae5;
    color: #065f46;
  }
  
  .actions-cell {
    display: flex;
    gap: 0.5rem;
    white-space: nowrap;
  }
  
  .btn-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
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
  
  .btn-icon.confirm {
    color: #059669;
    background-color: #d1fae5;
    border-color: #059669;
  }
  
  .btn-icon.confirm:hover {
    background-color: #a7f3d0;
  }
  
  .btn-icon.delete {
    color: #dc2626;
    background-color: #fee2e2;
    border-color: #dc2626;
  }
  
  .btn-icon.delete:hover {
    background-color: #fecaca;
  }
  
  .pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-top: 1.5rem;
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
  
  /* Modal styles */
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
    max-width: 600px;
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
  }
  
  .close-btn {
    background: none;
    border: none;
    color: var(--gray-500);
    cursor: pointer;
    font-size: 1rem;
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
  
  @media (max-width: 768px) {
    .filters-container {
      flex-direction: column;
      align-items: stretch;
    }
    
    .search-box {
      width: 100%;
    }
    
    .card-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }
    
    .actions {
      width: 100%;
    }
    
    .btn {
      flex: 1;
      justify-content: center;
    }
  }
  </style>