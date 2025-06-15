<!-- src/views/accounting/ChartOfAccountForm.vue -->
<template>
  <div class="account-form-page">
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
            <span class="breadcrumb-current">{{ isEdit ? 'Edit Account' : 'Create Account' }}</span>
          </div>
          <h1 class="page-title">
            <i :class="isEdit ? 'fas fa-edit' : 'fas fa-plus'"></i>
            {{ isEdit ? 'Edit Account' : 'Create New Account' }}
          </h1>
          <p class="page-subtitle">
            {{ isEdit ? 'Update account information and settings' : 'Add a new account to your chart of accounts' }}
          </p>
        </div>
        <div class="header-actions">
          <router-link to="/accounting/chart-of-accounts" class="btn btn-secondary">
            <i class="fas fa-times"></i>
            Cancel
          </router-link>
          <button @click="saveAccount" class="btn btn-primary" :disabled="!isFormValid || saving">
            <i v-if="saving" class="fas fa-spinner fa-spin"></i>
            <i v-else :class="isEdit ? 'fas fa-save' : 'fas fa-plus'"></i>
            {{ isEdit ? 'Update Account' : 'Create Account' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-container">
      <div class="loading-spinner"></div>
      <p>{{ isEdit ? 'Loading account details...' : 'Loading form data...' }}</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-triangle"></i>
      <h3>Error Loading Form</h3>
      <p>{{ error }}</p>
      <button @click="loadFormData" class="btn btn-primary">Try Again</button>
    </div>

    <!-- Form Content -->
    <div v-else class="form-content">
      <div class="form-layout">
        <!-- Main Form -->
        <div class="main-form">
          <form @submit.prevent="saveAccount" class="account-form">
            <!-- Basic Information Section -->
            <div class="form-section">
              <div class="section-header">
                <h2>
                  <i class="fas fa-info-circle"></i>
                  Basic Information
                </h2>
                <p>Enter the basic details for this account</p>
              </div>

              <div class="form-grid">
                <div class="form-group">
                  <label for="account_code" class="form-label required">
                    Account Code
                  </label>
                  <input
                    id="account_code"
                    v-model="form.account_code"
                    type="text"
                    class="form-input"
                    :class="{ 'error': errors.account_code }"
                    placeholder="e.g., 1001, 2001, 3001"
                    @blur="validateField('account_code')"
                  />
                  <div v-if="errors.account_code" class="field-error">
                    {{ errors.account_code }}
                  </div>
                  <div class="field-hint">
                    Unique code to identify this account
                  </div>
                </div>

                <div class="form-group">
                  <label for="name" class="form-label required">
                    Account Name
                  </label>
                  <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="form-input"
                    :class="{ 'error': errors.name }"
                    placeholder="e.g., Cash in Bank, Accounts Receivable"
                    @blur="validateField('name')"
                  />
                  <div v-if="errors.name" class="field-error">
                    {{ errors.name }}
                  </div>
                  <div class="field-hint">
                    Descriptive name for this account
                  </div>
                </div>
              </div>
            </div>

            <!-- Classification Section -->
            <div class="form-section">
              <div class="section-header">
                <h2>
                  <i class="fas fa-tags"></i>
                  Account Classification
                </h2>
                <p>Define the account type and hierarchy</p>
              </div>

              <div class="form-grid">
                <div class="form-group">
                  <label for="account_type" class="form-label required">
                    Account Type
                  </label>
                  <select
                    id="account_type"
                    v-model="form.account_type"
                    class="form-select"
                    :class="{ 'error': errors.account_type }"
                    @change="validateField('account_type')"
                  >
                    <option value="">Select Account Type</option>
                    <option value="Asset">Asset</option>
                    <option value="Liability">Liability</option>
                    <option value="Equity">Equity</option>
                    <option value="Revenue">Revenue</option>
                    <option value="Expense">Expense</option>
                  </select>
                  <div v-if="errors.account_type" class="field-error">
                    {{ errors.account_type }}
                  </div>
                  <div class="field-hint">
                    The primary classification of this account
                  </div>
                </div>

                <div class="form-group">
                  <label for="parent_account_id" class="form-label">
                    Parent Account
                  </label>
                  <select
                    id="parent_account_id"
                    v-model="form.parent_account_id"
                    class="form-select"
                    :class="{ 'error': errors.parent_account_id }"
                    @change="validateField('parent_account_id')"
                  >
                    <option value="">No Parent (Top Level)</option>
                    <optgroup 
                      v-for="group in groupedParentAccounts" 
                      :key="group.type" 
                      :label="group.type"
                    >
                      <option 
                        v-for="account in group.accounts" 
                        :key="account.account_id" 
                        :value="account.account_id"
                        :disabled="account.account_id == accountId"
                      >
                        {{ account.account_code }} - {{ account.name }}
                      </option>
                    </optgroup>
                  </select>
                  <div v-if="errors.parent_account_id" class="field-error">
                    {{ errors.parent_account_id }}
                  </div>
                  <div class="field-hint">
                    Optional: Choose a parent account for hierarchy
                  </div>
                </div>
              </div>
            </div>

            <!-- Status Section -->
            <div class="form-section">
              <div class="section-header">
                <h2>
                  <i class="fas fa-toggle-on"></i>
                  Account Status
                </h2>
                <p>Configure account activation and settings</p>
              </div>

              <div class="form-group">
                <div class="toggle-group">
                  <label class="toggle-label">
                    <input
                      v-model="form.is_active"
                      type="checkbox"
                      class="toggle-input"
                    />
                    <span class="toggle-slider"></span>
                    <span class="toggle-text">
                      <strong>{{ form.is_active ? 'Active' : 'Inactive' }}</strong>
                      <span class="toggle-description">
                        {{ form.is_active ? 'Account is active and can be used in transactions' : 'Account is inactive and cannot be used' }}
                      </span>
                    </span>
                  </label>
                </div>
              </div>
            </div>

            <!-- Submit Section -->
            <div class="form-actions">
              <router-link to="/accounting/chart-of-accounts" class="btn btn-secondary">
                <i class="fas fa-times"></i>
                Cancel
              </router-link>
              <button type="submit" class="btn btn-primary" :disabled="!isFormValid || saving">
                <i v-if="saving" class="fas fa-spinner fa-spin"></i>
                <i v-else :class="isEdit ? 'fas fa-save' : 'fas fa-plus'"></i>
                {{ isEdit ? 'Update Account' : 'Create Account' }}
              </button>
            </div>
          </form>
        </div>

        <!-- Sidebar -->
        <div class="form-sidebar">
          <!-- Preview Card -->
          <div class="preview-card">
            <h3>
              <i class="fas fa-eye"></i>
              Account Preview
            </h3>
            <div class="preview-content">
              <div class="preview-row">
                <span class="preview-label">Code:</span>
                <span class="preview-value">{{ form.account_code || 'Not set' }}</span>
              </div>
              <div class="preview-row">
                <span class="preview-label">Name:</span>
                <span class="preview-value">{{ form.name || 'Not set' }}</span>
              </div>
              <div class="preview-row">
                <span class="preview-label">Type:</span>
                <span class="preview-value" :class="form.account_type ? form.account_type.toLowerCase() : ''">
                  {{ form.account_type || 'Not selected' }}
                </span>
              </div>
              <div class="preview-row">
                <span class="preview-label">Parent:</span>
                <span class="preview-value">{{ parentAccountName || 'None (Top Level)' }}</span>
              </div>
              <div class="preview-row">
                <span class="preview-label">Status:</span>
                <span class="preview-value" :class="{ 'active': form.is_active, 'inactive': !form.is_active }">
                  <i :class="form.is_active ? 'fas fa-check-circle' : 'fas fa-times-circle'"></i>
                  {{ form.is_active ? 'Active' : 'Inactive' }}
                </span>
              </div>
            </div>
          </div>

          <!-- Hierarchy Visualization -->
          <div v-if="form.parent_account_id || (isEdit && originalAccount)" class="hierarchy-card">
            <h3>
              <i class="fas fa-sitemap"></i>
              Account Hierarchy
            </h3>
            <div class="hierarchy-tree">
              <div v-if="parentAccountName" class="hierarchy-node parent">
                <i class="fas fa-folder"></i>
                {{ parentAccountName }}
              </div>
              <div class="hierarchy-node current">
                <i class="fas fa-file"></i>
                {{ form.name || 'Current Account' }}
                <span v-if="form.account_code" class="node-code">({{ form.account_code }})</span>
              </div>
              <div v-if="childAccounts.length > 0" class="child-accounts">
                <div v-for="child in childAccounts" :key="child.account_id" class="hierarchy-node child">
                  <i class="fas fa-file"></i>
                  {{ child.name }}
                  <span class="node-code">({{ child.account_code }})</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Help Card -->
          <div class="help-card">
            <h3>
              <i class="fas fa-question-circle"></i>
              Help & Guidelines
            </h3>
            <div class="help-content">
              <div class="help-section">
                <h4>Account Code Guidelines:</h4>
                <ul>
                  <li>Assets: 1000-1999</li>
                  <li>Liabilities: 2000-2999</li>
                  <li>Equity: 3000-3999</li>
                  <li>Revenue: 4000-4999</li>
                  <li>Expenses: 5000-9999</li>
                </ul>
              </div>
              <div class="help-section">
                <h4>Best Practices:</h4>
                <ul>
                  <li>Use clear, descriptive names</li>
                  <li>Follow consistent numbering</li>
                  <li>Group related accounts together</li>
                  <li>Keep hierarchy simple</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'ChartOfAccountForm',
  props: {
    id: {
      type: [String, Number],
      default: null
    }
  },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      accounts: [],
      originalAccount: null,
      childAccounts: [],
      form: {
        account_code: '',
        name: '',
        account_type: '',
        is_active: true,
        parent_account_id: ''
      },
      errors: {},
      touchedFields: new Set()
    };
  },
  computed: {
    isEdit() {
      return !!this.id;
    },
    accountId() {
      return this.id;
    },
    isFormValid() {
      return (
        this.form.account_code &&
        this.form.name &&
        this.form.account_type &&
        Object.keys(this.errors).length === 0
      );
    },
    groupedParentAccounts() {
      const groups = {};
      this.accounts.forEach(account => {
        if (!groups[account.account_type]) {
          groups[account.account_type] = [];
        }
        groups[account.account_type].push(account);
      });
      
      return Object.keys(groups).map(type => ({
        type,
        accounts: groups[type].sort((a, b) => a.account_code.localeCompare(b.account_code))
      }));
    },
    parentAccountName() {
      if (!this.form.parent_account_id) return null;
      const parent = this.accounts.find(acc => acc.account_id == this.form.parent_account_id);
      return parent ? `${parent.account_code} - ${parent.name}` : null;
    }
  },
  mounted() {
    this.loadFormData();
  },
  watch: {
    'form.account_code'() {
      if (this.touchedFields.has('account_code')) {
        this.validateField('account_code');
      }
    },
    'form.name'() {
      if (this.touchedFields.has('name')) {
        this.validateField('name');
      }
    },
    'form.account_type'() {
      if (this.touchedFields.has('account_type')) {
        this.validateField('account_type');
      }
      // Clear parent account if type changes
      if (this.form.parent_account_id) {
        const parent = this.accounts.find(acc => acc.account_id == this.form.parent_account_id);
        if (parent && parent.account_type !== this.form.account_type) {
          this.form.parent_account_id = '';
        }
      }
    }
  },
  methods: {
    async loadFormData() {
      this.loading = true;
      this.error = null;
      
      try {
        // Load all accounts for parent selection
        const accountsResponse = await axios.get('/accounting/chart-of-accounts');
        this.accounts = accountsResponse.data.data || [];

        // If editing, load the specific account
        if (this.isEdit) {
          const accountResponse = await axios.get(`/accounting/chart-of-accounts/${this.id}`);
          const account = accountResponse.data.data;
          
          this.originalAccount = { ...account };
          this.form = {
            account_code: account.account_code,
            name: account.name,
            account_type: account.account_type,
            is_active: account.is_active,
            parent_account_id: account.parent_account_id || ''
          };
          
          this.childAccounts = account.child_accounts || [];
        }
      } catch (error) {
        console.error('Error loading form data:', error);
        this.error = error.response?.data?.message || 'Failed to load form data';
      } finally {
        this.loading = false;
      }
    },

    validateField(fieldName) {
      this.touchedFields.add(fieldName);
      this.errors = { ...this.errors };
      delete this.errors[fieldName];

      switch (fieldName) {
        case 'account_code':
          if (!this.form.account_code) {
            this.errors.account_code = 'Account code is required';
          } else if (!/^[A-Z0-9]+$/.test(this.form.account_code)) {
            this.errors.account_code = 'Account code should contain only letters and numbers';
          } else {
            // Check for uniqueness
            const existing = this.accounts.find(acc => 
              acc.account_code === this.form.account_code && 
              acc.account_id != this.accountId
            );
            if (existing) {
              this.errors.account_code = 'Account code already exists';
            }
          }
          break;

        case 'name':
          if (!this.form.name) {
            this.errors.name = 'Account name is required';
          } else if (this.form.name.length < 3) {
            this.errors.name = 'Account name must be at least 3 characters';
          }
          break;

        case 'account_type':
          if (!this.form.account_type) {
            this.errors.account_type = 'Account type is required';
          }
          break;

        case 'parent_account_id':
          if (this.form.parent_account_id) {
            if (this.form.parent_account_id == this.accountId) {
              this.errors.parent_account_id = 'Account cannot be its own parent';
            } else {
              const parent = this.accounts.find(acc => acc.account_id == this.form.parent_account_id);
              if (parent && parent.account_type !== this.form.account_type) {
                this.errors.parent_account_id = 'Parent account must be of the same type';
              }
            }
          }
          break;
      }
    },

    validateForm() {
      this.validateField('account_code');
      this.validateField('name');
      this.validateField('account_type');
      if (this.form.parent_account_id) {
        this.validateField('parent_account_id');
      }
    },

    async saveAccount() {
      this.validateForm();
      
      if (!this.isFormValid) {
        this.$toast.error('Please fix the errors before saving');
        return;
      }

      this.saving = true;
      
      try {
        const data = {
          ...this.form,
          parent_account_id: this.form.parent_account_id || null
        };

        if (this.isEdit) {
          await axios.put(`/accounting/chart-of-accounts/${this.id}`, data);
          this.$toast.success('Account updated successfully');
        } else {
          await axios.post('/accounting/chart-of-accounts', data);
          this.$toast.success('Account created successfully');
        }

        this.$router.push('/accounting/chart-of-accounts');
      } catch (error) {
        console.error('Error saving account:', error);
        
        if (error.response?.data?.errors) {
          this.errors = error.response.data.errors;
        } else {
          const message = error.response?.data?.message || 'Failed to save account';
          this.$toast.error(message);
        }
      } finally {
        this.saving = false;
      }
    }
  }
};
</script>

<style scoped>
.account-form-page {
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

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
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

/* Form Layout */
.form-layout {
  display: grid;
  grid-template-columns: 1fr 350px;
  gap: 2rem;
}

.main-form {
  min-width: 0;
}

.account-form {
  background: var(--card-bg);
  border-radius: 16px;
  border: 1px solid var(--border-color);
  overflow: hidden;
}

/* Form Sections */
.form-section {
  padding: 2rem;
  border-bottom: 1px solid var(--border-color);
}

.form-section:last-child {
  border-bottom: none;
}

.section-header {
  margin-bottom: 2rem;
}

.section-header h2 {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 0.5rem 0;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.section-header h2 i {
  color: #667eea;
}

.section-header p {
  color: var(--text-muted);
  margin: 0;
  font-size: 0.95rem;
}

/* Form Grid */
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}

/* Form Groups */
.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-label {
  font-weight: 600;
  color: var(--text-primary);
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.form-label.required::after {
  content: '*';
  color: #ff416c;
  font-weight: bold;
}

.form-input, .form-select {
  padding: 0.875rem 1rem;
  border: 2px solid var(--border-color);
  border-radius: 12px;
  background: var(--bg-primary);
  color: var(--text-primary);
  font-size: 0.9rem;
  transition: all 0.3s ease;
}

.form-input:focus, .form-select:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-input.error, .form-select.error {
  border-color: #ff416c;
  box-shadow: 0 0 0 3px rgba(255, 65, 108, 0.1);
}

.field-error {
  color: #ff416c;
  font-size: 0.8rem;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.field-hint {
  color: var(--text-muted);
  font-size: 0.8rem;
}

/* Toggle */
.toggle-group {
  background: var(--bg-secondary);
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid var(--border-color);
}

.toggle-label {
  display: flex;
  align-items: center;
  gap: 1rem;
  cursor: pointer;
}

.toggle-input {
  display: none;
}

.toggle-slider {
  width: 60px;
  height: 30px;
  background: #ccc;
  border-radius: 30px;
  position: relative;
  transition: all 0.3s ease;
  flex-shrink: 0;
}

.toggle-slider::after {
  content: '';
  width: 26px;
  height: 26px;
  background: white;
  border-radius: 50%;
  position: absolute;
  top: 2px;
  left: 2px;
  transition: all 0.3s ease;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

.toggle-input:checked + .toggle-slider {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.toggle-input:checked + .toggle-slider::after {
  transform: translateX(30px);
}

.toggle-text {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.toggle-description {
  color: var(--text-muted);
  font-size: 0.85rem;
  font-weight: normal;
}

/* Form Actions */
.form-actions {
  padding: 2rem;
  background: var(--bg-secondary);
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
}

/* Sidebar */
.form-sidebar {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.preview-card, .hierarchy-card, .help-card {
  background: var(--card-bg);
  border-radius: 16px;
  border: 1px solid var(--border-color);
  padding: 1.5rem;
}

.preview-card h3, .hierarchy-card h3, .help-card h3 {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 1rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.preview-card h3 i { color: #4facfe; }
.hierarchy-card h3 i { color: #667eea; }
.help-card h3 i { color: #43e97b; }

/* Preview */
.preview-content {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.preview-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--border-color);
}

.preview-row:last-child {
  border-bottom: none;
}

.preview-label {
  font-weight: 500;
  color: var(--text-secondary);
  font-size: 0.85rem;
}

.preview-value {
  font-weight: 600;
  color: var(--text-primary);
  font-size: 0.85rem;
  text-align: right;
}

.preview-value.asset { color: #667eea; }
.preview-value.liability { color: #ff416c; }
.preview-value.equity { color: #4facfe; }
.preview-value.revenue { color: #43e97b; }
.preview-value.expense { color: #fa709a; }

.preview-value.active { color: #43e97b; }
.preview-value.inactive { color: #ff416c; }

/* Hierarchy */
.hierarchy-tree {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.hierarchy-node {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem;
  border-radius: 8px;
  font-size: 0.85rem;
  transition: all 0.3s ease;
}

.hierarchy-node.parent {
  background: rgba(102, 126, 234, 0.1);
  color: #667eea;
  margin-left: 0;
}

.hierarchy-node.current {
  background: rgba(79, 172, 254, 0.1);
  color: #4facfe;
  margin-left: 1rem;
  font-weight: 600;
}

.hierarchy-node.child {
  background: rgba(67, 233, 123, 0.1);
  color: #43e97b;
  margin-left: 2rem;
  font-size: 0.8rem;
}

.node-code {
  font-weight: normal;
  opacity: 0.8;
  font-size: 0.8em;
}

.child-accounts {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

/* Help */
.help-content {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.help-section h4 {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 0.5rem 0;
}

.help-section ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

.help-section li {
  font-size: 0.8rem;
  color: var(--text-secondary);
  padding: 0.25rem 0;
  padding-left: 1rem;
  position: relative;
}

.help-section li::before {
  content: '•';
  position: absolute;
  left: 0;
  color: #667eea;
  font-weight: bold;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .form-layout {
    grid-template-columns: 1fr;
    gap: 1.5rem;
  }

  .form-sidebar {
    order: -1;
  }
}

@media (max-width: 768px) {
  .account-form-page {
    padding: 1rem;
  }

  .header-content {
    flex-direction: column;
    align-items: stretch;
  }

  .header-actions {
    justify-content: flex-start;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .form-section {
    padding: 1.5rem;
  }

  .form-actions {
    padding: 1.5rem;
    flex-direction: column;
  }

  .toggle-label {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.75rem;
  }
}
</style>