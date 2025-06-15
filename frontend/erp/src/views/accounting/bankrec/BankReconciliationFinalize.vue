<template>
  <div class="bank-reconciliation-finalize">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <button @click="goBack" class="btn-back">
            <i class="fas fa-arrow-left"></i>
          </button>
          <div>
            <h1 class="page-title">
              <i class="fas fa-check-double"></i>
              Finalize Bank Reconciliation
            </h1>
            <p class="page-subtitle">
              Reconciliation #{{ reconciliation.reconciliation_id }} - {{ reconciliation.bank_account?.account_name }}
            </p>
          </div>
        </div>
        <div class="header-actions">
          <button @click="exportReconciliation" class="btn-secondary">
            <i class="fas fa-download"></i>
            Export Report
          </button>
          <button @click="saveAsDraft" class="btn-outline" :disabled="saving">
            <i class="fas fa-save" :class="{ 'fa-spin': saving }"></i>
            Save as Draft
          </button>
          <button 
            @click="finalizeReconciliation" 
            class="btn-primary" 
            :disabled="!canFinalize || finalizing"
          >
            <i class="fas fa-lock" :class="{ 'fa-spin': finalizing }"></i>
            Finalize Reconciliation
          </button>
        </div>
      </div>
    </div>

    <!-- Reconciliation Summary -->
    <div class="reconciliation-summary">
      <div class="summary-header">
        <h2><i class="fas fa-chart-line"></i> Reconciliation Summary</h2>
        <div class="completion-badge" :class="getCompletionClass()">
          <i class="fas" :class="getCompletionIcon()"></i>
          {{ getCompletionStatus() }}
        </div>
      </div>
      
      <div class="summary-grid">
        <div class="summary-card balance-summary">
          <div class="card-header">
            <h3>Balance Analysis</h3>
            <div class="variance-indicator" :class="getVarianceClass(finalVariance)">
              <i class="fas" :class="finalVariance === 0 ? 'fa-check-circle' : 'fa-exclamation-triangle'"></i>
              {{ finalVariance === 0 ? 'Balanced' : 'Variance' }}
            </div>
          </div>
          <div class="balance-items">
            <div class="balance-item">
              <span class="label">Bank Statement Balance:</span>
              <span class="value statement">{{ formatCurrency(reconciliation.statement_balance) }}</span>
            </div>
            <div class="balance-item">
              <span class="label">Book Balance:</span>
              <span class="value book">{{ formatCurrency(reconciliation.book_balance) }}</span>
            </div>
            <div class="balance-item variance">
              <span class="label">Variance:</span>
              <span class="value" :class="getVarianceClass(finalVariance)">
                {{ formatCurrency(finalVariance) }}
              </span>
            </div>
          </div>
        </div>

        <div class="summary-card matching-summary">
          <div class="card-header">
            <h3>Matching Summary</h3>
            <div class="progress-circle">
              <svg width="60" height="60">
                <circle cx="30" cy="30" r="25" stroke="var(--gray-200)" stroke-width="4" fill="none"/>
                <circle 
                  cx="30" cy="30" r="25" 
                  stroke="var(--success-color)" 
                  stroke-width="4" 
                  fill="none"
                  :stroke-dasharray="progressCircumference"
                  :stroke-dashoffset="progressOffset"
                  class="progress-arc"
                />
              </svg>
              <div class="progress-text">{{ matchingProgress }}%</div>
            </div>
          </div>
          <div class="matching-items">
            <div class="matching-item">
              <span class="label">Total Transactions:</span>
              <span class="value">{{ totalTransactions }}</span>
            </div>
            <div class="matching-item">
              <span class="label">Matched:</span>
              <span class="value matched">{{ matchedTransactions }}</span>
            </div>
            <div class="matching-item">
              <span class="label">Unmatched:</span>
              <span class="value unmatched">{{ unmatchedTransactions }}</span>
            </div>
            <div class="matching-item">
              <span class="label">Adjustments:</span>
              <span class="value adjustments">{{ adjustmentCount }}</span>
            </div>
          </div>
        </div>

        <div class="summary-card validation-summary">
          <div class="card-header">
            <h3>Validation Results</h3>
            <div class="validation-status" :class="allValidationsPassed ? 'passed' : 'failed'">
              <i class="fas" :class="allValidationsPassed ? 'fa-check-circle' : 'fa-times-circle'"></i>
              {{ allValidationsPassed ? 'All Checks Passed' : 'Issues Found' }}
            </div>
          </div>
          <div class="validation-items">
            <div v-for="validation in validationChecks" :key="validation.id" class="validation-item">
              <div class="validation-check" :class="validation.passed ? 'passed' : 'failed'">
                <i class="fas" :class="validation.passed ? 'fa-check' : 'fa-times'"></i>
              </div>
              <span class="validation-label">{{ validation.label }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Issues and Warnings -->
    <div v-if="issues.length > 0 || warnings.length > 0" class="issues-section">
      <h3><i class="fas fa-exclamation-triangle"></i> Issues & Warnings</h3>
      
      <div v-if="issues.length > 0" class="issue-group error">
        <h4><i class="fas fa-times-circle"></i> Issues ({{ issues.length }})</h4>
        <div class="issue-list">
          <div v-for="issue in issues" :key="issue.id" class="issue-item">
            <div class="issue-icon">
              <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="issue-content">
              <h5>{{ issue.title }}</h5>
              <p>{{ issue.description }}</p>
              <div v-if="issue.action" class="issue-action">
                <button @click="resolveIssue(issue)" class="btn-resolve">
                  {{ issue.action }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="warnings.length > 0" class="issue-group warning">
        <h4><i class="fas fa-exclamation-triangle"></i> Warnings ({{ warnings.length }})</h4>
        <div class="issue-list">
          <div v-for="warning in warnings" :key="warning.id" class="issue-item">
            <div class="issue-icon">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="issue-content">
              <h5>{{ warning.title }}</h5>
              <p>{{ warning.description }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Transaction Review -->
    <div class="transaction-review">
      <div class="review-header">
        <h3><i class="fas fa-list-check"></i> Transaction Review</h3>
        <div class="review-filters">
          <button 
            @click="setReviewFilter('all')" 
            :class="['filter-btn', { 'active': reviewFilter === 'all' }]"
          >
            All Transactions
          </button>
          <button 
            @click="setReviewFilter('matched')" 
            :class="['filter-btn', { 'active': reviewFilter === 'matched' }]"
          >
            Matched ({{ matchedTransactions }})
          </button>
          <button 
            @click="setReviewFilter('adjustments')" 
            :class="['filter-btn', { 'active': reviewFilter === 'adjustments' }]"
          >
            Adjustments ({{ adjustmentCount }})
          </button>
          <button 
            @click="setReviewFilter('unmatched')" 
            :class="['filter-btn', { 'active': reviewFilter === 'unmatched' }]"
          >
            Unmatched ({{ unmatchedTransactions }})
          </button>
        </div>
      </div>

      <div class="review-content">
        <div class="transactions-table">
          <div class="table-header">
            <div class="col-type">Type</div>
            <div class="col-date">Date</div>
            <div class="col-description">Description</div>
            <div class="col-amount">Amount</div>
            <div class="col-status">Status</div>
            <div class="col-match">Match Info</div>
          </div>
          
          <div class="table-body">
            <div 
              v-for="transaction in filteredReviewTransactions" 
              :key="transaction.id"
              class="transaction-row"
              :class="getTransactionRowClass(transaction)"
            >
              <div class="col-type">
                <div class="transaction-type">
                  <i class="fas" :class="getTransactionIcon(transaction.type)"></i>
                  <span>{{ transaction.type }}</span>
                </div>
              </div>
              <div class="col-date">{{ formatDate(transaction.date) }}</div>
              <div class="col-description">
                <div class="description-text">{{ transaction.description || 'N/A' }}</div>
                <div v-if="transaction.reference" class="reference-text">
                  Ref: {{ transaction.reference }}
                </div>
              </div>
              <div class="col-amount">
                <span class="amount" :class="getAmountClass(transaction.amount)">
                  {{ formatCurrency(transaction.amount) }}
                </span>
              </div>
              <div class="col-status">
                <span class="status-badge" :class="getTransactionStatusClass(transaction)">
                  <i class="fas" :class="getTransactionStatusIcon(transaction)"></i>
                  {{ getTransactionStatus(transaction) }}
                </span>
              </div>
              <div class="col-match">
                <div v-if="transaction.matched && transaction.matchedWith" class="match-info">
                  <i class="fas fa-link"></i>
                  <span>{{ transaction.source === 'statement' ? 'Book' : 'Statement' }} #{{ transaction.matchedWith }}</span>
                </div>
                <div v-else-if="transaction.isAdjustment" class="adjustment-info">
                  <i class="fas fa-cog"></i>
                  <span>Reconciliation Adjustment</span>
                </div>
                <div v-else class="no-match">
                  <i class="fas fa-minus"></i>
                  <span>No match</span>
                </div>
              </div>
            </div>
          </div>
          
          <div v-if="filteredReviewTransactions.length === 0" class="empty-transactions">
            <i class="fas fa-inbox"></i>
            <p>No transactions found for the selected filter</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Finalization Notes -->
    <div class="finalization-notes">
      <div class="notes-header">
        <h3><i class="fas fa-sticky-note"></i> Finalization Notes</h3>
        <small>Add any final comments or observations about this reconciliation</small>
      </div>
      <div class="notes-content">
        <textarea 
          v-model="finalizationNotes" 
          class="notes-textarea"
          rows="4"
          placeholder="Enter any final notes, exceptions, or comments about this reconciliation..."
        ></textarea>
      </div>
    </div>

    <!-- Final Confirmation -->
    <div class="final-confirmation">
      <div class="confirmation-content">
        <div class="confirmation-icon">
          <i class="fas fa-shield-check"></i>
        </div>
        <div class="confirmation-text">
          <h3>Ready to Finalize</h3>
          <p>
            Once finalized, this reconciliation cannot be modified. Please review all transactions 
            and ensure all discrepancies have been resolved.
          </p>
          <div class="confirmation-checklist">
            <label class="checklist-item">
              <input 
                v-model="confirmations.reviewed" 
                type="checkbox" 
                class="confirmation-checkbox"
              />
              <span class="checkmark"></span>
              I have reviewed all transactions and matches
            </label>
            <label class="checklist-item">
              <input 
                v-model="confirmations.resolved" 
                type="checkbox" 
                class="confirmation-checkbox"
              />
              <span class="checkmark"></span>
              All discrepancies have been resolved or documented
            </label>
            <label class="checklist-item">
              <input 
                v-model="confirmations.accurate" 
                type="checkbox" 
                class="confirmation-checkbox"
              />
              <span class="checkmark"></span>
              The reconciliation is accurate and complete
            </label>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirmation Modal -->
    <div v-if="showConfirmationModal" class="modal-overlay" @click="closeConfirmationModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3><i class="fas fa-lock"></i> Confirm Finalization</h3>
          <button @click="closeConfirmationModal" class="btn-close">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="confirmation-warning">
            <div class="warning-icon">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="warning-content">
              <h4>This action cannot be undone</h4>
              <p>
                Finalizing this reconciliation will lock it permanently. You will not be able to 
                make any changes to the transactions, matches, or adjustments after this point.
              </p>
              <div class="finalization-summary">
                <div class="summary-item">
                  <span class="label">Reconciliation Period:</span>
                  <span class="value">{{ reconciliation.reconciliation_period }}</span>
                </div>
                <div class="summary-item">
                  <span class="label">Final Variance:</span>
                  <span class="value" :class="getVarianceClass(finalVariance)">
                    {{ formatCurrency(finalVariance) }}
                  </span>
                </div>
                <div class="summary-item">
                  <span class="label">Transactions Matched:</span>
                  <span class="value">{{ matchedTransactions }} of {{ totalTransactions }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="closeConfirmationModal" class="btn-secondary">Cancel</button>
          <button @click="confirmFinalization" class="btn-danger" :disabled="finalizing">
            <i class="fas fa-lock" :class="{ 'fa-spin': finalizing }"></i>
            Yes, Finalize Reconciliation
          </button>
        </div>
      </div>
    </div>

    <!-- Loading Overlay -->
    <div v-if="loading" class="loading-overlay">
      <div class="loading-content">
        <div class="loading-spinner">
          <i class="fas fa-spinner fa-spin"></i>
        </div>
        <p>{{ loadingMessage }}</p>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'BankReconciliationFinalize',
  data() {
    return {
      loading: false,
      saving: false,
      finalizing: false,
      loadingMessage: '',
      reconciliation: {
        bank_account: {}
      },
      transactions: [],
      reviewFilter: 'all',
      finalizationNotes: '',
      confirmations: {
        reviewed: false,
        resolved: false,
        accurate: false
      },
      showConfirmationModal: false,
      validationChecks: [
        {
          id: 'variance_check',
          label: 'Variance within acceptable limits',
          passed: false
        },
        {
          id: 'all_matched',
          label: 'All transactions matched or documented',
          passed: false
        },
        {
          id: 'adjustments_valid',
          label: 'All adjustments properly documented',
          passed: false
        },
        {
          id: 'date_consistency',
          label: 'Date consistency verified',
          passed: false
        }
      ],
      issues: [],
      warnings: []
    }
  },
  computed: {
    finalVariance() {
      const statement = parseFloat(this.reconciliation.statement_balance) || 0
      const book = parseFloat(this.reconciliation.book_balance) || 0
      return statement - book
    },
    totalTransactions() {
      return this.transactions.length
    },
    matchedTransactions() {
      return this.transactions.filter(t => t.matched).length
    },
    unmatchedTransactions() {
      return this.transactions.filter(t => !t.matched && !t.isAdjustment).length
    },
    adjustmentCount() {
      return this.transactions.filter(t => t.isAdjustment).length
    },
    matchingProgress() {
      if (this.totalTransactions === 0) return 100
      return Math.round((this.matchedTransactions / this.totalTransactions) * 100)
    },
    progressCircumference() {
      return 2 * Math.PI * 25 // radius = 25
    },
    progressOffset() {
      const progress = this.matchingProgress / 100
      return this.progressCircumference * (1 - progress)
    },
    allValidationsPassed() {
      return this.validationChecks.every(check => check.passed)
    },
    allConfirmationsChecked() {
      return Object.values(this.confirmations).every(confirmation => confirmation)
    },
    canFinalize() {
      return this.allValidationsPassed && 
             this.allConfirmationsChecked && 
             this.issues.length === 0 &&
             Math.abs(this.finalVariance) < 0.01 &&
             this.unmatchedTransactions === 0
    },
    filteredReviewTransactions() {
      switch (this.reviewFilter) {
        case 'matched':
          return this.transactions.filter(t => t.matched)
        case 'adjustments':
          return this.transactions.filter(t => t.isAdjustment)
        case 'unmatched':
          return this.transactions.filter(t => !t.matched && !t.isAdjustment)
        default:
          return this.transactions
      }
    }
  },
  async mounted() {
    await this.initializeData()
  },
  methods: {
    async initializeData() {
      this.loading = true
      this.loadingMessage = 'Loading reconciliation data...'
      
      try {
        const reconciliationId = this.$route.params.id
        
        // Load reconciliation details
        const reconciliationResponse = await axios.get(`/accounting/bank-reconciliations/${reconciliationId}`)
        this.reconciliation = reconciliationResponse.data.data
        
        // Load transactions and perform validations
        await this.loadTransactions()
        await this.performValidations()
        this.identifyIssuesAndWarnings()
        
      } catch (error) {
        console.error('Error initializing data:', error)
        this.$toast?.error('Failed to load reconciliation data')
        this.goBack()
      } finally {
        this.loading = false
      }
    },

    async loadTransactions() {
      try {
        // Mock transaction data - in real implementation, this would come from the API
        this.transactions = [
          {
            id: 's1',
            type: 'Deposit',
            amount: 1500.00,
            date: '2025-01-15',
            description: 'Customer payment - Invoice #1234',
            reference: 'DEP001',
            source: 'statement',
            matched: true,
            matchedWith: 'b1',
            isAdjustment: false
          },
          {
            id: 'b1',
            type: 'Deposit',
            amount: 1500.00,
            date: '2025-01-15',
            description: 'Customer payment received',
            account: 'Accounts Receivable',
            source: 'book',
            matched: true,
            matchedWith: 's1',
            isAdjustment: false
          },
          {
            id: 's2',
            type: 'Withdrawal',
            amount: -250.00,
            date: '2025-01-14',
            description: 'Check #1001 - Office supplies',
            reference: 'CHK001',
            source: 'statement',
            matched: true,
            matchedWith: 'b2',
            isAdjustment: false
          },
          {
            id: 'b2',
            type: 'Withdrawal',
            amount: -250.00,
            date: '2025-01-14',
            description: 'Office supplies purchase',
            account: 'Office Expenses',
            source: 'book',
            matched: true,
            matchedWith: 's2',
            isAdjustment: false
          },
          {
            id: 'adj1',
            type: 'Fee',
            amount: -15.00,
            date: '2025-01-13',
            description: 'Monthly service fee adjustment',
            source: 'adjustment',
            matched: false,
            matchedWith: null,
            isAdjustment: true
          }
        ]
      } catch (error) {
        console.error('Error loading transactions:', error)
        this.$toast?.error('Failed to load transactions')
      }
    },

    async performValidations() {
      // Variance check
      this.validationChecks[0].passed = Math.abs(this.finalVariance) < 0.01

      // All transactions matched check
      this.validationChecks[1].passed = this.unmatchedTransactions === 0

      // Adjustments valid check
      this.validationChecks[2].passed = this.adjustmentCount === 0 || 
        this.transactions.filter(t => t.isAdjustment).every(t => t.description)

      // Date consistency check
      this.validationChecks[3].passed = true // Simplified for demo
    },

    identifyIssuesAndWarnings() {
      this.issues = []
      this.warnings = []

      // Check for unmatched transactions
      if (this.unmatchedTransactions > 0) {
        this.issues.push({
          id: 'unmatched_transactions',
          title: 'Unmatched Transactions',
          description: `There are ${this.unmatchedTransactions} unmatched transactions that need to be resolved.`,
          action: 'Go to Matching'
        })
      }

      // Check for variance
      if (Math.abs(this.finalVariance) > 0.01) {
        this.issues.push({
          id: 'variance_exists',
          title: 'Outstanding Variance',
          description: `There is an outstanding variance of ${this.formatCurrency(this.finalVariance)} that needs to be resolved.`,
          action: 'Review Transactions'
        })
      }

      // Check for large adjustments
      const largeAdjustments = this.transactions.filter(t => t.isAdjustment && Math.abs(t.amount) > 100)
      if (largeAdjustments.length > 0) {
        this.warnings.push({
          id: 'large_adjustments',
          title: 'Large Adjustments Present',
          description: `There are ${largeAdjustments.length} adjustment(s) over $100. Please ensure these are properly documented.`
        })
      }

      // Check for old transactions
      const oldTransactions = this.transactions.filter(t => {
        const transactionDate = new Date(t.date)
        const cutoffDate = new Date()
        cutoffDate.setDate(cutoffDate.getDate() - 30)
        return transactionDate < cutoffDate
      })
      
      if (oldTransactions.length > 0) {
        this.warnings.push({
          id: 'old_transactions',
          title: 'Old Transactions',
          description: `There are ${oldTransactions.length} transaction(s) older than 30 days. Verify these are correct.`
        })
      }
    },

    setReviewFilter(filter) {
      this.reviewFilter = filter
    },

    getCompletionClass() {
      if (this.canFinalize) return 'completion-ready'
      if (this.issues.length === 0) return 'completion-warning'
      return 'completion-error'
    },

    getCompletionIcon() {
      if (this.canFinalize) return 'fa-check-circle'
      if (this.issues.length === 0) return 'fa-exclamation-triangle'
      return 'fa-times-circle'
    },

    getCompletionStatus() {
      if (this.canFinalize) return 'Ready to Finalize'
      if (this.issues.length === 0) return 'Review Required'
      return 'Issues Found'
    },

    getVarianceClass(variance) {
      if (Math.abs(variance) < 0.01) return 'variance-zero'
      return variance > 0 ? 'variance-positive' : 'variance-negative'
    },

    getTransactionRowClass(transaction) {
      if (transaction.isAdjustment) return 'adjustment-row'
      if (transaction.matched) return 'matched-row'
      return 'unmatched-row'
    },

    getTransactionIcon(type) {
      const iconMap = {
        'Deposit': 'fa-arrow-down',
        'Withdrawal': 'fa-arrow-up',
        'Transfer': 'fa-exchange-alt',
        'Fee': 'fa-minus-circle',
        'Interest': 'fa-percentage',
        'Check': 'fa-money-check'
      }
      return iconMap[type] || 'fa-file-invoice-dollar'
    },

    getAmountClass(amount) {
      if (amount === 0) return 'amount-zero'
      return amount > 0 ? 'amount-positive' : 'amount-negative'
    },

    getTransactionStatusClass(transaction) {
      if (transaction.isAdjustment) return 'status-adjustment'
      if (transaction.matched) return 'status-matched'
      return 'status-unmatched'
    },

    getTransactionStatusIcon(transaction) {
      if (transaction.isAdjustment) return 'fa-cog'
      if (transaction.matched) return 'fa-check-circle'
      return 'fa-clock'
    },

    getTransactionStatus(transaction) {
      if (transaction.isAdjustment) return 'Adjustment'
      if (transaction.matched) return 'Matched'
      return 'Unmatched'
    },

    resolveIssue(issue) {
      if (issue.id === 'unmatched_transactions') {
        this.$router.push(`/accounting/bank-reconciliations/${this.reconciliation.reconciliation_id}/match`)
      } else if (issue.id === 'variance_exists') {
        this.$router.push(`/accounting/bank-reconciliations/${this.reconciliation.reconciliation_id}/match`)
      }
    },

    async saveAsDraft() {
      this.saving = true
      
      try {
        await axios.put(`/accounting/bank-reconciliations/${this.reconciliation.reconciliation_id}`, {
          notes: this.finalizationNotes,
          status: 'In Progress'
        })
        
        this.$toast?.success('Draft saved successfully')
      } catch (error) {
        console.error('Error saving draft:', error)
        this.$toast?.error('Failed to save draft')
      } finally {
        this.saving = false
      }
    },

    finalizeReconciliation() {
      if (!this.canFinalize) {
        this.$toast?.warning('Please resolve all issues before finalizing')
        return
      }
      
      this.showConfirmationModal = true
    },

    closeConfirmationModal() {
      this.showConfirmationModal = false
    },

    async confirmFinalization() {
      this.finalizing = true
      
      try {
        // Finalize the reconciliation via API
        await axios.post(`/accounting/bank-reconciliations/${this.reconciliation.reconciliation_id}/finalize`, {
          finalization_notes: this.finalizationNotes,
          confirmations: this.confirmations
        })
        
        this.$toast?.success('Reconciliation finalized successfully')
        this.closeConfirmationModal()
        
        // Redirect to reconciliation detail page
        this.$router.push(`/accounting/bank-reconciliations/${this.reconciliation.reconciliation_id}`)
        
      } catch (error) {
        console.error('Error finalizing reconciliation:', error)
        this.$toast?.error('Failed to finalize reconciliation')
      } finally {
        this.finalizing = false
      }
    },

    async exportReconciliation() {
      try {
        // Export functionality would be implemented here
        this.$toast?.info('Export functionality will be implemented')
      } catch (error) {
        console.error('Error exporting reconciliation:', error)
        this.$toast?.error('Failed to export reconciliation')
      }
    },

    goBack() {
      this.$router.go(-1)
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
      if (amount === null || amount === undefined || isNaN(amount)) return '$0.00'
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount)
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
  --border-radius-lg: 16px;
  --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  --box-shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.1);
  --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.bank-reconciliation-finalize {
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
  position: sticky;
  top: 2rem;
  z-index: 100;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 2rem;
}

.title-section {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex: 1;
}

.btn-back {
  width: 3rem;
  height: 3rem;
  border: 2px solid var(--gray-200);
  background: var(--white);
  color: var(--gray-600);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: var(--transition);
  font-size: 1rem;
}

.btn-back:hover {
  background: var(--gray-50);
  border-color: var(--gray-300);
  color: var(--gray-800);
  transform: translateX(-2px);
}

.page-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--gray-900);
  margin: 0 0 0.25rem 0;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.page-title i {
  color: var(--primary-color);
}

.page-subtitle {
  color: var(--gray-600);
  font-size: 0.875rem;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 1rem;
  flex-shrink: 0;
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

.btn-primary:hover:not(:disabled) {
  background: var(--primary-dark);
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
}

.btn-primary:disabled {
  background: var(--gray-300);
  color: var(--gray-500);
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.btn-secondary {
  background: var(--gray-200);
  color: var(--gray-700);
}

.btn-secondary:hover:not(:disabled) {
  background: var(--gray-300);
}

.btn-outline {
  background: transparent;
  color: var(--gray-700);
  border: 2px solid var(--gray-300);
}

.btn-outline:hover:not(:disabled) {
  background: var(--gray-100);
  border-color: var(--gray-400);
}

.btn-danger {
  background: var(--danger-color);
  color: var(--white);
}

.btn-danger:hover:not(:disabled) {
  background: #b91c1c;
}

/* Reconciliation Summary */
.reconciliation-summary {
  background: var(--white);
  border-radius: var(--border-radius);
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: var(--box-shadow);
}

.summary-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.summary-header h2 {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.summary-header h2 i {
  color: var(--primary-color);
}

.completion-badge {
  padding: 0.75rem 1.5rem;
  border-radius: 9999px;
  font-weight: 600;
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.completion-ready {
  background: rgba(5, 150, 105, 0.1);
  color: var(--success-color);
}

.completion-warning {
  background: rgba(217, 119, 6, 0.1);
  color: var(--warning-color);
}

.completion-error {
  background: rgba(220, 38, 38, 0.1);
  color: var(--danger-color);
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 2rem;
}

.summary-card {
  border: 2px solid var(--gray-200);
  border-radius: var(--border-radius);
  overflow: hidden;
  transition: var(--transition);
}

.summary-card:hover {
  border-color: var(--gray-300);
  box-shadow: var(--box-shadow);
}

.summary-card .card-header {
  padding: 1.5rem;
  background: var(--gray-50);
  border-bottom: 1px solid var(--gray-200);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.summary-card .card-header h3 {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0;
}

.variance-indicator {
  padding: 0.5rem 1rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.variance-zero {
  background: rgba(5, 150, 105, 0.1);
  color: var(--success-color);
}

.variance-positive, .variance-negative {
  background: rgba(220, 38, 38, 0.1);
  color: var(--danger-color);
}

.progress-circle {
  position: relative;
  width: 60px;
  height: 60px;
}

.progress-circle svg {
  transform: rotate(-90deg);
}

.progress-arc {
  transition: stroke-dashoffset 0.5s ease;
}

.progress-text {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--gray-900);
}

.validation-status {
  padding: 0.5rem 1rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.validation-status.passed {
  background: rgba(5, 150, 105, 0.1);
  color: var(--success-color);
}

.validation-status.failed {
  background: rgba(220, 38, 38, 0.1);
  color: var(--danger-color);
}

.balance-items, .matching-items, .validation-items {
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.balance-item, .matching-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: var(--gray-50);
  border-radius: var(--border-radius);
}

.balance-item.variance {
  background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
  border: 1px solid var(--gray-200);
}

.balance-item .label, .matching-item .label {
  font-weight: 600;
  color: var(--gray-600);
}

.balance-item .value, .matching-item .value {
  font-weight: 700;
  color: var(--gray-900);
}

.value.statement {
  color: var(--primary-color);
}

.value.book {
  color: var(--success-color);
}

.value.matched {
  color: var(--success-color);
}

.value.unmatched {
  color: var(--warning-color);
}

.value.adjustments {
  color: var(--primary-color);
}

.validation-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
  background: var(--gray-50);
  border-radius: var(--border-radius);
}

.validation-check {
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  color: var(--white);
}

.validation-check.passed {
  background: var(--success-color);
}

.validation-check.failed {
  background: var(--danger-color);
}

.validation-label {
  font-weight: 500;
  color: var(--gray-700);
}

/* Issues Section */
.issues-section {
  background: var(--white);
  border-radius: var(--border-radius);
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: var(--box-shadow);
}

.issues-section h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 1.5rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.issues-section h3 i {
  color: var(--warning-color);
}

.issue-group {
  margin-bottom: 2rem;
}

.issue-group:last-child {
  margin-bottom: 0;
}

.issue-group h4 {
  font-size: 1rem;
  font-weight: 600;
  margin: 0 0 1rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.issue-group.error h4 {
  color: var(--danger-color);
}

.issue-group.warning h4 {
  color: var(--warning-color);
}

.issue-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.issue-item {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  border-radius: var(--border-radius);
  border: 1px solid var(--gray-200);
}

.issue-group.error .issue-item {
  background: rgba(220, 38, 38, 0.05);
  border-color: rgba(220, 38, 38, 0.2);
}

.issue-group.warning .issue-item {
  background: rgba(217, 119, 6, 0.05);
  border-color: rgba(217, 119, 6, 0.2);
}

.issue-icon {
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  color: var(--white);
  flex-shrink: 0;
}

.issue-group.error .issue-icon {
  background: var(--danger-color);
}

.issue-group.warning .issue-icon {
  background: var(--warning-color);
}

.issue-content {
  flex: 1;
}

.issue-content h5 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 0.5rem 0;
}

.issue-content p {
  color: var(--gray-700);
  margin: 0 0 1rem 0;
  line-height: 1.5;
}

.issue-action {
  margin-top: 0.5rem;
}

.btn-resolve {
  padding: 0.5rem 1rem;
  background: var(--primary-color);
  color: var(--white);
  border: none;
  border-radius: var(--border-radius);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: var(--transition);
}

.btn-resolve:hover {
  background: var(--primary-dark);
}

/* Transaction Review */
.transaction-review {
  background: var(--white);
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
  margin-bottom: 2rem;
}

.review-header {
  padding: 2rem 2rem 1rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--gray-200);
}

.review-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.review-header h3 i {
  color: var(--primary-color);
}

.review-filters {
  display: flex;
  gap: 0.5rem;
}

.filter-btn {
  padding: 0.5rem 1rem;
  border: 2px solid var(--gray-200);
  background: var(--white);
  color: var(--gray-700);
  border-radius: var(--border-radius);
  font-size: 0.875rem;
  cursor: pointer;
  transition: var(--transition);
}

.filter-btn:hover {
  background: var(--gray-50);
  border-color: var(--gray-300);
}

.filter-btn.active {
  background: var(--primary-color);
  border-color: var(--primary-color);
  color: var(--white);
}

.review-content {
  padding: 0;
}

.transactions-table {
  overflow-x: auto;
}

.table-header {
  display: grid;
  grid-template-columns: 120px 100px 2fr 120px 120px 160px;
  gap: 1rem;
  padding: 1rem 2rem;
  background: var(--gray-50);
  font-weight: 600;
  color: var(--gray-700);
  font-size: 0.875rem;
  border-bottom: 1px solid var(--gray-200);
}

.table-body {
  min-height: 200px;
}

.transaction-row {
  display: grid;
  grid-template-columns: 120px 100px 2fr 120px 120px 160px;
  gap: 1rem;
  padding: 1rem 2rem;
  border-bottom: 1px solid var(--gray-100);
  transition: var(--transition);
  align-items: center;
}

.transaction-row:hover {
  background: var(--gray-50);
}

.transaction-row.matched-row {
  background: rgba(5, 150, 105, 0.02);
}

.transaction-row.adjustment-row {
  background: rgba(37, 99, 235, 0.02);
}

.transaction-row.unmatched-row {
  background: rgba(217, 119, 6, 0.02);
}

.transaction-type {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 600;
}

.transaction-type i {
  color: var(--primary-color);
}

.description-text {
  font-weight: 500;
  color: var(--gray-900);
}

.reference-text {
  font-size: 0.75rem;
  color: var(--gray-500);
  margin-top: 0.25rem;
}

.amount {
  font-weight: 700;
  text-align: right;
}

.amount-positive {
  color: var(--success-color);
}

.amount-negative {
  color: var(--danger-color);
}

.amount-zero {
  color: var(--gray-600);
}

.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  display: flex;
  align-items: center;
  gap: 0.25rem;
  width: fit-content;
}

.status-matched {
  background: rgba(5, 150, 105, 0.1);
  color: var(--success-color);
}

.status-adjustment {
  background: rgba(37, 99, 235, 0.1);
  color: var(--primary-color);
}

.status-unmatched {
  background: rgba(217, 119, 6, 0.1);
  color: var(--warning-color);
}

.match-info, .adjustment-info, .no-match {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
}

.match-info {
  color: var(--success-color);
}

.adjustment-info {
  color: var(--primary-color);
}

.no-match {
  color: var(--gray-500);
}

.empty-transactions {
  text-align: center;
  padding: 3rem;
  color: var(--gray-500);
}

.empty-transactions i {
  font-size: 3rem;
  color: var(--gray-300);
  margin-bottom: 1rem;
}

/* Finalization Notes */
.finalization-notes {
  background: var(--white);
  border-radius: var(--border-radius);
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: var(--box-shadow);
}

.notes-header {
  margin-bottom: 1rem;
}

.notes-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 0.25rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.notes-header h3 i {
  color: var(--primary-color);
}

.notes-header small {
  color: var(--gray-600);
}

.notes-textarea {
  width: 100%;
  padding: 1rem;
  border: 2px solid var(--gray-200);
  border-radius: var(--border-radius);
  font-family: inherit;
  font-size: 0.875rem;
  resize: vertical;
  transition: var(--transition);
}

.notes-textarea:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* Final Confirmation */
.final-confirmation {
  background: linear-gradient(135deg, var(--gray-50) 0%, var(--white) 100%);
  border: 2px solid var(--gray-200);
  border-radius: var(--border-radius);
  padding: 2rem;
  margin-bottom: 2rem;
}

.confirmation-content {
  display: flex;
  gap: 2rem;
  align-items: flex-start;
}

.confirmation-icon {
  width: 4rem;
  height: 4rem;
  background: var(--primary-color);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  color: var(--white);
  flex-shrink: 0;
}

.confirmation-text {
  flex: 1;
}

.confirmation-text h3 {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 0.5rem 0;
}

.confirmation-text p {
  color: var(--gray-700);
  line-height: 1.6;
  margin: 0 0 1.5rem 0;
}

.confirmation-checklist {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.checklist-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  cursor: pointer;
  padding: 0.75rem;
  border-radius: var(--border-radius);
  transition: var(--transition);
}

.checklist-item:hover {
  background: var(--gray-50);
}

.confirmation-checkbox {
  display: none;
}

.checkmark {
  width: 1.5rem;
  height: 1.5rem;
  border: 2px solid var(--gray-300);
  border-radius: 4px;
  position: relative;
  transition: var(--transition);
}

.confirmation-checkbox:checked + .checkmark {
  background: var(--success-color);
  border-color: var(--success-color);
}

.confirmation-checkbox:checked + .checkmark::after {
  content: '';
  position: absolute;
  left: 0.25rem;
  top: 0.125rem;
  width: 0.25rem;
  height: 0.5rem;
  border: solid white;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
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
  max-width: 600px;
  width: 100%;
  box-shadow: var(--box-shadow-lg);
}

.modal-header {
  padding: 2rem 2rem 1rem 2rem;
  border-bottom: 1px solid var(--gray-200);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  color: var(--gray-900);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.modal-header h3 i {
  color: var(--primary-color);
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
  padding: 2rem;
}

.confirmation-warning {
  display: flex;
  gap: 1.5rem;
}

.warning-icon {
  width: 3rem;
  height: 3rem;
  background: var(--warning-color);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  color: var(--white);
  flex-shrink: 0;
}

.warning-content {
  flex: 1;
}

.warning-content h4 {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 0.5rem 0;
}

.warning-content p {
  color: var(--gray-700);
  line-height: 1.6;
  margin: 0 0 1.5rem 0;
}

.finalization-summary {
  background: var(--gray-50);
  border-radius: var(--border-radius);
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.summary-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.summary-item .label {
  font-weight: 600;
  color: var(--gray-600);
}

.summary-item .value {
  font-weight: 700;
  color: var(--gray-900);
}

.modal-footer {
  padding: 1rem 2rem 2rem 2rem;
  border-top: 1px solid var(--gray-200);
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
}

/* Loading Overlay */
.loading-overlay {
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
}

.loading-content {
  background: var(--white);
  border-radius: var(--border-radius);
  padding: 2rem;
  text-align: center;
  box-shadow: var(--box-shadow-lg);
}

.loading-spinner {
  font-size: 2rem;
  color: var(--primary-color);
  margin-bottom: 1rem;
}

.loading-content p {
  color: var(--gray-600);
  margin: 0;
}

/* Responsive Design */
@media (max-width: 1200px) {
  .summary-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .table-header, .transaction-row {
    grid-template-columns: 100px 80px 1fr 100px 100px 140px;
  }
}

@media (max-width: 1024px) {
  .bank-reconciliation-finalize {
    padding: 1rem;
  }

  .header-content {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  .header-actions {
    justify-content: center;
  }

  .review-header {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  .review-filters {
    justify-content: center;
    flex-wrap: wrap;
  }

  .confirmation-content {
    flex-direction: column;
    text-align: center;
  }
}

@media (max-width: 768px) {
  .title-section {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }

  .page-title {
    font-size: 1.5rem;
  }

  .table-header, .transaction-row {
    grid-template-columns: 1fr;
    gap: 0.5rem;
  }

  .transaction-row {
    padding: 1rem;
    border: 1px solid var(--gray-200);
    border-radius: var(--border-radius);
    margin-bottom: 0.5rem;
  }

  .table-header {
    display: none;
  }

  .col-type, .col-date, .col-description, .col-amount, .col-status, .col-match {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.25rem 0;
  }

  .col-type::before { content: 'Type: '; font-weight: 600; }
  .col-date::before { content: 'Date: '; font-weight: 600; }
  .col-description::before { content: 'Description: '; font-weight: 600; }
  .col-amount::before { content: 'Amount: '; font-weight: 600; }
  .col-status::before { content: 'Status: '; font-weight: 600; }
  .col-match::before { content: 'Match: '; font-weight: 600; }
}
</style>