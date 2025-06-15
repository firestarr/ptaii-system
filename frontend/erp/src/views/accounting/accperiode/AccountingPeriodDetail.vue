<template>
  <div class="period-detail-page">
    <!-- Loading State -->
    <div v-if="loading" class="loading-container">
      <div class="spinner"></div>
      <p>Loading period details...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-triangle"></i>
      <h2>Error Loading Period</h2>
      <p>{{ error }}</p>
      <button @click="goBack" class="btn btn-primary">
        <i class="fas fa-arrow-left"></i>
        Go Back
      </button>
    </div>

    <!-- Main Content -->
    <div v-else class="detail-content">
      <!-- Header -->
      <div class="page-header">
        <div class="header-content">
          <div class="header-left">
            <button @click="goBack" class="back-btn">
              <i class="fas fa-arrow-left"></i>
            </button>
            <div class="title-section">
              <div class="period-title">
                <h1>{{ period.period_name }}</h1>
                <span class="status-badge" :class="period.status.toLowerCase()">
                  {{ period.status }}
                </span>
              </div>
              <p class="period-subtitle">
                {{ formatDate(period.start_date) }} - {{ formatDate(period.end_date) }}
                ({{ periodDuration }} days)
              </p>
            </div>
          </div>
          <div class="header-actions">
            <button @click="refreshData" class="btn btn-secondary" :disabled="refreshing">
              <i class="fas fa-sync-alt" :class="{ 'fa-spin': refreshing }"></i>
              Refresh
            </button>
            <button @click="editPeriod" class="btn btn-primary" :disabled="period.status === 'Locked'">
              <i class="fas fa-edit"></i>
              Edit Period
            </button>
            <div class="dropdown" v-if="period.status === 'Open'">
              <button @click="toggleDropdown" class="btn btn-outline dropdown-toggle">
                <i class="fas fa-ellipsis-v"></i>
                Actions
              </button>
              <div v-if="showDropdown" class="dropdown-menu">
                <button @click="closePeriod" class="dropdown-item">
                  <i class="fas fa-lock"></i>
                  Close Period
                </button>
                <button @click="lockPeriod" class="dropdown-item">
                  <i class="fas fa-shield-alt"></i>
                  Lock Period
                </button>
                <div class="dropdown-divider"></div>
                <button @click="deletePeriod" class="dropdown-item danger">
                  <i class="fas fa-trash"></i>
                  Delete Period
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Summary Cards -->
      <div class="summary-section">
        <div class="summary-grid">
          <!-- Period Information -->
          <div class="summary-card primary">
            <div class="card-header">
              <h3>
                <i class="fas fa-info-circle"></i>
                Period Information
              </h3>
            </div>
            <div class="card-body">
              <div class="info-grid">
                <div class="info-item">
                  <label>Period ID</label>
                  <value>{{ period.period_id }}</value>
                </div>
                <div class="info-item">
                  <label>Duration</label>
                  <value>{{ periodDuration }} days</value>
                </div>
                <div class="info-item">
                  <label>Period Type</label>
                  <value>{{ getPeriodType() }}</value>
                </div>
                <div class="info-item">
                  <label>Fiscal Year</label>
                  <value>{{ getFiscalYear() }}</value>
                </div>
              </div>
            </div>
          </div>

          <!-- Transaction Summary -->
          <div class="summary-card success">
            <div class="card-header">
              <h3>
                <i class="fas fa-chart-line"></i>
                Transactions
              </h3>
            </div>
            <div class="card-body">
              <div class="stats-grid">
                <div class="stat-item">
                  <div class="stat-value">{{ statistics.totalTransactions }}</div>
                  <div class="stat-label">Total Entries</div>
                </div>
                <div class="stat-item">
                  <div class="stat-value">${{ formatCurrency(statistics.totalDebits) }}</div>
                  <div class="stat-label">Total Debits</div>
                </div>
                <div class="stat-item">
                  <div class="stat-value">${{ formatCurrency(statistics.totalCredits) }}</div>
                  <div class="stat-label">Total Credits</div>
                </div>
                <div class="stat-item">
                  <div class="stat-value">${{ formatCurrency(statistics.netAmount) }}</div>
                  <div class="stat-label">Net Amount</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Budget Information -->
          <div class="summary-card warning">
            <div class="card-header">
              <h3>
                <i class="fas fa-calculator"></i>
                Budget Summary
              </h3>
            </div>
            <div class="card-body">
              <div class="stats-grid">
                <div class="stat-item">
                  <div class="stat-value">${{ formatCurrency(statistics.totalBudgeted) }}</div>
                  <div class="stat-label">Budgeted</div>
                </div>
                <div class="stat-item">
                  <div class="stat-value">${{ formatCurrency(statistics.totalActual) }}</div>
                  <div class="stat-label">Actual</div>
                </div>
                <div class="stat-item">
                  <div class="stat-value" :class="{ 
                    positive: statistics.totalVariance > 0, 
                    negative: statistics.totalVariance < 0 
                  }">
                    ${{ formatCurrency(Math.abs(statistics.totalVariance)) }}
                  </div>
                  <div class="stat-label">Variance</div>
                </div>
                <div class="stat-item">
                  <div class="stat-value">{{ statistics.budgetUtilization }}%</div>
                  <div class="stat-label">Utilization</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Period Status -->
          <div class="summary-card info">
            <div class="card-header">
              <h3>
                <i class="fas fa-clock"></i>
                Period Status
              </h3>
            </div>
            <div class="card-body">
              <div class="status-info">
                <div class="status-item">
                  <label>Current Status</label>
                  <div class="status-value">
                    <span class="status-badge" :class="period.status.toLowerCase()">
                      {{ period.status }}
                    </span>
                  </div>
                </div>
                <div class="status-item">
                  <label>Days Remaining</label>
                  <div class="status-value">
                    <span :class="getDaysRemainingClass()">
                      {{ getDaysRemaining() }}
                    </span>
                  </div>
                </div>
                <div class="status-item">
                  <label>Progress</label>
                  <div class="progress-container">
                    <div class="progress-bar">
                      <div class="progress-fill" :style="{ width: `${getPeriodProgress()}%` }"></div>
                    </div>
                    <span class="progress-text">{{ getPeriodProgress() }}%</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabs Section -->
      <div class="tabs-section">
        <div class="tabs-header">
          <button 
            v-for="tab in tabs" 
            :key="tab.id" 
            @click="activeTab = tab.id"
            :class="['tab-btn', { active: activeTab === tab.id }]"
          >
            <i :class="tab.icon"></i>
            {{ tab.label }}
            <span v-if="tab.count !== undefined" class="tab-count">{{ tab.count }}</span>
          </button>
        </div>

        <div class="tabs-content">
          <!-- Journal Entries Tab -->
          <div v-show="activeTab === 'entries'" class="tab-panel">
            <div class="panel-header">
              <h3>Journal Entries</h3>
              <div class="panel-actions">
                <button @click="exportEntries" class="btn btn-outline">
                  <i class="fas fa-download"></i>
                  Export
                </button>
                <button @click="createEntry" class="btn btn-primary">
                  <i class="fas fa-plus"></i>
                  New Entry
                </button>
              </div>
            </div>
            
            <div v-if="journalEntries.length === 0" class="empty-state">
              <i class="fas fa-journal-whills"></i>
              <h4>No Journal Entries</h4>
              <p>No transactions have been recorded for this period yet.</p>
            </div>
            
            <div v-else class="entries-list">
              <div 
                v-for="entry in journalEntries" 
                :key="entry.journal_id" 
                class="entry-item"
                @click="viewEntry(entry)"
              >
                <div class="entry-main">
                  <div class="entry-header">
                    <span class="entry-number">{{ entry.journal_number }}</span>
                    <span class="entry-date">{{ formatDate(entry.entry_date) }}</span>
                    <span class="entry-status" :class="entry.status.toLowerCase()">
                      {{ entry.status }}
                    </span>
                  </div>
                  <div class="entry-description">{{ entry.description }}</div>
                  <div class="entry-reference" v-if="entry.reference_type">
                    {{ entry.reference_type }}: {{ entry.reference_id }}
                  </div>
                </div>
                <div class="entry-amount">
                  <div class="amount-value">${{ formatCurrency(getEntryAmount(entry)) }}</div>
                  <div class="amount-label">Total Amount</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Budgets Tab -->
          <div v-show="activeTab === 'budgets'" class="tab-panel">
            <div class="panel-header">
              <h3>Budget Items</h3>
              <div class="panel-actions">
                <button @click="exportBudgets" class="btn btn-outline">
                  <i class="fas fa-download"></i>
                  Export
                </button>
                <button @click="createBudget" class="btn btn-primary">
                  <i class="fas fa-plus"></i>
                  New Budget
                </button>
              </div>
            </div>
            
            <div v-if="budgets.length === 0" class="empty-state">
              <i class="fas fa-calculator"></i>
              <h4>No Budget Items</h4>
              <p>No budgets have been set up for this period yet.</p>
            </div>
            
            <div v-else class="budgets-list">
              <div 
                v-for="budget in budgets" 
                :key="budget.budget_id" 
                class="budget-item"
              >
                <div class="budget-main">
                  <div class="budget-account">{{ budget.account_name || 'Unknown Account' }}</div>
                  <div class="budget-amounts">
                    <div class="amount-group">
                      <span class="amount-label">Budgeted:</span>
                      <span class="amount-value">${{ formatCurrency(budget.budgeted_amount) }}</span>
                    </div>
                    <div class="amount-group">
                      <span class="amount-label">Actual:</span>
                      <span class="amount-value">${{ formatCurrency(budget.actual_amount) }}</span>
                    </div>
                    <div class="amount-group">
                      <span class="amount-label">Variance:</span>
                      <span class="amount-value" :class="{ 
                        positive: budget.variance > 0, 
                        negative: budget.variance < 0 
                      }">
                        ${{ formatCurrency(Math.abs(budget.variance)) }}
                      </span>
                    </div>
                  </div>
                </div>
                <div class="budget-progress">
                  <div class="progress-bar">
                    <div 
                      class="progress-fill" 
                      :style="{ width: `${getBudgetProgress(budget)}%` }"
                      :class="getBudgetProgressClass(budget)"
                    ></div>
                  </div>
                  <span class="progress-percentage">{{ getBudgetProgress(budget) }}%</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Assets Tab -->
          <div v-show="activeTab === 'assets'" class="tab-panel">
            <div class="panel-header">
              <h3>Asset Depreciations</h3>
              <div class="panel-actions">
                <button @click="exportAssets" class="btn btn-outline">
                  <i class="fas fa-download"></i>
                  Export
                </button>
                <button @click="calculateDepreciation" class="btn btn-primary">
                  <i class="fas fa-calculator"></i>
                  Calculate Depreciation
                </button>
              </div>
            </div>
            
            <div v-if="assetDepreciations.length === 0" class="empty-state">
              <i class="fas fa-building"></i>
              <h4>No Asset Depreciations</h4>
              <p>No asset depreciations have been calculated for this period yet.</p>
            </div>
            
            <div v-else class="assets-list">
              <div 
                v-for="asset in assetDepreciations" 
                :key="asset.depreciation_id" 
                class="asset-item"
              >
                <div class="asset-main">
                  <div class="asset-name">{{ asset.asset_name || 'Unknown Asset' }}</div>
                  <div class="asset-details">
                    <div class="detail-item">
                      <span class="detail-label">Depreciation Method:</span>
                      <span class="detail-value">{{ asset.depreciation_method || 'Unknown' }}</span>
                    </div>
                    <div class="detail-item">
                      <span class="detail-label">Depreciation Amount:</span>
                      <span class="detail-value">${{ formatCurrency(asset.depreciation_amount) }}</span>
                    </div>
                    <div class="detail-item">
                      <span class="detail-label">Accumulated:</span>
                      <span class="detail-value">${{ formatCurrency(asset.accumulated_depreciation) }}</span>
                    </div>
                  </div>
                </div>
                <div class="asset-status">
                  <span class="status-badge" :class="asset.status ? asset.status.toLowerCase() : 'active'">
                    {{ asset.status || 'Active' }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Activities Tab -->
          <div v-show="activeTab === 'activities'" class="tab-panel">
            <div class="panel-header">
              <h3>Period Activities</h3>
              <div class="panel-actions">
                <button @click="refreshActivities" class="btn btn-outline">
                  <i class="fas fa-sync-alt"></i>
                  Refresh
                </button>
              </div>
            </div>
            
            <div class="activities-timeline">
              <div 
                v-for="activity in activities" 
                :key="activity.id" 
                class="activity-item"
              >
                <div class="activity-icon" :class="activity.type">
                  <i :class="activity.icon"></i>
                </div>
                <div class="activity-content">
                  <div class="activity-header">
                    <span class="activity-title">{{ activity.title }}</span>
                    <span class="activity-time">{{ formatDateTime(activity.timestamp) }}</span>
                  </div>
                  <div class="activity-description">{{ activity.description }}</div>
                  <div v-if="activity.user" class="activity-user">
                    by {{ activity.user }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirmation Modals -->
    <div v-if="showCloseModal" class="modal-overlay" @click="closeModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>Close Accounting Period</h3>
          <button @click="closeModal" class="modal-close">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="warning-message">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
              <p><strong>Are you sure you want to close this period?</strong></p>
              <p>Closing this period will prevent new transactions from being entered, but you can still reopen it later if needed.</p>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="closeModal" class="btn btn-secondary">Cancel</button>
          <button @click="confirmClosePeriod" class="btn btn-warning" :disabled="processing">
            <i v-if="processing" class="fas fa-spinner fa-spin"></i>
            {{ processing ? 'Closing...' : 'Close Period' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="showLockModal" class="modal-overlay" @click="closeModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>Lock Accounting Period</h3>
          <button @click="closeModal" class="modal-close">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="warning-message">
            <i class="fas fa-shield-alt"></i>
            <div>
              <p><strong>Are you sure you want to lock this period?</strong></p>
              <p>Locking this period will permanently prevent any changes. This action cannot be undone!</p>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="closeModal" class="btn btn-secondary">Cancel</button>
          <button @click="confirmLockPeriod" class="btn btn-danger" :disabled="processing">
            <i v-if="processing" class="fas fa-spinner fa-spin"></i>
            {{ processing ? 'Locking...' : 'Lock Period' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="showDeleteModal" class="modal-overlay" @click="closeModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>Delete Accounting Period</h3>
          <button @click="closeModal" class="modal-close">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="warning-message">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
              <p><strong>Are you sure you want to delete this period?</strong></p>
              <p>This will permanently delete the period and all associated data. This action cannot be undone!</p>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="closeModal" class="btn btn-secondary">Cancel</button>
          <button @click="confirmDeletePeriod" class="btn btn-danger" :disabled="processing">
            <i v-if="processing" class="fas fa-spinner fa-spin"></i>
            {{ processing ? 'Deleting...' : 'Delete Period' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'AccountingPeriodDetail',
  data() {
    return {
      period: {},
      loading: true,
      refreshing: false,
      processing: false,
      error: null,
      activeTab: 'entries',
      showDropdown: false,
      showCloseModal: false,
      showLockModal: false,
      showDeleteModal: false,
      
      statistics: {
        totalTransactions: 0,
        totalDebits: 0,
        totalCredits: 0,
        netAmount: 0,
        totalBudgeted: 0,
        totalActual: 0,
        totalVariance: 0,
        budgetUtilization: 0
      },
      
      journalEntries: [],
      budgets: [],
      assetDepreciations: [],
      activities: [],
      
      tabs: [
        { id: 'entries', label: 'Journal Entries', icon: 'fas fa-journal-whills', count: 0 },
        { id: 'budgets', label: 'Budgets', icon: 'fas fa-calculator', count: 0 },
        { id: 'assets', label: 'Asset Depreciations', icon: 'fas fa-building', count: 0 },
        { id: 'activities', label: 'Activities', icon: 'fas fa-history', count: undefined }
      ]
    }
  },
  
  computed: {
    periodDuration() {
      if (!this.period.start_date || !this.period.end_date) return 0
      const start = new Date(this.period.start_date)
      const end = new Date(this.period.end_date)
      const diffTime = Math.abs(end - start)
      return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1
    }
  },
  
  async mounted() {
    await this.loadPeriodData()
    this.loadRelatedData()
    this.generateActivities()
    
    // Close dropdown when clicking outside
    document.addEventListener('click', this.handleOutsideClick)
  },
  
  beforeUnmount() {
    document.removeEventListener('click', this.handleOutsideClick)
  },
  
  methods: {
    async loadPeriodData() {
      try {
        this.loading = true
        const periodId = this.$route.params.id
        const response = await axios.get(`/accounting/accounting-periods/${periodId}`)
        this.period = response.data.data
      } catch (error) {
        console.error('Error loading period:', error)
        this.error = error.response?.data?.message || 'Failed to load period'
      } finally {
        this.loading = false
      }
    },
    
    async loadRelatedData() {
      try {
        // Simulate loading related data
        // In real implementation, these would be separate API calls
        await this.loadJournalEntries()
        await this.loadBudgets()
        await this.loadAssetDepreciations()
        this.calculateStatistics()
        this.updateTabCounts()
      } catch (error) {
        console.error('Error loading related data:', error)
      }
    },
    
    async loadJournalEntries() {
      // Simulate journal entries data
      this.journalEntries = [
        {
          journal_id: 1,
          journal_number: 'JE-2024-001',
          entry_date: '2024-01-15',
          description: 'Monthly office rent payment',
          reference_type: 'Invoice',
          reference_id: 'INV-001',
          status: 'Posted',
          total_amount: 2500.00
        },
        {
          journal_id: 2,
          journal_number: 'JE-2024-002',
          entry_date: '2024-01-20',
          description: 'Equipment purchase',
          reference_type: 'Purchase Order',
          reference_id: 'PO-002',
          status: 'Draft',
          total_amount: 15000.00
        }
      ]
    },
    
    async loadBudgets() {
      // Simulate budget data
      this.budgets = [
        {
          budget_id: 1,
          account_name: 'Office Rent',
          budgeted_amount: 30000.00,
          actual_amount: 27500.00,
          variance: 2500.00
        },
        {
          budget_id: 2,
          account_name: 'Equipment',
          budgeted_amount: 50000.00,
          actual_amount: 55000.00,
          variance: -5000.00
        }
      ]
    },
    
    async loadAssetDepreciations() {
      // Simulate asset depreciation data
      this.assetDepreciations = [
        {
          depreciation_id: 1,
          asset_name: 'Office Equipment',
          depreciation_method: 'Straight Line',
          depreciation_amount: 1250.00,
          accumulated_depreciation: 15000.00,
          status: 'Active'
        },
        {
          depreciation_id: 2,
          asset_name: 'Computer Systems',
          depreciation_method: 'Declining Balance',
          depreciation_amount: 850.00,
          accumulated_depreciation: 8500.00,
          status: 'Active'
        }
      ]
    },
    
    calculateStatistics() {
      // Calculate transaction statistics
      this.statistics.totalTransactions = this.journalEntries.length
      this.statistics.totalDebits = this.journalEntries.reduce((sum, entry) => sum + (entry.total_amount || 0), 0)
      this.statistics.totalCredits = this.statistics.totalDebits // Simplified
      this.statistics.netAmount = this.statistics.totalDebits - this.statistics.totalCredits
      
      // Calculate budget statistics
      this.statistics.totalBudgeted = this.budgets.reduce((sum, budget) => sum + budget.budgeted_amount, 0)
      this.statistics.totalActual = this.budgets.reduce((sum, budget) => sum + budget.actual_amount, 0)
      this.statistics.totalVariance = this.statistics.totalBudgeted - this.statistics.totalActual
      this.statistics.budgetUtilization = this.statistics.totalBudgeted > 0 
        ? Math.round((this.statistics.totalActual / this.statistics.totalBudgeted) * 100) 
        : 0
    },
    
    updateTabCounts() {
      this.tabs.find(tab => tab.id === 'entries').count = this.journalEntries.length
      this.tabs.find(tab => tab.id === 'budgets').count = this.budgets.length
      this.tabs.find(tab => tab.id === 'assets').count = this.assetDepreciations.length
    },
    
    generateActivities() {
      // Generate sample activities
      this.activities = [
        {
          id: 1,
          type: 'created',
          icon: 'fas fa-plus-circle',
          title: 'Period Created',
          description: 'Accounting period was created and set to Open status',
          timestamp: new Date(Date.now() - 86400000 * 7),
          user: 'John Doe'
        },
        {
          id: 2,
          type: 'transaction',
          icon: 'fas fa-exchange-alt',
          title: 'Journal Entry Added',
          description: 'Journal entry JE-2024-001 was posted',
          timestamp: new Date(Date.now() - 86400000 * 5),
          user: 'Jane Smith'
        },
        {
          id: 3,
          type: 'budget',
          icon: 'fas fa-calculator',
          title: 'Budget Updated',
          description: 'Budget for Office Rent was modified',
          timestamp: new Date(Date.now() - 86400000 * 3),
          user: 'Mike Johnson'
        }
      ]
    },
    
    async refreshData() {
      this.refreshing = true
      await this.loadPeriodData()
      await this.loadRelatedData()
      this.refreshing = false
      this.$toast?.success('Data refreshed successfully')
    },
    
    async refreshActivities() {
      this.generateActivities()
      this.$toast?.success('Activities refreshed')
    },
    
    editPeriod() {
      this.$router.push(`/accounting/periods/${this.period.period_id}/edit`)
    },
    
    goBack() {
      this.$router.push('/accounting/periods')
    },
    
    toggleDropdown() {
      this.showDropdown = !this.showDropdown
    },
    
    handleOutsideClick(event) {
      if (!event.target.closest('.dropdown')) {
        this.showDropdown = false
      }
    },
    
    closePeriod() {
      this.showCloseModal = true
      this.showDropdown = false
    },
    
    lockPeriod() {
      this.showLockModal = true
      this.showDropdown = false
    },
    
    deletePeriod() {
      this.showDeleteModal = true
      this.showDropdown = false
    },
    
    async confirmClosePeriod() {
      try {
        this.processing = true
        await axios.put(`/accounting/accounting-periods/${this.period.period_id}`, {
          ...this.period,
          status: 'Closed'
        })
        this.period.status = 'Closed'
        this.closeModal()
        this.$toast?.success('Period closed successfully')
      } catch (error) {
        console.error('Error closing period:', error)
        this.$toast?.error('Failed to close period')
      } finally {
        this.processing = false
      }
    },
    
    async confirmLockPeriod() {
      try {
        this.processing = true
        await axios.put(`/accounting/accounting-periods/${this.period.period_id}`, {
          ...this.period,
          status: 'Locked'
        })
        this.period.status = 'Locked'
        this.closeModal()
        this.$toast?.success('Period locked successfully')
      } catch (error) {
        console.error('Error locking period:', error)
        this.$toast?.error('Failed to lock period')
      } finally {
        this.processing = false
      }
    },
    
    async confirmDeletePeriod() {
      try {
        this.processing = true
        await axios.delete(`/accounting/accounting-periods/${this.period.period_id}`)
        this.$toast?.success('Period deleted successfully')
        this.goBack()
      } catch (error) {
        console.error('Error deleting period:', error)
        this.$toast?.error(error.response?.data?.message || 'Failed to delete period')
      } finally {
        this.processing = false
      }
    },
    
    closeModal() {
      this.showCloseModal = false
      this.showLockModal = false
      this.showDeleteModal = false
    },
    
    viewEntry(entry) {
      this.$router.push(`/accounting/journal-entries/${entry.journal_id}`)
    },
    
    createEntry() {
      this.$router.push(`/accounting/journal-entries/create?period_id=${this.period.period_id}`)
    },
    
    createBudget() {
      this.$router.push(`/accounting/budgets/create?period_id=${this.period.period_id}`)
    },
    
    calculateDepreciation() {
      this.$toast?.info('Depreciation calculation feature coming soon')
    },
    
    exportEntries() {
      this.$toast?.info('Export feature coming soon')
    },
    
    exportBudgets() {
      this.$toast?.info('Export feature coming soon')
    },
    
    exportAssets() {
      this.$toast?.info('Export feature coming soon')
    },
    
    formatDate(date) {
      if (!date) return ''
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      })
    },
    
    formatDateTime(date) {
      if (!date) return ''
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      })
    },
    
    formatCurrency(amount) {
      return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(amount || 0)
    },
    
    getPeriodType() {
      const duration = this.periodDuration
      if (duration <= 31) return 'Monthly'
      if (duration <= 92) return 'Quarterly'
      if (duration <= 184) return 'Semi-Annual'
      if (duration <= 366) return 'Annual'
      return 'Multi-Year'
    },
    
    getFiscalYear() {
      if (!this.period.start_date) return 'Unknown'
      const year = new Date(this.period.start_date).getFullYear()
      return `FY ${year}`
    },
    
    getDaysRemaining() {
      if (!this.period.end_date) return 'Unknown'
      const end = new Date(this.period.end_date)
      const now = new Date()
      const diffTime = end - now
      const days = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
      
      if (days < 0) return 'Expired'
      if (days === 0) return 'Today'
      return `${days} days`
    },
    
    getDaysRemainingClass() {
      if (!this.period.end_date) return ''
      const end = new Date(this.period.end_date)
      const now = new Date()
      const diffTime = end - now
      const days = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
      
      if (days < 0) return 'expired'
      if (days <= 7) return 'warning'
      return 'normal'
    },
    
    getPeriodProgress() {
      if (!this.period.start_date || !this.period.end_date) return 0
      const start = new Date(this.period.start_date)
      const end = new Date(this.period.end_date)
      const now = new Date()
      
      if (now < start) return 0
      if (now > end) return 100
      
      const total = end - start
      const elapsed = now - start
      return Math.round((elapsed / total) * 100)
    },
    
    getEntryAmount(entry) {
      return entry.total_amount || 0
    },
    
    getBudgetProgress(budget) {
      if (budget.budgeted_amount === 0) return 0
      return Math.min(100, Math.round((budget.actual_amount / budget.budgeted_amount) * 100))
    },
    
    getBudgetProgressClass(budget) {
      const progress = this.getBudgetProgress(budget)
      if (progress > 100) return 'over-budget'
      if (progress > 90) return 'near-budget'
      return 'under-budget'
    }
  }
}
</script>

<style scoped>
/* Main Layout */
.period-detail-page {
  padding: 1.5rem;
  background: #f8fafc;
  min-height: 100vh;
}

/* Loading & Error States */
.loading-container, .error-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 50vh;
  text-align: center;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #f3f4f6;
  border-top: 4px solid #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 1rem;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.error-container i {
  font-size: 3rem;
  color: #ef4444;
  margin-bottom: 1rem;
}

.error-container h2 {
  color: #1e293b;
  margin-bottom: 0.5rem;
}

.error-container p {
  color: #64748b;
  margin-bottom: 1.5rem;
}

/* Header */
.page-header {
  margin-bottom: 2rem;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.header-left {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  flex: 1;
}

.back-btn {
  background: #f3f4f6;
  border: none;
  border-radius: 8px;
  padding: 0.75rem;
  cursor: pointer;
  transition: all 0.2s;
  color: #6b7280;
}

.back-btn:hover {
  background: #e5e7eb;
  color: #374151;
}

.title-section {
  flex: 1;
}

.period-title {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 0.5rem;
}

.period-title h1 {
  font-size: 2rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.period-subtitle {
  color: #64748b;
  font-size: 1.1rem;
}

.header-actions {
  display: flex;
  gap: 1rem;
  align-items: center;
  position: relative;
}

/* Dropdown */
.dropdown {
  position: relative;
}

.dropdown-toggle {
  background: white;
  border: 1px solid #d1d5db;
  color: #374151;
}

.dropdown-menu {
  position: absolute;
  top: 100%;
  right: 0;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  min-width: 180px;
  z-index: 1000;
  margin-top: 0.25rem;
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  border: none;
  background: none;
  width: 100%;
  text-align: left;
  cursor: pointer;
  transition: background-color 0.2s;
  font-size: 0.9rem;
}

.dropdown-item:hover {
  background: #f8fafc;
}

.dropdown-item.danger {
  color: #ef4444;
}

.dropdown-item.danger:hover {
  background: #fef2f2;
}

.dropdown-divider {
  height: 1px;
  background: #e5e7eb;
  margin: 0.5rem 0;
}

/* Status Badge */
.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.status-badge.open {
  background: #dcfce7;
  color: #166534;
}

.status-badge.closed {
  background: #fee2e2;
  color: #991b1b;
}

.status-badge.locked {
  background: #fef3c7;
  color: #92400e;
}

/* Summary Section */
.summary-section {
  margin-bottom: 2rem;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}

.summary-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  border-left: 4px solid;
}

.summary-card.primary {
  border-color: #3b82f6;
}

.summary-card.success {
  border-color: #10b981;
}

.summary-card.warning {
  border-color: #f59e0b;
}

.summary-card.info {
  border-color: #6b7280;
}

.card-header {
  padding: 1.5rem 1.5rem 1rem 1.5rem;
  border-bottom: 1px solid #f1f5f9;
}

.card-header h3 {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.card-header h3 i {
  color: #64748b;
}

.card-body {
  padding: 1rem 1.5rem 1.5rem 1.5rem;
}

/* Info Grid */
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 1rem;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.info-item label {
  font-size: 0.8rem;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.info-item value {
  font-weight: 600;
  color: #1e293b;
  font-size: 0.9rem;
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
  gap: 1rem;
}

.stat-item {
  text-align: center;
}

.stat-value {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 0.25rem;
}

.stat-value.positive {
  color: #10b981;
}

.stat-value.negative {
  color: #ef4444;
}

.stat-label {
  font-size: 0.8rem;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

/* Status Info */
.status-info {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.status-item {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.status-item label {
  font-size: 0.8rem;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.status-value {
  font-weight: 600;
  color: #1e293b;
}

.status-value.expired {
  color: #ef4444;
}

.status-value.warning {
  color: #f59e0b;
}

.status-value.normal {
  color: #10b981;
}

/* Progress */
.progress-container {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.progress-bar {
  flex: 1;
  height: 8px;
  background: #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: #3b82f6;
  transition: width 0.3s ease;
}

.progress-fill.under-budget {
  background: #10b981;
}

.progress-fill.near-budget {
  background: #f59e0b;
}

.progress-fill.over-budget {
  background: #ef4444;
}

.progress-text {
  font-size: 0.8rem;
  font-weight: 500;
  color: #6b7280;
  min-width: 40px;
}

.progress-percentage {
  font-size: 0.8rem;
  font-weight: 500;
  color: #6b7280;
  min-width: 40px;
}

/* Tabs */
.tabs-section {
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.tabs-header {
  display: flex;
  background: #f8fafc;
  border-bottom: 1px solid #e5e7eb;
  overflow-x: auto;
}

.tab-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 1rem 1.5rem;
  border: none;
  background: none;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
  border-bottom: 3px solid transparent;
  font-weight: 500;
  color: #6b7280;
}

.tab-btn:hover {
  background: #f1f5f9;
  color: #374151;
}

.tab-btn.active {
  background: white;
  color: #3b82f6;
  border-bottom-color: #3b82f6;
}

.tab-count {
  background: #e5e7eb;
  color: #6b7280;
  padding: 0.125rem 0.5rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
}

.tab-btn.active .tab-count {
  background: #dbeafe;
  color: #3b82f6;
}

.tabs-content {
  padding: 1.5rem;
}

/* Panel */
.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.panel-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.panel-actions {
  display: flex;
  gap: 1rem;
}

/* Empty State */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  text-align: center;
}

.empty-state i {
  font-size: 3rem;
  color: #9ca3af;
  margin-bottom: 1rem;
}

.empty-state h4 {
  color: #374151;
  margin-bottom: 0.5rem;
}

.empty-state p {
  color: #6b7280;
}

/* Entries List */
.entries-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.entry-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}

.entry-item:hover {
  border-color: #3b82f6;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.entry-main {
  flex: 1;
}

.entry-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 0.5rem;
}

.entry-number {
  font-weight: 600;
  color: #3b82f6;
}

.entry-date {
  color: #6b7280;
  font-size: 0.9rem;
}

.entry-status {
  padding: 0.125rem 0.5rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
}

.entry-status.posted {
  background: #dcfce7;
  color: #166534;
}

.entry-status.draft {
  background: #fef3c7;
  color: #92400e;
}

.entry-description {
  color: #1e293b;
  margin-bottom: 0.25rem;
}

.entry-reference {
  color: #6b7280;
  font-size: 0.8rem;
}

.entry-amount {
  text-align: right;
}

.amount-value {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1e293b;
}

.amount-label {
  font-size: 0.8rem;
  color: #6b7280;
}

/* Budgets List */
.budgets-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.budget-item {
  padding: 1rem;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  transition: all 0.2s;
}

.budget-item:hover {
  border-color: #d1d5db;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.budget-main {
  margin-bottom: 1rem;
}

.budget-account {
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 0.5rem;
}

.budget-amounts {
  display: flex;
  gap: 1.5rem;
  flex-wrap: wrap;
}

.amount-group {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.amount-group .amount-label {
  font-size: 0.8rem;
  color: #6b7280;
}

.amount-group .amount-value {
  font-weight: 600;
  color: #1e293b;
}

.amount-group .amount-value.positive {
  color: #10b981;
}

.amount-group .amount-value.negative {
  color: #ef4444;
}

.budget-progress {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

/* Assets List */
.assets-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.asset-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  transition: all 0.2s;
}

.asset-item:hover {
  border-color: #d1d5db;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.asset-main {
  flex: 1;
}

.asset-name {
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 0.5rem;
}

.asset-details {
  display: flex;
  gap: 1.5rem;
  flex-wrap: wrap;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.detail-label {
  font-size: 0.8rem;
  color: #6b7280;
}

.detail-value {
  font-weight: 500;
  color: #1e293b;
  font-size: 0.9rem;
}

.asset-status {
  margin-left: 1rem;
}

/* Activities Timeline */
.activities-timeline {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.activity-item {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  border-radius: 8px;
  transition: background-color 0.2s;
}

.activity-item:hover {
  background: #f8fafc;
}

.activity-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 0.9rem;
}

.activity-icon.created {
  background: #10b981;
}

.activity-icon.transaction {
  background: #3b82f6;
}

.activity-icon.budget {
  background: #f59e0b;
}

.activity-content {
  flex: 1;
}

.activity-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.25rem;
}

.activity-title {
  font-weight: 600;
  color: #1e293b;
}

.activity-time {
  font-size: 0.8rem;
  color: #6b7280;
}

.activity-description {
  color: #64748b;
  margin-bottom: 0.25rem;
}

.activity-user {
  font-size: 0.8rem;
  color: #9ca3af;
}

/* Buttons */
.btn {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  background: #3b82f6;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #2563eb;
}

.btn-secondary {
  background: #6b7280;
  color: white;
}

.btn-secondary:hover:not(:disabled) {
  background: #4b5563;
}

.btn-outline {
  background: white;
  color: #374151;
  border: 1px solid #d1d5db;
}

.btn-outline:hover:not(:disabled) {
  background: #f9fafb;
  border-color: #9ca3af;
}

.btn-warning {
  background: #f59e0b;
  color: white;
}

.btn-warning:hover:not(:disabled) {
  background: #d97706;
}

.btn-danger {
  background: #ef4444;
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background: #dc2626;
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
  box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
  width: 100%;
  max-width: 500px;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.modal-close {
  background: none;
  border: none;
  font-size: 1.25rem;
  color: #9ca3af;
  cursor: pointer;
  padding: 0.25rem;
  border-radius: 4px;
  transition: color 0.2s;
}

.modal-close:hover {
  color: #6b7280;
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding: 1.5rem;
  border-top: 1px solid #e5e7eb;
}

.warning-message {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}

.warning-message i {
  color: #f59e0b;
  font-size: 1.25rem;
  margin-top: 0.25rem;
}

.warning-message p {
  margin-bottom: 0.5rem;
}

/* Responsive */
@media (max-width: 768px) {
  .period-detail-page {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    gap: 1.5rem;
    align-items: stretch;
  }
  
  .header-left {
    flex-direction: column;
    gap: 1rem;
  }
  
  .header-actions {
    justify-content: stretch;
    flex-wrap: wrap;
  }
  
  .header-actions .btn {
    flex: 1;
  }
  
  .summary-grid {
    grid-template-columns: 1fr;
  }
  
  .info-grid {
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
  }
  
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .tabs-header {
    overflow-x: auto;
  }
  
  .panel-header {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .panel-actions {
    justify-content: stretch;
  }
  
  .panel-actions .btn {
    flex: 1;
  }
  
  .entry-item {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }
  
  .entry-amount {
    text-align: left;
  }
  
  .budget-amounts {
    flex-direction: column;
    gap: 0.5rem;
  }
  
  .asset-item {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }
  
  .asset-status {
    margin-left: 0;
  }
  
  .asset-details {
    flex-direction: column;
    gap: 0.5rem;
  }
  
  .activity-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
  }
  
  .modal-content {
    margin: 1rem;
    max-width: none;
  }
}
</style>