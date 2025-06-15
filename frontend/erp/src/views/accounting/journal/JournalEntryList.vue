<!-- src/views/accounting/JournalEntryList.vue -->
<template>
  <div class="journal-entries-container">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <h1 class="page-title">
            <i class="fas fa-book"></i>
            Journal Entries
          </h1>
          <p class="page-subtitle">Manage accounting journal entries and transactions</p>
        </div>
        <div class="header-actions">
          <button @click="refreshData" class="btn btn-secondary" :disabled="loading">
            <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
            Refresh
          </button>
          <router-link to="/accounting/journal-entries/batch-upload" class="btn btn-outline">
            <i class="fas fa-upload"></i>
            Batch Upload
          </router-link>
          <router-link to="/accounting/journal-entries/create" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            New Entry
          </router-link>
        </div>
      </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
      <div class="filters-grid">
        <div class="filter-group">
          <label>Date Range</label>
          <div class="date-range">
            <input 
              type="date" 
              v-model="filters.fromDate" 
              @change="applyFilters"
              class="form-input"
            />
            <span class="date-separator">to</span>
            <input 
              type="date" 
              v-model="filters.toDate" 
              @change="applyFilters"
              class="form-input"
            />
          </div>
        </div>
        
        <div class="filter-group">
          <label>Period</label>
          <select v-model="filters.periodId" @change="applyFilters" class="form-select">
            <option value="">All Periods</option>
            <option v-for="period in periods" :key="period.period_id" :value="period.period_id">
              {{ period.period_name }}
            </option>
          </select>
        </div>
        
        <div class="filter-group">
          <label>Status</label>
          <select v-model="filters.status" @change="applyFilters" class="form-select">
            <option value="">All Status</option>
            <option value="Draft">Draft</option>
            <option value="Posted">Posted</option>
            <option value="Cancelled">Cancelled</option>
          </select>
        </div>
        
        <div class="filter-group">
          <label>Search</label>
          <div class="search-input">
            <i class="fas fa-search"></i>
            <input 
              type="text" 
              v-model="filters.search" 
              @input="debounceSearch"
              placeholder="Search journal number or description..."
              class="form-input"
            />
          </div>
        </div>
      </div>
      
      <div class="filter-actions">
        <button @click="clearFilters" class="btn btn-ghost">
          <i class="fas fa-times"></i>
          Clear Filters
        </button>
        <span class="results-count">{{ totalEntries }} entries found</span>
      </div>
    </div>

    <!-- Journal Entries Table -->
    <div class="entries-table-container">
      <div v-if="loading" class="loading-state">
        <div class="loading-spinner"></div>
        <p>Loading journal entries...</p>
      </div>
      
      <div v-else-if="journalEntries.length === 0" class="empty-state">
        <div class="empty-icon">
          <i class="fas fa-book-open"></i>
        </div>
        <h3>No Journal Entries Found</h3>
        <p>{{ hasFilters ? 'No entries match your current filters.' : 'Start by creating your first journal entry.' }}</p>
        <router-link to="/accounting/journal-entries/create" class="btn btn-primary">
          <i class="fas fa-plus"></i>
          Create First Entry
        </router-link>
      </div>
      
      <div v-else class="table-wrapper">
        <table class="entries-table">
          <thead>
            <tr>
              <th @click="sort('journal_number')" class="sortable">
                <span>Journal Number</span>
                <i class="fas fa-sort" :class="getSortIcon('journal_number')"></i>
              </th>
              <th @click="sort('entry_date')" class="sortable">
                <span>Entry Date</span>
                <i class="fas fa-sort" :class="getSortIcon('entry_date')"></i>
              </th>
              <th>Description</th>
              <th>Period</th>
              <th>Reference</th>
              <th @click="sort('status')" class="sortable">
                <span>Status</span>
                <i class="fas fa-sort" :class="getSortIcon('status')"></i>
              </th>
              <th>Total Amount</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in journalEntries" :key="entry.journal_id" class="entry-row">
              <td class="journal-number">
                <router-link 
                  :to="`/accounting/journal-entries/${entry.journal_id}`"
                  class="entry-link"
                >
                  {{ entry.journal_number }}
                </router-link>
              </td>
              <td class="entry-date">
                {{ formatDate(entry.entry_date) }}
              </td>
              <td class="description">
                <span class="description-text" :title="entry.description">
                  {{ truncateText(entry.description, 50) }}
                </span>
              </td>
              <td class="period">
                <span class="period-badge">
                  {{ entry.accounting_period?.period_name || 'N/A' }}
                </span>
              </td>
              <td class="reference">
                <span v-if="entry.reference_type" class="reference-info">
                  {{ entry.reference_type }}: {{ entry.reference_id }}
                </span>
                <span v-else class="text-muted">-</span>
              </td>
              <td class="status">
                <span class="status-badge" :class="`status-${entry.status?.toLowerCase()}`">
                  <i :class="getStatusIcon(entry.status)"></i>
                  {{ entry.status }}
                </span>
              </td>
              <td class="total-amount">
                <span class="amount">
                  {{ formatCurrency(calculateTotalAmount(entry.journal_entry_lines)) }}
                </span>
              </td>
              <td class="actions">
                <div class="action-buttons">
                  <router-link 
                    :to="`/accounting/journal-entries/${entry.journal_id}`"
                    class="btn-icon btn-view"
                    title="View Details"
                  >
                    <i class="fas fa-eye"></i>
                  </router-link>
                  
                  <router-link 
                    v-if="entry.status === 'Draft'"
                    :to="`/accounting/journal-entries/${entry.journal_id}/edit`"
                    class="btn-icon btn-edit"
                    title="Edit Entry"
                  >
                    <i class="fas fa-edit"></i>
                  </router-link>
                  
                  <button
                    v-if="entry.status === 'Draft'"
                    @click="postEntry(entry)"
                    class="btn-icon btn-post"
                    title="Post Entry"
                  >
                    <i class="fas fa-check"></i>
                  </button>
                  
                  <button
                    v-if="entry.status === 'Draft'"
                    @click="deleteEntry(entry)"
                    class="btn-icon btn-delete"
                    title="Delete Entry"
                  >
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.last_page > 1" class="pagination-section">
      <div class="pagination-info">
        Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} entries
      </div>
      <div class="pagination-controls">
        <button 
          @click="changePage(pagination.current_page - 1)"
          :disabled="pagination.current_page === 1"
          class="btn btn-pagination"
        >
          <i class="fas fa-chevron-left"></i>
          Previous
        </button>
        
        <div class="pagination-numbers">
          <button
            v-for="page in getVisiblePages()"
            :key="page"
            @click="changePage(page)"
            class="btn btn-pagination"
            :class="{ active: page === pagination.current_page }"
          >
            {{ page }}
          </button>
        </div>
        
        <button 
          @click="changePage(pagination.current_page + 1)"
          :disabled="pagination.current_page === pagination.last_page"
          class="btn btn-pagination"
        >
          Next
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="modal-overlay" @click="showDeleteModal = false">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>Delete Journal Entry</h3>
          <button @click="showDeleteModal = false" class="btn-close">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete journal entry <strong>{{ entryToDelete?.journal_number }}</strong>?</p>
          <p class="warning-text">This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button @click="showDeleteModal = false" class="btn btn-secondary">Cancel</button>
          <button @click="confirmDelete" class="btn btn-danger" :disabled="deleting">
            <i class="fas fa-trash" :class="{ 'fa-spin': deleting }"></i>
            {{ deleting ? 'Deleting...' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import { debounce } from 'lodash'

export default {
  name: 'JournalEntryList',
  data() {
    return {
      journalEntries: [],
      periods: [],
      loading: false,
      deleting: false,
      showDeleteModal: false,
      entryToDelete: null,
      filters: {
        fromDate: '',
        toDate: '',
        periodId: '',
        status: '',
        search: ''
      },
      sorting: {
        field: 'entry_date',
        direction: 'desc'
      },
      pagination: {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
        from: 0,
        to: 0
      },
      totalEntries: 0
    }
  },
  computed: {
    hasFilters() {
      return Object.values(this.filters).some(value => value !== '')
    }
  },
  mounted() {
    this.loadPeriods()
    this.loadJournalEntries()
    this.setDefaultDateRange()
  },
  methods: {
    async loadPeriods() {
      try {
        const response = await axios.get('/accounting/accounting-periods')
        this.periods = response.data.data || response.data
      } catch (error) {
        this.$toast.error('Failed to load accounting periods')
      }
    },
    
    async loadJournalEntries() {
      this.loading = true
      try {
        const params = {
          page: this.pagination.current_page,
          per_page: this.pagination.per_page,
          sort_field: this.sorting.field,
          sort_direction: this.sorting.direction,
          ...this.filters
        }
        
        // Remove empty filters
        Object.keys(params).forEach(key => {
          if (params[key] === '' || params[key] === null || params[key] === undefined) {
            delete params[key]
          }
        })
        
        const response = await axios.get('/accounting/journal-entries', { params })
        
        this.journalEntries = response.data.data
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          per_page: response.data.per_page,
          total: response.data.total,
          from: response.data.from,
          to: response.data.to
        }
        this.totalEntries = response.data.total
        
      } catch (error) {
        this.$toast.error('Failed to load journal entries')
        console.error('Error loading journal entries:', error)
      } finally {
        this.loading = false
      }
    },
    
    setDefaultDateRange() {
      const today = new Date()
      const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1)
      const lastDayOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0)
      
      this.filters.fromDate = firstDayOfMonth.toISOString().split('T')[0]
      this.filters.toDate = lastDayOfMonth.toISOString().split('T')[0]
    },
    
    applyFilters() {
      this.pagination.current_page = 1
      this.loadJournalEntries()
    },
    
    clearFilters() {
      this.filters = {
        fromDate: '',
        toDate: '',
        periodId: '',
        status: '',
        search: ''
      }
      this.applyFilters()
    },
    
    debounceSearch: debounce(function() {
      this.applyFilters()
    }, 500),
    
    sort(field) {
      if (this.sorting.field === field) {
        this.sorting.direction = this.sorting.direction === 'asc' ? 'desc' : 'asc'
      } else {
        this.sorting.field = field
        this.sorting.direction = 'asc'
      }
      this.loadJournalEntries()
    },
    
    getSortIcon(field) {
      if (this.sorting.field !== field) return ''
      return this.sorting.direction === 'asc' ? 'fa-sort-up' : 'fa-sort-down'
    },
    
    changePage(page) {
      if (page >= 1 && page <= this.pagination.last_page) {
        this.pagination.current_page = page
        this.loadJournalEntries()
      }
    },
    
    getVisiblePages() {
      const current = this.pagination.current_page
      const last = this.pagination.last_page
      const delta = 2
      const range = []
      
      for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
        range.push(i)
      }
      
      if (current - delta > 2) {
        range.unshift('...')
      }
      if (current + delta < last - 1) {
        range.push('...')
      }
      
      range.unshift(1)
      if (last !== 1) {
        range.push(last)
      }
      
      return range
    },
    
    async postEntry(entry) {
      try {
        await axios.post(`/accounting/journal-entries/${entry.journal_id}/post`)
        this.$toast.success('Journal entry posted successfully')
        this.loadJournalEntries()
      } catch (error) {
        this.$toast.error('Failed to post journal entry')
        console.error('Error posting entry:', error)
      }
    },
    
    deleteEntry(entry) {
      this.entryToDelete = entry
      this.showDeleteModal = true
    },
    
    async confirmDelete() {
      this.deleting = true
      try {
        await axios.delete(`/accounting/journal-entries/${this.entryToDelete.journal_id}`)
        this.$toast.success('Journal entry deleted successfully')
        this.showDeleteModal = false
        this.entryToDelete = null
        this.loadJournalEntries()
      } catch (error) {
        this.$toast.error('Failed to delete journal entry')
        console.error('Error deleting entry:', error)
      } finally {
        this.deleting = false
      }
    },
    
    refreshData() {
      this.loadJournalEntries()
      this.loadPeriods()
    },
    
    formatDate(date) {
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      })
    },
    
    formatCurrency(amount) {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount || 0)
    },
    
    calculateTotalAmount(lines) {
      if (!lines || !Array.isArray(lines)) return 0
      return lines.reduce((sum, line) => sum + (parseFloat(line.debit_amount) || 0), 0)
    },
    
    truncateText(text, length) {
      if (!text) return ''
      return text.length > length ? text.substring(0, length) + '...' : text
    },
    
    getStatusIcon(status) {
      switch (status?.toLowerCase()) {
        case 'draft': return 'fas fa-edit'
        case 'posted': return 'fas fa-check-circle'
        case 'cancelled': return 'fas fa-times-circle'
        default: return 'fas fa-question-circle'
      }
    }
  }
}
</script>

<style scoped>
.journal-entries-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 2rem;
  background: var(--bg-secondary);
  min-height: 100vh;
}

/* Page Header */
.page-header {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.title-section .page-title {
  font-size: 2rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 0.5rem 0;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.page-title i {
  color: #6366f1;
  font-size: 1.75rem;
}

.page-subtitle {
  color: var(--text-secondary);
  margin: 0;
  font-size: 1rem;
}

.header-actions {
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

/* Filters Section */
.filters-section {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  margin-bottom: 2rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.filters-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 1rem;
}

.filter-group label {
  display: block;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
}

.date-range {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.date-separator {
  color: var(--text-secondary);
  font-size: 0.875rem;
  white-space: nowrap;
}

.search-input {
  position: relative;
}

.search-input i {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
}

.search-input input {
  padding-left: 2.75rem;
}

.filter-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 1rem;
  border-top: 1px solid var(--border-color);
}

.results-count {
  color: var(--text-secondary);
  font-size: 0.875rem;
}

/* Table Container */
.entries-table-container {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
  margin-bottom: 2rem;
}

.table-wrapper {
  overflow-x: auto;
}

.entries-table {
  width: 100%;
  border-collapse: collapse;
}

.entries-table th {
  background: var(--bg-tertiary);
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: var(--text-primary);
  border-bottom: 2px solid var(--border-color);
  white-space: nowrap;
}

.entries-table th.sortable {
  cursor: pointer;
  user-select: none;
  transition: background-color 0.2s;
}

.entries-table th.sortable:hover {
  background: var(--bg-secondary);
}

.entries-table th.sortable span {
  margin-right: 0.5rem;
}

.entries-table th.sortable i {
  opacity: 0.5;
  transition: opacity 0.2s;
}

.entries-table th.sortable:hover i {
  opacity: 1;
}

.entries-table td {
  padding: 1rem;
  border-bottom: 1px solid var(--border-color);
  vertical-align: middle;
}

.entry-row {
  transition: background-color 0.2s;
}

.entry-row:hover {
  background: var(--bg-tertiary);
}

.entry-link {
  color: #6366f1;
  text-decoration: none;
  font-weight: 600;
  transition: color 0.2s;
}

.entry-link:hover {
  color: #4f46e5;
}

.description-text {
  color: var(--text-primary);
}

.period-badge {
  background: var(--bg-tertiary);
  color: var(--text-primary);
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
}

.reference-info {
  font-size: 0.875rem;
  color: var(--text-secondary);
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.025em;
}

.status-draft {
  background: rgba(245, 158, 11, 0.1);
  color: #d97706;
}

.status-posted {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
}

.status-cancelled {
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
}

.amount {
  font-weight: 600;
  color: var(--text-primary);
  font-family: 'Courier New', monospace;
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
}

.btn-icon {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
}

.btn-view {
  background: rgba(99, 102, 241, 0.1);
  color: #6366f1;
}

.btn-view:hover {
  background: rgba(99, 102, 241, 0.2);
  transform: translateY(-2px);
}

.btn-edit {
  background: rgba(245, 158, 11, 0.1);
  color: #d97706;
}

.btn-edit:hover {
  background: rgba(245, 158, 11, 0.2);
  transform: translateY(-2px);
}

.btn-post {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
}

.btn-post:hover {
  background: rgba(16, 185, 129, 0.2);
  transform: translateY(-2px);
}

.btn-delete {
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
}

.btn-delete:hover {
  background: rgba(239, 68, 68, 0.2);
  transform: translateY(-2px);
}

/* States */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  color: var(--text-secondary);
}

.loading-spinner {
  width: 48px;
  height: 48px;
  border: 4px solid rgba(99, 102, 241, 0.2);
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 1rem;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  text-align: center;
}

.empty-icon {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: var(--bg-tertiary);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1.5rem;
}

.empty-icon i {
  font-size: 2rem;
  color: var(--text-muted);
}

.empty-state h3 {
  color: var(--text-primary);
  margin: 0 0 0.5rem 0;
  font-size: 1.25rem;
}

.empty-state p {
  color: var(--text-secondary);
  margin: 0 0 1.5rem 0;
}

/* Pagination */
.pagination-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: white;
  padding: 1.5rem;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.pagination-info {
  color: var(--text-secondary);
  font-size: 0.875rem;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.pagination-numbers {
  display: flex;
  gap: 0.25rem;
}

.btn-pagination {
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--border-color);
  background: white;
  color: var(--text-secondary);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 0.875rem;
}

.btn-pagination:hover:not(:disabled) {
  background: var(--bg-tertiary);
  border-color: #6366f1;
}

.btn-pagination.active {
  background: #6366f1;
  color: white;
  border-color: #6366f1;
}

.btn-pagination:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Form Elements */
.form-input, .form-select {
  width: 100%;
  padding: 0.75rem;
  border: 2px solid var(--border-color);
  border-radius: 8px;
  font-size: 0.875rem;
  transition: border-color 0.2s, box-shadow 0.2s;
  background: white;
}

.form-input:focus, .form-select:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  font-weight: 500;
  font-size: 0.875rem;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}

.btn-primary {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
}

.btn-secondary {
  background: var(--bg-tertiary);
  color: var(--text-primary);
  border: 1px solid var(--border-color);
}

.btn-secondary:hover {
  background: var(--bg-secondary);
}

.btn-outline {
  background: white;
  color: #6366f1;
  border: 2px solid #6366f1;
}

.btn-outline:hover {
  background: #6366f1;
  color: white;
}

.btn-ghost {
  background: transparent;
  color: var(--text-secondary);
}

.btn-ghost:hover {
  background: var(--bg-tertiary);
}

.btn-danger {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
}

.btn-danger:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
}

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 16px;
  width: 90%;
  max-width: 500px;
  max-height: 90vh;
  overflow: hidden;
  box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
  margin: 0;
  color: var(--text-primary);
}

.btn-close {
  background: none;
  border: none;
  font-size: 1.25rem;
  cursor: pointer;
  color: var(--text-muted);
  padding: 0.25rem;
  border-radius: 4px;
}

.btn-close:hover {
  color: var(--text-primary);
  background: var(--bg-tertiary);
}

.modal-body {
  padding: 1.5rem;
}

.modal-body p {
  margin: 0 0 1rem 0;
  color: var(--text-primary);
}

.warning-text {
  color: #ef4444;
  font-size: 0.875rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1.5rem;
  border-top: 1px solid var(--border-color);
}

/* Utilities */
.text-muted {
  color: var(--text-muted);
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 768px) {
  .journal-entries-container {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    align-items: stretch;
  }
  
  .filters-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }
  
  .date-range {
    flex-direction: column;
    align-items: stretch;
  }
  
  .filter-actions {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .pagination-section {
    flex-direction: column;
    gap: 1rem;
  }
  
  .pagination-controls {
    flex-wrap: wrap;
    justify-content: center;
  }
  
  .entries-table {
    font-size: 0.875rem;
  }
  
  .entries-table th,
  .entries-table td {
    padding: 0.75rem 0.5rem;
  }
  
  .action-buttons {
    flex-direction: column;
  }
}
</style>