<template>
  <div class="bank-accounts-container">
    <!-- Header Section -->
    <div class="page-header">
      <div class="header-content">
        <div class="header-left">
          <h1 class="page-title">
            <i class="fas fa-university"></i>
            Bank Accounts
          </h1>
          <p class="page-subtitle">Manage your organization's bank accounts</p>
        </div>
        <div class="header-actions">
          <button @click="refreshData" class="btn btn-secondary" :disabled="loading">
            <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
            Refresh
          </button>
          <router-link to="/bank-accounts/create" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Add New Account
          </router-link>
        </div>
      </div>
    </div>

    <!-- Filter Section -->
    <div class="filters-card">
      <div class="filters-content">
        <div class="search-wrapper">
          <i class="fas fa-search search-icon"></i>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search by bank name, account name, or number..."
            class="search-input"
            @input="handleSearch"
          >
        </div>
        <div class="filter-actions">
          <select v-model="sortBy" @change="sortAccounts" class="filter-select">
            <option value="bank_name">Sort by Bank Name</option>
            <option value="account_name">Sort by Account Name</option>
            <option value="current_balance">Sort by Balance</option>
            <option value="created_at">Sort by Date Created</option>
          </select>
          <select v-model="sortOrder" @change="sortAccounts" class="filter-select">
            <option value="asc">Ascending</option>
            <option value="desc">Descending</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Accounts Grid -->
    <div class="accounts-grid" v-if="!loading && filteredAccounts.length > 0">
      <div
        v-for="account in filteredAccounts"
        :key="account.bank_id"
        class="account-card"
        @click="viewAccountDetail(account.bank_id)"
      >
        <div class="card-header">
          <div class="bank-info">
            <div class="bank-icon">
              <i class="fas fa-building"></i>
            </div>
            <div class="bank-details">
              <h3 class="bank-name">{{ account.bank_name }}</h3>
              <p class="account-number">{{ formatAccountNumber(account.account_number) }}</p>
            </div>
          </div>
          <div class="card-actions">
            <button
              @click.stop="editAccount(account.bank_id)"
              class="action-btn edit-btn"
              title="Edit Account"
            >
              <i class="fas fa-edit"></i>
            </button>
            <button
              @click.stop="deleteAccount(account)"
              class="action-btn delete-btn"
              title="Delete Account"
            >
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </div>
        
        <div class="card-content">
          <div class="account-info">
            <div class="info-row">
              <span class="label">Account Name:</span>
              <span class="value">{{ account.account_name }}</span>
            </div>
            <div class="info-row">
              <span class="label">GL Account:</span>
              <span class="value">{{ account.chart_of_account?.account_name || 'N/A' }}</span>
            </div>
          </div>
          
          <div class="balance-section">
            <div class="balance-label">Current Balance</div>
            <div class="balance-amount" :class="getBalanceClass(account.current_balance)">
              {{ formatCurrency(account.current_balance) }}
            </div>
          </div>
        </div>

        <div class="card-footer">
          <div class="reconciliation-info">
            <i class="fas fa-check-circle"></i>
            <span>{{ account.bank_reconciliations?.length || 0 }} reconciliations</span>
          </div>
          <div class="last-updated">
            Updated {{ formatDate(account.updated_at) }}
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-if="!loading && filteredAccounts.length === 0" class="empty-state">
      <div class="empty-content">
        <div class="empty-icon">
          <i class="fas fa-university"></i>
        </div>
        <h3>No Bank Accounts Found</h3>
        <p v-if="searchQuery">
          No accounts match your search criteria. Try adjusting your search terms.
        </p>
        <p v-else>
          You haven't added any bank accounts yet. Create your first bank account to get started.
        </p>
        <button @click="clearSearch" v-if="searchQuery" class="btn btn-secondary">
          Clear Search
        </button>
        <router-link v-else to="/bank-accounts/create" class="btn btn-primary">
          <i class="fas fa-plus"></i>
          Add First Account
        </router-link>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="loading-spinner"></div>
      <p>Loading bank accounts...</p>
    </div>

    <!-- Summary Cards -->
    <div class="summary-section" v-if="!loading && accounts.length > 0">
      <div class="summary-card">
        <div class="summary-icon total">
          <i class="fas fa-university"></i>
        </div>
        <div class="summary-content">
          <h4>Total Accounts</h4>
          <p class="summary-value">{{ accounts.length }}</p>
        </div>
      </div>
      
      <div class="summary-card">
        <div class="summary-icon balance">
          <i class="fas fa-dollar-sign"></i>
        </div>
        <div class="summary-content">
          <h4>Total Balance</h4>
          <p class="summary-value">{{ formatCurrency(totalBalance) }}</p>
        </div>
      </div>
      
      <div class="summary-card">
        <div class="summary-icon positive">
          <i class="fas fa-arrow-up"></i>
        </div>
        <div class="summary-content">
          <h4>Positive Balances</h4>
          <p class="summary-value">{{ positiveBalanceCount }}</p>
        </div>
      </div>
    </div>

    <!-- Confirmation Modal -->
    <div v-if="showDeleteModal" class="modal-overlay" @click="showDeleteModal = false">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>Confirm Deletion</h3>
          <button @click="showDeleteModal = false" class="close-btn">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete the bank account?</p>
          <div class="account-preview" v-if="accountToDelete">
            <strong>{{ accountToDelete.bank_name }}</strong><br>
            <span>{{ accountToDelete.account_name }}</span><br>
            <small>{{ formatAccountNumber(accountToDelete.account_number) }}</small>
          </div>
          <div class="warning-message">
            <i class="fas fa-exclamation-triangle"></i>
            This action cannot be undone.
          </div>
        </div>
        <div class="modal-actions">
          <button @click="showDeleteModal = false" class="btn btn-secondary">
            Cancel
          </button>
          <button @click="confirmDelete" class="btn btn-danger" :disabled="deleting">
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
import { useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'BankAccountsList',
  setup() {
    const router = useRouter()
    
    // Reactive data
    const accounts = ref([])
    const loading = ref(false)
    const searchQuery = ref('')
    const sortBy = ref('bank_name')
    const sortOrder = ref('asc')
    const showDeleteModal = ref(false)
    const accountToDelete = ref(null)
    const deleting = ref(false)

    // Computed properties
    const filteredAccounts = computed(() => {
      let filtered = accounts.value

      // Apply search filter
      if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase()
        filtered = filtered.filter(account =>
          account.bank_name.toLowerCase().includes(query) ||
          account.account_name.toLowerCase().includes(query) ||
          account.account_number.toLowerCase().includes(query)
        )
      }

      // Apply sorting
      filtered.sort((a, b) => {
        let aValue = a[sortBy.value]
        let bValue = b[sortBy.value]

        if (sortBy.value === 'current_balance') {
          aValue = parseFloat(aValue) || 0
          bValue = parseFloat(bValue) || 0
        } else {
          aValue = String(aValue).toLowerCase()
          bValue = String(bValue).toLowerCase()
        }

        if (sortOrder.value === 'desc') {
          return aValue < bValue ? 1 : -1
        }
        return aValue > bValue ? 1 : -1
      })

      return filtered
    })

    const totalBalance = computed(() => {
      return accounts.value.reduce((sum, account) => {
        return sum + (parseFloat(account.current_balance) || 0)
      }, 0)
    })

    const positiveBalanceCount = computed(() => {
      return accounts.value.filter(account => 
        parseFloat(account.current_balance) > 0
      ).length
    })

    // Methods
    const fetchAccounts = async () => {
      loading.value = true
      try {
        const response = await axios.get('/accounting/bank-accounts')
        accounts.value = response.data.data || []
      } catch (error) {
        console.error('Error fetching bank accounts:', error)
        showNotification('Error fetching bank accounts', 'error')
      } finally {
        loading.value = false
      }
    }

    const refreshData = () => {
      fetchAccounts()
    }

    const handleSearch = () => {
      // Search is reactive through computed property
    }

    const sortAccounts = () => {
      // Sorting is reactive through computed property
    }

    const clearSearch = () => {
      searchQuery.value = ''
    }

    const viewAccountDetail = (id) => {
      router.push(`/bank-accounts/${id}`)
    }

    const editAccount = (id) => {
      router.push(`/bank-accounts/${id}/edit`)
    }

    const deleteAccount = (account) => {
      accountToDelete.value = account
      showDeleteModal.value = true
    }

    const confirmDelete = async () => {
      if (!accountToDelete.value) return
      
      deleting.value = true
      try {
        await axios.delete(`/accounting/bank-accounts/${accountToDelete.value.bank_id}`)
        accounts.value = accounts.value.filter(
          account => account.bank_id !== accountToDelete.value.bank_id
        )
        showNotification('Bank account deleted successfully', 'success')
        showDeleteModal.value = false
        accountToDelete.value = null
      } catch (error) {
        console.error('Error deleting bank account:', error)
        showNotification(
          error.response?.data?.message || 'Error deleting bank account',
          'error'
        )
      } finally {
        deleting.value = false
      }
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
        month: 'short',
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
      fetchAccounts()
    })

    return {
      accounts,
      loading,
      searchQuery,
      sortBy,
      sortOrder,
      showDeleteModal,
      accountToDelete,
      deleting,
      filteredAccounts,
      totalBalance,
      positiveBalanceCount,
      fetchAccounts,
      refreshData,
      handleSearch,
      sortAccounts,
      clearSearch,
      viewAccountDetail,
      editAccount,
      deleteAccount,
      confirmDelete,
      formatCurrency,
      formatAccountNumber,
      formatDate,
      getBalanceClass
    }
  }
}
</script>

<style scoped>
.bank-accounts-container {
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

.page-subtitle {
  color: var(--gray-600);
  font-size: 1.1rem;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 1rem;
  flex-shrink: 0;
}

/* Filters */
.filters-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  margin-bottom: 2rem;
  overflow: hidden;
}

.filters-content {
  padding: 1.5rem;
  display: flex;
  gap: 2rem;
  align-items: center;
}

.search-wrapper {
  position: relative;
  flex: 1;
  max-width: 400px;
}

.search-icon {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--gray-400);
}

.search-input {
  width: 100%;
  padding: 0.75rem 1rem 0.75rem 2.5rem;
  border: 2px solid var(--gray-200);
  border-radius: 8px;
  font-size: 1rem;
  transition: all 0.2s ease;
}

.search-input:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.filter-actions {
  display: flex;
  gap: 1rem;
}

.filter-select {
  padding: 0.75rem 1rem;
  border: 2px solid var(--gray-200);
  border-radius: 8px;
  font-size: 0.9rem;
  background: white;
  cursor: pointer;
  transition: all 0.2s ease;
}

.filter-select:focus {
  outline: none;
  border-color: var(--primary-color);
}

/* Accounts Grid */
.accounts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.account-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
  cursor: pointer;
  overflow: hidden;
  border: 2px solid transparent;
}

.account-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 25px -5px rgba(0, 0, 0, 0.15);
  border-color: var(--primary-color);
}

.card-header {
  padding: 1.5rem 1.5rem 1rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.bank-info {
  display: flex;
  gap: 1rem;
  flex: 1;
}

.bank-icon {
  width: 48px;
  height: 48px;
  background: var(--primary-color);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.2rem;
  flex-shrink: 0;
}

.bank-details {
  flex: 1;
}

.bank-name {
  font-size: 1.2rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 0.25rem 0;
}

.account-number {
  color: var(--gray-600);
  font-family: 'Courier New', monospace;
  font-size: 0.9rem;
  margin: 0;
}

.card-actions {
  display: flex;
  gap: 0.5rem;
}

.action-btn {
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 0.9rem;
}

.edit-btn {
  background: var(--gray-100);
  color: var(--gray-600);
}

.edit-btn:hover {
  background: var(--primary-color);
  color: white;
}

.delete-btn {
  background: var(--gray-100);
  color: var(--gray-600);
}

.delete-btn:hover {
  background: var(--danger-color);
  color: white;
}

.card-content {
  padding: 0 1.5rem 1rem 1.5rem;
}

.account-info {
  margin-bottom: 1rem;
}

.info-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.label {
  font-weight: 500;
  color: var(--gray-600);
}

.value {
  color: var(--gray-900);
  font-weight: 500;
}

.balance-section {
  background: var(--gray-50);
  padding: 1rem;
  border-radius: 8px;
  text-align: center;
}

.balance-label {
  font-size: 0.9rem;
  color: var(--gray-600);
  margin-bottom: 0.25rem;
}

.balance-amount {
  font-size: 1.5rem;
  font-weight: 700;
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

.card-footer {
  padding: 1rem 1.5rem;
  background: var(--gray-50);
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.85rem;
  color: var(--gray-600);
}

.reconciliation-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.reconciliation-info i {
  color: var(--success-color);
}

/* Summary Section */
.summary-section {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
  margin-top: 2rem;
}

.summary-card {
  background: white;
  padding: 1.5rem;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  display: flex;
  gap: 1rem;
  align-items: center;
}

.summary-icon {
  width: 48px;
  height: 48px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.2rem;
}

.summary-icon.total {
  background: var(--primary-color);
}

.summary-icon.balance {
  background: var(--success-color);
}

.summary-icon.positive {
  background: var(--warning-color);
}

.summary-content h4 {
  margin: 0 0 0.25rem 0;
  font-size: 0.9rem;
  color: var(--gray-600);
  font-weight: 500;
}

.summary-value {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--gray-900);
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 4rem 2rem;
}

.empty-content {
  max-width: 400px;
  margin: 0 auto;
}

.empty-icon {
  width: 80px;
  height: 80px;
  background: var(--gray-100);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1.5rem auto;
  font-size: 2rem;
  color: var(--gray-400);
}

.empty-content h3 {
  margin: 0 0 1rem 0;
  color: var(--gray-700);
}

.empty-content p {
  color: var(--gray-600);
  margin: 0 0 2rem 0;
  line-height: 1.6;
}

/* Loading State */
.loading-state {
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

.btn-primary:hover {
  background: var(--primary-dark);
  transform: translateY(-1px);
}

.btn-secondary {
  background: var(--gray-100);
  color: var(--gray-700);
}

.btn-secondary:hover {
  background: var(--gray-200);
}

.btn-danger {
  background: var(--danger-color);
  color: white;
}

.btn-danger:hover {
  background: #b91c1c;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
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
  max-width: 500px;
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

.account-preview {
  background: var(--gray-50);
  padding: 1rem;
  border-radius: 8px;
  margin: 1rem 0;
  text-align: center;
}

.warning-message {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--warning-color);
  font-weight: 500;
  margin-top: 1rem;
}

.modal-actions {
  padding: 1rem 1.5rem 1.5rem 1.5rem;
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
  border-top: 1px solid var(--gray-200);
}

/* Responsive Design */
@media (max-width: 768px) {
  .bank-accounts-container {
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

  .filters-content {
    flex-direction: column;
    gap: 1rem;
  }

  .filter-actions {
    width: 100%;
  }

  .filter-select {
    flex: 1;
  }

  .accounts-grid {
    grid-template-columns: 1fr;
  }

  .card-header {
    flex-direction: column;
    gap: 1rem;
  }

  .card-actions {
    align-self: flex-end;
  }

  .summary-section {
    grid-template-columns: 1fr;
  }
}
</style>