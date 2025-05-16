<!-- src/views/purchasing/VendorInvoiceList.vue -->
<template>
    <div class="vendor-invoice-list">
      <div class="page-header">
        <h1>Vendor Invoices</h1>
        <div class="actions">
          <router-link to="/purchasing/vendor-invoices/create" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create Invoice
          </router-link>
        </div>
      </div>
  
      <div class="filters-container">
        <div class="filter-row">
          <div class="filter-group">
            <label>Status</label>
            <select v-model="filters.status" @change="loadInvoices(1)">
              <option value="">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="paid">Paid</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div class="filter-group">
            <label>Vendor</label>
            <select v-model="filters.vendor_id" @change="loadInvoices(1)">
              <option value="">All Vendors</option>
              <option v-for="vendor in vendors" :key="vendor.vendor_id" :value="vendor.vendor_id">
                {{ vendor.name }}
              </option>
            </select>
          </div>
          <div class="filter-group">
            <label>Date From</label>
            <input type="date" v-model="filters.date_from" @change="loadInvoices(1)" />
          </div>
          <div class="filter-group">
            <label>Date To</label>
            <input type="date" v-model="filters.date_to" @change="loadInvoices(1)" />
          </div>
          <div class="filter-group">
            <label>Currency</label>
            <select v-model="filters.currency_code" @change="loadInvoices(1)">
              <option value="">All Currencies</option>
              <option value="USD">USD</option>
              <option value="EUR">EUR</option>
              <option value="IDR">IDR</option>
              <option value="JPY">JPY</option>
            </select>
          </div>
          <div class="filter-group search-box">
            <label>Search</label>
            <div class="search-input">
              <input type="text" v-model="filters.search" placeholder="Invoice number..." />
              <button @click="loadInvoices(1)" class="btn btn-secondary">
                <i class="fas fa-search"></i>
              </button>
            </div>
          </div>
        </div>
        <div class="filter-row">
          <button @click="resetFilters" class="btn btn-outline">
            <i class="fas fa-sync"></i> Reset Filters
          </button>
        </div>
      </div>
  
      <div v-if="loading" class="loading">
        <i class="fas fa-spinner fa-spin"></i> Loading invoices...
      </div>
      
      <div v-else-if="invoices.length === 0" class="empty-state">
        <i class="fas fa-file-invoice"></i>
        <h3>No invoices found</h3>
        <p>Try changing your filters or create a new invoice.</p>
      </div>
      
      <div v-else class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th @click="sortBy('invoice_number')">
                Invoice # 
                <i v-if="sortField === 'invoice_number'" 
                   :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th @click="sortBy('invoice_date')">
                Date
                <i v-if="sortField === 'invoice_date'" 
                   :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
              </th>
              <th>Vendor</th>
              <th>Currency</th>
              <th>Total Amount</th>
              <th @click="sortBy('due_date')">
                Due Date
                <i v-if="sortField === 'due_date'" 
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
            <tr v-for="invoice in invoices" :key="invoice.invoice_id">
              <td>{{ invoice.invoice_number }}</td>
              <td>{{ formatDate(invoice.invoice_date) }}</td>
              <td>{{ invoice.vendor ? invoice.vendor.name : 'N/A' }}</td>
              <td>{{ invoice.currency_code }}</td>
              <td>{{ formatCurrency(invoice.total_amount, invoice.currency_code) }}</td>
              <td>{{ formatDate(invoice.due_date) }}</td>
              <td>
                <span :class="getStatusClass(invoice.status)">{{ capitalizeFirst(invoice.status) }}</span>
              </td>
              <td class="actions-cell">
                <router-link :to="`/purchasing/vendor-invoices/${invoice.invoice_id}`" class="btn-icon" title="View">
                  <i class="fas fa-eye"></i>
                </router-link>
                <router-link v-if="invoice.status === 'pending'" 
                  :to="`/purchasing/vendor-invoices/${invoice.invoice_id}/edit`" 
                  class="btn-icon" title="Edit">
                  <i class="fas fa-edit"></i>
                </router-link>
                <router-link v-if="invoice.status === 'pending'" 
                  :to="`/purchasing/vendor-invoices/${invoice.invoice_id}/approve`" 
                  class="btn-icon" title="Approve">
                  <i class="fas fa-check-circle"></i>
                </router-link>
                <router-link v-if="invoice.status === 'approved'" 
                  :to="`/purchasing/vendor-invoices/${invoice.invoice_id}/payment`" 
                  class="btn-icon" title="Payment">
                  <i class="fas fa-money-bill-wave"></i>
                </router-link>
                <button v-if="invoice.status === 'pending' || invoice.status === 'cancelled'" 
                  @click="confirmDelete(invoice)" 
                  class="btn-icon" title="Delete">
                  <i class="fas fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
  
      <div class="pagination-container" v-if="totalPages > 1">
        <div class="pagination-info">
          Showing {{ (currentPage - 1) * perPage + 1 }} to {{ Math.min(currentPage * perPage, totalItems) }} of {{ totalItems }} invoices
        </div>
        <div class="pagination-controls">
          <button class="pagination-btn" 
            :disabled="currentPage === 1" 
            @click="loadInvoices(currentPage - 1)">
            <i class="fas fa-chevron-left"></i>
          </button>
          
          <template v-for="page in displayedPages" :key="page">
            <button v-if="page !== '...'" 
              class="pagination-btn" 
              :class="{ active: page === currentPage }"
              @click="loadInvoices(page)">
              {{ page }}
            </button>
            <span v-else class="pagination-ellipsis">...</span>
          </template>
          
          <button class="pagination-btn" 
            :disabled="currentPage === totalPages" 
            @click="loadInvoices(currentPage + 1)">
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
            <p>Are you sure you want to delete invoice <strong>{{ invoiceToDelete?.invoice_number }}</strong>?</p>
            <p v-if="invoiceToDelete?.status === 'cancelled'" class="text-warning">
              <i class="fas fa-exclamation-triangle"></i> 
              This invoice is already cancelled.
            </p>
            <div class="form-actions">
              <button type="button" class="btn btn-secondary" @click="showDeleteModal = false">
                Cancel
              </button>
              <button type="button" class="btn btn-danger" @click="deleteInvoice">
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
    name: 'VendorInvoiceList',
    data() {
      return {
        invoices: [],
        vendors: [],
        loading: true,
        showDeleteModal: false,
        invoiceToDelete: null,
        filters: {
          status: '',
          vendor_id: '',
          date_from: '',
          date_to: '',
          currency_code: '',
          search: '',
        },
        sortField: 'invoice_date',
        sortDirection: 'desc',
        currentPage: 1,
        totalPages: 1,
        totalItems: 0,
        perPage: 10
      };
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
    created() {
      this.loadVendors();
      this.loadInvoices(1);
    },
    methods: {
      async loadVendors() {
        try {
          const response = await axios.get('/vendors');
          this.vendors = response.data.data;
        } catch (error) {
          console.error('Error loading vendors:', error);
        }
      },
      async loadInvoices(page) {
        this.loading = true;
        this.currentPage = page;
        
        try {
          const params = {
            page: page,
            per_page: this.perPage,
            sort_field: this.sortField,
            sort_direction: this.sortDirection,
            ...this.filters
          };
          
          const response = await axios.get('/vendor-invoices', { params });
          this.invoices = response.data.data.data;
          this.totalPages = response.data.data.last_page;
          this.totalItems = response.data.data.total;
        } catch (error) {
          console.error('Error loading invoices:', error);
        } finally {
          this.loading = false;
        }
      },
      sortBy(field) {
        if (this.sortField === field) {
          this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
          this.sortField = field;
          this.sortDirection = 'asc';
        }
        
        this.loadInvoices(1);
      },
      resetFilters() {
        this.filters = {
          status: '',
          vendor_id: '',
          date_from: '',
          date_to: '',
          currency_code: '',
          search: '',
        };
        this.loadInvoices(1);
      },
      confirmDelete(invoice) {
        this.invoiceToDelete = invoice;
        this.showDeleteModal = true;
      },
      async deleteInvoice() {
        if (!this.invoiceToDelete) return;
        
        try {
          await axios.delete(`/vendor-invoices/${this.invoiceToDelete.invoice_id}`);
          this.showDeleteModal = false;
          this.invoiceToDelete = null;
          this.loadInvoices(this.currentPage);
        } catch (error) {
          console.error('Error deleting invoice:', error);
          alert(error.response?.data?.message || 'Error deleting invoice');
        }
      },
      formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString();
      },
      formatCurrency(amount, currency) {
        if (amount === null || amount === undefined) return 'N/A';
        
        return new Intl.NumberFormat('en-US', {
          style: 'currency',
          currency: currency || 'USD',
          minimumFractionDigits: 2
        }).format(amount);
      },
      getStatusClass(status) {
        const statusClasses = {
          pending: 'status-pending',
          approved: 'status-approved',
          paid: 'status-paid',
          cancelled: 'status-cancelled'
        };
        
        return `status-badge ${statusClasses[status] || ''}`;
      },
      capitalizeFirst(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
      }
    }
  };
  </script>
  
  <style scoped>
  .vendor-invoice-list {
    padding: 1rem;
  }
  
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
  }
  
  .filters-container {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    padding: 1rem;
    margin-bottom: 1.5rem;
  }
  
  .filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1rem;
  }
  
  .filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 150px;
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
  }
  
  .search-box {
    flex-grow: 1;
  }
  
  .search-input {
    display: flex;
    gap: 0.5rem;
  }
  
  .search-input input {
    flex-grow: 1;
  }
  
  .table-responsive {
    overflow-x: auto;
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 1.5rem;
  }
  
  .data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
  }
  
  .data-table th {
    text-align: left;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-200);
    background-color: var(--gray-50);
    font-weight: 500;
    color: var(--gray-600);
    cursor: pointer;
  }
  
  .data-table th:hover {
    background-color: var(--gray-100);
  }
  
  .data-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-100);
    color: var(--gray-800);
  }
  
  .data-table tbody tr:hover {
    background-color: var(--gray-50);
  }
  
  .actions-cell {
    display: flex;
    gap: 0.5rem;
  }
  
  .btn-icon {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    width: 2rem;
    height: 2rem;
    border-radius: 0.375rem;
    color: var(--gray-500);
    background: none;
    border: 1px solid var(--gray-200);
    cursor: pointer;
    transition: all 0.2s;
  }
  
  .btn-icon:hover {
    background-color: var(--gray-100);
    color: var(--gray-800);
  }
  
  .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
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
    background-color: var(--gray-200);
    color: var(--gray-700);
    border: none;
  }
  
  .btn-secondary:hover {
    background-color: var(--gray-300);
  }
  
  .btn-outline {
    background-color: white;
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
  }
  
  .btn-outline:hover {
    background-color: var(--gray-100);
  }
  
  .btn-danger {
    background-color: var(--danger-color);
    color: white;
    border: none;
  }
  
  .btn-danger:hover {
    background-color: #b91c1c;
  }
  
  .pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
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
  
  .loading,
  .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 0;
    text-align: center;
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 1.5rem;
  }
  
  .loading i,
  .empty-state i {
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
  
  .close-btn:hover {
    background-color: var(--gray-100);
    color: var(--gray-800);
  }
  
  .modal-body {
    padding: 1.5rem;
  }
  
  .text-warning {
    color: var(--warning-color);
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  
  .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
  }
  
  .status-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
  }
  
  .status-pending {
    background-color: #fef3c7;
    color: #92400e;
  }
  
  .status-approved {
    background-color: #d1fae5;
    color: #065f46;
  }
  
  .status-paid {
    background-color: #dbeafe;
    color: #1e40af;
  }
  
  .status-cancelled {
    background-color: #fee2e2;
    color: #b91c1c;
  }
  
  @media (max-width: 768px) {
    .page-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }
    
    .filter-group {
      width: 100%;
    }
    
    .pagination-container {
      flex-direction: column;
      gap: 1rem;
    }
  }
  </style>