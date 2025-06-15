<!-- src/views/accounting/ChartOfAccountDetail.vue -->
<template>
  <div class="account-detail-page">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <div class="breadcrumb">
            <router-link to="/accounting/chart-of-accounts" class="breadcrumb-item">
              <i class="fas fa-sitemap"></i>
              Chart of Accounts
            </router-link>
            <i class="fas fa-chevron-right"></i>
            <span class="breadcrumb-current">Account Details</span>
          </div>
          <h1 class="page-title">
            <i class="fas fa-file-alt"></i>
            Account Details
          </h1>
          <p class="page-subtitle" v-if="account">
            Detailed information for {{ account.account_code }} - {{ account.name }}
          </p>
        </div>
        <div class="header-actions">
          <button @click="refreshData" class="btn btn-secondary" :disabled="loading">
            <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
            Refresh
          </button>
          <router-link 
            v-if="account" 
            :to="`/accounting/chart-of-accounts/${account.account_id}/edit`" 
            class="btn btn-outline"
          >
            <i class="fas fa-edit"></i>
            Edit Account
          </router-link>
          <button @click="deleteAccount" class="btn btn-danger" v-if="account && canDelete" :disabled="deleting">
            <i v-if="deleting" class="fas fa-spinner fa-spin"></i>
            <i v-else class="fas fa-trash"></i>
            Delete
          </button>
          <router-link to="/accounting/chart-of-accounts/create" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            New Account
          </router-link>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-container">
      <div class="loading-spinner"></div>
      <p>Loading account details...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-triangle"></i>
      <h3>Error Loading Account</h3>
      <p>{{ error }}</p>
      <button @click="loadAccount" class="btn btn-primary">Try Again</button>
    </div>

    <!-- Account Content -->
    <div v-else-if="account" class="account-content">
      <!-- Account Overview Cards -->
      <div class="overview-grid">
        <!-- Main Account Info -->
        <div class="info-card main-info">
          <div class="card-header">
            <h2>
              <i class="fas fa-info-circle"></i>
              Account Information
            </h2>
            <div class="account-status" :class="{ active: account.is_active, inactive: !account.is_active }">
              <i :class="account.is_active ? 'fas fa-check-circle' : 'fas fa-times-circle'"></i>
              {{ account.is_active ? 'Active' : 'Inactive' }}
            </div>
          </div>
          <div class="card-content">
            <div class="info-grid">
              <div class="info-item">
                <span class="info-label">Account Code</span>
                <span class="info-value code">{{ account.account_code }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Account Name</span>
                <span class="info-value">{{ account.name }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Account Type</span>
                <span class="info-value type" :class="account.account_type.toLowerCase()">
                  {{ account.account_type }}
                </span>
              </div>
              <div class="info-item">
                <span class="info-label">Parent Account</span>
                <span class="info-value" v-if="account.parent_account">
                  <router-link 
                    :to="`/accounting/chart-of-accounts/${account.parent_account.account_id}`"
                    class="parent-link"
                  >
                    {{ account.parent_account.account_code }} - {{ account.parent_account.name }}
                  </router-link>
                </span>
                <span class="info-value muted" v-else>None (Top Level)</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Hierarchy Info -->
        <div class="info-card hierarchy-info">
          <div class="card-header">
            <h2>
              <i class="fas fa-sitemap"></i>
              Account Hierarchy
            </h2>
          </div>
          <div class="card-content">
            <div class="hierarchy-tree">
              <!-- Parent Chain -->
              <div v-if="account.parent_account" class="hierarchy-level parent-level">
                <div class="hierarchy-node">
                  <i class="fas fa-folder"></i>
                  <router-link 
                    :to="`/accounting/chart-of-accounts/${account.parent_account.account_id}`"
                    class="hierarchy-link"
                  >
                    {{ account.parent_account.account_code }} - {{ account.parent_account.name }}
                  </router-link>
                </div>
              </div>
              
              <!-- Current Account -->
              <div class="hierarchy-level current-level">
                <div class="hierarchy-node current">
                  <i class="fas fa-file-alt"></i>
                  <span class="hierarchy-text">
                    {{ account.account_code }} - {{ account.name }}
                  </span>
                  <span class="current-indicator">Current</span>
                </div>
              </div>
              
              <!-- Child Accounts -->
              <div v-if="account.child_accounts && account.child_accounts.length > 0" class="hierarchy-level child-level">
                <div class="child-header">
                  <i class="fas fa-level-down-alt"></i>
                  <span>Child Accounts ({{ account.child_accounts.length }})</span>
                </div>
                <div class="child-accounts">
                  <div 
                    v-for="child in account.child_accounts" 
                    :key="child.account_id" 
                    class="hierarchy-node child"
                  >
                    <i class="fas fa-file"></i>
                    <router-link 
                      :to="`/accounting/chart-of-accounts/${child.account_id}`"
                      class="hierarchy-link"
                    >
                      {{ child.account_code }} - {{ child.name }}
                    </router-link>
                    <span class="child-status" :class="{ active: child.is_active, inactive: !child.is_active }">
                      <i :class="child.is_active ? 'fas fa-check-circle' : 'fas fa-times-circle'"></i>
                    </span>
                  </div>
                </div>
              </div>
              <div v-else class="hierarchy-level child-level">
                <div class="no-children">
                  <i class="fas fa-info-circle"></i>
                  <span>No child accounts</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Statistics -->
        <div class="info-card stats-info">
          <div class="card-header">
            <h2>
              <i class="fas fa-chart-bar"></i>
              Account Statistics
            </h2>
          </div>
          <div class="card-content">
            <div class="stats-grid">
              <div class="stat-item">
                <div class="stat-icon transactions">
                  <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="stat-content">
                  <h3>{{ stats.transactions || 0 }}</h3>
                  <p>Total Transactions</p>
                </div>
              </div>
              <div class="stat-item">
                <div class="stat-icon children">
                  <i class="fas fa-sitemap"></i>
                </div>
                <div class="stat-content">
                  <h3>{{ (account.child_accounts || []).length }}</h3>
                  <p>Child Accounts</p>
                </div>
              </div>
              <div class="stat-item">
                <div class="stat-icon budgets">
                  <i class="fas fa-calculator"></i>
                </div>
                <div class="stat-content">
                  <h3>{{ stats.budgets || 0 }}</h3>
                  <p>Budget Entries</p>
                </div>
              </div>
              <div class="stat-item">
                <div class="stat-icon bank">
                  <i class="fas fa-university"></i>
                </div>
                <div class="stat-content">
                  <h3>{{ stats.bankAccounts || 0 }}</h3>
                  <p>Bank Accounts</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Related Information Tabs -->
      <div class="tabs-container">
        <div class="tabs-header">
          <button 
            v-for="tab in tabs" 
            :key="tab.key"
            @click="activeTab = tab.key"
            class="tab-button"
            :class="{ active: activeTab === tab.key }"
          >
            <i :class="tab.icon"></i>
            {{ tab.label }}
            <span v-if="tab.count !== undefined" class="tab-count">{{ tab.count }}</span>
          </button>
        </div>
        
        <div class="tab-content">
          <!-- Journal Entries Tab -->
          <div v-if="activeTab === 'transactions'" class="tab-panel">
            <div class="panel-header">
              <h3>Recent Journal Entries</h3>
              <p>Latest transactions involving this account</p>
            </div>
            <div v-if="journalEntries.length > 0" class="table-container">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Journal #</th>
                    <th>Description</th>
                    <th>Debit</th>
                    <th>Credit</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="entry in journalEntries" :key="entry.line_id" class="table-row">
                    <td>{{ formatDate(entry.journal_entry?.entry_date) }}</td>
                    <td>
                      <span class="journal-number">{{ entry.journal_entry?.journal_number }}</span>
                    </td>
                    <td>{{ entry.description || entry.journal_entry?.description }}</td>
                    <td class="amount debit">{{ formatCurrency(entry.debit_amount) }}</td>
                    <td class="amount credit">{{ formatCurrency(entry.credit_amount) }}</td>
                    <td>
                      <span class="status-badge" :class="entry.journal_entry?.status">
                        {{ entry.journal_entry?.status || 'Draft' }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="empty-panel">
              <i class="fas fa-file-alt"></i>
              <h4>No Journal Entries</h4>
              <p>No transactions have been recorded for this account yet.</p>
            </div>
          </div>

          <!-- Budget Tab -->
          <div v-if="activeTab === 'budgets'" class="tab-panel">
            <div class="panel-header">
              <h3>Budget Information</h3>
              <p>Budget allocations and variance analysis</p>
            </div>
            <div v-if="budgets.length > 0" class="budget-grid">
              <div v-for="budget in budgets" :key="budget.budget_id" class="budget-card">
                <div class="budget-header">
                  <h4>{{ budget.accounting_period?.period_name || 'Unknown Period' }}</h4>
                  <span class="period-dates">
                    {{ formatDate(budget.accounting_period?.start_date) }} - 
                    {{ formatDate(budget.accounting_period?.end_date) }}
                  </span>
                </div>
                <div class="budget-amounts">
                  <div class="amount-item">
                    <span class="amount-label">Budgeted</span>
                    <span class="amount-value budgeted">{{ formatCurrency(budget.budgeted_amount) }}</span>
                  </div>
                  <div class="amount-item">
                    <span class="amount-label">Actual</span>
                    <span class="amount-value actual">{{ formatCurrency(budget.actual_amount) }}</span>
                  </div>
                  <div class="amount-item">
                    <span class="amount-label">Variance</span>
                    <span class="amount-value variance" :class="{ positive: budget.variance >= 0, negative: budget.variance < 0 }">
                      {{ formatCurrency(budget.variance) }}
                    </span>
                  </div>
                </div>
                <div class="budget-progress">
                  <div class="progress-bar">
                    <div 
                      class="progress-fill" 
                      :style="{ width: getBudgetProgress(budget) + '%' }"
                    ></div>
                  </div>
                  <span class="progress-text">{{ getBudgetProgress(budget) }}% of budget used</span>
                </div>
              </div>
            </div>
            <div v-else class="empty-panel">
              <i class="fas fa-calculator"></i>
              <h4>No Budget Entries</h4>
              <p>No budget allocations have been set for this account.</p>
            </div>
          </div>

          <!-- Bank Accounts Tab -->
          <div v-if="activeTab === 'bank'" class="tab-panel">
            <div class="panel-header">
              <h3>Associated Bank Accounts</h3>
              <p>Bank accounts linked to this GL account</p>
            </div>
            <div v-if="bankAccounts.length > 0" class="bank-grid">
              <div v-for="bank in bankAccounts" :key="bank.bank_id" class="bank-card">
                <div class="bank-header">
                  <div class="bank-icon">
                    <i class="fas fa-university"></i>
                  </div>
                  <div class="bank-info">
                    <h4>{{ bank.bank_name }}</h4>
                    <p>{{ bank.account_name }}</p>
                  </div>
                </div>
                <div class="bank-details">
                  <div class="detail-item">
                    <span class="detail-label">Account Number</span>
                    <span class="detail-value">{{ bank.account_number }}</span>
                  </div>
                  <div class="detail-item">
                    <span class="detail-label">Current Balance</span>
                    <span class="detail-value balance">{{ formatCurrency(bank.current_balance) }}</span>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="empty-panel">
              <i class="fas fa-university"></i>
              <h4>No Bank Accounts</h4>
              <p>No bank accounts are linked to this GL account.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="modal-overlay" @click="closeDeleteModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>Confirm Delete</h3>
          <button @click="closeDeleteModal" class="close-btn">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <div class="warning-icon">
            <i class="fas fa-exclamation-triangle"></i>
          </div>
          <p>Are you sure you want to delete this account?</p>
          <div class="account-info">
            <strong>{{ account?.account_code }} - {{ account?.name }}</strong>
          </div>
          <div class="warning-list">
            <div class="warning-item">
              <i class="fas fa-info-circle"></i>
              This action cannot be undone
            </div>
            <div v-if="!canDelete" class="warning-item danger">
              <i class="fas fa-ban"></i>
              Cannot delete: Account has child accounts or journal entries
            </div>
          </div>
        </div>
        <div class="modal-actions">
          <button @click="closeDeleteModal" class="btn btn-secondary">Cancel</button>
          <button 
            @click="confirmDelete" 
            class="btn btn-danger" 
            :disabled="deleting || !canDelete"
          >
            <i v-if="deleting" class="fas fa-spinner fa-spin"></i>
            <span v-else>Delete Account</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'ChartOfAccountDetail',
  props: {
    id: {
      type: [String, Number],
      required: true
    }
  },
  data() {
    return {
      loading: false,
      error: null,
      account: null,
      stats: {},
      journalEntries: [],
      budgets: [],
      bankAccounts: [],
      activeTab: 'transactions',
      showDeleteModal: false,
      deleting: false
    };
  },
  computed: {
    canDelete() {
      if (!this.account) return false;
      return (
        (!this.account.child_accounts || this.account.child_accounts.length === 0) &&
        this.journalEntries.length === 0
      );
    },
    tabs() {
      return [
        {
          key: 'transactions',
          label: 'Journal Entries',
          icon: 'fas fa-exchange-alt',
          count: this.journalEntries.length
        },
        {
          key: 'budgets',
          label: 'Budgets',
          icon: 'fas fa-calculator',
          count: this.budgets.length
        },
        {
          key: 'bank',
          label: 'Bank Accounts',
          icon: 'fas fa-university',
          count: this.bankAccounts.length
        }
      ];
    }
  },
  mounted() {
    this.loadAccount();
  },
  watch: {
    id() {
      this.loadAccount();
    }
  },
  methods: {
    async loadAccount() {
      this.loading = true;
      this.error = null;
      
      try {
        const response = await axios.get(`/accounting/chart-of-accounts/${this.id}`);
        this.account = response.data.data;
        
        // Load related data
        await Promise.all([
          this.loadJournalEntries(),
          this.loadBudgets(),
          this.loadBankAccounts(),
          this.loadStats()
        ]);
      } catch (error) {
        console.error('Error loading account:', error);
        this.error = error.response?.data?.message || 'Failed to load account details';
      } finally {
        this.loading = false;
      }
    },

    async loadJournalEntries() {
      try {
        // This would be a separate API endpoint to get journal entries for the account
        // For now, we'll simulate with the journal entry lines relationship
        this.journalEntries = this.account.journal_entry_lines || [];
      } catch (error) {
        console.error('Error loading journal entries:', error);
        this.journalEntries = [];
      }
    },

    async loadBudgets() {
      try {
        this.budgets = this.account.budgets || [];
      } catch (error) {
        console.error('Error loading budgets:', error);
        this.budgets = [];
      }
    },

    async loadBankAccounts() {
      try {
        this.bankAccounts = this.account.bank_accounts || [];
      } catch (error) {
        console.error('Error loading bank accounts:', error);
        this.bankAccounts = [];
      }
    },

    async loadStats() {
      this.stats = {
        transactions: this.journalEntries.length,
        budgets: this.budgets.length,
        bankAccounts: this.bankAccounts.length
      };
    },

    async refreshData() {
      await this.loadAccount();
    },

    deleteAccount() {
      this.showDeleteModal = true;
    },

    closeDeleteModal() {
      this.showDeleteModal = false;
      this.deleting = false;
    },

    async confirmDelete() {
      if (!this.canDelete || !this.account) return;
      
      this.deleting = true;
      
      try {
        await axios.delete(`/accounting/chart-of-accounts/${this.account.account_id}`);
        this.$toast.success('Account deleted successfully');
        this.$router.push('/accounting/chart-of-accounts');
      } catch (error) {
        console.error('Error deleting account:', error);
        const message = error.response?.data?.message || 'Failed to delete account';
        this.$toast.error(message);
      } finally {
        this.deleting = false;
        this.closeDeleteModal();
      }
    },

    formatDate(date) {
      if (!date) return 'N/A';
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    },

    formatCurrency(amount) {
      if (amount === null || amount === undefined) return '-';
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount);
    },

    getBudgetProgress(budget) {
      if (!budget.budgeted_amount || budget.budgeted_amount === 0) return 0;
      const progress = (budget.actual_amount / budget.budgeted_amount) * 100;
      return Math.min(Math.max(progress, 0), 100);
    }
  }
};
</script>

<style scoped>
.account-detail-page {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

/* Page Header - Same as form */
.page-header {
  margin-bottom: 2rem;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 2rem;
}

.title-section {
  flex: 1;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
  font-size: 0.9rem;
}

.breadcrumb-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--text-muted);
  text-decoration: none;
  transition: all 0.3s ease;
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
}

.breadcrumb-item:hover {
  color: #667eea;
  background: rgba(102, 126, 234, 0.1);
}

.breadcrumb-current {
  color: var(--text-primary);
  font-weight: 600;
  padding: 0.25rem 0.5rem;
  background: rgba(102, 126, 234, 0.1);
  border-radius: 6px;
}

.page-title {
  font-size: 2.5rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 0.5rem 0;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.page-title i {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.page-subtitle {
  color: var(--text-muted);
  font-size: 1.1rem;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 1rem;
  align-items: center;
  flex-wrap: wrap;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 12px;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 0.9rem;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
  background: var(--card-bg);
  color: var(--text-primary);
  border: 2px solid var(--border-color);
}

.btn-secondary:hover {
  border-color: #667eea;
  color: #667eea;
}

.btn-outline {
  background: transparent;
  color: var(--text-primary);
  border: 2px solid var(--border-color);
}

.btn-outline:hover {
  background: rgba(102, 126, 234, 0.1);
  border-color: #667eea;
  color: #667eea;
}

.btn-danger {
  background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
  color: white;
}

.btn-danger:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(255, 65, 108, 0.4);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

/* Loading and Error States */
.loading-container, .error-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  text-align: center;
}

.loading-spinner {
  width: 50px;
  height: 50px;
  border: 4px solid var(--border-color);
  border-top-color: #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 1rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.error-container i {
  font-size: 3rem;
  color: #ff416c;
  margin-bottom: 1rem;
}

/* Overview Grid */
.overview-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
  margin-bottom: 2rem;
}

.overview-grid .stats-info {
  grid-column: 1 / -1;
}

/* Info Cards */
.info-card {
  background: var(--card-bg);
  border-radius: 16px;
  border: 1px solid var(--border-color);
  overflow: hidden;
  transition: all 0.3s ease;
}

.info-card:hover {
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.card-header {
  padding: 1.5rem;
  border-bottom: 1px solid var(--border-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
}

.card-header h2 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.card-header h2 i {
  color: #667eea;
}

.account-status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 600;
  font-size: 0.9rem;
  padding: 0.5rem 1rem;
  border-radius: 20px;
}

.account-status.active {
  color: #43e97b;
  background: rgba(67, 233, 123, 0.1);
}

.account-status.inactive {
  color: #ff416c;
  background: rgba(255, 65, 108, 0.1);
}

.card-content {
  padding: 1.5rem;
}

/* Info Grid */
.info-grid {
  display: grid;
  gap: 1.5rem;
}

.info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background: var(--bg-secondary);
  border-radius: 12px;
  border: 1px solid var(--border-color);
}

.info-label {
  font-weight: 500;
  color: var(--text-secondary);
  font-size: 0.9rem;
}

.info-value {
  font-weight: 600;
  color: var(--text-primary);
  font-size: 0.95rem;
}

.info-value.code {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-family: 'Courier New', monospace;
}

.info-value.type {
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.8rem;
  text-transform: uppercase;
}

.info-value.type.asset { background: rgba(102, 126, 234, 0.1); color: #667eea; }
.info-value.type.liability { background: rgba(255, 65, 108, 0.1); color: #ff416c; }
.info-value.type.equity { background: rgba(79, 172, 254, 0.1); color: #4facfe; }
.info-value.type.revenue { background: rgba(67, 233, 123, 0.1); color: #43e97b; }
.info-value.type.expense { background: rgba(250, 112, 154, 0.1); color: #fa709a; }

.info-value.muted {
  color: var(--text-muted);
  font-style: italic;
}

.parent-link {
  color: #667eea;
  text-decoration: none;
  transition: all 0.3s ease;
}

.parent-link:hover {
  text-decoration: underline;
}

/* Hierarchy Tree */
.hierarchy-tree {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.hierarchy-level {
  position: relative;
}

.hierarchy-node {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem;
  border-radius: 12px;
  transition: all 0.3s ease;
}

.parent-level .hierarchy-node {
  background: rgba(102, 126, 234, 0.1);
  border-left: 4px solid #667eea;
}

.current-level .hierarchy-node {
  background: rgba(79, 172, 254, 0.1);
  border-left: 4px solid #4facfe;
  font-weight: 600;
}

.child-level .hierarchy-node {
  background: rgba(67, 233, 123, 0.1);
  border-left: 4px solid #43e97b;
  margin-left: 2rem;
}

.hierarchy-link {
  color: inherit;
  text-decoration: none;
  flex: 1;
}

.hierarchy-link:hover {
  text-decoration: underline;
}

.current-indicator {
  background: #4facfe;
  color: white;
  padding: 0.25rem 0.5rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
}

.child-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 0.5rem;
  font-size: 0.9rem;
}

.child-accounts {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.child-status {
  font-size: 0.8rem;
}

.child-status.active { color: #43e97b; }
.child-status.inactive { color: #ff416c; }

.no-children {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--text-muted);
  font-style: italic;
  padding: 1rem;
  background: var(--bg-secondary);
  border-radius: 8px;
}

/* Statistics */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 1rem;
}

.stat-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: var(--bg-secondary);
  border-radius: 12px;
  border: 1px solid var(--border-color);
  transition: all 0.3s ease;
}

.stat-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.25rem;
  flex-shrink: 0;
}

.stat-icon.transactions { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.stat-icon.children { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.stat-icon.budgets { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
.stat-icon.bank { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }

.stat-content h3 {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}

.stat-content p {
  color: var(--text-muted);
  font-size: 0.85rem;
  margin: 0;
}

/* Tabs */
.tabs-container {
  background: var(--card-bg);
  border-radius: 16px;
  border: 1px solid var(--border-color);
  overflow: hidden;
}

.tabs-header {
  display: flex;
  background: var(--bg-secondary);
  border-bottom: 1px solid var(--border-color);
}

.tab-button {
  flex: 1;
  padding: 1rem 1.5rem;
  border: none;
  background: transparent;
  color: var(--text-secondary);
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  position: relative;
}

.tab-button:hover {
  background: rgba(102, 126, 234, 0.1);
  color: #667eea;
}

.tab-button.active {
  color: #667eea;
  background: var(--card-bg);
}

.tab-button.active::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.tab-count {
  background: rgba(102, 126, 234, 0.2);
  color: #667eea;
  padding: 0.25rem 0.5rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
}

.tab-button.active .tab-count {
  background: #667eea;
  color: white;
}

.tab-content {
  padding: 2rem;
}

.tab-panel {
  min-height: 400px;
}

.panel-header {
  margin-bottom: 2rem;
}

.panel-header h3 {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 0.5rem 0;
}

.panel-header p {
  color: var(--text-muted);
  margin: 0;
}

/* Table */
.table-container {
  overflow-x: auto;
  border-radius: 12px;
  border: 1px solid var(--border-color);
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  background: var(--card-bg);
}

.data-table th {
  background: var(--bg-secondary);
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: var(--text-primary);
  border-bottom: 1px solid var(--border-color);
  font-size: 0.9rem;
}

.data-table td {
  padding: 1rem;
  border-bottom: 1px solid var(--border-color);
  color: var(--text-primary);
  font-size: 0.9rem;
}

.table-row:hover {
  background: rgba(102, 126, 234, 0.05);
}

.journal-number {
  font-family: 'Courier New', monospace;
  background: rgba(102, 126, 234, 0.1);
  color: #667eea;
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
  font-size: 0.8rem;
}

.amount {
  font-family: 'Courier New', monospace;
  text-align: right;
  font-weight: 600;
}

.amount.debit {
  color: #ff416c;
}

.amount.credit {
  color: #43e97b;
}

.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 500;
  text-transform: uppercase;
}

.status-badge.draft {
  background: rgba(156, 163, 175, 0.1);
  color: #9ca3af;
}

.status-badge.posted {
  background: rgba(67, 233, 123, 0.1);
  color: #43e97b;
}

/* Budget Grid */
.budget-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}

.budget-card {
  background: var(--bg-secondary);
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid var(--border-color);
}

.budget-header h4 {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 0.5rem 0;
}

.period-dates {
  color: var(--text-muted);
  font-size: 0.85rem;
}

.budget-amounts {
  margin: 1rem 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.amount-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.amount-label {
  font-weight: 500;
  color: var(--text-secondary);
  font-size: 0.9rem;
}

.amount-value {
  font-weight: 600;
  font-family: 'Courier New', monospace;
  font-size: 0.9rem;
}

.amount-value.budgeted { color: #4facfe; }
.amount-value.actual { color: var(--text-primary); }
.amount-value.variance.positive { color: #43e97b; }
.amount-value.variance.negative { color: #ff416c; }

.budget-progress {
  margin-top: 1rem;
}

.progress-bar {
  height: 8px;
  background: var(--border-color);
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 0.5rem;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
  border-radius: 4px;
  transition: width 0.3s ease;
}

.progress-text {
  font-size: 0.8rem;
  color: var(--text-muted);
}

/* Bank Grid */
.bank-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}

.bank-card {
  background: var(--bg-secondary);
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid var(--border-color);
}

.bank-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
}

.bank-icon {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.25rem;
}

.bank-info h4 {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 0.25rem 0;
}

.bank-info p {
  color: var(--text-muted);
  font-size: 0.9rem;
  margin: 0;
}

.bank-details {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.detail-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.detail-label {
  font-weight: 500;
  color: var(--text-secondary);
  font-size: 0.9rem;
}

.detail-value {
  font-weight: 600;
  color: var(--text-primary);
  font-size: 0.9rem;
}

.detail-value.balance {
  font-family: 'Courier New', monospace;
  color: #43e97b;
}

/* Empty Panel */
.empty-panel {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  text-align: center;
  color: var(--text-muted);
}

.empty-panel i {
  font-size: 3rem;
  margin-bottom: 1rem;
  opacity: 0.5;
}

.empty-panel h4 {
  color: var(--text-primary);
  margin-bottom: 0.5rem;
}

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(5px);
}

.modal-content {
  background: var(--card-bg);
  border-radius: 16px;
  width: 90%;
  max-width: 500px;
  max-height: 90vh;
  overflow-y: auto;
  border: 1px solid var(--border-color);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
  color: var(--text-primary);
  margin: 0;
}

.close-btn {
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  font-size: 1.25rem;
  padding: 0.5rem;
  border-radius: 8px;
  transition: all 0.3s ease;
}

.close-btn:hover {
  color: var(--text-primary);
  background: var(--bg-secondary);
}

.modal-body {
  padding: 1.5rem;
  text-align: center;
}

.warning-icon {
  font-size: 3rem;
  color: #ff416c;
  margin-bottom: 1rem;
}

.account-info {
  background: var(--bg-secondary);
  padding: 1rem;
  border-radius: 8px;
  margin: 1rem 0;
}

.warning-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-top: 1rem;
  text-align: left;
}

.warning-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
}

.warning-item.danger {
  color: #ff416c;
  font-weight: 500;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding: 1.5rem;
  border-top: 1px solid var(--border-color);
}

/* Responsive Design */
@media (max-width: 1024px) {
  .overview-grid {
    grid-template-columns: 1fr;
  }

  .tabs-header {
    flex-wrap: wrap;
  }

  .tab-button {
    flex: none;
    min-width: 150px;
  }
}

@media (max-width: 768px) {
  .account-detail-page {
    padding: 1rem;
  }

  .header-content {
    flex-direction: column;
    align-items: stretch;
  }

  .header-actions {
    justify-content: flex-start;
  }

  .stats-grid {
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  }

  .info-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }

  .tab-content {
    padding: 1rem;
  }

  .budget-grid, .bank-grid {
    grid-template-columns: 1fr;
  }

  .data-table {
    font-size: 0.8rem;
  }

  .data-table th, .data-table td {
    padding: 0.75rem 0.5rem;
  }
}
</style>