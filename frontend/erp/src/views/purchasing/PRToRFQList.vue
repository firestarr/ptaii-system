<!-- src/views/purchasing/PRToRFQList.vue -->
<template>
    <div class="pr-to-rfq-list-page">
      <div class="page-header">
        <h2 class="title">Convert Purchase Requisition to RFQ</h2>
      </div>
  
      <!-- Search Filters -->
      <div class="search-filter">
        <div class="search-box">
          <i class="fas fa-search search-icon"></i>
          <input 
            type="text" 
            v-model="filters.search" 
            placeholder="Cari nomor PR, pemohon..." 
            @input="handleSearch"
          />
          <button v-if="filters.search" @click="clearSearch" class="clear-search">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <div class="filters">
          <div class="filter-group">
            <label for="date-from">Tanggal Mulai</label>
            <input 
              type="date" 
              id="date-from" 
              v-model="filters.dateFrom" 
              @change="fetchPRs"
            >
          </div>
          
          <div class="filter-group">
            <label for="date-to">Tanggal Sampai</label>
            <input 
              type="date" 
              id="date-to" 
              v-model="filters.dateTo" 
              @change="fetchPRs"
            >
          </div>
          
          <div class="filter-group">
            <label for="requester-filter">Pemohon</label>
            <select id="requester-filter" v-model="filters.requesterId" @change="fetchPRs">
              <option value="">Semua Pemohon</option>
              <option v-for="requester in requesters" :key="requester.user_id" :value="requester.user_id">
                {{ requester.name }}
              </option>
            </select>
          </div>
        </div>
      </div>
  
      <!-- Loading State -->
      <div v-if="loading" class="loading-indicator">
        <i class="fas fa-spinner fa-spin"></i> Memuat data...
      </div>
  
      <!-- Error State -->
      <div v-else-if="error" class="error-message">
        <i class="fas fa-exclamation-circle"></i> {{ error }}
        <button class="retry-btn" @click="fetchPRs">Coba Lagi</button>
      </div>
  
      <!-- Empty State -->
      <div v-else-if="purchaseRequisitions.length === 0" class="empty-state">
        <div class="empty-icon">
          <i class="fas fa-exchange-alt"></i>
        </div>
        <h3>Tidak Ada PR yang Tersedia</h3>
        <p>Tidak ada Purchase Requisition yang telah disetujui dan siap untuk dikonversi ke RFQ.</p>
      </div>
  
      <!-- PR Table -->
      <div v-else class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>Nomor PR</th>
              <th>Tanggal PR</th>
              <th>Pemohon</th>
              <th>Status</th>
              <th>Jumlah Item</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="pr in purchaseRequisitions" :key="pr.pr_id">
              <td>{{ pr.pr_number }}</td>
              <td>{{ formatDate(pr.pr_date) }}</td>
              <td>{{ pr.requester?.name || '-' }}</td>
              <td>
                <div class="status-badge" :class="getStatusClass(pr.status)">
                  {{ pr.status }}
                </div>
              </td>
              <td>{{ pr.lines?.length || 0 }}</td>
              <td class="actions-cell">
                <router-link 
                  :to="`/purchasing/requisitions/${pr.pr_id}`" 
                  class="btn btn-icon btn-view" 
                  title="View Details"
                >
                  <i class="fas fa-eye"></i>
                </router-link>
                
                <router-link 
                  :to="`/purchasing/requisitions/${pr.pr_id}/convert`"
                  class="btn btn-icon btn-convert" 
                  title="Convert to RFQ"
                >
                  <i class="fas fa-exchange-alt"></i>
                </router-link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
  
      <!-- Pagination -->
      <div class="pagination-container" v-if="purchaseRequisitions.length > 0">
        <div class="pagination-info">
          Menampilkan {{ paginationInfo.from }} sampai {{ paginationInfo.to }} dari {{ paginationInfo.total }} item
        </div>
        <div class="pagination-controls">
          <button 
            class="pagination-btn" 
            :disabled="paginationInfo.currentPage === 1" 
            @click="changePage(paginationInfo.currentPage - 1)"
          >
            <i class="fas fa-chevron-left"></i>
          </button>
          
          <template v-for="page in displayedPages" :key="page">
            <button 
              v-if="page !== '...'" 
              class="pagination-btn" 
              :class="{ active: page === paginationInfo.currentPage }"
              @click="changePage(page)"
            >
              {{ page }}
            </button>
            <span v-else class="pagination-ellipsis">...</span>
          </template>
          
          <button 
            class="pagination-btn" 
            :disabled="paginationInfo.currentPage === paginationInfo.totalPages" 
            @click="changePage(paginationInfo.currentPage + 1)"
          >
            <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  import { debounce } from 'lodash';
  
  export default {
    name: 'PRToRFQList',
    data() {
      return {
        purchaseRequisitions: [],
        loading: true,
        error: null,
        filters: {
          search: '',
          status: 'approved', // Only show approved PRs for conversion
          dateFrom: null,
          dateTo: null,
          requesterId: ''
        },
        paginationInfo: {
          currentPage: 1,
          totalPages: 1,
          from: 0,
          to: 0,
          total: 0
        },
        requesters: [], // List of requesters for filter
        debouncedSearch: null
      };
    },
    computed: {
      displayedPages() {
        const total = this.paginationInfo.totalPages;
        const current = this.paginationInfo.currentPage;
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
      this.debouncedSearch = debounce(this.fetchPRs, 300);
      this.fetchRequesters();
      this.fetchPRs();
      
      // Check for query parameters that might indicate a redirect after action
      if (this.$route.query.message) {
        this.showMessage(this.$route.query.message, this.$route.query.type || 'info');
        
        // Clear the query parameters
        this.$router.replace({
          query: {}
        });
      }
    },
    methods: {
      async fetchPRs() {
        this.loading = true;
        this.error = null;
        
        try {
          const params = {
            page: this.paginationInfo.currentPage,
            per_page: 10,
            status: this.filters.status // Only approved PRs
          };
          
          // Add filters if they exist
          if (this.filters.search) params.search = this.filters.search;
          if (this.filters.dateFrom) params.date_from = this.filters.dateFrom;
          if (this.filters.dateTo) params.date_to = this.filters.dateTo;
          if (this.filters.requesterId) params.requester_id = this.filters.requesterId;
          
          const response = await axios.get('/purchase-requisitions', { params });
          
          // Extract data and pagination info
          this.purchaseRequisitions = response.data.data.data || [];
          this.paginationInfo = {
            currentPage: response.data.data.current_page,
            totalPages: response.data.data.last_page,
            from: response.data.data.from || 0,
            to: response.data.data.to || 0,
            total: response.data.data.total || 0
          };
        } catch (error) {
          console.error('Error fetching purchase requisitions:', error);
          this.error = 'Gagal memuat daftar PR. Silakan coba lagi.';
        } finally {
          this.loading = false;
        }
      },
      
      async fetchRequesters() {
        try {
          // Fetch users who can be requesters
          const response = await axios.get('/users?role=requester');
          this.requesters = response.data.data || [];
        } catch (error) {
          console.error('Error fetching requesters:', error);
          // Not critical, so we don't show error message
          this.requesters = [];
        }
      },
      
      handleSearch() {
        this.paginationInfo.currentPage = 1; // Reset to first page
        this.debouncedSearch();
      },
      
      clearSearch() {
        this.filters.search = '';
        this.handleSearch();
      },
      
      changePage(page) {
        if (page < 1 || page > this.paginationInfo.totalPages) return;
        
        this.paginationInfo.currentPage = page;
        this.fetchPRs();
        
        // Scroll to top of the table
        this.$nextTick(() => {
          const tableTop = document.querySelector('.table-container')?.offsetTop;
          if (tableTop) {
            window.scrollTo({ top: tableTop - 20, behavior: 'smooth' });
          }
        });
      },
      
      formatDate(dateString) {
        if (!dateString) return '-';
        
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateString).toLocaleDateString('id-ID', options);
      },
      
      getStatusClass(status) {
        switch (status) {
          case 'draft': return 'status-draft';
          case 'pending': return 'status-pending';
          case 'approved': return 'status-approved';
          case 'rejected': return 'status-rejected';
          case 'canceled': return 'status-canceled';
          default: return '';
        }
      },
      
      showMessage(message) {
        // If you have a toast/notification system, integrate it here
        alert(message); // Simple fallback if no notification system
      }
    }
  };
  </script>
  
  <style scoped>
  .pr-to-rfq-list-page {
    padding: 20px;
    max-width: 100%;
  }
  
  .page-header {
    margin-bottom: 24px;
  }
  
  .title {
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
  }
  
  .search-filter {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: flex-end;
    margin-bottom: 24px;
    background-color: white;
    padding: 16px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  }
  
  .search-box {
    position: relative;
    flex: 1;
    min-width: 250px;
  }
  
  .search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-500);
  }
  
  .search-box input {
    width: 100%;
    padding: 10px 12px 10px 36px;
    border: 1px solid var(--gray-300);
    border-radius: 4px;
    font-size: 0.875rem;
  }
  
  .clear-search {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--gray-500);
    cursor: pointer;
  }
  
  .filters {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
  }
  
  .filter-group {
    min-width: 150px;
  }
  
  .filter-group label {
    display: block;
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-bottom: 4px;
  }
  
  .filter-group select,
  .filter-group input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--gray-300);
    border-radius: 4px;
    font-size: 0.875rem;
  }
  
  .loading-indicator,
  .error-message {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px 0;
    font-size: 1rem;
  }
  
  .loading-indicator i,
  .error-message i {
    margin-right: 8px;
  }
  
  .error-message {
    color: #c62828;
    flex-direction: column;
  }
  
  .retry-btn {
    margin-top: 16px;
    padding: 8px 16px;
    background-color: var(--primary-color);
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
  }
  
  .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 0;
    text-align: center;
  }
  
  .empty-icon {
    font-size: 3rem;
    color: var(--gray-300);
    margin-bottom: 16px;
  }
  
  .empty-state h3 {
    margin-bottom: 8px;
    font-size: 1.25rem;
  }
  
  .empty-state p {
    color: var(--gray-600);
    max-width: 400px;
  }
  
  .table-container {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    overflow-x: auto;
  }
  
  .data-table {
    width: 100%;
    border-collapse: collapse;
  }
  
  .data-table th,
  .data-table td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid var(--gray-200);
  }
  
  .data-table th {
    background-color: var(--gray-50);
    font-weight: 600;
    color: var(--gray-700);
  }
  
  .data-table tr:last-child td {
    border-bottom: none;
  }
  
  .data-table tbody tr:hover {
    background-color: var(--gray-50);
  }
  
  .status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 500;
    font-size: 0.75rem;
    text-transform: uppercase;
  }
  
  .status-draft {
    background-color: var(--gray-200);
    color: var(--gray-700);
  }
  
  .status-pending {
    background-color: #ffecb3;
    color: #8b6d00;
  }
  
  .status-approved {
    background-color: #d0f0c0;
    color: #38761d;
  }
  
  .status-rejected {
    background-color: #ffcdd2;
    color: #c62828;
  }
  
  .status-canceled {
    background-color: var(--gray-300);
    color: var(--gray-600);
  }
  
  .actions-cell {
    white-space: nowrap;
  }
  
  .btn {
    padding: 8px 16px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: background-color 0.2s;
  }
  
  .btn-icon {
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 4px;
    padding: 0;
    border-radius: 4px;
  }
  
  .btn-view {
    background-color: var(--gray-100);
    color: var(--gray-700);
  }
  
  .btn-view:hover {
    background-color: var(--gray-200);
  }
  
  .btn-convert {
    background-color: #e3f2fd;
    color: #1565c0;
  }
  
  .btn-convert:hover {
    background-color: #bbdefb;
  }
  
  .pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding: 16px;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  }
  
  .pagination-info {
    color: var(--gray-600);
    font-size: 0.875rem;
  }
  
  .pagination-controls {
    display: flex;
    gap: 4px;
  }
  
  .pagination-btn {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: none;
    border: 1px solid var(--gray-200);
    border-radius: 4px;
    color: var(--gray-700);
    cursor: pointer;
  }
  
  .pagination-btn:hover:not(:disabled):not(.active) {
    background-color: var(--gray-100);
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
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-500);
  }
  
  @media (max-width: 768px) {
    .search-filter {
      flex-direction: column;
      align-items: stretch;
    }
    
    .search-box {
      width: 100%;
    }
    
    .filters {
      width: 100%;
    }
    
    .pagination-container {
      flex-direction: column;
      gap: 16px;
    }
    
    .pagination-info {
      text-align: center;
    }
    
    .pagination-controls {
      justify-content: center;
    }
  }
  </style>