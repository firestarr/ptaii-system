<!-- src/views/accounting/AccountingDashboard.vue -->
<template>
  <div class="accounting-dashboard">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <h1 class="page-title">
            <i class="fas fa-calculator"></i>
            Accounting Dashboard
          </h1>
          <p class="page-subtitle">Overview of your accounting activities and financial data</p>
        </div>
        <div class="header-actions">
          <button @click="refreshData" class="btn btn-secondary" :disabled="loading">
            <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
            Refresh
          </button>
          <router-link to="/accounting/journal-entries/create" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            New Journal Entry
          </router-link>
        </div>
      </div>
    </div>

    <!-- Quick Stats -->
    <div class="stats-section">
      <div class="stats-grid">
        <div class="stat-card" v-for="(stat, index) in quickStats" :key="index">
          <div class="stat-icon" :style="{ background: stat.gradient }">
            <i :class="stat.icon"></i>
          </div>
          <div class="stat-content">
            <h3>{{ stat.value }}</h3>
            <p>{{ stat.label }}</p>
            <div class="stat-trend" :class="stat.trend">
              <i :class="stat.trend === 'up' ? 'fas fa-arrow-up' : 'fas fa-arrow-down'"></i>
              <span>{{ stat.change }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="actions-section">
      <div class="section-header">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
      </div>
      <div class="actions-grid">
        <router-link to="/accounting/journal-entries" class="action-card">
          <div class="action-icon">
            <i class="fas fa-book"></i>
          </div>
          <div class="action-content">
            <h4>Journal Entries</h4>
            <p>View and manage all journal entries</p>
            <span class="action-count">{{ journalStats.total }} entries</span>
          </div>
          <div class="action-arrow">
            <i class="fas fa-chevron-right"></i>
          </div>
        </router-link>

        <router-link to="/accounting/journal-entries/create" class="action-card">
          <div class="action-icon">
            <i class="fas fa-plus-circle"></i>
          </div>
          <div class="action-content">
            <h4>Create Entry</h4>
            <p>Add a new journal entry</p>
            <span class="action-badge">Quick Add</span>
          </div>
          <div class="action-arrow">
            <i class="fas fa-chevron-right"></i>
          </div>
        </router-link>

        <router-link to="/accounting/journal-entries/post" class="action-card">
          <div class="action-icon">
            <i class="fas fa-check-circle"></i>
          </div>
          <div class="action-content">
            <h4>Post Entries</h4>
            <p>Post draft journal entries</p>
            <span class="action-count">{{ journalStats.draft }} draft entries</span>
          </div>
          <div class="action-arrow">
            <i class="fas fa-chevron-right"></i>
          </div>
        </router-link>

        <router-link to="/accounting/journal-entries/batch-upload" class="action-card">
          <div class="action-icon">
            <i class="fas fa-upload"></i>
          </div>
          <div class="action-content">
            <h4>Batch Upload</h4>
            <p>Upload multiple entries from Excel/CSV</p>
            <span class="action-badge">Bulk Import</span>
          </div>
          <div class="action-arrow">
            <i class="fas fa-chevron-right"></i>
          </div>
        </router-link>

        <router-link to="/accounting/chart-of-accounts" class="action-card">
          <div class="action-icon">
            <i class="fas fa-sitemap"></i>
          </div>
          <div class="action-content">
            <h4>Chart of Accounts</h4>
            <p>Manage account structure</p>
            <span class="action-count">{{ chartStats.accounts }} accounts</span>
          </div>
          <div class="action-arrow">
            <i class="fas fa-chevron-right"></i>
          </div>
        </router-link>

        <router-link to="/accounting/accounting-periods" class="action-card">
          <div class="action-icon">
            <i class="fas fa-calendar-alt"></i>
          </div>
          <div class="action-content">
            <h4>Accounting Periods</h4>
            <p>Manage fiscal periods</p>
            <span class="action-badge">{{ currentPeriod?.period_name || 'No Period' }}</span>
          </div>
          <div class="action-arrow">
            <i class="fas fa-chevron-right"></i>
          </div>
        </router-link>
      </div>
    </div>

    <!-- Recent Activities -->
    <div class="activities-section">
      <div class="section-header">
        <h3><i class="fas fa-history"></i> Recent Journal Entries</h3>
        <router-link to="/accounting/journal-entries" class="view-all-link">
          View All <i class="fas fa-arrow-right"></i>
        </router-link>
      </div>
      <div class="activities-content">
        <div v-if="loading" class="loading-state">
          <div class="loading-spinner"></div>
          <p>Loading recent activities...</p>
        </div>
        
        <div v-else-if="recentEntries.length === 0" class="empty-state">
          <div class="empty-icon">
            <i class="fas fa-book-open"></i>
          </div>
          <h4>No Recent Entries</h4>
          <p>Start by creating your first journal entry</p>
          <router-link to="/accounting/journal-entries/create" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Create Entry
          </router-link>
        </div>
        
        <div v-else class="entries-list">
          <div 
            v-for="entry in recentEntries" 
            :key="entry.journal_id"
            class="entry-item"
          >
            <div class="entry-info">
              <div class="entry-header">
                <router-link 
                  :to="`/accounting/journal-entries/${entry.journal_id}`"
                  class="entry-number"
                >
                  {{ entry.journal_number }}
                </router-link>
                <span class="entry-date">{{ formatDate(entry.entry_date) }}</span>
              </div>
              <div class="entry-description">
                {{ entry.description || 'No description' }}
              </div>
              <div class="entry-details">
                <span class="entry-period">{{ entry.accounting_period?.period_name }}</span>
                <span class="entry-amount">{{ formatCurrency(calculateTotalAmount(entry.journal_entry_lines)) }}</span>
              </div>
            </div>
            <div class="entry-status">
              <span class="status-badge" :class="`status-${entry.status?.toLowerCase()}`">
                <i :class="getStatusIcon(entry.status)"></i>
                {{ entry.status }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Summary Charts -->
    <div class="charts-section">
      <div class="charts-grid">
        <!-- Monthly Trend Chart -->
        <div class="chart-card">
          <div class="chart-header">
            <h4><i class="fas fa-chart-line"></i> Monthly Journal Entries</h4>
            <select v-model="chartPeriod" class="chart-filter">
              <option value="6">Last 6 Months</option>
              <option value="12">Last 12 Months</option>
            </select>
          </div>
          <div class="chart-content">
            <div class="chart-placeholder">
              <div class="chart-bars">
                <div v-for="(month, index) in monthlyData" :key="index" class="chart-bar">
                  <div 
                    class="bar-fill" 
                    :style="{ height: `${(month.entries / Math.max(...monthlyData.map(m => m.entries))) * 100}%` }"
                  ></div>
                  <span class="bar-label">{{ month.label }}</span>
                  <span class="bar-value">{{ month.entries }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Status Distribution -->
        <div class="chart-card">
          <div class="chart-header">
            <h4><i class="fas fa-chart-pie"></i> Entry Status Distribution</h4>
          </div>
          <div class="chart-content">
            <div class="status-distribution">
              <div class="status-item" v-for="status in statusDistribution" :key="status.name">
                <div class="status-indicator" :class="status.class"></div>
                <div class="status-info">
                  <span class="status-name">{{ status.name }}</span>
                  <span class="status-count">{{ status.count }}</span>
                </div>
                <div class="status-percentage">{{ status.percentage }}%</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'AccountingDashboard',
  data() {
    return {
      loading: false,
      chartPeriod: '6',
      recentEntries: [],
      currentPeriod: null,
      journalStats: {
        total: 0,
        draft: 0,
        posted: 0
      },
      chartStats: {
        accounts: 0
      },
      quickStats: [
        {
          value: '$0',
          label: 'Total Debits (Month)',
          icon: 'fas fa-plus-circle',
          gradient: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
          trend: 'up',
          change: '+0%'
        },
        {
          value: '$0',
          label: 'Total Credits (Month)',
          icon: 'fas fa-minus-circle',
          gradient: 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
          trend: 'up',
          change: '+0%'
        },
        {
          value: '0',
          label: 'Draft Entries',
          icon: 'fas fa-edit',
          gradient: 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
          trend: 'up',
          change: '+0%'
        },
        {
          value: '0',
          label: 'Posted Entries',
          icon: 'fas fa-check-circle',
          gradient: 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
          trend: 'up',
          change: '+0%'
        }
      ],
      monthlyData: [],
      statusDistribution: []
    }
  },
  async mounted() {
    await this.loadDashboardData()
  },
  methods: {
    async loadDashboardData() {
      this.loading = true
      try {
        await Promise.all([
          this.loadRecentEntries(),
          this.loadCurrentPeriod(),
          this.loadJournalStats(),
          this.loadChartStats(),
          this.loadMonthlyData(),
          this.loadStatusDistribution()
        ])
        this.updateQuickStats()
      } catch (error) {
        console.error('Error loading dashboard data:', error)
        this.$toast.error('Failed to load dashboard data')
      } finally {
        this.loading = false
      }
    },

    async loadRecentEntries() {
      try {
        const response = await axios.get('/accounting/journal-entries', {
          params: { per_page: 10, sort_field: 'entry_date', sort_direction: 'desc' }
        })
        this.recentEntries = response.data.data.slice(0, 10)
      } catch (error) {
        console.error('Error loading recent entries:', error)
      }
    },

    async loadCurrentPeriod() {
      try {
        const response = await axios.get('/accounting/accounting-periods/current')
        this.currentPeriod = response.data.data
      } catch (error) {
        // Fallback to get all periods and use the first one
        try {
          const response = await axios.get('/accounting/accounting-periods')
          const periods = response.data.data || response.data
          this.currentPeriod = periods.length > 0 ? periods[0] : null
        } catch (err) {
          console.error('Error loading periods:', err)
        }
      }
    },

    async loadJournalStats() {
      try {
        const response = await axios.get('/accounting/journal-entries')
        const entries = response.data.data
        
        this.journalStats = {
          total: entries.length,
          draft: entries.filter(e => e.status === 'Draft').length,
          posted: entries.filter(e => e.status === 'Posted').length
        }
      } catch (error) {
        console.error('Error loading journal stats:', error)
      }
    },

    async loadChartStats() {
      try {
        const response = await axios.get('/accounting/chart-of-accounts')
        const accounts = response.data.data || response.data
        this.chartStats.accounts = accounts.length
      } catch (error) {
        console.error('Error loading chart stats:', error)
      }
    },

    async loadMonthlyData() {
      // Generate mock monthly data for demonstration
      const months = []
      const now = new Date()
      
      for (let i = parseInt(this.chartPeriod) - 1; i >= 0; i--) {
        const date = new Date(now.getFullYear(), now.getMonth() - i, 1)
        const monthName = date.toLocaleDateString('en-US', { month: 'short' })
        
        months.push({
          label: monthName,
          entries: Math.floor(Math.random() * 50) + 10 // Mock data
        })
      }
      
      this.monthlyData = months
    },

    async loadStatusDistribution() {
      const total = this.journalStats.total || 1 // Avoid division by zero
      
      this.statusDistribution = [
        {
          name: 'Draft',
          count: this.journalStats.draft,
          percentage: Math.round((this.journalStats.draft / total) * 100),
          class: 'status-draft'
        },
        {
          name: 'Posted',
          count: this.journalStats.posted,
          percentage: Math.round((this.journalStats.posted / total) * 100),
          class: 'status-posted'
        }
      ]
    },

    updateQuickStats() {
      // Calculate totals from recent entries for demonstration
      const currentMonth = new Date().getMonth()
      const currentYear = new Date().getFullYear()
      
      const monthlyEntries = this.recentEntries.filter(entry => {
        const entryDate = new Date(entry.entry_date)
        return entryDate.getMonth() === currentMonth && entryDate.getFullYear() === currentYear
      })
      
      let totalDebits = 0
      let totalCredits = 0
      
      monthlyEntries.forEach(entry => {
        if (entry.journal_entry_lines) {
          entry.journal_entry_lines.forEach(line => {
            totalDebits += parseFloat(line.debit_amount) || 0
            totalCredits += parseFloat(line.credit_amount) || 0
          })
        }
      })
      
      this.quickStats[0].value = this.formatCurrency(totalDebits)
      this.quickStats[1].value = this.formatCurrency(totalCredits)
      this.quickStats[2].value = this.journalStats.draft.toString()
      this.quickStats[3].value = this.journalStats.posted.toString()
    },

    async refreshData() {
      await this.loadDashboardData()
      this.$toast.success('Dashboard data refreshed')
    },

    calculateTotalAmount(lines) {
      if (!lines || !Array.isArray(lines)) return 0
      return lines.reduce((sum, line) => sum + (parseFloat(line.debit_amount) || 0), 0)
    },

    getStatusIcon(status) {
      switch (status?.toLowerCase()) {
        case 'draft': return 'fas fa-edit'
        case 'posted': return 'fas fa-check-circle'
        case 'cancelled': return 'fas fa-times-circle'
        default: return 'fas fa-question-circle'
      }
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
    }
  },
  watch: {
    chartPeriod() {
      this.loadMonthlyData()
    }
  }
}
</script>

<style scoped>
.accounting-dashboard {
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

.page-title {
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

/* Stats Section */
.stats-section {
  margin-bottom: 2rem;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
}

.stat-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: var(--primary-gradient);
}

.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.stat-icon {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.5rem;
  flex-shrink: 0;
}

.stat-content h3 {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 0.25rem 0;
}

.stat-content p {
  color: var(--text-secondary);
  font-size: 0.9rem;
  margin: 0 0 0.5rem 0;
}

.stat-trend {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.8rem;
  font-weight: 500;
}

.stat-trend.up {
  color: #10b981;
}

.stat-trend.down {
  color: #ef4444;
}

/* Actions Section */
.actions-section {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.section-header h3 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.section-header h3 i {
  color: #6366f1;
}

.view-all-link {
  color: #6366f1;
  text-decoration: none;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.2s;
}

.view-all-link:hover {
  color: #4f46e5;
  transform: translateX(3px);
}

.actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}

.action-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.5rem;
  background: var(--bg-tertiary);
  border-radius: 12px;
  text-decoration: none;
  transition: all 0.3s ease;
  border: 2px solid transparent;
  position: relative;
  overflow: hidden;
}

.action-card:hover {
  border-color: #6366f1;
  background: rgba(99, 102, 241, 0.05);
  transform: translateY(-3px);
  box-shadow: 0 8px 25px rgba(99, 102, 241, 0.15);
}

.action-icon {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  flex-shrink: 0;
}

.action-content {
  flex: 1;
}

.action-content h4 {
  margin: 0 0 0.5rem 0;
  color: var(--text-primary);
  font-size: 1rem;
  font-weight: 600;
}

.action-content p {
  margin: 0 0 0.5rem 0;
  color: var(--text-secondary);
  font-size: 0.9rem;
  line-height: 1.4;
}

.action-count {
  background: rgba(99, 102, 241, 0.1);
  color: #6366f1;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 500;
}

.action-badge {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 500;
}

.action-arrow {
  color: var(--text-muted);
  transition: all 0.3s ease;
}

.action-card:hover .action-arrow {
  color: #6366f1;
  transform: translateX(5px);
}

/* Activities Section */
.activities-section {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.activities-content {
  min-height: 200px;
}

.loading-state, .empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 2rem;
  text-align: center;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 4px solid rgba(99, 102, 241, 0.2);
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin 1s ease-in-out infinite;
  margin-bottom: 1rem;
}

.empty-icon {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: var(--bg-tertiary);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1rem;
  font-size: 1.5rem;
  color: var(--text-muted);
}

.empty-state h4 {
  margin: 0 0 0.5rem 0;
  color: var(--text-primary);
}

.empty-state p {
  margin: 0 0 1.5rem 0;
  color: var(--text-secondary);
}

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
  background: var(--bg-tertiary);
  border-radius: 12px;
  transition: all 0.2s;
  border-left: 4px solid transparent;
}

.entry-item:hover {
  background: rgba(99, 102, 241, 0.05);
  border-left-color: #6366f1;
  transform: translateX(3px);
}

.entry-info {
  flex: 1;
}

.entry-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.entry-number {
  color: #6366f1;
  text-decoration: none;
  font-weight: 600;
  transition: color 0.2s;
}

.entry-number:hover {
  color: #4f46e5;
}

.entry-date {
  color: var(--text-muted);
  font-size: 0.8rem;
}

.entry-description {
  color: var(--text-primary);
  margin-bottom: 0.5rem;
  font-size: 0.9rem;
}

.entry-details {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.entry-period {
  background: rgba(99, 102, 241, 0.1);
  color: #6366f1;
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 500;
}

.entry-amount {
  font-weight: 600;
  color: var(--text-primary);
  font-family: 'Courier New', monospace;
  font-size: 0.9rem;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border-radius: 12px;
  font-size: 0.8rem;
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

/* Charts Section */
.charts-section {
  margin-bottom: 2rem;
}

.charts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 2rem;
}

.chart-card {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--border-color);
}

.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.chart-header h4 {
  margin: 0;
  color: var(--text-primary);
  font-size: 1rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.chart-header h4 i {
  color: #6366f1;
}

.chart-filter {
  padding: 0.5rem;
  border: 1px solid var(--border-color);
  border-radius: 6px;
  font-size: 0.8rem;
  background: white;
}

.chart-content {
  min-height: 200px;
}

.chart-placeholder {
  height: 200px;
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.chart-bars {
  display: flex;
  align-items: flex-end;
  gap: 1rem;
  height: 100%;
  width: 100%;
}

.chart-bar {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  height: 100%;
  position: relative;
}

.bar-fill {
  width: 100%;
  background: linear-gradient(180deg, #6366f1 0%, #8b5cf6 100%);
  border-radius: 4px 4px 0 0;
  min-height: 5px;
  transition: all 0.3s ease;
}

.bar-label {
  margin-top: 0.5rem;
  font-size: 0.8rem;
  color: var(--text-secondary);
  font-weight: 500;
}

.bar-value {
  position: absolute;
  top: -1.5rem;
  font-size: 0.7rem;
  color: var(--text-primary);
  font-weight: 600;
}

.status-distribution {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.status-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: var(--bg-tertiary);
  border-radius: 12px;
}

.status-indicator {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.status-indicator.status-draft {
  background: #d97706;
}

.status-indicator.status-posted {
  background: #059669;
}

.status-info {
  flex: 1;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.status-name {
  font-weight: 500;
  color: var(--text-primary);
}

.status-count {
  color: var(--text-secondary);
  font-size: 0.9rem;
}

.status-percentage {
  font-weight: 600;
  color: var(--text-primary);
  font-size: 1.1rem;
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

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 768px) {
  .accounting-dashboard {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    align-items: stretch;
  }
  
  .header-actions {
    justify-content: stretch;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .actions-grid {
    grid-template-columns: 1fr;
  }
  
  .charts-grid {
    grid-template-columns: 1fr;
  }
  
  .chart-header {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .chart-bars {
    gap: 0.5rem;
  }
  
  .entry-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
  }
  
  .entry-details {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
}
</style>