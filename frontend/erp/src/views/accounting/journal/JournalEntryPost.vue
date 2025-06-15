<!-- src/views/accounting/JournalEntryPost.vue -->
<template>
  <div class="journal-post-container">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <router-link to="/accounting/journal-entries" class="back-button">
            <i class="fas fa-arrow-left"></i>
          </router-link>
          <div>
            <h1 class="page-title">
              <i class="fas fa-check-circle"></i>
              Post Journal Entries
            </h1>
            <p class="page-subtitle">Review and post multiple journal entries</p>
          </div>
        </div>
        <div class="header-actions">
          <button @click="refreshData" class="btn btn-secondary" :disabled="loading">
            <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
            Refresh
          </button>
          <button 
            @click="postSelected" 
            class="btn btn-primary" 
            :disabled="selectedEntries.length === 0 || posting"
          >
            <i class="fas fa-check" :class="{ 'fa-spin': posting }"></i>
            Post Selected ({{ selectedEntries.length }})
          </button>
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

        <div class="filter-group">
          <label>Quick Filters</label>
          <div class="quick-filters">
            <button 
              @click="applyQuickFilter('all')" 
              class="filter-btn"
              :class="{ active: quickFilter === 'all' }"
            >
              All Draft
            </button>
            <button 
              @click="applyQuickFilter('balanced')" 
              class="filter-btn"
              :class="{ active: quickFilter === 'balanced' }"
            >
              Balanced Only
            </button>
            <button 
              @click="applyQuickFilter('unbalanced')" 
              class="filter-btn"
              :class="{ active: quickFilter === 'unbalanced' }"
            >
              Unbalanced
            </button>
          </div>
        </div>
      </div>
      
      <div class="summary-stats">
        <div class="stat-card">
          <div class="stat-value">{{ totalEntries }}</div>
          <div class="stat-label">Total Entries</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ balancedEntries }}</div>
          <div class="stat-label">Balanced</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ unbalancedEntries }}</div>
          <div class="stat-label">Unbalanced</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ formatCurrency(totalAmount) }}</div>
          <div class="stat-label">Total Amount</div>
        </div>
      </div>
    </div>

    <!-- Validation Summary -->
    <div v-if="validationSummary.length > 0" class="validation-section">
      <div class="validation-header">
        <h3><i class="fas fa-exclamation-triangle"></i> Validation Issues</h3>
        <span class="issue-count">{{ validationSummary.length }} issues found</span>
      </div>
      <div class="validation-list">
        <div 
          v-for="issue in validationSummary" 
          :key="issue.id" 
          class="validation-item"
          :class="issue.severity"
        >
          <div class="issue-icon">
            <i :class="getIssueIcon(issue.severity)"></i>
          </div>
          <div class="issue-content">
            <h4>{{ issue.title }}</h4>
            <p>{{ issue.description }}</p>
            <div class="issue-entries">
              <span v-for="entryId in issue.entries" :key="entryId" class="entry-tag">
                {{ getEntryNumber(entryId) }}
              </span>
            </div>
          </div>
          <div class="issue-actions">
            <button @click="fixIssue(issue)" class="btn-sm btn-outline">
              {{ issue.severity === 'error' ? 'Fix Required' : 'Review' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Journal Entries Table -->
    <div class="entries-table-container">
      <div v-if="loading" class="loading-state">
        <div class="loading-spinner"></div>
        <p>Loading draft journal entries...</p>
      </div>
      
      <div v-else-if="journalEntries.length === 0" class="empty-state">
        <div class="empty-icon">
          <i class="fas fa-check-circle"></i>
        </div>
        <h3>No Draft Entries Found</h3>
        <p>{{ hasFilters ? 'No draft entries match your current filters.' : 'All journal entries have been posted.' }}</p>
        <router-link to="/accounting/journal-entries/create" class="btn btn-primary">
          <i class="fas fa-plus"></i>
          Create New Entry
        </router-link>
      </div>
      
      <div v-else>
        <!-- Bulk Actions -->
        <div class="bulk-actions">
          <div class="selection-info">
            <label class="checkbox-wrapper">
              <input 
                type="checkbox" 
                :checked="isAllSelected"
                @change="toggleSelectAll"
                :indeterminate.prop="isPartiallySelected"
              />
              <span class="checkmark"></span>
              <span class="label">
                {{ selectedEntries.length === 0 ? 'Select All' : `${selectedEntries.length} selected` }}
              </span>
            </label>
          </div>
          
          <div class="bulk-buttons" v-if="selectedEntries.length > 0">
            <button @click="validateSelected" class="btn btn-outline">
              <i class="fas fa-check-double"></i>
              Validate Selected
            </button>
            <button @click="showPostModal = true" class="btn btn-success">
              <i class="fas fa-check"></i>
              Post Selected ({{ selectedEntries.length }})
            </button>
          </div>
        </div>

        <!-- Table -->
        <div class="table-wrapper">
          <table class="entries-table">
            <thead>
              <tr>
                <th style="width: 50px">
                  <input 
                    type="checkbox" 
                    :checked="isAllSelected"
                    @change="toggleSelectAll"
                    :indeterminate.prop="isPartiallySelected"
                  />
                </th>
                <th>Journal Number</th>
                <th>Entry Date</th>
                <th>Description</th>
                <th>Period</th>
                <th>Total Amount</th>
                <th>Status</th>
                <th>Validation</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr 
                v-for="entry in journalEntries" 
                :key="entry.journal_id" 
                class="entry-row"
                :class="{ 
                  'selected': selectedEntries.includes(entry.journal_id),
                  'error': !isEntryBalanced(entry),
                  'warning': hasValidationWarnings(entry)
                }"
              >
                <td class="checkbox-cell">
                  <label class="checkbox-wrapper">
                    <input 
                      type="checkbox" 
                      :value="entry.journal_id"
                      v-model="selectedEntries"
                    />
                    <span class="checkmark"></span>
                  </label>
                </td>
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
                    {{ truncateText(entry.description, 40) }}
                  </span>
                </td>
                <td class="period">
                  <span class="period-badge">
                    {{ entry.accounting_period?.period_name || 'N/A' }}
                  </span>
                </td>
                <td class="total-amount">
                  <span class="amount">
                    {{ formatCurrency(calculateTotalAmount(entry.journal_entry_lines)) }}
                  </span>
                </td>
                <td class="status">
                  <span class="status-badge status-draft">
                    <i class="fas fa-edit"></i>
                    Draft
                  </span>
                </td>
                <td class="validation">
                  <div class="validation-indicator">
                    <span 
                      v-if="isEntryBalanced(entry)" 
                      class="validation-badge success"
                      title="Entry is balanced"
                    >
                      <i class="fas fa-check"></i>
                      Balanced
                    </span>
                    <span 
                      v-else 
                      class="validation-badge error"
                      title="Entry is not balanced"
                    >
                      <i class="fas fa-exclamation-triangle"></i>
                      Unbalanced
                    </span>
                    <div v-if="getEntryWarnings(entry).length > 0" class="warnings">
                      <span 
                        v-for="warning in getEntryWarnings(entry)" 
                        :key="warning"
                        class="warning-badge"
                        :title="warning"
                      >
                        <i class="fas fa-exclamation"></i>
                      </span>
                    </div>
                  </div>
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
                      :to="`/accounting/journal-entries/${entry.journal_id}/edit`"
                      class="btn-icon btn-edit"
                      title="Edit Entry"
                    >
                      <i class="fas fa-edit"></i>
                    </router-link>
                    
                    <button
                      @click="postSingleEntry(entry)"
                      class="btn-icon btn-post"
                      title="Post Entry"
                      :disabled="!isEntryBalanced(entry) || posting"
                    >
                      <i class="fas fa-check"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
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

    <!-- Post Confirmation Modal -->
    <div v-if="showPostModal" class="modal-overlay" @click="showPostModal = false">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>Post Journal Entries</h3>
          <button @click="showPostModal = false" class="btn-close">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="post-summary">
            <h4>Posting Summary</h4>
            <div class="summary-grid">
              <div class="summary-item">
                <label>Entries to Post:</label>
                <span>{{ selectedEntries.length }}</span>
              </div>
              <div class="summary-item">
                <label>Total Amount:</label>
                <span>{{ formatCurrency(getSelectedTotalAmount()) }}</span>
              </div>
              <div class="summary-item">
                <label>Balanced Entries:</label>
                <span>{{ getSelectedBalancedCount() }}</span>
              </div>
              <div class="summary-item">
                <label>Unbalanced Entries:</label>
                <span class="text-error">{{ selectedEntries.length - getSelectedBalancedCount() }}</span>
              </div>
            </div>
          </div>
          
          <div v-if="selectedEntries.length - getSelectedBalancedCount() > 0" class="error-box">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
              <h4>Warning: Unbalanced Entries</h4>
              <p>Some selected entries are not balanced. Only balanced entries will be posted.</p>
            </div>
          </div>
          
          <div class="entries-list">
            <h5>Selected Entries:</h5>
            <div class="selected-entries">
              <div 
                v-for="entryId in selectedEntries" 
                :key="entryId"
                class="selected-entry"
                :class="{ 'balanced': isSelectedEntryBalanced(entryId) }"
              >
                <span class="entry-number">{{ getEntryNumber(entryId) }}</span>
                <span class="entry-amount">{{ formatCurrency(getEntryAmount(entryId)) }}</span>
                <span class="entry-status">
                  <i v-if="isSelectedEntryBalanced(entryId)" class="fas fa-check text-success"></i>
                  <i v-else class="fas fa-exclamation-triangle text-error"></i>
                </span>
              </div>
            </div>
          </div>
          
          <p><strong>Are you sure you want to post these journal entries?</strong></p>
          <p class="warning-text">Posted entries cannot be edited and will be final.</p>
        </div>
        <div class="modal-footer">
          <button @click="showPostModal = false" class="btn btn-secondary">Cancel</button>
          <button @click="confirmPost" class="btn btn-success" :disabled="posting">
            <i class="fas fa-check" :class="{ 'fa-spin': posting }"></i>
            {{ posting ? 'Posting...' : 'Post Entries' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Loading Overlay -->
    <div v-if="posting" class="loading-overlay">
      <div class="loading-content">
        <div class="loading-spinner"></div>
        <p>Posting journal entries...</p>
        <div class="progress-info">
          {{ postingProgress.current }} of {{ postingProgress.total }} entries processed
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import { debounce } from 'lodash'

export default {
  name: 'JournalEntryPost',
  data() {
    return {
      journalEntries: [],
      periods: [],
      loading: false,
      posting: false,
      showPostModal: false,
      selectedEntries: [],
      quickFilter: 'all',
      filters: {
        fromDate: '',
        toDate: '',
        periodId: '',
        search: ''
      },
      pagination: {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
        from: 0,
        to: 0
      },
      validationSummary: [],
      postingProgress: {
        current: 0,
        total: 0
      }
    }
  },
  computed: {
    hasFilters() {
      return Object.values(this.filters).some(value => value !== '') || this.quickFilter !== 'all'
    },
    totalEntries() {
      return this.journalEntries.length
    },
    balancedEntries() {
      return this.journalEntries.filter(entry => this.isEntryBalanced(entry)).length
    },
    unbalancedEntries() {
      return this.totalEntries - this.balancedEntries
    },
    totalAmount() {
      return this.journalEntries.reduce((sum, entry) => 
        sum + this.calculateTotalAmount(entry.journal_entry_lines), 0
      )
    },
    isAllSelected() {
      return this.journalEntries.length > 0 && this.selectedEntries.length === this.journalEntries.length
    },
    isPartiallySelected() {
      return this.selectedEntries.length > 0 && this.selectedEntries.length < this.journalEntries.length
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
          status: 'Draft', // Only draft entries
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
        
        this.generateValidationSummary()
        this.selectedEntries = []
        
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

    applyQuickFilter(filter) {
      this.quickFilter = filter
      // Apply client-side filtering based on balance status
      this.applyFilters()
    },

    debounceSearch: debounce(function() {
      this.applyFilters()
    }, 500),

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

    toggleSelectAll() {
      if (this.isAllSelected) {
        this.selectedEntries = []
      } else {
        this.selectedEntries = this.journalEntries.map(entry => entry.journal_id)
      }
    },

    isEntryBalanced(entry) {
      if (!entry.journal_entry_lines) return false
      const totalDebits = entry.journal_entry_lines.reduce((sum, line) => 
        sum + (parseFloat(line.debit_amount) || 0), 0
      )
      const totalCredits = entry.journal_entry_lines.reduce((sum, line) => 
        sum + (parseFloat(line.credit_amount) || 0), 0
      )
      const difference = Math.abs(totalDebits - totalCredits)
      return difference < 0.01 && totalDebits > 0
    },

    hasValidationWarnings(entry) {
      return this.getEntryWarnings(entry).length > 0
    },

    getEntryWarnings(entry) {
      const warnings = []
      
      if (!entry.description || entry.description.trim().length < 5) {
        warnings.push('Description too short')
      }
      
      if (entry.journal_entry_lines && entry.journal_entry_lines.length < 2) {
        warnings.push('Insufficient lines')
      }
      
      if (entry.journal_entry_lines) {
        const hasEmptyDescription = entry.journal_entry_lines.some(line => !line.description)
        if (hasEmptyDescription) {
          warnings.push('Line descriptions missing')
        }
      }
      
      return warnings
    },

    generateValidationSummary() {
      this.validationSummary = []
      
      const unbalancedEntries = this.journalEntries.filter(entry => !this.isEntryBalanced(entry))
      if (unbalancedEntries.length > 0) {
        this.validationSummary.push({
          id: 'unbalanced',
          severity: 'error',
          title: 'Unbalanced Entries',
          description: 'These entries have unequal debits and credits',
          entries: unbalancedEntries.map(e => e.journal_id)
        })
      }
      
      const shortDescriptions = this.journalEntries.filter(entry => 
        !entry.description || entry.description.trim().length < 5
      )
      if (shortDescriptions.length > 0) {
        this.validationSummary.push({
          id: 'short_description',
          severity: 'warning',
          title: 'Short Descriptions',
          description: 'These entries have very short or missing descriptions',
          entries: shortDescriptions.map(e => e.journal_id)
        })
      }
      
      const missingLineDescriptions = this.journalEntries.filter(entry => 
        entry.journal_entry_lines && 
        entry.journal_entry_lines.some(line => !line.description)
      )
      if (missingLineDescriptions.length > 0) {
        this.validationSummary.push({
          id: 'missing_line_desc',
          severity: 'warning',
          title: 'Missing Line Descriptions',
          description: 'Some journal lines are missing descriptions',
          entries: missingLineDescriptions.map(e => e.journal_id)
        })
      }
    },

    validateSelected() {
      const selectedBalanced = this.selectedEntries.filter(id => {
        const entry = this.journalEntries.find(e => e.journal_id === id)
        return this.isEntryBalanced(entry)
      })
      
      this.$toast.info(`${selectedBalanced.length} of ${this.selectedEntries.length} selected entries are balanced and ready to post`)
    },

    async postSelected() {
      this.showPostModal = true
    },

    async postSingleEntry(entry) {
      if (!this.isEntryBalanced(entry)) {
        this.$toast.error('Cannot post unbalanced entry')
        return
      }
      
      this.posting = true
      this.postingProgress = { current: 0, total: 1 }
      
      try {
        await axios.post(`/accounting/journal-entries/${entry.journal_id}/post`)
        this.$toast.success(`Journal entry ${entry.journal_number} posted successfully`)
        this.loadJournalEntries()
      } catch (error) {
        this.$toast.error('Failed to post journal entry')
        console.error('Error posting entry:', error)
      } finally {
        this.posting = false
      }
    },

    async confirmPost() {
      const balancedEntries = this.selectedEntries.filter(id => {
        const entry = this.journalEntries.find(e => e.journal_id === id)
        return this.isEntryBalanced(entry)
      })
      
      if (balancedEntries.length === 0) {
        this.$toast.error('No balanced entries to post')
        return
      }
      
      this.posting = true
      this.postingProgress = { current: 0, total: balancedEntries.length }
      
      let successCount = 0
      let errorCount = 0
      
      for (const entryId of balancedEntries) {
        try {
          await axios.post(`/accounting/journal-entries/${entryId}/post`)
          successCount++
        } catch (error) {
          errorCount++
          console.error(`Error posting entry ${entryId}:`, error)
        }
        this.postingProgress.current++
      }
      
      this.showPostModal = false
      this.posting = false
      
      if (successCount > 0) {
        this.$toast.success(`${successCount} journal entries posted successfully`)
      }
      if (errorCount > 0) {
        this.$toast.error(`${errorCount} entries failed to post`)
      }
      
      this.loadJournalEntries()
    },

    getSelectedTotalAmount() {
      return this.selectedEntries.reduce((sum, entryId) => {
        const entry = this.journalEntries.find(e => e.journal_id === entryId)
        return sum + (entry ? this.calculateTotalAmount(entry.journal_entry_lines) : 0)
      }, 0)
    },

    getSelectedBalancedCount() {
      return this.selectedEntries.filter(id => {
        const entry = this.journalEntries.find(e => e.journal_id === id)
        return this.isEntryBalanced(entry)
      }).length
    },

    isSelectedEntryBalanced(entryId) {
      const entry = this.journalEntries.find(e => e.journal_id === entryId)
      return this.isEntryBalanced(entry)
    },

    getEntryNumber(entryId) {
      const entry = this.journalEntries.find(e => e.journal_id === entryId)
      return entry ? entry.journal_number : 'N/A'
    },

    getEntryAmount(entryId) {
      const entry = this.journalEntries.find(e => e.journal_id === entryId)
      return entry ? this.calculateTotalAmount(entry.journal_entry_lines) : 0
    },

    refreshData() {
      this.loadJournalEntries()
      this.loadPeriods()
    },

    fixIssue(issue) {
      // Navigate to first entry with this issue for fixing
      if (issue.entries.length > 0) {
        this.$router.push(`/accounting/journal-entries/${issue.entries[0]}/edit`)
      }
    },

    getIssueIcon(severity) {
      switch (severity) {
        case 'error': return 'fas fa-exclamation-circle'
        case 'warning': return 'fas fa-exclamation-triangle'
        default: return 'fas fa-info-circle'
      }
    },

    calculateTotalAmount(lines) {
      if (!lines || !Array.isArray(lines)) return 0
      return lines.reduce((sum, line) => sum + (parseFloat(line.debit_amount) || 0), 0)
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

    truncateText(text, length) {
      if (!text) return ''
      return text.length > length ? text.substring(0, length) + '...' : text
    }
  }
}
</script>

<style scoped>
.journal-post-container {
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

.title-section {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.back-button {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--bg-tertiary);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-secondary);
  text-decoration: none;
  transition: all 0.2s;
}

.back-button:hover {
  background: var(--bg-secondary);
  color: var(--text-primary);
  transform: translateX(-2px);
}

.page-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 0.25rem 0;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.page-title i {
  color: #059669;
}

.page-subtitle {
  color: var(--text-secondary);
  margin: 0;
  font-size: 0.9rem;
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
  margin-bottom: 1.5rem;
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

.quick-filters {
  display: flex;
  gap: 0.5rem;
}

.filter-btn {
  padding: 0.5rem 1rem;
  border: 2px solid var(--border-color);
  background: white;
  color: var(--text-secondary);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 0.8rem;
  font-weight: 500;
}

.filter-btn:hover {
  border-color: #6366f1;
  color: #6366f1;
}

.filter-btn.active {
  background: #6366f1;
  color: white;
  border-color: #6366f1;
}

.summary-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 1rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--border-color);
}

.stat-card {
  text-align: center;
  padding: 1rem;
  background: var(--bg-tertiary);
  border-radius: 12px;
  border: 1px solid var(--border-color);
}

.stat-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 0.25rem;
}

.stat-label {
  font-size: 0.8rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.025em;
}

/* Validation Section */
.validation-section {
  background: white;
  border-radius: 16px;
  margin-bottom: 2rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
  overflow: hidden;
}

.validation-header {
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(217, 119, 6, 0.1) 100%);
  padding: 1rem 1.5rem;
  border-bottom: 1px solid rgba(245, 158, 11, 0.3);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.validation-header h3 {
  margin: 0;
  color: #92400e;
  font-size: 1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.issue-count {
  background: rgba(245, 158, 11, 0.2);
  color: #92400e;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 600;
}

.validation-list {
  padding: 1.5rem;
}

.validation-item {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  border-radius: 12px;
  margin-bottom: 1rem;
  border: 2px solid;
}

.validation-item:last-child {
  margin-bottom: 0;
}

.validation-item.error {
  background: rgba(239, 68, 68, 0.05);
  border-color: rgba(239, 68, 68, 0.2);
}

.validation-item.warning {
  background: rgba(245, 158, 11, 0.05);
  border-color: rgba(245, 158, 11, 0.2);
}

.issue-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  flex-shrink: 0;
}

.validation-item.error .issue-icon {
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
}

.validation-item.warning .issue-icon {
  background: rgba(245, 158, 11, 0.1);
  color: #d97706;
}

.issue-content {
  flex: 1;
}

.issue-content h4 {
  margin: 0 0 0.5rem 0;
  color: var(--text-primary);
  font-size: 1rem;
}

.issue-content p {
  margin: 0 0 0.75rem 0;
  color: var(--text-secondary);
  font-size: 0.9rem;
}

.issue-entries {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.entry-tag {
  background: var(--bg-tertiary);
  color: var(--text-primary);
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 500;
}

.issue-actions {
  display: flex;
  align-items: center;
}

/* Bulk Actions */
.bulk-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  background: var(--bg-tertiary);
  border-bottom: 1px solid var(--border-color);
}

.selection-info {
  display: flex;
  align-items: center;
}

.bulk-buttons {
  display: flex;
  gap: 0.75rem;
}

/* Checkbox Styles */
.checkbox-wrapper {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

.checkbox-wrapper input[type="checkbox"] {
  display: none;
}

.checkmark {
  width: 20px;
  height: 20px;
  border: 2px solid var(--border-color);
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  background: white;
}

.checkbox-wrapper input[type="checkbox"]:checked + .checkmark {
  background: #6366f1;
  border-color: #6366f1;
}

.checkbox-wrapper input[type="checkbox"]:checked + .checkmark::after {
  content: '✓';
  color: white;
  font-weight: bold;
  font-size: 0.8rem;
}

.checkbox-wrapper input[type="checkbox"]:indeterminate + .checkmark {
  background: #6366f1;
  border-color: #6366f1;
}

.checkbox-wrapper input[type="checkbox"]:indeterminate + .checkmark::after {
  content: '−';
  color: white;
  font-weight: bold;
  font-size: 1rem;
}

.checkbox-wrapper .label {
  font-size: 0.9rem;
  color: var(--text-primary);
  font-weight: 500;
}

/* Table Styles */
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

.entries-table td {
  padding: 1rem;
  border-bottom: 1px solid var(--border-color);
  vertical-align: middle;
}

.entry-row {
  transition: all 0.2s;
}

.entry-row:hover {
  background: var(--bg-tertiary);
}

.entry-row.selected {
  background: rgba(99, 102, 241, 0.05);
  border-left: 4px solid #6366f1;
}

.entry-row.error {
  background: rgba(239, 68, 68, 0.05);
}

.entry-row.warning {
  background: rgba(245, 158, 11, 0.05);
}

.checkbox-cell {
  text-align: center;
  width: 50px;
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

.amount {
  font-weight: 600;
  color: var(--text-primary);
  font-family: 'Courier New', monospace;
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

.validation-indicator {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.validation-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.25rem 0.5rem;
  border-radius: 8px;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
}

.validation-badge.success {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
}

.validation-badge.error {
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
}

.warnings {
  display: flex;
  gap: 0.25rem;
}

.warning-badge {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: rgba(245, 158, 11, 0.1);
  color: #d97706;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
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

.btn-post:hover:not(:disabled) {
  background: rgba(16, 185, 129, 0.2);
  transform: translateY(-2px);
}

.btn-post:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* States */
.loading-state, .empty-state {
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
  animation: spin 1s ease-in-out infinite;
  margin-bottom: 1rem;
}

.empty-state .empty-icon {
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
  color: #059669;
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
  max-width: 700px;
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
  max-height: 60vh;
  overflow-y: auto;
}

.post-summary h4 {
  margin: 0 0 1rem 0;
  color: var(--text-primary);
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.summary-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: var(--bg-tertiary);
  border-radius: 8px;
}

.summary-item label {
  font-weight: 600;
  color: var(--text-secondary);
}

.error-box {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  background: rgba(239, 68, 68, 0.1);
  border: 2px solid rgba(239, 68, 68, 0.3);
  border-radius: 12px;
  margin: 1rem 0;
}

.error-box i {
  color: #dc2626;
  font-size: 1.25rem;
  margin-top: 0.25rem;
}

.error-box h4 {
  margin: 0 0 0.5rem 0;
  color: #dc2626;
}

.error-box p {
  margin: 0;
  color: #b91c1c;
}

.entries-list h5 {
  margin: 0 0 1rem 0;
  color: var(--text-primary);
}

.selected-entries {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  max-height: 200px;
  overflow-y: auto;
}

.selected-entry {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: var(--bg-tertiary);
  border-radius: 8px;
  border-left: 4px solid var(--border-color);
}

.selected-entry.balanced {
  border-left-color: #059669;
}

.entry-number {
  font-weight: 600;
  color: var(--text-primary);
}

.entry-amount {
  font-family: 'Courier New', monospace;
  color: var(--text-secondary);
}

.entry-status {
  font-size: 1.1rem;
}

.warning-text {
  color: #dc2626;
  font-size: 0.875rem;
  font-style: italic;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1.5rem;
  border-top: 1px solid var(--border-color);
}

/* Loading Overlay */
.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(248, 250, 252, 0.95);
  backdrop-filter: blur(10px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  flex-direction: column;
}

.loading-content {
  text-align: center;
}

.loading-content .loading-spinner {
  width: 60px;
  height: 60px;
  border: 4px solid rgba(99, 102, 241, 0.2);
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin 1s ease-in-out infinite;
  margin: 0 auto 1rem;
}

.loading-content p {
  color: var(--text-primary);
  font-weight: 500;
  font-size: 1.1rem;
  margin-bottom: 0.5rem;
}

.progress-info {
  color: var(--text-secondary);
  font-size: 0.9rem;
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

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
}

.btn-secondary {
  background: var(--bg-tertiary);
  color: var(--text-primary);
  border: 1px solid var(--border-color);
}

.btn-secondary:hover:not(:disabled) {
  background: var(--bg-secondary);
}

.btn-outline {
  background: white;
  color: #6366f1;
  border: 2px solid #6366f1;
}

.btn-outline:hover:not(:disabled) {
  background: #6366f1;
  color: white;
}

.btn-success {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
}

.btn-success:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.8rem;
}

/* Utilities */
.text-success {
  color: #059669;
}

.text-error {
  color: #dc2626;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 768px) {
  .journal-post-container {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    align-items: stretch;
  }
  
  .title-section {
    align-items: flex-start;
  }
  
  .header-actions {
    justify-content: stretch;
  }
  
  .filters-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }
  
  .date-range {
    flex-direction: column;
    align-items: stretch;
  }
  
  .summary-stats {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .bulk-actions {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
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
  
  .pagination-section {
    flex-direction: column;
    gap: 1rem;
  }
  
  .pagination-controls {
    flex-wrap: wrap;
    justify-content: center;
  }
  
  .summary-grid {
    grid-template-columns: 1fr;
  }
  
  .quick-filters {
    flex-direction: column;
  }
}
</style>