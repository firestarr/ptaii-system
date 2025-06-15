<template>
  <div class="bank-account-detail-container">
    <!-- Header Section -->
    <div class="page-header">
      <div class="header-content">
        <div class="header-left">
          <router-link to="/bank-accounts" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Back to Bank Accounts
          </router-link>
          <div class="title-section">
            <h1 class="page-title" v-if="account">
              <i class="fas fa-university"></i>
              {{ account.bank_name }}
            </h1>
            <div class="title-meta" v-if="account">
              <span class="account-number">{{ formatAccountNumber(account.account_number) }}</span>
              <span class="separator">•</span>
              <span class="account-name">{{ account.account_name }}</span>
            </div>
          </div>
        </div>
        <div class="header-actions" v-if="account">
          <button @click="refreshData" class="btn btn-secondary" :disabled="loading">
            <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
            Refresh
          </button>
          <router-link :to="`/bank-accounts/${account.bank_id}/edit`" class="btn btn-primary">
            <i class="fas fa-edit"></i>
            Edit Account
          </router-link>
          <button @click="showDeleteModal = true" class="btn btn-danger">
            <i class="fas fa-trash"></i>
            Delete
          </button>
        </div>
      </div>
    </div>

    <!-- Account Overview Cards -->
    <div class="overview-section" v-if="account">
      <div class="overview-card balance-card">
        <div class="card-header">
          <div class="card-icon balance">
            <i class="fas fa-dollar-sign"></i>
          </div>
          <div class="card-info">
            <h3>Current Balance</h3>
            <p>As of {{ formatDate(account.updated_at) }}</p>
          </div>
        </div>
        <div class="card-content">
          <div class="balance-amount" :class="getBalanceClass(account.current_balance)">
            {{ formatCurrency(account.current_balance) }}
          </div>
        </div>
      </div>

      <div class="overview-card">
        <div class="card-header">
          <div class="card-icon reconciliation">
            <i class="fas fa-check-circle"></i>
          </div>
          <div class="card-info">
            <h3>Reconciliations</h3>
            <p>Bank statement matching</p>
          </div>
        </div>
        <div class="card-content">
          <div class="stat-value">{{ reconciliationsCount }}</div>
          <div class="stat-label">Total reconciliations</div>
        </div>
      </div>

      <div class="overview-card">
        <div class="card-header">
          <div class="card-icon status">
            <i class="fas fa-info-circle"></i>
          </div>
          <div class="card-info">
            <h3>Account Status</h3>
            <p>Current account state</p>
          </div>
        </div>
        <div class="card-content">
          <div class="status-badge active">
            <i class="fas fa-check"></i>
            Active
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" v-if="account">
      <div class="content-grid">
        <!-- Account Details Card -->
        <div class="detail-card">
          <div class="card-header">
            <h3>
              <i class="fas fa-info-circle"></i>
              Account Information
            </h3>
            <button @click="copyAccountInfo" class="action-btn" title="Copy Account Info">
              <i class="fas fa-copy"></i>
            </button>
          </div>
          
          <div class="detail-content">
            <div class="detail-row">
              <div class="detail-item">
                <label>Bank Name</label>
                <value>{{ account.bank_name }}</value>
              </div>
              <div class="detail-item">
                <label>Account Name</label>
                <value>{{ account.account_name }}</value>
              </div>
            </div>
            
            <div class="detail-row">
              <div class="detail-item">
                <label>Account Number</label>
                <value class="account-number-full" @click="toggleAccountNumber">
                  {{ showFullAccountNumber ? account.account_number : formatAccountNumber(account.account_number) }}
                  <i class="fas fa-eye toggle-icon" :class="{ 'fa-eye-slash': showFullAccountNumber }"></i>
                </value>
              </div>
              <div class="detail-item" v-if="account.routing_number">
                <label>Routing Number</label>
                <value>{{ account.routing_number }}</value>
              </div>
            </div>

            <div class="detail-row">
              <div class="detail-item">
                <label>GL Account</label>
                <value v-if="account.chart_of_account">
                  {{ account.chart_of_account.account_code }} - {{ account.chart_of_account.account_name }}
                </value>
                <value v-else class="text-muted">Not assigned</value>
              </div>
              <div class="detail-item" v-if="account.bank_branch">
                <label>Branch</label>
                <value>{{ account.bank_branch }}</value>
              </div>
            </div>

            <div class="detail-row" v-if="account.notes">
              <div class="detail-item full-width">
                <label>Notes</label>
                <value class="notes">{{ account.notes }}</value>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Reconciliations -->
        <div class="detail-card">
          <div class="card-header">
            <h3>
              <i class="fas fa-history"></i>
              Recent Reconciliations
            </h3>
            <router-link :to="`/bank-reconciliations?bank_id=${account.bank_id}`" class="action-btn" title="View All">
              <i class="fas fa-external-link-alt"></i>
            </router-link>
          </div>
          
          <div class="detail-content">
            <div v-if="recentReconciliations.length > 0" class="reconciliations-list">
              <div
                v-for="reconciliation in recentReconciliations"
                :key="reconciliation.reconciliation_id"
                class="reconciliation-item"
                @click="viewReconciliation(reconciliation.reconciliation_id)"
              >
                <div class="reconciliation-date">
                  {{ formatDate(reconciliation.statement_date) }}
                </div>
                <div class="reconciliation-details">
                  <div class="reconciliation-status">
                    <span class="status-badge" :class="reconciliation.status.toLowerCase()">
                      {{ reconciliation.status }}
                    </span>
                  </div>
                  <div class="reconciliation-balance">
                    {{ formatCurrency(reconciliation.statement_balance) }}
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="empty-reconciliations">
              <i class="fas fa-file-alt"></i>
              <p>No reconciliations found</p>
              <button class="btn btn-sm btn-primary">
                <i class="fas fa-plus"></i>
                Create First Reconciliation
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="quick-actions-section">
        <h3>
          <i class="fas fa-bolt"></i>
          Quick Actions
        </h3>
        <div class="actions-grid">
          <button class="action-card" @click="createReconciliation">
            <div class="action-icon reconciliation">
              <i class="fas fa-balance-scale"></i>
            </div>
            <div class="action-content">
              <h4>New Reconciliation</h4>
              <p>Reconcile bank statement</p>
            </div>
          </button>

          <router-link :to="`/bank-transactions?bank_id=${account.bank_id}`" class="action-card">
            <div class="action-icon transactions">
              <i class="fas fa-exchange-alt"></i>
            </div>
            <div class="action-content">
              <h4>View Transactions</h4>
              <p>See transaction history</p>
            </div>
          </router-link>

          <button class="action-card" @click="exportAccountData">
            <div class="action-icon export">
              <i class="fas fa-download"></i>
            </div>
            <div class="action-content">
              <h4>Export Data</h4>
              <p>Download account info</p>
            </div>
          </button>

          <button class="action-card" @click="generateReport">
            <div class="action-icon report">
              <i class="fas fa-chart-bar"></i>
            </div>
            <div class="action-content">
              <h4>Generate Report</h4>
              <p>Account activity report</p>
            </div>
          </button>
        </div>
      </div>

      <!-- Account Timeline -->
      <div class="timeline-section">
        <h3>
          <i class="fas fa-clock"></i>
          Account Timeline
        </h3>
        <div class="timeline">
          <div class="timeline-item">
            <div class="timeline-marker created">
              <i class="fas fa-plus"></i>
            </div>
            <div class="timeline-content">
              <div class="timeline-date">{{ formatDate(account.created_at) }}</div>
              <div class="timeline-title">Account Created</div>
              <div class="timeline-description">Bank account was added to the system</div>
            </div>
          </div>

          <div class="timeline-item" v-if="account.updated_at !== account.created_at">
            <div class="timeline-marker updated">
              <i class="fas fa-edit"></i>
            </div>
            <div class="timeline-content">
              <div class="timeline-date">{{ formatDate(account.updated_at) }}</div>
              <div class="timeline-title">Account Updated</div>
              <div class="timeline-description">Account information was last modified</div>
            </div>
          </div>

          <div class="timeline-item" v-if="lastReconciliation">
            <div class="timeline-marker reconciliation">
              <i class="fas fa-check-circle"></i>
            </div>
            <div class="timeline-content">
              <div class="timeline-date">{{ formatDate(lastReconciliation.statement_date) }}</div>
              <div class="timeline-title">Last Reconciliation</div>
              <div class="timeline-description">
                Bank statement reconciled - {{ formatCurrency(lastReconciliation.statement_balance) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="loading-spinner"></div>
      <p>Loading account details...</p>
    </div>

    <!-- Error State -->
    <div v-if="error" class="error-state">
      <div class="error-icon">
        <i class="fas fa-exclamation-triangle"></i>
      </div>
      <h3>Error Loading Account</h3>
      <p>{{ error }}</p>
      <button @click="fetchAccount" class="btn btn-primary">
        <i class="fas fa-retry"></i>
        Try Again
      </button>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="modal-overlay" @click="showDeleteModal = false">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>Confirm Account Deletion</h3>
          <button @click="showDeleteModal = false" class="close-btn">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="warning-section">
            <div class="warning-icon">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="warning-content">
              <h4>This action cannot be undone</h4>
              <p>Deleting this bank account will permanently remove:</p>
              <ul>
                <li>Account information and settings</li>
                <li>All associated reconciliations</li>
                <li>Transaction history links</li>
              </ul>
            </div>
          </div>
          
          <div class="account-summary" v-if="account">
            <h5>Account to be deleted:</h5>
            <div class="summary-details">
              <strong>{{ account.bank_name }}</strong><br>
              <span>{{ account.account_name }}</span><br>
              <small>{{ formatAccountNumber(account.account_number) }}</small>
            </div>
          </div>
        </div>
        <div class="modal-actions">
          <button @click="showDeleteModal = false" class="btn btn-secondary">
            Cancel
          </button>
          <button @click="deleteAccount" class="btn btn-danger" :disabled="deleting">
            <i class="fas fa-trash" v-if="!deleting"></i>
            <i class="fas fa-spinner fa-spin" v-if="deleting"></i>
            {{ deleting ? 'Deleting...' : 'Delete Account' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'

export default {
  name: 'BankAccountDetail',
  setup() {
    const router = useRouter()
    const route = useRoute()
    
    // Reactive data
    const account = ref(null)
    const loading = ref(false)
    const error = ref(null)
    const showDeleteModal = ref(false)
    const deleting = ref(false)
    const showFullAccountNumber = ref(false)

    // Computed properties
    const accountId = computed(() => route.params.id)
    
    const recentReconciliations = computed(() => {
      return account.value?.bank_reconciliations?.slice(0, 5) || []
    })

    const reconciliationsCount = computed(() => {
      return account.value?.bank_reconciliations?.length || 0
    })

    const lastReconciliation = computed(() => {
      const reconciliations = account.value?.bank_reconciliations
      return reconciliations && reconciliations.length > 0 ? reconciliations[0] : null
    })

    // Methods
    const fetchAccount = async () => {
      loading.value = true
      error.value = null
      
      try {
        const response = await axios.get(`/accounting/bank-accounts/${accountId.value}`)
        account.value = response.data.data
      } catch (err) {
        console.error('Error fetching account:', err)
        error.value = err.response?.data?.message || 'Failed to load account details'
      } finally {
        loading.value = false
      }
    }

    const refreshData = () => {
      fetchAccount()
    }

    const deleteAccount = async () => {
      deleting.value = true
      
      try {
        await axios.delete(`/accounting/bank-accounts/${accountId.value}`)
        showNotification('Bank account deleted successfully', 'success')
        router.push('/accounting/bank-accounts')
      } catch (err) {
        console.error('Error deleting account:', err)
        showNotification(
          err.response?.data?.message || 'Error deleting account',
          'error'
        )
      } finally {
        deleting.value = false
        showDeleteModal.value = false
      }
    }

    const toggleAccountNumber = () => {
      showFullAccountNumber.value = !showFullAccountNumber.value
    }

    const copyAccountInfo = async () => {
      if (!account.value) return
      
      const info = `Bank: ${account.value.bank_name}\nAccount: ${account.value.account_name}\nNumber: ${account.value.account_number}`
      
      try {
        await navigator.clipboard.writeText(info)
        showNotification('Account information copied to clipboard', 'success')
      } catch (err) {
        console.error('Failed to copy:', err)
        showNotification('Failed to copy to clipboard', 'error')
      }
    }

    const createReconciliation = () => {
      router.push(`/bank-reconciliations/create?bank_id=${accountId.value}`)
    }

    const viewReconciliation = (reconciliationId) => {
      router.push(`/bank-reconciliations/${reconciliationId}`)
    }

    const exportAccountData = () => {
      // Implement export functionality
      showNotification('Export functionality coming soon', 'info')
    }

    const generateReport = () => {
      // Implement report generation
      showNotification('Report generation coming soon', 'info')
    }

    // Utility methods
    const formatCurrency = (amount) => {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount || 0)
    }

    const formatAccountNumber = (number) => {
      return number ? `****${number.slice(-4)}` : 'N/A'
    }

    const formatDate = (date) => {
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      })
    }

    const getBalanceClass = (balance) => {
      const amount = parseFloat(balance) || 0
      if (amount > 0) return 'positive'
      if (amount < 0) return 'negative'
      return 'zero'
    }

    const showNotification = (message, type = 'info') => {
      // Implement notification system here
      console.log(`${type.toUpperCase()}: ${message}`)
    }

    // Lifecycle
    onMounted(() => {
      fetchAccount()
    })

    return {
      account,
      loading,
      error,
      showDeleteModal,
      deleting,
      showFullAccountNumber,
      accountId,
      recentReconciliations,
      reconciliationsCount,
      lastReconciliation,
      fetchAccount,
      refreshData,
      deleteAccount,
      toggleAccountNumber,
      copyAccountInfo,
      createReconciliation,
      viewReconciliation,
      exportAccountData,
      generateReport,
      formatCurrency,
      formatAccountNumber,
      formatDate,
      getBalanceClass
    }
  }
}
</script>

<style scoped>
.bank-account-detail-container {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

/* Page Header */
.page-header {
  margin-bottom: 2rem;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 2rem;
}

.header-left {
  flex: 1;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--gray-600);
  text-decoration: none;
  margin-bottom: 1rem;
  font-size: 0.9rem;
  transition: color 0.2s ease;
}

.back-link:hover {
  color: var(--primary-color);
}

.title-section {
  margin-bottom: 1rem;
}

.page-title {
  font-size: 2rem;
  font-weight: 700;
  color: var(--gray-900);
  margin: 0 0 0.5rem 0;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.page-title i {
  color: var(--primary-color);
}

.title-meta {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--gray-600);
  font-size: 1rem;
}

.account-number {
  font-family: 'Courier New', monospace;
  background: var(--gray-100);
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
}

.separator {
  color: var(--gray-400);
}

.header-actions {
  display: flex;
  gap: 1rem;
  flex-shrink: 0;
}

/* Overview Cards */
.overview-section {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.overview-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.overview-card .card-header {
  padding: 1.5rem 1.5rem 1rem 1.5rem;
  display: flex;
  gap: 1rem;
  align-items: flex-start;
}

.card-icon {
  width: 48px;
  height: 48px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.2rem;
  flex-shrink: 0;
}

.card-icon.balance {
  background: var(--success-color);
}

.card-icon.reconciliation {
  background: var(--primary-color);
}

.card-icon.status {
  background: var(--warning-color);
}

.card-info {
  flex: 1;
}

.card-info h3 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 0.25rem 0;
}

.card-info p {
  color: var(--gray-600);
  margin: 0;
  font-size: 0.85rem;
}

.overview-card .card-content {
  padding: 0 1.5rem 1.5rem 1.5rem;
}

.balance-amount {
  font-size: 2rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
}

.balance-amount.positive {
  color: var(--success-color);
}

.balance-amount.negative {
  color: var(--danger-color);
}

.balance-amount.zero {
  color: var(--gray-500);
}

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  color: var(--gray-900);
  margin-bottom: 0.25rem;
}

.stat-label {
  color: var(--gray-600);
  font-size: 0.85rem;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 600;
}

.status-badge.active {
  background: rgba(5, 150, 105, 0.1);
  color: var(--success-color);
}

/* Main Content */
.main-content {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.content-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
}

.detail-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.detail-card .card-header {
  padding: 1.5rem 1.5rem 1rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--gray-200);
}

.detail-card .card-header h3 {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.detail-card .card-header h3 i {
  color: var(--primary-color);
}

.action-btn {
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 8px;
  background: var(--gray-100);
  color: var(--gray-600);
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
}

.action-btn:hover {
  background: var(--primary-color);
  color: white;
}

.detail-content {
  padding: 1.5rem;
}

.detail-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
  margin-bottom: 1.5rem;
}

.detail-row:last-child {
  margin-bottom: 0;
}

.detail-item {
  display: flex;
  flex-direction: column;
}

.detail-item.full-width {
  grid-column: 1 / -1;
}

.detail-item label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--gray-600);
  margin-bottom: 0.25rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.detail-item value {
  color: var(--gray-900);
  font-weight: 500;
  display: block;
}

.detail-item value.notes {
  background: var(--gray-50);
  padding: 1rem;
  border-radius: 8px;
  line-height: 1.6;
}

.detail-item value.text-muted {
  color: var(--gray-500);
  font-style: italic;
}

.account-number-full {
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  transition: color 0.2s ease;
}

.account-number-full:hover {
  color: var(--primary-color);
}

.toggle-icon {
  font-size: 0.8rem;
  opacity: 0.6;
}

/* Reconciliations List */
.reconciliations-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.reconciliation-item {
  padding: 1rem;
  border: 1px solid var(--gray-200);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.reconciliation-item:hover {
  border-color: var(--primary-color);
  background: var(--gray-50);
}

.reconciliation-date {
  font-weight: 600;
  color: var(--gray-900);
}

.reconciliation-details {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.25rem;
}

.reconciliation-status .status-badge {
  padding: 0.25rem 0.75rem;
  font-size: 0.75rem;
}

.reconciliation-balance {
  font-weight: 600;
  color: var(--gray-700);
}

.empty-reconciliations {
  text-align: center;
  padding: 2rem;
  color: var(--gray-600);
}

.empty-reconciliations i {
  font-size: 2rem;
  margin-bottom: 1rem;
  color: var(--gray-400);
}

/* Quick Actions */
.quick-actions-section h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 1.5rem 0;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.quick-actions-section h3 i {
  color: var(--primary-color);
}

.actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.action-card {
  background: white;
  border: 2px solid var(--gray-200);
  border-radius: 12px;
  padding: 1.5rem;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
  color: inherit;
  display: flex;
  gap: 1rem;
  align-items: center;
}

.action-card:hover {
  border-color: var(--primary-color);
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.action-icon {
  width: 48px;
  height: 48px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.2rem;
  flex-shrink: 0;
}

.action-icon.reconciliation {
  background: var(--primary-color);
}

.action-icon.transactions {
  background: var(--success-color);
}

.action-icon.export {
  background: var(--warning-color);
}

.action-icon.report {
  background: var(--danger-color);
}

.action-content {
  flex: 1;
}

.action-content h4 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 0.25rem 0;
}

.action-content p {
  color: var(--gray-600);
  margin: 0;
  font-size: 0.85rem;
}

/* Timeline */
.timeline-section h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 1.5rem 0;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.timeline-section h3 i {
  color: var(--primary-color);
}

.timeline {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  padding: 2rem;
  position: relative;
}

.timeline::before {
  content: '';
  position: absolute;
  left: 2.75rem;
  top: 2rem;
  bottom: 2rem;
  width: 2px;
  background: var(--gray-200);
}

.timeline-item {
  display: flex;
  gap: 1.5rem;
  margin-bottom: 2rem;
  position: relative;
}

.timeline-item:last-child {
  margin-bottom: 0;
}

.timeline-marker {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 0.9rem;
  flex-shrink: 0;
  position: relative;
  z-index: 1;
}

.timeline-marker.created {
  background: var(--success-color);
}

.timeline-marker.updated {
  background: var(--primary-color);
}

.timeline-marker.reconciliation {
  background: var(--warning-color);
}

.timeline-content {
  flex: 1;
  padding-top: 0.5rem;
}

.timeline-date {
  font-size: 0.85rem;
  color: var(--gray-500);
  margin-bottom: 0.25rem;
}

.timeline-title {
  font-weight: 600;
  color: var(--gray-900);
  margin-bottom: 0.25rem;
}

.timeline-description {
  color: var(--gray-600);
  font-size: 0.9rem;
  line-height: 1.5;
}

/* Loading and Error States */
.loading-state,
.error-state {
  text-align: center;
  padding: 4rem 2rem;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 4px solid var(--gray-200);
  border-top: 4px solid var(--primary-color);
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem auto;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.error-icon {
  width: 80px;
  height: 80px;
  background: var(--danger-color);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1.5rem auto;
  font-size: 2rem;
  color: white;
}

.error-state h3 {
  margin: 0 0 1rem 0;
  color: var(--gray-700);
}

.error-state p {
  color: var(--gray-600);
  margin: 0 0 2rem 0;
  line-height: 1.6;
}

/* Buttons */
.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  justify-content: center;
}

.btn-primary {
  background: var(--primary-color);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: var(--primary-dark);
  transform: translateY(-1px);
}

.btn-secondary {
  background: var(--gray-100);
  color: var(--gray-700);
}

.btn-secondary:hover:not(:disabled) {
  background: var(--gray-200);
}

.btn-danger {
  background: var(--danger-color);
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background: #b91c1c;
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.8rem;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
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
  background: white;
  border-radius: 12px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  max-width: 600px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  padding: 1.5rem 1.5rem 1rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--gray-200);
}

.modal-header h3 {
  margin: 0;
  color: var(--gray-900);
}

.close-btn {
  width: 32px;
  height: 32px;
  border: none;
  background: none;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--gray-400);
  transition: all 0.2s ease;
}

.close-btn:hover {
  background: var(--gray-100);
  color: var(--gray-600);
}

.modal-body {
  padding: 1.5rem;
}

.warning-section {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.warning-icon {
  width: 48px;
  height: 48px;
  background: var(--warning-color);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.2rem;
  flex-shrink: 0;
}

.warning-content {
  flex: 1;
}

.warning-content h4 {
  margin: 0 0 0.5rem 0;
  color: var(--gray-900);
}

.warning-content p {
  color: var(--gray-600);
  margin: 0 0 1rem 0;
}

.warning-content ul {
  color: var(--gray-600);
  padding-left: 1.5rem;
  margin: 0;
}

.account-summary {
  background: var(--gray-50);
  padding: 1rem;
  border-radius: 8px;
  text-align: center;
}

.account-summary h5 {
  margin: 0 0 0.5rem 0;
  color: var(--gray-700);
  font-size: 0.9rem;
  font-weight: 600;
}

.summary-details {
  color: var(--gray-900);
}

.modal-actions {
  padding: 1rem 1.5rem 1.5rem 1.5rem;
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
  border-top: 1px solid var(--gray-200);
}

/* Responsive Design */
@media (max-width: 1024px) {
  .content-grid {
    grid-template-columns: 1fr;
  }
  
  .actions-grid {
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  }
}

@media (max-width: 768px) {
  .bank-account-detail-container {
    padding: 1rem;
  }

  .header-content {
    flex-direction: column;
    gap: 1rem;
  }

  .header-actions {
    width: 100%;
    justify-content: stretch;
  }

  .overview-section {
    grid-template-columns: 1fr;
  }

  .detail-row {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .actions-grid {
    grid-template-columns: 1fr;
  }

  .action-card {
    flex-direction: column;
    text-align: center;
  }

  .timeline::before {
    left: 1.25rem;
  }

  .timeline-item {
    gap: 1rem;
  }

  .timeline-marker {
    width: 32px;
    height: 32px;
    font-size: 0.8rem;
  }
}
</style>