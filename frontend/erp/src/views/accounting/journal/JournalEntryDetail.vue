<!-- src/views/accounting/JournalEntryDetail.vue -->
<template>
  <div class="journal-entry-detail-container">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <router-link to="/accounting/journal-entries" class="back-button">
            <i class="fas fa-arrow-left"></i>
          </router-link>
          <div>
            <h1 class="page-title">
              <i class="fas fa-book"></i>
              Journal Entry Details
            </h1>
            <p class="page-subtitle" v-if="journalEntry">
              {{ journalEntry.journal_number }} - {{ formatDate(journalEntry.entry_date) }}
            </p>
          </div>
        </div>
        <div class="header-actions" v-if="journalEntry">
          <button @click="printEntry" class="btn btn-secondary">
            <i class="fas fa-print"></i>
            Print
          </button>
          <button @click="duplicateEntry" class="btn btn-outline">
            <i class="fas fa-copy"></i>
            Duplicate
          </button>
          <router-link 
            v-if="journalEntry.status === 'Draft'"
            :to="`/accounting/journal-entries/${journalEntry.journal_id}/edit`" 
            class="btn btn-primary"
          >
            <i class="fas fa-edit"></i>
            Edit
          </router-link>
          <button
            v-if="journalEntry.status === 'Draft'"
            @click="showPostModal = true"
            class="btn btn-success"
          >
            <i class="fas fa-check"></i>
            Post Entry
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="loading-spinner"></div>
      <p>Loading journal entry details...</p>
    </div>

    <!-- Entry Details -->
    <div v-else-if="journalEntry" class="entry-content">
      <!-- Status Banner -->
      <div class="status-banner" :class="`status-${journalEntry.status.toLowerCase()}`">
        <div class="status-content">
          <div class="status-icon">
            <i :class="getStatusIcon(journalEntry.status)"></i>
          </div>
          <div class="status-info">
            <h3>{{ journalEntry.status }}</h3>
            <p>{{ getStatusDescription(journalEntry.status) }}</p>
          </div>
          <div class="status-actions" v-if="journalEntry.status === 'Draft'">
            <button @click="showPostModal = true" class="btn btn-success">
              <i class="fas fa-check"></i>
              Post Now
            </button>
          </div>
        </div>
      </div>

      <!-- Entry Information -->
      <div class="info-section">
        <div class="section-header">
          <h3><i class="fas fa-info-circle"></i> Entry Information</h3>
          <div class="balance-indicator" :class="{ 'balanced': isBalanced, 'unbalanced': !isBalanced }">
            <i :class="isBalanced ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'"></i>
            <span>{{ isBalanced ? 'Balanced' : 'Unbalanced' }}</span>
          </div>
        </div>
        <div class="section-content">
          <div class="info-grid">
            <div class="info-item">
              <label>Journal Number</label>
              <div class="info-value highlight">{{ journalEntry.journal_number }}</div>
            </div>
            <div class="info-item">
              <label>Entry Date</label>
              <div class="info-value">{{ formatDate(journalEntry.entry_date) }}</div>
            </div>
            <div class="info-item">
              <label>Accounting Period</label>
              <div class="info-value">
                <span class="period-badge">
                  {{ journalEntry.accounting_period?.period_name || 'N/A' }}
                </span>
              </div>
            </div>
            <div class="info-item">
              <label>Status</label>
              <div class="info-value">
                <span class="status-badge" :class="`status-${journalEntry.status.toLowerCase()}`">
                  <i :class="getStatusIcon(journalEntry.status)"></i>
                  {{ journalEntry.status }}
                </span>
              </div>
            </div>
            <div class="info-item" v-if="journalEntry.reference_type">
              <label>Reference</label>
              <div class="info-value">
                {{ journalEntry.reference_type }}: {{ journalEntry.reference_id }}
              </div>
            </div>
            <div class="info-item full-width" v-if="journalEntry.description">
              <label>Description</label>
              <div class="info-value description">{{ journalEntry.description }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Journal Lines -->
      <div class="lines-section">
        <div class="section-header">
          <h3><i class="fas fa-list"></i> Journal Lines</h3>
          <div class="totals-summary">
            <div class="total-item">
              <span class="label">Total Debits:</span>
              <span class="amount debit">{{ formatCurrency(totalDebits) }}</span>
            </div>
            <div class="total-item">
              <span class="label">Total Credits:</span>
              <span class="amount credit">{{ formatCurrency(totalCredits) }}</span>
            </div>
            <div class="total-item">
              <span class="label">Difference:</span>
              <span class="amount" :class="{ 'error': !isBalanced }">
                {{ formatCurrency(Math.abs(totalDebits - totalCredits)) }}
              </span>
            </div>
          </div>
        </div>
        <div class="section-content">
          <div class="lines-table-container">
            <table class="lines-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Account</th>
                  <th>Description</th>
                  <th>Debit</th>
                  <th>Credit</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(line, index) in journalEntry.journal_entry_lines" :key="line.line_id" class="line-row">
                  <td class="line-number">{{ index + 1 }}</td>
                  <td class="account-info">
                    <div class="account-code">{{ line.chart_of_account?.account_code }}</div>
                    <div class="account-name">{{ line.chart_of_account?.name }}</div>
                  </td>
                  <td class="line-description">
                    {{ line.description || '-' }}
                  </td>
                  <td class="amount-cell debit">
                    <span v-if="line.debit_amount > 0" class="amount">
                      {{ formatCurrency(line.debit_amount) }}
                    </span>
                    <span v-else class="empty-amount">-</span>
                  </td>
                  <td class="amount-cell credit">
                    <span v-if="line.credit_amount > 0" class="amount">
                      {{ formatCurrency(line.credit_amount) }}
                    </span>
                    <span v-else class="empty-amount">-</span>
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="totals-row">
                  <td colspan="3" class="totals-label">
                    <strong>Totals</strong>
                  </td>
                  <td class="amount-cell debit">
                    <strong class="amount">{{ formatCurrency(totalDebits) }}</strong>
                  </td>
                  <td class="amount-cell credit">
                    <strong class="amount">{{ formatCurrency(totalCredits) }}</strong>
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>

      <!-- Activity Timeline -->
      <div class="activity-section" v-if="activities.length > 0">
        <div class="section-header">
          <h3><i class="fas fa-history"></i> Activity Timeline</h3>
        </div>
        <div class="section-content">
          <div class="timeline">
            <div v-for="activity in activities" :key="activity.id" class="timeline-item">
              <div class="timeline-marker" :class="activity.type">
                <i :class="activity.icon"></i>
              </div>
              <div class="timeline-content">
                <div class="activity-header">
                  <h4>{{ activity.title }}</h4>
                  <span class="activity-time">{{ formatDateTime(activity.timestamp) }}</span>
                </div>
                <p>{{ activity.description }}</p>
                <div v-if="activity.user" class="activity-user">
                  by {{ activity.user }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Related Entries -->
      <div class="related-section" v-if="relatedEntries.length > 0">
        <div class="section-header">
          <h3><i class="fas fa-link"></i> Related Journal Entries</h3>
        </div>
        <div class="section-content">
          <div class="related-grid">
            <router-link 
              v-for="entry in relatedEntries" 
              :key="entry.journal_id"
              :to="`/accounting/journal-entries/${entry.journal_id}`"
              class="related-card"
            >
              <div class="related-header">
                <span class="related-number">{{ entry.journal_number }}</span>
                <span class="related-date">{{ formatDate(entry.entry_date) }}</span>
              </div>
              <div class="related-description">{{ entry.description }}</div>
              <div class="related-amount">{{ formatCurrency(calculateTotalAmount(entry.journal_entry_lines)) }}</div>
            </router-link>
          </div>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else class="error-state">
      <div class="error-icon">
        <i class="fas fa-exclamation-triangle"></i>
      </div>
      <h3>Journal Entry Not Found</h3>
      <p>The requested journal entry could not be found or you don't have permission to view it.</p>
      <router-link to="/accounting/journal-entries" class="btn btn-primary">
        <i class="fas fa-arrow-left"></i>
        Back to Journal Entries
      </router-link>
    </div>

    <!-- Post Confirmation Modal -->
    <div v-if="showPostModal" class="modal-overlay" @click="showPostModal = false">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>Post Journal Entry</h3>
          <button @click="showPostModal = false" class="btn-close">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="post-summary">
            <h4>Entry Summary</h4>
            <div class="summary-grid">
              <div class="summary-item">
                <label>Journal Number:</label>
                <span>{{ journalEntry?.journal_number }}</span>
              </div>
              <div class="summary-item">
                <label>Total Amount:</label>
                <span>{{ formatCurrency(totalDebits) }}</span>
              </div>
              <div class="summary-item">
                <label>Number of Lines:</label>
                <span>{{ journalEntry?.journal_entry_lines?.length || 0 }}</span>
              </div>
              <div class="summary-item">
                <label>Balance Status:</label>
                <span :class="{ 'text-success': isBalanced, 'text-error': !isBalanced }">
                  {{ isBalanced ? 'Balanced' : 'Unbalanced' }}
                </span>
              </div>
            </div>
          </div>
          
          <div v-if="!isBalanced" class="warning-box">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
              <h4>Warning: Entry Not Balanced</h4>
              <p>This journal entry is not balanced. Posted entries should have equal debits and credits.</p>
            </div>
          </div>
          
          <p>Are you sure you want to post this journal entry? Once posted, it cannot be edited.</p>
        </div>
        <div class="modal-footer">
          <button @click="showPostModal = false" class="btn btn-secondary">Cancel</button>
          <button @click="postEntry" class="btn btn-success" :disabled="posting">
            <i class="fas fa-check" :class="{ 'fa-spin': posting }"></i>
            {{ posting ? 'Posting...' : 'Post Entry' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'JournalEntryDetail',
  props: {
    id: {
      type: [String, Number],
      required: true
    }
  },
  data() {
    return {
      loading: false,
      posting: false,
      showPostModal: false,
      journalEntry: null,
      activities: [],
      relatedEntries: []
    }
  },
  computed: {
    totalDebits() {
      if (!this.journalEntry?.journal_entry_lines) return 0
      return this.journalEntry.journal_entry_lines.reduce((sum, line) => 
        sum + (parseFloat(line.debit_amount) || 0), 0
      )
    },
    totalCredits() {
      if (!this.journalEntry?.journal_entry_lines) return 0
      return this.journalEntry.journal_entry_lines.reduce((sum, line) => 
        sum + (parseFloat(line.credit_amount) || 0), 0
      )
    },
    isBalanced() {
      const difference = Math.abs(this.totalDebits - this.totalCredits)
      return difference < 0.01 && this.totalDebits > 0
    }
  },
  async mounted() {
    await this.loadJournalEntry()
    this.generateMockActivities()
    this.loadRelatedEntries()
  },
  methods: {
    async loadJournalEntry() {
      this.loading = true
      try {
        const response = await axios.get(`/accounting/journal-entries/${this.id}`)
        this.journalEntry = response.data.data
      } catch (error) {
        this.$toast.error('Failed to load journal entry')
        console.error('Error loading journal entry:', error)
      } finally {
        this.loading = false
      }
    },

    async loadRelatedEntries() {
      try {
        // Load related entries based on same period or reference
        const params = {}
        if (this.journalEntry?.period_id) {
          params.period_id = this.journalEntry.period_id
        }
        if (this.journalEntry?.reference_type && this.journalEntry?.reference_id) {
          params.reference_type = this.journalEntry.reference_type
          params.reference_id = this.journalEntry.reference_id
        }
        
        if (Object.keys(params).length > 0) {
          const response = await axios.get('/accounting/journal-entries', { 
            params: { ...params, per_page: 5 } 
          })
          this.relatedEntries = response.data.data.filter(entry => 
            entry.journal_id !== this.journalEntry.journal_id
          )
        }
      } catch (error) {
        console.error('Error loading related entries:', error)
      }
    },

    generateMockActivities() {
      if (!this.journalEntry) return
      
      this.activities = [
        {
          id: 1,
          type: 'created',
          icon: 'fas fa-plus',
          title: 'Entry Created',
          description: `Journal entry ${this.journalEntry.journal_number} was created`,
          timestamp: this.journalEntry.created_at || this.journalEntry.entry_date,
          user: 'System User'
        }
      ]
      
      if (this.journalEntry.status === 'Posted') {
        this.activities.unshift({
          id: 2,
          type: 'posted',
          icon: 'fas fa-check',
          title: 'Entry Posted',
          description: 'Journal entry was posted and is now final',
          timestamp: this.journalEntry.updated_at || this.journalEntry.entry_date,
          user: 'System User'
        })
      }
    },

    async postEntry() {
      this.posting = true
      try {
        await axios.post(`/accounting/journal-entries/${this.journalEntry.journal_id}/post`)
        this.$toast.success('Journal entry posted successfully')
        this.showPostModal = false
        await this.loadJournalEntry()
        this.generateMockActivities()
      } catch (error) {
        this.$toast.error('Failed to post journal entry')
        console.error('Error posting entry:', error)
      } finally {
        this.posting = false
      }
    },

    async duplicateEntry() {
      try {
        const duplicateData = {
          journal_number: `${this.journalEntry.journal_number}-COPY`,
          entry_date: new Date().toISOString().split('T')[0],
          reference_type: this.journalEntry.reference_type,
          reference_id: this.journalEntry.reference_id,
          description: `Copy of ${this.journalEntry.description}`,
          period_id: this.journalEntry.period_id,
          status: 'Draft',
          lines: this.journalEntry.journal_entry_lines.map(line => ({
            account_id: line.account_id,
            debit_amount: line.debit_amount,
            credit_amount: line.credit_amount,
            description: line.description
          }))
        }
        
        const response = await axios.post('/accounting/journal-entries', duplicateData)
        this.$toast.success('Journal entry duplicated successfully')
        this.$router.push(`/accounting/journal-entries/${response.data.data.journal_id}/edit`)
      } catch (error) {
        this.$toast.error('Failed to duplicate journal entry')
        console.error('Error duplicating entry:', error)
      }
    },

    printEntry() {
      window.print()
    },

    getStatusIcon(status) {
      switch (status?.toLowerCase()) {
        case 'draft': return 'fas fa-edit'
        case 'posted': return 'fas fa-check-circle'
        case 'cancelled': return 'fas fa-times-circle'
        default: return 'fas fa-question-circle'
      }
    },

    getStatusDescription(status) {
      switch (status?.toLowerCase()) {
        case 'draft': return 'This entry is in draft mode and can be edited'
        case 'posted': return 'This entry has been posted and is final'
        case 'cancelled': return 'This entry has been cancelled'
        default: return 'Unknown status'
      }
    },

    calculateTotalAmount(lines) {
      if (!lines || !Array.isArray(lines)) return 0
      return lines.reduce((sum, line) => sum + (parseFloat(line.debit_amount) || 0), 0)
    },

    formatDate(date) {
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      })
    },

    formatDateTime(date) {
      return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      })
    },

    formatCurrency(amount) {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount || 0)
    }
  }
}
</script>

<style scoped>
.journal-entry-detail-container {
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
  color: #6366f1;
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

/* Status Banner */
.status-banner {
  border-radius: 16px;
  padding: 1.5rem;
  margin-bottom: 2rem;
  border: 2px solid;
}

.status-banner.status-draft {
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(217, 119, 6, 0.1) 100%);
  border-color: rgba(245, 158, 11, 0.3);
}

.status-banner.status-posted {
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
  border-color: rgba(16, 185, 129, 0.3);
}

.status-banner.status-cancelled {
  background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.1) 100%);
  border-color: rgba(239, 68, 68, 0.3);
}

.status-content {
  display: flex;
  align-items: center;
  gap: 1.5rem;
}

.status-icon {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
}

.status-draft .status-icon {
  background: rgba(245, 158, 11, 0.2);
  color: #d97706;
}

.status-posted .status-icon {
  background: rgba(16, 185, 129, 0.2);
  color: #059669;
}

.status-cancelled .status-icon {
  background: rgba(239, 68, 68, 0.2);
  color: #dc2626;
}

.status-info {
  flex: 1;
}

.status-info h3 {
  margin: 0 0 0.5rem 0;
  font-size: 1.25rem;
  color: var(--text-primary);
}

.status-info p {
  margin: 0;
  color: var(--text-secondary);
}

/* Sections */
.info-section, .lines-section, .activity-section, .related-section {
  background: white;
  border-radius: 16px;
  margin-bottom: 2rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
  overflow: hidden;
}

.section-header {
  background: var(--bg-tertiary);
  padding: 1.5rem;
  border-bottom: 1px solid var(--border-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.section-header h3 {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.section-header h3 i {
  color: #6366f1;
}

.balance-indicator {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border-radius: 12px;
  font-size: 0.875rem;
  font-weight: 600;
}

.balance-indicator.balanced {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
}

.balance-indicator.unbalanced {
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
}

.totals-summary {
  display: flex;
  gap: 2rem;
  align-items: center;
}

.total-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.total-item .label {
  font-size: 0.875rem;
  color: var(--text-secondary);
}

.total-item .amount {
  font-weight: 600;
  font-family: 'Courier New', monospace;
}

.total-item .amount.debit {
  color: #dc2626;
}

.total-item .amount.credit {
  color: #059669;
}

.total-item .amount.error {
  color: #dc2626;
}

.section-content {
  padding: 1.5rem;
}

/* Info Grid */
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}

.info-item.full-width {
  grid-column: 1 / -1;
}

.info-item label {
  display: block;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
  text-transform: uppercase;
  letter-spacing: 0.025em;
}

.info-value {
  color: var(--text-primary);
  font-size: 1rem;
}

.info-value.highlight {
  font-weight: 700;
  font-size: 1.1rem;
  color: #6366f1;
}

.info-value.description {
  line-height: 1.6;
  padding: 0.75rem;
  background: var(--bg-tertiary);
  border-radius: 8px;
  border-left: 4px solid #6366f1;
}

.period-badge {
  background: var(--bg-tertiary);
  color: var(--text-primary);
  padding: 0.375rem 0.75rem;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 500;
  display: inline-block;
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

.status-badge.status-draft {
  background: rgba(245, 158, 11, 0.1);
  color: #d97706;
}

.status-badge.status-posted {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
}

.status-badge.status-cancelled {
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
}

/* Lines Table */
.lines-table-container {
  overflow-x: auto;
  border: 2px solid var(--border-color);
  border-radius: 12px;
}

.lines-table {
  width: 100%;
  border-collapse: collapse;
}

.lines-table th {
  background: var(--bg-tertiary);
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: var(--text-primary);
  border-bottom: 2px solid var(--border-color);
  white-space: nowrap;
}

.lines-table td {
  padding: 1rem;
  border-bottom: 1px solid var(--border-color);
  vertical-align: middle;
}

.line-row:hover {
  background: var(--bg-tertiary);
}

.line-number {
  text-align: center;
  font-weight: 600;
  color: var(--text-secondary);
  width: 50px;
}

.account-info {
  min-width: 250px;
}

.account-code {
  font-weight: 600;
  color: #6366f1;
  font-size: 0.875rem;
}

.account-name {
  color: var(--text-primary);
  font-size: 0.9rem;
  margin-top: 0.25rem;
}

.line-description {
  color: var(--text-secondary);
  font-style: italic;
}

.amount-cell {
  text-align: right;
  font-family: 'Courier New', monospace;
  font-weight: 600;
  min-width: 120px;
}

.amount-cell.debit .amount {
  color: #dc2626;
}

.amount-cell.credit .amount {
  color: #059669;
}

.empty-amount {
  color: var(--text-muted);
}

.totals-row {
  background: var(--bg-tertiary);
  font-weight: 700;
}

.totals-label {
  text-align: right;
  color: var(--text-primary);
}

/* Timeline */
.timeline {
  position: relative;
}

.timeline::before {
  content: '';
  position: absolute;
  left: 30px;
  top: 0;
  bottom: 0;
  width: 2px;
  background: var(--border-color);
}

.timeline-item {
  position: relative;
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.timeline-item:last-child {
  margin-bottom: 0;
}

.timeline-marker {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  position: relative;
  z-index: 1;
  border: 3px solid white;
}

.timeline-marker.created {
  background: rgba(99, 102, 241, 0.1);
  color: #6366f1;
}

.timeline-marker.posted {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
}

.timeline-content {
  flex: 1;
  padding-top: 0.5rem;
}

.activity-header {
  display: flex;
  justify-content: between;
  align-items: flex-start;
  margin-bottom: 0.5rem;
}

.activity-header h4 {
  margin: 0;
  color: var(--text-primary);
  font-size: 1rem;
}

.activity-time {
  color: var(--text-muted);
  font-size: 0.8rem;
  margin-left: auto;
}

.timeline-content p {
  margin: 0 0 0.5rem 0;
  color: var(--text-secondary);
}

.activity-user {
  color: var(--text-muted);
  font-size: 0.8rem;
  font-style: italic;
}

/* Related Entries */
.related-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1rem;
}

.related-card {
  background: var(--bg-tertiary);
  border: 2px solid var(--border-color);
  border-radius: 12px;
  padding: 1rem;
  text-decoration: none;
  transition: all 0.2s;
}

.related-card:hover {
  border-color: #6366f1;
  background: rgba(99, 102, 241, 0.05);
  transform: translateY(-2px);
}

.related-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.related-number {
  font-weight: 600;
  color: #6366f1;
}

.related-date {
  color: var(--text-muted);
  font-size: 0.8rem;
}

.related-description {
  color: var(--text-primary);
  margin-bottom: 0.5rem;
  font-size: 0.9rem;
}

.related-amount {
  color: var(--text-secondary);
  font-weight: 600;
  font-family: 'Courier New', monospace;
}

/* States */
.loading-state, .error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  text-align: center;
}

.loading-spinner {
  width: 60px;
  height: 60px;
  border: 4px solid rgba(99, 102, 241, 0.2);
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin 1s ease-in-out infinite;
  margin-bottom: 1rem;
}

.error-icon {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: rgba(239, 68, 68, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1.5rem;
}

.error-icon i {
  font-size: 2rem;
  color: #dc2626;
}

.error-state h3 {
  color: var(--text-primary);
  margin: 0 0 0.5rem 0;
  font-size: 1.25rem;
}

.error-state p {
  color: var(--text-secondary);
  margin: 0 0 1.5rem 0;
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
  max-width: 600px;
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

.warning-box {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  background: rgba(245, 158, 11, 0.1);
  border: 2px solid rgba(245, 158, 11, 0.3);
  border-radius: 12px;
  margin: 1rem 0;
}

.warning-box i {
  color: #d97706;
  font-size: 1.25rem;
  margin-top: 0.25rem;
}

.warning-box h4 {
  margin: 0 0 0.5rem 0;
  color: #92400e;
}

.warning-box p {
  margin: 0;
  color: #a16207;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1.5rem;
  border-top: 1px solid var(--border-color);
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
  .journal-entry-detail-container {
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
  
  .status-content {
    flex-direction: column;
    text-align: center;
  }
  
  .totals-summary {
    flex-direction: column;
    gap: 0.75rem;
    align-items: flex-start;
  }
  
  .info-grid {
    grid-template-columns: 1fr;
  }
  
  .lines-table-container {
    font-size: 0.8rem;
  }
  
  .related-grid {
    grid-template-columns: 1fr;
  }
  
  .timeline::before {
    left: 20px;
  }
  
  .timeline-marker {
    width: 40px;
    height: 40px;
    font-size: 1rem;
  }
  
  .summary-grid {
    grid-template-columns: 1fr;
  }
}

/* Print Styles */
@media print {
  .page-header .header-actions,
  .status-actions,
  .back-button {
    display: none !important;
  }
  
  .journal-entry-detail-container {
    padding: 0;
    background: white;
  }
  
  .info-section, .lines-section {
    break-inside: avoid;
    box-shadow: none;
    border: 1px solid #ccc;
  }
}
</style>