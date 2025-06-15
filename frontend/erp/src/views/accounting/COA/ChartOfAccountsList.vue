<!-- src/views/accounting/ChartOfAccountsList.vue -->
<template>
  <div class="chart-accounts-page">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <h1 class="page-title">
            <i class="fas fa-sitemap"></i>
            Chart of Accounts
          </h1>
          <p class="page-subtitle">Manage your accounting structure and account hierarchy</p>
        </div>
        <div class="header-actions">
          <button @click="refreshData" class="btn btn-secondary" :disabled="loading">
            <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
            Refresh
          </button>
          <button @click="toggleView" class="btn btn-outline">
            <i :class="viewMode === 'hierarchy' ? 'fas fa-list' : 'fas fa-sitemap'"></i>
            {{ viewMode === 'hierarchy' ? 'List View' : 'Tree View' }}
          </button>
          <button @click="showStructureViewer" class="btn btn-info">
            <i class="fas fa-eye"></i>
            Structure Viewer
          </button>
          <router-link to="/accounting/chart-of-accounts/create" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Add Account
          </router-link>
        </div>
      </div>
    </div>

    <!-- Filters and Search -->
    <div class="filters-section">
      <div class="search-filters">
        <div class="search-box">
          <i class="fas fa-search"></i>
          <input 
            v-model="searchTerm" 
            type="text" 
            placeholder="Search accounts by code or name..."
            @input="handleSearch"
          />
        </div>
        <div class="filter-group">
          <select v-model="selectedType" @change="applyFilters" class="filter-select">
            <option value="">All Types</option>
            <option value="Asset">Asset</option>
            <option value="Liability">Liability</option>
            <option value="Equity">Equity</option>
            <option value="Revenue">Revenue</option>
            <option value="Expense">Expense</option>
          </select>
          <select v-model="selectedStatus" @change="applyFilters" class="filter-select">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-container">
      <div class="loading-spinner"></div>
      <p>Loading chart of accounts...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-triangle"></i>
      <h3>Error Loading Accounts</h3>
      <p>{{ error }}</p>
      <button @click="loadAccounts" class="btn btn-primary">Try Again</button>
    </div>

    <!-- Accounts Content -->
    <div v-else class="accounts-content">
      <!-- Statistics Cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon asset">
            <i class="fas fa-building"></i>
          </div>
          <div class="stat-info">
            <h3>{{ stats.assets || 0 }}</h3>
            <p>Assets</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon liability">
            <i class="fas fa-credit-card"></i>
          </div>
          <div class="stat-info">
            <h3>{{ stats.liabilities || 0 }}</h3>
            <p>Liabilities</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon equity">
            <i class="fas fa-balance-scale"></i>
          </div>
          <div class="stat-info">
            <h3>{{ stats.equity || 0 }}</h3>
            <p>Equity</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon revenue">
            <i class="fas fa-chart-line"></i>
          </div>
          <div class="stat-info">
            <h3>{{ stats.revenue || 0 }}</h3>
            <p>Revenue</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon expense">
            <i class="fas fa-receipt"></i>
          </div>
          <div class="stat-info">
            <h3>{{ stats.expenses || 0 }}</h3>
            <p>Expenses</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon total">
            <i class="fas fa-calculator"></i>
          </div>
          <div class="stat-info">
            <h3>{{ filteredAccounts.length }}</h3>
            <p>Total Accounts</p>
          </div>
        </div>
      </div>

      <!-- Hierarchy View -->
      <div v-if="viewMode === 'hierarchy'" class="hierarchy-view">
        <div class="accounts-tree">
          <div v-for="account in rootAccounts" :key="account.account_id" class="tree-root">
            <AccountNode 
              :account="account" 
              :level="0"
              @edit="editAccount"
              @delete="deleteAccount"
              @view="viewAccount"
            />
          </div>
        </div>
      </div>

      <!-- List View -->
      <div v-else class="list-view">
        <div class="accounts-grid">
          <div 
            v-for="account in filteredAccounts" 
            :key="account.account_id" 
            class="account-card"
            @click="viewAccount(account.account_id)"
          >
            <div class="account-header">
              <div class="account-code">{{ account.account_code }}</div>
              <div class="account-status" :class="{ active: account.is_active, inactive: !account.is_active }">
                <i :class="account.is_active ? 'fas fa-check-circle' : 'fas fa-times-circle'"></i>
                {{ account.is_active ? 'Active' : 'Inactive' }}
              </div>
            </div>
            <div class="account-body">
              <h3 class="account-name">{{ account.name }}</h3>
              <div class="account-meta">
                <span class="account-type" :class="account.account_type.toLowerCase()">
                  {{ account.account_type }}
                </span>
                <span v-if="account.parent_account" class="parent-info">
                  <i class="fas fa-level-up-alt"></i>
                  {{ account.parent_account.name }}
                </span>
              </div>
            </div>
            <div class="account-actions">
              <button @click.stop="viewAccount(account.account_id)" class="action-btn view">
                <i class="fas fa-eye"></i>
              </button>
              <button @click.stop="editAccount(account.account_id)" class="action-btn edit">
                <i class="fas fa-edit"></i>
              </button>
              <button @click.stop="deleteAccount(account)" class="action-btn delete" :disabled="account.childAccounts && account.childAccounts.length > 0">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="filteredAccounts.length === 0 && !loading" class="empty-state">
        <i class="fas fa-sitemap"></i>
        <h3>No Accounts Found</h3>
        <p v-if="searchTerm">No accounts match your search criteria.</p>
        <p v-else>Start by creating your first account.</p>
        <router-link to="/accounting/chart-of-accounts/create" class="btn btn-primary">
          <i class="fas fa-plus"></i>
          Create First Account
        </router-link>
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
          <p>Are you sure you want to delete this account?</p>
          <div class="account-info">
            <strong>{{ accountToDelete?.account_code }} - {{ accountToDelete?.name }}</strong>
          </div>
          <div class="warning-message">
            <i class="fas fa-exclamation-triangle"></i>
            This action cannot be undone.
          </div>
        </div>
        <div class="modal-actions">
          <button @click="closeDeleteModal" class="btn btn-secondary">Cancel</button>
          <button @click="confirmDelete" class="btn btn-danger" :disabled="deleting">
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

// Account Node Component for Hierarchy View
const AccountNode = {
  name: 'AccountNode',
  props: ['account', 'level'],
  emits: ['edit', 'delete', 'view'],
  data() {
    return {
      expanded: false
    };
  },
  computed: {
    hasChildren() {
      return this.account.child_accounts && this.account.child_accounts.length > 0;
    },
    indentStyle() {
      return {
        paddingLeft: `${this.level * 30 + 20}px`
      };
    }
  },
  template: `
    <div class="tree-node">
      <div class="node-content" :style="indentStyle" :class="{ 'has-children': hasChildren }">
        <div class="expand-toggle" @click="expanded = !expanded" v-if="hasChildren">
          <i :class="expanded ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"></i>
        </div>
        <div class="account-info" @click="$emit('view', account.account_id)">
          <div class="account-code">{{ account.account_code }}</div>
          <div class="account-name">{{ account.name }}</div>
          <div class="account-type" :class="account.account_type.toLowerCase()">{{ account.account_type }}</div>
          <div class="account-status" :class="{ active: account.is_active, inactive: !account.is_active }">
            <i :class="account.is_active ? 'fas fa-check-circle' : 'fas fa-times-circle'"></i>
          </div>
        </div>
        <div class="node-actions">
          <button @click.stop="$emit('view', account.account_id)" class="action-btn view">
            <i class="fas fa-eye"></i>
          </button>
          <button @click.stop="$emit('edit', account.account_id)" class="action-btn edit">
            <i class="fas fa-edit"></i>
          </button>
          <button @click.stop="$emit('delete', account)" class="action-btn delete" :disabled="hasChildren">
            <i class="fas fa-trash"></i>
          </button>
        </div>
      </div>
      <div v-if="expanded && hasChildren" class="child-nodes">
        <AccountNode 
          v-for="child in account.child_accounts" 
          :key="child.account_id"
          :account="child" 
          :level="level + 1"
          @edit="$emit('edit', $event)"
          @delete="$emit('delete', $event)"
          @view="$emit('view', $event)"
        />
      </div>
    </div>
  `
};

export default {
  name: 'ChartOfAccountsList',
  components: {
    AccountNode
  },
  data() {
    return {
      accounts: [],
      filteredAccounts: [],
      loading: false,
      error: null,
      searchTerm: '',
      selectedType: '',
      selectedStatus: '',
      viewMode: 'hierarchy', // 'hierarchy' or 'list'
      showDeleteModal: false,
      accountToDelete: null,
      deleting: false,
      searchTimeout: null,
      stats: {
        assets: 0,
        liabilities: 0,
        equity: 0,
        revenue: 0,
        expenses: 0
      }
    };
  },
  computed: {
    rootAccounts() {
      return this.filteredAccounts.filter(account => !account.parent_account_id);
    }
  },
  mounted() {
    this.loadAccounts();
  },
  methods: {
    async loadAccounts() {
      this.loading = true;
      this.error = null;
      
      try {
        const response = await axios.get('/accounting/chart-of-accounts/hierarchy');
        this.accounts = response.data.data || [];
        this.applyFilters();
        this.calculateStats();
      } catch (error) {
        console.error('Error loading accounts:', error);
        this.error = error.response?.data?.message || 'Failed to load accounts';
      } finally {
        this.loading = false;
      }
    },

    async refreshData() {
      await this.loadAccounts();
    },

    handleSearch() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => {
        this.applyFilters();
      }, 300);
    },

    applyFilters() {
      let filtered = [...this.accounts];

      // Apply search filter
      if (this.searchTerm) {
        const term = this.searchTerm.toLowerCase();
        filtered = filtered.filter(account => 
          account.account_code.toLowerCase().includes(term) ||
          account.name.toLowerCase().includes(term)
        );
      }

      // Apply type filter
      if (this.selectedType) {
        filtered = filtered.filter(account => account.account_type === this.selectedType);
      }

      // Apply status filter
      if (this.selectedStatus) {
        const isActive = this.selectedStatus === 'active';
        filtered = filtered.filter(account => account.is_active === isActive);
      }

      this.filteredAccounts = filtered;
    },

    calculateStats() {
      this.stats = {
        assets: this.accounts.filter(a => a.account_type === 'Asset').length,
        liabilities: this.accounts.filter(a => a.account_type === 'Liability').length,
        equity: this.accounts.filter(a => a.account_type === 'Equity').length,
        revenue: this.accounts.filter(a => a.account_type === 'Revenue').length,
        expenses: this.accounts.filter(a => a.account_type === 'Expense').length
      };
    },

    toggleView() {
      this.viewMode = this.viewMode === 'hierarchy' ? 'list' : 'hierarchy';
    },

    showStructureViewer() {
      this.$router.push('/accounting/chart-of-accounts/structure');
    },

    viewAccount(accountId) {
      this.$router.push(`/accounting/chart-of-accounts/${accountId}`);
    },

    editAccount(accountId) {
      this.$router.push(`/accounting/chart-of-accounts/${accountId}/edit`);
    },

    deleteAccount(account) {
      this.accountToDelete = account;
      this.showDeleteModal = true;
    },

    closeDeleteModal() {
      this.showDeleteModal = false;
      this.accountToDelete = null;
      this.deleting = false;
    },

    async confirmDelete() {
      if (!this.accountToDelete) return;
      
      this.deleting = true;
      
      try {
        await axios.delete(`/accounting/chart-of-accounts/${this.accountToDelete.account_id}`);
        await this.loadAccounts();
        this.closeDeleteModal();
        this.$toast.success('Account deleted successfully');
      } catch (error) {
        console.error('Error deleting account:', error);
        const message = error.response?.data?.message || 'Failed to delete account';
        this.$toast.error(message);
      } finally {
        this.deleting = false;
      }
    }
  }
};
</script>

<style scoped>
.chart-accounts-page {
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

.title-section {
  flex: 1;
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

.btn-primary:hover {
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

.btn-info {
  background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
  color: white;
}

.btn-danger {
  background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
  color: white;
}

/* Filters Section */
.filters-section {
  background: var(--card-bg);
  border-radius: 16px;
  padding: 1.5rem;
  margin-bottom: 2rem;
  border: 1px solid var(--border-color);
}

.search-filters {
  display: flex;
  gap: 1.5rem;
  align-items: center;
  flex-wrap: wrap;
}

.search-box {
  position: relative;
  flex: 1;
  min-width: 300px;
}

.search-box i {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
}

.search-box input {
  width: 100%;
  padding: 0.875rem 1rem 0.875rem 3rem;
  border: 2px solid var(--border-color);
  border-radius: 12px;
  background: var(--bg-primary);
  color: var(--text-primary);
  font-size: 0.9rem;
  transition: all 0.3s ease;
}

.search-box input:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.filter-group {
  display: flex;
  gap: 1rem;
}

.filter-select {
  padding: 0.875rem 1rem;
  border: 2px solid var(--border-color);
  border-radius: 12px;
  background: var(--bg-primary);
  color: var(--text-primary);
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.3s ease;
}

.filter-select:focus {
  outline: none;
  border-color: #667eea;
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

/* Statistics Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: var(--card-bg);
  border-radius: 16px;
  padding: 1.5rem;
  border: 1px solid var(--border-color);
  display: flex;
  align-items: center;
  gap: 1rem;
  transition: all 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
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
}

.stat-icon.asset { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.stat-icon.liability { background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%); }
.stat-icon.equity { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.stat-icon.revenue { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
.stat-icon.expense { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
.stat-icon.total { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }

.stat-info h3 {
  font-size: 2rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}

.stat-info p {
  color: var(--text-muted);
  font-size: 0.9rem;
  margin: 0;
}

/* Hierarchy View */
.hierarchy-view {
  background: var(--card-bg);
  border-radius: 16px;
  border: 1px solid var(--border-color);
  overflow: hidden;
}

.tree-node {
  border-bottom: 1px solid var(--border-color);
}

.tree-node:last-child {
  border-bottom: none;
}

.node-content {
  display: flex;
  align-items: center;
  padding: 1rem;
  transition: all 0.3s ease;
  cursor: pointer;
}

.node-content:hover {
  background: rgba(102, 126, 234, 0.05);
}

.expand-toggle {
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  margin-right: 0.5rem;
  color: var(--text-muted);
}

.account-info {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex: 1;
}

.account-code {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.9rem;
  min-width: 100px;
  text-align: center;
}

.account-name {
  font-weight: 600;
  color: var(--text-primary);
  flex: 1;
}

.account-type {
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 500;
  text-transform: uppercase;
}

.account-type.asset { background: rgba(102, 126, 234, 0.1); color: #667eea; }
.account-type.liability { background: rgba(255, 65, 108, 0.1); color: #ff416c; }
.account-type.equity { background: rgba(79, 172, 254, 0.1); color: #4facfe; }
.account-type.revenue { background: rgba(67, 233, 123, 0.1); color: #43e97b; }
.account-type.expense { background: rgba(250, 112, 154, 0.1); color: #fa709a; }

.account-status {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.8rem;
  font-weight: 500;
}

.account-status.active { color: #43e97b; }
.account-status.inactive { color: #ff416c; }

.node-actions {
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
  transition: all 0.3s ease;
  background: transparent;
}

.action-btn.view {
  color: #4facfe;
  background: rgba(79, 172, 254, 0.1);
}

.action-btn.edit {
  color: #667eea;
  background: rgba(102, 126, 234, 0.1);
}

.action-btn.delete {
  color: #ff416c;
  background: rgba(255, 65, 108, 0.1);
}

.action-btn:hover {
  transform: scale(1.1);
}

.action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

/* List View */
.accounts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 1.5rem;
}

.account-card {
  background: var(--card-bg);
  border-radius: 16px;
  border: 1px solid var(--border-color);
  padding: 1.5rem;
  cursor: pointer;
  transition: all 0.3s ease;
}

.account-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
  border-color: #667eea;
}

.account-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.account-card .account-code {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.9rem;
}

.account-body {
  margin-bottom: 1rem;
}

.account-name {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 0.5rem 0;
}

.account-meta {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.parent-info {
  font-size: 0.8rem;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.account-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 4rem 2rem;
}

.empty-state i {
  font-size: 4rem;
  color: var(--text-muted);
  margin-bottom: 1rem;
}

.empty-state h3 {
  color: var(--text-primary);
  margin-bottom: 0.5rem;
}

.empty-state p {
  color: var(--text-muted);
  margin-bottom: 2rem;
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
}

.account-info {
  background: var(--bg-secondary);
  padding: 1rem;
  border-radius: 8px;
  margin: 1rem 0;
}

.warning-message {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #ff416c;
  font-weight: 500;
  margin-top: 1rem;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding: 1.5rem;
  border-top: 1px solid var(--border-color);
}

/* Responsive Design */
@media (max-width: 768px) {
  .chart-accounts-page {
    padding: 1rem;
  }

  .header-content {
    flex-direction: column;
    align-items: stretch;
  }

  .header-actions {
    justify-content: flex-start;
  }

  .search-filters {
    flex-direction: column;
    gap: 1rem;
  }

  .search-box {
    min-width: auto;
  }

  .filter-group {
    flex-wrap: wrap;
  }

  .stats-grid {
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  }

  .accounts-grid {
    grid-template-columns: 1fr;
  }

  .node-content {
    flex-wrap: wrap;
    gap: 0.5rem;
  }

  .account-info {
    flex-wrap: wrap;
  }
}
</style>