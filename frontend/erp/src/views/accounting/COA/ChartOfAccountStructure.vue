<!-- src/views/accounting/ChartOfAccountStructure.vue -->
<template>
  <div class="structure-viewer-page">
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
            <span class="breadcrumb-current">Account Structure</span>
          </div>
          <h1 class="page-title">
            <i class="fas fa-project-diagram"></i>
            Account Structure Viewer
          </h1>
          <p class="page-subtitle">Interactive visualization of your chart of accounts hierarchy and relationships</p>
        </div>
        <div class="header-actions">
          <button @click="refreshData" class="btn btn-secondary" :disabled="loading">
            <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
            Refresh
          </button>
          <button @click="toggleView" class="btn btn-outline">
            <i :class="viewMode === 'tree' ? 'fas fa-th-large' : 'fas fa-project-diagram'"></i>
            {{ viewMode === 'tree' ? 'Grid View' : 'Tree View' }}
          </button>
          <button @click="exportStructure" class="btn btn-info">
            <i class="fas fa-download"></i>
            Export
          </button>
          <router-link to="/accounting/chart-of-accounts/create" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Add Account
          </router-link>
        </div>
      </div>
    </div>

    <!-- Control Panel -->
    <div class="control-panel">
      <div class="control-group">
        <div class="filter-section">
          <h3>
            <i class="fas fa-filter"></i>
            Filters & Options
          </h3>
          <div class="filter-controls">
            <div class="filter-item">
              <label>Account Type</label>
              <select v-model="selectedType" @change="applyFilters" class="filter-select">
                <option value="">All Types</option>
                <option value="Asset">Assets</option>
                <option value="Liability">Liabilities</option>
                <option value="Equity">Equity</option>
                <option value="Revenue">Revenue</option>
                <option value="Expense">Expenses</option>
              </select>
            </div>
            <div class="filter-item">
              <label>Status</label>
              <select v-model="selectedStatus" @change="applyFilters" class="filter-select">
                <option value="">All Status</option>
                <option value="active">Active Only</option>
                <option value="inactive">Inactive Only</option>
              </select>
            </div>
            <div class="filter-item">
              <label>Depth Level</label>
              <select v-model="maxDepth" @change="applyFilters" class="filter-select">
                <option value="">All Levels</option>
                <option value="1">Level 1 Only</option>
                <option value="2">Up to Level 2</option>
                <option value="3">Up to Level 3</option>
                <option value="4">Up to Level 4</option>
              </select>
            </div>
          </div>
        </div>
        
        <div class="search-section">
          <h3>
            <i class="fas fa-search"></i>
            Search
          </h3>
          <div class="search-controls">
            <div class="search-box">
              <i class="fas fa-search"></i>
              <input 
                v-model="searchTerm" 
                type="text" 
                placeholder="Search by code or name..."
                @input="handleSearch"
              />
              <button v-if="searchTerm" @click="clearSearch" class="clear-search">
                <i class="fas fa-times"></i>
              </button>
            </div>
          </div>
        </div>

        <div class="view-options">
          <h3>
            <i class="fas fa-eye"></i>
            View Options
          </h3>
          <div class="option-toggles">
            <label class="toggle-option">
              <input v-model="showInactive" type="checkbox" @change="applyFilters">
              <span class="toggle-label">Show Inactive</span>
            </label>
            <label class="toggle-option">
              <input v-model="showCodes" type="checkbox">
              <span class="toggle-label">Show Codes</span>
            </label>
            <label class="toggle-option">
              <input v-model="showBalance" type="checkbox">
              <span class="toggle-label">Show Balances</span>
            </label>
            <label class="toggle-option">
              <input v-model="expandAll" type="checkbox" @change="toggleExpandAll">
              <span class="toggle-label">Expand All</span>
            </label>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-container">
      <div class="loading-spinner"></div>
      <p>Loading account structure...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-triangle"></i>
      <h3>Error Loading Structure</h3>
      <p>{{ error }}</p>
      <button @click="loadAccounts" class="btn btn-primary">Try Again</button>
    </div>

    <!-- Structure Content -->
    <div v-else class="structure-content">
      <!-- Statistics Banner -->
      <div class="stats-banner">
        <div class="stat-item">
          <div class="stat-icon total">
            <i class="fas fa-layer-group"></i>
          </div>
          <div class="stat-info">
            <h3>{{ filteredAccounts.length }}</h3>
            <p>Total Accounts</p>
          </div>
        </div>
        <div class="stat-item">
          <div class="stat-icon depth">
            <i class="fas fa-sort-amount-down"></i>
          </div>
          <div class="stat-info">
            <h3>{{ maxStructureDepth }}</h3>
            <p>Max Depth</p>
          </div>
        </div>
        <div class="stat-item">
          <div class="stat-icon parents">
            <i class="fas fa-folder"></i>
          </div>
          <div class="stat-info">
            <h3>{{ parentAccountsCount }}</h3>
            <p>Parent Accounts</p>
          </div>
        </div>
        <div class="stat-item">
          <div class="stat-icon leaves">
            <i class="fas fa-file"></i>
          </div>
          <div class="stat-info">
            <h3>{{ leafAccountsCount }}</h3>
            <p>Leaf Accounts</p>
          </div>
        </div>
      </div>

      <!-- Tree View -->
      <div v-if="viewMode === 'tree'" class="tree-view">
        <div class="structure-tree">
          <div v-for="type in accountTypes" :key="type" class="type-section">
            <div class="type-header" @click="toggleTypeExpansion(type)">
              <div class="type-toggle">
                <i :class="expandedTypes.has(type) ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"></i>
              </div>
              <div class="type-icon" :class="type.toLowerCase()">
                <i :class="getTypeIcon(type)"></i>
              </div>
              <div class="type-info">
                <h3>{{ type }}</h3>
                <span class="type-count">{{ getAccountsByType(type).length }} accounts</span>
              </div>
              <div class="type-badge" :class="type.toLowerCase()">
                {{ type }}
              </div>
            </div>
            
            <Transition name="expand">
              <div v-if="expandedTypes.has(type)" class="type-content">
                <div v-for="account in getAccountsByType(type)" :key="account.account_id" class="account-branch">
                  <StructureNode 
                    :account="account" 
                    :level="0"
                    :show-codes="showCodes"
                    :show-balance="showBalance"
                    :search-term="searchTerm"
                    :max-depth="maxDepth"
                    :expanded-state="expandedNodes"
                    @toggle-expand="toggleNodeExpansion"
                    @view-account="viewAccount"
                    @edit-account="editAccount"
                  />
                </div>
              </div>
            </Transition>
          </div>
        </div>
      </div>

      <!-- Grid View -->
      <div v-else class="grid-view">
        <div class="grid-container">
          <div v-for="type in accountTypes" :key="type" class="type-grid">
            <div class="grid-type-header">
              <div class="grid-type-icon" :class="type.toLowerCase()">
                <i :class="getTypeIcon(type)"></i>
              </div>
              <h3>{{ type }}</h3>
              <span class="grid-type-count">{{ getAccountsByType(type).length }}</span>
            </div>
            <div class="grid-accounts">
              <div 
                v-for="account in getAccountsByType(type)" 
                :key="account.account_id" 
                class="grid-account-card"
                :class="{ 
                  inactive: !account.is_active,
                  'has-children': hasChildren(account),
                  highlighted: isHighlighted(account)
                }"
                @click="viewAccount(account.account_id)"
              >
                <div class="grid-card-header">
                  <div class="grid-account-code" v-if="showCodes">{{ account.account_code }}</div>
                  <div class="grid-account-status">
                    <i :class="account.is_active ? 'fas fa-check-circle' : 'fas fa-times-circle'"></i>
                  </div>
                </div>
                <div class="grid-card-body">
                  <h4 class="grid-account-name">{{ account.name }}</h4>
                  <div class="grid-account-meta">
                    <span v-if="account.parent_account" class="grid-parent-info">
                      <i class="fas fa-level-up-alt"></i>
                      {{ account.parent_account.account_code }}
                    </span>
                    <span v-if="hasChildren(account)" class="grid-children-info">
                      <i class="fas fa-sitemap"></i>
                      {{ getChildrenCount(account) }} children
                    </span>
                  </div>
                  <div v-if="showBalance && account.balance !== undefined" class="grid-balance">
                    <span class="balance-label">Balance:</span>
                    <span class="balance-amount" :class="{ negative: account.balance < 0 }">
                      {{ formatCurrency(account.balance) }}
                    </span>
                  </div>
                </div>
                <div class="grid-card-actions">
                  <button @click.stop="viewAccount(account.account_id)" class="grid-action-btn view">
                    <i class="fas fa-eye"></i>
                  </button>
                  <button @click.stop="editAccount(account.account_id)" class="grid-action-btn edit">
                    <i class="fas fa-edit"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="filteredAccounts.length === 0 && !loading" class="empty-state">
        <i class="fas fa-search"></i>
        <h3>No Accounts Found</h3>
        <p v-if="searchTerm">No accounts match your search criteria.</p>
        <p v-else-if="selectedType || selectedStatus">No accounts match your filter criteria.</p>
        <p v-else>No accounts available in the chart of accounts.</p>
        <div class="empty-actions">
          <button v-if="hasFilters" @click="clearFilters" class="btn btn-outline">
            <i class="fas fa-filter"></i>
            Clear Filters
          </button>
          <router-link to="/accounting/chart-of-accounts/create" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Create Account
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

// Structure Node Component for Tree View
const StructureNode = {
  name: 'StructureNode',
  props: [
    'account', 
    'level', 
    'showCodes', 
    'showBalance', 
    'searchTerm', 
    'maxDepth', 
    'expandedState'
  ],
  emits: ['toggle-expand', 'view-account', 'edit-account'],
  computed: {
    hasChildren() {
      return this.account.child_accounts && this.account.child_accounts.length > 0;
    },
    isExpanded() {
      return this.expandedState.has(this.account.account_id);
    },
    shouldShowChildren() {
      return this.hasChildren && this.isExpanded && (!this.maxDepth || this.level < this.maxDepth - 1);
    },
    isHighlighted() {
      if (!this.searchTerm) return false;
      const term = this.searchTerm.toLowerCase();
      return (
        this.account.account_code.toLowerCase().includes(term) ||
        this.account.name.toLowerCase().includes(term)
      );
    },
    nodeStyle() {
      return {
        paddingLeft: `${this.level * 2 + 1}rem`,
        marginLeft: this.level > 0 ? '1rem' : '0',
        borderLeft: this.level > 0 ? '2px solid var(--border-color)' : 'none'
      };
    }
  },
  template: `
    <div class="structure-node" :class="{ highlighted: isHighlighted }">
      <div class="node-content" :style="nodeStyle">
        <div class="node-toggle" @click="$emit('toggle-expand', account.account_id)" v-if="hasChildren">
          <i :class="isExpanded ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"></i>
        </div>
        <div class="node-info" @click="$emit('view-account', account.account_id)">
          <div class="node-icon" :class="{ 'has-children': hasChildren, 'leaf': !hasChildren }">
            <i :class="hasChildren ? 'fas fa-folder' : 'fas fa-file-alt'"></i>
          </div>
          <div class="node-details">
            <div class="node-primary">
              <span v-if="showCodes" class="node-code">{{ account.account_code }}</span>
              <span class="node-name">{{ account.name }}</span>
            </div>
            <div class="node-secondary">
              <span class="node-type" :class="account.account_type.toLowerCase()">{{ account.account_type }}</span>
              <span class="node-status" :class="{ active: account.is_active, inactive: !account.is_active }">
                <i :class="account.is_active ? 'fas fa-check-circle' : 'fas fa-times-circle'"></i>
              </span>
            </div>
          </div>
          <div v-if="showBalance && account.balance !== undefined" class="node-balance">
            <span class="balance-amount" :class="{ negative: account.balance < 0 }">
              {{ formatCurrency(account.balance) }}
            </span>
          </div>
        </div>
        <div class="node-actions">
          <button @click.stop="$emit('view-account', account.account_id)" class="node-action-btn view">
            <i class="fas fa-eye"></i>
          </button>
          <button @click.stop="$emit('edit-account', account.account_id)" class="node-action-btn edit">
            <i class="fas fa-edit"></i>
          </button>
        </div>
      </div>
      <Transition name="expand">
        <div v-if="shouldShowChildren" class="node-children">
          <StructureNode 
            v-for="child in account.child_accounts" 
            :key="child.account_id"
            :account="child" 
            :level="level + 1"
            :show-codes="showCodes"
            :show-balance="showBalance"
            :search-term="searchTerm"
            :max-depth="maxDepth"
            :expanded-state="expandedState"
            @toggle-expand="$emit('toggle-expand', $event)"
            @view-account="$emit('view-account', $event)"
            @edit-account="$emit('edit-account', $event)"
          />
        </div>
      </Transition>
    </div>
  `,
  methods: {
    formatCurrency(amount) {
      if (amount === null || amount === undefined) return '-';
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount);
    }
  }
};

export default {
  name: 'ChartOfAccountStructure',
  components: {
    StructureNode
  },
  data() {
    return {
      loading: false,
      error: null,
      accounts: [],
      filteredAccounts: [],
      searchTerm: '',
      selectedType: '',
      selectedStatus: '',
      maxDepth: '',
      showInactive: true,
      showCodes: true,
      showBalance: false,
      expandAll: false,
      viewMode: 'tree', // 'tree' or 'grid'
      expandedTypes: new Set(['Asset', 'Liability', 'Equity', 'Revenue', 'Expense']),
      expandedNodes: new Set(),
      searchTimeout: null
    };
  },
  computed: {
    accountTypes() {
      const types = [...new Set(this.filteredAccounts.map(acc => acc.account_type))];
      return ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'].filter(type => types.includes(type));
    },
    maxStructureDepth() {
      let maxDepth = 0;
      const calculateDepth = (accounts, depth = 1) => {
        accounts.forEach(account => {
          maxDepth = Math.max(maxDepth, depth);
          if (account.child_accounts && account.child_accounts.length > 0) {
            calculateDepth(account.child_accounts, depth + 1);
          }
        });
      };
      calculateDepth(this.filteredAccounts.filter(acc => !acc.parent_account_id));
      return maxDepth;
    },
    parentAccountsCount() {
      return this.filteredAccounts.filter(acc => 
        acc.child_accounts && acc.child_accounts.length > 0
      ).length;
    },
    leafAccountsCount() {
      return this.filteredAccounts.filter(acc => 
        !acc.child_accounts || acc.child_accounts.length === 0
      ).length;
    },
    hasFilters() {
      return this.searchTerm || this.selectedType || this.selectedStatus || this.maxDepth;
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
        
        // Auto-expand first level if expand all is not set
        if (!this.expandAll) {
          this.accounts.forEach(account => {
            if (!account.parent_account_id && account.child_accounts && account.child_accounts.length > 0) {
              this.expandedNodes.add(account.account_id);
            }
          });
        }
      } catch (error) {
        console.error('Error loading accounts:', error);
        this.error = error.response?.data?.message || 'Failed to load account structure';
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
        filtered = this.filterBySearchTerm(filtered, term);
      }

      // Apply type filter
      if (this.selectedType) {
        filtered = this.filterByType(filtered, this.selectedType);
      }

      // Apply status filter
      if (this.selectedStatus === 'active') {
        filtered = filtered.filter(account => account.is_active);
      } else if (this.selectedStatus === 'inactive') {
        filtered = filtered.filter(account => !account.is_active);
      }

      // Apply inactive filter
      if (!this.showInactive) {
        filtered = filtered.filter(account => account.is_active);
      }

      this.filteredAccounts = filtered;
    },

    filterBySearchTerm(accounts, term) {
      const matchingAccounts = new Set();
      
      const addAccountAndAncestors = (account) => {
        matchingAccounts.add(account.account_id);
        // Add all children
        if (account.child_accounts) {
          account.child_accounts.forEach(child => addAccountAndAncestors(child));
        }
        // Add parent if exists
        if (account.parent_account_id) {
          const parent = accounts.find(acc => acc.account_id === account.parent_account_id);
          if (parent) {
            matchingAccounts.add(parent.account_id);
          }
        }
      };

      // Find directly matching accounts
      accounts.forEach(account => {
        if (
          account.account_code.toLowerCase().includes(term) ||
          account.name.toLowerCase().includes(term)
        ) {
          addAccountAndAncestors(account);
        }
      });

      return accounts.filter(account => matchingAccounts.has(account.account_id));
    },

    filterByType(accounts, type) {
      return accounts.filter(account => account.account_type === type);
    },

    clearSearch() {
      this.searchTerm = '';
      this.applyFilters();
    },

    clearFilters() {
      this.searchTerm = '';
      this.selectedType = '';
      this.selectedStatus = '';
      this.maxDepth = '';
      this.applyFilters();
    },

    toggleView() {
      this.viewMode = this.viewMode === 'tree' ? 'grid' : 'tree';
    },

    toggleTypeExpansion(type) {
      if (this.expandedTypes.has(type)) {
        this.expandedTypes.delete(type);
      } else {
        this.expandedTypes.add(type);
      }
    },

    toggleNodeExpansion(accountId) {
      if (this.expandedNodes.has(accountId)) {
        this.expandedNodes.delete(accountId);
      } else {
        this.expandedNodes.add(accountId);
      }
    },

    toggleExpandAll() {
      if (this.expandAll) {
        // Expand all nodes
        this.accounts.forEach(account => {
          this.addAllAccountIds(account, this.expandedNodes);
        });
      } else {
        // Collapse all nodes
        this.expandedNodes.clear();
      }
    },

    addAllAccountIds(account, set) {
      set.add(account.account_id);
      if (account.child_accounts) {
        account.child_accounts.forEach(child => {
          this.addAllAccountIds(child, set);
        });
      }
    },

    getAccountsByType(type) {
      return this.filteredAccounts
        .filter(account => account.account_type === type && !account.parent_account_id)
        .sort((a, b) => a.account_code.localeCompare(b.account_code));
    },

    getTypeIcon(type) {
      const icons = {
        Asset: 'fas fa-building',
        Liability: 'fas fa-credit-card',
        Equity: 'fas fa-balance-scale',
        Revenue: 'fas fa-chart-line',
        Expense: 'fas fa-receipt'
      };
      return icons[type] || 'fas fa-file-alt';
    },

    hasChildren(account) {
      return account.child_accounts && account.child_accounts.length > 0;
    },

    getChildrenCount(account) {
      return account.child_accounts ? account.child_accounts.length : 0;
    },

    isHighlighted(account) {
      if (!this.searchTerm) return false;
      const term = this.searchTerm.toLowerCase();
      return (
        account.account_code.toLowerCase().includes(term) ||
        account.name.toLowerCase().includes(term)
      );
    },

    viewAccount(accountId) {
      this.$router.push(`/accounting/chart-of-accounts/${accountId}`);
    },

    editAccount(accountId) {
      this.$router.push(`/accounting/chart-of-accounts/${accountId}/edit`);
    },

    exportStructure() {
      // Implementation for exporting structure
      const structureData = {
        accounts: this.filteredAccounts,
        exportDate: new Date().toISOString(),
        filters: {
          type: this.selectedType,
          status: this.selectedStatus,
          search: this.searchTerm
        }
      };
      
      const blob = new Blob([JSON.stringify(structureData, null, 2)], {
        type: 'application/json'
      });
      
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `chart-of-accounts-structure-${new Date().toISOString().split('T')[0]}.json`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
      
      this.$toast.success('Structure exported successfully');
    },

    formatCurrency(amount) {
      if (amount === null || amount === undefined) return '-';
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
      }).format(amount);
    }
  }
};
</script>

<style scoped>
.structure-viewer-page {
  padding: 2rem;
  max-width: 1600px;
  margin: 0 auto;
}

/* Page Header - Reuse from previous components */
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

.btn-info {
  background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
  color: white;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

/* Control Panel */
.control-panel {
  background: var(--card-bg);
  border-radius: 16px;
  border: 1px solid var(--border-color);
  padding: 2rem;
  margin-bottom: 2rem;
}

.control-group {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 2rem;
}

.control-group h3 {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 1rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.control-group h3 i {
  color: #667eea;
}

.filter-controls {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.filter-item {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.filter-item label {
  font-weight: 500;
  color: var(--text-secondary);
  font-size: 0.9rem;
}

.filter-select {
  padding: 0.75rem 1rem;
  border: 2px solid var(--border-color);
  border-radius: 8px;
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

.search-controls {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.search-box {
  position: relative;
  display: flex;
  align-items: center;
}

.search-box i {
  position: absolute;
  left: 1rem;
  color: var(--text-muted);
  z-index: 2;
}

.search-box input {
  width: 100%;
  padding: 0.75rem 1rem 0.75rem 3rem;
  border: 2px solid var(--border-color);
  border-radius: 8px;
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

.clear-search {
  position: absolute;
  right: 0.5rem;
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 4px;
  transition: all 0.3s ease;
}

.clear-search:hover {
  color: var(--text-primary);
  background: var(--bg-secondary);
}

.option-toggles {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.toggle-option {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-size: 0.9rem;
}

.toggle-option input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: #667eea;
  cursor: pointer;
}

.toggle-label {
  color: var(--text-primary);
  font-weight: 500;
  cursor: pointer;
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

/* Statistics Banner */
.stats-banner {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-item {
  background: var(--card-bg);
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid var(--border-color);
  display: flex;
  align-items: center;
  gap: 1rem;
  transition: all 0.3s ease;
}

.stat-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.stat-icon {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.25rem;
}

.stat-icon.total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.stat-icon.depth { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.stat-icon.parents { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
.stat-icon.leaves { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }

.stat-info h3 {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}

.stat-info p {
  color: var(--text-muted);
  font-size: 0.9rem;
  margin: 0;
}

/* Tree View */
.tree-view {
  background: var(--card-bg);
  border-radius: 16px;
  border: 1px solid var(--border-color);
  overflow: hidden;
}

.type-section {
  border-bottom: 1px solid var(--border-color);
}

.type-section:last-child {
  border-bottom: none;
}

.type-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.5rem;
  cursor: pointer;
  transition: all 0.3s ease;
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.02) 0%, rgba(118, 75, 162, 0.02) 100%);
}

.type-header:hover {
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
}

.type-toggle {
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
  transition: all 0.3s ease;
}

.type-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.25rem;
}

.type-icon.asset { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.type-icon.liability { background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%); }
.type-icon.equity { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.type-icon.revenue { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
.type-icon.expense { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }

.type-info {
  flex: 1;
}

.type-info h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 0.25rem 0;
}

.type-count {
  color: var(--text-muted);
  font-size: 0.9rem;
}

.type-badge {
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
}

.type-badge.asset { background: rgba(102, 126, 234, 0.1); color: #667eea; }
.type-badge.liability { background: rgba(255, 65, 108, 0.1); color: #ff416c; }
.type-badge.equity { background: rgba(79, 172, 254, 0.1); color: #4facfe; }
.type-badge.revenue { background: rgba(67, 233, 123, 0.1); color: #43e97b; }
.type-badge.expense { background: rgba(250, 112, 154, 0.1); color: #fa709a; }

.type-content {
  background: var(--bg-secondary);
}

/* Structure Node */
.structure-node {
  border-bottom: 1px solid var(--border-color);
  transition: all 0.3s ease;
}

.structure-node:last-child {
  border-bottom: none;
}

.structure-node.highlighted {
  background: rgba(255, 193, 7, 0.1);
  border-left: 4px solid #ffc107;
}

.node-content {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem;
  transition: all 0.3s ease;
  min-height: 60px;
}

.node-content:hover {
  background: rgba(102, 126, 234, 0.05);
}

.node-toggle {
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--text-muted);
  border-radius: 4px;
  transition: all 0.3s ease;
}

.node-toggle:hover {
  background: rgba(102, 126, 234, 0.1);
  color: #667eea;
}

.node-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex: 1;
  cursor: pointer;
}

.node-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.node-icon.has-children {
  background: rgba(102, 126, 234, 0.1);
  color: #667eea;
}

.node-icon.leaf {
  background: rgba(67, 233, 123, 0.1);
  color: #43e97b;
}

.node-details {
  flex: 1;
  min-width: 0;
}

.node-primary {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.25rem;
}

.node-code {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 600;
  font-family: 'Courier New', monospace;
  flex-shrink: 0;
}

.node-name {
  font-weight: 600;
  color: var(--text-primary);
  font-size: 0.95rem;
}

.node-secondary {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.node-type {
  padding: 0.125rem 0.5rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
}

.node-type.asset { background: rgba(102, 126, 234, 0.1); color: #667eea; }
.node-type.liability { background: rgba(255, 65, 108, 0.1); color: #ff416c; }
.node-type.equity { background: rgba(79, 172, 254, 0.1); color: #4facfe; }
.node-type.revenue { background: rgba(67, 233, 123, 0.1); color: #43e97b; }
.node-type.expense { background: rgba(250, 112, 154, 0.1); color: #fa709a; }

.node-status {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.8rem;
}

.node-status.active { color: #43e97b; }
.node-status.inactive { color: #ff416c; }

.node-balance {
  text-align: right;
  min-width: 120px;
}

.balance-amount {
  font-family: 'Courier New', monospace;
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--text-primary);
}

.balance-amount.negative {
  color: #ff416c;
}

.node-actions {
  display: flex;
  gap: 0.5rem;
}

.node-action-btn {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
  background: transparent;
  color: var(--text-muted);
}

.node-action-btn:hover {
  transform: scale(1.1);
}

.node-action-btn.view:hover {
  background: rgba(79, 172, 254, 0.1);
  color: #4facfe;
}

.node-action-btn.edit:hover {
  background: rgba(102, 126, 234, 0.1);
  color: #667eea;
}

/* Grid View */
.grid-view {
  background: var(--card-bg);
  border-radius: 16px;
  border: 1px solid var(--border-color);
  padding: 2rem;
}

.grid-container {
  display: flex;
  flex-direction: column;
  gap: 3rem;
}

.type-grid {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.grid-type-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem 1.5rem;
  background: var(--bg-secondary);
  border-radius: 12px;
  border: 1px solid var(--border-color);
}

.grid-type-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.25rem;
}

.grid-type-icon.asset { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.grid-type-icon.liability { background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%); }
.grid-type-icon.equity { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.grid-type-icon.revenue { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
.grid-type-icon.expense { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }

.grid-type-header h3 {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
  flex: 1;
}

.grid-type-count {
  background: rgba(102, 126, 234, 0.1);
  color: #667eea;
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.9rem;
  font-weight: 600;
}

.grid-accounts {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 1.5rem;
}

.grid-account-card {
  background: var(--card-bg);
  border: 2px solid var(--border-color);
  border-radius: 12px;
  padding: 1.5rem;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
}

.grid-account-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
  border-color: #667eea;
}

.grid-account-card.inactive {
  opacity: 0.7;
}

.grid-account-card.has-children::before {
  content: '';
  position: absolute;
  top: 10px;
  left: 10px;
  width: 8px;
  height: 8px;
  background: #667eea;
  border-radius: 50%;
}

.grid-account-card.highlighted {
  border-color: #ffc107;
  background: rgba(255, 193, 7, 0.05);
}

.grid-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.grid-account-code {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.9rem;
  font-family: 'Courier New', monospace;
}

.grid-account-status {
  font-size: 1.25rem;
}

.grid-account-status .fa-check-circle { color: #43e97b; }
.grid-account-status .fa-times-circle { color: #ff416c; }

.grid-card-body {
  margin-bottom: 1rem;
}

.grid-account-name {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 0.75rem 0;
  line-height: 1.3;
}

.grid-account-meta {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  font-size: 0.85rem;
  color: var(--text-muted);
}

.grid-parent-info, .grid-children-info {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.grid-balance {
  margin-top: 0.75rem;
  padding-top: 0.75rem;
  border-top: 1px solid var(--border-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.balance-label {
  font-size: 0.85rem;
  color: var(--text-muted);
  font-weight: 500;
}

.balance-amount {
  font-family: 'Courier New', monospace;
  font-weight: 600;
  font-size: 0.9rem;
  color: #43e97b;
}

.balance-amount.negative {
  color: #ff416c;
}

.grid-card-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

.grid-action-btn {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
  background: transparent;
  color: var(--text-muted);
}

.grid-action-btn:hover {
  transform: scale(1.1);
}

.grid-action-btn.view:hover {
  background: rgba(79, 172, 254, 0.1);
  color: #4facfe;
}

.grid-action-btn.edit:hover {
  background: rgba(102, 126, 234, 0.1);
  color: #667eea;
}

/* Empty State */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  text-align: center;
  background: var(--card-bg);
  border-radius: 16px;
  border: 1px solid var(--border-color);
}

.empty-state i {
  font-size: 4rem;
  color: var(--text-muted);
  margin-bottom: 1rem;
  opacity: 0.5;
}

.empty-state h3 {
  color: var(--text-primary);
  margin-bottom: 0.5rem;
}

.empty-state p {
  color: var(--text-muted);
  margin-bottom: 2rem;
}

.empty-actions {
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
}

/* Transitions */
.expand-enter-active, .expand-leave-active {
  transition: all 0.3s ease;
  overflow: hidden;
}

.expand-enter-from, .expand-leave-to {
  opacity: 0;
  max-height: 0;
}

.expand-enter-to, .expand-leave-from {
  opacity: 1;
  max-height: 1000px;
}

/* Responsive Design */
@media (max-width: 1200px) {
  .control-group {
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
  }

  .view-options {
    grid-column: 1 / -1;
  }

  .grid-accounts {
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  }
}

@media (max-width: 768px) {
  .structure-viewer-page {
    padding: 1rem;
  }

  .header-content {
    flex-direction: column;
    align-items: stretch;
  }

  .header-actions {
    justify-content: flex-start;
  }

  .control-group {
    grid-template-columns: 1fr;
    gap: 1.5rem;
  }

  .control-panel {
    padding: 1.5rem;
  }

  .stats-banner {
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  }

  .node-content {
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 1rem 0.75rem;
  }

  .node-primary {
    flex-wrap: wrap;
  }

  .node-balance {
    min-width: auto;
    order: 1;
    width: 100%;
    text-align: left;
    margin-top: 0.5rem;
  }

  .grid-accounts {
    grid-template-columns: 1fr;
  }

  .grid-account-meta {
    font-size: 0.8rem;
  }

  .empty-actions {
    flex-direction: column;
    align-items: center;
  }
}
</style>