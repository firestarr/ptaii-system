<template>
  <div class="bank-reconciliation-list">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <h1 class="page-title">
            <i class="fas fa-balance-scale"></i>
            Bank Reconciliations
          </h1>
          <p class="page-subtitle">Manage your bank account reconciliations</p>
        </div>
        <div class="header-actions">
          <button @click="refreshData" class="btn-secondary" :disabled="loading">
            <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
            Refresh
          </button>
          <button @click="goToCreate" class="btn-primary">
            <i class="fas fa-plus"></i>
            New Reconciliation
          </button>
        </div>
      </div>
    </div>

    <!-- Filters and Search -->
    <div class="filters-section">
      <div class="search-filter">
        <div class="search-box">
          <i class="fas fa-search"></i>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search reconciliations..."
            class="search-input"
            @input="handleSearch"
          />
        </div>
        <div class="filter-group">
          <select v-model="selectedBank" @change="applyFilters" class="filter-select">
            <option value="">All Banks</option>
            <option v-for="bank in bankAccounts" :key="bank.bank_id" :value="bank.bank_id">
              {{ bank.account_name }}
            </option>
          </select>
          <select v-model="selectedStatus" @change="applyFilters" class="filter-select">
            <option value="">All Status</option>
            <option value="Draft">Draft</option>
            <option value="In Progress">In Progress</option>
            <option value="Finalized">Finalized</option>
          </select>
          <input
            v-model="dateFrom"
            type="date"
            class="filter-input"
            @change="applyFilters"
            placeholder="From Date"
          />
          <input
            v-model="dateTo"
            type="date"
            class="filter-input"
            @change="applyFilters"
            placeholder="To Date"
          />
        </div>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards">
      <div class="summary-card">
        <div class="card-icon pending">
          <i class="fas fa-clock"></i>
        </div>
        <div class="card-content">
          <h3>{{ summaryStats.pending }}</h3>
          <p>Pending Reconciliations</p>
        </div>
      </div>
      <div class="summary-card">
        <div class="card-icon completed">
          <i class="fas fa-check-circle"></i>
        </div>
        <div class="card-content">
          <h3>{{ summaryStats.completed }}</h3>
          <p>Completed This Month</p>
        </div>
      </div>
      <div class="summary-card">
        <div class="card-icon variance">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="card-content">
          <h3>{{ formatCurrency(summaryStats.totalVariance) }}</h3>
          <p>Total Outstanding Variance</p>
        </div>
      </div>
    </div>

    <!-- Data Table -->
    <div class="data-table-container">
      <div class="table-header">
        <h3>Reconciliation Records</h3>
        <div class="table-actions">
          <button @click="exportData" class="btn-outline">
            <i class="fas fa-download"></i>
            Export
          </button>
        </div>
      </div>

      <div class="table-wrapper" v-if="!loading">
        <table class="data-table">
          <thead>
            <tr>
              <th @click="sortBy('reconciliation_id')" class="sortable">
                ID
                <i class="fas fa-sort" :class="getSortIcon('reconciliation_id')"></i>
              </th>
              <th @click="sortBy('bank_account')" class="sortable">
                Bank Account
                <i class="fas fa-sort" :class="getSortIcon('bank_account')"></i>
              </th>
              <th @click="sortBy('statement_date')" class="sortable">
                Statement Date
                <i class="fas fa-sort" :class="getSortIcon('statement_date')"></i>
              </th>
              <th>Statement Balance</th>
              <th>Book Balance</th>
              <th>Variance</th>
              <th @click="sortBy('status')" class="sortable">
                Status
                <i class="fas fa-sort" :class="getSortIcon('status')"></i>
              </th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="reconciliation in reconciliations.data" :key="reconciliation.reconciliation_id">
              <td class="id-cell">#{{ reconciliation.reconciliation_id }}</td>
              <td class="bank-cell">
                <div class="bank-info">
                  <i class="fas fa-university"></i>
                  <span>{{ reconciliation.bank_account?.account_name || 'N/A' }}</span>
                </div>
              </td>
              <td>{{ formatDate(reconciliation.statement_date) }}</td>
              <td class="amount-cell">{{ formatCurrency(reconciliation.statement_balance) }}</td>
              <td class="amount-cell">{{ formatCurrency(reconciliation.book_balance) }}</td>
              <td class="variance-cell" :class="getVarianceClass(reconciliation.statement_balance - reconciliation.book_balance)">
                {{ formatCurrency(reconciliation.statement_balance - reconciliation.book_balance) }}
              </td>
              <td>
                <span class="status-badge" :class="getStatusClass(reconciliation.status)">
                  {{ reconciliation.status }}
                </span>
              </td>
              <td class="actions-cell">
                <div class="action-buttons">
                  <button @click="viewDetail(reconciliation.reconciliation_id)" class="btn-icon" title="View Details">
                    <i class="fas fa-eye"></i>
                  </button>
                  <button 
                    v-if="reconciliation.status !== 'Finalized'"
                    @click="editReconciliation(reconciliation.reconciliation_id)" 
                    class="btn-icon edit" 
                    title="Edit"
                  >
                    <i class="fas fa-edit"></i>
                  </button>
                  <button 
                    v-if="reconciliation.status === 'Draft'"
                    @click="deleteReconciliation(reconciliation.reconciliation_id)" 
                    class="btn-icon delete" 
                    title="Delete"
                  >
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-state">
        <div class="loading-spinner">
          <i class="fas fa-spinner fa-spin"></i>
        </div>
        <p>Loading reconciliations...</p>
      </div>

      <!-- Empty State -->
      <div v-if="!loading && reconciliations.data && reconciliations.data.length === 0" class="empty-state">
        <div class="empty-icon">
          <i class="fas fa-balance-scale"></i>
        </div>
        <h3>No reconciliations found</h3>
        <p>Create your first bank reconciliation to get started</p>
        <button @click="goToCreate" class="btn-primary">
          <i class="fas fa-plus"></i>
          Create Reconciliation
        </button>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="reconciliations.data && reconciliations.data.length > 0" class="pagination-section">
      <div class="pagination-info">
        Showing {{ reconciliations.from }} to {{ reconciliations.to }} of {{ reconciliations.total }} records
      </div>
      <div class="pagination-controls">
        <button 
          @click="changePage(reconciliations.current_page - 1)"
          :disabled="reconciliations.current_page <= 1"
          class="btn-pagination"
        >
          <i class="fas fa-chevron-left"></i>
          Previous
        </button>
        
        <div class="page-numbers">
          <button 
            v-for="page in getPageNumbers()" 
            :key="page"
            @click="changePage(page)"
            :class="['btn-page', { 'active': page === reconciliations.current_page }]"
          >
            {{ page }}
          </button>
        </div>
        
        <button 
          @click="changePage(reconciliations.current_page + 1)"
          :disabled="reconciliations.current_page >= reconciliations.last_page"
          class="btn-pagination"
        >
          Next
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="modal-overlay" @click="closeDeleteModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>Confirm Delete</h3>
          <button @click="closeDeleteModal" class="btn-close">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete this reconciliation? This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button @click="closeDeleteModal" class="btn-secondary">Cancel</button>
          <button @click="confirmDelete" class="btn-danger">
            <i class="fas fa-trash"></i>
            Delete
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'BankReconciliationList',
  data() {
    return {
      loading: false,
      searchQuery: '',
      selectedBank: '',
      selectedStatus: '',
      dateFrom: '',
      dateTo: '',
      sortField: 'statement_date',
      sortDirection: 'desc',
      currentPage: 1,
      perPage: 15,
      reconciliations: {
        data: [],
        current_page: 1,
        last_page: 1,
        total: 0,
        from: 0,
        to: 0
      },
      bankAccounts: [],
      summaryStats: {
        pending: 0,
        completed: 0,
        totalVariance: 0
      },
      showDeleteModal: false,
      deleteId: null,
      searchTimeout: null
    }
  },
  mounted() {
    this.loadData()
    this.loadBankAccounts()
    this.loadSummaryStats()
  },
  methods: {
    async loadData() {
      this.loading = true
      try {
        const params = {
          page: this.currentPage,
          per_page: this.perPage,
          sort_field: this.sortField,
          sort_direction: this.sortDirection
        }

        if (this.selectedBank) params.bank_id = this.selectedBank
        if (this.selectedStatus) params.status = this.selectedStatus
        if (this.searchQuery) params.search = this.searchQuery
        if (this.dateFrom) params.date_from = this.dateFrom
        if (this.dateTo) params.date_to = this.dateTo

        const response = await axios.get('/accounting/bank-reconciliations', { params })
        this.reconciliations = response.data
      } catch (error) {
        console.error('Error loading reconciliations:', error)
        this.$toast?.error('Failed to load reconciliations')
      } finally {
        this.loading = false
      }
    },

    async loadBankAccounts() {
      try {
        const response = await axios.get('/accounting/bank-accounts')
        this.bankAccounts = response.data.data
      } catch (error) {
        console.error('Error loading bank accounts:', error)
      }
    },

    async loadSummaryStats() {
      try {
        // This would be a separate endpoint for summary statistics
        // For now, we'll calculate from the current data
        this.summaryStats = {
          pending: this.reconciliations.data?.filter(r => r.status !== 'Finalized').length || 0,
          completed: this.reconciliations.data?.filter(r => r.status === 'Finalized').length || 0,
          totalVariance: this.reconciliations.data?.reduce((sum, r) => sum + Math.abs(r.statement_balance - r.book_balance), 0) || 0
        }
      } catch (error) {
        console.error('Error loading summary stats:', error)
      }
    },

    handleSearch() {
      clearTimeout(this.searchTimeout)
      this.searchTimeout = setTimeout(() => {
        this.currentPage = 1
        this.loadData()
      }, 500)
    },

    applyFilters() {
      this.currentPage = 1
      this.loadData()
    },

    sortBy(field) {
      if (this.sortField === field) {
        this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc'
      } else {
        this.sortField = field
        this.sortDirection = 'asc'
      }
      this.loadData()
    },

    getSortIcon(field) {
      if (this.sortField !== field) return ''
      return this.sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down'
    },

    changePage(page) {
      if (page >= 1 && page <= this.reconciliations.last_page) {
        this.currentPage = page
        this.loadData()
      }
    },

    getPageNumbers() {
      const pages = []
      const start = Math.max(1, this.reconciliations.current_page - 2)
      const end = Math.min(this.reconciliations.last_page, this.reconciliations.current_page + 2)
      
      for (let i = start; i <= end; i++) {
        pages.push(i)
      }
      return pages
    },

    refreshData() {
      this.loadData()
      this.loadSummaryStats()
    },

    goToCreate() {
      this.$router.push('/accounting/bank-reconciliations/create')
    },

    viewDetail(id) {
      this.$router.push(`/accounting/bank-reconciliations/${id}`)
    },

    editReconciliation(id) {
      this.$router.push(`/accounting/bank-reconciliations/${id}/edit`)
    },

    deleteReconciliation(id) {
      this.deleteId = id
      this.showDeleteModal = true
    },

    closeDeleteModal() {
      this.showDeleteModal = false
      this.deleteId = null
    },

    async confirmDelete() {
      try {
        await axios.delete(`/accounting/bank-reconciliations/${this.deleteId}`)
        this.$toast?.success('Reconciliation deleted successfully')
        this.closeDeleteModal()
        this.loadData()
      } catch (error) {
        console.error('Error deleting reconciliation:', error)
        this.$toast?.error('Failed to delete reconciliation')
      }
    },

    async exportData() {
      try {
        // Implementation for data export
        this.$toast?.info('Export functionality will be implemented')
      } catch (error) {
        console.error('Error exporting data:', error)
      }
    },

    formatDate(date) {
      if (!date) return 'N/A'
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      })
    },

    formatCurrency(amount) {
      if (amount === null || amount === undefined) return 'N/A'
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount)
    },

    getStatusClass(status) {
      const statusMap = {
        'Draft': 'status-draft',
        'In Progress': 'status-progress',
        'Finalized': 'status-finalized'
      }
      return statusMap[status] || 'status-draft'
    },

    getVarianceClass(variance) {
      if (variance === 0) return 'variance-zero'
      return variance > 0 ? 'variance-positive' : 'variance-negative'
    }
  }
}
</script>

<style scoped>
/* CSS Variables */
:root {
  --primary-color: #2563eb;
  --primary-dark: #1d4ed8;
  --success-color: #059669;
  --warning-color: #d97706;
  --danger-color: #dc2626;
  --gray-50: #f8fafc;
  --gray-100: #f1f5f9;
  --gray-200: #e2e8f0;
  --gray-300: #cbd5e1;
  --gray-400: #94a3b8;
  --gray-500: #64748b;
  --gray-600: #475569;
  --gray-700: #334155;
  --gray-800: #1e293b;
  --gray-900: #0f172a;
  --white: #ffffff;
  --border-radius: 12px;
  --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  --box-shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.1);
  --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.bank-reconciliation-list {
  min-height: 100vh;
  background: var(--gray-50);
  padding: 2rem;
}

/* Page Header */
.page-header {
  background: var(--white);
  border-radius: var(--border-radius);
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: var(--box-shadow);
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 2rem;
}

.title-section {
  flex: 1;
}

.page-title {
  font-size: 2rem;
  font-weight: 700;
  color: var(--gray-900);
  margin: 0 0 0.5rem 0;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.page-title i {
  color: var(--primary-color);
}

.page-subtitle {
  color: var(--gray-600);
  font-size: 1rem;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 1rem;
}

/* Buttons */
.btn-primary, .btn-secondary, .btn-outline, .btn-danger {
  padding: 0.75rem 1.5rem;
  border-radius: var(--border-radius);
  font-weight: 600;
  font-size: 0.875rem;
  border: none;
  cursor: pointer;
  transition: var(--transition);
  display: flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
}

.btn-primary {
  background: var(--primary-color);
  color: var(--white);
}

.btn-primary:hover {
  background: var(--primary-dark);
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
}

.btn-secondary {
  background: var(--gray-200);
  color: var(--gray-700);
}

.btn-secondary:hover {
  background: var(--gray-300);
}

.btn-outline {
  background: transparent;
  color: var(--gray-700);
  border: 2px solid var(--gray-300);
}

.btn-outline:hover {
  background: var(--gray-100);
  border-color: var(--gray-400);
}

.btn-danger {
  background: var(--danger-color);
  color: var(--white);
}

.btn-danger:hover {
  background: #b91c1c;
}

/* Filters Section */
.filters-section {
  background: var(--white);
  border-radius: var(--border-radius);
  padding: 1.5rem;
  margin-bottom: 2rem;
  box-shadow: var(--box-shadow);
}

.search-filter {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.search-box {
  position: relative;
  flex: 1;
  max-width: 400px;
}

.search-box i {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--gray-400);
}

.search-input {
  width: 100%;
  padding: 0.75rem 1rem 0.75rem 3rem;
  border: 2px solid var(--gray-200);
  border-radius: var(--border-radius);
  font-size: 0.875rem;
  transition: var(--transition);
}

.search-input:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.filter-group {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.filter-select, .filter-input {
  padding: 0.75rem;
  border: 2px solid var(--gray-200);
  border-radius: var(--border-radius);
  font-size: 0.875rem;
  min-width: 150px;
  transition: var(--transition);
}

.filter-select:focus, .filter-input:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* Summary Cards */
.summary-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.summary-card {
  background: var(--white);
  border-radius: var(--border-radius);
  padding: 1.5rem;
  box-shadow: var(--box-shadow);
  display: flex;
  align-items: center;
  gap: 1rem;
  transition: var(--transition);
}

.summary-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--box-shadow-lg);
}

.card-icon {
  width: 3rem;
  height: 3rem;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  color: var(--white);
}

.card-icon.pending {
  background: var(--warning-color);
}

.card-icon.completed {
  background: var(--success-color);
}

.card-icon.variance {
  background: var(--danger-color);
}

.card-content h3 {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--gray-900);
  margin: 0;
}

.card-content p {
  color: var(--gray-600);
  font-size: 0.875rem;
  margin: 0;
}

/* Data Table */
.data-table-container {
  background: var(--white);
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
  overflow: hidden;
}

.table-header {
  padding: 1.5rem;
  border-bottom: 1px solid var(--gray-200);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.table-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0;
}

.table-wrapper {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.data-table th {
  background: var(--gray-50);
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: var(--gray-700);
  border-bottom: 1px solid var(--gray-200);
}

.data-table th.sortable {
  cursor: pointer;
  user-select: none;
  transition: var(--transition);
}

.data-table th.sortable:hover {
  background: var(--gray-100);
}

.data-table th.sortable i {
  margin-left: 0.5rem;
  opacity: 0.5;
}

.data-table td {
  padding: 1rem;
  border-bottom: 1px solid var(--gray-100);
  vertical-align: middle;
}

.data-table tbody tr {
  transition: var(--transition);
}

.data-table tbody tr:hover {
  background: var(--gray-50);
}

.id-cell {
  font-weight: 600;
  color: var(--primary-color);
}

.bank-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.bank-info i {
  color: var(--gray-400);
}

.amount-cell {
  font-weight: 600;
  text-align: right;
}

.variance-cell {
  font-weight: 600;
  text-align: right;
}

.variance-zero {
  color: var(--gray-600);
}

.variance-positive {
  color: var(--success-color);
}

.variance-negative {
  color: var(--danger-color);
}

/* Status Badges */
.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.status-draft {
  background: rgba(156, 163, 175, 0.1);
  color: var(--gray-600);
}

.status-progress {
  background: rgba(217, 119, 6, 0.1);
  color: var(--warning-color);
}

.status-finalized {
  background: rgba(5, 150, 105, 0.1);
  color: var(--success-color);
}

/* Action Buttons */
.actions-cell {
  width: 120px;
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
}

.btn-icon {
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: var(--transition);
  background: var(--gray-100);
  color: var(--gray-600);
}

.btn-icon:hover {
  background: var(--gray-200);
  transform: scale(1.1);
}

.btn-icon.edit:hover {
  background: rgba(37, 99, 235, 0.1);
  color: var(--primary-color);
}

.btn-icon.delete:hover {
  background: rgba(220, 38, 38, 0.1);
  color: var(--danger-color);
}

/* Loading and Empty States */
.loading-state, .empty-state {
  padding: 4rem 2rem;
  text-align: center;
  color: var(--gray-600);
}

.loading-spinner {
  font-size: 2rem;
  color: var(--primary-color);
  margin-bottom: 1rem;
}

.empty-icon {
  font-size: 4rem;
  color: var(--gray-300);
  margin-bottom: 1rem;
}

.empty-state h3 {
  color: var(--gray-700);
  margin-bottom: 0.5rem;
}

.empty-state p {
  margin-bottom: 2rem;
}

/* Pagination */
.pagination-section {
  background: var(--white);
  border-radius: var(--border-radius);
  padding: 1.5rem;
  margin-top: 2rem;
  box-shadow: var(--box-shadow);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}

.pagination-info {
  color: var(--gray-600);
  font-size: 0.875rem;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-pagination {
  padding: 0.5rem 1rem;
  border: 2px solid var(--gray-200);
  background: var(--white);
  color: var(--gray-700);
  border-radius: var(--border-radius);
  cursor: pointer;
  transition: var(--transition);
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-pagination:hover:not(:disabled) {
  background: var(--gray-50);
  border-color: var(--gray-300);
}

.btn-pagination:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.page-numbers {
  display: flex;
  gap: 0.25rem;
}

.btn-page {
  width: 2.5rem;
  height: 2.5rem;
  border: 2px solid var(--gray-200);
  background: var(--white);
  color: var(--gray-700);
  border-radius: var(--border-radius);
  cursor: pointer;
  transition: var(--transition);
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-page:hover {
  background: var(--gray-50);
  border-color: var(--gray-300);
}

.btn-page.active {
  background: var(--primary-color);
  border-color: var(--primary-color);
  color: var(--white);
}

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}

.modal-content {
  background: var(--white);
  border-radius: var(--border-radius);
  max-width: 400px;
  width: 100%;
  box-shadow: var(--box-shadow-lg);
}

.modal-header {
  padding: 1.5rem;
  border-bottom: 1px solid var(--gray-200);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  color: var(--gray-900);
}

.btn-close {
  width: 2rem;
  height: 2rem;
  border: none;
  background: transparent;
  color: var(--gray-400);
  border-radius: 6px;
  cursor: pointer;
  transition: var(--transition);
}

.btn-close:hover {
  background: var(--gray-100);
  color: var(--gray-600);
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  padding: 1.5rem;
  border-top: 1px solid var(--gray-200);
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .bank-reconciliation-list {
    padding: 1rem;
  }

  .header-content {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  .filter-group {
    flex-direction: column;
  }

  .summary-cards {
    grid-template-columns: 1fr;
  }

  .pagination-section {
    flex-direction: column;
    gap: 1rem;
  }
}

@media (max-width: 768px) {
  .page-title {
    font-size: 1.5rem;
  }

  .summary-card {
    padding: 1rem;
  }

  .data-table {
    font-size: 0.75rem;
  }

  .data-table th,
  .data-table td {
    padding: 0.75rem 0.5rem;
  }

  .action-buttons {
    flex-direction: column;
  }
}
</style>