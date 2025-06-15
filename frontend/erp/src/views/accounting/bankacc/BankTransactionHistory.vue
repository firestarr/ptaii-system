<template>
  <div class="bank-transaction-history-container">
    <!-- Header Section -->
    <div class="page-header">
      <div class="header-content">
        <div class="header-left">
          <router-link :to="`/bank-accounts/${bankAccountId}`" class="back-link" v-if="bankAccountId">
            <i class="fas fa-arrow-left"></i>
            Back to Account Details
          </router-link>
          <router-link to="/bank-accounts" class="back-link" v-else>
            <i class="fas fa-arrow-left"></i>
            Back to Bank Accounts
          </router-link>
          <div class="title-section">
            <h1 class="page-title">
              <i class="fas fa-exchange-alt"></i>
              Transaction History
            </h1>
            <div class="title-meta" v-if="selectedAccount">
              <span class="bank-name">{{ selectedAccount.bank_name }}</span>
              <span class="separator">•</span>
              <span class="account-name">{{ selectedAccount.account_name }}</span>
            </div>
          </div>
        </div>
        <div class="header-actions">
          <button @click="exportTransactions" class="btn btn-secondary" :disabled="loading">
            <i class="fas fa-download"></i>
            Export
          </button>
          <button @click="refreshData" class="btn btn-primary" :disabled="loading">
            <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
            Refresh
          </button>
        </div>
      </div>
    </div>

    <!-- Account Summary Card -->
    <div class="account-summary-card" v-if="selectedAccount">
      <div class="summary-content">
        <div class="account-info">
          <div class="account-icon">
            <i class="fas fa-university"></i>
          </div>
          <div class="account-details">
            <h3>{{ selectedAccount.bank_name }}</h3>
            <p>{{ selectedAccount.account_name }}</p>
            <span class="account-number">{{ formatAccountNumber(selectedAccount.account_number) }}</span>
          </div>
        </div>
        <div class="balance-info">
          <div class="balance-label">Current Balance</div>
          <div class="balance-amount" :class="getBalanceClass(selectedAccount.current_balance)">
            {{ formatCurrency(selectedAccount.current_balance) }}
          </div>
        </div>
      </div>
    </div>

    <!-- Filters and Search -->
    <div class="filters-card">
      <div class="filters-content">
        <div class="search-section">
          <div class="search-wrapper">
            <i class="fas fa-search search-icon"></i>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search transactions by description, reference, or amount..."
              class="search-input"
              @input="handleSearch"
            >
          </div>
        </div>
        
        <div class="filter-section">
          <div class="filter-row">
            <div class="filter-group">
              <label>Date Range</label>
              <div class="date-range">
                <input
                  v-model="filters.dateFrom"
                  type="date"
                  class="date-input"
                  @change="applyFilters"
                >
                <span class="date-separator">to</span>
                <input
                  v-model="filters.dateTo"
                  type="date"
                  class="date-input"
                  @change="applyFilters"
                >
              </div>
            </div>

            <div class="filter-group">
              <label>Transaction Type</label>
              <select v-model="filters.type" @change="applyFilters" class="filter-select">
                <option value="">All Types</option>
                <option value="debit">Debit</option>
                <option value="credit">Credit</option>
              </select>
            </div>

            <div class="filter-group">
              <label>Amount Range</label>
              <div class="amount-range">
                <input
                  v-model="filters.amountFrom"
                  type="number"
                  step="0.01"
                  placeholder="Min"
                  class="amount-input"
                  @input="applyFilters"
                >
                <span class="amount-separator">-</span>
                <input
                  v-model="filters.amountTo"
                  type="number"
                  step="0.01"
                  placeholder="Max"
                  class="amount-input"
                  @input="applyFilters"
                >
              </div>
            </div>

            <div class="filter-group">
              <label>Status</label>
              <select v-model="filters.status" @change="applyFilters" class="filter-select">
                <option value="">All Status</option>
                <option value="cleared">Cleared</option>
                <option value="pending">Pending</option>
                <option value="reconciled">Reconciled</option>
              </select>
            </div>
          </div>

          <div class="filter-actions">
            <button @click="clearFilters" class="btn btn-outline btn-sm">
              <i class="fas fa-eraser"></i>
              Clear Filters
            </button>
            <div class="quick-filters">
              <button @click="setQuickFilter('today')" class="quick-filter-btn" :class="{ active: quickFilter === 'today' }">
                Today
              </button>
              <button @click="setQuickFilter('week')" class="quick-filter-btn" :class="{ active: quickFilter === 'week' }">
                This Week
              </button>
              <button @click="setQuickFilter('month')" class="quick-filter-btn" :class="{ active: quickFilter === 'month' }">
                This Month
              </button>
              <button @click="setQuickFilter('quarter')" class="quick-filter-btn" :class="{ active: quickFilter === 'quarter' }">
                This Quarter
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Transaction Statistics -->
    <div class="stats-section" v-if="transactionStats">
      <div class="stat-card">
        <div class="stat-icon income">
          <i class="fas fa-arrow-up"></i>
        </div>
        <div class="stat-content">
          <div class="stat-label">Total Credits</div>
          <div class="stat-value positive">{{ formatCurrency(transactionStats.totalCredits) }}</div>
          <div class="stat-count">{{ transactionStats.creditCount }} transactions</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon expense">
          <i class="fas fa-arrow-down"></i>
        </div>
        <div class="stat-content">
          <div class="stat-label">Total Debits</div>
          <div class="stat-value negative">{{ formatCurrency(transactionStats.totalDebits) }}</div>
          <div class="stat-count">{{ transactionStats.debitCount }} transactions</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon net">
          <i class="fas fa-equals"></i>
        </div>
        <div class="stat-content">
          <div class="stat-label">Net Movement</div>
          <div class="stat-value" :class="getBalanceClass(transactionStats.netAmount)">
            {{ formatCurrency(transactionStats.netAmount) }}
          </div>
          <div class="stat-count">{{ transactionStats.totalCount }} total</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon average">
          <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-content">
          <div class="stat-label">Average Transaction</div>
          <div class="stat-value">{{ formatCurrency(transactionStats.averageAmount) }}</div>
          <div class="stat-count">Per transaction</div>
        </div>
      </div>
    </div>

    <!-- Transactions List -->
    <div class="transactions-section">
      <div class="section-header">
        <h3>
          <i class="fas fa-list"></i>
          Transactions
          <span class="transaction-count" v-if="filteredTransactions.length > 0">
            ({{ filteredTransactions.length }} {{ filteredTransactions.length === 1 ? 'transaction' : 'transactions' }})
          </span>
        </h3>
        <div class="sort-controls">
          <select v-model="sortBy" @change="sortTransactions" class="sort-select">
            <option value="transaction_date">Date</option>
            <option value="amount">Amount</option>
            <option value="description">Description</option>
            <option value="reference">Reference</option>
          </select>
          <button @click="toggleSortOrder" class="sort-order-btn" :title="sortOrder === 'desc' ? 'Sort Ascending' : 'Sort Descending'">
            <i class="fas fa-sort-amount-down" v-if="sortOrder === 'desc'"></i>
            <i class="fas fa-sort-amount-up" v-if="sortOrder === 'asc'"></i>
          </button>
        </div>
      </div>

      <!-- Transaction List -->
      <div class="transactions-list" v-if="!loading && filteredTransactions.length > 0">
        <div
          v-for="transaction in paginatedTransactions"
          :key="transaction.transaction_id"
          class="transaction-item"
          @click="viewTransactionDetail(transaction)"
        >
          <div class="transaction-date">
            <div class="date-main">{{ formatTransactionDate(transaction.transaction_date) }}</div>
            <div class="date-time">{{ formatTransactionTime(transaction.transaction_date) }}</div>
          </div>

          <div class="transaction-content">
            <div class="transaction-header">
              <div class="transaction-description">
                <h4>{{ transaction.description || 'No description' }}</h4>
                <p v-if="transaction.reference">Ref: {{ transaction.reference }}</p>
              </div>
              <div class="transaction-amount" :class="getTransactionAmountClass(transaction)">
                <span class="amount-sign">{{ transaction.type === 'debit' ? '-' : '+' }}</span>
                <span class="amount-value">{{ formatCurrency(Math.abs(transaction.amount)) }}</span>
              </div>
            </div>

            <div class="transaction-details">
              <div class="detail-tags">
                <span class="transaction-type" :class="transaction.type">
                  {{ transaction.type.toUpperCase() }}
                </span>
                <span class="transaction-status" :class="transaction.status">
                  {{ transaction.status.toUpperCase() }}
                </span>
                <span class="transaction-category" v-if="transaction.category">
                  {{ transaction.category }}
                </span>
              </div>
              
              <div class="balance-after" v-if="transaction.balance_after">
                Balance: {{ formatCurrency(transaction.balance_after) }}
              </div>
            </div>

            <div class="transaction-meta" v-if="transaction.notes || transaction.reconciliation_id">
              <div class="transaction-notes" v-if="transaction.notes">
                <i class="fas fa-sticky-note"></i>
                {{ transaction.notes }}
              </div>
              <div class="reconciliation-info" v-if="transaction.reconciliation_id">
                <i class="fas fa-check-circle"></i>
                Reconciled
              </div>
            </div>
          </div>

          <div class="transaction-actions">
            <button class="action-btn" @click.stop="editTransaction(transaction)" title="Edit Transaction">
              <i class="fas fa-edit"></i>
            </button>
            <button class="action-btn" @click.stop="duplicateTransaction(transaction)" title="Duplicate Transaction">
              <i class="fas fa-copy"></i>
            </button>
            <button class="action-btn delete" @click.stop="deleteTransaction(transaction)" title="Delete Transaction">
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="!loading && filteredTransactions.length === 0" class="empty-state">
        <div class="empty-content">
          <div class="empty-icon">
            <i class="fas fa-exchange-alt"></i>
          </div>
          <h3>No Transactions Found</h3>
          <p v-if="hasActiveFilters">
            No transactions match your current filters. Try adjusting your search criteria.
          </p>
          <p v-else>
            No transactions have been recorded for this account yet.
          </p>
          <div class="empty-actions">
            <button @click="clearFilters" v-if="hasActiveFilters" class="btn btn-secondary">
              Clear Filters
            </button>
            <button @click="addTransaction" class="btn btn-primary">
              <i class="fas fa-plus"></i>
              Add Transaction
            </button>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-state">
        <div class="loading-spinner"></div>
        <p>Loading transactions...</p>
      </div>

      <!-- Pagination -->
      <div class="pagination-section" v-if="!loading && filteredTransactions.length > 0">
        <div class="pagination-info">
          Showing {{ paginationStart }} to {{ paginationEnd }} of {{ filteredTransactions.length }} transactions
        </div>
        <div class="pagination-controls">
          <button
            @click="previousPage"
            :disabled="currentPage === 1"
            class="pagination-btn"
          >
            <i class="fas fa-chevron-left"></i>
            Previous
          </button>
          
          <div class="page-numbers">
            <button
              v-for="page in visiblePages"
              :key="page"
              @click="goToPage(page)"
              class="page-btn"
              :class="{ active: page === currentPage }"
            >
              {{ page }}
            </button>
          </div>
          
          <button
            @click="nextPage"
            :disabled="currentPage === totalPages"
            class="pagination-btn"
          >
            Next
            <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Transaction Detail Modal -->
    <div v-if="showTransactionModal && selectedTransaction" class="modal-overlay" @click="closeTransactionModal">
      <div class="modal-content transaction-modal" @click.stop>
        <div class="modal-header">
          <h3>Transaction Details</h3>
          <button @click="closeTransactionModal" class="close-btn">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <div class="modal-body">
          <div class="transaction-detail-grid">
            <div class="detail-section">
              <h4>Basic Information</h4>
              <div class="detail-row">
                <div class="detail-item">
                  <label>Date & Time</label>
                  <value>{{ formatDateTime(selectedTransaction.transaction_date) }}</value>
                </div>
                <div class="detail-item">
                  <label>Amount</label>
                  <value class="amount" :class="getTransactionAmountClass(selectedTransaction)">
                    {{ selectedTransaction.type === 'debit' ? '-' : '+' }}{{ formatCurrency(Math.abs(selectedTransaction.amount)) }}
                  </value>
                </div>
              </div>
              
              <div class="detail-row">
                <div class="detail-item">
                  <label>Type</label>
                  <value>
                    <span class="transaction-type" :class="selectedTransaction.type">
                      {{ selectedTransaction.type.toUpperCase() }}
                    </span>
                  </value>
                </div>
                <div class="detail-item">
                  <label>Status</label>
                  <value>
                    <span class="transaction-status" :class="selectedTransaction.status">
                      {{ selectedTransaction.status.toUpperCase() }}
                    </span>
                  </value>
                </div>
              </div>
            </div>

            <div class="detail-section">
              <h4>Description & Reference</h4>
              <div class="detail-row">
                <div class="detail-item full-width">
                  <label>Description</label>
                  <value>{{ selectedTransaction.description || 'No description provided' }}</value>
                </div>
              </div>
              
              <div class="detail-row" v-if="selectedTransaction.reference">
                <div class="detail-item full-width">
                  <label>Reference Number</label>
                  <value>{{ selectedTransaction.reference }}</value>
                </div>
              </div>

              <div class="detail-row" v-if="selectedTransaction.notes">
                <div class="detail-item full-width">
                  <label>Notes</label>
                  <value class="notes">{{ selectedTransaction.notes }}</value>
                </div>
              </div>
            </div>

            <div class="detail-section" v-if="selectedTransaction.category || selectedTransaction.balance_after">
              <h4>Additional Information</h4>
              <div class="detail-row">
                <div class="detail-item" v-if="selectedTransaction.category">
                  <label>Category</label>
                  <value>{{ selectedTransaction.category }}</value>
                </div>
                <div class="detail-item" v-if="selectedTransaction.balance_after">
                  <label>Balance After Transaction</label>
                  <value :class="getBalanceClass(selectedTransaction.balance_after)">
                    {{ formatCurrency(selectedTransaction.balance_after) }}
                  </value>
                </div>
              </div>
            </div>

            <div class="detail-section" v-if="selectedTransaction.reconciliation_id">
              <h4>Reconciliation</h4>
              <div class="reconciliation-details">
                <i class="fas fa-check-circle"></i>
                <span>This transaction has been reconciled</span>
                <button class="btn btn-sm btn-secondary">
                  View Reconciliation
                </button>
              </div>
            </div>
          </div>
        </div>
        
        <div class="modal-actions">
          <button @click="closeTransactionModal" class="btn btn-secondary">
            Close
          </button>
          <button @click="editTransaction(selectedTransaction)" class="btn btn-primary">
            <i class="fas fa-edit"></i>
            Edit Transaction
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'

export default {
  name: 'BankTransactionHistory',
  setup() {
    const router = useRouter()
    const route = useRoute()
    
    // Reactive data
    const transactions = ref([])
    const selectedAccount = ref(null)
    const loading = ref(false)
    const searchQuery = ref('')
    const sortBy = ref('transaction_date')
    const sortOrder = ref('desc')
    const quickFilter = ref('')
    const currentPage = ref(1)
    const itemsPerPage = ref(20)
    const showTransactionModal = ref(false)
    const selectedTransaction = ref(null)
    
    const filters = ref({
      dateFrom: '',
      dateTo: '',
      type: '',
      amountFrom: '',
      amountTo: '',
      status: ''
    })

    // Computed properties
    const bankAccountId = computed(() => route.params.bankId || route.query.bank_id)
    
    const filteredTransactions = computed(() => {
      let filtered = transactions.value

      // Apply search filter
      if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase()
        filtered = filtered.filter(transaction =>
          transaction.description?.toLowerCase().includes(query) ||
          transaction.reference?.toLowerCase().includes(query) ||
          transaction.notes?.toLowerCase().includes(query) ||
          transaction.amount.toString().includes(query)
        )
      }

      // Apply date filters
      if (filters.value.dateFrom) {
        filtered = filtered.filter(transaction =>
          new Date(transaction.transaction_date) >= new Date(filters.value.dateFrom)
        )
      }
      
      if (filters.value.dateTo) {
        filtered = filtered.filter(transaction =>
          new Date(transaction.transaction_date) <= new Date(filters.value.dateTo)
        )
      }

      // Apply type filter
      if (filters.value.type) {
        filtered = filtered.filter(transaction => transaction.type === filters.value.type)
      }

      // Apply amount filters
      if (filters.value.amountFrom) {
        filtered = filtered.filter(transaction =>
          Math.abs(transaction.amount) >= parseFloat(filters.value.amountFrom)
        )
      }
      
      if (filters.value.amountTo) {
        filtered = filtered.filter(transaction =>
          Math.abs(transaction.amount) <= parseFloat(filters.value.amountTo)
        )
      }

      // Apply status filter
      if (filters.value.status) {
        filtered = filtered.filter(transaction => transaction.status === filters.value.status)
      }

      // Apply sorting
      filtered.sort((a, b) => {
        let aValue = a[sortBy.value]
        let bValue = b[sortBy.value]

        if (sortBy.value === 'amount') {
          aValue = Math.abs(parseFloat(aValue))
          bValue = Math.abs(parseFloat(bValue))
        } else if (sortBy.value === 'transaction_date') {
          aValue = new Date(aValue)
          bValue = new Date(bValue)
        } else {
          aValue = String(aValue || '').toLowerCase()
          bValue = String(bValue || '').toLowerCase()
        }

        if (sortOrder.value === 'desc') {
          return aValue < bValue ? 1 : -1
        }
        return aValue > bValue ? 1 : -1
      })

      return filtered
    })

    const paginatedTransactions = computed(() => {
      const start = (currentPage.value - 1) * itemsPerPage.value
      const end = start + itemsPerPage.value
      return filteredTransactions.value.slice(start, end)
    })

    const totalPages = computed(() => {
      return Math.ceil(filteredTransactions.value.length / itemsPerPage.value)
    })

    const paginationStart = computed(() => {
      return (currentPage.value - 1) * itemsPerPage.value + 1
    })

    const paginationEnd = computed(() => {
      return Math.min(currentPage.value * itemsPerPage.value, filteredTransactions.value.length)
    })

    const visiblePages = computed(() => {
      const pages = []
      const start = Math.max(1, currentPage.value - 2)
      const end = Math.min(totalPages.value, start + 4)
      
      for (let i = start; i <= end; i++) {
        pages.push(i)
      }
      return pages
    })

    const transactionStats = computed(() => {
      if (filteredTransactions.value.length === 0) return null

      let totalCredits = 0
      let totalDebits = 0
      let creditCount = 0
      let debitCount = 0

      filteredTransactions.value.forEach(transaction => {
        if (transaction.type === 'credit') {
          totalCredits += parseFloat(transaction.amount)
          creditCount++
        } else {
          totalDebits += parseFloat(transaction.amount)
          debitCount++
        }
      })

      const netAmount = totalCredits - totalDebits
      const totalCount = filteredTransactions.value.length
      const averageAmount = totalCount > 0 ? (totalCredits + totalDebits) / totalCount : 0

      return {
        totalCredits,
        totalDebits,
        creditCount,
        debitCount,
        netAmount,
        totalCount,
        averageAmount
      }
    })

    const hasActiveFilters = computed(() => {
      return Object.values(filters.value).some(filter => filter !== '') ||
             searchQuery.value !== '' ||
             quickFilter.value !== ''
    })

    // Methods
    const fetchTransactions = async () => {
      loading.value = true
      try {
        let url = '/bank-transactions'
        const params = {}
        
        if (bankAccountId.value) {
          params.bank_id = bankAccountId.value
        }
        
        const response = await axios.get(url, { params })
        transactions.value = response.data.data || []
      } catch (error) {
        console.error('Error fetching transactions:', error)
        showNotification('Error fetching transactions', 'error')
      } finally {
        loading.value = false
      }
    }

    const fetchBankAccount = async () => {
      if (!bankAccountId.value) return
      
      try {
        const response = await axios.get(`/accounting/bank-accounts/${bankAccountId.value}`)
        selectedAccount.value = response.data.data
      } catch (error) {
        console.error('Error fetching bank account:', error)
      }
    }

    const refreshData = () => {
      fetchTransactions()
      if (bankAccountId.value) {
        fetchBankAccount()
      }
    }

    const handleSearch = () => {
      currentPage.value = 1
    }

    const applyFilters = () => {
      currentPage.value = 1
    }

    const clearFilters = () => {
      filters.value = {
        dateFrom: '',
        dateTo: '',
        type: '',
        amountFrom: '',
        amountTo: '',
        status: ''
      }
      searchQuery.value = ''
      quickFilter.value = ''
      currentPage.value = 1
    }

    const setQuickFilter = (period) => {
      const now = new Date()
      const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
      
      quickFilter.value = period
      
      switch (period) {
        case 'today':
          filters.value.dateFrom = today.toISOString().split('T')[0]
          filters.value.dateTo = today.toISOString().split('T')[0]
          break
        case 'week':
          const weekStart = new Date(today)
          weekStart.setDate(today.getDate() - today.getDay())
          filters.value.dateFrom = weekStart.toISOString().split('T')[0]
          filters.value.dateTo = today.toISOString().split('T')[0]
          break
        case 'month':
          const monthStart = new Date(today.getFullYear(), today.getMonth(), 1)
          filters.value.dateFrom = monthStart.toISOString().split('T')[0]
          filters.value.dateTo = today.toISOString().split('T')[0]
          break
        case 'quarter':
          const quarterStart = new Date(today.getFullYear(), Math.floor(today.getMonth() / 3) * 3, 1)
          filters.value.dateFrom = quarterStart.toISOString().split('T')[0]
          filters.value.dateTo = today.toISOString().split('T')[0]
          break
      }
      
      applyFilters()
    }

    const sortTransactions = () => {
      // Sorting is reactive through computed property
    }

    const toggleSortOrder = () => {
      sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
    }

    const viewTransactionDetail = (transaction) => {
      selectedTransaction.value = transaction
      showTransactionModal.value = true
    }

    const closeTransactionModal = () => {
      showTransactionModal.value = false
      selectedTransaction.value = null
    }

    const editTransaction = (transaction) => {
      router.push(`/bank-transactions/${transaction.transaction_id}/edit`)
    }

    const duplicateTransaction = (transaction) => {
      router.push({
        name: 'CreateBankTransaction',
        query: { duplicate: transaction.transaction_id }
      })
    }

    const deleteTransaction = (transaction) => {
      // Implement delete functionality
      showNotification('Delete functionality coming soon', 'info')
    }

    const addTransaction = () => {
      const query = bankAccountId.value ? { bank_id: bankAccountId.value } : {}
      router.push({ name: 'CreateBankTransaction', query })
    }

    const exportTransactions = () => {
      // Implement export functionality
      showNotification('Export functionality coming soon', 'info')
    }

    // Pagination methods
    const goToPage = (page) => {
      currentPage.value = page
    }

    const previousPage = () => {
      if (currentPage.value > 1) {
        currentPage.value--
      }
    }

    const nextPage = () => {
      if (currentPage.value < totalPages.value) {
        currentPage.value++
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

    const formatTransactionDate = (date) => {
      return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
      })
    }

    const formatTransactionTime = (date) => {
      return new Date(date).toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
      })
    }

    const formatDateTime = (date) => {
      return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      })
    }

    const getBalanceClass = (balance) => {
      const amount = parseFloat(balance) || 0
      if (amount > 0) return 'positive'
      if (amount < 0) return 'negative'
      return 'zero'
    }

    const getTransactionAmountClass = (transaction) => {
      return transaction.type === 'credit' ? 'positive' : 'negative'
    }

    const showNotification = (message, type = 'info') => {
      // Implement notification system here
      console.log(`${type.toUpperCase()}: ${message}`)
    }

    // Watchers
    watch(() => route.params.bankId, (newId) => {
      if (newId) {
        fetchBankAccount()
        fetchTransactions()
      }
    })

    watch(() => route.query.bank_id, (newId) => {
      if (newId) {
        fetchBankAccount()
        fetchTransactions()
      }
    })

    // Lifecycle
    onMounted(() => {
      if (bankAccountId.value) {
        fetchBankAccount()
      }
      fetchTransactions()
    })

    return {
      transactions,
      selectedAccount,
      loading,
      searchQuery,
      sortBy,
      sortOrder,
      quickFilter,
      currentPage,
      itemsPerPage,
      showTransactionModal,
      selectedTransaction,
      filters,
      bankAccountId,
      filteredTransactions,
      paginatedTransactions,
      totalPages,
      paginationStart,
      paginationEnd,
      visiblePages,
      transactionStats,
      hasActiveFilters,
      refreshData,
      handleSearch,
      applyFilters,
      clearFilters,
      setQuickFilter,
      sortTransactions,
      toggleSortOrder,
      viewTransactionDetail,
      closeTransactionModal,
      editTransaction,
      duplicateTransaction,
      deleteTransaction,
      addTransaction,
      exportTransactions,
      goToPage,
      previousPage,
      nextPage,
      formatCurrency,
      formatAccountNumber,
      formatTransactionDate,
      formatTransactionTime,
      formatDateTime,
      getBalanceClass,
      getTransactionAmountClass
    }
  }
}
</script>

<style scoped>
.bank-transaction-history-container {
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

.bank-name {
  font-weight: 600;
}

.separator {
  color: var(--gray-400);
}

.header-actions {
  display: flex;
  gap: 1rem;
  flex-shrink: 0;
}

/* Account Summary Card */
.account-summary-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  margin-bottom: 2rem;
  overflow: hidden;
}

.summary-content {
  padding: 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.account-info {
  display: flex;
  gap: 1rem;
  align-items: center;
  flex: 1;
}

.account-icon {
  width: 64px;
  height: 64px;
  background: var(--primary-color);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.5rem;
  flex-shrink: 0;
}

.account-details h3 {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 0.25rem 0;
}

.account-details p {
  color: var(--gray-700);
  margin: 0 0 0.5rem 0;
}

.account-number {
  font-family: 'Courier New', monospace;
  background: var(--gray-100);
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.85rem;
}

.balance-info {
  text-align: right;
}

.balance-label {
  font-size: 0.9rem;
  color: var(--gray-600);
  margin-bottom: 0.25rem;
}

.balance-amount {
  font-size: 2rem;
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

/* Filters Card */
.filters-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  margin-bottom: 2rem;
  overflow: hidden;
}

.filters-content {
  padding: 2rem;
}

.search-section {
  margin-bottom: 2rem;
}

.search-wrapper {
  position: relative;
  max-width: 500px;
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

.filter-section {
  border-top: 1px solid var(--gray-200);
  padding-top: 2rem;
}

.filter-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.filter-group {
  display: flex;
  flex-direction: column;
}

.filter-group label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--gray-700);
  margin-bottom: 0.5rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.date-range,
.amount-range {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.date-input,
.amount-input,
.filter-select {
  padding: 0.5rem 0.75rem;
  border: 2px solid var(--gray-200);
  border-radius: 6px;
  font-size: 0.9rem;
  background: white;
  transition: all 0.2s ease;
}

.date-input:focus,
.amount-input:focus,
.filter-select:focus {
  outline: none;
  border-color: var(--primary-color);
}

.date-separator,
.amount-separator {
  color: var(--gray-400);
  font-size: 0.85rem;
}

.filter-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}

.quick-filters {
  display: flex;
  gap: 0.5rem;
}

.quick-filter-btn {
  padding: 0.5rem 1rem;
  border: 2px solid var(--gray-200);
  border-radius: 20px;
  background: white;
  color: var(--gray-700);
  font-size: 0.85rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
}

.quick-filter-btn:hover {
  border-color: var(--primary-color);
  color: var(--primary-color);
}

.quick-filter-btn.active {
  background: var(--primary-color);
  border-color: var(--primary-color);
  color: white;
}

/* Statistics Section */
.stats-section {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  padding: 1.5rem;
  display: flex;
  gap: 1rem;
  align-items: center;
}

.stat-icon {
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

.stat-icon.income {
  background: var(--success-color);
}

.stat-icon.expense {
  background: var(--danger-color);
}

.stat-icon.net {
  background: var(--primary-color);
}

.stat-icon.average {
  background: var(--warning-color);
}

.stat-content {
  flex: 1;
}

.stat-label {
  font-size: 0.85rem;
  color: var(--gray-600);
  margin-bottom: 0.25rem;
  font-weight: 500;
}

.stat-value {
  font-size: 1.5rem;
  font-weight: 700;
  margin-bottom: 0.25rem;
}

.stat-value.positive {
  color: var(--success-color);
}

.stat-value.negative {
  color: var(--danger-color);
}

.stat-count {
  font-size: 0.75rem;
  color: var(--gray-500);
}

/* Transactions Section */
.transactions-section {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.section-header {
  padding: 1.5rem 2rem;
  border-bottom: 1px solid var(--gray-200);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.section-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.section-header h3 i {
  color: var(--primary-color);
}

.transaction-count {
  font-size: 0.9rem;
  color: var(--gray-500);
  font-weight: 400;
}

.sort-controls {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.sort-select {
  padding: 0.5rem 0.75rem;
  border: 2px solid var(--gray-200);
  border-radius: 6px;
  font-size: 0.85rem;
  background: white;
}

.sort-order-btn {
  width: 36px;
  height: 36px;
  border: 2px solid var(--gray-200);
  border-radius: 6px;
  background: white;
  color: var(--gray-600);
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.sort-order-btn:hover {
  border-color: var(--primary-color);
  color: var(--primary-color);
}

/* Transactions List */
.transactions-list {
  display: flex;
  flex-direction: column;
}

.transaction-item {
  padding: 1.5rem 2rem;
  border-bottom: 1px solid var(--gray-100);
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  gap: 1.5rem;
}

.transaction-item:hover {
  background: var(--gray-50);
}

.transaction-item:last-child {
  border-bottom: none;
}

.transaction-date {
  flex-shrink: 0;
  text-align: center;
  min-width: 80px;
}

.date-main {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--gray-900);
}

.date-time {
  font-size: 0.75rem;
  color: var(--gray-500);
}

.transaction-content {
  flex: 1;
}

.transaction-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 0.75rem;
}

.transaction-description h4 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 0.25rem 0;
}

.transaction-description p {
  font-size: 0.85rem;
  color: var(--gray-600);
  margin: 0;
}

.transaction-amount {
  text-align: right;
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 1.1rem;
  font-weight: 700;
}

.transaction-amount.positive {
  color: var(--success-color);
}

.transaction-amount.negative {
  color: var(--danger-color);
}

.amount-sign {
  font-size: 0.9rem;
}

.transaction-details {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.detail-tags {
  display: flex;
  gap: 0.5rem;
}

.transaction-type,
.transaction-status,
.transaction-category {
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.transaction-type.credit {
  background: rgba(5, 150, 105, 0.1);
  color: var(--success-color);
}

.transaction-type.debit {
  background: rgba(220, 38, 38, 0.1);
  color: var(--danger-color);
}

.transaction-status.cleared {
  background: rgba(5, 150, 105, 0.1);
  color: var(--success-color);
}

.transaction-status.pending {
  background: rgba(217, 119, 6, 0.1);
  color: var(--warning-color);
}

.transaction-status.reconciled {
  background: rgba(37, 99, 235, 0.1);
  color: var(--primary-color);
}

.transaction-category {
  background: rgba(99, 102, 241, 0.1);
  color: #6366f1;
}

.balance-after {
  font-size: 0.85rem;
  color: var(--gray-600);
}

.transaction-meta {
  display: flex;
  gap: 1rem;
  align-items: center;
  font-size: 0.85rem;
  color: var(--gray-600);
}

.transaction-notes,
.reconciliation-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.transaction-actions {
  display: flex;
  gap: 0.5rem;
  flex-shrink: 0;
  opacity: 0;
  transition: opacity 0.2s ease;
}

.transaction-item:hover .transaction-actions {
  opacity: 1;
}

.action-btn {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 6px;
  background: var(--gray-100);
  color: var(--gray-600);
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
}

.action-btn:hover {
  background: var(--primary-color);
  color: white;
}

.action-btn.delete:hover {
  background: var(--danger-color);
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

.empty-actions {
  display: flex;
  gap: 1rem;
  justify-content: center;
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

/* Pagination */
.pagination-section {
  padding: 1.5rem 2rem;
  border-top: 1px solid var(--gray-200);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.pagination-info {
  font-size: 0.85rem;
  color: var(--gray-600);
}

.pagination-controls {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.pagination-btn {
  padding: 0.5rem 1rem;
  border: 2px solid var(--gray-200);
  border-radius: 6px;
  background: white;
  color: var(--gray-700);
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
}

.pagination-btn:hover:not(:disabled) {
  border-color: var(--primary-color);
  color: var(--primary-color);
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.page-numbers {
  display: flex;
  gap: 0.25rem;
}

.page-btn {
  width: 36px;
  height: 36px;
  border: 2px solid var(--gray-200);
  border-radius: 6px;
  background: white;
  color: var(--gray-700);
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
}

.page-btn:hover {
  border-color: var(--primary-color);
  color: var(--primary-color);
}

.page-btn.active {
  background: var(--primary-color);
  border-color: var(--primary-color);
  color: white;
}

/* Transaction Detail Modal */
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
  max-width: 800px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
}

.transaction-modal .modal-body {
  padding: 2rem;
}

.modal-header {
  padding: 1.5rem 2rem 1rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--gray-200);
}

.modal-header h3 {
  margin: 0;
  color: var(--gray-900);
  font-size: 1.25rem;
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

.transaction-detail-grid {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.detail-section {
  background: var(--gray-50);
  padding: 1.5rem;
  border-radius: 8px;
}

.detail-section h4 {
  font-size: 1rem;
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 1rem 0;
  border-bottom: 1px solid var(--gray-200);
  padding-bottom: 0.5rem;
}

.detail-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  margin-bottom: 1rem;
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
  font-size: 0.8rem;
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

.detail-item value.amount {
  font-size: 1.25rem;
  font-weight: 700;
}

.detail-item value.notes {
  background: white;
  padding: 1rem;
  border-radius: 6px;
  line-height: 1.6;
  border: 1px solid var(--gray-200);
}

.reconciliation-details {
  display: flex;
  align-items: center;
  gap: 1rem;
  color: var(--success-color);
}

.modal-actions {
  padding: 1rem 2rem 2rem 2rem;
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
  border-top: 1px solid var(--gray-200);
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

.btn-outline {
  background: transparent;
  color: var(--gray-700);
  border: 2px solid var(--gray-200);
}

.btn-outline:hover {
  background: var(--gray-50);
  border-color: var(--gray-300);
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

/* Responsive Design */
@media (max-width: 1024px) {
  .filter-row {
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  }
  
  .stats-section {
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  }
}

@media (max-width: 768px) {
  .bank-transaction-history-container {
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

  .summary-content {
    flex-direction: column;
    gap: 1.5rem;
    text-align: center;
  }

  .filters-content {
    padding: 1.5rem;
  }

  .filter-row {
    grid-template-columns: 1fr;
  }

  .filter-actions {
    flex-direction: column;
    gap: 1rem;
  }

  .quick-filters {
    flex-wrap: wrap;
    justify-content: center;
  }

  .stats-section {
    grid-template-columns: 1fr;
  }

  .section-header {
    padding: 1rem 1.5rem;
    flex-direction: column;
    gap: 1rem;
  }

  .transaction-item {
    padding: 1rem 1.5rem;
    flex-direction: column;
    gap: 1rem;
  }

  .transaction-header {
    flex-direction: column;
    gap: 0.5rem;
  }

  .transaction-amount {
    justify-content: flex-start;
  }

  .transaction-actions {
    opacity: 1;
    justify-content: center;
  }

  .pagination-section {
    padding: 1rem 1.5rem;
    flex-direction: column;
    gap: 1rem;
  }

  .pagination-controls {
    justify-content: center;
  }

  .detail-row {
    grid-template-columns: 1fr;
  }

  .modal-content {
    margin: 1rem;
    max-height: calc(100vh - 2rem);
  }
}

@media (max-width: 480px) {
  .page-title {
    font-size: 1.5rem;
  }

  .account-icon {
    width: 48px;
    height: 48px;
    font-size: 1.2rem;
  }

  .balance-amount {
    font-size: 1.5rem;
  }

  .quick-filter-btn {
    padding: 0.4rem 0.8rem;
    font-size: 0.8rem;
  }

  .page-numbers {
    gap: 0.125rem;
  }

  .page-btn {
    width: 32px;
    height: 32px;
    font-size: 0.8rem;
  }
}
</style>