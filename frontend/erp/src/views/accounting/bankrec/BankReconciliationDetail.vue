<template>
  <div class="bank-reconciliation-detail">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <button @click="goBack" class="btn-back">
            <i class="fas fa-arrow-left"></i>
          </button>
          <div>
            <h1 class="page-title">
              <i class="fas fa-balance-scale"></i>
              Bank Reconciliation #{{ reconciliation.reconciliation_id }}
            </h1>
            <p class="page-subtitle">
              {{ reconciliation.bank_account?.account_name }} - {{ formatDate(reconciliation.statement_date) }}
            </p>
          </div>
          <div class="status-indicator">
            <span class="status-badge" :class="getStatusClass(reconciliation.status)">
              {{ reconciliation.status }}
            </span>
          </div>
        </div>
        <div class="header-actions">
          <button @click="refreshData" class="btn-secondary" :disabled="loading">
            <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
            Refresh
          </button>
          <button 
            v-if="reconciliation.status !== 'Finalized'"
            @click="editReconciliation" 
            class="btn-outline"
          >
            <i class="fas fa-edit"></i>
            Edit
          </button>
          <button 
            v-if="reconciliation.status === 'In Progress'"
            @click="goToMatchTransactions" 
            class="btn-primary"
          >
            <i class="fas fa-link"></i>
            Match Transactions
          </button>
          <button 
            v-if="reconciliation.status === 'In Progress' && canFinalize"
            @click="goToFinalize" 
            class="btn-success"
          >
            <i class="fas fa-check-double"></i>
            Finalize
          </button>
        </div>
      </div>
    </div>

    <!-- Progress Steps -->
    <div class="progress-section">
      <div class="progress-steps">
        <div class="step" :class="{ 'active': true, 'completed': reconciliation.status !== 'Draft' }">
          <div class="step-indicator">
            <i class="fas fa-file-alt"></i>
          </div>
          <div class="step-content">
            <h4>Created</h4>
            <p>Reconciliation created</p>
          </div>
        </div>
        <div class="step" :class="{ 'active': reconciliation.status !== 'Draft', 'completed': reconciliation.status === 'Finalized' }">
          <div class="step-indicator">
            <i class="fas fa-link"></i>
          </div>
          <div class="step-content">
            <h4>Matching</h4>
            <p>Match transactions</p>
          </div>
        </div>
        <div class="step" :class="{ 'active': reconciliation.status === 'Finalized', 'completed': reconciliation.status === 'Finalized' }">
          <div class="step-indicator">
            <i class="fas fa-check-double"></i>
          </div>
          <div class="step-content">
            <h4>Finalized</h4>
            <p>Reconciliation complete</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-section">
      <div class="summary-cards">
        <div class="summary-card balance-card">
          <div class="card-header">
            <h3><i class="fas fa-university"></i> Statement Balance</h3>
          </div>
          <div class="card-value statement-value">
            {{ formatCurrency(reconciliation.statement_balance) }}
          </div>
          <div class="card-footer">
            As of {{ formatDate(reconciliation.statement_date) }}
          </div>
        </div>

        <div class="summary-card balance-card">
          <div class="card-header">
            <h3><i class="fas fa-book"></i> Book Balance</h3>
          </div>
          <div class="card-value book-value">
            {{ formatCurrency(reconciliation.book_balance) }}
          </div>
          <div class="card-footer">
            Per accounting records
          </div>
        </div>

        <div class="summary-card variance-card">
          <div class="card-header">
            <h3><i class="fas fa-calculator"></i> Variance</h3>
          </div>
          <div class="card-value" :class="getVarianceClass(variance)">
            {{ formatCurrency(variance) }}
          </div>
          <div class="card-footer">
            {{ variance === 0 ? 'Balanced' : (variance > 0 ? 'Overage' : 'Shortage') }}
          </div>
        </div>

        <div class="summary-card progress-card">
          <div class="card-header">
            <h3><i class="fas fa-chart-line"></i> Progress</h3>
          </div>
          <div class="progress-content">
            <div class="progress-value">{{ reconcilationProgress }}%</div>
            <div class="progress-bar">
              <div class="progress-fill" :style="{ width: reconcilationProgress + '%' }"></div>
            </div>
          </div>
          <div class="card-footer">
            {{ reconciledLines.length }} of {{ totalLines.length }} lines matched
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content Grid -->
    <div class="content-grid">
      <!-- Left Column - Bank Account Info -->
      <div class="left-column">
        <div class="info-card">
          <div class="card-header">
            <h3><i class="fas fa-info-circle"></i> Bank Account Information</h3>
          </div>
          <div class="card-body">
            <div class="info-list">
              <div class="info-item">
                <span class="label">Account Name</span>
                <span class="value">{{ reconciliation.bank_account?.account_name || 'N/A' }}</span>
              </div>
              <div class="info-item">
                <span class="label">Account Number</span>
                <span class="value">{{ reconciliation.bank_account?.account_number || 'N/A' }}</span>
              </div>
              <div class="info-item">
                <span class="label">Bank Name</span>
                <span class="value">{{ reconciliation.bank_account?.bank_name || 'N/A' }}</span>
              </div>
              <div class="info-item">
                <span class="label">Account Type</span>
                <span class="value">{{ reconciliation.bank_account?.account_type || 'N/A' }}</span>
              </div>
              <div class="info-item">
                <span class="label">Currency</span>
                <span class="value">{{ reconciliation.bank_account?.currency || 'USD' }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="info-card">
          <div class="card-header">
            <h3><i class="fas fa-calendar-alt"></i> Reconciliation Details</h3>
          </div>
          <div class="card-body">
            <div class="info-list">
              <div class="info-item">
                <span class="label">Period</span>
                <span class="value">{{ reconciliation.reconciliation_period || 'N/A' }}</span>
              </div>
              <div class="info-item">
                <span class="label">Created Date</span>
                <span class="value">{{ formatDate(reconciliation.created_at) }}</span>
              </div>
              <div class="info-item">
                <span class="label">Last Modified</span>
                <span class="value">{{ formatDate(reconciliation.updated_at) }}</span>
              </div>
              <div class="info-item">
                <span class="label">Status</span>
                <span class="value">
                  <span class="status-badge" :class="getStatusClass(reconciliation.status)">
                    {{ reconciliation.status }}
                  </span>
                </span>
              </div>
            </div>
          </div>
        </div>

        <div v-if="reconciliation.notes" class="info-card">
          <div class="card-header">
            <h3><i class="fas fa-sticky-note"></i> Notes</h3>
          </div>
          <div class="card-body">
            <p class="notes-content">{{ reconciliation.notes }}</p>
          </div>
        </div>
      </div>

      <!-- Right Column - Transaction Lines -->
      <div class="right-column">
        <div class="transactions-card">
          <div class="card-header">
            <div class="header-title">
              <h3><i class="fas fa-list"></i> Reconciliation Lines</h3>
              <span class="lines-count">{{ reconciliationLines.length }} transactions</span>
            </div>
            <div class="header-actions">
              <div class="filter-tabs">
                <button 
                  @click="filterLines('all')" 
                  :class="['filter-tab', { 'active': currentFilter === 'all' }]"
                >
                  All ({{ reconciliationLines.length }})
                </button>
                <button 
                  @click="filterLines('reconciled')" 
                  :class="['filter-tab', { 'active': currentFilter === 'reconciled' }]"
                >
                  Matched ({{ reconciledLines.length }})
                </button>
                <button 
                  @click="filterLines('unreconciled')" 
                  :class="['filter-tab', { 'active': currentFilter === 'unreconciled' }]"
                >
                  Unmatched ({{ unreconciledLines.length }})
                </button>
              </div>
            </div>
          </div>
          <div class="card-body">
            <div v-if="filteredLines.length === 0" class="empty-transactions">
              <div class="empty-icon">
                <i class="fas fa-exchange-alt"></i>
              </div>
              <h4>No transactions found</h4>
              <p v-if="currentFilter === 'all'">
                Start by matching bank statement transactions with book entries.
              </p>
              <p v-else-if="currentFilter === 'reconciled'">
                No transactions have been matched yet.
              </p>
              <p v-else>
                All transactions have been matched.
              </p>
              <button 
                v-if="reconciliation.status !== 'Finalized'"
                @click="goToMatchTransactions" 
                class="btn-primary"
              >
                <i class="fas fa-plus"></i>
                Add Transactions
              </button>
            </div>

            <div v-else class="transactions-list">
              <div 
                v-for="line in filteredLines" 
                :key="line.line_id"
                class="transaction-item"
                :class="{ 'reconciled': line.is_reconciled, 'unreconciled': !line.is_reconciled }"
              >
                <div class="transaction-header">
                  <div class="transaction-type">
                    <i class="fas" :class="getTransactionIcon(line.transaction_type)"></i>
                    <span class="type-label">{{ line.transaction_type }}</span>
                  </div>
                  <div class="transaction-status">
                    <span v-if="line.is_reconciled" class="status-matched">
                      <i class="fas fa-check-circle"></i> Matched
                    </span>
                    <span v-else class="status-unmatched">
                      <i class="fas fa-clock"></i> Pending
                    </span>
                  </div>
                </div>
                <div class="transaction-details">
                  <div class="detail-row">
                    <span class="detail-label">Transaction ID:</span>
                    <span class="detail-value">#{{ line.transaction_id }}</span>
                  </div>
                  <div class="detail-row">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value">{{ formatDate(line.transaction_date) }}</span>
                  </div>
                  <div class="detail-row">
                    <span class="detail-label">Amount:</span>
                    <span class="detail-value amount" :class="getAmountClass(line.amount)">
                      {{ formatCurrency(line.amount) }}
                    </span>
                  </div>
                  <div v-if="line.description" class="detail-row">
                    <span class="detail-label">Description:</span>
                    <span class="detail-value">{{ line.description }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions Panel -->
    <div v-if="reconciliation.status !== 'Finalized'" class="quick-actions">
      <div class="actions-header">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
      </div>
      <div class="actions-grid">
        <button @click="goToMatchTransactions" class="action-item">
          <div class="action-icon">
            <i class="fas fa-link"></i>
          </div>
          <div class="action-content">
            <h4>Match Transactions</h4>
            <p>Link bank statement entries with book records</p>
          </div>
        </button>
        <button @click="editReconciliation" class="action-item">
          <div class="action-icon">
            <i class="fas fa-edit"></i>
          </div>
          <div class="action-content">
            <h4>Edit Details</h4>
            <p>Update reconciliation information</p>
          </div>
        </button>
        <button 
          v-if="canFinalize"
          @click="goToFinalize" 
          class="action-item primary"
        >
          <div class="action-icon">
            <i class="fas fa-check-double"></i>
          </div>
          <div class="action-content">
            <h4>Finalize</h4>
            <p>Complete the reconciliation process</p>
          </div>
        </button>
      </div>
    </div>

    <!-- Loading Overlay -->
    <div v-if="loading" class="loading-overlay">
      <div class="loading-content">
        <div class="loading-spinner">
          <i class="fas fa-spinner fa-spin"></i>
        </div>
        <p>Loading reconciliation data...</p>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'BankReconciliationDetail',
  data() {
    return {
      loading: false,
      reconciliation: {
        bank_account: {},
        reconciliation_lines: []
      },
      reconciliationLines: [],
      currentFilter: 'all'
    }
  },
  computed: {
    variance() {
      const statement = parseFloat(this.reconciliation.statement_balance) || 0
      const book = parseFloat(this.reconciliation.book_balance) || 0
      return statement - book
    },
    totalLines() {
      return this.reconciliationLines
    },
    reconciledLines() {
      return this.reconciliationLines.filter(line => line.is_reconciled)
    },
    unreconciledLines() {
      return this.reconciliationLines.filter(line => !line.is_reconciled)
    },
    reconcilationProgress() {
      if (this.totalLines.length === 0) return 0
      return Math.round((this.reconciledLines.length / this.totalLines.length) * 100)
    },
    canFinalize() {
      return Math.abs(this.variance) < 0.01 && this.unreconciledLines.length === 0
    },
    filteredLines() {
      switch (this.currentFilter) {
        case 'reconciled':
          return this.reconciledLines
        case 'unreconciled':
          return this.unreconciledLines
        default:
          return this.reconciliationLines
      }
    }
  },
  async mounted() {
    await this.loadReconciliation()
  },
  methods: {
    async loadReconciliation() {
      this.loading = true
      try {
        const id = this.$route.params.id
        const response = await axios.get(`/accounting/bank-reconciliations/${id}`)
        this.reconciliation = response.data.data
        
        // Load reconciliation lines
        await this.loadReconciliationLines()
      } catch (error) {
        console.error('Error loading reconciliation:', error)
        this.$toast?.error('Failed to load reconciliation details')
        this.goBack()
      } finally {
        this.loading = false
      }
    },

    async loadReconciliationLines() {
      try {
        const response = await axios.get(`/accounting/bank-reconciliations/${this.reconciliation.reconciliation_id}/lines`)
        this.reconciliationLines = response.data.data || []
      } catch (error) {
        console.error('Error loading reconciliation lines:', error)
        this.reconciliationLines = []
      }
    },

    async refreshData() {
      await this.loadReconciliation()
    },

    filterLines(filter) {
      this.currentFilter = filter
    },

    goBack() {
      this.$router.go(-1)
    },

    editReconciliation() {
      this.$router.push(`/accounting/bank-reconciliations/${this.reconciliation.reconciliation_id}/edit`)
    },

    goToMatchTransactions() {
      this.$router.push(`/accounting/bank-reconciliations/${this.reconciliation.reconciliation_id}/match`)
    },

    goToFinalize() {
      this.$router.push(`/accounting/bank-reconciliations/${this.reconciliation.reconciliation_id}/finalize`)
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
      if (Math.abs(variance) < 0.01) return 'variance-zero'
      return variance > 0 ? 'variance-positive' : 'variance-negative'
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

.bank-reconciliation-detail {
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

.status-indicator {
  margin-left: auto;
}

.header-actions {
  display: flex;
  gap: 1rem;
  flex-shrink: 0;
}

/* Buttons */
.btn-primary, .btn-secondary, .btn-outline, .btn-success {
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

.btn-success {
  background: var(--success-color);
  color: var(--white);
}

.btn-success:hover {
  background: #047857;
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(5, 150, 105, 0.3);
}

/* Status Badges */
.status-badge {
  padding: 0.5rem 1rem;
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

/* Progress Steps */
.progress-section {
  background: var(--white);
  border-radius: var(--border-radius);
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: var(--box-shadow);
}

.progress-steps {
  display: flex;
  justify-content: space-between;
  position: relative;
}

.progress-steps::before {
  content: '';
  position: absolute;
  top: 2rem;
  left: 2rem;
  right: 2rem;
  height: 2px;
  background: var(--gray-200);
  z-index: 1;
}

.step {
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
  z-index: 2;
  flex: 1;
  max-width: 200px;
}

.step-indicator {
  width: 4rem;
  height: 4rem;
  border-radius: 50%;
  background: var(--gray-200);
  color: var(--gray-500);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  margin-bottom: 1rem;
  transition: var(--transition);
}

.step.active .step-indicator {
  background: var(--primary-color);
  color: var(--white);
}

.step.completed .step-indicator {
  background: var(--success-color);
  color: var(--white);
}

.step-content {
  text-align: center;
}

.step-content h4 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 0.25rem 0;
}

.step-content p {
  font-size: 0.875rem;
  color: var(--gray-600);
  margin: 0;
}

/* Summary Section */
.summary-section {
  margin-bottom: 2rem;
}

.summary-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
}

.summary-card {
  background: var(--white);
  border-radius: var(--border-radius);
  padding: 1.5rem;
  box-shadow: var(--box-shadow);
  transition: var(--transition);
}

.summary-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--box-shadow-lg);
}

.summary-card .card-header {
  margin-bottom: 1rem;
}

.summary-card .card-header h3 {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--gray-600);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.card-value {
  font-size: 2rem;
  font-weight: 700;
  color: var(--gray-900);
  margin-bottom: 0.5rem;
}

.statement-value {
  color: var(--primary-color);
}

.book-value {
  color: var(--success-color);
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

.card-footer {
  font-size: 0.875rem;
  color: var(--gray-600);
}

.progress-content {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.progress-value {
  font-size: 2rem;
  font-weight: 700;
  color: var(--primary-color);
}

.progress-bar {
  height: 8px;
  background: var(--gray-200);
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: var(--primary-color);
  border-radius: 4px;
  transition: width 0.5s ease;
}

/* Content Grid */
.content-grid {
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 2rem;
  margin-bottom: 2rem;
}

/* Info Cards */
.info-card, .transactions-card {
  background: var(--white);
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
  margin-bottom: 1.5rem;
}

.info-card .card-header, .transactions-card .card-header {
  padding: 1.5rem;
  border-bottom: 1px solid var(--gray-200);
}

.info-card .card-header h3, .transactions-card .card-header h3 {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.info-card .card-header h3 i, .transactions-card .card-header h3 i {
  color: var(--primary-color);
}

.info-card .card-body, .transactions-card .card-body {
  padding: 1.5rem;
}

.info-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: var(--gray-50);
  border-radius: var(--border-radius);
}

.info-item .label {
  font-weight: 600;
  color: var(--gray-600);
  font-size: 0.875rem;
}

.info-item .value {
  font-weight: 500;
  color: var(--gray-900);
  text-align: right;
}

.notes-content {
  color: var(--gray-700);
  line-height: 1.6;
  margin: 0;
}

/* Transactions */
.transactions-card .card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.header-title {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.lines-count {
  font-size: 0.875rem;
  color: var(--gray-500);
  background: var(--gray-100);
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
}

.filter-tabs {
  display: flex;
  gap: 0.5rem;
}

.filter-tab {
  padding: 0.5rem 1rem;
  border: 2px solid var(--gray-200);
  background: var(--white);
  color: var(--gray-700);
  border-radius: var(--border-radius);
  font-size: 0.875rem;
  cursor: pointer;
  transition: var(--transition);
}

.filter-tab:hover {
  background: var(--gray-50);
  border-color: var(--gray-300);
}

.filter-tab.active {
  background: var(--primary-color);
  border-color: var(--primary-color);
  color: var(--white);
}

.empty-transactions {
  text-align: center;
  padding: 3rem 1rem;
  color: var(--gray-600);
}

.empty-icon {
  font-size: 3rem;
  color: var(--gray-300);
  margin-bottom: 1rem;
}

.empty-transactions h4 {
  color: var(--gray-700);
  margin-bottom: 0.5rem;
}

.empty-transactions p {
  margin-bottom: 2rem;
}

.transactions-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.transaction-item {
  border: 2px solid var(--gray-200);
  border-radius: var(--border-radius);
  padding: 1rem;
  transition: var(--transition);
}

.transaction-item:hover {
  border-color: var(--gray-300);
  background: var(--gray-50);
}

.transaction-item.reconciled {
  border-color: rgba(5, 150, 105, 0.3);
  background: rgba(5, 150, 105, 0.05);
}

.transaction-item.unreconciled {
  border-color: rgba(217, 119, 6, 0.3);
  background: rgba(217, 119, 6, 0.05);
}

.transaction-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.transaction-type {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.transaction-type i {
  color: var(--primary-color);
}

.type-label {
  font-weight: 600;
  color: var(--gray-900);
}

.status-matched {
  color: var(--success-color);
  font-size: 0.875rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.status-unmatched {
  color: var(--warning-color);
  font-size: 0.875rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.transaction-details {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.5rem;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.875rem;
}

.detail-label {
  color: var(--gray-600);
  font-weight: 500;
}

.detail-value {
  color: var(--gray-900);
  font-weight: 600;
}

.detail-value.amount {
  font-weight: 700;
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

/* Quick Actions */
.quick-actions {
  background: var(--white);
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
  overflow: hidden;
}

.actions-header {
  padding: 1.5rem 2rem;
  border-bottom: 1px solid var(--gray-200);
  background: var(--gray-50);
}

.actions-header h3 {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.actions-header h3 i {
  color: var(--primary-color);
}

.actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 0;
}

.action-item {
  padding: 2rem;
  border: none;
  background: var(--white);
  cursor: pointer;
  transition: var(--transition);
  display: flex;
  align-items: center;
  gap: 1rem;
  text-align: left;
  border-right: 1px solid var(--gray-200);
}

.action-item:hover {
  background: var(--gray-50);
}

.action-item.primary:hover {
  background: rgba(37, 99, 235, 0.05);
}

.action-item:last-child {
  border-right: none;
}

.action-icon {
  width: 3rem;
  height: 3rem;
  border-radius: 50%;
  background: var(--gray-100);
  color: var(--gray-600);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  flex-shrink: 0;
}

.action-item.primary .action-icon {
  background: rgba(37, 99, 235, 0.1);
  color: var(--primary-color);
}

.action-content h4 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 0.25rem 0;
}

.action-content p {
  font-size: 0.875rem;
  color: var(--gray-600);
  margin: 0;
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
  .content-grid {
    grid-template-columns: 1fr;
  }

  .summary-cards {
    grid-template-columns: repeat(2, 1fr);
  }

  .actions-grid {
    grid-template-columns: 1fr;
  }

  .action-item {
    border-right: none;
    border-bottom: 1px solid var(--gray-200);
  }

  .action-item:last-child {
    border-bottom: none;
  }
}

@media (max-width: 768px) {
  .bank-reconciliation-detail {
    padding: 1rem;
  }

  .header-content {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  .title-section {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }

  .header-actions {
    justify-content: center;
  }

  .summary-cards {
    grid-template-columns: 1fr;
  }

  .progress-steps {
    flex-direction: column;
    gap: 2rem;
  }

  .progress-steps::before {
    display: none;
  }

  .filter-tabs {
    flex-direction: column;
  }

  .transaction-details {
    grid-template-columns: 1fr;
  }
}
</style>